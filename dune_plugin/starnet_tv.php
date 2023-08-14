<?php
require_once 'lib/hashed_array.php';
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
    const CHANNELS_ZOOM = 'channels_zoom';

    ///////////////////////////////////////////////////////////////////////

    public static $tvg_id = array('tvg-id', 'tvg-name');
    public static $catchup = array('catchup', 'catchup-type');
    public static $catchup_source = array('catchup-source', 'catchup-template');
    public static $tvg_logo = array('tvg-logo', 'url-logo');
    public static $tvg_archive = array('catchup-days', 'catchup-time', 'timeshift', 'arc-timeshift', 'arc-time', 'tvg-rec');

    ///////////////////////////////////////////////////////////////////////

    /**
     * @var Starnet_Plugin
     */
    protected $plugin;

    /**
     * @var bool
     */
    protected $show_all_channels_group;

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
     * @var array
     */
    protected $epg_ids;

    /**
     * @template Group
     * @var Hashed_Array<Group>
     */
    protected $groups;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Starnet_Plugin $plugin
     */
    public function __construct(Starnet_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->show_all_channels_group = true;
        $this->playback_url_is_stream_url = false;

        User_Input_Handler_Registry::get_instance()->register_handler($this);
    }

    public function get_handler_id()
    {
        return self::ID . '_handler';
    }

    public function unload_channels()
    {
        unset($this->channels, $this->groups);
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

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $group_id
     * @return Group|mixed
     * @throws Exception
     */
    public function get_group($group_id)
    {
        $g = $this->groups->get($group_id);

        if (is_null($g)) {
            hd_print(__METHOD__ . ": Unknown group: $group_id");
            throw new Exception(__METHOD__ . ": Unknown group: $group_id");
        }

        return $g;
    }

    /**
     * @param string $channel_id
     * @param $plugin_cookies
     * @return bool
     */
    public function is_favorite_channel_id($channel_id, $plugin_cookies)
    {
        $fav_channel_ids = $this->get_fav_channel_ids($plugin_cookies);
        return in_array($channel_id, $fav_channel_ids);
    }

    /**
     * @param string $fav_op_type
     * @param string $channel_id
     * @param $plugin_cookies
     * @return array
     */
    public function change_tv_favorites($fav_op_type, $channel_id, &$plugin_cookies)
    {
        $fav_channel_ids = $this->get_fav_channel_ids($plugin_cookies);

        switch ($fav_op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                if (in_array($channel_id, $fav_channel_ids) === false) {
                    hd_print(__METHOD__ . ": Add channel $channel_id to favorites");
                    $fav_channel_ids[] = $channel_id;
                }
                break;
            case PLUGIN_FAVORITES_OP_REMOVE:
                $k = array_search($channel_id, $fav_channel_ids);
                if ($k !== false) {
                    hd_print(__METHOD__ . ": Remove channel $channel_id from favorites");
                    unset ($fav_channel_ids[$k]);
                }
                break;
            case ACTION_CLEAR_FAVORITES:
                hd_print(__METHOD__ . ": Clear favorites");
                $fav_channel_ids = array();
                break;
            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $k = array_search($channel_id, $fav_channel_ids);
                if ($k !== false && $k !== 0) {
                    hd_print(__METHOD__ . ": Move channel $channel_id up");
                    $t = $fav_channel_ids[$k - 1];
                    $fav_channel_ids[$k - 1] = $fav_channel_ids[$k];
                    $fav_channel_ids[$k] = $t;
                }
                break;
            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $k = array_search($channel_id, $fav_channel_ids);
                if ($k !== false && $k !== count($fav_channel_ids) - 1) {
                    hd_print(__METHOD__ . ": Move channel $channel_id down");
                    $t = $fav_channel_ids[$k + 1];
                    $fav_channel_ids[$k + 1] = $fav_channel_ids[$k];
                    $fav_channel_ids[$k] = $t;
                }
                break;
        }

        $this->set_fav_channel_ids($plugin_cookies, $fav_channel_ids);

        $media_urls = array(Starnet_Tv_Favorites_Screen::get_media_url_str(),
            Starnet_Tv_Channel_List_Screen::get_media_url_str(Default_Dune_Plugin::ALL_CHANNEL_GROUP_ID));

        Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
        $post_action = Starnet_Epfs_Handler::invalidate_folders($media_urls);

        return Action_Factory::invalidate_folders($media_urls, $post_action);
    }

    /**
     * @param $plugin_cookies
     * @return array
     */
    public function get_fav_channel_ids($plugin_cookies)
    {
        $fav_channel_ids = array();

        $favorites = $this->get_fav_cookie($plugin_cookies);
        if (isset($plugin_cookies->{$favorites})) {
            $fav_channel_ids = array_filter(explode(",", $plugin_cookies->{$favorites}));
        }

        return array_unique($fav_channel_ids);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param $plugin_cookies
     * @param array $ids
     */
    public function set_fav_channel_ids($plugin_cookies, $ids)
    {
        $plugin_cookies->{$this->get_fav_cookie($plugin_cookies)} = implode(',', array_unique($ids));
    }

    /**
     * @param $plugin_cookies
     * @return string
     */
    public function get_fav_cookie($plugin_cookies)
    {
        $channel_list = isset($plugin_cookies->playlist_idx) ? $plugin_cookies->playlist_idx : 'default';
        return 'favorite_channels_' . hash('crc32', $channel_list);
    }

    /**
     * @param $plugin_cookies
     * @throws Exception
     */
    public function ensure_channels_loaded($plugin_cookies)
    {
        if (!isset($this->channels)) {
            $this->load_channels($plugin_cookies);
        }
    }

    /**
     * @param $plugin_cookies
     * @throws Exception
     */
    public function load_channels($plugin_cookies)
    {
        if (!isset($plugin_cookies->pass_sex)) {
            $plugin_cookies->pass_sex = '0000';
        }

        // first check if playlist in cache
        if (!$this->plugin->InitPlaylist($plugin_cookies)) {
            return;
        }
        $m3u_parser = $this->plugin->m3u_parser;

        $pl_entries = $m3u_parser->getM3uEntries();
        $m3u_info = $m3u_parser->getM3uInfo();

        $global_catchup = $m3u_info->getAnyAttribute(self::$catchup);
        $global_catchup_source = $m3u_info->getAnyAttribute(self::$catchup_source);
        $icon_url_base = $m3u_info->getAttribute('url-logo');

        if ($this->plugin->get_settings($plugin_cookies, PARAM_EPG_SOURCE) === PARAM_EPG_INTERNAL) {
            $epg_idx = $this->plugin->get_settings($plugin_cookies, PARAM_EPG_INTERNAL_IDX);
            $epg_sources = $m3u_parser->getXmltvSources();
        } else {
            $epg_idx = $this->plugin->get_settings($plugin_cookies, PARAM_EPG_EXTERNAL_IDX);
            $epg_sources = $this->plugin->get_settings($plugin_cookies,PARAM_CUSTOM_XMLTV_SOURCES);
        }

        if (empty($epg_sources) || !isset($epg_sources[$epg_idx])) {
            hd_print("no xmltv source defined for this playlist");
        } else {
            hd_print("xmltv source: " . $epg_sources[$epg_idx]);
            $this->plugin->epg_man->set_xmltv_url($epg_sources[$epg_idx]);
        }

        $this->show_all_channels_group = !isset($plugin_cookies->show_all) || $plugin_cookies->show_all === 'yes';
        $fav_category_id = Default_Dune_Plugin::FAV_CHANNEL_GROUP_ID;
        $this->groups = new Hashed_Array();
        // All channels category
        hd_print(__METHOD__ . ": Using default all channels group, icon: " . Default_Dune_Plugin::ALL_CHANNEL_GROUP_ICON_PATH);
        $all_channels = new All_Channels_Group(
            $this,
            Default_Dune_Plugin::ALL_CHANNEL_GROUP_ID,
            Default_Dune_Plugin::ALL_CHANNEL_GROUP_CAPTION,
            Default_Dune_Plugin::ALL_CHANNEL_GROUP_ICON_PATH);
        $this->groups->put($all_channels);

        // Favorites group
        hd_print(__METHOD__ . ": Using default favorites channels group icon: " . Default_Dune_Plugin::FAV_CHANNEL_GROUP_ICON_PATH);
        $fav_group = new Favorites_Group(
            Default_Dune_Plugin::FAV_CHANNEL_GROUP_ID,
            Default_Dune_Plugin::FAV_CHANNEL_GROUP_CAPTION,
            Default_Dune_Plugin::FAV_CHANNEL_GROUP_ICON_PATH);
        $this->groups->put($fav_group);

        // History channels category
        hd_print(__METHOD__ . ": Using default history channels group, icon: " . Default_Dune_Plugin::PLAYBACK_HISTORY_GROUP_ICON_PATH);
        $history_channels = new History_Group(
            $this,
            Default_Dune_Plugin::PLAYBACK_HISTORY_GROUP_ID,
            Default_Dune_Plugin::PLAYBACK_HISTORY_CAPTION,
            Default_Dune_Plugin::PLAYBACK_HISTORY_GROUP_ICON_PATH);
        $this->groups->put($history_channels);

        // Collect categories from playlist
        $disabled_groups = $this->plugin->get_settings($plugin_cookies,"disabled_groups");
        $id = 1;
        $group_names = array();
        foreach ($pl_entries as $entry) {
            $group_name = $entry->getGroupTitle();
            if (isset($group_names[$group_name]) || in_array(hash('crc32', $group_name), $disabled_groups)) continue;

            $adult = (strpos($group_name, "зрослы") !== false
            || strpos($group_name, "adult") !== false
            || strpos($group_name, "18+") !== false
            || strpos($group_name, "xxx") !== false);

            $group_names[$group_name] = $id;
            $this->groups->put(new Default_Group((string)$id++, $group_name, '', $adult));
            hd_print(__METHOD__ . ": Added category: $group_name");
        }

        $fav_channel_ids = $this->get_fav_channel_ids($plugin_cookies);

        // Read channels
        $disabled_channels = $this->plugin->get_settings($plugin_cookies,"disabled_channels");
        $this->epg_ids = array();
        $this->channels = new Hashed_Array();
        $number = 0;
        foreach ($pl_entries as $entry) {

            $channel_id = $entry->getEntryId();
            $hash = hash('crc32', $channel_id);
            // ignore disabled channel
            $channel_name = $entry->getTitle();
            if (!in_array($hash, $disabled_channels)) {
                hd_print(__METHOD__ . ": Channel $channel_name is disabled");
                continue;
            }

            // if group is not registered it was disabled
            $group_name = $entry->getGroupTitle();
            if (!isset($group_names[$group_name])) continue;

            $number++;
            $tv_category_id = $group_names[$group_name];

            if ($this->channels->has($hash)) {
                /** @var Channel $channel */
                $channel = $this->channels->get($hash);
                if ($tv_category_id !== $fav_category_id) {
                    foreach($channel->get_groups() as $group) {
                        if ($group->get_id() !== $fav_category_id) {
                            hd_print(__METHOD__ . ": Channel $channel_name already exist in category: " . $group->get_title() . "(" . $group->get_id() . ")");
                        }
                    }
                }
            } else {
                $icon_url = $entry->getAnyAttribute(self::$tvg_logo);
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
                    $catchup = $entry->getAnyAttribute(self::$catchup);
                    $archive_url = $entry->getAnyAttribute(self::$catchup_source);
                    if (empty($archive_url) && !empty($global_catchup_source)) {
                        $archive_url = $global_catchup_source;
                    }

                    if (empty($archive)) {
                        if (($catchup === 'flussonic' || $catchup === 'fs' || $global_catchup === 'flussonic' || $global_catchup === 'fs')
                            && preg_match("|^(https?://[^/]+)/([^/]+)/([^/]+)\.m3u8(\?.+=.+)?$|", $entry->getPath(), $m)) {
                            $archive_url = "$m[1]/$m[2]/$m[3]-" . '${start}' . "-14400.m3u8$m[4]";
                        } else if ($catchup === 'shift' || $global_catchup === 'shift' || $catchup === 'archive' || $global_catchup === 'archive') {
                            $archive_url = $entry->getPath();
                            $archive_url .= ((strpos($archive_url, '?') !== false) ? '&' : '?');
                            $archive_url .= 'utc=${start}&lutc=${timestamp}';
                        } else if (($catchup === 'xc' || $global_catchup === 'xc')
                            && preg_match("|^(https?://[^/]+)/(?:live/)?([^/]+)/([^/]+)/([^/.]+)(\.m3u8?)?$|", $entry->getPath(), $m)) {
                            $extension = $m[5] ?: '.ts';
                            $archive_url = "$m[1]/timeshift/$m[2]/$m[3]/240/{Y}-{m}-{d}:{H}-{M}/$m[4].$extension";
                        }
                    } else if (!preg_match("|https?://|", $archive_url)){
                        $archive_url = $entry->getPath() . $archive_url;
                    }
                }

                $protected = false;
                $adult_code = $entry->getAnyAttribute(array('parent-code', 'censored'));
                /** @var Group $parent_group */
                $parent_group = $this->groups->get($tv_category_id);
                if (!empty($adult_code) || (!is_null($parent_group) && $parent_group->is_adult_group())) {
                    $protected = !empty($plugin_cookies->pass_sex);
                }

                $channel = new Default_Channel(
                    $hash,
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
                $this->channels->put($channel);

                $this->epg_ids[$epg_id] = '';
                $this->epg_ids[$channel_name] = '';

                // Link group and channel.
                $channel->add_group($parent_group);
                $parent_group->add_channel($channel);
            }
        }

        $this->set_fav_channel_ids($plugin_cookies, $fav_channel_ids);

        hd_print(__METHOD__ . ": Loaded: channels: {$this->channels->size()}, groups: {$this->groups->size()}");

        $this->plugin->epg_man->index_xmltv_file($plugin_cookies, $this->epg_ids);
    }

    /**
     * @param User_Input_Handler $handler
     * @param $plugin_cookies
     * @return array
     */
    public function reload_channels(User_Input_Handler $handler, &$plugin_cookies)
    {
        hd_print(__METHOD__ . ": Reload channels");
        $this->plugin->ClearPlaylistCache($plugin_cookies);
        $this->unload_channels();
        try {
            $this->load_channels($plugin_cookies);
        } catch (Exception $e) {
            hd_print(__METHOD__ . ": Reload channel list failed: $plugin_cookies->playlist_idx");
            return null;
        }

        Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
        $post_action = Starnet_Epfs_Handler::invalidate_folders(null,
            User_Input_Handler_Registry::create_action($handler, RESET_CONTROLS_ACTION_ID));

        return Action_Factory::invalidate_folders(array(
            Starnet_Tv_Groups_Screen::ID,
            Starnet_Tv_Channel_List_Screen::ID
        ), $post_action);
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
                Playback_Points::push($channel_id, ($archive_ts !== -1 ? $archive_ts : $now));
            }

            // update url if play archive or different type of the stream
            $url = $this->plugin->GenerateStreamUrl($plugin_cookies, $archive_ts, $channel);

            $zoom_data = HD::get_data_items(self::CHANNELS_ZOOM, true);
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

        //$t = microtime(1);

        $this->ensure_channels_loaded($plugin_cookies);
        $this->playback_runtime = PHP_INT_MAX;

        $channels = array();

        foreach ($this->get_channels() as $channel) {
            $group_id_arr = array();

            if ($this->show_all_channels_group === true) {
                $group_id_arr[] = Default_Dune_Plugin::ALL_CHANNEL_GROUP_ID;
            }

            foreach ($channel->get_groups() as $g) {
                $group_id_arr[] = $g->get_id();
            }

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

        /** @var Default_Group $group */
        foreach ($this->get_groups() as $group) {
            if ($group->is_favorite_group()) {
                continue;
            }

            if ($group->is_history_group()) {
                continue;
            }

            if ($this->show_all_channels_group === false && $group->is_all_channels_group()) {
                continue;
            }

            $groups[] = array
            (
                PluginTvGroup::id => $group->get_id(),
                PluginTvGroup::caption => $group->get_title(),
                PluginTvGroup::icon_url => $group->get_icon_url()
            );
        }

        $is_favorite_group = isset($media_url->is_favorites);
        $initial_group_id = (string)$media_url->group_id;
        $initial_is_favorite = 0;

        if ($is_favorite_group) {
            $initial_group_id = null;
            $initial_is_favorite = 1;
        }

        $fav_channel_ids = $this->get_fav_channel_ids($plugin_cookies);

        //hd_print(__METHOD__ . ': Info loaded at ' . (microtime(1) - $t) . ' secs');

        return array(
            PluginTvInfo::show_group_channels_only => true,

            PluginTvInfo::groups => $groups,
            PluginTvInfo::channels => $channels,

            PluginTvInfo::favorites_supported => true,
            PluginTvInfo::favorites_icon_url => Default_Dune_Plugin::FAV_CHANNEL_GROUP_ICON_PATH,

            PluginTvInfo::initial_channel_id => (string)$media_url->channel_id,
            PluginTvInfo::initial_group_id => $initial_group_id,

            PluginTvInfo::initial_is_favorite => $initial_is_favorite,
            PluginTvInfo::favorite_channel_ids => $fav_channel_ids,

            PluginTvInfo::initial_archive_tm => isset($media_url->archive_tm) ? (int)$media_url->archive_tm : -1,

            PluginTvInfo::epg_font_size => $epg_font_size,

            PluginTvInfo::actions => $this->get_action_map(),
            PluginTvInfo::timer => Action_Factory::timer(1000),
        );
    }

    public function get_action_map()
    {
        $actions = array();
        //$actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ZOOM_MENU);
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

        Playback_Points::update($channel_id);

        switch ($user_input->control_id) {
            case GUI_EVENT_PLAYBACK_STOP:
                if (!$this->plugin->new_ui_support
                    || !(isset($user_input->playback_stop_pressed) || isset($user_input->playback_power_off_needed))) break;

                Playback_Points::save(smb_tree::get_folder_info($plugin_cookies, PARAM_HISTORY_PATH));
                Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
                return Starnet_Epfs_Handler::invalidate_folders(null,
                    Action_Factory::invalidate_folders(array(Starnet_TV_History_Screen::get_media_url_str())));

            case ACTION_ZOOM_MENU:
                $attrs['dialog_params']['frame_style'] = DIALOG_FRAME_STYLE_GLASS;
                $zoom_data = HD::get_data_items(self::CHANNELS_ZOOM, true);
                $dune_zoom = isset($zoom_data[$channel_id]) ? $zoom_data[$channel_id] : DuneVideoZoomPresets::not_set;

                $defs = array();
                Control_Factory::add_label($defs,'', TR::t('tv_screen_switch_channel'));
                Control_Factory::add_combobox($defs, $this, null, ACTION_ZOOM_SELECT, "",
                    $dune_zoom, DuneVideoZoomPresets::$zoom_ops, 1000, true);
                Control_Factory::add_button_close ($defs, $this, null,ACTION_ZOOM_APPLY,
                    "", TR::t('apply'), 600);
                return Action_Factory::show_dialog(TR::t('tv_screen_zoom_channel'), $defs,true,0, $attrs);

            case ACTION_ZOOM_APPLY:
                $zoom_data = HD::get_data_items(self::CHANNELS_ZOOM, true);
                if ($user_input->{ACTION_ZOOM_SELECT} === DuneVideoZoomPresets::not_set) {
                    $zoom_preset = DuneVideoZoomPresets::normal;
                    hd_print(__METHOD__ . ": Zoom preset removed for channel: $channel_id ($zoom_preset)");
                    unset ($zoom_data[$channel_id]);
                } else {
                    $zoom_preset = $zoom_data[$channel_id] = $user_input->{ACTION_ZOOM_SELECT};
                    hd_print(__METHOD__ . ": Zoom preset $zoom_preset for channel: $channel_id");
                }

                HD::put_data_items(self::CHANNELS_ZOOM, $zoom_data);
                //set_video_zoom(get_zoom_value($zoom_preset));
                break;
        }

        return null;
    }
}
