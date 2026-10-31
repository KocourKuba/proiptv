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

require_once 'lib/abstract_regular_screen.php';
require_once 'lib/user_input_handler_registry.php';
require_once 'starnet_vod_libraries_screen.php';
require_once 'starnet_vod_movie_screen.php';

/**
 * One search result for several media libraries. Every movie belongs to its own playlist:
 * the movie url carries its playlist id, the movie is played and remembered in the history of that playlist.
 */
class Starnet_Vod_Merged_Search_Screen extends Abstract_Regular_Screen
{
    const ID = 'vod_merged_search';

    /**
     * Collected result of the current search:
     * 'key' - search string and playlists,
     * 'libraries' - playlist id => array('loaded' => bool, 'done' => bool, 'name' => string, 'ids' => movie id => true),
     * 'movies' - array(playlist id, Short_Movie) in the order they are shown
     *
     * @var array|null
     */
    private static $search = null;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $search_string plain string or json encoded array with title and original_title
     * @param string[] $playlist_ids
     * @return false|string
     */
    public static function make_media_url_str($search_string, $playlist_ids)
    {
        return MediaURL::encode(array(
            PARAM_SCREEN_ID => static::ID,
            'search' => $search_string,
            'playlists' => implode(',', $playlist_ids),
        ));
    }

    /**
     * Searches in all libraries. Opens the merged result, or the movie itself if only one movie is found.
     * Nothing found is reported by a dialog instead of an empty list
     *
     * @param Default_Dune_Plugin $plugin
     * @param string $search_string plain string or json encoded array with title and original_title
     * @param string[] $playlist_ids
     * @param string $caption
     * @return array
     */
    public static function make_search_action($plugin, $search_string, $playlist_ids, $caption)
    {
        self::start_search($plugin, $search_string, $playlist_ids);

        $loaded = false;
        foreach (self::$search['libraries'] as $library) {
            $loaded = $loaded || $library['loaded'];
        }

        if (!$loaded) {
            self::$search = null;
            return Action_Factory::show_title_dialog(TR::t('err_load_vod'), Dune_Last_Error::get_last_error(LAST_ERROR_VOD_LIST));
        }

        $movies = self::$search['movies'];
        if (empty($movies)) {
            self::$search = null;
            hd_debug_print("Nothing found: $search_string");
            return Action_Factory::show_title_dialog($caption, TR::t('tv_screen_not_found'));
        }

        if (count($movies) === 1 && self::is_search_done()) {
            /** @var Short_Movie $movie */
            list($playlist_id, $movie) = $movies[0];
            hd_debug_print("Only one movie found, open it: $movie->id ($playlist_id)");
            return Action_Factory::open_folder(
                Starnet_Vod_Movie_Screen::make_vod_media_url_str($movie->id, $movie->name, $movie->poster_url, $movie->info, $playlist_id),
                $movie->name
            );
        }

        return Action_Factory::open_folder(static::make_media_url_str($search_string, $playlist_ids), $caption);
    }

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $add_to_favorite = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));

        $actions[GUI_EVENT_KEY_ENTER] = Action_Factory::open_folder();
        $actions[GUI_EVENT_KEY_D_BLUE] = $add_to_favorite;
        $actions[GUI_EVENT_KEY_DUNE] = $add_to_favorite;

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if ($user_input->control_id !== ACTION_ADD_FAV || !isset($user_input->selected_media_url)) {
            return null;
        }

        // favorites of the movie playlist
        $media_url = MediaURL::decode($user_input->selected_media_url);
        if (!$this->plugin->select_vod_playlist(safe_get_value($media_url, PARAM_PLAYLIST_ID))) {
            return null;
        }

        $movie_id = $media_url->movie_id;
        $is_in_favorites = $this->plugin->is_channel_in_order(VOD_FAV_GROUP_ID, $movie_id);
        $this->plugin->change_vod_favorites($is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD, $movie_id);

        return Action_Factory::invalidate_folders(
            array(
                $user_input->parent_media_url,
                Default_Dune_Plugin::get_group_media_url_str(VOD_FAV_GROUP_ID),
                Default_Dune_Plugin::get_group_media_url_str(VOD_HISTORY_GROUP_ID),
                Default_Dune_Plugin::get_group_media_url_str(VOD_GROUP_ID)
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("from_ndx: $from_ndx, MediaURL: " . $media_url->get_media_url_string(true), true);

        $search_string = safe_get_value($media_url, 'search', '');
        $playlist_ids = explode(',', safe_get_value($media_url, 'playlists', ''));

        // a new php process or a new search, the result is collected again
        if (is_null(self::$search) || self::$search['key'] !== self::make_key($search_string, $playlist_ids)) {
            self::start_search($this->plugin, $search_string, $playlist_ids);
        }

        // next parts of the libraries
        while (count(self::$search['movies']) <= $from_ndx && !self::is_search_done()) {
            self::fetch_next($this->plugin, $search_string);
        }

        $total = count(self::$search['movies']);
        $fav_ids = array();
        $items = array();
        foreach (array_slice(self::$search['movies'], $from_ndx) as $entry) {
            /** @var Short_Movie $movie */
            list($playlist_id, $movie) = $entry;

            if (!isset($fav_ids[$playlist_id])) {
                $fav_ids[$playlist_id] = $this->plugin->select_vod_playlist($playlist_id)
                    ? array_flip($this->plugin->get_channels_order(VOD_FAV_GROUP_ID))
                    : array();
            }

            // the media library of the movie: the tags line of the info, else the last line of the info
            $library = self::$search['libraries'][$playlist_id]['name'];
            $info = empty($movie->info) ? $movie->name : $movie->info;
            $view_item_params = array(
                ViewItemParams::icon_path => $movie->poster_url,
                ViewItemParams::item_detailed_icon_path => empty($movie->big_poster_url) ? $movie->poster_url : $movie->big_poster_url,
                ViewItemParams::item_caption_color => DEF_LABEL_TEXT_COLOR_WHITE,
            );
            if (defined('ViewItemParams::item_detailed_info_tags')) {
                $view_item_params[ViewItemParams::item_detailed_info] = $info;
                $view_item_params[ViewItemParams::item_detailed_info_tags] = $library;
            } else {
                // a translation marker is translated by the firmware only as a whole string
                $view_item_params[ViewItemParams::item_detailed_info] = TR::translate($info) . "||$library";
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Vod_Movie_Screen::make_vod_media_url_str(
                    $movie->id, $movie->name, $movie->poster_url, $movie->info, $playlist_id),
                PluginRegularFolderItem::caption => $movie->name,
                PluginRegularFolderItem::starred => isset($fav_ids[$playlist_id][$movie->id]),
                PluginRegularFolderItem::view_item_params => $view_item_params,
            );
        }

        return array(
            PluginRegularFolderRange::total => $total,
            PluginRegularFolderRange::more_items_available => !self::is_search_done(),
            PluginRegularFolderRange::from_ndx => min((int)$from_ndx, $total),
            PluginRegularFolderRange::count => count($items),
            PluginRegularFolderRange::items => $items
        );
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $search_string
     * @param string[] $playlist_ids
     * @return string
     */
    private static function make_key($search_string, $playlist_ids)
    {
        return $search_string . "\n" . implode(',', $playlist_ids);
    }

    /**
     * New search: the first part of every library
     *
     * @param Default_Dune_Plugin $plugin
     * @param string $search_string
     * @param string[] $playlist_ids
     * @return void
     */
    private static function start_search($plugin, $search_string, $playlist_ids)
    {
        self::$search = array(
            'key' => self::make_key($search_string, $playlist_ids),
            'libraries' => array(),
            'movies' => array(),
        );

        foreach ($playlist_ids as $playlist_id) {
            if (empty($playlist_id)) continue;

            $info = Starnet_Vod_Libraries_Screen::get_library_info($plugin, $playlist_id);
            self::$search['libraries'][$playlist_id] = array(
                'loaded' => false,
                'done' => false,
                'name' => $info[PARAM_NAME],
                'ids' => array(),
            );
        }

        self::fetch_next($plugin, $search_string);
    }

    /**
     * Next part of every library that is not finished yet. A library is finished when it gives no new movies:
     * the provider paging is over, or the whole result is returned again (M3U, providers without paging)
     *
     * @param Default_Dune_Plugin $plugin
     * @param string $search_string
     * @return void
     */
    private static function fetch_next($plugin, $search_string)
    {
        foreach (array_keys(self::$search['libraries']) as $playlist_id) {
            $library = &self::$search['libraries'][$playlist_id];
            if ($library['done']) continue;

            if (!$plugin->select_vod_playlist($playlist_id)) {
                hd_debug_print("Media library of playlist $playlist_id is not available");
                $library['done'] = true;
                continue;
            }

            if (!$library['loaded']) {
                // as on entering the VOD: M3U VOD playlist is loaded to the search database, providers init their data
                if (!$plugin->vod->fetchVodCategories()) {
                    hd_debug_print("Media library of playlist $playlist_id: fetch categories failed");
                    $library['done'] = true;
                    continue;
                }

                $library['loaded'] = true;
                $plugin->vod->reset_movie_counter();
                $plugin->vod->try_reset_pages();
            }

            $count = 0;
            foreach ($plugin->vod->getSearchResults($search_string) as $movie) {
                if (isset($library['ids'][$movie->id])) continue;

                $library['ids'][$movie->id] = true;
                self::$search['movies'][] = array($playlist_id, $movie);
                ++$count;
            }

            hd_debug_print("Media library of playlist $playlist_id: found $count movies");
            if ($count === 0) {
                $library['done'] = true;
            }
        }
        unset($library);
    }

    /**
     * @return bool
     */
    private static function is_search_done()
    {
        foreach (self::$search['libraries'] as $library) {
            if (!$library['done']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected $folder_view_ids = array(
        VIEW_ICONS_5X2_MOVIE_CAPTION,
        VIEW_ICONS_5X2_MOVIE_NO_CAPTION,
        VIEW_ICONS_5X3_MOVIE_NO_CAPTION,
        VIEW_LIST_1X12_VOD_INFO_SMALL,
        VIEW_LIST_1X10_VOD_INFO_NORMAL,
    );
}
