<?php
require_once 'curl_wrapper.php';
require_once 'user_input_handler_registry.php';
require_once 'dune_default_sqlite_engine.php';
require_once 'abstract_controls_screen.php';

class Dune_Default_UI_Parameters extends Dune_Default_Sqlite_Engine
{
    const AUTHOR_LOGO = "ProIPTV by sharky72  [ ´¯¤¤¯(ºº)¯¤¤¯` ]";

    const RESOURCE_URL = 'http://iptv.esalecrm.net/res/';
    const CHANGELOG_URL_PREFIX = 'https://raw.githubusercontent.com/KocourKuba/proiptv/master/build/';

    const DEFAULT_MOV_ICON_PATH = 'plugin_file://icons/mov_unset.png';
    const VOD_ICON_PATH = 'gui_skin://small_icons/movie.aai';

    const SANDWICH_BASE = 'gui_skin://special_icons/sandwich_base.aai';
    const SANDWICH_MASK = 'cut_icon://{name=sandwich_mask}';
    const SANDWICH_COVER = 'cut_icon://{name=sandwich_cover}';

    const TV_SANDWICH_WIDTH = 246;
    const TV_SANDWICH_HEIGHT = 140;

    const TV_SANDWICH_WIDTH_SMALL = 160;
    const TV_SANDWICH_HEIGHT_SMALL = 160;

    const VOD_SANDWICH_WIDTH = 190;
    const VOD_SANDWICH_HEIGHT = 290;
    const VOD_CHANNEL_ICON_WIDTH = 190;
    const VOD_CHANNEL_ICON_HEIGHT = 290;

    const CONTROL_ZOOM = 'zoom_select';
    const CONTROL_EXTERNAL_PLAYER = 'use_external_player';

    const EPG_DIALOG_WIDTH = 1600;
    const EPG_PROGRESS_WIDTH = 750;

    /**
     * @var array
     */
    public $plugin_info;

    /**
     * @var Screen[]
     */
    private $screens;

    /**
     * @var array
     */
    private $screens_views;

    public function __construct()
    {
        HD::load_firmware_features();

        if (is_r22_or_higher()) {
            ini_set('memory_limit', '384M');
        }

        $this->plugin_info = get_plugin_manifest_info();
    }

    /**
     * @param object $object
     * @return void
     */
    public function create_screen($object)
    {
        if (!is_null($object) && method_exists($object, 'get_id')) {
            if (isset($this->screens[$object->get_id()])) {
                hd_debug_print("Error: screen (id: " . $object->get_id() . ") already registered.");
            } else {
                $this->screens[$object->get_id()] = $object;
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
        }
    }

    /**
     * @return array
     */
    public function get_screens()
    {
        return $this->screens;
    }

    /**
     * @param string $id
     * @return Screen
     */
    public function get_screen($id)
    {
        return safe_get_value($this->screens, $id);
    }

    /**
     * @param string $name
     * @return array
     */
    public function get_screen_view($name)
    {
        return safe_get_value($this->screens_views, $name, array());
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
        } else if (empty($background) || !file_exists(get_cached_image_path($background))) {
            $background = $this->plugin_info['app_background'];
        } else {
            $background = get_cached_image_path($background);
        }

        return $background;
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
     * @param User_Input_Handler $handler
     * @param string|array $param_action
     * @return array
     */
    public function show_protect_settings_dialog($handler, $param_action, $add_params = null)
    {
        $pass_settings = $this->get_parameter(PARAM_SETTINGS_PASSWORD);
        if (empty($pass_settings)) {
            if (is_array($param_action)) {
                return $param_action;
            }
            return User_Input_Handler_Registry::create_action($handler, $param_action, null, $add_params);
        }

        $new_params['param_action'] = $param_action;
        $new_params['add_params'] = $add_params;

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $handler, null, 'pass', TR::t('setup_pass'),
            '', true, true, false, true, 500, true);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, ACTION_PASSWORD_APPLY, TR::t('ok'), 300, $new_params);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('setup_enter_pass'), $defs, true);
    }

    public function apply_protect_settings_dialog($handler, $user_input)
    {
        if ($this->get_parameter(PARAM_SETTINGS_PASSWORD) !== $user_input->pass) {
            return null;
        }
        return User_Input_Handler_Registry::create_action($handler,
            $user_input->param_action,
            isset($user_input->add_params) ? $user_input->add_params : null);
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $initial_value
     * @return array
     */
    public function show_export_dialog($handler, $initial_value)
    {
        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $handler, null, CONTROL_EDIT_NAME, '',
            $initial_value, false, false, false, true, 800, true);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, ACTION_EXPORT_APPLY_DLG, TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('enter_name'), $defs, true);
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function get_plugin_info_dlg($handler)
    {
        static $history_txt;

        $lang = strtolower(TR::get_current_language());
        if (empty($history_txt)) {
            $doc = Curl_Wrapper::getInstance()->download_content(self::CHANGELOG_URL_PREFIX . "changelog.$lang.md");
            if ($doc === false) {
                hd_debug_print("Failed to get actual changelog.$lang.md, load local copy");
                $path = get_install_path("changelog.$lang.md");
                if (!file_exists($path)) {
                    $path = get_install_path("changelog.english.md");
                }
                $doc = file_get_contents($path);
            }

            $history_txt = str_replace(array("###", "\r"), '', $doc);
        }

        $defs = array();
        $qr_code = get_temp_path('tg.jpg');
        if (!file_exists($qr_code)) {
            $res = Curl_Wrapper::getInstance()->download_file(self::RESOURCE_URL . "TG.jpg", $qr_code);
            if ($res) {
                Control_Factory::add_smart_label($defs, "", "<gap width=1400/><icon dy=-10 width=100 height=100>$qr_code</icon>");
                Control_Factory::add_vgap($defs, 15);
            }
        }

        Control_Factory::add_multiline_label($defs, null, $history_txt, 14);
        Control_Factory::add_vgap($defs, 20);

        $text = sprintf("<gap width=%s/><icon>%s</icon><gap width=10/><icon>%s</icon><text color=%s size=small>  %s</text>",
            1130,
            get_image_path('page_plus_btn.png'),
            get_image_path('page_minus_btn.png'),
            DEF_LABEL_TEXT_COLOR_SILVER,
            TR::load('scroll_page')
        );
        Control_Factory::add_smart_label($defs, '', $text);
        Control_Factory::add_vgap($defs, -80);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, ACTION_DONATE_DLG, TR::t('setup_donate_title'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 250, true);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('setup_changelog'), $defs, true, 1800);
    }

    public function do_donate_dialog()
    {
        try {
            hd_debug_print(null, true);
            $img_ym = get_temp_path('qr_ym.png');
            $img_pp = get_temp_path('qr_pp.png');
            Curl_Wrapper::getInstance()->download_file(self::RESOURCE_URL . "QR_YM.png", $img_ym);
            Curl_Wrapper::getInstance()->download_file(self::RESOURCE_URL . "QR_PP.png", $img_pp);

            Control_Factory::add_vgap($defs, 50);
            Control_Factory::add_smart_label($defs, "", "<text>YooMoney</text><gap width=400/><text>PayPal</text>");
            Control_Factory::add_smart_label($defs, "", "<icon>$img_ym</icon><gap width=140/><icon>$img_pp</icon>");
            Control_Factory::add_vgap($defs, 450);

            $attrs['dialog_params'] = array('frame_style' => DIALOG_FRAME_STYLE_GLASS);
            return Action_Factory::show_dialog(TR::t('setup_donate_title'), $defs, true, 1150, $attrs);
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        return Action_Factory::status(0);
    }

    /**
     * @param User_Input_Handler $handler
     * @param array $prog_info
     * @param array|null $attrs
     * @return array|null
     */
    public function do_show_channel_epg($handler, $prog_info, $attrs = null)
    {
        hd_debug_print(null, true);

        if (!isset($prog_info[PluginTvEpgProgram::ext_id])) {
            hd_debug_print("Unknown channel ID", true);
            return null;
        }

        if (!isset($prog_info[PluginTvEpgProgram::name])) {
            $title = TR::t('epg_not_exist');
        } else {
            // program epg available
            $title = $prog_info[PluginTvEpgProgram::name];
            $diff = time() - $prog_info[PluginTvEpgProgram::start_tm_sec];

            // begin and end of program, elapsed time
            $elapsed_text = sprintf("<gap width=0/><text color=%s size=normal>%s %s - %s</text><gap width=50/><text color=%s size=normal>%s %s</text>",
                DEF_LABEL_TEXT_COLOR_GOLD,
                TR::load('time'),
                format_datetime('H:i', $prog_info[PluginTvEpgProgram::start_tm_sec]),
                format_datetime('H:i', $prog_info[PluginTvEpgProgram::end_tm_sec]),
                DEF_LABEL_TEXT_COLOR_TURQUOISE,
                TR::load('live'),
                format_duration_seconds($diff)
            );
            Control_Factory::add_smart_label($defs, null, $elapsed_text);

            // Progress bar placed after elapsed time on the same line
            Control_Factory::add_vgap($defs, -64);
            $pos_percent = round(100 * $diff / ($prog_info[PluginTvEpgProgram::end_tm_sec] - $prog_info[PluginTvEpgProgram::start_tm_sec]));
            Control_Factory_Ext::add_progress_bar_ext($defs,
                self::EPG_DIALOG_WIDTH - self::EPG_PROGRESS_WIDTH - 150,
                self::EPG_PROGRESS_WIDTH, $pos_percent);

            // Elapsed percent placed after elapsed time on the same line
            $percent_text = sprintf("<gap width=%s/><text color=%s size=normal>%s%%</text>",
                self::EPG_DIALOG_WIDTH - 100,
                DEF_LABEL_TEXT_COLOR_TURQUOISE,
                $pos_percent);
            Control_Factory::add_vgap($defs, -74);
            Control_Factory::add_smart_label($defs, null, $percent_text);

            // EPG description
            Control_Factory::add_multiline_label($defs, null, $prog_info[PluginTvEpgProgram::description], 12);
            Control_Factory::add_vgap($defs, 30);

            // help line if description more than dialog height
            $help_text = sprintf("<gap width=%s/><icon>%s</icon><gap width=10/><icon>%s</icon><text color=%s size=small>  %s</text>",
                self::EPG_DIALOG_WIDTH - 1050,
                get_image_path('page_plus_btn.png'),
                get_image_path('page_minus_btn.png'),
                DEF_LABEL_TEXT_COLOR_SILVER,
                TR::load('scroll_page')
            );
            Control_Factory::add_smart_label($defs, '', $help_text);
            Control_Factory::add_vgap($defs, -80);
        }

        self::add_epg_shift_defs($defs, $handler, $this->get_channel_epg_shift($prog_info[PluginTvEpgProgram::ext_id]), true);

        Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 250, true);

        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($title, $defs, true, self::EPG_DIALOG_WIDTH, $attrs);
    }

    public function do_edit_channel_parameters($handler, $channel_id)
    {
        hd_debug_print("Do Edit channel: $channel_id", true);

        $defs = array();

        if (!is_limited_apk()) {
            $pl_opts_idx = SwitchOnOff::to_def($this->get_channel_ext_player($channel_id));
            $pl_opts = array(SwitchOnOff::on => TR::t('tv_screen_external_player'), SwitchOnOff::off => TR::t('tv_screen_internal_player'));
            Control_Factory::add_combobox($defs, $handler, null, self::CONTROL_EXTERNAL_PLAYER,
                TR::t('setup_playback_settings'), $pl_opts_idx, $pl_opts, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        if ($this->get_bool_setting(PARAM_PER_CHANNELS_ZOOM)) {
            $zoom_opts_idx = $this->get_channel_zoom($channel_id);
            if (empty($zoom_opts_idx)) {
                $zoom_opts_idx = DuneVideoZoomPresets::not_set;
            }

            Control_Factory::add_combobox($defs, $handler, null, self::CONTROL_ZOOM,
                TR::t('tv_screen_channel_zoom'), $zoom_opts_idx, $this->get_zoom_opts_translated(), Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        self::add_epg_shift_defs($defs, $handler, $this->get_channel_epg_shift($channel_id), false);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, ACTION_EDIT_CHANNEL_APPLY, TR::t('ok'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);

        return Action_Factory::show_dialog(TR::t('tv_screen_edit_channel'), $defs, true);
    }

    public function do_edit_channel_apply($user_input, $channel_id)
    {
        if ($this->get_bool_setting(PARAM_PER_CHANNELS_ZOOM)) {
            $this->set_channel_zoom($channel_id, $user_input->{self::CONTROL_ZOOM});
        }
        if (!is_limited_apk()) {
            $this->set_channel_ext_player($channel_id, SwitchOnOff::to_bool($user_input->{self::CONTROL_EXTERNAL_PLAYER}));
        }
        $this->set_channel_epg_shift($channel_id, $user_input->{PARAM_EPG_SHIFT_HOURS}, $user_input->{PARAM_EPG_SHIFT_MINS});
    }
    ///////////////////////////////////////////////////////////////////////

    protected static function add_epg_shift_defs(&$defs, $handler, $initial_epg_shift, $apply)
    {
        $shift_ops_hours = array();
        for ($i = -24; $i < 25; $i++) {
            $shift_ops_hours[$i] = TR::t('setup_epg_shift_hours__1', sprintf("%+03d", $i));
        }
        $shift_ops_hours[0] = TR::t('setup_epg_shift_hours__1', sprintf(" %02d", 0));

        Control_Factory::add_combobox($defs, $handler, null, PARAM_EPG_SHIFT_HOURS,
            TR::t('setup_epg_shift_hours'), (int)($initial_epg_shift / 3600), $shift_ops_hours, 250, false, $apply);

        $shift_ops_mins = array();
        for ($i = 0; $i < 60; $i += 5) {
            $shift_ops_mins[$i] = TR::t('setup_epg_shift_mins__1', sprintf("%02d", $i));
        }

        Control_Factory::add_combobox($defs, $handler, null, PARAM_EPG_SHIFT_MINS,
            TR::t('setup_epg_shift_min'), (int)(abs($initial_epg_shift % 3600) / 60), $shift_ops_mins, 250, false, $apply);
    }

    protected function get_zoom_opts_translated()
    {
        static $zoom_ops_translated;

        if (empty($zoom_ops_translated)) {
            $zoom_ops_translated = array(
                DuneVideoZoomPresets::not_set => TR::t('tv_screen_zoom_not_set'),
                DuneVideoZoomPresets::normal => TR::t('tv_screen_zoom_normal'),
                DuneVideoZoomPresets::enlarge => TR::t('tv_screen_zoom_enlarge'),
                DuneVideoZoomPresets::make_wider => TR::t('tv_screen_zoom_make_wider'),
                DuneVideoZoomPresets::fill_screen => TR::t('tv_screen_zoom_fill_screen'),
                DuneVideoZoomPresets::full_fill_screen => TR::t('tv_screen_zoom_full_fill_screen'),
                DuneVideoZoomPresets::make_taller => TR::t('tv_screen_zoom_make_taller'),
                DuneVideoZoomPresets::cut_edges => TR::t('tv_screen_zoom_cut_edges'),
                DuneVideoZoomPresets::full_enlarge => TR::t('tv_screen_zoom_full_enlarge'),
                DuneVideoZoomPresets::full_stretch => TR::t('tv_screen_zoom_full_stretch'),
            );
        }

        return $zoom_ops_translated;
    }

    /**
     * @param MediaURL $media_url
     * @return Screen
     * @throws Exception
     */
    protected function get_screen_by_url(MediaURL $media_url)
    {
        $screen_id = safe_get_member($media_url, PARAM_SCREEN_ID, $media_url->get_raw_string());

        return $this->get_screen_by_id($screen_id);
    }

    /**
     * @param string $screen_id
     * @return Screen
     * @throws Exception
     */
    protected function get_screen_by_id($screen_id)
    {
        if (isset($this->screens[$screen_id])) {
            hd_debug_print("'$screen_id'", true);
            return $this->screens[$screen_id];
        }

        hd_debug_print("Error: no screen with id '$screen_id' found.");
        print_backtrace();
        throw new Exception('Screen not found');
    }

    /**
     * @param string $background
     * @return void
     */
    public function init_screen_view_parameters($background) {
        hd_debug_print(null, true);
        hd_debug_print("Selected background: $background", true);

        $not_loaded_vod = array(
            ViewItemParams::item_paint_icon => true,
            ViewItemParams::icon_path => self::DEFAULT_MOV_ICON_PATH,
            ViewItemParams::item_detailed_icon_path => 'missing://',
        );

        $this->screens_views = array(
            // 1x10 title list view with right side icon
            'list_1x11_small_info' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array(
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 11,
                    ViewParams::paint_icon_selection_box => true,
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

                PluginRegularFolderView::base_view_item_params => array(
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

            'list_1x11_info' => array(
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::view_params => array(
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 11,
                    ViewParams::paint_icon_selection_box => true,
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
                    ViewParams::zoom_detailed_icon => false,
                ),
                PluginRegularFolderView::base_view_item_params => array(
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
                PluginRegularFolderView::view_params => array(
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
                PluginRegularFolderView::base_view_item_params => array(
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

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
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

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
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

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
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

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
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

            'icons_5x4_no_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
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

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
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

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
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

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
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

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
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

            'icons_7x4_no_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array(
                    ViewParams::num_cols => 7,
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
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH_SMALL,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT_SMALL,
                ),

                PluginRegularFolderView::base_view_item_params => array(
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

            'icons_7x4_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array(
                    ViewParams::num_cols => 7,
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
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH_SMALL,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT_SMALL,
                ),

                PluginRegularFolderView::base_view_item_params => array(
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_SMALL,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 0.7,
                    ViewItemParams::icon_sel_scale_factor => 0.8,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_5x2_movie_no_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_path => self::VOD_ICON_PATH,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => self::VOD_CHANNEL_ICON_WIDTH,
                    ViewItemParams::icon_height => self::VOD_CHANNEL_ICON_HEIGHT,
                    ViewItemParams::icon_sel_margin_top => 0,
                    ViewItemParams::item_paint_caption => false,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => $not_loaded_vod
            ),

            'icons_5x2_movie_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array(
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

                PluginRegularFolderView::base_view_item_params => array(
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_path => self::VOD_ICON_PATH,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => self::VOD_CHANNEL_ICON_WIDTH,
                    ViewItemParams::icon_height => self::VOD_CHANNEL_ICON_HEIGHT,
                    ViewItemParams::icon_sel_margin_top => 0,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_SMALL,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::item_caption_sel_dy => 25,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => $not_loaded_vod
            ),

            'icons_5x3_movie_no_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::view_params => array(
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 3,
                    ViewParams::paint_details => true,
                    ViewParams::paint_path_box => false,
                    ViewParams::zoom_detailed_icon => true,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::paint_item_info_in_details => true,

                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,

                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                ),

                PluginRegularFolderView::base_view_item_params => array(
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_sel_scale_factor => 1.1,
                    ViewItemParams::icon_path => self::VOD_ICON_PATH,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 167,
                    ViewItemParams::icon_height => 250,
                    ViewItemParams::icon_sel_margin_top => 0,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::item_caption_width => 1100
                ),

                PluginRegularFolderView::not_loaded_view_item_params => $not_loaded_vod
            ),

            'list_1x10_vod_info_normal' => array(
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::view_params => array(
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 10,
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

                    ViewParams::paint_sandwich => false,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::VOD_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::VOD_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array(
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

                PluginRegularFolderView::not_loaded_view_item_params => $not_loaded_vod
            ),

            'list_1x12_vod_info_small' => array(
                PluginRegularFolderView::async_icon_loading => false,
                PluginRegularFolderView::view_params => array(
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
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

                    ViewParams::paint_sandwich => false,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::VOD_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::VOD_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::base_view_item_params => array(
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => self::VOD_ICON_PATH,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 55,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_width => 1100
                ),
                PluginRegularFolderView::not_loaded_view_item_params => $not_loaded_vod
            ),
        );
    }
}
