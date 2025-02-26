<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
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

class Starnet_Vod_Genres_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'vod_genres';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(GUI_EVENT_KEY_ENTER => User_Input_Handler_Registry::create_action($this, 'select_genre'));
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if ($user_input->control_id === 'select_genre') {
            if (!isset($user_input->selected_media_url)) {
                return null;
            }

            $media_url = MediaURL::decode($user_input->selected_media_url);
            $genre_id = $media_url->genre_id;
            $caption = $this->plugin->vod->get_genre_caption($genre_id);
            $media_url_str = $this->plugin->vod->get_genre_media_url_str($genre_id);

            return Action_Factory::open_folder($media_url_str, $caption);
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $this->plugin->vod->ensure_genres_loaded();

        $genre_ids = $this->plugin->vod->get_genre_ids();

        $items = array();

        foreach ($genre_ids as $genre_id) {
            $caption = $this->plugin->vod->get_genre_caption($genre_id);
            $media_url_str = $this->plugin->vod->get_genre_media_url_str($genre_id);
            $icon_url = $this->plugin->vod->get_genre_icon_url($genre_id);

            $items[] = array(
                PluginRegularFolderItem::media_url => $media_url_str,
                PluginRegularFolderItem::caption => $caption,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $icon_url,
                )
            );
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_views()
    {
        hd_debug_print(null, true);

        return array(
            $this->plugin->get_screen_view('list_1x11_info'),
        );
    }
}
