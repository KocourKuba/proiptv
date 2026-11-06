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

require_once 'starnet_vod_movie_list_screen.php';

/**
 * The media library as a catalog (r25+, the rows view is on in the NewUI settings of the plugin), it replaces the
 * category list on the same media url, see Default_Dune_Plugin::get_screen_by_url(). The list of the row titles on the
 * left is made by the firmware, every row is a category or a local group (favorites, history, play list) with
 * its first movies and the "show all" tile. Under the rows there is the info of the focused movie, as in the movie list.
 *
 * Only the first row is loaded with the screen (a row loaded later under the focus on the titles covers the rows
 * below it), the others are stubs that the firmware asks for by get_folder_rows() one by one. Traps are from the
 * catalog of Melange by Aidar Garikhanov.
 */
class Starnet_Vod_Catalog_Screen extends Starnet_Vod_Movie_List_Screen
{
    const ID = 'vod_catalog';

    const ACTION_OPEN_GROUP = 'catalog_open_group';
    const ACTION_GREEN_KEY = 'catalog_green_key';
    const ACTION_SHOW_ALL = 'catalog_show_all';
    const ACTION_CHANGE_VIEW = 'catalog_change_view';

    const ROW_TILES = 10;
    const ROW_STUBS = 9;
    const ROW_INACTIVE_HEIGHT = 300;
    const TITLE_ROW_HEIGHT = 55;
    const ITEMS_ROW_PREFIX = 'r';
    const TITLE_ROW_PREFIX = 't';
    const GROUP_PREFIX = 'g';
    const PARAMS_ID = 'vod_catalog';

    // tails of the item ids after <row id>:
    const ITEM_MORE = 'more';
    const ITEM_NOTE = 'note';
    const ITEM_STUB = 'stub';

    // a local group can ask the provider for every movie it has not loaded yet: one call takes this many seconds,
    // the timer builds the view again and the row goes on, the loaded movies stay in the movie cache
    const LOCAL_GROUP_TIME_LIMIT = 3.0;
    const LOCAL_GROUP_TIMER_MS = 1000;
    const LOCAL_GROUP_MAX_TICKS = 30;

    // geometry of the info and the art, as the firmware's own catalog but the width of the info
    const INFO_HEIGHT = 600;
    const ART_WIDTH = 1280;
    const ART_HEIGHT = 720;
    // compact view: no art, two rows of posters stay on the screen under the info
    const COMPACT_INFO_WIDTH = 1720;
    const COMPACT_INFO_HEIGHT = 390;
    const ROWS_ART_MASK_URL = 'plugin_file://%shell_ext%/icons/mask.png';
    const MORE_ICON_URL = 'plugin_file://%shell_ext%/icons/more.png';

    /**
     * Rows of the shown media library: row id => id, title, list key or local group id, media url and caption of "show all"
     *
     * @var array
     */
    protected $catalog_rows = array();

    /**
     * Loaded movies: row id => Short_Movie[]
     *
     * @var array
     */
    protected $catalog_movies = array();

    /**
     * Media url of the media library of $catalog_movies
     *
     * @var string|null
     */
    protected $catalog_key;

    /**
     * A local group was cut by the time limit
     *
     * @var bool
     */
    protected $local_group_cut = false;

    /**
     * Ticks of the timer since the catalog was opened
     *
     * @var int
     */
    protected $local_group_ticks = 0;

    /**
     * The next view is built by the timer
     *
     * @var bool
     */
    protected $is_timer_reload = false;

    /**
     * Local group of the row of the movie whose info is built
     *
     * @var string|null
     */
    protected $info_row_group;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return array
     */
    protected function do_get_action_map()
    {
        hd_debug_print(null, true);

        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER);
        $actions[GUI_EVENT_KEY_PLAY] = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);
        $actions[GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, self::ACTION_OPEN_GROUP, TR::t('search'),
            array(LIST_IDX => VOD_SEARCH_GROUP_ID));

        // adds the movie to the favorites or removes it, as on the TV rows screen: in the favorites row it always removes
        $add_to_favorite = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV);
        $actions[GUI_EVENT_KEY_D_BLUE] = $add_to_favorite;
        $actions[GUI_EVENT_KEY_DUNE] = $add_to_favorite;

        $actions[GUI_EVENT_KEY_A_RED] = User_Input_Handler_Registry::create_action($this, self::ACTION_CHANGE_VIEW);

        // the key does not depend on the row in the action map, the handler looks at the selected row
        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, self::ACTION_GREEN_KEY);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $control_id = $user_input->control_id;

        switch ($control_id) {
            case GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE:
                return $this->update_rows_info($user_input);

            case GUI_EVENT_TIMER:
                return $this->on_local_group_timer($user_input);

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($user_input);

            case self::ACTION_CHANGE_VIEW:
                $this->plugin->toggle_parameter(PARAM_VOD_CATALOG_COMPACT, false);
                return Action_Factory::invalidate_folders(array($user_input->parent_media_url));

            case self::ACTION_GREEN_KEY:
                // on a movie of the history row: delete it, as on the history screen. Otherwise the filter
                if (isset($user_input->selected_item_id) && $this->get_selected_row_group($user_input) === VOD_HISTORY_GROUP_ID) {
                    return $this->delete_selected_movie($user_input);
                }

                return $this->is_filter_supported() ? self::make_open_filter_action() : null;

            case ACTION_ITEM_DELETE:
                return $this->delete_selected_movie($user_input);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $this->plugin->clear_all_vod_history();
                return Action_Factory::invalidate_folders(array(
                    $user_input->parent_media_url,
                    Default_Dune_Plugin::get_group_media_url_str(VOD_HISTORY_GROUP_ID)
                ));

            case ACTION_OPEN_FOLDER:
                $action = $this->open_special_item($user_input);
                if ($action !== false) {
                    return $action;
                }
                break;

            case self::ACTION_OPEN_GROUP:
                return $this->open_group(safe_get_value($user_input, LIST_IDX));

            case self::ACTION_SHOW_ALL:
                return $this->open_row(safe_get_value($user_input, LIST_IDX));

            case Starnet_Vod_Category_List_Screen::ACTION_VOD_PLAYLIST_SELECTED:
                $provider = $this->plugin->vod->get_provider();
                if (is_null($provider) || !isset($user_input->{LIST_IDX})
                    || $user_input->{LIST_IDX} === $provider->GetPlaylistVodId()) {
                    return null;
                }

                hd_debug_print("select vod playlist: " . $user_input->{LIST_IDX});
                $provider->SetProviderParameter(PARAM_PLAYLIST_VOD_ID, $user_input->{LIST_IDX});
                return $this->reload_catalog($user_input);

            case Starnet_Vod_Category_List_Screen::ACTION_VOD_CATEGORY_MODE_SELECTED:
                if (!isset($user_input->{LIST_IDX})
                    || $user_input->{LIST_IDX} === $this->plugin->vod->get_category_mode()) {
                    return null;
                }

                hd_debug_print("select vod category mode: " . $user_input->{LIST_IDX});
                $this->plugin->vod->set_category_mode($user_input->{LIST_IDX});
                $this->catalog_key = null;
                return Action_Factory::invalidate_folders(array($user_input->parent_media_url));

            case ACTION_RELOAD:
                return $this->reload_catalog($user_input);
        }

        // actions with the selected movie, it is found by get_rows_movie()
        return parent::handle_user_input($user_input, $plugin_cookies);
    }

    /**
     * Local group of the selected row, on a tile or on the row titles on the left
     *
     * @param object $user_input
     * @return string|null null - a category
     */
    protected function get_selected_row_group($user_input)
    {
        if (isset($user_input->selected_item_id)) {
            list($row_id,) = self::parse_item_id($user_input->selected_item_id);
        } else if (isset($user_input->selected_row_id)) {
            // the items row or the title row of the group
            $row_id = (string)substr($user_input->selected_row_id, strlen(self::ITEMS_ROW_PREFIX));
        } else {
            return null;
        }

        $this->ensure_catalog_rows();
        return isset($this->catalog_rows[$row_id]) ? $this->catalog_rows[$row_id]['group'] : null;
    }

    /**
     * The history row: deletes the movie, on the row titles asks to clear the history. The favorites row: deletes the movie
     *
     * @param object $user_input
     * @return array|null
     */
    protected function delete_selected_movie($user_input)
    {
        $group = $this->get_selected_row_group($user_input);
        if (!isset($user_input->selected_item_id)) {
            return $group === VOD_HISTORY_GROUP_ID
                ? Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'), $this, ACTION_CONFIRM_CLEAR_DLG_APPLY)
                : null;
        }

        $movie = $this->get_rows_movie($user_input->selected_item_id);
        if (is_null($movie)) {
            return null;
        }

        if ($group === VOD_HISTORY_GROUP_ID) {
            hd_debug_print("Delete movie from history: $movie->id");
            $this->plugin->remove_vod_history($movie->id);
        } else if ($group === VOD_FAV_GROUP_ID) {
            $this->plugin->change_vod_favorites(PLUGIN_FAVORITES_OP_REMOVE, $movie->id);
        } else {
            return null;
        }

        return Action_Factory::invalidate_folders(array(
            $user_input->parent_media_url,
            Default_Dune_Plugin::get_group_media_url_str($group)
        ));
    }

    /**
     * A local group cut by the time limit goes on: the view is built again, the focus stays. Without it the timer
     * waits for the local groups the firmware has not asked for yet, they can be cut too
     *
     * @param object $user_input
     * @return array|null
     */
    protected function on_local_group_timer($user_input)
    {
        if (!$this->is_local_group_timer_needed()) {
            return null;
        }

        $this->local_group_ticks++;
        if ($this->local_group_cut) {
            $this->is_timer_reload = true;
            return Action_Factory::invalidate_folders(array($user_input->parent_media_url));
        }

        return Action_Factory::change_behaviour($this->do_get_action_map(), self::LOCAL_GROUP_TIMER_MS);
    }

    /**
     * @return bool
     */
    protected function is_local_group_timer_needed()
    {
        if ($this->local_group_ticks >= self::LOCAL_GROUP_MAX_TICKS) {
            return false;
        }

        if ($this->local_group_cut) {
            return true;
        }

        foreach ($this->catalog_rows as $row_id => $row) {
            if (!is_null($row['group']) && !isset($this->catalog_movies[$row_id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * ENTER on a tile that is not a movie
     *
     * @param object $user_input
     * @return array|null|false false - it is a movie
     */
    protected function open_special_item($user_input)
    {
        // the focus is on the row titles or on a row without items
        if (!isset($user_input->selected_item_id)) {
            hd_debug_print('ENTER without a selected item, selected_header: ' . safe_get_value($user_input, 'selected_header'));
            return null;
        }

        list($row_id, $tail) = self::parse_item_id($user_input->selected_item_id);
        if ($tail === self::ITEM_MORE) {
            return $this->open_row($row_id);
        }

        if ($tail === self::ITEM_NOTE) {
            // the row is not loaded: the next view asks for it again
            return Action_Factory::invalidate_folders(array($user_input->parent_media_url));
        }

        return strncmp($tail, self::ITEM_STUB, strlen(self::ITEM_STUB)) === 0 ? null : false;
    }

    /**
     * "Show all" of the row: its tile and ENTER on its title on the left
     *
     * @param string|null $row_id
     * @return array|null
     */
    protected function open_row($row_id)
    {
        $this->ensure_catalog_rows();
        if (!isset($this->catalog_rows[$row_id])) {
            return null;
        }

        $row = $this->catalog_rows[$row_id];
        return Action_Factory::open_folder($row['url'], $row['title']);
    }

    /**
     * @param string|null $group_id
     * @return array|null
     */
    protected function open_group($group_id)
    {
        if ($group_id === VOD_SEARCH_GROUP_ID) {
            return Action_Factory::open_folder(Default_Dune_Plugin::get_group_media_url_str($group_id), TR::t(VOD_SEARCH_GROUP_CAPTION));
        }

        if ($group_id === VOD_FILTER_GROUP_ID) {
            return Action_Factory::open_folder(Default_Dune_Plugin::get_group_media_url_str($group_id), TR::t(VOD_FILTER_GROUP_CAPTION));
        }

        return null;
    }

    /**
     * VOD of the playlist is loaded again, it creates the VOD screens anew
     *
     * @param object $user_input
     * @return array
     */
    protected function reload_catalog($user_input)
    {
        hd_debug_print('reload catalog');
        $playlist_id = safe_get_value(MediaURL::decode($user_input->parent_media_url), PARAM_PLAYLIST_ID);
        if (empty($playlist_id) || $playlist_id === $this->plugin->get_active_playlist_id()) {
            $enabled = $this->plugin->init_vod_context(true);
        } else {
            $context = $this->plugin->get_vod_context($playlist_id, true);
            $enabled = !is_null($context) && $context->enabled;
        }

        if (!$enabled) {
            return Action_Factory::show_title_dialog(TR::t('err_load_vod'), TR::t('err_vod_not_available'));
        }

        return Action_Factory::invalidate_folders(array($user_input->parent_media_url));
    }

    /**
     * @param object $user_input
     * @return array
     */
    protected function create_popup_menu($user_input)
    {
        $menu_items = array();
        $row_group = $this->get_selected_row_group($user_input);
        $movie = isset($user_input->selected_item_id) ? $this->get_rows_movie($user_input->selected_item_id) : null;
        if (!is_null($movie)) {
            if ($row_group === VOD_HISTORY_GROUP_ID) {
                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_ITEM_DELETE, TR::t('delete'), 'remove.png');
            }

            $is_in_favorites = $this->plugin->is_channel_in_order(VOD_FAV_GROUP_ID, $movie->id);
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_ADD_FAV,
                $is_in_favorites ? TR::t('delete_from_favorite') : TR::t('add_to_favorite'), RowsItemsParams::fav_sticker_icon_url);
            if ($this->is_list_supported()) {
                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_ADD_TO_LIST, TR::t('add_to_list'));
            }
            $menu_items[] = Control_Factory::menu_separator();
        } else if (!isset($user_input->selected_item_id) && $row_group === VOD_HISTORY_GROUP_ID) {
            // on the row titles on the left
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_ITEM_DELETE, TR::t('clear_history'), 'brush.png');
            $menu_items[] = Control_Factory::menu_separator();
        }

        Starnet_Vod_Category_List_Screen::add_source_menu_items($menu_items, $this->plugin, $this);

        $groups = $this->plugin->vod->get_special_groups();
        $open_groups = array(
            VOD_SEARCH_GROUP_ID => array(VOD_SEARCH_GROUP_CAPTION, 'search.png'),
            VOD_FILTER_GROUP_ID => array(VOD_FILTER_GROUP_CAPTION, 'filter.png'),
        );
        foreach ($open_groups as $group_id => $params) {
            $group = $groups->get($group_id);
            if (empty($group) || $group[ACTION_DISABLED]) continue;

            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, self::ACTION_OPEN_GROUP, TR::t($params[0]), $params[1],
                array(LIST_IDX => $group_id));
        }

        $menu_items[] = Control_Factory::menu_separator();

        $title = TR::t('playlist_name_msg__1', TR::t(VOD_GROUP_CAPTION));
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_RELOAD, $title, 'refresh.png');

        return Action_Factory::show_popup_menu($menu_items);
    }

    ///////////////////////////////////////////////////////////////////////
    // Rows and their data

    /**
     * @inheritDoc
     */
    public function get_folder_view_v2(MediaURL $media_url, $sel_state, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $this->rows_last_viewed = null;
        $key = $media_url->get_raw_string();
        if ($this->catalog_key !== $key) {
            $this->catalog_key = $key;
            $this->catalog_movies = array();
        }

        if (!$this->is_timer_reload) {
            $this->local_group_ticks = 0;
        }
        $this->is_timer_reload = false;
        $this->local_group_cut = false;

        $this->build_catalog_rows();

        // the local groups change while the catalog is under another screen, the categories are kept until a reload
        foreach ($this->catalog_rows as $row_id => $row) {
            if (!is_null($row['group'])) {
                unset($this->catalog_movies[$row_id]);
            }
        }

        $fav_ids = array_flip($this->plugin->get_channels_order(VOD_FAV_GROUP_ID));
        $list_ids = $this->get_list_ids();

        $rows = array(Rows_Factory::vgap_row(10, 10));
        $is_first = true;
        foreach ($this->catalog_rows as $row_id => $row) {
            $rows[] = Rows_Factory::title_row(self::TITLE_ROW_PREFIX . $row_id, $row['title'], self::GROUP_PREFIX . $row_id,
                null, null, TitleRowsParams::width, self::TITLE_ROW_HEIGHT, 30, 100, 9);

            if ($is_first || isset($this->catalog_movies[$row_id])) {
                $rows[] = $this->make_catalog_row($row_id, $fav_ids, $list_ids);
            } else {
                $rows[] = $this->make_stub_row($row_id);
            }
            $is_first = false;
        }

        if ($is_first) {
            $rows[] = Rows_Factory::gcomps_row('empty',
                array(GComps_Factory::label(GComp_Geom::place_top_left(1500, 60, 125, 0), null,
                    TR::t('tv_screen_not_found'), 1, '#808080FF', 36)),
                '', 1920, 230);
        }
        $rows[] = Rows_Factory::vgap_row(80, 80);

        return array(
            PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_ROWS,
            PluginFolderView::multiple_views_supported => false,
            PluginFolderView::folder_type => null,
            PluginFolderView::archive => null,
            PluginFolderView::data => array(
                PluginRowsFolderView::pane => $this->get_catalog_pane($rows, $is_first),
                PluginRowsFolderView::actions => $this->get_action_map($media_url, $plugin_cookies),
                PluginRowsFolderView::timer => $this->is_local_group_timer_needed() ? Action_Factory::timer(self::LOCAL_GROUP_TIMER_MS) : null,
                PluginRowsFolderView::sel_state => $sel_state,
            )
        );
    }

    /**
     * One row a call, the firmware asks for the others next. Always a row with the asked id: after a reply
     * without rows the firmware asks again at once (every ~1 ms, seen by the author of Melange)
     *
     * @inheritDoc
     */
    public function get_folder_rows(MediaURL $media_url, $row_ids)
    {
        hd_debug_print(null, true);

        $this->rows_last_viewed = null;
        $id = isset($row_ids[0]) ? (string)$row_ids[0] : '';
        $this->ensure_catalog_rows();

        $row_id = (string)substr($id, strlen(self::ITEMS_ROW_PREFIX));
        if (isset($this->catalog_rows[$row_id])) {
            $row = $this->make_catalog_row($row_id,
                array_flip($this->plugin->get_channels_order(VOD_FAV_GROUP_ID)), $this->get_list_ids());
        } else {
            hd_debug_print("Unknown row: $id");
            $row = $this->make_row_from_items($id, '', self::GROUP_PREFIX . $id,
                array(Rows_Factory::add_regular_item("$id:" . self::ITEM_NOTE, self::ROWS_FAILED_URL, TR::load('vod_catalog_row_failed'))));
        }

        return array(PluginFolderRows::rows => array($row));
    }

    /**
     * Rows of the media library: the local groups and the categories, the sub categories instead of their parent
     *
     * @return void
     */
    protected function build_catalog_rows()
    {
        $this->catalog_rows = array();

        foreach ($this->plugin->vod->get_special_groups() as $group) {
            if (empty($group) || $group[ACTION_DISABLED]) continue;

            $group_id = $group[COLUMN_GROUP_ID];
            if (!$this->is_local_group_shown($group_id)) continue;

            $this->add_catalog_row($group_id, TR::load($group[COLUMN_TITLE]), null, $group_id,
                Default_Dune_Plugin::get_group_media_url_str($group_id));
        }

        if (!$this->plugin->vod->fetchVodCategories()) {
            hd_debug_print('Error: Fetch categories');
            return;
        }

        $category_index = $this->plugin->vod->getCategoryIndex();
        foreach ($category_index as $category) {
            $sub_categories = $category->get_sub_categories();
            foreach (is_null($sub_categories) ? array($category) : $sub_categories as $leaf) {
                $category_id = $leaf->get_id();
                // these are not lists of movies
                if ($category_id === Vod_Category::FLAG_SEARCH || $category_id === Vod_Category::FLAG_FILTER) continue;

                $url = Starnet_Vod_Category_List_Screen::make_movies_media_url_str($leaf);
                $this->add_catalog_row($url, $leaf->get_caption(), self::get_list_key(MediaURL::decode($url)), null, $url);
            }
        }

        hd_debug_print('Catalog rows: ' . count($this->catalog_rows));
    }

    /**
     * @param string $key unique in the catalog, the row id does not depend on the place of the row
     * @param string $title
     * @param string|null $list_key
     * @param string|null $group local group id
     * @param string $url media url of "show all"
     * @return void
     */
    protected function add_catalog_row($key, $title, $list_key, $group, $url)
    {
        $this->catalog_rows[substr(md5($key), 0, 8)] = array('title' => $title, 'list_key' => $list_key, 'group' => $group, 'url' => $url);
    }

    /**
     * @param string $group_id
     * @return bool
     */
    protected function is_local_group_shown($group_id)
    {
        switch ($group_id) {
            case VOD_FAV_GROUP_ID:
                return $this->plugin->get_order_count(VOD_FAV_GROUP_ID) > 0;

            case VOD_HISTORY_GROUP_ID:
                return $this->plugin->get_all_vod_history_count() !== 0;

            case VOD_LIST_GROUP_ID:
                $provider = $this->plugin->vod->get_provider();
                return !empty($provider) && Default_Dune_Plugin::is_provider_m3u_vod($provider)
                    && $this->plugin->get_order_count(VOD_LIST_GROUP_ID) > 0;
        }

        // the search and the filter are not rows, they are in the popup menu
        return false;
    }

    /**
     * A fresh php process has no rows, also a screen can be asked without its view
     *
     * @return void
     */
    protected function ensure_catalog_rows()
    {
        if (empty($this->catalog_rows)) {
            $this->build_catalog_rows();
        }
    }

    /**
     * Movies of the row, loaded if they are not yet. An empty result is not kept: it can be a failure of the request
     *
     * @param string $row_id
     * @return Short_Movie[]
     */
    protected function get_row_movies($row_id)
    {
        if (isset($this->catalog_movies[$row_id])) {
            return $this->catalog_movies[$row_id];
        }

        if (!isset($this->catalog_rows[$row_id])) {
            return array();
        }

        $row = $this->catalog_rows[$row_id];
        if (is_null($row['group'])) {
            // the first page always, a list opened by "show all" could have moved the page of this list
            $this->plugin->vod->reset_page_index($row['list_key']);
            $movies = array_slice($this->plugin->vod->getMovieList($row['list_key']), 0, self::ROW_TILES);
        } else {
            $movies = $this->load_local_group_movies($row_id, $row['group']);
        }

        if (!empty($movies)) {
            $this->catalog_movies[$row_id] = $movies;
        }

        return $movies;
    }

    /**
     * Favorites, history or the play list, the first ROW_TILES of them. The movies not loaded yet can be requests
     * to the provider, the screen and a key of the user wait for them: over the time limit the row goes on by the timer
     *
     * @param string $row_id
     * @param string $group_id
     * @return Short_Movie[]
     */
    protected function load_local_group_movies($row_id, $group_id)
    {
        $ids = array();
        if ($group_id === VOD_HISTORY_GROUP_ID) {
            foreach ($this->plugin->get_all_vod_history() as $movie_info) {
                if (!empty($movie_info)) {
                    $ids[] = $movie_info[COLUMN_MOVIE_ID];
                }
            }
        } else {
            $ids = $this->plugin->get_channels_order($group_id);
        }

        $ids = array_unique($ids);
        $this->catalog_rows[$row_id]['total'] = count($ids);

        $start = microtime(true);
        $movies = array();
        foreach ($ids as $movie_id) {
            if (count($movies) >= self::ROW_TILES) break;

            if (microtime(true) - $start > self::LOCAL_GROUP_TIME_LIMIT) {
                hd_debug_print("Local group $group_id is cut by the time limit: " . count($movies));
                $this->local_group_cut = true;
                break;
            }

            $movie = $this->plugin->vod->get_loaded_movie($movie_id);
            if (is_null($movie)) continue;

            $info = $movie->get_movie_info();
            $movies[] = new Short_Movie($movie_id, $info[PluginMovie::name], $info[PluginMovie::poster_url]);
        }

        return $movies;
    }

    /**
     * @param string $row_id
     * @param array $fav_ids favorite movie ids as keys
     * @param array $list_ids movie ids of the play list group as keys
     * @return array
     */
    protected function make_catalog_row($row_id, $fav_ids, $list_ids)
    {
        // all movies of the favorites row are favorites, no need to mark them
        if ($this->catalog_rows[$row_id]['group'] === VOD_FAV_GROUP_ID) {
            $fav_ids = array();
        }

        $items = array();
        foreach ($this->get_row_movies($row_id) as $i => $movie) {
            $items[] = Rows_Factory::add_regular_item("$row_id:$i", self::get_rows_poster($movie), null,
                $this->get_rows_movie_stickers($movie, $fav_ids, $list_ids));
        }

        $show_all = null;
        if (empty($items)) {
            // ENTER on it tries again
            $items[] = Rows_Factory::add_regular_item("$row_id:" . self::ITEM_NOTE, self::ROWS_FAILED_URL, TR::load('vod_catalog_row_failed'));
        } else if (is_null($this->catalog_rows[$row_id]['group']) || safe_get_value($this->catalog_rows[$row_id], 'total', 0) > self::ROW_TILES) {
            // a local group has "show all" only if the row does not hold all its movies, the end of a category is not known
            $items[] = Rows_Factory::add_regular_item("$row_id:" . self::ITEM_MORE, self::MORE_ICON_URL, TR::load('vod_catalog_show_all'));
            $show_all = $this->make_show_all_action($row_id);
        }

        return $this->make_row_from_items(self::ITEMS_ROW_PREFIX . $row_id, $this->catalog_rows[$row_id]['title'],
            self::GROUP_PREFIX . $row_id, $items, $show_all);
    }

    /**
     * ENTER on the title of the row on the left runs it, without it ENTER moves the focus into the row
     *
     * @param string $row_id
     * @return array
     */
    protected function make_show_all_action($row_id)
    {
        return User_Input_Handler_Registry::create_action($this, self::ACTION_SHOW_ALL, null, array(LIST_IDX => $row_id));
    }

    /**
     * Row the firmware asks for by get_folder_rows()
     *
     * @param string $row_id
     * @return array
     */
    protected function make_stub_row($row_id)
    {
        $items = array();
        for ($i = 0; $i < self::ROW_STUBS; $i++) {
            $items[] = Rows_Factory::add_regular_item("$row_id:" . self::ITEM_STUB . $i, self::ROWS_LOADING_URL);
        }

        // a category opens without its first page, the number of movies of a local group is not known yet
        $show_all = is_null($this->catalog_rows[$row_id]['group']) ? $this->make_show_all_action($row_id) : null;
        $row = $this->make_row_from_items(self::ITEMS_ROW_PREFIX . $row_id, $this->catalog_rows[$row_id]['title'],
            self::GROUP_PREFIX . $row_id, $items, $show_all);
        $row[PluginRow::options] = PLUGIN_ROW_OPT_LOADING;

        return $row;
    }

    /**
     * @param string $id
     * @param string $title
     * @param string $group_id
     * @param array $items
     * @param array|null $show_all_action
     * @return array
     */
    protected function make_row_from_items($id, $title, $group_id, $items, $show_all_action = null)
    {
        $template = Rows_Factory::regular_row_template(self::PARAMS_ID, $title, $group_id, null, $show_all_action,
            self::ROW_HEIGHT, self::ROW_INACTIVE_HEIGHT);
        $row = Rows_Factory::regular_row_from_template($template, $id, $items);
        $row[PluginRow::data][PluginRegularRow::inactive_left_padding] = 120;

        return $row;
    }

    /**
     * @param array $rows
     * @param bool $is_empty
     * @return array
     */
    protected function get_catalog_pane($rows, $is_empty)
    {
        $pane = Rows_Factory::pane(
            $rows,
            null,
            Rows_Factory::focus(GCOMP_FOCUS_DEFAULT_CUT_IMAGE, GCOMP_FOCUS_DEFAULT_RECT),
            null,
            !$is_empty,
            false,
            -1,
            null,
            null,
            0.89,
            0.0,
            -0.5,
            50
        );
        $pane[PluginRowsPane::horizontal_focus_end_min_offset] = 130;

        $has_art = $this->is_rows_art_shown();
        Rows_Factory::pane_set_geometry(
            $pane,
            1920,
            1080,
            0,
            0,
            $this->get_rows_info_height(),
            0,
            $this->get_rows_info_width(),
            $this->get_rows_info_height() - self::INFO_Y,
            self::INFO_X,
            self::INFO_Y,
            $has_art ? self::ART_WIDTH : 0,
            $has_art ? self::ART_HEIGHT : 0
        );

        $def_params = Rows_Factory::variable_params(200, self::ROW_HEIGHT, 0, 190, 285, 7, 10, self::INFO_TEXT_COLOR, 28);
        $sel_params = Rows_Factory::variable_params(200, self::ROW_HEIGHT, 0, 200, 300, 0, 10, self::INFO_TITLE_COLOR, 28);
        $inactive_params = Rows_Factory::variable_params(200, self::ROW_INACTIVE_HEIGHT, 5, 190, 285, 0, 0, RowsItemsParams::inactive_caption_color, 28);
        $params = Rows_Factory::item_params($def_params, $sel_params, $inactive_params,
            self::ROWS_LOADING_URL, self::ROWS_FAILED_URL, 3, 3, Rows_Factory::margins(3, 3, 3, 4));
        Rows_Factory::set_item_params_template($pane, self::PARAMS_ID, $params);

        return $pane;
    }

    ///////////////////////////////////////////////////////////////////////
    // Items

    /**
     * @param string $item_id
     * @return array row id, tail of the item id
     */
    protected static function parse_item_id($item_id)
    {
        $pos = strrpos($item_id, ':');
        if ($pos === false) {
            return array('', '');
        }

        return array((string)substr($item_id, 0, $pos), (string)substr($item_id, $pos + 1));
    }

    /**
     * @inheritDoc
     */
    protected function get_rows_movie($item_id)
    {
        list($row_id, $tail) = self::parse_item_id($item_id);
        if ($tail === '' || strspn($tail, '0123456789') !== strlen($tail)) {
            return null;
        }

        // the movies are lost in a fresh php process
        $this->ensure_catalog_rows();
        $movies = $this->get_row_movies($row_id);

        return isset($movies[(int)$tail]) ? $movies[(int)$tail] : null;
    }

    /**
     * @inheritDoc
     */
    protected function update_rows_info($user_input)
    {
        if (!isset($user_input->item_id, $user_input->folder_key)) {
            return null;
        }

        list($row_id, $tail) = self::parse_item_id($user_input->item_id);
        if ($tail === self::ITEM_MORE) {
            if (!isset($this->catalog_rows[$row_id])) {
                return null;
            }

            // the title of the row
            return $this->make_rows_info_action($user_input, new Short_Movie('', $this->catalog_rows[$row_id]['title'], ''));
        }

        // a stub, a message
        $movie = $this->get_rows_movie($user_input->item_id);
        if (is_null($movie)) {
            return null;
        }

        $this->info_row_group = isset($this->catalog_rows[$row_id]) ? $this->catalog_rows[$row_id]['group'] : null;
        return $this->make_rows_info_action($user_input, $movie);
    }

    /**
     * The keys depend on the row of the movie, see the action map. The "show all" tile has none
     *
     * @inheritDoc
     */
    protected function get_rows_key_hints($movie)
    {
        if ($movie->id === '') {
            return array();
        }

        $hints = array(array(PaneParams::fav_button_red, TR::load('vod_change_view')));
        if ($this->info_row_group === VOD_HISTORY_GROUP_ID) {
            $hints[] = array(PaneParams::fav_button_green, TR::load('delete'));
        } else if ($this->is_filter_supported()) {
            $hints[] = array(PaneParams::fav_button_green, TR::load(VOD_FILTER_GROUP_CAPTION));
        }

        $hints[] = array(PaneParams::fav_button_yellow, TR::load('search'));
        $hints[] = $this->get_favorite_key_hint($movie);

        return $hints;
    }

    /**
     * The view of the catalog chosen by the red key, the same for all playlists
     *
     * @return bool
     */
    protected function is_compact_view()
    {
        return $this->plugin->get_bool_parameter(PARAM_VOD_CATALOG_COMPACT, false);
    }

    /**
     * @inheritDoc
     */
    protected function is_rows_art_shown()
    {
        return !$this->is_compact_view();
    }

    /**
     * @inheritDoc
     */
    protected function get_rows_info_width()
    {
        return $this->is_compact_view() ? self::COMPACT_INFO_WIDTH : self::INFO_WIDTH;
    }

    /**
     * @inheritDoc
     */
    protected function get_rows_info_height()
    {
        return $this->is_compact_view() ? self::COMPACT_INFO_HEIGHT : self::INFO_HEIGHT;
    }

    /**
     * @inheritDoc
     */
    protected function get_rows_art_mask()
    {
        return self::ROWS_ART_MASK_URL;
    }
}
