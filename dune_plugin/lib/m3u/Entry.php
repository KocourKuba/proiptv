<?php
require_once 'lib/json_serializer.php';
require_once 'ExtTagDefault.php';
require_once 'M3U_Helpers.php';
require_once 'M3U_Helpers.php';

class Entry extends Json_Serializer
{
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
     * @return ExtTag
     */
    public function getEntryTag($tag)
    {
        return isset($this->tags[$tag]) ? $this->tags[$tag] : null;
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
        $this->is_header = ($parsed_tag->isTag(TAG_EXTM3U));
        return $parsed_tag;
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
                safe_merge_array($attributes, $item->getAttributes());
            }
        } else if ($this->hasTag($tag)) {
            $attributes = $this->getEntryTag($tag);
        }

        return $attributes;
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
     * Get attribute for selected tag
     * If tag not specified search attribute in all tags
     *
     * @param string $attribute_name
     * @param string|null $tag
     * @return string
     */
    public function getEntryAttribute($attribute_name, $tag = null)
    {
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

    /**
     * @param array $attrs
     * @param null $tag
     * @param null $found_attr
     * @return string
     */
    public function getAnyEntryAttribute($attrs, $tag = null, &$found_attr = null)
    {
        foreach ($attrs as $attr) {
            $val = $this->getEntryAttribute($attr, $tag);
            if (empty($val)) continue;

            if ($found_attr !== null) {
                $found_attr = $attr;
            }
            return $val;
        }

        return '';
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

    //////////////////////////////////////////////////////////////////////////////
    /// Specialized methods to get specific data
    ///

    /**
     * Returns EXTINF Title
     * #EXTINF:0 group-title="Базовые Россия", 1 channel
     * example result: 1 channel
     *
     * @return string
     */
    public function getEntryTitle()
    {
        $extInf = $this->getEntryTag(TAG_EXTINF);
        return is_null($extInf) ? null : $extInf->getTagValue();
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
            $this->group_title = $this->getEntryAttribute('group-title', TAG_EXTINF);
            if (empty($this->group_title)) {
                $exgGrp = $this->getEntryTag(TAG_EXTGRP);
                $this->group_title = is_null($exgGrp) ? null : $exgGrp->getTagValue();
                if (empty($this->group_title)) {
                    $this->group_title = TR::load_string('no_category');
                }
            }
        }

        return $this->group_title;
    }

    /**
     * Returns entry id. Need for identify channel if possible
     *
     * @return string
     */
    public function getEntryId()
    {
        /*
         * Tags used to get entry ID
		 * "CUID", "channel-id", "tvg-chno", "tvg-name", "name",
         */
        static $tags = array("CUID", "channel-id", "ch-id", "tvg-chno", "ch-number");

        $ch_id = $this->getAnyEntryAttribute($tags);

        return empty($ch_id) ? hash('crc32', $this->getPath()) : $ch_id;
    }

    /**
     * Get icon for channel
     *
     * @return string
     */
    public function getEntryIcon()
    {
        /*
         * attributes contains picon information
		 * "tvg-logo", "url-logo"
         */
        static $tags = array("tvg-logo", "url-logo");

        return $this->getAnyEntryAttribute($tags);
    }

    /**
     * Get catchup type for channel
     *
     * @return string
     */
    public function getCatchup()
    {
        /*
         * attributes contains catchup information
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
         * attributes contains xmltv epg sources
		 * "catchup", "catchup-type"
         */
        static $attrs = array("url-tvg", "x-tvg-url");

        return $this->getAllEntryAttributes($attrs, TAG_EXTM3U);
    }

    /**
     * Get protected parameter for channel
     *
     * @return string
     */
    public function getProtectedCode() {
        static $adult_attrs = array("adult", "parent-code", "censored");

        $adult_code = $this->getAnyEntryAttribute($adult_attrs, TAG_EXTINF, $used_tag);
        if ($used_tag === "adult" && (int)$adult_code !== 1) {
            $adult_code = '';
        }

        return $adult_code;
    }
}
