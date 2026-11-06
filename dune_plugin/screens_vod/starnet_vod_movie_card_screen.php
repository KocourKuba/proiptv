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
require_once 'lib/gcomps_factory.php';
require_once 'lib/text_width.php';

/**
 * Card of a movie in the rows view of VOD (r25+), a gcomps screen as the card of the firmware's own catalog:
 * the darkened art, the poster with the menu under it on the left, the info of the movie on the right.
 * The plugin draws the selection itself, the keys change the components by change_gcomps.
 * The "episodes" item shows the list of the seasons or the series in the right panel instead of the info, the focus
 * goes there by ENTER or the right key; a season opens its series in the same panel, a series starts the playback.
 */
class Starnet_Vod_Movie_Card_Screen extends Abstract_Controls_Screen
{
    const ID = 'vod_movie_card';

    const ACTION_QUALITY_SELECTED = 'card_quality_selected';

    const MENU_WATCH = 0;
    const MENU_FAVORITE = 1;
    const MENU_EPISODES = 2;
    const MENU_MAX_ITEMS = 3;

    const FOCUS_MENU = 0;
    const FOCUS_LIST = 1;
    const LEVEL_SEASONS = 0;
    const LEVEL_SERIES = 1;

    const LIST_Y = 190;
    const LIST_ROW_H = 96;
    const LIST_VISIBLE = 7;
    // the poster of a season is portrait, the image of a series is usually a wide frame
    const LIST_SEASON_POSTER_W = 64;
    const LIST_SERIES_POSTER_W = 150;
    const LIST_POSTER_H = 90;
    const LIST_DESC_Y = 875;
    const LIST_DESC_LINES = 2;
    const LIST_DESC_SIZE = 30;

    const POSTER_X = 65;
    const POSTER_Y = 56;
    const POSTER_W = 290;
    const POSTER_H = 432;
    const MENU_Y = 505;
    const MENU_ITEM_H = 50;
    const MENU_TEXT_DX = 10;

    const INFO_X = 460;
    const INFO_W = 1340;
    const INFO_BOTTOM = 935;
    const INFO_LABEL_W = 270;
    const LINE_H = 50;
    const TEXT_SIZE = 34;
    const DESC_LINE_H = 49;
    const DESC_MAX_LINES = 100;
    const CAST_MAX_LINES = 3;
    const HINT_Y = 985;
    const FAVORITE_HINT_Y = 517;

    const TITLE_COLOR = '#FFFFE0FF';
    const SUBTITLE_COLOR = '#AFAFA0FF';
    const TEXT_COLOR = '#AFAFA0FF';
    const LABEL_COLOR = '#808080FF';
    const YEAR_COLOR = '#FFFF40FF';
    const SELECTION_COLOR = '#2850C0D0';
    const SELECTION_DIM_COLOR = '#2850C060';
    const DARKEN_COLOR = '#000000C0';
    const LINE_COLOR = '#FFFFFF30';
    const BACKGROUND_COLOR = '#000000FF';

    const POSTER_LOADING_URL = 'plugin_file://%shell_ext%/icons/loading_movie_poster.png';
    const POSTER_FAILED_URL = 'plugin_file://%shell_ext%/icons/no_movie_poster.png';
    const SCROLL_KEYS_URL = 'plugin_file://%shell_ext%/icons/p_plus_p_minus.png';

    /**
     * @var int
     */
    protected $menu_sel = self::MENU_WATCH;

    /**
     * First shown line of the description
     *
     * @var int
     */
    protected $desc_top = 0;

    /**
     * Lines of the description below the shown ones, it is what can be scrolled
     *
     * @var int
     */
    protected $desc_max_top = 0;

    /**
     * Where the keys go: the menu or the list in the right panel
     *
     * @var int
     */
    protected $focus = self::FOCUS_MENU;

    /**
     * What the list shows: the seasons or the series
     *
     * @var int
     */
    protected $list_level = self::LEVEL_SERIES;

    /**
     * Season of the shown series
     *
     * @var string
     */
    protected $list_season = '';

    /**
     * @var int
     */
    protected $list_sel = 0;

    /**
     * @var int
     */
    protected $list_top = 0;

    /**
     * Selected season, the list of the seasons returns to it
     *
     * @var int
     */
    protected $season_sel = 0;

    ///////////////////////////////////////////////////////////////////////

    /**
     * Only the movie and its playlist: the playback invalidates the card by this url
     *
     * @param string $movie_id
     * @return false|string
     */
    public static function make_vod_media_url_str($movie_id)
    {
        return MediaURL::encode(Vod_Context::add_playlist_id(array(PARAM_SCREEN_ID => static::ID, PARAM_MOVIE_ID => $movie_id)));
    }

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        // the actions bring the movie and its playlist themselves, as the gcomps screens of shell_ext and Melange do
        $params = self::make_action_params($media_url->{PARAM_MOVIE_ID}, safe_get_value($media_url, PARAM_PLAYLIST_ID));

        $actions = array();
        foreach (array(GUI_EVENT_KEY_UP, GUI_EVENT_KEY_DOWN, GUI_EVENT_KEY_LEFT, GUI_EVENT_KEY_RIGHT, GUI_EVENT_KEY_ENTER, GUI_EVENT_KEY_RETURN,
                     GUI_EVENT_KEY_PLAY, GUI_EVENT_KEY_P_PLUS, GUI_EVENT_KEY_P_MINUS) as $key) {
            $actions[$key] = User_Input_Handler_Registry::create_action($this, $key, null, $params);
        }

        return $actions;
    }

    /**
     * @param string $movie_id
     * @param string|null $playlist_id
     * @return array
     */
    protected static function make_action_params($movie_id, $playlist_id)
    {
        return array(PARAM_MOVIE_ID => $movie_id, PARAM_PLAYLIST_ID => $playlist_id);
    }

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->get_folder_view_v2($media_url, null, $plugin_cookies);
    }

    /**
     * @param MediaURL $media_url
     * @param string|null $sel_state selection of the reloaded screen
     * @param object $plugin_cookies
     * @return array
     */
    public function get_folder_view_v2(MediaURL $media_url, $sel_state, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $state = is_string($sel_state) ? json_decode($sel_state, true) : null;
        $sel = isset($state['sel']) ? (int)$state['sel'] : self::MENU_WATCH;
        $this->menu_sel = in_array($sel, $this->get_menu_ids($media_url->{PARAM_MOVIE_ID}), true) ? $sel : self::MENU_WATCH;
        $this->desc_top = isset($state['top']) ? max(0, (int)$state['top']) : 0;

        $movie = $this->plugin->vod->get_loaded_movie($media_url->{PARAM_MOVIE_ID});
        $info = is_null($movie) ? array() : $movie->get_movie_info();

        $has_seasons = !is_null($movie) && $movie->has_seasons();
        $this->focus = $this->menu_sel === self::MENU_EPISODES && isset($state['focus']) && (int)$state['focus'] === self::FOCUS_LIST
            ? self::FOCUS_LIST : self::FOCUS_MENU;
        $this->list_level = isset($state['level']) ? (int)$state['level'] : ($has_seasons ? self::LEVEL_SEASONS : self::LEVEL_SERIES);
        if (!$has_seasons) {
            $this->list_level = self::LEVEL_SERIES;
        }
        $this->list_season = isset($state['season']) ? (string)$state['season'] : '';
        $this->list_sel = isset($state['list_sel']) ? max(0, (int)$state['list_sel']) : 0;
        $this->list_top = isset($state['list_top']) ? max(0, (int)$state['list_top']) : 0;
        $this->season_sel = isset($state['season_sel']) ? max(0, (int)$state['season_sel']) : 0;

        return array(
            PluginFolderView::multiple_views_supported => false,
            PluginFolderView::archive => null,
            PluginFolderView::folder_type => null,
            PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
            PluginFolderView::data => array(
                PluginGCompsFolderView::window_def => $this->get_window($media_url->{PARAM_MOVIE_ID}, $info),
                PluginGCompsFolderView::sel_state => $this->get_sel_state(),
                PluginGCompsFolderView::actions => $this->get_action_map($media_url, $plugin_cookies),
                PluginGCompsFolderView::timer => null,
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $movie_id = safe_get_value($user_input, PARAM_MOVIE_ID);
        if (empty($movie_id)) {
            return null;
        }

        $in_list = $this->focus === self::FOCUS_LIST;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_LEFT:
                if (!$in_list) {
                    return null;
                }

                $this->focus = self::FOCUS_MENU;
                return $this->make_change_menu_action($movie_id);

            case GUI_EVENT_KEY_RIGHT:
                if ($in_list || $this->menu_sel !== self::MENU_EPISODES) {
                    return null;
                }

                $this->focus = self::FOCUS_LIST;
                return $this->make_change_menu_action($movie_id);

            case GUI_EVENT_KEY_RETURN:
                if (!$in_list) {
                    return Action_Factory::close_and_run();
                }

                $movie = $this->plugin->vod->get_loaded_movie($movie_id);
                if ($this->list_level === self::LEVEL_SERIES && !is_null($movie) && $movie->has_seasons()) {
                    $this->list_level = self::LEVEL_SEASONS;
                    $this->list_sel = $this->season_sel;
                    $this->list_top = max(0, $this->list_sel - self::LIST_VISIBLE + 1);
                } else {
                    $this->focus = self::FOCUS_MENU;
                }
                return $this->make_change_menu_action($movie_id);

            case GUI_EVENT_KEY_UP:
            case GUI_EVENT_KEY_DOWN:
                if ($in_list) {
                    return $this->move_list($movie_id, $user_input->control_id === GUI_EVENT_KEY_UP ? -1 : 1);
                }

                $ids = $this->get_menu_ids($movie_id);
                $pos = array_search($this->menu_sel, $ids, true) + ($user_input->control_id === GUI_EVENT_KEY_UP ? -1 : 1);
                if (!isset($ids[$pos])) {
                    return null;
                }

                $this->menu_sel = $ids[$pos];
                return $this->make_change_menu_action($movie_id);

            case GUI_EVENT_KEY_P_PLUS:
            case GUI_EVENT_KEY_P_MINUS:
                $direction = $user_input->control_id === GUI_EVENT_KEY_P_PLUS ? -1 : 1;
                if ($in_list) {
                    return $this->move_list($movie_id, $direction * self::LIST_VISIBLE);
                }

                return $this->menu_sel === self::MENU_WATCH ? $this->scroll_description($direction) : null;

            case GUI_EVENT_KEY_PLAY:
                if ($in_list && $this->list_level === self::LEVEL_SERIES) {
                    return $this->play_selected_series($movie_id, $user_input, $plugin_cookies);
                }

                return $this->play($movie_id, $user_input, $plugin_cookies);

            case GUI_EVENT_KEY_ENTER:
                if ($in_list) {
                    return $this->list_level === self::LEVEL_SEASONS
                        ? $this->open_selected_season($movie_id)
                        : $this->play_selected_series($movie_id, $user_input, $plugin_cookies);
                }

                if ($this->menu_sel === self::MENU_FAVORITE) {
                    return $this->toggle_favorite($movie_id);
                }

                if ($this->menu_sel === self::MENU_EPISODES) {
                    $this->focus = self::FOCUS_LIST;
                    return $this->make_change_menu_action($movie_id);
                }

                return $this->watch($movie_id, $user_input, $plugin_cookies);

            case self::ACTION_QUALITY_SELECTED:
                $this->plugin->set_vod_setting(PARAM_VOD_SELECTED_QUALITY, $user_input->quality);
                if (isset($user_input->series_id)) {
                    return Starnet_Vod_Movie_List_Screen::play_series($this->plugin, $movie_id, $user_input->series_id, $user_input, $plugin_cookies);
                }

                return $this->play($movie_id, $user_input, $plugin_cookies);
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////
    // Actions

    /**
     * Items of the menu in their order. "Episodes" is for a movie with seasons or several series
     *
     * @param string $movie_id
     * @return int[]
     */
    protected function get_menu_ids($movie_id)
    {
        $ids = array(self::MENU_WATCH);
        $movie = $this->plugin->vod->get_loaded_movie($movie_id);
        if (!is_null($movie) && ($movie->has_seasons() || count($movie->get_series_list()) > 1)) {
            $ids[] = self::MENU_EPISODES;
        }
        $ids[] = self::MENU_FAVORITE;

        return $ids;
    }

    /**
     * Items of the list in the right panel
     *
     * @param Movie $movie
     * @return array id, name, description, poster
     */
    protected function get_list_items($movie)
    {
        $items = array();
        if ($this->list_level === self::LEVEL_SEASONS) {
            foreach ($movie->get_seasons_list() as $season) {
                $items[] = array('id' => (string)$season->id, 'name' => empty($season->name) ? $season->id : $season->name,
                    'desc' => $season->description, 'poster' => $season->poster);
            }

            return $items;
        }

        $has_seasons = $movie->has_seasons();
        foreach ($movie->get_series_list() as $series_id => $series) {
            if (!$has_seasons || $series->season_id === $this->list_season) {
                $items[] = array('id' => (string)$series_id, 'name' => $series->name, 'desc' => $series->description, 'poster' => $series->poster);
            }
        }

        return $items;
    }

    /**
     * @param string $movie_id
     * @param int $delta
     * @return array|null
     */
    protected function move_list($movie_id, $delta)
    {
        $movie = $this->plugin->vod->get_loaded_movie($movie_id);
        if (is_null($movie)) {
            return null;
        }

        $count = count($this->get_list_items($movie));
        $sel = max(0, min($count - 1, $this->list_sel + $delta));
        if ($sel === $this->list_sel) {
            return null;
        }

        $this->list_sel = $sel;
        if ($sel < $this->list_top) {
            $this->list_top = $sel;
        } else if ($sel >= $this->list_top + self::LIST_VISIBLE) {
            $this->list_top = $sel - self::LIST_VISIBLE + 1;
        }

        return $this->make_change_menu_action($movie_id);
    }

    /**
     * @param string $movie_id
     * @return array|null
     */
    protected function open_selected_season($movie_id)
    {
        $movie = $this->plugin->vod->get_loaded_movie($movie_id);
        if (is_null($movie)) {
            return null;
        }

        $items = $this->get_list_items($movie);
        if (!isset($items[$this->list_sel])) {
            return null;
        }

        $this->season_sel = $this->list_sel;
        $this->list_season = $items[$this->list_sel]['id'];
        $this->list_level = self::LEVEL_SERIES;
        $this->list_sel = 0;
        $this->list_top = 0;

        return $this->make_change_menu_action($movie_id);
    }

    /**
     * The quality if there is a choice, then the playback of the series
     *
     * @param string $movie_id
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    protected function play_selected_series($movie_id, $user_input, &$plugin_cookies)
    {
        $movie = $this->plugin->vod->get_loaded_movie($movie_id);
        if (is_null($movie)) {
            return null;
        }

        $items = $this->get_list_items($movie);
        if (!isset($items[$this->list_sel])) {
            return null;
        }

        $series_id = $items[$this->list_sel]['id'];
        $qualities = $movie->get_qualities($series_id);
        unset($qualities['auto']);
        if (count($qualities) < 2) {
            return Starnet_Vod_Movie_List_Screen::play_series($this->plugin, $movie_id, $series_id, $user_input, $plugin_cookies);
        }

        return $this->make_quality_menu($movie_id, safe_get_value($user_input, PARAM_PLAYLIST_ID), $qualities, $series_id);
    }

    /**
     * "Watch": a series continues from the last viewing, a single movie plays at once or after the choice of the quality
     *
     * @param string $movie_id
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    protected function watch($movie_id, $user_input, &$plugin_cookies)
    {
        $movie = $this->plugin->vod->get_loaded_movie($movie_id);
        if (is_null($movie)) {
            return Action_Factory::show_title_dialog(TR::t('err_load_vod'), TR::t('warn_msg5'));
        }

        if ($movie->has_seasons() || count($movie->get_series_list()) > 1) {
            return $this->play($movie_id, $user_input, $plugin_cookies);
        }

        $series_ids = array_keys($movie->get_series_list());
        $qualities = empty($series_ids) ? array() : $movie->get_qualities($series_ids[0]);
        unset($qualities['auto']);
        if (count($qualities) < 2) {
            return $this->play($movie_id, $user_input, $plugin_cookies);
        }

        return $this->make_quality_menu($movie_id, safe_get_value($user_input, PARAM_PLAYLIST_ID), $qualities);
    }

    /**
     * @param string $movie_id
     * @param string|null $playlist_id
     * @param array $qualities id => name, without 'auto'
     * @param string|null $series_id series to play after the choice, null - the last viewed one
     * @return array
     */
    protected function make_quality_menu($movie_id, $playlist_id, $qualities, $series_id = null)
    {
        $params = self::make_action_params($movie_id, $playlist_id);
        if (!is_null($series_id)) {
            $params['series_id'] = $series_id;
        }

        $current = $this->plugin->get_vod_setting(PARAM_VOD_SELECTED_QUALITY, 'auto');
        if ($current !== 'auto' && !isset($qualities[$current])) {
            $current = 'auto';
        }

        $menu_items = array();
        foreach (safe_merge_array(array('auto' => TR::t('by_default')), $qualities) as $key => $name) {
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, self::ACTION_QUALITY_SELECTED, $name,
                (string)$key === (string)$current ? 'check.png' : null,
                safe_merge_array($params, array('quality' => (string)$key)));
        }

        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param string $movie_id
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    protected function play($movie_id, $user_input, &$plugin_cookies)
    {
        $action = Starnet_Vod_Movie_List_Screen::play_movie($this->plugin, $movie_id, $user_input, $plugin_cookies);
        return $action === false ? Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'), TR::t('warn_msg5')) : $action;
    }

    /**
     * The opened lists under the card get the new star, the card only changes its menu
     *
     * @param string $movie_id
     * @return array
     */
    protected function toggle_favorite($movie_id)
    {
        $in_favorites = $this->plugin->is_channel_in_order(VOD_FAV_GROUP_ID, $movie_id);
        $this->plugin->change_vod_favorites($in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD, $movie_id);

        $message = Action_Factory::show_title_dialog(TR::t('information'), $in_favorites ? TR::t('deleted_from_favorite') : TR::t('added_to_favorite'));

        // all folders except this card
        return Action_Factory::invalidate_folders(array(self::make_vod_media_url_str($movie_id)), $this->make_change_menu_action($movie_id, $message), true);
    }

    /**
     * @param string $movie_id
     * @param array|null $post_action
     * @return array
     */
    protected function make_change_menu_action($movie_id, $post_action = null)
    {
        $changes = array(
            GComps_Factory::get_change_def('menu', null, null, null, null, $this->get_menu_children($movie_id), GCOMP_TRANSITION_NONE),
            GComps_Factory::get_change_def('right', null, null, null, null, $this->get_right_children($movie_id), GCOMP_TRANSITION_NONE),
        );

        return Action_Factory::change_gcomps($changes, $post_action, 0, $this->get_sel_state());
    }

    /**
     * @param int $direction
     * @return array|null
     */
    protected function scroll_description($direction)
    {
        $top = max(0, min($this->desc_max_top, $this->desc_top + $direction * $this->get_desc_page()));
        if ($top === $this->desc_top) {
            return null;
        }

        $this->desc_top = $top;
        $change = GComps_Factory::get_change_def('desc_lines', $this->get_desc_lines_geom(), null, null, null, null, GCOMP_TRANSITION_NONE);
        return Action_Factory::change_gcomps(array($change), null, 0, $this->get_sel_state());
    }

    /**
     * @return string
     */
    protected function get_sel_state()
    {
        return json_encode(array(
            'sel' => $this->menu_sel,
            'top' => $this->desc_top,
            'focus' => $this->focus,
            'level' => $this->list_level,
            'season' => $this->list_season,
            'list_sel' => $this->list_sel,
            'list_top' => $this->list_top,
            'season_sel' => $this->season_sel,
        ));
    }

    ///////////////////////////////////////////////////////////////////////
    // Components

    /**
     * @param string $movie_id
     * @param array $info PluginMovie fields, empty if the movie is not loaded
     * @return array
     */
    protected function get_window($movie_id, $info)
    {
        $poster_url = safe_get_value($info, PluginMovie::poster_url, '');

        $defs = array();
        $defs[] = GComps_Factory::get_rect_def(GComp_Geom::place_top_left(1920, 1080, 0, 0), null, self::DARKEN_COLOR);
        if (!empty($poster_url)) {
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(self::POSTER_W, self::POSTER_H, self::POSTER_X, self::POSTER_Y), null,
                $poster_url, true, true, self::POSTER_LOADING_URL, self::POSTER_FAILED_URL);
        }

        $defs[] = GComps_Factory::get_panel_def('menu',
            GComp_Geom::place_top_left(self::POSTER_W, self::MENU_ITEM_H * self::MENU_MAX_ITEMS, self::POSTER_X, self::MENU_Y),
            null, $this->get_menu_children($movie_id));

        $defs[] = GComps_Factory::get_panel_def('right', GComp_Geom::place_top_left(1920, 1080, 0, 0), null, $this->get_right_children($movie_id));

        return GComps_Factory::get_window_def($defs, null, empty($poster_url) ? null : $poster_url, self::BACKGROUND_COLOR, null, true, -1,
            array(ImageFitDef::base_halign_ratio => 0.62, ImageFitDef::base_valign_ratio => 0.5));
    }

    /**
     * @param string $movie_id
     * @return array
     */
    protected function get_menu_children($movie_id)
    {
        $movie = $this->plugin->vod->get_loaded_movie($movie_id);
        $captions = array(
            self::MENU_WATCH => TR::load('vod_card_watch'),
            self::MENU_EPISODES => TR::load(!is_null($movie) && $movie->has_seasons() ? 'vod_card_episodes' : 'vod_card_series'),
            self::MENU_FAVORITE => TR::load(VOD_FAV_GROUP_CAPTION),
        );

        $defs = array();
        foreach ($this->get_menu_ids($movie_id) as $i => $id) {
            $caption = $captions[$id];
            $y = $i * self::MENU_ITEM_H;
            $selected = $id === $this->menu_sel;
            if ($selected) {
                $defs[] = GComps_Factory::get_rect_def(GComp_Geom::place_top_left(self::POSTER_W, self::MENU_ITEM_H, 0, $y), null,
                    $this->focus === self::FOCUS_MENU ? self::SELECTION_COLOR : self::SELECTION_DIM_COLOR);
            }

            $defs[] = self::make_label($caption, self::MENU_TEXT_DX, $y + 2, self::POSTER_W - self::MENU_TEXT_DX, $selected ? self::TITLE_COLOR : self::TEXT_COLOR);
        }

        return $defs;
    }

    /**
     * The right panel: the info of the movie, the list of the "episodes" item or only the hint of the "favorite" item
     *
     * @param string $movie_id
     * @return array
     */
    protected function get_right_children($movie_id)
    {
        if ($this->menu_sel === self::MENU_FAVORITE) {
            $hint = $this->plugin->is_channel_in_order(VOD_FAV_GROUP_ID, $movie_id) ? 'vod_card_favorite_remove_hint' : 'vod_card_favorite_add_hint';
            return array(GComps_Factory::label(GComp_Geom::place_top_left(self::INFO_W, self::TEXT_SIZE + 12, self::INFO_X, self::FAVORITE_HINT_Y), null,
                TR::load($hint), 1, self::TEXT_COLOR, self::TEXT_SIZE, null, array('halign' => HALIGN_CENTER, 'fit' => GCOMP_TEXT_FIT_APPEND_ELLIPSIS)));
        }

        $movie = $this->plugin->vod->get_loaded_movie($movie_id);
        $info = is_null($movie) ? array() : $movie->get_movie_info();

        $defs = array();
        if (!is_null($movie) && $this->menu_sel === self::MENU_EPISODES) {
            $this->add_list_defs($defs, $movie_id, $movie, $info);
        } else {
            $this->add_info_defs($defs, $movie_id, $info);
        }

        return $defs;
    }

    /**
     * @param array $defs
     * @param string $movie_id
     * @param Movie $movie
     * @param array $info
     * @return void
     */
    protected function add_list_defs(&$defs, $movie_id, $movie, $info)
    {
        $items = $this->get_list_items($movie);
        $count = count($items);
        $in_list = $this->focus === self::FOCUS_LIST;

        $defs[] = self::make_label(safe_get_value($info, PluginMovie::name, ''), self::INFO_X, 50, self::INFO_W, self::TITLE_COLOR, 48);

        if ($this->list_level === self::LEVEL_SEASONS) {
            $subtitle = TR::load('vod_card_episodes');
        } else if ($movie->has_seasons()) {
            $subtitle = $this->get_season_name($movie);
        } else {
            $subtitle = TR::load('vod_card_series');
        }
        $defs[] = self::make_label($subtitle, self::INFO_X, 120, self::INFO_W, self::SUBTITLE_COLOR);

        $last = min($count, $this->list_top + self::LIST_VISIBLE);
        for ($i = $this->list_top; $i < $last; $i++) {
            $y = self::LIST_Y + ($i - $this->list_top) * self::LIST_ROW_H;
            $selected = $in_list && $i === $this->list_sel;
            if ($selected) {
                $defs[] = GComps_Factory::get_rect_def(GComp_Geom::place_top_left(self::INFO_W, self::LIST_ROW_H, self::INFO_X, $y), null, self::SELECTION_COLOR);
            }

            $right = $this->list_level === self::LEVEL_SEASONS
                ? $this->get_season_right_text($movie, $items[$i]['id'])
                : $this->get_series_right_text($movie_id, $items[$i]['id']);
            $right_width = $right === '' ? 0 : Text_Width::get($right, self::LIST_DESC_SIZE);
            if ($right !== '') {
                $defs[] = self::make_label($right, self::INFO_X + self::INFO_W - $right_width - self::MENU_TEXT_DX, $y + 26, $right_width,
                    self::TEXT_COLOR, self::LIST_DESC_SIZE);
            }

            // the poster of the movie if the item has none, an image url must be a string
            $poster = $items[$i]['poster'];
            if (!is_string($poster) || $poster === '') {
                $poster = safe_get_value($info, PluginMovie::poster_url, '');
            }
            $poster_w = $this->list_level === self::LEVEL_SEASONS ? self::LIST_SEASON_POSTER_W : self::LIST_SERIES_POSTER_W;
            if (is_string($poster) && $poster !== '') {
                $defs[] = GComps_Factory::get_image_def(
                    GComp_Geom::place_top_left($poster_w, self::LIST_POSTER_H, self::INFO_X + self::MENU_TEXT_DX, $y + 3), null,
                    $poster, true, true, self::POSTER_LOADING_URL, self::POSTER_FAILED_URL);
            }

            $name_x = self::MENU_TEXT_DX + $poster_w + 16;
            $defs[] = self::make_label($items[$i]['name'], self::INFO_X + $name_x, $y + 24, self::INFO_W - $name_x - $right_width - 30,
                $selected ? self::TITLE_COLOR : self::TEXT_COLOR);
        }

        // the description of the selected item, the list has the place for it only under the rows
        if (isset($items[$this->list_sel]) && !empty($items[$this->list_sel]['desc'])) {
            $text = str_replace(array("\r", "\n"), ' ', $items[$this->list_sel]['desc']);
            foreach (Text_Width::wrap($text, self::LIST_DESC_SIZE, self::INFO_W, self::LIST_DESC_LINES) as $i => $line) {
                $defs[] = self::make_label($line, self::INFO_X, self::LIST_DESC_Y + $i * (self::LIST_DESC_SIZE + 5), self::INFO_W,
                    self::YEAR_COLOR, self::LIST_DESC_SIZE);
            }
        }

        if ($count > self::LIST_VISIBLE) {
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(114, 50, self::INFO_X, self::HINT_Y), null, self::SCROLL_KEYS_URL);
            $defs[] = self::make_label(TR::load('scroll_page'), self::INFO_X + 124, self::HINT_Y + 2, 600, self::TEXT_COLOR);
        }
    }

    /**
     * @param Movie $movie
     * @return string
     */
    protected function get_season_name($movie)
    {
        foreach ($movie->get_seasons_list() as $season) {
            if ((string)$season->id === $this->list_season) {
                return empty($season->name) ? $this->list_season : $season->name;
            }
        }

        return $this->list_season;
    }

    /**
     * @param Movie $movie
     * @param string $season_id
     * @return string
     */
    protected function get_season_right_text($movie, $season_id)
    {
        $count = 0;
        foreach ($movie->get_series_list() as $series) {
            if ($series->season_id === $season_id) {
                $count++;
            }
        }

        return $count === 0 ? '' : TR::load('vod_card_series_count__1', $count);
    }

    /**
     * Viewing of the series
     *
     * @param string $movie_id
     * @param string $series_id
     * @return string
     */
    protected function get_series_right_text($movie_id, $series_id)
    {
        $row = $this->plugin->get_vod_history_params($movie_id, $series_id);
        if (empty($row)) {
            return '';
        }

        if ($row[COLUMN_WATCHED]) {
            return TR::load('vod_card_watched__1', format_datetime('d.m.Y H:i', $row[COLUMN_TIMESTAMP]));
        }

        $duration = (int)$row[COLUMN_DURATION];
        if ($duration <= 0) {
            return '';
        }

        $position = max(0, min((int)$row[COLUMN_POSITION], $duration));
        return sprintf('[ %s / %s ]  %d%%', format_duration_seconds($position), format_duration_seconds($duration), (int)($position * 100 / $duration));
    }

    /**
     * @param array $defs
     * @param string $movie_id
     * @param array $info
     * @return void
     */
    protected function add_info_defs(&$defs, $movie_id, $info)
    {
        $x = self::INFO_X;
        $w = self::INFO_W;
        $y = 50;

        $title = safe_get_value($info, PluginMovie::name, '');
        $defs[] = self::make_label($title, $x, $y, $w, self::TITLE_COLOR, 48);
        $y += 70;

        $original = safe_get_value($info, PluginMovie::name_original, '');
        if (!empty($original) && $original !== $title) {
            $defs[] = self::make_label($original, $x, $y, $w, self::SUBTITLE_COLOR);
            $y += self::LINE_H + 8;
        }

        $y = $this->add_progress_defs($defs, $movie_id, $y);

        // genres and age on the left, the length on the right
        $values = array_filter(array(safe_get_value($info, PluginMovie::genres_str, ''), safe_get_value($info, PluginMovie::rate_mpaa, '')), 'strlen');
        $length = (int)safe_get_value($info, PluginMovie::length_min, -1);
        $length_text = $length > 0 ? TR::load('vod_rows_length__2', (int)($length / 60), $length % 60) : '';
        if (!empty($values) || $length_text !== '') {
            $y = self::add_left_right_line($defs, implode(' | ', $values), self::TEXT_COLOR, $length_text, $y);
        }

        // year and country on the left, the rating on the right
        $values = array();
        if ((int)safe_get_value($info, PluginMovie::year, -1) > 0) {
            $values[] = $info[PluginMovie::year];
        }
        $country = safe_get_value($info, PluginMovie::country, '');
        if (!empty($country)) {
            $values[] = $country;
        }
        $rating = safe_get_value($info, PluginMovie::rate_imdb, '');
        if (!empty($values) || (is_numeric($rating) && (float)$rating > 0)) {
            $is_rated = is_numeric($rating) && (float)$rating > 0;
            if ($is_rated) {
                $rating_width = Text_Width::get($rating, self::TEXT_SIZE);
                $defs[] = GComps_Factory::get_image_def(
                    GComp_Geom::place_top_left(78, 37, $x + $w - $rating_width - 88, $y + 6), null, get_image_path('imdb.png'), true);
            }
            $y = self::add_left_right_line($defs, implode(', ', $values), self::YEAR_COLOR, $is_rated ? $rating : '', $y);
        }

        $y += 10;
        $people = array(
            'vod_card_cast' => safe_get_value($info, PluginMovie::actors_str, ''),
            'vod_card_directors' => safe_get_value($info, PluginMovie::directors_str, ''),
            'vod_card_writers' => safe_get_value($info, PluginMovie::scenarios_str, ''),
        );
        foreach ($people as $key => $value) {
            if (empty($value)) continue;

            $defs[] = self::make_label(TR::load($key), $x, $y, self::INFO_LABEL_W, self::LABEL_COLOR);

            // the cast can take several lines, what does not fit is replaced by an ellipsis
            $value_w = $w - self::INFO_LABEL_W;
            $max_lines = $key === 'vod_card_cast' ? self::CAST_MAX_LINES : 1;
            foreach (Text_Width::wrap($value, self::TEXT_SIZE, $value_w, $max_lines) as $i => $line) {
                if ($i > 0) {
                    $y += self::DESC_LINE_H;
                }
                $defs[] = self::make_label($line, $x + self::INFO_LABEL_W, $y, $value_w, self::TEXT_COLOR);
            }
            $y += self::LINE_H;
        }

        $this->add_description_defs($defs, trim((string)safe_get_value($info, PluginMovie::description, '')), $y + 8);
    }

    /**
     * Date of the last viewing and the watched part
     *
     * @param array $defs
     * @param string $movie_id
     * @param int $y
     * @return int the next line
     */
    protected function add_progress_defs(&$defs, $movie_id, $y)
    {
        $last = null;
        foreach ($this->plugin->get_vod_history($movie_id) as $row) {
            if (is_null($last) || $row[COLUMN_TIMESTAMP] > $last[COLUMN_TIMESTAMP]) {
                $last = $row;
            }
        }

        if (is_null($last) || $last[COLUMN_TIMESTAMP] <= 0) {
            return $y;
        }

        $date = format_datetime('d.m.Y H:i', $last[COLUMN_TIMESTAMP]);
        if ($last[COLUMN_WATCHED]) {
            return self::add_left_right_line($defs, TR::load('vod_card_watched__1', $date), self::TEXT_COLOR, '', $y);
        }

        $progress = '';
        $duration = (int)$last[COLUMN_DURATION];
        if ($duration > 0) {
            $position = min((int)$last[COLUMN_POSITION], $duration);
            $progress = sprintf('[ %s / %s ]  %d%%', format_duration_seconds($position), format_duration_seconds($duration), (int)($position * 100 / $duration));
        }

        $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(32, 32, self::INFO_X, $y + 8), null, get_image_path('play_small.png'), true);

        return self::add_left_right_line($defs, $date, self::TEXT_COLOR, $progress, $y, 50);
    }

    /**
     * The description in a clipping panel, its inner panel moves to scroll
     *
     * @param array $defs
     * @param string $description
     * @param int $y
     * @return void
     */
    protected function add_description_defs(&$defs, $description, $y)
    {
        $this->desc_max_top = 0;
        $visible = (int)((self::INFO_BOTTOM - $y) / self::DESC_LINE_H);
        if ($description === '' || $visible < 1) {
            return;
        }

        $lines = Text_Width::wrap(str_replace(array("\r", "\n"), ' ', $description), self::TEXT_SIZE, self::INFO_W, self::DESC_MAX_LINES);
        $this->desc_max_top = max(0, count($lines) - $visible);
        $this->desc_top = min($this->desc_top, $this->desc_max_top);

        $children = array();
        foreach ($lines as $i => $line) {
            $children[] = self::make_label($line, 0, $i * self::DESC_LINE_H, self::INFO_W, self::TEXT_COLOR);
        }

        $this->desc_height = count($lines) * self::DESC_LINE_H;
        $this->desc_page = $visible;
        $inner = GComps_Factory::get_panel_def('desc_lines', $this->get_desc_lines_geom(), null, $children);
        $defs[] = GComps_Factory::get_panel_def('desc',
            GComp_Geom::place_top_left(self::INFO_W, $visible * self::DESC_LINE_H, self::INFO_X, $y), null, array($inner));

        if ($this->desc_max_top > 0) {
            $defs[] = GComps_Factory::get_rect_def(GComp_Geom::place_top_left(self::INFO_W, 2, self::INFO_X, self::INFO_BOTTOM + 10), null, self::LINE_COLOR);
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(114, 50, self::INFO_X, self::HINT_Y), null, self::SCROLL_KEYS_URL);
            $defs[] = self::make_label(TR::load('scroll_page'), self::INFO_X + 124, self::HINT_Y + 2, 600, self::TEXT_COLOR);
        }
    }

    /**
     * Height of the description, see add_description_defs()
     *
     * @var int
     */
    protected $desc_height = 0;

    /**
     * Lines of the description in a page
     *
     * @var int
     */
    protected $desc_page = 1;

    /**
     * @return int
     */
    protected function get_desc_page()
    {
        return max(1, $this->desc_page - 1);
    }

    /**
     * @return array
     */
    protected function get_desc_lines_geom()
    {
        return GComp_Geom::place_top_left(self::INFO_W, $this->desc_height, 0, -$this->desc_top * self::DESC_LINE_H);
    }

    /**
     * @param array $defs
     * @param string $left
     * @param string $left_color
     * @param string $right
     * @param int $y
     * @param int $left_dx
     * @return int the next line
     */
    protected static function add_left_right_line(&$defs, $left, $left_color, $right, $y, $left_dx = 0)
    {
        $right_width = $right === '' ? 0 : Text_Width::get($right, self::TEXT_SIZE);
        if ($right !== '') {
            $defs[] = self::make_label($right, self::INFO_X + self::INFO_W - $right_width, $y, $right_width, self::TEXT_COLOR);
        }

        if ($left !== '') {
            $defs[] = self::make_label($left, self::INFO_X + $left_dx, $y, self::INFO_W - $left_dx - $right_width - 20, $left_color);
        }

        return $y + self::LINE_H;
    }

    /**
     * One line of the same font size, the label cuts a long text with an ellipsis
     *
     * @param string $text
     * @param int $x
     * @param int $y
     * @param int $width
     * @param string $color
     * @param int $font_size
     * @return array
     */
    protected static function make_label($text, $x, $y, $width, $color, $font_size = self::TEXT_SIZE)
    {
        return GComps_Factory::label(GComp_Geom::place_top_left($width, $font_size + 12, $x, $y), null,
            $text, 1, $color, $font_size, null, array('fit' => GCOMP_TEXT_FIT_APPEND_ELLIPSIS));
    }
}
