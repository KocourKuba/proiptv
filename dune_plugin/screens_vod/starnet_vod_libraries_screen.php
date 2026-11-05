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

require_once 'lib/abstract_preloaded_regular_screen.php';
require_once 'lib/user_input_handler_registry.php';
require_once 'lib/list_utils.php';

/**
 * Media libraries of all playlists that have VOD. The selected one is opened in its own VOD context,
 * the active TV playlist is not changed.
 * The blue key sets which media libraries are shown and in what order (PARAM_VOD_LIBRARIES_VIEW).
 * The green key opens the VOD settings of the selected media library (r25+, not for M3U).
 */
class Starnet_Vod_Libraries_Screen extends Abstract_Preloaded_Regular_Screen
{
    const ID = 'vod_libraries';

    const ACTION_SHOW_SETUP = 'libraries_show_setup';
    const ACTION_SHOW_SETUP_APPLY = 'libraries_show_setup_apply';
    const SHOW_SETUP_CONFIG_ID = 'proiptv_vod_libraries';
    const ACTION_VOD_SETTINGS = 'libraries_vod_settings';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $open = User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER);

        $actions[GUI_EVENT_KEY_ENTER] = $open;
        $actions[GUI_EVENT_KEY_PLAY] = $open;
        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, self::ACTION_SHOW_SETUP,
            TR::t('vod_libraries_setup'));
        if (is_r25_or_higher()) {
            // VOD settings exist only on r25+
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, self::ACTION_VOD_SETTINGS,
                TR::t('setup_vod_title'));
        }
        $actions[GUI_EVENT_KEY_RETURN] = Action_Factory::close_and_run();

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if ($user_input->control_id === self::ACTION_SHOW_SETUP) {
            return $this->show_setup();
        }

        if ($user_input->control_id === self::ACTION_SHOW_SETUP_APPLY) {
            $this->apply_setup();
            return $this->invalidate_current_folder(MediaURL::decode(static::ID), $plugin_cookies, 0);
        }

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $playlist_id = safe_get_value($selected_media_url, PARAM_PLAYLIST_ID);
        if (empty($playlist_id)) {
            return null;
        }

        if ($user_input->control_id === self::ACTION_VOD_SETTINGS) {
            return $this->open_vod_settings($playlist_id);
        }

        if ($user_input->control_id !== ACTION_OPEN_FOLDER) {
            return null;
        }

        hd_debug_print("Open media library of playlist: $playlist_id");
        if (!$this->plugin->select_vod_playlist($playlist_id)) {
            return Action_Factory::show_title_dialog(TR::t('err_load_vod'), TR::t('err_vod_not_available'));
        }

        if (!$this->plugin->vod->fetchVodCategories()) {
            return Action_Factory::show_title_dialog(TR::t('err_load_vod'), Dune_Last_Error::get_last_error(LAST_ERROR_VOD_LIST));
        }

        // the category list url gets the playlist of the selected VOD context
        return Action_Factory::open_folder(Starnet_Vod_Category_List_Screen::make_group_media_url_str(VOD_GROUP_ID),
            safe_get_value($selected_media_url, PARAM_NAME));
    }

    /**
     * VOD settings of the media library, M3U media libraries do not have them
     *
     * @param string $playlist_id
     * @return array|null
     */
    protected function open_vod_settings($playlist_id)
    {
        $provider = $this->plugin->get_provider($playlist_id);
        if (is_null($provider) || Default_Dune_Plugin::is_provider_m3u_vod($provider)) {
            return null;
        }

        // the settings screen writes to the settings db of the selected VOD context
        if (!$this->plugin->select_vod_playlist($playlist_id)) {
            return Action_Factory::show_title_dialog(TR::t('err_load_vod'), TR::t('err_vod_not_available'));
        }

        return Action_Factory::open_folder(
            Starnet_Setup_Interface_Vod_Screen::make_controls_media_url_str(static::ID, -1, $playlist_id),
            TR::t('setup_vod_title'));
    }

    /**
     * Playlists that have VOD, in the order of the playlists
     *
     * @param Default_Dune_Plugin $plugin
     * @return string[]
     */
    public static function get_vod_playlists_ids($plugin)
    {
        $ids = array();
        foreach ($plugin->get_all_playlists_ids() as $playlist_id) {
            if ($plugin->has_playlist_vod($playlist_id)) {
                $ids[] = $playlist_id;
            }
        }

        return $ids;
    }

    /**
     * Media libraries in the shown order: playlist id => 1 shown, 0 hidden.
     * The saved order first, a media library that is not saved yet (a new playlist) is shown at the end;
     * the saved ones that have no VOD anymore are dropped
     *
     * @return array
     */
    protected function get_view_config()
    {
        $vod_ids = self::get_vod_playlists_ids($this->plugin);
        $has_vod = array_flip($vod_ids);

        $cfg = array();
        $saved = json_decode($this->plugin->get_parameter(PARAM_VOD_LIBRARIES_VIEW, ''), true);
        if (is_array($saved)) {
            foreach ($saved as $playlist_id => $shown) {
                $playlist_id = (string)$playlist_id;
                if (isset($has_vod[$playlist_id])) {
                    $cfg[$playlist_id] = $shown ? 1 : 0;
                }
            }
        }

        foreach ($vod_ids as $playlist_id) {
            if (!isset($cfg[$playlist_id])) {
                $cfg[$playlist_id] = 1;
            }
        }

        return $cfg;
    }

    /**
     * List of all media libraries: the shown ones are checked, the order can be changed
     *
     * @return array|null
     */
    protected function show_setup()
    {
        $cfg = $this->get_view_config();
        if (empty($cfg)) {
            return null;
        }

        $all_items = array();
        $all_ids = array();
        $checked_ids = array();
        foreach ($cfg as $playlist_id => $shown) {
            $playlist_id = (string)$playlist_id;
            $info = self::get_library_info($this->plugin, $playlist_id);
            Action_Factory::add_gui_item($all_items, $all_ids, $playlist_id, $info[PARAM_NAME], $info['icon']);
            if ($shown) {
                $checked_ids[] = $playlist_id;
            }
        }

        List_Utils::write_config_file(self::SHOW_SETUP_CONFIG_ID, $cfg);

        return Action_Factory::edit_list_config(self::SHOW_SETUP_CONFIG_ID, TR::t('vod_libraries_setup'),
            $all_items, $checked_ids,
            null, null, null, null,
            User_Input_Handler_Registry::create_action($this, self::ACTION_SHOW_SETUP_APPLY));
    }

    /**
     * The list is closed: the order and the shown media libraries are saved as one plugin parameter
     *
     * @return void
     */
    protected function apply_setup()
    {
        $edited = List_Utils::read_config_file(self::SHOW_SETUP_CONFIG_ID);
        safe_unlink(List_Utils::config_file_path(self::SHOW_SETUP_CONFIG_ID));
        if (empty($edited)) {
            // the list was not changed
            return;
        }

        $cfg = array();
        $current = $this->get_view_config();
        foreach ($edited as $playlist_id => $shown) {
            $playlist_id = (string)$playlist_id;
            if (isset($current[$playlist_id])) {
                $cfg[$playlist_id] = $shown ? 1 : 0;
            }
        }

        // a media library missing in the edited list keeps its place at the end
        foreach ($current as $playlist_id => $shown) {
            if (!isset($cfg[(string)$playlist_id])) {
                $cfg[(string)$playlist_id] = $shown;
            }
        }

        hd_debug_print('Media libraries view: ' . json_encode($cfg));
        $this->plugin->set_parameter(PARAM_VOD_LIBRARIES_VIEW, json_encode($cfg));
    }

    /**
     * Title, icon and VOD type of the media library of the playlist
     *
     * @param Default_Dune_Plugin $plugin
     * @param string $playlist_id
     * @return array PARAM_NAME, 'icon', PARAM_TYPE
     */
    public static function get_library_info($plugin, $playlist_id)
    {
        $params = $plugin->get_playlist_parameters($playlist_id);
        $title = safe_get_value($params, PARAM_NAME, '');
        if (empty($title)) {
            $title = $playlist_id;
        }

        $provider = $plugin->get_provider($playlist_id);
        if (is_null($provider)) {
            $icon_file = VOD_GROUP_ICON;
            $vod_type = TR::load('plugin_vod_type_m3u');
        } else {
            if ($title !== $provider->getName()) {
                $title .= " ({$provider->getName()})";
            }
            $icon_file = $provider->getLogo();
            $vod_type = Default_Dune_Plugin::is_provider_m3u_vod($provider)
                ? TR::load('plugin_vod_type_m3u')
                : TR::load('plugin_vod_type_json');
        }

        return array(PARAM_NAME => $title, 'icon' => $icon_file, PARAM_TYPE => $vod_type);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $items = array();
        foreach ($this->get_view_config() as $playlist_id => $shown) {
            if (!$shown) continue;

            $playlist_id = (string)$playlist_id;
            $info = self::get_library_info($this->plugin, $playlist_id);
            $title = $info[PARAM_NAME];
            $icon_file = $info['icon'];
            $vod_type = $info[PARAM_TYPE];

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array(
                    PARAM_SCREEN_ID => static::ID,
                    PARAM_PLAYLIST_ID => $playlist_id,
                    PARAM_NAME => $title,
                )),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::starred => $playlist_id === $this->plugin->get_active_playlist_id(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $icon_file,
                    ViewItemParams::item_detailed_icon_path => $icon_file,
                    ViewItemParams::item_detailed_info => "$title||$vod_type",
                ),
            );
        }

        hd_debug_print('Media libraries: ' . count($items));
        return $items;
    }

    /**
     * @inheritDoc
     */
    protected $folder_view_ids = array(
        VIEW_LIST_1X11_INFO,
        VIEW_LIST_2X11_SMALL_INFO,
        VIEW_LIST_3X11_NO_INFO,
    );
}
