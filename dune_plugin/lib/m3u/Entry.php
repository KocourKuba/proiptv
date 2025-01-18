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
require_once 'M3uTags.php';

class Entry extends Json_Serializer
{
    public static $tvg_archives = array(ATTR_CATCHUP_DAYS, ATTR_CATCHUP_TIME, ATTR_TIMESHIFT, ATTR_ARC_TIMESHIFT, ATTR_ARC_TIME, ATTR_TVG_REC);
    public static $epg_id_tags = array(ATTR_TVG_ID, ATTR_TVG_NAME, ATTR_TVG_EPGID, ATTR_CHANNEL_NAME);
    public static $icon_attrs = array(ATTR_TVG_LOGO, ATTR_URL_LOGO);

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
    protected $channel_id;

    /**
     * @var string
     */
    protected $channel_icon;

    /**
     * @var string
     */
    protected $group_title;

    /**
     * @var string
     */
    protected $group_icon;

    /**
     * @var int
     */
    protected $archive;

    /**
     * @var array
     */
    protected $epg_ids = array();

    /**
     * @var array
     */
    protected $matches = array();

    /**
     * @var array
     */
    protected $ext_params = array();

    /**
     * @return boolean
     */
    public function isM3U_Header()
    {
        return $this->is_header;
    }

    /**
     * @param string $line
     * @return ExtTag
     */
    public function parseExtTag($line)
    {
        $tag = new ExtTagDefault();
        $parsed_tag = $tag->parseData($line);
        if (is_null($parsed_tag)) {
            return null;
        }

        $this->addTag($parsed_tag);
        $this->is_header = ($parsed_tag->isTag(TAG_EXTM3U) || $parsed_tag->isTag(TAG_PLAYLIST));
        return $parsed_tag;
    }

    /**
     * @param Entry $entry
     */
    public function mergeEntry($entry)
    {
        foreach ($entry->tags as $value) {
            $this->addTag($value);
        }
    }

    /**
     * @param ExtTag $tag
     */
    public function addTag($tag)
    {
        if (isset($this->tags[$tag->getTagName()])) {
            $this->tags[$tag->getTagName()]->addAttributes($tag->getAttributes());
        } else {
            $this->tags[$tag->getTagName()] = $tag;
        }
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
        return $this->hasTag($tag) ? $this->tags[$tag] : null;
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
     * @return string
     */
    public function getChannelId()
    {
        return $this->channel_id;
    }

    /**
     * @return array
     */
    public function getMatches()
    {
        return $this->matches;
    }

    /**
     * Returns EXTINF Title (1 channel)
     * #EXTINF:0 group-title="base channels", 1 channel
     * example result: 1 channel
     *
     * @return string
     */
    public function getEntryTitle()
    {
        $extInf = $this->getEntryTag(TAG_EXTINF);
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
        return $this->group_title;
    }

    /**
     * Returns category icon
     *
     * @return string
     */
    public function getGroupIcon()
    {
        return $this->group_icon;
    }

    /**
     * Set category icon
     *
     * @param string $icon_base_url
     */
    public function updateGroupIcon($icon_base_url)
    {
        $this->group_icon = $this->getEntryAttribute(ATTR_GROUP_LOGO);
        if (!empty($group_logo) && !empty($icon_base_url) && !is_http($group_logo)) {
            $this->group_icon = $icon_base_url . $group_logo;
        }
    }

    /**
     * Get TimeShift for channel
     *
     * @return int
     */
    public function getTimeShift()
    {
        return (int)$this->getEntryAttribute(ATTR_TVG_SHIFT, TAG_EXTINF);
    }

    /**
     * Get archive in days
     *
     * @return int
     */
    public function getArchive()
    {
        return $this->archive;
    }

    /**
     * Get EPG IDs
     *
     * @return array
     */
    public function getEpgIds()
    {
        return $this->epg_ids;
    }

    /**
     * Set icon for channel
     *
     * @param string $icon_base_url
     * @param array $icon_replace_pattern
     */
    public function updateChannelIcon($icon_base_url, $icon_replace_pattern)
    {
        // make full url for icon if used base url
        $this->channel_icon = $this->getAnyEntryAttribute(self::$icon_attrs);
        if (!empty($icon_base_url) && !is_http($this->channel_icon)) {
            $this->channel_icon = $icon_base_url . $this->channel_icon;
        }

        // Apply replacement pattern
        if (!empty($icon_replace_pattern)) {
            foreach ($icon_replace_pattern as $pattern) {
                $this->channel_icon = preg_replace($pattern['search'], $pattern['replace'], $this->channel_icon);
            }
        }
    }

    /**
     * Get icon for channel
     *
     * @return string
     */
    public function getChannelIcon()
    {
        return $this->channel_icon;
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
        static $attrs = array(ATTR_CATCHUP, ATTR_CATCHUP_TYPE);

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
		 * "catchup-source", "catchup-template"
         */
        static $attrs = array(ATTR_CATCHUP_SOURCE, ATTR_CATCHUP_TEMPLATE);

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
		 * "url-tvg", "x-tvg-url"
         */
        static $attrs = array(ATTR_URL_TVG, ATTR_X_TVG_URL);

        return $this->getAllEntryAttributes($attrs, TAG_EXTM3U);
    }

    /**
     * Get protected parameter for channel
     *
     * @return string
     */
    public function getProtectedCode()
    {
        static $adult_attrs = array(ATTR_ADULT, ATTR_PARENT_CODE, ATTR_CENSORED);

        $adult_code = $this->getAnyEntryAttribute($adult_attrs, TAG_EXTINF, $used_tag);
        if ($used_tag === ATTR_ADULT && (int)$adult_code !== 1) {
            $adult_code = '';
        }

        return $adult_code;
    }

    /**
     * Calculate channel id
     *
     * @param string $id_parser
     * @param string $id_map
     */
    public function updateChannelId($id_parser, $id_map)
    {
        // first use id url parser
        if (!empty($id_parser) && preg_match($id_parser, $this->path, $matches)) {
            $this->matches = $matches;
        }

        if (isset($matches['id'])) {
            $channel_id = $matches['id'];
        }

        // try to get by id mapper
        if (empty($channel_id) && !empty($id_map)) {
            $channel_id = $this->getEntryAttribute($id_map);
        }

        // search in attributes
        if (empty($channel_id)) {
            $channel_id = $this->getEntryAttribute(ATTR_CHANNEL_ID_ATTRS);
        }

        // Nothing - using url hash as channel id
        if (empty($channel_id)) {
            $channel_id = Hashed_Array::hash($this->path);
        }

        $this->channel_id = $channel_id;
    }

    /**
     * Calculate category title value of attribute 'group-title' or #EXTGRP value
     *
     * @return void
     */
    public function updateGroupTitle()
    {
        $this->group_title = $this->getEntryAttribute(ATTR_GROUP_TITLE, TAG_EXTINF);
        if (empty($this->group_title)) {
            $exgGrp = $this->getEntryTag(TAG_EXTGRP);
            if (!is_null($exgGrp)) {
                $this->group_title = $exgGrp->getTagValue();
            }
            if (empty($this->group_title)) {
                $this->group_title = TR::load_string('no_category');
            }
        }
    }

    /**
     * Update channel archive in days
     *
     * @return void
     */
    public function updateArchiveLength()
    {
        // set channel archive in days
        $archive = (int)$this->getAnyEntryAttribute(self::$tvg_archives, TAG_EXTINF, $used_tag);
        if ($used_tag === ATTR_CATCHUP_TIME) {
            $archive /= 86400;
        }

        $this->archive = $archive;
    }

    /**
     * Update EPG IDs
     *
     * @return void
     */
    public function updateEpgIds()
    {
        // set channel EPG IDs
        $epg_ids = $this->getAllEntryAttributes(self::$epg_id_tags);
        $epg_ids['id'] = $this->channel_id;
        $this->epg_ids = array_unique($epg_ids);
    }

    /**
     * Returns extended attributes for channel
     *
     * @return array
     */
    public function getExtParams()
    {
        return $this->ext_params;
    }

    /**
     * Update extended attributes for channel
     *
     * @return void
     */
    public function updateExtParams()
    {
        $ext_tag = $this->getEntryTag(TAG_EXTVLCOPT);
        if ($ext_tag !== null) {
            $this->ext_params[PARAM_EXT_VLC_OPTS] = $ext_tag->getTagValues();
        }

        $ext_tag = $this->getEntryTag(TAG_EXTHTTP);
        if ($ext_tag !== null && ($ext_http_values = json_decode($ext_tag->getTagValue(), true)) !== false) {
            $this->ext_params[PARAM_EXT_HTTP] = $ext_http_values;
        }
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
        if ($attribute_name === ATTR_CHANNEL_NAME) {
            return $this->getEntryTitle();
        }

        if ($attribute_name === ATTR_CHANNEL_ID_ATTRS) {
            // Attributes used to get entry ID
            // "CUID", "channel-id", "ch-id", "tvg-chno", "ch-number",
            static $attrs = array(ATTR_CUID, ATTR_CHANNEL_ID, ATTR_CH_ID, ATTR_TVG_CHNO, ATTR_CH_NUMBER);
            return $this->getAnyEntryAttribute($attrs);
        }

        if ($attribute_name === ATTR_CHANNEL_HASH) {
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
}
