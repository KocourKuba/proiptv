<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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

require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';
require_once 'lib/m3u/KnownCatchupSourceTags.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Playlists_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'playlist_setup';

    const CONTROL_RESET_PLAYLIST_DLG = 'reset_playlist';
    const ACTION_RESET_PLAYLIST_DLG_APPLY = 'reset_playlist_apply';
    const CONTROL_EXT_PARAMS_DLG = 'ext_params';
    const ACTION_EXT_PARAMS_DLG_APPLY = 'ext_params_apply';
    const CONTROL_USER_AGENT = 'user_agent';
    const CONTROL_DUNE_PARAMS = 'dune_params';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        return $this->do_get_control_defs();
    }

    /**
     * defs for all controls on screen
     * @return array
     */
    public function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // catchup settings

        $catchup_ops[ATTR_CATCHUP_UNKNOWN] = TR::t('by_default');
        $catchup_ops[ATTR_CATCHUP_SHIFT] = ATTR_CATCHUP_SHIFT;
        $catchup_ops[ATTR_CATCHUP_FLUSSONIC] = ATTR_CATCHUP_FLUSSONIC;
        //$catchup_ops[KnownCatchupSourceTags::cu_xstreamcode] = KnownCatchupSourceTags::cu_xstreamcode;
        $catchup_idx = $this->plugin->get_setting(PARAM_USER_CATCHUP, ATTR_CATCHUP_UNKNOWN);
        Control_Factory::add_combobox($defs, $this, null, PARAM_USER_CATCHUP,
            TR::t('setup_channels_archive_type'), $catchup_idx, $catchup_ops, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // additional parameters

        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_EXT_PARAMS_DLG, TR::t('setup_channels_ext_params'), TR::t('edit'),
            get_image_path('web.png'), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // reset playlist settings

        Control_Factory::add_image_button($defs, $this, null, self::CONTROL_RESET_PLAYLIST_DLG,
            TR::t('setup_channels_src_reset_playlist'), TR::t('clear'),
            get_image_path('brush.png'), self::CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_action_screen(
                        Starnet_Setup_Screen::ID,
                        RESET_CONTROLS_ACTION_ID,
                        null,
                        array('initial_sel_ndx' => $this->return_index)
                    )
                );

            case PARAM_USER_CATCHUP:
                $this->plugin->set_setting($user_input->control_id, $user_input->{$user_input->control_id});
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case self::CONTROL_RESET_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_RESET_PLAYLIST_DLG_APPLY);

            case self::ACTION_RESET_PLAYLIST_DLG_APPLY: // handle streaming settings dialog result
                $this->plugin->safe_clear_current_epg_cache();
                $this->plugin->remove_playlist_data($this->plugin->get_active_playlist_id());
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case self::CONTROL_EXT_PARAMS_DLG:
                return Action_Factory::show_dialog(TR::t('setup_channels_ext_params'), $this->do_get_ext_params_control_defs(), true);

            case self::ACTION_EXT_PARAMS_DLG_APPLY: // handle pass dialog result
                $user_agent = $user_input->{self::CONTROL_USER_AGENT};
                if (empty($user_agent)) {
                    $this->plugin->set_setting(PARAM_USER_AGENT, '');
                    HD::set_dune_user_agent(HD::get_default_user_agent());
                } else if ($user_agent !== HD::get_default_user_agent()) {
                    $this->plugin->set_setting(PARAM_USER_AGENT, $user_agent);
                    HD::set_dune_user_agent($user_agent);
                }

                $this->plugin->set_setting(PARAM_DISABLE_DUNE_PARAMS, $user_input->{PARAM_DISABLE_DUNE_PARAMS});

                $dune_params = explode(',', $user_input->{self::CONTROL_DUNE_PARAMS});
                $params_array = array();
                foreach ($dune_params as $param) {
                    $param_pair = explode(':', $param);
                    if (empty($param_pair) || count($param_pair) < 2) continue;

                    $param_pair[0] = trim($param_pair[0]);
                    if (strpos($param_pair[1], ",,") !== false) {
                        $param_pair[1] = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $param_pair[1]);
                    } else {
                        $param_pair[1] = str_replace(",", ",,", $param_pair[1]);
                    }

                    $params_array[$param_pair[0]] = $param_pair[1];
                }

                $provider = $this->plugin->get_active_provider();
                if (!is_null($provider)) {
                    // do not update dune_params if they the same as config value
                    $config_dune_params = $provider->getConfigValue(PARAM_DUNE_PARAMS);
                    if ($user_input->{self::CONTROL_DUNE_PARAMS} === $config_dune_params) {
                        $params_array = array();
                    }
                }

                $this->plugin->set_dune_params($params_array);

                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RELOAD:
                hd_debug_print(ACTION_RELOAD);
                $action = Action_Factory::invalidate_all_folders(
                    $plugin_cookies,
                    null,
                    Action_Factory::reset_controls($this->do_get_control_defs())
                );

                if (!$this->plugin->reload_channels($plugin_cookies)) {
                    return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), $action);
                }

                return $action;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }

    /**
     * adult pass dialog defs
     * @return array
     */
    public function do_get_ext_params_control_defs()
    {
        hd_debug_print(null, true);
        $defs = array();

        Control_Factory::add_vgap($defs, 20);

        $user_agent = $this->plugin->get_setting(PARAM_USER_AGENT, '');
        Control_Factory::add_text_field($defs, $this, null, self::CONTROL_USER_AGENT, TR::t('setup_channels_user_agent'),
            $user_agent, false, false, false, true, 1200);

        $dune_params = $this->plugin->get_dune_params();
        $dune_params_str = '';
        foreach ($dune_params as $key => $param) {
            if (!empty($dune_params_str)) {
                $dune_params_str .= ',';
            }
            $dune_params_str .= "$key:$param";
        }

        $provider = $this->plugin->get_active_provider();
        if (!is_null($provider) && empty($dune_params_str)) {
            $dune_params_str = $provider->getConfigValue(PARAM_DUNE_PARAMS);
        }

        $disable_params = $this->plugin->get_setting(PARAM_DISABLE_DUNE_PARAMS, 1);
        Control_Factory::add_combobox($defs, $this, null, PARAM_DISABLE_DUNE_PARAMS,
            TR::t('setup_channels_disable_dune_params'), $disable_params, SwitchOnOff::$translated, 60);

        Control_Factory::add_text_field($defs, $this, null, self::CONTROL_DUNE_PARAMS, TR::t('setup_channels_dune_params'),
            $dune_params_str, false, false, false, true, 1200);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this, null, self::ACTION_EXT_PARAMS_DLG_APPLY, TR::t('ok'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }
}
