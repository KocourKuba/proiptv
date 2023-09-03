<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Interface_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'interface_setup';

    const SETUP_ACTION_SHOW_TV = 'show_tv';
    const SETUP_ACTION_SHOW_ALL = 'show_all';
    const SETUP_ACTION_SHOW_FAVORITES = 'show_favorites';
    const SETUP_ACTION_SHOW_HISTORY = 'show_history';
    const SETUP_ACTION_ASK_EXIT = 'ask_exit';
    const SETUP_ACTION_EPG_FONT_SIZE = 'epg_font_size';

    ///////////////////////////////////////////////////////////////////////

    /**
     * interface dialog defs
     * @param $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        //hd_debug_print();
        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // Show in main screen
        if (!is_apk()) {
            if (!isset($plugin_cookies->{self::SETUP_ACTION_SHOW_TV})) {
                $plugin_cookies->{self::SETUP_ACTION_SHOW_TV} = SetupControlSwitchDefs::switch_on;
            }

            $show_tv = $plugin_cookies->{self::SETUP_ACTION_SHOW_TV};
            Control_Factory::add_image_button($defs, $this, null,
                self::SETUP_ACTION_SHOW_TV, TR::t('setup_show_in_main'), SetupControlSwitchDefs::$on_off_translated[$show_tv],
                get_image_path(SetupControlSwitchDefs::$on_off_img[$show_tv]), self::CONTROLS_WIDTH);
        }

        if (!isset($plugin_cookies->{self::SETUP_ACTION_ASK_EXIT})) {
            $plugin_cookies->{self::SETUP_ACTION_ASK_EXIT} = SetupControlSwitchDefs::switch_on;
        }

        $ask_exit = $plugin_cookies->{self::SETUP_ACTION_ASK_EXIT};
        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_ASK_EXIT, TR::t('setup_ask_exit'), SetupControlSwitchDefs::$on_off_translated[$ask_exit],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$ask_exit]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show all channels category
        if (!isset($plugin_cookies->{self::SETUP_ACTION_SHOW_ALL})) {
            $plugin_cookies->{self::SETUP_ACTION_SHOW_ALL} = SetupControlSwitchDefs::switch_on;
        }

        $show_all = $plugin_cookies->{self::SETUP_ACTION_SHOW_ALL};
        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_SHOW_ALL, TR::t('setup_show_all_channels'), SetupControlSwitchDefs::$on_off_translated[$show_all],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_all]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show favorites category
        if (!isset($plugin_cookies->{self::SETUP_ACTION_SHOW_FAVORITES})) {
            $plugin_cookies->{self::SETUP_ACTION_SHOW_FAVORITES} = SetupControlSwitchDefs::switch_on;
        }

        $show_fav = $plugin_cookies->{self::SETUP_ACTION_SHOW_FAVORITES};
        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_SHOW_FAVORITES, TR::t('setup_show_favorites'), SetupControlSwitchDefs::$on_off_translated[$show_fav],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_fav]), self::CONTROLS_WIDTH);


        //////////////////////////////////////
        // show history category
        if (!isset($plugin_cookies->{self::SETUP_ACTION_SHOW_HISTORY})) {
            $plugin_cookies->{self::SETUP_ACTION_SHOW_HISTORY} = SetupControlSwitchDefs::switch_on;
        }

        $show_history = $plugin_cookies->{self::SETUP_ACTION_SHOW_HISTORY};
        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_SHOW_HISTORY, TR::t('setup_show_history'), SetupControlSwitchDefs::$on_off_translated[$show_history],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_history]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // epg font size
        $font_size = $this->plugin->get_parameters(PARAM_EPG_FONT_SIZE, SetupControlSwitchDefs::switch_off);
        $font_ops_translated[SetupControlSwitchDefs::switch_on] = '%tr%setup_small';
        $font_ops_translated[SetupControlSwitchDefs::switch_off] = '%tr%setup_normal';

        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_EPG_FONT_SIZE, TR::t('setup_epg_font'), $font_ops_translated[$font_size],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$font_size]), self::CONTROLS_WIDTH);

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

    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("changing $control_id value to $new_value");
        }

        switch ($control_id) {
            case self::SETUP_ACTION_SHOW_TV:
                if (!is_apk()) {
                    self::toggle_cookie_param($plugin_cookies, $control_id);
                }
                break;

            case self::SETUP_ACTION_ASK_EXIT:
                self::toggle_cookie_param($plugin_cookies, $control_id);
                return Action_Factory::invalidate_folders(
                    array(Starnet_Tv_Groups_Screen::ID),
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies)));

            case self::SETUP_ACTION_SHOW_ALL:
            case self::SETUP_ACTION_SHOW_FAVORITES:
            case self::SETUP_ACTION_SHOW_HISTORY:
                self::toggle_cookie_param($plugin_cookies, $control_id);
                return $this->plugin->tv->reload_channels($this, $plugin_cookies);

            case self::SETUP_ACTION_EPG_FONT_SIZE:
                $this->plugin->toggle_parameter(PARAM_EPG_FONT_SIZE, SetupControlSwitchDefs::switch_off);
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }

    private static function toggle_cookie_param($plugin_cookies, $param)
    {
        $plugin_cookies->{$param} = ($plugin_cookies->{$param} === SetupControlSwitchDefs::switch_off)
            ? SetupControlSwitchDefs::switch_on
            : SetupControlSwitchDefs::switch_off;

        hd_debug_print("$param: " . $plugin_cookies->{$param});
    }
}
