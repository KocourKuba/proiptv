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

class Starnet_Setup_Plugin_Interface_Screen extends Abstract_Controls_Screen
{
    const ID = 'plugin_interface_setup';

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
        // Show in main screen
        if (!is_limited_apk()) {
            $show_tv = $this->plugin->get_parameter(PARAM_SHOW_TV, SwitchOnOff::on);
            hd_debug_print(PARAM_SHOW_TV . ": $show_tv", true);
            Control_Factory::add_image_button($defs, $this, PARAM_SHOW_TV,
                TR::t('setup_show_in_main'), SwitchOnOff::translate($show_tv), SwitchOnOff::to_image($show_tv));
        }

        //////////////////////////////////////
        // auto play
        $auto_play = $this->plugin->get_parameter(PARAM_AUTO_PLAY, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, PARAM_AUTO_PLAY,
            TR::t('setup_autostart'), SwitchOnOff::translate($auto_play), SwitchOnOff::to_image($auto_play));

        //////////////////////////////////////
        // auto resume
        $auto_resume = $this->plugin->get_parameter(PARAM_AUTO_RESUME, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, PARAM_AUTO_RESUME,
            TR::t('setup_continue_play'), SwitchOnOff::translate($auto_resume), SwitchOnOff::to_image($auto_resume));

        //////////////////////////////////////
        // ask exit
        $ask_exit = $this->plugin->get_parameter(PARAM_ASK_EXIT, SwitchOnOff::on);
        hd_debug_print(PARAM_ASK_EXIT . ": $ask_exit", true);
        Control_Factory::add_image_button($defs, $this, PARAM_ASK_EXIT,
            TR::t('setup_ask_exit'), SwitchOnOff::translate($ask_exit), SwitchOnOff::to_image($ask_exit));

        //////////////////////////////////////
        // Show playlist first
        $playlist_first = $this->plugin->get_parameter(PARAM_PLAYLIST_FIRST, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, PARAM_PLAYLIST_FIRST,
            TR::t('setup_playlist_first'), SwitchOnOff::translate($playlist_first), SwitchOnOff::to_image($playlist_first));

        //////////////////////////////////////
        // movies get to the watch history of the box ("Recent")
        $save_recent = $this->plugin->get_parameter(PARAM_VOD_SAVE_RECENT, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, PARAM_VOD_SAVE_RECENT,
            TR::t('setup_vod_save_recent'), SwitchOnOff::translate($save_recent), SwitchOnOff::to_image($save_recent));

        //////////////////////////////////////
        // show media libraries of all playlists as a separate icon
        $show_libraries_icon = $this->plugin->get_parameter(PARAM_SHOW_VOD_LIBRARIES_ICON, SwitchOnOff::off);
        hd_debug_print(PARAM_SHOW_VOD_LIBRARIES_ICON . ": $show_libraries_icon", true);
        Control_Factory::add_image_button($defs, $this, PARAM_SHOW_VOD_LIBRARIES_ICON,
            TR::t('setup_show_vod_libraries_icon'), SwitchOnOff::translate($show_libraries_icon), SwitchOnOff::to_image($show_libraries_icon));

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
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                return self::make_return_action($parent_media_url);

            case PARAM_SHOW_TV:
                $this->plugin->toggle_parameter($control_id);
                break;

            case PARAM_AUTO_PLAY:
            case PARAM_AUTO_RESUME:
            case PARAM_PLAYLIST_FIRST:
            case PARAM_ASK_EXIT:
            case PARAM_VOD_SAVE_RECENT:
                $this->plugin->toggle_parameter($control_id, false);
                break;

            case PARAM_SHOW_VOD_LIBRARIES_ICON:
                $this->plugin->toggle_parameter($control_id, false);
                $plugin_cookies->{PARAM_COOKIE_SHOW_VOD_LIBRARIES_ICON} = SwitchOnOff::to_def($this->plugin->get_bool_parameter($control_id, false));

                return Action_Factory::invalidate_all_folders(
                    $plugin_cookies,
                    null,
                    Action_Factory::reset_controls($this->do_get_control_defs(null, $plugin_cookies))
                );
        }

        return Action_Factory::reset_controls($this->do_get_control_defs(null, $plugin_cookies));
    }
}
