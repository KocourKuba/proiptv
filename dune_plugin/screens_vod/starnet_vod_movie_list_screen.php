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

/**
 * Movies of a VOD category, search or filter.
 *
 * r25+ has one more view after the regular ones: rows of posters under the info of the
 * focused movie drawn over its art, as the firmware's own movie lists. The views are
 * switched by the red key there, the firmware can not switch to a rows view by itself.
 * The rows view keeps the loaded movies of the list: the next page is loaded when the
 * focus reaches the last rows and the folder is reloaded with it, the rows out of
 * sight are given as placeholders and filled by get_folder_rows() (r25 only).
 */
class Starnet_Vod_Movie_List_Screen extends Abstract_Regular_Screen
{
    const ID = 'vod_movies_list';

    const ACTION_CHANGE_VIEW = 'change_view';

    // marker of the rows view in get_folder_views()
    const ROWS_VIEW = 'vod_rows_view';

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
    const INFO_WIDTH = 1056;
    const INFO_HEIGHT = 456;
    const ART_WIDTH = 1024;
    const ART_HEIGHT = 576;
    const ROW_HEIGHT = 302;

    const INFO_TITLE_COLOR = '#FFFFE0FF';
    const INFO_SUBTITLE_COLOR = '#808080FF';
    const INFO_TEXT_COLOR = '#AFAFA0FF';
    const INFO_CAST_COLOR = '#FFFF40FF';
    const INFO_TEXT_SIZE = 30;

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

        if ($this->is_rows_view_enabled()) {
            $actions[GUI_EVENT_KEY_A_RED] = User_Input_Handler_Registry::create_action($this, self::ACTION_CHANGE_VIEW, TR::t('vod_change_view'));
        }

        $actions[GUI_EVENT_KEY_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);
        $add_to_favorite = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
        $actions[GUI_EVENT_KEY_D_BLUE] = $add_to_favorite;
        $actions[GUI_EVENT_KEY_DUNE] = $add_to_favorite;
        $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_SHOW_SEARCH_DLG, TR::t('search'));

        $provider = $this->plugin->vod->get_provider();
        if (!empty($provider) && Default_Dune_Plugin::is_provider_m3u_vod($provider)) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_TO_LIST, TR::t('add_to_list'));
        }
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        return $actions;
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

        if ($control_id === GUI_EVENT_TIMER) {
            return $this->on_rows_timer($user_input);
        }

        if ($control_id === self::ACTION_CHANGE_VIEW) {
            $this->switch_to_next_folder_view();
            $this->rows_list = null;
            return Action_Factory::invalidate_folders(array($user_input->parent_media_url));
        }

        $movie = $this->get_selected_movie($user_input);
        if (is_null($movie)) {
            hd_debug_print('no selected movie', true);
            return null;
        }

        $movie_id = $movie->id;

        switch ($control_id) {
            case ACTION_OPEN_FOLDER:
                return Action_Factory::open_folder(
                    Starnet_Vod_Movie_Screen::make_vod_media_url_str($movie->id, $movie->name, $movie->poster_url, $movie->info),
                    $movie->name
                );

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
                $cnt = $this->plugin->get_order_count(VOD_LIST_GROUP_ID);
                if ($cnt) {
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        ACTION_JUMP_TO_CHANNEL_IN_GROUP, TR::t('jump_to_list_group'));
                }
                if ($this->is_rows_view_enabled()) {
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        self::ACTION_CHANGE_VIEW, TR::t('vod_change_view'));
                }
                return Action_Factory::show_popup_menu($menu_items);
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
     * @inheritDoc
     */
    public function get_folder_views()
    {
        $views = parent::get_folder_views();
        if ($this->is_rows_view_enabled()) {
            $views[] = array(self::ROWS_VIEW => true);
        }

        return $views;
    }

    /**
     * The rows view is on r25+ and only if it is enabled in the VOD settings of the playlist
     *
     * @return bool
     */
    protected function is_rows_view_enabled()
    {
        return is_r25_or_higher()
            && SwitchOnOff::to_bool($this->plugin->get_vod_setting(PARAM_VOD_NEWUI_VIEW, SwitchOnOff::off));
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
        $views = $this->get_folder_views();
        $idx = $this->get_folder_view_index();
        return isset($views[$idx][self::ROWS_VIEW]);
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

        $view = parent::get_folder_view($media_url, $plugin_cookies);
        if ($this->is_rows_view_enabled()) {
            // the views are switched by the red key, the firmware can not switch to the rows view
            $view[PluginFolderView::multiple_views_supported] = 0;
        }

        return $view;
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
            $stickers = null;
            if (isset($fav_ids[$movie->id])) {
                $stickers = $this->get_rows_sticker('star.png');
            } else if (isset($list_ids[$movie->id])) {
                $stickers = $this->get_rows_sticker('play_small.png');
            }
            $items[] = Rows_Factory::add_regular_item(self::ITEM_ID_PREFIX . $i, $movie->poster_url, null, $stickers);
        }

        return Rows_Factory::regular_row_from_template($template, self::ROW_ID_PREFIX . $row_ndx, $items);
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

        $art_url = empty($movie->big_poster_url) ? $movie->poster_url : $movie->big_poster_url;

        return Action_Factory::update_rows_info(
            $user_input->folder_key,
            $user_input->item_id,
            $this->get_rows_info_defs($movie),
            empty($art_url) ? self::ROWS_ART_LOADING_URL : $art_url,
            self::ROWS_ART_LOADING_URL,
            get_image_path(self::ROWS_ART_MASK),
            array(),
            null,
            true
        );
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
     * Title, original title, year | country | genres | age | length | rating, cast and description
     *
     * @param Short_Movie $short_movie
     * @return array
     */
    protected function get_rows_info_defs($short_movie)
    {
        $title = $short_movie->name;
        $subtitle = '';
        $lines = array();
        $cast = '';
        $description = '';

        // the full info can be a provider request per movie, it is loaded only if enabled in the VOD settings;
        // a movie that is already loaded (its screen was opened) is shown in full anyway.
        // M3U VOD has no more info than its movie list, the setting is not shown for it
        $provider = $this->plugin->vod->get_provider();
        if (!empty($provider) && !Default_Dune_Plugin::is_provider_m3u_vod($provider)
            && SwitchOnOff::to_bool($this->plugin->get_vod_setting(PARAM_VOD_NEWUI_FULL_INFO, SwitchOnOff::off))) {
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
            $cast = $info[PluginMovie::actors_str];
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

        $width = self::INFO_WIDTH;
        $height = self::INFO_HEIGHT - self::INFO_Y;
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

        if (!empty($cast)) {
            $lines[] = array($cast, self::INFO_CAST_COLOR);
        }

        foreach ($lines as $line) {
            $color = self::INFO_TEXT_COLOR;
            if (is_array($line)) {
                $color = $line[1];
                $line = $line[0];
            }
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left($width, $item_h + 6, 0, $y - 3), null,
                $line, 1, $color, self::INFO_TEXT_SIZE, null,
                array('min_font_size' => (int)(self::INFO_TEXT_SIZE / 2)));
            $y += $item_h + 10;
        }

        $description = trim((string)$description);
        if ($description !== '') {
            $y -= 10;
            $max_lines = (int)(($height - $y - self::INFO_TEXT_SIZE) / (self::INFO_TEXT_SIZE + 5));
            if ($max_lines > 0) {
                $defs[] = GComps_Factory::label(GComp_Geom::place_top_left($width, -1, 0, $y), null,
                    $description, $max_lines, self::INFO_TEXT_COLOR, self::INFO_TEXT_SIZE, 'info_desc',
                    array('line_spacing' => 5));
            }
        }

        return $defs;
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
