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

require_once 'lib/hashed_array.php';
require_once 'lib/ordered_array.php';
require_once 'lib/default_group.php';
require_once 'lib/default_channel.php';
require_once 'lib/default_epg_item.php';
require_once 'vod/vod_standard.php';

class Starnet_Tv implements User_Input_Handler
{
    const ID = 'tv';

    const DEFAULT_CHANNEL_ICON_PATH = 'plugin_file://icons/default_channel.png';

    ///////////////////////////////////////////////////////////////////////

    public static $tvg_id = array('tvg-id', 'tvg-name');
    public static $tvg_archive = array('catchup-days', 'catchup-time', 'timeshift', 'arc-timeshift', 'arc-time', 'tvg-rec');

    static public $null_hashed_array;
    static public $null_ordered_array;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @var Starnet_Plugin
     */
    protected $plugin;

    /**
     * @var int
     */
    protected $playback_runtime;

    /**
     * @var string
     */
    protected $playback_url_is_stream_url;

    /**
     * @template Channel
     * @var Hashed_Array<string, Channel>
     */
    protected $channels;

    /**
     * @template Group
     * @var Hashed_Array<string, Group>
     */
    protected $groups;

    /**
     * @template Group
     * @var Hashed_Array<string, Group>
     */
    protected $special_groups;

    /**
     * @var M3uParser
     */
    protected $m3u_parser;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Starnet_Plugin $plugin
     */
    public function __construct(Starnet_Plugin $plugin)
    {
        User_Input_Handler_Registry::get_instance()->register_handler($this);

        self::$null_hashed_array = new Hashed_Array();
        self::$null_ordered_array = new Ordered_Array();

        $this->plugin = $plugin;
        $this->playback_url_is_stream_url = false;
        $this->groups = new Hashed_Array();
        $this->channels = new Hashed_Array();
        $this->special_groups = new Hashed_Array();
        $this->m3u_parser = new M3uParser();
    }

    /**
     * @inheritDoc
     */
    public static function get_handler_id()
    {
        return static::ID . '_handler';
    }

    /**
     * @return M3uParser
     */
    public function get_m3u_parser()
    {
        return $this->m3u_parser;
    }

    /**
     * @param array|Ordered_Array|Hashed_Array|string|null $filter
     * @return Hashed_Array<Channel>|null
     */
    public function &get_channels($filter = null)
    {
        if ($this->channels->size() === 0) {
            hd_debug_print("Channels not loaded");
            return self::$null_hashed_array;
        }

        if (is_null($filter)) {
            return $this->channels;
        }

        if (is_array($filter)) {
            return $this->channels->filter($filter);
        }

        if (is_object($filter)) {
            if ($filter instanceof Ordered_Array) {
                return $this->channels->filter($filter->get_order());
            }

            if ($filter instanceof Hashed_Array) {
                return $this->channels->filter($filter->get_ordered_values());
            }
        }

        hd_debug_print("Unknown filter type");
        return self::$null_hashed_array;
    }

    /**
     * @param string $channel_id
     * @return Channel
     */
    public function get_channel($channel_id)
    {
        if ($this->channels->size() === 0) {
            hd_debug_print("Channels not loaded");
            return null;
        }

        return $this->channels->get($channel_id);
    }

    /**
     * @return Hashed_Array<Channel>
     */
    public function get_enabled_channels()
    {
        $channels = new Hashed_Array();
        if ($this->channels->size() === 0) {
            hd_debug_print("Channels not loaded");
            return $channels;
        }

        /** @var Channel $channel */
        foreach ($this->channels as $channel) {
            if (is_null($channel) || $channel->is_disabled()) continue;
            $channels->put($channel->get_id(), $channel);
        }
        return $channels;
    }

    /**
     * returns all groups if filter not set
     * returns all groups filtered by filter array
     *
     * @param array|Ordered_Array|Hashed_Array|string|null $filter
     * @return Hashed_Array<Group>|Group
     */
    public function get_groups($filter = null)
    {
        if (is_null($this->groups)) {
            hd_debug_print("Groups not loaded");
            return new Hashed_Array();
        }

        if (is_null($filter)) {
            return $this->groups;
        }

        if (is_array($filter)) {
            return $this->groups->filter($filter);
        }

        if (is_object($filter)) {
            if ($filter instanceof Ordered_Array) {
                return $this->groups->filter($filter->get_order());
            }

            if ($filter instanceof Hashed_Array) {
                return $this->groups->filter($filter->get_ordered_values());
            }
        }

        return $this->groups->get($filter);
    }

    /**
     * returns group with selected id
     *
     * @param string $group_id
     * @return Group
     */
    public function get_group($group_id)
    {
        if ($this->groups->size() === 0) {
            hd_debug_print("Groups not loaded");
            return null;
        }

        return $this->groups->get($group_id);
    }

    /**
     * returns group with selected id
     *
     * @param string $group_id
     * @return Group
     */
    public function get_any_group($group_id)
    {
        $group = $this->get_group($group_id);
        if (is_null($group)) {
            $group = $this->get_special_group($group_id);
        }

        return $group;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * disable channels by pattern and remove it from order
     *
     * @param string $pattern
     * @param string $group_id
     * @param bool $regex
     */
    public function disable_group_channels($pattern, $group_id, $regex = true)
    {
        hd_debug_print("Hide channels type: $pattern in group: $group_id");

        $group = $this->get_any_group($group_id);
        if (is_null($group)) {
            return;
        }

        $i = 0;
        foreach ($group->get_group_enabled_channels() as $channel) {
            if ($regex) {
                $disable = preg_match("#$pattern#", $channel->get_title());
            } else {
                $disable = stripos($channel->get_title(), $pattern) !== false;
            }

            if ($disable) {
                $channel->set_disabled(true);
                $i++;
            }
        }
        hd_debug_print("Total hide channels: $i");
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * returns only not null and enabled groups
     *
     * @template Group
     * @return  Hashed_Array<Group>
     */
    public function get_enabled_groups()
    {
        $order = new Hashed_Array();
        foreach ($this->groups as $group) {
            if (is_null($group) || $group->is_disabled()) continue;
            $order->put($group->get_id(), $group);
        }

        return $order;
    }

    /**
     * returns only not null and disabled groups
     *
     * @template Group
     * @return  Hashed_Array<Group>
     */
    public function get_disabled_groups()
    {
        $order = new Hashed_Array();
        foreach ($this->groups as $group) {
            if (is_null($group) || !$group->is_disabled()) continue;

            $order->put($group->get_id(), $group);
        }

        return $order;
    }

    /**
     * disable group and remove it from order
     *
     * @param string $group_id
     */
    public function disable_group($group_id)
    {
        hd_debug_print("Hide group: $group_id");
        if (($group = $this->groups->get($group_id)) !== null) {
            $group->set_disabled(true);
        }

        $this->get_groups_order()->remove_item($group_id);
        $this->plugin->save_settings();
    }

    /**
     * @return Hashed_Array
     */
    public function get_special_groups()
    {
        return $this->special_groups;
    }

    /**
     * @return Group
     */
    public function get_special_group($id)
    {
        return $this->special_groups->get($id);
    }

    public function get_special_groups_count()
    {
        $groups_cnt = 0;
        /** @var Group $group */
        foreach($this->special_groups as $group) {
            if (!is_null($group) && !$group->is_disabled()) $groups_cnt++;
        }
        return $groups_cnt;
    }

    /**
     * @override DunePlugin
     * @param string $op_type
     * @param string $channel_id
     * @return array
     */
    public function change_tv_favorites($op_type, $channel_id)
    {
        hd_debug_print(null, true);

        $fav_group = $this->get_special_group(FAVORITES_GROUP_ID);
        if (is_null($fav_group))
            return null;
        $order = &$fav_group->get_items_order();
        switch ($op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                if (!$order->add_item($channel_id)) return null;

                hd_debug_print("Add channel $channel_id to favorites", true);
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                if (!$order->remove_item($channel_id)) return null;

                hd_debug_print("Remove channel $channel_id from favorites", true);
                break;

            case PLUGIN_FAVORITES_OP_MOVE_UP:
                if (!$order->arrange_item($channel_id, Ordered_Array::UP)) return null;

                break;

            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                if (!$order->arrange_item($channel_id, Ordered_Array::DOWN)) return null;

                break;

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("Clear favorites", true);
                $order->clear();
                break;
        }

        $this->plugin->set_dirty(true, PLUGIN_ORDERS);

        $player_state = get_player_state_assoc();
        if (isset($player_state['playback_state']) && $player_state['playback_state'] === PLAYBACK_PLAYING) {
            $this->plugin->save_orders(true);
            return Action_Factory::invalidate_folders(array(), null, true);
        }

        return Starnet_Epfs_Handler::invalidate_folders(
            array(Starnet_Tv_Favorites_Screen::get_media_url_string(FAVORITES_GROUP_ID),
                Starnet_Tv_Channel_List_Screen::get_media_url_string(ALL_CHANNEL_GROUP_ID)
            )
        );
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_action_map()
    {
        hd_debug_print(null, true);

        return array(
            GUI_EVENT_PLAYBACK_STOP => User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLAYBACK_STOP),
            GUI_EVENT_TIMER => User_Input_Handler_Registry::create_action($this,
                GUI_EVENT_TIMER,
                null,
                $this->plugin->get_epg_manager()->is_index_locked() ? array('locked' => true) : null),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (!isset($user_input->control_id))
            return null;

        switch ($user_input->control_id) {
            case GUI_EVENT_TIMER:
                $post_action = null;

                if (isset($user_input->locked)) {
                    clearstatcache();
                    if ($this->plugin->get_epg_manager()->is_index_locked()) {
                        $new_actions = $this->get_action_map();
                        $new_actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this,
                            GUI_EVENT_TIMER,
                            null,
                            array('locked' => true));

                        $post_action = Action_Factory::change_behaviour($new_actions, 1000);
                    } else {
                        foreach($this->plugin->get_epg_manager()->get_delayed_epg() as $channel_id) {
                            hd_debug_print("Refresh EPG for channel ID: $channel_id");
                            $day_start_ts = strtotime(date("Y-m-d")) + get_local_time_zone_offset();
                            $day_epg = $this->plugin->get_day_epg($channel_id, $day_start_ts, $plugin_cookies);
                            $post_action = Action_Factory::update_epg($channel_id, true, $day_start_ts, $day_epg, $post_action);
                        }
                        $this->plugin->get_epg_manager()->clear_delayed_epg();
                    }
                }

                return $post_action;

            case GUI_EVENT_PLAYBACK_STOP:
                $channel = $this->plugin->tv->get_channel($user_input->plugin_tv_channel_id);
                if (is_null($channel) || $channel->is_protected()) break;

                $this->plugin->get_playback_points()->update_point($user_input->plugin_tv_channel_id);

                if (isset($user_input->playback_stop_pressed) || isset($user_input->playback_power_off_needed)) {
                    $this->plugin->get_playback_points()->save();
                    return Action_Factory::invalidate_folders(array(Starnet_Tv_Groups_Screen::ID));
                }
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return int
     */
    public function reload_channels()
    {
        $this->unload_channels();
        return $this->load_channels();
    }

    /**
     * @return void
     */
    public function unload_channels()
    {
        $this->groups->clear();
        $this->channels->clear();
        $this->special_groups->clear();
    }

    /**
     * @return int
     */
    public function load_channels()
    {
        if ($this->channels->size() !== 0) {
            hd_debug_print("Channels already loaded", true);
            return 1;
        }

        hd_debug_print();

        HD::set_last_error(null);

        $this->plugin->load_settings(true);
        $this->plugin->load_orders(true);
        $this->plugin->load_history(true);
        $epg_manager = $this->plugin->get_epg_manager();
        if (is_null($epg_manager)) {
            $this->plugin->init_epg_manager();
        }
        $epg_manager->set_cache_ttl($this->plugin->get_setting(PARAM_EPG_CACHE_TTL, 3));
        $this->plugin->create_screen_views();

        $pass_sex = $this->plugin->get_parameter(PARAM_ADULT_PASSWORD, '0000');
        $enable_protected = !empty($pass_sex);
        /** @var Hashed_Array<string, string> $custom_group_icons */
        $custom_group_icons = $this->plugin->get_setting(PARAM_GROUPS_ICONS, new Hashed_Array());
        // convert absolute path to filename
        foreach($custom_group_icons as $key => $icon) {
            if (strpos($icon, DIRECTORY_SEPARATOR) !== false) {
                $icon = basename($icon);
                $custom_group_icons->set($key, $icon);
            }
        }

        // Favorites category
        $special_group = new Default_Group($this->plugin,
            FAVORITES_GROUP_ID,
            TR::load_string(Default_Group::FAV_CHANNEL_GROUP_CAPTION),
            Default_Group::FAV_CHANNEL_GROUP_ICON);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // History channels category
        $special_group = new Default_Group($this->plugin,
            HISTORY_GROUP_ID,
            TR::load_string(Default_Group::HISTORY_GROUP_CAPTION),
            Default_Group::HISTORY_GROUP_ICON,
            false);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // Changed channels category
        $special_group = new Default_Group($this->plugin,
            CHANGED_CHANNELS_GROUP_ID,
            TR::load_string(Default_Group::CHANGED_CHANNELS_GROUP_CAPTION),
            Default_Group::CHANGED_CHANNELS_GROUP_ICON,
            false);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // Vod category
        $special_group = new Default_Group($this->plugin,
            VOD_GROUP_ID,
            TR::load_string(Default_Group::VOD_GROUP_CAPTION),
            Default_Group::VOD_GROUP_ICON,
            false);
        $special_group->set_disabled(true);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // All channels category
        $special_group = new Default_Group($this->plugin,
            ALL_CHANNEL_GROUP_ID,
            TR::load_string(Default_Group::ALL_CHANNEL_GROUP_CAPTION),
            Default_Group::ALL_CHANNEL_GROUP_ICON,
            false);
        $this->special_groups->set($special_group->get_id(), $special_group);

        $first_run = $this->get_known_channels()->size() === 0;

        /** @var Group $special_group */
        foreach ($this->special_groups as $special_group) {
            $group_icon = $custom_group_icons->get($special_group->get_id());
            if (!is_null($group_icon)) {
                $special_group->set_icon_url(get_cached_image_path($group_icon));
            }
        }

        // first check if playlist in cache
        if (!$this->plugin->init_playlist()) {
            return 0;
        }

        $catchup['global'] = $this->m3u_parser->getM3uInfo()->getCatchup();
        $global_catchup_source = $this->m3u_parser->getM3uInfo()->getCatchupSource();
        $icon_url_base = $this->m3u_parser->getHeaderAttribute('url-logo', Entry::TAG_EXTM3U);

        $this->plugin->vod = null;
        $provider = $this->plugin->get_current_provider();
        if (is_null($provider)) {
            $mapper = $this->plugin->get_setting(PARAM_ID_MAPPER, 'by_default');
            if ($mapper !== 'default') {
                $id_map = $mapper;
                hd_debug_print("Use custom ID detection: $id_map");
            }
        } else {
            $playlist_catchup = $provider->getProviderConfigValue(CONFIG_PLAYLIST_CATCHUP);
            if (!empty($playlist_catchup)) {
                $catchup['global'] = $playlist_catchup;
                hd_debug_print("set provider catchup: $playlist_catchup");
            }

            $id_parser = $provider->getProviderConfigValue(CONFIG_ID_PARSER);
            if (!empty($id_parser)) {
                $id_parser = "/$id_parser/";
                hd_debug_print("using provider ({$provider->getId()}) specific id parsing: $id_parser");
            }

            $id_map = $provider->getProviderConfigValue(CONFIG_ID_MAP);
            if (!empty($id_map)) {
                hd_debug_print("using provider ({$provider->getId()}) specific id mapping: $id_map");
            }

            if (empty($id_map) && empty($id_parser)) {
                hd_debug_print("no provider specific id mapping, use M3U attributes");
            }

            $vod_source = $provider->getProviderConfigValue(CONFIG_VOD_SOURCE);
            if (!empty($vod_source)) {
                $vod_class = "vod_" . ($provider->getProviderConfigValue(CONFIG_VOD_CUSTOM) ? $provider->getId() : "standard");
                hd_debug_print("Used VOD class: $vod_class");
                $enable = false;
                if (class_exists($vod_class)) {
                    $this->plugin->vod = new $vod_class($this->plugin);
                    $enable = $this->plugin->vod->init_vod($provider);
                    $this->get_special_group(VOD_GROUP_ID)->set_disabled(!$enable);
                    $this->plugin->vod->init_vod_screens($enable);
                } else {
                    hd_debug_print("VOD class not found");
                }
                hd_debug_print("VOD show: " . var_export($enable, true));
            }

            $ignore_groups = $provider->getProviderConfigValue(CONFIG_IGNORE_GROUPS);
        }

        $this->groups = new Hashed_Array();
        $this->channels = new Hashed_Array();

        $this->plugin->get_playback_points()->load_points(true);

        // User catchup settings has higher priority than playlist or provider settings
        $user_catchup = $this->plugin->get_setting(PARAM_USER_CATCHUP, KnownCatchupSourceTags::cu_unknown);
        if ($user_catchup !== KnownCatchupSourceTags::cu_unknown) {
            $catchup['global'] = $user_catchup;
        }

        $source = $this->plugin->get_active_xmltv_source();
        if (empty($source)) {
            $sources = $this->plugin->get_all_xmltv_sources();
            $key = $this->plugin->get_active_xmltv_source_key();
            hd_debug_print("XMLTV active source key: $key");
            $source = $sources->get($key);
            if (is_null($source) && $sources->size()) {
                hd_debug_print("Unknown key for XMLTV source, try use first");
                $sources->rewind();
            }
            $this->plugin->set_active_xmltv_source_key($sources->key());
        }

        hd_debug_print("XMLTV source selected: $source");
        $this->plugin->init_epg_manager();
        $res = $epg_manager->is_xmltv_cache_valid();
        if ($res !== -1) {
            if ($res === 0) {
                $epg_manager->download_xmltv_source();
            }

            $epg_manager->index_xmltv_channels();
        }

        hd_debug_print("Build categories and channels...");
        $t = microtime(true);

        $picons = $epg_manager->get_picons();

        // suppress save after add group
        $this->plugin->set_postpone_save(true, PLUGIN_SETTINGS);
        $this->plugin->set_postpone_save(true, PLUGIN_ORDERS);

        // Collect categories from playlist
        $disabled_group = $this->get_disabled_group_ids();
        $playlist_groups = new Ordered_Array();
        $pl_entries = $this->m3u_parser->getM3uEntries();
        foreach ($pl_entries as $entry) {
            $title = $entry->getGroupTitle();
            if ($this->groups->has($title)) continue;

            if (!empty($ignore_groups) && in_array($title, $ignore_groups)) continue;

            // using title as id
            $group_icon = $custom_group_icons->get($title);
            if (!is_null($group_icon)) {
                $group_icon = get_cached_image_path($custom_group_icons->get($title));
            }
            $group = new Default_Group($this->plugin, $title, $title, $group_icon);
            $adult = (strpos($title, "зрослы") !== false
                || strpos($title, "adult") !== false
                || strpos($title, "18+") !== false
                || strpos($title, "xxx") !== false);

            $group->set_adult($adult);
            if ($disabled_group->in_order($group->get_id())) {
                $group->set_disabled(true);
                hd_debug_print("Hidden category # $title");
            } else if (!$this->get_groups_order()->in_order($group->get_id())) {
                hd_debug_print("New    category # $title");
                $this->get_groups_order()->add_item($title);
            }

            $playlist_groups->add_item($title);
            $this->groups->set($group->get_id(), $group);
        }

        // cleanup order if saved group removed from playlist
        // hidden groups
        $orphans_groups = array_diff($disabled_group->get_order(), $playlist_groups->get_order());
        if (!empty($orphans_groups)) {
            $this->plugin->set_dirty();
        }

        foreach ($orphans_groups as $group) {
            hd_debug_print("Remove orphaned hidden group: $group", true);
            $disabled_group->remove_item($group);
        }

        // orders
        $playlist_groups->add_items(
            array(PARAM_DISABLED_GROUPS,
                PARAM_DISABLED_CHANNELS,
                PARAM_KNOWN_CHANNELS,
                PARAM_GROUPS_ORDER,
                FAVORITES_GROUP_ID,
                FAVORITES_MOVIE_GROUP_ID,
            )
        );
        $orphans_groups = array_diff($this->plugin->get_order_names(), $playlist_groups->get_order());
        foreach ($orphans_groups as $orphan_group_id) {
            hd_debug_print("Remove orphaned order for group id: $orphan_group_id", true);
            $this->plugin->remove_order($orphan_group_id);
        }

        $use_playlist_picons = $this->plugin->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS) === PLAYLIST_PICONS;
        // Read channels
        $playlist_group_channels = array();
        $number = 0;
        foreach ($pl_entries as $entry) {
            $channel_id = $entry->getEntryId();
            if (empty($channel_id)) {
                if (!empty($id_map)) {
                    $channel_id = ($id_map === 'name') ? $entry->getEntryTitle() : $entry->getEntryAttribute($id_map);
                } else if (!empty($id_parser) && preg_match($id_parser, $entry->getPath(), $m)) {
                    $channel_id = $m['id'];
                }

                if (empty($channel_id)) {
                    $channel_id = Hashed_Array::hash($entry->getPath());
                }
            }

            // if group is not registered it was disabled
            $channel_name = $entry->getEntryTitle();
            $group_title = $entry->getGroupTitle();
            if (empty($channel_name)) {
                hd_print("Bad entry: " . $entry);
                $channel_name = "no name";
            }

            $number++;

            /** @var Channel $channel */
            $channel = $this->channels->get($channel_id);
            if (is_null($channel)) {
                $epg_ids = $entry->getAllEntryAttributes(self::$tvg_id);
                if (!empty($epg_ids)) {
                    $epg_ids = array_unique(array_values($epg_ids));
                }

                $playlist_icon = $entry->getEntryIcon();
                if (!empty($icon_url_base) && !preg_match(HTTP_PATTERN, $playlist_icon)) {
                    $playlist_icon = $icon_url_base . $playlist_icon;
                }

                $xmltv_icon = isset($picons[$channel_name]) ? $picons[$channel_name]: '';

                if ($use_playlist_picons) {
                    $icon_url = empty($playlist_icon) ? $xmltv_icon : $playlist_icon;
                } else {
                    $icon_url = $xmltv_icon;
                }

                if (empty($icon_url)) {
                    $icon_url = self::DEFAULT_CHANNEL_ICON_PATH;
                }

            $used_tag = '';
                $archive = (int)$entry->getAnyEntryAttribute(self::$tvg_archive, Entry::TAG_EXTINF, $used_tag);
                if ($used_tag === 'catchup-time') {
                    $archive /= 86400;
                }

                $archive_url = '';
                if ($archive !== 0) {
                    $catchup['channel'] = $entry->getCatchup();
                    $archive_url = $entry->getCatchupSource();
                    if (empty($archive_url) && !empty($global_catchup_source)) {
                        $archive_url = $global_catchup_source;
                    }

                    if (empty($archive_url)) {
                        if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_shift, $catchup)) {
                            $archive_url = $entry->getPath()
                                . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                . 'utc=${start}&lutc=${timestamp}';
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_timeshift, $catchup)) {
                            $archive_url = $entry->getPath()
                                . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                . 'timeshift=${start}&timenow=${timestamp}';
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_archive, $catchup)) {
                            $archive_url = $entry->getPath()
                                . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                . 'archive=${start}&archive_end=${end}';
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_flussonic, $catchup)
                            && preg_match("#^(https?://[^/]+)/(.+)/([^/]+)\.(m3u8?|ts)(\?.+=.+)?$#", $entry->getPath(), $m)) {
                            $params = isset($m[5]) ? $m[5] : '';
                            $archive_url = "$m[1]/$m[2]/$m[3]-" . '${start}' . "-14400.$m[4]$params";
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_xstreamcode, $catchup)
                            && preg_match("#^(https?://[^/]+)/(?:live/)?([^/]+)/([^/]+)/([^/.]+)(\.m3u8?)?$#", $entry->getPath(), $m)) {
                            $extension = $m[6] ?: '.ts';
                            $archive_url = "$m[1]/timeshift/$m[2]/$m[3]/240/{Y}-{m}-{d}:{H}-{M}/$m[5].$extension";
                        } else {
                            // if no info about catchup, use 'shift'
                            $archive_url = $entry->getPath()
                                . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                . 'utc=${start}&lutc=${timestamp}';
                        }
                    } else if (!preg_match(HTTP_PATTERN, $archive_url)) {
                        $archive_url = $entry->getPath() . $archive_url;
                    }
                }

                $ext_params[PARAM_DUNE_PARAMS] = $this->plugin->get_setting(PARAM_DUNE_PARAMS);

                $ext_tag = $entry->getEntryTag(Entry::TAG_EXTHTTP);
                if ($ext_tag !== null && ($ext_http_values = json_decode($ext_tag->getTagValue(), true)) !== false) {
                    foreach ($ext_http_values as $key => $value) {
                        $ext_params[Entry::TAG_EXTHTTP][strtolower($key)] = $value;
                    }

                    if (isset($ext_params[Entry::TAG_EXTHTTP]['user-agent'])) {
                        hd_debug_print(Entry::TAG_EXTHTTP
                            . " Channel: $channel_name uses custom User-Agent: '{$ext_params[Entry::TAG_EXTHTTP]['user-agent']}'", true);
                        $ch_useragent = "User-Agent: " . $ext_params[Entry::TAG_EXTHTTP]['user-agent'];
                    }
                }

                $ext_tag = $entry->getEntryTag(Entry::TAG_EXTVLCOPT);
                if ($ext_tag !== null) {
                    $ext_vlc_opts = array();
                    foreach ($ext_tag->getTagValues() as $value) {
                        $pair = explode('=', $value);
                        $ext_vlc_opts[strtolower(trim($pair[0]))] = trim($pair[1]);
                    }

                    if (isset($ext_vlc_opts['http-user-agent'])) {
                        hd_debug_print(Entry::TAG_EXTVLCOPT
                            . " Channel: $channel_name uses custom User-Agent: '{$ext_vlc_opts['http-user-agent']}'", true);
                        $ch_useragent = "User-Agent: " . $ext_vlc_opts['http-user-agent'];
                    }

                    if (isset($ext_vlc_opts['dune-params'])) {
                        hd_debug_print(Entry::TAG_EXTVLCOPT
                            . " Channel: $channel_name uses custom dune_params: '{$ext_vlc_opts['dune-params']}'", true);

                        foreach ($ext_vlc_opts['dune-params'] as $param) {
                            $param_pair = explode(':', $param);
                            if (count($param_pair) < 2) continue;

                            $param_pair[0] = trim($param_pair[0]);
                            if (strpos($param_pair[1], ",,") !== false) {
                                $param_pair[1] = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $param_pair[1]);
                            } else {
                                $param_pair[1] = str_replace(",", ",,", $param_pair[1]);
                            }

                            $ext_params[PARAM_DUNE_PARAMS][$param_pair[0]] = $param_pair[1];
                            unset($ext_vlc_opts['dune-params']);
                        }
                    }

                    $ext_params[Entry::TAG_EXTVLCOPT] = $ext_vlc_opts;
                }

                if (!empty($ch_useragent)) {
                    // escape commas for dune_params
                    if (strpos($ch_useragent, ",,") !== false) {
                        $ch_useragent = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $ch_useragent);
                    } else {
                        $ch_useragent = str_replace(",", ",,", $ch_useragent);
                    }

                    $ch_useragent = rawurlencode("User-Agent: " . $ch_useragent);
                    if (isset($ext_params[PARAM_DUNE_PARAMS]['http_headers'])) {
                        $ext_params[PARAM_DUNE_PARAMS]['http_headers'] .= $ch_useragent;
                    } else {
                        $ext_params[PARAM_DUNE_PARAMS]['http_headers'] = $ch_useragent;
                    }

                    unset($ch_useragent);
                }

                /** @var Group $parent_group */
                $parent_group = $this->groups->get($group_title);
                if (is_null($parent_group)) continue;

                $protected = false;
                $adult_code = $entry->getProtectedCode();
                if (!empty($adult_code) || $parent_group->is_adult_group()) {
                    $protected = $enable_protected;
                }

                $group_logo = $entry->getEntryAttribute('group-logo');
                if (!empty($group_logo) && $parent_group->get_icon_url() === Default_Group::DEFAULT_GROUP_ICON) {
                    if (!preg_match(HTTP_PATTERN, $group_logo)) {
                        if (!empty($icon_url_base)) {
                            $group_logo = $icon_url_base . $group_logo;
                        } else {
                            $group_logo = Default_Group::DEFAULT_GROUP_ICON;
                        }
                    }

                    hd_debug_print("Set picon: $group_logo", true);
                    $parent_group->set_icon_url($group_logo);
                }

                $channel = new Default_Channel(
                    $this->plugin,
                    $channel_id,
                    $channel_name,
                    $icon_url,
                    $entry->getPath(),
                    $archive_url,
                    $archive,
                    $number,
                    $epg_ids,
                    $protected,
                    (int)$entry->getEntryAttribute('tvg-shift', Entry::TAG_EXTINF),
                    $ext_params
                );

                // ignore disabled channel
                if ($this->get_disabled_channel_ids()->in_order($channel_id)) {
                    //hd_debug_print("Channel $channel_name is disabled", true);
                    $channel->set_disabled(true);
                }

                $playlist_group_channels[$parent_group->get_id()][] = $channel_id;
                $this->channels->set($channel->get_id(), $channel);
                if ($first_run) {
                    $this->get_known_channels()->set($channel->get_id(), $channel->get_title());
                }

                foreach ($epg_ids as $epg_id) {
                    $epg_ids[$epg_id] = '';
                }

                // Link group and channel.
                $channel->add_group($parent_group);
                $parent_group->add_channel($channel);
            }
        }

        $changed = count($this->get_changed_channels_ids());
        $this->get_special_group(CHANGED_CHANNELS_GROUP_ID)->set_disabled($changed === 0);

        // cleanup orders if saved group removed from playlist
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $channels = isset($playlist_group_channels[$group->get_id()]) ? $playlist_group_channels[$group->get_id()] : array();
            $orphans_channels = array_diff($group->get_items_order()->get_order(), $channels);
            if (!empty($orphans_channels)) {
                if (LogSeverity::$is_debug) {
                    hd_debug_print("Remove from group: {$group->get_title()} total orphaned channels: "
                        . count($orphans_channels) . " : " . json_encode($orphans_channels));
                    hd_debug_print("Playlist group channels: " . json_encode($channels));
                }
                $group->get_items_order()->remove_items($orphans_channels);
                $this->plugin->set_dirty(true, PLUGIN_ORDERS);
            }
        }

        if (!is_null($all_channels = $this->get_channels())) {
            $orphans_channels = array_diff($this->get_disabled_channel_ids()->get_order(), $all_channels->get_order());
            if (!empty($orphans_channels)) {
                if (LogSeverity::$is_debug) {
                    hd_debug_print("Remove total orphaned disabled channels: "
                        . count($orphans_channels) . " : " . json_encode($orphans_channels));
                }
                $this->get_disabled_channel_ids()->remove_items($orphans_channels);
                $this->plugin->set_dirty(true, PLUGIN_ORDERS);
            }
        }

        $this->plugin->set_postpone_save(false, PLUGIN_SETTINGS);
        $this->plugin->set_postpone_save(false, PLUGIN_ORDERS);

        hd_debug_print("Loaded channels: {$this->channels->size()}, hidden channels: {$this->get_disabled_channel_ids()->size()}, changed channels: $changed");
        hd_debug_print("Total groups: {$this->groups->size()}, hidden groups: " . ($this->groups->size() - $this->get_groups_order()->size()));
        hd_debug_print("------------------------------------------------------------");
        hd_debug_print("Load channels done: " . (microtime(true) - $t) . " secs");
        HD::ShowMemoryUsage();

        if ($epg_manager->is_xmltv_cache_valid() === 1) {
            hd_debug_print("Run background indexing: {$this->plugin->get_active_xmltv_source()} ({$this->plugin->get_active_xmltv_source_key()})");
            $this->plugin->start_bg_indexing();
            sleep(1);
        }

        return 2;
    }

    /**
     * Generate url from template with macros substitution
     * Make url ts wrapped
     * @param string $channel_id
     * @param int $archive_ts
     * @return string
     * @throws Exception
     */
    public function generate_stream_url($channel_id, $archive_ts = -1)
    {
        hd_debug_print(null, true);

        $channel = $this->get_channel($channel_id);
        if (is_null($channel)) {
            throw new Exception("Channel with id: $channel_id not found");
        }

        // replace all macros
        $stream_url = ((int)$archive_ts <= 0) ? $channel->get_url() : $channel->get_archive_url();
        if (empty($stream_url)) {
            throw new Exception("Empty url!");
        }

        $replaces = array();

        $provider = $this->plugin->get_current_provider();
        if (!is_null($provider) && $provider->getProviderConfigValue(CONFIG_PROVIDER_TYPE) === PROVIDER_TYPE_EDEM) {
            $replaces['localhost'] = $provider->getCredential(MACRO_SUBDOMAIN);
            $replaces['00000000000000'] = $provider->getCredential(MACRO_OTTKEY);
        }

        if ((int)$archive_ts > 0) {
            $now = time();
            $replaces[catchup_params::CU_START] = $archive_ts;
            $replaces[catchup_params::CU_UTC] = $archive_ts;
            $replaces[catchup_params::CU_CURRENT_UTC] = $now;
            $replaces[catchup_params::CU_TIMESTAMP] = $now;
            $replaces[catchup_params::CU_END] = $now;
            $replaces[catchup_params::CU_UTCEND] = $now;
            $replaces[catchup_params::CU_OFFSET] = $now - $archive_ts;
            $replaces[catchup_params::CU_DURATION] = 14400;
            $replaces[catchup_params::CU_DURMIN] = 240;
            $replaces[catchup_params::CU_YEAR]  = $replaces[catchup_params::CU_START_YEAR]  = date('Y', $archive_ts);
            $replaces[catchup_params::CU_MONTH] = $replaces[catchup_params::CU_START_MONTH] = date('m', $archive_ts);
            $replaces[catchup_params::CU_DAY]   = $replaces[catchup_params::CU_START_DAY]   = date('d', $archive_ts);
            $replaces[catchup_params::CU_HOUR]  = $replaces[catchup_params::CU_START_HOUR]  = date('H', $archive_ts);
            $replaces[catchup_params::CU_MIN]   = $replaces[catchup_params::CU_START_MIN]   = date('M', $archive_ts);
            $replaces[catchup_params::CU_SEC]   = $replaces[catchup_params::CU_START_SEC]   = date('S', $archive_ts);
            $replaces[catchup_params::CU_END_YEAR]  = date('Y', $now);
            $replaces[catchup_params::CU_END_MONTH] = date('m', $now);
            $replaces[catchup_params::CU_END_DAY]   = date('d', $now);
            $replaces[catchup_params::CU_END_HOUR]  = date('H', $now);
            $replaces[catchup_params::CU_END_MIN]   = date('M', $now);
            $replaces[catchup_params::CU_END_SEC]   = date('S', $now);
        }

        hd_debug_print("replaces: " . raw_json_encode($replaces), true);
        foreach ($replaces as $key => $value) {
            if (strpos($stream_url, $key) !== false) {
                hd_debug_print("replace $key to $value", true);
                $stream_url = str_replace($key, $value, $stream_url);
            }
        }

        if (HD::get_dune_user_agent() !== HD::get_default_user_agent()) {
            $user_agent = rawurlencode("User-Agent: " . HD::get_dune_user_agent());
        }

        $ext_params = $channel->get_ext_params();
        $dune_params = "";
        if (isset($ext_params[PARAM_DUNE_PARAMS])) {
            if (!empty($user_agent)) {
                if (!isset($ext_params[PARAM_DUNE_PARAMS]['http_headers'])) {
                    $ext_params[PARAM_DUNE_PARAMS]['http_headers'] = $user_agent;
                } else {
                    $pos = strpos($ext_params[PARAM_DUNE_PARAMS]['http_headers'], "UserAgent:");
                    if ($pos === false) {
                        $ext_params[PARAM_DUNE_PARAMS]['http_headers'] .= "," . $user_agent;
                    }
                }
            }
        } else if (!empty($user_agent)) {
            $ext_params[PARAM_DUNE_PARAMS]['http_headers'] = $user_agent;
        }

        if (isset($ext_params[PARAM_DUNE_PARAMS])) {
            foreach ($ext_params[PARAM_DUNE_PARAMS] as $key => $value) {
                if (!empty($dune_params)) {
                    $dune_params .= ",";
                }

                $dune_params .= "$key:$value";
            }
            if (!empty($dune_params)) {
                $stream_url .= "|||dune_params|||$dune_params";
            }
        }

        return HD::make_ts($stream_url);
    }

    /**
     * @param MediaURL $media_url
     * @param int $archive_ts
     * @throws Exception
     */
    public function tv_player_exec($media_url, $archive_ts = -1)
    {
        $url = $this->generate_stream_url($media_url->channel_id, $archive_ts);

        if (!$this->get_channels_for_ext_player()->in_order($media_url->channel_id)) {
            return Action_Factory::tv_play($media_url);
        }

        $url = str_replace("ts://", "", $url);
        $param_pos = strpos($url, '|||dune_params');
        $url =  $param_pos!== false ? substr($url, 0, $param_pos) : $url;
        $cmd = 'am start -d "' . $url . '" -t "video/*" -a android.intent.action.VIEW 2>&1';
        hd_debug_print("play movie in the external player: $cmd");
        exec($cmd, $output);
        hd_debug_print("external player exec result code" . HD::ArrayToStr($output));
        return null;
    }

    /**
     * @param string $channel_id
     * @param int $archive_ts
     * @param string $protect_code
     * @return string
     */
    public function get_tv_playback_url($channel_id, $archive_ts, $protect_code)
    {
        try {
            if ($this->load_channels() === 0) {
                throw new Exception("Channels not loaded!");
            }

            $pass_sex = ($this->plugin->get_parameter(PARAM_ADULT_PASSWORD, '0000'));
            // get channel by hash
            $channel = $this->get_channel($channel_id);
            if (is_null($channel)){
                throw new Exception("Unknown channel");
            }

            if ($protect_code !== $pass_sex && $channel->is_protected()) {
                throw new Exception("Wrong adult password");
            }

            if (!$channel->is_protected()) {
                $now = $channel->get_archive() > 0 ? time() : 0;
                $this->plugin->get_playback_points()->push_point($channel_id, ($archive_ts !== -1 ? $archive_ts : $now));
            }

            // update url if play archive or different type of the stream
            $url = $this->generate_stream_url($channel_id, $archive_ts);

            if ($this->plugin->get_bool_setting(PARAM_PER_CHANNELS_ZOOM)) {
                $zoom_preset = $this->get_channel_zoom($channel_id);
                if (!is_null($zoom_preset)) {
                    if (!is_android()) {
                        $zoom_preset = DuneVideoZoomPresets::normal;
                        hd_debug_print("zoom_preset: reset to normal $zoom_preset");
                    }

                    if ($zoom_preset !== DuneVideoZoomPresets::not_set) {
                        $url .= (strpos($url, "|||dune_params|||") === false ? "|||dune_params|||" : ",");
                        $url .= "zoom:$zoom_preset";
                    }
                }
            }
        } catch (Exception $ex) {
            hd_debug_print("Exception: " . $ex->getMessage());
            $url = '';
        }

        hd_debug_print($url);
        return $url;
    }

    /**
     * @param MediaURL $media_url
     * @return array
     * @throws Exception
     */
    public function get_tv_info(MediaURL $media_url)
    {
        $epg_font_size = $this->plugin->get_bool_parameter(PARAM_EPG_FONT_SIZE, false)
            ? PLUGIN_FONT_SMALL
            : PLUGIN_FONT_NORMAL;

        if ($this->load_channels() === 0) {
            hd_debug_print("Channels not loaded!");
            return array();
        }
        $this->playback_runtime = PHP_INT_MAX;

        $group_all = $this->get_special_group(ALL_CHANNEL_GROUP_ID);
        $show_all = !$group_all->is_disabled();
        $all_channels = new Hashed_Array();
        /** @var Group $group */
        foreach ($this->groups as $group) {
            if ($group->is_disabled()) continue;

            $group_id_arr = new Hashed_Array();
            if($show_all) {
                $group_id_arr->put(ALL_CHANNEL_GROUP_ID, '');
            }

            /** @var Group $group */
            foreach ($group->get_items_order() as $item) {
                $channel = $this->get_channel($item);
                if (is_null($channel)) continue;

                foreach ($channel->get_groups() as $in_group) {
                    $group_id_arr->put($in_group->get_id(), '');
                }
                if ($group_id_arr->size() === 0) continue;

                $all_channels->put(
                    $channel->get_id(),
                    array(
                        PluginTvChannel::id => $channel->get_id(),
                        PluginTvChannel::caption => $channel->get_title(),
                        PluginTvChannel::group_ids => $group_id_arr->get_keys(),
                        PluginTvChannel::icon_url => $channel->get_icon_url(),
                        PluginTvChannel::number => $channel->get_number(),

                        PluginTvChannel::have_archive => $channel->get_archive() > 0,
                        PluginTvChannel::is_protected => $channel->is_protected(),

                        // set default epg range
                        PluginTvChannel::past_epg_days => $channel->get_past_epg_days(),
                        PluginTvChannel::future_epg_days => $channel->get_future_epg_days(),

                        PluginTvChannel::archive_past_sec => $channel->get_archive_past_sec(),
                        PluginTvChannel::archive_delay_sec => $this->plugin->get_setting(PARAM_ARCHIVE_DELAY_TIME, 60),

                        // Buffering time
                        PluginTvChannel::buffering_ms => $this->plugin->get_setting(PARAM_BUFFERING_TIME, 1000),
                        PluginTvChannel::timeshift_hours => $channel->get_timeshift_hours(),

                        PluginTvChannel::playback_url_is_stream_url => $this->playback_url_is_stream_url,
                    )
                );
            }
        }

        $groups_order = array_merge($show_all ? array(ALL_CHANNEL_GROUP_ID) : array(),
            $this->get_groups_order()->get_order());

        $groups = array();
        /** @var Group $group */
        foreach ($groups_order as $id) {
            $group = $this->get_any_group($id);
            if (is_null($group) || $group->is_disabled()) continue;

            $group_icon = $group->get_icon_url();
            $groups[] = array(
                PluginTvGroup::id => $group->get_id(),
                PluginTvGroup::caption => $group->get_title(),
                PluginTvGroup::icon_url => is_null($group_icon) ? Default_Group::DEFAULT_GROUP_ICON: $group_icon
            );
        }

        if (isset($media_url->is_favorites)) {
            $initial_group_id = null;
            $initial_is_favorite = 1;
        } else {
            $initial_group_id = (string)$media_url->group_id;
            $initial_is_favorite = 0;
        }

        if (LogSeverity::$is_debug) {
            hd_debug_print("All groups: " . raw_json_encode($groups));
            hd_debug_print("All channels: " . raw_json_encode($all_channels->get_ordered_values()));
        }

        return array(
            PluginTvInfo::show_group_channels_only => true,

            PluginTvInfo::groups => $groups,
            PluginTvInfo::channels => $all_channels->get_ordered_values(),

            PluginTvInfo::favorites_supported => true,
            PluginTvInfo::favorites_icon_url => $this->get_special_group(FAVORITES_GROUP_ID)->get_icon_url(),

            PluginTvInfo::initial_channel_id => (string)$media_url->channel_id,
            PluginTvInfo::initial_group_id => $initial_group_id,

            PluginTvInfo::initial_is_favorite => $initial_is_favorite,
            PluginTvInfo::favorite_channel_ids => $this->get_special_group(FAVORITES_GROUP_ID)->get_items_order()->get_order(),

            PluginTvInfo::initial_archive_tm => isset($media_url->archive_tm) ? (int)$media_url->archive_tm : -1,

            PluginTvInfo::epg_font_size => $epg_font_size,

            PluginTvInfo::actions => $this->get_action_map(),
            PluginTvInfo::timer => Action_Factory::timer(1000),
        );
    }

    /**
     * @return Ordered_Array
     */
    public function &get_groups_order()
    {
        return $this->plugin->get_orders(PARAM_GROUPS_ORDER, new Ordered_Array());
    }

    /**
     * @param Ordered_Array $groups_order
     */
    public function set_groups_order($groups_order)
    {
        $this->plugin->set_orders(PARAM_GROUPS_ORDER, $groups_order);
    }

    /**
     * @return Ordered_Array
     */
    public function &get_disabled_group_ids()
    {
        return $this->plugin->get_orders(PARAM_DISABLED_GROUPS, new Ordered_Array());
    }

    /**
     * @param Ordered_Array $groups
     * @return void
     */
    public function set_disabled_group_ids($groups)
    {
        $this->plugin->get_orders(PARAM_DISABLED_GROUPS, $groups);
    }

    /**
     * @return Ordered_Array
     */
    public function &get_disabled_channel_ids()
    {
        return $this->plugin->get_orders(PARAM_DISABLED_CHANNELS);
    }

    /**
     * @param Ordered_Array $channels
     * @return void
     */
    public function set_disabled_channel_ids($channels)
    {
        $this->plugin->set_orders(PARAM_DISABLED_CHANNELS, $channels);
    }

    /**
     * @return Hashed_Array
     */
    public function &get_channels_zoom()
    {
        return $this->plugin->get_setting(PARAM_CHANNELS_ZOOM, new Hashed_Array());
    }

    /**
     * @return string
     */
    public function get_channel_zoom($channel_id)
    {
        $zoom = $this->get_channels_zoom()->get($channel_id);
        return is_null($zoom) ? DuneVideoZoomPresets::not_set : $zoom;
    }

    /**
     * @param string $channel_id
     * @param string|null $preset
     * @return void
     */
    public function set_channel_zoom($channel_id, $preset)
    {
        if ($preset === null) {
            $this->get_channels_zoom()->erase($channel_id);
        } else {
            $this->get_channels_zoom()->set($channel_id, $preset);
        }

        $this->plugin->save_settings();
    }

    /**
     * @return Ordered_Array
     */
    public function &get_channels_for_ext_player()
    {
        return $this->plugin->get_setting(PARAM_CHANNEL_PLAYER, new Ordered_Array());
    }

    /**
     * @return Hashed_Array
     */
    public function &get_known_channels()
    {
        return $this->plugin->get_orders(PARAM_KNOWN_CHANNELS, new Hashed_Array());
    }

    /**
     * @param string $type // new, removed, null or other value - total
     * @return array
     */
    public function get_changed_channels_ids($type = null)
    {
        $known_channels = $this->get_known_channels();
        $all_channels = $this->get_channels();
        if (is_null($all_channels)) {
            return array();
        }

        if ($type === 'new') {
            return array_diff($all_channels->get_order(), $known_channels->get_order());
        }

        if ($type === 'removed') {
            return array_diff($known_channels->get_order(), $all_channels->get_order());
        }

        $new_channels = array_diff($all_channels->get_order(), $known_channels->get_order());
        $removed_channels = array_diff($known_channels->get_order(), $all_channels->get_order());
        return array_merge($new_channels, $removed_channels);
    }

    /**
     * @param string $channel_id
     * @param bool $external
     * @return void
     */
    public function set_channel_for_ext_player($channel_id, $external)
    {
        if ($external) {
            $this->get_channels_for_ext_player()->add_item($channel_id);
        } else {
            $this->get_channels_for_ext_player()->remove_item($channel_id);
        }
    }
}
