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

///////////////////////////////////////////////////////////////////////////

class Starnet_Setup_Ext_Screen extends Abstract_Controls_Screen
{
    const ID = 'ext_setup';

    const CONTROL_ADULT_PASS_DLG = 'adult_pass_dialog';
    const ACTION_ADULT_PASS_DLG_APPLY = 'adult_pass_apply';
    const ACTION_SETTINGS_PASS_DLG_APPLY = 'settings_pass_apply';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs($plugin_cookies);
    }

    /**
     * @param object $plugin_cookies
     * @return array
     */
    protected function do_get_control_defs(&$plugin_cookies)
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // adult channel password
        $params = array('adult' => true);
        Control_Factory::add_image_button($defs, $this, self::CONTROL_ADULT_PASS_DLG, TR::t('setup_adult_title'),
            TR::t('setup_adult_change'), get_image_path('text.png'), Control_Factory::SCR_CONTROLS_WIDTH, $params);

        //////////////////////////////////////
        // Settings protection
        $params['adult'] = false;
        Control_Factory::add_image_button($defs, $this, self::CONTROL_ADULT_PASS_DLG, TR::t('setup_settings_protection_title'),
            TR::t('setup_adult_change'), get_image_path('text.png'), Control_Factory::SCR_CONTROLS_WIDTH, $params);

        //////////////////////////////////////
        // Settings full size remote
        if (is_limited_apk()) {
            $remote = $this->plugin->get_parameter(PARAM_FULL_SIZE_REMOTE, SwitchOnOff::off);
            Control_Factory::add_image_button($defs, $this, PARAM_FULL_SIZE_REMOTE,
                TR::t('setup_settings_full_remote'), SwitchOnOff::translate($remote), SwitchOnOff::to_image($remote));
        }

        //////////////////////////////////////
        // Patch palette
        $fix_palette = $this->plugin->get_parameter(PARAM_FIX_PALETTE, SwitchOnOff::off);
        if (!color_palette_check() && $fix_palette === SwitchOnOff::on) {
            $fix_palette = SwitchOnOff::off;
            $this->plugin->set_parameter(PARAM_FIX_PALETTE, $fix_palette);
        }
        Control_Factory::add_image_button($defs, $this, PARAM_FIX_PALETTE,
            TR::t('setup_settings_patch_palette'), SwitchOnOff::translate($fix_palette), SwitchOnOff::to_image($fix_palette));

        //////////////////////////////////////
        // debugging
        $debug_state = safe_get_value($plugin_cookies, PARAM_COOKIE_ENABLE_DEBUG, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, PARAM_COOKIE_ENABLE_DEBUG,
            TR::t('setup_debug'), SwitchOnOff::translate($debug_state), SwitchOnOff::to_image($debug_state));

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $control_id = $user_input->control_id;
        $post_action = null;
        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                return self::make_return_action($parent_media_url);

            case self::CONTROL_ADULT_PASS_DLG: // show pass dialog
                return $this->do_get_pass_control_defs($user_input->adult);

            case self::ACTION_ADULT_PASS_DLG_APPLY: // handle pass dialog result
                $param = $user_input->adult ? PARAM_ADULT_PASSWORD : PARAM_SETTINGS_PASSWORD;
                $old_pass = $this->plugin->get_parameter($param);
                if (empty($old_pass)) {
                    if (!empty($user_input->pass2)) {
                        $msg = TR::t('setup_pass_changed');
                        $this->plugin->set_parameter($param, $user_input->pass2);
                    } else {
                        $msg = TR::t('setup_pass_not_changed');
                    }
                } else if ($user_input->pass1 !== $old_pass) {
                    $msg = TR::t('err_wrong_old_password');
                } else if (empty($user_input->pass2)) {
                    $msg = TR::t('setup_pass_disabled');
                    $this->plugin->set_parameter($param, '');
                } else if ($user_input->pass1 !== $user_input->pass2) {
                    $msg = TR::t('setup_pass_changed');
                    $this->plugin->set_parameter($param, $user_input->pass2);
                } else {
                    $msg = TR::t('setup_pass_not_changed');
                }
                hd_debug_print("pass: $param, old pass: $old_pass, new pass: $user_input->pass2", true);

                $post_action = Action_Factory::show_title_dialog($msg);
                break;

            case PARAM_FULL_SIZE_REMOTE:
                $this->plugin->toggle_parameter(PARAM_FULL_SIZE_REMOTE, false);
                break;

            case PARAM_FIX_PALETTE:
                $new = $this->plugin->toggle_parameter(PARAM_FIX_PALETTE, false);
                if ($new) {
                    if (color_palette_check()) {
                        $error_msg = TR::t('err_no_need_patch');
                    } else {
                        $error_msg = '';
                        $action = color_palette_patch($error_msg);
                        if ($action !== false) {
                            return Action_Factory::show_title_dialog(TR::t('setup_settings_patch_palette'), TR::t('setup_patch_success'), $action);
                        }
                    }
                    $this->plugin->set_bool_parameter(PARAM_FIX_PALETTE, false);
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_patch'), $error_msg);
                } else if (color_palette_check()) {
                    $action = color_palette_restore();
                    if ($action !== null) {
                        return Action_Factory::show_title_dialog(TR::t('setup_settings_patch_palette'), TR::t('setup_patch_success'), $action);
                    }

                    $post_action = Action_Factory::show_title_dialog(TR::t('err_patch'), TR::t('err_restore_patch'));
                }

                break;

            case PARAM_COOKIE_ENABLE_DEBUG:
                $debug = SwitchOnOff::to_bool(toggle_cookie_param($plugin_cookies, PARAM_COOKIE_ENABLE_DEBUG));
                set_debug_log($debug);
                hd_debug_print("Debug logging: " . var_export($debug, true));
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies), $post_action);
    }

    /**
     * adult pass dialog defs
     * @param bool $adult
     * @return array
     */
    protected function do_get_pass_control_defs($adult)
    {
        hd_debug_print(null, true);

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        $old_pass = $this->plugin->get_parameter($adult ? PARAM_ADULT_PASSWORD : PARAM_SETTINGS_PASSWORD);
        if (!empty($old_pass)) {
            Control_Factory::add_text_field($defs, $this, 'pass1', TR::t('setup_old_pass'), '',
                true, true, false, true, Control_Factory::DLG_CONTROLS_WIDTH);
        }

        Control_Factory::add_text_field($defs, $this, 'pass2', TR::t('setup_new_pass'), '',
            true, true, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this, self::ACTION_ADULT_PASS_DLG_APPLY, TR::t('ok'), array('adult' => $adult));
        Control_Factory::add_cancel_button($defs);
        Control_Factory::add_vgap($defs, 10);

        $title = $adult ? TR::t('setup_adult_password') : TR::t('setup_settings_protection');
        return Action_Factory::show_dialog($defs, $title);
    }
}
