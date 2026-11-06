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

require_once 'starnet_vod_search_screen.php';
require_once 'lib/abstract_regular_screen.php';
require_once 'lib/user_input_handler_registry.php';
require_once 'lib/vod/short_movie_range.php';
require_once 'lib/epfs/config.php';
require_once 'lib/epfs/rows_factory.php';
require_once 'lib/gcomps_factory.php';
require_once 'lib/text_width.php';

/**
 * Movies of a VOD category, search or filter.
 *
 * The regular views, or on r25+ with the rows view on in the NewUI settings of the plugin the
 * only view is rows of posters under the info of the focused movie drawn over its art, as the
 * firmware's own movie lists (opened from "show all" of the catalog, the search and the filter).
 * The rows view keeps the loaded movies of the list: the next page is loaded when the
 * focus reaches the last rows and the folder is reloaded with it, the rows out of
 * sight are given as placeholders and filled by get_folder_rows() (r25 only).
 */
class Starnet_Vod_Movie_List_Screen extends Abstract_Regular_Screen
{
    const ID = 'vod_movies_list';

    const ACTION_OPEN_FILTER = 'movie_list_open_filter';

    const ROWS_COLS = 9;
    // rows around the focused one given at once, the others are placeholders
    const ROWS_NEAR_FOCUS = 4;
    // the next page is loaded when the focus is in one of the last rows, ahead of the end of the list
    const ROWS_LOAD_AHEAD = 3;
    // how often the rows view checks whether the next page is needed
    const ROWS_TIMER_MS = 200;

    const ROW_ID_PREFIX = 'row';
    const ITEM_ID_PREFIX = 'm';
    const LOADING_ROW_ID = 'ld';
    const ROWS_PARAMS_ID = 'vod_movies';

    // info of the focused movie and its art (narrow art layout)
    const INFO_X = 100;
    const INFO_Y = 40;
    // up to the art: the posters are portrait, they take only the right part of the art area
    const INFO_WIDTH = 1270;
    const INFO_HEIGHT = 456;
    const ART_WIDTH = 1024;
    const ART_HEIGHT = 576;
    const ROW_HEIGHT = 302;

    const INFO_TITLE_COLOR = '#FFFFE0FF';
    const INFO_SUBTITLE_COLOR = '#808080FF';
    const INFO_TEXT_COLOR = '#AFAFA0FF';
    const INFO_TEXT_SIZE = 30;
    const INFO_MAX_LINE_ROWS = 2;

    // size of the poster in a row and the watched part
    const POSTER_WIDTH = 190;
    const POSTER_HEIGHT = 285;
    const POSTER_PROGRESS_HEIGHT = 6;
    const PROGRESS_DONE_COLOR = '#FFFFFFFF';
    const PROGRESS_REST_COLOR = '#404040FF';
    // the watched part in the info
    const INFO_PROGRESS_ROW_HEIGHT = 44;
    const INFO_PROGRESS_WIDTH = 320;
    const INFO_PROGRESS_HEIGHT = 6;

    const ROWS_LOADING_URL = 'plugin_file://%shell_ext%/icons/loading_movie_poster_noalpha.png';
    const ROWS_FAILED_URL = 'plugin_file://%shell_ext%/icons/no_movie_poster.png';
    const ROWS_ART_LOADING_URL = 'plugin_file://%shell_ext%/icons/bg0.jpg';
    const ROWS_ART_MASK = 'vod_rows_mask.png';

    /**
     * Movies of the rows view: media url string of the list, Short_Movie[] in the list order,
     * movie id => true, whether the provider has no more pages
     *
     * @var array|null
     */
    protected $rows_list;

    /**
     * Index of the focused movie in the rows view
     *
     * @var int
     */
    protected $rows_focus = 0;

    /**
     * Number of the movies in the rows of the last built pane
     *
     * @var int
     */
    protected $rows_shown = 0;

    /**
     * The last viewing of the movies for the rows being built, see get_movie_progress()
     *
     * @var array|null
     */
    protected $rows_last_viewed;

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map();
    }

    /**
     * @return array
     */
    protected function do_get_action_map()
    {
        hd_debug_print(null, true);

        $is_rows_view = $this->is_rows_view();
        if ($is_rows_view) {
            $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER);
            $actions[GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE);
            $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);
        } else {
            $actions[GUI_EVENT_KEY_ENTER] = Action_Factory::open_folder();
        }

        $actions[GUI_EVENT_KEY_PLAY] = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);
        $actions[GUI_EVENT_KEY_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);
        $add_to_favorite = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
        $actions[GUI_EVENT_KEY_D_BLUE] = $add_to_favorite;
        $actions[GUI_EVENT_KEY_DUNE] = $add_to_favorite;
        $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_SHOW_SEARCH_DLG, TR::t('search'));

        // the rows view has the filter here, "add to list" is in the popup menu there
        if ($is_rows_view) {
            if ($this->is_filter_supported()) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, self::ACTION_OPEN_FILTER, TR::t(VOD_FILTER_GROUP_CAPTION));
            }
        } else if ($this->is_list_supported()) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_TO_LIST, TR::t('add_to_list'));
        }
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        return $actions;
    }

    /**
     * The provider has the filter of the movies
     *
     * @return bool
     */
    protected function is_filter_supported()
    {
        $group = $this->plugin->vod->get_special_groups()->get(VOD_FILTER_GROUP_ID);
        return !empty($group) && !$group[ACTION_DISABLED];
    }

    /**
     * The media library has the play list group
     *
     * @return bool
     */
    protected function is_list_supported()
    {
        $provider = $this->plugin->vod->get_provider();
        return !empty($provider) && Default_Dune_Plugin::is_provider_m3u_vod($provider);
    }

    /**
     * @return array
     */
    protected static function make_open_filter_action()
    {
        return Action_Factory::open_folder(Default_Dune_Plugin::get_group_media_url_str(VOD_FILTER_GROUP_ID), TR::t(VOD_FILTER_GROUP_CAPTION));
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $control_id = $user_input->control_id;

        if ($control_id === GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE) {
            return $this->update_rows_info($user_input);
        }

        if ($control_id === self::ACTION_OPEN_FILTER) {
            return self::make_open_filter_action();
        }

        if ($control_id === GUI_EVENT_TIMER) {
            return $this->on_rows_timer($user_input);
        }

        $movie = $this->get_selected_movie($user_input);
        if (is_null($movie)) {
            hd_debug_print('no selected movie', true);
            return null;
        }

        $movie_id = $movie->id;

        switch ($control_id) {
            case ACTION_PLAY_ITEM:
                $action = self::play_movie($this->plugin, $movie_id, $user_input, $plugin_cookies);
                // no series to play: the movie screen tells about it
                return $action === false ? self::make_open_movie_action($movie) : $action;

            case ACTION_OPEN_FOLDER:
                return $this->make_open_card_action($movie);

            case ACTION_SHOW_SEARCH_DLG:
                $defs = array();
                Control_Factory::add_text_field($defs, $this, ACTION_NEW_SEARCH, '', $movie->name,
                    false, false, true, true, Control_Factory::DLG_MAX_CONTROLS_WIDTH, false, true);
                Control_Factory::add_vgap($defs, 500);
                return Action_Factory::show_dialog($defs, TR::t('search'));

            case ACTION_NEW_SEARCH:
                $search_string = $user_input->{ACTION_NEW_SEARCH};
                $this->plugin->arrange_settings_values(VOD_SEARCH_LIST, $search_string, Ordered_Array::TOP);
                return Action_Factory::close_dialog_and_run(
                    Action_Factory::invalidate_folders(
                        array(Default_Dune_Plugin::get_group_media_url_str(VOD_SEARCH_GROUP_ID)),
                        static::make_search_action($this->plugin, $search_string, TR::t('search__1', $search_string))
                    )
                );

            case ACTION_ADD_FAV:
                $is_in_favorites = $this->plugin->is_channel_in_order(VOD_FAV_GROUP_ID, $movie_id);
                $opt_type = $is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_vod_favorites($opt_type, $movie_id);
                return Action_Factory::invalidate_folders(
                    array(
                        $user_input->parent_media_url,
                        Default_Dune_Plugin::get_group_media_url_str(VOD_FAV_GROUP_ID),
                        Default_Dune_Plugin::get_group_media_url_str(VOD_HISTORY_GROUP_ID),
                        Default_Dune_Plugin::get_group_media_url_str(VOD_GROUP_ID)
                    )
                );

            case ACTION_ADD_TO_LIST:
                $is_in_list = $this->plugin->is_channel_in_order(VOD_LIST_GROUP_ID, $movie_id);
                $this->plugin->change_channels_order(VOD_LIST_GROUP_ID, $movie_id, $is_in_list);
                $this->plugin->vod->toggle_special_group(VOD_LIST_GROUP_ID, !$this->plugin->get_order_count(VOD_LIST_GROUP_ID));
                hd_debug_print("Movie id: $movie_id added to list: $is_in_list");
                return Action_Factory::invalidate_folders(
                    array(
                        $user_input->parent_media_url,
                        Default_Dune_Plugin::get_group_media_url_str(VOD_LIST_GROUP_ID),
                        Default_Dune_Plugin::get_group_media_url_str(VOD_GROUP_ID),
                    )
                );

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                return Action_Factory::close_and_run(Action_Factory::open_folder(
                    Default_Dune_Plugin::get_group_media_url_str(VOD_LIST_GROUP_ID), TR::t('movie_list')));

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items = array();
                if ($this->is_rows_view() && $this->is_list_supported()) {
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_ADD_TO_LIST, TR::t('add_to_list'));
                }
                $cnt = $this->plugin->get_order_count(VOD_LIST_GROUP_ID);
                if ($cnt) {
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        ACTION_JUMP_TO_CHANNEL_IN_GROUP, TR::t('jump_to_list_group'));
                }
                return Action_Factory::show_popup_menu($menu_items);
        }

        return null;
    }

    /**
     * The movie screen
     *
     * @param Short_Movie $movie
     * @return array
     */
    protected static function make_open_movie_action($movie)
    {
        return Action_Factory::open_folder(
            Starnet_Vod_Movie_Screen::make_vod_media_url_str($movie->id, $movie->name, $movie->poster_url, $movie->info),
            $movie->name
        );
    }

    /**
     * ENTER in the rows view: the card of the movie.
     * The old movie screen tells about a movie that has nothing to show
     *
     * @param Short_Movie $short_movie
     * @return array
     */
    protected function make_open_card_action($short_movie)
    {
        $movie = $this->plugin->vod->get_loaded_movie($short_movie->id);
        if (is_null($movie) || (!$movie->has_seasons() && !$movie->has_series())) {
            return self::make_open_movie_action($short_movie);
        }

        return Action_Factory::open_folder(Starnet_Vod_Movie_Card_Screen::make_vod_media_url_str($short_movie->id), $short_movie->name);
    }

    /**
     * Starts the movie without its screen, from the saved position
     *
     * @param Default_Dune_Plugin $plugin
     * @param string $movie_id
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null|false false - the movie has nothing to play
     */
    public static function play_movie($plugin, $movie_id, $user_input, &$plugin_cookies)
    {
        $movie = $plugin->vod->get_loaded_movie($movie_id);
        if (is_null($movie) || !$movie->has_series()) {
            hd_debug_print("Movie $movie_id has no series to play");
            return false;
        }

        return self::play_series($plugin, $movie_id, self::get_series_to_play($plugin, $movie), $user_input, $plugin_cookies);
    }

    /**
     * Starts the series of the movie from its saved position, the next series follow in the player
     *
     * @param Default_Dune_Plugin $plugin
     * @param string $movie_id
     * @param string|null $series_id null - the first series
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    public static function play_series($plugin, $movie_id, $series_id, $user_input, &$plugin_cookies)
    {
        // the playback takes the series and the position from this media url, as from the series list
        $user_input->selected_media_url = Starnet_Vod_Series_List_Screen::make_vod_media_url_str($movie_id, null, $series_id);

        try {
            return $plugin->vod->vod_player_exec($user_input, $plugin_cookies);
        } catch (Exception $ex) {
            hd_debug_print("Movie can't played");
            print_backtrace_exception($ex);
            return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'), TR::t('warn_msg2__1', $ex->getMessage()));
        }
    }

    /**
     * The last watched series, the next one if it is watched to the end
     *
     * @param Default_Dune_Plugin $plugin
     * @param Movie $movie
     * @return string|null null - the first series
     */
    protected static function get_series_to_play($plugin, $movie)
    {
        $last = null;
        foreach ($plugin->get_vod_history($movie->get_id()) as $row) {
            if (is_null($last) || $row[COLUMN_TIMESTAMP] > $last[COLUMN_TIMESTAMP]) {
                $last = $row;
            }
        }

        if (is_null($last)) {
            return null;
        }

        $last_id = (string)$last[COLUMN_SERIES_ID];
        if (!$last[COLUMN_WATCHED]) {
            return $last_id;
        }

        $is_next = false;
        foreach (array_keys($movie->get_series_list()) as $series_id) {
            if ($is_next) {
                return (string)$series_id;
            }
            $is_next = (string)$series_id === $last_id;
        }

        return null;
    }

    /**
     * The movie the user input is about: the selected item of the regular view or the rows view
     *
     * @param object $user_input
     * @return Short_Movie|null
     */
    protected function get_selected_movie($user_input)
    {
        if (isset($user_input->selected_media_url)) {
            $media_url = MediaURL::decode($user_input->selected_media_url);
            if (!isset($media_url->movie_id)) {
                return null;
            }

            return new Short_Movie(
                $media_url->movie_id,
                safe_get_value($media_url, 'name', ''),
                safe_get_value($media_url, 'poster_url', ''),
                safe_get_value($media_url, 'info', '')
            );
        }

        if (isset($user_input->selected_item_id)) {
            return $this->get_rows_movie($user_input->selected_item_id);
        }

        hd_debug_print('user input selected media url not set', true);
        return null;
    }

    /**
     * Get MediaURL string representation (json encoded)
     * *
     * @param string $category_id
     * @param string $genre_id
     * @return false|string
     */
    public static function make_vod_media_url_str($category_id, $genre_id)
    {
        return MediaURL::encode(Vod_Context::add_playlist_id(array(PARAM_SCREEN_ID => static::ID, 'category_id' => $category_id, 'genre_id' => $genre_id)));
    }

    /**
     * Opens the search result, or the movie itself if only one movie is found.
     * Nothing found is reported by a dialog instead of an empty list
     *
     * @param Default_Dune_Plugin $plugin
     * @param string $search_string plain string or json encoded array with title and original_title
     * @param string $caption
     * @return array
     */
    public static function make_search_action($plugin, $search_string, $caption)
    {
        $movies = $plugin->vod->prefetchSearchList($search_string);
        if (empty($movies)) {
            $plugin->vod->takePrefetchedSearchList($search_string);
            hd_debug_print("Nothing found: $search_string");
            return Action_Factory::show_title_dialog($caption, TR::t('tv_screen_not_found'));
        }

        if (count($movies) === 1) {
            $plugin->vod->takePrefetchedSearchList($search_string);
            $movie = $movies[0];
            hd_debug_print("Only one movie found, open it: $movie->id");
            return Action_Factory::open_folder(
                Starnet_Vod_Movie_Screen::make_vod_media_url_str($movie->id, $movie->name, $movie->poster_url, $movie->info),
                $movie->name
            );
        }

        return Action_Factory::open_folder(static::make_vod_media_url_str(Vod_Category::FLAG_SEARCH, $search_string), $caption);
    }

    /**
     * Key of the movie counter and the pages of the list
     *
     * @param MediaURL $media_url
     * @return string
     */
    protected static function get_list_key(MediaURL $media_url)
    {
        if (empty($media_url->genre_id)
            || $media_url->category_id === Vod_Category::FLAG_ALL_MOVIES
            || $media_url->category_id === Vod_Category::FLAG_ALL_SERIALS) {
            return $media_url->category_id;
        }

        return $media_url->category_id . "_" . $media_url->genre_id;
    }

    /**
     * Next page of the list
     *
     * @param MediaURL $media_url
     * @param bool $first_page the first page of a search can be prefetched by make_search_action()
     * @return Short_Movie[]
     */
    protected function fetch_movies(MediaURL $media_url, $first_page)
    {
        if ($media_url->category_id === Vod_Category::FLAG_SEARCH) {
            $movies = $first_page ? $this->plugin->vod->takePrefetchedSearchList($media_url->genre_id) : null;
            if (is_null($movies)) {
                $movies = $this->plugin->vod->getSearchResults($media_url->genre_id);
            }
            return $movies;
        }

        if ($media_url->category_id === Vod_Category::FLAG_FILTER) {
            return $this->plugin->vod->getFilterList($media_url->genre_id);
        }

        return $this->plugin->vod->getMovieList(self::get_list_key($media_url));
    }

    /**
     * @param MediaURL $media_url
     * @param int $from_ndx
     * @param object $plugin_cookies
     * @return array
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("from_ndx: $from_ndx, MediaURL: " . $media_url->get_media_url_string(true), true);

        $this->plugin->vod->try_reset_pages();
        $key = self::get_list_key($media_url);
        $movies = $this->fetch_movies($media_url, $from_ndx === 0);

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

        $sticker = Control_Factory::create_sticker(get_image_path('play_small.png'), -20, -12, "left");
        // flipped once: in_array() per movie over these is O(page * list), and
        // the vod list group can hold the whole catalogue - tens of thousands
        // of entries on a provider m3u
        $fav_ids = array_flip($this->plugin->get_channels_order(VOD_FAV_GROUP_ID));
        $list_ids = $this->get_list_ids();

        $items = array();
        if (isset($movie_range->short_movies)) {
            foreach ($movie_range->short_movies as $movie) {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_Vod_Movie_Screen::make_vod_media_url_str($movie->id, $movie->name, $movie->poster_url, $movie->info),
                    PluginRegularFolderItem::caption => $movie->name,
                    PluginRegularFolderItem::starred => isset($fav_ids[$movie->id]),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::item_sticker => isset($list_ids[$movie->id]) ? $sticker : null,
                        ViewItemParams::icon_path => $movie->poster_url,
                        ViewItemParams::item_detailed_info => $movie->info,
                        ViewItemParams::item_detailed_icon_path => empty($movie->big_poster_url) ? $movie->poster_url : $movie->big_poster_url,
                        ViewItemParams::item_caption_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ),
                );
            }
        }

        return $this->create_regular_folder_range($items, $movie_range->from_ndx, $total, true);
    }

    /**
     * Movie ids of the play list group as keys, empty if the provider has no such group
     *
     * @return array
     */
    protected function get_list_ids()
    {
        $provider = $this->plugin->vod->get_provider();
        if ($provider && Default_Dune_Plugin::is_provider_m3u_vod($provider)) {
            return array_flip($this->plugin->get_channels_order(VOD_LIST_GROUP_ID));
        }

        return array();
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

        return array(
            PluginRegularFolderRange::total => (int)$total,
            PluginRegularFolderRange::more_items_available => $more_items_available,
            PluginRegularFolderRange::from_ndx => (int)$from_ndx,
            PluginRegularFolderRange::count => count($items),
            PluginRegularFolderRange::items => $items
        );
    }

    /**
     * The view belongs to the playlist of the media library (the selected VOD context), not to the active one
     *
     * @inheritDoc
     */
    protected function load_folder_view_index()
    {
        return $this->plugin->get_vod_setting(static::ID . '_view_idx', 0);
    }

    /**
     * @inheritDoc
     */
    protected function save_folder_view_index($idx)
    {
        $this->plugin->set_vod_setting(static::ID . '_view_idx', $idx);
    }

    /**
     * @return bool
     */
    protected function is_rows_view()
    {
        return $this->plugin->is_vod_rows_enabled();
    }

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array|null
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->get_folder_view_v2($media_url, null, $plugin_cookies);
    }

    /**
     * @param MediaURL $media_url
     * @param string|null $sel_state selection state of the reloaded rows view
     * @param object $plugin_cookies
     * @return array|null
     */
    public function get_folder_view_v2(MediaURL $media_url, $sel_state, &$plugin_cookies)
    {
        if ($this->is_rows_view()) {
            return $this->get_rows_folder_view($media_url, $sel_state, $plugin_cookies);
        }

        $this->rows_list = null;
        $this->reset_list($media_url);

        return parent::get_folder_view($media_url, $plugin_cookies);
    }

    /**
     * Starts the list from the first page
     *
     * @param MediaURL $media_url
     * @return void
     */
    protected function reset_list(MediaURL $media_url)
    {
        // the first page of a search was already fetched by make_search_action()
        $this->plugin->vod->reset_movie_counter($media_url->category_id === Vod_Category::FLAG_SEARCH
            && $this->plugin->vod->hasPrefetchedSearchList($media_url->genre_id));
        $this->plugin->vod->clear_movie_cache();
    }

    ///////////////////////////////////////////////////////////////////////
    // Rows view (r25+)

    /**
     * @param MediaURL $media_url
     * @param string|null $sel_state
     * @param object $plugin_cookies
     * @return array
     */
    protected function get_rows_folder_view(MediaURL $media_url, $sel_state, &$plugin_cookies)
    {
        $key = $media_url->get_raw_string();
        if (is_null($this->rows_list) || $this->rows_list['key'] !== $key) {
            // a new list, the same one is kept: it is reloaded with the next page,
            // after the favorites change or when it is opened again
            $this->reset_list($media_url);
            $this->plugin->vod->try_reset_pages();

            $this->rows_list = array('key' => $key, 'movies' => array(), 'ids' => array(), 'complete' => false);
            $this->rows_focus = 0;
            $this->load_rows_page($media_url, true);
        }

        return array(
            PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_ROWS,
            PluginFolderView::multiple_views_supported => false,
            PluginFolderView::folder_type => null,
            PluginFolderView::archive => null,
            PluginFolderView::data => array(
                PluginRowsFolderView::pane => $this->get_rows_pane(),
                PluginRowsFolderView::actions => $this->get_action_map($media_url, $plugin_cookies),
                PluginRowsFolderView::timer => $this->rows_list['complete'] ? null : Action_Factory::timer(self::ROWS_TIMER_MS),
                PluginRowsFolderView::sel_state => $sel_state,
            )
        );
    }

    /**
     * Adds the next page of the list to the rows view
     *
     * @param MediaURL $media_url
     * @param bool $first_page
     * @return int number of the added movies
     */
    protected function load_rows_page(MediaURL $media_url, $first_page)
    {
        $added = 0;
        foreach ($this->fetch_movies($media_url, $first_page) as $movie) {
            // a provider without paging returns the same movies again
            if (isset($this->rows_list['ids'][$movie->id])) continue;

            $this->rows_list['ids'][$movie->id] = true;
            $this->rows_list['movies'][] = $movie;
            $added++;
        }

        if ($added === 0) {
            $this->rows_list['complete'] = true;
        }

        hd_debug_print("Rows view: added $added movies, total: " . count($this->rows_list['movies']));
        return $added;
    }

    /**
     * @return array
     */
    protected function get_rows_pane()
    {
        $this->rows_last_viewed = null;
        $count = count($this->rows_list['movies']);
        $this->rows_shown = $count;
        $num_rows = (int)ceil($count / self::ROWS_COLS);
        $focus_row = (int)($this->rows_focus / self::ROWS_COLS);

        $template = Rows_Factory::regular_row_template(self::ROWS_PARAMS_ID, null, 'vod', null, null, self::ROW_HEIGHT, -1);
        $fav_ids = array_flip($this->plugin->get_channels_order(VOD_FAV_GROUP_ID));
        $list_ids = $this->get_list_ids();

        $rows = array(Rows_Factory::vgap_row(20));
        $with_placeholders = false;
        for ($row_ndx = 0; $row_ndx < $num_rows; $row_ndx++) {
            if (abs($row_ndx - $focus_row) <= self::ROWS_NEAR_FOCUS) {
                $rows[] = $this->make_rows_row($template, $row_ndx, $fav_ids, $list_ids);
            } else {
                $rows[] = array(
                    PluginRow::type => PLUGIN_ROW_TYPE_DUP,
                    PluginRow::id => self::ROW_ID_PREFIX . $row_ndx,
                    PluginRow::data => self::LOADING_ROW_ID,
                    PluginRow::options => PLUGIN_ROW_OPT_LOADING | PLUGIN_ROW_OPT_OPTIMIZE_INVISIBLE,
                );
                $with_placeholders = true;
            }
        }

        if ($count === 0) {
            $rows[] = Rows_Factory::gcomps_row('empty',
                array(GComps_Factory::label(GComp_Geom::place_top_left(1500, 60, 125, 0), null,
                    TR::t('tv_screen_not_found'), 1, '#808080FF', 36)),
                '', 1920, 230);
        }

        $initial_item_id = null;
        $initial_row_id = null;
        if ($this->rows_focus > 0 && $this->rows_focus < $count) {
            $initial_item_id = self::ITEM_ID_PREFIX . $this->rows_focus;
            $initial_row_id = self::ROW_ID_PREFIX . $focus_row;
        }

        $pane = Rows_Factory::pane(
            $rows,
            null,
            Rows_Factory::focus(GCOMP_FOCUS_DEFAULT_CUT_IMAGE, GCOMP_FOCUS_DEFAULT_RECT),
            null,
            false,
            true,
            -1,
            $initial_item_id,
            $initial_row_id,
            0.84,
            0.0,
            -0.8,
            50
        );
        $pane[PluginRowsPane::horizontal_focus_end_min_offset] = 0;

        if ($with_placeholders) {
            $items = array();
            for ($i = 0; $i < self::ROWS_COLS; $i++) {
                // the id is a suffix of the item id, the row id is prepended
                $items[] = Rows_Factory::add_regular_item(".$i", self::ROWS_LOADING_URL);
            }
            $loading_row = Rows_Factory::regular_row_from_template($template, self::LOADING_ROW_ID, $items);
            $loading_row[PluginRow::options] = PLUGIN_ROW_OPT_LOADING;
            $pane[PluginRowsPane::row_dup_map] = array(self::LOADING_ROW_ID => $loading_row);
        }

        Rows_Factory::pane_set_geometry(
            $pane,
            1920,
            1080,
            0,
            0,
            $count === 0 ? 0 : self::INFO_HEIGHT,
            0,
            self::INFO_WIDTH,
            self::INFO_HEIGHT - self::INFO_Y,
            self::INFO_X,
            self::INFO_Y,
            self::ART_WIDTH,
            self::ART_HEIGHT
        );

        $def_params = Rows_Factory::variable_params(200, self::ROW_HEIGHT, 0, 190, 285, 7, 10, self::INFO_TEXT_COLOR, 28);
        $sel_params = Rows_Factory::variable_params(200, self::ROW_HEIGHT, 0, 200, 300, 0, 10, self::INFO_TITLE_COLOR, 28);
        $params = Rows_Factory::item_params($def_params, $sel_params, null,
            self::ROWS_LOADING_URL, self::ROWS_FAILED_URL, 3, 3, Rows_Factory::margins(8, 4, 4, 6));
        Rows_Factory::set_item_params_template($pane, self::ROWS_PARAMS_ID, $params);

        return $pane;
    }

    /**
     * r25+: rows given as placeholders by get_rows_pane()
     *
     * @param MediaURL $media_url
     * @param array $row_ids
     * @return array
     */
    public function get_folder_rows(MediaURL $media_url, $row_ids)
    {
        hd_debug_print(null, true);

        $this->rows_last_viewed = null;
        $rows = array();
        if (!is_null($this->rows_list) && $this->rows_list['key'] === $media_url->get_raw_string()) {
            $num_rows = (int)ceil(count($this->rows_list['movies']) / self::ROWS_COLS);
            $template = Rows_Factory::regular_row_template(self::ROWS_PARAMS_ID, null, 'vod', null, null, self::ROW_HEIGHT, -1);
            $fav_ids = array_flip($this->plugin->get_channels_order(VOD_FAV_GROUP_ID));
            $list_ids = $this->get_list_ids();
            $prefix_len = strlen(self::ROW_ID_PREFIX);

            foreach ($row_ids as $row_id) {
                if (strncmp($row_id, self::ROW_ID_PREFIX, $prefix_len) !== 0) continue;

                $row_ndx = (int)substr($row_id, $prefix_len);
                if ($row_ndx >= 0 && $row_ndx < $num_rows) {
                    $rows[] = $this->make_rows_row($template, $row_ndx, $fav_ids, $list_ids);
                }
            }
        }

        return array(PluginFolderRows::rows => $rows);
    }

    /**
     * @param array $template from Rows_Factory::regular_row_template()
     * @param int $row_ndx
     * @param array $fav_ids favorite movie ids as keys
     * @param array $list_ids movie ids of the play list group as keys
     * @return array
     */
    protected function make_rows_row($template, $row_ndx, $fav_ids, $list_ids)
    {
        $movies = $this->rows_list['movies'];
        $from = $row_ndx * self::ROWS_COLS;
        $to = min($from + self::ROWS_COLS, count($movies));

        $items = array();
        for ($i = $from; $i < $to; $i++) {
            $movie = $movies[$i];
            $stickers = $this->get_rows_movie_stickers($movie, $fav_ids, $list_ids);
            $items[] = Rows_Factory::add_regular_item(self::ITEM_ID_PREFIX . $i, self::get_rows_poster($movie), null, $stickers);
        }

        return Rows_Factory::regular_row_from_template($template, self::ROW_ID_PREFIX . $row_ndx, $items);
    }

    /**
     * Never empty: an item of a row without an icon url string kills the shell (seen by the author of Melange)
     *
     * @param Short_Movie $movie
     * @return string
     */
    protected static function get_rows_poster($movie)
    {
        return is_string($movie->poster_url) && $movie->poster_url !== '' ? $movie->poster_url : self::ROWS_FAILED_URL;
    }

    /**
     * Stickers of the movie in a row: favorite, otherwise in the play list
     *
     * @param Short_Movie $movie
     * @param array $fav_ids favorite movie ids as keys
     * @param array $list_ids movie ids of the play list group as keys
     * @return array|null
     */
    protected function get_rows_movie_stickers($movie, $fav_ids, $list_ids)
    {
        $stickers = array();
        if (isset($fav_ids[$movie->id])) {
            $stickers = $this->get_rows_sticker('star.png');
        } else if (isset($list_ids[$movie->id])) {
            $stickers = $this->get_rows_sticker('play_small.png');
        }

        // the watched part at the bottom of the poster
        $progress = $this->get_movie_progress($movie->id);
        if (!is_null($progress)) {
            $y = self::POSTER_HEIGHT - self::POSTER_PROGRESS_HEIGHT;
            $stickers[] = Rows_Factory::add_regular_sticker_rect(self::PROGRESS_REST_COLOR,
                Rows_Factory::r(0, $y, self::POSTER_WIDTH, self::POSTER_PROGRESS_HEIGHT));
            $stickers[] = Rows_Factory::add_regular_sticker_rect(self::PROGRESS_DONE_COLOR,
                Rows_Factory::r(0, $y, max(4, (int)(self::POSTER_WIDTH * $progress['part'])), self::POSTER_PROGRESS_HEIGHT));
        }

        return empty($stickers) ? null : $stickers;
    }

    /**
     * The last viewing of the movie, the map is read once for the rows that are built together
     *
     * @param string $movie_id
     * @return array|null 'part' watched from 0 to 1, 'left' seconds; null - never viewed, watched to the end or no duration is known
     */
    protected function get_movie_progress($movie_id)
    {
        if (is_null($this->rows_last_viewed)) {
            $this->rows_last_viewed = $this->plugin->get_vod_last_viewed();
        }

        if (!isset($this->rows_last_viewed[$movie_id])) {
            return null;
        }

        $row = $this->rows_last_viewed[$movie_id];
        if ($row[COLUMN_WATCHED]) {
            return null;
        }

        $duration = (int)$row[COLUMN_DURATION];
        if ($duration <= 0) {
            return null;
        }

        $position = max(0, min((int)$row[COLUMN_POSITION], $duration));
        return array('part' => $position / $duration, 'left' => $duration - $position);
    }

    /**
     * Icon in the top right corner of a poster
     *
     * @param string $image
     * @return array
     */
    protected function get_rows_sticker($image)
    {
        $bg = RowsItemsParams::fav_sticker_bg_width;
        $icon = RowsItemsParams::fav_sticker_icon_width;
        return array(
            Rows_Factory::add_regular_sticker_rect(RowsItemsParams::fav_sticker_bg_color, Rows_Factory::r(190 - $bg, 0, $bg, $bg)),
            Rows_Factory::add_regular_sticker_image(get_image_path($image),
                Rows_Factory::r(190 - ($bg + $icon) / 2, ($bg - $icon) / 2, $icon, $icon)),
        );
    }

    /**
     * @param string $item_id
     * @return Short_Movie|null
     */
    protected function get_rows_movie($item_id)
    {
        $idx = self::get_rows_movie_index($item_id);
        if ($idx < 0 || is_null($this->rows_list) || !isset($this->rows_list['movies'][$idx])) {
            return null;
        }

        return $this->rows_list['movies'][$idx];
    }

    /**
     * @param string $item_id
     * @return int -1 not a movie (a placeholder)
     */
    protected static function get_rows_movie_index($item_id)
    {
        $prefix_len = strlen(self::ITEM_ID_PREFIX);
        if (strncmp($item_id, self::ITEM_ID_PREFIX, $prefix_len) !== 0) {
            return -1;
        }

        $idx = (string)substr($item_id, $prefix_len);
        return $idx !== '' && strspn($idx, '0123456789') === strlen($idx) ? (int)$idx : -1;
    }

    /**
     * Info and art of the focused movie
     *
     * @param object $user_input
     * @return array|null
     */
    protected function update_rows_info($user_input)
    {
        if (!isset($user_input->item_id, $user_input->folder_key)) {
            return null;
        }

        $movie = $this->get_rows_movie($user_input->item_id);
        if (is_null($movie)) {
            return null;
        }

        // the next page is loaded by the timer, the firmware does not run a post action of the info update
        $this->rows_focus = self::get_rows_movie_index($user_input->item_id);

        return $this->make_rows_info_action($user_input, $movie);
    }

    /**
     * @param object $user_input
     * @param Short_Movie $movie
     * @return array
     */
    protected function make_rows_info_action($user_input, $movie)
    {
        $bg_url = null;
        $nl_bg_url = null;
        $mask_url = null;
        if ($this->is_rows_art_shown()) {
            $art_url = empty($movie->big_poster_url) ? $movie->poster_url : $movie->big_poster_url;
            $bg_url = empty($art_url) ? self::ROWS_ART_LOADING_URL : $art_url;
            $nl_bg_url = self::ROWS_ART_LOADING_URL;
            $mask_url = $this->get_rows_art_mask();
        }

        return Action_Factory::update_rows_info(
            $user_input->folder_key,
            $user_input->item_id,
            $this->get_rows_info_defs($movie),
            $bg_url,
            $nl_bg_url,
            $mask_url,
            array(),
            null,
            true
        );
    }

    /**
     * @return string
     */
    protected function get_rows_art_mask()
    {
        return get_image_path(self::ROWS_ART_MASK);
    }

    /**
     * The compact view of the catalog has no art
     *
     * @return bool
     */
    protected function is_rows_art_shown()
    {
        return true;
    }

    /**
     * @return int
     */
    protected function get_rows_info_width()
    {
        return self::INFO_WIDTH;
    }

    /**
     * Top of the rows on the screen
     *
     * @return int
     */
    protected function get_rows_info_height()
    {
        return self::INFO_HEIGHT;
    }

    /**
     * Timer of the rows view while the list has more pages: loads the next page when the focus
     * is in the last rows and reloads the folder with it (the focus stays), otherwise waits again
     *
     * @param object $user_input
     * @return array|null
     */
    protected function on_rows_timer($user_input)
    {
        if (is_null($this->rows_list) || !$this->is_rows_view()) {
            return null;
        }

        $count = count($this->rows_list['movies']);
        $reload = $count > $this->rows_shown;
        if (!$reload && !$this->rows_list['complete']
            && $this->rows_focus >= $count - self::ROWS_LOAD_AHEAD * self::ROWS_COLS) {
            $reload = $this->load_rows_page(MediaURL::decode($this->rows_list['key']), false) > 0;
        }

        if ($reload) {
            $media_url_str = isset($user_input->parent_media_url) ? $user_input->parent_media_url : $this->rows_list['key'];
            return Action_Factory::invalidate_folders(array($media_url_str));
        }

        if ($this->rows_list['complete']) {
            return null;
        }

        return Action_Factory::change_behaviour($this->do_get_action_map(), self::ROWS_TIMER_MS);
    }

    /**
     * Title, original title, year | country | genres | age | length | rating and description
     *
     * @param Short_Movie $short_movie
     * @return array
     */
    protected function get_rows_info_defs($short_movie)
    {
        $title = $short_movie->name;
        $subtitle = '';
        $lines = array();
        $description = '';

        // the full info can be a provider request per movie, it is loaded only if enabled in the NewUI settings;
        // a movie that is already loaded (its screen was opened) is shown in full anyway.
        // M3U VOD has no more info than its movie list, the setting does not apply to it
        $provider = $this->plugin->vod->get_provider();
        if (!empty($provider) && !Default_Dune_Plugin::is_provider_m3u_vod($provider)
            && $this->plugin->get_bool_parameter(PARAM_VOD_NEWUI_FULL_INFO, false)) {
            $movie = $this->plugin->vod->get_loaded_movie($short_movie->id);
        } else {
            $movie = $this->plugin->vod->get_cached_movie($short_movie->id);
        }

        if (is_null($movie)) {
            // the list info only: the name and the lines of the known values
            $info = explode('|', (string)$short_movie->info);
            array_shift($info);
            $info = array_filter(array_map('trim', $info), 'strlen');
            if (!empty($info)) {
                $lines[] = implode(' | ', $info);
            }
        } else {
            $info = $movie->get_movie_info();
            $title = $info[PluginMovie::name];
            $subtitle = $info[PluginMovie::name_original];
            $description = $info[PluginMovie::description];

            $values = array();
            if ($info[PluginMovie::year] > 0) {
                $values[] = $info[PluginMovie::year];
            }
            $values[] = $info[PluginMovie::country];
            $values[] = $info[PluginMovie::genres_str];
            $values[] = $info[PluginMovie::rate_mpaa];
            $length = $info[PluginMovie::length_min];
            if ($length > 0) {
                $values[] = TR::load('vod_rows_length__2', (int)($length / 60), $length % 60);
            }
            $rating = $info[PluginMovie::rate_imdb];
            if (!is_numeric($rating) || (float)$rating <= 0) {
                $rating = $info[PluginMovie::rate_kinopoisk];
            }
            if (!is_numeric($rating) || (float)$rating > 0) {
                $values[] = $rating;
            }

            $values = array_filter(array_map('trim', $values), 'strlen');
            if (!empty($values)) {
                $lines[] = implode(' | ', $values);
            }
        }

        if (empty($title)) {
            $title = $short_movie->name;
        }

        $width = $this->get_rows_info_width();
        $height = $this->get_rows_info_height() - self::INFO_Y;
        $item_h = 40;
        $defs = array();

        $y = 20;
        $defs[] = GComps_Factory::label_v2(GComp_Geom::place_top_left($width, 56, 0, $y), null,
            $title, 1, self::INFO_TITLE_COLOR, 56, 'info_title',
            array('line_spacing' => 0, 'min_font_size' => 45, 'valign' => VALIGN_BOTTOM, 'fit' => GCOMP_TEXT_FIT_APPEND_ELLIPSIS));
        $y += 56 + 7;

        if (!empty($subtitle) && $subtitle !== $title) {
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left($width, $item_h + 6, 0, $y - 3), null,
                $subtitle, 1, self::INFO_SUBTITLE_COLOR, 36, 'info_subtitle',
                array('line_spacing' => 0, 'min_font_size' => 20));
            $y += $item_h + 15;
        } else {
            $y += 10;
        }

        foreach ($lines as $line) {
            // the font size stays: a long text takes two lines and ends with an ellipsis, the label cuts what the estimate missed
            foreach (Text_Width::wrap($line, self::INFO_TEXT_SIZE, $width, self::INFO_MAX_LINE_ROWS) as $i => $row) {
                if ($i > 0) {
                    $y += self::INFO_TEXT_SIZE + 5;
                }
                $defs[] = GComps_Factory::label(GComp_Geom::place_top_left($width, $item_h + 6, 0, $y - 3), null,
                    $row, 1, self::INFO_TEXT_COLOR, self::INFO_TEXT_SIZE, null,
                    array('fit' => GCOMP_TEXT_FIT_APPEND_ELLIPSIS));
            }
            $y += $item_h + 10;
        }

        $bottom_y = $height - PaneParams::fav_btn_height;
        $progress = $this->get_rows_info_progress($short_movie);
        if (!is_null($progress)) {
            $bottom_y -= self::INFO_PROGRESS_ROW_HEIGHT;
            $this->add_info_progress($defs, $progress, $bottom_y);
        }

        $description = trim((string)$description);
        if ($description !== '') {
            $y -= 10;
            // the room above the hints of the color keys and the watched part
            $desc_height = $bottom_y - 10;
            $max_lines = (int)(($desc_height - $y - self::INFO_TEXT_SIZE) / (self::INFO_TEXT_SIZE + 5));
            if ($max_lines > 0) {
                $defs[] = GComps_Factory::label(GComp_Geom::place_top_left($width, -1, 0, $y), null,
                    $description, $max_lines, self::INFO_TEXT_COLOR, self::INFO_TEXT_SIZE, 'info_desc',
                    array('line_spacing' => 5));
            }
        }

        $this->add_key_hints($defs, $this->get_rows_key_hints($short_movie), $height - PaneParams::fav_btn_height);

        return $defs;
    }

    /**
     * The watched part shown in the info
     *
     * @param Short_Movie $movie
     * @return array|null see get_movie_progress()
     */
    protected function get_rows_info_progress($movie)
    {
        // the map is read anew: the movie could be played since the rows were built
        $this->rows_last_viewed = null;
        return $this->get_movie_progress($movie->id);
    }

    /**
     * @param array $defs
     * @param array $progress see get_movie_progress()
     * @param int $y
     * @return void
     */
    protected function add_info_progress(&$defs, $progress, $y)
    {
        $bar_y = $y + (int)((self::INFO_PROGRESS_ROW_HEIGHT - self::INFO_PROGRESS_HEIGHT) / 2);
        $defs[] = GComps_Factory::get_rect_def(
            GComp_Geom::place_top_left(self::INFO_PROGRESS_WIDTH, self::INFO_PROGRESS_HEIGHT, 0, $bar_y), null, self::PROGRESS_REST_COLOR);
        $defs[] = GComps_Factory::get_rect_def(
            GComp_Geom::place_top_left(max(4, (int)(self::INFO_PROGRESS_WIDTH * $progress['part'])), self::INFO_PROGRESS_HEIGHT, 0, $bar_y),
            null, self::PROGRESS_DONE_COLOR);

        if ($progress['left'] > 0) {
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left(600, self::INFO_PROGRESS_ROW_HEIGHT, self::INFO_PROGRESS_WIDTH + 40, $y), null,
                TR::load('vod_rows_left__1', format_duration_seconds($progress['left'])), 1,
                self::INFO_TEXT_COLOR, PaneParams::fav_btn_font_size);
        }
    }

    /**
     * Color keys of the movie: key icon, translated caption
     *
     * @param Short_Movie $movie
     * @return array
     */
    protected function get_rows_key_hints($movie)
    {
        $hints = array();
        if ($this->is_filter_supported()) {
            $hints[] = array(PaneParams::fav_button_green, TR::load(VOD_FILTER_GROUP_CAPTION));
        }

        $hints[] = array(PaneParams::fav_button_yellow, TR::load('search'));
        $hints[] = $this->get_favorite_key_hint($movie);

        return $hints;
    }

    /**
     * @param Short_Movie $movie
     * @return array
     */
    protected function get_favorite_key_hint($movie)
    {
        $in_favorites = $this->plugin->is_channel_in_order(VOD_FAV_GROUP_ID, $movie->id);
        return array(PaneParams::fav_button_blue, TR::load($in_favorites ? 'delete_from_favorite' : 'add_to_favorite'));
    }

    /**
     * Key icons with their captions in one line, as in the info of the TV rows screen
     *
     * @param array $defs
     * @param array $hints from get_rows_key_hints()
     * @param int $y
     * @return void
     */
    protected function add_key_hints(&$defs, $hints, $y)
    {
        $x = 0;
        foreach ($hints as $hint) {
            $defs[] = GComps_Factory::get_image_def(
                GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $x, $y), null, $hint[0]);
            $x += PaneParams::fav_btn_width + 1;

            $text_width = Text_Width::get($hint[1], PaneParams::fav_btn_font_size);
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left($text_width, -1, $x, $y - 4), null,
                $hint[1], 1, PaneParams::fav_btn_font_color, PaneParams::fav_btn_font_size);
            $x += $text_width + 30;
        }
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
