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
require_once 'lib/curl_wrapper.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'setup';

    const CONTROL_INTERFACE_SCREEN = 'interface_screen';
    const CONTROL_PLAYLISTS_SCREEN = 'playlists_screen';
    const CONTROL_EPG_SCREEN = 'epg_screen';
    const CONTROL_PLAYBACK_SCREEN = 'playback_screen';
    const CONTROL_EXT_SETUP_SCREEN = 'extended_setup_screen';

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
        return $this->do_get_control_defs();
    }

    /**
     * defs for all controls on screen
     * @return array
     */
    public function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $setting_icon = get_image_path('settings.png');

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        Control_Factory::add_vgap($defs, -10);
        Control_Factory::add_image_button($defs, $this, null,
            ACTION_PLUGIN_INFO,
            Dune_Default_UI_Parameters::AUTHOR_LOGO,
            " v.{$this->plugin->plugin_info['app_version']} [{$this->plugin->plugin_info['app_release_date']}]",
            get_image_path('info.png'),
            Abstract_Controls_Screen::CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 10);

        //////////////////////////////////////
        // Interface settings 2
        Control_Factory::add_image_button($defs, $this, null, self::CONTROL_INTERFACE_SCREEN,
            TR::t('setup_interface_title'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        if (HD::rows_api_support()) {
            //////////////////////////////////////
            // Interface NewUI settings 4
            Control_Factory::add_image_button($defs, $this, null, CONTROL_INTERFACE_NEWUI_SCREEN,
                TR::t('setup_interface_newui_title'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // Category settings 4
        Control_Factory::add_image_button($defs, $this, null, CONTROL_CATEGORY_SCREEN,
            TR::t('setup_category_title'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Playlist settings 6
        Control_Factory::add_image_button($defs, $this, null, self::CONTROL_PLAYLISTS_SCREEN,
            TR::t('tv_screen_playlists_setup'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // EPG settings 8
        Control_Factory::add_image_button($defs, $this, null, self::CONTROL_EPG_SCREEN,
            TR::t('setup_epg_settings'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Streaming settings 10
        Control_Factory::add_image_button($defs, $this, null, self::CONTROL_PLAYBACK_SCREEN,
            TR::t('setup_playback_settings'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Extended settings 12
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_EXT_SETUP_SCREEN,
            TR::t('setup_extended_setup'), TR::t('setup_change_settings'), $setting_icon, self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        static $history_txt;

        $lang = strtolower(TR::get_current_language());
        if (empty($history_txt)) {
            $doc = Curl_Wrapper::simple_download_content(Dune_Default_UI_Parameters::CHANGELOG_URL_PREFIX . "changelog.$lang.md");
            if ($doc === false) {
                hd_debug_print("Failed to get actual changelog.$lang.md, load local copy");
                $path = get_install_path("changelog.$lang.md");
                if (!file_exists($path)) {
                    $path = get_install_path("changelog.english.md");
                }
                $doc = file_get_contents($path);
            }

            $history_txt = str_replace(array("###", "##"), '', $doc);
        }

        $control_id = $user_input->control_id;
        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return Action_Factory::close_and_run();

            case ACTION_PLUGIN_INFO:
                return $this->plugin->get_plugin_info_dlg($this);

            case ACTION_DONATE_DLG: // show donate QR codes
                return $this->plugin->do_donate_dialog();

            case self::CONTROL_INTERFACE_SCREEN: // show interface settings dialog
                return Action_Factory::open_folder(Starnet_Setup_Interface_Screen::get_media_url_str(), TR::t('setup_interface_title'));

            case CONTROL_INTERFACE_NEWUI_SCREEN: // show interface NewUI settings dialog
                return Action_Factory::open_folder(Starnet_Setup_Interface_NewUI_Screen::get_media_url_str(), TR::t('setup_interface_newui_title'));

            case CONTROL_CATEGORY_SCREEN: // show category settings dialog
                return Action_Factory::open_folder(Starnet_Setup_Category_Screen::get_media_url_str(), TR::t('setup_category_title'));

            case self::CONTROL_PLAYLISTS_SCREEN: // show epg settings dialog
                return Action_Factory::open_folder(
                    Starnet_Setup_Playlists_Screen::get_media_url_string($this->plugin->get_active_playlist_id()),
                    TR::t('tv_screen_playlists_setup')
                );

            case self::CONTROL_EPG_SCREEN: // show epg settings dialog
                return Action_Factory::open_folder(Starnet_Setup_Epg_Screen::get_media_url_str(), TR::t('setup_epg_settings'));

            case self::CONTROL_PLAYBACK_SCREEN: // show streaming settings dialog
                return Action_Factory::open_folder(Starnet_Setup_Playback_Screen::get_media_url_str(), TR::t('setup_playback_settings'));

            case self::CONTROL_EXT_SETUP_SCREEN: // show additional settings dialog
                return Action_Factory::open_folder(Starnet_Setup_Ext_Screen::get_media_url_str(), TR::t('setup_extended_setup'));

            case RESET_CONTROLS_ACTION_ID:
                $sel_ndx = safe_get_member($user_input, 'initial_sel_ndx', -1);
                return Action_Factory::reset_controls($this->do_get_control_defs(), null, $sel_ndx);
        }

        return null;
    }
}
