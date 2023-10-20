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
require_once 'user_input_handler_registry.php';
require_once 'action_factory.php';
require_once 'control_factory.php';
require_once 'control_factory_ext.php';
require_once 'default_archive.php';
require_once 'catchup_params.php';
require_once 'epg_manager_sql.php';
require_once 'm3u/M3uParser.php';

class Default_Dune_Plugin implements DunePlugin
{
    const AUTHOR_LOGO = "ProIPTV by sharky72  [ ´¯¤¤¯(ºº)¯¤¤¯` ]";
    const SANDWICH_BASE = 'gui_skin://special_icons/sandwich_base.aai';
    const SANDWICH_MASK = 'cut_icon://{name=sandwich_mask}';
    const SANDWICH_COVER = 'cut_icon://{name=sandwich_cover}';
    const RESOURCE_URL = 'http://iptv.esalecrm.net/res';
    const ARCHIVE_URL_PREFIX = 'http://iptv.esalecrm.net/res';
    const ARCHIVE_ID = 'common';

    /////////////////////////////////////////////////////////////////////////////
    // views variables
    const TV_SANDWICH_WIDTH = 246;
    const TV_SANDWICH_HEIGHT = 140;

    private $plugin_cookies;
    private $internet_status = -2;
    private $opexec_id = -1;

    /**
     * @var array
     */
    public $plugin_info;

    /**
     * @var M3uParser
     */
    protected $m3u_parser;

    /**
     * @var Epg_Manager|Epg_Manager_Sql
     */
    protected $epg_manager;

    /**
     * @var Starnet_Tv
     */
    public $tv;

    /**
     * @var Playback_Points
     */
    protected $playback_points;

    /**
     * @var array
     */
    protected $screens;

    /**
     * @var array
     */
    protected $screens_views;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var array
     */
    protected $postpone_save;

    /**
     * @var array
     */
    protected $is_durty;

    ///////////////////////////////////////////////////////////////////////

    protected function __construct()
    {
        $this->plugin_info = get_plugin_manifest_info();
        $this->postpone_save = array(PLUGIN_PARAMETERS => false, PLUGIN_SETTINGS => false);
        $this->is_durty = array(PLUGIN_PARAMETERS => false, PLUGIN_SETTINGS => false);
        $this->m3u_parser = new M3uParser();
    }

    public function set_plugin_cookies($plugin_cookies)
    {
        $this->plugin_cookies = $plugin_cookies;
    }

    public function get_plugin_cookies()
    {
        return $this->plugin_cookies;
    }

    public function set_internet_status($internet_status)
    {
        $this->internet_status = $internet_status;
    }

    public function get_internet_status()
    {
        return $this->internet_status;
    }

    public function set_opexec_id($opexec_id)
    {
        $this->opexec_id = $opexec_id;
    }

    public function get_opexec_id()
    {
        return $this->opexec_id;
    }

    public function upgrade_parameters(&$plugin_cookies)
    {
        $this->load(PLUGIN_PARAMETERS);
        if (isset($plugin_cookies->pass_sex)) {
            $this->set_parameter(PARAM_ADULT_PASSWORD, $plugin_cookies->pass_sex);
            unset($plugin_cookies->pass_sex);
        } else {
            $this->set_parameter(PARAM_ADULT_PASSWORD, '0000');
        }
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Screen support.
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * @param $object
     * @return void
     */
    public function create_screen($object)
    {
        if (!is_null($object) && method_exists($object, 'get_id')) {
            $this->add_screen($object);
            User_Input_Handler_Registry::get_instance()->register_handler($object);
        } else {
            hd_debug_print(get_class($object) . ": Screen class is illegal. get_id method not defined!");
        }
    }

    public function init_epg_manager()
    {
        if (class_exists('SQLite3') && $this->get_parameter(PARAM_EPG_CACHE_ENGINE, ENGINE_SQLITE) === ENGINE_SQLITE) {
            hd_debug_print("Using sqlite cache engine");
            $this->epg_manager = new Epg_Manager_Sql($this->plugin_info['app_version'], $this->get_cache_dir(), $this->get_active_xmltv_source());
        } else {
            hd_debug_print("Using legacy cache engine");
            $this->epg_manager = new Epg_Manager($this->plugin_info['app_version'], $this->get_cache_dir(), $this->get_active_xmltv_source());
        }

        $flags = $this->get_bool_parameter(PARAM_FUZZY_SEARCH_EPG, false) ? EPG_FUZZY_SEARCH : 0;
        $flags |= $this->get_bool_parameter(PARAM_FAKE_EPG, false) ? EPG_FAKE_EPG : 0;
        $this->epg_manager->set_flags($flags);
        $this->epg_manager->set_cache_ttl($this->get_setting(PARAM_EPG_CACHE_TTL, 3));
    }

    /**
     * @return M3uParser
     */
    public function get_m3u_parser()
    {
        return $this->m3u_parser;
    }

    /**
     * @return Epg_Manager|Epg_Manager_Sql
     */
    public function get_epg_manager()
    {
        return $this->epg_manager;
    }

    /**
     * @param Screen $scr
     * @return void
     */
    protected function add_screen(Screen $scr)
    {
        if (isset($this->screens[$scr->get_id()])) {
            hd_debug_print("Error: screen (id: " . $scr->get_id() . ") already registered.");
        } else {
            $this->screens[$scr->get_id()] = $scr;
        }
    }

    /**
     * @return array const
     */
    public function get_screens()
    {
        return $this->screens;
    }

    /**
     * @param string $screen_id
     * @return Screen
     * @throws Exception
     */
    protected function get_screen_by_id($screen_id)
    {
        hd_debug_print(null, true);

        if (isset($this->screens[$screen_id])) {
            hd_debug_print("'$screen_id'", true);
            return $this->screens[$screen_id];
        }

        hd_debug_print("Error: no screen with id '$screen_id' found.");
        HD::print_backtrace();
        throw new Exception('Screen not found');
    }

    /**
     * @param MediaURL $media_url
     * @return Screen
     * @throws Exception
     */
    protected function get_screen_by_url(MediaURL $media_url)
    {
        $screen_id = isset($media_url->screen_id) ? $media_url->screen_id : $media_url->get_raw_string();

        return $this->get_screen_by_id($screen_id);
    }

    ///////////////////////////////////////////////////////////////////////////
    //
    // DunePlugin implementations
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param $user_input
     * @param $plugin_cookies
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
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_folder_view($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $decoded_media_url = MediaURL::decode($media_url);
        return $this->get_screen_by_url($decoded_media_url)->get_folder_view($decoded_media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_next_folder_view($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $decoded_media_url = MediaURL::decode($media_url);

        return $this->get_screen_by_url($decoded_media_url)->get_next_folder_view($decoded_media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param int $from_ndx
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_regular_folder_items($media_url, $from_ndx, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $decoded_media_url = MediaURL::decode($media_url);

        return $this->get_screen_by_url($decoded_media_url)->get_folder_range($decoded_media_url, $from_ndx, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_tv_info($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (is_null($this->tv)) {
            hd_debug_print("TV is not supported");
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        $decoded_media_url = MediaURL::decode($media_url);

        return $this->tv->get_tv_info($decoded_media_url);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return string
     * @throws Exception
     */
    public function get_tv_stream_url($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (is_null($this->tv)) {
            hd_debug_print("TV is not supported");
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $media_url;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $channel_id
     * @param int $archive_tm_sec
     * @param string $protect_code
     * @param $plugin_cookies
     * @return string
     * @throws Exception
     */
    public function get_tv_playback_url($channel_id, $archive_tm_sec, $protect_code, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (is_null($this->tv)) {
            hd_debug_print("TV is not supported");
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $this->tv->get_tv_playback_url($channel_id, $archive_tm_sec, $protect_code);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $channel_id
     * @param int $day_start_tm_sec
     * @param $plugin_cookies
     * @return array
     */
    public function get_day_epg($channel_id, $day_start_tm_sec, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $day_epg = array();
        try {
            if (is_null($this->tv)) {
                hd_debug_print("TV is not supported");
                HD::print_backtrace();
                throw new Exception('TV is not supported');
            }

            // get channel by hash
            $channel = $this->tv->get_channel($channel_id);

            // correct day start to local timezone
            $day_start_tm_sec -= get_local_time_zone_offset();

            // get personal time shift for channel
            $time_shift = 3600 * ($channel->get_timeshift_hours() + $this->get_setting(PARAM_EPG_SHIFT, 0));
            hd_debug_print("EPG time shift $time_shift", true);
            $day_start_tm_sec += $time_shift;

            if (LogSeverity::$is_debug) {
                hd_debug_print("day_start timestamp: $day_start_tm_sec (" . format_datetime("Y-m-d H:i", $day_start_tm_sec) . ")");
            }

            foreach ($this->epg_manager->get_day_epg_items($channel, $day_start_tm_sec) as $time => $value) {
                $tm_start = (int)$time + $time_shift;
                $tm_end = (int)$value[Epg_Params::EPG_END] + $time_shift;
                $day_epg[] = array(
                    PluginTvEpgProgram::start_tm_sec => $tm_start,
                    PluginTvEpgProgram::end_tm_sec => $tm_end,
                    PluginTvEpgProgram::name => $value[Epg_Params::EPG_NAME],
                    PluginTvEpgProgram::description => $value[Epg_Params::EPG_DESC],
                );

                if (LogSeverity::$is_debug) {
                    hd_debug_print(format_datetime("m-d H:i", $tm_start) . " - " . format_datetime("m-d H:i", $tm_end) . " {$value[Epg_Params::EPG_NAME]}");
                }
            }
        } catch (Exception $ex) {
            $msg = $ex->getMessage();
            if (!empty($msg)) {
                hd_debug_print($msg);
            }
        }

        return $day_epg;
    }

    /**
     * @override DunePlugin
     * @param $channel_id
     * @param $program_ts
     * @param $plugin_cookies
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

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $op_type
     * @param string $channel_id
     * @param $plugin_cookies
     * @return array
     */
    public function change_tv_favorites($op_type, $channel_id, &$plugin_cookies = null)
    {
        hd_debug_print(null, true);

        if (is_null($this->tv)) {
            hd_debug_print("TV is not supported");
            HD::print_backtrace();
            return array();
        }

        return $this->tv->change_tv_favorites($op_type, $channel_id);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_vod_info($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("VOD is not supported");

        HD::print_backtrace();
        throw new Exception("VOD is not supported");
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return string
     */
    public function get_vod_stream_url($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("VOD is not supported");

        return '';
    }

    ///////////////////////////////////////////////////////////////////////
    // Settings storage methods
    //

    /**
     * Block or release save settings action
     * If released will perform save action
     *
     * @param bool $snooze
     * @param string $item
     */
    public function set_postpone_save($snooze, $item = PLUGIN_SETTINGS)
    {
        hd_debug_print(null, true);
        hd_debug_print("Snooze: " . var_export($snooze, true) . ", item: $item", true);
        $this->postpone_save[$item] = $snooze;
        if ($snooze) {
            return;
        }

        if ($item === PLUGIN_SETTINGS) {
            $this->save_settings();
        } else {
            $this->save_parameters();
        }
    }

    /**
     * Is settings contains unsaved changes
     *
     * @return bool
     */
    public function is_durty($item = PLUGIN_SETTINGS)
    {
        return $this->is_durty[$item];
    }

    /**
     * Is settings contains unsaved changes
     *
     * @param bool $val
     * @param string $item
     */
    public function set_durty($val = true, $item = PLUGIN_SETTINGS)
    {
        hd_debug_print(null, true);
        $this->is_durty[$item] = $val;
    }

    /**
     * Get settings for selected playlist
     *
     * @param string $type
     * @param mixed|null $default
     * @return mixed
     */
    public function &get_setting($type, $default = null)
    {
        $this->load(PLUGIN_SETTINGS);

        if (!isset($this->settings[$type])) {
            $this->settings[$type] = $default;
        } else {
            $default_type = gettype($default);
            $param_type = gettype($this->settings[$type]);
            if ($default_type === 'object' && $param_type !== $default_type) {
                hd_debug_print("Settings type requested: $default_type. But $param_type loaded. Reset to default", true);
                $this->settings[$type] = $default;
            }
        }

        return $this->settings[$type];
    }

    /**
     * Set settings for selected playlist
     *
     * @param string $type
     * @param mixed $val
     */
    public function set_setting($type, $val)
    {
        $this->settings[$type] = $val;
        $this->set_durty();
        $this->save_settings();
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
        return $this->get_setting($type,
                $default ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on;
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
     * Remove setting for selected playlist
     *
     * @param string $type
     */
    public function remove_setting($type)
    {
        unset($this->settings[$type]);
        $this->set_durty();
        $this->save_settings();
    }

    /**
     * Remove settings for selected playlist
     */
    public function remove_settings()
    {
        unset($this->settings);
        $hash = $this->get_current_playlist_hash();
        hd_debug_print("remove $hash.settings", true);
        HD::erase_data_items("$hash.settings");
        foreach (glob_dir(get_cached_image_path(), "/^$hash.*$/i") as $file) {
            unlink($file);
        }
    }

    ///////////////////////////////////////////////////////////////////////
    // Parameters storage methods

    /**
     * load plugin/playlist settings
     *
     * @param string $item
     * @param bool $force
     * @return void
     */
    public function load($item, $force = false)
    {
        if ($force) {
            $this->{$item} = null;
        }

        $name = (($item === PLUGIN_SETTINGS) ? $this->get_current_playlist_hash() : 'common') . '.settings';

        if (is_null($this->{$item})) {
            hd_debug_print("Load: $name", true);
            $this->{$item} = HD::get_data_items($name, true, false);
            if (LogSeverity::$is_debug) {
                foreach ($this->{$item} as $key => $param) hd_debug_print("$key => $param");
            }
        }
    }

    /**
     * save playlist settings
     *
     * @param bool $force
     * @return void
     */
    public function save_settings($force = false)
    {
        hd_debug_print(null, true);
        $this->save($this->get_current_playlist_hash() . '.settings', PLUGIN_SETTINGS, $force);
    }

    /**
     * save plugin parameters
     *
     * @param bool $force
     * @return void
     */
    public function save_parameters($force = false)
    {
        hd_debug_print(null, true);
        $this->save('common.settings', PLUGIN_PARAMETERS, $force);
    }

    /**
     * save data
     * @param string $name
     * @param string $type
     * @param bool $force
     * @return void
     */
    private function save($name, $type, $force = false)
    {
        if (is_null($this->{$type})) {
            hd_debug_print("this->$type is not set!", true);
            return;
        }

        if ($this->postpone_save[$type] && !$force) {
            return;
        }

        if ($force || $this->is_durty($type)) {
            $this->set_durty(false, $type);
            hd_debug_print("Save: $name", true);
            foreach ($this->{$type} as $key => $param) hd_debug_print("$key => $param", true);
            HD::put_data_items($name, $this->{$type}, false);
        }
    }

    /**
     * Get plugin parameters
     * Parameters does not depend on playlists and used globally
     *
     * @param string $param
     * @param mixed|null $default
     * @return mixed
     */
    public function &get_parameter($param, $default = null)
    {
        $this->load(PLUGIN_PARAMETERS);

        if (!isset($this->parameters[$param])) {
            hd_debug_print("load default: $param = $default", true);
            $this->parameters[$param] = $default;
        } else {
            $default_type = gettype($default);
            $param_type = gettype($this->parameters[$param]);
            if ($default_type === 'object' && $param_type !== $default_type) {
                hd_debug_print("Parameter type requested: $default_type. But $param_type loaded. Reset to default", true);
                $this->parameters[$param] = $default;
            }
        }

        return $this->parameters[$param];
    }

    /**
     * Get plugin parameter type
     *
     * @param string $param
     * @return string|null
     */
    public function get_parameter_type($param)
    {
        $this->load(PLUGIN_PARAMETERS);

        if (!isset($this->parameters[$param])) {
            return null;
        }

        $type = gettype($this->parameters[$param]);
        return ($type === 'object') ? get_class($this->parameters[$param]) : $type;
    }

    /**
     * set plugin parameters
     *
     * @param string $param
     * @param mixed $val
     */
    public function set_parameter($param, $val)
    {
        $this->parameters[$param] = $val;
        $this->set_durty(true,PLUGIN_PARAMETERS);
        $this->save_parameters();
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

    /**
     * Remove parameter
     * @param string $param
     */
    public function remove_parameter($param)
    {
        unset($this->parameters[$param]);
        $this->set_durty(true,PLUGIN_PARAMETERS);
        $this->save_parameters();
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

    ///////////////////////////////////////////////////////////////////////
    // Methods

    /**
     * @return void
     */
    public function init_plugin()
    {
        hd_print("----------------------------------------------------");
        $this->load(PLUGIN_PARAMETERS, true);
        $this->update_log_level();
        if (LogSeverity::$is_debug) {
            // small hack to show parameters in log
            $this->load(PLUGIN_PARAMETERS, true);
        }

        $this->init_epg_manager();
        $this->create_screen_views();
        $this->playback_points = new Playback_Points($this);

        hd_debug_print("Init plugin done!");
        hd_print("----------------------------------------------------");
    }

    /**
     * Initialize and parse selected playlist
     *
     * @return bool
     */
    public function init_playlist()
    {
        $this->init_user_agent();

        // first check if playlist in cache
        if ($this->get_playlists()->size() === 0) {
            hd_debug_print("No playlists!");
            return false;
        }

        $force = false;
        $tmp_file = $this->get_current_playlist_cache();
        if (file_exists($tmp_file)) {
            $mtime = filemtime($tmp_file);
            $diff = time() - $mtime;
            if ($diff > 3600) {
                hd_debug_print("Playlist cache expired " . ($diff - 3600) . " sec ago. Timestamp $mtime. Forcing reload");
                unlink($tmp_file);
                $force = true;
            }
        } else {
            $force = true;
        }

        try {
            if ($force !== false) {
                $url = $this->get_current_playlist();
                if (empty($url)) {
                    hd_debug_print("Tv playlist not defined");
                    throw new Exception("Tv playlist not defined");
                }

                hd_debug_print("m3u playlist ({$this->get_current_playlist_hash()} - {$this->get_playlist_name($url)}): $url");
                if (preg_match(HTTP_PATTERN, $url)) {
                    $user_agent = HD::get_dune_user_agent();
                    $cmd = get_install_path('bin/https_proxy.sh') . " '$url' '$tmp_file' '$user_agent'";
                    hd_debug_print("Exec: $cmd", true);
                    shell_exec($cmd);
                    if (!file_exists($tmp_file)) {
                        throw new Exception("Can't download playlist $url");
                    }
                } else {
                    $contents = @file_get_contents($url);
                    if ($contents === false) {
                        throw new Exception("Can't read playlist: $url");
                    }
                    file_put_contents($tmp_file, $contents);
                    $mtime = filemtime($tmp_file);
                    hd_debug_print("Save $tmp_file (timestamp: $mtime)");
                }
            }

            //  Is already parsed?
            $this->m3u_parser->setupParser($tmp_file, $force);
            if ($this->m3u_parser->getEntriesCount() === 0) {
                if (!$this->m3u_parser->parseInMemory()) {
                    HD::set_last_error("Ошибка чтения плейлиста!");
                    throw new Exception("Can't read playlist");
                }

                $count = $this->m3u_parser->getEntriesCount();
                if ($count === 0) {
                    HD::set_last_error("Пустой плейлист!");
                    hd_debug_print('Empty playlist');
                    $this->clear_playlist_cache();
                    throw new Exception("Empty playlist");
                }

                hd_debug_print("Total entries loaded from playlist m3u file: $count");
                HD::ShowMemoryUsage();
            }
        } catch (Exception $ex) {
            hd_debug_print("Unable to load tv playlist: " . $ex->getMessage());
            return false;
        }

        hd_debug_print("Init playlist done!");
        return true;
    }

    /**
     * @return void
     */
    public function init_user_agent()
    {
        $user_agent = $this->get_setting(PARAM_USER_AGENT);
        if (!empty($user_agent) && $user_agent !== HD::get_default_user_agent()) {
            HD::set_dune_user_agent($user_agent);
        }
    }

    /**
     * @return Playback_Points
     */
    public function get_playback_points()
    {
        return $this->playback_points;
    }

    /**
     * @return Ordered_Array
     */
    public function &get_playlists()
    {
        return $this->get_parameter(PARAM_PLAYLISTS, new Ordered_Array());
    }

    /**
     * @var Ordered_Array $playlists
     */
    public function set_playlists($playlists)
    {
        $this->set_parameter(PARAM_PLAYLISTS, $playlists);
    }

    /**
     * $param int $idx
     * @return string
     */
    public function get_playlist_by_idx($idx)
    {
        return $this->get_playlists()->get_item_by_idx($idx);
    }

    /**
     * $param int $idx
     * @return void
     */
    public function set_playlists_idx($idx)
    {
        $this->get_playlists()->set_saved_pos($idx);
        $this->save_parameters(true);
    }

    /**
     * @return string
     */
    public function get_current_playlist()
    {
        return $this->get_playlists()->get_selected_item();
    }

    /**
     * @return string
     */
    public function get_current_playlist_hash()
    {
        return Hashed_Array::hash($this->get_current_playlist());
    }

    /**
     * @return string
     */
    public function get_current_playlist_cache()
    {
        return get_temp_path($this->get_current_playlist_hash() . "_playlist.m3u8");
    }

    /**
     * Clear downloaded playlist
     * @return void
     */
    public function clear_playlist_cache()
    {
        $tmp_file = $this->get_current_playlist_cache();
        if (file_exists($tmp_file)) {
            $this->m3u_parser->setupParser('');
            hd_debug_print("remove $tmp_file", true);
            unlink($tmp_file);
        }
    }

    /**
     * @return Hashed_Array
     */
    public function get_playlists_names()
    {
        return $this->get_parameter(PARAM_PLAYLISTS_NAMES, new Hashed_Array());
    }

    /**
     * @param string $item
     * @return string const
     */
    public function get_playlist_name($item)
    {
        /** @var Hashed_Array $playlist_names */
        $playlist_names = $this->get_parameter(PARAM_PLAYLISTS_NAMES, new Hashed_Array());
        $name = $playlist_names->get(Hashed_Array::hash($item));
        if (is_null($name)) {
            $name = basename($item);
            if (($pos = strpos($name, '?')) !== false) {
                $name = substr($name, 0, $pos);
            }
        }

        return $name;
    }

    /**
     * @param string $item
     * @param string $name
     * @return void
     */
    public function set_playlist_name($item, $name)
    {
        if (empty($name)) {
            $this->remove_parameter(PARAM_PLAYLISTS_NAMES);
        } else {
            /** @var Hashed_Array $playlist_names */
            $playlist_names = $this->get_parameter(PARAM_PLAYLISTS_NAMES, new Hashed_Array());
            $playlist_names->set(Hashed_Array::hash($item), $name);
            $this->set_parameter(PARAM_PLAYLISTS_NAMES, $playlist_names);
        }
    }

    /**
     * @param string $item
     * @return void
     */
    public function remove_playlist_name($item)
    {
        /** @var Hashed_Array $playlist_names */
        $playlist_names = $this->get_parameter(PARAM_PLAYLISTS_NAMES, new Hashed_Array());
        $playlist_names->erase(Hashed_Array::hash($item));
        $this->set_parameter(PARAM_PLAYLISTS_NAMES, $playlist_names);
    }

    /**
     * @param string $item
     * @return string|null
     */
    public function get_xmltv_source_name($item)
    {
        /** @var Hashed_Array $xmltv_sources */
        $xmltv_sources = $this->get_parameter(PARAM_XMLTV_SOURCE_NAMES, new Hashed_Array());
        $name = $xmltv_sources->get(Hashed_Array::hash($item));
        if (is_null($name)) {
            $name = HD::string_ellipsis($item);
        }

        return $name;
    }

    /**
     * @param string $item
     * @param string $name
     * @return void
     */
    public function set_xmltv_source_name($item, $name)
    {
        if (empty($name)) {
            $this->remove_parameter(PARAM_XMLTV_SOURCE_NAMES);
        } else {
            /** @var Hashed_Array $xmltv_sources */
            $xmltv_sources = $this->get_parameter(PARAM_XMLTV_SOURCE_NAMES, new Hashed_Array());
            $xmltv_sources->set(Hashed_Array::hash($item), $name);
            $this->set_parameter(PARAM_XMLTV_SOURCE_NAMES, $xmltv_sources);
        }
    }

    /**
     * @param string $item
     * @return void
     */
    public function remove_xmltv_source_name($item)
    {
        /** @var Hashed_Array $xmltv_sources */
        $xmltv_sources = $this->get_parameter(PARAM_XMLTV_SOURCE_NAMES, new Hashed_Array());
        $xmltv_sources->erase(Hashed_Array::hash($item));
        $this->set_parameter(PARAM_XMLTV_SOURCE_NAMES, $xmltv_sources);
    }

    public function get_special_groups_count()
    {
        $groups_cnt = 0;
        foreach($this->tv->get_special_groups() as $group) {
            if (is_null($group) || $group->is_disabled()) $groups_cnt++;
        }
        return $groups_cnt;
    }

    /**
     * get external xmltv sources
     *
     * @return Ordered_Array
     */
    public function &get_ext_xmltv_sources()
    {
        if ($this->get_parameter_type(PARAM_EXT_XMLTV_SOURCES) === 'Hashed_Array') {
            // convert old type parameter
            /** @var Hashed_Array $old_array */
            $old_array = $this->get_parameter(PARAM_EXT_XMLTV_SOURCES, new Hashed_Array());
            $new_array = new Ordered_Array();
            $new_array->add_items($old_array->get_ordered_values());
            $this->set_parameter(PARAM_EXT_XMLTV_SOURCES, $new_array);
        }

        return $this->get_parameter(PARAM_EXT_XMLTV_SOURCES, new Ordered_Array());
    }

    /**
     * set external xmltv sources
     *
     * @param Hashed_Array $xmltv_sources
     * @return void
     */
    public function set_ext_xmltv_sources($xmltv_sources)
    {
        $this->set_parameter(PARAM_EXT_XMLTV_SOURCES, $xmltv_sources);
    }

    /**
     * get all xmltv source
     *
     * @return Hashed_Array const
     */
    public function get_all_xmltv_sources()
    {
        hd_debug_print(null, true);

        /** @var Hashed_Array $sources */
        $xmltv_sources = new Hashed_Array();
        foreach ($this->m3u_parser->getXmltvSources() as $source) {
            $xmltv_sources->add($source);
        }

        if ($xmltv_sources->size() !== 0) {
            $xmltv_sources->add(EPG_SOURCES_SEPARATOR_TAG);
        }

        foreach ($this->get_ext_xmltv_sources() as $key => $source) {
            $xmltv_sources->set($key, $source);
        }

        foreach ($xmltv_sources as $key => $source) {
            hd_debug_print("$key => $source", true);
        }
        return $xmltv_sources;
    }

    /**
     * @return string
     */
    public function get_active_xmltv_source_key()
    {
        return $this->get_setting(PARAM_XMLTV_SOURCE_KEY, '');
    }

    /**
     * @param string $key
     * @return void
     */
    public function set_active_xmltv_source_key($key)
    {
        $this->set_setting(PARAM_XMLTV_SOURCE_KEY, $key);
    }

    /**
     * @return string
     */
    public function get_active_xmltv_source()
    {
        return $this->get_setting(PARAM_CUR_XMLTV_SOURCE, '');
    }

    /**
     * @param string $source
     * @return void
     */
    public function set_active_xmltv_source($source)
    {
        $this->set_setting(PARAM_CUR_XMLTV_SOURCE, $source);
    }

    /**
     * @return string
     */
    public function get_cache_dir()
    {
        $cache_dir = smb_tree::get_folder_info($this->get_parameter(PARAM_CACHE_PATH));
        if (!is_null($cache_dir) && rtrim($cache_dir, DIRECTORY_SEPARATOR) === get_data_path(EPG_CACHE_SUBDIR)) {
            $this->remove_parameter(PARAM_CACHE_PATH);
            $cache_dir = null;
        }

        if (is_null($cache_dir)) {
            $cache_dir = get_data_path(EPG_CACHE_SUBDIR);
        }

        return $cache_dir;
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
                $this->remove_parameter(PARAM_HISTORY_PATH);
                $path = get_data_path('history');
            }
        }
        hd_debug_print($path, true);

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * @param string|null $path
     * @return void
     */
    public function set_history_path($path = null)
    {
        if (is_null($path) || $path === get_data_path('history')) {
            $this->remove_parameter(PARAM_HISTORY_PATH);
            return;
        }

        create_path($path);
        $this->set_parameter(PARAM_HISTORY_PATH, $path);
    }

    /**
     * @return void
     */
    public function update_log_level()
    {
        set_debug_log($this->get_bool_parameter(PARAM_ENABLE_DEBUG, false));
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Misc.
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * @param array $defs
     */
    public function create_setup_header(&$defs)
    {
        Control_Factory::add_vgap($defs, -10);
        Control_Factory::add_label($defs, self::AUTHOR_LOGO,
            " v.{$this->plugin_info['app_version']} [{$this->plugin_info['app_release_date']}]",
            20);
    }

    /**
     * @return string
     */
    public function get_background_image()
    {
        $background = $this->get_setting(PARAM_PLUGIN_BACKGROUND);
        if ($background === $this->plugin_info['app_background']) {
            $this->remove_setting(PARAM_PLUGIN_BACKGROUND);
        } else if (strncmp($background, get_cached_image_path(), strlen(get_cached_image_path())) === 0) {
            $this->set_setting(PARAM_PLUGIN_BACKGROUND, basename($background));
        } else if (is_null($background) || !file_exists(get_cached_image_path($background))) {
            $background = $this->plugin_info['app_background'];
        } else {
            $background = get_cached_image_path($background);
        }

        return $background;
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
            $this->remove_setting(PARAM_PLUGIN_BACKGROUND);
        } else {
            $this->set_setting(PARAM_PLUGIN_BACKGROUND, $path);
        }
    }

    public function get_icon($id)
    {
        $archive = $this->get_image_archive();

        return is_null($archive) ? null : $archive->get_archive_url($id);
    }

    public function get_image_archive()
    {
        return Default_Archive::get_image_archive(self::ARCHIVE_ID,self::ARCHIVE_URL_PREFIX);
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Screen views parameters
    //
    ///////////////////////////////////////////////////////////////////////

    public function get_screen_view($name)
    {
        return isset($this->screens_views[$name]) ? $this->screens_views[$name] : array();
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $action_id
     * @param string $caption
     * @param string $icon
     * @param $add_params array|null
     * @return array
     */
    public function create_menu_item($handler, $action_id, $caption = null, $icon = null, $add_params = null)
    {
        if ($action_id === GuiMenuItemDef::is_separator) {
            return array($action_id => true);
        }

        if ($icon !== null && strpos("://", $icon) === false) {
            $icon = get_image_path($icon);
        }

        return User_Input_Handler_Registry::create_popup_item($handler, $action_id, $caption, $icon, $add_params);
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function playlist_menu($handler)
    {
        $menu_items = array();

        $cur = $this->get_playlists()->get_saved_pos();
        foreach ($this->get_playlists() as $key => $playlist) {
            if ($key !== 0 && ($key % 15) === 0)
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

            $menu_items[] = $this->create_menu_item($handler,
                ACTION_PLAYLIST_SELECTED,
                $this->get_playlist_name($playlist),
                ($cur !== $key) ? null : "check.png",
                array('list_idx' => $key));
        }

        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function epg_source_menu($handler)
    {
        $menu_items = array();

        $sources = $this->get_all_xmltv_sources();
        $source_key = $this->get_active_xmltv_source_key();

        $idx = 0;
        foreach ($sources as $key => $item) {
            if ($idx !== 0 && ($idx % 15) === 0)
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            $idx++;

            if ($item === EPG_SOURCES_SEPARATOR_TAG) {
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
                continue;
            }

            $menu_items[] = $this->create_menu_item($handler,
                ACTION_EPG_SOURCE_SELECTED,
                $this->get_xmltv_source_name($item),
                ($source_key === $key) ? "check.png" : null,
                array('list_idx' => $key)
            );
        }

        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function sort_menu($handler)
    {
        $menu_items = array();

        $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_SORT, TR::t('sort_channels'), null, array('sort_type' => 'channels'));
        $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_SORT, TR::t('sort_groups'), null, array('sort_type' => 'groups'));
        $menu_items[] = $this->create_menu_item($handler, ACTION_RESET_ITEMS_SORT, TR::t('reset_channels_sort'), null, array('reset_type' => 'channels'));
        $menu_items[] = $this->create_menu_item($handler, ACTION_RESET_ITEMS_SORT, TR::t('reset_groups_sort'), null, array('reset_type' => 'groups'));
        $menu_items[] = $this->create_menu_item($handler, ACTION_RESET_ITEMS_SORT, TR::t('reset_all_sort'), null, array('reset_type' => 'all'));
        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $group_id
     * @return array
     */
    public function edit_hidden_menu($handler, $group_id)
    {
        $has_hidden = false;
        $menu_items = array();
        $group = $this->tv->get_group($group_id);
        if ($this->tv->get_disabled_group_ids()->size() !== 0) {
            hd_debug_print("Disabled groups: " . $this->tv->get_disabled_group_ids()->size(), true);
            $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_EDIT,
                TR::t('tv_screen_edit_hidden_group'), "edit.png", array('action_edit' => Starnet_Edit_List_Screen::SCREEN_EDIT_GROUPS));
        }

        if ($group_id === ALL_CHANNEL_GROUP_ID) {
            $has_hidden = $this->tv->get_disabled_channel_ids()->size() !== 0;
            hd_debug_print("Disabled channels: " . $this->tv->get_disabled_channel_ids()->size(), true);
        } else if ($group !== null && $this->tv->get_special_group($group_id) === null) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_group'), "hide.png");
            $has_hidden = $group->get_group_channels()->size() !== $group->get_items_order()->size();
        }

        if ($has_hidden) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_EDIT,
                TR::t('tv_screen_edit_hidden_channels'), "edit.png", array('action_edit' => Starnet_Edit_List_Screen::SCREEN_EDIT_CHANNELS) );
        }

        if (!empty($menu_items)) {
            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        }

        return $menu_items;
    }

    public function create_plugin_title()
    {
        $name = $this->get_playlist_name($this->get_current_playlist());
        if (is_null($name)) {
            $name = basename($this->get_current_playlist());
        }
        $plugin_name = $this->plugin_info['app_caption'];
        $name = empty($name) ? $plugin_name : "$plugin_name ($name)";
        hd_debug_print("plugin title: $name");
        return $name;
    }

    /**
     * @return void
     */
    public function create_screen_views()
    {
        hd_debug_print(null, true);

        $background = $this->get_background_image();
        hd_debug_print("Selected background: $background", true);

        $this->screens_views = array(

            // 1x10 title list view with right side icon
            'list_1x11_small_info' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 11,
                    ViewParams::paint_icon_selection_box=> true,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewParams::help_line_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_TURQUOISE,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::zoom_detailed_icon => false,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_width => 60,
                    ViewItemParams::icon_height => 60,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::item_caption_dx => 25,
                    ViewItemParams::item_caption_width => 1100,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            'list_1x11_info' => array(
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 11,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewParams::help_line_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_TURQUOISE,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 70,
                    ViewItemParams::icon_height => 70,
                    ViewItemParams::item_caption_dx => 30,
                    ViewItemParams::item_caption_width => 1100,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'list_2x11_small_info' => array(
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 2,
                    ViewParams::num_rows => 11,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewParams::help_line_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_TURQUOISE,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 70,
                    ViewItemParams::icon_height => 70,
                    ViewItemParams::item_caption_dx => 74,
                    ViewItemParams::item_caption_width => 550,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'list_3x11_no_info' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 11,
                    ViewParams::paint_details => false,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 70,
                    ViewItemParams::icon_height => 70,
                    ViewItemParams::item_caption_dx => 97,
                    ViewItemParams::item_caption_width => 600,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_5x3_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 3,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::content_box_padding_left => 70,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_5x3_no_caption' => array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_5x4_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 4,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::content_box_padding_left => 70,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 0.9,
                    ViewItemParams::icon_sel_scale_factor => 1.0,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_5x4_no_caption' => array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 4,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 0.9,
                    ViewItemParams::icon_sel_scale_factor => 1.0,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_4x3_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.2,
                    ViewItemParams::icon_sel_scale_factor => 1.4,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_4x3_no_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.2,
                    ViewItemParams::icon_sel_scale_factor => 1.4,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_3x3_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.2,
                    ViewItemParams::icon_sel_scale_factor => 1.4,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_3x3_no_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.2,
                    ViewItemParams::icon_sel_scale_factor => 1.4,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

        );
    }
}
