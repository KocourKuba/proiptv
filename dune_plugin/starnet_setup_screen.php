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

class Starnet_Setup_Screen extends Abstract_Controls_Screen
{
    const ID = 'setup';

    const CONTROL_INTERFACE_SCREEN = 'interface_screen';
    const CONTROL_PLAYLISTS_SCREEN = 'playlists_screen';
    const CONTROL_FOLDERS_SCREEN = 'folders_screen';
    const CONTROL_EXT_SETUP_SCREEN = 'extended_setup_screen';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs();
    }

    /**
     * @return array
     */
    protected function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $setting_icon = get_image_path('settings.png');

        $defs = array();

        $ret_index = 0;
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
        $ret_index += 2;

        //////////////////////////////////////
        // Interface settings
        Control_Factory::add_image_button($defs, $this, array(PARAM_RETURN_INDEX => $ret_index), self::CONTROL_INTERFACE_SCREEN,
            TR::t('setup_interface_title'), TR::t('setup_change_settings'), $setting_icon, static::CONTROLS_WIDTH);
        $ret_index += 2;

        //////////////////////////////////////
        // Folders settings
        Control_Factory::add_image_button($defs, $this, array(PARAM_RETURN_INDEX => $ret_index), self::CONTROL_FOLDERS_SCREEN,
            TR::t('setup_folder_settings'), TR::t('setup_change_settings'), $setting_icon, static::CONTROLS_WIDTH);
        $ret_index += 2;

        //////////////////////////////////////
        // Extended settings
        Control_Factory::add_image_button($defs, $this, array(PARAM_RETURN_INDEX => $ret_index), self::CONTROL_EXT_SETUP_SCREEN,
            TR::t('setup_extended_setup'), TR::t('setup_change_settings'), $setting_icon, static::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

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
                return Action_Factory::open_folder(
                    Starnet_Setup_Interface_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_interface_title'));

            case self::CONTROL_FOLDERS_SCREEN: // show folders settings dialog
                return Action_Factory::open_folder(
                    Starnet_Setup_Folders_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_folder_settings'));

            case self::CONTROL_EXT_SETUP_SCREEN: // show additional settings dialog
                return Action_Factory::open_folder(
                    Starnet_Setup_Ext_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_extended_setup'));

            case ACTION_REFRESH_SCREEN:
            case RESET_CONTROLS_ACTION_ID:
                $sel_ndx = safe_get_member($user_input, 'initial_sel_ndx', -1);
                return Action_Factory::reset_controls($this->do_get_control_defs(), null, $sel_ndx);
        }

        return null;
    }
}
