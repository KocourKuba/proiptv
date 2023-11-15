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
require_once 'hd.php';
require_once 'mediaurl.php';
require_once 'user_input_handler_registry.php';
require_once 'control_factory_ext.php';
require_once 'default_archive.php';
require_once 'catchup_params.php';
require_once 'epg_manager_sql.php';
require_once 'provider_config.php';
require_once 'named_storage.php';
require_once 'm3u/M3uParser.php';

class Default_Dune_Plugin implements DunePlugin
{
    const AUTHOR_LOGO = "ProIPTV by sharky72  [ ´¯¤¤¯(ºº)¯¤¤¯` ]";
    const SANDWICH_BASE = 'gui_skin://special_icons/sandwich_base.aai';
    const SANDWICH_MASK = 'cut_icon://{name=sandwich_mask}';
    const SANDWICH_COVER = 'cut_icon://{name=sandwich_cover}';
    const RESOURCE_URL = 'http://iptv.esalecrm.net/res/';
    const CONFIG_URL = 'http://iptv.esalecrm.net/update/';
    const ARCHIVE_URL_PREFIX = 'http://iptv.esalecrm.net/res';
    const ARCHIVE_ID = 'common';

    /////////////////////////////////////////////////////////////////////////////
    // views variables
    const TV_SANDWICH_WIDTH = 246;
    const TV_SANDWICH_HEIGHT = 140;

    const VOD_SANDWICH_WIDTH = 190;
    const VOD_SANDWICH_HEIGHT = 290;
    const VOD_CHANNEL_ICON_WIDTH = 190;
    const VOD_CHANNEL_ICON_HEIGHT = 290;

    const DEFAULT_MOV_ICON_PATH = 'plugin_file://icons/mov_unset.png';
    const VOD_ICON_PATH = 'gui_skin://small_icons/movie.aai';

    private $plugin_cookies;
    private $internet_status = -2;
    private $opexec_id = -1;

    /**
     * @var bool
     */
    protected $inited = false;

    /**
     * @var array
     */
    public $plugin_info;

    /**
     * @var Epg_Manager|Epg_Manager_Sql
     */
    protected $epg_manager;

    /**
     * @var Starnet_Tv
     */
    public $tv;

    /**
     * @var vod_standard
     */
    public $vod;

    /**
     * @var Playback_Points
     */
    protected $playback_points;

    /**
     * @var Screen[]
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
    protected $orders;

    /**
     * @var array
     */
    protected $history;

    /**
     * @var array
     */
    protected $postpone_save;

    /**
     * @var array
     */
    protected $is_dirty;

    /**
     * @var Hashed_Array
     */
    protected $providers;

    /**
     * @var Named_Storage
     */
    protected $cur_provider;

    /**
     * @var string
     */
    protected $cur_provider_id;

    ///////////////////////////////////////////////////////////////////////

    protected function __construct()
    {
        $this->plugin_info = get_plugin_manifest_info();
        $this->postpone_save = array(PLUGIN_PARAMETERS => false, PLUGIN_SETTINGS => false, PLUGIN_ORDERS => false, PLUGIN_HISTORY => false);
        $this->is_dirty = array(PLUGIN_PARAMETERS => false, PLUGIN_SETTINGS => false, PLUGIN_ORDERS => false, PLUGIN_HISTORY => false);
        $this->providers = new Hashed_Array();
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

    /**
     * @return Hashed_Array<Provider_Config>
     */
    public function get_providers()
    {
        return $this->providers;
    }

    /**
     * @return Provider_Config|null
     */
    public function get_current_provider()
    {
        $playlist = $this->get_current_playlist();
        if (is_null($playlist) || $playlist->type !== PARAM_PROVIDER) {
            return null;
        }

        if (is_null($this->cur_provider)) {
            $this->cur_provider = $this->init_provider($playlist);
        }

        return $this->cur_provider;
    }

    /**
     * @param Provider_Config|null $cur_provider
     */
    public function set_current_provider($cur_provider)
    {
        $this->cur_provider = $cur_provider;
    }

    /**
     * @return Provider_Config[]
     */
    public function get_enabled_providers()
    {
        $providers = array();
        if (!is_null($this->providers)) {
            foreach ($this->providers as $value) {
                if ($value->getEnable()) {
                    $providers[] = $value;
                }
            }
        }

        return $providers;
    }

    /**
     * @param string $name
     * @return Provider_Config
     */
    public function get_provider($name)
    {
        return $this->providers->get($name);
    }

    /**
     * @return Epg_Manager|Epg_Manager_Sql
     */
    public function get_epg_manager()
    {
        return $this->epg_manager;
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
            if (isset($this->screens[$object->get_id()])) {
                hd_debug_print("Error: screen (id: " . $object->get_id() . ") already registered.");
            } else {
                $this->screens[$object->get_id()] = $object;
                hd_debug_print("Screen added: " . $object->get_id());
                if ($object instanceof User_Input_Handler) {
                    User_Input_Handler_Registry::get_instance()->register_handler($object);
                }
            }
        } else {
            hd_debug_print(get_class($object) . ": Screen class is illegal. get_id method not defined!");
        }
    }

    /**
     * @param string $id
     * @return void
     */
    public function destroy_screen($id)
    {
        if (isset($this->screens[$id])) {
            if ($this->screens[$id] instanceof User_Input_Handler) {
                User_Input_Handler_Registry::get_instance()->unregister_handler($this->screens[$id]->get_handler_id());
            }
            unset($this->screens[$id]);
        } else {
            hd_debug_print("Screen not exist: $id");
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
        print_backtrace();
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
            print_backtrace();
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
            print_backtrace();
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
            print_backtrace();
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
                print_backtrace();
                throw new Exception('TV is not supported');
            }

            if (is_null($channel_id)) {
                throw new Exception('Unknown channel id');
            }

            // get channel by hash
            $channel = $this->tv->get_channel($channel_id);
            if (is_null($channel)) {
                throw new Exception('Unknown channel');
            }

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
                    hd_debug_print(format_datetime("m-d H:i", $tm_start)
                        . " - " . format_datetime("m-d H:i", $tm_end)
                        . " {$value[Epg_Params::EPG_NAME]}"
                    );
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
            print_backtrace();
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

        print_backtrace();
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
    // Playlist settings methods
    //

    /**
     * Get settings for selected playlist
     *
     * @param string $type
     * @param mixed|null $default
     * @return mixed
     */
    public function &get_setting($type, $default = null)
    {
        $this->load_settings();

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
        $this->set_dirty();
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
     * Remove setting for selected playlist
     *
     * @param string $type
     */
    public function remove_setting($type)
    {
        unset($this->settings[$type]);
        $this->set_dirty();
        $this->save_settings();
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin parameters methods
    //

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
        $this->load_parameters();

        if (!isset($this->parameters[$param])) {
            hd_debug_print("load default $param: $default", true);
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
        $this->load_parameters();

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
        $this->set_dirty(true,PLUGIN_PARAMETERS);
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
        $this->set_dirty(true,PLUGIN_PARAMETERS);
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

    ///////////////////////////////////////////////////////////////////////
    // Orders settings
    //

    /**
     * Set channels order for selected playlist
     *
     * @param string $id
     * @param mixed $val
     */
    public function set_orders($id, $val)
    {
        $this->orders[$id] = $val;
        $this->set_dirty(true, PLUGIN_ORDERS);
        $this->save_orders();
    }

    /**
     * Get channels orders for selected playlist
     *
     * @param string $id
     * @param $default
     * @return mixed
     */
    public function &get_orders($id, $default = null)
    {
        $this->load_orders();

        if (!isset($this->orders[$id])) {
            $this->orders[$id] = is_null($default) ? new Ordered_Array() : $default;
        }

        return $this->orders[$id];
    }

    /**
     * Remove order from storage
     *
     * @param string $id
     */
    public function remove_order($id)
    {
        unset($this->orders[$id]);
        $this->set_dirty(true, PLUGIN_ORDERS);
        $this->save_orders();
    }

    /**
     * Get order names in storage
     *
     * @return array
     */
    public function get_order_names()
    {
        $this->load_orders();
        return is_array($this->orders) ? array_keys($this->orders) : array();
    }

    ///////////////////////////////////////////////////////////////////////
    // History settings
    //

    /**
     * Set channels order for selected playlist
     *
     * @param string $id
     * @param mixed $val
     */
    public function set_history($id, $val)
    {
        $this->history[$id] = $val;
        $this->set_dirty(true, PLUGIN_HISTORY);
        $this->save_history();
    }

    /**
     * Get history for selected playlist
     *
     * @param string $id
     * @param mixed|null $default
     * @return Hashed_Array<string, HistoryItem>|Ordered_Array
     */
    public function &get_history($id, $default = null)
    {
        $this->load_history();

        if (!isset($this->history[$id])) {
            $this->history[$id] = is_null($default) ? new Hashed_Array() : $default;
        }

        return $this->history[$id];
    }

    /**
     * Remove order from storage
     *
     * @param string $id
     */
    public function remove_history($id)
    {
        unset($this->history[$id]);
        $this->set_dirty(true, PLUGIN_HISTORY);
        $this->save_history();
    }

    /**
     * Get order names in storage
     *
     * @return array
     */
    public function get_history_names()
    {
        $this->load_history();
        return is_array($this->history) ? array_keys($this->history) : array();
    }
    ///////////////////////////////////////////////////////////////////////
    // Storages methods
    //

    /**
     * Block or release save settings action
     * If released will perform save action
     *
     * @param bool $snooze
     * @param string $item
     */
    public function set_postpone_save($snooze, $item)
    {
        hd_debug_print(null, true);
        hd_debug_print("Snooze: " . var_export($snooze, true) . ", item: $item", true);
        $this->postpone_save[$item] = $snooze;
        if ($snooze) {
            return;
        }

        if ($item === PLUGIN_SETTINGS) {
            $this->save_settings();
        } else if ($item === PLUGIN_PARAMETERS) {
            $this->save_parameters();
        } else if ($item === PLUGIN_ORDERS) {
            $this->save_orders();
        } else if ($item === PLUGIN_HISTORY) {
            $this->save_history();
        }
    }

    /**
     * Is settings contains unsaved changes
     *
     * @return bool
     */
    public function is_dirty($item = PLUGIN_SETTINGS)
    {
        return $this->is_dirty[$item];
    }

    /**
     * Is settings contains unsaved changes
     *
     * @param bool $val
     * @param string $item
     */
    public function set_dirty($val = true, $item = PLUGIN_SETTINGS)
    {
        //hd_debug_print("$item: set_dirty: " . var_export($val, true), true);
        if (!is_null($item)) {
            $this->is_dirty[$item] = $val;
        }
    }

    /**
     * load playlist settings
     *
     * @param bool $force
     * @return void
     */
    public function load_settings($force = false)
    {
        $active_playlist_key = $this->get_active_playlist_key();
        if (!empty($active_playlist_key)) {
            $this->load($this->get_active_playlist_key() . '.settings', PLUGIN_SETTINGS, $force);
        }
    }

    /**
     * load plugin settings
     *
     * @param bool $force
     * @return void
     */
    public function load_parameters($force = false)
    {
        $this->load('common.settings', PLUGIN_PARAMETERS, $force);
    }

    /**
     * load playlist settings
     *
     * @param bool $force
     * @return void
     */
    public function load_orders($force = false)
    {
        $this->load($this->get_active_playlist_key() . '_' . PLUGIN_ORDERS . '.settings', PLUGIN_ORDERS, $force);
    }

    /**
     * load playlist history
     *
     * @param bool $force
     * @return void
     */
    public function load_history($force = false)
    {
        $type = PLUGIN_HISTORY;
        if ($force) {
            $this->{$type} = null;
        }

        if (!isset($this->{$type})) {
            $file = $this->get_history_path() . DIRECTORY_SEPARATOR . "{$this->get_active_playlist_key()}_$type.settings";
            hd_debug_print("Load ($type): $file");
            $this->{$type} = HD::get_items($file, true, false);
            if (LogSeverity::$is_debug) {
                foreach ($this->{$type} as $key => $param) hd_debug_print("$key => " . (is_array($param) ? json_encode($param) : $param), true);
            }
        }
    }

    /**
     * load plugin/playlist/orders/history settings
     *
     * @param string $name
     * @param string $type
     * @param bool $force
     * @return void
     */
    private function load($name, $type, $force = false)
    {
        if ($force) {
            $this->{$type} = null;
        }

        if (!isset($this->{$type})) {
            hd_debug_print("Load ($type): $name");
            $this->{$type} = HD::get_data_items($name, true, false);
            if (LogSeverity::$is_debug) {
                foreach ($this->{$type} as $key => $param) hd_debug_print("$key => " . (is_array($param) ? json_encode($param) : $param));
            }
        }
    }

    /**
     * save playlist settings
     *
     * @param bool $force
     * @return bool
     */
    public function save_settings($force = false)
    {
        return $this->save($this->get_active_playlist_key() . '.settings', PLUGIN_SETTINGS, $force);
    }

    /**
     * save plugin parameters
     *
     * @param bool $force
     * @return bool
     */
    public function save_parameters($force = false)
    {
        return $this->save('common.settings', PLUGIN_PARAMETERS, $force);
    }

    /**
     * save playlist channels orders
     *
     * @param bool $force
     * @return bool
     */
    public function save_orders($force = false)
    {
        return $this->save($this->get_active_playlist_key() . '_' . PLUGIN_ORDERS . '.settings', PLUGIN_ORDERS, $force);
    }

    /**
     * save playlist history
     *
     * @param bool $force
     * @return bool
     */
    public function save_history($force = false)
    {
        $type = PLUGIN_HISTORY;

        if (is_null($this->{$type})) {
            hd_debug_print("this->$type is not set!", true);
            return false;
        }

        if ($this->postpone_save[$type] && !$force) {
            return false;
        }

        if ($force || $this->is_dirty($type)) {
            $file = $this->get_history_path() . DIRECTORY_SEPARATOR . "{$this->get_active_playlist_key()}_$type.settings";
            hd_debug_print("Save: $file", true);
            if (LogSeverity::$is_debug) {
                foreach ($this->{$type} as $key => $param) hd_debug_print("$key => " . (is_array($param) ? json_encode($param) : $param));
            }
            HD::put_items($file, $this->{$type}, false);
            $this->set_dirty(false, $type);
            return true;
        }

        return false;
    }

    /**
     * save data
     * @param string $name
     * @param string $type
     * @param bool $force
     * @return bool
     */
    private function save($name, $type, $force = false)
    {
        if (is_null($this->{$type})) {
            hd_debug_print("this->$type is not set!", true);
            return false;
        }

        if ($this->postpone_save[$type] && !$force) {
            return false;
        }

        if ($force || $this->is_dirty($type)) {
            hd_debug_print("Save: $name", true);
            if (LogSeverity::$is_debug) {
                foreach ($this->{$type} as $key => $param) hd_debug_print("$key => " . (is_array($param) ? json_encode($param) : $param));
            }
            HD::put_data_items($name, $this->{$type}, false);
            $this->set_dirty(false, $type);
            return true;
        }
        return false;
    }

    /**
     * Remove settings for selected playlist
     * @param string $id
     */
    public function remove_settings($id)
    {
        unset($this->settings);
        hd_debug_print("remove $id.settings", true);
        HD::erase_data_items("$id.settings");
        hd_debug_print("remove {$id}_" . PLUGIN_ORDERS . ".settings", true);
        HD::erase_data_items("{$id}_" . PLUGIN_ORDERS . ".settings");
        hd_debug_print("remove {$id}_" . PLUGIN_HISTORY . ".settings", true);
        HD::erase_data_items("{$id}_" . PLUGIN_HISTORY . ".settings");

        foreach (glob_dir(get_cached_image_path(), "/^$id.*$/i") as $file) {
            hd_debug_print("remove cached image: $file", true);
            unlink($file);
        }
    }

    ///////////////////////////////////////////////////////////////////////
    // Methods

    /**
     * @return void
     */
    public function init_plugin($force = false)
    {
        if (!$force && $this->inited) {
            return;
        }

        hd_print("----------------------------------------------------");
        LogSeverity::$is_debug = true;
        $this->load_parameters(true);
        $this->update_log_level();

        $this->init_epg_manager();
        $this->create_screen_views();
        $this->playback_points = new Playback_Points($this);

        if ($this->providers->size() === 0) {
            // 1. Check local debug version
            // 2. Try to download from web
            // 3. Check previously downloaded web version
            // 4. Check preinstalled version
            // 5. Houston we have a problem
            if (file_exists($tmp_file = get_install_path("providers_debug.json"))) {
                $jsonArray = HD::ReadContentFromFile($tmp_file);
            } else {
                $tmp_file = get_data_path("providers.json");
                $jsonArray = HD::DownloadJson(self::CONFIG_URL . "providers.json");
                if ($jsonArray === false || !isset($jsonArray['providers'])) {
                    if (file_exists($tmp_file)) {
                        $jsonArray = HD::ReadContentFromFile($tmp_file);
                    } else if (file_exists($tmp_file = get_install_path("providers.json"))) {
                        $jsonArray = HD::ReadContentFromFile($tmp_file);
                    } else {
                        hd_debug_print("Problem to download providers configuration");
                        return;
                    }
                } else {
                    HD::StoreContentToFile($tmp_file, $jsonArray);
                }
            }

            foreach ($jsonArray['providers'] as $item) {
                if (isset($item['class'])) {
                    $config = new $item['class']();
                } else {
                    $config = new Provider_Config();
                }

                foreach ($item as $key => $value) {
                    $words = explode('_', $key);
                    $setter = "set";
                    foreach ($words as $word) {
                        $setter .= ucwords($word);
                    }
                    if (method_exists($config, $setter)) {
                        $config->{$setter}($value);
                    } else {
                        hd_debug_print("Unknown method $setter");
                    }
                }

                if ($config->getId() !== '') {
                    $this->providers->set($config->getId(), $config);
                    // cache provider logo
                    $logo = $config->getLogo();
                    $filename = basename($logo);
                    if (!file_exists(get_cached_image_path($filename))) {
                        try {
                            $data = HD::http_get_document($logo);
                            file_put_contents(get_cached_image_path($filename), $data);
                        } catch (Exception $ex) {
                            hd_debug_print("failed to download provider logo: $logo");
                        }
                    }
                }
            }
        }

        $this->tv->unload_channels();

        hd_debug_print("Init plugin done!");
        hd_print("----------------------------------------------------");

        $this->inited = true;
    }

    public function upgrade_parameters(&$plugin_cookies)
    {
        hd_debug_print(null, true);

        $this->load_parameters(true);
        $this->update_log_level();

        if ($this->get_parameter(PLUGIN_CONFIG_VERSION) === '3') {
            // upgrade completed
            return;
        }

        $this->set_postpone_save(true, PLUGIN_PARAMETERS);

        if (isset($plugin_cookies->pass_sex)) {
            $this->set_parameter(PARAM_ADULT_PASSWORD, $plugin_cookies->pass_sex);
            unset($plugin_cookies->pass_sex);
        } else {
            $this->get_parameter(PARAM_ADULT_PASSWORD, '0000');
        }

        // Convert playlists to Named_Storage
        $new_storage = new Hashed_Array();

        /** @var Hashed_Array $playlist_names */
        $playlist_names = $this->get_parameter(PARAM_PLAYLISTS_NAMES, new Hashed_Array());
        /** @var Ordered_Array $old_playslists */
        $old_playslists = $this->get_parameter(PARAM_PLAYLISTS, new Ordered_Array());
        $selected = Hashed_Array::hash($old_playslists->get_selected_item());
        $found = '';
        foreach ($old_playslists as $playlist) {
            hd_debug_print("upgrade playlist: $playlist", true);
            $item = new Named_Storage();
            $id = Hashed_Array::hash($playlist);
            if ($id === $selected) {
                $found = $selected;
            }
            $name = $playlist_names->get($id);
            if (empty($name)) {
                $name = $playlist;
                if (($pos = strpos($name, '?')) !== false) {
                    $name = substr($name, 0, $pos);
                }
                $name = basename($name);
            }
            $item->name = $name;
            $item->params['uri'] = $playlist;
            $item->type = preg_match(HTTP_PATTERN, $playlist) ? PARAM_LINK : PARAM_FILE;
            hd_debug_print("new storage: id: $id, type: $item->type, name: $item->name, params: " . raw_json_encode($item->params), true);
            $new_storage->set($id, $item);
        }

        if (empty($found)) {
            $new_storage->rewind();
            $found = $new_storage->key();
        }

        $this->set_parameter(PARAM_CUR_PLAYLIST_ID, $found);
        $this->set_parameter(PARAM_PLAYLIST_STORAGE, $new_storage);

        $this->remove_parameter(PARAM_PLAYLISTS);
        $this->remove_parameter(PARAM_PLAYLISTS_NAMES);

        // convert old type xmltv parameter
        $new_storage = new Hashed_Array();
        $source_names = $this->get_parameter(PARAM_XMLTV_SOURCE_NAMES, new Hashed_Array());
        $type = $this->get_parameter_type(PARAM_EXT_XMLTV_SOURCES);
        if ($type !== null) {
            /** @var Hashed_Array $old_array */
            $old_array = $this->get_parameter(PARAM_EXT_XMLTV_SOURCES);
            /** @var Hashed_Array $source_names */
            foreach ($old_array as $key => $source) {
                hd_debug_print("($type) upgrade xmltv source: $source", true);
                $item = new Named_Storage();
                $id = ($type === 'Hashed_Array') ? $key : Hashed_Array::hash($source);
                $item->params['uri'] = $source;
                $name = $source_names->get($key);
                if (preg_match(HTTP_PATTERN, $source, $m)) {
                    $item->type = PARAM_LINK;
                    if (empty($name)) {
                        $name = $m[2];
                    } else {
                        $name = $source;
                    }
                } else {
                    $item->type = PARAM_FILE;
                }
                $item->name = $name;
                hd_debug_print("new storage: id: $id, type: $item->type, name: $item->name, params: " . raw_json_encode($item->params), true);
                $new_storage->put($id, $item);
            }
            $this->set_parameter(PARAM_XMLTV_SOURCES, $new_storage);
            $this->remove_parameter(PARAM_EXT_XMLTV_SOURCES);
            $this->remove_parameter(PARAM_XMLTV_SOURCE_NAMES);
        }

        $this->set_parameter(PLUGIN_CONFIG_VERSION, '3');
        $this->save_parameters(true);

        // Move channels orders from settings to separate storage

        /** @var Named_Storage $playlist */
        foreach($this->get_playlists() as $id => $playlist) {
            $settings_name = "$id.settings";
            $order_name = $id . '_' . PLUGIN_ORDERS . '.settings';
            hd_debug_print("loading: $settings_name", true);

            $this->load($settings_name, PLUGIN_SETTINGS, true);
            $this->load($order_name, PLUGIN_ORDERS, true);

            $this->set_postpone_save(true, PLUGIN_SETTINGS);
            $this->set_postpone_save(true, PLUGIN_ORDERS);

            $all_keys = array_keys($this->settings);
            foreach ($all_keys as $key) {
                if (strpos($key,PARAM_CHANNELS_ORDER) !== false) {
                    hd_debug_print("load old order from: $key", true);
                    $order = $this->get_setting($key);
                    $id = substr($key, strlen(PARAM_CHANNELS_ORDER . '_'));
                    $this->set_orders($id, $order);
                    $this->remove_setting($key);
                } else if (strpos($key,FAVORITES_GROUP_ID) !== false) {
                    hd_debug_print("load old order from: $key", true);
                    $order = $this->get_setting($key);
                    $this->set_orders(FAVORITES_GROUP_ID, $order);
                    $this->remove_setting($key);
                } else if (in_array($key, array(PARAM_DISABLED_GROUPS, PARAM_DISABLED_CHANNELS, PARAM_KNOWN_CHANNELS, PARAM_GROUPS_ORDER))) {
                    hd_debug_print("load old order from: $key", true);
                    $order = $this->get_setting($key);
                    $this->set_orders($key, $order);
                    $this->remove_setting($key);
                }
            }
            $this->save($settings_name, PLUGIN_SETTINGS, true);
            $this->save($order_name, PLUGIN_ORDERS, true);
        }

        $this->parameters = null;
        $this->settings = null;
        $this->orders = null;
    }

    /**
     * @param Named_Storage $info
     * @return Provider_Config|null
     */
    public function init_provider($info)
    {
        $provider = $this->get_provider($info->params[PARAM_PROVIDER]);
        if (is_null($provider)) {
            hd_debug_print("unknown provider");
            return null;
        }

        if (!$provider->getEnable()) {
            hd_debug_print("provider disabled");
            return null;
        }

        $provider->parse_provider_creds($info);

        return $provider;
    }

    public function init_epg_manager()
    {
        $this->epg_manager = null;
        if ($this->get_parameter(PARAM_EPG_CACHE_ENGINE, ENGINE_SQLITE) === ENGINE_LEGACY) {
            hd_debug_print("Using legacy cache engine");
        } else if (class_exists('SQLite3')) {
            hd_debug_print("Using sqlite cache engine");
            $this->epg_manager = new Epg_Manager_Sql($this->plugin_info['app_version'], $this->get_cache_dir(), $this->get_active_xmltv_source());
        } else {
            hd_debug_print("Selected sqlite but system does not support it. Switch to legacy");
            $this->set_parameter(PARAM_EPG_CACHE_ENGINE, ENGINE_LEGACY);
        }

        if (is_null($this->epg_manager)) {
            $this->epg_manager = new Epg_Manager($this->plugin_info['app_version'], $this->get_cache_dir(), $this->get_active_xmltv_source());
        }

        $flags = $this->get_bool_parameter(PARAM_FUZZY_SEARCH_EPG, false) ? EPG_FUZZY_SEARCH : 0;
        $flags |= $this->get_bool_parameter(PARAM_FAKE_EPG, false) ? EPG_FAKE_EPG : 0;
        $this->epg_manager->set_flags($flags);
        $this->epg_manager->set_cache_ttl($this->get_setting(PARAM_EPG_CACHE_TTL, 3));
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
        $tmp_file = $this->get_current_playlist_cache(true);
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
                $item = $this->get_current_playlist();
                if (empty($item->type)) {
                    hd_debug_print("Tv playlist not defined");
                    throw new Exception("Tv playlist not defined");
                }

                hd_debug_print("m3u playlist ({$this->get_active_playlist_key()} - $item->name): $item->type");
                if ($item->type === PARAM_FILE) {
                    $contents = file_get_contents($item->params['uri']);
                } else {
                    if ($item->type === PARAM_LINK) {
                        if (!preg_match(HTTP_PATTERN, $item->params['uri'])) {
                            throw new Exception("Malformed playlist url: {$item->params['uri']}");
                        }
                        $playlist_url = $item->params['uri'];
                    } else if ($item->type === PARAM_PROVIDER) {
                        $provider = $this->init_provider($item);
                        if (is_null($provider)) {
                            throw new Exception("Unable to init provider $item");
                        }
                        $provider->request_provider_info();
                        $playlist_url = $provider->replace_macros($provider->getPlaylistSource());

                    } else {
                        throw new Exception("Unknown playlist type");
                    }

                    $contents = HD::http_download_https_proxy($playlist_url);
                }

                if ($contents === false || strpos($contents, '#EXTM3U') === false) {
                    HD::set_last_error("Empty or incorrect playlist !\n\n" . $contents);
                    throw new Exception("Can't parse playlist");
                }

                file_put_contents($tmp_file, $contents);
                $mtime = filemtime($tmp_file);
                hd_debug_print("Save $tmp_file (timestamp: $mtime)");
            }

            // Is already parsed?
            $this->tv->get_m3u_parser()->setupParser($tmp_file, $force);
            if ($this->tv->get_m3u_parser()->getEntriesCount() === 0) {
                if (!$this->tv->get_m3u_parser()->parseInMemory()) {
                    HD::set_last_error("Ошибка чтения плейлиста!");
                    throw new Exception("Can't read playlist");
                }

                $count = $this->tv->get_m3u_parser()->getEntriesCount();
                if ($count === 0) {
                    $contents = @file_get_contents($tmp_file);
                    HD::set_last_error("Пустой плейлист!\n\n" . $contents);
                    hd_debug_print("Empty playlist");
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
     * Initialize and parse selected playlist
     *
     * @return bool
     */
    public function init_vod_playlist()
    {
        $provider = $this->get_current_provider();
        if (is_null($provider) || !$provider->getVodEnabled()) {
            return false;
        }

        $vod_url = $provider->getVodConfigValue('vod_source');
        if (empty($vod_url)) {
            return false;
        }

        $this->init_user_agent();
        $force = false;
        $tmp_file = $this->get_current_playlist_cache(false);
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
                hd_debug_print("vod source: $vod_url");
                $contents = HD::http_download_https_proxy($provider->replace_macros($vod_url));
                if ($contents === false || strpos($contents, '#EXTM3U') === false) {
                    HD::set_last_error("Empty or incorrect playlist !\n\n" . $contents);
                    throw new Exception("Can't parse playlist");
                }

                file_put_contents($tmp_file, $contents);
                $mtime = filemtime($tmp_file);
                hd_debug_print("Save $tmp_file (timestamp: $mtime)");
            }

            // Is already parsed?
            $this->vod->get_m3u_parser()->setupParser($tmp_file, $force);
        } catch (Exception $ex) {
            hd_debug_print("Unable to load VOD playlist: " . $ex->getMessage());
            return false;
        }

        hd_debug_print("Init VOD playlist done!");
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
     * Start indexing in background and return immediately
     * @return void
     */
    public function start_bg_indexing()
    {
        $config = array(
            'debug' => LogSeverity::$is_debug,
            'log_file' => $this->get_epg_manager()->get_cache_stem('.log'),
            'version' => $this->plugin_info['app_version'],
            'cache_dir' => $this->get_cache_dir(),
            'cache_engine' => $this->get_parameter(PARAM_EPG_CACHE_ENGINE, ENGINE_SQLITE),
            'cache_ttl' => $this->get_setting(PARAM_EPG_CACHE_TTL, 3),
            'xmltv_url' => $this->get_active_xmltv_source(),
        );

        file_put_contents(get_temp_path('parse_config.json'), json_encode($config));

        $cmd = get_install_path('bin/cgi_wrapper.sh') . " 'index_epg.php' &";
        hd_debug_print("exec: $cmd", true);
        exec($cmd);
    }

    ///////////////////////////////////////////////////////////////////////////////////
    ///

    /**
     * @return Playback_Points
     */
    public function get_playback_points()
    {
        return $this->playback_points;
    }

    /**
     * @return Hashed_Array
     */
    public function &get_playlists()
    {
        return $this->get_parameter(PARAM_PLAYLIST_STORAGE, new Hashed_Array());
    }

    /**
     * @param string $id
     * @return Named_Storage
     */
    public function get_playlist($id)
    {
        return $this->get_playlists()->get($id);
    }

    /**
     * @return string
     */
    public function get_active_playlist_key()
    {
        $id = $this->get_parameter(PARAM_CUR_PLAYLIST_ID);
        if (empty($id) || !$this->get_playlists()->has($id)) {
            if ($this->get_playlists()->size()) {
                $this->get_playlists()->rewind();
                $id = $this->get_playlists()->key();
            }
        }

        return $id;
    }

    /**
     * $param string $id
     * @return void
     */
    public function set_active_playlist_key($id)
    {
        $this->set_parameter(PARAM_CUR_PLAYLIST_ID, $id);
        $this->set_current_provider(null);
    }

    /**
     * @return Named_Storage
     */
    public function get_current_playlist()
    {
        return $this->get_playlists()->get($this->get_active_playlist_key());
    }

    /**
     * @param bool $is_tv
     * @return string
     */
    public function get_current_playlist_cache($is_tv)
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
        $tmp_file = get_temp_path($playlist_id . "_playlist.m3u8");
        if (file_exists($tmp_file)) {
            $this->tv->get_m3u_parser()->setupParser('');
            hd_debug_print("remove $tmp_file", true);
            unlink($tmp_file);
        }
    }

    /**
     * get external xmltv sources
     *
     * @return Hashed_Array
     */
    public function &get_ext_xmltv_sources()
    {
        return $this->get_parameter(PARAM_XMLTV_SOURCES, new Hashed_Array());
    }

    /**
     * get all xmltv source
     *
     * @return Hashed_Array<string, Named_Storage>
     */
    public function get_all_xmltv_sources()
    {
        hd_debug_print(null, true);

        /** @var Hashed_Array $sources */
        $xmltv_sources = new Hashed_Array();
        foreach ($this->tv->get_m3u_parser()->getXmltvSources() as $m3u8source) {
            if (!preg_match(HTTP_PATTERN, $m3u8source, $m)) continue;

            $item = new Named_Storage();
            $item->type = PARAM_LINK;
            $item->params['uri'] = $m3u8source;
            $item->name = $m[2];
            $xmltv_sources->put(Hashed_Array::hash($m3u8source), $item);
        }

        if ($xmltv_sources->size() === 0) {
            $provider = $this->get_current_provider();
            if (!is_null($provider)) {
                $sources = $provider->getXmltvSources();
                if (!empty($sources)) {
                    foreach ($sources as $source) {
                        if (!preg_match(HTTP_PATTERN, $source, $m)) continue;

                        $item = new Named_Storage();
                        $item->type = PARAM_LINK;
                        $item->params['uri'] = $source;
                        $item->name = $m[2];
                        $xmltv_sources->put(Hashed_Array::hash($source), $item);
                    }
                }
            }
        }

        if ($xmltv_sources->size() !== 0) {
            $xmltv_sources->add(EPG_SOURCES_SEPARATOR_TAG);
        }

        /** @var Named_Storage $source */
        foreach ($this->get_ext_xmltv_sources() as $key => $source) {
            $xmltv_sources->set($key, $source);
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
        /** @var Named_Storage $xmltv_source */
        $xmltv_source = $this->get_all_xmltv_sources()->get($key);
        $this->set_setting(PARAM_XMLTV_SOURCE_KEY, $key);
        $this->set_active_xmltv_source($xmltv_source->params['uri']);
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

        if ($icon !== null && strpos($icon,"://") === false) {
            $icon = get_image_path($icon);
        }

        if (file_exists(get_cached_image_path(basename($icon)))) {
            $icon = get_cached_image_path(basename($icon));
        }

        hd_debug_print("icon: $icon");
        return User_Input_Handler_Registry::create_popup_item($handler, $action_id, $caption, $icon, $add_params);
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function playlist_menu($handler)
    {
        $menu_items = array();

        $cur = $this->get_active_playlist_key();
        $idx = 0;
        foreach ($this->get_playlists() as $key => $item) {
            if ($idx !== 0 && ($idx % 15) === 0) {
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            }
            $idx++;

            $icon = null;
            if ($item->type === PARAM_PROVIDER) {
                $provider = $this->init_provider($item);
                if (!is_null($provider)) {
                    $icon = $provider->getLogo();
                }
            } else if ($item->type === PARAM_LINK) {
                $icon = "link.png";
            } else if ($item->type === PARAM_FILE) {
                $icon = "m3u_file.png";
            }

            $menu_items[] = $this->create_menu_item($handler,
                ACTION_PLAYLIST_SELECTED,
                $item->name,
                ($cur !== $key) ? $icon : "check.png",
                array(LIST_IDX => $key));
        }

        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->create_menu_item($handler, ACTION_RELOAD, TR::t('refresh'), "refresh.png", array('reload_action' => 'playlist'));

        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function all_providers_menu($handler)
    {
        $menu_items = array();

        $idx = 0;
        foreach ($this->get_enabled_providers() as $provider) {
            if ($idx !== 0 && ($idx % 15) === 0) {
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            }
            $idx++;

            $menu_items[] = $this->create_menu_item($handler,
                ACTION_EDIT_PROVIDER_DLG,
                $provider->getName(),
                $provider->getLogo(),
                array(PARAM_PROVIDER => $provider->getId())
            );
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
            if ($idx !== 0 && ($idx % 15) === 0) {
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            }
            $idx++;

            if ($item === EPG_SOURCES_SEPARATOR_TAG) {
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
                continue;
            }

            $name = $item->name;
            $cached_xmltv_file = $this->get_cache_dir() . DIRECTORY_SEPARATOR . "$key.xmltv";
            if (file_exists($cached_xmltv_file)) {
                $check_time_file = filemtime($cached_xmltv_file);
                $name .= " (" . date("d.m H:i", $check_time_file) . ")";
            }

            $menu_items[] = $this->create_menu_item($handler,
                ACTION_EPG_SOURCE_SELECTED,
                $name,
                ($source_key === $key) ? "check.png" : null,
                array(LIST_IDX => $key)
            );
        }

        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->create_menu_item($handler, ACTION_RELOAD, TR::t('refresh'), "refresh.png", array('reload_action' => 'epg'));

        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function sort_menu($handler)
    {
        $menu_items = array();

        $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_SORT, TR::t('sort_channels'),
            null, array(ACTION_SORT_TYPE => ACTION_SORT_CHANNELS));
        $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_SORT, TR::t('sort_groups'),
            null, array(ACTION_SORT_TYPE => ACTION_SORT_GROUPS));
        $menu_items[] = $this->create_menu_item($handler, ACTION_RESET_ITEMS_SORT, TR::t('reset_channels_sort'),
            null, array(ACTION_RESET_TYPE => ACTION_SORT_CHANNELS));
        $menu_items[] = $this->create_menu_item($handler, ACTION_RESET_ITEMS_SORT, TR::t('reset_groups_sort'),
            null, array(ACTION_RESET_TYPE => ACTION_SORT_GROUPS));
        $menu_items[] = $this->create_menu_item($handler, ACTION_RESET_ITEMS_SORT, TR::t('reset_all_sort'),
            null, array(ACTION_RESET_TYPE => ACTION_SORT_ALL));
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
        $menu_items = array();

        if ($group_id === null) {
            return $menu_items;
        }

        if ($this->tv->get_special_group($group_id) === null) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_group'), "hide.png");
        }

        hd_debug_print("Disabled groups: " . $this->tv->get_disabled_group_ids()->size(), true);
        if ($this->tv->get_disabled_group_ids()->size() !== 0) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_EDIT,
                TR::t('tv_screen_edit_hidden_group'), "edit.png", array('action_edit' => Starnet_Edit_List_Screen::SCREEN_EDIT_GROUPS));
        }

        $has_hidden_channels = false;
        if (!is_null($group = $this->tv->get_group($group_id))) {
            $has_hidden_channels = $group->get_group_channels()->size() !== $group->get_items_order()->size();
        } else if ($group_id === ALL_CHANNEL_GROUP_ID) {
            $has_hidden_channels = $this->tv->get_disabled_channel_ids()->size() !== 0;
            hd_debug_print("Disabled channels: " . $this->tv->get_disabled_channel_ids()->size(), true);
        }

        if ($has_hidden_channels) {
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
        $name = $this->get_current_playlist()->name;
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
                    ViewItemParams::icon_dx => 30,
                    ViewItemParams::icon_dy => 0,
                    ViewItemParams::item_caption_dx => 30,
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
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_width => 60,
                    ViewItemParams::icon_height => 60,
                    ViewItemParams::icon_dx => 35,
                    ViewItemParams::icon_dy => -5,
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
                    ViewItemParams::icon_width => 60,
                    ViewItemParams::icon_height => 60,
                    ViewItemParams::icon_dx => 35,
                    ViewItemParams::icon_dy => -5,
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
                    ViewItemParams::icon_width => 60,
                    ViewItemParams::icon_height => 60,
                    ViewItemParams::icon_dx => 35,
                    ViewItemParams::icon_dy => -5,
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

            'icons_5x2_movie_no_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 2,
                    ViewParams::paint_details => true,
                    ViewParams::paint_item_info_in_details => true,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,

                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,

                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::VOD_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::VOD_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_path => self::VOD_ICON_PATH,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => self::VOD_CHANNEL_ICON_WIDTH,
                    ViewItemParams::icon_height => self::VOD_CHANNEL_ICON_HEIGHT,
                    ViewItemParams::icon_sel_margin_top => 0,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::item_caption_width => 1100
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_MOV_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            'list_1x10_movie_info_normal' => array(
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 10,
                    ViewParams::paint_details => true,
                    ViewParams::paint_item_info_in_details => true,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,

                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,

                    ViewParams::paint_sandwich => false,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::VOD_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::VOD_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_path => self::VOD_ICON_PATH,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 12,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::icon_sel_margin_top => 0,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::item_caption_width => 1100,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_MOV_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            'list_1x12_vod_info_normal' => array(
                PluginRegularFolderView::async_icon_loading => false,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_details => true,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                ),
                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 55,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_width => 1100
                ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),
        );
    }
}
