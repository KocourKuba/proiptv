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

class Starnet_Setup_Folders_Screen extends Abstract_Controls_Screen
{
    const ID = 'folders_setup';

    const CONTROL_HISTORY_CHANGE_FOLDER = 'change_history_folder';
    const CONTROL_COPY_TO_DATA = 'copy_to_data';
    const CONTROL_COPY_TO_PLUGIN = 'copy_to_plugin';
    const CONTROL_CHANGE_XMLTV_CACHE_PATH = 'change_xmltv_cache_path';
    const CONTROL_CHANGE_CURL_CACHE_PATH = 'change_curl_cache_path';
    const ACTION_HISTORY_RESET_DEFAULT = 'reset_history_default';
    const ACTION_EPG_RESET_DEFAULT = 'reset_epg_default';
    const ACTION_HISTORY_FOLDER_SELECTED = 'history_folder_selected';
    const ACTION_EPG_FOLDER_SELECTED = 'epg_folder_selected';
    const ACTION_CURL_FOLDER_SELECTED = 'curl_folder_selected';
    const ACTION_CURL_RESET_DEFAULT = 'reset_curl_default';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs();
    }

    protected function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $defs = array();

        $folder_icon = get_image_path('folder.png');
        $refresh_icon = get_image_path('refresh.png');

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // history

        $history_path = $this->plugin->get_history_path();
        hd_debug_print("history path: $history_path");
        $display_path = HD::string_ellipsis($history_path);
        Control_Factory::add_image_button($defs, $this, self::CONTROL_HISTORY_CHANGE_FOLDER,
            TR::t('setup_history_folder_path'), $display_path, $folder_icon);

        Control_Factory::add_image_button($defs, $this, self::CONTROL_COPY_TO_DATA,
            TR::t('setup_copy_to_data'), TR::t('apply'), $refresh_icon);

        Control_Factory::add_image_button($defs, $this, self::CONTROL_COPY_TO_PLUGIN,
            TR::t('setup_copy_to_plugin'), TR::t('apply'), $refresh_icon);

        //////////////////////////////////////
        // EPG cache dir
        $cache_dir = $this->plugin->get_cache_dir(PARAM_XMLTV_CACHE_PATH, EPG_CACHE_SUBDIR);
        $free_size = TR::t('setup_epg_storage_info__1', HD::get_storage_size($cache_dir));
        $cache_dir = HD::string_ellipsis($cache_dir . '/');
        Control_Factory::add_image_button($defs, $this, self::CONTROL_CHANGE_XMLTV_CACHE_PATH, $free_size, $cache_dir, get_image_path('folder.png'));

        //////////////////////////////////////
        // Curl cache dir
        $cache_dir = $this->plugin->get_cache_dir(PARAM_CURL_CACHE_PATH, CURL_CACHE_SUBDIR);
        hd_debug_print("Cache path: $cache_dir");
        create_path($cache_dir);
        $free_size = TR::t('setup_curl_storage_info__1', HD::get_storage_size($cache_dir));
        $cache_dir = HD::string_ellipsis($cache_dir . '/');
        Control_Factory::add_image_button($defs, $this, self::CONTROL_CHANGE_CURL_CACHE_PATH, $free_size, $cache_dir, get_image_path('folder.png'));

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
            if ($this->force_parent_reload) {
                $this->force_parent_reload = false;
                hd_debug_print("Force parent reload", true);
                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
            }

            $actions[] = self::make_return_action(MediaURL::decode($user_input->parent_media_url));
            return Action_Factory::composite($actions);

            case self::CONTROL_HISTORY_CHANGE_FOLDER:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        Starnet_Folder_Screen::PARAM_CHOOSE_FOLDER => self::ACTION_HISTORY_FOLDER_SELECTED,
                        Starnet_Folder_Screen::PARAM_RESET_ACTION => self::ACTION_HISTORY_RESET_DEFAULT,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('setup_history_folder_path'));

            case self::ACTION_HISTORY_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                hd_debug_print(self::ACTION_HISTORY_FOLDER_SELECTED . ' ' . $data->{PARAM_FILEPATH});
                $this->plugin->set_history_path($data->{PARAM_FILEPATH});

                $post_action = Action_Factory::show_title_dialog(
                    TR::t('folder_screen_selected_folder__1', $data->{PARAM_CAPTION}),
                    $data->{PARAM_FILEPATH},
                    null,
                    Control_Factory::SCR_CONTROLS_WIDTH
                );
                break;

            case self::ACTION_HISTORY_RESET_DEFAULT:
                $data = MediaURL::make(array('filepath' => get_data_path()));
                hd_debug_print("do set history folder to default: $data->{PARAM_FILEPATH}");
                $this->plugin->set_history_path();
                break;

            case self::CONTROL_COPY_TO_DATA:
                $history_path = $this->plugin->get_history_path();
                hd_debug_print("copy to: $history_path");
                try {
                    HD::copy_data(get_data_path('history'), "/_" . PARAM_TV_HISTORY_ITEMS . "$/", $history_path);
                    $post_action = Action_Factory::show_title_dialog(TR::t('setup_copy_done'));
                } catch (Exception $ex) {
                    print_backtrace_exception($ex);
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_copy'), $ex->getMessage());
                }
                break;

            case self::CONTROL_COPY_TO_PLUGIN:
                hd_debug_print("copy to: " . get_data_path());
                try {
                    HD::copy_data($this->plugin->get_history_path(), '_' . PARAM_TV_HISTORY_ITEMS . '$/', get_data_path(HISTORY_SUBDIR));
                    $post_action = Action_Factory::show_title_dialog(TR::t('setup_copy_done'));
                } catch (Exception $ex) {
                    print_backtrace_exception($ex);
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_copy'), $ex->getMessage());
                }
                break;

            case self::CONTROL_CHANGE_XMLTV_CACHE_PATH:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        PARAM_END_ACTION => ACTION_RELOAD,
                        Starnet_Folder_Screen::PARAM_CHOOSE_FOLDER => self::ACTION_EPG_FOLDER_SELECTED,
                        Starnet_Folder_Screen::PARAM_RESET_ACTION => self::ACTION_EPG_RESET_DEFAULT,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('setup_epg_xmltv_cache_caption'));

            case self::ACTION_EPG_RESET_DEFAULT:
                hd_debug_print(self::ACTION_EPG_RESET_DEFAULT);
                foreach ($this->plugin->get_xmltv_sources_hash(XMLTV_SOURCE_ALL, $this->plugin->get_active_playlist_id()) as $id) {
                    Epg_Manager_Xmltv::clear_epg_files($id);
                }
                $this->plugin->set_parameter(PARAM_XMLTV_CACHE_PATH, '');
                $this->plugin->init_epg_manager();

                $default_path = $this->plugin->get_cache_dir(PARAM_XMLTV_CACHE_PATH, EPG_CACHE_SUBDIR);
                $actions[] = Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $default_path));
                $actions[] = User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID);
                return Action_Factory::composite($actions);

            case self::ACTION_EPG_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                hd_debug_print(self::ACTION_EPG_FOLDER_SELECTED . ": " . $data->{PARAM_FILEPATH}, true);
                if ($this->plugin->get_cache_dir(PARAM_XMLTV_CACHE_PATH, EPG_CACHE_SUBDIR) === $data->{PARAM_FILEPATH}) break;

                Epg_Manager_Xmltv::clear_epg_files(null);
                $this->plugin->set_parameter(PARAM_XMLTV_CACHE_PATH, str_replace("//", "/", $data->{PARAM_FILEPATH}));
                $this->plugin->init_epg_manager();
                $this->force_parent_reload = true;

                $actions[] = Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->{PARAM_CAPTION}));
                $actions[] = User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID);
                return Action_Factory::composite($actions);

            case self::CONTROL_CHANGE_CURL_CACHE_PATH:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        PARAM_END_ACTION => ACTION_RELOAD,
                        Starnet_Folder_Screen::PARAM_CHOOSE_FOLDER => self::ACTION_CURL_FOLDER_SELECTED,
                        Starnet_Folder_Screen::PARAM_RESET_ACTION => self::ACTION_CURL_RESET_DEFAULT,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('setup_epg_curl_cache_caption'));

            case self::ACTION_CURL_RESET_DEFAULT:
                hd_debug_print(self::ACTION_CURL_RESET_DEFAULT);
                return $this->set_new_path('');

            case self::ACTION_CURL_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                hd_debug_print(self::ACTION_CURL_FOLDER_SELECTED . ": " . $data->{PARAM_FILEPATH}, true);
                if ($this->plugin->get_cache_dir(PARAM_CURL_CACHE_PATH, CURL_CACHE_SUBDIR) !== $data->{PARAM_FILEPATH}) {
                    $selected_path = str_replace("//", "/", $data->{PARAM_FILEPATH});
                    return $this->set_new_path($selected_path);
                }
                break;

            case RESET_CONTROLS_ACTION_ID:
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs(), $post_action);
    }

    protected function set_new_path($path)
    {
        $curl_wrapper = $this->plugin->setup_curl();
        $curl_wrapper->clear_cache(true);
        $this->plugin->set_parameter(PARAM_CURL_CACHE_PATH, $path);
        $this->plugin->reset_vod();
        $this->plugin->reset_channels();
        $this->force_parent_reload = true;

        $new_path = $this->plugin->get_cache_dir(PARAM_CURL_CACHE_PATH, CURL_CACHE_SUBDIR);
        $actions[] = Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $new_path));
        $actions[] = User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID);
        return Action_Factory::composite($actions);
    }
}
