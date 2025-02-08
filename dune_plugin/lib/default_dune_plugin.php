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

    /*
    * Map attributes to database columns
    */
    public static $id_to_column_mapper = array(
        ATTR_CHANNEL_ID => "ch_id",     // channel id found by regex parser
        ATTR_CUID => "ch_id",           // attribute CUID
        ATTR_TVG_ID => "epg_id",        // attribute tvg-id
        ATTR_TVG_NAME => "tvg_name",    // attribute tvg-name
        ATTR_CHANNEL_NAME => "title",   // channel title
        ATTR_CHANNEL_HASH => "hash",    // url hash
    );

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
        $this->sql_playlist = new Sql_Wrapper(new SQLite3(":memory:", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, ''));
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

        $this->active_provider = null;
        $this->active_playlist = $this->get_playlist($id);

        $this->set_parameter(PARAM_CUR_PLAYLIST_ID, $id);
    }

    /**
     * @return Hashed_Array
     */
    public function get_all_playlists()
    {
        // CREATE TABLE IF NOT EXISTS 'playlists' (id INTEGER PRIMARY_KEY AUTOINCREMENT, db_name TEXT UNIQUE, name TEXT, params TEXT);

        $rows = $this->sql_params->fetch_array("SELECT * FROM playlists ORDER BY id;");
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
        return $this->sql_params->query_value("SELECT count(*) FROM playlists;");
    }

    /**
     * @param string $id
     * @return array
     */
    public function get_playlist($id)
    {
        // CREATE TABLE IF NOT EXISTS 'playlists' (id INTEGER PRIMARY_KEY AUTOINCREMENT, playlist_id TEXT UNIQUE, name TEXT, type TEXT, params TEXT);

        $q_key = Sql_Wrapper::sql_quote($id);
        $row = $this->sql_params->query_value("SELECT * FROM playlists WHERE playlist_id = $q_key LIMIT 1;", true);
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
        // CREATE TABLE IF NOT EXISTS 'playlists' (id INTEGER PRIMARY_KEY AUTOINCREMENT, playlist_id TEXT UNIQUE, name TEXT, type TEXT, params TEXT);

        $q_key = Sql_Wrapper::sql_quote($id);
        $q_type = Sql_Wrapper::sql_quote($stg[PARAM_TYPE]);
        $q_name = Sql_Wrapper::sql_quote($stg[PARAM_NAME]);
        $q_params = Sql_Wrapper::sql_quote(json_encode($stg[PARAM_PARAMS]));
        $this->sql_params->exec("INSERT OR REPLACE INTO playlists (playlist_id, name, type, params) VALUES ($q_key, $q_name, $q_type, $q_params);");
    }

    /**
     * @param string $playlist_id
     * @param int $direction
     * @return bool
     */
    public function arrange_playlist_order_rows($playlist_id, $direction)
    {
        $q_playlist_id = Sql_Wrapper::sql_quote($playlist_id);
        $qry = "SELECT previous, current, next
            FROM (SELECT playlist_id,
                         LAG(id) OVER (ORDER BY id)  AS previous,
                         id                          AS current,
                         LEAD(id) OVER (ORDER BY id) AS next,
                         FIRST_VALUE(id) OVER (ORDER BY id) AS first,
                         FIRST_VALUE(id) OVER (ORDER BY id DESC) AS last
                  FROM playlists)
            WHERE playlist_id = $q_playlist_id;";

        return $this->arrange_rows($qry, 'playlists', $direction);
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
            if ($this->load_channels($plugin_cookies) === 0) {
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

            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $this->arrange_channels_order_rows(FAV_CHANNELS_GROUP_ID, $channel_id, Ordered_Array::UP);
                break;

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

        return Starnet_Epfs_Handler::epfs_invalidate_folders(
            array(Starnet_Tv_Favorites_Screen::get_media_url_string(FAV_CHANNELS_GROUP_ID),
                Starnet_Tv_Channel_List_Screen::get_media_url_string(ALL_CHANNELS_GROUP_ID)
            )
        );
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
        $query = "SELECT zoom FROM settings.channel_params WHERE channel_id = $q_channel_id";
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
        $query = "INSERT OR IGNORE INTO settings.channel_params (channel_id, zoom) VALUES ($q_channel_id, $q_preset);";
        $query .= "UPDATE settings.channel_params SET zoom = $q_preset WHERE channel_id = $q_channel_id;";
        $query .= "DELETE FROM settings.channel_params WHERE zoom = 'x' AND external_player = 0;";
        $this->sql_playlist->exec_transaction($query);
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
        $query = "INSERT OR IGNORE INTO settings.channel_params (channel_id, external_player) VALUES ($q_channel_id, $q_external);";
        $query .= "UPDATE settings.channel_params SET external_player = $q_external WHERE channel_id = $q_channel_id;";
        $query .= "DELETE FROM settings.channel_params WHERE zoom = 'x' AND external_player = 0;";
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @return bool
     */
    public function get_channel_ext_player($channel_id)
    {
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $query = "SELECT external_player FROM settings.channel_params WHERE channel_id = $q_channel_id";
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
        $table_name = self::get_orders_table_name($table);
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
        $table_name = self::get_orders_table_name($table);
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
        $table_name = self::get_orders_table_name($table);
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
        $table_name = self::get_orders_table_name($table);
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
        $table_name = self::get_orders_table_name($table);
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
        $table_name = self::get_orders_table_name($table);
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
        $table_name = self::get_orders_table_name($table);
        $q_value = Sql_Wrapper::sql_quote($item);
        $query = "SELECT previous, current, next
            FROM (SELECT item,
                         LAG(id) OVER (ORDER BY id)  AS previous,
                         id                          AS current,
                         LEAD(id) OVER (ORDER BY id) AS next,
                         FIRST_VALUE(id) OVER (ORDER BY id) AS first,
                         FIRST_VALUE(id) OVER (ORDER BY id DESC) AS last
                  FROM $table_name)
            WHERE item = $q_value;";

        return $this->arrange_rows($query, $table_name, $direction);
    }

    /**
     * Get VOD history for selected playlist sorted by movie_id and last viewed time_stamp (most recent first)
     *
     * @return array
     */
    public function get_all_history()
    {
        return $this->sql_playlist->fetch_array("SELECT *, MAX(time_stamp) FROM vod_history.history GROUP BY movie_id ORDER BY time_stamp DESC;");
    }

    /**
     * Get count of VOD history for selected playlist
     *
     * @return int
     */
    public function get_all_history_count()
    {
        return $this->sql_playlist->query_value("SELECT count(DISTINCT movie_id) FROM vod_history.history;");
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @return array
     */
    public function get_history($movie_id)
    {
        $q_id = Sql_Wrapper::sql_quote($movie_id);
        return $this->sql_playlist->fetch_array("SELECT * FROM vod_history.history WHERE movie_id = $q_id;");
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @return int
     */
    public function get_history_count($movie_id)
    {
        $q_id = Sql_Wrapper::sql_quote($movie_id);
        return $this->sql_playlist->query_value("SELECT count(*) FROM vod_history.history WHERE movie_id = $q_id;");
    }

    /**
     * Get param for movie_id and series_id
     *
     * @param string $movie_id
     * @param string $series_id
     * @param string $param_name
     * @return array
     */
    public function get_history_params($movie_id, $series_id, $param_name = null)
    {
        $q_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series = Sql_Wrapper::sql_quote($series_id);
        if ($param_name === null) {
            $query = "SELECT * FROM vod_history.history WHERE movie_id = $q_id AND series_id = $q_series;";
        } else {
            $q_param = Sql_Wrapper::sql_quote($param_name);
            $query = "SELECT $q_param FROM vod_history.history WHERE movie_id = $q_id AND series_id = $q_series;";
        }
        return $this->sql_playlist->query_value($query, $param_name === null);
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @param string $series_id
     * @param array $value
     * @return void
     */
    public function set_history($movie_id, $series_id, $value)
    {
        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series_id = Sql_Wrapper::sql_quote($series_id);
        $params = SQL_Wrapper::sql_make_list_from_keys($value);
        $values = SQL_Wrapper::sql_make_list_from_values($value);
        $query = "INSERT OR REPLACE INTO vod_history.history (movie_id, series_id, $params)
                    VALUES ($q_movie_id, $q_series_id, $values);";
        $this->sql_playlist->exec($query);
    }

    /**
     * Remove history by movie_id
     *
     * @param string $movie_id
     */
    public function remove_history($movie_id)
    {
        $q_value = Sql_Wrapper::sql_quote($movie_id);
        $this->sql_playlist->exec("DELETE FROM vod_history.history WHERE movie_id = $q_value;");
    }

    /**
     * Remove history by movie_id and series_id
     *
     * @param $movie_id
     * @param $series_id
     */
    public function remove_history_part($movie_id, $series_id)
    {
        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series_id = Sql_Wrapper::sql_quote($series_id);
        $this->sql_playlist->exec("DELETE FROM vod_history.history WHERE movie_id = $q_movie_id AND series_id = $q_series_id;");
    }

    /**
     * Clear all history
     */
    public function clear_all_history()
    {
        $this->sql_playlist->exec("DELETE FROM vod_history.history;");
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

    ////////////////////////////////////////////////////////////////////////////
    /// Playback points

    /**
     * @return array
     */
    public function get_tv_history()
    {
        $playback_points = array();
        $list = $this->sql_playlist->fetch_array("SELECT * FROM tv_history.history ORDER BY time_stamp DESC;");
        foreach ($list as $row) {
            $playback_points[$row['channel_id']] = $row['time_stamp'];
        }

        return $playback_points;
    }

    /**
     * @return int
     */
    public function get_tv_history_count()
    {
        return $this->sql_playlist->query_value("SELECT count(*) FROM tv_history.history;");
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
                $query = "INSERT OR IGNORE INTO tv_history.history (channel_id, time_stamp) VALUES ($q_id, $archive_ts);";
                $query .= "UPDATE tv_history.history SET time_stamp = $archive_ts WHERE channel_id = $q_id;";
                $query .= "DELETE FROM tv_history.history WHERE rowid NOT IN (SELECT rowid FROM tv_history.history ORDER BY time_stamp DESC LIMIT 7);";
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
        $this->sql_playlist->exec("DELETE FROM tv_history.history WHERE channel_id = $q_id;");
    }

    /**
     * @return void
     */
    public function clear_tv_history()
    {
        hd_debug_print();
        $this->sql_playlist->exec("DELETE FROM tv_history.history;");
    }

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
    public function init_playlist($force = false)
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
            $err = HD::get_last_error();
            if (!empty($err)) {
                $err .= "\n\n";
            }
            $err .= $ex->getMessage();
            HD::set_last_error("pl_last_error", $err);
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
                        HD::set_last_error("vod_last_error", $exception_msg);
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
                    HD::set_last_error("vod_last_error", $exception_msg);
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

        $saved_source = $this->sql_playlist->fetch_array("SELECT * FROM playlist.playlist_xmltv;");
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

            $playlist_sources->set($hash, $item);
            hd_debug_print("playlist source: ($hash) $url", true);

            if (!empty($known_sources)) {
                $q_type = SQL_Wrapper::sql_quote(PARAM_LINK);
                $q_known_sources = SQL_Wrapper::sql_make_list_from_quoted_values($known_sources);
                $query = "DELETE FROM playlist.playlist_xmltv WHERE type = $q_type AND hash NOT IN ($q_known_sources);";
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
        $rows = $this->sql_params->fetch_array("SELECT * FROM xmltv_sources;");
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
        return $this->sql_params->query_value("SELECT (*) FROM xmltv_sources;");
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
        $row = $this->sql_params->query_value("SELECT * FROM xmltv_sources WHERE hash = $q_hash;");
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
        $query = "INSERT OR REPLACE INTO xmltv_sources $q_list;";
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
        $this->sql_params->exec("DELETE FROM xmltv_sources WHERE hash = $q_hash;");
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
            $this->sql_playlist->exec("UPDATE playlist.playlist_xmltv SET cache = '$cache' WHERE hash = '$hash';");
        } else {
            $this->sql_params->exec("UPDATE xmltv_sources SET cache = '$cache' WHERE hash = '$hash';");
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
    //
    // Misc.
    //
    ///////////////////////////////////////////////////////////////////////

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
        } else if (is_null($background) || !file_exists(get_cached_image_path($background))) {
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

    ///////////////////////////////////////////////////////////////////////
    //
    // popup menus
    //
    ///////////////////////////////////////////////////////////////////////

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

            $cnt = $this->get_groups_count(false, true);
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
        $params = array(
            'screen_id' => Starnet_Edit_List_Screen::ID,
            'source_window_id' => $source_screen_id,
            'source_media_url_str' => $source_screen_id,
            'edit_list' => $action_edit,
            'windowCounter' => 1,
        );

        if ($action_edit === Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_CHANNELS
            || $action_edit === Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_GROUPS) {
            $params['end_action'] = ACTION_RELOAD;
            $params['cancel_action'] = ACTION_EMPTY;
        } else if ($action_edit === Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST) {
            $params['allow_order'] = true;
            $params['save_data'] = PLUGIN_PARAMETERS;
            $params['end_action'] = ACTION_RELOAD;
            $params['cancel_action'] = RESET_CONTROLS_ACTION_ID;
        } else if ($action_edit === Starnet_Edit_List_Screen::SCREEN_EDIT_EPG_LIST) {
            $params['save_data'] = PLUGIN_PARAMETERS;
            $params['end_action'] = ACTION_RELOAD;
            $params['cancel_action'] = RESET_CONTROLS_ACTION_ID;
        }

        $sel_id = null;
        switch ($action_edit) {
            case Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_CHANNELS:
                if (!is_null($media_url) && isset($media_url->group_id)) {
                    $params['group_id'] = $media_url->group_id;
                }
                $title = TR::t('tv_screen_edit_hidden_channels');
                break;

            case Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_GROUPS:
                $title = TR::t('tv_screen_edit_hidden_group');
                break;

            case Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST:
                $params['extension'] = PLAYLIST_PATTERN;
                $title = TR::t('setup_channels_src_edit_playlists');
                $active_key = $this->get_active_playlist_key();
                if (!empty($active_key) && $this->get_playlist($active_key) !== null) {
                    $sel_id = $this->get_all_playlists()->get_idx($active_key);
                }
                break;

            case Starnet_Edit_List_Screen::SCREEN_EDIT_EPG_LIST:
                $params['extension'] = EPG_PATTERN;
                $title = TR::t('setup_edit_xmltv_list');
                break;

            default:
                return null;
        }

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

    ///////////////////////////////////////////////////////////

    public function get_id_column()
    {
        return Default_Dune_Plugin::$id_to_column_mapper[$this->channel_id_map];
    }

    /**
     * @param Sql_Wrapper $db
     * @return int|string
     */
    static public function detectBestChannelId($db)
    {
        hd_debug_print(null, true);

        $cnt = $db->query_value("SELECT count(hash) FROM iptv.iptv_channels;");
        if (empty($cnt)) {
            return ATTR_CHANNEL_HASH;
        }

        hd_debug_print("Total collected entries: $cnt", true);

        $stat = array();
        foreach (self::$id_to_column_mapper as $key => $value) {
            $query = "SELECT sum(cnt - 1) AS dupes
                FROM (SELECT $value, COUNT($value) AS cnt
                      FROM iptv.iptv_channels GROUP BY $value HAVING cnt > 0 ORDER BY cnt DESC);";
            $res = $db->query_value($query);

            if ($res === false || $res === null) continue;

            $res -= 1;
            hd_debug_print("Key '$key' => '$value' dupes count: $res");

            $stat[$key] = $res;
        }

        $max_dupes = $cnt + 1;
        foreach ($stat as $key => $value) {
            if ($value < $max_dupes) {
                $max_dupes = $value;
                $minkey = $key;
            }
        }

        $minkey = empty($minkey) ? ATTR_CHANNEL_HASH : $minkey;
        hd_debug_print("Best ID: $minkey");
        return $minkey;
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

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// IPTV

    ///////////////////////////////////////////////////////////////////////
    /// IPTV

    /**
     * @param Object $plugin_cookies
     * @return int
     */
    public function load_channels(&$plugin_cookies, $reload_playlist = false)
    {
        hd_debug_print();

        HD::set_last_error("pl_last_error", null);

        $plugin_cookies->toggle_move = false;

        if (!$reload_playlist && $this->get_playlist_entries_count() !== 0) {
            hd_debug_print("Channels already loaded", true);
            return 1;
        }

        $this->init_playlist_db();

        $this->init_screen_view_parameters($this->get_background_image());

        if ($this->is_vod_playlist()) {
            hd_debug_print("Using standard VOD implementation");
            $vod_class = 'vod_standard';
            $this->vod = new $vod_class($this);
            $this->vod_enabled = $this->vod->init_vod(null);
            $this->vod->init_vod_screens();

            return 2;
        }

        $this->perf->reset('start');

        // first check if playlist in cache
        if (false === $this->init_playlist($reload_playlist)) {
            return 0;
        }

        $filename = $this->iptv_m3u_parser->get_filename();

        try {
            $this->perf->setLabel('start_parse_playlist');

            $mtime = filemtime($filename);
            $date_fmt = format_datetime("Y-m-d H:i", $mtime);
            hd_debug_print("Parse playlist $filename (timestamp: $mtime, $date_fmt)");

            $tables = $this->sql_playlist->fetch_single_array("PRAGMA database_list", 'name');

            if (in_array('iptv', $tables)) {
                $count = $this->sql_playlist->query_value("SELECT count(hash) FROM iptv.iptv_channels;");
            } else {
                $count = 0;
                $db_name = LogSeverity::$is_debug ? "$filename.db" : ":memory:";
                $this->sql_playlist->exec("ATTACH DATABASE '$db_name' AS iptv;");
            }

            if (empty($count)) {
                if ($this->iptv_m3u_parser->parseIptvPlaylist($this->sql_playlist)) {
                    $count = $this->sql_playlist->query_value("SELECT count(hash) FROM iptv.iptv_channels;");
                }

                if (empty($count)) {
                    $contents = @file_get_contents($filename);
                    $exception_msg = TR::load_string('err_load_playlist') . " Empty playlist!\n\n$contents";
                    $this->clear_playlist_cache();
                    throw new Exception($exception_msg);
                }
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
            $err = HD::get_last_error();
            if (!empty($err)) {
                $err .= "\n\n";
            }
            $err .= $ex->getMessage();
            HD::set_last_error("pl_last_error", $err);
            print_backtrace_exception($ex);
            if (isset($playlist[PARAM_TYPE]) && file_exists($filename)) {
                unlink($filename);
            }
            hd_debug_print_separator();
            return 0;
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
                $this->set_special_group_visible(VOD_GROUP_ID, !$this->vod_enabled);
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

        $known_cnt = $this->get_channels_count();
        $is_new = $known_cnt == 0;
        hd_debug_print("Known channels: $known_cnt");
        // add provider ignored groups to known_groups
        if (!empty($ignore_groups)) {
            $query = '';
            foreach ($ignore_groups as $group_id) {
                $q_group_id = Sql_Wrapper::sql_quote($group_id);
                $query .= "INSERT OR IGNORE INTO playlist_orders.groups (group_id, disabled) VALUES ($q_group_id, 1);" . PHP_EOL;
            }
            $this->sql_playlist->exec_transaction($query);
        }

        ////////////////////////////////////////////////////////////////////////////////////
        /// update tables with removed and added groups and channels

        // get name of the column for channel ID
        $id_column = $this->get_id_column();
        hd_debug_print("ID column:      $id_column");

        // mark as removed channels that not present iptv_channels db
        $this->sql_playlist->exec("UPDATE playlist_orders.channels SET changed = -1 WHERE channel_id NOT IN (SELECT $id_column FROM iptv.iptv_channels);");

        // select new groups that not present in groups table but exist in iptv_groups
        $query_new_groups = "SELECT * FROM iptv.iptv_groups WHERE group_id NOT IN (SELECT group_id FROM groups);";
        $new_groups = $this->sql_playlist->fetch_array($query_new_groups);

        $query = "INSERT OR IGNORE INTO playlist_orders.groups (group_id, title, icon, adult) VALUES (:group_id, :title, :icon, :adult);";
        $stm_groups = $this->sql_playlist->prepare($query);
        $new_groups_ids = array();
        foreach ($new_groups as $group_row) {
            $q_group_id = Sql_Wrapper::sql_quote($group_row['group_id']);
            $order_table_name = Default_Dune_Plugin::get_orders_table_name($group_row['group_id']);

            $new_groups_ids[] = $group_row['group_id'];
            $query = "CREATE TABLE playlist_orders.$order_table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";
            $this->sql_playlist->exec($query);

            // set group icon
            $group_icon = empty($group_row['icon']) ? DEFAULT_GROUP_ICON : $group_row['icon'];

            $stm_groups->bindValue(':group_id', $group_row['group_id']);
            $stm_groups->bindValue(':title', $group_row['group_id']);
            $stm_groups->bindValue(':icon', $group_icon);
            $stm_groups->bindValue(':adult', $group_row['adult']);
            $stm_groups->execute();

            // select all channels from iptv_channels for selected group that not disabled in known_channels
            $query_channels = "SELECT * FROM iptv.iptv_channels "
                . "WHERE group_id = $q_group_id "
                . "AND $id_column NOT IN (SELECT channel_id FROM playlist_orders.channels WHERE disabled == 0) "
                . "GROUP BY $id_column ORDER BY ROWID ASC;";
            $pl_entries = $this->sql_playlist->fetch_array($query_channels);

            $query = '';
            foreach ($pl_entries as $entry) {
                $unique_channel_id = Sql_Wrapper::sql_quote($entry[$id_column]);
                $query .= "INSERT OR IGNORE INTO playlist_orders.$order_table_name (channel_id) VALUES($unique_channel_id);";
            }
            $this->sql_playlist->exec_transaction($query);
        }

        if (!empty($new_groups_ids)) {
            $list_new_groups = Sql_Wrapper::sql_make_list_from_quoted_values($new_groups_ids);
            hd_debug_print("New groups:     $list_new_groups");
        }

        // add new channels
        $query = "INSERT OR IGNORE INTO playlist_orders.channels (channel_id, title, adult, changed)
                        SELECT $id_column, title, adult, 1
                        FROM iptv.iptv_channels
                        WHERE group_id IN (SELECT group_id FROM playlist_orders.groups WHERE disabled = 0 AND special = 0)
                          AND $id_column NOT IN (SELECT channel_id FROM playlist_orders.channels WHERE disabled == 0)
                        GROUP BY $id_column ORDER BY ROWID ASC";
        $this->sql_playlist->exec($query);

        if ($is_new) {
            // if it first run for this playlist remove changed status for all channels
            $this->clear_changed_channels();
        }

        // cleanup order if group removed from playlist
        $query = "SELECT group_id FROM playlist_orders.groups WHERE group_id NOT IN (SELECT group_id FROM iptv.iptv_groups) AND special = 0;";
        $rows = $this->sql_playlist->fetch_single_array($query, 'group_id');
        if (!empty($rows)) {
            $list_removed_groups = Sql_Wrapper::sql_make_list_from_quoted_values($rows);
            hd_debug_print("Removed groups: $list_removed_groups", true);
            $this->sql_playlist->exec("DELETE FROM playlist_orders.orders_group WHERE group_id IN ($list_removed_groups);");
            $this->sql_playlist->exec("DELETE FROM playlist_orders.groups WHERE group_id IN ($list_removed_groups);");
        }

        $total_groups_cnt = $this->get_groups_count();
        $hidden_groups_cnt = $this->get_groups_count(false, true);

        $total_channels_cnt = $this->get_channels_count();
        $hidden_channels_cnt = $this->get_channels_count(null, 1);

        $changed_channels_cnt = $this->get_changed_channels_count();
        $this->set_special_group_visible(CHANGED_CHANNELS_GROUP_ID, $changed_channels_cnt === 0);

        $this->perf->setLabel('end');
        $report = $this->perf->getFullReport();

        hd_debug_print("Channels:       $total_channels_cnt, hidden channels: $hidden_channels_cnt, changed channels: $changed_channels_cnt");
        hd_debug_print("Groups:         $total_groups_cnt, hidden groups: $hidden_groups_cnt");
        hd_debug_print("Load time:      {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage:   {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_debug_print_separator();

        if ($this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === ENGINE_XMLTV) {
            foreach ($this->get_selected_xmltv_sources() as $source) {
                $this->run_bg_epg_indexing($source);
            }
        }

        return 2;
    }

    /**
     * @param Object $plugin_cookies
     * @param bool $reload_playlist
     * @return int
     */
    public function reload_channels(&$plugin_cookies, $reload_playlist = true)
    {
        $this->reset_playlist_db();
        return $this->load_channels($plugin_cookies, $reload_playlist);
    }

    /**
     * @param string|null $group_id
     * @param int $disabled -1 - all, 0 - only enabled, 1 - only disabled
     * @return array
     */
    public function get_channels($group_id = null, $disabled = -1)
    {
        if (is_null($group_id) || $group_id === ALL_CHANNELS_GROUP_ID) {
            $where = '';
        } else {
            $where = "WHERE group_id = " . Sql_Wrapper::sql_quote($group_id);
        }

        if ($disabled !== -1) {
            $where = empty($where) ? "WHERE disabled = $disabled" : "$where AND disabled = $disabled";
        }

        $column = $this->get_id_column();
        $query = "SELECT ch.channel_id, pl.* FROM iptv.iptv_channels AS pl JOIN playlist_orders.channels AS ch ON pl.$column = ch.channel_id $where;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param string|null $group_id
     * * @param int $disabled -1 - all, 0 - only enabled, 1 - only disabled
     * @return int
     */
    public function get_channels_count($group_id = null, $disabled = -1)
    {
        if (is_null($group_id) || $group_id === ALL_CHANNELS_GROUP_ID) {
            $where = '';
        } else {
            $where = "WHERE group_id = " . Sql_Wrapper::sql_quote($group_id);
        }

        if ($disabled !== -1) {
            $where = empty($where) ? "WHERE disabled = $disabled" : "$where AND disabled = $disabled";
        }

        $column = $this->get_id_column();
        $query = "SELECT count(ch.channel_id) FROM iptv.iptv_channels AS pl JOIN playlist_orders.channels AS ch ON pl.$column = ch.channel_id $where;";
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @return int
     */
    public function get_playlist_entries_count()
    {
        if($this->sql_playlist->query_value("SELECT name FROM sqlite_master WHERE type='table' AND name='iptv.iptv_channels';")) {
            return $this->sql_playlist->query_value("SELECT count(*) FROM iptv.iptv_channels;");
        }
        return 0;
    }

    /**
     * @return array
     */
    public function get_channels_by_order($group_id)
    {
        $order_table = self::get_orders_table_name($group_id);
        $column = $this->get_id_column();
        $query = "SELECT ord.ROWID, ord.channel_id, pl.* FROM iptv.iptv_channels AS pl JOIN $order_table AS ord ON pl.$column = ord.channel_id ORDER BY ord.id;";
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
            $where = "WHERE id IN ($channels)";
        } else {
            $where = "WHERE id = " . Sql_Wrapper::sql_quote($channel_id);
        }
        $this->sql_playlist->exec("UPDATE playlist_orders.channels SET disabled = $disable $where");
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
                        FROM iptv.iptv_channels as tv
                            JOIN playlist_orders.channels AS ch ON tv.$column = ch.channel_id
                        WHERE ch.channel_id = $channel_id AND ch.disabled = 0;";
        } else {
            $query = "SELECT * FROM playlist_orders.channels WHERE channel_id = $channel_id AND disabled = 0;";
        }

        return $this->sql_playlist->query_value($query, true);
    }

    /**
     * disable channels by pattern and remove it from order
     *
     * @param string $pattern
     * @param string $group_id
     * @param bool $is_regex
     */
    public function hide_channels_by_mask($pattern, $group_id, $is_regex = true)
    {
        hd_debug_print("Hide channels type: $pattern in group: $group_id");

        $disabled = array();
        foreach ($this->get_channels($group_id, 0) as $item) {
            if ($is_regex) {
                $add = preg_match("#$pattern#", $item['title']);
            } else {
                $add = stripos($item['title'], $pattern) !== false;
            }

            if ($add) {
                $disabled[] = $item['channel_id'];
            }
        }

        if (!empty($disabled)) {
            $values = Sql_Wrapper::sql_make_list_from_quoted_values($disabled);
            $this->sql_playlist->exec("UPDATE playlist_orders.channels SET disabled = 1 WHERE id IN ($values)");
            hd_debug_print("Total channels hidden: " . count($disabled));
        }
    }

    /**
     * @param string $channel_id
     * @param bool $changed
     */
    public function set_changed_channel($channel_id, $changed)
    {
        $changed = (int)$changed;
        $id = Sql_Wrapper::sql_quote($channel_id);
        $this->sql_playlist->exec("UPDATE playlist_orders.channels SET changed = $changed WHERE id = $id");
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
                        FROM iptv.iptv_channels AS pl
                            JOIN playlist_orders.channels AS ch ON pl.$column = ch.channel_id
                        WHERE $val ORDER BY ch.ROWID;";
        } else if ($type === 'removed') {
            $query = "SELECT ROWID, channel_id, title FROM playlist_orders.channels WHERE $val ORDER BY ROWID;";
        } else {
            $query = "SELECT ch.ROWID, ch.channel_id, pl.*, ch.title
                    FROM playlist_orders.channels AS ch
                        LEFT JOIN iptv.iptv_channels AS pl ON pl.ch_id = ch.channel_id
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
        $query = "SELECT channel_id FROM playlist_orders.channels WHERE $val ORDER BY ROWID;";
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
        $qry = "SELECT count(channel_id) FROM playlist_orders.channels $where $cond;";

        return $this->sql_playlist->query_value($qry);
    }

    /**
     * @return void
     */
    public function clear_changed_channels()
    {
        $this->sql_playlist->exec("DELETE FROM playlist_orders.channels WHERE changed == -1;");
        $this->sql_playlist->exec("UPDATE playlist_orders.channels SET changed = 0 WHERE changed == 1;");
    }

    ///////////////////////////////////////////////////////////////////////
    /// groups
    /**
     * returns all groups
     * @return array
     */
    public function get_groups($special = false, $disabled = false)
    {
        $special = (int)$special;
        $disabled = (int)$disabled;
        $query = "SELECT * FROM playlist_orders.groups WHERE special == $special AND disabled = $disabled;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * returns group with selected id
     *
     * @param string $group_id
     * @return array
     */
    public function get_any_group($group_id)
    {
        $q_group = Sql_Wrapper::sql_quote($group_id);
        $query = "SELECT * FROM playlist_orders.groups WHERE AND group_id = $q_group;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * returns group with selected id
     *
     * @param string $group_id
     * @param bool $special
     * @return array
     */
    public function get_group($group_id, $special = false)
    {
        $special = (int)$special;
        $q_group_id = Sql_Wrapper::sql_quote($group_id);
        $query = "SELECT * FROM playlist_orders.groups WHERE special == $special AND group_id = $q_group_id AND disabled = 0";
        return $this->sql_playlist->query_value($query, true);
    }

    /**
     * returns how many enabled groups
     *
     * @param bool $special
     * @param bool $disabled
     * @return int
     */
    public function get_groups_count($special = false, $disabled = false)
    {
        $special = (int)$special;
        $disabled = (int)$disabled;
        $query = "SELECT count(group_id) FROM playlist_orders.groups WHERE special == $special AND disabled = $disabled";
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @param string $group_id
     * @param bool $hide
     * @return void
     */
    public function set_special_group_visible($group_id, $hide)
    {
        $disabled = (int)$hide;
        $id = Sql_Wrapper::sql_quote($group_id);
        $query = "UPDATE playlist_orders.groups SET disabled = $disabled WHERE group_id = $id AND special = 1;";
        $this->sql_playlist->exec($query);
    }

    /**
     * @param string|array $group_ids
     * @param bool $hide
     * @return void
     */
    public function set_groups_visible($group_ids, $hide)
    {
        $disabled = (int)$hide;

        if (is_array($group_ids)) {
            $groups = Sql_Wrapper::sql_make_list_from_quoted_values($group_ids);
            $where = "WHERE id IN ($groups)";
            $to_alter = $group_ids;
        } else {
            $where = "WHERE id = " . Sql_Wrapper::sql_quote($group_ids);
            $to_alter[] = $group_ids;
        }

        $query = "UPDATE playlist_orders.groups SET disabled = $disabled $where;";
        foreach ($to_alter as $id) {
            $id = Sql_Wrapper::sql_quote($id);
            $table_name = self::get_orders_table_name($id);

            if ($disabled) {
                $query .= "DELETE FROM playlist_orders.orders_group WHERE group_id = $id;";
                $query .= "DROP TABLE IF EXISTS $table_name;";
            } else {
                $query .= "INSERT OR IGNORE INTO playlist_orders.orders_group (group_id) VALUES ($id);";
                $query .= "CREATE TABLE IF NOT EXISTS $table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";
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
        return $this->sql_playlist->query_value("SELECT icon FROM playlist_orders.groups WHERE group_id = $group_id;");
    }

    public function set_group_icon($group_id, $icon)
    {
        $group_id = Sql_Wrapper::sql_quote($group_id);
        $old_cached_image = $this->get_group_icon($group_id);
        hd_debug_print("Assign icon: $icon to group: $group_id");
        $this->sql_playlist->exec("UPDATE playlist_orders.groups SET icon = $icon WHERE group_id = $group_id;");

        if (!empty($old_cached_image)
            && strpos($old_cached_image, 'plugin_file://') !== false
            && $this->sql_playlist->query_value("SELECT count(group_id) FROM playlist_orders.groups WHERE icon = $icon;") == 0) {
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
        return $this->sql_playlist->fetch_single_array("SELECT group_id FROM orders_group", 'group_id');
    }

    /**
     * @return array
     */
    public function get_groups_by_order()
    {
        $query = "SELECT g.group_id, title, icon FROM playlist_orders.groups AS g INNER JOIN orders_group USING(group_id) ORDER BY id;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @return void
     */
    public function sort_groups_order($reset = false)
    {
        $qry = "BEGIN" . PHP_EOL;
        $qry .= "CREATE TABLE playlist_orders.orders_group_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id TEXT UNIQUE, group_icon TEXT);";
        $qry .= "INSERT INTO playlist_orders.orders_group_tmp (group_id, group_icon) SELECT group_id, group_icon FROM playlist_orders.groups WHERE disabled == 0";
        if ($reset) {
            $qry .= ";" . PHP_EOL;
        } else {
            $qry .= " ORDER BY group_id;" . PHP_EOL;
        }
        $qry .= "DROP TABLE playlist_orders.orders_group;";
        $qry .= "ALTER TABLE playlist_orders.orders_group_tmp RENAME TO playlist_orders.groups_order;";
        $qry .= "COMMIT;" . PHP_EOL;

        $this->sql_playlist->exec($qry);
    }

    /**
     * @return int
     */
    public function get_groups_order_count()
    {
        return $this->sql_playlist->query_value("SELECT count(group_id) FROM orders_group");
    }

    /**
     * @param string $group_id
     * @param bool $remove
     * @return void
     */
    public function change_groups_order($group_id, $remove)
    {
        $q_group_id = Sql_Wrapper::sql_quote($group_id);
        if ($remove) {
            $this->sql_playlist->exec("DELETE FROM orders_group WHERE group_id = $q_group_id");
        } else {
            $this->sql_playlist->exec("INSERT OR IGNORE INTO orders_group (group_id) VALUES ($q_group_id);");
        }
    }

    /**
     * @param string $group_id
     * @param string $channel_id
     * @param int $direction
     * @return bool
     */
    public function arrange_channels_order_rows($group_id, $channel_id, $direction)
    {
        $table_name = self::get_orders_table_name($group_id);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $qry = "SELECT previous, current, next
            FROM (SELECT channel_id,
                         LAG(id) OVER (ORDER BY id)  AS previous,
                         id                          AS current,
                         LEAD(id) OVER (ORDER BY id) AS next,
                         FIRST_VALUE(id) OVER (ORDER BY id) AS first,
                         FIRST_VALUE(id) OVER (ORDER BY id DESC) AS last
                  FROM $table_name)
            WHERE channel_id = $q_channel_id;";

        return $this->arrange_rows($qry, $table_name, $direction);
    }

    /**
     * @param string $group_id
     * @param int $direction
     * @return bool
     */
    public function arrange_groups_order_rows($group_id, $direction)
    {
        $q_groups_id = Sql_Wrapper::sql_quote($group_id);
        $table_name = 'orders_group';
        $qry = "SELECT previous, current, next
            FROM (SELECT group_id,
                         LAG(id) OVER (ORDER BY id)  AS previous,
                         id                          AS current,
                         LEAD(id) OVER (ORDER BY id) AS next,
                         FIRST_VALUE(id) OVER (ORDER BY id) AS first,
                         FIRST_VALUE(id) OVER (ORDER BY id DESC) AS last
                  FROM $table_name)
            WHERE channel_id = $q_groups_id;";

        return $this->arrange_rows($qry, $table_name, $direction);
    }

    /**
     * @param string $query
     * @param string $table_name
     * @param int $direction
     * @return bool
     */
    private function arrange_rows($query, $table_name, $direction)
    {
        $positions = $this->sql_playlist->query_value($query, true);
        if (empty($positions)) {
            return false;
        }

        $left = $positions['current'];
        switch ($direction) {
            case Ordered_Array::UP:
                if($positions['previous'] === null) {
                    return false;
                }
                $right = $positions['previous'];
                break;
            case Ordered_Array::DOWN:
                if($positions['next'] === null) {
                    return false;
                }
                $right = $positions['next'];
                break;
            case Ordered_Array::TOP:
                if ($positions['first'] === $positions['current']) {
                    return false;
                }
                $right = $positions['first'];
                break;
            case Ordered_Array::BOTTOM:
                if ($positions['last'] === $positions['current']) {
                    return false;
                }
                $right = $positions['last'];
                break;
            default:
                return false;
        }

        $query = "BEGIN;
                UPDATE $table_name SET id = -$left WHERE id = $left;
                UPDATE $table_name SET id = $left WHERE id = $right;
                UPDATE $table_name SET id = $right WHERE id = -$left;
                COMMIT;
                ";

        return $this->sql_playlist->exec_transaction($query);
    }

    /**
     * return orders for selected group
     * @param string $group_id
     * @return array
     */
    public function get_channels_order($group_id)
    {
        $table_name = self::get_orders_table_name($group_id);
        return $this->sql_playlist->fetch_single_array("SELECT channel_id FROM $table_name ORDER BY id;", 'channel_id');
    }

    public function remove_channels_order($group_id)
    {
        $table_name = self::get_orders_table_name($group_id);
        $this->sql_playlist->exec("DROP TABLE $table_name;");
    }

    public function change_channels_order($group_id, $channel_id, $remove)
    {
        $table_name = self::get_orders_table_name($group_id);
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
        $table_name = self::get_orders_table_name($group_id);
        $table_name_tmp = $table_name . "_tmp";
        $qry = "BEGIN;" . PHP_EOL;
        if ($reset) {
            $qry .= "DROP TABLE playlist_orders.$table_name;" . PHP_EOL;
            $qry .= "CREATE TABLE playlist_orders.$table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);" . PHP_EOL;
            $qry .= "INSERT OR IGNORE INTO playlist_orders.$table_name (channel_id)
                        SELECT $column FROM iptv.iptv_channels
                        WHERE group_id == $q_group_id AND $column IN
                        (SELECT channel_id FROM playlist_orders.channels WHERE disabled == 0);" . PHP_EOL;
        } else {
            $qry .= "CREATE TABLE playlist_orders.$table_name_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);" . PHP_EOL;
            $qry .= "INSERT OR IGNORE INTO playlist_orders.$table_name_tmp (channel_id)
                        SELECT $column FROM iptv.iptv_channels
                        WHERE group_id == $q_group_id AND $column IN
                        (SELECT channel_id FROM playlist_orders.channels WHERE disabled == 0) ORDER BY title;" . PHP_EOL;

            $qry .= "INSERT INTO playlist_orders.$table_name_tmp (channel_id) SELECT channel_id FROM $table_name ORDER BY channel_id;" . PHP_EOL;
            $qry .= "DROP TABLE playlist_orders.$table_name;" . PHP_EOL;
            $qry .= "ALTER TABLE playlist_orders.$table_name_tmp RENAME TO $table_name;" . PHP_EOL;
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
        $table_name = self::get_orders_table_name($group_id);
        return $this->sql_playlist->query_value("SELECT count(channel_id) FROM $table_name;");
    }

    /**
     * @param string $group_id
     * @return string
     */
    public static function get_orders_table_name($group_id)
    {
        if ($group_id === FAV_MOVIE_GROUP_ID) {
            return "orders_fav_vod";
        }

        if ($group_id === FAV_CHANNELS_GROUP_ID) {
            return "orders_fav_tv";
        }

        if ($group_id === VOD_FILTER_LIST) {
            return "vod_filters";
        }

        if ($group_id === VOD_SEARCH_LIST) {
            return "vod_search";
        }

        return "orders_" . Hashed_Array::hash($group_id);
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin parameters methods (global)
    //

    ///////////////////////////////////////////////////////////////////////
    // Plugin parameters (global)
    //

    /**
     * Load global plugin settings
     *
     * @return void
     */
    public function init_parameters()
    {
        hd_debug_print(null, true);

        $params_db = get_data_path("common.db");
        $this->sql_params = new Sql_Wrapper(new SQLite3($params_db, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, ''));
        $query =  "CREATE TABLE IF NOT EXISTS parameters (name TEXT PRIMARY KEY, value TEXT);";
        $query .= "CREATE TABLE IF NOT EXISTS playlists (id INTEGER PRIMARY KEY AUTOINCREMENT, playlist_id TEXT UNIQUE, name TEXT NOT NULL, type TEXT, params TEXT);";
        $query .= "CREATE TABLE IF NOT EXISTS xmltv_sources (hash TEXT PRIMARY KEY, type TEXT, name TEXT NOT NULL, uri TEXT NOT NULL, cache TEXT NOT NULL);";
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
                    $q = '';
                    foreach ($param as $k => $v) {
                        if (!isset($param->params[PARAM_URI]) || !is_proto_http($param->params[PARAM_URI])) continue;

                        $q_key = Sql_Wrapper::sql_quote($k);
                        $q_type = Sql_Wrapper::sql_quote(PARAM_LINK);
                        $q_name = Sql_Wrapper::sql_quote($param->name);
                        $q_uri = Sql_Wrapper::sql_quote($param->params[PARAM_URI]);
                        if (!isset($param->params[PARAM_CACHE])) {
                            $param->params[PARAM_CACHE] = XMLTV_CACHE_AUTO;
                        }
                        $q_cache = Sql_Wrapper::sql_quote($param->params[PARAM_CACHE]);
                        $q .= "INSERT OR IGNORE INTO xmltv_sources (hash, type, name, uri, cache) VALUES ($q_key, $q_type, $q_name, $q_uri, $q_cache);";
                    }
                    $this->sql_params->exec_transaction($q);
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
                    $query .= "INSERT OR IGNORE INTO parameters (name, value) VALUES ($q_key, $q_param);";
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
        $this->sql_params->exec("INSERT OR REPLACE INTO parameters (name, value) VALUES ($q_name, $q_value);");
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
        $value = $this->sql_params->query_value("SELECT value FROM parameters WHERE name = $q_name;");
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
    static public function put_settings($id, $data)
    {
        if (!empty($id)) {
            $db = new SQLite3(get_data_path("$id.db"), SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, '');
            $query = '';
            foreach ($data as $key => $value) {
                $q_name = SQL_Wrapper::sql_quote($key);
                $q_value = SQL_Wrapper::sql_quote($value);
                $q_type = SQL_Wrapper::sql_quote(gettype($value));
                $query .= "INSERT OR IGNORE INTO playlist.settings (name, value, type) VALUES ($q_name, $q_value, $q_type);";
            }
            if (!empty($query)) {
                $db->exec("BEGIN;" . $query . "COMMIT;");
            }
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
        $row = $this->sql_playlist->query_value("SELECT value, type FROM playlist.settings WHERE name = '$name';", true);
        if (empty($row)) {
            $q_name = Sql_Wrapper::sql_quote($name);
            $q_type = Sql_Wrapper::sql_quote($type);
            $q_value = Sql_Wrapper::sql_quote($default);
            $this->sql_playlist->exec("INSERT INTO playlist.settings (name, value, type) VALUES ($q_name, $q_value, $q_type);");
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
        hd_debug_print("Set setting: $name to $value", true);

        $q_name = Sql_Wrapper::sql_quote($name);
        $q_value = Sql_Wrapper::sql_quote(gettype($value));
        $q_type = Sql_Wrapper::sql_quote(gettype($value));

        $this->sql_playlist->exec("INSERT OR REPLACE INTO playlist.settings (name, value, type) VALUES ($q_name, $q_value, $q_type);");
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
        $query = "SELECT hash FROM playlist.selected_xmltv;";
        return $this->sql_playlist->fetch_single_array($query, PARAM_HASH);
    }

    /**
     * @param array $values
     */
    public function set_selected_xmltv_sources($values)
    {
        $query = "DELETE FROM playlist.selected_xmltv;";
        foreach ($values as $hash) {
            $query .= "INSERT INTO playlist.selected_xmltv (hash) VALUES ('$hash');";
        }

        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param Hashed_Array $values
     */
    public function set_playlist_xmltv($values)
    {
        $query = "DELETE FROM playlist.playlist_xmltv;";
        /** @var Named_Storage $params */
        foreach ($values as $key => $params) {
            $q_hash = Sql_Wrapper::sql_quote($key);
            $q_type = Sql_Wrapper::sql_quote(PARAM_LINK);
            $q_name = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_NAME));
            $q_uri = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_URI));
            $q_cache = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_CACHE, XMLTV_CACHE_AUTO));
            $query .= "INSERT INTO playlist.playlist_xmltv (hash, type, name, uri, cache) VALUES ($q_hash, $q_type, $q_name, $q_uri, $q_cache);";
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
        return array_map(function ($v) {
            return $v;
        }, $this->sql_playlist->fetch_array("SELECT * FROM playlist.dune_params;"));
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
        return $this->sql_playlist->query_value("SELECT value FROM playlist.cookies WHERE $were;");
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

        $this->sql_playlist->exec("INSERT OR REPLACE INTO playlist.cookies (param, value, time_stamp)  VALUES ('$name', '$value', '$expired');");
    }

    /**
     * Get cookie
     *
     * @param string $name
     */
    public function remove_cookie($name)
    {
        $this->sql_playlist->exec("DELETE FROM playlist.cookies WHERE param = '$name';");
    }

    /**
     * Set dune_params
     *
     * @param array $value
     */
    public function set_dune_params($value)
    {
        $query = "DELETE FROM playlist.dune_params;";
        foreach ($value as $k => $v) {
            $q_k = Sql_Wrapper::sql_quote($k);
            $q_v = Sql_Wrapper::sql_quote($v);
            $query .= "INSERT INTO playlist.dune_params (name, value) VALUES ($q_k, $q_v);";
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
        $this->sql_playlist = new Sql_Wrapper(new SQLite3(":memory:", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, ''));
    }

    public function init_playlist_db()
    {
        $playlist_id = $this->get_parameter(PARAM_CUR_PLAYLIST_ID);
        if (empty($playlist_id)) {
            return false;
        }

        if (empty($this->active_playlist)) {
            $this->active_playlist = $this->get_playlist($playlist_id);
        }

        hd_debug_print_separator();
        if (empty($this->active_playlist)) {
            hd_debug_print("Playlist info for ID: $playlist_id is not exist!");
        } else {
            hd_debug_print("Process playlist: {$this->active_playlist[PARAM_NAME]} ($playlist_id)");
        }

        $this->sql_playlist->exec('PRAGMA journal_mode=MEMORY;');

        // attach to playlist db. if db not exist it will be created
        $db_name = get_data_path("$playlist_id.db");
        $this->sql_playlist->exec("ATTACH DATABASE '$db_name' AS playlist");

        // create settings table
        $query = "CREATE TABLE IF NOT EXISTS playlist.settings (name TEXT PRIMARY KEY, value TEXT DEFAULT '', type TEXT DEFAULT '');";
        // create tables for epg_playlist, selected_xmltv_sources and channel_params (zoom, external player)
        $query .= "CREATE TABLE IF NOT EXISTS playlist.playlist_xmltv (hash TEXT PRIMARY KEY, type TEXT, name TEXT, uri TEXT, cache TEXT);";
        $query .= "CREATE TABLE IF NOT EXISTS playlist.selected_xmltv (id INTEGER PRIMARY KEY, hash TEXT UNIQUE);";
        $query .= "CREATE TABLE IF NOT EXISTS playlist.channel_params (channel_id TEXT PRIMARY KEY, zoom TEXT DEFAULT 'x', external_player INTEGER DEFAULT 0);";
        $query .= "CREATE TABLE IF NOT EXISTS playlist.dune_params (param TEXT PRIMARY KEY, value TEXT DEFAULT '');";
        $query .= "CREATE TABLE IF NOT EXISTS playlist.cookies (param TEXT PRIMARY KEY, value TEXT DEFAULT '', time_stamp INTEGER DEFAULT 0);";

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
                'tv_channel_list_view_idx', 'tv_groups_view_idx',
                'tv_history_view_idx', 'tv_favorites_view_idx',
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
                    $query .= "INSERT OR IGNORE INTO playlist.settings (name, value, type) VALUES ($q_name, $q_value, $q_type);";
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
                        $query .= "INSERT OR IGNORE INTO playlist.playlist_xmltv
                                    (hash, type, name, uri, cache) VALUES ($q_channel_id, $q_type, $q_name, $q_uri, $q_cache);";
                    }
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_EPG_PLAYLIST]);
                } else if ($key === PARAM_SELECTED_XMLTV_SOURCES) {
                    hd_debug_print("Convert 'selected_xmltv_sources' to 'selected_xmltv' table");
                    $query = '';
                    foreach ($value as $hash) {
                        $q_hash = Sql_Wrapper::sql_quote($hash);
                        $query .= "INSERT OR IGNORE INTO playlist.selected_xmltv (hash) VALUES ($q_hash);";
                    }
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_SELECTED_XMLTV_SOURCES]);
                } else if ($key === PARAM_CHANNELS_ZOOM) {
                    hd_debug_print("Convert 'channels_zoom' to 'channel_params' table");
                    $query = '';
                    foreach ($value as $channel_id => $zoom) {
                        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
                        $q_zoom = Sql_Wrapper::sql_quote($zoom);
                        $query = "INSERT OR IGNORE INTO playlist.channel_params (channel_id, zoom) VALUES ($q_channel_id, $q_zoom);";
                        $query .= "UPDATE playlist.channel_params SET zoom = $q_zoom WHERE channel_id = $q_channel_id;";
                    }
                    $query .= "DELETE FROM playlist.channel_params WHERE zoom = 'x' AND external_player = 0;";
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_CHANNELS_ZOOM]);
                } else if ($key === PARAM_CHANNEL_PLAYER) {
                    hd_debug_print("Convert 'channel_player' to 'channel_params' table");
                    $query = '';
                    foreach ($value as $channel_id) {
                        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
                        $query = "INSERT OR IGNORE INTO playlist.channel_params (channel_id, external_player) VALUES ($q_channel_id, 1);";
                        $query .= "UPDATE playlist.channel_params SET external_player = 1 WHERE channel_id = $q_channel_id;";
                    }
                    $query .= "DELETE FROM playlist.channel_params WHERE zoom = 'x' AND external_player = 0;";
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_CHANNEL_PLAYER]);
                } else if ($key === PARAM_DUNE_PARAMS) {
                    hd_debug_print("Convert 'dune_params' to 'dune_params' table");
                    $query = '';
                    foreach ($value as $k => $v) {
                        $q_k = Sql_Wrapper::sql_quote($k);
                        $q_v = Sql_Wrapper::sql_quote($v);
                        $query .= "INSERT OR IGNORE INTO playlist.dune_params (param, value) VALUES ($q_k, $q_v);";
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
                $query = "INSERT INTO playlist.cookies (param, value, time_stamp) VALUES('$key', $q_value, $time_stamp);";
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

                        $query .= "INSERT OR IGNORE INTO playlist.playlist_xmltv
                                (hash, type, name, uri, cache) VALUES ($q_hash, $q_type, $q_name, $q_source, $q_cache);";
                    }

                    if (!empty($known_sources)) {
                        $q_known_sources = SQL_Wrapper::sql_make_list_from_quoted_values($known_sources);
                        $query .= "DELETE FROM playlist.playlist_xmltv WHERE type = $q_type AND hash NOT IN ($q_known_sources);";
                    }
                    $this->sql_playlist->exec_transaction($query);
                }
            }
        }

        $db_name = get_data_path("$plugin_orders_name.db");
        $this->sql_playlist->exec("ATTACH DATABASE '$db_name' AS playlist_orders;");

        // create group table
        $query = "CREATE TABLE IF NOT EXISTS playlist_orders.groups
                                        (group_id TEXT PRIMARY KEY,
                                         title TEXT DEFAULT '',
                                         icon TEXT DEFAULT '',
                                         adult INTEGER DEFAULT 0,
                                         disabled INTEGER DEFAULT 0,
                                         special INTEGER DEFAULT 0
                                         );";
        // create channels table
        $query .= "CREATE TABLE IF NOT EXISTS playlist_orders.channels
                            (channel_id TEXT PRIMARY KEY, title TEXT, disabled INTEGER DEFAULT 0, adult INTEGER DEFAULT 0, changed INTEGER DEFAULT 0);";
        // create order_groups table
        $query .= "CREATE TABLE IF NOT EXISTS playlist_orders.orders_group (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id TEXT UNIQUE);";

        // create tables for vod search and filters
        foreach (array(VOD_FILTER_LIST, VOD_SEARCH_LIST) as $list) {
            $table_name = self::get_orders_table_name($list);
            $query .= "CREATE TABLE IF NOT EXISTS playlist_orders.$table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, item TEXT UNIQUE);";
        }
        // create tables for favorites
        foreach (array(FAV_CHANNELS_GROUP_ID, FAV_MOVIE_GROUP_ID) as $group_id) {
            $table_name = self::get_orders_table_name($group_id);
            $query .= "CREATE TABLE IF NOT EXISTS playlist_orders.$table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";
        }

        $this->sql_playlist->exec_transaction($query);

        // add special groups to the table if the not exists
        $special_group = array(
            array('group_id' => ALL_CHANNELS_GROUP_ID, 'title' => ALL_CHANNELS_GROUP_CAPTION, 'icon' => ALL_CHANNELS_GROUP_ICON),
            array('group_id' => FAV_CHANNELS_GROUP_ID, 'title' => FAV_CHANNELS_GROUP_CAPTION, 'icon' => FAV_CHANNELS_GROUP_ICON),
            array('group_id' => HISTORY_GROUP_ID, 'title' => HISTORY_GROUP_CAPTION, 'icon' => HISTORY_GROUP_ICON),
            array('group_id' => CHANGED_CHANNELS_GROUP_ID, 'title' => CHANGED_CHANNELS_GROUP_CAPTION, 'icon' => CHANGED_CHANNELS_GROUP_ICON),
            array('group_id' => VOD_GROUP_ID, 'title' => VOD_GROUP_CAPTION, 'icon' => VOD_GROUP_ICON),
        );

        $query = '';
        foreach ($special_group as $group) {
            $group['special'] = 1;
            $values = Sql_Wrapper::sql_make_insert_list($group);
            $query .= "INSERT OR IGNORE INTO playlist_orders.groups $values;";
        }
        $this->sql_playlist->exec_transaction($query);

        $orders_file = get_data_path("$plugin_orders_name.settings");
        if (file_exists($orders_file)) {
            hd_debug_print("Load (PLUGIN_ORDERS): $plugin_orders_name.settings");
            $plugin_orders = HD::get_items($orders_file, true, false);
            foreach ($plugin_orders as $key => $param) {
                hd_debug_print("$key => '" . (is_array($param) ? json_encode($param) : $param) . "'");
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
                    $query .= "INSERT OR IGNORE INTO playlist_orders.groups (group_id, title, icon, adult) VALUES ($q_group_id, $q_group_id, $group_icon, $adult);";
                    $query .= "INSERT OR IGNORE INTO playlist_orders.orders_group (group_id) VALUES ($q_group_id);";
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
                    $query .= "INSERT OR IGNORE INTO playlist_orders.groups
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
                    $query .= "INSERT OR IGNORE INTO playlist_orders.channels (channel_id, title) VALUES ($q_channel_id, $q_title);";
                }
                $this->sql_playlist->exec_transaction($query);
                unset($plugin_orders[PARAM_KNOWN_CHANNELS]);
            }

            if (isset($plugin_orders[PARAM_DISABLED_CHANNELS]) && $plugin_orders[PARAM_DISABLED_CHANNELS]->size() !== 0) {
                hd_debug_print("Move 'disabled_channels' to 'channels' db table");
                $values = Sql_Wrapper::sql_make_list_from_quoted_values($plugin_orders[PARAM_DISABLED_CHANNELS]->get_order());
                $query = "UPDATE playlist_orders.channels SET disabled = 1 WHERE channel_id IN ($values);";
                $this->sql_playlist->exec($query);
                unset($plugin_orders[PARAM_DISABLED_CHANNELS]);
            }

            foreach ($plugin_orders as $order_name => $order) {
                $table_name = self::get_orders_table_name($order_name);
                hd_debug_print("Move '$order_name' channels orders to $table_name db table");
                $query = "CREATE TABLE IF NOT EXISTS playlist_orders.$table_name (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id TEXT UNIQUE);";
                foreach ($order as $channel_id) {
                    $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
                    $query .= "INSERT OR IGNORE INTO playlist_orders.$table_name (channel_id) VALUES ($q_channel_id);";
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
        $this->sql_playlist->exec("ATTACH DATABASE '$tv_history_db_name.db' AS tv_history");
        // create tv history table
        $query = "CREATE TABLE IF NOT EXISTS tv_history.history (channel_id TEXT UNIQUE NOT NULL, time_stamp INTEGER DEFAULT 0);";
        $this->sql_playlist->exec($query);

        $tv_history_name = $history_path . $this->make_name(PARAM_TV_HISTORY_ITEMS);
        if (file_exists($tv_history_name)) {
            $points = HD::get_items($tv_history_name);
            hd_debug_print("Load (PLUGIN TV HISTORY) from: $tv_history_name", true);
            $query = '';
            foreach ($points as $key => $item) {
                $q_key = Sql_Wrapper::sql_quote($key);
                $item = (int)$item;
                $query .= "INSERT OR IGNORE INTO tv_history.history (channel_id, time_stamp) VALUES ($q_key, $item);";
            }
            $query .= "DELETE FROM tv_history.history WHERE rowid NOT IN (SELECT rowid FROM tv_history.history ORDER BY time_stamp DESC LIMIT 7);";
            $this->sql_playlist->exec_transaction($query);
            hd_debug_print("Remove TV History: $tv_history_name");
            HD::erase_items($tv_history_name);
        }

        // create vod history table
        if ($this->is_vod_playlist() || ($provider !== null && $provider->hasApiCommand(API_COMMAND_GET_VOD))) {
            // vod history is only one per playlist
            $vod_history_name = $history_path . $this->make_name(VOD_HISTORY);
            $this->sql_playlist->exec("ATTACH DATABASE '$vod_history_name.db' AS vod_history");
            $query = "CREATE TABLE IF NOT EXISTS vod_history.history
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
                /** @var Hashed_Array $history */
                $history = HD::get_items($vod_history_filename, true, false);
                if (isset($history[VOD_HISTORY])) {
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
                            $query .= "INSERT OR IGNORE INTO vod_history.history
                                (movie_id, series_id, watched, position, duration, time_stamp)
                                VALUES ($q_movie_id, $q_series_id, $watched, $item->position, $item->duration, $item->date);";
                        }
                    }
                    $this->sql_playlist->exec_transaction($query);
                    unset($history[VOD_HISTORY]);
                }

                foreach (array(VOD_FILTER_LIST, VOD_SEARCH_LIST) as $list) {
                    if (!isset($history[$list])) continue;

                    $table_name = self::get_orders_table_name($list);
                    hd_debug_print("Move '$list' to '$table_name' db table");

                    $query = '';
                    foreach ($history[$list] as $value) {
                        $q_item = Sql_Wrapper::sql_quote($value);
                        $query .= "INSERT OR IGNORE INTO playlist_orders.$table_name (item) VALUES ($q_item);";
                    }

                    $this->sql_playlist->exec_transaction($query);
                    unset($history[$list]);
                }

                if (empty($history)) {
                    hd_debug_print("Remove VOD history: $vod_history_filename");
                    unlink($vod_history_filename);
                } else {
                    HD::put_items($vod_history_filename, $history, false);
                    foreach ($history as $movie_id => $param) {
                        hd_debug_print("!!!!! Vod history $movie_id is not imported: " . (is_array($param) ? json_encode($param) : $param), true);
                    }
                }
            }
        }

        hd_debug_print("Database initialized.");
        hd_debug_print_separator();
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
