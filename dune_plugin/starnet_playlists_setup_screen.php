<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Playlists_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'channels_setup';

    const SETUP_ACTION_RESET_PLAYLIST_DLG = 'reset_playlist';
    const SETUP_ACTION_RESET_PLAYLIST_APPLY = 'reset_playlist_apply';
    const SETUP_ACTION_SQUARE_ICONS = PARAM_SQUARE_ICONS;
    const SETUP_ACTION_USER_CATCHUP = PARAM_USER_CATCHUP;
    const SETUP_ACTION_EXT_PARAMS_DLG = 'ext_params';
    const SETUP_ACTION_EXT_PARAMS_APPLY = 'ext_params_apply';
    const CONTROL_ACTION_USER_AGENT = 'user_agent';
    const CONTROL_ACTION_DUNE_PARAMS = 'dune_params';

    ///////////////////////////////////////////////////////////////////////

    /**
     * defs for all controls on screen
     * @param $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        hd_debug_print();
        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // playlists
        $playlist_idx = $this->plugin->get_playlists_idx();
        $display_path = array();
        foreach ($this->plugin->get_playlists()->get_order() as $item) {
            $display_path[] = HD::string_ellipsis($item);
        }
        if (empty($display_path)) {
            Control_Factory::add_label($defs, TR::t('setup_channels_src_playlists'), TR::t('setup_channels_src_no_playlists'));
        } else if (count($display_path) > 1) {
            if ($playlist_idx >= count($display_path)) {
                $this->plugin->set_playlists_idx(0);
            }
            Control_Factory::add_combobox($defs, $this, null, ACTION_CHANGE_PLAYLIST,
                TR::t('setup_channels_src_playlists'), $playlist_idx, $display_path, self::CONTROLS_WIDTH, true);
        } else {
            Control_Factory::add_label($defs, TR::t('setup_channels_src_playlists'), $display_path[0]);
            $this->plugin->set_playlists_idx(0);
        }

        //////////////////////////////////////
        // playlist import source

        Control_Factory::add_image_button($defs, $this, null,
            ACTION_ITEMS_EDIT, TR::t('setup_channels_src_edit_playlists'), TR::t('edit'),
            get_image_path('edit.png'), self::CONTROLS_WIDTH);

        $catchup_ops[KnownCatchupSourceTags::cu_unknown] = TR::t('by_default');
        $catchup_ops[KnownCatchupSourceTags::cu_default] = KnownCatchupSourceTags::cu_default;
        $catchup_ops[KnownCatchupSourceTags::cu_shift] = KnownCatchupSourceTags::cu_shift;
        $catchup_ops[KnownCatchupSourceTags::cu_append] = KnownCatchupSourceTags::cu_append;
        $catchup_ops[KnownCatchupSourceTags::cu_flussonic] = KnownCatchupSourceTags::cu_flussonic;
        $catchup_ops[KnownCatchupSourceTags::cu_xstreamcode] = KnownCatchupSourceTags::cu_xstreamcode;
        $catchup_idx = $this->plugin->get_settings(PARAM_USER_CATCHUP, KnownCatchupSourceTags::cu_unknown);
        Control_Factory::add_combobox($defs, $this, null, self::SETUP_ACTION_USER_CATCHUP,
            TR::t('setup_channels_archive_type'), $catchup_idx, $catchup_ops, self::CONTROLS_WIDTH, true);

        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_EXT_PARAMS_DLG, TR::t('setup_channels_ext_params'), TR::t('edit'),
            get_image_path('web.png'), self::CONTROLS_WIDTH);

        $square_icons = $this->plugin->get_settings(PARAM_SQUARE_ICONS, SetupControlSwitchDefs::switch_off);
        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_SQUARE_ICONS, TR::t('setup_channels_square_icons'), SetupControlSwitchDefs::$on_off_translated[$square_icons],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$square_icons]), self::CONTROLS_WIDTH);

        Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_RESET_PLAYLIST_DLG,
            TR::t('setup_channels_src_reset_playlist'), TR::t('clear'),
            get_image_path('brush.png'), self::CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 10);

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
    public function do_get_ext_params_control_defs()
    {
        $defs = array();

        Control_Factory::add_vgap($defs, 20);

        $user_agent = $this->plugin->get_settings(PARAM_USER_AGENT, HD::get_dune_user_agent());
        Control_Factory::add_text_field($defs, $this, null, self::CONTROL_ACTION_USER_AGENT, TR::t('setup_channels_user_agent'),
            $user_agent, false, false, 0, 1, 1200, 0);

        $dune_params = $this->plugin->get_settings(PARAM_DUNE_PARAMS, array());
        $dune_params_str = '';
        foreach ($dune_params as $param) {
            if (!empty($dune_params_str)) {
                $dune_params_str .= '|';
            }
            $dune_params_str .= $param;
        }

        Control_Factory::add_text_field($defs, $this, null, self::CONTROL_ACTION_DUNE_PARAMS, TR::t('setup_channels_dune_params'),
            $dune_params_str, false, false, 0, 1, 1200, 0);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this, null, self::SETUP_ACTION_EXT_PARAMS_APPLY, TR::t('ok'), 300);
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
        $new_value = '';
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("changing $control_id value to $new_value");
        }

        switch ($control_id) {

            case ACTION_CHANGE_PLAYLIST:
                hd_debug_print("Change playlist index: $new_value");
                $old_value = $this->plugin->get_playlists_idx();
                $this->plugin->set_playlists_idx($new_value);
                $action = $this->plugin->tv->reload_channels($this, $plugin_cookies);
                if ($action === null) {
                    $this->plugin->set_playlists_idx($old_value);
                    return Action_Factory::show_title_dialog(TR::t('err_load_playlist'));
                }
                return $action;

            case ACTION_ITEMS_EDIT:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Edit_List_Screen::ID,
                        'source_window_id' => static::ID,
                        'edit_list' => Starnet_Edit_List_Screen::SCREEN_TYPE_PLAYLIST,
                        'end_action' => ACTION_RELOAD,
                        'extension' => 'm3u|m3u8',
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_channels_src_edit_playlists'));

            case self::SETUP_ACTION_USER_CATCHUP:
                $this->plugin->set_settings(PARAM_USER_CATCHUP, $new_value);
                $this->plugin->tv->unload_channels();
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case self::SETUP_ACTION_SQUARE_ICONS:
                $this->plugin->toggle_setting(PARAM_SQUARE_ICONS, SetupControlSwitchDefs::switch_off);
                $this->need_update_epfs = true;
                return $this->update_epfs_data($plugin_cookies, Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies)));

            case self::SETUP_ACTION_RESET_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::SETUP_ACTION_RESET_PLAYLIST_APPLY);

            case self::SETUP_ACTION_RESET_PLAYLIST_APPLY: // handle streaming settings dialog result
                $this->plugin->tv->unload_channels();
                $this->plugin->epg_man->clear_epg_cache();
                $this->plugin->remove_settings();
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case self::SETUP_ACTION_EXT_PARAMS_DLG:
                return Action_Factory::show_dialog(TR::t('setup_channels_ext_params'), $this->do_get_ext_params_control_defs(), true);

            case self::SETUP_ACTION_EXT_PARAMS_APPLY: // handle pass dialog result
                $user_agent = $user_input->{self::CONTROL_ACTION_USER_AGENT};
                if ($user_agent !== HD::get_dune_user_agent()) {
                    $this->plugin->set_settings(PARAM_USER_AGENT, $user_agent);
                    HD::set_dune_user_agent($user_agent);
                }

                $dune_params_text = $user_input->{self::CONTROL_ACTION_DUNE_PARAMS};
                $dune_params = explode(',', $dune_params_text);
                foreach ($dune_params as $param) {
                    $dune_params = explode(':', $param);
                    if (strpos($dune_params[1], ",,") !== false) {
                        $dune_params[1] = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $dune_params[1]);
                    } else {
                        $dune_params[1] = str_replace(",", ",,", $dune_params[1]);
                    }

                    $params_array[$dune_params[0]] = $dune_params[1];
                }
                if (!empty($params_array)) {
                    $this->plugin->set_settings(PARAM_DUNE_PARAMS, $params_array);
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RELOAD:
                hd_debug_print("reload");
                return $this->plugin->tv->reload_channels($this, $plugin_cookies);
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
