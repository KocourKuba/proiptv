<?php
///////////////////////////////////////////////////////////////////////////

require_once 'tr.php';
require_once 'tv/tv.php';
require_once 'mediaurl.php';
require_once 'user_input_handler_registry.php';
require_once 'action_factory.php';
require_once 'control_factory.php';
require_once 'control_factory_ext.php';
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
    const ALL_CHANNEL_GROUP_ICON_PATH = 'plugin_file://icons/all.png';

    const FAV_CHANNEL_GROUP_ID = '##favorites##';
    const FAV_CHANNEL_GROUP_CAPTION = '%tr%plugin_favorites';
    const FAV_CHANNEL_GROUP_ICON_PATH = 'plugin_file://icons/fav.png';

    const PLAYBACK_HISTORY_GROUP_ID = '##playback_history_tv_group##';
    const PLAYBACK_HISTORY_CAPTION = '%tr%plugin_history';
    const PLAYBACK_HISTORY_GROUP_ICON_PATH = 'plugin_file://icons/history.png';

    /////////////////////////////////////////////////////////////////////////////
    // views variables
    const TV_SANDWICH_WIDTH = 245;
    const TV_SANDWICH_HEIGHT = 140;

    const VOD_SANDWICH_WIDTH = 190;
    const VOD_SANDWICH_HEIGHT = 290;
    const DEFAULT_CHANNEL_ICON_PATH = 'plugin_file://icons/channel_unset.png';
    const DEFAULT_MOV_ICON_PATH = 'plugin_file://icons/mov_unset.png';
    const VOD_ICON_PATH = 'gui_skin://small_icons/movie.aai';

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

    /**
     * @param $defs
     */
    public function create_setup_header(&$defs)
    {
        Control_Factory::add_vgap($defs, -10);
        Control_Factory::add_label($defs, "ПроIPTV by sharky72            ",
            " v.{$this->plugin_info['app_version']} [{$this->plugin_info['app_release_date']}]",
            20);
    }

    /**
     * @param $object
     */
    public function create_screen(&$object)
    {
        if (!is_null($object) && method_exists($object, 'get_id')) {
            //hd_print(__METHOD__ . ': ' . get_class($object));
            $this->add_screen($object);
            User_Input_Handler_Registry::get_instance()->register_handler($object);
        } else {
            hd_print(__METHOD__ . ": " . get_class($object) . ": Screen class is illegal. get_id method not defined!");
        }
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Screen support.
    //
    ///////////////////////////////////////////////////////////////////////

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
    // Folder support.
    //
    ///////////////////////////////////////////////////////////////////////

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
    //
    // IPTV channels support (TV support).
    //
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
    //
    // VOD support.
    //
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
    // Methods
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * Get settings for selected playlist
     *
     * @param Object $plugin_cookies
     * @param string $type
     * @param mixed|null $default
     * @return mixed
     */
    public function get_settings($plugin_cookies, $type, $default = null)
    {
        $hash = $this->GetPlaylistHash($plugin_cookies);
        if (!isset($this->settings[$hash])) {
            $this->settings[$hash] = HD::get_items(get_data_path("settings_$hash"));
        }

        return isset($this->settings[$hash][$type]) ? $this->settings[$hash][$type] : $default;
    }

    /**
     * Set settings for selected playlist
     *
     * @param Object $plugin_cookies
     * @param string $type
     * @param mixed $val
     */
    public function put_settings($plugin_cookies, $type, $val)
    {
        $hash = $this->GetPlaylistHash($plugin_cookies);
        $this->settings[$hash][$type] = $val;
        HD::put_items(get_data_path("settings_$hash"), $this->settings[$hash]);
    }

    /**
     * Initialize and parse selected playlist
     *
     * @param Object $plugin_cookies
     * @return bool
     */
    public function InitPlaylist($plugin_cookies)
    {
        // first check if playlist in cache
        $force = false;
        $tmp_file = $this->GetPlaylistCache($plugin_cookies);
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
                $url = $this->GetPlaylist($plugin_cookies);
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
            if ($this->m3u_parser->getEntriesCount() === 0) {
                $this->m3u_parser->setupParser($tmp_file);
                if (!$this->m3u_parser->parseInMemory()) {
                    $this->set_last_error("Ошибка чтения плейлиста!");
                    throw new Exception("Can't read playlist");
                }

                $count = $this->m3u_parser->getEntriesCount();
                if ($count === 0) {
                    $this->set_last_error("Пустой плейлист!");
                    hd_print(__METHOD__ . ": $this->last_error");
                    $this->ClearPlaylistCache($plugin_cookies);
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
     * Clear downloaded playlist
     * @param $plugin_cookies
     * @return void
     */
    public function ClearPlaylistCache($plugin_cookies)
    {
        $tmp_file = $this->GetPlaylistCache($plugin_cookies);
        hd_print(__METHOD__ . ": $tmp_file");
        if (file_exists($tmp_file)) {
            copy($tmp_file, $tmp_file . ".m3u");
            unlink($tmp_file);
        }
    }

    /**
     * @param $plugin_cookies
     * @return string
     */
    public function GetPlaylist($plugin_cookies)
    {
        $idx = isset($plugin_cookies->playlist_idx) ? $plugin_cookies->playlist_idx : 0;
        return isset($plugin_cookies->playlists[$idx]) ? $plugin_cookies->playlists[$idx] : '';
    }

    /**
     * @param $plugin_cookies
     * @return string
     */
    public function GetPlaylistHash($plugin_cookies)
    {
        return hash('crc32', $this->GetPlaylist($plugin_cookies));
    }

    /**
     * @param $plugin_cookies
     * @return string
     */
    public function GetPlaylistCache($plugin_cookies)
    {
        return get_temp_path($this->GetPlaylistHash($plugin_cookies) . "_playlist.m3u8");
    }

    /**
     * @param Channel $a
     * @param Channel $b
     * @return int
     */
    public static function sort_channels_cb($a, $b)
    {
        // Sort by channel numbers.
        return strnatcasecmp($a->get_number(), $b->get_number());
    }

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
     * Generate url from template with macros substitution
     * Make url ts wrapped
     * @param $plugin_cookies
     * @param int $archive_ts
     * @param Channel $channel
     * @return string
     * @throws Exception
     */
    public function GenerateStreamUrl($plugin_cookies, $archive_ts, Channel $channel)
    {
        $now = time();
        $is_archive = (int)$archive_ts > 0;
        $stream_type = $this->get_format($plugin_cookies);
        $ext_params = $channel->get_ext_params();
        $channel_id = $channel->get_channel_id();
        $ext_params[Plugin_Constants::CHANNEL_ID] = $channel_id;
        $ext_params[Stream_Params::CU_START] = $archive_ts;
        $ext_params[Stream_Params::CU_NOW] = $now;
        $ext_params[Stream_Params::CU_OFFSET] = $now - $archive_ts;
        $ext_params[Stream_Params::CU_STOP] = $archive_ts + $this->get_stream_param($stream_type, Stream_Params::CU_DURATION);
        $ext_params[Stream_Params::CU_DURATION] = $this->get_stream_param($stream_type, Stream_Params::CU_DURATION);
        $ext_params[Ext_Params::M_DEVICE_ID] = $this->get_device_id($plugin_cookies);
        $ext_params[Ext_Params::M_SERVER_ID] = $this->get_server_id($plugin_cookies);
        $ext_params[Ext_Params::M_PROFILE_ID] = $this->get_profile_id($plugin_cookies);
        $ext_params[Ext_Params::M_QUALITY_ID] = $this->get_quality_id($plugin_cookies);
        $ext_params[Ext_Params::M_PASSWORD] = $this->get_password($plugin_cookies);

        $replaces = array(
            Stream_Params::CU_START      => Plugin_Macros::START,
            Stream_Params::CU_NOW        => Plugin_Macros::NOW,
            Stream_Params::CU_DURATION   => Plugin_Macros::DURATION,
            Stream_Params::CU_STOP       => Plugin_Macros::STOP,
            Stream_Params::CU_OFFSET     => Plugin_Macros::OFFSET,
            Ext_Params::M_SUBDOMAIN      => Plugin_Macros::SUBDOMAIN,
            Ext_Params::M_DOMAIN         => Plugin_Macros::DOMAIN,
            Ext_Params::M_PORT           => Plugin_Macros::PORT,
            Ext_Params::M_LOGIN          => Plugin_Macros::LOGIN,
            Ext_Params::M_PASSWORD       => Plugin_Macros::PASSWORD,
            Ext_Params::M_TOKEN          => Plugin_Macros::TOKEN,
            Ext_Params::M_INT_ID         => Plugin_Macros::INT_ID,
            Ext_Params::M_HOST           => Plugin_Macros::HOST,
            Ext_Params::M_QUALITY_ID     => Plugin_Macros::QUALITY_ID,
            Ext_Params::M_DEVICE_ID      => Plugin_Macros::DEVICE_ID,
            Ext_Params::M_SERVER_ID      => Plugin_Macros::SERVER_ID,
            Ext_Params::M_PROFILE_ID     => Plugin_Macros::PROFILE_ID,
            Ext_Params::M_VAR1           => Plugin_Macros::VAR1,
            Ext_Params::M_VAR2           => Plugin_Macros::VAR2,
            Ext_Params::M_VAR3           => Plugin_Macros::VAR3,
        );

        $channel_custom_url = $channel->get_custom_url();
        $channel_custom_arc_url = $channel->get_custom_archive_template();
        if (empty($channel_custom_url)) {
            // url template, live or archive
            $live_url = $this->get_stream_param($stream_type, Stream_Params::URL_TEMPLATE);

            if (empty($channel_custom_arc_url)) {
                // global url archive template
                $archive_url = $this->get_stream_param($stream_type, Stream_Params::URL_ARC_TEMPLATE);
            } else {
                // custom archive url template
                $archive_url = $channel_custom_arc_url;
            }
        } else {
            // custom url
            $live_url = $channel_custom_url;

            if (empty($channel_custom_arc_url)) {
                // global custom url archive template
                $archive_url = $this->get_stream_param($stream_type, Stream_Params::URL_CUSTOM_ARC_TEMPLATE);
            } else {
                // custom url archive or template
                $archive_url = $channel_custom_arc_url;
            }
        }

        if ($is_archive) {
            // replace macros to live url
            $play_template_url = str_replace(Plugin_Macros::LIVE_URL, $live_url, $archive_url);
            $custom_stream_type = $channel->get_custom_archive_url_type();
        } else {
            $play_template_url = $live_url;
            $custom_stream_type = $channel->get_custom_url_type();
        }

        //hd_print("play template: $play_template_url");
        //foreach($ext_params as $key => $value) { hd_print("ext_params: key: $key, value: $value"); }

        // replace all macros
        foreach ($replaces as $key => $value) {
            if (isset($ext_params[$key])) {
                $play_template_url = str_replace($value, $ext_params[$key], $play_template_url);
            }
        }

        foreach ($replaces as $value) {
            if (strpos($play_template_url, $value) !== false) {
                throw new Exception("Template $value not replaced. Url not generated.");
            }
        }

        $url = $this->UpdateDuneParams($play_template_url, $plugin_cookies, $custom_stream_type);

        return HD::make_ts($url);
    }

    /**
     * @param string $url
     * @param $plugin_cookies
     * @param int $custom_type
     * @return string
     */
    public function UpdateDuneParams($url, $plugin_cookies, $custom_type = '')
    {
        $dune_params = $this->get_settings(PARAM_DUNE_PARAMS);
        if (!empty($dune_params)) {
            //hd_print("Additional dune params: $dune_params");
            $dune_params = trim($dune_params, '|');
            $url .= "|||dune_params|||$dune_params";
        }

        return $url;
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Misc.
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
     * @param string $image
     * @return string
     */
    public function get_image_path($image = null)
    {
        return get_install_path("/img/" . ($image === null ?: $image));
    }

    ///////////////////////////////////////////////////////////////////////
    // Folder views.

    /**
     * @return array[]
     */
    public function GET_TV_GROUP_LIST_FOLDER_VIEWS()
    {
        return array(

            // 1x10 list view with info
            array
            (
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 10,
                    ViewParams::paint_details => true,
                    ViewParams::paint_item_info_in_details => false,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,

                    ViewParams::paint_sandwich => false,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => static::VOD_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => static::VOD_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => false,
                    ViewItemParams::icon_path => self::VOD_ICON_PATH,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
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

            // 3x10 list view
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 10,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => false,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_caption_width => 485,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_SMALL,
                    ViewItemParams::item_caption_dx => 50,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // small no caption
            array
            (
                PluginRegularFolderView::async_icon_loading => false,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 4,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::content_box_padding_left => 70,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),
        );
    }

    /**
     * @return array[]
     */
    public function GET_TV_CHANNEL_LIST_FOLDER_VIEWS()
    {
        return array(
            // 4x3 with title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.25,
                    ViewItemParams::icon_sel_scale_factor => 1.5,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 3x3 without title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.25,
                    ViewItemParams::icon_sel_scale_factor => 1.5,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 4x4 without title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 4,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 5x4 without title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 4,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 2x10 title list view with right side icon
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 2,
                    ViewParams::num_rows => 10,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => $this->get_settings(PARAM_SQUARE_ICONS) ? 60 : 84,
                    ViewItemParams::icon_height => $this->get_settings(PARAM_SQUARE_ICONS) ? 60 : 48,
                    ViewItemParams::item_caption_width => 485,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_SMALL,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 1x10 title list view with right side icon
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 10,
                    ViewParams::paint_icon_selection_box=> true,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,
                    ViewParams::background_path=> $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::item_detailed_info_text_color => 11,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::zoom_detailed_icon => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::icon_dx => 26,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_width => 1060,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),
        );
    }

    /**
     * @return array[]
     */
    public function GET_HISTORY_LIST_FOLDER_VIEWS()
    {
        return array(
            // 1x10 title list view with right side icon
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 10,
                    ViewParams::paint_icon_selection_box=> true,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,
                    ViewParams::background_path=> $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::item_detailed_info_text_color => 11,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::zoom_detailed_icon => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::icon_dx => 26,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_width => 1060,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),
        );
    }

    /**
     * @return array[]
     */
    public function GET_TEXT_ONE_COL_VIEWS()
    {
        return array(
            array(
                PluginRegularFolderView::async_icon_loading => false,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_details => true,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                ),
                PluginRegularFolderView::base_view_item_params =>
                    array
                    (
                        ViewItemParams::icon_path => 'missing://',
                        ViewItemParams::item_layout => HALIGN_LEFT,
                        ViewItemParams::icon_valign => VALIGN_CENTER,
                        ViewItemParams::icon_dx => 20,
                        ViewItemParams::icon_dy => -5,
                        ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                        ViewItemParams::item_caption_width => 1550
                    ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),
        );
    }

    /**
     * @return array
     */
    public function GET_FOLDER_VIEWS()
    {
        if (defined('ViewParams::details_box_width')) {
            $view[] = array(
                PluginRegularFolderView::async_icon_loading => false,
                PluginRegularFolderView::view_params => array(
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_content_box_background => false,
                    ViewParams::paint_icon_selection_box => true,
                    ViewParams::paint_details_box_background => false,
                    ViewParams::icon_selection_box_width => 770,
                    ViewParams::paint_path_box_background => false,
                    ViewParams::paint_widget_background => false,
                    ViewParams::paint_details => true,
                    ViewParams::paint_item_info_in_details => true,
                    ViewParams::details_box_width => 900,
                    ViewParams::paint_scrollbar => false,
                    ViewParams::content_box_padding_right => 500,
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::background_path => $this->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                ),
                PluginRegularFolderView::base_view_item_params => array(
                    ViewItemParams::item_layout => 0,
                    ViewItemParams::icon_width => 30,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::item_caption_dx => 55,
                    ViewItemParams::icon_dx => 5,
                    ViewItemParams::icon_sel_scale_factor => 1.01,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                    ViewItemParams::icon_sel_dx => 6,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_valign => 1,
                ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
            );
        }

        $view[] = array(
            PluginRegularFolderView::view_params => array(
                ViewParams::num_cols => 1,
                ViewParams::num_rows => 10,
                ViewParams::paint_details => true,
                ViewParams::paint_item_info_in_details => true,
                ViewParams::detailed_icon_scale_factor => 0.5,
                ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                ViewParams::item_detailed_info_auto_line_break => true
            ),
            PluginRegularFolderView::base_view_item_params => array(
                ViewItemParams::item_paint_icon => true,
                ViewItemParams::icon_sel_scale_factor => 1.2,
                ViewItemParams::item_layout => HALIGN_LEFT,
                ViewItemParams::icon_valign => VALIGN_CENTER,
                ViewItemParams::icon_dx => 10,
                ViewItemParams::icon_dy => -5,
                ViewItemParams::icon_width => 50,
                ViewItemParams::icon_height => 50,
                ViewItemParams::icon_sel_margin_top => 0,
                ViewItemParams::item_paint_caption => true,
                ViewItemParams::item_caption_width => 1100,
                ViewItemParams::item_detailed_icon_path => 'missing://'
            ),
            PluginRegularFolderView::not_loaded_view_item_params => array(),
            PluginRegularFolderView::async_icon_loading => false,
            PluginRegularFolderView::timer => Action_Factory::timer(5000),
        );
        return $view;
    }
}
