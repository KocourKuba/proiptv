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

/**
 * NewUI settings of the plugin, the same for all playlists: the support of NewUI and on r25+ the rows view of VOD
 */
class Starnet_Setup_Plugin_NewUI_Screen extends Abstract_Controls_Screen
{
    const ID = 'plugin_newui_setup';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param object $plugin_cookies
     * @return array
     */
    protected function do_get_control_defs($media_url = null, &$plugin_cookies = null)
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // Enable NewUI
        $show_newui = safe_get_value($plugin_cookies, PARAM_COOKIE_ENABLE_NEWUI, SwitchOnOff::on);
        hd_debug_print(PARAM_COOKIE_ENABLE_NEWUI . ": $show_newui", true);
        Control_Factory::add_image_button($defs, $this, PARAM_COOKIE_ENABLE_NEWUI,
            TR::t('setup_support_newui'), SwitchOnOff::translate($show_newui), SwitchOnOff::to_image($show_newui));

        if (is_r25_or_higher()) {
            //////////////////////////////////////
            // Rows view of VOD
            $newui_view = $this->plugin->get_parameter(PARAM_VOD_NEWUI_VIEW, SwitchOnOff::off);
            Control_Factory::add_image_button($defs, $this, PARAM_VOD_NEWUI_VIEW,
                TR::t('setup_vod_newui_view'), SwitchOnOff::translate($newui_view), SwitchOnOff::to_image($newui_view));

            //////////////////////////////////////
            // Full movie info in the rows view, M3U VOD has no more info than its movie list and does not use it
            if (SwitchOnOff::to_bool($newui_view)) {
                $full_info = $this->plugin->get_parameter(PARAM_VOD_NEWUI_FULL_INFO, SwitchOnOff::off);
                Control_Factory::add_image_button($defs, $this, PARAM_VOD_NEWUI_FULL_INFO,
                    TR::t('setup_vod_full_info'), SwitchOnOff::translate($full_info), SwitchOnOff::to_image($full_info));
            }
        }

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $control_id = $user_input->control_id;
        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $reload = $this->force_parent_reload;
                $this->force_parent_reload = false;
                $post_action = self::make_return_action(MediaURL::decode($user_input->parent_media_url));
                if ($reload) {
                    // the opened VOD folders are built again, the TV pane of NewUI (epfs) does not depend on these settings
                    return Action_Factory::invalidate_folders(array(), $post_action, true);
                }
                return $post_action;

            case PARAM_COOKIE_ENABLE_NEWUI:
                toggle_cookie_param($plugin_cookies, $control_id, SwitchOnOff::on);
                Starnet_Epfs_Handler::clear_epfs_file();
                $actions[] = Action_Factory::show_title_dialog(TR::t('warning'), TR::t('setup_reboot_required'));
                $actions[] = Action_Factory::restart();
                return Action_Factory::composite($actions);

            case PARAM_VOD_NEWUI_VIEW:
            case PARAM_VOD_NEWUI_FULL_INFO:
                $this->force_parent_reload = true;
                $this->plugin->toggle_parameter($control_id, false);
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs(null, $plugin_cookies));
    }
}
