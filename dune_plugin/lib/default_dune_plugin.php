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
    protected $ext_epg_supported = false;

    /**
     * @var bool
     */
    protected $inited = false;

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

    /**
     * @var M3uParser
     */
    protected $vod_m3u_parser;

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
        parent::__construct();

        $this->providers = new Hashed_Array();
        $this->epg_presets = new Hashed_Array();
        $this->image_libs = new Hashed_Array();
        $this->perf = new Perf_Collector();
        $this->iptv_m3u_parser = new M3uParser();
        $this->vod_m3u_parser = new M3uParser();
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
        hd_debug_print(null, true);

        return User_Input_Handler_Registry::get_instance()->handle_user_input($user_input, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param object $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_folder_view($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $decoded_media_url = MediaURL::decode($media_url);
        return $this->get_screen_by_url($decoded_media_url)->get_folder_view($decoded_media_url, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param object $plugin_cookies
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
     * @param object $plugin_cookies
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
     * @param object $plugin_cookies
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
            $channel_row = $this->get_channel_info($channel_id, true);
            if (empty($channel_row)) {
                throw new Exception("Unknown channel");
            }

            if ($channel_row[M3uParser::COLUMN_ADULT] && !empty($pass_sex)) {
                if ($protect_code !== $pass_sex) {
                    throw new Exception("Wrong adult password: $protect_code");
                }
            } else {
                $now = $channel_row[M3uParser::COLUMN_ARCHIVE] > 0 ? time() : 0;
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
     * @param int $day_start_tm_sec
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
            $channel_row = $this->get_channel_info($channel_id, true);
            if (empty($channel_row)) {
                throw new Exception('Unknown channel');
            }

            if (LogSeverity::$is_debug) {
                hd_debug_print("day_start timestamp: $day_start_tm_sec ("
                    . format_datetime("Y-m-d H:i", $day_start_tm_sec) . ") TZ offset: "
                    . get_local_time_zone_offset());
            }

            $playlist_id = $this->get_active_playlist_id();

            // correct day start to local timezone
            $day_start_tm_sec -= get_local_time_zone_offset();

            // get personal time shift for channel
            $time_shift = 3600 * ($channel_row[M3uParser::COLUMN_TIMESHIFT] + $this->get_setting(PARAM_EPG_SHIFT, 0));
            hd_debug_print("EPG time shift $time_shift", true);
            $day_start_tm_sec += $time_shift;

            $show_ext_epg = $this->get_bool_setting(PARAM_SHOW_EXT_EPG) && $this->ext_epg_supported;

            $items = $this->epg_manager->get_day_epg_items($channel_row, $day_start_tm_sec);

            foreach ($items as $time => $value) {
                if (!isset($value[PluginTvEpgProgram::end_tm_sec], $value[PluginTvEpgProgram::name], $value[PluginTvEpgProgram::description])) {
                    hd_debug_print("malformed epg data: " . pretty_json_format($value));
                } else {
                    $tm_start = (int)$time + $time_shift;
                    $tm_end = (int)$value[PluginTvEpgProgram::end_tm_sec] + $time_shift;
                    $day_epg[] = array(
                        PluginTvEpgProgram::start_tm_sec => $tm_start,
                        PluginTvEpgProgram::end_tm_sec => $tm_end,
                        PluginTvEpgProgram::name => $value[PluginTvEpgProgram::name],
                        PluginTvEpgProgram::description => $value[PluginTvEpgProgram::description],
                    );

                    if (LogSeverity::$is_debug) {
                        hd_debug_print(format_datetime("m-d H:i", $tm_start)
                            . " - " . format_datetime("m-d H:i", $tm_end)
                            . " {$value[PluginTvEpgProgram::name]}", true);
                    }

                    if (!$show_ext_epg || in_array($channel_id, $this->epg_manager->get_delayed_epg())) continue;

                    $ext_epg[$time]["start_tm"] = $tm_start;
                    $ext_epg[$time]["title"] = $value[PluginTvEpgProgram::name];
                    $ext_epg[$time]["desc"] = $value[PluginTvEpgProgram::description];

                    if (empty($value[PluginTvEpgProgram::icon_url])) {
                        $ext_epg[$time][PluginTvExtEpgProgram::main_icon] = safe_get_value($channel_row, COLUMN_ICON, DEFAULT_CHANNEL_ICON_PATH);
                    } else {
                        $ext_epg[$time][PluginTvExtEpgProgram::main_icon] = $value[PluginTvEpgProgram::icon_url];
                    }

                    if (!empty($value[PluginTvExtEpgProgram::main_category])) {
                        $ext_epg[$time][PluginTvExtEpgProgram::main_category] = $value[PluginTvExtEpgProgram::main_category];
                    }

                    if (!empty($value[PluginTvExtEpgProgram::icon_urls])) {
                        $ext_epg[$time][PluginTvExtEpgProgram::icon_urls] = $value[PluginTvExtEpgProgram::icon_urls];
                    }

                    if (!empty($value[PluginTvExtEpgProgram::year])) {
                        $ext_epg[$time][PluginTvExtEpgProgram::year] = $value[PluginTvExtEpgProgram::year];
                    }

                    if (!empty($value[PluginTvExtEpgProgram::country])) {
                        $ext_epg[$time][PluginTvExtEpgProgram::country] = $value[PluginTvExtEpgProgram::country];
                    }

                    if (!empty($time[PluginTvExtEpgProgram::director])) {
                        $ext_epg[$time][PluginTvExtEpgProgram::director] = $value[PluginTvExtEpgProgram::director];
                    }
                    if (!empty($value[PluginTvExtEpgProgram::composer])) {
                        $ext_epg[$time][PluginTvExtEpgProgram::composer] = $value[PluginTvExtEpgProgram::composer];
                    }

                    if (!empty($value[PluginTvExtEpgProgram::editor])) {
                        $ext_epg[$time][PluginTvExtEpgProgram::editor] = $value[PluginTvExtEpgProgram::editor];
                    }

                    if (!empty($value[PluginTvExtEpgProgram::writer])) {
                        $ext_epg[$time][PluginTvExtEpgProgram::writer] = $value[PluginTvExtEpgProgram::writer];
                    }

                    if (!empty($value[PluginTvExtEpgProgram::actor]))
                        $ext_epg[$time][PluginTvExtEpgProgram::actor] = $value[PluginTvExtEpgProgram::actor];

                    if (!empty($value[PluginTvExtEpgProgram::presenter]))
                        $ext_epg[$time][PluginTvExtEpgProgram::presenter] = $value[PluginTvExtEpgProgram::presenter];

                    if (!empty($value[PluginTvExtEpgProgram::imdb_rating]))
                        $ext_epg[$time][PluginTvExtEpgProgram::imdb_rating] = $value[PluginTvExtEpgProgram::imdb_rating];
                }
            }

            $apk_subst = getenv('FS_PREFIX');
            if (!empty($ext_epg) && is_dir("$apk_subst/tmp/ext_epg")) {
                $filename = "$playlist_id-$channel_id-" . strftime('%Y-%m-%d', $day_start_tm_sec) . ".json";
                file_put_contents("$apk_subst/tmp/ext_epg/$filename", pretty_json_format($ext_epg));
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

        hd_debug_print(null, true);

        switch ($op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                hd_debug_print("Add channel $channel_id to favorites", true);
                $this->change_channels_order(TV_FAV_GROUP_ID, $channel_id, false);
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                hd_debug_print("Remove channel $channel_id from favorites", true);
                $this->change_channels_order(TV_FAV_GROUP_ID, $channel_id, true);
                break;

            case ACTION_ITEM_UP:
            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $this->arrange_channels_order_rows(TV_FAV_GROUP_ID, $channel_id, Ordered_Array::UP);
                break;

            case ACTION_ITEM_DOWN:
            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $this->arrange_channels_order_rows(TV_FAV_GROUP_ID, $channel_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEM_TOP:
                $this->arrange_channels_order_rows(TV_FAV_GROUP_ID, $channel_id, Ordered_Array::TOP);
                break;

            case ACTION_ITEM_BOTTOM:
                $this->arrange_channels_order_rows(TV_FAV_GROUP_ID, $channel_id, Ordered_Array::BOTTOM);
                break;

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("Clear favorites", true);
                $this->remove_channels_order(TV_FAV_GROUP_ID);
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
     * Initialize and parse selected playlist
     *
     * @param string $playlist_id
     * @param bool $force
     * @return bool
     */
    public function init_playlist_parser($playlist_id, $force = false)
    {
        hd_debug_print(null, true);

        $this->perf->setLabel('start_init_parser');

        $ret = false;
        $tmp_file = '';
        try {
            if (!$this->is_playlist_exist($playlist_id)) {
                hd_debug_print("Tv playlist not defined");
                throw new Exception("Tv playlist not defined");
            }

            $params = $this->get_playlist_parameters($playlist_id);
            hd_debug_print("Using playlist " . json_encode($params));

            $this->init_user_agent($playlist_id);

            $tmp_file = $this->get_playlist_cache($playlist_id, true);

            if (!$force) {
                $force = $this->is_playlist_cache_expired($tmp_file);
            }

            $type = safe_get_value($params, PARAM_TYPE);
            if ($force !== false) {
                hd_debug_print("m3u playlist: {$params[PARAM_NAME]} ($playlist_id)");
                if ($type === PARAM_PROVIDER) {
                    $provider = $this->get_active_provider();
                    if (is_null($provider)) {
                        throw new Exception("Unable to init provider to download: " . json_encode($params));
                    }

                    if ($provider->get_provider_info($force) === false) {
                        throw new Exception("Unable to get provider info to download: " . json_encode($params));
                    }

                    hd_debug_print("Load provider playlist to: $tmp_file");
                    $res = $provider->load_playlist($tmp_file);
                    $logfile = $provider->getCurlWrapper()->get_raw_response_headers();
                } else {
                    $uri = safe_get_value($params, PARAM_URI);
                    if ($type === PARAM_FILE) {
                        if (empty($uri)) {
                            throw new Exception("Empty url: $uri");
                        }
                        hd_debug_print("m3u copy local file: $uri to $tmp_file");
                        $res = copy($uri, $tmp_file);
                    } else if ($type === PARAM_LINK || $type === PARAM_CONF) {
                        hd_debug_print("m3u download link: $uri");
                        if (!is_proto_http($uri)) {
                            throw new Exception("Incorrect playlist url: $uri");
                        }
                        list($res, $logfile) = Curl_Wrapper::simple_download_file($uri, $tmp_file);
                    } else {
                        throw new Exception("Unknown playlist type");
                    }

                    $id_map = safe_get_value($params, PARAM_ID_MAPPER);
                    hd_debug_print("playlist id map: $id_map");
                    if (empty($id_map) || $id_map === 'by_default') {
                        $params[PARAM_ID_MAPPER] = ATTR_CHANNEL_HASH;
                    }
                }

                if (!$res || !file_exists($tmp_file)) {
                    $exception_msg = TR::load('err_load_playlist');
                    if ($type !== PARAM_FILE && !empty($logfile)) {
                        $exception_msg .= "\n\n$logfile";
                    }
                    throw new Exception($exception_msg);
                }

                $contents = file_get_contents($tmp_file);
                if (strpos($contents, TAG_EXTM3U) === false) {
                    $exception_msg = TR::load('err_load_playlist') . "\n\n$contents";
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

                $replace = SwitchOnOff::to_bool($provider->GetParameter(PARAM_REPLACE_ICON, SwitchOnOff::on));
                if (!$replace) {
                    $icon_replace_pattern = $provider->getConfigValue(CONFIG_ICON_REPLACE);
                }
            } else {
                $id_parser = '';
                $id_map = safe_get_value($params, PARAM_ID_MAPPER, '');
                hd_debug_print("ID mapper for playlist: $id_map", true);
            }

            $this->iptv_m3u_parser->setPlaylist($tmp_file, $force);

            if (!empty($id_parser)) {
                $this->channel_id_map = ATTR_PARSED_ID;
                hd_debug_print("Using specific ID parser: $this->channel_id_map ($id_parser)", true);
            }

            if (!empty($id_map)) {
                hd_debug_print("Using specific ID mapping: $id_map", true);
                $this->channel_id_map = $id_map;
            }

            if (empty($id_parser) && empty($id_map)) {
                hd_debug_print("No specific mmapping set using HASH", true);
                $this->channel_id_map = ATTR_CHANNEL_HASH;
            }

            $parser_params = array('id_parser' => $id_parser, 'icon_replace_pattern' => $icon_replace_pattern);
            $this->iptv_m3u_parser->setupParserParameters($parser_params);
            $this->iptv_m3u_parser->parseHeader();
            hd_debug_print("Init playlist done!");
            $ret = true;
        } catch (Exception $ex) {
            $err = HD::get_last_error($this->get_pl_error_name());
            if (!empty($err)) {
                $err .= "\n\n";
            }
            $err .= $ex->getMessage();
            HD::set_last_error($this->get_pl_error_name(), $err);
            print_backtrace_exception($ex);
            if (empty($type) && file_exists($tmp_file)) {
                unlink($tmp_file);
            }
        }

        $this->perf->setLabel('end_init_parser');
        $report = $this->perf->getFullReport('start_init_parser', 'end_init_parser');

        hd_debug_print("Init time:     {$report[Perf_Collector::TIME]} sec");
        hd_debug_print_separator();

        return $ret;
    }

    /**
     * @param string $channel_id
     * @param int $program_ts
     * @param object $plugin_cookies
     * @return mixed|null
     */
    public function get_program_info($channel_id, $program_ts, $plugin_cookies)
    {
        hd_debug_print(null, true);
        $channel = $this->get_channel_info($channel_id);
        if (is_null($channel)) {
            return null;
        }

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

    ////////////////////////////////////////////////////////
    /// init database

    /**
     * @param string $playlist_id
     * @return bool
     */
    public function init_playlist_db($playlist_id)
    {
        hd_debug_print(null, true);
        if (empty($playlist_id)) {
            hd_debug_print("Empty playlist id");
            return false;
        }

        hd_debug_print_separator();
        if (!$this->is_playlist_exist($playlist_id)) {
            hd_debug_print("Playlist info for ID: $playlist_id is not exist!");
            return false;
        }

        $params = $this->get_playlist_parameters($playlist_id);
        hd_debug_print("Process playlist: {$params[PARAM_NAME]} ($playlist_id)");

        $db_file = get_data_path("$playlist_id.db");

        if ($this->sql_playlist) {
            // attach to playlist db. if db not exist it will be created
            if ($this->is_database_attached('main', $db_file) === 2) {
                hd_debug_print("Database already inited!", true);
                return true;
            }
            $this->reset_playlist_db();
        }

        hd_debug_print("Load database: $db_file", true);
        $this->sql_playlist = new Sql_Wrapper($db_file);

        $playlist_xmltv = self::get_table_name(XMLTV_SOURCE_PLAYLIST);
        $selected_xmltv = self::SELECTED_XMLTV_TABLE;
        $settings_table = self::SETTINGS_TABLE;
        $cookies_table = self::COOKIES_TABLE;

        // create settings table
        $query  = sprintf(self::CREATE_PLAYLIST_SETTINGS_TABLE, $settings_table);
        $query .= sprintf(self::CREATE_XMLTV_TABLE, $playlist_xmltv);
        $query .= sprintf(self::CREATE_SELECTED_XMTLV_TABLE, $selected_xmltv);
        $query .= sprintf(self::CREATE_COOKIES_TABLE, $cookies_table);

        // create tables for vod search, vod filters, vod favorites
        foreach (array(VOD_FILTER_LIST => 'item', VOD_SEARCH_LIST => 'item', VOD_FAV_GROUP_ID => COLUMN_CHANNEL_ID) as $list => $column) {
            $table_name = self::get_table_name($list);
            $query .= sprintf(self::CREATE_ORDERED_TABLE, $table_name, $column);
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

            // Move old parameters show groups to settings
            $move_parameters = array(PARAM_SHOW_ALL, PARAM_SHOW_FAVORITES, PARAM_SHOW_HISTORY);
            foreach ($move_parameters as $parameter) {
                if (!array_key_exists($parameter, $plugin_settings)) {
                    $plugin_settings[$parameter] = SwitchOnOff::to_def($this->get_bool_parameter($parameter));
                }
            }

            // remove obsolete settings
            $removed_settings = array('cur_xmltv_sources', 'epg_cache_ttl', 'epg_cache_ttl', 'force_http', 'epg_cache_type');
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
                    $query = '';
                    /** @var Named_Storage $v */
                    foreach ($value as $k => $v) {
                        $list = array(
                            COLUMN_HASH => $k,
                            COLUMN_TYPE => (empty($v->type) ? PARAM_LINK : $v->type),
                            COLUMN_NAME => $v->name,
                            COLUMN_URI => $v->params[PARAM_URI],
                            COLUMN_CACHE => (isset($v->params[PARAM_CACHE]) ? $v->params[PARAM_CACHE] : XMLTV_CACHE_AUTO),
                        );
                        $insert = Sql_Wrapper::sql_make_insert_list($list);
                        $query .= "INSERT OR IGNORE INTO $playlist_xmltv $insert;";
                    }
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_EPG_PLAYLIST]);
                } else if ($key === PARAM_SELECTED_XMLTV_SOURCES) {
                    hd_debug_print("Convert 'selected_xmltv_sources' to 'selected_xmltv' table");
                    $query = '';
                    foreach ($value as $hash) {
                        $q_hash = Sql_Wrapper::sql_quote($hash);
                        $query .= "INSERT OR IGNORE INTO $selected_xmltv (hash) VALUES ($q_hash);";
                    }
                    $this->sql_playlist->exec_transaction($query);
                    unset($plugin_settings[PARAM_SELECTED_XMLTV_SOURCES]);
                } else if ($key === PARAM_CHANNELS_ZOOM || $key === PARAM_CHANNEL_PLAYER) {
                    unset($plugin_settings[$key]);
                } else if ($key === PARAM_DUNE_PARAMS) {
                    hd_debug_print("Move 'dune_params' to playlist parameter");
                    $params[PARAM_DUNE_PARAMS] = json_encode($value);
                    $this->set_playlist_parameters($playlist_id, $params);
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
                $query = "INSERT INTO $cookies_table (param, value, time_stamp) VALUES('$key', $q_value, $time_stamp);";
                $this->sql_playlist->exec($query);
                hd_debug_print("Remove cookie: $token_path");
                unlink($token_path);
            }
        }

        // transfer old orders settings to new db
        $provider_playlist_id = '';
        $provider = null;
        $plugin_orders_name = $playlist_id . '_' . PLUGIN_ORDERS;
        $provider_class = safe_get_value($params, PARAM_PROVIDER);
        if (empty($provider_class)) {
            hd_debug_print("Playlist is not a IPTV provider");
        } else {
            $provider = $this->get_provider($playlist_id);
            if ($provider !== null) {
                hd_debug_print("Using provider {$provider->getId()} ({$provider->getName()}) playlist id: $playlist_id");

                $provider_playlist_id = $this->get_playlist_parameter($playlist_id, MACRO_PLAYLIST_ID);
                $provider_id = empty($provider_playlist_id) ? '' : "_$provider_playlist_id";
                $plugin_orders_name = $playlist_id . '_' . PLUGIN_ORDERS . $provider_id;

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

                        $query .= "INSERT OR IGNORE INTO $playlist_xmltv
                                (hash, type, name, uri, cache) VALUES ($q_hash, $q_type, $q_name, $q_source, $q_cache);";
                    }

                    if (!empty($known_sources)) {
                        $where = Sql_Wrapper::sql_make_where_clause($known_sources, 'hash', true);
                        $query .= "DELETE FROM $playlist_xmltv $where AND type = $q_type;";
                    }
                    $this->sql_playlist->exec_transaction($query);
                }
            }
        }

        $db_file = get_data_path("$plugin_orders_name.db");
        if ($this->attachDatabase($db_file, self::PLAYLIST_ORDERS_DB) === 0) {
            hd_debug_print("Can't attach to database: $db_file with name: " . self::PLAYLIST_ORDERS_DB);
        }

        // create group table
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $query = sprintf(self::CREATE_GROUPS_INFO_TABLE, $groups_info_table);
        // create channels table
        $channels_info_table = self::get_table_name(CHANNELS_INFO);
        $query .= sprintf(self::CREATE_CHANNELS_INFO_TABLE, $channels_info_table);
        // create order_groups table
        $query .= sprintf(self::CREATE_ORDERED_TABLE, self::get_table_name(GROUPS_ORDER), COLUMN_GROUP_ID);

        // create tables for favorites
        $query .= sprintf(self::CREATE_ORDERED_TABLE, self::get_table_name(TV_FAV_GROUP_ID), COLUMN_CHANNEL_ID);

        $this->sql_playlist->exec_transaction($query);

        // add special groups to the table if the not exists
        $special_group = array(
            array(COLUMN_GROUP_ID => TV_FAV_GROUP_ID, COLUMN_TITLE => TV_FAV_GROUP_CAPTION, COLUMN_ICON => TV_FAV_GROUP_ICON),
            array(COLUMN_GROUP_ID => TV_HISTORY_GROUP_ID, COLUMN_TITLE => TV_HISTORY_GROUP_CAPTION, COLUMN_ICON => TV_HISTORY_GROUP_ICON),
            array(COLUMN_GROUP_ID => TV_CHANGED_CHANNELS_GROUP_ID, COLUMN_TITLE => TV_CHANGED_CHANNELS_GROUP_CAPTION, COLUMN_ICON => TV_CHANGED_CHANNELS_GROUP_ICON),
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
                $where = Sql_Wrapper::sql_make_where_clause($plugin_orders[PARAM_DISABLED_CHANNELS]->get_order(), 'channel_id');
                $query = "UPDATE $channels_info_table SET disabled = 1 $where;";
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

        // attach to tv_history db. if db not exist it will be created
        // tv history is per playlist or per provider playlist
        $history_path = get_slash_trailed_path($this->get_history_path());
        create_path($history_path);
        $tv_history_db = $history_path . $this->make_name(TV_HISTORY, $provider_playlist_id) . ".db";
        if ($this->attachDatabase($tv_history_db,  self::TV_HISTORY_DB) === 0) {
            hd_debug_print("Can't attach to database: $tv_history_db with name: " . self::TV_HISTORY_DB);
        } else {
            // create tv history table
            $tv_history_table = self::get_table_name(TV_HISTORY);
            $query = sprintf(self::CREATE_TV_HISTORY_TABLE, $tv_history_table);
            $this->sql_playlist->exec($query);

            $tv_history_name = $history_path . $this->make_name(PARAM_TV_HISTORY_ITEMS);
            if (file_exists($tv_history_name)) {
                $points = HD::get_items($tv_history_name);
                hd_debug_print("Load (PLUGIN TV HISTORY) from: $tv_history_name", true);
                $query = '';
                foreach ($points as $key => $item) {
                    $q_key = Sql_Wrapper::sql_quote($key);
                    $item = (int)$item;
                    $query .= "INSERT OR IGNORE INTO $tv_history_table (channel_id, time_stamp) VALUES ($q_key, $item);";
                }
                $query .= "DELETE FROM $tv_history_table WHERE rowid NOT IN (SELECT rowid FROM $tv_history_table ORDER BY time_stamp DESC LIMIT 7);";
                $this->sql_playlist->exec_transaction($query);
                hd_debug_print("Remove TV History: $tv_history_name");
                HD::erase_items($tv_history_name);
            }
        }

        // create vod history table
        if ($this->is_vod_playlist() || ($provider !== null && $provider->hasApiCommand(API_COMMAND_GET_VOD))) {
            // vod history is only one per playlist
            $vod_history_db = $history_path . $this->make_name(VOD_HISTORY) . ".db";
            $vod_history_table = self::get_table_name(VOD_HISTORY);
            if ($this->attachDatabase($vod_history_db,  self::VOD_HISTORY_DB) === 0) {
                hd_debug_print("Can't attach to database: $vod_history_db with name: " . self::VOD_HISTORY_DB);
            } else {
                $query = sprintf(self::CREATE_VOD_HISTORY_TABLE, $vod_history_table);
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
            }
        }

        hd_debug_print("Database initialized.");
        hd_debug_print_separator();

        $this->init_screen_view_parameters($this->get_background_image());

        return true;
    }

    /**
     * Initialize and parse selected playlist
     *
     * @param string $playlist_id
     * @return bool
     */
    public function init_vod_playlist($playlist_id)
    {
        hd_debug_print(null, true);

        if (!$this->is_playlist_exist($playlist_id)) {
            hd_debug_print("Playlist not defined");
            return false;
        }

        $params = $this->get_playlist_parameters($playlist_id);
        $type = safe_get_value($params, PARAM_TYPE);
        $pl_type = safe_get_value($params, PARAM_PL_TYPE);

        if ($type === PARAM_PROVIDER) {
            $provider = $this->get_active_provider();
            if (is_null($provider)) {
                hd_debug_print("Unknown provider");
                return false;
            }

            if (!$provider->hasApiCommand(API_COMMAND_GET_VOD)) {
                hd_debug_print("Failed to get VOD playlist from provider");
                return false;
            }
        } else if ($pl_type !== CONTROL_PLAYLIST_VOD) {
            hd_debug_print("Playlist is not VOD type");
            return false;
        }

        $this->init_user_agent($playlist_id);
        $tmp_file = $this->get_playlist_cache($playlist_id, false);
        $force = $this->is_playlist_cache_expired($tmp_file);

        try {
            if ($force !== false) {
                $uri = safe_get_value($params, PARAM_URI);
                if ($type === PARAM_PROVIDER) {
                    hd_debug_print("download provider vod");
                    $res = $provider->execApiCommand(API_COMMAND_GET_VOD, $tmp_file);
                    if ($res === false) {
                        $exception_msg = TR::load('err_load_vod') . "\n\n" . $provider->getCurlWrapper()->get_raw_response_headers();
                        HD::set_last_error($this->get_vod_error_name(), $exception_msg);
                        throw new Exception($exception_msg);
                    }
                } else if ($type === PARAM_FILE) {
                    hd_debug_print("m3u copy local file: $uri to $tmp_file");
                    if (empty($uri)) {
                        throw new Exception("Empty playlist path");
                    }

                    $res = copy($uri, $tmp_file);
                    if ($res === false) {
                        $exception_msg = TR::load('err_load_vod') . PHP_EOL . PHP_EOL .
                            "m3u copy local file: $uri to $tmp_file";
                        throw new Exception($exception_msg);
                    }
                } else if ($type === PARAM_LINK || $type === PARAM_CONF) {
                    hd_debug_print("m3u download link: $uri");
                    if (empty($uri)) {
                        throw new Exception("Empty playlist url");
                    }
                    list($res, $logfile) = Curl_Wrapper::simple_download_file($uri, $tmp_file);
                    if ($res === false) {
                        $exception_msg = TR::load('err_load_vod') . "\n\n$logfile";
                        throw new Exception($exception_msg);
                    }
                } else {
                    throw new Exception("Unknown playlist type");
                }

                $playlist_file = file_get_contents($tmp_file);
                if (strpos($playlist_file, TAG_EXTM3U) === false) {
                    $exception_msg = TR::load('err_load_vod') . "\n\nPlaylist is not a M3U file\n\n$playlist_file";
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

    ///////////////////////////////////////////////////////////////////////
    /// IPTV

    /**
     * Return false if load channels failed
     * Return true if channels loaded successful
     *
     * @param object $plugin_cookies
     * @return bool
     */
    public function load_channels(&$plugin_cookies, $reload_playlist = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("Force reload: " . var_export($reload_playlist, true));

        $this->perf->reset('start');

        HD::set_last_error($this->get_pl_error_name(), null);

        $plugin_cookies->toggle_move = false;

        $playlist_id = $this->get_active_playlist_id();
        if (empty($playlist_id)) {
            hd_debug_print("No active playlist found");
            return false;
        }

        if (!$this->init_playlist_db($playlist_id)) {
            hd_debug_print("Init playlist db failed");
            return false;
        }

        $db_file = LogSeverity::$is_debug ? ($this->get_playlist_cache($playlist_id, true) . ".db") : ":memory:";
        $database_attached = $this->attachDatabase($db_file, M3uParser::IPTV_DB);
        if ($database_attached === 0) {
            hd_debug_print("Can't attach to database: $db_file with name: " . M3uParser::IPTV_DB);
        }

        if (!$reload_playlist && $database_attached === 2) {
            return true;
        }

        $ext_epg_channels = get_temp_path("channel_ids.txt");
        if (file_exists($ext_epg_channels)) {
            unlink($ext_epg_channels);
        }

        if ($this->is_vod_playlist()) {
            hd_debug_print("Using standard VOD implementation");
            $vod_class = 'vod_standard';
            $this->vod = new $vod_class($this);
            $this->vod_enabled = $this->vod->init_vod(null);
            $this->vod->init_vod_screens();

            $enable_vod_icon = SwitchOnOff::to_def($this->vod_enabled && $this->get_bool_parameter(PARAM_SHOW_VOD_ICON, false));
            $plugin_cookies->{PARAM_SHOW_VOD_ICON} = $enable_vod_icon;
            hd_debug_print("Show VOD icon: $enable_vod_icon", true);

            return true;
        }

        // first check if playlist in cache and load it
        if (false === $this->init_playlist_parser($playlist_id, $reload_playlist)) {
            $this->detachDatabase(M3uParser::IPTV_DB);
            if ($db_file !== ':memory:' && file_exists($db_file)) {
                unlink($db_file);
            }
            return false;
        }

        $filename = $this->iptv_m3u_parser->get_filename();

        try {
            $this->perf->setLabel('start_parse_playlist');

            $mtime = filemtime($filename);
            $date_fmt = format_datetime("Y-m-d H:i", $mtime);
            hd_debug_print("Parse playlist $filename (timestamp: $mtime, $date_fmt)");

            $count = $this->iptv_m3u_parser->parseIptvPlaylist($this->sql_playlist);
            if (empty($count)) {
                $contents = @file_get_contents($filename);
                $exception_msg = TR::load('err_load_playlist') . " Empty playlist!\n\n$contents";
                $this->clear_playlist_cache();
                throw new Exception($exception_msg);
            }

            // update playlists xmltv sources
            $saved_source = $this->get_xmltv_sources(XMLTV_SOURCE_PLAYLIST);
            $hashes = array();
            foreach ($saved_source as $source) {
                $hashes[$source[PARAM_HASH]] = array(
                    PARAM_NAME => $source[PARAM_NAME],
                    PARAM_URI => $source[PARAM_URI],
                    PARAM_CACHE => $source[PARAM_CACHE]
                );
            }
            hd_debug_print("saved playlist sources: " . json_encode($hashes), true);

            foreach ($this->iptv_m3u_parser->getXmltvSources() as $url) {
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

            $this->set_xmltv_sources(XMLTV_SOURCE_PLAYLIST, $saved_source);

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

        hd_debug_print("VOD enabled: " . SwitchOnOff::to_def($this->vod_enabled), true);

        $enable_vod_icon = SwitchOnOff::to_def($this->vod_enabled && $this->get_bool_parameter(PARAM_SHOW_VOD_ICON, false));
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

        $groups_info_table = self::get_table_name(GROUPS_INFO);

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

        $channel_info_table = self::get_table_name(CHANNELS_INFO);
        $query = "SELECT count(channel_id) FROM $channel_info_table;";
        $is_new = $this->sql_playlist->query_value($query) === 0;

        // get name of the column for channel ID
        $id_column = $this->get_id_column();
        hd_debug_print("ID column:           $id_column");

        // update existing database for empty group_id (converted from known_channels.settings)
        hd_debug_print("Update groups name", true);
        $iptv_channels = M3uParser::CHANNELS_TABLE;
        $query = "UPDATE $channel_info_table
                    SET group_id = (
                        SELECT group_id
                        FROM $iptv_channels
                        WHERE $channel_info_table.channel_id = $iptv_channels.$id_column
                        LIMIT 1
                    )
                    WHERE group_id = ''
                    AND EXISTS (
                        SELECT 1
                        FROM $iptv_channels
                        WHERE $channel_info_table.channel_id = $iptv_channels.$id_column
                          AND $channel_info_table.group_id != $iptv_channels.group_id
                    );";
        $this->sql_playlist->exec($query);

        $iptv_groups = M3uParser::GROUPS_TABLE;

        // select existing groups
        $existing_groups = $this->get_groups_order();

        // mark as removed channels that not present iptv.iptv_channels db
        hd_debug_print("Remove non-existing channels", true);
        $query = "UPDATE $channel_info_table SET changed = -1 WHERE channel_id NOT IN
                    (SELECT $id_column AS channel_id FROM $iptv_channels WHERE channel_id NOT NULL);";
        $this->sql_playlist->exec($query);

        // select new groups that not present in groups table but exist in iptv_groups
        $groups_order_table = self::get_table_name(GROUPS_ORDER);
        $query_new_groups = "SELECT * FROM $iptv_groups WHERE group_id NOT IN (SELECT DISTINCT group_id FROM $groups_info_table);";
        $new_groups = $this->sql_playlist->fetch_array($query_new_groups);
        if (!empty($new_groups)) {
            $new_groups_ids = array_map(function($value) {
                return $value[COLUMN_GROUP_ID];
            }, $new_groups);

            $list_new_groups = Sql_Wrapper::sql_make_list_from_values($new_groups_ids);
            hd_debug_print("New groups:          $list_new_groups");

            hd_debug_print("Create group orders table for new groups", true);
            $query = '';
            foreach ($new_groups_ids as $group_id) {
                $order_table_name = self::get_table_name($group_id);
                hd_debug_print("New groups order $group_id ($order_table_name)", true);
                $query .= sprintf(self::CREATE_ORDERED_TABLE, $order_table_name, COLUMN_CHANNEL_ID);
            }
            $this->sql_playlist->exec_transaction($query);

            hd_debug_print("Add new groups", true);
            $query = '';
            foreach ($new_groups as $group_row) {
                $q_group_id = Sql_Wrapper::sql_quote($group_row[COLUMN_GROUP_ID]);
                $q_group_icon = Sql_Wrapper::sql_quote(empty($group_row[COLUMN_ICON]) ? DEFAULT_GROUP_ICON : $group_row[COLUMN_ICON]);
                $q_adult = Sql_Wrapper::sql_quote($group_row[M3uParser::COLUMN_ADULT]);

                $query .= "INSERT OR IGNORE INTO $groups_info_table (group_id, title, icon, adult) VALUES ($q_group_id, $q_group_id, $q_group_icon, $q_adult);";
                $query .= "INSERT OR IGNORE INTO $groups_order_table (group_id) VALUES ($q_group_id);";
            }
            $this->sql_playlist->exec_transaction($query);
        }

        // add new channels
        hd_debug_print("Adding new channels", true);
        $query = "INSERT OR IGNORE INTO $channel_info_table (channel_id, title, group_id, adult)
                    SELECT $id_column AS channel_id, title, group_id, adult
                    FROM $iptv_channels
                    WHERE group_id IN (SELECT group_id FROM $groups_info_table WHERE special = 0)
                      AND channel_id NOT IN (SELECT channel_id FROM $channel_info_table)
                      AND channel_id IS NOT NULL
                    GROUP BY channel_id ORDER BY ROWID ASC;";
        $this->sql_playlist->exec($query);

        $query = '';
        hd_debug_print("Adding channels to new groups orders", true);
        foreach ($new_groups as $group_row) {
            $order_table_name = self::get_table_name($group_row[COLUMN_GROUP_ID]);
            $q_group_id = Sql_Wrapper::sql_quote($group_row[COLUMN_GROUP_ID]);

            // Add channels to orders from iptv_channels for selected group that not disabled in known_channels
            $query .= "INSERT OR IGNORE INTO $order_table_name (channel_id)
                        SELECT channel_id FROM $channel_info_table
                        WHERE group_id = $q_group_id
                        AND changed = 1 AND disabled == 0 
                        ORDER BY ROWID ASC;";
        }
        $this->sql_playlist->exec_transaction($query);

        hd_debug_print("Adding new channels to existing orders", true);
        $query = '';
        foreach ($existing_groups as $group_id) {
            $order_table_name = self::get_table_name($group_id);
            $q_group_id = Sql_Wrapper::sql_quote($group_id);
            // add new channels to group order
            $query .= "INSERT OR IGNORE INTO $order_table_name (channel_id)
                        SELECT channel_id FROM $channel_info_table WHERE group_id = $q_group_id AND changed = 1 ORDER BY ROWID ASC;";
            // remove not existing channels from group order
            $query .= "DELETE FROM $order_table_name
                        WHERE channel_id IN (SELECT channel_id FROM $channel_info_table WHERE group_id = $q_group_id AND changed = -1);";
        }
        $this->sql_playlist->exec_transaction($query);

        // reset changed flag for channels present in channels_info table and iptv_channels table
        hd_debug_print("Reset changes for existing channels", true);
        $query = "UPDATE $channel_info_table SET changed = 0
                    WHERE channel_id IN (SELECT $id_column AS channel_id FROM $iptv_channels);";
        $this->sql_playlist->exec($query);

        // reset changed flag for channels in disabled groups
        hd_debug_print("Reset changed for disabled channels", true);
        $query = "UPDATE $channel_info_table SET changed = 0, disabled = 1
                    WHERE group_id IN (SELECT group_id FROM $groups_info_table WHERE disabled = 1 AND special = 0);";
        $this->sql_playlist->exec($query);

        if ($is_new) {
            // if it first run for this playlist remove changed status for all channels
            $this->clear_changed_channels();
        }

        // cleanup order if group removed from playlist
        hd_debug_print("Cleanup removed groups orders", true);
        $query = "SELECT group_id FROM $groups_info_table WHERE group_id NOT IN (SELECT group_id FROM $iptv_groups) AND special = 0;";
        $removed_groups = $this->sql_playlist->fetch_single_array($query, COLUMN_GROUP_ID);
        if (!empty($rows)) {
            hd_debug_print("Removed groups:      " . implode(', ', $removed_groups), true);
            $where = Sql_Wrapper::sql_make_where_clause($removed_groups, 'group_id');
            $query = "DELETE FROM $groups_order_table $where;";
            $query .= "DELETE FROM $groups_info_table $where;";
            $this->sql_playlist->exec_transaction($query);
        }

        $known_cnt = $this->get_channels_count(TV_ALL_CHANNELS_GROUP_ID, PARAM_ALL, PARAM_ALL);
        $visible_groups_cnt = $this->get_groups_count(PARAM_GROUP_ORDINARY, PARAM_ENABLED);
        $hidden_groups_cnt = $this->get_groups_count(PARAM_GROUP_ORDINARY, PARAM_DISABLED);

        $visible_channels_cnt = $this->get_channels_count(TV_ALL_CHANNELS_GROUP_ID, PARAM_ENABLED);
        $hidden_channels_cnt = $this->get_channels_count(TV_ALL_CHANNELS_GROUP_ID, PARAM_DISABLED);
        $all_hidden_channels_cnt = $this->get_channels_count(TV_ALL_CHANNELS_GROUP_ID, PARAM_DISABLED, PARAM_ALL);

        $added_channels_cnt = $this->get_changed_channels_count(PARAM_NEW);
        $removed_channels_cnt = $this->get_changed_channels_count(PARAM_REMOVED);

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
     * @param object $plugin_cookies
     * @return bool
     */
    public function reload_channels(&$plugin_cookies)
    {
        hd_debug_print(null, true);
        $this->reset_playlist_db();
        return $this->load_channels($plugin_cookies, true);
    }

    /**
     * @return void
     */
    public function init_user_agent($playlist_id)
    {
        $user_agent = $this->get_playlist_parameter($playlist_id, PARAM_USER_AGENT);
        hd_debug_print("Init user agent: $user_agent");
        if (!empty($user_agent) && $user_agent !== HD::get_default_user_agent()) {
            HD::set_dune_user_agent($user_agent);
        }
    }

    /**
     * @param string|$source
     * @return void
     */
    public function run_bg_epg_indexing($source)
    {
        hd_debug_print(null, true);

        $item = $this->get_xmltv_sources(XMLTV_SOURCE_ALL)->get($source);
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

        $log_path = get_temp_path('bg_indexing_error.log');
        export_DuneSystem();

        $ext_php = get_platform_php();
        $script_path = get_install_path('bin/index_epg.php');
        $cmd = "$ext_php -f $script_path $config_file >$log_path 2>&1 &";
        hd_debug_print("exec: $cmd", true);
        shell_exec($cmd);
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

    public function is_ext_epg_exist()
    {
        return $this->ext_epg_supported;
    }

    public function get_default_channel_icon($classic = true)
    {
        if ($classic) {
            return DEFAULT_CHANNEL_ICON_PATH;
        }
        return $this->get_bool_setting(PARAM_NEWUI_SQUARE_ICONS, false) ? DEFAULT_CHANNEL_ICON_PATH_SQ : DEFAULT_CHANNEL_ICON_PATH;
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
        if (empty($id) || (!$this->is_playlist_exist($id) && $this->get_all_playlists_count())) {
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
        $stream_url = $channel_row[M3uParser::COLUMN_PATH];
        if (empty($stream_url)) {
            throw new Exception("Empty url!");
        }

        $force_detect = false;
        $provider = $this->get_active_provider();
        if (!is_null($provider)) {
            if ($provider->GetParameter(MACRO_PLAYLIST_ID) !== CUSTOM_PLAYLIST_ID) {
                $url_subst = $provider->getConfigValue(CONFIG_URL_SUBST);
                if (!empty($url_subst)) {
                    $stream_url = preg_replace($url_subst['regex'], $url_subst['replace'], $stream_url);
                    $stream_url = $provider->replace_macros($stream_url);
                }
            }

            $streams = $provider->GetStreams();
            if (!empty($streams)) {
                $idx = $provider->GetParameter(MACRO_STREAM_ID);
                $force_detect = ($streams[$idx] === 'MPEG-TS');
            }

            $detect_stream = $provider->getConfigValue(PARAM_DUNE_FORCE_TS);
            if ($detect_stream) {
                $force_detect = $detect_stream;
            }
        }

        if ((int)$archive_ts !== -1) {
            $m3u_info = $this->get_iptv_m3u_parser()->getM3uInfo();
            if (!empty($m3u_info)) {
                $catchup = $this->get_iptv_m3u_parser()->getM3uInfo()->getCatchupType();
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

            $channel_catchup = $channel_row[M3uParser::COLUMN_CATCHUP];
            if (!empty($channel_catchup)) {
                // channel catchup override playlist, user and config settings
                $catchup = $channel_catchup;
            } else if (empty($catchup)) {
                $catchup = ATTR_CATCHUP_SHIFT;
            }

            $archive_url = $channel_row[M3uParser::COLUMN_CATCHUP_SOURCE];
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
            $dune_params_str = $this->generate_dune_params($channel_row[COLUMN_CHANNEL_ID],
                json_decode(safe_get_value($channel_row, M3uParser::COLUMN_EXT_PARAMS), true));

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
        $params = $this->get_playlist_parameters($this->get_active_playlist_id());
        if (safe_get_value($params, PARAM_USE_DUNE_PARAMS, SwitchOnOff::on) === SwitchOnOff::on) {
            $playlist_dune_params = array();
            $dune_params_str = safe_get_value($params, PARAM_DUNE_PARAMS);
            if (!empty($dune_params_str)) {
                $dune_params = explode(',', $dune_params_str);
                foreach ($dune_params as $param) {
                    $param_pair = explode(':', $param);
                    if (empty($param_pair) || count($param_pair) < 2) continue;

                    $param_pair[0] = trim($param_pair[0]);
                    if (strpos($param_pair[1], ",,") !== false) {
                        $param_pair[1] = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $param_pair[1]);
                    } else {
                        $param_pair[1] = str_replace(",", ",,", $param_pair[1]);
                    }

                    $playlist_dune_params[$param_pair[0]] = $param_pair[1];
                }

                $playlist_dune_params = array_slice($playlist_dune_params, 0);
            }

            $provider = $this->get_active_provider();
            $provider_dune_params = array();
            if (!is_null($provider)) {
                $provider_dune_params = dune_params_to_array($provider->getConfigValue(PARAM_DUNE_PARAMS));
            }

            $all_params = array_merge($provider_dune_params, $playlist_dune_params);
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
            $zoom_data = $this->get_channel_zoom($channel_id);
            if (!empty($zoom_data)) {
                $dune_params['zoom'] = $zoom_data;
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

        return rtrim($path, '/');
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
     * @param string $id
     * @param bool $is_tv
     * @return string
     */
    public function get_playlist_cache($id, $is_tv)
    {
        return get_temp_path($id . ($is_tv ? "_playlist.m3u8" : "_vod_playlist.m3u8"));
    }

    /**
     * Clear downloaded playlist
     * @param string $playlist_id
     * @return void
     */
    public function clear_playlist_cache($playlist_id = null)
    {
        if ($playlist_id === null) {
            $playlist_id = $this->get_active_playlist_id();
        }

        $this->iptv_m3u_parser->clear_data();
        $tmp_file = get_temp_path($playlist_id . "_playlist.m3u8");
        if (file_exists($tmp_file)) {
            hd_debug_print("clear_playlist_cache: remove $tmp_file");
            unlink($tmp_file);
        }
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

    public function get_icon($id)
    {
        $archive = $this->get_image_archive();

        return is_null($archive) ? null : $archive->get_archive_url($id);
    }

    /**
     * @param array $channel_row
     * @param string $picons_source
     * @param string $default
     * @return string
     */
    public function get_channel_picon($channel_row, $picons_source, $default = DEFAULT_CHANNEL_ICON_PATH)
    {
        if ($picons_source !== XMLTV_PICONS) {
            // playlist icons first in priority
            $icon_url = $channel_row[COLUMN_ICON];
        }

        // if selected xmltv or combined mode look into xmltv source
        // in combined mode search is not performed if already got picon from playlist
        if ($picons_source === XMLTV_PICONS || ($picons_source === COMBINED_PICONS && empty($icon_url))) {
            $epg_ids = self::make_epg_ids($channel_row);
            $icon_url = $this->get_epg_manager()->get_picon($epg_ids);
            if (empty($icon_url)) {
                hd_debug_print("no picon for " . pretty_json_format($epg_ids), true);
            }
        }

        return empty($icon_url) ? $default : $icon_url;
    }

    public function get_image_archive()
    {
        return Default_Archive::get_image_archive(self::ARCHIVE_ID, self::ARCHIVE_URL_PREFIX);
    }

    public function make_name($storage, $id = '')
    {
        if (!empty($id)) {
            $id = "_$id";
        }

        return $this->get_active_playlist_id() . "_$storage$id";
    }

    /**
     * @param string $id
     */
    public static function get_group_media_url_str($id)
    {
        switch ($id) {
            case TV_FAV_GROUP_ID:
                return Starnet_Tv_Favorites_Screen::get_media_url_string(TV_FAV_GROUP_ID);

            case TV_HISTORY_GROUP_ID:
                return Starnet_Tv_History_Screen::get_media_url_string(TV_HISTORY_GROUP_ID);

            case TV_CHANGED_CHANNELS_GROUP_ID:
                return Starnet_Tv_Changed_Channels_Screen::get_media_url_string(TV_CHANGED_CHANNELS_GROUP_ID);

            case VOD_GROUP_ID:
                return Starnet_Vod_Category_List_Screen::get_media_url_string(VOD_GROUP_ID);

            case VOD_FAV_GROUP_ID:
                return Starnet_Vod_Favorites_Screen::get_media_url_string(VOD_FAV_GROUP_ID);

            case VOD_HISTORY_GROUP_ID:
                return Starnet_Vod_History_Screen::get_media_url_string(VOD_HISTORY_GROUP_ID);

            case VOD_SEARCH_GROUP_ID:
                return Starnet_Vod_Search_Screen::get_media_url_string(VOD_SEARCH_GROUP_ID);

            case VOD_FILTER_GROUP_ID:
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
    public function refresh_playlist_menu($handler)
    {
        $title = TR::t('playlist_name_msg__1', $this->get_playlist_parameter($this->get_active_playlist_id(), PARAM_NAME));
        $menu_items[] = $this->create_menu_item($handler, ACTION_RELOAD, $title, "refresh.png",
            array(ACTION_RELOAD_SOURCE => Starnet_Edit_Playlists_Screen::SCREEN_EDIT_PLAYLIST));
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

        $menu_items = array();
        if ($group_id !== null) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");
            $menu_items[] = $this->create_menu_item($handler, ACTION_SORT_POPUP, TR::t('sort_popup_menu'), "sort.png");
            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

            if ($is_classic) {
                if ($group_id === TV_CHANGED_CHANNELS_GROUP_ID) {
                    $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_changed'), "brush.png");
                }
            } else {
                if ($group_id === TV_FAV_GROUP_ID && $this->get_channels_order_count(TV_FAV_GROUP_ID) !== 0) {
                    $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "brush.png");
                } else if ($group_id === TV_HISTORY_GROUP_ID && $this->get_tv_history_count() !== 0) {
                    $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_history'), "brush.png");
                } else if ($group_id === TV_CHANGED_CHANNELS_GROUP_ID) {
                    $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_changed'), "brush.png");
                }
            }

            $menu_items = array_merge($menu_items, $this->edit_hidden_menu($handler, $group_id));
            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        }

        if ($is_classic) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_CHANGE_GROUP_ICON, TR::t('change_group_icon'), "image.png");
        }
        $menu_items[] = $this->create_menu_item($handler, CONTROL_CATEGORY_SCREEN, TR::t('setup_category_title'), "settings.png");
        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

        $menu_items[] = $this->create_menu_item($handler,
            ACTION_ITEMS_EDIT,
            TR::t('setup_channels_src_edit_playlists'),
            "m3u_file.png",
            array(CONTROL_ACTION_EDIT => Starnet_Edit_Playlists_Screen::SCREEN_EDIT_PLAYLIST));

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
                $is_xmltv_engine = $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === ENGINE_XMLTV;
                $engine = TR::load(($is_xmltv_engine ? 'setup_epg_cache_xmltv' : 'setup_epg_cache_json'));
                $menu_items[] = $this->create_menu_item($handler,
                    ACTION_EPG_CACHE_ENGINE, TR::t('setup_epg_cache_engine__1', $engine), "engine.png");

                if ($preset_cnt > 1) {
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

            if ($provider->getConfigValue(PROVIDER_EXT_PARAMS) === true) {
                $menu_items[] = $this->create_menu_item($handler,
                    ACTION_EDIT_PROVIDER_EXT_DLG,
                    TR::t('edit_ext_account'),
                    "settings.png",
                    array(PARAM_PROVIDER => $provider->getId(), COLUMN_PLAYLIST_ID => $provider->get_provider_playlist_id())
                );
            }
        }

        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        if (!$is_classic) {
            $menu_items[] = $this->create_menu_item($handler, CONTROL_INTERFACE_NEWUI_SCREEN, TR::t('setup_interface_newui_title'), "settings.png");
        }
        $menu_items[] = $this->create_menu_item($handler, ACTION_SETTINGS,TR::t('entry_setup'), "settings.png");

        file_put_contents(get_temp_path('menu.json'), json_encode($menu_items));
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

        $cnt = $this->get_channels_count($group_id, PARAM_DISABLED);
        hd_debug_print("Disabled channels in $group_id: $cnt", true);
        if ($cnt !== 0) {
            $menu_items[] = $this->create_menu_item($handler,
                ACTION_ITEMS_EDIT,
                TR::t('tv_screen_edit_hidden_channels'),
                "edit.png",
                array(CONTROL_ACTION_EDIT => Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_CHANNELS));
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
     * @return array|null
     */
    public function do_edit_list_screen($source_screen_id, $action_edit, $media_url = null)
    {
        $post_action = null;
        switch ($action_edit) {
            case Starnet_Edit_Hidden_List_Screen::SCREEN_EDIT_HIDDEN_CHANNELS:
                $params['screen_id'] = Starnet_Edit_Hidden_List_Screen::ID;
                $params['end_action'] = ACTION_REFRESH_SCREEN;
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

            case Starnet_Edit_Playlists_Screen::SCREEN_EDIT_PLAYLIST:
                $params['screen_id'] = Starnet_Edit_Playlists_Screen::ID;
                $params['allow_order'] = true;
                $params['end_action'] = ACTION_INVALIDATE;
                $params['cancel_action'] = RESET_CONTROLS_ACTION_ID;
                $params['extension'] = PLAYLIST_PATTERN;
                $title = TR::t('setup_channels_src_edit_playlists');
                $active_key = $this->get_active_playlist_id();
                if (!empty($active_key) && $this->is_playlist_exist($active_key)) {
                    $handler = User_Input_Handler_Registry::get_instance()->get_registered_handler(Starnet_Edit_Playlists_Screen::ID);
                    $post_action = User_Input_Handler_Registry::create_action($handler,ACTION_INVALIDATE, null, array('playlist_id' => $active_key));
                }
                break;

            case Starnet_Edit_Xmltv_List_Screen::SCREEN_EDIT_XMLTV_LIST:
                $params['screen_id'] = Starnet_Edit_Xmltv_List_Screen::ID;
                $params['end_action'] = ACTION_RELOAD;
                $params['cancel_action'] = RESET_CONTROLS_ACTION_ID;
                $title = TR::t('setup_edit_xmltv_list');
                break;

            default:
                return null;
        }

        $params['source_window_id'] = $source_screen_id;
        $params['source_media_url_str'] = $source_screen_id;
        $params['edit_list'] = $action_edit;
        $params['windowCounter'] = 1;

        return Action_Factory::open_folder(MediaURL::encode($params), $title, null, null, $post_action);
    }

    /**
     * @param string $channel_id
     * @param bool $classic
     * @return array|null
     */
    public function do_show_channel_info($channel_id, $classic = true)
    {
        $channel_row = $this->get_channel_info($channel_id, true);
        if (empty($channel_row)) {
            return null;
        }

        $picons_source = $this->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS);
        $icon = $this->get_channel_picon($channel_row, $picons_source, $this->get_default_channel_icon($classic));

        $info = "ID: " . $channel_row[COLUMN_CHANNEL_ID] . PHP_EOL;
        $info .= "Name: " . $channel_row[COLUMN_TITLE] . PHP_EOL;
        $info .= "Archive: " . $channel_row[M3uParser::COLUMN_ARCHIVE] . " days" . PHP_EOL;
        $info .= "Protected: " . TR::load(SwitchOnOff::to_def($channel_row[M3uParser::COLUMN_ADULT])) . PHP_EOL;
        $info .= "EPG IDs: " . implode(', ', self::make_epg_ids($channel_row)) . PHP_EOL;
        if ($channel_row[M3uParser::COLUMN_TIMESHIFT] != 0) {
            $info .= "Timeshift hours: {$channel_row[M3uParser::COLUMN_TIMESHIFT]}" . PHP_EOL;
        }
        $info .= "Category: {$channel_row[COLUMN_GROUP_ID]}" . PHP_EOL;
        $info .= "Icon: " . wrap_string_to_lines($icon, 70) . PHP_EOL;
        $info .= PHP_EOL;

        try {
            $live_url = $this->generate_stream_url($channel_row, -1, true);
            $info .= "Live URL: " . wrap_string_to_lines($live_url, 70) . PHP_EOL;
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        if ($channel_row[M3uParser::COLUMN_ARCHIVE] > 0) {
            try {
                $archive_url = $this->generate_stream_url($channel_row, time() - 3600, true);
                $info .= "Archive URL: " . wrap_string_to_lines($archive_url, 70) . PHP_EOL;
            } catch (Exception $ex) {
                print_backtrace_exception($ex);
            }
        }

        $dune_params = $this->generate_dune_params($channel_id,
            json_decode(safe_get_value($channel_row, M3uParser::COLUMN_EXT_PARAMS), true));
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
            TR::load('scroll_page')
        );
        Control_Factory::add_smart_label($defs, '', $text);
        Control_Factory::add_vgap($defs, -80);

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

        $all_sources = $this->get_xmltv_sources(XMLTV_SOURCE_ALL);
        $selected_sources = $this->get_selected_xmltv_sources();
        $active_sources = new Hashed_Array();
        foreach ($selected_sources as $key) {
            $item = $all_sources->get($key);
            if ($item === null) continue;

            $item[PARAM_HASH] = Hashed_Array::hash($item[PARAM_URI]);
            $active_sources->set($key, $item);
        }

        return $active_sources;
    }

    public function cleanup_active_xmltv_source()
    {
        hd_debug_print(null, true);
        $locks = $this->epg_manager->is_any_index_locked();
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

        $is_json_engine = $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === ENGINE_JSON;
        $use_playlist_picons = $this->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS);

        if ($is_json_engine && $use_playlist_picons === PLAYLIST_PICONS) {
            hd_debug_print("No need to cleanup");
            return;
        }

        $playlist_sources = $this->get_xmltv_sources_hash(XMLTV_SOURCE_PLAYLIST);
        $ext_sources = $this->get_xmltv_sources_hash(XMLTV_SOURCE_EXTERNAL);
        $all_sources = array_unique(array_merge($playlist_sources, $ext_sources));
        hd_debug_print("Load All XMLTV sources keys: " . json_encode($all_sources));

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
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///

    /**
     * @param string $filename
     * @return bool
     */
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
            $table_name = self::PLAYLISTS_TABLE;
            $this->sql_params->exec("DELETE FROM $table_name WHERE playlist_id = '$playlist_id'");
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
        return $this->get_active_playlist_id() . "_$source";
    }

    /**
     * @return array
     */
    public static function get_id_detect_mapper()
    {
        return array(
            CONTROL_DETECT_ID => TR::load('detect'),
            ATTR_CHANNEL_HASH => TR::load('hash_url'),
            ATTR_CUID => TR::load('attribute_name__1', ATTR_CHANNEL_ID),
            ATTR_TVG_ID => TR::load('attribute_name__1', ATTR_TVG_ID),
            ATTR_TVG_NAME => TR::load('attribute_name__1', ATTR_TVG_NAME),
            ATTR_CHANNEL_NAME => TR::load('channel_name')
        );
    }

    public function collect_detect_info($db, &$detect_info)
    {
        $table_name = M3uParser::CHANNELS_TABLE;
        $entries_cnt = $db->query_value("SELECT COUNT(*) FROM $table_name;");

        $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();
        $stat = M3uParser::detectBestChannelId($db);

        $detect_info = TR::load('channels__1', $entries_cnt) . PHP_EOL;
        $max_dupes = $entries_cnt + 1;
        foreach ($stat as $key => $value) {
            if ($key === ATTR_PARSED_ID) continue;
            if ($value === -1) {
                $detect_info .= TR::load('duplicates__1', $mapper_ops[$key]) . PHP_EOL;
                continue;
            }

            $detect_info .= TR::load('duplicates__2', $mapper_ops[$key], $value) . PHP_EOL;
            if ($value < $max_dupes) {
                $max_dupes = $value;
                $minkey = $key;
            }
        }

        $minkey = empty($minkey) ? ATTR_CHANNEL_HASH : $minkey;
        hd_debug_print("Best ID: $minkey");
        $detect_info .= PHP_EOL . TR::load('selected__1', $mapper_ops[$minkey]) . PHP_EOL;

        return $minkey;
    }

    public static function make_epg_ids($channel_row)
    {
        return array('epg_id' => $channel_row[M3uParser::COLUMN_EPG_ID], 'id' => $channel_row[COLUMN_CHANNEL_ID], 'name' => $channel_row[COLUMN_TITLE]);
    }

    public static function is_special_group_id($group_id)
    {
        return ($group_id === TV_ALL_CHANNELS_GROUP_ID
            || $group_id === TV_FAV_GROUP_ID
            || $group_id === TV_HISTORY_GROUP_ID
            || $group_id === TV_CHANGED_CHANNELS_GROUP_ID
            || $group_id === VOD_GROUP_ID);
    }
}
