<?php
require_once 'lib/hashed_array.php';
require_once 'lib/ordered_array.php';
require_once 'lib/tv/tv.php';
require_once 'lib/tv/default_channel.php';
require_once 'lib/tv/all_channels_group.php';
require_once 'lib/tv/favorites_group.php';
require_once 'lib/tv/history_group.php';
require_once 'lib/tv/default_epg_item.php';
require_once 'starnet_setup_screen.php';

class Starnet_Tv implements Tv, User_Input_Handler
{
    const ID = 'tv';
    const ALL_CHANNEL_GROUP_ICON_PATH = 'plugin_file://icons/all_folder.png';
    const FAV_CHANNEL_GROUP_ICON_PATH = 'plugin_file://icons/favorite_folder.png';
    const PLAYBACK_HISTORY_GROUP_ICON_PATH = 'plugin_file://icons/history_folder.png';

    ///////////////////////////////////////////////////////////////////////

    public static $tvg_id = array('tvg-id', 'tvg-name');
    public static $tvg_archive = array('catchup-days', 'catchup-time', 'timeshift', 'arc-timeshift', 'arc-time', 'tvg-rec');

    ///////////////////////////////////////////////////////////////////////

    /**
     * @var Starnet_Plugin
     */
    protected $plugin;

    /**
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
     * @var Hashed_Array<Channel>
     */
    protected $channels;

    /**
     * @template Group
     * @var Hashed_Array<Group>
     */
    protected $groups;

    /**
     * @template Group
     * @var Hashed_Array<Group>
     */
    protected $special_groups;

    /**
     * @var Ordered_Array
     */
    protected $groups_order;

    /**
     * @var Ordered_Array
     */
    protected $disabled_groups;

    /**
     * @var Ordered_Array
     */
    protected $disabled_channels;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Starnet_Plugin $plugin
     */
    public function __construct(Starnet_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->playback_url_is_stream_url = false;

        User_Input_Handler_Registry::get_instance()->register_handler($this);
    }

    public function get_handler_id()
    {
        return self::ID . '_handler';
    }

    /**
     * @return Hashed_Array<Channel>
     */
    public function get_channels()
    {
        return $this->channels;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $channel_id
     * @return Channel|mixed
     */
    public function get_channel($channel_id)
    {
        $channels = $this->get_channels();
        if ($channels === null) {
            hd_print(__METHOD__ . ": Channels no loaded");
            return null;
        }

        $channel = $this->channels->get($channel_id);

        if (is_null($channel)) {
            hd_print(__METHOD__ . ": Unknown channel: $channel_id");
        }

        return $channel;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @template Group
     * @return  Hashed_Array<Group>
     */
    public function get_groups()
    {
        return $this->groups;
    }

    /**
     * @return Group
     */
    public function get_special_group($id)
    {
        return $this->special_groups->get($id);
    }

    /**
     * @return Ordered_Array
     */
    public function get_groups_order()
    {
        return $this->groups_order;
    }

    /**
     * @return Ordered_Array
     */
    public function get_disabled_groups()
    {
        return $this->disabled_groups;
    }

    /**
     * @param string $group_id
     */
    public function disable_group($group_id)
    {
        $this->disabled_groups->add_item($group_id);
        $this->groups_order->remove_item($group_id);

        if (($group = $this->groups->get($group_id)) !== null) {
            $group->set_disabled(true);
        }
    }

    /**
     * @return Ordered_Array
     */
    public function get_disabled_channels()
    {
        return $this->disabled_channels;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $group_id
     * @return Group|mixed
     */
    public function get_group($group_id)
    {
        $group = $this->groups->get($group_id);
        if (is_null($group)) {
            $group = $this->special_groups->get($group_id);
            if (is_null($group)) {
                hd_print(__METHOD__ . ": Unknown group: $group_id");
                return null;
            }
        }

        return $group;
    }

    /**
     * @param string $fav_op_type
     * @param string $channel_id
     * @param $plugin_cookies
     * @return array
     */
    public function change_favorites($fav_op_type, $channel_id, &$plugin_cookies)
    {
        $favorites = $this->get_favorites();
        switch ($fav_op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                if ($favorites->add_item($channel_id)) {
                    hd_print(__METHOD__ . ": Add channel $channel_id to favorites");
                }
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                if ($favorites->remove_item($channel_id)) {
                    hd_print(__METHOD__ . ": Remove channel $channel_id from favorites");
                }
                break;

            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $favorites->arrange_item($channel_id, Ordered_Array::UP);
                break;

            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $favorites->arrange_item($channel_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEMS_CLEAR:
                hd_print(__METHOD__ . ": Clear favorites");
                $favorites->clear();
                break;
        }

        $media_urls = array(Starnet_Tv_Favorites_Screen::get_media_url_str(),
            Starnet_Tv_Channel_List_Screen::get_media_url_str(ALL_CHANNEL_GROUP_ID));

        Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
        $post_action = Starnet_Epfs_Handler::invalidate_folders($media_urls);

        return Action_Factory::invalidate_folders($media_urls, $post_action);
    }

    /**
     * @return Ordered_Array
     */
    public function get_favorites()
    {
        return $this->get_special_group(FAV_CHANNEL_GROUP_ID)->get_items_order();
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Special method for callback in Ordered_Array
     * @return void
     */
    public function save_favorites_order()
    {
        $this->plugin->set_settings(PARAM_FAVORITES, $this->get_special_group(FAV_CHANNEL_GROUP_ID)->get_items_order());
    }

    /**
     * @param $plugin_cookies
     * @return void
     * @throws Exception
     */
    public function ensure_channels_loaded($plugin_cookies)
    {
        if (!isset($this->channels)) {
            $this->load_channels($plugin_cookies);
        }
    }

    /**
     * @return void
     */
    public function unload_channels()
    {
        $this->channels = null;
        $this->groups = null;
        $this->special_groups = null;
        $this->groups_order = null;
        $this->disabled_groups = null;
        $this->disabled_channels = null;
    }

    /**
     * @param $plugin_cookies
     * @return void
     * @throws Exception
     */
    public function load_channels($plugin_cookies)
    {
        if (!isset($plugin_cookies->pass_sex)) {
            $plugin_cookies->pass_sex = '0000';
        }

        $this->groups = new Hashed_Array();
        $this->channels = new Hashed_Array();
        $this->special_groups = new Hashed_Array();

        $this->groups_order = new Ordered_Array();
        $this->groups_order->set_callback($this->plugin, PARAM_GROUPS_ORDER);

        $this->disabled_groups = new Ordered_Array();
        $this->disabled_groups->set_callback($this->plugin, PARAM_DISABLED_GROUPS);

        $this->disabled_channels = new Ordered_Array();
        $this->disabled_channels->set_callback($this->plugin, PARAM_DISABLED_CHANNELS);

        // first check if playlist in cache
        if (!$this->plugin->init_playlist()) {
            return;
        }

        $m3u_info = $this->plugin->m3u_parser->getM3uInfo();
        $global_catchup = $m3u_info->getCatchup();
        $global_catchup_source = $m3u_info->getCatchupSource();
        $icon_url_base = $m3u_info->getAttribute('url-logo');
        $this->plugin->update_xmltv_source();

        $user_catchup = $this->plugin->get_settings(PARAM_USER_CATCHUP, KnownCatchupSourceTags::cu_unknown);
        if (empty($global_catchup_source) && $user_catchup !== KnownCatchupSourceTags::cu_unknown) {
            $global_catchup_source = $user_catchup;
        }
        // All channels category
        $this->special_groups->put(new All_Channels_Group(
            ALL_CHANNEL_GROUP_ID,
            Default_Dune_Plugin::ALL_CHANNEL_GROUP_CAPTION,
            self::ALL_CHANNEL_GROUP_ICON_PATH));

        // Favorites group
        $favorites_group = new Favorites_Group(
            FAV_CHANNEL_GROUP_ID,
            Default_Dune_Plugin::FAV_CHANNEL_GROUP_CAPTION,
            self::FAV_CHANNEL_GROUP_ICON_PATH);
        $favorites_group->get_items_order()->set_callback($this->plugin, PARAM_FAVORITES);
        $this->special_groups->put($favorites_group);

        // History channels category
        $this->special_groups->put(new History_Group(
            PLAYBACK_HISTORY_GROUP_ID,
            Default_Dune_Plugin::PLAYBACK_HISTORY_CAPTION,
            self::PLAYBACK_HISTORY_GROUP_ICON_PATH));

        $this->groups_order->set_save_delay(true);
        //foreach ($this->groups_order->get_order() as $item) hd_print("loaded: $item");

        // Collect categories from playlist
        $pl_entries = $this->plugin->m3u_parser->getM3uEntries();
        foreach ($pl_entries as $entry) {
            $title = $entry->getGroupTitle();
            if ($this->groups->has($title)) continue;

            // using title as id
            $group_logo = $entry->getAttribute('group-logo');
            $group = new Default_Group(null, $title, empty($group_logo) ? null : $group_logo);
            $adult = (strpos($title, "зрослы") !== false
                || strpos($title, "adult") !== false
                || strpos($title, "18+") !== false
                || strpos($title, "xxx") !== false);

            $group->set_adult($adult);
            if ($this->disabled_groups->in_order($group->get_id())) {
                $group->set_disabled(true);
                hd_print(__METHOD__ . ": Hide  category # $title");
            } else if ($this->groups_order->in_order($group->get_id())) {
                hd_print(__METHOD__ . ": Known category # $title");
            } else {
                hd_print(__METHOD__ . ": New   category # $title");
                $this->groups_order->add_item($title);
            }

            $group->get_items_order()->set_callback($this->plugin, $title . PARAM_CHANNELS_ORDER);
            // disable save
            $group->get_items_order()->set_save_delay(true);
            $this->groups->put($group);
        }
        // enable save
        $this->groups_order->set_save_delay(false);
        $this->groups_order->save();

        // Read channels
        $epg_ids = array();
        $this->channels = new Hashed_Array();
        $number = 0;
        foreach ($pl_entries as $entry) {
            $channel_id = $entry->getEntryId();
            if ($channel_id === null) {
                $channel_id = $entry->getAttribute("tvg-id");
                if ((empty($channel_id)) || $this->channels->has($channel_id)) {
                    $channel_id = $entry->getTitle();
                }
                if ($this->channels->has($channel_id)) {
                    $channel_id = hash('crc32', $entry->getPath());
                }
            }
            // ignore disabled channel
            $channel_name = $entry->getTitle();
            if ($this->disabled_channels->in_order($channel_id)) {
                hd_print(__METHOD__ . ": Channel $channel_name is disabled");
                continue;
            }

            // if group is not registered it was disabled
            $group_title = $entry->getGroupTitle();
            if ($this->groups->has($group_title) === false) {
                hd_print(__METHOD__ . ": Channel $channel_name in disabled group $group_title");
                continue;
            }

            $number++;

            /** @var Channel $channel */
            $channel = $this->channels->get($channel_id);
            if (!is_null($channel)) {
                foreach ($channel->get_groups() as $group) {
                    hd_print(__METHOD__ . ": Channel $channel_name already exist in category: " . $group->get_title() . " (" . $group->get_id() . ")");
                }
            } else {
                //hd_print(__METHOD__ . ": attributes: " . serialize($entry->getAttributes()));
                $icon_url = $entry->getEntryIcon();
                if (empty($icon_url)) {
                    $icon_url = "plugin_file://icons/channel_unset.png";
                } else if (!empty($icon_url_base) && !preg_match("|https?://|", $icon_url)) {
                    $icon_url = $icon_url_base . $icon_url;
                }

                $epg_id = $entry->getAnyAttribute(self::$tvg_id);

                $used_tag = '';
                $archive = (int)$entry->getAnyAttribute(self::$tvg_archive, $used_tag);
                if ($used_tag === 'catchup-time') {
                    $archive /= 86400;
                }

                $archive_url = '';
                if ($archive !== 0) {
                    $catchup = $entry->getCatchup();
                    $archive_url = $entry->getCatchupSource();
                    if (empty($archive_url) && !empty($global_catchup_source)) {
                        $archive_url = $global_catchup_source;
                    }

                    if (empty($archive_url)) {
                        if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_shift, array($catchup, $global_catchup))) {
                            $archive_url = $entry->getPath()
                                . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                . 'utc=${start}&lutc=${timestamp}';
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_timeshift, array($catchup, $global_catchup))) {
                                $archive_url = $entry->getPath()
                                    . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                    . 'timeshift=${start}&timenow=${timestamp}';
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_archive, array($catchup, $global_catchup))) {
                            $archive_url = $entry->getPath()
                                . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                . 'archive=${start}&archive_end=${end}';
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_flussonic, array($catchup, $global_catchup))
                            && preg_match("#^(https?://[^/]+)/([^/]+)/([^/]+)\.(m3u8?|ts)(\?.+=.+)?$#", $entry->getPath(), $m)) {
                                $archive_url = "$m[1]/$m[2]/$m[3]-" . '${start}' . "-14400.$m[4]$m[5]";
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_xstreamcode, array($catchup, $global_catchup))
                            && preg_match("|^(https?://[^/]+)/(?:live/)?([^/]+)/([^/]+)/([^/.]+)(\.m3u8?)?$|", $entry->getPath(), $m)) {
                            $extension = $m[5] ?: '.ts';
                            $archive_url = "$m[1]/timeshift/$m[2]/$m[3]/240/{Y}-{m}-{d}:{H}-{M}/$m[4].$extension";
                        }
                    } else if (!preg_match("|https?://|", $archive_url)){
                        $archive_url = $entry->getPath() . $archive_url;
                    } else {
                        $archive = 0;
                    }
                }

                $protected = false;
                $adult_code = $entry->getAnyAttribute(array("adult", "parent-code", "censored"), $used_tag);
                if ($used_tag === "adult" && (int)$adult_code !== 1) {
                    $adult_code = '';
                }

                /** @var Group $parent_group */
                $parent_group = $this->groups->get($group_title);
                if ((!empty($adult_code)) || (!is_null($parent_group) && $parent_group->is_adult_group())) {
                    $protected = !empty($plugin_cookies->pass_sex);
                }

                $channel = new Default_Channel(
                    $channel_id,
                    $channel_id,
                    $entry->getTitle(),
                    $icon_url,
                    $entry->getPath(),
                    $archive_url,
                    $archive,
                    $number,
                    $epg_id,
                    $channel_name,
                    $protected,
                    (int)$entry->getAttribute('tvg-shift')
                );
                //hd_print("channel: " . str_replace(chr(0), ' ', serialize($channel)));
                $this->channels->put($channel);

                $epg_ids[$epg_id] = '';
                $epg_ids[$channel_name] = '';

                // Link group and channel.
                $channel->add_group($parent_group);
                $parent_group->add_channel($channel);
            }
        }

        // enable save for each group
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $group->get_items_order()->set_save_delay(false);
            $group->get_items_order()->save();
        }

        hd_print(__METHOD__ . ": Loaded: channels: {$this->channels->size()}, groups: {$this->groups_order->size()} ({$this->groups->size()})");

        $this->plugin->epg_man->index_xmltv_file($plugin_cookies, $epg_ids);
    }

    /**
     * @param User_Input_Handler $handler
     * @param $plugin_cookies
     * @return array
     */
    public function reload_channels(User_Input_Handler $handler, &$plugin_cookies)
    {
        hd_print(__METHOD__ . ": Reload channels");
        $this->plugin->clear_playlist_cache();
        $this->unload_channels();
        try {
            $this->load_channels($plugin_cookies);
        } catch (Exception $e) {
            hd_print(__METHOD__ . ": Reload channel list failed: $plugin_cookies->playlist_idx");
            return null;
        }

        Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
        return Action_Factory::invalidate_folders(
            array(
                Starnet_Tv_Groups_Screen::ID,
                Starnet_Tv_Channel_List_Screen::ID,
                Starnet_Playlists_Setup_Screen::ID,
            ),
            Starnet_Epfs_Handler::invalidate_folders(null,
            User_Input_Handler_Registry::create_action($handler, RESET_CONTROLS_ACTION_ID)));
    }

    /**
     * @param string $playback_url
     * @param $plugin_cookies
     * @return string
     */
    public function get_tv_stream_url($playback_url, &$plugin_cookies)
    {
        return $playback_url;
    }

    /**
     * @param string $channel_id
     * @param int $archive_ts
     * @param string $protect_code
     * @param $plugin_cookies
     * @return string
     */
    public function get_tv_playback_url($channel_id, $archive_ts, $protect_code, &$plugin_cookies)
    {
        hd_print(__METHOD__ . ": channel: $channel_id archive_ts: $archive_ts, protect code: $protect_code");

        try {
            $this->ensure_channels_loaded($plugin_cookies);

            $pass_sex = isset($plugin_cookies->pass_sex) ? $plugin_cookies->pass_sex : '0000';
            // get channel by hash
            $channel = $this->get_channel($channel_id);
            if ($protect_code !== $pass_sex && $channel->is_protected()) {
                throw new Exception("Wrong adult password");
            }

            if (!$channel->is_protected()) {
                $now = $channel->has_archive() ? time() : 0;
                $this->plugin->playback_points->push_point($channel_id, ($archive_ts !== -1 ? $archive_ts : $now));
            }

            // update url if play archive or different type of the stream
            $url = $this->plugin->generate_stream_url($archive_ts, $channel);

            $zoom_data = $this->plugin->get_settings(PARAM_CHANNELS_ZOOM, array());
            if (isset($zoom_data[$channel_id])) {
                $zoom_preset = $zoom_data[$channel_id];
                hd_print(__METHOD__ . ": zoom_preset: $zoom_preset");
            } else if (!is_android() && !is_apk()) {
                $zoom_preset = DuneVideoZoomPresets::normal;
                hd_print(__METHOD__ . ": zoom_preset: reset to normal $zoom_preset");
            } else {
                $zoom_preset = '-';
                //hd_print(__METHOD__ . ": zoom_preset: not applicable");
            }

            if ($zoom_preset !== '-') {
                $url .= (strpos($url, "|||dune_params") === false ? "|||dune_params|||" : ",");
                $url .= "zoom:$zoom_preset";
            }

            hd_print(__METHOD__ . ": $url");
        } catch (Exception $ex) {
            hd_print(__METHOD__ . ": Exception: " . $ex->getMessage());
            $url = '';
        }

        return $url;
    }

    /**
     * @param string $channel_id
     * @param integer $day_start_ts
     * @param $plugin_cookies
     * @return array
     */
    public function get_day_epg($channel_id, $day_start_ts, &$plugin_cookies)
    {
        $day_epg = array();

        try {
            // get channel by hash
            $channel = $this->get_channel($channel_id);
        } catch (Exception $ex) {
            hd_print(__METHOD__ . ": Can't get channel with ID: $channel_id");
            return $day_epg;
        }

        // correct day start to local timezone
        $day_start_ts -= get_local_time_zone_offset();

        //hd_print(__METHOD__ . ": day_start timestamp: $day_start_ts (" . format_datetime("Y-m-d H:i", $day_start_ts) . ")");
        $day_epg_items = $this->plugin->epg_man->get_day_epg_items($channel, $day_start_ts, $plugin_cookies);
        if ($day_epg_items !== false) {
            // get personal time shift for channel
            $time_shift = 3600 * ($channel->get_timeshift_hours() + (isset($plugin_cookies->epg_shift) ? $plugin_cookies->epg_shift : 0));
            //hd_print(__METHOD__ . ": EPG time shift $time_shift");
            foreach ($day_epg_items as $time => $value) {
                $tm_start = (int)$time + $time_shift;
                $tm_end = (int)$value[Epg_Params::EPG_END] + $time_shift;
                $day_epg[] = array
                (
                    PluginTvEpgProgram::start_tm_sec => $tm_start,
                    PluginTvEpgProgram::end_tm_sec => $tm_end,
                    PluginTvEpgProgram::name => $value[Epg_Params::EPG_NAME],
                    PluginTvEpgProgram::description => $value[Epg_Params::EPG_DESC],
                );

                //hd_print(format_datetime("m-d H:i", $tm_start) . " - " . format_datetime("m-d H:i", $tm_end) . " {$value[Epg_Params::EPG_NAME]}");
            }
        }

        return $day_epg;
    }

    public function get_program_info($channel_id, $program_ts, $plugin_cookies)
    {
        $program_ts = ($program_ts > 0 ? $program_ts : time());
        hd_print(__METHOD__ . ": for $channel_id at time $program_ts " . format_datetime("Y-m-d H:i", $program_ts));
        $day_start = date("Y-m-d", $program_ts);
        $day_ts = strtotime($day_start) + get_local_time_zone_offset();
        $day_epg = $this->get_day_epg($channel_id, $day_ts, $plugin_cookies);
        foreach ($day_epg as $item) {
            if ($program_ts >= $item[PluginTvEpgProgram::start_tm_sec] && $program_ts < $item[PluginTvEpgProgram::end_tm_sec]) {
                return $item;
            }
        }

        hd_print(__METHOD__ . ": No entries found for time $program_ts");
        return null;
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_tv_info(MediaURL $media_url, &$plugin_cookies)
    {
        $epg_font_size = isset($plugin_cookies->epg_font_size) ? $plugin_cookies->epg_font_size : SetupControlSwitchDefs::switch_normal;
        $show_all = (!isset($plugin_cookies->show_all) || $plugin_cookies->show_all === 'yes');

        //$t = microtime(1);

        $this->ensure_channels_loaded($plugin_cookies);
        $this->playback_runtime = PHP_INT_MAX;

        $channels = array();

        foreach ($this->channels as $channel) {
            if ($channel->is_disabled()) continue;

            $group_id_arr = array();

            if ($show_all) {
                $group_id_arr[] = ALL_CHANNEL_GROUP_ID;
            }

            $in_groups = 0;
            foreach ($channel->get_groups() as $group) {
                if ($group->is_disabled()) continue;

                $group_id_arr[] = $group->get_id();
                $in_groups++;
            }

            if ($in_groups === 0) continue;

            $channels[] = array(
                PluginTvChannel::id => $channel->get_id(),
                PluginTvChannel::caption => $channel->get_title(),
                PluginTvChannel::group_ids => $group_id_arr,
                PluginTvChannel::icon_url => $channel->get_icon_url(),
                PluginTvChannel::number => $channel->get_number(),

                PluginTvChannel::have_archive => $channel->has_archive(),
                PluginTvChannel::is_protected => $channel->is_protected(),

                // set default epg range
                PluginTvChannel::past_epg_days => $channel->get_past_epg_days(),
                PluginTvChannel::future_epg_days => $channel->get_future_epg_days(),

                PluginTvChannel::archive_past_sec => $channel->get_archive_past_sec(),
                PluginTvChannel::archive_delay_sec => (isset($plugin_cookies->delay_time) ? $plugin_cookies->delay_time : 60),

                // Buffering time
                PluginTvChannel::buffering_ms => (isset($plugin_cookies->buf_time) ? $plugin_cookies->buf_time : 1000),
                PluginTvChannel::timeshift_hours => $channel->get_timeshift_hours(),

                PluginTvChannel::playback_url_is_stream_url => $this->playback_url_is_stream_url,
            );
        }

        $groups = array();
        if ($show_all) {
            $group = $this->get_special_group(ALL_CHANNEL_GROUP_ID);
            if (!is_null($group)) {
                $groups[] = array
                (
                    PluginTvGroup::id => $group->get_id(),
                    PluginTvGroup::caption => $group->get_title(),
                    PluginTvGroup::icon_url => $group->get_icon_url()
                );
            }
        }

        /** @var Group $group */
        foreach ($this->get_groups_order()->get_order() as $id) {
            $group = $this->groups->get($id);
            if ($group !== null && !$group->is_disabled()) {
                $groups[] = array
                (
                    PluginTvGroup::id => $group->get_id(),
                    PluginTvGroup::caption => $group->get_title(),
                    PluginTvGroup::icon_url => $group->get_icon_url()
                );
            }
        }

        if (isset($media_url->is_favorites)) {
            $initial_group_id = null;
            $initial_is_favorite = 1;
        } else {
            $initial_group_id = (string)$media_url->group_id;
            $initial_is_favorite = 0;
        }

        //hd_print(__METHOD__ . ': groups: ' . count($groups) . ' channels: ' . count($channels));
        //hd_print(__METHOD__ . ': Info loaded at ' . (microtime(1) - $t) . ' secs');

        return array(
            PluginTvInfo::show_group_channels_only => true,

            PluginTvInfo::groups => $groups,
            PluginTvInfo::channels => $channels,

            PluginTvInfo::favorites_supported => true,
            PluginTvInfo::favorites_icon_url => self::FAV_CHANNEL_GROUP_ICON_PATH,

            PluginTvInfo::initial_channel_id => (string)$media_url->channel_id,
            PluginTvInfo::initial_group_id => $initial_group_id,

            PluginTvInfo::initial_is_favorite => $initial_is_favorite,
            PluginTvInfo::favorite_channel_ids => $this->get_favorites()->get_order(),

            PluginTvInfo::initial_archive_tm => isset($media_url->archive_tm) ? (int)$media_url->archive_tm : -1,

            PluginTvInfo::epg_font_size => $epg_font_size,

            PluginTvInfo::actions => $this->get_action_map(),
            PluginTvInfo::timer => Action_Factory::timer(1000),
        );
    }

    public function get_action_map()
    {
        $actions = array();
        $actions[GUI_EVENT_PLAYBACK_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLAYBACK_STOP);

        return $actions;
    }

    /**
     * @throws Exception
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);

        if (!isset($user_input->control_id)) {
            return null;
        }

        $channel_id = $user_input->plugin_tv_channel_id;

        $this->plugin->playback_points->update_point($channel_id);

        if ($user_input->control_id === GUI_EVENT_PLAYBACK_STOP
            && $this->plugin->new_ui_support
            && (isset($user_input->playback_stop_pressed) || isset($user_input->playback_power_off_needed))) {

            $this->plugin->playback_points->save();
            Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
            return Starnet_Epfs_Handler::invalidate_folders(null,
                Action_Factory::invalidate_folders(array(Starnet_TV_History_Screen::get_media_url_str())));
        }

        return null;
    }
}
