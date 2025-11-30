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
    const ACTION_EDIT_PROVIDER_SETTINGS = 'edit_setup_provider';
    const ACTION_EDIT_IPTV_SETTINGS = 'edit_setup_iptv';
    const ACTION_EDIT_CATEGORY_SCREEN = 'edit_category_screen';
    const ACTION_RESET_PLAYLIST_DLG_APPLY = 'reset_playlist_apply';
    const ACTION_BG_FILE_SELECTED = 'bg_file_selected';
    const ACTION_BG_RESET_DEFAULT = 'bg_reset_default';

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
        $ret_index = 1;

        $playlist_id = isset($media_url->{PARAM_PLAYLIST_ID}) ? $media_url->{PARAM_PLAYLIST_ID} : $this->plugin->get_active_playlist_id();

        //////////////////////////////////////
        // Playlist name

        $uri = $this->plugin->get_playlist_parameter($playlist_id, PARAM_URI);
        $name = $this->plugin->get_playlist_parameter($playlist_id, PARAM_NAME, basename($uri));
        Control_Factory::add_text_field($defs, $this, null, CONTROL_EDIT_NAME, TR::t('playlist_name'),
            $name, false, false, false, true, static::CONTROLS_WIDTH, true);
        $ret_index += 1;

        //////////////////////////////////////
        // IPTV settings

        if ($this->plugin->get_playlist_parameter($playlist_id, PARAM_TYPE) === PARAM_PROVIDER) {
            Control_Factory::add_image_button($defs, $this, array(PARAM_RETURN_INDEX => $ret_index), self::ACTION_EDIT_PROVIDER_SETTINGS,
                TR::t('edit_provider_settings'), TR::t('setup_change_settings'), $setting_icon, static::CONTROLS_WIDTH);
        } else {
            Control_Factory::add_image_button($defs, $this, array(PARAM_RETURN_INDEX => $ret_index), self::ACTION_EDIT_IPTV_SETTINGS,
                TR::t('edit_iptv_settings'), TR::t('setup_change_settings'), $setting_icon, static::CONTROLS_WIDTH);
        }
        $ret_index += 2;

        //////////////////////////////////////
        // Category settings
        Control_Factory::add_image_button($defs, $this, array(PARAM_RETURN_INDEX => $ret_index), self::ACTION_EDIT_CATEGORY_SCREEN,
            TR::t('setup_category_title'), TR::t('setup_change_settings'), $setting_icon, static::CONTROLS_WIDTH);
        $ret_index += 2;

        //////////////////////////////////////
        // Interface NewUI settings
        Control_Factory::add_image_button($defs, $this, array(PARAM_RETURN_INDEX => $ret_index), ACTION_EDIT_NEWUI_SETTINGS,
            TR::t('setup_interface_newui_title'), TR::t('setup_change_settings'), $setting_icon, static::CONTROLS_WIDTH);
        $ret_index += 2;

        //////////////////////////////////////
        // EPG settings
        Control_Factory::add_image_button($defs, $this, array(PARAM_RETURN_INDEX => $ret_index), self::CONTROL_EPG_SCREEN,
            TR::t('setup_epg_settings'), TR::t('setup_change_settings'), $setting_icon, static::CONTROLS_WIDTH);
        $ret_index += 2;

        //////////////////////////////////////
        // Streaming settings
        Control_Factory::add_image_button($defs, $this, array(PARAM_RETURN_INDEX => $ret_index), self::CONTROL_PLAYBACK_SCREEN,
            TR::t('setup_playback_settings'), TR::t('setup_change_settings'), $setting_icon, static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // change background
        if ($this->plugin->is_background_image_default()) {
            $button = TR::t('by_default');
        } else {
            $button = substr(basename($this->plugin->get_background_image()), strlen($this->plugin->get_active_playlist_id()) + 1);
        }

        Control_Factory::add_image_button($defs, $this, null,
            ACTION_CHANGE_BACKGROUND, TR::t('change_background'), $button,
            get_image_path('image.png'), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // reset playlist settings

        Control_Factory::add_image_button($defs, $this, null, self::CONTROL_RESET_PLAYLIST_DLG,
            TR::t('setup_channels_src_reset_playlist'), TR::t('clear'),
            get_image_path('brush.png'), static::CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 10);

        //file_put_contents(get_temp_path("test.json"), pretty_json_format($defs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
        $sel_ndx = safe_get_member($user_input, 'initial_sel_ndx', -1);

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return self::make_return_action($parent_media_url);

            case CONTROL_EDIT_NAME:
                $this->plugin->set_playlist_parameter($playlist_id, PARAM_NAME, $user_input->{CONTROL_EDIT_NAME});
                break;

            case self::ACTION_EDIT_PROVIDER_SETTINGS:
                return Action_Factory::open_folder(
                    Starnet_Setup_Provider_Screen::make_controls_media_url_str(static::ID, $user_input->return_index, $playlist_id),
                    TR::t('edit_provider_settings')
                );

            case self::ACTION_EDIT_IPTV_SETTINGS:
                return Action_Factory::open_folder(
                    Starnet_Setup_Simple_IPTV_Screen::make_controls_media_url_str(static::ID, $user_input->return_index, $playlist_id),
                    TR::t('edit_iptv_settings')
                );

            case self::CONTROL_PLAYBACK_SCREEN: // show streaming settings dialog
                return Action_Factory::open_folder(
                    Starnet_Setup_Playback_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_playback_settings'));

            case ACTION_EDIT_NEWUI_SETTINGS: // show interface NewUI settings dialog
                return Action_Factory::open_folder(
                    Starnet_Setup_Interface_NewUI_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_interface_newui_title'));

            case self::CONTROL_EPG_SCREEN: // show epg settings dialog
                return Action_Factory::open_folder(
                    Starnet_Setup_Epg_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_epg_settings'));

            case self::ACTION_EDIT_CATEGORY_SCREEN: // show category settings dialog
                return Action_Factory::open_folder(
                    Starnet_Setup_Category_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_category_title'));

            case ACTION_CHANGE_BACKGROUND:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        PARAM_EXTENSION => 'png|jpg|jpeg',
                        Starnet_Folder_Screen::PARAM_CHOOSE_FILE => self::ACTION_BG_FILE_SELECTED,
                        Starnet_Folder_Screen::PARAM_RESET_ACTION => self::ACTION_BG_RESET_DEFAULT,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                        Starnet_Folder_Screen::PARAM_ALLOW_IMAGE_LIB => true,
                        Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('select_file'));

            case self::ACTION_BG_FILE_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $old_image = $this->plugin->get_background_image();
                $is_old_default = $this->plugin->is_background_image_default();
                $cached_image = get_cached_image_path($this->plugin->get_active_playlist_id() . '_' . $data->{Starnet_Folder_Screen::PARAM_CAPTION});

                hd_print("copy from: " . $data->{PARAM_FILEPATH} . " to: $cached_image");
                if (!copy($data->{PARAM_FILEPATH}, $cached_image)) {
                    return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_copy'));
                }

                if (!$is_old_default && $old_image !== $cached_image) {
                    safe_unlink($old_image);
                }

                hd_debug_print("Set image $cached_image as background");
                $this->plugin->set_background_image($cached_image);
                $this->plugin->init_screen_view_parameters($cached_image);

                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                $actions[] = User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID);
                return Action_Factory::composite($actions);

            case self::ACTION_BG_RESET_DEFAULT:
                hd_debug_print("Background set to default");
                $this->plugin->set_background_image(null);
                $this->plugin->init_screen_view_parameters($this->plugin->plugin_info['app_background']);

                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                $actions[] = User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID);
                return Action_Factory::composite($actions);

            case self::CONTROL_RESET_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_RESET_PLAYLIST_DLG_APPLY);

            case self::ACTION_RESET_PLAYLIST_DLG_APPLY:
                $this->plugin->clear_playlist_epg_cache();
                $this->plugin->remove_playlist_data($playlist_id);
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RELOAD:
                $this->plugin->reset_channels_loaded();
                if ($this->plugin->init_playlist_db(true)) {
                    $post_action = Action_Factory::invalidate_all_folders($plugin_cookies, null, $post_action);
                } else {
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_init_database'));
                }
                break;

            case ACTION_REFRESH_SCREEN:
            case RESET_CONTROLS_ACTION_ID:
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($parent_media_url), $post_action, $sel_ndx);
    }
}
