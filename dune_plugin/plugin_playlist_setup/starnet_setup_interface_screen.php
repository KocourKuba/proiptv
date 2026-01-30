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

class Starnet_Setup_Interface_Screen extends Abstract_Controls_Screen
{
    const ID = 'interface_setup';

    const CONTROL_EDIT_CATEGORY_SCREEN = 'edit_category_screen';
    const CONTROL_EDIT_NEWUI_SETTINGS = 'edit_newui_settings';
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

        $params = array(PARAM_RETURN_INDEX => 1);
        if (!$this->plugin->is_vod_playlist()) {
            //////////////////////////////////////
            // Category settings
            Control_Factory::add_image_button($defs, $this, self::CONTROL_EDIT_CATEGORY_SCREEN, TR::t('setup_category_title'),
                TR::t('setup_change_settings'), $setting_icon, Control_Factory::SCR_CONTROLS_WIDTH, $params);

            if (Starnet_Epfs_Handler::$enabled) {
                //////////////////////////////////////
                // Interface NewUI settings
                Control_Factory::add_image_button($defs, $this, self::CONTROL_EDIT_NEWUI_SETTINGS, TR::t('setup_interface_newui_title'),
                    TR::t('setup_change_settings'), $setting_icon, Control_Factory::SCR_CONTROLS_WIDTH, $params);
            }
        }

        //////////////////////////////////////
        // change background
        if ($this->plugin->is_background_image_default()) {
            $button = TR::t('by_default');
        } else {
            $button = substr(basename($this->plugin->get_background_image()), strlen($this->plugin->get_active_playlist_id()) + 1);
        }

        Control_Factory::add_image_button($defs, $this, ACTION_CHANGE_BACKGROUND,
            TR::t('change_background'), $button, get_image_path('image.png'), Control_Factory::SCR_CONTROLS_WIDTH, $params);

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
        $sel_ndx = safe_get_value($user_input, 'initial_sel_ndx', -1);

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $ret_action = null;
                if ($this->force_parent_reload) {
                    $ret_action = ACTION_RELOAD;
                }
                return self::make_return_action($parent_media_url, $ret_action);

            case self::CONTROL_EDIT_CATEGORY_SCREEN: // show category settings dialog
                return Action_Factory::open_folder(
                    Starnet_Setup_Category_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_category_title'));

            case self::CONTROL_EDIT_NEWUI_SETTINGS: // show interface NewUI settings screen
                return Action_Factory::open_folder(
                    Starnet_Setup_Interface_NewUI_Screen::make_controls_media_url_str(static::ID, $user_input->return_index),
                    TR::t('setup_interface_newui_title'));

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
                $cached_image = get_cached_image_path($this->plugin->get_active_playlist_id() . '_' . $data->{PARAM_CAPTION});

                hd_print("copy from: " . $data->{PARAM_FILEPATH} . " to: $cached_image");
                if (!copy($data->{PARAM_FILEPATH}, $cached_image)) {
                    return Action_Factory::show_title_dialog(TR::t('error'), TR::t('err_copy'));
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
