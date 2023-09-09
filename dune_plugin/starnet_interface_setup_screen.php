<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Interface_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'interface_setup';

    const CONTROL_SHOW_TV = 'show_tv';

    ///////////////////////////////////////////////////////////////////////

    /**
     * interface dialog defs
     * @param $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // Show in main screen
        if (!is_apk()) {
            if (!isset($plugin_cookies->{self::CONTROL_SHOW_TV})) {
                $plugin_cookies->{self::CONTROL_SHOW_TV} = SetupControlSwitchDefs::switch_on;
            }

            $show_tv = $plugin_cookies->{self::CONTROL_SHOW_TV};
            Control_Factory::add_image_button($defs, $this, null,
                self::CONTROL_SHOW_TV, TR::t('setup_show_in_main'), SetupControlSwitchDefs::$on_off_translated[$show_tv],
                get_image_path(SetupControlSwitchDefs::$on_off_img[$show_tv]), self::CONTROLS_WIDTH);
        }

        $ask_exit = $this->plugin->get_parameter(PARAM_ASK_EXIT, SetupControlSwitchDefs::switch_on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_ASK_EXIT, TR::t('setup_ask_exit'), SetupControlSwitchDefs::$on_off_translated[$ask_exit],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$ask_exit]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show all channels category
        $show_all = $this->plugin->get_parameter(PARAM_SHOW_ALL, SetupControlSwitchDefs::switch_on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_ALL, TR::t('setup_show_all_channels'), SetupControlSwitchDefs::$on_off_translated[$show_all],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_all]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show favorites category
        $show_fav = $this->plugin->get_parameter(PARAM_SHOW_FAVORITES, SetupControlSwitchDefs::switch_on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_FAVORITES, TR::t('setup_show_favorites'), SetupControlSwitchDefs::$on_off_translated[$show_fav],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_fav]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show history category
        $show_history = $this->plugin->get_parameter(PARAM_SHOW_HISTORY, SetupControlSwitchDefs::switch_on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_HISTORY, TR::t('setup_show_history'), SetupControlSwitchDefs::$on_off_translated[$show_history],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_history]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // epg font size
        $font_size = $this->plugin->get_parameter(PARAM_EPG_FONT_SIZE, SetupControlSwitchDefs::switch_off);
        $font_ops_translated[SetupControlSwitchDefs::switch_on] = '%tr%setup_small';
        $font_ops_translated[SetupControlSwitchDefs::switch_off] = '%tr%setup_normal';

        Control_Factory::add_image_button($defs, $this, null,
            PARAM_EPG_FONT_SIZE, TR::t('setup_epg_font'), $font_ops_translated[$font_size],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$font_size]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // change background
        Control_Factory::add_image_button($defs, $this, null,
            ACTION_CHANGE_BACKGROUND, TR::t('change_background'), TR::t('edit'),
            "image.png", self::CONTROLS_WIDTH);

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
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        dump_input_handler($user_input);

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("changing $control_id value to $new_value", LOG_LEVEL_DEBUG);
        }

        switch ($control_id) {
            case self::CONTROL_SHOW_TV:
                if (!is_apk()) {
                    self::toggle_cookie_param($plugin_cookies, $control_id);
                }
                break;

            case PARAM_ASK_EXIT:
                $this->plugin->toggle_parameter($control_id, SetupControlSwitchDefs::switch_on);
                return Starnet_Epfs_Handler::invalidate_folders(
                    array(Starnet_Tv_Groups_Screen::ID),
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies)));

            case PARAM_SHOW_ALL:
            case PARAM_SHOW_FAVORITES:
            case PARAM_SHOW_HISTORY:
                $this->plugin->toggle_parameter($control_id, SetupControlSwitchDefs::switch_on);
                $this->plugin->tv->reload_channels($plugin_cookies);

                return Action_Factory::invalidate_all_folders($plugin_cookies, $this->plugin->get_screens(),
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies)));

            case ACTION_CHANGE_BACKGROUND:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => array(
                            'action' => ACTION_CHANGE_BACKGROUND,
                            'extension'	=> 'png|jpg|jpeg',
                        ),
                        'allow_network' => !is_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('edit_list_file'));

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file->action === ACTION_CHANGE_BACKGROUND) {
                    $cached_image = get_cached_image_path("{$this->plugin->get_playlist_hash()}_$data->caption");
                    hd_print("copy from: $data->filepath to: $cached_image");
                    if (!copy($data->filepath, $cached_image)) {
                        return Action_Factory::show_title_dialog(TR::t('err_copy'));
                    }

                    hd_debug_print("Set image $cached_image as background");
                    $this->plugin->set_background_image($cached_image);
                    $this->plugin->create_screen_views();

                    return Action_Factory::invalidate_all_folders($plugin_cookies, $this->plugin->get_screens(),
                        Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies)));
                }
                break;

            case ACTION_RESET_DEFAULT:
                hd_debug_print("Background set to default");
                $this->plugin->set_background_image(null);
                $this->plugin->create_screen_views();

                return Action_Factory::invalidate_all_folders($plugin_cookies, $this->plugin->get_screens(),
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies)));

            case PARAM_EPG_FONT_SIZE:
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

        hd_debug_print("$param: " . $plugin_cookies->{$param}, LOG_LEVEL_DEBUG);
    }
}
