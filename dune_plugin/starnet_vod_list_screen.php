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

require_once 'lib/abstract_regular_screen.php';
require_once 'lib/short_movie_range.php';
require_once 'starnet_vod_search_screen.php';

class Starnet_Vod_List_Screen extends Abstract_Regular_Screen implements User_Input_Handler
{
    const ID = 'vod_list';

    /**
     * @param MediaURL $media_url
     * @param Object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $add_action = User_Input_Handler_Registry::create_action($this, ACTION_CREATE_SEARCH, TR::t('search'));

        return array(
            GUI_EVENT_KEY_ENTER => Action_Factory::open_folder(),
            GUI_EVENT_KEY_SEARCH => $add_action,
            GUI_EVENT_KEY_C_YELLOW => $add_action,
            GUI_EVENT_KEY_D_BLUE => User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite')),
            GUI_EVENT_KEY_STOP => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        $media_url = MediaURL::decode($user_input->selected_media_url);
        $movie_id = $media_url->movie_id;

        switch ($user_input->control_id) {
            case ACTION_CREATE_SEARCH:
                $defs = array();
                Control_Factory::add_text_field($defs,
                    $this, null, ACTION_NEW_SEARCH, '',
                    $media_url->name, false, false, true, true, 1300, false, true);
                Control_Factory::add_vgap($defs, 500);
                return Action_Factory::show_dialog(TR::t('search'), $defs, true);

            case ACTION_NEW_SEARCH:
                return Action_Factory::close_dialog_and_run(User_Input_Handler_Registry::create_action($this, ACTION_RUN_SEARCH));

            case ACTION_RUN_SEARCH:
                $search_string = $user_input->{ACTION_NEW_SEARCH};
                /** @var Ordered_Array $search_items */
                $search_items = &$this->plugin->get_history(VOD_SEARCH_LIST, new Ordered_Array());
                $search_items->remove_item($search_string);
                $search_items->insert_item($search_string, false);
                $this->plugin->save_history(true);

                return Action_Factory::invalidate_folders(
                    array(Starnet_Vod_Search_Screen::get_media_url_string(SEARCH_MOVIES_GROUP_ID)),
                    Action_Factory::open_folder(
                        static::get_media_url_string(Vod_Category::FLAG_SEARCH, $search_string),
                        TR::t('search') . ": $search_string"));

            case ACTION_ADD_FAV:
                $fav_ids = $this->plugin->get_channels_order(FAV_MOVIE_GROUP_ID);
                $opt_type = in_array($movie_id, $fav_ids) ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_vod_favorites($opt_type, $movie_id);
                return Action_Factory::invalidate_folders(
                    array(
                        $user_input->parent_media_url,
                        Starnet_Vod_Favorites_Screen::get_media_url_string(FAV_MOVIE_GROUP_ID),
                        Starnet_Vod_History_Screen::get_media_url_string(HISTORY_MOVIES_GROUP_ID),
                        Starnet_Vod_Category_List_Screen::get_media_url_string(VOD_GROUP_ID)
                    )
                );
        }

        return null;
    }

    /**
     * @param string $category_id
     * @param string $genre_id
     * @return false|string
     */
    public static function get_media_url_string($category_id, $genre_id)
    {
        return MediaURL::encode(array('screen_id' => self::ID, 'category_id' => $category_id, 'genre_id' => $genre_id));
    }

    /**
     * @param MediaURL $media_url
     * @param int $from_ndx
     * @param Object $plugin_cookies
     * @return array
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("from_ndx: $from_ndx, MediaURL: " . $media_url->get_media_url_str(true), true);

        $this->plugin->vod->try_reset_pages();
        if (empty($media_url->genre_id)
            || $media_url->category_id === Vod_Category::FLAG_ALL_MOVIES
            || $media_url->category_id === Vod_Category::FLAG_ALL_SERIALS) {
            $key = $media_url->category_id;
        } else {
            $key = $media_url->category_id . "_" . $media_url->genre_id;
        }

        $movies = array();

        if ($media_url->category_id === Vod_Category::FLAG_SEARCH) {
            if ($from_ndx === 0) {
                $movies = $this->plugin->vod->getSearchList($media_url->genre_id);
            }
        } else if ($media_url->category_id === Vod_Category::FLAG_FILTER) {
            $movies = $this->plugin->vod->getFilterList($media_url->genre_id);
        } else {
            $movies = $this->plugin->vod->getMovieList($key);
        }

        $count = count($movies);
        if ($count) {
            $this->plugin->vod->add_movie_counter($key, $count);
            $movie_range = new Short_Movie_Range($from_ndx, $this->plugin->vod->get_movie_counter($key), $movies);
        } else {
            $movie_range = new Short_Movie_Range(0, 0);
        }

        $total = $movie_range->total;
        if ($total <= 0) {
            return $this->create_regular_folder_range(array());
        }

        $fav_ids = $this->plugin->get_channels_order(FAV_MOVIE_GROUP_ID);
        $items = array();
        if (isset($movie_range->short_movies)) {
            foreach ($movie_range->short_movies as $movie) {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_Vod_Movie_Screen::get_media_url_string($movie->id, $movie->name, $movie->poster_url, $movie->info),
                    PluginRegularFolderItem::caption => $movie->name,
                    PluginRegularFolderItem::starred => in_array($movie->id, $fav_ids),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => $movie->poster_url,
                        ViewItemParams::item_detailed_info => $movie->info,
                        ViewItemParams::item_detailed_icon_path => empty($movie->big_poster_url) ? $movie->poster_url : $movie->big_poster_url,
                        ViewItemParams::item_caption_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ),
                );

                $this->plugin->vod->set_cached_short_movie(new Short_Movie($movie->id, $movie->name, $movie->poster_url, $movie->info));
            }
        }

        return $this->create_regular_folder_range($items, $movie_range->from_ndx, $total, true);
    }

    /**
     * @param array $items
     * @param int $from_ndx
     * @param int $total
     * @param bool $more_items_available
     * @return array
     */
    public function create_regular_folder_range($items, $from_ndx = 0, $total = -1, $more_items_available = false)
    {
        if ($total === -1) {
            $total = $from_ndx + count($items);
        }

        if ($from_ndx >= $total) {
            $from_ndx = $total;
            $items = array();
        } else if ($from_ndx + count($items) > $total) {
            array_splice($items, $total - $from_ndx);
        }

        return array
        (
            PluginRegularFolderRange::total => (int)$total,
            PluginRegularFolderRange::more_items_available => $more_items_available,
            PluginRegularFolderRange::from_ndx => (int)$from_ndx,
            PluginRegularFolderRange::count => count($items),
            PluginRegularFolderRange::items => $items
        );
    }

    /**
     * @param MediaURL $media_url
     * @param Object $plugin_cookies
     * @return array|null
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        $this->plugin->vod->reset_movie_counter();
        $this->plugin->vod->clear_movie_cache();

        return parent::get_folder_view($media_url, $plugin_cookies);
    }

    /**
     * @inheritDoc
     */
    public function get_folder_views()
    {
        hd_debug_print(null, true);

        return array(
            $this->plugin->get_screen_view('icons_5x2_movie_no_caption'),
            $this->plugin->get_screen_view('list_1x10_movie_info_normal'),
        );
    }
}
