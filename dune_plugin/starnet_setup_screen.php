<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'setup';

    const SETUP_ACTION_ADULT_PASS_DLG = 'adult_pass_dialog';
    const SETUP_ACTION_ADULT_PASS_APPLY = 'adult_pass_apply';
    const SETUP_ACTION_INTERFACE_SCREEN = 'interface_screen';
    const SETUP_ACTION_CHANNELS_SCREEN = 'channels_screen';
    const SETUP_ACTION_EPG_SCREEN = 'epg_screen';
    const SETUP_ACTION_STREAMING_SCREEN = 'streaming_screen';
    const SETUP_ACTION_HISTORY_SCREEN = 'history_screen';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return false|string
     */
    public static function get_media_url_str()
    {
        return MediaURL::encode(array('screen_id' => self::ID));
    }

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct(self::ID, $plugin);

        $plugin->create_screen($this);
    }

    /**
     * @return string
     */
    public function get_handler_id()
    {
        return self::ID . '_handler';
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * defs for all controls on screen
     * @param $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        hd_debug_print();
        $text_icon = $this->plugin->get_image_path('text.png');
        $setting_icon = $this->plugin->get_image_path('settings.png');

        $defs = array();
        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // Interface settings
        Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_INTERFACE_SCREEN,
            TR::t('setup_interface_title'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Channels settings
        Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_CHANNELS_SCREEN,
            TR::t('tv_screen_channels_setup'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // EPG settings
        Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_EPG_SCREEN,
            TR::t('setup_epg_settings'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Streaming settings
        Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_STREAMING_SCREEN,
            TR::t('setup_streaming_settings'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // History view info location
        if (!is_apk()) {
            Control_Factory::add_image_button($defs, $this, null,
                self::SETUP_ACTION_HISTORY_SCREEN,
                TR::t('setup_history_folder_path'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // adult channel password
        Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_ADULT_PASS_DLG,
            TR::t('setup_adult_title'), TR::t('setup_adult_change'), $text_icon, self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs($plugin_cookies);
    }

    /**
     * adult pass dialog defs
     * @return array
     */
    public function do_get_pass_control_defs()
    {
        $defs = array();

        $pass1 = '';
        $pass2 = '';

        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $this, null, 'pass1', TR::t('setup_old_pass'),
            $pass1, 1, true, 0, 1, 500, 0);
        Control_Factory::add_text_field($defs, $this, null, 'pass2', TR::t('setup_new_pass'),
            $pass2, 1, true, 0, 1, 500, 0);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this, null, self::SETUP_ACTION_ADULT_PASS_APPLY, TR::t('ok'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }

    /**
     * user remote input handler Implementation of UserInputHandler
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);

        $control_id = $user_input->control_id;
        switch ($control_id) {
            case self::SETUP_ACTION_INTERFACE_SCREEN: // show interface settings dialog
                return Action_Factory::open_folder(Starnet_Interface_Setup_Screen::get_media_url_str(), TR::t('setup_interface_title'));

            case self::SETUP_ACTION_CHANNELS_SCREEN: // show epg settings dialog
                return Action_Factory::open_folder(Starnet_Playlists_Setup_Screen::get_media_url_str(), TR::t('tv_screen_channels_setup'));

            case self::SETUP_ACTION_EPG_SCREEN: // show epg settings dialog
                return Action_Factory::open_folder(Starnet_Epg_Setup_Screen::get_media_url_str(), TR::t('setup_epg_settings'));

            case self::SETUP_ACTION_STREAMING_SCREEN: // show streaming settings dialog
                return Action_Factory::open_folder(Starnet_Streaming_Setup_Screen::get_media_url_str(), TR::t('setup_streaming_settings'));

            case self::SETUP_ACTION_HISTORY_SCREEN:
                return Action_Factory::open_folder(Starnet_History_Setup_Screen::get_media_url_str(), TR::t('setup_history_change_folder'));

            case self::SETUP_ACTION_ADULT_PASS_DLG: // show pass dialog
                $defs = $this->do_get_pass_control_defs();
                return Action_Factory::show_dialog(TR::t('setup_adult_password'), $defs, true);

            case self::SETUP_ACTION_ADULT_PASS_APPLY: // handle pass dialog result
                $need_reload = false;
                if ($user_input->pass1 !== $plugin_cookies->pass_sex) {
                    $msg = TR::t('err_wrong_old_password');
                } else if (empty($user_input->pass2)) {
                    $plugin_cookies->pass_sex = '';
                    $msg = TR::t('setup_pass_disabled');
                    $need_reload = true;
                } else if ($user_input->pass1 !== $user_input->pass2) {
                    $plugin_cookies->pass_sex = $user_input->pass2;
                    $msg = TR::t('setup_pass_changed');
                    $need_reload = true;
                } else {
                    $msg = TR::t('setup_pass_not_changed');
                }

                return Action_Factory::show_title_dialog($msg, $need_reload ? $this->plugin->tv->reload_channels($this, $plugin_cookies) : null);
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
