<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
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

require_once 'group.php';
require_once 'json_serializer.php';

class Default_Group extends Json_Serializer implements Group
{
    const DEFAULT_GROUP_ICON = 'plugin_file://icons/default_group.png';

    const ALL_CHANNEL_GROUP_CAPTION = 'plugin_all_channels';
    const ALL_CHANNEL_GROUP_ICON = 'plugin_file://icons/all_folder.png';

    const FAV_CHANNEL_GROUP_CAPTION = 'plugin_favorites';
    const FAV_CHANNEL_GROUP_ICON = 'plugin_file://icons/favorite_folder.png';

    const HISTORY_GROUP_CAPTION = 'plugin_history';
    const HISTORY_GROUP_ICON = 'plugin_file://icons/history_folder.png';

    const CHANGED_CHANNELS_GROUP_CAPTION = 'plugin_changed';
    const CHANGED_CHANNELS_GROUP_ICON = 'plugin_file://icons/changed_channels.png';

    const FAV_MOVIES_GROUP_CAPTION = 'plugin_favorites';
    const FAV_MOVIES_GROUP_ICON = 'plugin_file://icons/favorite_vod_folder.png';

    const VOD_GROUP_CAPTION = 'plugin_vod';
    const VOD_GROUP_ICON = "plugin_file://icons/vod_folder.png";

    const SEARCH_MOVIES_GROUP_CAPTION = 'search';
    const SEARCH_MOVIES_GROUP_ICON = 'plugin_file://icons/search_movie_folder.png';

    const FILTER_MOVIES_GROUP_CAPTION = 'filters';
    const FILTER_MOVIES_GROUP_ICON = 'plugin_file://icons/filter_movie_folder.png';

    const HISTORY_MOVIES_GROUP_CAPTION = 'plugin_history';
    const HISTORY_MOVIES_GROUP_ICON = 'plugin_file://icons/history_vod_folder.png';

    /**
     * @var string
     */
    protected $_id;

    /**
     * @var string
     */
    protected $_title;

    /**
     * @var string
     */
    protected $_icon_url;

    /**
     * @var boolean
     */
    protected $_adult;

    /**
     * @var boolean
     */
    protected $_disabled;

    /**
     * @var Hashed_Array
     */
    protected $_channels;

    /**
     * @var bool
     */
    protected $_order_support;

    /**
     * @var Default_Dune_Plugin
     */
    private $plugin;

    public function __sleep()
    {
        $vars = get_object_vars($this);
        unset($vars['plugin']);
        return array_keys($vars);
    }

    /**
     * @param $plugin
     * @param string $id
     * @param string $title
     * @param string|null $icon_url
     * @param bool $order_support
     */
    public function __construct($plugin, $id, $title, $icon_url = null, $order_support = true)
    {
        $this->plugin = $plugin;

        if (is_null($title)) {
            $title = $id;
        }

        $this->_id = $id;
        $this->_title = $title;
        $this->_icon_url = $icon_url;
        $this->_adult = false;
        $this->_disabled = false;
        $this->_order_support = $order_support;

        $this->_channels = new Hashed_Array();
    }

    /**
     * @inheritDoc
     */
    public function get_id()
    {
        return $this->_id;
    }

    /**
     * @inheritDoc
     */
    public function get_title()
    {
        return $this->_title;
    }

    /**
     * @inheritDoc
     */
    public function get_icon_url()
    {
        return empty($this->_icon_url) ? self::DEFAULT_GROUP_ICON : $this->_icon_url;
    }

    /**
     * @inheritDoc
     */
    public function set_icon_url($icon_url)
    {
        $this->_icon_url = $icon_url;
    }

    /**
     * @inheritDoc
     */
    public function is_special_group()
    {
        return in_array($this->_id, array(
            ALL_CHANNEL_GROUP_ID,
            FAVORITES_GROUP_ID,
            HISTORY_GROUP_ID,
            CHANGED_CHANNELS_GROUP_ID,
            VOD_GROUP_ID,
            FAVORITES_MOVIE_GROUP_ID,
            SEARCH_MOVIES_GROUP_ID,
            FILTER_MOVIES_GROUP_ID,
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function is_adult_group()
    {
        return $this->_adult;
    }

    /**
     * @inheritDoc
     */
    public function set_adult($adult)
    {
        $this->_adult = $adult;
    }

    /**
     * @inheritDoc
     */
    public function is_disabled()
    {
        if ($this->_id === ALL_CHANNEL_GROUP_ID) {
            return !$this->plugin->get_bool_parameter(PARAM_SHOW_ALL);
        }

        if ($this->_id === FAVORITES_GROUP_ID) {
            return !$this->plugin->get_bool_parameter(PARAM_SHOW_FAVORITES);
        }

        if ($this->_id === HISTORY_GROUP_ID) {
            return !$this->plugin->get_bool_parameter(PARAM_SHOW_HISTORY);
        }

        if ($this->_id === CHANGED_CHANNELS_GROUP_ID) {
            return $this->_disabled || !$this->plugin->get_bool_parameter(PARAM_SHOW_CHANGED_CHANNELS);
        }

        return $this->_disabled;
    }

    /**
     * @inheritDoc
     */
    public function set_disabled($disabled)
    {
        $this->_disabled = $disabled;
        if (!$this->is_special_group()) {
            if ($disabled) {
                $this->plugin->tv->get_disabled_group_ids()->add_item($this->_id);
                $this->plugin->tv->get_groups_order()->remove_item($this->_id);
            } else {
                $this->plugin->tv->get_disabled_group_ids()->remove_item($this->_id);
                $this->plugin->tv->get_groups_order()->add_item($this->_id);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function &get_group_channels()
    {
        if ($this->_id === ALL_CHANNEL_GROUP_ID) {
            return $this->plugin->tv->get_channels();
        }

        return $this->_channels;
    }

    /**
     * @inheritDoc
     */
    public function get_group_channel($id)
    {
        if ($this->_id === ALL_CHANNEL_GROUP_ID) {
            return $this->plugin->tv->get_channel($id);
        }

        return $this->_channels->get($id);
    }

    /**
     * @return Hashed_Array
     */
    public function get_group_enabled_channels()
    {
        $channels = new Hashed_Array();
        if ($this->_id === ALL_CHANNEL_GROUP_ID) {
            foreach ($this->plugin->tv->get_enabled_groups() as $egroup) {
                foreach ($egroup->get_group_channels() as $channel) {
                    if (is_null($channel) || $channel->is_disabled()) continue;
                    $channels->put($channel->get_id(), $channel);
                }
            }
        } else {
            foreach ($this->get_group_channels() as $channel) {
                if (is_null($channel) || $channel->is_disabled()) continue;
                $channels->put($channel->get_id(), $channel);
            }
        }

        return $channels;
    }

    /**
     * @return Hashed_Array
     */
    public function get_group_disabled_channels()
    {
        $channels = new Hashed_Array();
        if ($this->_id === ALL_CHANNEL_GROUP_ID) {
            /** @var Default_Channel $channel */
            foreach ($this->plugin->tv->get_channels() as $channel) {
                if (is_null($channel) || !$channel->is_disabled()) continue;
                $channels->put($channel->get_id(), $channel);
            }
        } else {
            foreach ($this->get_group_channels() as $channel) {
                if (is_null($channel) || !$channel->is_disabled()) continue;
                $channels->put($channel->get_id(), $channel);
            }
        }

        return $channels;
    }

    /**
     * @inheritDoc
     */
    public function &get_items_order()
    {
        if ($this->_order_support) {
            return $this->plugin->get_orders($this->_id);
        }

        $empty = new Ordered_Array();
        return $empty;
    }

    /**
     * @inheritDoc
     */
    public function set_items_order($order)
    {
        if ($this->_order_support) {
            $this->plugin->set_orders($this->_id, $order);
        }
    }

    /**
     * @param string $id
     * @return bool
     */
    public function in_items_order($id)
    {
        if ($this->_order_support) {
            return $this->plugin->get_orders($this->_id)->in_order($id);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function get_media_url_str()
    {
        switch ($this->_id) {
            case FAVORITES_GROUP_ID:
                return Starnet_Tv_Favorites_Screen::get_media_url_string(FAVORITES_GROUP_ID);

            case HISTORY_GROUP_ID:
                return Starnet_TV_History_Screen::get_media_url_string(HISTORY_GROUP_ID);

            case CHANGED_CHANNELS_GROUP_ID:
                return Starnet_Tv_Changed_Channels_Screen::get_media_url_string(CHANGED_CHANNELS_GROUP_ID);

            case VOD_GROUP_ID:
                return Starnet_Vod_Category_List_Screen::get_media_url_string(VOD_GROUP_ID);

            case FAVORITES_MOVIE_GROUP_ID:
                return Starnet_Vod_Favorites_Screen::get_media_url_string(FAVORITES_MOVIE_GROUP_ID);

            case HISTORY_MOVIES_GROUP_ID:
                return Starnet_Vod_History_Screen::get_media_url_string(HISTORY_MOVIES_GROUP_ID);

            case SEARCH_MOVIES_GROUP_ID:
                return Starnet_Vod_Search_Screen::get_media_url_string(SEARCH_MOVIES_GROUP_ID);

            case FILTER_MOVIES_GROUP_ID:
                return Starnet_Vod_Filter_Screen::get_media_url_string();
        }

        return Starnet_Tv_Channel_List_Screen::get_media_url_string($this->get_id());
    }

    ////////////////////////////////////////////////////////////////////////////
    /// Methods

    /**
     * @inheritDoc
     */
    public function add_channel($channel)
    {
        $this->_channels->set($channel->get_id(), $channel);
        if ($this->_order_support && !$channel->is_disabled()) {
            $this->get_items_order()->add_item($channel->get_id());
        }
    }

    /**
     * Sort channels in group
     *
     * @param bool $reset
     */
    public function sort_group_items($reset = false)
    {
        /** @var Default_Channel $channel */
        $order = &$this->get_items_order();
        $order->clear();
        if ($reset) {
            foreach ($this->_channels as $channel) {
                if (is_null($channel) || $channel->is_disabled()) continue;

                $order->add_item($channel->get_id());
            }
        } else {
            // group items order contain only ID of the channels
            $names = new Hashed_Array();
            foreach ($this->_channels as $channel) {
                if (!is_null($channel) && !$channel->is_disabled()) {
                    $names->set($channel->get_id(), $channel->get_title());
                }
            }
            $names->value_sort();
            $order->add_items($names->get_keys());
        }
    }
}
