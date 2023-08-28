<?php
///////////////////////////////////////////////////////////////////////////

require_once 'tr.php';
require_once 'tv/tv.php';
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
     * @var int
     */
    private $playlist_idx = -1;

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
            //hd_print(__METHOD__ . ': ' . get_class($object));
            $this->add_screen($object);
            User_Input_Handler_Registry::get_instance()->register_handler($object);
        } else {
            hd_print(__METHOD__ . ": " . get_class($object) . ": Screen class is illegal. get_id method not defined!");
        }
    }

    /**
     * @param Screen $scr
     */
    protected function add_screen(Screen $scr)
    {
        if (isset($this->screens[$scr->get_id()])) {
            hd_print(__METHOD__ . ": Error: screen (id: " . $scr->get_id() . ") already registered.");
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
            // hd_print(__METHOD__ . ": '$screen_id'");
            return $this->screens[$screen_id];
        }

        hd_print(__METHOD__ . ": Error: no screen with id '$screen_id' found.");
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
        //hd_print(__METHOD__ . ": MediaUrl: $media_url");
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
            hd_print(__METHOD__ . ': TV is not supported');
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
            hd_print(__METHOD__ . ': TV is not supported');
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $this->tv->get_tv_stream_url($media_url, $plugin_cookies);
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
            hd_print(__METHOD__ . ': TV is not supported');
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
            hd_print(__METHOD__ . ': TV is not supported');
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $this->tv->get_day_epg($channel_id, $day_start_tm_sec, $plugin_cookies);
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
            hd_print(__METHOD__ . ': TV is not supported');
            HD::print_backtrace();
            return array();
        }

        return $this->tv->change_favorites($op_type, $channel_id, $plugin_cookies);
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
        hd_print(__METHOD__ . ': VOD is not supported');
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
        hd_print(__METHOD__ . ': VOD is not supported');
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
            //hd_print(__METHOD__ . ": loaded $hash.settings");
        }

        return isset($this->settings[$hash][$type]) ? $this->settings[$hash][$type] : $default;
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
        //hd_print(__METHOD__ . ": saved $hash.settings");
    }

    /**
     * Remove settings for selected playlist
     */
    public function remove_settings()
    {
        $hash = $this->get_playlist_hash();
        unset($this->settings[$hash]);
        hd_print(__METHOD__ . ": remove $hash.settings");
        HD::erase_data_items("$hash.settings");
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
        $default_type = gettype($default);
        if (!isset($this->parameters)) {
            $this->parameters = HD::get_data_items(PLUGIN_PARAMS, true, false);
            hd_print(__METHOD__ . " : First load: " . PLUGIN_PARAMS);
        }

        if (!isset($this->parameters[$type])) {
            return $default;
        }

        $param_type = gettype($this->parameters[$type]);
        if ($default_type === 'object' && $param_type !== $default_type) {
            hd_print(__METHOD__ . " : default: $default_type param: $param_type");
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
        //hd_print(__METHOD__ . ": Saved: $type to: " . PLUGIN_PARAMS);
        $this->parameters[$type] = $val;
        HD::put_data_items(PLUGIN_PARAMS, $this->parameters, false);
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

        $force = false;
        $tmp_file = $this->get_playlist_cache();
        if (file_exists($tmp_file)) {
            $mtime = filemtime($tmp_file);
            if (time() - $mtime > 3600) {
                hd_print(__METHOD__ . ": Playlist cache expired. Forcing reload");
                $force = true;
            }
        } else {
            $force = true;
        }

        try {
            if ($force !== false) {
                $url = $this->get_current_playlist();
                if (empty($url)) {
                    hd_print(__METHOD__ . ": Tv playlist not defined");
                    throw new Exception('Tv playlist not defined');
                }

                hd_print(__METHOD__ . ": m3u playlist: $url");
                if (preg_match('|https?://|', $url)) {
                    $contents = HD::http_get_document($url);
                } else {
                    $contents = file_get_contents($url);
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
                    hd_print(__METHOD__ . ": $this->last_error");
                    $this->clear_playlist_cache();
                    throw new Exception("Empty playlist");
                }

                hd_print(__METHOD__ . ": Total entries loaded from playlist m3u file: $count");
                HD::ShowMemoryUsage();
            }
        } catch (Exception $ex) {
            hd_print(__METHOD__ . ": Unable to load tv playlist: " . $ex->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @return Ordered_Array
     */
    public function get_playlists()
    {
        if (is_null($this->playlists)) {
            $this->playlists = $this->get_parameters(PARAM_PLAYLISTS, new Ordered_Array($this, PARAM_PLAYLISTS, 'parameters'));
        }

        return $this->playlists;
    }

    /**
     * @return int
     */
    public function get_playlists_idx()
    {
        if ($this->playlist_idx === -1) {
            $this->playlist_idx = $this->get_parameters(PARAM_PLAYLIST_IDX, 0);
        }

        return $this->playlist_idx;
    }

    /**
     * @oaram int $idx
     */
    public function set_playlists_idx($idx)
    {
        $this->playlist_idx = $idx;
        $this->set_parameters(PARAM_PLAYLIST_IDX, $this->playlist_idx);
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
    public function get_current_playlist()
    {
        return $this->get_playlist($this->get_playlists_idx());
    }

    /**
     * @return string
     */
    public function get_playlist_hash()
    {
        return hash('crc32', $this->get_current_playlist());
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
        hd_print(__METHOD__ . ": $tmp_file");
        if (file_exists($tmp_file)) {
            copy($tmp_file, $tmp_file . ".m3u");
            unlink($tmp_file);
        }
    }

    public function get_special_groups_count($plugin_cookies)
    {
        $groups_cnt = 0;
        if ($this->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::SETUP_ACTION_SHOW_ALL)) $groups_cnt++;
        if ($this->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::SETUP_ACTION_SHOW_FAVORITES)) $groups_cnt++;
        if ($this->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::SETUP_ACTION_SHOW_HISTORY)) $groups_cnt++;

        return $groups_cnt;
    }

    public function is_special_groups_enabled($plugin_cookies, $id)
    {
        return (!isset($plugin_cookies->{$id}) || $plugin_cookies->{$id} === SetupControlSwitchDefs::switch_on);
    }

    /**
     * @return void
     */
    public function update_xmltv_source()
    {
        $source = $this->get_settings(PARAM_EPG_SOURCE, PARAM_EPG_SOURCE_INTERNAL);

        if ($source === PARAM_EPG_SOURCE_INTERNAL) {
            $sources = $this->m3u_parser->getXmltvSources();
        } else {
            $sources = $this->get_settings(PARAM_CUSTOM_XMLTV_SOURCES, array());
        }

        $epg_idx = $this->get_settings(PARAM_EPG_IDX, array());
        $idx = isset($epg_idx[$source]) ? $epg_idx[$source] : 0;
        if (isset($sources[$idx])) {
            $this->epg_man->set_xmltv_url($sources[$idx]);
        } else {
            hd_print("no xmltv source defined for this playlist");
        }
    }

    /**
     * Generate url from template with macros substitution
     * Make url ts wrapped
     * @param int $archive_ts
     * @param Channel $channel
     * @return string
     * @throws Exception
     */
    public function generate_stream_url($archive_ts, Channel $channel)
    {
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

        $dune_params = $this->get_settings(PARAM_DUNE_PARAMS);
        if (!empty($dune_params)) {
            //hd_print("Additional dune params: $dune_params");
            $dune_params = trim($dune_params, '|');
            $stream_url .= "|||dune_params|||$dune_params";
        }

        return HD::make_ts($stream_url);
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
     * @param $defs
     */
    public function create_setup_header(&$defs)
    {
        Control_Factory::add_vgap($defs, -10);
        Control_Factory::add_label($defs, "ProIPTV by sharky72  [ ´¯¤¤¯(ºº)¯¤¤¯` ]",
            " v.{$this->plugin_info['app_version']} [{$this->plugin_info['app_release_date']}]",
            20);
    }

    /**
     * @param string $image
     * @return string
     */
    public function get_image_path($image = null)
    {
        return get_install_path("/img/" . ($image === null ?: ltrim($image, '/')));
    }
}
