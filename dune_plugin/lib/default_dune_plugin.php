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
require_once 'lib/dune_default_ui_parameters.php';
require_once 'lib/epg/epg_manager_json.php';
require_once 'lib/perf_collector.php';
require_once 'lib/smb_tree.php';

class Default_Dune_Plugin extends Dune_Default_UI_Parameters implements DunePlugin
{
    const ARCHIVE_URL_PREFIX = 'http://iptv.esalecrm.net/res';
    const ARCHIVE_ID = 'common';
    const PARSE_CONFIG = "%s_parse_config.json";

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
     * @var bool
     */
    protected $channels_loaded = false;

    /**
     * @var string
     */
    private $default_channel_icon_classic = DEFAULT_CHANNEL_ICON_PATH;

    /**
     * @var string
     */
    private $default_channel_icon_newui = DEFAULT_CHANNEL_ICON_PATH;

    /**
     * @var string
     */
    private $picons_source = PLAYLIST_PICONS;

    /**
     * @var string
     */
    private $use_xmltv = false;

    /**
     * @var Epg_Manager_Xmltv|Epg_Manager_Json
     */
    protected $epg_manager;

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
     * @var M3uParser
     */
    protected $iptv_m3u_parser;

    private $plugin_cookies;
    private $internet_status = -2;
    private $opexec_id = -1;

    ///////////////////////////////////////////////////////////////////////

    public function __construct()
    {
        parent::__construct();

        $this->providers = new Hashed_Array();
        $this->epg_presets = new Hashed_Array();
        $this->image_libs = new Hashed_Array();
        $this->iptv_m3u_parser = new M3uParser();
    }

    ///////////////////////////////////////////////////////////////////////////
    // DunePlugin implementations

    /**
     * @override DunePlugin
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        return User_Input_Handler_Registry::get_instance()->handle_user_input($user_input, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url_str
     * @param object $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_folder_view($media_url_str, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url_str, true);

        $media_url = MediaURL::decode($media_url_str);
        return $this->get_screen_by_url($media_url)->get_folder_view($media_url, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url_str
     * @param object $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_next_folder_view($media_url_str, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $media_url = MediaURL::decode($media_url_str);

        return $this->get_screen_by_url($media_url)->get_next_folder_view($media_url, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url_str
     * @param int $from_ndx
     * @param object $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_regular_folder_items($media_url_str, $from_ndx, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $media_url = MediaURL::decode($media_url_str);

        return $this->get_screen_by_url($media_url)->get_folder_range($media_url, $from_ndx, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url_str
     * @param object $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_tv_info($media_url_str, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (is_null($this->iptv)) {
            hd_debug_print("TV is not supported");
            print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $this->iptv->get_tv_info(MediaURL::decode($media_url_str), $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param object $plugin_cookies
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
     * @param object $plugin_cookies
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

            $pass_sex = $this->get_parameter(PARAM_ADULT_PASSWORD);
            $channel_row = $this->get_channel_info($channel_id);
            if (empty($channel_row)) {
                throw new Exception("Unknown channel");
            }

            // do not store adult channels to history
            if (!$channel_row[COLUMN_ADULT]) {
                $now = $channel_row[COLUMN_ARCHIVE] > 0 ? time() : 0;
                $this->push_tv_history($channel_id, ($archive_tm_sec !== -1 ? $archive_tm_sec : $now));
            } else if (!empty($pass_sex) && $protect_code !== $pass_sex) {
                throw new Exception("Wrong adult password: $protect_code");
            }

            $url = $this->generate_stream_url($channel_row, $archive_tm_sec);
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            $url = '';
        }

        hd_debug_print("Playback URL: $url", true);
        return $url;
    }

    /**
     * @override DunePlugin
     * @param string $channel_id
     * @param int $day_start_tm_sec timestamp in local TZ
     * @param object $plugin_cookies
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
            $channel_row = $this->get_channel_info($channel_id);
            if (empty($channel_row)) {
                throw new Exception('Unknown channel');
            }

            $time_shift =  $channel_row[COLUMN_TIMESHIFT] * 3600 + $channel_row[COLUMN_EPG_SHIFT];
            // Calculate day start based on playlist and channel time shift
            // correct day start to local timezone
            $utc_day_start_tm_sec = from_local_time_zone_offset($day_start_tm_sec + $time_shift);

            if (LogSeverity::$is_debug) {
                hd_debug_print("Day_start: $day_start_tm_sec ("
                    . format_datetime("Y-m-d H:i", $day_start_tm_sec) . ") TZ offset: "
                    . get_local_time_zone_offset() / 3600);

                hd_debug_print("Shifted day_start: $utc_day_start_tm_sec ("
                    . format_datetime("Y-m-d H:i", $utc_day_start_tm_sec) . ") TZ offset: "
                    . get_local_time_zone_offset() / 3600);
            }

            $show_ext_epg = $this->is_ext_epg_enabled();

            $cached = false;
            $day_epg_items = $this->epg_manager->get_day_epg_items($channel_row, $utc_day_start_tm_sec, $cached);
            if (isset($day_epg_items['error'])) {
                $day_epg[] = array(
                    PluginTvEpgProgram::start_tm_sec => $utc_day_start_tm_sec,
                    PluginTvEpgProgram::end_tm_sec => $utc_day_start_tm_sec + 86400,
                    PluginTvEpgProgram::name => TR::load('epg_not_exist'),
                    PluginTvEpgProgram::description => $day_epg_items['error']
                );
            }
            foreach ($day_epg_items['items'] as $start => $value) {
                if (!isset($value[PluginTvEpgProgram::end_tm_sec], $value[PluginTvEpgProgram::name], $value[PluginTvEpgProgram::description])) {
                    hd_debug_print("malformed epg data: " . pretty_json_format($value));
                    continue;
                }

                // calculate program start and end based on total time shift
                $tm_start = (int)$start - $time_shift;
                $tm_end = (int)$value[PluginTvEpgProgram::end_tm_sec] - $time_shift;
                $day_epg[] = array(
                    PluginTvEpgProgram::start_tm_sec => $tm_start,
                    PluginTvEpgProgram::end_tm_sec => $tm_end,
                    PluginTvEpgProgram::name => $value[PluginTvEpgProgram::name],
                    PluginTvEpgProgram::description => $value[PluginTvEpgProgram::description],
                );

                if (LogSeverity::$is_debug && !$cached) {
                    hd_debug_print(format_datetime("m-d H:i", $tm_start)
                        . " ($tm_start) - " . format_datetime("m-d H:i", $tm_end)
                        . " ($tm_end) {$value[PluginTvEpgProgram::name]}", true);
                }

                if (!$show_ext_epg || in_array($channel_id, $this->epg_manager->get_delayed_epg())) continue;

                $channel_picon = $this->get_channel_picon($channel_row, true);

                $ext_epg[$start]["start_tm"] = $tm_start;
                $ext_epg[$start]["title"] = $value[PluginTvEpgProgram::name];
                $ext_epg[$start]["desc"] = $value[PluginTvEpgProgram::description];

                if (empty($value[PluginTvEpgProgram::icon_url])) {
                    $ext_epg[$start][PluginTvExtEpgProgram::main_icon] = $channel_picon;
                } else {
                    $ext_epg[$start][PluginTvExtEpgProgram::main_icon] = $value[PluginTvEpgProgram::icon_url];
                }

                if (!empty($value[PluginTvExtEpgProgram::main_category])) {
                    $ext_epg[$start][PluginTvExtEpgProgram::main_category] = $value[PluginTvExtEpgProgram::main_category];
                }

                if (!empty($value[PluginTvExtEpgProgram::icon_urls])) {
                    $ext_epg[$start][PluginTvExtEpgProgram::icon_urls] = $value[PluginTvExtEpgProgram::icon_urls];
                }

                if (!empty($value[PluginTvExtEpgProgram::year])) {
                    $ext_epg[$start][PluginTvExtEpgProgram::year] = $value[PluginTvExtEpgProgram::year];
                }

                if (!empty($value[PluginTvExtEpgProgram::country])) {
                    $ext_epg[$start][PluginTvExtEpgProgram::country] = $value[PluginTvExtEpgProgram::country];
                }

                if (!empty($start[PluginTvExtEpgProgram::director])) {
                    $ext_epg[$start][PluginTvExtEpgProgram::director] = $value[PluginTvExtEpgProgram::director];
                }
                if (!empty($value[PluginTvExtEpgProgram::composer])) {
                    $ext_epg[$start][PluginTvExtEpgProgram::composer] = $value[PluginTvExtEpgProgram::composer];
                }

                if (!empty($value[PluginTvExtEpgProgram::editor])) {
                    $ext_epg[$start][PluginTvExtEpgProgram::editor] = $value[PluginTvExtEpgProgram::editor];
                }

                if (!empty($value[PluginTvExtEpgProgram::writer])) {
                    $ext_epg[$start][PluginTvExtEpgProgram::writer] = $value[PluginTvExtEpgProgram::writer];
                }

                if (!empty($value[PluginTvExtEpgProgram::actor]))
                    $ext_epg[$start][PluginTvExtEpgProgram::actor] = $value[PluginTvExtEpgProgram::actor];

                if (!empty($value[PluginTvExtEpgProgram::presenter]))
                    $ext_epg[$start][PluginTvExtEpgProgram::presenter] = $value[PluginTvExtEpgProgram::presenter];

                if (!empty($value[PluginTvExtEpgProgram::imdb_rating]))
                    $ext_epg[$start][PluginTvExtEpgProgram::imdb_rating] = $value[PluginTvExtEpgProgram::imdb_rating];
            }

            if (!empty($day_epg) && !empty($ext_epg)) {
                $playlist_id = $this->get_active_playlist_id();
                $dir = getenv('FS_PREFIX') . '/tmp/ext_epg';
                if (!empty($playlist_id) && create_path($dir)) {
                    $filename = sprintf("%s-%s-%s.json", $playlist_id, Hashed_Array::hash($channel_id), strftime('%Y-%m-%d', $day_start_tm_sec));
                    hd_debug_print("save ext_epg to: $filename");
                    if (file_put_contents(get_temp_path($filename), pretty_json_format($ext_epg))) {
                        rename(get_temp_path($filename), "$dir/$filename");
                    }
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
     * @param object $plugin_cookies
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

        $fav_id = $this->get_fav_id();
        hd_debug_print(null, true);

        switch ($op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                hd_debug_print("Add channel $channel_id to favorites", true);
                $this->change_channels_order($fav_id, $channel_id, false);
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                hd_debug_print("Remove channel $channel_id from favorites", true);
                $this->change_channels_order($fav_id, $channel_id, true);
                break;

            case ACTION_ITEM_UP:
            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $this->arrange_channels_order_rows($fav_id, $channel_id, Ordered_Array::UP);
                break;

            case ACTION_ITEM_DOWN:
            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $this->arrange_channels_order_rows($fav_id, $channel_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEM_TOP:
                $this->arrange_channels_order_rows($fav_id, $channel_id, Ordered_Array::TOP);
                break;

            case ACTION_ITEM_BOTTOM:
                $this->arrange_channels_order_rows($fav_id, $channel_id, Ordered_Array::BOTTOM);
                break;

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("Clear favorites", true);
                $this->remove_channels_order($fav_id);
                break;
        }

        $player_state = get_player_state_assoc();
        if (safe_get_value($player_state, PLAYBACK_STATE) === PLAYBACK_PLAYING) {
            return Action_Factory::invalidate_folders(array(), null, true);
        }

        return null;
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param object $plugin_cookies
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
     * @param object $plugin_cookies
     * @return string
     */
    public function get_vod_stream_url($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("VOD is not supported");

        return '';
    }

    ////////////////////////////////////////////////////////////////////////////
    /// Main methods

    public function is_channels_loaded()
    {
        return $this->channels_loaded;
    }

    public function reset_channels_loaded()
    {
        return $this->channels_loaded = false;
    }

    public function is_use_xmltv()
    {
        return $this->use_xmltv;
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
        $this->reset_playlist_db();

        LogSeverity::$is_debug = true;
        $this->init_parameters();

        hd_debug_print("Init plugin done!");
        hd_debug_print_separator();

        $this->inited = true;
    }

    /**
     * @return bool
     */
    public function is_plugin_inited()
    {
        return $this->inited;
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
                $this->epg_manager = new Epg_Manager_Json($this);
                $this->use_xmltv = false;
            }
        }

        if (is_null($this->epg_manager)) {
            hd_debug_print("Using 'Epg_Manager_Xmltv' cache engine");
            $this->epg_manager = new Epg_Manager_Xmltv($this);
            $this->use_xmltv = true;
        }
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

        $ret = false;
        $tmp_file = '';
        try {
            $playlist_id = $this->get_active_playlist_id();
            if (!$this->is_playlist_entry_exist($playlist_id)) {
                throw new Exception("Tv playlist not defined");
            }

            $params = $this->get_playlist_parameters($playlist_id);
            hd_debug_print("Using playlist " . json_encode($params));

            $type = safe_get_value($params, PARAM_TYPE);

            $icon_replace_pattern = array();
            if ($type === PARAM_PROVIDER) {
                $provider = $this->get_active_provider();
                if (is_null($provider)) {
                    throw new Exception("Unable to init provider");
                }

                if ($provider->get_provider_info($force) === false) {
                    throw new Exception("Unable to get provider info");
                }

                $id_parser = $provider->getConfigValue(CONFIG_ID_PARSER);
                $id_map = $provider->getConfigValue(CONFIG_ID_MAP);

                $replace = SwitchOnOff::to_bool($provider->GetProviderParameter(PARAM_REPLACE_ICON, SwitchOnOff::on));
                if (!$replace) {
                    $icon_replace_pattern = $provider->getConfigValue(CONFIG_ICON_REPLACE);
                }
            } else {
                $id_parser = '';
                $id_map = safe_get_value($params, PARAM_ID_MAPPER, '');
                hd_debug_print("ID mapper for playlist: $id_map", true);
            }

            if (!empty($id_parser)) {
                $this->channel_id_map = ATTR_PARSED_ID;
                hd_debug_print("Using specific ID parser: $this->channel_id_map ($id_parser)", true);
            }

            if (!empty($id_map)) {
                hd_debug_print("Using specific ID mapping: $id_map", true);
                $this->channel_id_map = $id_map;
            }

            if (empty($id_parser) && empty($id_map)) {
                hd_debug_print("No specific mapping set using HASH", true);
                $this->channel_id_map = ATTR_CHANNEL_HASH;
            }

            $parser_params = array('id_parser' => $id_parser, 'icon_replace_pattern' => $icon_replace_pattern);
            $this->iptv_m3u_parser->setupParserParameters($parser_params);
            hd_debug_print("Init playlist parser done!");
            $ret = true;
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
            $err = $ex->getMessage();
            $rq_err = Dune_Last_Error::get_last_error(LAST_ERROR_REQUEST);
            if (!empty($rq_err)) {
                $err .= "\n\n" . $rq_err;
            }

            $pl_err = Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST);
            if (!empty($pl_err)) {
                $err .= "\n\n" . $pl_err;
            }

            Dune_Last_Error::set_last_error(LAST_ERROR_PLAYLIST, $err);
            print_backtrace_exception($ex);
            if (empty($type) && file_exists($tmp_file)) {
                unlink($tmp_file);
            }
        }

        return $ret;
    }

    /**
     * @param string $channel_id
     * @param int $program_ts
     * @return mixed|null
     */
    public function get_epg_info($channel_id, $program_ts)
    {
        hd_debug_print(null, true);

        $program_ts = ($program_ts > 0 ? $program_ts : time());
        $program_ts_str = format_datetime("Y-m-d H:i", $program_ts);
        hd_debug_print("channel ID: $channel_id at time $program_ts_str ($program_ts)", true);
        $day_start_ts = strtotime(format_datetime("Y-m-d", $program_ts) . " UTC");
        $day_epg = $this->get_day_epg($channel_id, $day_start_ts, $plugin_cookies);
        if (empty($day_epg)) {
            hd_debug_print("No entries found for channel $channel_id");
        } else {
            $not_found = true;
            foreach ($day_epg as $item) {
                if ($program_ts >= $item[PluginTvEpgProgram::start_tm_sec] && $program_ts < $item[PluginTvEpgProgram::end_tm_sec]) {
                    $not_found = false;
                    break;
                }
            }

            if ($not_found) {
                hd_debug_print("No entries in range for selected time in " . count($day_epg) . " entries");
            }
        }

        $item[PluginTvEpgProgram::ext_id] = $channel_id;
        return $item;
    }

    /**
     * @param bool $only_headers
     * @param bool $force
     * @return bool
     */
    public function load_and_parse_m3u_iptv_playlist($only_headers, $force = false)
    {
        $base_name = $this->get_playlist_cache_filepath(true);
        $m3u_file = $base_name . '.m3u8';
        $db_file = $base_name . '.db';
        try {
            if (!$force) {
                $is_expired = $this->is_playlist_cache_expired(true);
                if (!$is_expired) {
                    $db_file = $this->get_playlist_cache_filepath(true) . '.db';
                    $database_attached = $this->sql_playlist->attachDatabase($db_file, M3uParser::IPTV_DB);
                    if ($database_attached === 0) {
                        hd_debug_print("Can't attach to database: $db_file with name: " . M3uParser::IPTV_DB);
                    } else if ($database_attached === 2) {
                        $this->channels_loaded = true;
                        return true;
                    }
                }
            }

            hd_debug_print("Playlist cache is not valid or database not exist. Reload playlist.");
            // clear playlist
            if (file_exists($m3u_file)) {
                unlink($m3u_file);
            }

            // clear playlist db
            $this->sql_playlist->detachDatabase($db_file);
            if (file_exists($db_file)) {
                unlink($db_file);
            }

            $perf = new Perf_Collector();
            $perf->reset('start_download_playlist');

            $playlist_id = $this->get_active_playlist_id();
            $params = $this->get_playlist_parameters($playlist_id);
            hd_debug_print("Using playlist " . json_encode($params));
            $type = safe_get_value($params, PARAM_TYPE);

            hd_debug_print("m3u playlist: {$params[PARAM_NAME]} ($playlist_id)");
            if ($type === PARAM_PROVIDER) {
                $provider = $this->get_active_provider();
                if (is_null($provider)) {
                    throw new Exception("Unable to init provider to download: " . json_encode($params));
                }

                hd_debug_print("Load provider playlist to: $m3u_file");

                if ($provider->GetPlaylistIptvId() === DIRECT_FILE_PLAYLIST_ID) {
                    $file_path = $provider->GetPlaylistIptvUrl();
                    hd_debug_print("copy file: $file_path to $m3u_file");
                    $res = copy($file_path, $m3u_file);
                    if ($res === false) {
                        $errors = error_get_last();
                        $logfile = "Copy error: " . $errors['type'] . "\n" . $errors['message'];
                    }
                } else {
                    if ($provider->get_provider_info() === false) {
                        throw new Exception("Unable to get provider info to download: " . json_encode($params));
                    }
                    $cmd = API_COMMAND_GET_PLAYLIST;
                    $curl_opts = $provider->getCurlOpts($cmd);
                    $exec_result = $provider->execApiCommand($cmd, $m3u_file, false, $curl_opts);
                    $res = $provider->postExecAction($cmd, $exec_result, $m3u_file);
                    if ($res === false) {
                        $logfile = "Error code: " . $provider->getCurlWrapper()->get_error_no() . "\n" . $provider->getCurlWrapper()->get_error_desc();
                    }
                }
            } else {
                $uri = safe_get_value($params, PARAM_URI);
                if (empty($uri)) {
                    throw new Exception("Empty url: $uri");
                }
                if ($type === PARAM_FILE) {
                    hd_debug_print("m3u copy local file: $uri to $m3u_file");
                    $res = copy($uri, $m3u_file);
                    if ($res === false) {
                        $errors = error_get_last();
                        $logfile = "Copy error: " . $errors['type'] . "\n" .$errors['message'];
                    }
                } else if ($type === PARAM_LINK || $type === PARAM_CONF) {
                    hd_debug_print("m3u download link: $uri");
                    if (!is_proto_http($uri)) {
                        throw new Exception("Incorrect playlist url: $uri");
                    }

                    $curl_wrapper = Curl_Wrapper::getInstance();
                    $this->set_curl_timeouts($curl_wrapper);
                    $res = $curl_wrapper->download_file($uri, $m3u_file, true);
                    $logfile = "Error code: " . $curl_wrapper->get_error_no() . "\n" . $curl_wrapper->get_error_desc();
                } else {
                    throw new Exception("Unknown playlist type");
                }
            }

            if (!$res || !file_exists($m3u_file)) {
                $exception_msg = TR::load('err_load_playlist');
                if (!empty($logfile)) {
                    $exception_msg .= "\n\n$logfile";
                }
                throw new Exception($exception_msg);
            }

            $contents = file_get_contents($m3u_file);
            if (strpos($contents, TAG_EXTM3U) === false) {
                $exception_msg = TR::load('err_bad_m3u_file') . "\n\n$contents";
                throw new Exception($exception_msg);
            }

            $contents = trim($contents, "\x0B\xEF\xBB\xBF");
            $encoding = HD::detect_encoding($contents);
            if ($encoding !== 'utf-8') {
                hd_debug_print("Playlist encoding: $encoding");
                //$contents = iconv($encoding, 'utf-8', $contents);
            }
            file_put_contents($m3u_file, $contents);
            $perf->setLabel('end_download_playlist');

            $perf->setLabel('start_parse_playlist');
            $mtime = filemtime($m3u_file);
            $date_fmt = format_datetime("Y-m-d H:i", $mtime);
            hd_debug_print("Parse playlist $m3u_file (timestamp: $mtime, $date_fmt)");

            $this->iptv_m3u_parser->setPlaylistFile($m3u_file);
            $this->iptv_m3u_parser->parseHeader();

            // update playlists xmltv sources
            $saved_source = $this->get_xmltv_sources(XMLTV_SOURCE_PLAYLIST, $playlist_id);
            $hashes = array();
            foreach ($saved_source as $source) {
                $hashes[$source[PARAM_HASH]] = array(
                    PARAM_NAME => $source[PARAM_NAME],
                    PARAM_URI => $source[PARAM_URI],
                    PARAM_CACHE => $source[PARAM_CACHE]
                );
            }
            hd_debug_print("saved playlist sources: " . json_encode($hashes), true);

            $sources = $this->iptv_m3u_parser->getXmltvSources();
            foreach ($sources as $url) {
                $item = array();
                $hash = Hashed_Array::hash($url);
                $item[PARAM_HASH] = $hash;
                $item[PARAM_TYPE] = PARAM_LINK;
                if (key_exists($hash, $hashes)) {
                    $item[PARAM_NAME] = $hashes[$hash][PARAM_NAME];
                    $item[PARAM_URI] = $hashes[$hash][PARAM_URI];
                    $item[PARAM_CACHE] = $hashes[$hash][PARAM_CACHE];
                } else {
                    $item[PARAM_NAME] = basename($url);
                    $item[PARAM_URI] = $url;
                    $item[PARAM_CACHE] = XMLTV_CACHE_AUTO;
                }

                $saved_source->put($hash, $item);
                hd_debug_print("playlist source: ($hash) $url", true);
            }

            $this->set_playlist_xmltv_sources($playlist_id, $saved_source);

            if ($only_headers) {
                $info = "Total sources: " . $sources->size();
            } else {
                $database_attached = $this->sql_playlist->attachDatabase($db_file, M3uParser::IPTV_DB);
                if ($database_attached === 0) {
                    $exception_msg = "Can't attach to database: $db_file with name: " . M3uParser::IPTV_DB;
                    throw new Exception($exception_msg);
                }

                hd_debug_print("Database attached: $database_attached");
                $count = $this->iptv_m3u_parser->parseIptvPlaylist($this->sql_playlist);
                if (!$count) {
                    $contents = @file_get_contents($m3u_file);
                    $exception_msg = TR::load('err_load_playlist') . " Empty playlist!\n\n$contents";
                    throw new Exception($exception_msg);
                }
                $info = "Total entries: $count";
            }

            $perf->setLabel('end_parse_playlist');

            $report_download = $perf->getReportItem(Perf_Collector::TIME, 'start_download_playlist', 'end_download_playlist');
            $report_parse = $perf->getReportItem(Perf_Collector::TIME, 'start_parse_playlist', 'end_parse_playlist');
            $mem_report = $perf->getReportItem(Perf_Collector::MEMORY_USAGE_KB, 'start_download_playlist', 'end_parse_playlist');
            hd_debug_print_separator();
            hd_debug_print("Parse playlist done!");
            hd_debug_print($info);
            hd_debug_print("Download time: $report_download sec");
            hd_debug_print("Parse time:    $report_parse sec");
            hd_debug_print("Memory usage:  $mem_report kb");
            hd_debug_print_separator();
            $ret = true;
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            $ret = false;
            hd_debug_print($ex->getMessage());
            $err = $ex->getMessage();
            $rq_err = Dune_Last_Error::get_last_error(LAST_ERROR_REQUEST);
            if (!empty($rq_err)) {
                $err .= "\n\n" . $rq_err;
            }

            $pl_err = Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST);
            if (!empty($pl_err)) {
                $err .= "\n\n" . $pl_err;
            }

            Dune_Last_Error::set_last_error(LAST_ERROR_PLAYLIST, $err);
            if (file_exists($m3u_file)) {
                hd_debug_print("Clear playlist: $m3u_file");
                unlink($m3u_file);
            }
            $this->sql_playlist->detachDatabase(M3uParser::IPTV_DB);
            if (file_exists($db_file)) {
                hd_debug_print("Clear db: $db_file");
                unlink($db_file);
            }
            hd_debug_print_separator();
        }

        return $ret;
    }

    public function is_m3u_vod()
    {
        return get_class($this->vod) === 'vod_standard';
    }

    ////////////////////////////////////////////////////////
    /// init database

    /**
     * @param bool $force
     * @return bool
     */
    public function init_playlist_db($force = false)
    {
        hd_debug_print(null, true);

        Dune_Last_Error::clear_last_error(LAST_ERROR_PLAYLIST);
        Dune_Last_Error::clear_last_error(LAST_ERROR_REQUEST);

        $playlist_id = $this->get_active_playlist_id();
        if (!$this->is_playlist_entry_exist($playlist_id)) {
            hd_debug_print("Playlist info for ID: $playlist_id is not exist!");
            return false;
        }

        $params = $this->get_playlist_parameters($playlist_id);
        hd_debug_print("Process playlist: {$params[PARAM_NAME]} ($playlist_id)");

        $db_file = get_data_path("$playlist_id.db");

        if ($this->sql_playlist) {
            // attach to playlist db. if db not exist it will be created
            if (!$force && $this->sql_playlist->is_database_attached('main', $db_file) === 2) {
                hd_debug_print("Database already inited!", true);
                return true;
            }
            $this->reset_playlist_db();
        }

        hd_debug_print("Load playlist settings database: $db_file", true);
        $this->sql_playlist = new Sql_Wrapper($db_file);
        if (!$this->sql_playlist->is_valid()) {
            return false;
        }

        // create settings table
        $query = sprintf(self::CREATE_PLAYLIST_SETTINGS_TABLE, self::SETTINGS_TABLE);
        $this->sql_playlist->exec($query);

        // create cookies table
        $query = sprintf(self::CREATE_COOKIES_TABLE, self::COOKIES_TABLE);
        $this->sql_playlist->exec($query);

        // create common TV Favorites table
        $query = sprintf(self::CREATE_ORDERED_TABLE, self::get_table_name(TV_FAV_COMMON_GROUP_ID), COLUMN_CHANNEL_ID);
        $this->sql_playlist->exec($query);

        // create tables for vod search, vod filters, vod favorites
        if ($this->is_vod_enabled()) {
            $tables = array(
                VOD_FILTER_LIST => 'item',
                VOD_SEARCH_LIST => 'item',
                VOD_FAV_GROUP_ID => COLUMN_CHANNEL_ID,
            );
            if ($this->get_vod_class() === 'vod_standard') {
                $tables[VOD_LIST_GROUP_ID] = COLUMN_CHANNEL_ID;
            }

            foreach ($tables as $list => $column) {
                $table_name = self::get_table_name($list);
                $query = sprintf(self::CREATE_ORDERED_TABLE, $table_name, $column);
                $this->sql_playlist->exec($query);
            }
        }

        $provider_class = $this->get_playlist_parameter($playlist_id, PARAM_PROVIDER);
        if (!empty($provider_class)) {
            $provider = $this->get_provider($playlist_id);
            if ($provider !== null) {
                // update xmltv playlist sources from config
                $config_sources = $provider->getConfigValue(CONFIG_XMLTV_SOURCES);
                if (!empty($config_sources)) {
                    $playlist_xmltv = self::PLAYLIST_XMLTV_TABLE;
                    $query = '';
                    $q_type = Sql_Wrapper::sql_quote(PARAM_CONF);
                    $q_cache = Sql_Wrapper::sql_quote(XMLTV_CACHE_AUTO);
                    $known_sources = array();
                    foreach ($config_sources as $source) {
                        $hash = Hashed_Array::hash($source);
                        $q_source = Sql_Wrapper::sql_quote($source);
                        $q_name = Sql_Wrapper::sql_quote(basename($source));
                        $known_sources[] = $hash;

                        $query .= "INSERT OR IGNORE INTO $playlist_xmltv
                        (playlist_id, hash, type, name, uri, cache) VALUES ('$playlist_id', '$hash', $q_type, $q_name, $q_source, $q_cache);";
                    }

                    if (!empty($known_sources)) {
                        $where = Sql_Wrapper::sql_make_where_clause($known_sources, COLUMN_HASH, true);
                        $query .= "DELETE FROM $playlist_xmltv WHERE $where AND type = $q_type;";
                    }
                    $this->sql_params->exec_transaction($query);
                }

                $provider_playlist_id = $provider->GetPlaylistIptvId();
                hd_debug_print("Provider playlist: $provider_playlist_id", true);
            }
        }

        $db_file = get_data_path($this->make_base_name(PLUGIN_ORDERS) . '.db');
        hd_debug_print("Orders database path: $db_file", true);
        if ($this->sql_playlist->attachDatabase($db_file, self::PLAYLIST_ORDERS_DB) === 0) {
            hd_debug_print("Can't attach to database with name: " . self::PLAYLIST_ORDERS_DB);
        }

        // create group table
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $query = sprintf(self::CREATE_GROUPS_INFO_TABLE, $groups_info_table);
        $this->sql_playlist->exec($query);
        // create table
        $query = sprintf(self::CREATE_CHANNELS_INFO_TABLE, self::get_table_name(CHANNELS_INFO));
        $this->sql_playlist->exec($query);
        if (!$this->sql_playlist->is_column_exists(CHANNELS_INFO, COLUMN_EPG_SHIFT, self::PLAYLIST_ORDERS_DB)) {
            $query = sprintf("ALTER TABLE %s ADD COLUMN %s INTEGER DEFAULT 0;", self::get_table_name(CHANNELS_INFO), COLUMN_EPG_SHIFT);
            $this->sql_playlist->exec($query);
        }
        // create order_groups table
        $query = sprintf(self::CREATE_ORDERED_TABLE, self::get_table_name(GROUPS_ORDER), COLUMN_GROUP_ID);
        $this->sql_playlist->exec($query);
        // create table for favorites
        $query = sprintf(self::CREATE_ORDERED_TABLE, self::get_table_name(TV_FAV_GROUP_ID), COLUMN_CHANNEL_ID);
        $this->sql_playlist->exec($query);

        // add special groups to the table if the not exists
        $special_group = array(
            array(COLUMN_GROUP_ID => TV_FAV_GROUP_ID, COLUMN_TITLE => TV_FAV_GROUP_CAPTION, COLUMN_ICON => TV_FAV_GROUP_ICON),
            array(COLUMN_GROUP_ID => TV_HISTORY_GROUP_ID, COLUMN_TITLE => TV_HISTORY_GROUP_CAPTION, COLUMN_ICON => TV_HISTORY_GROUP_ICON),
            array(COLUMN_GROUP_ID => TV_CHANGED_CHANNELS_GROUP_ID, COLUMN_TITLE => TV_CHANGED_CHANNELS_GROUP_CAPTION,
                COLUMN_ICON => TV_CHANGED_CHANNELS_GROUP_ICON),
            array(COLUMN_GROUP_ID => VOD_GROUP_ID, COLUMN_TITLE => VOD_GROUP_CAPTION, COLUMN_ICON => VOD_GROUP_ICON),
            array(COLUMN_GROUP_ID => TV_ALL_CHANNELS_GROUP_ID, COLUMN_TITLE => TV_ALL_CHANNELS_GROUP_CAPTION, COLUMN_ICON => TV_ALL_CHANNELS_GROUP_ICON),
        );

        $query = '';
        foreach ($special_group as $group) {
            $group['special'] = 1;
            $values = Sql_Wrapper::sql_make_insert_list($group);
            $query .= "INSERT OR IGNORE INTO $groups_info_table $values;";
        }
        $this->sql_playlist->exec_transaction($query);

        $history_path = $this->get_history_path();
        $tv_history_db = $history_path . $this->make_base_name(TV_HISTORY, null, false) . ".db";
        // attach to tv_history db. if db not exist it will be created
        if ($this->sql_playlist->attachDatabase($tv_history_db, self::TV_HISTORY_DB) === 0) {
            hd_debug_print("Can't attach to database: $tv_history_db with name: " . self::TV_HISTORY_DB);
        } else {
            // create tv history table
            $tv_history_table = self::get_table_name(TV_HISTORY);
            $query = sprintf(self::CREATE_TV_HISTORY_TABLE, $tv_history_table);
            $this->sql_playlist->exec($query);
            if (!$this->sql_playlist->is_column_exists( self::TV_HISTORY_TABLE, COLUMN_TIME_START, self::TV_HISTORY_DB)) {
                $query = "BEGIN TRANSACTION;";
                $query .= sprintf("ALTER TABLE %s ADD COLUMN %s INTEGER DEFAULT 0;", $tv_history_table, COLUMN_TIME_START);
                $query .= sprintf("ALTER TABLE %s ADD COLUMN %s INTEGER DEFAULT 0;", $tv_history_table, COLUMN_TIME_END);
                $query .= "COMMIT;";
                $this->sql_playlist->exec($query);
            }
        }

        // move playlist xmltv sources to new database table
        if ($this->sql_playlist->is_table_exists(self::XMLTV_TABLE)) {
            $old_table = self::XMLTV_TABLE;
            $rows = $this->sql_playlist->fetch_array("SELECT * FROM $old_table;");

            $table_name = self::PLAYLIST_XMLTV_TABLE;
            $query = '';
            foreach ($rows as $row) {
                $row[COLUMN_PLAYLIST_ID] = $playlist_id;
                $values = Sql_Wrapper::sql_make_insert_list($row);
                $query .= "INSERT OR IGNORE INTO $table_name $values;";
            }
            $this->sql_params->exec_transaction($query);
            $this->sql_playlist->exec("DROP TABLE $old_table;");
        }

        // move selected xmltv sources to new database table
        if ($this->sql_playlist->is_table_exists(self::SELECTED_XMLTV_TABLE)) {
            $table_name = self::SELECTED_XMLTV_TABLE;
            $rows = $this->sql_playlist->fetch_single_array("SELECT hash FROM $table_name;", COLUMN_HASH);
            $query = '';
            foreach ($rows as $hash) {
                $query .= "INSERT OR IGNORE INTO $table_name (playlist_id, hash) VALUES ('$playlist_id', '$hash');";
            }
            $this->sql_params->exec_transaction($query);
            $this->sql_playlist->exec("DROP TABLE $table_name;");
        }

        // remove unused settings from db
        $where = Sql_Wrapper::sql_make_where_clause(array('cur_xmltv_source', 'cur_xmltv_key'), COLUMN_NAME);
        $this->sql_playlist->exec(sprintf("DELETE FROM %s WHERE $where;", self::SETTINGS_TABLE));

        //////////////////////////////////////////////////////
        /// Upgrade settings to database
        $group_icons = $this->upgrade_settings($playlist_id);
        $this->upgrade_orders($playlist_id, $group_icons);

        // tv history is per playlist or per provider playlist
        $this->upgrade_tv_history();

        // create vod history table
        if ($this->is_vod_playlist() || (!empty($provider) && $provider->hasApiCommand(API_COMMAND_GET_VOD))) {
            $vod_history_db = $this->get_history_path() . $this->make_base_name(VOD_HISTORY, null, false) . ".db";
            if ($this->sql_playlist->attachDatabase($vod_history_db, self::VOD_HISTORY_DB) === 0) {
                hd_debug_print("Can't attach to database: $vod_history_db with name: " . self::VOD_HISTORY_DB);
            } else {
                $query = sprintf(self::CREATE_VOD_HISTORY_TABLE, self::get_table_name(VOD_HISTORY));
                $this->sql_playlist->exec($query);
                $this->upgrade_vod_history();
            }
        }

        hd_debug_print("Database initialized.");
        hd_debug_print_separator();

        $this->init_screen_view_parameters($this->get_background_image());

        return true;
    }

    ///////////////////////////////////////////////////////////////////////
    /// IPTV

    /**
     * Return false if load channels failed
     * Return true if channels loaded successful
     *
     * @param object $plugin_cookies
     * @param bool $reload_playlist
     * @return bool
     */
    public function load_channels(&$plugin_cookies, $reload_playlist = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("Force reload: " . var_export($reload_playlist, true));

        $plugin_cookies->toggle_move = false;

        $this->init_plugin();

        // Init playlist db
        if (!$this->init_playlist_db()) {
            hd_debug_print("Init playlist db failed");
            return false;
        }

        Dune_Last_Error::clear_last_error(LAST_ERROR_PLAYLIST);

        $playlist_id = $this->get_active_playlist_id();
        $perf = new Perf_Collector();
        $perf->reset('start');

        $this->update_ui_settings();

        // check is vod.
        $this->init_user_agent();

        if ($this->vod === null) {
            $this->vod_enabled = false;
            $vod_class = $this->get_vod_class();
            if (!empty($vod_class)) {
                hd_debug_print("Using VOD: $vod_class");
                $this->vod = new $vod_class($this);
                $provider = $this->get_active_provider();
                if (!is_null($provider)) {
                    $ignore_groups = $provider->getConfigValue(CONFIG_IGNORE_GROUPS);
                }

                $this->vod_enabled = $this->vod->init_vod($provider);
                $this->vod->init_vod_screens();
                hd_debug_print("VOD enabled: " . SwitchOnOff::to_def($this->vod_enabled), true);
            }
        }

        if ($this->is_vod_playlist()) {
            hd_debug_print("VOD playlist inited", true);
            return true;
        }

        if ($this->channels_loaded && !$reload_playlist) {
            hd_debug_print("Channels already loaded", true);
            return true;
        }

        $enable_vod_icon = SwitchOnOff::to_def($this->vod_enabled && $this->get_bool_parameter(PARAM_SHOW_VOD_ICON, false));
        $plugin_cookies->{PARAM_SHOW_VOD_ICON} = $enable_vod_icon;
        hd_debug_print("Show VOD icon: $enable_vod_icon", true);

        // clear ext epg
        $ext_epg_channels = get_temp_path("channel_ids.txt");
        if (file_exists($ext_epg_channels)) {
            unlink($ext_epg_channels);
        }

        // init playlist parser
        if (false === $this->init_playlist_parser($reload_playlist)) {
            return false;
        }

        // Parse playlist.
        // if playlist is expired it will downloaded
        // in case of download or parse error returns false
        // if parse success database with playlist is attached
        if (!$this->load_and_parse_m3u_iptv_playlist(false, $reload_playlist)) {
            hd_debug_print("Can't download playlist: $playlist_id");
            return false;
        }

        $this->init_epg_manager();
        $this->cleanup_stalled_locks();
        $this->cleanup_active_xmltv_source();

        if ($this->use_xmltv || $this->picons_source !== PLAYLIST_PICONS) {
            Epg_Manager_Xmltv::set_xmltv_sources($this->get_active_sources());
        }

        $delay_load = false;
        $bg_indexing_runs = false;
        if ($this->picons_source !== PLAYLIST_PICONS) {
            $delay_load = $this->get_bool_setting(PARAM_PICONS_DELAY_LOAD, false);
            $all_sources = $this->get_active_sources();
            if ($all_sources->size() === 0) {
                hd_debug_print("No active XMLTV sources found to collect playlist icons...");
            } else if ($delay_load) {
                $bg_indexing_runs = $this->check_and_run_bg_indexing($all_sources, INDEXING_CHANNELS | INDEXING_ENTRIES);
            } else {
                foreach ($all_sources as $params) {
                    $flag = Epg_Manager_Xmltv::check_xmltv_source($this, $params, INDEXING_CHANNELS);
                    if ($flag !== 0) {
                        $params[PARAM_CURL_CONNECT_TIMEOUT] = $this->get_parameter(PARAM_CURL_CONNECT_TIMEOUT, 30);
                        $params[PARAM_CURL_DOWNLOAD_TIMEOUT] = $this->get_parameter(PARAM_CURL_DOWNLOAD_TIMEOUT, 120);
                        Epg_Manager_Xmltv::reindex_xmltv($params, $flag);
                    }
                }
            }
        }

        if ($this->channels_loaded && !$delay_load && $this->use_xmltv) {
            $this->check_and_run_bg_indexing($this->get_active_sources(), INDEXING_ENTRIES);
            return true;
        }

        hd_debug_print_separator();
        hd_debug_print("Build categories and channels...");

        $playlist_entries = $this->get_playlist_entries_count();
        $playlist_groups = $this->get_playlist_group_count();

        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $channel_info_table = self::get_table_name(CHANNELS_INFO);
        $groups_order_table = self::get_table_name(GROUPS_ORDER);
        $iptv_channels = M3uParser::CHANNELS_TABLE;

        // add provider ignored groups to known_groups
        if (!empty($ignore_groups)) {
            $query = '';
            foreach ($ignore_groups as $group_id) {
                $q_group_id = Sql_Wrapper::sql_quote($group_id);
                $query .= "INSERT OR IGNORE INTO $groups_info_table (group_id, disabled) VALUES ($q_group_id, 1);" . PHP_EOL;
            }
            $this->sql_playlist->exec_transaction($query);
        }

        ////////////////////////////////////////////////////////////////////////////////////
        /// update tables with removed and added groups and channels

        $query = "SELECT COUNT(channel_id) FROM $channel_info_table;";
        $is_new = $this->sql_playlist->query_value($query) === 0;

        // get name of the column for channel ID
        $id_column = $this->get_id_column();

        // update existing database for empty group_id (converted from known_channels.settings)
        $query = "SELECT COUNT(*) FROM $channel_info_table WHERE group_id = '';";
        if ($this->sql_playlist->query_value($query)) {
            hd_debug_print("Update groups name for converted settings", true);
            $query = "UPDATE $channel_info_table
                        SET group_id = (
                            SELECT group_id
                            FROM $iptv_channels
                            WHERE channel_id = $iptv_channels.$id_column
                            LIMIT 1)
                        WHERE group_id = ''
                        AND EXISTS (
                            SELECT 1
                            FROM $iptv_channels
                            WHERE channel_id = $iptv_channels.$id_column AND group_id != $iptv_channels.group_id);";
            $this->sql_playlist->exec($query);
        }

        // select new groups that not present in groups table but exist in iptv_groups
        $iptv_groups = M3uParser::GROUPS_TABLE;
        $query_new_groups = "SELECT * FROM $iptv_groups WHERE group_id NOT IN (SELECT group_id FROM $groups_info_table);";
        $new_groups = $this->sql_playlist->fetch_array($query_new_groups);
        if (!empty($new_groups)) {
            hd_debug_print("Adding new groups: " . json_encode(extract_column($new_groups, COLUMN_GROUP_ID)), true);
            $query = '';
            foreach ($new_groups as $group_row) {
                $group_id = $group_row[COLUMN_GROUP_ID];
                $q_group_id = Sql_Wrapper::sql_quote($group_id);
                $q_group_icon = Sql_Wrapper::sql_quote(empty($group_row[COLUMN_ICON]) ? DEFAULT_GROUP_ICON : $group_row[COLUMN_ICON]);
                $q_adult = Sql_Wrapper::sql_quote($group_row[COLUMN_ADULT]);
                $query .= "INSERT OR IGNORE INTO $groups_info_table (group_id, title, icon, adult) VALUES ($q_group_id, $q_group_id, $q_group_icon, $q_adult);";
                $query .= "INSERT OR IGNORE INTO $groups_order_table (group_id) VALUES ($q_group_id);";

                $group_channels_order_table = self::get_table_name($group_id);
                $query .= sprintf(self::CREATE_ORDERED_TABLE, $group_channels_order_table, COLUMN_CHANNEL_ID);
                hd_debug_print("Added new group channels order: $group_id ($group_channels_order_table)", true);
            }
            $this->sql_playlist->exec_transaction($query);
        }

        // Update group icons
        $query = "SELECT group_id, icon from $iptv_groups WHERE icon <> '';";
        $groups_rows = $this->sql_playlist->fetch_array($query);
        if (!empty($groups_rows)) {
            hd_debug_print("Update changed group id icons", true);
            $query = '';
            foreach ($groups_rows as $row) {
                $q_group_id = Sql_Wrapper::sql_quote($row[COLUMN_GROUP_ID]);
                $q_group_icon = Sql_Wrapper::sql_quote($row[COLUMN_ICON]);
                $query .= "UPDATE $groups_info_table SET icon = $q_group_icon
                             WHERE group_id = $q_group_id
                               AND icon != $q_group_icon 
                               AND (icon = '' OR icon = 'plugin_file://icons/default_group.png');";
            }
            $this->sql_playlist->exec_transaction($query);
        }

        // cleanup if group removed from playlist
        $query = "SELECT group_id FROM $groups_info_table WHERE group_id NOT IN (SELECT group_id FROM $iptv_groups) AND special = 0;";
        $removed_groups = $this->sql_playlist->fetch_single_array($query, COLUMN_GROUP_ID);
        if (!empty($removed_groups)) {
            $groups_order_table = self::get_table_name(GROUPS_ORDER);
            $where = Sql_Wrapper::sql_make_where_clause($removed_groups, COLUMN_GROUP_ID);
            $query = "DELETE FROM $groups_order_table WHERE $where;";
            $query .= "DELETE FROM $groups_info_table WHERE $where;";
            foreach ($removed_groups as $group_id) {
                $group_channels_order_table = self::get_table_name($group_id);
                $query .= "DROP TABLE IF EXISTS $group_channels_order_table;";
                hd_debug_print("Removing group channels order: $group_id ($group_channels_order_table)", true);
            }
            $this->sql_playlist->exec_transaction($query);
        }

        // mark as removed channels that not present iptv.iptv_channels db
        $query = "SELECT channel_id FROM $channel_info_table WHERE channel_id NOT IN
                    (SELECT $id_column AS channel_id FROM $iptv_channels WHERE channel_id NOT NULL AND channel_id != '');";
        $removed_channels = $this->sql_playlist->fetch_single_array($query, COLUMN_CHANNEL_ID);

        $query = "SELECT $id_column AS channel_id FROM $iptv_channels
                    WHERE channel_id NOT IN (SELECT channel_id FROM $channel_info_table WHERE changed != -1);";
        $new_channels = $this->sql_playlist->fetch_single_array($query, COLUMN_CHANNEL_ID);

        if (!empty($removed_channels)) {
            $remove_where = Sql_Wrapper::sql_make_where_clause($removed_channels, COLUMN_CHANNEL_ID);
            $query = "UPDATE $channel_info_table SET changed = -1 WHERE $remove_where;";
            $this->sql_playlist->exec($query);
            hd_debug_print("Removing not exist channels: $remove_where", true);
        }

        if (!empty($new_channels)) {
            // add new channels
            $add_where = Sql_Wrapper::sql_make_where_clause($new_channels, COLUMN_CHANNEL_ID);
            $query = "INSERT OR REPLACE INTO $channel_info_table (channel_id, title, group_id, adult)
                        SELECT $id_column AS channel_id, title, group_id, adult
                        FROM $iptv_channels WHERE $add_where
                        GROUP BY channel_id ORDER BY ROWID;";
            $this->sql_playlist->exec($query);
            hd_debug_print("Adding new channels: " . json_encode($new_channels), true);
        }

        // update group_id title and adult if changed for channels
        $query = "SELECT ch.channel_id, pl.title, pl.group_id, pl.adult
                    FROM $channel_info_table AS ch
                    INNER JOIN $iptv_channels as pl
                        ON channel_id = pl.$id_column
                    WHERE changed = 0
                      AND (ch.title != pl.title OR ch.title IS NULL OR
                           ch.group_id != pl.group_id OR ch.group_id OR
                           ch.adult != pl.adult OR ch.adult);";
        $changed_channels = $this->sql_playlist->fetch_array($query);

        if (!empty($changed_channels)) {
            hd_debug_print("Update changed info for channels", true);
            $query = '';
            foreach ($changed_channels as $changed_channel) {
                $q_channel_id = Sql_Wrapper::sql_quote($changed_channel[COLUMN_CHANNEL_ID]);
                $q_title = Sql_Wrapper::sql_quote($changed_channel[COLUMN_TITLE]);
                $q_group_id = Sql_Wrapper::sql_quote($changed_channel[COLUMN_GROUP_ID]);
                $q_adult = Sql_Wrapper::sql_quote($changed_channel[COLUMN_ADULT]);
                $query .= "UPDATE $channel_info_table SET title = $q_title, group_id = $q_group_id, adult = $q_adult WHERE channel_id = $q_channel_id;";
            }
            $this->sql_playlist->exec_transaction($query);
        }

        $query = '';
        if (!empty($new_groups)) {
            hd_debug_print("Fill new group channels orders", true);
            foreach ($new_groups as $group_row) {
                $group_channels_order_table = self::get_table_name($group_row[COLUMN_GROUP_ID]);
                $q_group_id = Sql_Wrapper::sql_quote($group_row[COLUMN_GROUP_ID]);

                // Add channels to orders from iptv_channels for selected group that not disabled in known_channels
                $query .= "INSERT OR IGNORE INTO $group_channels_order_table (channel_id)
                            SELECT channel_id FROM $channel_info_table
                            WHERE group_id = $q_group_id
                            AND changed = 1 AND disabled == 0 
                            ORDER BY ROWID;";
            }
            $this->sql_playlist->exec_transaction($query);
        }

        $existing_group_ids = $this->get_groups(PARAM_GROUP_ORDINARY, PARAM_ENABLED, COLUMN_GROUP_ID);

        hd_debug_print("Update group channels orders", true);
        $query = '';
        foreach ($existing_group_ids as $group_id) {
            if (empty($group_id)) continue;

            $group_channels_order_table = self::get_table_name($group_id);
            $q_group_id = Sql_Wrapper::sql_quote($group_id);
            $query .= "INSERT OR IGNORE INTO $group_channels_order_table (channel_id)
                        SELECT channel_id FROM $channel_info_table WHERE group_id = $q_group_id AND disabled = 0 AND changed != -1 ORDER BY ROWID;";

            $query .= "DELETE FROM $group_channels_order_table WHERE channel_id IN
                        (SELECT channel_id FROM $group_channels_order_table
                        EXCEPT
                        SELECT channel_id FROM $channel_info_table WHERE group_id = $q_group_id AND changed != -1);";
        }
        $this->sql_playlist->exec_transaction($query);

        hd_debug_print("Reset changed flag for channels in disabled groups", true);
        $query = "UPDATE $channel_info_table SET changed = 0, disabled = 1
                    WHERE group_id IN (SELECT group_id FROM $groups_info_table WHERE disabled = 1 AND special = 0);";
        $this->sql_playlist->exec($query);

        if ($is_new) {
            hd_debug_print("Clear changed flag for new playlist", true);
            $this->clear_changed_channels();
        }

        $known_cnt = $this->get_channels_count(TV_ALL_CHANNELS_GROUP_ID, PARAM_ALL, PARAM_ALL);
        $visible_groups_cnt = $this->get_groups_count(PARAM_GROUP_ORDINARY, PARAM_ENABLED);
        $hidden_groups_cnt = $this->get_groups_count(PARAM_GROUP_ORDINARY, PARAM_DISABLED);

        $visible_channels_cnt = $this->get_channels_count(TV_ALL_CHANNELS_GROUP_ID, PARAM_ENABLED);
        $hidden_channels_cnt = $this->get_channels_count(TV_ALL_CHANNELS_GROUP_ID, PARAM_DISABLED);
        $all_hidden_channels_cnt = $this->get_channels_count(TV_ALL_CHANNELS_GROUP_ID, PARAM_DISABLED, PARAM_ALL);

        $added_channels_cnt = $this->get_changed_channels_count(PARAM_NEW);
        $removed_channels_cnt = $this->get_changed_channels_count(PARAM_REMOVED);

        $perf->setLabel('end');
        $report = $perf->getFullReport();

        hd_debug_print("Is new playlist:     " . var_export($is_new, true));
        hd_debug_print("ID column:           $id_column");
        hd_debug_print("Playlist channels:   $playlist_entries");
        hd_debug_print("Playlist groups:     $playlist_groups");
        hd_debug_print("Known channels:      $known_cnt");
        hd_debug_print("Visible channels:    $visible_channels_cnt");
        hd_debug_print("Hidden channels:     $hidden_channels_cnt");
        hd_debug_print("All hidden channels: $all_hidden_channels_cnt");
        hd_debug_print("New channels:        $added_channels_cnt");
        hd_debug_print("Removed channels:    $removed_channels_cnt");
        hd_debug_print("Visible groups:      $visible_groups_cnt");
        hd_debug_print("Hidden groups:       $hidden_groups_cnt");
        hd_debug_print("New groups:          " . count($new_groups));
        hd_debug_print("Removed groups:      " . count($removed_groups));
        hd_debug_print("Load time:           {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage:        {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_debug_print_separator();

        if (!$bg_indexing_runs && $this->use_xmltv) {
            $this->check_and_run_bg_indexing($this->get_active_sources(), INDEXING_ENTRIES);
        }

        $this->channels_loaded = true;

        return true;
    }

    public function reset_channels()
    {
        $this->vod = null;
        $this->reset_channels_loaded();
        $this->reset_playlist_db();
    }

    /**
     * @return void
     */
    public function init_user_agent()
    {
        $user_agent = $this->get_playlist_parameter($this->get_active_playlist_id(), PARAM_USER_AGENT);
        if (!empty($user_agent) && $user_agent !== HD::get_default_user_agent()) {
            hd_debug_print("Set user agent: $user_agent");
            HD::set_dune_user_agent($user_agent);
        }
    }

    /**
     * @param Curl_Wrapper $curl_wrapper
     * @return void
     */
    public function set_curl_timeouts(&$curl_wrapper)
    {
        $curl_wrapper->set_connection_timeout($this->get_parameter(PARAM_CURL_CONNECT_TIMEOUT, 30));
        $curl_wrapper->set_download_timeout($this->get_parameter(PARAM_CURL_DOWNLOAD_TIMEOUT, 120));
    }

    /**
     * @param Hashed_Array $sources
     * @param int $indexing_flag
     * @return bool
     */
    public function check_and_run_bg_indexing($sources, $indexing_flag)
    {
        $indexing_run = false;
        $to_index = array();
        foreach ($sources as $source_id => $params) {
            $indexing_flag = Epg_Manager_Xmltv::check_xmltv_source($this, $params, $indexing_flag);
            if ($indexing_flag !== 0) {
                $to_index[$source_id] = array('flag' => $indexing_flag, 'params' => $params);
            }
        }

        foreach ($to_index as $source_id => $value) {
            $this->run_bg_epg_indexing($source_id, $value['flag']);
            $indexing_run = true;
        }

        return $indexing_run;
    }

    /**
     * @param string $source_id
     * @param int $indexing_flag
     * @param bool $allow_index
     * @return void
     */
    public function run_bg_epg_indexing($source_id, $indexing_flag, $allow_index = false)
    {
        hd_debug_print(null, true);

        $allow_index |= $this->get_bool_setting(PARAM_PICONS_DELAY_LOAD, false);
        $allow_index |= $this->use_xmltv;
        if (!$allow_index) {
            return;
        }

        $item = $this->get_xmltv_sources(XMLTV_SOURCE_ALL, $this->get_active_playlist_id())->get($source_id);
        if ($item === null) {
            hd_debug_print("XMLTV source '$source_id' not found");
            return;
        }

        if (!isset($item[PARAM_HASH])) {
            $item[PARAM_HASH] = Hashed_Array::hash($item[PARAM_URI]);
        }

        // background indexing performed only for one url!
        hd_debug_print("Run background indexing for: '$source_id': {$item[PARAM_URI]}");
        $item[PARAM_CURL_CONNECT_TIMEOUT] = $this->get_parameter(PARAM_CURL_CONNECT_TIMEOUT, 30);
        $item[PARAM_CURL_DOWNLOAD_TIMEOUT] = $this->get_parameter(PARAM_CURL_DOWNLOAD_TIMEOUT, 120);

        $config = array(
            PARAM_COOKIE_ENABLE_DEBUG => LogSeverity::$is_debug,
            PARAM_CACHE_DIR => $this->get_cache_dir(),
            PARAMS_XMLTV => $item,
            PARAM_INDEXING_FLAG => $indexing_flag,
        );

        $config_file = get_temp_path(sprintf(self::PARSE_CONFIG, $source_id));
        hd_debug_print("Config: " . json_encode($config), true);
        file_put_contents($config_file, pretty_json_format($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        export_DuneSystem();

        $ext_php = get_platform_php();
        $script_path = get_install_path('bin/index_epg.php');
        $log_path = get_temp_path("{$source_id}_bg_error.log");
        $cmd = "$ext_php -f $script_path $config_file >$log_path 2>&1 &";
        hd_debug_print("exec: $cmd", true);
        shell_exec($cmd);
    }

    /**
     * @param string $hash
     */
    public function add_selected_xmltv_id($hash)
    {
        hd_debug_print(null, true);
        hd_debug_print("Add to selected: $hash", true);

        $playlist_id = $this->get_active_playlist_id();
        $table_name = self::SELECTED_XMLTV_TABLE;
        $this->sql_params->exec("INSERT OR IGNORE INTO $table_name (playlist_id, hash) VALUES ('$playlist_id', '$hash');");
    }

    /**
     * @param string $hash
     */
    public function remove_selected_xmltv_id($hash)
    {
        hd_debug_print(null, true);
        hd_debug_print("Removed from selected: $hash", true);

        $playlist_id = $this->get_active_playlist_id();
        $table_name = self::SELECTED_XMLTV_TABLE;
        $this->sql_params->exec("DELETE FROM $table_name WHERE playlist_id = '$playlist_id' AND hash = '$hash';");
    }

    /**
     * @return array
     */
    public function get_selected_xmltv_ids()
    {
        if ($this->sql_playlist->is_table_exists(M3uParser::S_CHANNELS_TABLE, M3uParser::IPTV_DB)) {
            $playlist_id = $this->get_active_playlist_id();
            $table_name = self::SELECTED_XMLTV_TABLE;
            return $this->sql_params->fetch_single_array("SELECT hash FROM $table_name WHERE playlist_id = '$playlist_id' ORDER BY ROWID;", PARAM_HASH);
        }

        return array();
    }

    /**
     * @return bool
     */
    public function is_selected_xmltv_id($hash)
    {
        if (!$this->sql_playlist->is_table_exists(M3uParser::S_CHANNELS_TABLE, M3uParser::IPTV_DB)) {
            return false;
        }

        $playlist_id = $this->get_active_playlist_id();
        $table_name = self::SELECTED_XMLTV_TABLE;
        return (bool)$this->sql_params->query_value("SELECT count(*) FROM $table_name WHERE playlist_id = '$playlist_id' AND hash = '$hash';");
    }

    /**
     * @param string $playlist_id
     * @param array|string $values
     */
    public function set_selected_xmltv_ids($playlist_id, $values)
    {
        hd_debug_print(null, true);

        if (!is_array($values)) {
            $values = array($values);
        }
        hd_debug_print("Set selected: " . json_encode($values), true);

        $table_name = self::SELECTED_XMLTV_TABLE;
        $query = '';
        foreach ($values as $hash) {
            $query .= "INSERT INTO $table_name (playlist_id, hash) VALUES ('$playlist_id', '$hash');";
        }

        $this->sql_params->exec_transaction($query);
    }

    public function get_plugin_cookies()
    {
        return $this->plugin_cookies;
    }

    public function set_plugin_cookies(&$plugin_cookies)
    {
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

    /**
     * @return Hashed_Array<array>
     */
    public function get_epg_presets()
    {
        return $this->epg_presets;
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

    public function is_ext_epg_enabled()
    {
        return is_ext_epg_supported() && $this->get_bool_setting(PARAM_SHOW_EXT_EPG);
    }

    public function get_default_channel_icon($is_classic = true)
    {
        if ($is_classic) {
            if (empty($this->default_channel_icon_classic)) {
                $this->default_channel_icon_classic = DEFAULT_CHANNEL_ICON_PATH;
            }
            return $this->default_channel_icon_classic;
        }

        if (empty($this->default_channel_icon_newui)) {
            $this->default_channel_icon_newui = $this->get_bool_setting(PARAM_NEWUI_SQUARE_ICONS, false)
                ? DEFAULT_CHANNEL_ICON_PATH_SQ
                : DEFAULT_CHANNEL_ICON_PATH;
        }
        return $this->default_channel_icon_newui;
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

        $api_class = $this->providers->get($name);
        return is_null($api_class) ? null : clone $api_class;
    }

    /**
     * @return api_default|null
     */
    public function get_active_provider()
    {
        if (is_null($this->active_provider)) {
            $this->active_provider = $this->get_provider($this->get_active_playlist_id(), true);
        }

        return $this->active_provider;
    }

    /**
     * @param string $playlist_id
     * @param bool $request_token
     * @return api_default|null
     */
    public function get_provider($playlist_id, $request_token = false)
    {
        hd_debug_print(null, true);

        if ($playlist_id === $this->get_active_playlist_id() && $this->active_provider !== null) {
            return $this->get_active_provider();
        }

        $params = $this->get_playlist_parameters($playlist_id);
        if (safe_get_value($params, PARAM_TYPE) !== PARAM_PROVIDER) {
            hd_debug_print("Playlist $playlist_id is not a provider");
            return null;
        }

        $provider_id = safe_get_value($params, PARAM_PROVIDER);
        $provider = $this->create_provider_class($provider_id);
        if (is_null($provider)) {
            hd_debug_print("unknown provider class: $provider_id");
            return null;
        }

        if ($provider->getEnable()) {
            $provider->set_provider_playlist_id($playlist_id);
            $name = $provider->getName();
            hd_debug_print("Using provider $provider_id ($name) playlist id: $playlist_id");
            if ($request_token && !$provider->request_provider_token()) {
                hd_debug_print("Can't get provider token");
            }
        } else {
            hd_debug_print("provider $provider_id is disabled");
            $provider = null;
        }


        return $provider;
    }

    /**
     * @return string
     */
    public function get_active_playlist_id()
    {
        $id = $this->get_parameter(PARAM_CUR_PLAYLIST_ID);
        if (!$this->is_playlist_entry_exist($id) && $this->get_all_playlists_count()) {
            $ids = $this->get_all_playlists_ids();
            $this->set_active_playlist_id(reset($ids));
        }

        return $id;
    }

    /**
     * $param string $id
     * @return void
     */
    public function set_active_playlist_id($id)
    {
        hd_debug_print(null, true);

        $this->set_parameter(PARAM_CUR_PLAYLIST_ID, $id);
        $this->active_provider = null;
        $this->reset_playlist_db();
    }

    /**
     * @param string $playlist_id
     * @param int $direction
     * @return bool
     */
    public function arrange_playlist_order_rows($playlist_id, $direction)
    {
        return $this->arrange_rows(self::PLAYLISTS_TABLE, COLUMN_PLAYLIST_ID, $playlist_id, $direction);
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
     * clear EPG cache for active playlist
     *
     * @return void
     */
    public function clear_playlist_epg_cache()
    {
        hd_debug_print(null, true);
        $playlist_id = $this->get_active_playlist_id();
        Epg_Manager_Json::clear_epg_files($playlist_id);
        foreach ($this->get_selected_xmltv_ids() as $id) {
            Epg_Manager_Xmltv::clear_epg_files($id);
        }
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
                if ($this->change_channels_order(VOD_FAV_GROUP_ID, $movie_id, false)) {
                    hd_debug_print("Movie id: $movie_id added to favorites");
                }
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                if ($this->change_channels_order(VOD_FAV_GROUP_ID, $movie_id, true)) {
                    hd_debug_print("Movie id: $movie_id removed from favorites");
                }
                break;

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("Movie favorites cleared");
                $this->remove_channels_order(VOD_FAV_GROUP_ID);
                break;

            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $this->arrange_channels_order_rows(VOD_FAV_GROUP_ID, $movie_id, Ordered_Array::UP);
                break;

            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $this->arrange_channels_order_rows(VOD_FAV_GROUP_ID, $movie_id, Ordered_Array::DOWN);
                break;
            default:
        }
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

        hd_debug_print("Generate stream url for channel id: {$channel_row[COLUMN_CHANNEL_ID]} '{$channel_row[COLUMN_TITLE]}'");

        // replace all macros
        $stream_url = $channel_row[COLUMN_PATH];
        if (empty($stream_url)) {
            throw new Exception("Empty url!");
        }

        $force_detect = false;
        $provider = $this->get_active_provider();
        if (!is_null($provider)) {
            $idx = $provider->GetPlaylistIptvId();
            if ($idx !== DIRECT_PLAYLIST_ID) {
                $url_subst = $provider->getConfigValue(CONFIG_URL_SUBST);
                if (!empty($url_subst)) {
                    $stream_url = preg_replace($url_subst['regex'], $url_subst['replace'], $stream_url);
                    $stream_url = $provider->replace_macros($stream_url);
                }
            }

            $streams = $provider->GetStreams();
            if (!empty($streams)) {
                $idx = $provider->GetProviderParameter(MACRO_STREAM_ID);
                $force_detect = ($streams[$idx] === 'MPEG-TS');
            }

            $detect_stream = $provider->getConfigValue(PARAM_DUNE_FORCE_TS);
            if ($detect_stream) {
                $force_detect = $detect_stream;
            }
        }

        if ((int)$archive_ts !== -1) {
            $m3u_info = $this->iptv_m3u_parser->getM3uInfo();
            if (!empty($m3u_info)) {
                $catchup = $this->iptv_m3u_parser->getM3uInfo()->getCatchupType();
            }

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

            $channel_catchup = $channel_row[COLUMN_CATCHUP];
            if (!empty($channel_catchup)) {
                // channel catchup override playlist, user and config settings
                $catchup = $channel_catchup;
            } else if (empty($catchup)) {
                $catchup = ATTR_CATCHUP_SHIFT;
            }

            $archive_url = $channel_row[COLUMN_CATCHUP_SOURCE];
            hd_debug_print("using catchup params: $catchup", true);
            if (empty($archive_url)) {
                /** @var array $m */
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
            $dune_params_str = $this->generate_dune_params($channel_row[COLUMN_CHANNEL_ID],
                json_decode(safe_get_value($channel_row, COLUMN_EXT_PARAMS), true));

            if (!empty($dune_params_str)) {
                $stream_url .= HD::DUNE_PARAMS_MAGIC . $dune_params_str;
            }

            $detect_ts = $this->get_bool_setting(PARAM_DUNE_FORCE_TS, false) || $force_detect;
            $stream_url = HD::make_ts($stream_url, $detect_ts);
        }

        return $stream_url;
    }

    /**
     * @return array
     */
    public function collect_dune_params()
    {
        $params = $this->get_playlist_parameters($this->get_active_playlist_id());
        if (safe_get_value($params, PARAM_USE_DUNE_PARAMS, SwitchOnOff::on) === SwitchOnOff::off) {
            return array();
        }

        $dune_params = dune_params_to_array(safe_get_value($params, PARAM_DUNE_PARAMS));

        $provider = $this->get_active_provider();
        if (!is_null($provider)) {
            $dune_params = array_unique(safe_merge_array($dune_params, dune_params_to_array($provider->getConfigValue(PARAM_DUNE_PARAMS))));
        }

        if (HD::get_dune_user_agent() !== HD::get_default_user_agent()) {
            $user_agent = "User-Agent: " . HD::get_dune_user_agent();
            if (!empty($user_agent)) {
                if (!isset($dune_params['http_headers'])) {
                    $dune_params['http_headers'] = $user_agent;
                } else {
                    $pos = strpos($dune_params['http_headers'], "User-Agent:");
                    if ($pos === false) {
                        $dune_params['http_headers'] .= "," . $user_agent;
                    }
                }
            }
        }

        return $dune_params;
    }

    /**
     * @param string $channel_id
     * @param array $ext_params
     * @return string
     */
    public function generate_dune_params($channel_id, $ext_params)
    {
        $dune_params = $this->collect_dune_params();

        if (!empty($ext_params[PARAM_EXT_VLC_OPTS])) {
            $ext_vlc_opts = array();
            foreach ($ext_params[PARAM_EXT_VLC_OPTS] as $value) {
                $pair = explode('=', $value);
                $ext_vlc_opts[strtolower(trim($pair[0]))] = trim($pair[1]);
            }

            if (isset($ext_vlc_opts['http-user-agent'])) {
                $dune_params['http_headers'] = "User-Agent: " . $ext_vlc_opts['http-user-agent'];
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
                $ch_useragent = $ext_params[TAG_EXTHTTP]['user-agent'];

                // escape commas for dune_params
                if (strpos($ch_useragent, ",,") !== false) {
                    $ch_useragent = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $ch_useragent);
                } else {
                    $ch_useragent = str_replace(",", ",,", $ch_useragent);
                }

                $dune_params['http_headers'] .= rawurlencode("User-Agent: " . $ch_useragent);
            }
        }

        if ($this->get_bool_setting(PARAM_PER_CHANNELS_ZOOM)) {
            $zoom_data = $this->get_channel_zoom($channel_id);
            if (!empty($zoom_data)) {
                $dune_params[COLUMN_ZOOM] = $zoom_data;
            }
        }

        if (empty($dune_params)) {
            return "";
        }

        $magic = str_replace(array('=', '+'), array(':', '%20'), http_build_query($dune_params, null, ','));
        hd_debug_print("dune_params: $magic", true);

        return $magic;
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

        $channel_row = $this->get_channel_info($media_url->channel_id);
        if (empty($channel_row)) {
            throw new Exception("Unknown channel");
        }

        $url = $this->generate_stream_url($channel_row, $archive_ts, true);
        $cmd = 'am start -d "' . $url . '" -t "video/*" -a android.intent.action.VIEW 2>&1';
        hd_debug_print("play movie in the external player: $cmd");
        /** @var array $output */
        exec($cmd, $output);
        hd_debug_print("external player exec result code" . HD::ArrayToStr($output));
        return null;
    }

    public function get_fav_id()
    {
        return $this->get_bool_setting(PARAM_USE_COMMON_FAV, false) ? TV_FAV_COMMON_GROUP_ID : TV_FAV_GROUP_ID;
    }

    /**
     * @return string
     */
    public function get_history_path()
    {
        $path = smb_tree::get_folder_info($this->get_parameter(PARAM_HISTORY_PATH));
        if (is_null($path)) {
            $path = get_data_path(HISTORY_SUBDIR);
        } else {
            $path = get_slash_trailed_path($path);
            if ($path === get_data_path() || $path === get_slash_trailed_path(get_data_path(HISTORY_SUBDIR))) {
                // reset old settings to new
                $this->set_parameter(PARAM_HISTORY_PATH, '');
                $path = get_data_path(HISTORY_SUBDIR);
            }
        }

        if (substr($path, -1, 1) !== '/') {
            $path .= '/';
        }

        if (!file_exists($path)) {
            create_path($path);
        }

        return $path;
    }

    /**
     * @param string|null $path
     * @return void
     */
    public function set_history_path($path = null)
    {
        if (is_null($path) || $path === get_data_path(HISTORY_SUBDIR)) {
            $this->set_parameter(PARAM_HISTORY_PATH, '');
            return;
        }

        create_path($path);
        $this->set_parameter(PARAM_HISTORY_PATH, $path);
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
        $params = $this->get_playlist_parameters($this->get_active_playlist_id());
        if (empty($params)) {
            return false;
        }

        return safe_get_value($params, PARAM_PL_TYPE) === CONTROL_PLAYLIST_VOD;
    }

    /**
     * @return string
     */
    public static function get_playlist_cache_path()
    {
        $path = get_slash_trailed_path(get_data_path('playlist_cache'));
        create_path($path);
        return $path;
    }

    /**
     * @return string
     */
    public function get_playlist_cache_filepath($is_tv)
    {
        if ($is_tv) {
            $base_name = self::get_playlist_cache_path() . $this->make_base_name(IPTV_PLAYLIST);
        } else {
            $base_name = self::get_playlist_cache_path() . $this->make_base_name(VOD_PLAYLIST, null, false);
        }

        return $base_name;
    }

    /**
     * Clear downloaded playlist
     * @param string|null $playlist_id
     * @return void
     */
    public function clear_playlist_cache($playlist_id)
    {
        hd_debug_print(null, true);
        if ($playlist_id === null) {
            delete_directory(self::get_playlist_cache_path());
            return;
        }

        $this->iptv_m3u_parser->clear_data();
        $tmp_file = self::get_playlist_cache_path() . "{$playlist_id}_playlist.m3u8";
        if (file_exists($tmp_file)) {
            hd_debug_print("clear_playlist_cache: remove $tmp_file");
            unlink($tmp_file);
        }
    }

    ///////////////////////////////////////////////////////////////////////
    // Misc.

    public function update_ui_settings()
    {
        $this->picons_source = $this->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS);
        $this->default_channel_icon_classic = '';
        $this->default_channel_icon_newui = '';
    }

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

    public function get_icon($id)
    {
        $archive = $this->get_image_archive();

        return is_null($archive) ? null : $archive->get_archive_url($id);
    }

    /**
     * @param array $channel_row
     * @param bool $is_classic
     * @return string
     */
    public function get_channel_picon($channel_row, $is_classic)
    {
        // if selected xmltv or combined mode look into xmltv source
        // in combined mode search is not performed if already got picon from playlist
        do {
            if ($this->picons_source !== XMLTV_PICONS) {
                // playlist icons first in priority
                $icon_url = $channel_row[COLUMN_ICON];
            }

            if ($this->picons_source !== XMLTV_PICONS && ($this->picons_source !== COMBINED_PICONS || !empty($icon_url))) break;
            if (empty($this->epg_manager)) break;

            if (!empty($channel_row[COLUMN_TITLE])) {
                $picon_ids[] = mb_convert_case($channel_row[COLUMN_TITLE], MB_CASE_LOWER, "UTF-8");
            }

            if (!empty($aliases[ATTR_TVG_NAME])) {
                $picon_ids[] = mb_convert_case($aliases[ATTR_TVG_NAME], MB_CASE_LOWER, "UTF-8");
            }

            if (!empty($channel_row[COLUMN_EPG_ID])) {
                $picon_ids[] = $channel_row[COLUMN_EPG_ID];
            }

            if (!empty($channel_row[COLUMN_CHANNEL_ID])) {
                $picon_ids[] = $channel_row[COLUMN_CHANNEL_ID];
            }

            if (empty($picon_ids)) break;

            $placeHolders = Sql_Wrapper::sql_make_list_from_values(array_unique($picon_ids));
            foreach (Epg_Manager_Xmltv::get_sources() as $key => $params) {
                if (Epg_Manager_Xmltv::is_index_locked($key, INDEXING_DOWNLOAD | INDEXING_CHANNELS)) continue;

                $icon_url = Epg_Manager_Xmltv::get_picon($key, $placeHolders);
                if (!empty($icon_url)) break;
            }
        } while (false);

        return empty($icon_url) ? $this->get_default_channel_icon($is_classic) : $icon_url;
    }

    public function get_image_archive()
    {
        return Default_Archive::get_image_archive(self::ARCHIVE_ID, self::ARCHIVE_URL_PREFIX);
    }

    /**
     * Generate base name like 'edem_a1bde_orders_custom' or edem_a1bde_tv_history
     * where edem_a1bde - is playlist id, orders/tv_history - storage name,
     * custom - provider playlist id. for default provider playlist id value is omitted
     *
     * @param string $storage_name
     * @param string|null $playlist_id
     * @param bool $use_provider_playlist
     * @return string
     */
    public function make_base_name($storage_name, $playlist_id = null, $use_provider_playlist = true)
    {
        if (!empty($storage_name)) {
            $storage_name = "_$storage_name";
        }

        if (empty($playlist_id)) {
            $playlist_id = $this->get_active_playlist_id();
        }

        $provider_playlist_id = $use_provider_playlist ? $this->get_playlist_parameter($playlist_id, PARAM_PLAYLIST_IPTV_ID) : '';
        $provider_playlist_id = empty($provider_playlist_id) || $provider_playlist_id === PARAM_DEFAULT_CONFIG_PLAYLIST_ID ? '' : "_$provider_playlist_id";

        return $playlist_id . $storage_name . $provider_playlist_id;
    }

    /**
     * @param string $id
     * @return string
     */
    public static function get_group_media_url_str($id)
    {
        switch ($id) {
            case TV_FAV_GROUP_ID:
                return Starnet_Tv_Favorites_Screen::make_group_media_url_str(TV_FAV_GROUP_ID);

            case TV_HISTORY_GROUP_ID:
                return Starnet_Tv_History_Screen::make_group_media_url_str(TV_HISTORY_GROUP_ID);

            case TV_CHANGED_CHANNELS_GROUP_ID:
                return Starnet_Tv_Changed_Channels_Screen::make_group_media_url_str(TV_CHANGED_CHANNELS_GROUP_ID);

            case VOD_GROUP_ID:
                return Starnet_Vod_Category_List_Screen::make_group_media_url_str(VOD_GROUP_ID);

            case VOD_FAV_GROUP_ID:
                return Starnet_Vod_Favorites_Screen::make_group_media_url_str(VOD_FAV_GROUP_ID);

            case VOD_HISTORY_GROUP_ID:
                return Starnet_Vod_History_Screen::make_group_media_url_str(VOD_HISTORY_GROUP_ID);

            case VOD_SEARCH_GROUP_ID:
                return Starnet_Vod_Search_Screen::make_group_media_url_str(VOD_SEARCH_GROUP_ID);

            case VOD_FILTER_GROUP_ID:
                return Starnet_Vod_Filter_Screen::make_group_media_url_str();

            case VOD_LIST_GROUP_ID:
                return Starnet_Vod_List_Screen::make_group_media_url_str(VOD_LIST_GROUP_ID);
        }

        return Starnet_Tv_Channel_List_Screen::make_group_media_url_str($id);
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
        if (!is_null($provider) && !$this->use_xmltv) {
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
        $menu_items[] = $this->create_menu_item($handler,
            ENGINE_XMLTV, TR::t('setup_epg_cache_xmltv'),
            $this->use_xmltv ? "check.png" : null
        );

        $provider = $this->get_active_provider();
        if ($provider !== null) {
            $epg_presets = $provider->getConfigValue(EPG_JSON_PRESETS);
            if (count($epg_presets) != 1) {
                $engine = TR::t('setup_epg_cache_json');
            } else {
                $preset = $this->get_setting(PARAM_EPG_JSON_PRESET, 0);
                $name = safe_get_value($epg_presets[$preset], 'title', $epg_presets[$preset]['name']);
                $engine = TR::t('setup_epg_cache_json__1', $name);
            }

            $menu_items[] = $this->create_menu_item($handler, ENGINE_JSON, $engine, $this->use_xmltv ? null : "check.png");
        }
        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function refresh_playlist_menu($handler)
    {
        $icon_file = "refresh.png";
        $playlist_parameters = $this->get_playlist_parameters($this->get_active_playlist_id());
        $title = safe_get_value($playlist_parameters, PARAM_NAME);
        if (safe_get_value($playlist_parameters, PARAM_TYPE) === PARAM_PROVIDER) {
            $provider = $this->create_provider_class(safe_get_value($playlist_parameters, PARAM_PROVIDER));
            if (!is_null($provider)) {
                if ($title !== $provider->getName()) {
                    $title .= " ({$provider->getName()})";
                }
                $icon_file = $provider->getLogo();
            }

            $playlists = $provider->GetPlaylistsIptv();
            $provider_playlist_id = safe_get_value($playlist_parameters, PARAM_PLAYLIST_IPTV_ID);
            if ($provider_playlist_id !== PARAM_DEFAULT_CONFIG_PLAYLIST_ID) {
                $title .= " - {$playlists[$provider_playlist_id][COLUMN_NAME]}";
            }
        }

        $title = TR::t('playlist_name_msg__1', $title);
        $menu_items[] = $this->create_menu_item($handler, ACTION_RELOAD, $title, $icon_file, array('clear_playlist' => true));
        $menu_items[] = $this->create_menu_item($handler,
            ACTION_ITEMS_EDIT,
            TR::t('setup_channels_src_edit_playlists'),
            "m3u_file.png",
            array(CONTROL_ACTION_EDIT => Starnet_Edit_Playlists_Screen::SCREEN_EDIT_PLAYLIST));
        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
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
        hd_debug_print(null, true);
        hd_debug_print("group: $group_id, is classic: " . var_export($is_classic, true), true);

        $fav_id = $this->get_fav_id();
        $menu_items = array();
        if ($group_id !== null) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");
            $menu_items[] = $this->create_menu_item($handler, ACTION_SORT_POPUP, TR::t('sort_popup_menu'), "sort.png");
            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

            if (!$is_classic) {
                if ($group_id === $fav_id && $this->get_order_count($fav_id)) {
                    $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "brush.png");
                }
            }
            if ($group_id === TV_HISTORY_GROUP_ID && $this->get_tv_history_count() !== 0) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_history'), "brush.png");
            } else if ($group_id === TV_CHANGED_CHANNELS_GROUP_ID) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_changed'), "brush.png");
            }

            $menu_items = array_merge($menu_items, $this->edit_hidden_menu($handler, $group_id));
            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        }

        if ($is_classic) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_CHANGE_GROUP_ICON, TR::t('change_group_icon'), "image.png");
        }
        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

        $menu_items[] = $this->create_menu_item($handler,
            ACTION_ITEMS_EDIT,
            TR::t('setup_edit_xmltv_list'),
            "epg.png",
            array(CONTROL_ACTION_EDIT => Starnet_Edit_Xmltv_List_Screen::SCREEN_EDIT_XMLTV_LIST));

        $provider = $this->get_active_provider();
        if (!is_null($provider)) {
            $epg_presets = $provider->getConfigValue(EPG_JSON_PRESETS);
            $preset_cnt = count($epg_presets);
            if ($preset_cnt) {
                $menu_items[] = $this->create_menu_item($handler,
                    ACTION_EPG_CACHE_ENGINE, TR::t('setup_epg_cache_engine__1',
                        TR::t($this->use_xmltv ? 'setup_epg_cache_xmltv' : 'setup_epg_cache_json')),
                    "engine.png");

                if (!$this->use_xmltv && $preset_cnt > 1) {
                    $preset = $this->get_setting(PARAM_EPG_JSON_PRESET, 0);
                    $name = safe_get_value($epg_presets[$preset], 'title', $epg_presets[$preset]['name']);
                    $menu_items[] = $this->create_menu_item($handler,
                        ACTION_CHANGE_EPG_SOURCE,
                        TR::t('change_json_epg_source__1', $name),
                        "epg.png");
                }
            }

            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            if ($provider->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_INFO_DLG, TR::t('subscription'), "info.png");
            }
        }

        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->create_menu_item($handler,
            ACTION_EDIT_PLAYLIST_SETTINGS,
            TR::t('setup_playlist'),
            "playlist_settings.png");
        $menu_items[] = $this->create_menu_item($handler, ACTION_PLUGIN_SETTINGS, TR::t('entry_setup'), "settings.png");

        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $group_id
     * @param bool $groups
     * @return array
     */
    public function edit_hidden_menu($handler, $group_id, $groups = true)
    {
        $menu_items = array();

        if ($group_id === null) {
            return $menu_items;
        }

        if ($groups) {
            if (!self::is_special_group_id($group_id)) {
                $menu_items[] = $this->create_menu_item($handler,
                    ACTION_ITEM_DELETE,
                    TR::t('tv_screen_hide_group'),
                    "hide.png");
            }

            $cnt = $this->get_groups_count(PARAM_GROUP_ORDINARY, PARAM_DISABLED);
            hd_debug_print("Disabled groups: $cnt", true);
            if ($cnt) {
                $menu_items[] = $this->create_menu_item($handler,
                    ACTION_ITEMS_EDIT,
                    TR::t('tv_screen_edit_hidden_group'),
                    "edit.png",
                    array(CONTROL_ACTION_EDIT => Starnet_Edit_Hidden_List_Screen::PARAM_HIDDEN_GROUPS));
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

        $cnt = $this->get_channels_count($group_id, PARAM_DISABLED);
        hd_debug_print("Disabled channels in $group_id: $cnt", true);
        if ($cnt !== 0) {
            $menu_items[] = $this->create_menu_item($handler,
                ACTION_ITEMS_EDIT,
                TR::t('tv_screen_edit_hidden_channels'),
                "edit.png",
                array(CONTROL_ACTION_EDIT => Starnet_Edit_Hidden_List_Screen::PARAM_HIDDEN_CHANNELS));
        }

        return $menu_items;
    }

    ///////////////////////////////////////////////////////////////////////
    // Dialogs and screens

    public function get_plugin_title()
    {
        return $this->plugin_info['app_caption'];
    }

    /**
     * @param string $source_screen_id
     * @param string $action_edit
     * @param MediaURL|null $media_url
     * @param array|null $post_action
     * @return array|null
     */
    public function do_edit_list_screen($source_screen_id, $action_edit, $media_url = null, $post_action = null)
    {
        switch ($action_edit) {
            case Starnet_Edit_Hidden_List_Screen::PARAM_HIDDEN_CHANNELS:
                $params = array(
                    PARAM_END_ACTION => ACTION_INVALIDATE,
                    PARAM_CANCEL_ACTION => ACTION_EMPTY,
                    Starnet_Edit_Hidden_List_Screen::PARAM_EDIT_LIST => $action_edit
                );

                if (!is_null($media_url) && isset($media_url->group_id)) {
                    $params['group_id'] = $media_url->group_id;
                }
                $new_media_url_str = Starnet_Edit_Hidden_List_Screen::make_callback_media_url_str($source_screen_id, $params);
                $title = TR::t('tv_screen_edit_hidden_channels');
                break;

            case Starnet_Edit_Hidden_List_Screen::PARAM_HIDDEN_GROUPS:
                $new_media_url_str = Starnet_Edit_Hidden_List_Screen::make_callback_media_url_str($source_screen_id,
                    array(
                        PARAM_END_ACTION => ACTION_INVALIDATE,
                        PARAM_CANCEL_ACTION => ACTION_EMPTY,
                        Starnet_Edit_Hidden_List_Screen::PARAM_EDIT_LIST => $action_edit
                    )
                );
                $title = TR::t('tv_screen_edit_hidden_group');
                break;

            case Starnet_Edit_Playlists_Screen::SCREEN_EDIT_PLAYLIST:
                $new_media_url_str = Starnet_Edit_Playlists_Screen::make_callback_media_url_str($source_screen_id,
                    array(
                        PARAM_END_ACTION => ACTION_RELOAD,
                        PARAM_CANCEL_ACTION => RESET_CONTROLS_ACTION_ID,
                        PARAM_EXTENSION => PLAYLIST_PATTERN
                    )
                );
                $title = TR::t('setup_channels_src_edit_playlists');
                break;

            case Starnet_Edit_Xmltv_List_Screen::SCREEN_EDIT_XMLTV_LIST:
                $new_media_url_str = Starnet_Edit_Xmltv_List_Screen::make_callback_media_url_str($source_screen_id,
                    array(
                        PARAM_END_ACTION => ACTION_RELOAD,
                        PARAM_CANCEL_ACTION => RESET_CONTROLS_ACTION_ID,
                    )
                );
                $title = TR::t('setup_edit_xmltv_list');
                break;

            default:
                return null;
        }

        return Action_Factory::open_folder($new_media_url_str, $title, null, null, $post_action);
    }

    /**
     * @param string $channel_id
     * @param bool $is_classic
     * @return array|null
     */
    public function do_show_channel_info($channel_id, $is_classic)
    {
        $channel_row = $this->get_channel_info($channel_id);
        if (empty($channel_row)) {
            return null;
        }

        $provider = $this->get_active_provider();

        if (!is_null($provider) && $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === ENGINE_JSON) {
            $day_start_ts = from_local_time_zone_offset(strtotime(date("Y-m-d")));
            $epg_url = $this->get_epg_manager()->get_epg_url($provider, $channel_row, $day_start_ts, $epg_id, $preset);
        } else {
            $epg_id = implode(', ', array_unique(array_filter(self::make_epg_ids($channel_row))));
        }
        $defs = array();

        Control_Factory::add_vgap($defs, -20);
        self::format_smart_label($defs, TR::load('number'), $channel_row[COLUMN_CH_NUMBER]);
        self::format_smart_label($defs, "ID:", $channel_row[COLUMN_CHANNEL_ID]);
        self::format_smart_label($defs, TR::load('name'), $channel_row[COLUMN_TITLE]);
        self::format_smart_label($defs, TR::load('group'), $channel_row[COLUMN_GROUP_ID]);
        self::format_smart_label($defs, TR::load('archive'), $channel_row[COLUMN_ARCHIVE] . ' ' . TR::load('days'));
        self::format_smart_label($defs, TR::load('adult'), $channel_row[COLUMN_ADULT] ? TR::load('yes') : TR::load('no'));
        self::format_smart_label($defs, "EPG IDs:", $epg_id);

        if ($channel_row[COLUMN_TIMESHIFT] != 0) {
            self::format_smart_label($defs, TR::load('time_shift'), $channel_row[COLUMN_TIMESHIFT] . ' ' . TR::load('hours'));
        }
        if ($channel_row[COLUMN_EPG_SHIFT] != 0) {
            $epg_shift = format_duration_minutes((int)$channel_row[COLUMN_EPG_SHIFT]);
            self::format_smart_label($defs, TR::load('setup_epg_shift'), $epg_shift . ' ' . TR::load('hours'));
        }
        Control_Factory::add_vgap($defs, 30);

        $icon = $this->get_channel_picon($channel_row, $is_classic);
        self::format_smart_label($defs, TR::load('icon'), $icon);

        if (!empty($epg_url)) {
            self::format_smart_label($defs, TR::load('epg_url'), $epg_url);
        }

        try {
            $live_url = $this->generate_stream_url($channel_row, -1, true);
            $live_url = htmlspecialchars($live_url);
            self::format_smart_label($defs, TR::load('live_url'), $live_url);
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        if ($channel_row[COLUMN_ARCHIVE] > 0) {
            try {
                $archive_url = $this->generate_stream_url($channel_row, time() - 3600, true);
                $archive_url = htmlspecialchars($archive_url);
                self::format_smart_label($defs, TR::load('archive_url'), $archive_url);
            } catch (Exception $ex) {
                print_backtrace_exception($ex);
            }
        }

        $ext_params = safe_get_value($channel_row, COLUMN_EXT_PARAMS);
        $dune_params = $this->generate_dune_params($channel_id, json_decode($ext_params, true));
        if (!empty($dune_params)) {
            Control_Factory::add_vgap($defs, 30);
            self::format_smart_label($defs, "dune_params:", $dune_params);
        }

        if (!empty($live_url) && !is_limited_apk()) {
            $descriptors = array(
                0 => array("pipe", "r"), // stdin
                1 => array("pipe", "w"), // sdout
                2 => array("pipe", "w"), // stderr
            );

            hd_debug_print("Get media info for: $live_url");
            /** @var array $pipes */
            $process = proc_open(
                get_install_path("bin/media_check.sh $live_url"),
                $descriptors,
                $pipes);

            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]);

                fclose($pipes[1]);
                proc_close($process);

                Control_Factory::add_vgap($defs, 30);
                foreach (explode("\n", $output) as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    if (strpos($line, "Output") !== false) break;
                    if (strpos($line, "Stream") !== false) {
                        $line = substr($line, 7);
                        $line = preg_replace("/ \(\[.*\)| \[.*]|, [0-9k.]+ tb[rcn]|, q=[0-9\-]+/", "", $line);
                        self::format_smart_label($defs, TR::load('stream'), $line);
                    }
                }
            }
        }

        Control_Factory::add_vgap($defs, 20);
        Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 250, true);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('channel_info_dlg'), $defs, true, 1750);
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
     * @return Hashed_Array<array>
     */
    public function get_active_sources()
    {
        hd_debug_print(null, true);

        $all_sources = $this->get_xmltv_sources(XMLTV_SOURCE_ALL, $this->get_active_playlist_id());
        $selected_sources = $this->get_selected_xmltv_ids();
        $active_sources = new Hashed_Array();
        foreach ($selected_sources as $key) {
            $item = $all_sources->get($key);
            if ($item === null) continue;

            $item[PARAM_HASH] = Hashed_Array::hash($item[PARAM_URI]);
            $active_sources->set($key, $item);
        }

        return $active_sources;
    }

    public function cleanup_stalled_locks()
    {
        hd_debug_print(null, true);
        $locks = Epg_Manager_Xmltv::get_any_index_locked();
        if ($locks === false) {
            return;
        }

        foreach ($locks as $lock) {
            hd_debug_print("Found stalled lock: $lock");

            $ar = explode('_', $lock);
            $pid = (int)end($ar);

            if ($pid !== 0 && !send_process_signal($pid, 0)) {
                hd_debug_print("Remove stalled lock: $lock");
                shell_exec("rmdir {$this->get_cache_dir()}" . '/' . $lock);
            }
        }
    }

    public function cleanup_active_xmltv_source()
    {
        $playlist_id = $this->get_active_playlist_id();
        $playlist_sources = $this->get_xmltv_sources_hash(XMLTV_SOURCE_PLAYLIST, $playlist_id);
        $ext_sources = $this->get_xmltv_sources_hash(XMLTV_SOURCE_EXTERNAL, null);
        $all_sources = array_unique(array_merge($playlist_sources, $ext_sources));
        hd_debug_print("Load All XMLTV sources keys: " . json_encode($all_sources), true);

        $cur_sources = $this->get_selected_xmltv_ids();
        hd_debug_print("Load selected XMLTV sources keys: " . json_encode($cur_sources), true);

        // remove non-existing values from selected sources
        $removed_source = array_diff($cur_sources, $all_sources);
        if (!empty($removed_source)) {
            hd_debug_print("Removed source: " . json_encode($removed_source));
            foreach ($removed_source as $source) {
                $this->remove_selected_xmltv_id($source);
            }
        }
    }

    /**
     * @param bool $is_tv
     * @return bool
     */
    public function is_playlist_cache_expired($is_tv)
    {
        $cache_time = $is_tv ? PARAM_PLAYLIST_CACHE_TIME_IPTV : PARAM_PLAYLIST_CACHE_TIME_VOD;
        if ($this->get_setting($cache_time, 1) === PHP_INT_MAX) {
            return false;
        }

        $base_name = $this->get_playlist_cache_filepath($is_tv);
        $m3u_file = $base_name . '.m3u8';
        $db_file = $base_name . '.db';

        if (!file_exists($m3u_file)) {
            hd_debug_print("Playlist cache '$m3u_file' is not exist");
            return true;
        }

        if (!file_exists($db_file)) {
            hd_debug_print("Database '$db_file' is not exist");
            return true;
        }

        $now = time();
        $mtime = filemtime($m3u_file);
        $cache_expired = $mtime + $this->get_setting($cache_time, 1) * 3600;
        if ($cache_expired > $now) {
            return false;
        }

        hd_debug_print("Playlist cache $m3u_file expired " . ($now - $cache_expired) . " sec ago. Timestamp $mtime. Forcing reload");
        return true;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///

    /**
     * @param User_Input_Handler $handler
     * @param $plugin_cookies
     * @return array
     */
    public function new_search($handler, $plugin_cookies)
    {
        $search_text = safe_get_member($plugin_cookies, PARAM_COOKIE_LAST_TV_SEARCH, '');

        $defs = array();
        Control_Factory::add_text_field($defs, $handler, null, ACTION_NEW_SEARCH, '', $search_text,
            false, false, true, true, 1300, false, true);
        Control_Factory::add_vgap($defs, 500);
        return Action_Factory::show_dialog(TR::t('tv_screen_search_channel'), $defs, true, 1300);
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $search_text
     * @return array
     */
    public function do_search($handler, $search_text, &$plugin_cookies)
    {
        if (empty($search_text)) {
            return null;
        }

        hd_debug_print("Do search channel name : '$search_text'", true);
        $plugin_cookies->{PARAM_COOKIE_LAST_TV_SEARCH} = $search_text;

        $groups_order = $this->get_groups_by_order();
        $show_adult = $this->get_bool_setting(PARAM_SHOW_ADULT);

        $defs = array();
        $q_result = false;
        foreach ($groups_order as $group_row) {
            if ($group_row[COLUMN_ADULT] && !$show_adult) continue;

            $channels_rows = $this->get_channels_by_order($group_row[COLUMN_GROUP_ID]);
            foreach ($channels_rows as $channel_row) {
                if (!$show_adult && $channel_row[COLUMN_ADULT] !== 0) continue;

                $ch_title = $channel_row[COLUMN_TITLE];
                $s = mb_stripos($ch_title, $search_text, 0, "UTF-8");
                if ($s !== false) {
                    $ch_id = $channel_row[COLUMN_CHANNEL_ID];
                    $q_result = true;
                    hd_debug_print("found channel: '$ch_title', id: $ch_id in group: '{$group_row[COLUMN_GROUP_ID]}'", true);
                    $add_params[COLUMN_CHANNEL_ID] = $ch_id;
                    Control_Factory::add_close_dialog_and_apply_button($defs, $handler, ACTION_JUMP_TO_CHANNEL_IN_GROUP,
                        $ch_title, 900, $add_params);
                }
            }
        }

        if ($q_result === false) {
            Control_Factory::add_multiline_label($defs, '', TR::t('tv_screen_not_found'), 6);
            Control_Factory::add_vgap($defs, 20);
            Control_Factory::add_close_dialog_and_apply_button($defs, $handler, ACTION_SHOW_SEARCH_DLG,
                '', TR::t('new_search'), 300);
        }

        return Action_Factory::show_dialog(TR::t('search'), $defs, true);
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
        foreach ($this->get_channels($group_id, PARAM_ENABLED) as $item) {
            if ($is_regex) {
                $add = preg_match("#$pattern#", $item[COLUMN_TITLE]);
            } else {
                $add = stripos($item[COLUMN_TITLE], $pattern) !== false;
            }

            if ($add) {
                $disabled_ids[] = $item[COLUMN_CHANNEL_ID];
            }
        }

        $cnt = count($disabled_ids);
        if ($cnt !== 0) {
            $this->set_channel_visible($disabled_ids, false);
            hd_debug_print("Total channels hidden: $cnt from groups: " . Sql_Wrapper::sql_make_list_from_keys($groups));
        }

        return $cnt;
    }

    /**
     * disable channels if HD vairant is present
     *
     * @param string $group_id
     * @return int
     */
    public function hide_sd_channels($group_id)
    {
        $disabled_ids = array();
        $groups = array();
        $rows = $this->get_channels($group_id, PARAM_ENABLED);
        usort($rows, function ($a, $b) {
            if ($a[COLUMN_TITLE] == $b[COLUMN_TITLE]) {
                return 0;
            }
            return ($a[COLUMN_TITLE] < $b[COLUMN_TITLE]) ? -1 : 1;
        });
        $cnt = count($rows);
        for($i = 0; $i < $cnt; $i++) {
            if (preg_match("#\sHD|\sFHD#", $rows[$i][COLUMN_TITLE])) continue;

            for ($j = $i + 1; $j < $cnt; $j++) {
                $len = strlen($rows[$i][COLUMN_TITLE]);
                if (strncasecmp($rows[$i][COLUMN_TITLE], $rows[$j][COLUMN_TITLE], $len) !== 0) break;

                $add = preg_match("#^\sHD|\sFHD#", substr($rows[$j][COLUMN_TITLE], $len));
                if ($add) {
                    $disabled_ids[] = $rows[$i][COLUMN_CHANNEL_ID];
                    $i = $j;
                    break;
                }
            }
        }

        $cnt = count($disabled_ids);
        if ($cnt !== 0) {
            $this->set_channel_visible($disabled_ids, false);
            hd_debug_print("Total channels hidden: $cnt from groups: " . Sql_Wrapper::sql_make_list_from_keys($groups));
        }

        return $cnt;
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

        if ($remove_playlist) {
            $tables = array(self::PLAYLISTS_TABLE, self::PLAYLIST_PARAMETERS_TABLE, self::PLAYLIST_XMLTV_TABLE, self::SELECTED_XMLTV_TABLE);
            $query = '';
            foreach ($tables as $table) {
                $query .= "DELETE FROM $table WHERE playlist_id = '$playlist_id';";
            }
            $this->sql_params->exec($query);
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

    public function is_full_size_remote()
    {
        return !is_limited_apk() || $this->get_bool_parameter(PARAM_FULL_SIZE_REMOTE, false);
    }

    /**
     * @return array
     */
    public static function get_id_detect_mapper()
    {
        return array(
            CONTROL_DETECT_ID => TR::load('detect'),
            ATTR_CUID => TR::load('attribute_name__1', ATTR_CHANNEL_ID),
            ATTR_TVG_ID => TR::load('attribute_name__1', ATTR_TVG_ID),
            ATTR_TVG_NAME => TR::load('attribute_name__1', ATTR_TVG_NAME),
            ATTR_CHANNEL_NAME => TR::load('channel_name'),
            ATTR_CHANNEL_HASH => TR::load('hash_url')
        );
    }

    /**
     * @param string $filename
     * @return array
     * @throws Exception
     */
    public function collect_detect_info($filename)
    {
        $parser = new M3uParser();
        $parser->setPlaylistFile($filename, true);

        $db = new Sql_Wrapper(':memory:');
        if (!$db->is_valid()) {
            throw new Exception("Unable to create database");
        }

        $database_attached = $db->attachDatabase(':memory:', M3uParser::IPTV_DB);
        if ($database_attached === 0) {
            throw new Exception("Can't attach to database: " . M3uParser::IPTV_DB);
        }

        $entries_cnt = $parser->parseIptvPlaylist($db);
        if (!$entries_cnt) {
            throw new Exception(TR::load('err_empty_playlist'));
        }

        $table_name = M3uParser::CHANNELS_TABLE;
        $entries_cnt = (int)$db->query_value("SELECT COUNT(*) FROM $table_name;");

        $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();
        $stat = M3uParser::detectBestChannelId($db);

        $detect_info = TR::load('channels__1', $entries_cnt) . PHP_EOL;
        $max_dupes = $entries_cnt + 1;
        foreach ($mapper_ops as $key => $value) {
            if ($key === CONTROL_DETECT_ID) continue;
            if (!isset($stat[$key])) {
                $detect_info .= TR::load('duplicates__1', $value) . PHP_EOL;
                continue;
            }

            $detect_info .= TR::load('duplicates__2', $value, $stat[$key]) . PHP_EOL;
            if ($stat[$key] < $max_dupes) {
                $max_dupes = $stat[$key];
                $minkey = $key;
            }
        }

        $minkey =  empty($minkey) ? ATTR_CHANNEL_HASH : $minkey;
        hd_debug_print("Best ID: $minkey");
        $detect_info .= PHP_EOL . TR::load('selected__1', $mapper_ops[$minkey]) . PHP_EOL;

        return array($minkey, $detect_info);
    }

    public static function make_epg_ids($channel_row)
    {
        return array('epg_id' => $channel_row[COLUMN_EPG_ID], 'id' => $channel_row[COLUMN_CHANNEL_ID], 'name' => $channel_row[COLUMN_TITLE]);
    }

    public static function is_special_group_id($group_id)
    {
        return ($group_id === TV_ALL_CHANNELS_GROUP_ID
            || $group_id === TV_FAV_GROUP_ID
            || $group_id === TV_FAV_COMMON_GROUP_ID
            || $group_id === TV_HISTORY_GROUP_ID
            || $group_id === TV_CHANGED_CHANNELS_GROUP_ID
            || $group_id === VOD_GROUP_ID);
    }

    public function get_import_xmltv_logs_actions($xmltv_ids, $actions, $plugin_cookies, $post_action = null)
    {
        $res = Epg_Manager_Xmltv::import_indexing_log($xmltv_ids);

        if ($res === -1 || $res === -2) {
            return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_load_xmltv_source'),
                Dune_Last_Error::get_last_error(LAST_ERROR_XMLTV));
        }

        if ($res === 0) {
            hd_debug_print("No imports. Timer stopped");
            return null;
        }

        if ($res === 1) {
            hd_debug_print("Logs imported. Timer stopped");
            return Action_Factory::invalidate_all_folders($plugin_cookies, null, $post_action);
        }

        if ($res === 2) {
            return Action_Factory::change_behaviour($actions, 1000, $post_action);
        }

        return null;
    }

    /**
     * Called when first start url
     *
     * @param string $channel_id
     * @param int $archive_ts
     */
    public function push_tv_history($channel_id, $archive_ts)
    {
        hd_debug_print(null, true);
        $player_state = get_player_state_assoc();
        $state = safe_get_value($player_state, PLAYER_STATE);
        $event = safe_get_value($player_state, LAST_PLAYBACK_EVENT);
        if ($state !== null && $state !== PLAYER_STATE_NAVIGATOR && $event !== PLAYBACK_PCR_DISCONTINUITY) {
            hd_debug_print("Push history for channel_id $channel_id at time mark: $archive_ts", true);
            $this->playback_points[$channel_id] = $archive_ts;
        }
    }

    /**
     * Called when playing stop
     *
     * @param string|null $id
     */
    public function update_tv_history($id)
    {
        hd_debug_print(null, true);
        if ($id === null || !isset($this->playback_points[$id])) {
            return;
        }

        // update point for selected channel
        $player_state = get_player_state_assoc();
        $state = safe_get_value($player_state, PLAYBACK_STATE);
        $position = safe_get_value($player_state, PLAYBACK_POSITION, 0);
        if ($state !== PLAYBACK_PLAYING && $state !== PLAYBACK_STOPPED) {
            return;
        }

        if ($this->playback_points[$id] !== 0) {
            $archive_ts = $this->playback_points[$id] + $position;
        } else {
            $archive_ts = 0;
        }

        $list = array(COLUMN_CHANNEL_ID => $id, COLUMN_TIMESTAMP => $archive_ts, COLUMN_TIME_START => -1, COLUMN_TIME_END => -1);

        if ($archive_ts !== 0) {
            $prog_info = $this->get_epg_info($id, $archive_ts);
            if (isset($prog_info[PluginTvEpgProgram::start_tm_sec])) {
                $list[COLUMN_TIME_START] = $prog_info[PluginTvEpgProgram::start_tm_sec];
                $list[COLUMN_TIME_END] = $prog_info[PluginTvEpgProgram::end_tm_sec];
            }
        }

        $table_name = self::get_table_name(TV_HISTORY);
        $q_id = Sql_Wrapper::sql_quote($id);
        $insert = Sql_Wrapper::sql_make_insert_list($list);
        $q_update = Sql_Wrapper::sql_make_set_list($list);
        $query = "INSERT OR IGNORE INTO $table_name $insert;";
        $query .= "UPDATE $table_name SET $q_update WHERE channel_id = $q_id;";
        $query .= "DELETE FROM $table_name WHERE ROWID NOT IN (SELECT rowid FROM $table_name ORDER BY time_stamp DESC LIMIT 7);";
        $this->sql_playlist->exec_transaction($query);

        unset($this->playback_points[$id]);

        hd_debug_print("Save history for channel_id $id at time mark: $archive_ts", true);
    }

    public static function send_log_to_developer($plugin, &$error = null)
    {
        $serial = get_serial_number();
        if (empty($serial)) {
            hd_debug_print("Unable to get DUNE serial.");
            $serial = 'XX-XX-XX-XX-XX';
        }
        $ver = $plugin->plugin_info['app_version'];
        $ver = str_replace('.', '_', $ver);
        $timestamp = format_datetime('Ymd_His', time());
        $model = get_product_id();
        $zip_file_name = "proiptv_{$ver}_{$model}_{$serial}_$timestamp.zip";
        hd_debug_print("Prepare archive $zip_file_name for send");
        $zip_file = get_temp_path($zip_file_name);
        $apk_subst = getenv('FS_PREFIX');
        $plugin_name = get_plugin_name();

        $paths = array(
            get_temp_path("*.txt"),
            get_temp_path("*.log"),
            get_temp_path("*.m3u8"),
            get_temp_path("*.m3u"),
            "$apk_subst/tmp/run/shell.log",
            "$apk_subst/tmp/run/shell.log.old",
        );

        if (file_exists("$apk_subst/D/dune_plugin_logs/$plugin_name.log")) {
            $paths[] = "$apk_subst/D/dune_plugin_logs/$plugin_name.*";
        }
        if (file_exists("$apk_subst/tmp/mnt/D/dune_plugin_logs/$plugin_name.log")) {
            $paths[] = "$apk_subst/tmp/mnt/D/dune_plugin_logs/$plugin_name.*";
        }
        if (file_exists("$apk_subst/tmp/run/$plugin_name.log")) {
            $paths[] = "$apk_subst/tmp/run/$plugin_name.*";
        }

        $plugin_backup = self::do_backup_settings($plugin, get_temp_path(), false);
        if ($plugin_backup === false) {
            $paths[] = get_data_path("*.settings");
        } else {
            $paths[] = $plugin_backup;
        }

        $files = array();
        foreach ($paths as $path) {
            foreach (glob($path) as $file) {
                if (is_file($file) && filesize($file) > 0) {
                    $files[] = $file;
                }
            }
        }

        $handle = false;
        $ret = false;
        try {
            $zip = new ZipArchive();
            $zip->open($zip_file, ZipArchive::CREATE);
            foreach ($files as $key => $file) {
                $zip->addFile($file, "/$key." . basename($file));
            }
            $zip->close();

            $url = base64_decode("aHR0cDovL2lwdHYuZXNhbGVjcm0ubmV0L3VwbG9hZC8", true) . $zip_file_name;
            $handle = fopen($zip_file, 'rb');
            if (is_resource($handle)) {
                $wrapper = Curl_Wrapper::getInstance();
                $wrapper->set_options(array(CURLOPT_INFILE => $handle, CURLOPT_INFILESIZE => filesize($zip_file)));
                $wrapper->set_send_headers(array("accept: */*", "Expect: 100-continue", "Content-Type: application/zip"));
                $content = $wrapper->download_content($url);

                $http_code = $wrapper->get_http_code();
                if ($content === false) {
                    $err_msg = "Fetch $url failed. HTTP error: $http_code ({$wrapper->get_error_no()})";
                    hd_debug_print($err_msg);
                    return false;
                }

                $http_code_str = HD::http_status_code_to_string($http_code);
                if ($http_code >= 400) {
                    $err_msg = "Fetch $url failed. HTTP request failed ($http_code): $http_code_str";
                    hd_debug_print($err_msg);
                    return false;
                }

                if ($http_code >= 300) {
                    $err_msg = "Fetch $url completed, but ignored. HTTP request ($http_code): $http_code_str";
                    hd_debug_print($err_msg);
                }

                hd_debug_print("Log file sent");
                $ret = true;
            }
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            $msg = ": Unable to upload log: " . $ex->getMessage();
            if ($error !== null) {
                $error = $msg;
            }
        }

        if (is_resource($handle)) {
            @fclose($handle);
        }
        @unlink($zip_file);

        return $ret;
    }

    /**
     * @param Default_Dune_Plugin $plugin
     * @param string $folder_path
     * @return bool|string
     */
    public static function do_backup_settings($plugin, $folder_path, $complete = true)
    {
        $folder_path = get_paved_path($folder_path);

        hd_debug_print("Backup path: $folder_path");
        if ($complete) {
            $timestamp = format_datetime('Y-m-d_H-i', time());
            $zip_file_name = "proiptv_backup_{$plugin->plugin_info['app_version']}_$timestamp.zip";
        } else {
            $zip_file_name = "proiptv_backup.zip";
        }
        $zip_file = get_temp_path($zip_file_name);

        try {
            $zip = new ZipArchive();
            if (!$zip->open($zip_file, ZipArchive::CREATE)) {
                throw new Exception(TR::t("err_create_zip__1", $zip_file));
            }

            $rootPath = get_data_path();
            foreach (array("\.settings", "\.db") as $ext) {
                foreach (glob_dir($rootPath, "/$ext/i") as $full_path) {
                    if (file_exists($full_path)) {
                        $zip->addFile($full_path, basename($full_path));
                    }
                }
            }

            if ($complete) {
                $added_folders = array($rootPath . CACHED_IMAGE_SUBDIR, $rootPath . 'skin_backup');
                /** @var SplFileInfo[] $files */
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath),
                    RecursiveIteratorIterator::SELF_FIRST);

                foreach ($files as $file) {
                    if ($file->isDir()) continue;

                    $filePath = $file->getRealPath();
                    foreach ($added_folders as $folder) {
                        if (0 === strncmp($filePath, $folder, strlen($folder))) {
                            $relativePath = substr($filePath, strlen($rootPath));
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                }
            }

            if (!$zip->close()) {
                throw new Exception("Error create zip file: $zip_file " . $zip->getStatusString());
            }

            $backup_path = "$folder_path/$zip_file_name";
            if ($zip_file !== $backup_path && false === copy($zip_file, $backup_path)) {
                throw new Exception(TR::t('err_copy__2', $zip_file, $backup_path));
            }
        } catch (Exception $ex) {
            hd_debug_print(HD::get_storage_size(get_temp_path()));
            print_backtrace_exception($ex);
            return false;
        }

        clearstatcache();
        if ($zip_file !== $backup_path) {
            hd_print("unlink $zip_file");
            unlink($zip_file);
        }

        return $backup_path;
    }

    //////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param $playlist_id
     * @return Hashed_Array
     */
    protected function upgrade_settings($playlist_id)
    {
        $group_icons = new Hashed_Array();
        $settings_path = get_data_path("$playlist_id.settings");
        if (!file_exists($settings_path)) {
            return $group_icons;
        }

        hd_debug_print("Load (PLUGIN_SETTINGS): $playlist_id.settings");
        $plugin_settings = HD::get_items($settings_path, true, false);

        // convert old settings
        if (array_key_exists('cur_xmltv_sources', $plugin_settings)) {
            $active_sources = $plugin_settings['cur_xmltv_sources'];
            hd_debug_print("convert active sources from hashed array: " . $active_sources, true);
            $active_sources = $active_sources->get_keys();
            $plugin_settings[PARAM_SELECTED_XMLTV_SOURCES] = $active_sources;
            unset($plugin_settings['cur_xmltv_sources']);
        }

        if (array_key_exists('cur_xmltv_key', $plugin_settings)) {
            $plugin_settings[PARAM_SELECTED_XMLTV_SOURCES][] = $plugin_settings['cur_xmltv_key'];
            unset($plugin_settings['cur_xmltv_key']);
        }

        // Move old parameters show groups to settings
        $move_parameters = array(PARAM_SHOW_ALL, PARAM_SHOW_FAVORITES, PARAM_SHOW_HISTORY);
        foreach ($move_parameters as $parameter) {
            if (!array_key_exists($parameter, $plugin_settings)) {
                $plugin_settings[$parameter] = SwitchOnOff::to_def($this->get_bool_parameter($parameter));
            }
        }

        // remove obsolete settings
        $removed_settings = array('epg_cache_ttl', 'epg_cache_ttl', 'force_http', 'epg_cache_type', 'cur_xmltv_source');
        foreach ($removed_settings as $parameter) {
            if (array_key_exists($parameter, $plugin_settings)) {
                unset($plugin_settings[$parameter]);
            }
        }

        foreach ($plugin_settings as $key => $param) {
            hd_debug_print("$key => '" . (is_array($param) ? json_encode($param) : $param) . "'", true);
        }

        // Move settings to db
        $settings_table = self::SETTINGS_TABLE;
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
                $query .= "INSERT OR IGNORE INTO $settings_table (name, value, type) VALUES ($q_name, $q_value, $q_type);";
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
                $playlist_xmltv = self::PLAYLIST_XMLTV_TABLE;
                $query = '';
                /** @var Named_Storage $v */
                foreach ($value as $k => $v) {
                    $list = array(
                        COLUMN_PLAYLIST_ID => $playlist_id,
                        COLUMN_HASH => $k,
                        COLUMN_TYPE => (empty($v->type) ? PARAM_LINK : $v->type),
                        COLUMN_NAME => $v->name,
                        COLUMN_URI => $v->params[PARAM_URI],
                        COLUMN_CACHE => (isset($v->params[PARAM_CACHE]) ? $v->params[PARAM_CACHE] : XMLTV_CACHE_AUTO),
                    );
                    $insert = Sql_Wrapper::sql_make_insert_list($list);
                    $query .= "INSERT OR IGNORE INTO $playlist_xmltv $insert;";
                }
                $this->sql_params->exec_transaction($query);
                unset($plugin_settings[PARAM_EPG_PLAYLIST]);
            } else if ($key === PARAM_SELECTED_XMLTV_SOURCES) {
                hd_debug_print("Convert 'selected_xmltv_sources' to 'selected_xmltv' table");
                $selected_xmltv = self::SELECTED_XMLTV_TABLE;
                $query = '';
                foreach ($value as $hash) {
                    $query .= "INSERT OR IGNORE INTO $selected_xmltv (playlist_id, hash) VALUES ('$playlist_id', '$hash');";
                }
                $this->sql_params->exec_transaction($query);
                unset($plugin_settings[PARAM_SELECTED_XMLTV_SOURCES]);
            } else if ($key === 'channels_zoom' || $key === 'channel_player') {
                // obsolete
                unset($plugin_settings[$key]);
            } else if ($key === PARAM_DUNE_PARAMS) {
                hd_debug_print("Move 'dune_params' to playlist parameter");
                $dune_params_str = dune_params_array_to_string($value);
                if (!empty($dune_params_str)) {
                    $params[PARAM_DUNE_PARAMS] = $dune_params_str;
                    $this->set_playlist_parameters($playlist_id, $params);
                }
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

        $cookies_table = self::COOKIES_TABLE;
        $tokens = array(PARAM_TOKEN => "$playlist_id.token", PARAM_REFRESH_TOKEN => "$playlist_id.refresh_token", PARAM_SESSION_ID => "{$playlist_id}_session_id");
        foreach ($tokens as $key => $value) {
            $token_path = get_data_path("$playlist_id.$key");
            if (file_exists($token_path)) {
                hd_debug_print("Move '$key' to 'cookies' table");
                $time_stamp = filemtime($token_path);
                $q_value = Sql_Wrapper::sql_quote(file_get_contents($token_path));
                $query = "INSERT INTO $cookies_table (param, value, time_stamp) VALUES('$key', $q_value, $time_stamp);";
                $this->sql_playlist->exec($query);
                hd_debug_print("Remove cookie: $token_path");
                unlink($token_path);
            }
        }

        return $group_icons;
    }

    /**
     * @param string $playlist_id
     * @param Hashed_Array $group_icons
     * @return void
     */
    protected function upgrade_orders($playlist_id, $group_icons)
    {
        $plugin_orders_name = $this->make_base_name(PLUGIN_ORDERS, $playlist_id);
        $orders_file = get_data_path("$plugin_orders_name.settings");
        if (!file_exists($orders_file)) {
            return;
        }

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

        $groups_info_table = self::get_table_name(GROUPS_INFO);
        // move groups order to database
        if (isset($plugin_orders[PARAM_GROUPS_ORDER]) && $plugin_orders[PARAM_GROUPS_ORDER]->size() !== 0) {
            hd_debug_print("Move 'group_orders' to 'groups' db table");
            $groups_order_table = self::get_table_name(GROUPS_ORDER);
            $query = '';
            foreach ($plugin_orders[PARAM_GROUPS_ORDER] as $group_id) {
                $adult = M3uParser::is_adult_group($group_id);
                $q_group_id = Sql_Wrapper::sql_quote($group_id);
                $group_icon = Sql_Wrapper::sql_quote($group_icons->has($group_id) ? $group_icons->get($group_id) : DEFAULT_GROUP_ICON);
                $query .= "INSERT OR IGNORE INTO $groups_info_table
                            (group_id, title, icon, adult) VALUES ($q_group_id, $q_group_id, $group_icon, $adult);";
                $query .= "INSERT OR IGNORE INTO $groups_order_table (group_id) VALUES ($q_group_id);";
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
                $query .= "INSERT OR IGNORE INTO $groups_info_table
                            (group_id, title, icon, disabled, adult) VALUES ($q_group_id, $q_group_id, $group_icon, 1, $adult);";
            }
            $this->sql_playlist->exec_transaction($query);
            unset($plugin_orders[PARAM_DISABLED_GROUPS]);
        }

        // create known_channels db if not exist and import old orders settings
        $channels_info_table = self::get_table_name(CHANNELS_INFO);
        if (isset($plugin_orders[PARAM_KNOWN_CHANNELS]) && $plugin_orders[PARAM_KNOWN_CHANNELS]->size() !== 0) {
            hd_debug_print("Move 'known_channels' to 'channels' db table");
            $query = '';
            foreach ($plugin_orders[PARAM_KNOWN_CHANNELS] as $channel_id => $title) {
                $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
                $q_title = Sql_Wrapper::sql_quote($title);
                $query .= "INSERT OR IGNORE INTO $channels_info_table (channel_id, title, changed) VALUES ($q_channel_id, $q_title, 0);";
            }
            $this->sql_playlist->exec_transaction($query);
            unset($plugin_orders[PARAM_KNOWN_CHANNELS]);
        }

        if (isset($plugin_orders[PARAM_DISABLED_CHANNELS]) && $plugin_orders[PARAM_DISABLED_CHANNELS]->size() !== 0) {
            hd_debug_print("Move 'disabled_channels' to 'channels' db table");
            $where = Sql_Wrapper::sql_make_where_clause($plugin_orders[PARAM_DISABLED_CHANNELS]->get_order(), COLUMN_CHANNEL_ID);
            $query = "UPDATE $channels_info_table SET disabled = 1 WHERE $where;";
            $this->sql_playlist->exec($query);
            unset($plugin_orders[PARAM_DISABLED_CHANNELS]);
        }

        foreach ($plugin_orders as $order_name => $order) {
            $table_name = self::get_table_name($order_name);
            hd_debug_print("Move '$order_name' channels orders to $table_name db table");
            $query = sprintf(self::CREATE_ORDERED_TABLE, $table_name, COLUMN_CHANNEL_ID);
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

    /**
     * @return void
     */
    protected function upgrade_tv_history()
    {
        $tv_history_name = $this->get_history_path() . $this->make_base_name(PARAM_TV_HISTORY_ITEMS);
        if (!file_exists($tv_history_name)) {
            return;
        }
        $points = HD::get_items($tv_history_name);
        hd_debug_print("Load (PLUGIN TV HISTORY) from: $tv_history_name", true);
        $tv_history_table = self::get_table_name(TV_HISTORY);
        $query = '';
        foreach ($points as $key => $item) {
            $q_key = Sql_Wrapper::sql_quote($key);
            $item = (int)$item;
            $query .= "INSERT OR IGNORE INTO $tv_history_table (channel_id, time_stamp, program_title) VALUES ($q_key, $item, '');";
        }
        $query .= "DELETE FROM $tv_history_table WHERE rowid NOT IN (SELECT rowid FROM $tv_history_table ORDER BY time_stamp DESC LIMIT 7);";
        $this->sql_playlist->exec_transaction($query);
        hd_debug_print("Remove TV History: $tv_history_name");
        HD::erase_items($tv_history_name);
    }

    /**
     * @return void
     */
    protected function upgrade_vod_history()
    {
        // vod history is only one per playlist
        $vod_history_filename = $this->get_history_path() . $this->make_base_name('history') . ".settings";
        if (!file_exists($vod_history_filename)) {
            return;
        }
        hd_debug_print("Load (PLUGIN VOD HISTORY): $vod_history_filename");
        /** @var array $history */
        $history = HD::get_items($vod_history_filename, true, false);
        if (isset($history[VOD_HISTORY]) && $history[VOD_HISTORY]->size() !== 0) {
            $vod_history_table = self::get_table_name(VOD_HISTORY);
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
                    $query .= "INSERT OR IGNORE INTO $vod_history_table
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

    protected function get_vod_class()
    {
        $provider = $this->get_active_provider();
        if (!is_null($provider)) {
            return $provider->get_vod_class();
        }

        if ($this->is_vod_playlist()) {
            return 'vod_standard';
        }

        return null;
    }

    protected static function format_smart_label(&$defs, $name, $text)
    {
        if ($name === null) {
            Control_Factory::add_smart_label($defs, null,
                sprintf("<text color=%s size=small>%s</text>", DEF_LABEL_TEXT_COLOR_WHITE, $text),  -30);
        } else {
            Control_Factory::add_smart_label($defs, null,
                sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s</text>",
                    DEF_LABEL_TEXT_COLOR_GOLD, $name,
                    DEF_LABEL_TEXT_COLOR_WHITE, $text),
                -30
            );
        }
    }
}
