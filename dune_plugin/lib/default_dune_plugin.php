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
require_once 'epg_manager_json.php';
require_once 'named_storage.php';
require_once 'api/api_default.php';
require_once 'm3u/M3uParser.php';

class Default_Dune_Plugin implements DunePlugin
{
    const AUTHOR_LOGO = "ProIPTV by sharky72  [ ´¯¤¤¯(ºº)¯¤¤¯` ]";
    const SANDWICH_BASE = 'gui_skin://special_icons/sandwich_base.aai';
    const SANDWICH_MASK = 'cut_icon://{name=sandwich_mask}';
    const SANDWICH_COVER = 'cut_icon://{name=sandwich_cover}';
    const RESOURCE_URL = 'http://iptv.esalecrm.net/res/';
    const CONFIG_URL = 'http://iptv.esalecrm.net/config/providers';
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
    protected $cur_provider;

    ///////////////////////////////////////////////////////////////////////

    protected function __construct()
    {
        $this->plugin_info = get_plugin_manifest_info();
        $this->providers = new Hashed_Array();
        $this->epg_presets = new Hashed_Array();
        $this->image_libs = new Hashed_Array();
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
     * @return Hashed_Array<api_default>
     */
    public function get_providers()
    {
        return $this->providers;
    }

    /**
     * @return api_default|null
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
     * @param api_default|null $cur_provider
     */
    public function set_current_provider($cur_provider)
    {
        $this->cur_provider = $cur_provider;
    }

    /**
     * @return api_default[]
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
     * @return api_default|null
     */
    public function get_provider($name)
    {
        $config = $this->providers->get($name);
        return is_null($config) ? null : clone $config;
    }

    /**
     * @return string|null
     */
    public function get_epg_preset_url()
    {
        $provider = $this->get_current_provider();
        if (is_null($provider)) {
            hd_debug_print("Not supported provider");
            return null;
        }

        $preset = $this->get_epg_preset();
        if (is_null($preset)) {
            return null;
        }

        $epg_url = str_replace(MACRO_API, $provider->getApiUrl(), $preset[EPG_JSON_SOURCE]);
        if (strpos($epg_url, MACRO_PROVIDER) !== false) {
            $epg_alias = $provider->getConfigValue(EPG_JSON_ALIAS);
            $alias = empty($epg_alias) ? $provider->getId() : $epg_alias;
            hd_debug_print("using alias: $alias", true);
            $epg_url = str_replace(MACRO_PROVIDER, $alias, $epg_url);
        }

        if (strpos($epg_url, MACRO_TOKEN) !== false) {
            $token = $provider->getCredential(MACRO_TOKEN);
            hd_debug_print("using token: $token", true);
            $epg_url = str_replace(MACRO_TOKEN, $token, $epg_url);
        }

        return $epg_url;
    }

    /**
     * @return array|null
     */
    public function get_epg_preset_parser()
    {
        $preset = $this->get_epg_preset();
        if (is_null($preset) || !isset($preset[EPG_JSON_PARSER])) {
            return null;
        }

        return $preset[EPG_JSON_PARSER];
    }

    /**
     * @return Epg_Manager|Epg_Manager_Sql
     */
    public function get_epg_manager()
    {
        return $this->epg_manager;
    }

    /**
     * @return array|null
     */
    protected function get_epg_preset()
    {
        $provider = $this->get_current_provider();
        if (is_null($provider)) {
            hd_debug_print("Not supported provider");
            return null;
        }

        $preset_name = $provider->getConfigValue(EPG_JSON_PRESET);
        if (empty($preset_name)) {
            hd_debug_print("No preset for selected provider");
            return null;
        }

        return $this->epg_presets->get($preset_name);
    }

    /**
     * @return Hashed_Array
     */
    public function get_image_libs()
    {
        return $this->image_libs;
    }

    /**
     * @param $preset_name string
     * @return array|null
     */
    public function get_image_lib($preset_name)
    {
        return $this->image_libs->get($preset_name);
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

            if (LogSeverity::$is_debug) {
                hd_debug_print("day_start timestamp: $day_start_tm_sec ("
                    . format_datetime("Y-m-d H:i", $day_start_tm_sec) . ") TZ offset: "
                    . get_local_time_zone_offset());
            }

            // correct day start to local timezone
            $day_start_tm_sec -= get_local_time_zone_offset();

            // get personal time shift for channel
            $time_shift = 3600 * ($channel->get_timeshift_hours() + $this->get_setting(PARAM_EPG_SHIFT, 0));
            hd_debug_print("EPG time shift $time_shift", true);
            $day_start_tm_sec += $time_shift;

            $items = $this->epg_manager->get_day_epg_items($channel, $day_start_tm_sec);

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
                            . " {$value[Epg_Params::EPG_NAME]}"
                        );
                    }
                } else {
                    hd_debug_print("malformed epg data: " . raw_json_encode($value));
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
            if ($default !== null) {
                hd_debug_print("load default $param: $default", true);
            }
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
        hd_debug_print(null, true);
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

        $this->parameters = null;
        $this->settings = null;
        $this->orders = null;
        $this->history = null;
        $this->cur_provider = null;

        $this->postpone_save = array(PLUGIN_PARAMETERS => false, PLUGIN_SETTINGS => false, PLUGIN_ORDERS => false, PLUGIN_HISTORY => false);
        $this->is_dirty = array(PLUGIN_PARAMETERS => false, PLUGIN_SETTINGS => false, PLUGIN_ORDERS => false, PLUGIN_HISTORY => false);

        hd_debug_print_separator();
        LogSeverity::$is_debug = true;
        $this->load_parameters(true);
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
                $jsonArray = HD::ReadContentFromFile($tmp_file);
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

                $jsonArray = HD::DownloadJson(self::CONFIG_URL . "?ver=$ver&model=$model&serial=$serial");
                if ($jsonArray === false || !isset($jsonArray['providers'])) {
                    if (file_exists($tmp_file)) {
                        hd_debug_print("Load actual providers configuration");
                        $jsonArray = HD::ReadContentFromFile($tmp_file);
                    } else if (file_exists($tmp_file = get_install_path($name))) {
                        hd_debug_print("Load installed providers configuration");
                        $jsonArray = HD::ReadContentFromFile($tmp_file);
                    }
                } else {
                    HD::StoreContentToFile($tmp_file, $jsonArray);
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
                if (!isset($item['id'])) continue;

                $api_class = "api_{$item['id']}";
                if (!class_exists($api_class)) {
                    $api_class = 'api_default';
                }

                /** @var api_default $config */
                hd_debug_print("provider api: $api_class ({$item['name']})");
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

                $this->providers->set($provider->getId(), $provider);
                // cache provider logo
                $logo = $provider->getLogo();
                $filename = basename($logo);
                $local_file = get_install_path("logo/$filename");
                if (file_exists($local_file)) {
                    $provider->setLogo("plugin_file://logo/$filename");
                } else {
                    try {
                        $cached_file = get_cached_image_path($filename);
                        HD::http_save_document($logo, $cached_file);
                        $provider->setLogo($cached_file);
                    } catch (Exception $ex) {
                        hd_debug_print("failed to download provider logo: $logo");
                    }
                }
            }
        }

        $this->init_epg_manager();
        $this->create_screen_views();
        $this->playback_points = new Playback_Points($this);

        $this->tv->unload_channels();

        hd_debug_print("Init plugin done!");
        hd_debug_print_separator();

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
            $item->params[PARAM_URI] = $playlist;
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
                $item->params[PARAM_URI] = $source;
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
     * @return api_default|null
     */
    public function init_provider($info)
    {
        hd_debug_print("provider info:" . json_encode($info));
        $provider = $this->get_provider($info->params[PARAM_PROVIDER]);
        if (is_null($provider)) {
            hd_debug_print("unknown provider");
            return null;
        }

        if (!$provider->getEnable()) {
            hd_debug_print("provider disabled");
            return null;
        }

        hd_debug_print("parse provider_info ({$provider->getType()}): $info", true);

        switch ($provider->getType()) {
            case PROVIDER_TYPE_PIN:
                $provider->setCredential(MACRO_PASSWORD, isset($info->params[MACRO_PASSWORD]) ? $info->params[MACRO_PASSWORD] : '');
                break;

            case PROVIDER_TYPE_LOGIN_TOKEN:
                $provider->setCredential(MACRO_LOGIN, isset($info->params[MACRO_LOGIN]) ? $info->params[MACRO_LOGIN] : '');
                $provider->setCredential(MACRO_PASSWORD, isset($info->params[MACRO_PASSWORD]) ? $info->params[MACRO_PASSWORD] : '');
                $provider->setCredential(MACRO_TOKEN,
                    md5(strtolower($provider->getCredential(MACRO_LOGIN)) . md5($provider->getCredential(MACRO_PASSWORD))));
                break;

            case PROVIDER_TYPE_LOGIN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
                $provider->setCredential(MACRO_LOGIN, isset($info->params[MACRO_LOGIN]) ? $info->params[MACRO_LOGIN] : '');
                $provider->setCredential(MACRO_PASSWORD, isset($info->params[MACRO_PASSWORD]) ? $info->params[MACRO_PASSWORD] : '');
                break;

            case PROVIDER_TYPE_EDEM:
                $provider->setCredential(MACRO_SUBDOMAIN, isset($info->params[MACRO_SUBDOMAIN]) ? $info->params[MACRO_SUBDOMAIN] : '');
                $provider->setCredential(MACRO_OTTKEY, isset($info->params[MACRO_OTTKEY]) ? $info->params[MACRO_OTTKEY] : '');
                $provider->setCredential(MACRO_VPORTAL, isset($info->params[MACRO_VPORTAL]) ? $info->params[MACRO_VPORTAL] : '');
                break;

            default:
                return null;
        }

        $domains = $provider->GetDomains();
        if (!empty($domains)) {
            $provider->setCredential(MACRO_DOMAIN_ID, key($domains));
        }
        $servers = $provider->GetServers();
        if (!empty($servers)) {
            $provider->setCredential(MACRO_SERVER_ID, key($servers));
        }
        $devices = $provider->GetDevices();
        if (!empty($devices)) {
            $provider->setCredential(MACRO_DEVICE_ID, key($devices));
        }
        $qualities = $provider->GetQualities();
        if (!empty($qualities)) {
            $provider->setCredential(MACRO_QUALITY_ID, key($qualities));
        }
        $streams = $provider->getStreams();
        if (!empty($streams)) {
            $provider->setCredential(MACRO_STREAM_ID, key($streams));
        }

        foreach($info->params as $key => $item) {
            if ($key === MACRO_DOMAIN_ID || $key === MACRO_SERVER_ID || $key === MACRO_DEVICE_ID || $key === MACRO_QUALITY_ID || $key === MACRO_STREAM_ID) {
                $provider->setCredential($key, $item);
            }
        }

        return $provider;
    }

    /**
     * @return void
     */
    public function init_epg_manager()
    {
        $this->epg_manager = null;
        $engine_class = 'Epg_Manager';
        $engine = $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV);
        if ($engine === ENGINE_JSON) {
            $provider = $this->get_current_provider();
            if (!is_null($provider)) {
                $preset = $provider->getConfigValue(EPG_JSON_PRESET);
                if (!empty($preset)) {
                    hd_debug_print("Using JSON cache engine");
                    $engine_class = 'Epg_Manager_Json';
                }
            }
        } else if (class_exists('SQLite3')) {
            $engine_class = 'Epg_Manager_Sql';
        }

        hd_debug_print("Using $engine_class cache engine");
        $this->epg_manager = new $engine_class($this->plugin_info['app_version'], $this->get_cache_dir(), $this->get_active_xmltv_source(), $this);

        $flags = 0;
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
                $playlist = $this->get_current_playlist();
                if (empty($playlist->type)) {
                    hd_debug_print("Tv playlist not defined");
                    throw new Exception("Tv playlist not defined");
                }

                hd_debug_print("m3u playlist ({$this->get_active_playlist_key()} - $playlist->name)");
                if ($playlist->type === PARAM_FILE) {
                    $contents = file_get_contents($playlist->params[PARAM_URI]);
                    hd_debug_print("m3u load local file {$playlist->params[PARAM_URI]}", true);
                } else if ($playlist->type === PARAM_LINK) {
                    if (!preg_match(HTTP_PATTERN, $playlist->params[PARAM_URI])) {
                        throw new Exception("Malformed playlist url: {$playlist->params[PARAM_URI]}");
                    }
                    $playlist_url = $playlist->params[PARAM_URI];
                    hd_debug_print("m3u download url $playlist_url", true);
                    $contents = HD::http_download_https_proxy($playlist_url);
                } else if ($playlist->type === PARAM_PROVIDER) {
                    $provider = $this->get_current_provider();
                    if (is_null($provider)) {
                        throw new Exception("Unable to init provider $playlist");
                    }

                    $provider->request_provider_token();
                    $contents = $provider->execApiCommand(API_COMMAND_PLAYLIST, true);
                } else {
                    throw new Exception("Unknown playlist type");
                }

                if ($contents === false || strpos($contents, '#EXTM3U') === false) {
                    HD::set_last_error("pl_last_error", "Empty or incorrect playlist !\n\n" . $contents);
                    throw new Exception("Can't parse playlist");
                }

                $encoding = HD::detect_encoding($contents);
                if ($encoding !== 'utf-8') {
                    hd_debug_print("Fixing playlist encoding: $encoding");
                    $contents = iconv($encoding, 'utf-8', $contents);
                }

                file_put_contents($tmp_file, $contents);
                $mtime = filemtime($tmp_file);
                hd_debug_print("Save $tmp_file (timestamp: $mtime)");
            }

            // Is already parsed?
            $this->tv->get_m3u_parser()->setupParser($tmp_file, $force);
            if ($this->tv->get_m3u_parser()->getEntriesCount() === 0) {
                if (!$this->tv->get_m3u_parser()->parseInMemory()) {
                    HD::set_last_error("pl_last_error", "Ошибка чтения плейлиста!");
                    throw new Exception("Can't read playlist");
                }

                $count = $this->tv->get_m3u_parser()->getEntriesCount();
                if ($count === 0) {
                    $contents = @file_get_contents($tmp_file);
                    HD::set_last_error("pl_last_error", "Пустой плейлист!\n\n" . $contents);
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
        if (is_null($provider)) {
            return false;
        }

        if (!$provider->hasApiCommand(API_COMMAND_VOD)) {
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
                $contents = $provider->execApiCommand(API_COMMAND_VOD, true);
                if ($contents === false || strpos($contents, '#EXTM3U') === false) {
                    HD::set_last_error("pl_last_error", "Empty or incorrect playlist !\n\n" . $contents);
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
            'cache_engine' => $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV),
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
                if (!empty($id)) {
                    $this->set_parameter(PARAM_CUR_PLAYLIST_ID, $id);
                }
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
            hd_debug_print("clear_playlist_cache: remove $tmp_file");
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
            $item->params[PARAM_URI] = $m3u8source;
            $item->name = $m[2];
            $xmltv_sources->put(Hashed_Array::hash($m3u8source), $item);
        }

        $provider = $this->get_current_provider();
        if (!is_null($provider)) {
            $sources = $provider->getConfigValue(CONFIG_XMLTV_SOURCES);
            if (!empty($sources)) {
                foreach ($sources as $source) {
                    if (!preg_match(HTTP_PATTERN, $source, $m)) continue;

                    $item = new Named_Storage();
                    $item->type = PARAM_LINK;
                    $item->params[PARAM_URI] = $source;
                    $item->name = $m[2];
                    $xmltv_sources->put(Hashed_Array::hash($source), $item);
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
        if (!is_null($xmltv_source)) {
            $this->set_setting(PARAM_XMLTV_SOURCE_KEY, $key);
            $this->set_active_xmltv_source($xmltv_source->params[PARAM_URI]);
        }
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
            $title = $item->name;
            if ($item->type === PARAM_PROVIDER) {
                $provider = $this->get_provider($item->params[PARAM_PROVIDER]);
                if (!is_null($provider)) {
                    $icon = $provider->getLogo();
                    if ($item->name !== $provider->getName()) {
                        $title .= " ({$provider->getName()})";
                    }
                }
            } else if ($item->type === PARAM_LINK) {
                $icon = "link.png";
            } else if ($item->type === PARAM_FILE) {
                $icon = "m3u_file.png";
            }

            $menu_items[] = $this->create_menu_item($handler,
                ACTION_PLAYLIST_SELECTED,
                $title,
                ($cur !== $key) ? $icon : "check.png",
                array(LIST_IDX => $key));
        }

        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->create_menu_item($handler, ACTION_RELOAD, TR::t('refresh_playlist'), "refresh.png", array('reload_action' => 'playlist'));

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
        $menu_items[] = $this->create_menu_item($handler, ACTION_RELOAD, TR::t('refresh_epg'), "refresh.png", array('reload_action' => 'epg'));

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

        $provider = $this->get_current_provider();
        $epg_preset = is_null($provider) ? '?' : $provider->getConfigValue(EPG_JSON_PRESET);
        $menu_items[] = $this->create_menu_item($handler, ENGINE_JSON, TR::t('setup_epg_cache_json__1', $epg_preset),
            ($engine === ENGINE_JSON) ? "check.png" : null
        );
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
            if ($group_id === HISTORY_GROUP_ID && $this->get_playback_points()->size() !== 0) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_history'), "brush.png");
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            } else if ($group_id === FAVORITES_GROUP_ID && $this->tv->get_special_group($group_id)->get_items_order()->size() !== 0) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "brush.png");
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            } else if ($group_id === CHANGED_CHANNELS_GROUP_ID) {
                $menu_items[] = $this->create_menu_item($handler, ACTION_ITEMS_CLEAR, TR::t('clear_changed'), "brush.png");
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
            } else if ($this->tv->get_special_group($group_id) === null) {
                if ($is_classic) {
                    $menu_items = array_merge($menu_items, $this->edit_hidden_menu($handler, $group_id));
                } else {
                    $menu_items[] = $this->create_menu_item($handler, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_group'), "hide.png");
                }
                $menu_items[] = $this->create_menu_item($handler, ACTION_SORT_POPUP, TR::t('sort_popup_menu'), "sort.png");
            }

            $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
        }

        if ($is_classic) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_CHANGE_GROUP_ICON, TR::t('change_group_icon'), "image.png");
        } else {
            $menu_items[] = $this->create_menu_item($handler, ACTION_TOGGLE_ICONS_TYPE, TR::t('tv_screen_toggle_icons_aspect'), "image.png");
        }
        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

        if ($this->get_playlists()->size()) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_CHANGE_PLAYLIST, TR::t('change_playlist'), "playlist.png");
        }

        $is_xmltv_engine = $this->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === ENGINE_XMLTV;
        if ($is_xmltv_engine && $this->get_all_xmltv_sources()->size()) {
            $menu_items[] = $this->create_menu_item($handler, ACTION_CHANGE_EPG_SOURCE, TR::t('change_epg_source'), "epg.png");
        }

        $provider = $this->get_current_provider();
        if (!is_null($provider)) {
            $epg_url = $this->get_epg_preset_url();
            if (!empty($epg_url)) {
                $engine = TR::load_string(($is_xmltv_engine ? 'setup_epg_cache_xmltv' : 'setup_epg_cache_json'));
                $menu_items[] = $this->create_menu_item($handler,
                    ACTION_EPG_CACHE_ENGINE, TR::t('setup_epg_cache_engine__1', $engine), "engine.png");
            }

            if ($provider->hasApiCommand(API_COMMAND_INFO)) {
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);
                $menu_items[] = $this->create_menu_item($handler, ACTION_INFO_DLG, TR::t('subscription'), "info.png");
            }

            $menu_items[] = $this->create_menu_item($handler,
                ACTION_EDIT_PROVIDER_DLG,
                TR::t('edit_account'),
                $provider->getLogo(),
                array(PARAM_PROVIDER => $provider->getId())
            );
        }

        $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

        return $menu_items;
    }

    public function create_plugin_title()
    {
        $playlist = $this->get_current_playlist();
        $name = is_null($playlist) ? '' : $playlist->name;
        $plugin_name = $this->plugin_info['app_caption'];
        $name = empty($name) ? $plugin_name : "$plugin_name ($name)";
        hd_debug_print("plugin title: $name");
        return $name;
    }

    /**
     * @param $handler
     * @param string $provider_id
     * @param string $playlist_id
     * @return array|null
     */
    public function do_edit_provider_dlg($handler, $provider_id, $playlist_id = '')
    {
        hd_debug_print(null, true);

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if ($provider_id === 'current') {
            $playlist_id = $this->get_active_playlist_key();
            $provider = $this->get_current_provider();
            $playlist = $this->get_playlist($playlist_id);
            if (!is_null($playlist)) {
                $name = $playlist->name;
            }
        } else if ($playlist_id === null) {
            // add new provider
            $provider = $this->get_provider($provider_id);
            hd_debug_print("new provider : $provider", true);
        } else {
            // edit existing provider
            $playlist = $this->get_playlist($playlist_id);
            if (!is_null($playlist)) {
                $name = $playlist->name;
                hd_debug_print("playlist info : $playlist", true);
                $provider = $this->init_provider($playlist);
                hd_debug_print("existing provider : " . json_encode($provider), true);
            } else {
                $provider = $this->get_provider($provider_id);
            }
        }

        if (is_null($provider)) {
            return $defs;
        }

        if (empty($name)) {
            $name = $provider->getName();
        }

        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_EDIT_NAME, TR::t('name'), $name,
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        switch ($provider->getType()) {
            case PROVIDER_TYPE_PIN:
                Control_Factory::add_text_field($defs, $handler, null,
                    CONTROL_PASSWORD, TR::t('token'), $provider->getCredential(MACRO_PASSWORD),
                    false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
                break;

            case PROVIDER_TYPE_LOGIN:
            case PROVIDER_TYPE_LOGIN_TOKEN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
                Control_Factory::add_text_field($defs, $handler, null,
                    CONTROL_LOGIN, TR::t('login'), $provider->getCredential(MACRO_LOGIN),
                    false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
                Control_Factory::add_text_field($defs, $handler, null,
                    CONTROL_PASSWORD, TR::t('password'), $provider->getCredential(MACRO_PASSWORD),
                    false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
                break;

            case PROVIDER_TYPE_EDEM:
                $subdomain = $provider->getCredential(MACRO_SUBDOMAIN);
                if (!empty($subdomain) && $subdomain !== $provider->getConfigValue(CONFIG_SUBDOMAIN)) {
                    Control_Factory::add_text_field($defs, $handler, null,
                        CONTROL_OTT_SUBDOMAIN, TR::t('domain'), $provider->getCredential(MACRO_SUBDOMAIN),
                        false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
                }
                Control_Factory::add_text_field($defs, $handler, null,
                    CONTROL_OTT_KEY, TR::t('ottkey'), $provider->getCredential(MACRO_OTTKEY),
                    false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

                Control_Factory::add_text_field($defs, $handler, null,
                    CONTROL_VPORTAL, TR::t('vportal'), $provider->getCredential(MACRO_VPORTAL),
                    false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
                break;

            default:
                return null;
        }

        $streams = $provider->GetStreams();
        if (!empty($streams)) {
            $idx = $provider->getCredential(MACRO_STREAM_ID);
            if (empty($idx)) {
                $idx = key($streams);
            }
            hd_debug_print("streams ($idx): " . json_encode($streams), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_STREAM,
                TR::t('stream'), $idx, $streams, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $domains = $provider->GetDomains();
        if (!empty($domains)) {
            $idx = $provider->getCredential(MACRO_DOMAIN_ID);
            if (empty($idx)) {
                $idx = key($domains);
            }
            hd_debug_print("domains ($idx): " . json_encode($domains), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_DOMAIN,
                TR::t('domain'), $idx, $domains, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $servers = $provider->GetServers();
        if (!empty($servers)) {
            $idx = $provider->getCredential(MACRO_SERVER_ID);
            if (empty($idx)) {
                $idx = key($servers);
            }
            hd_debug_print("servers ($idx): " . json_encode($servers), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_SERVER,
                TR::t('server'), $idx, $servers, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $devices = $provider->GetDevices();
        if (!empty($devices)) {
            $idx = $provider->getCredential(MACRO_DEVICE_ID);
            if (empty($idx)) {
                $idx = key($devices);
            }
            hd_debug_print("devices ($idx): " . json_encode($devices), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_DEVICE,
                TR::t('device'), $idx, $devices, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $qualities = $provider->GetQualities();
        if (!empty($qualities)) {
            $idx = $provider->getCredential(MACRO_QUALITY_ID);
            if (empty($idx)) {
                $idx = key($qualities);
            }
            hd_debug_print("qualities ($idx): " . json_encode($qualities), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_QUALITY,
                TR::t('quality'), $idx, $qualities, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler,
            array(PARAM_PROVIDER => $provider->getId(), CONTROL_EDIT_ITEM => $playlist_id),
            ACTION_EDIT_PROVIDER_DLG_APPLY,
            TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog("{$provider->getName()} ({$provider->getId()})", $defs, true);
    }

    /**
     * @param $user_input
     * @return null|string
     */
    public function apply_edit_provider_dlg($user_input)
    {
        hd_debug_print(null, true);

        if ($user_input->parent_media_url === Starnet_Tv_Groups_Screen::ID) {
            $provider = $this->get_current_provider();
        } else {
            $provider = $this->get_provider($user_input->{PARAM_PROVIDER});
        }

        if (is_null($provider)) {
            return null;
        }

        $item = new Named_Storage();
        $item->type = PARAM_PROVIDER;
        $item->name = $user_input->{CONTROL_EDIT_NAME};

        $params[PARAM_PROVIDER] = $user_input->{PARAM_PROVIDER};
        $id = $user_input->{CONTROL_EDIT_ITEM};
        switch ($provider->getType()) {
            case PROVIDER_TYPE_PIN:
                $params[MACRO_PASSWORD] = $user_input->{CONTROL_PASSWORD};
                $id = empty($id) ? Hashed_Array::hash($item->type.$item->name.$params[MACRO_PASSWORD]) : $id;
                $not_set = empty($params[MACRO_PASSWORD]);
                break;

            case PROVIDER_TYPE_LOGIN:
            case PROVIDER_TYPE_LOGIN_TOKEN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
                $params[MACRO_LOGIN] = $user_input->{CONTROL_LOGIN};
                $params[MACRO_PASSWORD] = $user_input->{CONTROL_PASSWORD};
                $id = empty($id) ? Hashed_Array::hash($item->type.$item->name.$params[MACRO_LOGIN].$params[MACRO_PASSWORD]) : $id;
                $not_set = empty($params[MACRO_LOGIN]) || empty($params[MACRO_PASSWORD]);
                if ($provider->getType() === PROVIDER_TYPE_LOGIN_STOKEN) {
                    $provider->setCredential(MACRO_TOKEN, '');
                }
                break;

            case PROVIDER_TYPE_EDEM:
                if (!empty($user_input->{CONTROL_OTT_SUBDOMAIN})) {
                    $params[MACRO_SUBDOMAIN] = $user_input->{CONTROL_OTT_SUBDOMAIN};
                } else {
                    $params[MACRO_SUBDOMAIN] = $provider->getConfigValue(CONFIG_SUBDOMAIN);
                }
                $params[MACRO_OTTKEY] = $user_input->{CONTROL_OTT_KEY};
                $params[MACRO_VPORTAL] = $user_input->{CONTROL_VPORTAL};

                $id = empty($id) ? Hashed_Array::hash($item->type.$item->name.$params[MACRO_SUBDOMAIN].$params[MACRO_OTTKEY]) : $id;
                $not_set = empty($params[MACRO_OTTKEY]);
                break;

            default:
                return null;
        }

        if ($not_set) {
            return null;
        }

        if (isset($user_input->{CONTROL_DOMAIN})) {
            $params[MACRO_DOMAIN_ID] = $user_input->{CONTROL_DOMAIN};
        }

        if (isset($user_input->{CONTROL_SERVER})) {
            $params[MACRO_SERVER_ID] = $user_input->{CONTROL_SERVER};
            $provider->SetServer($user_input->{CONTROL_SERVER});
        }

        if (isset($user_input->{CONTROL_DEVICE})) {
            $params[MACRO_DEVICE_ID] = $user_input->{CONTROL_DEVICE};
        }

        if (isset($user_input->{CONTROL_QUALITY})) {
            $params[MACRO_QUALITY_ID] = $user_input->{CONTROL_QUALITY};
        }

        if (isset($user_input->{CONTROL_STREAM})) {
            $params[MACRO_STREAM_ID] = $user_input->{CONTROL_STREAM};
        }

        $item->params = $params;

        hd_debug_print("compiled provider info: $item->name, provider params: " . raw_json_encode($item->params), true);
        $this->get_playlists()->set($id, $item);
        $this->save_parameters(true);
        $this->clear_playlist_cache($id);

        if ($this->get_active_playlist_key() === $id) {
            $this->set_active_playlist_key($id);
        }

        return $id;
    }

    /**
     * @param $channel_id
     * @return array|null
     */
    public function do_show_channel_info($channel_id)
    {
        $channel = $this->tv->get_channel($channel_id);
        if (is_null($channel)) {
            return null;
        }

        $info = "ID: " . $channel->get_id() . PHP_EOL;
        $info .= "Name: " . $channel->get_title() . PHP_EOL;
        $info .= "Archive: " . $channel->get_archive() . PHP_EOL;
        $info .= "Protected: " . TR::load_string($channel->is_protected() ? 'yes' : 'no') . PHP_EOL;
        $info .= "EPG IDs: " . implode(', ', $channel->get_epg_ids()) . PHP_EOL;
        $info .= "Timeshift hours: " . $channel->get_timeshift_hours() . PHP_EOL;
        $groups = array();
        foreach ($channel->get_groups() as $group) {
            $groups[] = $group->get_id();
        }
        $info .= "Categories: " . implode(', ', $groups) . PHP_EOL;

        $info .= PHP_EOL;
        $info .= "Icon URL: " . wrap_string_to_lines($channel->get_icon_url(), 70) . PHP_EOL;

        try {
            $live_url = $this->tv->generate_stream_url($channel_id, -1, true);
            $info .= "Live URL: " . wrap_string_to_lines($live_url, 70) . PHP_EOL;
        } catch(Exception $ex) {
            hd_debug_print($ex);
        }

        if ($channel->get_archive() > 0 ) {
            try {
                $archive_url = $this->tv->generate_stream_url($channel_id, time() - 3600, true);
                $info .= "Archive URL: " . wrap_string_to_lines($archive_url, 70) . PHP_EOL;
            } catch (Exception $ex) {
                hd_debug_print($ex);
            }
        }

        $dune_params = $this->tv->generate_dune_params($channel);
        if (!empty($dune_params)) {
            $info .= PHP_EOL . "Params: $dune_params" . PHP_EOL;
        }

        if (!empty($live_url) && with_network_manager()) {
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
                foreach(explode("\n", $output) as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    if (strpos($line, "Output") !== false) break;
                    if (strpos($line, "Stream") !== false) {
                        $info .= preg_replace("/ \([\[].*\)| \[.*\]|, [0-9k\.]+ tb[rcn]|, q=[0-9\-]+/", "", $line) . PHP_EOL;
                    }
                }
            }
        }

        Control_Factory::add_multiline_label($defs, null, $info, 12);
        Control_Factory::add_vgap($defs, 20);

        $text = sprintf("<gap width=%s/><icon>%s</icon><gap width=10/><icon>%s</icon><text color=%s size=small>  %s</text>",
            1160,
            get_image_path('page_plus_btn.png'),
            get_image_path('page_minus_btn.png'),
            DEF_LABEL_TEXT_COLOR_SILVER,
            TR::load_string('scroll_page')
        );
        Control_Factory::add_smart_label($defs, '', $text);
        Control_Factory::add_vgap($defs, -80);

        Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 250, true);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('channel_info_dlg'), $defs, true, 1700);
    }

    /**
     * @param $handler
     * @return array|null
     */
    public function do_show_subscription($handler)
    {
        $provider = $this->get_current_provider();
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
        $provider = $this->get_current_provider();
        if (is_null($provider)) {
            return null;
        }

        return $provider->GetPayUI();
    }

    /**
     * @param $handler
     * @param $action
     * @return array
     */
    public function show_password_dialog($handler, $action)
    {
        $pass_settings = $this->get_parameter(PARAM_SETTINGS_PASSWORD);
        if (empty($pass_settings)) {
            return User_Input_Handler_Registry::create_action($handler, $action);
        }

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $handler, null, 'pass', TR::t('setup_pass'),
            '', true, true, false, true, 500, true);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, array("action" => $action),
            ACTION_PASSWORD_APPLY, TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('setup_enter_pass'), $defs, true);
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
                    ViewParams::background_order => 'before_all',
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
