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

class Starnet_Setup_Playlist_Screen extends Abstract_Controls_Screen
{
    const ID = 'playlist_setup';

    const CONTROL_RESET_PLAYLIST_DLG = 'reset_playlist';
    const CONTROL_PLAYBACK_SCREEN = 'playback_screen';
    const CONTROL_EPG_SCREEN = 'epg_screen';
    const CONTROL_EDIT_INTERFACE_SETTINGS = 'edit_interface_screen';
    const CONTROL_EDIT_PROVIDER_SETTINGS = 'edit_setup_provider';
    const CONTROL_EDIT_IPTV_SETTINGS = 'edit_setup_iptv';
    const ACTION_RESET_PLAYLIST_DLG_APPLY = 'reset_playlist_apply';

    ///////////////////////////////////////////////////////////////////////

    /**
     * Get MediaURL string representation (json encoded)
     *
     * @param string $parent_id
     * @param int $return_index
     * @param string|null $playlist_id
     * @return false|string
     */
    public static function make_controls_media_url_str($parent_id, $return_index = -1, $playlist_id = null)
    {
        return MediaURL::encode(
            array(
                PARAM_SCREEN_ID => static::ID,
                PARAM_SOURCE_WINDOW_ID => $parent_id,
                PARAM_RETURN_INDEX => $return_index,
                PARAM_PLAYLIST_ID => $playlist_id
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs($media_url);
    }

    /**
     * @param MediaURL $media_url
     * @return array
     */
    protected function do_get_control_defs($media_url)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $defs = array();

        $setting_icon = get_image_path('settings.png');

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        $playlist_id = isset($media_url->{PARAM_PLAYLIST_ID}) ? $media_url->{PARAM_PLAYLIST_ID} : $this->plugin->get_active_playlist_id();

        //////////////////////////////////////
        // Playlist name

        $params = array(PARAM_RETURN_INDEX => 1);
        $uri = $this->plugin->get_playlist_parameter($playlist_id, PARAM_URI);
        $name = $this->plugin->get_playlist_parameter($playlist_id, PARAM_NAME, basename($uri));
        Control_Factory::add_text_field($defs, $this, CONTROL_EDIT_NAME, TR::t('playlist_name'), $name,
            false, false, false, true, Control_Factory::SCR_CONTROLS_WIDTH,
            true, false, $params);

        //////////////////////////////////////
        // IPTV settings

        if ($this->plugin->get_playlist_parameter($playlist_id, PARAM_TYPE) === PARAM_PROVIDER) {
            Control_Factory::add_image_button($defs, $this, self::CONTROL_EDIT_PROVIDER_SETTINGS, TR::t('edit_provider_settings'),
                TR::t('setup_change_settings'), $setting_icon, Control_Factory::SCR_CONTROLS_WIDTH, $params);
        } else {
            Control_Factory::add_image_button($defs, $this, self::CONTROL_EDIT_IPTV_SETTINGS, TR::t('edit_iptv_settings'),
                TR::t('setup_change_settings'), $setting_icon, Control_Factory::SCR_CONTROLS_WIDTH, $params);
        }

        //////////////////////////////////////
        // Category settings
        Control_Factory::add_image_button($defs, $this, self::CONTROL_EDIT_INTERFACE_SETTINGS, TR::t('setup_interface_title'),
            TR::t('setup_change_settings'), $setting_icon, Control_Factory::SCR_CONTROLS_WIDTH, $params);

        if (!$this->plugin->is_vod_playlist()) {
            //////////////////////////////////////
            // EPG settings
            Control_Factory::add_image_button($defs, $this, self::CONTROL_EPG_SCREEN, TR::t('setup_epg_settings'),
                TR::t('setup_change_settings'), $setting_icon, Control_Factory::SCR_CONTROLS_WIDTH, $params);
        }

        //////////////////////////////////////
        // Streaming settings
        Control_Factory::add_image_button($defs, $this, self::CONTROL_PLAYBACK_SCREEN, TR::t('setup_playback_settings'),
            TR::t('setup_change_settings'), $setting_icon, Control_Factory::SCR_CONTROLS_WIDTH, $params);

        //////////////////////////////////////
        // reset playlist settings

        Control_Factory::add_image_button($defs, $this, self::CONTROL_RESET_PLAYLIST_DLG, TR::t('setup_channels_src_reset_playlist'),
            TR::t('clear'), get_image_path('brush.png'), Control_Factory::SCR_CONTROLS_WIDTH, $params);

        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $post_action = null;
        $control_id = $user_input->control_id;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $playlist_id = isset($parent_media_url->{PARAM_PLAYLIST_ID}) ? $parent_media_url->{PARAM_PLAYLIST_ID} : $this->plugin->get_active_playlist_id();
        $sel_ndx = safe_get_value($user_input, 'initial_sel_ndx', -1);

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $ret_action = null;
                if ($this->force_parent_reload) {
                    $ret_action = ACTION_RELOAD;
                }
                return self::make_return_action($parent_media_url, $ret_action);

            case CONTROL_EDIT_NAME:
                $this->plugin->set_playlist_parameter($playlist_id, PARAM_NAME, $user_input->{CONTROL_EDIT_NAME});
                break;

            case self::CONTROL_EDIT_PROVIDER_SETTINGS:
                return Action_Factory::open_folder(
                    Starnet_Setup_Provider_Screen::make_controls_media_url_str(static::ID, $user_input->return_index, $playlist_id),
                    TR::t('edit_provider_settings')
                );

            case self::CONTROL_EDIT_IPTV_SETTINGS:
                return Action_Factory::open_folder(
                    Starnet_Setup_Simple_IPTV_Screen::make_controls_media_url_str(static::ID, $user_input->return_index, $playlist_id),
                    TR::t('edit_iptv_settings')
                );

            case self::CONTROL_EDIT_INTERFACE_SETTINGS: // show interface settings screen
                return Action_Factory::open_folder(
                    Starnet_Setup_Interface_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_interface_title'));

            case self::CONTROL_PLAYBACK_SCREEN: // show streaming settings screen
                return Action_Factory::open_folder(
                    Starnet_Setup_Playback_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_playback_settings'));

            case self::CONTROL_EPG_SCREEN: // show epg settings dialog
                return Action_Factory::open_folder(
                    Starnet_Setup_Epg_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_epg_settings'));

            case self::CONTROL_RESET_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_RESET_PLAYLIST_DLG_APPLY);

            case self::ACTION_RESET_PLAYLIST_DLG_APPLY:
                $playlist_id = $this->plugin->get_active_playlist_id();
                Epg_Manager_Json::clear_epg_files($playlist_id);
                foreach ($this->plugin->get_selected_xmltv_ids() as $id) {
                    Epg_Manager_Xmltv::clear_epg_files($id);
                }
                $this->plugin->remove_playlist_data($playlist_id);
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RELOAD:
                $this->force_parent_reload = true;
                $this->plugin->reset_channels_loaded();
                if ($this->plugin->init_playlist_db(true)) {
                    $post_action = Action_Factory::invalidate_all_folders($plugin_cookies);
                } else {
                    $post_action = Action_Factory::show_title_dialog(TR::t('error'), TR::t('err_init_database'));
                }
                break;

            case ACTION_REFRESH_SCREEN:
            case RESET_CONTROLS_ACTION_ID:
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($parent_media_url), $post_action, $sel_ndx);
    }
}
