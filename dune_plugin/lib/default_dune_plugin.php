<?php
///////////////////////////////////////////////////////////////////////////

require_once 'tr.php';
require_once 'mediaurl.php';
require_once 'user_input_handler_registry.php';
require_once 'action_factory.php';
require_once 'control_factory.php';
require_once 'control_factory_ext.php';
require_once 'catchup_params.php';
require_once 'epg_manager.php';
require_once 'm3u/M3uParser.php';

class Default_Dune_Plugin implements DunePlugin
{
    const AUTHOR_LOGO = "ProIPTV by sharky72  [ ´¯¤¤¯(ºº)¯¤¤¯` ]";
    const SANDWICH_BASE = 'gui_skin://special_icons/sandwich_base.aai';
    const SANDWICH_MASK = 'cut_icon://{name=sandwich_mask}';
    const SANDWICH_COVER = 'cut_icon://{name=sandwich_cover}';

    /////////////////////////////////////////////////////////////////////////////
    // views variables
    const TV_SANDWICH_WIDTH = 245;
    const TV_SANDWICH_HEIGHT = 140;
    const DEFAULT_CHANNEL_ICON_PATH = 'plugin_file://icons/default_channel.png';

    /**
     * @var array
     */
    public $plugin_info;

    /**
     * @var bool
     */
    public $new_ui_support;

    /**
     * @var M3uParser
     */
    public $m3u_parser;

    /**
     * @var Epg_Manager
     */
    public $epg_man;

    /**
     * @var Starnet_Tv
     */
    public $tv;

    /**
     * @var Starnet_Tv_Groups_Screen
     */
    public $tv_groups_screen;

    /**
     * @var Starnet_Tv_Channel_List_Screen
     */
    public $tv_channels_screen;

    /**
     * @var Starnet_Setup_Screen
     */
    public $main_setup_screen;

    /**
     * @var Starnet_Playlists_Setup_Screen
     */
    public $channels_setup_screen;

    /**
     * @var Starnet_Interface_Setup_Screen
     */
    public $interface_setup_screen;

    /**
     * @var Starnet_Epg_Setup_Screen
     */
    public $epg_setup_screen;

    /**
     * @var Starnet_Streaming_Setup_Screen
     */
    public $stream_setup_screen;

    /**
     * @var Starnet_History_Setup_Screen
     */
    public $history_setup_screen;

    /**
     * @var Starnet_Folder_Screen
     */
    public $folder_screen;

    /**
     * @var Starnet_Edit_List_Screen
     */
    public $edit_list_screen;

    /**
     * @var Starnet_Tv_Favorites_Screen
     */
    public $tv_favorites_screen;

    /**
     * @var Starnet_TV_History_Screen
     */
    public $tv_history_screen;

    /**
     * @var Playback_Points
     */
    public $playback_points;

    /**
     * @var array|Screen[]
     */
    private $screens;

    /**
     * @var Ordered_Array
     */
    private $playlists;

    /**
     * @var array
     */
    private $xmltv_sources;

    /**
     * @var string
     */
    protected $last_error;

    /**
     * @var array
     */
    private $settings;

    /**
     * @var array
     */
    private $parameters;

    ///////////////////////////////////////////////////////////////////////

    protected function __construct()
    {
        $this->plugin_info = get_plugin_manifest_info();
        $this->screens = array();
        $this->new_ui_support = HD::rows_api_support();
        $this->m3u_parser = new M3uParser();
        $this->epg_man = new Epg_Manager($this);
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Screen support.
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * @param $object
     */
    public function create_screen($object)
    {
        if (!is_null($object) && method_exists($object, 'get_id')) {
            //'' . get_class($object));
            $this->add_screen($object);
            User_Input_Handler_Registry::get_instance()->register_handler($object);
        } else {
            hd_debug_print(get_class($object) . ": Screen class is illegal. get_id method not defined!");
        }
    }

    /**
     * @param Screen $scr
     */
    protected function add_screen(Screen $scr)
    {
        if (isset($this->screens[$scr->get_id()])) {
            hd_debug_print("Error: screen (id: " . $scr->get_id() . ") already registered.");
        } else {
            $this->screens[$scr->get_id()] = $scr;
        }
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $screen_id
     * @return Screen
     * @throws Exception
     */
    protected function get_screen_by_id($screen_id)
    {
        if (isset($this->screens[$screen_id])) {
            // hd_debug_print("'$screen_id'");
            return $this->screens[$screen_id];
        }

        hd_debug_print("Error: no screen with id '$screen_id' found.");
        HD::print_backtrace();
        throw new Exception('Screen not found');
    }

    ///////////////////////////////////////////////////////////////////////////

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
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);
        return User_Input_Handler_Registry::get_instance()->handle_user_input($user_input, $plugin_cookies);
    }

    /**
     * @param string $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_folder_view($media_url, &$plugin_cookies)
    {
        //hd_debug_print("MediaUrl: $media_url");
        $decoded_media_url = MediaURL::decode($media_url);

        return $this->get_screen_by_url($decoded_media_url)->get_folder_view($decoded_media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param string $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_next_folder_view($media_url, &$plugin_cookies)
    {
        $decoded_media_url = MediaURL::decode($media_url);

        return $this->get_screen_by_url($decoded_media_url)->get_next_folder_view($decoded_media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param string $media_url
     * @param int $from_ndx
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_regular_folder_items($media_url, $from_ndx, &$plugin_cookies)
    {
        $decoded_media_url = MediaURL::decode($media_url);

        return $this->get_screen_by_url($decoded_media_url)->get_folder_range($decoded_media_url, $from_ndx, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $media_url
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_tv_info($media_url, &$plugin_cookies)
    {
        if (is_null($this->tv)) {
            hd_debug_print('TV is not supported');
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        $decoded_media_url = MediaURL::decode($media_url);

        return $this->tv->get_tv_info($decoded_media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $media_url
     * @param $plugin_cookies
     * @return string
     * @throws Exception
     */
    public function get_tv_stream_url($media_url, &$plugin_cookies)
    {
        if (is_null($this->tv)) {
            hd_debug_print('TV is not supported');
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $media_url;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $channel_id
     * @param int $archive_tm_sec
     * @param string $protect_code
     * @param $plugin_cookies
     * @return string
     * @throws Exception
     */
    public function get_tv_playback_url($channel_id, $archive_tm_sec, $protect_code, &$plugin_cookies)
    {
        if (is_null($this->tv)) {
            hd_debug_print('TV is not supported');
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $this->tv->get_tv_playback_url($channel_id, $archive_tm_sec, $protect_code, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $channel_id
     * @param int $day_start_tm_sec
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_day_epg($channel_id, $day_start_tm_sec, &$plugin_cookies)
    {
        if (is_null($this->tv)) {
            hd_debug_print('TV is not supported');
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        $day_epg = array();

        try {
            // get channel by hash
            $channel = $this->tv->get_channel($channel_id);
        } catch (Exception $ex) {
            hd_debug_print("Can't get channel with ID: $channel_id");
            return $day_epg;
        }

        // correct day start to local timezone
        $day_start_tm_sec -= get_local_time_zone_offset();

        //hd_debug_print("day_start timestamp: $day_start_ts (" . format_datetime("Y-m-d H:i", $day_start_ts) . ")");
        $day_epg_items = $this->epg_man->get_day_epg_items($channel, $day_start_tm_sec);
        if ($day_epg_items !== false) {
            // get personal time shift for channel
            $time_shift = 3600 * ($channel->get_timeshift_hours() + (isset($plugin_cookies->epg_shift) ? $plugin_cookies->epg_shift : 0));
            //hd_debug_print("EPG time shift $time_shift");
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

                //hd_debug_print(format_datetime("m-d H:i", $tm_start) . " - " . format_datetime("m-d H:i", $tm_end) . " {$value[Epg_Params::EPG_NAME]}");
            }
        }

        return $day_epg;
    }

    public function get_program_info($channel_id, $program_ts, $plugin_cookies)
    {
        $program_ts = ($program_ts > 0 ? $program_ts : time());
        //hd_debug_print("for $channel_id at time $program_ts " . format_datetime("Y-m-d H:i", $program_ts));
        $day_start = date("Y-m-d", $program_ts);
        $day_ts = strtotime($day_start) + get_local_time_zone_offset();
        $day_epg = $this->get_day_epg($channel_id, $day_ts, $plugin_cookies);
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
     * @param string $op_type
     * @param string $channel_id
     * @param $plugin_cookies
     * @return array
     */
    public function change_tv_favorites($op_type, $channel_id, &$plugin_cookies)
    {
        if (is_null($this->tv)) {
            hd_debug_print('TV is not supported');
            HD::print_backtrace();
            return array();
        }

        return $this->change_favorites($op_type, $channel_id);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_vod_info($media_url, &$plugin_cookies)
    {
        hd_debug_print('VOD is not supported');
        HD::print_backtrace();
        throw new Exception('VOD is not supported');
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $media_url
     * @param $plugin_cookies
     * @return string
     */
    public function get_vod_stream_url($media_url, &$plugin_cookies)
    {
        hd_debug_print('VOD is not supported');
        return '';
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Parameters and settings storage implementation
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * Get settings for selected playlist
     *
     * @param string $type
     * @param mixed|null $default
     * @return mixed
     */
    public function get_settings($type, $default = null)
    {
        $hash = $this->get_playlist_hash();
        if (!isset($this->settings[$hash])) {
            $this->settings[$hash] = HD::get_items(get_data_path("$hash.settings"), true, false);
            hd_debug_print("First load: $hash.settings");
        }

        if (!isset($this->settings[$hash][$type])) {
            return $default;
        }

        $default_type = gettype($default);
        $param_type = gettype($this->settings[$hash][$type]);
        if ($default_type === 'object' && $param_type !== $default_type) {
            hd_debug_print("Settings type requested: $default_type. But $param_type loaded");
            return $default;
        }

        return $this->settings[$hash][$type];
    }

    /**
     * Set settings for selected playlist
     *
     * @param string $type
     * @param mixed $val
     */
    public function set_settings($type, $val)
    {
        $hash = $this->get_playlist_hash();
        $this->settings[$hash][$type] = $val;
        HD::put_data_items("$hash.settings", $this->settings[$hash], false);
        //hd_debug_print("saved $hash.settings");
    }

    /**
     * Remove settings for selected playlist
     */
    public function remove_settings()
    {
        $hash = $this->get_playlist_hash();
        unset($this->settings[$hash]);
        hd_debug_print("remove $hash.settings");
        HD::erase_data_items("$hash.settings");
    }

    /**
     * remove all settings and clear cache when uninstall plugin
     */
    public function uninstall_plugin()
    {
        $this->epg_man->clear_all_epg_cache();

        if ($this->get_parameters(PARAM_HISTORY_PATH) === get_data_path()) {
            $this->playback_points->clear_points();
        }

        foreach (array_keys($this->settings) as $hash) {
            unset($this->settings[$hash]);
            hd_debug_print("remove $hash.settings");
            HD::erase_data_items("$hash.settings");
        }

        HD::erase_data_items(PLUGIN_PARAMS);
    }

    /**
     * Get plugin parameters
     * Parameters does not depend on playlists and used globally
     *
     * @param string $type
     * @param mixed|null $default
     * @return mixed
     */
    public function get_parameters($type, $default = null)
    {
        if (!isset($this->parameters)) {
            $this->parameters = HD::get_data_items(PLUGIN_PARAMS, true, false);
            hd_debug_print("First load: " . PLUGIN_PARAMS);
        }

        if (!isset($this->parameters[$type])) {
            return $default;
        }

        $default_type = gettype($default);
        $param_type = gettype($this->parameters[$type]);
        if ($default_type === 'object' && $param_type !== $default_type) {
            hd_debug_print("Param type requested: $default_type. But $param_type loaded");
            return $default;
        }

        return $this->parameters[$type];
    }

    /**
     * set plugin parameters
     *
     * @param string $type
     * @param mixed $val
     */
    public function set_parameters($type, $val)
    {
        //hd_debug_print("Saved: $type to: " . PLUGIN_PARAMS);
        $this->parameters[$type] = $val;
        HD::put_data_items(PLUGIN_PARAMS, $this->parameters, false);
    }

    public function toggle_setting($param, $default)
    {
        $old = $this->get_settings($param, $default);
        $new = ($old === SetupControlSwitchDefs::switch_on)
            ? SetupControlSwitchDefs::switch_off
            : SetupControlSwitchDefs::switch_on;
        $this->set_settings($param, $new);
    }

    public function toggle_parameter($param, $default)
    {
        $old = $this->get_parameters($param, $default);
        $new = ($old === SetupControlSwitchDefs::switch_on)
            ? SetupControlSwitchDefs::switch_off
            : SetupControlSwitchDefs::switch_on;
        $this->set_parameters($param, $new);
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Methods
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * Initialize and parse selected playlist
     *
     * @return bool
     */
    public function init_playlist()
    {
        // first check if playlist in cache
        if ($this->get_playlists()->size() === 0)
            return false;

        $this->init_user_agent();

        $force = false;
        $tmp_file = $this->get_playlist_cache();
        if (file_exists($tmp_file)) {
            $mtime = filemtime($tmp_file);
            if (time() - $mtime > 3600) {
                hd_debug_print("Playlist cache expired. Forcing reload");
                $force = true;
            }
        } else {
            $force = true;
        }

        try {
            if ($force !== false) {
                $url = $this->get_playlists()->get_selected_item();
                if (empty($url)) {
                    hd_debug_print("Tv playlist not defined");
                    throw new Exception('Tv playlist not defined');
                }

                hd_debug_print("m3u playlist: $url");
                if (preg_match('|https?://|', $url)) {
                    $contents = HD::http_get_document($url);
                } else {
                    $contents = @file_get_contents($url);
                    if ($contents === false) {
                        throw new Exception("Can't read playlist: $url");
                    }
                }
                file_put_contents($tmp_file, $contents);
            }

            //  Is already parsed?
            $this->m3u_parser->setupParser($tmp_file, $force);
            if ($this->m3u_parser->getEntriesCount() === 0) {
                if (!$this->m3u_parser->parseInMemory()) {
                    $this->set_last_error("Ошибка чтения плейлиста!");
                    throw new Exception("Can't read playlist");
                }

                $count = $this->m3u_parser->getEntriesCount();
                if ($count === 0) {
                    $this->set_last_error("Пустой плейлист!");
                    hd_debug_print($this->last_error);
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

        return true;
    }

    /**
     * @return void
     */
    public function init_user_agent()
    {
        $user_agent = $this->get_settings(PARAM_USER_AGENT, HD::get_dune_user_agent());
        if ($user_agent !== HD::get_dune_user_agent()) {
            HD::set_dune_user_agent($user_agent);
        }
    }

    /**
     * @return Ordered_Array
     */
    public function get_playlists()
    {
        if (is_null($this->playlists)) {
            $this->playlists = new Ordered_Array($this, PARAM_PLAYLISTS, 'parameters');
        }

        return $this->playlists;
    }

    /**
     * $param int $idx
     * @return string
     */
    public function get_playlist($idx)
    {
        return $this->get_playlists()->get_item_by_idx($idx);
    }

    /**
     * @return string
     */
    public function get_playlist_hash()
    {
        return hash('crc32', $this->get_playlists()->get_selected_item());
    }

    /**
     * @return string
     */
    public function get_playlist_cache()
    {
        return get_temp_path($this->get_playlist_hash() . "_playlist.m3u8");
    }

    /**
     * Clear downloaded playlist
     * @return void
     */
    public function clear_playlist_cache()
    {
        $tmp_file = $this->get_playlist_cache();
        hd_debug_print($tmp_file);
        if (file_exists($tmp_file)) {
            copy($tmp_file, $tmp_file . ".m3u");
            unlink($tmp_file);
        }
    }

    /**
     * @return Ordered_Array
     */
    public function get_favorites()
    {
        return $this->tv->get_special_group(FAV_CHANNEL_GROUP_ID)->get_items_order();
    }

    /**
     * @param string $fav_op_type
     * @param string $channel_id
     * @return array
     */
    public function change_favorites($fav_op_type, $channel_id)
    {
        $favorites = $this->get_favorites();
        switch ($fav_op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                if ($favorites->add_item($channel_id)) {
                    hd_debug_print("Add channel $channel_id to favorites");
                }
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                if ($favorites->remove_item($channel_id)) {
                    hd_debug_print("Remove channel $channel_id from favorites");
                }
                break;

            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $favorites->arrange_item($channel_id, Ordered_Array::UP);
                break;

            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $favorites->arrange_item($channel_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("Clear favorites");
                $favorites->clear();
                break;
        }

        return Starnet_Epfs_Handler::invalidate_folders(array(
            Starnet_Tv_Favorites_Screen::ID,
            Starnet_Tv_Channel_List_Screen::get_media_url_string(ALL_CHANNEL_GROUP_ID))
        );
    }

    public function get_special_groups_count($plugin_cookies)
    {
        $groups_cnt = 0;
        if ($this->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::CONTROL_SHOW_ALL)) $groups_cnt++;
        if ($this->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::CONTROL_SHOW_FAVORITES)) $groups_cnt++;
        if ($this->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::CONTROL_SHOW_HISTORY)) $groups_cnt++;

        return $groups_cnt;
    }

    public function is_special_groups_enabled($plugin_cookies, $id)
    {
        return (!isset($plugin_cookies->{$id}) || $plugin_cookies->{$id} === SetupControlSwitchDefs::switch_on);
    }

    /**
     * set xmltv source
     * @param $idx
     * @param $xmltv_sources
     * @return void
     */
    public function set_xmltv_source($idx, $xmltv_sources)
    {
        $this->xmltv_sources[$idx] = $xmltv_sources;
    }

    /**
     * set xmltv source
     * @return Ordered_Array|null
     */
    public function get_xmltv_source()
    {
        $source_type = $this->get_xmltv_source_type();
        return isset($this->xmltv_sources[$source_type]) ? $this->xmltv_sources[$source_type] : null;
    }

    /**
     * get xmltv source type
     * @return string
     */
    public function get_xmltv_source_type()
    {
        return $this->get_settings(PARAM_EPG_SOURCE_TYPE, PARAM_EPG_SOURCE_INTERNAL);
    }

    /**
     * set xmltv source type
     * @param string $type
     */
    public function set_xmltv_source_type($type)
    {
        $this->set_settings(PARAM_EPG_SOURCE_TYPE, $type);
    }

    /**
     * tell epg manager to reload xmltv source
     * @return void
     */
    public function update_xmltv_source()
    {
        $m3u_xmltv_sources = new Ordered_Array();
        foreach ($this->m3u_parser->getXmltvSources() as $source) {
            $m3u_xmltv_sources->add_item($source);
        }

        $m3u_xmltv_sources->set_saved_pos($this->get_settings(PARAM_INTERNAL_EPG_IDX, 0));
        $this->set_xmltv_source(PARAM_EPG_SOURCE_INTERNAL, $m3u_xmltv_sources);
        $this->set_xmltv_source(PARAM_EPG_SOURCE_EXTERNAL, new Ordered_Array($this, PARAM_CUSTOM_XMLTV_SOURCES));

        $source = $this->get_xmltv_source();
        if (is_null($source)) {
            $this->epg_man->set_xmltv_url(null);
            hd_debug_print("no xmltv source defined for this playlist");
        } else {
            $this->epg_man->set_xmltv_url($source->get_selected_item());
            $this->epg_man->index_xmltv_channels();
        }
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
        $channel = $this->tv->get_channel($channel_id);
        if (is_null($channel)) {
            throw new Exception("Channel with id: $channel_id not found");
        }

        // replace all macros
        if ((int)$archive_ts <= 0) {
            $stream_url = $channel->get_url();
        } else {
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

            $stream_url = $channel->get_archive_url();
            foreach ($replaces as $key => $value) {
                $stream_url = str_replace($key, $value, $stream_url);
            }
        }

        if (empty($stream_url)) {
            throw new Exception("Empty url!");
        }

        if (!preg_match('/\.' . VIDEO_PATTERN . '$/i', $stream_url)
            && !preg_match('/\.' . AUDIO_PATTERN . '$/i', $stream_url)) {
            $stream_url = HD::make_ts($stream_url);
        }

        $ext_params = $channel->get_ext_params();
        if (isset($ext_params[PARAM_DUNE_PARAMS])) {
            //hd_debug_print("Additional dune params: $dune_params");

            $dune_params = "";
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

        return $stream_url;
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Misc.
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public function get_last_error()
    {
        return $this->last_error;
    }

    /**
     * @param string $error
     */
    public function set_last_error($error)
    {
        $this->last_error = $error;
    }

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
     * @param string $channel_id
     * @param int $archive_ts
     * @throws Exception
     */
    public function external_player_exec($channel_id, $archive_ts = -1)
    {
        $url = $this->generate_stream_url($channel_id, $archive_ts);
        $url = str_replace("ts://", "", $url);
        $param_pos = strpos($url, '|||dune_params');
        $url =  $param_pos!== false ? substr($url, 0, $param_pos) : $url;
        $cmd = 'am start -d "' . $url . '" -t "video/*" -a android.intent.action.VIEW 2>&1';
        hd_debug_print("play movie in the external player: $cmd");
        exec($cmd, $output);
        hd_debug_print("external player exec result code" . HD::ArrayToStr($output));
    }
}
