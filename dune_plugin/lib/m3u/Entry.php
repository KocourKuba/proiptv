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
    /**
     * Attributes used to get archive length
     * "catchup-days", "catchup-time", "timeshift", "arc-timeshift", "arc-time", "tvg-rec"
     * "catchup-time" contains seconds instead of days in other cases
     */
    public static $tvg_archives_attrs = array(ATTR_CATCHUP_DAYS, ATTR_CATCHUP_TIME, ATTR_TIMESHIFT, ATTR_ARC_TIMESHIFT, ATTR_ARC_TIME, ATTR_TVG_REC);

    /**
     * Attributes used to determine channel ID
     * "CUID", "channel-id", "ch-id", "tvg-chno", "ch-number",
     */
    public static $channel_id_attrs = array(ATTR_CUID, ATTR_CHANNEL_ID, ATTR_CH_ID, ATTR_TVG_CHNO, ATTR_CH_NUMBER);

    /**
     * Attributes contains xmltv epg sources
     * "url-tvg", "x-tvg-url"
     */
    public static $epg_src_attrs = array(ATTR_URL_TVG, ATTR_X_TVG_URL);

    /*
     * Attributes contains catchup information
     * "catchup", "catchup-type"
     */
    public static $catchup_attrs = array(ATTR_CATCHUP, ATTR_CATCHUP_TYPE);

    /*
     * Attributes contains catchup source information
     * "catchup-source", "catchup-template"
     */
    public static $catchup_source_attrs = array(ATTR_CATCHUP_SOURCE, ATTR_CATCHUP_TEMPLATE);

    /*
     * Attributes contains icon links
     * "tvg-logo", "url-logo"
     */
    public static $icon_attrs = array(ATTR_TVG_LOGO, ATTR_URL_LOGO);

    /*
     * Attributes contains epg id
     * "adult", "parent-code", "censored"
     */
    public static $adult_attrs = array(ATTR_ADULT, ATTR_PARENT_CODE, ATTR_CENSORED);

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
    protected $hash;

    /**
     * @var string
     */
    protected $parsed_id;

    /**
     * @var string
     */
    protected $cuid;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var int
     */
    protected $timeshift;

    /**
     * @var string
     */
    protected $icon;

    /**
     * @var array
     */
    protected $ext_params;

    /**
     * @var string
     */
    protected $group_title;

    /**
     * @var string
     */
    protected $group_logo;

    /**
     * @var int
     */
    protected $adult = 0;

    /**
     * @var string
     */
    protected $parent_code;

    /**
     * @var int
     */
    protected $archive;

    /**
     * @var string
     */
    protected $catchup_type;

    /**
     * @var string
     */
    protected $catchup_source;

    /**
     * @return bool
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
            $entry_tag = $this->getEntryTag($tag);
            $attributes = empty($entry_tag) ? array() : $entry_tag->getAttributes();
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
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Update path and calculate hash
     *
     * @param string $hash
     * @return void
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Update path and calculate hash
     *
     * @param string $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getParsedId()
    {
        return $this->parsed_id;
    }

    /**
     * @return string
     */
    public function getCUID()
    {
        return $this->cuid;
    }

    /**
     * @param string $id_parser
     * @return void
     */
    public function updateParsedId($id_parser)
    {
        // set channel id, first use id url parser
        /** @var array $m */
        if (!empty($id_parser) && preg_match($id_parser, $this->path, $m) && isset($m['id'])) {
            $this->parsed_id = $m['id'];
        }
    }

    /**
     * @return void
     */
    public function updateCUID()
    {
        $this->cuid = $this->getEntryAttribute(ATTR_CHANNEL_ID_ATTRS);
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return void
     */
    public function updateTitle()
    {
        $extInf = $this->getEntryTag(TAG_EXTINF);
        $this->title = is_null($extInf) ? 'no name' : $extInf->getTagValue();
    }

    /**
     * @return int
     */
    public function getTimeshift()
    {
        return $this->timeshift;
    }

    /**
     * @return void
     */
    public function updateTimeshift()
    {
        $timeshift = $this->getEntryAttribute(ATTR_TVG_SHIFT, TAG_EXTINF);
        $this->timeshift = ($timeshift !== null) ? (int)$timeshift : 0;
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @param string $icon_base_url
     * @param array $icon_replace_pattern
     * @return void
     */
    public function updateIcon($icon_base_url, $icon_replace_pattern = array())
    {
        // set channel icon
        // make full url for icon if used base url
        $channel_icon = $this->getAnyEntryAttribute(self::$icon_attrs, TAG_EXTINF);
        if (!empty($channel_icon) && !is_proto_http($channel_icon)) {
            if (empty($icon_base_url)) {
                $channel_icon = 'http://' . $channel_icon;
            } else {
                $channel_icon = $icon_base_url . $channel_icon;
            }
        }

        // Apply replacement pattern
        if (!empty($channel_icon) && !empty($icon_replace_pattern)) {
            foreach ($icon_replace_pattern as $pattern) {
                $channel_icon = preg_replace($pattern['search'], $pattern['replace'], $channel_icon);
            }
        }

        $this->icon = $channel_icon;
    }

    /**
     * @return array|string|null
     */
    public function getExtParams($encoded = false)
    {
        return $encoded ? (empty($this->ext_params) ? null : json_encode($this->ext_params)) : $this->ext_params;
    }

    /**
     * @return void
     */
    public function updateExtParams()
    {
        // Update extended attributes for channel
        $ext_params = array();
        $ext_tag = $this->getEntryTag(TAG_EXTVLCOPT);
        if ($ext_tag !== null) {
            $ext_params[PARAM_EXT_VLC_OPTS] = $ext_tag->getTagValues();
        }

        $ext_tag = $this->getEntryTag(TAG_EXTHTTP);
        if ($ext_tag !== null && ($ext_http_values = json_decode($ext_tag->getTagValue(), true)) !== false) {
            $ext_params[PARAM_EXT_HTTP] = $ext_http_values;
        }

        $this->ext_params = $ext_params;
    }

    /**
     * @return string
     */
    public function getGroupLogo()
    {
        return $this->group_logo;
    }

    /**
     * @param string $icon_base_url
     * @return void
     */
    public function updateGroupLogo($icon_base_url)
    {
        // set group logo
        $group_icon = $this->getEntryAttribute(ATTR_GROUP_LOGO, TAG_EXTINF);
        if (!empty($group_logo)) {
            if (!empty($icon_base_url) && !is_proto_http($group_logo)) {
                $group_icon = $icon_base_url . $group_logo;
            }
        }

        $this->group_logo = $group_icon;
    }

    /**
     * @return string
     */
    public function getGroupTitle()
    {
        return $this->group_title;
    }

    /**
     * @return void
     */
    public function updateGroupTitle()
    {
        // set group title
        $group_title = $this->getEntryAttribute(ATTR_GROUP_TITLE, TAG_EXTINF);
        if (empty($group_title)) {
            $exgGrp = $this->getEntryTag(TAG_EXTGRP);
            if (!is_null($exgGrp)) {
                $group_title = $exgGrp->getTagValue();
            }
            if (empty($group_title)) {
                $group_title = TR::load('no_category');
            }
        }
        $this->group_title = $group_title;
    }

    /**
     * @return int
     */
    public function getAdult()
    {
        return $this->adult;
    }

    /**
     * @return string
     */
    public function getParentCode()
    {
        return $this->parent_code;
    }

    /**
     * @return void
     */
    public function updateParentCode()
    {
        $used_tag = '';
        $parent_code = $this->getAnyEntryAttribute(self::$adult_attrs, TAG_EXTINF, $used_tag);
        if ($used_tag === ATTR_ADULT && (int)$parent_code !== 1) {
            $parent_code = '0000';
        }

        if (!empty($parent_code)) {
            $this->adult = 1;
        }

        $this->parent_code = $parent_code;
    }

    /**
     * Get xmltv epg sources for playlist
     * this attribute is header specific
     *
     * @return array
     */
    public function getEpgSources()
    {
        return $this->getAllEntryAttributes(self::$epg_src_attrs, TAG_EXTM3U);
    }

    /**
     * Update channel archive in days
     *
     * @param string $tag
     * @param int $playlist_value
     */
    public function updateArchive($tag, $playlist_value = 0)
    {
        // set channel archive in days
        $archive = $this->getAnyEntryAttribute(self::$tvg_archives_attrs, $tag, $used_tag);
        if ($archive === null) {
            $archive = 0;
        }

        if ($used_tag === ATTR_CATCHUP_TIME) {
            $archive /= 86400;
        }

        if ($archive === 0 && $playlist_value !== 0) {
            $archive = $playlist_value;
        }

        $this->archive = $archive;
    }

    /**
     * Get archive in days
     * this attribute is header or channel
     *
     * @return int
     */
    public function getArchive()
    {
        return $this->archive;
    }

    /**
     * Update catchup type for selected tag
     *
     * @param string $tag
     * @return void
     */
    public function updateCatchupType($tag)
    {
        $this->catchup_type = $this->getAnyEntryAttribute(self::$catchup_attrs, $tag);
    }

    /**
     * Get Catchup type
     *
     * @return string
     */
    public function getCatchupType()
    {
        return $this->catchup_type;
    }

    /**
     * Update catchup source for selected tag
     *
     * @param string $tag
     * @return void
     */
    public function updateCatchupSource($tag)
    {
        $this->catchup_source = $this->getAnyEntryAttribute(self::$catchup_source_attrs, $tag);
    }

    /**
     * Get Catchup source
     *
     * @return string
     */
    public function getCatchupSource()
    {
        return $this->catchup_source;
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
        switch ($attribute_name) {
            case ATTR_CHANNEL_NAME:
                $extInf = $this->getEntryTag(TAG_EXTINF);
                return is_null($extInf) ? '' : $extInf->getTagValue();

            case ATTR_CHANNEL_ID_ATTRS:
                return $this->getAnyEntryAttribute(self::$channel_id_attrs);

            case ATTR_CHANNEL_HASH:
                return $this->getHash();

            default:
                if (is_null($this->tags)) break;

                if (is_null($tag)) {
                    foreach ($this->tags as $item) {
                        $val = $item->getAttributeValue($attribute_name);
                        if (!is_null($val)) {
                            return $val;
                        }
                    }
                    break;
                }

                if ($this->hasTag($tag)) {
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
