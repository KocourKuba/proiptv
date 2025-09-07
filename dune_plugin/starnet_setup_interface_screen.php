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

class Starnet_Setup_Interface_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'interface_setup';

    const CONTROL_SHOW_TV = 'show_tv';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        return $this->do_get_control_defs($plugin_cookies);
    }

    /**
     * interface dialog defs
     * @param object $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // Show in main screen
        if (!is_limited_apk()) {
            $show_tv = self::get_cookie_bool_param($plugin_cookies, self::CONTROL_SHOW_TV);
            hd_debug_print(self::CONTROL_SHOW_TV . ": $show_tv", true);
            Control_Factory::add_image_button($defs, $this, null,
                self::CONTROL_SHOW_TV, TR::t('setup_show_in_main'), SwitchOnOff::translate($show_tv),
                SwitchOnOff::to_image($show_tv), self::CONTROLS_WIDTH);
        }

        $ask_exit = $this->plugin->get_parameter(PARAM_ASK_EXIT, SwitchOnOff::on);
        hd_debug_print(PARAM_ASK_EXIT . ": $ask_exit", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_ASK_EXIT, TR::t('setup_ask_exit'), SwitchOnOff::translate($ask_exit),
            SwitchOnOff::to_image($ask_exit), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show separate VOD icon
        $show_vod_icon = $this->plugin->get_parameter(PARAM_SHOW_VOD_ICON, SwitchOnOff::off);
        hd_debug_print(PARAM_SHOW_VOD_ICON . ": $show_vod_icon", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_VOD_ICON, TR::t('setup_show_vod_icon'), SwitchOnOff::translate($show_vod_icon),
            SwitchOnOff::to_image($show_vod_icon), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // epg font size
        $font_size = $this->plugin->get_parameter(PARAM_EPG_FONT_SIZE, SwitchOnOff::off);
        hd_debug_print(PARAM_EPG_FONT_SIZE . ": $font_size", true);
        $font_ops_translated = array(SwitchOnOff::on => TR::t('setup_small'), SwitchOnOff::off => TR::t('setup_normal'));
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_EPG_FONT_SIZE, TR::t('setup_epg_font'), SwitchOnOff::translate_from($font_ops_translated, $font_size),
            SwitchOnOff::to_image($font_size), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // change background
        if ($this->plugin->is_background_image_default()) {
            $button = TR::t('by_default');
        } else {
            $button = substr(basename($this->plugin->get_background_image()), strlen($this->plugin->get_active_playlist_id()) + 1);
        }

        Control_Factory::add_image_button($defs, $this, null,
            ACTION_CHANGE_BACKGROUND, TR::t('change_background'), $button,
            get_image_path('image.png'), self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("changing $control_id value to $new_value", true);
        }

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:

            return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(
                        Starnet_Setup_Screen::ID,
                        RESET_CONTROLS_ACTION_ID,
                        null,
                        array('initial_sel_ndx' => $this->return_index)
                    )
                );

            case self::CONTROL_SHOW_TV:
                if (!is_limited_apk()) {
                    self::toggle_cookie_param($plugin_cookies, $control_id);
                }
                break;

            case PARAM_SHOW_VOD_ICON:
                $this->plugin->toggle_parameter($control_id);
                $enable_vod_icon = SwitchOnOff::to_def($this->plugin->is_vod_enabled() && $this->plugin->get_bool_parameter(PARAM_SHOW_VOD_ICON));
                $plugin_cookies->{PARAM_SHOW_VOD_ICON} = $enable_vod_icon;
                hd_debug_print("Update cookie values: $enable_vod_icon", true);

                return Action_Factory::invalidate_all_folders(
                    $plugin_cookies,
                    null,
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies))
                );

            case PARAM_ASK_EXIT:
            case PARAM_EPG_FONT_SIZE:
                $this->plugin->toggle_parameter($control_id, false);
                break;

            case ACTION_CHANGE_BACKGROUND:
                $media_url = Starnet_Folder_Screen::make_media_url(static::ID,
                    array(
                        PARAM_EXTENSION => 'png|jpg|jpeg',
                        Starnet_Folder_Screen::PARAM_CHOOSE_FILE => ACTION_FILE_SELECTED,
                        Starnet_Folder_Screen::PARAM_RESET_ACTION => ACTION_RESET_DEFAULT,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                        Starnet_Folder_Screen::PARAM_ALLOW_IMAGE_LIB => true,
                        Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                    )
                );
                return Action_Factory::open_folder($media_url->get_media_url_str(), TR::t('select_file'));

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $old_image = $this->plugin->get_background_image();
                $is_old_default = $this->plugin->is_background_image_default();
                $cached_image = get_cached_image_path($this->plugin->get_active_playlist_id() . '_' . $data->{Starnet_Folder_Screen::PARAM_CAPTION});

                hd_print("copy from: " . $data->{PARAM_FILEPATH} . " to: $cached_image");
                if (!copy($data->{PARAM_FILEPATH}, $cached_image)) {
                    return Action_Factory::show_title_dialog(TR::t('err_copy'));
                }

                if (!$is_old_default && $old_image !== $cached_image) {
                    unlink($old_image);
                }

                hd_debug_print("Set image $cached_image as background");
                $this->plugin->set_background_image($cached_image);
                $this->plugin->init_screen_view_parameters($cached_image);

                return Action_Factory::invalidate_all_folders(
                    $plugin_cookies,
                    null,
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies))
                );

            case ACTION_RESET_DEFAULT:
                hd_debug_print("Background set to default");
                $this->plugin->set_background_image(null);
                $this->plugin->init_screen_view_parameters($this->plugin->plugin_info['app_background']);

                return Action_Factory::invalidate_all_folders(
                    $plugin_cookies,
                    null,
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies))
                );
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
