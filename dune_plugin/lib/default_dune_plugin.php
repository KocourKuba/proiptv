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

require_once 'tr.php';
require_once 'mediaurl.php';
require_once 'control_factory_ext.php';
require_once 'default_archive.php';
require_once 'catchup_params.php';
require_once 'named_storage.php';
require_once 'api/api_default.php';
require_once 'm3u/M3uParser.php';
require_once 'm3u/M3uTags.php';
require_once 'lib/ui_parameters.php';
require_once 'lib/epg/epg_manager_json.php';
require_once 'lib/perf_collector.php';
require_once 'lib/smb_tree.php';

class Default_Dune_Plugin extends UI_parameters implements DunePlugin
{
    const AUTHOR_LOGO = "ProIPTV by sharky72  [ ´¯¤¤¯(ºº)¯¤¤¯` ]";
    const CONFIG_URL = 'http://iptv.esalecrm.net/config/providers';
    const ARCHIVE_URL_PREFIX = 'http://iptv.esalecrm.net/res';
    const ARCHIVE_ID = 'common';
    const PARSE_CONFIG = "%s_parse_config.json";

    const PL_ORDERS_DB = 'playlist_orders';
    const TV_HIISTORY_DB = 'tv_history';
    const VOD_HISTORY_DB = 'vod_history';

    protected $parameters_table = 'parameters';
    protected $playlist_table = 'playlist';
    protected $xmltv_table = 'xmltv_sources';

    protected $pl_settings = 'settings';
    protected $pl_xmltv = 'playlist_xmltv';
    protected $pl_sel_xmltv = 'selected_xmltv';
    protected $pl_ch_params = 'channel_params';
    protected $pl_dune_params = 'dune_params';
    protected $pl_cookies = 'cookies';

    protected $pl_groups_parameters = 'playlist_orders.groups_parameters';
    protected $pl_groups_order = 'playlist_orders.groups_order';

    protected $pl_channels = 'playlist_orders.channels';
    protected $vod_history = 'vod_history.history';
    protected $tv_history = 'tv_history.history';

    protected $iptv_channels = M3uParser::CHANNELS_TABLE;
    protected $iptv_groups = M3uParser::GROUPS_TABLE;

    /**
     * @var array
     */
    public $plugin_info;

    /**
     * @var Starnet_Tv
     */
    public $iptv;

    /**
     * @var vod_standard
     */
    public $vod;

    /**
     * @var bool
     */
    protected $vod_enabled = false;

    /**
     * @var bool
     */
    protected $inited = false;

    /**
     * @var Epg_Manager_Xmltv|Epg_Manager_Json
     */
    protected $epg_manager;

    /**
     * @var string
     */
    protected $playback_channel_id;

    /**
     * @var Hashed_Array
     */
    protected $providers;

    /**
     * @var Hashed_Array
     */
    protected $epg_presets;

    /**
     * @var Hashed_Array
     */
    protected $image_libs;

    /**
     * @var api_default
     */
    protected $active_provider;

    /**
     * @var array
     */
    protected $active_playlist = array();

    /**
     * @var string
     */
    protected $channel_id_map = '';

    /**
     * @var M3uParser
     */
    protected $iptv_m3u_parser;

    /**
     * @var M3uParser
     */
    protected $vod_m3u_parser;

    /**
     * @var Sql_Wrapper
     */
    protected $sql_params;

    /**
     * @var Sql_Wrapper
     */
    protected $sql_playlist;

    /**
     * @var Perf_Collector
     */
    protected $perf;

    private $plugin_cookies;
    private $internet_status = -2;
    private $opexec_id = -1;

    ///////////////////////////////////////////////////////////////////////

    public function __construct()
    {
        if (is_newer_versions()) {
            ini_set('memory_limit', '384M');
        }

        $this->plugin_info = get_plugin_manifest_info();
        $this->providers = new Hashed_Array();
        $this->epg_presets = new Hashed_Array();
        $this->image_libs = new Hashed_Array();
        $this->perf = new Perf_Collector();
        $this->iptv_m3u_parser = new M3uParser();
        $this->vod_m3u_parser = new M3uParser();
    }

    public function get_plugin_cookies()
    {
        return $this->plugin_cookies;
    }

    public function set_plugin_cookies(&$plugin_cookies)
    {
        hd_debug_print(null, true);
        $this->plugin_cookies = $plugin_cookies;
    }

    public function set_plugin_cookie($name, $value)
    {
        hd_debug_print("set_plugin_cookie: $name = $value", true);
        return $this->plugin_cookies->{$name} = $value;
    }

    public function get_internet_status()
    {
        return $this->internet_status;
    }

    public function set_internet_status($internet_status)
    {
        $this->internet_status = $internet_status;
    }

    public function get_opexec_id()
    {
        return $this->opexec_id;
    }

    public function set_opexec_id($opexec_id)
    {
        $this->opexec_id = $opexec_id;
    }

    public function get_sql_playlist()
    {
        return $this->sql_playlist;
    }

    /**
     * @return Hashed_Array<array>
     */
    public function get_epg_presets()
    {
        return $this->epg_presets;
    }

    /**
     * @return M3uParser
     */
    public function get_vod_m3u_parser()
    {
        return $this->vod_m3u_parser;
    }

    /**
     * @return M3uParser
     */
    public function get_iptv_m3u_parser()
    {
        return $this->iptv_m3u_parser;
    }

    /**
     * @return Hashed_Array<api_default>
     */
    public function get_providers()
    {
        return $this->providers;
    }

    /**
     * @return bool
     */
    public function is_vod_enabled()
    {
        return $this->vod_enabled;
    }

    /**
     * @param string $name
     * @return api_default|null
     */
    public function create_provider_class($name)
    {
        if (empty($name)) {
            return null;
        }

        $config = $this->providers->get($name);
        return is_null($config) ? null : clone $config;
    }

    /**
     * @return api_default|null
     */
    public function get_active_provider()
    {
        hd_debug_print(null, true);

        if (empty($this->active_playlist) || $this->active_playlist[PARAM_TYPE] !== PARAM_PROVIDER) {
            return null;
        }

        if (is_null($this->active_provider)) {
            $this->active_provider = $this->create_provider_class($this->active_playlist[PARAM_PARAMS][PARAM_PROVIDER]);
            if (is_null($this->active_provider)) {
                hd_debug_print("unknown provider class: " . $this->active_playlist[PARAM_PARAMS][PARAM_PROVIDER]);
                return null;
            }

            $id = $this->active_provider->getId();
            if (!$this->active_provider->getEnable()) {
                hd_debug_print("provider $id is disabled");
            } else {
                $this->active_provider->set_provider_playlist_info($this->get_active_playlist_key(), $this->active_playlist);
            }

            $name = $this->active_provider->getName();
            $playlist_id = $this->active_provider->get_provider_playlist_id();
            hd_debug_print("Using provider $id ($name) - playlist id: $playlist_id");
            if (!$this->active_provider->request_provider_token()) {
                hd_debug_print("Can't get provider token");
            }
        }

        return $this->active_provider;
    }

    /**
     * @return array
     */
    public function get_active_playlist()
    {
        if (empty($this->active_playlist)) {
            $this->active_playlist = $this->get_playlist($this->get_active_playlist_key());
        }

        return $this->active_playlist;
    }

    /**
     * @return string
     */
    public function get_active_playlist_key()
    {
        $id = $this->get_parameter(PARAM_CUR_PLAYLIST_ID);
        if (empty($id) || ($this->get_playlist($id) === null && $this->get_all_playlists_count())) {
            $this->set_active_playlist_key($this->get_all_playlists()->key());
        }

        return $id;
    }

    /**
     * $param string $id
     * @return void
     */
    public function set_active_playlist_key($id)
    {
        hd_debug_print(null, true);

        $this->set_parameter(PARAM_CUR_PLAYLIST_ID, $id);
        $this->active_provider = null;
        $this->active_playlist = $this->get_playlist($id);
    }

    /**
     * @return Hashed_Array
     */
    public function get_all_playlists()
    {
        // (id INTEGER PRIMARY_KEY AUTOINCREMENT, db_name TEXT UNIQUE, name TEXT, params TEXT);

        $rows = $this->sql_params->fetch_array("SELECT * FROM $this->playlist_table ORDER BY id;");
        $playlists = new Hashed_Array();
        foreach ($rows as $row) {
            $stg[PARAM_NAME] = $row[PARAM_NAME];
            $stg[PARAM_TYPE] = $row[PARAM_TYPE];
            $stg[PARAM_PARAMS] = json_decode($row[PARAM_PARAMS], true);
            $playlists->set($row['playlist_id'], $stg);
        }

        return $playlists;
    }

    /**
     * @return int
     */
    public function get_all_playlists_count()
    {
        return $this->sql_params->query_value("SELECT count(*) FROM $this->playlist_table;");
    }

    /**
     * @param string $id
     * @return array
     */
    public function get_playlist($id)
    {
        // (id INTEGER PRIMARY_KEY AUTOINCREMENT, playlist_id TEXT UNIQUE, name TEXT, type TEXT, params TEXT);

        $q_key = Sql_Wrapper::sql_quote($id);
        $row = $this->sql_params->query_value("SELECT * FROM $this->playlist_table WHERE playlist_id = $q_key LIMIT 1;", true);
        if (empty($row)) {
            return null;
        }

        $stg[PARAM_NAME] = $row[PARAM_NAME];
        $stg[PARAM_TYPE] = $row[PARAM_TYPE];
        $stg[PARAM_PARAMS] = json_decode($row['params'], true);
        return $stg;
    }

    /**
     * @param string $id
     * @param array $stg
     * @return void
     */
    public function set_playlist($id, $stg)
    {
        // 'playlists' (id INTEGER PRIMARY_KEY AUTOINCREMENT, playlist_id TEXT UNIQUE, name TEXT, type TEXT, params TEXT);

        $q_key = Sql_Wrapper::sql_quote($id);
        $q_type = Sql_Wrapper::sql_quote($stg[PARAM_TYPE]);
        $q_name = Sql_Wrapper::sql_quote($stg[PARAM_NAME]);
        $q_params = Sql_Wrapper::sql_quote(json_encode($stg[PARAM_PARAMS]));
        $this->sql_params->exec("INSERT OR REPLACE INTO $this->playlist_table (playlist_id, name, type, params) VALUES ($q_key, $q_name, $q_type, $q_params);");
    }

    /**
     * @param string $playlist_id
     * @param int $direction
     * @return bool
     */
    public function arrange_playlist_order_rows($playlist_id, $direction)
    {
        return $this->arrange_rows('playlists', 'playlist_id', $playlist_id, $direction);
    }

    ///////////////////////////////////////////////////////////////////////////
    // EPG Manager

    /**
     * @return Epg_Manager_Xmltv|Epg_Manager_Json
     */
    public function &get_epg_manager()
    {
        return $this->epg_manager;
    }

    /**
     * clear memory cache and entire cache folder for selected hash
     * if hash is empty clear all cache
     *
     * @param string|null $hash
     * @return void
     */
    public function safe_clear_selected_epg_cache($hash = null)
    {
        hd_debug_print(null, true);
        if (isset($this->epg_manager)) {
            $this->epg_manager->clear_epg_files($hash);
        }
    }

    /**
     * clear cache for JSON epg manager
     *
     * @return void
     */
    public function safe_clear_current_epg_cache()
    {
        hd_debug_print(null, true);
        if (isset($this->epg_manager)) {
            $this->epg_manager->clear_current_epg_cache();
        }
    }

    ///////////////////////////////////////////////////////////////////////////
    //
    // DunePlugin implementations
    //
    ///////////////////////////////////////////////////////////////////////


    /**
     * @override DunePlugin
     * @param Object $user_input
     * @param Object $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        return User_Input_Handler_Registry::get_instance()->handle_user_input($user_input, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param Object $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_folder_view($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $decoded_media_url = MediaURL::decode($media_url);
        return $this->get_screen_by_url($decoded_media_url)->get_folder_view($decoded_media_url, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param Object $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_next_folder_view($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $decoded_media_url = MediaURL::decode($media_url);

        return $this->get_screen_by_url($decoded_media_url)->get_next_folder_view($decoded_media_url, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param int $from_ndx
     * @param Object $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_regular_folder_items($media_url, $from_ndx, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $decoded_media_url = MediaURL::decode($media_url);

        return $this->get_screen_by_url($decoded_media_url)->get_folder_range($decoded_media_url, $from_ndx, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param Object $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_tv_info($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (is_null($this->iptv)) {
            hd_debug_print("TV is not supported");
            print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $this->iptv->get_tv_info(MediaURL::decode($media_url), $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param Object $plugin_cookies
     * @return string
     * @throws Exception
     */
    public function get_tv_stream_url($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (is_null($this->iptv)) {
            hd_debug_print("TV is not supported");
            print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $media_url;
    }

    /**
     * @override DunePlugin
     * @param string $channel_id
     * @param int $archive_tm_sec
     * @param string $protect_code
     * @param Object $plugin_cookies
     * @return string
     * @throws Exception
     */
    public function get_tv_playback_url($channel_id, $archive_tm_sec, $protect_code, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (is_null($this->iptv)) {
            hd_debug_print("TV is not supported");
            print_backtrace();
            throw new Exception('TV is not supported');
        }

        try {
            if (!$this->load_channels($plugin_cookies)) {
                throw new Exception("Channels not loaded!");
            }

            $pass_sex = $this->get_parameter(PARAM_ADULT_PASSWORD, '0000');
            $channel_row = $this->get_channel_info($channel_id, true);
            if (empty($channel_row)) {
                throw new Exception("Unknown channel");
            }

            if ($channel_row['adult'] && !empty($pass_sex)) {
                if ($protect_code !== $pass_sex) {
                    throw new Exception("Wrong adult password: $protect_code");
                }
            } else {
                $now = $channel_row['archive'] > 0 ? time() : 0;
                $this->push_tv_history($channel_id, ($archive_tm_sec !== -1 ? $archive_tm_sec : $now));
            }

            $url = $this->generate_stream_url($channel_row, $archive_tm_sec);
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            $url = '';
        }

        hd_debug_print($url);
        return $url;
    }

    /**
     * @override DunePlugin
     * @param string $channel_id
     * @param int $program_ts
     * @param Object $plugin_cookies
     * @return mixed|null
     */
    public function get_program_info($channel_id, $program_ts, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $program_ts = ($program_ts > 0 ? $program_ts : time());
        hd_debug_print("channel ID: $channel_id at time $program_ts " . format_datetime("Y-m-d H:i", $program_ts), true);
        $day_epg = $this->get_day_epg($channel_id,
            strtotime(date("Y-m-d", $program_ts)) + get_local_time_zone_offset(),
            $plugin_cookies);

        foreach ($day_epg as $item) {
            if ($program_ts >= $item[PluginTvEpgProgram::start_tm_sec] && $program_ts < $item[PluginTvEpgProgram::end_tm_sec]) {
                return $item;
            }
        }

        hd_debug_print("No entries found for time $program_ts");
        return null;
    }

    /**
     * @override DunePlugin
     * @param string $channel_id
     * @param int $day_start_tm_sec
     * @param Object $plugin_cookies
     * @return array
     */
    public function get_day_epg($channel_id, $day_start_tm_sec, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $day_epg = array();
        try {
            if (is_null($this->iptv)) {
                hd_debug_print("TV is not supported");
                print_backtrace();
                throw new Exception('TV is not supported');
            }

            if (is_null($channel_id)) {
                throw new Exception('Unknown channel id');
            }

            // get channel by hash
            $channel_row = $this->get_channel_info($channel_id, true);
            if (empty($channel_row)) {
                throw new Exception('Unknown channel');
            }

            if (LogSeverity::$is_debug) {
                hd_debug_print("day_start timestamp: $day_start_tm_sec ("
                    . format_datetime("Y-m-d H:i", $day_start_tm_sec) . ") TZ offset: "
                    . get_local_time_zone_offset());
            }

            // correct day start to local timezone
            $day_start_tm_sec -= get_local_time_zone_offset();

            // get personal time shift for channel
            $time_shift = 3600 * ($channel_row['timeshift'] + $this->get_setting(PARAM_EPG_SHIFT, 0));
            hd_debug_print("EPG time shift $time_shift", true);
            $day_start_tm_sec += $time_shift;

            $items = $this->epg_manager->get_day_epg_items($channel_row, $day_start_tm_sec);

            foreach ($items as $time => $value) {
                if (isset($value[Epg_Params::EPG_END], $value[Epg_Params::EPG_NAME], $value[Epg_Params::EPG_DESC])) {
                    $tm_start = (int)$time + $time_shift;
                    $tm_end = (int)$value[Epg_Params::EPG_END] + $time_shift;
                    $day_epg[] = array(
                        PluginTvEpgProgram::start_tm_sec => $tm_start,
                        PluginTvEpgProgram::end_tm_sec => $tm_end,
                        PluginTvEpgProgram::name => $value[Epg_Params::EPG_NAME],
                        PluginTvEpgProgram::description => $value[Epg_Params::EPG_DESC],
                    );

                    if (LogSeverity::$is_debug) {
                        hd_debug_print(format_datetime("m-d H:i", $tm_start)
                            . " - " . format_datetime("m-d H:i", $tm_end)
                            . " {$value[Epg_Params::EPG_NAME]}", true);
                    }
                } else {
                    hd_debug_print("malformed epg data: " . pretty_json_format($value));
                }
            }
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        return $day_epg;
    }

    /**
     * @override DunePlugin
     * @param string $op_type
     * @param string $channel_id
     * @param Object $plugin_cookies
     * @return array
     */
    public function change_tv_favorites($op_type, $channel_id, &$plugin_cookies = null)
    {
        hd_debug_print(null, true);

        if (is_null($this->iptv)) {
            hd_debug_print("TV is not supported");
            print_backtrace();
            return array();
        }

        hd_debug_print(null, true);

        switch ($op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                hd_debug_print("Add channel $channel_id to favorites", true);
                $this->change_channels_order(FAV_CHANNELS_GROUP_ID, $channel_id, false);
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                hd_debug_print("Remove channel $channel_id from favorites", true);
                $this->change_channels_order(FAV_CHANNELS_GROUP_ID, $channel_id, true);
                break;

            case ACTION_ITEM_UP:
            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $this->arrange_channels_order_rows(FAV_CHANNELS_GROUP_ID, $channel_id, Ordered_Array::UP);
                break;

            case ACTION_ITEM_DOWN:
            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $this->arrange_channels_order_rows(FAV_CHANNELS_GROUP_ID, $channel_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEM_TOP:
                $this->arrange_channels_order_rows(FAV_CHANNELS_GROUP_ID, $channel_id, Ordered_Array::TOP);
                break;

            case ACTION_ITEM_BOTTOM:
                $this->arrange_channels_order_rows(FAV_CHANNELS_GROUP_ID, $channel_id, Ordered_Array::BOTTOM);
                break;

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("Clear favorites", true);
                $this->remove_channels_order(FAV_CHANNELS_GROUP_ID);
                break;
        }

        $player_state = get_player_state_assoc();
        if (isset($player_state['playback_state']) && $player_state['playback_state'] === PLAYBACK_PLAYING) {
            return Action_Factory::invalidate_folders(array(), null, true);
        }

        return null;
    }

    /**
     * @param string $fav_op_type
     * @param string $movie_id
     */
    public function change_vod_favorites($fav_op_type, $movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("action: $fav_op_type, moive id: $movie_id", true);

        switch ($fav_op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                if ($this->change_channels_order(FAV_MOVIE_GROUP_ID, $movie_id, false)) {
                    hd_debug_print("Movie id: $movie_id added to favorites");
                }
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                if ($this->change_channels_order(FAV_MOVIE_GROUP_ID, $movie_id, true)) {
                    hd_debug_print("Movie id: $movie_id removed from favorites");
                }
                break;

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("Movie favorites cleared");
                $this->remove_channels_order(FAV_MOVIE_GROUP_ID);
                break;

            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $this->arrange_channels_order_rows(FAV_MOVIE_GROUP_ID, $movie_id, Ordered_Array::UP);
                break;

            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $this->arrange_channels_order_rows(FAV_MOVIE_GROUP_ID, $movie_id, Ordered_Array::DOWN);
                break;
            default:
        }
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param Object $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_vod_info($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("VOD is not supported");

        print_backtrace();
        throw new Exception("VOD is not supported");
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param Object $plugin_cookies
     * @return string
     */
    public function get_vod_stream_url($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("VOD is not supported");

        return '';
    }

    /**
     * Generate url from template with macros substitution
     * Make url ts wrapped
     * @param array $channel_row
     * @param int $archive_ts
     * @return string
     * @throws Exception
     */
    public function generate_stream_url($channel_row, $archive_ts = -1, $clean = false)
    {
        hd_debug_print(null, true);

        hd_debug_print("Generate stream url for channel id: {$channel_row['channel_id']} '{$channel_row['title']}'");

        // replace all macros
        $stream_url = $channel_row['path'];
        if (empty($stream_url)) {
            throw new Exception("Empty url!");
        }

        $force_detect = false;
        $provider = $this->get_active_provider();
        if (!is_null($provider)) {
            if ($provider->getParameter(MACRO_PLAYLIST_ID) !== CUSTOM_PLAYLIST_ID) {
                $url_subst = $provider->getConfigValue(CONFIG_URL_SUBST);
                if (!empty($url_subst)) {
                    $stream_url = preg_replace($url_subst['regex'], $url_subst['replace'], $stream_url);
                    $stream_url = $provider->replace_macros($stream_url);
                }
            }

            $streams = $provider->GetStreams();
            if (!empty($streams)) {
                $idx = $provider->getParameter(MACRO_STREAM_ID);
                $force_detect = ($streams[$idx] === 'MPEG-TS');
            }

            $detect_stream = $provider->getConfigValue(PARAM_DUNE_FORCE_TS);
            if ($detect_stream) {
                $force_detect = $detect_stream;
            }
        }

        if ((int)$archive_ts !== -1) {
            $catchup = $this->get_iptv_m3u_parser()->getM3uInfo()->getCatchupType();

            if (empty($catchup) && !is_null($provider)) {
                $catchup = $provider->getConfigValue(CONFIG_PLAYLIST_CATCHUP);
                hd_debug_print("set catchup params from config: $catchup", true);
            }

            if (empty($catchup) && strpos($stream_url, 'mpegts') !== false) {
                $catchup = ATTR_CATCHUP_FLUSSONIC;
                hd_debug_print("force catchup params for mpegts: $catchup", true);
            }

            $user_catchup = $this->get_setting(PARAM_USER_CATCHUP, ATTR_CATCHUP_UNKNOWN);
            if ($user_catchup !== ATTR_CATCHUP_UNKNOWN) {
                $catchup = $user_catchup;
                hd_debug_print("force set user catchup: $catchup");
            }

            $channel_catchup = $channel_row['catchup'];
            if (!empty($channel_catchup)) {
                // channel catchup override playlist, user and config settings
                $catchup = $channel_catchup;
            } else if (empty($catchup)) {
                $catchup = ATTR_CATCHUP_SHIFT;
            }

            $archive_url = $channel_row['catchup_source'];
            hd_debug_print("using catchup params: $catchup", true);
            if (empty($archive_url)) {
                if (KnownCatchupSourceTags::is_tag(ATTR_CATCHUP_SHIFT, $catchup)) {
                    $archive_url = $stream_url
                        . ((strpos($stream_url, '?') !== false) ? '&' : '?')
                        . 'utc=${start}&lutc=${timestamp}';
                    hd_debug_print("archive url template (shift): $archive_url", true);
                } else if (KnownCatchupSourceTags::is_tag(ATTR_TIMESHIFT, $catchup)) {
                    $archive_url = $stream_url
                        . ((strpos($stream_url, '?') !== false) ? '&' : '?')
                        . 'timeshift=${start}&timenow=${timestamp}';
                    hd_debug_print("archive url template (timeshift): $archive_url", true);
                } else if (KnownCatchupSourceTags::is_tag(ATTR_CATCHUP_ARCHIVE, $catchup)) {
                    $archive_url = $stream_url
                        . ((strpos($stream_url, '?') !== false) ? '&' : '?')
                        . 'archive=${start}&archive_end=${end}';
                    hd_debug_print("archive url template (archive): $archive_url", true);
                } else if (KnownCatchupSourceTags::is_tag(ATTR_CATCHUP_FLUSSONIC, $catchup)
                    && preg_match("#^(https?://[^/]+)/(.+)/([^/.?]+)(\.m3u8)?(\?.+=.+)?$#", $stream_url, $m)) {
                    $params = safe_get_value($m, 5, '');
                    if ($m[3] === 'mpegts') {
                        //$archive_url = "$m[1]/$m[2]/timeshift_abs-" . '${start}' . ".ts$params";
                        $archive_url = "$m[1]/$m[2]/archive-" . '${start}' . "-14400.ts$params";
                    } else {
                        $archive_url = "$m[1]/$m[2]/$m[3]-" . '${start}' . "-14400$m[4]$params";
                    }
                    hd_debug_print("archive url template (flussonic): $archive_url", true);
                } else if (KnownCatchupSourceTags::is_tag(ATTR_CATCHUP_XTREAM_CODES, $catchup)
                    && preg_match("#^(https?://[^/]+)/(?:live/)?([^/]+)/([^/]+)/([^/.]+)(\.m3u8?)?$#", $stream_url, $m)) {
                    $extension = $m[6] ?: '.ts';
                    $archive_url = "$m[1]/timeshift/$m[2]/$m[3]/240/{Y}-{m}-{d}:{H}-{M}/$m[5].$extension";
                    hd_debug_print("archive url template (xtream code): $archive_url", true);
                } else {
                    // if no info about catchup, use 'shift'
                    $archive_url = $stream_url
                        . ((strpos($stream_url, '?') !== false) ? '&' : '?')
                        . 'utc=${start}&lutc=${timestamp}';
                    hd_debug_print("archive url template (default shift): $archive_url", true);
                }
            } else if (!is_proto_http($archive_url)) {
                $archive_url = $stream_url
                    . ((strpos($stream_url, '?') !== false) ? '&' : '?')
                    . ltrim($archive_url, "?");
                hd_debug_print("archive url template (append): $archive_url", true);
            } else {
                hd_debug_print("archive url template (playlist): $archive_url", true);
            }

            $stream_url = $archive_url;

            $replaces = array();
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
            $replaces[catchup_params::CU_YEAR] = $replaces[catchup_params::CU_START_YEAR] = date('Y', $archive_ts);
            $replaces[catchup_params::CU_MONTH] = $replaces[catchup_params::CU_START_MONTH] = date('m', $archive_ts);
            $replaces[catchup_params::CU_DAY] = $replaces[catchup_params::CU_START_DAY] = date('d', $archive_ts);
            $replaces[catchup_params::CU_HOUR] = $replaces[catchup_params::CU_START_HOUR] = date('H', $archive_ts);
            $replaces[catchup_params::CU_MIN] = $replaces[catchup_params::CU_START_MIN] = date('M', $archive_ts);
            $replaces[catchup_params::CU_SEC] = $replaces[catchup_params::CU_START_SEC] = date('S', $archive_ts);
            $replaces[catchup_params::CU_END_YEAR] = date('Y', $now);
            $replaces[catchup_params::CU_END_MONTH] = date('m', $now);
            $replaces[catchup_params::CU_END_DAY] = date('d', $now);
            $replaces[catchup_params::CU_END_HOUR] = date('H', $now);
            $replaces[catchup_params::CU_END_MIN] = date('M', $now);
            $replaces[catchup_params::CU_END_SEC] = date('S', $now);

            hd_debug_print("replaces: " . pretty_json_format($replaces), true);
            foreach ($replaces as $key => $value) {
                if (strpos($stream_url, $key) !== false) {
                    hd_debug_print("replace $key to $value", true);
                    $stream_url = str_replace($key, $value, $stream_url);
                }
            }
        }

        if (!$clean) {
            $dune_params_str = $this->generate_dune_params($channel_row['channel_id'], json_decode($channel_row['ext_params'], true));
            if (!empty($dune_params_str)) {
                $stream_url .= $dune_params_str;
            }

            $detect_ts = $this->get_bool_setting(PARAM_DUNE_FORCE_TS, false) || $force_detect;
            $stream_url = HD::make_ts($stream_url, $detect_ts);
        }

        return $stream_url;
    }

    /**
     * @param string $channel_id
     * @param array $ext_params
     * @return string
     */
    public function generate_dune_params($channel_id, $ext_params)
    {
        if (!$this->get_bool_setting(PARAM_DISABLE_DUNE_PARAMS, false)) {
            $plugin_dune_params = $this->get_dune_params();
            if (!empty($plugin_dune_params)) {
                $plugin_dune_params = array_slice($plugin_dune_params, 0);
            }

            $provider = $this->get_active_provider();
            $provider_dune_params = array();
            if (!is_null($provider)) {
                $provider_dune_params = dune_params_to_array($provider->getConfigValue(PARAM_DUNE_PARAMS));
            }

            $all_params = array_merge($provider_dune_params, $plugin_dune_params);
            $dune_params = array_unique($all_params);
        }

        if (!empty($ext_params[PARAM_EXT_VLC_OPTS])) {
            $ext_vlc_opts = array();
            foreach ($ext_params[PARAM_EXT_VLC_OPTS] as $value) {
                $pair = explode('=', $value);
                $ext_vlc_opts[strtolower(trim($pair[0]))] = trim($pair[1]);
            }

            if (isset($ext_vlc_opts['http-user-agent'])) {
                $dune_params['http_headers'] = "User-Agent: " . rawurlencode($ext_vlc_opts['http-user-agent']);
            }

            if (isset($ext_vlc_opts['dune-params'])) {
                foreach ($ext_vlc_opts['dune-params'] as $param) {
                    $param_pair = explode(':', $param);
                    if (count($param_pair) < 2) continue;

                    $param_pair[0] = trim($param_pair[0]);
                    if (strpos($param_pair[1], ",,") !== false) {
                        $param_pair[1] = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $param_pair[1]);
                    } else {
                        $param_pair[1] = str_replace(",", ",,", $param_pair[1]);
                    }

                    $dune_params[$param_pair[0]] = $param_pair[1];
                }
            }
        }

        if (!empty($ext_params[PARAM_EXT_HTTP])) {
            foreach ($ext_params[PARAM_EXT_HTTP] as $key => $value) {
                $ext_params[TAG_EXTHTTP][strtolower($key)] = $value;
            }

            if (isset($ext_params[TAG_EXTHTTP]['user-agent'])) {
                $ch_useragent = "User-Agent: " . $ext_params[TAG_EXTHTTP]['user-agent'];

                // escape commas for dune_params
                if (strpos($ch_useragent, ",,") !== false) {
                    $ch_useragent = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $ch_useragent);
                } else {
                    $ch_useragent = str_replace(",", ",,", $ch_useragent);
                }

                $ch_useragent = rawurlencode("User-Agent: " . $ch_useragent);
                if (isset($dune_params['http_headers'])) {
                    $dune_params['http_headers'] .= $ch_useragent;
                } else {
                    $dune_params['http_headers'] = $ch_useragent;
                }
            }
        }

        if (HD::get_dune_user_agent() !== HD::get_default_user_agent()) {
            $user_agent = "User-Agent: " . HD::get_dune_user_agent();
            if (!empty($user_agent)) {
                if (!isset($dune_params['http_headers'])) {
                    $dune_params['http_headers'] = $user_agent;
                } else {
                    $pos = strpos($dune_params['http_headers'], "UserAgent:");
                    if ($pos === false) {
                        $dune_params['http_headers'] .= "," . $user_agent;
                    }
                }
            }
        }

        if ($this->get_bool_setting(PARAM_PER_CHANNELS_ZOOM)) {
            $zoom_preset = $this->get_channel_zoom($channel_id);
            if (!is_null($zoom_preset)) {
                if (!is_android()) {
                    $zoom_preset = DuneVideoZoomPresets::normal;
                    hd_debug_print("zoom_preset: reset to normal $zoom_preset");
                }

                if ($zoom_preset !== DuneVideoZoomPresets::not_set) {
                    $dune_params['zoom'] = $zoom_preset;
                }
            }
        }

        if (empty($dune_params)) {
            return "";
        }

        $params = HD::DUNE_PARAMS_MAGIC . str_replace('=', ':', http_build_query($dune_params, null, ','));
        hd_debug_print("dune_params: $params");

        return $params;
    }

    /**
     * @param MediaURL $media_url
     * @param int $archive_ts
     * @throws Exception
     */
    public function tv_player_exec($media_url, $archive_ts = -1)
    {
        if (!$this->get_channel_ext_player($media_url->channel_id)) {
            return Action_Factory::tv_play($media_url);
        }

        $channel_row = $this->get_channel_info($media_url->channel_id, true);
        if (empty($channel_row)) {
            throw new Exception("Unknown channel");
        }

        $url = $this->generate_stream_url($channel_row, $archive_ts, true);
        $cmd = 'am start -d "' . $url . '" -t "video/*" -a android.intent.action.VIEW 2>&1';
        hd_debug_print("play movie in the external player: $cmd");
        exec($cmd, $output);
        hd_debug_print("external player exec result code" . HD::ArrayToStr($output));
        return null;
    }

    /**
     * @return string
     */
    public function get_channel_zoom($channel_id)
    {
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $query = "SELECT zoom FROM $this->pl_ch_params WHERE channel_id = $q_channel_id";
        $value = $this->sql_playlist->query_value($query);
        return empty($value) ? DuneVideoZoomPresets::not_set : $value;
    }

    /**
     * @param string $channel_id
     * @param string|null $preset
     * @return void
     */
    public function set_channel_zoom($channel_id, $preset)
    {
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $q_preset = Sql_Wrapper::sql_quote($preset === null ? "x" : $preset);
        $query = "INSERT OR IGNORE INTO $this->pl_ch_params (channel_id, zoom) VALUES ($q_channel_id, $q_preset);";
        $query .= "UPDATE $this->pl_ch_params SET zoom = $q_preset WHERE channel_id = $q_channel_id;";
        $query .= "DELETE FROM $this->pl_ch_params WHERE zoom = 'x' AND external_player = 0;";
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @return array
     */
    public function get_channels_zoom($group_id)
    {
        $order_table = self::get_table_name($group_id);
        $query = "SELECT ch.channel_id, ch.zoom FROM $this->pl_ch_params AS ch
                    JOIN $order_table AS ord ON ch.channel_id = ord.channel_id;";
        $result = array();
        foreach ($this->sql_playlist->fetch_array($query) as $value) {
            $result[$value['channel_id']] = $value['zoom'];
        }
        return $result;
    }

    /**
    /**
     * @param string $channel_id
     * @param bool $external
     * @return void
     */
    public function set_channel_ext_player($channel_id, $external)
    {
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $q_external = Sql_Wrapper::sql_quote($external ? 1 : 0);
        $query = "INSERT OR IGNORE INTO $this->pl_ch_params (channel_id, external_player) VALUES ($q_channel_id, $q_external);";
        $query .= "UPDATE $this->pl_ch_params SET external_player = $q_external WHERE channel_id = $q_channel_id;";
        $query .= "DELETE FROM $this->pl_ch_params WHERE zoom = 'x' AND external_player = 0;";
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @return bool
     */
    public function get_channel_ext_player($channel_id)
    {
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $query = "SELECT external_player FROM $this->pl_ch_params WHERE channel_id = $q_channel_id";
        $value = $this->sql_playlist->query_value($query);
        return !empty($value);
    }

    ///////////////////////////////////////////////////////////////////////
    // Storages methods
    //

    /**
     * Get all custom table values ordered by ROWID
     *
     * @param string $table
     * @return array
     */
    public function get_all_table_values($table)
    {
        $table_name = self::get_table_name($table);
        return $this->sql_playlist->fetch_array("SELECT * FROM $table_name ORDER BY id ASC");
    }

    /**
     * count values
     *
     * @param string $table
     * @return array
     */
    public function get_all_table_values_count($table)
    {
        $table_name = self::get_table_name($table);
        return $this->sql_playlist->query_value("SELECT count(*) FROM $table_name");
    }

    /**
     * Get ROWID for value
     *
     * @param string $table
     * @param string $value
     * @return array
     */
    public function get_table_value_id($table, $value)
    {
        $table_name = self::get_table_name($table);
        $q_value = Sql_Wrapper::sql_quote($value);
        return $this->sql_playlist->query_value("SELECT id FROM $table_name WHERE item = $q_value");
    }

    /**
     * Get value by ROWID
     *
     * @param string $table
     * @param int $id
     * @return array
     */
    public function get_table_value($table, $id)
    {
        $table_name = self::get_table_name($table);
        return $this->sql_playlist->query_value("SELECT item FROM $table_name WHERE id = $id");
    }

    /**
     * Update or add value
     *
     * @param string $table
     * @param string $value
     * @param int $id
     */
    public function set_table_value($table, $value, $id = -1)
    {
        $table_name = self::get_table_name($table);
        $q_value = Sql_Wrapper::sql_quote($value);
        if ($id === -1) {
            $this->sql_playlist->exec("INSERT OR IGNORE INTO $table_name (item) VALUES ($q_value);");
        } else {
            $this->sql_playlist->exec("UPDATE $table_name SET item = $q_value WHERE id = $id;");
        }
    }

    /**
     * Remove value
     *
     * @param string $table
     * @param string $value
     */
    public function remove_table_value($table, $value)
    {
        $table_name = self::get_table_name($table);
        $q_value = Sql_Wrapper::sql_quote($value);
        $this->sql_playlist->exec("DELETE FROM $table_name WHERE item = $q_value;");
    }

    /**
     * Arrange values
     *
     * @param string $table
     * @param string $item
     * @param int $direction
     * @return bool
     */
    public function arrange_table_values($table, $item, $direction)
    {
        return $this->arrange_rows(self::get_table_name($table), 'item', $item, $direction);
    }

    ////////////////////////////////////////////////////////////////////////////
    /// VOD history

    /**
     * Get VOD history for selected playlist sorted by movie_id and last viewed time_stamp (most recent first)
     *
     * @return array
     */
    public function get_all_vod_history()
    {
        return $this->sql_playlist->fetch_array("SELECT *, MAX(time_stamp) FROM $this->vod_history GROUP BY movie_id ORDER BY time_stamp DESC;");
    }

    /**
     * Get count of VOD history for selected playlist
     *
     * @return int
     */
    public function get_all_history_count()
    {
        return $this->sql_playlist->query_value("SELECT count(DISTINCT movie_id) FROM $this->vod_history;");
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @return array
     */
    public function get_vod_history($movie_id)
    {
        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
        return $this->sql_playlist->fetch_array("SELECT * FROM $this->vod_history WHERE movie_id = $q_movie_id;");
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @param string $series_id
     * @param array $values
     * @return void
     */
    public function set_vod_history($movie_id, $series_id, $values)
    {
        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series_id = Sql_Wrapper::sql_quote($series_id);
        $q_params = SQL_Wrapper::sql_make_list_from_keys($values);
        $q_values = SQL_Wrapper::sql_make_list_from_quoted_values($values);
        $query = "INSERT OR REPLACE INTO $this->vod_history (movie_id, series_id, $q_params)
                    VALUES ($q_movie_id, $q_series_id, $q_values);";
        $this->sql_playlist->exec($query);
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @return int
     */
    public function get_vod_history_count($movie_id)
    {
        $q_id = Sql_Wrapper::sql_quote($movie_id);
        return $this->sql_playlist->query_value("SELECT count(*) FROM $this->vod_history WHERE movie_id = $q_id;");
    }

    /**
     * Get param for movie_id and series_id
     *
     * @param string $movie_id
     * @param string $series_id
     * @param string $param_name
     * @return array
     */
    public function get_vod_history_params($movie_id, $series_id, $param_name = null)
    {
        $q_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series = Sql_Wrapper::sql_quote($series_id);
        if ($param_name === null) {
            $query = "SELECT * FROM $this->vod_history WHERE movie_id = $q_id AND series_id = $q_series;";
        } else {
            $q_param = Sql_Wrapper::sql_quote($param_name);
            $query = "SELECT $q_param FROM $this->vod_history WHERE movie_id = $q_id AND series_id = $q_series;";
        }
        return $this->sql_playlist->query_value($query, $param_name === null);
    }

    /**
     * Remove history by movie_id
     *
     * @param string $movie_id
     */
    public function remove_vod_history($movie_id)
    {
        $q_value = Sql_Wrapper::sql_quote($movie_id);
        $this->sql_playlist->exec("DELETE FROM $this->vod_history WHERE movie_id = $q_value;");
    }

    /**
     * Remove history by movie_id and series_id
     *
     * @param $movie_id
     * @param $series_id
     */
    public function remove_vod_history_part($movie_id, $series_id)
    {
        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series_id = Sql_Wrapper::sql_quote($series_id);
        $this->sql_playlist->exec("DELETE FROM $this->vod_history WHERE movie_id = $q_movie_id AND series_id = $q_series_id;");
    }

    /**
     * Clear all history
     */
    public function clear_all_vod_history()
    {
        $this->sql_playlist->exec("DELETE FROM $this->vod_history;");
    }

    /**
     * @return string
     */
    public function get_history_path()
    {
        $path = smb_tree::get_folder_info($this->get_parameter(PARAM_HISTORY_PATH));
        if (is_null($path)) {
            $path = get_data_path('history');
        } else {
            $path = get_slash_trailed_path($path);
            if ($path === get_data_path() || $path === get_data_path('history/')) {
                // reset old settings to new
                $this->set_parameter(PARAM_HISTORY_PATH, '');
                $path = get_data_path('history');
            }
        }
        hd_debug_print($path, true);

        return rtrim($path, '/');
    }

    /**
     * @param string|null $path
     * @return void
     */
    public function set_history_path($path = null)
    {
        if (is_null($path) || $path === get_data_path('history')) {
            $this->set_parameter(PARAM_HISTORY_PATH, '');
            return;
        }

        create_path($path);
        $this->set_parameter(PARAM_HISTORY_PATH, $path);
    }

    ////////////////////////////////////////////////////////////////////////////
    /// TV history

    /**
     * @return array
     */
    public function get_tv_history()
    {
        $playback_points = array();
        $list = $this->sql_playlist->fetch_array("SELECT * FROM $this->tv_history ORDER BY time_stamp DESC;");
        foreach ($list as $row) {
            $playback_points[$row['channel_id']] = $row[PARAM_TIMESTAMP];
        }

        return $playback_points;
    }

    /**
     * @return int
     */
    public function get_tv_history_count()
    {
        return $this->sql_playlist->query_value("SELECT count(*) FROM $this->tv_history;");
    }

    /**
     * @param string|null $id
     */
    public function update_tv_history($id)
    {
        if ($this->playback_channel_id === null && $id === null)
            return;

        // update point for selected channel
        $id = ($id !== null) ? $id : $this->playback_channel_id;

        if (isset($this->playback_points[$id])) {
            $player_state = get_player_state_assoc();
            if (isset($player_state['playback_state'], $player_state['playback_position'])
                && ($player_state['playback_state'] === PLAYBACK_PLAYING || $player_state['playback_state'] === PLAYBACK_STOPPED)) {

                // if channel does support archive do not update current point
                $this->playback_points[$id] += ($this->playback_points[$id] !== 0) ? $player_state['playback_position'] : 0;
                hd_debug_print("channel_id $id at time mark: {$this->playback_points[$id]}", true);
            }
        }
    }

    /**
     * @param string $channel_id
     * @param integer $archive_ts
     */
    public function push_tv_history($channel_id, $archive_ts)
    {
        $player_state = get_player_state_assoc();
        if (isset($player_state['player_state']) && $player_state['player_state'] !== 'navigator') {
            if (!isset($player_state['last_playback_event']) || ($player_state['last_playback_event'] !== PLAYBACK_PCR_DISCONTINUITY)) {

                //hd_debug_print("channel_id $channel_id time mark: $archive_ts");
                $this->playback_channel_id = $channel_id;
                $q_id = Sql_Wrapper::sql_quote($channel_id);
                $query = "INSERT OR IGNORE INTO $this->tv_history (channel_id, time_stamp) VALUES ($q_id, $archive_ts);";
                $query .= "UPDATE $this->tv_history SET time_stamp = $archive_ts WHERE channel_id = $q_id;";
                $query .= "DELETE FROM $this->tv_history WHERE rowid NOT IN (SELECT rowid FROM $this->tv_history ORDER BY time_stamp DESC LIMIT 7);";
                $this->sql_playlist->exec_transaction($query);

            }
        }
    }

    /**
     * @param string $id
     */
    public function erase_tv_history($id)
    {
        hd_debug_print("erase $id");
        $q_id = Sql_Wrapper::sql_quote($id);
        $this->sql_playlist->exec("DELETE FROM $this->tv_history WHERE channel_id = $q_id;");
    }

    /**
     * @return void
     */
    public function clear_tv_history()
    {
        hd_debug_print();
        $this->sql_playlist->exec("DELETE FROM $this->tv_history;");
    }

    ////////////////////////////////////////////////////////////////////////////
    /// Main methods

    /**
     * @return void
     */
    public function init_plugin($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force: " . var_export($force, true), true);

        if (!$force && $this->inited) {
            return;
        }

        hd_debug_print_separator();

        $this->active_provider = null;
        $this->active_playlist = null;
        $this->reset_playlist_db();

        LogSeverity::$is_debug = true;
        $this->init_parameters();
        $this->update_log_level();

        if ($this->providers->size() === 0) {
            // 1. Check local debug version
            // 2. Try to download from web release version
            // 3. Check previously downloaded web release version
            // 4. Check preinstalled version
            // 5. Houston we have a problem
            $tmp_file = get_install_path("providers_debug.json");
            if (file_exists($tmp_file)) {
                hd_debug_print("Load debug providers configuration: $tmp_file");
                $jsonArray = parse_json_file($tmp_file);
            } else {
                $name = "providers_{$this->plugin_info['app_base_version']}.json";
                $tmp_file = get_data_path($name);
                $serial = get_serial_number();
                if (empty($serial)) {
                    hd_debug_print("Unable to get DUNE serial.");
                    $serial = 'XXXX';
                }
                $ver = $this->plugin_info['app_version'];
                $model = get_product_id();
                $firmware = get_raw_firmware_version();
                $jsonArray = HD::DownloadJson(self::CONFIG_URL . "?ver=$ver&model=$model&firmware=$firmware&serial=$serial");
                if ($jsonArray === false || !isset($jsonArray['providers'])) {
                    if (file_exists($tmp_file)) {
                        hd_debug_print("Load actual providers configuration");
                        $jsonArray = parse_json_file($tmp_file);
                    } else if (file_exists($tmp_file = get_install_path($name))) {
                        hd_debug_print("Load installed providers configuration");
                        $jsonArray = parse_json_file($tmp_file);
                    }
                } else {
                    store_to_json_file($tmp_file, $jsonArray);
                }
            }

            foreach ($jsonArray['plugin_config']['image_libs'] as $key => $value) {
                hd_debug_print("available image lib: $key");
                $this->image_libs->set($key, $value);
            }

            foreach ($jsonArray['epg_presets'] as $key => $value) {
                hd_debug_print("available epg preset: $key");
                $this->epg_presets->set($key, $value);
            }

            if ($jsonArray === false || !isset($jsonArray['providers'])) {
                hd_debug_print("Problem to get providers configuration");
                return;
            }

            foreach ($jsonArray['providers'] as $item) {
                if (!isset($item['id'], $item['enable']) || $item['enable'] === false) continue;

                $api_class = "api_{$item['id']}";
                if (!class_exists($api_class)) {
                    $api_class = 'api_default';
                }

                //hd_debug_print("provider api: $api_class ({$item['name']})");
                /** @var api_default $provider */
                $provider = new $api_class($this);
                foreach ($item as $key => $value) {
                    $words = explode('_', $key);
                    $setter = "set";
                    foreach ($words as $word) {
                        $setter .= ucwords($word);
                    }
                    if (method_exists($provider, $setter)) {
                        $provider->{$setter}($value);
                    } else {
                        hd_debug_print("Unknown method $setter", true);
                    }
                }

                // cache provider logo
                $logo = $provider->getLogo();
                $filename = basename($logo);
                $local_file = get_install_path("logo/$filename");
                if (file_exists($local_file)) {
                    $provider->setLogo("plugin_file://logo/$filename");
                } else {
                    $cached_file = get_cached_image_path($filename);
                    list($res,) = Curl_Wrapper::simple_download_file($logo, $cached_file);
                    if ($res) {
                        $provider->setLogo($cached_file);
                    } else {
                        hd_debug_print("failed to download provider logo: $logo");
                    }
                }
                $this->providers->set($provider->getId(), $provider);
            }
        }

        hd_debug_print("Init plugin done!");
        hd_debug_print_separator();

        $this->inited = true;
    }

    /**
     * @return void
     */
    public function update_log_level()
    {
        set_debug_log($this->get_bool_parameter(PARAM_ENABLE_DEBUG, false));
    }

    /**
     * @return void
     */
    public function init_epg_manager()
    {
        hd_debug_print(null, true);

        $this->epg_manager = null;
        $engine = $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV);
        $provider = $this->get_active_provider();
        if (($engine === ENGINE_JSON) && !is_null($provider)) {
            $preset = $provider->getConfigValue(EPG_JSON_PRESETS);
            if (!empty($preset)) {
                hd_debug_print("Using 'Epg_Manager_Json' cache engine");
                $this->epg_manager = new Epg_Manager_Json();
            }
        }

        if (is_null($this->epg_manager)) {
            hd_debug_print("Using 'Epg_Manager_Xmltv' cache engine");
            $this->epg_manager = new Epg_Manager_Xmltv();
        }

        $this->epg_manager->init_by_plugin($this);
    }

    /**
     * @return string
     */
    public function get_cache_dir()
    {
        $cache_dir = smb_tree::get_folder_info($this->get_parameter(PARAM_CACHE_PATH));
        if (!is_null($cache_dir) && rtrim($cache_dir, '/') === get_data_path(EPG_CACHE_SUBDIR)) {
            $this->set_parameter(PARAM_CACHE_PATH, '');
            $cache_dir = null;
        }

        if (is_null($cache_dir)) {
            $cache_dir = get_data_path(EPG_CACHE_SUBDIR);
        }

        return str_replace("//", "/", $cache_dir);
    }

    /**
     * Returns true if plugin is in VOD mode
     *
     * @return bool
     */
    public function is_vod_playlist()
    {
        if ($this->active_playlist === null) {
            return false;
        }

        return (isset($this->active_playlist[PARAM_PARAMS][PARAM_PL_TYPE]) && $this->active_playlist[PARAM_PARAMS][PARAM_PL_TYPE] === CONTROL_PLAYLIST_VOD);
    }

    /**
     * Initialize and parse selected playlist
     *
     * @param bool $force
     * @return bool
     */
    public function init_playlist_parser($force = false)
    {
        hd_debug_print(null, true);

        $tmp_file = '';
        try {
            if ($this->active_playlist === null) {
                hd_debug_print("Tv playlist not defined");
                throw new Exception("Tv playlist not defined");
            }

            $this->perf->reset('start');

            $this->init_user_agent();

            $tmp_file = $this->get_active_playlist_cache(true);

            if (!$force) {
                $force = $this->is_playlist_cache_expired($tmp_file);
            }

            if ($force !== false) {
                hd_debug_print("m3u playlist: {$this->active_playlist[PARAM_NAME]} ({$this->get_active_playlist_key()})");
                if ($this->active_playlist[PARAM_TYPE] === PARAM_PROVIDER) {
                    $provider = $this->get_active_provider();
                    if (is_null($provider)) {
                        throw new Exception("Unable to init provider to download: " . json_encode($this->active_playlist));
                    }

                    if ($provider->get_provider_info($force) === false) {
                        throw new Exception("Unable to get provider info to download: " . json_encode($this->active_playlist));
                    }

                    hd_debug_print("Load provider playlist to: $tmp_file");
                    $res = $provider->load_playlist($tmp_file);
                    $logfile = $provider->getCurlWrapper()->get_raw_response_headers();
                } else {
                    if ($this->active_playlist[PARAM_TYPE] === PARAM_FILE) {
                        hd_debug_print("m3u copy local file: {$this->active_playlist[PARAM_PARAMS][PARAM_URI]} to $tmp_file");
                        $res = copy($this->active_playlist[PARAM_PARAMS][PARAM_URI], $tmp_file);
                    } else if ($this->active_playlist[PARAM_TYPE] === PARAM_LINK || $this->active_playlist[PARAM_TYPE] === PARAM_CONF) {
                        $playlist_url = $this->active_playlist[PARAM_PARAMS][PARAM_URI];
                        hd_debug_print("m3u download link: $playlist_url");
                        if (!is_proto_http($playlist_url)) {
                            throw new Exception("Incorrect playlist url: $playlist_url");
                        }
                        list($res, $logfile) = Curl_Wrapper::simple_download_file($playlist_url, $tmp_file);
                    } else {
                        throw new Exception("Unknown playlist type");
                    }

                    if (!isset($playlist[PARAM_PARAMS][PARAM_ID_MAPPER]) || $playlist[PARAM_PARAMS][PARAM_ID_MAPPER] === 'by_default') {
                        $this->active_playlist[PARAM_PARAMS][PARAM_ID_MAPPER] = ATTR_CHANNEL_HASH;
                    }
                }

                if (!$res || !file_exists($tmp_file)) {
                    $exception_msg = TR::load_string('err_load_playlist');
                    if ($this->active_playlist[PARAM_TYPE] !== PARAM_FILE && !empty($logfile)) {
                        $exception_msg .= "\n\n$logfile";
                    }
                    throw new Exception($exception_msg);
                }

                $contents = file_get_contents($tmp_file);
                if (strpos($contents, TAG_EXTM3U) === false) {
                    $exception_msg = TR::load_string('err_load_playlist') . "\n\n$contents";
                    throw new Exception($exception_msg);
                }

                $encoding = HD::detect_encoding($contents);
                if ($encoding !== 'utf-8') {
                    hd_debug_print("Fixing playlist encoding: $encoding");
                    $contents = iconv($encoding, 'utf-8', $contents);
                    file_put_contents($tmp_file, $contents);
                }
            }

            $icon_replace_pattern = array();
            if ($this->active_playlist[PARAM_TYPE] === PARAM_PROVIDER) {
                $provider = $this->get_active_provider();
                if (is_null($provider)) {
                    throw new Exception("Unable to init provider");
                }

                if ($provider->get_provider_info($force) === false) {
                    throw new Exception("Unable to get provider info");
                }

                $id_parser = $provider->getConfigValue(CONFIG_ID_PARSER);
                $id_map = $provider->getConfigValue(CONFIG_ID_MAP);

                if ($provider->getParameter(PARAM_REPLACE_ICON, SetupControlSwitchDefs::switch_on) === SetupControlSwitchDefs::switch_off) {
                    $icon_replace_pattern = $provider->getConfigValue(CONFIG_ICON_REPLACE);
                }
            } else {
                $id_parser = '';
                $id_map = safe_get_value($this->active_playlist[PARAM_PARAMS], PARAM_ID_MAPPER, "");
            }

            $this->iptv_m3u_parser->setPlaylist($tmp_file, $force);

            $parser_params = array(
                'id_map' => $id_map,
                'id_parser' => $id_parser,
                'icon_replace_pattern' => $icon_replace_pattern
            );

            if (!empty($id_parser)) {
                hd_debug_print("Using specific ID parser: $id_parser", true);
                $this->channel_id_map = ATTR_CHANNEL_ID;
            }

            if (!empty($id_map)) {
                hd_debug_print("Using specific ID mapping: $id_map", true);
                $this->channel_id_map = $id_map;
            }

            if (empty($id_map) && empty($id_parser)) {
                hd_debug_print("No specific ID mapping or URL parser", true);
                $this->channel_id_map = ATTR_CHANNEL_HASH;
            }

            $this->iptv_m3u_parser->setupParserParameters($parser_params);
            $this->iptv_m3u_parser->parseHeader();
            hd_debug_print("Init playlist done!");
        } catch (Exception $ex) {
            $err = HD::get_last_error($this->get_pl_error_name());
            if (!empty($err)) {
                $err .= "\n\n";
            }
            $err .= $ex->getMessage();
            HD::set_last_error($this->get_pl_error_name(), $err);
            print_backtrace_exception($ex);
            if (isset($playlist[PARAM_TYPE]) && file_exists($tmp_file)) {
                unlink($tmp_file);
            }
            return false;
        }

        return true;
    }

    /**
     * @return void
     */
    public function init_user_agent()
    {
        $user_agent = $this->get_setting(PARAM_USER_AGENT, '');
        if (!empty($user_agent) && $user_agent !== HD::get_default_user_agent()) {
            HD::set_dune_user_agent($user_agent);
        }
    }

    /**
     * @param bool $is_tv
     * @return string
     */
    public function get_active_playlist_cache($is_tv)
    {
        return get_temp_path($this->get_active_playlist_key() . ($is_tv ? "_playlist.m3u8" : "_vod_playlist.m3u8"));
    }

    /**
     * Clear downloaded playlist
     * @param string $playlist_id
     * @return void
     */
    public function clear_playlist_cache($playlist_id = null)
    {
        if ($playlist_id === null) {
            $playlist_id = $this->get_active_playlist_key();
        }

        $this->iptv_m3u_parser->clear_data();
        $tmp_file = get_temp_path($playlist_id . "_playlist.m3u8");
        if (file_exists($tmp_file)) {
            hd_debug_print("clear_playlist_cache: remove $tmp_file");
            unlink($tmp_file);
        }
    }

    /**
     * Initialize and parse selected playlist
     *
     * @return bool
     */
    public function init_vod_playlist()
    {
        hd_debug_print(null, true);

        if ($this->active_playlist === null) {
            hd_debug_print("Playlist not defined");
            return false;
        }

        $provider = null;
        if ($this->active_playlist[PARAM_TYPE] === PARAM_PROVIDER) {
            $provider = $this->get_active_provider();
            if (is_null($provider)) {
                hd_debug_print("Unknown provider");
                return false;
            }

            if (!$provider->hasApiCommand(API_COMMAND_GET_VOD)) {
                hd_debug_print("Failed to get VOD playlist from provider");
                return false;
            }
        } else if (!isset($this->active_playlist[PARAM_PARAMS][PARAM_PL_TYPE])
                    || $this->active_playlist[PARAM_PARAMS][PARAM_PL_TYPE] === CONTROL_PLAYLIST_IPTV) {
            hd_debug_print("Unknown playlist type or IPTV playlist");
            return false;
        }

        $this->init_user_agent();
        $tmp_file = $this->get_active_playlist_cache(false);
        $force = $this->is_playlist_cache_expired($tmp_file);

        try {
            if ($force !== false) {
                if ($provider !== null) {
                    hd_debug_print("download provider vod");
                    $res = $provider->execApiCommand(API_COMMAND_GET_VOD, $tmp_file);
                    if ($res === false) {
                        $exception_msg = TR::load_string('err_load_vod') . "\n\n" . $provider->getCurlWrapper()->get_raw_response_headers();
                        HD::set_last_error($this->get_vod_error_name(), $exception_msg);
                        throw new Exception($exception_msg);
                    }
                } else if ($this->active_playlist[PARAM_TYPE] === PARAM_FILE) {
                    hd_debug_print("m3u copy local file: {$this->active_playlist[PARAM_PARAMS][PARAM_URI]} to $tmp_file");
                    $res = copy($this->active_playlist[PARAM_PARAMS][PARAM_URI], $tmp_file);
                    if ($res === false) {
                        $exception_msg = TR::load_string('err_load_vod') . PHP_EOL . PHP_EOL .
                            "m3u copy local file: {$this->active_playlist[PARAM_PARAMS][PARAM_URI]} to $tmp_file";
                        throw new Exception($exception_msg);
                    }
                } else if ($this->active_playlist[PARAM_TYPE] === PARAM_LINK || $this->active_playlist[PARAM_TYPE] === PARAM_CONF) {
                    $playlist_url = $this->active_playlist[PARAM_PARAMS][PARAM_URI];
                    hd_debug_print("m3u download link: $playlist_url");
                    list($res, $logfile) = Curl_Wrapper::simple_download_file($playlist_url, $tmp_file);
                    if ($res === false) {
                        $exception_msg = TR::load_string('err_load_vod') . "\n\n$logfile";
                        throw new Exception($exception_msg);
                    }
                } else {
                    throw new Exception("Unknown playlist type");
                }

                $playlist_file = file_get_contents($tmp_file);
                if (strpos($playlist_file, TAG_EXTM3U) === false) {
                    $exception_msg = TR::load_string('err_load_vod') . "\n\nPlaylist is not a M3U file\n\n$playlist_file";
                    HD::set_last_error($this->get_vod_error_name(), $exception_msg);
                    throw new Exception($exception_msg);
                }

                $mtime = filemtime($tmp_file);
                hd_debug_print("Stored $tmp_file (timestamp: $mtime)");
            }

            $this->vod_m3u_parser->setVodPlaylist($tmp_file, $force);
        } catch (Exception $ex) {
            hd_debug_print("Unable to load VOD playlist");
            print_backtrace_exception($ex);
            if (file_exists($tmp_file)) {
                unlink($tmp_file);
            }
            return false;
        }

        hd_debug_print("Init VOD playlist done!");
        return true;
    }

    /**
     * get all xmltv source
     *
     * @return Hashed_Array<Named_Storage>
     */
    public function get_all_xmltv_sources()
    {
        hd_debug_print(null, true);

        $xmltv_sources = new Hashed_Array();
        $xmltv_sources->add_items($this->get_playlist_xmltv_sources());
        $xmltv_sources->add_items($this->get_ext_xmltv_sources());

        return $xmltv_sources;
    }

    /**
     * get playlist xmltv source
     *
     * @return Hashed_Array<Named_Storage>
     */
    public function get_playlist_xmltv_sources()
    {
        hd_debug_print(null, true);

        $saved_source = $this->sql_playlist->fetch_array("SELECT * FROM $this->pl_xmltv;");
        $hashes = array();
        foreach ($saved_source as $source) {
            $hashes[$source[PARAM_HASH]] = array(PARAM_NAME => $source[PARAM_NAME], PARAM_URI => $source[PARAM_URI], PARAM_CACHE => $source[PARAM_CACHE]);
        }
        hd_debug_print("saved playlist sources: " . json_encode($hashes), true);

        $playlist_sources = new Hashed_Array();
        $known_sources = array();
        foreach ($this->iptv_m3u_parser->getXmltvSources() as $url) {
            $item = array();
            $hash = Hashed_Array::hash($url);
            $known_sources[] = $hash;
            if (key_exists($hash, $hashes)) {
                $item[PARAM_NAME] = $hashes[$hash][PARAM_NAME];
                $item[PARAM_URI] = $hashes[$hash][PARAM_URI];
                $item[PARAM_CACHE] = $hashes[$hash][PARAM_CACHE];
            } else {
                $item[PARAM_NAME] = basename($url);
                $item[PARAM_URI] = $url;
                $item[PARAM_CACHE] = XMLTV_CACHE_AUTO;
            }
            $item[PARAM_PARAMS][PARAM_HASH] = $hash;

            $playlist_sources->set($hash, $item);
            hd_debug_print("playlist source: ($hash) $url", true);

            if (!empty($known_sources)) {
                $q_type = SQL_Wrapper::sql_quote(PARAM_LINK);
                $q_known_sources = SQL_Wrapper::sql_make_list_from_quoted_values($known_sources);
                $query = "DELETE FROM $this->pl_xmltv WHERE type = $q_type AND hash NOT IN ($q_known_sources);";
                $this->sql_playlist->exec_transaction($query);
            }
        }

        $this->set_playlist_xmltv($playlist_sources);

        return $playlist_sources;
    }

    /**
     * get external xmltv sources
     *
     * @return Hashed_Array
     */
    public function get_ext_xmltv_sources()
    {
        $rows = $this->sql_params->fetch_array("SELECT * FROM $this->xmltv_table;");
        $sources = new Hashed_Array();
        foreach ($rows as $row) {
            $sources->set($row[PARAM_HASH], $row);
        }

        return $sources;
    }

    /**
     * get external xmltv sources count
     *
     * @return int
     */
    public function get_ext_xmltv_sources_count()
    {
        return $this->sql_params->query_value("SELECT (*) FROM $this->xmltv_table;");
    }

    /**
     * get external xmltv sources
     *
     * @param string $hash
     * @return Named_Storage
     */
    public function get_ext_xmltv_source($hash)
    {
        $q_hash = Sql_Wrapper::sql_quote($hash);
        $row = $this->sql_params->query_value("SELECT * FROM $this->xmltv_table WHERE hash = $q_hash;");
        return empty($row) ? null : $row;
    }

    /**
     * get external xmltv sources
     *
     * @param array $value
     * @return void
     */
    public function set_ext_xmltv_source($value)
    {
        $q_list = Sql_Wrapper::sql_make_insert_list($value);
        $query = "INSERT OR REPLACE INTO $this->xmltv_table $q_list;";
        $this->sql_params->exec($query);
    }

    /**
     * get external xmltv sources
     *
     * @param string $hash
     * @return void
     */
    public function remove_ext_xmltv_source($hash)
    {
        $q_hash = Sql_Wrapper::sql_quote($hash);
        $this->sql_params->exec("DELETE FROM $this->xmltv_table WHERE hash = $q_hash;");
    }

    /**
     * @param string $source
     * @param string $hash
     * @param string $cache
     * @return void
     */
    public function update_xmltv_source_cache($source, $hash, $cache)
    {
        if ($source === 'playlist') {
            $this->sql_playlist->exec("UPDATE $this->pl_xmltv SET cache = '$cache' WHERE hash = '$hash';");
        } else {
            $this->sql_params->exec("UPDATE $this->xmltv_table SET cache = '$cache' WHERE hash = '$hash';");
        }
    }

    /**
     * @param array $defs
     */
    public function create_setup_header(&$defs)
    {
        Control_Factory::add_vgap($defs, -10);
        Control_Factory::add_label($defs, self::AUTHOR_LOGO,
            " v.{$this->plugin_info['app_version']} [{$this->plugin_info['app_release_date']}]",
            14);
    }

    /**
     * @param string|$source
     * @return void
     */
    public function run_bg_epg_indexing($source)
    {
        hd_debug_print(null, true);

        $item = $this->get_all_xmltv_sources()->get($source);
        if ($item === null) {
            hd_debug_print("XMLTV source $source not found");
            return;
        }

        if (!isset($item[PARAM_HASH])) {
            $item[PARAM_HASH] = Hashed_Array::hash($item[PARAM_URI]);
        }

        // background indexing performed only for one url!
        hd_debug_print("Run background indexing for: ($source) {$item[PARAM_URI]}");
        $config = array(
            PARAM_ENABLE_DEBUG => LogSeverity::$is_debug,
            PARAM_CACHE_DIR => $this->get_cache_dir(),
            PARAMS_XMLTV => $item
        );

        $config_file = get_temp_path(sprintf(self::PARSE_CONFIG, $source));
        hd_debug_print("Config: " . json_encode($config), true);
        file_put_contents($config_file, pretty_json_format($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $ext_php = get_platform_php();
        $script_path = get_install_path('bin/index_epg.php');
        $log_path = get_temp_path('bg_indexing_error.log');
        export_DuneSystem();

        $cmd = "$ext_php -f $script_path $config_file >$log_path 2>&1 &";
        hd_debug_print("exec: $cmd", true);
        shell_exec($cmd);
    }

    ///////////////////////////////////////////////////////////////////////
    // Misc.

    /**
     * @return Hashed_Array
     */
    public function get_image_libs()
    {
        return $this->image_libs;
    }

    /**
     * @param string $preset_name
     * @return array|null
     */
    public function get_image_lib($preset_name)
    {
        return $this->image_libs->get($preset_name);
    }

    /**
     * @return bool
     */
    public function is_background_image_default()
    {
        return ($this->get_background_image() === $this->plugin_info['app_background']);
    }

    /**
     * @param string|null $path
     * @return void
     */
    public function set_background_image($path)
    {
        if (is_null($path) || $path === $this->plugin_info['app_background'] || !file_exists($path)) {
            $this->set_setting(PARAM_PLUGIN_BACKGROUND, '');
        } else {
            $this->set_setting(PARAM_PLUGIN_BACKGROUND, $path);
        }
    }

    /**
     * @return string
     */
    public function get_background_image()
    {
        $background = $this->get_setting(PARAM_PLUGIN_BACKGROUND, '');
        if ($background === $this->plugin_info['app_background']) {
            $this->set_setting(PARAM_PLUGIN_BACKGROUND, '');
        } else if (strncmp($background, get_cached_image_path(), strlen(get_cached_image_path())) === 0) {
            $this->set_setting(PARAM_PLUGIN_BACKGROUND, basename($background));
        } else if (empty($background) || !file_exists(get_cached_image_path($background))) {
            $background = $this->plugin_info['app_background'];
        } else {
            $background = get_cached_image_path($background);
        }

        return $background;
    }

    public function get_icon($id)
    {
        $archive = $this->get_image_archive();

        return is_null($archive) ? null : $archive->get_archive_url($id);
    }

    public function get_image_archive()
    {
        return Default_Archive::get_image_archive(self::ARCHIVE_ID, self::ARCHIVE_URL_PREFIX);
    }

    public function get_id_column()
    {
        return M3uParser::$id_to_column_mapper[$this->channel_id_map];
    }

    public function make_name($storage, $id = '')
    {
        if (!empty($id)) {
            $id = "_$id";
        }

        return $this->get_active_playlist_key() . "_$storage$id";
    }

    /**
     * @param string $id
     */
    public static function get_group_media_url_str($id)
    {
        switch ($id) {
            case FAV_CHANNELS_GROUP_ID:
                return Starnet_Tv_Favorites_Screen::get_media_url_string(FAV_CHANNELS_GROUP_ID);

            case HISTORY_GROUP_ID:
                return Starnet_Tv_History_Screen::get_media_url_string(HISTORY_GROUP_ID);

            case CHANGED_CHANNELS_GROUP_ID:
                return Starnet_Tv_Changed_Channels_Screen::get_media_url_string(CHANGED_CHANNELS_GROUP_ID);

            case VOD_GROUP_ID:
                return Starnet_Vod_Category_List_Screen::get_media_url_string(VOD_GROUP_ID);

            case FAV_MOVIE_GROUP_ID:
                return Starnet_Vod_Favorites_Screen::get_media_url_string(FAV_MOVIE_GROUP_ID);

            case HISTORY_MOVIES_GROUP_ID:
                return Starnet_Vod_History_Screen::get_media_url_string(HISTORY_MOVIES_GROUP_ID);

            case SEARCH_MOVIES_GROUP_ID:
                return Starnet_Vod_Search_Screen::get_media_url_string(SEARCH_MOVIES_GROUP_ID);

            case FILTER_MOVIES_GROUP_ID:
                return Starnet_Vod_Filter_Screen::get_media_url_string();
        }

        return Starnet_Tv_Channel_List_Screen::get_media_url_string($id);
    }

    ///////////////////////////////////////////////////////////////////////
    // popup menus

    /**
     * @param User_Input_Handler $handler
     * @param string $action_id
     * @param string $caption
     * @param string $icon
     * @param array|null $add_params
     * @return array
     */
    public function create_menu_item($handler, $action_id, $caption = null, $icon = null, $add_params = null)
    {
        if ($action_id === GuiMenuItemDef::is_separator) {
            return array($action_id => true);
        }

        if (!empty($icon)) {
            if (strpos($icon, "://") === false) {
                $icon = get_image_path($icon);
            } else if (strpos($icon, "plugin_file://") === false
                && file_exists(get_cached_image_path(basename($icon)))) {
                $icon = get_cached_image_path(basename($icon));
            }
        }

        return User_Input_Handler_Registry::create_popup_item($handler, $action_id, $caption, $icon, $add_params);
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function epg_source_menu($handler)
    {
        $menu_items = array();

        $provider = $this->get_active_provider();
        if (!is_null($provider) && $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === ENGINE_JSON) {
            $epg_presets = $provider->getConfigValue(EPG_JSON_PRESETS);
            if (!empty($epg_presets)) {
                $current = $this->get_setting(PARAM_EPG_JSON_PRESET, 0);
                foreach ($epg_presets as $key => $epg_preset) {
                    $selected = (int)$key === (int)$current;
                    $menu_items[] = $this->create_menu_item($handler,
                        ACTION_EPG_SOURCE_SELECTED,
                        isset($epg_preset['title']) ? $epg_preset['title'] : $epg_preset['name'],
                        $selected ? "check.png" : null,
                        array(LIST_IDX => $key, IS_LIST_SELECTED => $selected)
                    );
                }
            }
        }

        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function epg_engine_menu($handler)
    {
        $engine = $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV);

        $menu_items[] = $this->create_menu_item($handler, ENGINE_XMLTV, TR::t('setup_epg_cache_xmltv'),
            ($engine === ENGINE_XMLTV) ? "check.png" : null
        );

        $provider = $this->get_active_provider();
        if ($provider !== null) {
            $epg_preset = $provider->getConfigValue(EPG_JSON_PRESETS);
            $preset = $this->get_setting(PARAM_EPG_JSON_PRESET, 0);
            $name = safe_get_value($epg_preset[$preset], 'title', $epg_preset[$preset]['name']);
            $menu_items[] = $this->create_menu_item($handler,
                ENGINE_JSON,
                TR::t('setup_epg_cache_json__1', $name),
                ($engine === ENGINE_JSON) ? "check.png" : null
            );
        }
        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function picons_source_menu($handler)
    {
        $icons_playlist = $this->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS);

        $menu_items[] = $this->create_menu_item($handler, PLAYLIST_PICONS, TR::t('playlist_picons'),
            ($icons_playlist === PLAYLIST_PICONS) ? "check.png" : null);
        $menu_items[] = $this->create_menu_item($handler, XMLTV_PICONS, TR::t('xmltv_picons'),
            ($icons_playlist === XMLTV_PICONS) ? "check.png" : null);
        $menu_items[] = $this->create_menu_item($handler, COMBINED_PICONS, TR::t('combined_picons'),
            ($icons_playlist === COMBINED_PICONS) ? "check.png" : null);
        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $group_id
     * @param bool $is_classic
     * @return array
     */
    public function common_categories_menu($handler, $group_id, $is_classic = true)
    {
        $menu_items = array();
        if ($group_id !== null) {
            if ($group_id === HISTORY_GROUP_ID && $this->get_tv_history_count() !== 0) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_history'), "brush.png");
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            } else if ($group_id === FAV_CHANNELS_GROUP_ID && $this->get_channels_order_count(FAV_CHANNELS_GROUP_ID) !== 0) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "brush.png");
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            } else if ($group_id === CHANGED_CHANNELS_GROUP_ID) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_changed'), "brush.png");
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            }

            if ($is_classic) {
                $menu_items = array_merge($menu_items, $this->edit_hidden_menu($handler, $group_id));
            } else {
                $menu_items[] = $this->create_menu_item($handler, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_group'), "hide.png");
            }

            $menu_items[] = $this->create_menu_item($handler, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");

            $is_special = $this->get_group($group_id, true);
            if (empty($is_special)) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_SORT_POPUP, TR::t('sort_popup_menu'), "sort.png");
            }

            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        }

        if ($is_classic) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_CHANGE_GROUP_ICON, TR::t('change_group_icon'), "image.png");
            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        }

        $menu_items[] = $this->create_menu_item($handler,
            ACTION_ITEMS_EDIT,
            TR::t('setup_channels_src_edit_playlists'),
            "m3u_file.png",
            array(CONTROL_ACTION_EDIT => Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST));

        $menu_items[] = $this->create_menu_item($handler,
            ACTION_ITEMS_EDIT,
            TR::t('setup_edit_xmltv_list'),
            "epg.png",
            array(CONTROL_ACTION_EDIT => Starnet_Edit_List_Screen::SCREEN_EDIT_EPG_LIST));

        if ($is_classic) {
            $menu_items[] = $this->create_menu_item($handler,
                ACTION_RELOAD,
                TR::t('refresh_playlist'),
                "refresh.png",
                array('reload_action' => Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST));
        }

        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

        if ($this->get_all_xmltv_sources()->size() !== 0) {
            $icons_playlist = $this->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS);
            if ($icons_playlist === PLAYLIST_PICONS) {
                $sources = TR::load_string('playlist_picons');
            } else if ($icons_playlist === XMLTV_PICONS) {
                $sources = TR::load_string('xmltv_picons');
            } else {
                $sources = TR::load_string('combined_picons');
            }
            $menu_items[] = $this->create_menu_item($handler, ACTION_CHANGE_PICONS_SOURCE, TR::t('change_picons_source__1', $sources), "image.png");
        }

        $is_xmltv_engine = $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === ENGINE_XMLTV;
        $engine = TR::load_string(($is_xmltv_engine ? 'setup_epg_cache_xmltv' : 'setup_epg_cache_json'));
        $menu_items[] = $this->create_menu_item($handler,
            ACTION_EPG_CACHE_ENGINE, TR::t('setup_epg_cache_engine__1', $engine), "engine.png");

        $provider = $this->get_active_provider();
        if (!is_null($provider) && !$is_xmltv_engine) {
            $epg_presets = $provider->getConfigValue(EPG_JSON_PRESETS);
            if (count($epg_presets) > 1) {
                $preset = $this->get_setting(PARAM_EPG_JSON_PRESET, 0);
                $name = safe_get_value($epg_presets[$preset], 'title', $epg_presets[$preset]['name']);
                $menu_items[] = $this->create_menu_item($handler, ACTION_CHANGE_EPG_SOURCE, TR::t('change_json_epg_source__1', $name), "epg.png");
            }
        }

        if (!is_null($provider)) {
            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            if ($provider->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_INFO_DLG, TR::t('subscription'), "info.png");
            }

            $menu_items[] = $this->create_menu_item($handler,
                ACTION_EDIT_PROVIDER_DLG,
                TR::t('edit_account'),
                $provider->getLogo(),
                array(PARAM_PROVIDER => $provider->getId(), PARAM_PLAYLIST_ID => $provider->get_provider_playlist_id())
            );

            if ($provider->getConfigValue(PROVIDER_EXT_PARAMS) === true) {
                $menu_items[] = $this->create_menu_item($handler,
                    ACTION_EDIT_PROVIDER_EXT_DLG,
                    TR::t('edit_ext_account'),
                    "settings.png",
                    array(PARAM_PROVIDER => $provider->getId(), PARAM_PLAYLIST_ID => $provider->get_provider_playlist_id())
                );
            }
        }

        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

        $menu_items[] = $this->create_menu_item($handler, ACTION_SETTINGS,
            TR::t('entry_setup'), "settings.png");

        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $group_id
     * @param bool $top
     * @return array
     */
    public function edit_hidden_menu($handler, $group_id, $top = true)
    {
        $menu_items = array();

        if ($group_id === null) {
            return $menu_items;
        }

        if ($top) {
            $is_special = $this->get_group($group_id, true);
            if (empty($is_special)) {
                $menu_items[] = $this->create_menu_item($handler,
                    ACTION_ITEM_DELETE,
                    TR::t('tv_screen_hide_group'),
                    "hide.png");
            }

            $cnt = $this->get_groups_count(0, 1);
            hd_debug_print("Disabled groups: $cnt", true);
            if ($cnt !== 0) {
                $menu_items[] = $this->create_menu_item($handler,
                    ACTION_ITEMS_EDIT,
                    TR::t('tv_screen_edit_hidden_group'),
                    "edit.png",
                    array(CONTROL_ACTION_EDIT => Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_GROUPS));
            }
        } else {
            $menu_items[] = $this->create_menu_item($handler,
                ACTION_ITEM_DELETE,
                TR::t('tv_screen_hide_channel'),
                "remove.png");
            $menu_items[] = $this->create_menu_item($handler,
                ACTION_ITEM_DELETE_CHANNELS,
                TR::t('tv_screen_hide_group_channels'),
                "remove.png");
        }

        if ($group_id === ALL_CHANNELS_GROUP_ID) {
            $cnt = $this->get_channels_count(ALL_CHANNELS_GROUP_ID, 1);
        } else {
            $cnt = $this->get_channels_count($group_id, 1);
        }
        hd_debug_print("Disabled channels: $cnt", true);

        if (!$top && $cnt !== 0) {
            $menu_items[] = $this->create_menu_item($handler,
                ACTION_ITEMS_EDIT,
                TR::t('tv_screen_edit_hidden_channels'),
                "edit.png",
                array(CONTROL_ACTION_EDIT => Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_CHANNELS));
        }

        if (!empty($menu_items)) {
            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        }

        return $menu_items;
    }

    ///////////////////////////////////////////////////////////////////////
    // Dialogs and screens

    public function create_plugin_title()
    {
        $name = empty($this->active_playlist) ? '' : $this->active_playlist[PARAM_NAME];
        $plugin_name = $this->plugin_info['app_caption'];
        $name = empty($name) ? $plugin_name : "$plugin_name ($name)";
        hd_debug_print("plugin title: $name");
        return $name;
    }

    /**
     * @param string $source_screen_id
     * @param string $action_edit
     * @param MediaURL|null $media_url
     * @return array|null
     */
    public function do_edit_list_screen($source_screen_id, $action_edit, $media_url = null)
    {
        $sel_id = null;
        switch ($action_edit) {
            case Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_CHANNELS:
                $params['screen_id'] = Starnet_Edit_Hidden_List_Screen::ID;
                $params['end_action'] = ACTION_INVALIDATE;
                $params['cancel_action'] = ACTION_EMPTY;
                if (!is_null($media_url) && isset($media_url->group_id)) {
                    $params['group_id'] = $media_url->group_id;
                }
                $title = TR::t('tv_screen_edit_hidden_channels');
                break;

            case Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_GROUPS:
                $params['screen_id'] = Starnet_Edit_Hidden_List_Screen::ID;
                $params['end_action'] = ACTION_INVALIDATE;
                $params['cancel_action'] = ACTION_EMPTY;
                $title = TR::t('tv_screen_edit_hidden_group');
                break;

            case Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST:
                $params['screen_id'] = Starnet_Edit_List_Screen::ID;
                $params['allow_order'] = true;
                $params['end_action'] = ACTION_REFRESH_SCREEN;
                $params['cancel_action'] = RESET_CONTROLS_ACTION_ID;
                $params['extension'] = PLAYLIST_PATTERN;
                $title = TR::t('setup_channels_src_edit_playlists');
                $active_key = $this->get_active_playlist_key();
                if (!empty($active_key) && $this->get_playlist($active_key) !== null) {
                    $sel_id = $this->get_all_playlists()->get_idx($active_key);
                }
                break;

            case Starnet_Edit_List_Screen::SCREEN_EDIT_EPG_LIST:
                $params['screen_id'] = Starnet_Edit_List_Screen::ID;
                $params['end_action'] = ACTION_RELOAD;
                $params['cancel_action'] = RESET_CONTROLS_ACTION_ID;
                $params['extension'] = EPG_PATTERN;
                $title = TR::t('setup_edit_xmltv_list');
                break;

            default:
                return null;
        }

        $params['source_window_id'] = $source_screen_id;
        $params['source_media_url_str'] = $source_screen_id;
        $params['edit_list'] = $action_edit;
        $params['windowCounter'] = 1;


        return Action_Factory::open_folder(MediaURL::encode($params), $title, null, $sel_id);
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $provider_id
     * @param string $playlist_id
     * @return array|null
     */
    public function do_edit_provider_dlg($handler, $provider_id, $playlist_id = '')
    {
        hd_debug_print(null, true);
        hd_debug_print("Provider id: $provider_id, Playlist id: $playlist_id", true);

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (empty($playlist_id)) {
            // add new provider
            $provider = $this->create_provider_class($provider_id);
            hd_debug_print("new provider : $provider_id", true);
        } else {
            // edit existing provider
            $item = $this->get_playlist($playlist_id);
            if (!is_null($item)) {
                $name = $item[PARAM_NAME];
                hd_debug_print("existing provider: $playlist_id", true);
                hd_debug_print("provider info:" . pretty_json_format($item), true);
                $provider = $this->create_provider_class($item[PARAM_PARAMS][PARAM_PROVIDER]);
                if (!is_null($provider)) {
                    $provider->set_provider_playlist_info($playlist_id, $item);
                }
            } else {
                $provider = $this->create_provider_class($provider_id);
            }
        }

        if (is_null($provider)) {
            return $defs;
        }

        if (empty($name)) {
            $name = $provider->getName();
        }

        $defs = $provider->GetSetupUI($name, $playlist_id, $handler);
        if (empty($defs)) {
            return null;
        }

        return Action_Factory::show_dialog("{$provider->getName()} ({$provider->getId()})", $defs, true);
    }

    /**
     * @param User_Input_Handler $handler
     * @return array|null
     */
    public function do_edit_provider_ext_dlg($handler)
    {
        hd_debug_print(null, true);

        $provider = $this->get_active_provider();
        if (is_null($provider)) {
            return array();
        }

        $defs = $provider->GetExtSetupUI($handler);
        if (empty($defs)) {
            return null;
        }

        $head = array();
        Control_Factory::add_vgap($head, 20);

        return Action_Factory::show_dialog("{$provider->getName()} ({$provider->getId()})", array_merge($head, $defs), true);
    }

    /**
     * @param Object $user_input
     * @return bool|array|string
     */
    public function apply_edit_provider_dlg($user_input)
    {
        hd_debug_print(null, true);

        if ($user_input->parent_media_url === Starnet_Tv_Groups_Screen::ID) {
            $provider = $this->get_active_provider();
        } else {
            // edit existing or new provider in starnet_edit_list_screen
            $provider = $this->create_provider_class($user_input->{PARAM_PROVIDER});
        }

        if (is_null($provider)) {
            return false;
        }

        return $provider->ApplySetupUI($user_input);
    }

    /**
     * @param object $user_input
     * @return bool
     */
    public function apply_edit_provider_ext_dlg($user_input)
    {
        hd_debug_print(null, true);

        $provider = $this->get_active_provider();
        if (is_null($provider)) {
            return false;
        }

        return $provider->ApplyExtSetupUI($user_input);
    }

    /**
     * @param string $channel_id
     * @return array|null
     */
    public function do_show_channel_info($channel_id)
    {
        $channel_row = $this->get_channel_info($channel_id, true);
        if (empty($channel_row)) {
            return null;
        }

        $epg_ids = array('epg_id' => $channel_row['epg_id'], 'id' => $channel_id, 'name' => $channel_row['title']);

        $info = "ID: " . $channel_row['channel_id'] . PHP_EOL;
        $info .= "Name: " . $channel_row['title'] . PHP_EOL;
        $info .= "Archive: " . $channel_row['archive'] . " days" . PHP_EOL;
        $info .= "Protected: " . TR::load_string($channel_row['adult'] ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off) . PHP_EOL;
        $info .= "EPG IDs: " . implode(', ', $epg_ids) . PHP_EOL;
        if ($channel_row['timeshift'] != 0) {
            $info .= "Timeshift hours: {$channel_row['timeshift']}" . PHP_EOL;
        }
        $info .= "Category: {$channel_row['group_id']}" . PHP_EOL;
        $info .= "Icon: " . wrap_string_to_lines($channel_row['icon'], 70) . PHP_EOL;
        $info .= PHP_EOL;

        try {
            $live_url = $this->generate_stream_url($channel_row, -1, true);
            $info .= "Live URL: " . wrap_string_to_lines($live_url, 70) . PHP_EOL;
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        if ($channel_row['archive'] > 0) {
            try {
                $archive_url = $this->generate_stream_url($channel_row, time() - 3600, true);
                $info .= "Archive URL: " . wrap_string_to_lines($archive_url, 70) . PHP_EOL;
            } catch (Exception $ex) {
                print_backtrace_exception($ex);
            }
        }

        $dune_params = $this->generate_dune_params($channel_id, json_decode($channel_row['ext_params'], true));
        if (!empty($dune_params)) {
            $info .= "dune_params: " . substr($dune_params, strlen(HD::DUNE_PARAMS_MAGIC)) . PHP_EOL;
        }

        if (!empty($live_url) && !is_limited_apk()) {
            $descriptors = array(
                0 => array("pipe", "r"), // stdin
                1 => array("pipe", "w"), // sdout
                2 => array("pipe", "w"), // stderr
            );

            hd_debug_print("Get media info for: $live_url");
            $process = proc_open(
                get_install_path("bin/media_check.sh $live_url"),
                $descriptors,
                $pipes);

            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]);

                fclose($pipes[1]);
                proc_close($process);

                $info .= "\n";
                foreach (explode("\n", $output) as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    if (strpos($line, "Output") !== false) break;
                    if (strpos($line, "Stream") !== false) {
                        $info .= preg_replace("/ \([\[].*\)| \[.*\]|, [0-9k\.]+ tb[rcn]|, q=[0-9\-]+/", "", $line) . PHP_EOL;
                    }
                }
            }
        }

        Control_Factory::add_multiline_label($defs, null, $info, 18);
        Control_Factory::add_vgap($defs, 10);

        $text = sprintf("<gap width=%s/><icon>%s</icon><gap width=10/><icon>%s</icon><text color=%s size=small>  %s</text>",
            1200,
            get_image_path('page_plus_btn.png'),
            get_image_path('page_minus_btn.png'),
            DEF_LABEL_TEXT_COLOR_SILVER,
            TR::load_string('scroll_page')
        );
        Control_Factory::add_smart_label($defs, '', $text);
        Control_Factory::add_vgap($defs, -80);

        Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 250, true);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('channel_info_dlg'), $defs, true, 1750);
    }

    /**
     * @param string $channel_id
     * @param $plugin_cookies
     * @return array|null
     */
    public function do_show_channel_epg($channel_id, $plugin_cookies)
    {
        $channel = $this->get_channel_info($channel_id);
        if (is_null($channel)) {
            return null;
        }

        $prog_info = $this->get_program_info($channel_id, -1, $plugin_cookies);

        if (is_null($prog_info)) {
            $title = TR::load_string('epg_not_exist');
            $info = '';
        } else {
            // program epg available
            $title = $prog_info[PluginTvEpgProgram::name];
            $info = $prog_info[PluginTvEpgProgram::description];
        }

        Control_Factory::add_multiline_label($defs, null, $info, 18);
        Control_Factory::add_vgap($defs, 10);

        $text = sprintf("<gap width=%s/><icon>%s</icon><gap width=10/><icon>%s</icon><text color=%s size=small>  %s</text>",
            850,
            get_image_path('page_plus_btn.png'),
            get_image_path('page_minus_btn.png'),
            DEF_LABEL_TEXT_COLOR_SILVER,
            TR::load_string('scroll_page')
        );
        Control_Factory::add_smart_label($defs, '', $text);
        Control_Factory::add_vgap($defs, -80);

        Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 250, true);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($title, $defs, true, 1400);
    }

    /**
     * @param User_Input_Handler $handler
     * @return array|null
     */
    public function do_show_subscription($handler)
    {
        hd_debug_print(null, true);

        $provider = $this->get_active_provider();
        if (is_null($provider)) {
            return null;
        }

        return $provider->GetInfoUI($handler);
    }

    /**
     * @return array|null
     */
    public function do_show_add_money()
    {
        hd_debug_print(null, true);

        $provider = $this->get_active_provider();
        if (is_null($provider)) {
            return null;
        }

        return $provider->GetPayUI();
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $param_action
     * @return array
     */
    public function show_protect_settings_dialog($handler, $param_action)
    {
        $pass_settings = $this->get_parameter(PARAM_SETTINGS_PASSWORD);
        if (empty($pass_settings)) {
            return User_Input_Handler_Registry::create_action($handler, $param_action);
        }

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $handler, null, 'pass', TR::t('setup_pass'),
            '', true, true, false, true, 500, true);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, array("param_action" => $param_action),
            ACTION_PASSWORD_APPLY, TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('setup_enter_pass'), $defs, true);
    }

    /**
     * @return Hashed_Array<array>
     */
    public function get_active_sources()
    {
        hd_debug_print(null, true);

        $all_sources = $this->get_all_xmltv_sources();
        $selected_sources = $this->get_selected_xmltv_sources();
        $active_sources = new Hashed_Array();
        foreach ($selected_sources as $key) {
            $item = $all_sources->get($key);
            if ($item === null) continue;

            $item[PARAM_PARAMS][PARAM_HASH] = Hashed_Array::hash($item[PARAM_URI]);
            $active_sources->set($key, $item);
        }

        return $active_sources;
    }

    public function cleanup_active_xmltv_source()
    {
        $is_json_engine = $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === ENGINE_JSON;
        $use_playlist_picons = $this->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS);

        if ($is_json_engine && $use_playlist_picons === PLAYLIST_PICONS) {
            hd_debug_print("No need to cleanup");
            return;
        }

        $playlist_sources = $this->get_playlist_xmltv_sources()->get_keys();
        $ext_sources = $this->get_ext_xmltv_sources()->get_keys();
        $all_sources = array_unique(array_merge($playlist_sources, $ext_sources));

        $cur_sources = $this->get_selected_xmltv_sources();
        hd_debug_print("Load selected XMLTV sources keys: " . json_encode($cur_sources));

        // remove non-existing values from active sources
        $changed = false;
        $filtered_source = array_intersect($cur_sources, $all_sources);
        if (count($cur_sources) !== count($filtered_source)) {
            $cur_sources = $filtered_source;
            hd_debug_print("Filtered source: " . json_encode($cur_sources));
            $changed = true;
        }

        if (empty($cur_sources) && !empty($playlist_sources)) {
            $cur_sources[] = reset($playlist_sources);
            $changed = true;
        }

        if ($changed) {
            hd_debug_print("Save selected XMLTV sources keys: " . json_encode($cur_sources));
            $this->set_selected_xmltv_sources($cur_sources);
        }

        $this->epg_manager->set_xmltv_sources($this->get_active_sources());

        $locks = $this->epg_manager->is_any_index_locked();
        if ($locks === false) {
            return;
        }

        foreach ($locks as $lock) {
            $ar = explode('_', $lock);
            $pid = (int)end($ar);

            if ($pid !== 0 && !send_process_signal($pid, 0)) {
                hd_debug_print("Remove stalled lock: $lock");
                shell_exec("rmdir {$this->get_cache_dir()}" . '/' . $lock);
            }
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///

    protected function is_playlist_cache_expired($filename)
    {
        if (!file_exists($filename)) {
            hd_debug_print("Playlist cache $filename not exist");
            return true;
        }

        $mtime = filemtime($filename);
        $diff = time() - $mtime;
        if ($diff <= 3600) {
            return false;
        }

        hd_debug_print("Playlist cache $filename expired " . ($diff - 3600) . " sec ago. Timestamp $mtime. Forcing reload");
        unlink($filename);
        return true;
    }

    ///////////////////////////////////////////////////////////////////////
    /// IPTV

    /**
     * Return false if load channels failed
     * Return true if channels loaded successful
     *
     * @param Object $plugin_cookies
     * @return bool
     */
    public function load_channels(&$plugin_cookies, $reload_playlist = false)
    {
        hd_debug_print();

        HD::set_last_error($this->get_pl_error_name(), null);

        $plugin_cookies->toggle_move = false;

        $this->init_playlist_db();

        $playlist_loaded = $this->is_database_attached(M3uParser::IPTV_DB, basename($this->get_active_playlist_cache(true)));

        if (!$reload_playlist && $playlist_loaded === 1) {
            return true;
        }

        if ($playlist_loaded == 2) {
            $this->sql_playlist->exec("DETACH DATABASE iptv");
        }

        if ($this->is_vod_playlist()) {
            hd_debug_print("Using standard VOD implementation");
            $vod_class = 'vod_standard';
            $this->vod = new $vod_class($this);
            $this->vod_enabled = $this->vod->init_vod(null);
            $this->vod->init_vod_screens();

            return true;
        }

        $this->perf->reset('start');

        // first check if playlist in cache
        if (false === $this->init_playlist_parser($reload_playlist)) {
            return false;
        }

        $filename = $this->iptv_m3u_parser->get_filename();

        try {
            $this->perf->setLabel('start_parse_playlist');

            $mtime = filemtime($filename);
            $date_fmt = format_datetime("Y-m-d H:i", $mtime);
            hd_debug_print("Parse playlist $filename (timestamp: $mtime, $date_fmt)");

            $db_name = LogSeverity::$is_debug ? "$filename.db" : ":memory:";
            $this->sql_playlist->exec("ATTACH DATABASE '$db_name' AS " . M3uParser::IPTV_DB);

            if ($this->iptv_m3u_parser->parseIptvPlaylist($this->sql_playlist)) {
                $count = $this->get_playlist_entries_count();
            }

            if (empty($count)) {
                $contents = @file_get_contents($filename);
                $exception_msg = TR::load_string('err_load_playlist') . " Empty playlist!\n\n$contents";
                $this->clear_playlist_cache();
                throw new Exception($exception_msg);
            }

            $this->perf->setLabel('end_parse_playlist');
            $report = $this->perf->getFullReport('start_parse_playlist', 'end_parse_playlist');

            hd_debug_print_separator();
            hd_debug_print("Parse playlist done!");
            hd_debug_print("Total entries: $count");
            hd_debug_print("Parse time:    {$report[Perf_Collector::TIME]} sec");
            hd_debug_print("Memory usage:  {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
            hd_debug_print_separator();
        } catch (Exception $ex) {
            $err = HD::get_last_error($this->get_pl_error_name());
            if (!empty($err)) {
                $err .= "\n\n";
            }
            $err .= $ex->getMessage();
            HD::set_last_error($this->get_pl_error_name(), $err);
            print_backtrace_exception($ex);
            if (isset($playlist[PARAM_TYPE]) && file_exists($filename)) {
                unlink($filename);
            }
            hd_debug_print_separator();
            return false;
        }

        $this->init_epg_manager();
        $this->cleanup_active_xmltv_source();

        $provider = $this->get_active_provider();
        $this->vod = null;
        $this->vod_enabled = false;
        if (!is_null($provider)) {
            $ignore_groups = $provider->getConfigValue(CONFIG_IGNORE_GROUPS);

            $vod_class = $provider->get_vod_class();
            if (!empty($vod_class)) {
                hd_debug_print("Using VOD: $vod_class");
                $this->vod = new $vod_class($this);
                $this->vod_enabled = $this->vod->init_vod($provider);
                $this->vod->init_vod_screens();
            }
        }

        hd_debug_print("VOD enabled: " . ($this->vod_enabled ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off), true);

        $enable_vod_icon = ($this->get_bool_parameter(PARAM_SHOW_VOD_ICON, false) && $this->vod_enabled)
            ? SetupControlSwitchDefs::switch_on
            : SetupControlSwitchDefs::switch_off;

        $plugin_cookies->{PARAM_SHOW_VOD_ICON} = $enable_vod_icon;
        hd_debug_print("Show VOD icon: $enable_vod_icon", true);

        $epg_manager = $this->get_epg_manager();

        $picons_source = $this->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS);
        if ($picons_source !== PLAYLIST_PICONS) {
            $all_sources = $this->get_active_sources();
            if ($all_sources->size() === 0) {
                hd_debug_print("No active XMLTV sources found to collect playlist icons...");
            } else {
                // Indexing xmltv file to make channel to display-name map and picons
                // Parsing channels is cheap for all Dune variants
                foreach ($all_sources as $params) {
                    $epg_manager->set_url_params($params);
                    $epg_manager->check_and_index_xmltv_source(false);
                }
            }
        }

        hd_debug_print_separator();
        hd_debug_print("Build categories and channels...");

        $playlist_entries = $this->get_playlist_entries_count();
        hd_debug_print("Playlist channels:   $playlist_entries");
        $playlist_groups = $this->get_playlist_group_count();
        hd_debug_print("Playlist groups:     $playlist_groups");

        $is_new = $this->get_channels_count(ALL_CHANNELS_GROUP_ID, -1) === 0;
        // add provider ignored groups to known_groups
        if (!empty($ignore_groups)) {
            $query = '';
            foreach ($ignore_groups as $group_id) {
                $q_group_id = Sql_Wrapper::sql_quote($group_id);
                $query .= "INSERT OR IGNORE INTO $this->pl_groups_parameters (group_id, disabled) VALUES ($q_group_id, 1);" . PHP_EOL;
            }
            $this->sql_playlist->exec_transaction($query);
        }

        ////////////////////////////////////////////////////////////////////////////////////
        /// update tables with removed and added groups and channels

        // get name of the column for channel ID
        $id_column = $this->get_id_column();
        hd_debug_print("ID column:           $id_column");

        // update existing database for empty group_id (converted from known_channels.settings)
        $query = "UPDATE $this->pl_channels
                    SET group_id = (
                        SELECT $this->iptv_channels.group_id
                        FROM $this->iptv_channels
                        WHERE $this->pl_channels.channel_id = $this->iptv_channels.ch_id
                    )
                    WHERE EXISTS (
                        SELECT 1
                        FROM $this->iptv_channels
                        WHERE $this->pl_channels.channel_id = $this->iptv_channels.$id_column
                          AND $this->pl_channels.group_id != $this->iptv_channels.group_id
                    );";

        $this->sql_playlist->exec($query);

        // mark as removed channels that not present iptv.iptv_channels db
        $query = "UPDATE $this->pl_channels SET changed = -1 WHERE channel_id NOT IN (SELECT $id_column FROM $this->iptv_channels);";
        $this->sql_playlist->exec($query);

        // select new groups that not present in groups table but exist in iptv_groups
        $query_new_groups = "SELECT * FROM $this->iptv_groups WHERE group_id NOT IN (SELECT DISTINCT group_id FROM $this->pl_groups_parameters);";
        $new_groups = $this->sql_playlist->fetch_array($query_new_groups);

        $new_groups_ids = array();
        foreach ($new_groups as $group_row) {
            $new_groups_ids[] = $group_row['group_id'];
            $order_table_name = self::get_table_name($group_row['group_id']);

            $q_group_id = Sql_Wrapper::sql_quote($group_row['group_id']);
            $q_group_icon = Sql_Wrapper::sql_quote(empty($group_row['icon']) ? DEFAULT_GROUP_ICON : $group_row['icon']);
            $q_adult = Sql_Wrapper::sql_quote($group_row['adult']);

            $query = "CREATE TABLE IF NOT EXISTS $order_table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";
            $query .= "INSERT OR IGNORE INTO $this->pl_groups_parameters (group_id, title, icon, adult) VALUES ($q_group_id, $q_group_id, $q_group_icon, $q_adult);";
            $query .= "INSERT OR IGNORE INTO $this->pl_groups_order (group_id) VALUES ($q_group_id);";
            $this->sql_playlist->exec_transaction($query);

            // select all channels from iptv_channels for selected group that not disabled in known_channels
            $query = "SELECT * FROM $this->iptv_channels
                    WHERE group_id = $q_group_id
                    AND $id_column NOT IN (SELECT channel_id FROM $this->pl_channels WHERE disabled == 0) 
                    GROUP BY $id_column ORDER BY ROWID ASC;";
            $pl_entries = $this->sql_playlist->fetch_array($query);

            $query = '';
            foreach ($pl_entries as $entry) {
                $unique_channel_id = Sql_Wrapper::sql_quote($entry[$id_column]);
                $query .= "INSERT OR IGNORE INTO $order_table_name (channel_id) VALUES($unique_channel_id);";
            }
            $this->sql_playlist->exec_transaction($query);
        }

        if (!empty($new_groups_ids)) {
            $list_new_groups = Sql_Wrapper::sql_make_list_from_quoted_values($new_groups_ids);
            hd_debug_print("New groups:          $list_new_groups");
        }

        // add new channels
        $query = "INSERT OR IGNORE INTO $this->pl_channels (channel_id, title, group_id, adult, changed)
                    SELECT $id_column, title, group_id, adult, 1
                    FROM $this->iptv_channels
                    WHERE group_id IN (SELECT group_id FROM $this->pl_groups_parameters WHERE special = 0)
                      AND $id_column NOT IN (SELECT channel_id FROM $this->pl_channels)
                    GROUP BY $id_column ORDER BY ROWID ASC;";

        $query .= "UPDATE $this->pl_channels SET changed = 0, disabled = 1
                    WHERE group_id IN (SELECT group_id FROM $this->pl_groups_parameters WHERE disabled = 1 AND special = 0);";
        $this->sql_playlist->exec($query);

        if ($is_new) {
            // if it first run for this playlist remove changed status for all channels
            $this->clear_changed_channels();
        }

        // cleanup order if group removed from playlist
        $query = "SELECT group_id FROM $this->pl_groups_parameters WHERE group_id NOT IN (SELECT group_id FROM $this->iptv_groups) AND special = 0;";
        $removed_groups = $this->sql_playlist->fetch_single_array($query, 'group_id');
        if (!empty($rows)) {
            $list_removed_groups = Sql_Wrapper::sql_make_list_from_quoted_values($removed_groups);
            hd_debug_print("Removed groups:      $list_removed_groups", true);
            $query = "DELETE FROM $this->pl_groups_order WHERE group_id IN ($list_removed_groups);";
            $query .= "DELETE FROM $this->pl_groups_parameters WHERE group_id IN ($list_removed_groups);";
            $this->sql_playlist->exec_transaction($query);
        }

        $known_cnt = $this->get_channels_count(ALL_CHANNELS_GROUP_ID, -1, -1);
        $visible_groups_cnt = $this->get_groups_count(0, 0);
        $hidden_groups_cnt = $this->get_groups_count(0, 1);

        $visible_channels_cnt = $this->get_channels_count(ALL_CHANNELS_GROUP_ID, 0);
        $hidden_channels_cnt = $this->get_channels_count(ALL_CHANNELS_GROUP_ID, 1);
        $all_hidden_channels_cnt = $this->get_channels_count(ALL_CHANNELS_GROUP_ID, 1, -1);

        $added_channels_cnt = $this->get_changed_channels_count('new');
        $removed_channels_cnt = $this->get_changed_channels_count('removed');

        $this->perf->setLabel('end');
        $report = $this->perf->getFullReport();

        hd_debug_print("Known channels:      $known_cnt");
        hd_debug_print("Visible channels:    $visible_channels_cnt");
        hd_debug_print("Hidden channels:     $hidden_channels_cnt");
        hd_debug_print("All hidden channels: $all_hidden_channels_cnt");
        hd_debug_print("New channels:        $added_channels_cnt");
        hd_debug_print("Removed channels:    $removed_channels_cnt");
        hd_debug_print("Visible groups:      $visible_groups_cnt");
        hd_debug_print("Hidden groups:       $hidden_groups_cnt");
        hd_debug_print("Load time:           {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage:        {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_debug_print_separator();

        if ($this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === ENGINE_XMLTV) {
            foreach ($this->get_selected_xmltv_sources() as $source) {
                $this->run_bg_epg_indexing($source);
            }
        }

        return true;
    }

    /**
     * @param Object $plugin_cookies
     * @return bool
     */
    public function reload_channels(&$plugin_cookies)
    {
        hd_debug_print(null, true);
        $this->reset_playlist_db();
        $this->init_playlist_db();
        return $this->load_channels($plugin_cookies, true);
    }

    /**
     * @param string|null $group_id
     * @param int $disabled_channels -1 - all, 0 - only enabled, 1 - only disabled
     * @param bool $full true - full information, false only channel_id, title and statuses
     * @return array
     */
    public function get_channels($group_id, $disabled_channels, $full = false)
    {
        if (is_null($group_id) || $group_id === ALL_CHANNELS_GROUP_ID) {
            $where = "WHERE pl.group_id IN (SELECT group_id FROM $this->pl_groups_parameters WHERE special = 0 AND disabled = 0)";
        } else {
            $where = "WHERE pl.group_id = " . Sql_Wrapper::sql_quote($group_id);
        }

        if ($disabled_channels !== -1) {
            $where = empty($where) ? "WHERE disabled = $disabled_channels" : "$where AND disabled = $disabled_channels";
        }

        if ($full) {
            $column = $this->get_id_column();
            $query = "SELECT ch.channel_id, pl.* FROM $this->iptv_channels AS pl JOIN $this->pl_channels AS ch ON pl.$column = ch.channel_id $where;";
        } else {
            $query = "SELECT * FROM $this->pl_channels AS pl $where;";
        }

        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param string|null $group_id
     * @param int $disabled_channels -1 - all, 0 - only enabled, 1 - only disabled
     * @param int $disabled_groups -1 - all, 0 - only enabled, 1 - only disabled
     * @return int
     */
    public function get_channels_count($group_id, $disabled_channels, $disabled_groups = 0)
    {
        if (is_null($group_id) || $group_id === ALL_CHANNELS_GROUP_ID) {
            $and = ($disabled_groups !== -1) ? "AND disabled = $disabled_groups" : "";
            $where = "WHERE pl.group_id IN (SELECT group_id FROM $this->pl_groups_parameters WHERE special = 0 $and)";
        } else {
            $where = "WHERE pl.group_id = " . Sql_Wrapper::sql_quote($group_id);
        }

        if ($disabled_channels !== -1) {
            $where = empty($where) ? "WHERE disabled = $disabled_channels" : "$where AND disabled = $disabled_channels";
        }

        $column = $this->get_id_column();
        $query = "SELECT count(ch.channel_id) FROM $this->iptv_channels AS pl JOIN $this->pl_channels AS ch ON pl.$column = ch.channel_id $where;";
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @return int
     */
    public function get_playlist_entries_count()
    {
        $query = "SELECT name FROM " . M3uParser::IPTV_DB . ".sqlite_master WHERE type = 'table' AND name = '" . M3uParser::S_CHANNELS_TABLE . "';";
        if ($this->sql_playlist->query_value($query)) {
            return $this->sql_playlist->query_value("SELECT count(*) FROM $this->iptv_channels;");
        }

        return 0;
    }

    /**
     * @return int
     */
    public function get_playlist_group_count()
    {
        $query = "SELECT name FROM " . M3uParser::IPTV_DB . ".sqlite_master WHERE type = 'table' AND name = '" . M3uParser::S_GROUPS_TABLE . "';";
        if ($this->sql_playlist->query_value($query)) {
            return $this->sql_playlist->query_value("SELECT count(*) FROM $this->iptv_groups;");
        }

        return 0;
    }

    /**
     * @return array
     */
    public function get_channels_by_order($group_id)
    {
        $order_table = self::get_table_name($group_id);
        $column = $this->get_id_column();
        $query = "SELECT ord.id, ord.channel_id, pl.*
                    FROM $this->iptv_channels AS pl
                    JOIN $order_table AS ord ON pl.$column = ord.channel_id
                    JOIN $this->pl_channels as ch ON ch.channel_id = ord.channel_id AND ch.disabled = 0
                    ORDER BY ord.id;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * enable/disable channel(s)
     *
     * @param string|array $channel_id
     * @param bool $hide
     */
    public function set_channel_visible($channel_id, $hide)
    {
        if (empty($channel_id)) {
            return;
        }

        $disable = (int)$hide;

        if (is_array($channel_id)) {
            $channels = Sql_Wrapper::sql_make_list_from_quoted_values($channel_id);
            $where = "WHERE channel_id IN ($channels)";
            $groups_select = "SELECT DISTINCT group_id FROM $this->pl_channels $where;";
        } else {
            $channels = Sql_Wrapper::sql_quote($channel_id);
            $where = "WHERE channel_id = $channels";
            $groups_select = "SELECT group_id FROM $this->pl_channels $where);";
        }

        $query = '';
        foreach ($this->sql_playlist->fetch_array($groups_select) as $group) {
            $q_table = self::get_table_name($group['group_id']);
            if ($hide) {
                $query .= "DELETE FROM $q_table $where;";
            } else {
                $q_group = Sql_Wrapper::sql_quote($group['group_id']);
                $query .= "INSERT OR IGNORE INTO $q_table (channel_id)
                            SELECT channel_id FROM $this->pl_channels WHERE channel_id IN ($channels) AND group_id = $q_group ORDER BY ROWID;";
            }
        }
        $query .= "UPDATE $this->pl_channels SET disabled = $disable $where;";

        $this->sql_playlist->exec($query);
    }

    /**
     * @param string $channel_id
     * @param bool $full
     * @return array
     */
    public function get_channel_info($channel_id, $full = false)
    {
        $channel_id = Sql_Wrapper::sql_quote($channel_id);
        if ($full) {
            $column = $this->get_id_column();
            $query = "SELECT ch.channel_id, tv.*
                        FROM $this->iptv_channels as tv
                            JOIN $this->pl_channels AS ch ON tv.$column = ch.channel_id
                        WHERE ch.channel_id = $channel_id AND ch.disabled = 0;";
        } else {
            $query = "SELECT * FROM $this->pl_channels WHERE channel_id = $channel_id AND disabled = 0;";
        }

        return $this->sql_playlist->query_value($query, true);
    }

    /**
     * disable channels by pattern and remove it from order
     *
     * @param string $pattern
     * @param string $group_id
     * @param bool $is_regex
     * @return int
     */
    public function hide_channels_by_mask($pattern, $group_id, $is_regex = true)
    {
        hd_debug_print("Hide channels type: '$pattern' in group: '$group_id'");

        $disabled_ids = array();
        $groups = array();
        foreach ($this->get_channels($group_id, 0) as $item) {
            if ($is_regex) {
                $add = preg_match("#$pattern#", $item['title']);
            } else {
                $add = stripos($item['title'], $pattern) !== false;
            }

            if ($add) {
                $disabled_ids[] = $item['channel_id'];
            }
        }

        $cnt = count($disabled_ids);
        if ($cnt !== 0) {
            $this->set_channel_visible($disabled_ids, true);
            hd_debug_print("Total channels hidden: $cnt from groups: " . Sql_Wrapper::sql_make_list_from_keys($groups));
        }

        return $cnt;
    }

    /////////////////////////////////////////////////////////////////
    /// Changed channels

    /**
     * @param string $channel_id
     * @param bool $changed
     */
    public function set_changed_channel($channel_id, $changed)
    {
        $changed = (int)$changed;
        $id = Sql_Wrapper::sql_quote($channel_id);
        $this->sql_playlist->exec("UPDATE $this->pl_channels SET changed = $changed WHERE channel_id = $id");
    }

    /**
     * @param string $type // new, removed, null or other value - total
     * @return array
     */
    public function get_changed_channels($type)
    {
        $val = self::type_to_val($type);
        $column = $this->get_id_column();
        if ($type === 'new') {
            $query = "SELECT ch.ROWID, ch.channel_id, pl.*
                        FROM $this->iptv_channels AS pl
                            JOIN $this->pl_channels AS ch ON pl.$column = ch.channel_id
                        WHERE $val ORDER BY ch.ROWID;";
        } else if ($type === 'removed') {
            $query = "SELECT ROWID, channel_id, title FROM $this->pl_channels WHERE $val ORDER BY ROWID;";
        } else {
            $query = "SELECT ch.ROWID, ch.channel_id, pl.*, ch.title
                    FROM $this->pl_channels AS ch
                        LEFT JOIN $this->iptv_channels AS pl ON pl.ch_id = ch.channel_id
                    WHERE $val
                    ORDER BY ch.ROWID;";
        }

        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param string $type // new, removed, null or other value - total
     * @return array
     */
    public function get_changed_channels_ids($type)
    {
        $val = self::type_to_val($type);
        $query = "SELECT channel_id FROM $this->pl_channels WHERE $val ORDER BY ROWID;";
        return $this->sql_playlist->fetch_single_array($query, 'channel_id');
    }

    /**
     * @param string $type // new, removed, null or other value - total
     * @param string $channel_id
     * @return int
     */
    public function get_changed_channels_count($type = null, $channel_id = null)
    {
        if ($type === 'new') {
            $where = "WHERE changed = 1";
        } else if ($type === 'removed') {
            $where = "WHERE changed = -1";
        } else {
            $where = "WHERE changed != 0";
        }

        $cond = is_null($channel_id) ? "" : ("AND channel_id = " . Sql_Wrapper::sql_quote($channel_id));
        $query = "SELECT count(*) FROM $this->pl_channels $where $cond;";

        return $this->sql_playlist->query_value($query);
    }

    /**
     * @return void
     */
    public function clear_changed_channels()
    {
        $query = "DELETE FROM $this->pl_channels WHERE changed = -1;";
        $query .= "UPDATE $this->pl_channels SET changed = 0 WHERE changed = 1;";
        $this->sql_playlist->exec_transaction($query);
    }

    ///////////////////////////////////////////////////////////////////////
    /// groups
    /**
     * returns groups
     *
     * @param int $type 0 - regular groups, 1 - special groups, -1 - all groups
     * @param int $disabled 0 - enabled groups, 1 - disabled groups, -1 - all groups
     * @return array
     */
    public function get_groups($type, $disabled)
    {
        $where = ($disabled === -1) ? "" : "WHERE disabled = $disabled";
        $and = empty($where) ? "WHERE" : "AND";
        $where = $type === -1 ? "" : "$where $and special = $type";
        $query = "SELECT * FROM $this->pl_groups_parameters $where ORDER by id;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * returns group with selected id
     * @param int $type 0 - only regular groups, 1 - special groups, -1 - all groups
     *
     * @param string $group_id
     * @return array
     */
    public function get_group($group_id, $type = 0)
    {
        $q_group_id = Sql_Wrapper::sql_quote($group_id);
        $and = $type === -1 ? "" : "AND special = $type";
        $query = "SELECT * FROM $this->pl_groups_parameters WHERE group_id = $q_group_id AND disabled = 0 $and ORDER by id;";
        return $this->sql_playlist->query_value($query, true);
    }

    /**
     * Returns how many enabled groups
     *
     * @param int $type 0 - regular groups, 1 - special groups, -1 - all groups
     * @param int $disabled 0 - enabled groups, 1 - disabled groups, -1 - all groups
     * @return int
     */
    public function get_groups_count($type, $disabled)
    {
        $where = ($disabled === -1) ? "" : "WHERE disabled = $disabled";
        $and = empty($where) ? "WHERE" : "AND";
        $where = $type === -1 ? "" : "$where $and special = $type";
        $query = "SELECT count(*) FROM $this->pl_groups_parameters $where ORDER by id;";
        return $this->sql_playlist->query_value($query);
    }

    /**
     * Set visibility for group or groups array
     *
     * @param string|array $group_ids
     * @param bool $hide
     * @return void
     */
    public function set_groups_visible($group_ids, $hide)
    {
        $disabled = (int)$hide;

        if (is_array($group_ids)) {
            $groups = Sql_Wrapper::sql_make_list_from_quoted_values($group_ids);
            $where = "WHERE group_id IN ($groups)";
            $to_alter = $group_ids;
        } else {
            $where = "WHERE group_id = " . Sql_Wrapper::sql_quote($group_ids);
            $to_alter[] = $group_ids;
        }

        $query = "UPDATE $this->pl_groups_parameters SET disabled = $disabled $where;";
        foreach ($to_alter as $group_id) {
            $q_group_id = Sql_Wrapper::sql_quote($group_id);
            $table_name = self::get_table_name($group_id);

            if ($disabled) {
                $query .= "DELETE FROM $this->pl_groups_order WHERE group_id = $q_group_id;";
                $query .= "DROP TABLE IF EXISTS $table_name;";
                $query .= "UPDATE $this->pl_channels SET disabled = 1 WHERE group_id = $q_group_id;";
            } else {
                $query .= "INSERT OR IGNORE INTO $this->pl_groups_order (group_id) VALUES ($q_group_id);";
                $query .= "CREATE TABLE IF NOT EXISTS $table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";
                $query .= "UPDATE $this->pl_channels SET disabled = 0 WHERE group_id = $q_group_id;";
                $query .= "INSERT OR IGNORE INTO $table_name (channel_id ) SELECT channel_id FROM $this->pl_channels WHERE group_id = $q_group_id AND disabled = 0;";
            }
        }
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param $group_id
     * @return string|false
     */
    public function get_group_icon($group_id)
    {
        $group_id = Sql_Wrapper::sql_quote($group_id);
        return $this->sql_playlist->query_value("SELECT icon FROM $this->pl_groups_parameters WHERE group_id = $group_id;");
    }

    public function set_group_icon($group_id, $icon)
    {
        $group_id = Sql_Wrapper::sql_quote($group_id);
        $old_cached_image = $this->get_group_icon($group_id);
        hd_debug_print("Assign icon: $icon to group: $group_id");
        $this->sql_playlist->exec("UPDATE $this->pl_groups_parameters SET icon = $icon WHERE group_id = $group_id;");

        if (!empty($old_cached_image)
            && strpos($old_cached_image, 'plugin_file://') !== false
            && $this->sql_playlist->query_value("SELECT count(*) FROM $this->pl_groups_parameters WHERE icon = $icon;") == 0) {
            $old_cached_image_path = get_cached_image_path($old_cached_image);
            if (file_exists($old_cached_image_path)) {
                unlink($old_cached_image_path);
            }
        }
    }

    /**
     * @return array
     */
    public function get_groups_order()
    {
        return $this->sql_playlist->fetch_single_array("SELECT group_id FROM $this->pl_groups_order", 'group_id');
    }

    /**
     * @return array
     */
    public function get_groups_by_order()
    {
        $query = "SELECT g.group_id, g.title, g.icon
                    FROM $this->pl_groups_parameters AS g
                    INNER JOIN $this->pl_groups_order as o USING(group_id) ORDER BY o.id;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @return void
     */
    public function sort_groups_order($reset = false)
    {
        $order = $reset ? ";" : "ORDER BY group_id;";

        $qry = "CREATE TABLE {$this->pl_groups_order}_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id TEXT UNIQUE, group_icon TEXT);";
        $qry .= "INSERT INTO {$this->pl_groups_order}_tmp (group_id, group_icon)
                    SELECT group_id, group_icon FROM $this->pl_groups_parameters WHERE disabled = 0 $order";
        $qry .= "DROP TABLE $this->pl_groups_order;";
        $qry .= "ALTER TABLE {$this->pl_groups_order}_tmp RENAME TO $this->pl_groups_order;";

        $this->sql_playlist->exec_transaction($qry);
    }

    /**
     * @return int
     */
    public function get_groups_order_count()
    {
        return $this->sql_playlist->query_value("SELECT count(*) FROM $this->pl_groups_order");
    }

    /**
     * @param string $group_id
     * @param string $channel_id
     * @param int $direction
     * @return bool
     */
    public function arrange_channels_order_rows($group_id, $channel_id, $direction)
    {
        return $this->arrange_rows(self::get_table_name($group_id), 'channel_id', $channel_id, $direction);
    }

    /**
     * @param string $group_id
     * @param int $direction
     * @return bool
     */
    public function arrange_groups_order_rows($group_id, $direction)
    {
        return $this->arrange_rows($this->pl_groups_order, 'group_id', $group_id, $direction);
    }

    /**
     * @param string $table_name
     * @param string $column
     * @param string $item
     * @param int $direction
     * @return bool
     */
    private function arrange_rows($table_name, $column, $item, $direction)
    {
        $sub_query = "SELECT id AS cur FROM $table_name WHERE $column = " . Sql_Wrapper::sql_quote($item);
        $query = "SELECT * FROM (
            (SELECT MAX(id) AS prev FROM $table_name WHERE id < ($sub_query))
         INNER JOIN
            ($sub_query)
         INNER JOIN
            (SELECT MIN(id) AS next FROM $table_name WHERE id > ($sub_query))
         INNER JOIN
            (SELECT id AS first FROM $table_name ORDER BY id ASC LIMIT 1)
         INNER JOIN
            (SELECT id AS last FROM $table_name ORDER BY id DESC LIMIT 1));";

        $positions = $this->sql_playlist->query_value($query, true);
        if (empty($positions)) {
            return false;
        }

        $left = $positions['cur'];
        switch ($direction) {
            case Ordered_Array::UP:
                if($positions['prev'] === null) {
                    return false;
                }
                $right = $positions['prev'];
                break;
            case Ordered_Array::DOWN:
                if($positions['next'] === null) {
                    return false;
                }
                $right = $positions['next'];
                break;
            case Ordered_Array::TOP:
                if ($positions['first'] === $positions['cur']) {
                    return false;
                }
                $right = $positions['first'];
                break;
            case Ordered_Array::BOTTOM:
                if ($positions['last'] === $positions['cur']) {
                    return false;
                }
                $right = $positions['last'];
                break;
            default:
                return false;
        }

        $query = "UPDATE $table_name SET id = -$left WHERE id = $left;
                  UPDATE $table_name SET id = $left  WHERE id = $right;
                  UPDATE $table_name SET id = $right WHERE id = -$left;";

        return $this->sql_playlist->exec_transaction($query);
    }

    /**
     * return orders for selected group
     * @param string $group_id
     * @return array
     */
    public function get_channels_order($group_id)
    {
        $table_name = self::get_table_name($group_id);
        return $this->sql_playlist->fetch_single_array("SELECT channel_id FROM $table_name ORDER BY id;", 'channel_id');
    }

    /**
     * return is channel in group order
     * @param string $group_id
     * @return int
     */
    public function is_channel_in_order($group_id, $channel_id)
    {
        $table_name = self::get_table_name($group_id);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $query = "SELECT id FROM $table_name WHERE channel_id = $q_channel_id;";
        return (int)$this->sql_playlist->query_value($query);
    }

    public function remove_channels_order($group_id)
    {
        $table_name = self::get_table_name($group_id);
        $this->sql_playlist->exec("DELETE FROM $table_name;");
    }

    public function change_channels_order($group_id, $channel_id, $remove)
    {
        $table_name = self::get_table_name($group_id);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        if ($remove) {
            $qry = "DELETE FROM $table_name WHERE channel_id = $q_channel_id;";
        } else {
            $qry = "INSERT INTO $table_name (channel_id) VALUES ($q_channel_id);";
        }
        return $this->sql_playlist->exec($qry);
    }

    /**
     * @param string $group_id
     * @param bool $reset
     * @return void
     */
    public function sort_channels_order($group_id, $reset = false)
    {
        $column = self::get_id_column();
        $q_group_id = Sql_Wrapper::sql_quote($group_id);
        $table_name = self::get_table_name($group_id);
        $qry = "BEGIN;" . PHP_EOL;
        if ($reset) {
            $qry .= "DROP TABLE $table_name;" . PHP_EOL;
            $qry .= "CREATE TABLE $table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";
            $qry .= "INSERT OR IGNORE INTO $table_name (channel_id)
                        SELECT $column FROM $this->iptv_channels
                        WHERE group_id == $q_group_id AND $column IN
                        (SELECT channel_id FROM $this->pl_channels WHERE disabled == 0);";
        } else {
            $qry .= "CREATE TABLE {$table_name}_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";
            $qry .= "INSERT OR IGNORE INTO {$table_name}_tmp (channel_id)
                        SELECT $column FROM $this->iptv_channels
                        WHERE group_id == $q_group_id AND $column IN
                        (SELECT channel_id FROM $this->pl_channels WHERE disabled == 0) ORDER BY title;";

            $qry .= "INSERT INTO {$table_name}_tmp (channel_id) SELECT channel_id FROM $table_name ORDER BY channel_id;";
            $qry .= "DROP TABLE $table_name;" . PHP_EOL;
            $qry .= "ALTER TABLE {$table_name}_tmp RENAME TO $table_name;";
        }
        $qry .= "COMMIT;" . PHP_EOL;

        $this->sql_playlist->exec($qry);
    }

    /**
     * @param string $group_id
     * @return int
     */
    public function get_channels_order_count($group_id)
    {
        $table_name = self::get_table_name($group_id);
        return $this->sql_playlist->query_value("SELECT count(*) FROM $table_name;");
    }

    /**
     * @param string $group_id
     * @return string
     */
    public static function get_table_name($group_id)
    {
        switch ($group_id) {
            case FAV_MOVIE_GROUP_ID:
                return "orders_fav_vod";

            case VOD_FILTER_LIST:
                return "vod_filters";

            case VOD_SEARCH_LIST:
                return "vod_search";

            case FAV_CHANNELS_GROUP_ID:
                return self::PL_ORDERS_DB . ".orders_fav_tv";
        }

        return self::PL_ORDERS_DB . ".orders_" . Hashed_Array::hash($group_id);
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin parameters methods (global)
    //

    /**
     * Load global plugin settings
     *
     * @return void
     */
    public function init_parameters()
    {
        hd_debug_print(null, true);

        $this->sql_params = new Sql_Wrapper(get_data_path("common.db"));
        $query =  "CREATE TABLE IF NOT EXISTS $this->parameters_table (name TEXT PRIMARY KEY, value TEXT);";
        $query .= "CREATE TABLE IF NOT EXISTS $this->playlist_table
                    (id INTEGER PRIMARY KEY AUTOINCREMENT, playlist_id TEXT UNIQUE, name TEXT NOT NULL, type TEXT, params TEXT);";
        $query .= "CREATE TABLE IF NOT EXISTS $this->xmltv_table
                    (hash TEXT PRIMARY KEY, type TEXT, name TEXT NOT NULL, uri TEXT NOT NULL, cache TEXT NOT NULL);";
        $this->sql_params->exec($query);

        $parameters = HD::get_data_items('common.settings', true, false);
        if (!empty($parameters)) {
            hd_debug_print("Move 'common.settings' to common.db");
            $removed_parameters = array(
                'config_version', 'cur_xmltv_source', 'cur_xmltv_key', 'fuzzy_search_epg', ALL_CHANNELS_GROUP_ID,
                PARAM_EPG_JSON_PRESET, PARAM_BUFFERING_TIME, PARAM_ICONS_IN_ROW, PARAM_CHANNEL_POSITION,
                PARAM_EPG_CACHE_ENGINE, PARAM_PER_CHANNELS_ZOOM,
                PARAM_SHOW_FAVORITES, PARAM_SHOW_HISTORY, PARAM_SHOW_ALL, PARAM_SHOW_CHANGED_CHANNELS, PARAM_FAKE_EPG,
                PARAM_SHOW_VOD_ICON, PARAM_SHOW_VOD, 'force_http',
            );
            $query = '';
            /** @var Named_Storage|string $param */
            foreach ($parameters as $key => $param) {
                if (in_array($key, $removed_parameters)) {
                    unset($parameters[$key]);
                    continue;
                }

                hd_debug_print("$key => '" . $param . "'");
                if ($key === PARAM_PLAYLIST_STORAGE) {
                    foreach ($param as $k => $stg) {
                        if (empty($k)) continue;

                        if (($stg->type === PARAM_FILE || $stg->type === PARAM_LINK)
                            && !isset($stg->params[PARAM_PL_TYPE])) {
                            $stg->params[PARAM_PL_TYPE] = CONTROL_PLAYLIST_IPTV;
                        }

                        if ($stg->type === PARAM_PROVIDER) {
                            // remove obsolete parameters for provider
                            foreach (array(MACRO_TOKEN, MACRO_REFRESH_TOKEN, MACRO_SESSION_ID, MACRO_EXPIRE_DATA) as $macro) {
                                if (isset($stg->params[$macro])) {
                                    unset($stg->params[$macro]);
                                }
                            }
                        }
                        $this->set_playlist($k, array(PARAM_TYPE => $stg->type, PARAM_NAME => $stg->name, PARAM_PARAMS => $stg->params));
                    }
                    unset($parameters[$key]);
                } else if ($key === PARAM_EXT_XMLTV_SOURCES) {
                    foreach ($param as $hash => $stg) {
                        if (!isset($stg->params[PARAM_URI]) || !is_proto_http($stg->params[PARAM_URI])) continue;

                        $item = array(
                            PARAM_HASH => $hash,
                            PARAM_TYPE => PARAM_LINK,
                            PARAM_NAME => $stg->name,
                            PARAM_URI => $stg->params[PARAM_URI],
                            PARAM_CACHE => safe_get_value($stg->params, PARAM_CACHE, XMLTV_CACHE_AUTO)
                        );
                        $this->set_ext_xmltv_source($item);
                    }
                    unset($parameters[$key]);
                } else {
                    $type = gettype($param);
                    if ($type === 'NULL' ) {
                        $param = '';
                    } else if ($type == 'boolean') {
                        $param = $param ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off;
                    }
                    $q_key = Sql_Wrapper::sql_quote($key);
                    $q_param = Sql_Wrapper::sql_quote($param);
                    $query .= "INSERT OR IGNORE INTO $this->parameters_table (name, value) VALUES ($q_key, $q_param);";
                    unset($parameters[$key]);
                }
            }
            $this->sql_params->exec_transaction($query);
            if (empty($parameters)) {
                unlink(get_data_path("common.settings"));
            }
            foreach ($parameters as $key => $value) {
                hd_debug_print("!!!!! Parameter $key is not imported: " . $value);
            }
        }
    }

    /**
     * Set global plugin parameter
     * Parameters does not depend on playlists and used globally
     *
     * @param string $name
     * @param string $value
     */
    public function set_parameter($name, $value)
    {
        hd_debug_print(null, true);
        hd_debug_print("Set parameter: $name => $value", true);

        $q_name = Sql_Wrapper::sql_quote($name);
        $q_value = Sql_Wrapper::sql_quote($value);
        $this->sql_params->exec("INSERT OR REPLACE INTO $this->parameters_table (name, value) VALUES ($q_name, $q_value);");
    }

    /**
     * Get global plugin parameters
     * Parameters does not depend on playlists and used globally
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    public function get_parameter($name, $default = '')
    {
        $q_name = Sql_Wrapper::sql_quote($name);
        $value = $this->sql_params->query_value("SELECT value FROM $this->parameters_table WHERE name = $q_name;");
        if (empty($value)) {
            $value = $default;
        }
        return $value;
    }

    /**
     * @param string $param
     * @param bool $default
     * @return bool
     */
    public function toggle_parameter($param, $default = true)
    {
        $new_val = !$this->get_bool_parameter($param, $default);
        $this->set_bool_parameter($param, $new_val);
        return $new_val;
    }

    /**
     * Get plugin boolean parameters
     *
     * @param string $type
     * @param bool $default
     * @return bool
     */
    public function get_bool_parameter($type, $default = true)
    {
        return $this->get_parameter($type,
                $default ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on;
    }

    /**
     * Set plugin boolean parameters
     *
     * @param string $type
     * @param bool $val
     */
    public function set_bool_parameter($type, $val = true)
    {
        $this->set_parameter($type, $val ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off);
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin settings methods (per playlist configuration)
    //

    /**
     * load playlist settings by ID
     *
     * @param string $id
     * @param array $data
     * @return void
     */
    public function put_settings($id, $data)
    {
        if (!empty($id)) {
            $db = new Sql_Wrapper(get_data_path("$id.db"));
            $query = '';
            foreach ($data as $key => $value) {
                $type = gettype($value);
                if ($type === 'NULL') {
                    $type = 'string';
                    $value = '';
                }
                $q_value = SQL_Wrapper::sql_quote($value);
                $query .= "INSERT OR IGNORE INTO $this->pl_settings (name, value, type) VALUES ('$key', $q_value, '$type');";
            }

            $db->exec_transaction($query);
        }
    }

    /**
     * Get settings for selected playlist
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get_setting($name, $default)
    {
        $type = gettype($default);
        $row = $this->sql_playlist->query_value("SELECT value, type FROM $this->pl_settings WHERE name = '$name';", true);
        if (empty($row)) {
            return $default;
        }

        settype($row['value'], $type);
        return $row['value'];
    }

    /**
     * Set settings for selected playlist
     *
     * @param string $name
     * @param mixed $value
     */
    public function set_setting($name, $value)
    {
        hd_debug_print(null, true);
        hd_debug_print("Set setting: $name => $value", true);

        $q_value = Sql_Wrapper::sql_quote($value);
        $type = gettype($value);
        $this->sql_playlist->exec("INSERT OR REPLACE INTO $this->pl_settings (name, value, type) VALUES ('$name', $q_value, '$type');");
    }

    /**
     * @param string $param
     * @param bool $default
     * @return bool
     */
    public function toggle_setting($param, $default = true)
    {
        $new_val = !$this->get_bool_setting($param, $default);
        $this->set_bool_setting($param, $new_val);
        return $new_val;
    }

    /**
     * Get plugin boolean parameters
     *
     * @param string $type
     * @param bool $default
     * @return bool
     */
    public function get_bool_setting($type, $default = true)
    {
        $value = $this->get_setting($type, $default ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off);
        return $value === SetupControlSwitchDefs::switch_on;
    }

    /**
     * Set plugin boolean parameters
     *
     * @param string $type
     * @param bool $val
     */
    public function set_bool_setting($type, $val = true)
    {
        $this->set_setting($type, $val ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off);
    }

    /**
     * @return array
     */
    public function get_selected_xmltv_sources()
    {
        return $this->sql_playlist->fetch_single_array("SELECT hash FROM $this->pl_sel_xmltv;", PARAM_HASH);
    }

    /**
     * @param array $values
     */
    public function set_selected_xmltv_sources($values)
    {
        $query = "DELETE FROM $this->pl_sel_xmltv;";
        foreach ($values as $hash) {
            $query .= "INSERT INTO $this->pl_sel_xmltv (hash) VALUES ('$hash');";
        }

        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param Hashed_Array $values
     */
    public function set_playlist_xmltv($values)
    {
        $query = "DELETE FROM $this->pl_xmltv;";
        /** @var Named_Storage $params */
        foreach ($values as $key => $params) {
            $q_type = Sql_Wrapper::sql_quote(PARAM_LINK);
            $q_name = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_NAME));
            $q_uri = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_URI));
            $q_cache = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_CACHE, XMLTV_CACHE_AUTO));
            $query .= "INSERT INTO $this->pl_xmltv (hash, type, name, uri, cache) VALUES ('$key', $q_type, $q_name, $q_uri, $q_cache);";
        }

        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * Get dune_params
     *
     * @return array
     */
    public function get_dune_params()
    {
        $ret_array = array();
        foreach ($this->sql_playlist->fetch_array("SELECT * FROM $this->pl_dune_params;") as $pair) {
            $ret_array[$pair['param']] = $pair['value'];
        }

        return $ret_array;
    }

    /**
     * Get cookie
     *
     * @param string $name
     * @param bool $check_expire
     * @return string
     */
    public function get_cookie($name, $check_expire = false)
    {
        if ($check_expire) {
            $were = "param = '$name' AND time_stamp > " . time();
        } else {
            $were = "param = '$name'";
        }
        return $this->sql_playlist->query_value("SELECT value FROM $this->pl_cookies WHERE $were;");
    }

    /**
     * Get cookie
     *
     * @param string $name
     * @param string $value
     * @param int|null $expired
     */
    public function set_cookie($name, $value, $expired = null)
    {
        if ($expired === null) {
            $expired = time();
        }

        $this->sql_playlist->exec("INSERT OR REPLACE INTO $this->pl_cookies (param, value, time_stamp) VALUES ('$name', '$value', '$expired');");
    }

    /**
     * Get cookie
     *
     * @param string $name
     */
    public function remove_cookie($name)
    {
        $this->sql_playlist->exec("DELETE FROM $this->pl_cookies WHERE param = '$name';");
    }

    /**
     * Set dune_params
     *
     * @param array $value
     */
    public function set_dune_params($value)
    {
        $query = "DELETE FROM $this->pl_dune_params;";
        foreach ($value as $k => $v) {
            $q_k = Sql_Wrapper::sql_quote($k);
            $q_v = Sql_Wrapper::sql_quote($v);
            $query .= "INSERT INTO $this->pl_dune_params (param, value) VALUES ($q_k, $q_v);";
        }
        $this->sql_playlist->exec_transaction($query);
    }

    ////////////////////////////////////////////////////////
    /// init database

    /**
     * @return void
     */
    public function reset_playlist_db()
    {
        hd_debug_print(null, true);
        $this->sql_playlist = null;
    }

    public function init_playlist_db()
    {
        hd_debug_print(null, true);
        $playlist_id = $this->get_parameter(PARAM_CUR_PLAYLIST_ID);
        if (empty($playlist_id)) {
            hd_debug_print("Empty playlist id");
            return false;
        }

        $active_playlist = $this->get_playlist($playlist_id);
        if (empty($this->active_playlist)) {
            $this->active_playlist = $active_playlist;
        }

        hd_debug_print_separator();
        if (empty($this->active_playlist)) {
            hd_debug_print("Playlist info for ID: $playlist_id is not exist!");
        } else {
            hd_debug_print("Process playlist: {$this->active_playlist[PARAM_NAME]} ($playlist_id)");
        }

        $db_name = get_data_path("$playlist_id.db");

        if ($this->sql_playlist) {
            // attach to playlist db. if db not exist it will be created
            if ($this->is_database_attached('main', $playlist_id) === 1) {
                hd_debug_print("Database already inited!", true);
                return true;
            }
            $this->reset_playlist_db();
        }

        hd_debug_print("Load database: $db_name", true);
        $this->sql_playlist = new Sql_Wrapper($db_name);

        // create settings table
        $query  = "CREATE TABLE IF NOT EXISTS $this->pl_settings (name TEXT PRIMARY KEY, value TEXT DEFAULT '', type TEXT DEFAULT '');";
        $query .= "CREATE TABLE IF NOT EXISTS $this->pl_xmltv (hash TEXT PRIMARY KEY, type TEXT, name TEXT, uri TEXT, cache TEXT);";
        $query .= "CREATE TABLE IF NOT EXISTS $this->pl_sel_xmltv (id INTEGER PRIMARY KEY, hash TEXT UNIQUE);";
        $query .= "CREATE TABLE IF NOT EXISTS $this->pl_ch_params (channel_id TEXT PRIMARY KEY, zoom TEXT DEFAULT 'x', external_player INTEGER DEFAULT 0);";
        $query .= "CREATE TABLE IF NOT EXISTS $this->pl_dune_params (param TEXT PRIMARY KEY, value TEXT DEFAULT '');";
        $query .= "CREATE TABLE IF NOT EXISTS $this->pl_cookies (param TEXT PRIMARY KEY, value TEXT DEFAULT '', time_stamp INTEGER DEFAULT 0);";

        // create tables for vod search, vod filters, vod favorites
        foreach (array(VOD_FILTER_LIST => 'item', VOD_SEARCH_LIST => 'item', FAV_MOVIE_GROUP_ID => 'channel_id') as $list => $column) {
            $table_name = self::get_table_name($list);
            $query .= "CREATE TABLE IF NOT EXISTS $table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, $column TEXT UNIQUE);";
        }
        $this->sql_playlist->exec_transaction($query);

        $group_icons = new Hashed_Array();
        $settings_path = get_data_path("$playlist_id.settings");
        if (file_exists($settings_path)) {
            hd_debug_print("Load (PLUGIN_SETTINGS): $playlist_id.settings");
            $plugin_settings = HD::get_items($settings_path, true, false);

            // convert old settings
            if (array_key_exists('cur_xmltv_sources', $plugin_settings)) {
                $active_sources = $plugin_settings['cur_xmltv_sources'];
                hd_debug_print("convert active sources from hashed array: " . $active_sources, true);
                $active_sources = $active_sources->get_keys();
                $plugin_settings[PARAM_SELECTED_XMLTV_SOURCES] = $active_sources;
            }

            // Move old parameters show icons to settings
            $move_parameters = array(PARAM_SHOW_ALL, PARAM_SHOW_FAVORITES, PARAM_SHOW_HISTORY, PARAM_SHOW_CHANGED_CHANNELS, PARAM_SHOW_VOD);
            foreach ($move_parameters as $parameter) {
                if (!array_key_exists($parameter, $plugin_settings)) {
                    $plugin_settings[$parameter] = $this->get_bool_parameter($parameter) ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off;
                }
            }

            // Fix show_vod setting
            if (isset($plugin_settings[PARAM_SHOW_VOD])) {
                if ($plugin_settings[PARAM_SHOW_VOD] !== SetupControlSwitchDefs::switch_on
                    && $plugin_settings[PARAM_SHOW_VOD] !== SetupControlSwitchDefs::switch_off) {
                    $plugin_settings[PARAM_SHOW_VOD] = SetupControlSwitchDefs::switch_on;
                }
            } else {
                $plugin_settings[PARAM_SHOW_VOD] = SetupControlSwitchDefs::switch_off;
            }

            // remove obsolete settings
            $removed_settings = array(
                'cur_xmltv_sources', 'epg_cache_ttl', 'epg_cache_ttl',
                'force_http', 'epg_cache_type');
            foreach ($removed_settings as $parameter) {
                if (array_key_exists($parameter, $plugin_settings)) {
                    unset($plugin_settings[$parameter]);
                }
            }

            foreach ($plugin_settings as $key => $param) {
                hd_debug_print("$key => '" . (is_array($param) ? json_encode($param) : $param) . "'", true);
            }

            // Move settings to db
            $query = '';
            foreach ($plugin_settings as $key => $value) {
                $type = gettype($value);
                if ($type === 'object' || $type === 'array') continue;
                if ($type === 'NULL') {
                    $type = 'string';
                    $value = '';
                }
                if ($key !== 'dune_params') {
                    $q_name = Sql_Wrapper::sql_quote($key);
                    $q_value = Sql_Wrapper::sql_quote($value);
                    $q_type = Sql_Wrapper::sql_quote($type);
                    $query .= "INSERT OR IGNORE INTO $this->pl_settings (name, value, type) VALUES ($q_name, $q_value, $q_type);";
                }
                unset($plugin_settings[$key]);
            }
            $this->sql_playlist->exec_transaction($query);

            // Move epg_playlist, selected_xmltv_sorces, channel_zoom, channel_player to tables
            foreach ($plugin_settings as $key => $value) {
                $type = gettype($value);
                if ($type !== 'object' && $type !== 'array') continue;

                if ($key === PARAM_EPG_PLAYLIST) {
                    hd_debug_print("Convert 'epg_playlist' to 'playlist_xmltv' table");
                    $query = '';
                    /** @var Named_Storage $v */
                    foreach ($value as $k => $v) {
                        $q_channel_id = Sql_Wrapper::sql_quote($k);
                        $q_type = Sql_Wrapper::sql_quote(empty($v->type) ? PARAM_LINK : $v->type);
                        $q_name = Sql_Wrapper::sql_quote($v->name);
                        $q_uri = Sql_Wrapper::sql_quote($v->params[PARAM_URI]);
                        $q_cache = Sql_Wrapper::sql_quote(isset($v->params[PARAM_CACHE]) ? $v->params[PARAM_CACHE] : XMLTV_CACHE_AUTO);
                        $query .= "INSERT OR IGNORE INTO $this->pl_xmltv
                                    (hash, type, name, uri, cache) VALUES ($q_channel_id, $q_type, $q_name, $q_uri, $q_cache);";
                    }
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_EPG_PLAYLIST]);
                } else if ($key === PARAM_SELECTED_XMLTV_SOURCES) {
                    hd_debug_print("Convert 'selected_xmltv_sources' to 'selected_xmltv' table");
                    $query = '';
                    foreach ($value as $hash) {
                        $q_hash = Sql_Wrapper::sql_quote($hash);
                        $query .= "INSERT OR IGNORE INTO $this->pl_sel_xmltv (hash) VALUES ($q_hash);";
                    }
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_SELECTED_XMLTV_SOURCES]);
                } else if ($key === PARAM_CHANNELS_ZOOM) {
                    hd_debug_print("Convert 'channels_zoom' to 'channel_params' table");
                    $query = '';
                    foreach ($value as $channel_id => $zoom) {
                        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
                        $q_zoom = Sql_Wrapper::sql_quote($zoom);
                        $query = "INSERT OR IGNORE INTO $this->pl_ch_params (channel_id, zoom) VALUES ($q_channel_id, $q_zoom);";
                        $query .= "UPDATE $this->pl_ch_params SET zoom = $q_zoom WHERE channel_id = $q_channel_id;";
                    }
                    $query .= "DELETE FROM $this->pl_ch_params WHERE zoom = 'x' AND external_player = 0;";
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_CHANNELS_ZOOM]);
                } else if ($key === PARAM_CHANNEL_PLAYER) {
                    hd_debug_print("Convert 'channel_player' to 'channel_params' table");
                    $query = '';
                    foreach ($value as $channel_id) {
                        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
                        $query = "INSERT OR IGNORE INTO $this->pl_ch_params (channel_id, external_player) VALUES ($q_channel_id, 1);";
                        $query .= "UPDATE $this->pl_ch_params SET external_player = 1 WHERE channel_id = $q_channel_id;";
                    }
                    $query .= "DELETE FROM $this->pl_ch_params WHERE zoom = 'x' AND external_player = 0;";
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_CHANNEL_PLAYER]);
                } else if ($key === PARAM_DUNE_PARAMS) {
                    hd_debug_print("Convert 'dune_params' to 'dune_params' table");
                    $query = '';
                    foreach ($value as $k => $v) {
                        $q_k = Sql_Wrapper::sql_quote($k);
                        $q_v = Sql_Wrapper::sql_quote($v);
                        $query .= "INSERT OR IGNORE INTO $this->pl_dune_params (param, value) VALUES ($q_k, $q_v);";
                    }
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_DUNE_PARAMS]);
                }
            }

            // move group icons from settings to db (for old plugin settings)
            if (array_key_exists(PARAM_GROUPS_ICONS, $plugin_settings)) {
                $group_icons = $plugin_settings[PARAM_GROUPS_ICONS];
                unset($plugin_settings[PARAM_GROUPS_ICONS]);
            }

            if (empty($plugin_settings)) {
                hd_debug_print("Remove settings: $settings_path");
                unlink($settings_path);
            } else {
                foreach ($plugin_settings as $key => $value) {
                    hd_debug_print("!!!!! Setting $key is not imported: " . $value);
                }
            }
        }

        $tokens = array(PARAM_TOKEN => "$playlist_id.token", PARAM_REFRESH_TOKEN => "$playlist_id.refresh_token", PARAM_SESSION_ID => "{$playlist_id}_session_id");
        foreach ($tokens as $key => $value) {
            $token_path = get_data_path("$playlist_id.$key");
            if (file_exists($token_path)) {
                hd_debug_print("Move '$key' to 'cookies' table");
                $time_stamp = filemtime($token_path);
                $q_value = Sql_Wrapper::sql_quote(file_get_contents($token_path));
                $query = "INSERT INTO $this->pl_cookies (param, value, time_stamp) VALUES('$key', $q_value, $time_stamp);";
                $this->sql_playlist->exec($query);
                hd_debug_print("Remove cookie: $token_path");
                unlink($token_path);
            }
        }

        // transfer old orders settings to new db
        $provider_playlist_id = '';
        $provider = null;
        $plugin_orders_name = $playlist_id . '_' . PLUGIN_ORDERS;
        $provider_class = safe_get_value($this->active_playlist[PARAM_PARAMS], PARAM_PROVIDER);
        if (empty($provider_class)) {
            hd_debug_print("Playlist is not a IPTV provider");
        } else {
            $provider = $this->create_provider_class($provider_class);
            if (is_null($provider)) {
                hd_debug_print("Unknown provider class: " . $this->active_playlist[PARAM_PARAMS][PARAM_PROVIDER]);
            } else {
                $id = $provider->getId();
                if (!$provider->getEnable()) {
                    hd_debug_print("Provider $id is disabled");
                } else {
                    $provider->set_provider_playlist_info($this->get_active_playlist_key(), $this->active_playlist);
                }

                $name = $provider->getName();
                $playlist_id = $provider->get_provider_playlist_id();
                hd_debug_print("Using provider $id ($name) - playlist id: $playlist_id");

                $provider_playlist_id = $provider->getParameter(MACRO_PLAYLIST_ID);
                $id = empty($provider_playlist_id) ? '' : "_$provider_playlist_id";
                $plugin_orders_name = $playlist_id . '_' . PLUGIN_ORDERS . $id;

                $config_sources = $provider->getConfigValue(CONFIG_XMLTV_SOURCES);
                if (!empty($config_sources)) {
                    $query = '';
                    $q_type = Sql_Wrapper::sql_quote(PARAM_CONF);
                    $q_cache = Sql_Wrapper::sql_quote(XMLTV_CACHE_AUTO);
                    $known_sources = array();
                    foreach ($config_sources as $source) {
                        $hash = Hashed_Array::hash($source);
                        $q_hash = Sql_Wrapper::sql_quote($hash);
                        $q_source = Sql_Wrapper::sql_quote($source);
                        $q_name = Sql_Wrapper::sql_quote(basename($source));
                        $known_sources[] = $hash;

                        $query .= "INSERT OR IGNORE INTO $this->pl_xmltv
                                (hash, type, name, uri, cache) VALUES ($q_hash, $q_type, $q_name, $q_source, $q_cache);";
                    }

                    if (!empty($known_sources)) {
                        $q_known_sources = SQL_Wrapper::sql_make_list_from_quoted_values($known_sources);
                        $query .= "DELETE FROM $this->pl_xmltv WHERE type = $q_type AND hash NOT IN ($q_known_sources);";
                    }
                    $this->sql_playlist->exec_transaction($query);
                }
            }
        }

        $db_name = get_data_path("$plugin_orders_name.db");
        $this->sql_playlist->exec("ATTACH DATABASE '$db_name' AS " . self::PL_ORDERS_DB);

        // create group table
        $query = "CREATE TABLE IF NOT EXISTS $this->pl_groups_parameters
                                        (id INTEGER PRIMARY KEY AUTOINCREMENT,
                                         group_id TEXT UNIQUE,
                                         title TEXT DEFAULT '',
                                         icon TEXT DEFAULT '',
                                         adult INTEGER DEFAULT 0,
                                         disabled INTEGER DEFAULT 0,
                                         special INTEGER DEFAULT 0
                                         );";
        // create channels table
        $query .= "CREATE TABLE IF NOT EXISTS $this->pl_channels
                            (channel_id TEXT PRIMARY KEY NOT NULL, title TEXT DEFAULT '',
                             group_id TEXT DEFAULT '', disabled INTEGER DEFAULT 0,
                             adult INTEGER DEFAULT 0, changed INTEGER DEFAULT 0);";
        // create order_groups table
        $query .= "CREATE TABLE IF NOT EXISTS $this->pl_groups_order (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id TEXT UNIQUE);";

        // create tables for favorites
        $table_name = self::get_table_name(FAV_CHANNELS_GROUP_ID);
        $query .= "CREATE TABLE IF NOT EXISTS $table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";

        $this->sql_playlist->exec_transaction($query);

        // add special groups to the table if the not exists
        $special_group = array(
            array('group_id' => FAV_CHANNELS_GROUP_ID, 'title' => FAV_CHANNELS_GROUP_CAPTION, 'icon' => FAV_CHANNELS_GROUP_ICON),
            array('group_id' => HISTORY_GROUP_ID, 'title' => HISTORY_GROUP_CAPTION, 'icon' => HISTORY_GROUP_ICON),
            array('group_id' => CHANGED_CHANNELS_GROUP_ID, 'title' => CHANGED_CHANNELS_GROUP_CAPTION, 'icon' => CHANGED_CHANNELS_GROUP_ICON),
            array('group_id' => VOD_GROUP_ID, 'title' => VOD_GROUP_CAPTION, 'icon' => VOD_GROUP_ICON),
            array('group_id' => ALL_CHANNELS_GROUP_ID, 'title' => ALL_CHANNELS_GROUP_CAPTION, 'icon' => ALL_CHANNELS_GROUP_ICON),
        );

        $query = '';
        foreach ($special_group as $group) {
            $group['special'] = 1;
            $values = Sql_Wrapper::sql_make_insert_list($group);
            $query .= "INSERT OR IGNORE INTO $this->pl_groups_parameters $values;";
        }
        $this->sql_playlist->exec_transaction($query);

        $orders_file = get_data_path("$plugin_orders_name.settings");
        if (file_exists($orders_file)) {
            hd_debug_print("Load (PLUGIN_ORDERS): $plugin_orders_name.settings");
            $plugin_orders = HD::get_items($orders_file, true, false);
            foreach ($plugin_orders as $key => $param) {
                hd_debug_print("$key => '" . (is_array($param) ? json_encode($param) : $param) . "'", true);
            }

            // Current group icons in the orders settings
            if (isset($plugin_orders[PARAM_GROUPS_ICONS])){
                // get group icons from orders
                $group_icons = $plugin_orders[PARAM_GROUPS_ICONS];
                unset($plugin_orders[PARAM_GROUPS_ICONS]);
            }

            // move groups order to database
            if (isset($plugin_orders[PARAM_GROUPS_ORDER]) && $plugin_orders[PARAM_GROUPS_ORDER]->size() !== 0) {
                hd_debug_print("Move 'group_orders' to 'groups' db table");
                $query = '';
                foreach ($plugin_orders[PARAM_GROUPS_ORDER] as $group_id) {
                    $adult = M3uParser::is_adult_group($group_id);
                    $q_group_id = Sql_Wrapper::sql_quote($group_id);
                    $group_icon = Sql_Wrapper::sql_quote($group_icons->has($group_id) ? $group_icons->get($group_id) : DEFAULT_GROUP_ICON);
                    $query .= "INSERT OR IGNORE INTO $this->pl_groups_parameters
                                (group_id, title, icon, adult) VALUES ($q_group_id, $q_group_id, $group_icon, $adult);";
                    $query .= "INSERT OR IGNORE INTO $this->pl_groups_order (group_id) VALUES ($q_group_id);";
                }
                $this->sql_playlist->exec_transaction($query);

                unset($plugin_orders[PARAM_GROUPS_ORDER]);
            }

            // move disabled groups to database
            if (isset($plugin_orders[PARAM_DISABLED_GROUPS]) && $plugin_orders[PARAM_DISABLED_GROUPS]->size() !== 0) {
                hd_debug_print("Move 'disabled_group' orders to 'groups' db table");
                $query = '';
                foreach ($plugin_orders[PARAM_DISABLED_GROUPS] as $group_id) {
                    $adult = M3uParser::is_adult_group($group_id);
                    $q_group_id = Sql_Wrapper::sql_quote($group_id);
                    $group_icon = Sql_Wrapper::sql_quote($group_icons->has($group_id) ? $group_icons->get($group_id) : DEFAULT_GROUP_ICON);
                    $query .= "INSERT OR IGNORE INTO $this->pl_groups_parameters
                                (group_id, title, icon, disabled, adult) VALUES ($q_group_id, $q_group_id, $group_icon, 1, $adult);";
                }
                $this->sql_playlist->exec_transaction($query);
                unset($plugin_orders[PARAM_DISABLED_GROUPS]);
            }

            // create known_channels db if not exist and import old orders settings
            if (isset($plugin_orders[PARAM_KNOWN_CHANNELS]) && $plugin_orders[PARAM_KNOWN_CHANNELS]->size() !== 0) {
                hd_debug_print("Move 'known_channels' to 'channels' db table");
                $query = '';
                foreach ($plugin_orders[PARAM_KNOWN_CHANNELS] as $channel_id => $title) {
                    $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
                    $q_title = Sql_Wrapper::sql_quote($title);
                    $query .= "INSERT OR IGNORE INTO $this->pl_channels (channel_id, title) VALUES ($q_channel_id, $q_title);";
                }
                $this->sql_playlist->exec_transaction($query);
                unset($plugin_orders[PARAM_KNOWN_CHANNELS]);
            }

            if (isset($plugin_orders[PARAM_DISABLED_CHANNELS]) && $plugin_orders[PARAM_DISABLED_CHANNELS]->size() !== 0) {
                hd_debug_print("Move 'disabled_channels' to 'channels' db table");
                $values = Sql_Wrapper::sql_make_list_from_quoted_values($plugin_orders[PARAM_DISABLED_CHANNELS]->get_order());
                $query = "UPDATE $this->pl_channels SET disabled = 1 WHERE channel_id IN ($values);";
                $this->sql_playlist->exec($query);
                unset($plugin_orders[PARAM_DISABLED_CHANNELS]);
            }

            foreach ($plugin_orders as $order_name => $order) {
                $table_name = self::get_table_name($order_name);
                hd_debug_print("Move '$order_name' channels orders to $table_name db table");
                $query = "CREATE TABLE IF NOT EXISTS $table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";
                foreach ($order as $channel_id) {
                    $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
                    $query .= "INSERT OR IGNORE INTO $table_name (channel_id) VALUES ($q_channel_id);";
                }
                $this->sql_playlist->exec_transaction($query);
                unset($plugin_orders[$order_name]);
            }

            if (empty($orders)) {
                hd_debug_print("Remove orders: $orders_file");
                unlink($orders_file);
            } else {
                HD::put_data_items("$plugin_orders_name.settings", $orders, false);
                foreach ($plugin_orders as $key => $value) {
                    hd_debug_print("!!!!! Order $key is not imported: " . $value);
                }
            }
        }

        // attach to tv_history db. if db not exist it will be created
        // tv history is per playlist or per provider playlist
        $history_path = get_slash_trailed_path($this->get_history_path());
        $tv_history_db_name = $history_path . $this->make_name(PARAM_TV_HISTORY, $provider_playlist_id);
        $this->sql_playlist->exec("ATTACH DATABASE '$tv_history_db_name.db' AS " . self::TV_HIISTORY_DB);
        // create tv history table
        $query = "CREATE TABLE IF NOT EXISTS $this->tv_history (channel_id TEXT UNIQUE NOT NULL, time_stamp INTEGER DEFAULT 0);";
        $this->sql_playlist->exec($query);

        $tv_history_name = $history_path . $this->make_name(PARAM_TV_HISTORY_ITEMS);
        if (file_exists($tv_history_name)) {
            $points = HD::get_items($tv_history_name);
            hd_debug_print("Load (PLUGIN TV HISTORY) from: $tv_history_name", true);
            $query = '';
            foreach ($points as $key => $item) {
                $q_key = Sql_Wrapper::sql_quote($key);
                $item = (int)$item;
                $query .= "INSERT OR IGNORE INTO $this->tv_history (channel_id, time_stamp) VALUES ($q_key, $item);";
            }
            $query .= "DELETE FROM $this->tv_history WHERE rowid NOT IN (SELECT rowid FROM $this->tv_history ORDER BY time_stamp DESC LIMIT 7);";
            $this->sql_playlist->exec_transaction($query);
            hd_debug_print("Remove TV History: $tv_history_name");
            HD::erase_items($tv_history_name);
        }

        // create vod history table
        if ($this->is_vod_playlist() || ($provider !== null && $provider->hasApiCommand(API_COMMAND_GET_VOD))) {
            // vod history is only one per playlist
            $vod_history_name = $history_path . $this->make_name(VOD_HISTORY);
            $this->sql_playlist->exec("ATTACH DATABASE '$vod_history_name.db' AS " . self::VOD_HISTORY_DB);
            $query = "CREATE TABLE IF NOT EXISTS $this->vod_history
                        (movie_id TEXT,
                         series_id TEXT,
                         watched INTEGER DEFAULT 0,
                         position INTEGER DEFAULT 0,
                         duration INTEGER DEFAULT 0,
                         time_stamp INTEGER DEFAULT 0,
                         UNIQUE(movie_id, series_id)
                        );";
            $this->sql_playlist->exec($query);

            $vod_history_filename = $history_path . $this->make_name(PLUGIN_HISTORY) . ".settings";
            if (file_exists($vod_history_filename)) {
                hd_debug_print("Load (PLUGIN VOD HISTORY): $vod_history_filename");
                /** @var array $history */
                $history = HD::get_items($vod_history_filename, true, false);
                if (isset($history[VOD_HISTORY]) && $history[VOD_HISTORY]->size() !== 0) {
                    hd_debug_print("Move '" . VOD_HISTORY . "' to 'vod_history' db table");
                    $query = '';
                    /** @var array $param */
                    foreach ($history[VOD_HISTORY] as $movie_id => $param) {
                        hd_debug_print("$movie_id => '" . (is_array($param) ? json_encode($param) : $param) . "'", true);
                        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
                        /** @var History_Item $item */
                        foreach ($param as $series_id => $item) {
                            $q_series_id = Sql_Wrapper::sql_quote($series_id);
                            $watched = (int)$item->watched;
                            $query .= "INSERT OR IGNORE INTO $this->vod_history
                                (movie_id, series_id, watched, position, duration, time_stamp)
                                VALUES ($q_movie_id, $q_series_id, $watched, $item->position, $item->duration, $item->date);";
                        }
                    }
                    $this->sql_playlist->exec_transaction($query);
                    unset($history[VOD_HISTORY]);
                }

                $query = '';
                foreach (array(VOD_FILTER_LIST, VOD_SEARCH_LIST) as $list) {
                    if (!isset($history[$list]) || $history[$list]->size() === 0) continue;

                    $table_name = self::get_table_name($list);
                    hd_debug_print("Move '$list' to '$table_name' db table");

                    foreach ($history[$list]->get_order() as $value) {
                        $q_item = Sql_Wrapper::sql_quote($value);
                        $query .= "INSERT OR IGNORE INTO $table_name (item) VALUES ($q_item);";
                    }

                    unset($history[$list]);
                }
                $this->sql_playlist->exec_transaction($query);

                if (empty($history)) {
                    hd_debug_print("Remove VOD history: $vod_history_filename");
                    unlink($vod_history_filename);
                } else {
                    HD::put_items($vod_history_filename, $history, false);
                    foreach ($history as $type => $param) {
                        hd_debug_print("!!!!! Vod history $type is not imported: " . (is_array($param) ? json_encode($param) : $param), true);
                    }
                }
            }
        }

        hd_debug_print("Database initialized.");
        hd_debug_print_separator();

        $this->init_screen_view_parameters($this->get_background_image());

        return true;
    }

    /**
     * Remove all data for selected playlist
     * @param string $playlist_id
     * @param bool $remove_playlist
     */
    public function remove_playlist_data($playlist_id, $remove_playlist = false)
    {
        if (empty($playlist_id)) {
            return;
        }

        $need_reset = $this->get_active_playlist_key() === $playlist_id;

        if ($remove_playlist) {
            $this->sql_params->exec("DELETE FROM playlists WHERE playlist_id = '$playlist_id'");
        }

        if ($need_reset) {
            $this->reset_playlist_db();
        }

        // remove settings
        foreach (glob_dir(get_data_path(), "/^$playlist_id.*$/i") as $file) {
            hd_debug_print("remove settings or orders db: $file", true);
            unlink($file);
        }

        // history
        foreach (glob_dir($this->get_history_path(), "/^$playlist_id.*$/i") as $file) {
            hd_debug_print("remove history: $file", true);
            unlink($file);
        }

        // clear cached images for selected id
        foreach (glob_dir(get_cached_image_path(), "/^$playlist_id.*$/i") as $file) {
            hd_debug_print("remove cached image: $file", true);
            unlink($file);
        }
    }

    /**
     * Return 1 if database attached and filename of the database the same
     * Return 2 if database attached and filename not set
     * Return 0 if no database attached or filename not match
     *
     * @param string $db_name
     * @param string $db_filename
     * @return int
     */
    public function is_database_attached($db_name, $db_filename = null)
    {
        if ($this->sql_playlist) {
            foreach ($this->sql_playlist->fetch_array("PRAGMA database_list") as $database) {
                if ($database['name'] == $db_name) {
                    hd_debug_print("Database exist: {$database['name']}", true);
                    if ($db_filename == null) {
                        return 2;
                    }
                    if (basename($database['file']) === "$db_filename.db") {
                        return 1;
                    }
                }
            }
            hd_debug_print("Not exist: $db_name, with filename: $db_filename.db", true);
        } else {
            hd_debug_print("No sql wrapper", true);
        }
        return 0;
    }

    public function get_pl_error_name()
    {
        return $this->get_custom_error_name("pl_last_error");
    }

    public function get_vod_error_name()
    {
        return $this->get_custom_error_name("vod_last_error");
    }

    public function get_request_error_name()
    {
        return $this->get_custom_error_name("request_last_error");
    }

    public function get_custom_error_name($source)
    {
        return $this->get_active_playlist_key() . "_$source";
    }

    /**
     * @param string $type
     * @return string
     */
    private static function type_to_val($type)
    {
        if ($type === 'new') {
            $val = "changed = 1";
        } else if ($type === 'removed') {
            $val = "changed = -1";
        } else {
            $val = "changed != 0";
        }

        return $val;
    }
}
