<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

require_once 'lib/json_serializer.php';
require_once 'ExtTagDefault.php';

class Entry extends Json_Serializer
{
    const TAG_EXTM3U = '#EXTM3U';
    const TAG_EXTINF = '#EXTINF';
    const TAG_EXTGRP = '#EXTGRP';
    const TAG_EXTHTTP = '#EXTHTTP';
    const TAG_EXTVLCOPT = '#EXTVLCOPT';

    const ATTR_CHANNEL_NAME = 'name';
    const ATTR_CHANNEL_ID = 'channel_id_attributes';
    const ATTR_CHANNEL_HASH = 'by_default';

    /**
     * @var bool
     */
    protected $is_header = false;

    /**
     * @var ExtTag[]|null
     */
    protected $tags;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $group_title;

    /**
     * @return boolean
     */
    public function isM3U_Header()
    {
        return $this->is_header;
    }

    /**
     * @param string $line
     * @param bool $full
     * @return ExtTag
     */
    public function parseExtTag($line, $full)
    {
        $tag = new ExtTagDefault();
        $parsed_tag = $full ? $tag->parseFullData($line) : $tag->parseData($line);
        if (is_null($parsed_tag)) {
            return null;
        }

        $this->addTag($parsed_tag);
        $this->is_header = ($parsed_tag->isTag(self::TAG_EXTM3U));
        return $parsed_tag;
    }

    /**
     * @param ExtTag $tag
     */
    public function addTag($tag)
    {
        if (isset($this->tags[$tag->getTagName()])) {
            $this->tags[$tag->getTagName()]->addTagValue($tag->getTagValue());
        } else {
            $this->tags[$tag->getTagName()] = $tag;
        }
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Get attributes for selected tag
     * If tag not specified returns attributes from all tags
     *
     * @param string $tag
     * @return array
     */
    public function getEntryAttributes($tag = null)
    {
        $attributes = array();
        if (!is_null($this->tags) && is_null($tag)) {
            foreach ($this->tags as $item) {
                $attributes = safe_merge_array($attributes, $item->getAttributes());
            }
        } else if ($this->hasTag($tag)) {
            $attributes = $this->getEntryTag($tag);
        }

        return $attributes;
    }

    /**
     * @param string $tag_name
     * @return bool
     */
    public function hasTag($tag_name)
    {
        return isset($this->tags[$tag_name]);
    }

    /**
     * @return ExtTag
     */
    public function getEntryTag($tag)
    {
        return isset($this->tags[$tag]) ? $this->tags[$tag] : null;
    }

    /**
     * @param string $attribute_name
     * @param string $tag
     * @return bool
     */
    public function hasEntryAttribute($attribute_name, $tag = null)
    {
        if (!is_null($this->tags)) {
            if (is_null($tag)) {
                foreach ($this->tags as $item) {
                    $val = $item->hasAttributeValue($attribute_name);
                    if ($val) {
                        return true;
                    }
                }
            } else if ($this->hasTag($tag)) {
                return $this->tags[$tag]->hasAttributeValue($attribute_name);
            }
        }
        return false;
    }

    /**
     * Returns EXTINF Title
     * #EXTINF:0 group-title="Базовые Россия", 1 channel
     * example result: 1 channel
     *
     * @return string
     */
    public function getEntryTitle()
    {
        $extInf = $this->getEntryTag(self::TAG_EXTINF);
        return is_null($extInf) ? '' : $extInf->getTagValue();
    }

    /**
     * Returns calculated category title
     * value of attribute 'group-title' or #EXTGRP value or string 'no_category' if not found
     *
     * @return string
     */
    public function getGroupTitle()
    {
        if (is_null($this->group_title)) {
            $this->group_title = $this->getEntryAttribute('group-title', self::TAG_EXTINF);
            if (empty($this->group_title)) {
                $exgGrp = $this->getEntryTag(self::TAG_EXTGRP);
                $this->group_title = is_null($exgGrp) ? null : $exgGrp->getTagValue();
                if (empty($this->group_title)) {
                    $this->group_title = TR::load_string('no_category');
                }
            }
        }

        return $this->group_title;
    }

    /**
     * Get attribute for selected tag
     * If tag not specified search attribute in all tags
     *
     * @param string $attribute_name
     * @param string|null $tag
     * @return string
     */
    public function getEntryAttribute($attribute_name, $tag = null)
    {
        if ($attribute_name === self::ATTR_CHANNEL_NAME) {
            return $this->getEntryTitle();
        }

        if ($attribute_name === self::ATTR_CHANNEL_ID) {
            return $this->getEntryId();
        }

        if ($attribute_name === self::ATTR_CHANNEL_HASH) {
            return hash('crc32', $this->getPath());
        }

        if (!is_null($this->tags)) {
            if (is_null($tag)) {
                foreach ($this->tags as $item) {
                    $val = $item->getAttributeValue($attribute_name);
                    if (!is_null($val)) {
                        return $val;
                    }
                }
            } else if ($this->hasTag($tag)) {
                return $this->tags[$tag]->getAttributeValue($attribute_name);
            }
        }

        return null;
    }

    //////////////////////////////////////////////////////////////////////////////
    /// Specialized methods to get specific data
    ///

    /**
     * Returns entry id. Need for identify channel if possible
     *
     * @return string
     */
    public function getEntryId()
    {
        /*
         * Attributes used to get entry ID
		 * "CUID", "channel-id", "ch-id", "tvg-chno", "ch-number",
         */
        static $attrs = array("CUID", "channel-id", "ch-id", "tvg-chno", "ch-number");

        return $this->getAnyEntryAttribute($attrs);
    }

    /**
     * @param string|array $attrs
     * @param null $tag
     * @param null $found_attr
     * @return string
     */
    public function getAnyEntryAttribute($attrs, $tag = null, &$found_attr = null)
    {
        if (!is_array($attrs)) {
            return $this->getEntryAttribute($attrs, $tag);
        }

        $val = '';
        foreach ($attrs as $attr) {
            $val = $this->getEntryAttribute($attr, $tag);
            if (empty($val)) continue;

            if ($found_attr !== null) {
                $found_attr = $attr;
            }
            break;
        }
        return $val;
    }

    /**
     * Get icon for channel
     *
     * @return string
     */
    public function getEntryIcon()
    {
        /*
         * Attributes contains picon information
		 * "tvg-logo", "url-logo"
         */
        static $attrs = array("tvg-logo", "url-logo");

        return $this->getAnyEntryAttribute($attrs);
    }

    /**
     * Get catchup type for channel
     *
     * @return string
     */
    public function getCatchup()
    {
        /*
         * Attributes contains catchup information
		 * "catchup", "catchup-type"
         */
        static $attrs = array("catchup", "catchup-type");

        return $this->getAnyEntryAttribute($attrs);
    }

    /**
     * Get catchup-source for channel
     *
     * @return string
     */
    public function getCatchupSource()
    {
        /*
         * attributes contains catchup information
		 * "catchup", "catchup-type"
         */
        static $attrs = array("catchup-source", "catchup-template");

        return $this->getAnyEntryAttribute($attrs);
    }

    /**
     * Get xmltv epg sources for playlist
     *
     * @return array
     */
    public function getEpgSources()
    {
        /*
         * Attributes contains xmltv epg sources
		 * "catchup", "catchup-type"
         */
        static $attrs = array("url-tvg", "x-tvg-url");

        return $this->getAllEntryAttributes($attrs, self::TAG_EXTM3U);
    }

    /**
     * @param array $attrs
     * @param null $tag
     * @return array
     */
    public function getAllEntryAttributes($attrs, $tag = null)
    {
        $ret = array();
        foreach ($attrs as $attr) {
            $val = $this->getEntryAttribute($attr, $tag);
            if (empty($val)) continue;

            $ret[$attr] = $val;
        }

        return $ret;
    }

    /**
     * Get protected parameter for channel
     *
     * @return string
     */
    public function getProtectedCode()
    {
        static $adult_attrs = array("adult", "parent-code", "censored");

        $adult_code = $this->getAnyEntryAttribute($adult_attrs, self::TAG_EXTINF, $used_tag);
        if ($used_tag === "adult" && (int)$adult_code !== 1) {
            $adult_code = '';
        }

        return $adult_code;
    }
}
