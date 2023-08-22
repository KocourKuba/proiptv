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
    // views constants
    const ALL_CHANNEL_GROUP_ID = '##all_channels##';
    const ALL_CHANNEL_GROUP_CAPTION = '%tr%plugin_all_channels';
    const FAV_CHANNEL_GROUP_ID = '##favorites##';
    const FAV_CHANNEL_GROUP_CAPTION = '%tr%plugin_favorites';
    const PLAYBACK_HISTORY_GROUP_ID = '##playback_history_tv_group##';
    const PLAYBACK_HISTORY_CAPTION = '%tr%plugin_history';

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
     * @var Starnet_Tv_Favorites_Screen
     */
    public $tv_favorites_screen;

    /**
     * @var Starnet_TV_History_Screen
     */
    public $tv_history_screen;

    /**
     * @var array|Screen[]
     */
    private $screens;

    ///////////////////////////////////////////////////////////////////////

    protected function __construct()
    {
        $this->plugin_info = get_plugin_manifest_info();
        $this->screens = array();
        $this->new_ui_support = HD::rows_api_support();
        $this->m3u_parser = new M3uParser();
        $this->epg_man = new Epg_Manager();
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
     * @throws Exception
     */
    public function change_tv_favorites($op_type, $channel_id, &$plugin_cookies)
    {
        if (is_null($this->tv)) {
            hd_print(__METHOD__ . ': TV is not supported');
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $this->tv->change_tv_favorites($op_type, $channel_id, $plugin_cookies);
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
        $hash = $this->GetPlaylistHash();
        if (!isset($this->settings[$hash])) {
            $this->settings[$hash] = HD::get_items(get_data_path($hash), true, false);
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
        $hash = $this->GetPlaylistHash();
        $this->settings[$hash][$type] = $val;
        HD::put_data_items($hash, $this->settings[$hash], false);
    }

    /**
     * Remove settings for selected playlist
     */
    public function remove_settings()
    {
        $hash = $this->GetPlaylistHash();
        unset($this->settings[$hash]);
        HD::erase_data_items($hash);
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
            $this->parameters = HD::get_data_items(PLUGIN_PARAMS);
            hd_print(__METHOD__ . " : Loaded common parameters");
        }

        return isset($this->parameters[$type]) ? $this->parameters[$type] : $default;
    }

    /**
     * set plugin parameters
     *
     * @param string $type
     * @param mixed $val
     */
    public function set_parameters($type, $val)
    {
        $this->parameters[$type] = $val;
        HD::put_data_items(PLUGIN_PARAMS, $this->parameters);
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
    public function InitPlaylist()
    {
        // first check if playlist in cache
        $force = false;
        $tmp_file = $this->GetPlaylistCache();
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
                $url = $this->GetPlaylist();
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
                    $this->ClearPlaylistCache();
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
     * @return string
     */
    public function GetPlaylistCache()
    {
        return get_temp_path($this->GetPlaylistHash() . "_playlist.m3u8");
    }

    /**
     * Clear downloaded playlist
     * @return void
     */
    public function ClearPlaylistCache()
    {
        $tmp_file = $this->GetPlaylistCache();
        hd_print(__METHOD__ . ": $tmp_file");
        if (file_exists($tmp_file)) {
            copy($tmp_file, $tmp_file . ".m3u");
            unlink($tmp_file);
        }
    }

    /**
     * @return string
     */
    public function GetPlaylist()
    {
        $playlists = $this->get_parameters(PARAM_PLAYLISTS, array());
        $idx = $this->get_parameters(PARAM_PLAYLIST_IDX, 0);
        return isset($playlists[$idx]) ? $playlists[$idx] : '';
    }

    /**
     * @return string
     */
    public function GetPlaylistHash()
    {
        return hash('crc32', $this->GetPlaylist());
    }

    /**
     * @return void
     */
    public function UpdateXmltvSource()
    {
        $source = $this->get_settings(PARAM_EPG_SOURCE, PARAM_EPG_INTERNAL);

        if ($source === PARAM_EPG_INTERNAL) {
            $sources = $this->m3u_parser->getXmltvSources();
            $used_idx = PARAM_EPG_INTERNAL_IDX;
        } else {
            $sources = $this->get_settings(PARAM_CUSTOM_XMLTV_SOURCES);
            $used_idx = PARAM_EPG_EXTERNAL_IDX;
        }

        $epg_idx = $this->get_settings($used_idx, 0);
        if (isset($sources[$epg_idx])) {
            $this->epg_man->set_xmltv_url($sources[$epg_idx]);
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
    public function GenerateStreamUrl($archive_ts, Channel $channel)
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
            $replaces[catchup_params::CU_UTCEND] = $now;
            $replaces[catchup_params::CU_OFFSET] = $now - $archive_ts;
            $replaces[catchup_params::CU_DURATION] = 14400;
            $replaces[catchup_params::CU_YEAR] = date('Y', $archive_ts);
            $replaces[catchup_params::CU_MONTH] = date('m', $archive_ts);
            $replaces[catchup_params::CU_DAY] = date('d', $archive_ts);
            $replaces[catchup_params::CU_HOUR] = date('H', $archive_ts);
            $replaces[catchup_params::CU_MINUTE] = date('M', $archive_ts);

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
        Control_Factory::add_label($defs, "ProIPTV by sharky72 [---------------------]",
            " v.{$this->plugin_info['app_version']} [{$this->plugin_info['app_release_date']}]",
            20);
    }

    /**
     * @param string $image
     * @return string
     */
    public function get_image_path($image = null)
    {
        return get_install_path("/img/" . ($image === null ?: $image));
    }
}
