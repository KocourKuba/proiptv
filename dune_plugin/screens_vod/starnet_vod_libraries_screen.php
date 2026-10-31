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

/**
 * Media libraries of all playlists that have VOD. The selected one is opened in its own VOD context,
 * the active TV playlist is not changed.
 */
class Starnet_Vod_Libraries_Screen extends Abstract_Preloaded_Regular_Screen
{
    const ID = 'vod_libraries';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $open = User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER);

        $actions[GUI_EVENT_KEY_ENTER] = $open;
        $actions[GUI_EVENT_KEY_PLAY] = $open;
        $actions[GUI_EVENT_KEY_RETURN] = Action_Factory::close_and_run();

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if ($user_input->control_id !== ACTION_OPEN_FOLDER || !isset($user_input->selected_media_url)) {
            return null;
        }

        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $playlist_id = safe_get_value($selected_media_url, PARAM_PLAYLIST_ID);
        if (empty($playlist_id)) {
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
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $items = array();
        foreach ($this->plugin->get_all_playlists_ids() as $playlist_id) {
            if (!$this->plugin->has_playlist_vod($playlist_id)) continue;

            $params = $this->plugin->get_playlist_parameters($playlist_id);
            $title = safe_get_value($params, PARAM_NAME, '');
            if (empty($title)) {
                $title = $playlist_id;
            }

            $provider = $this->plugin->get_provider($playlist_id);
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
