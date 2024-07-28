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

class Starnet_Vod_Seasons_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'vod_seasons';

    /**
     * @param string $movie_id
     * @param string|null $season_id
     * @return false|string
     */
    public static function get_media_url_string($movie_id, $season_id = null)
    {
        $arr = array('screen_id' => self::ID, 'movie_id' => $movie_id);
        if ($season_id !== null) {
            $arr['season_id'] = $season_id;
        }

        return MediaURL::encode($arr);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param Object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(
            GUI_EVENT_KEY_ENTER => Action_Factory::open_folder(),
            GUI_EVENT_KEY_PLAY  => Action_Factory::open_folder(),
            GUI_EVENT_KEY_STOP  => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP),
        );
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param Object $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $movie = $this->plugin->vod->get_loaded_movie($media_url->movie_id);
        if (is_null($movie)) {
            return array();
        }

        $items = array();

        foreach ($movie->season_list as $season) {
            hd_debug_print("movie_id: $movie->id season_id: $season->id season_name: $season->name", true);
            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Vod_Series_List_Screen::get_media_url_string($movie->id, $season->id),
                PluginRegularFolderItem::caption => $season->name,
                PluginRegularFolderItem::view_item_params => array(ViewItemParams::icon_path => 'gui_skin://small_icons/folder.aai'),
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
            $this->plugin->get_screen_view('list_1x11_small_info'),
            $this->plugin->get_screen_view('list_1x11_info'),
        );
    }
}
