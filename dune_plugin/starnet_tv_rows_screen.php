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

require_once 'lib/epg/ext_epg_program.php';
require_once 'lib/epfs/abstract_rows_screen.php';
require_once 'lib/epfs/rows_factory.php';
require_once 'lib/epfs/gcomps_factory.php';
require_once 'lib/epfs/gcomp_geom.php';
require_once 'lib/user_input_handler_registry.php';

class Starnet_Tv_Rows_Screen extends Abstract_Rows_Screen
{
    const ID = 'rows_epfs';
    const ICON_PROP = 'icon_prop';
    const ICON_LOADING = 'icon_loading_url';
    const ICON_FAILED = 'icon_loading_failed_url';

    ///////////////////////////////////////////////////////////////////////////

    private $clear_playback_points = false;

    private $show_caption = true;
    private $channels_in_row = 7;
    private $square_icons = false;
    private $show_continues = true;

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map();
    }

    protected function do_get_action_map()
    {
        hd_debug_print(null, true);

        $actions[GUI_EVENT_KEY_PLAY] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_PLAY);
        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);
        if (isset($plugin_cookies->toggle_move) && $plugin_cookies->toggle_move) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOP, TR::t('top'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_BOTTOM, TR::t('bottom'));
        } else {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        }
        $add_to_favorite = User_Input_Handler_Registry::create_action($this, PLUGIN_FAVORITES_OP_ADD);
        $actions[GUI_EVENT_KEY_D_BLUE] = $add_to_favorite;
        $actions[GUI_EVENT_KEY_DUNE] = $add_to_favorite;
        $actions[GUI_EVENT_KEY_INFO] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_INFO);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE);
        $actions[GUI_EVENT_KEY_SELECT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        if (!is_limited_apk()) {
            // this key used to fire event from background xmltv indexing script
            $actions[EVENT_INDEXING_DONE] = User_Input_Handler_Registry::create_action($this, EVENT_INDEXING_DONE);
        }

        if ($this->plugin->is_plugin_inited()) {
            $this->plugin->init_plugin();
            $this->plugin->add_shortcuts_handlers($this, $actions);
        }

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function get_timer()
    {
        return Action_Factory::timer(1000);
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        // Dump handled possible $user_input message for NewUI.

        //control_id => plugin_rows_info_update
        //folder_key => proiptv:proiptv
        //item_id => group_id:Общие;channel_id:204

        //selected_header => 1 - user in the leftmost list
        //selected_row_id => group_id:Общие;row_idx:0
        //selected_header_id => Общие
        //selected_title_ndx => 1
        //parent_sel_state => garbage

        //selected_header => 0 - user in selected item in row
        //selected_row_id => group_id:Общие;row_idx:0
        //selected_item_id => group_id:Общие;channel_id:204
        //selected_title_ndx => 1
        //parent_sel_state => garbage

        $is_sel_channel = false;
        if (isset($user_input->item_id)) {
            $media_url = self::row_id_decoder($user_input->item_id);
        } else if (isset($user_input->selected_item_id)) {
            $is_sel_channel = true;
            $media_url = self::row_id_decoder($user_input->selected_item_id);
        } else if (isset($user_input->selected_row_id)) {
            $media_url = self::row_id_decoder($user_input->selected_row_id);
        } else {
            $media_url = MediaURL::decode();
        }

        $fav_id = $this->plugin->get_fav_id();
        $reload_action = User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

        $control_id = $user_input->control_id;

        switch ($control_id) {
            case GUI_EVENT_TIMER:
                if (!$this->plugin->is_plugin_inited()) {
                    return User_Input_Handler_Registry::create_screen_action(Starnet_Entry_Handler::ID, ACTION_RELOAD);
                }

                $error_msg = Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST);
                if (!empty($error_msg)) {
                    hd_debug_print("Playlist loading error: $error_msg");
                    return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), $error_msg);
                }

                if (!is_limited_apk()) return null;

                $actions[] = $this->plugin->get_import_xmltv_logs_actions($plugin_cookies);
                $actions[] = Action_Factory::change_behaviour($this->do_get_action_map(), 1000);
                return Action_Factory::composite($actions);

            case EVENT_INDEXING_DONE:
                return $this->plugin->get_import_xmltv_logs_actions($plugin_cookies);

            case GUI_EVENT_KEY_PLAY:
            case GUI_EVENT_KEY_ENTER:
                $tv_play_action = Action_Factory::tv_play($media_url);

                if (isset($user_input->action_origin)) {
                    return Action_Factory::close_and_run(
                        Action_Factory::invalidate_epfs_folders($plugin_cookies, $tv_play_action)
                    );
                }

                return $tv_play_action;

            case GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE:
                if (!isset($user_input->item_id, $user_input->folder_key)) {
                    return null;
                }

                $info_children = $this->do_get_info_children($media_url, $plugin_cookies);
                $pass_sex = $this->plugin->get_parameter(PARAM_ADULT_PASSWORD);
                if (!$info_children['adult'] || empty($pass_sex)) {
                    $urls[] = sprintf("plugin_tv://%s/%s", get_plugin_name(), self::row_id_decoder($user_input->item_id)->get_media_url_string());
                }

                return Action_Factory::update_rows_info(
                    $user_input->folder_key,
                    $user_input->item_id,
                    $info_children['defs'],
                    empty($info_children['fanart_url']) ? "plugin_file://%shell_ext%/icons/bg1.jpg" : $info_children['fanart_url'],
                    get_image_path(PaneParams::vod_bg_url),
                    get_image_path(PaneParams::vod_mask_url),
                    empty($urls) ? array() : $urls
                );

            case ACTION_SORT_POPUP:
                hd_debug_print("Start event popup menu for playlist", true);
                return User_Input_Handler_Registry::create_action(
                    $this,
                    GUI_EVENT_KEY_POPUP_MENU,
                    null,
                    array(ACTION_SORT_POPUP => true)
                );

            case GUI_EVENT_KEY_POPUP_MENU:
                hd_debug_print("Start event popup menu");
                return Action_Factory::show_popup_menu($this->do_popup_menu($user_input));

            case PLUGIN_FAVORITES_OP_ADD:
            case PLUGIN_FAVORITES_OP_REMOVE:
                if (!isset($media_url->group_id)) {
                    return null;
                }

                $is_in_favorites = $this->plugin->is_channel_in_order($fav_id, $media_url->channel_id);
                $opt_type = $is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_tv_favorites($opt_type, $media_url->channel_id);
                return Action_Factory::invalidate_epfs_folders($plugin_cookies);

            case ACTION_ITEM_TOGGLE_MOVE:
                $plugin_cookies->toggle_move = !$plugin_cookies->toggle_move;
                break;

            case ACTION_ITEM_UP:
            case ACTION_ITEM_DOWN:
            case ACTION_ITEM_TOP:
            case ACTION_ITEM_BOTTOM:
                $direction = $this->action_to_direction($control_id);
                if (!isset($media_url->group_id)
                    || $media_url->group_id === TV_HISTORY_GROUP_ID
                    || $media_url->group_id === TV_ALL_CHANNELS_GROUP_ID
                    || $media_url->group_id === TV_CHANGED_CHANNELS_GROUP_ID
                    || $direction === null) {
                    return null;
                }

                if ($is_sel_channel && $this->plugin->arrange_groups_order_rows($media_url->group_id, $direction)) {
                    break;
                }

                if ($media_url->group_id === $fav_id) {
                    $this->plugin->change_tv_favorites($control_id, $media_url->channel_id);
                    break;
                }

                $this->plugin->arrange_channels_order_rows($media_url->group_id, $media_url->channel_id, $direction);
                return Action_Factory::invalidate_epfs_folders($plugin_cookies);

            case ACTION_ITEMS_SORT:
                $group = $this->plugin->get_group($media_url->group_id, PARAM_GROUP_ORDINARY);
                if (is_null($group) || !isset($user_input->{ACTION_SORT_TYPE})) {
                    return null;
                }

                if ($user_input->{ACTION_SORT_TYPE} === ACTION_SORT_CHANNELS) {
                    $this->plugin->sort_channels_order($media_url->group_id);
                } else {
                    $this->plugin->sort_groups_order();
                }
                return $reload_action;

            case ACTION_RESET_ITEMS_SORT:
                if (!isset($user_input->{ACTION_RESET_TYPE})) {
                    return null;
                }

                if ($user_input->{ACTION_RESET_TYPE} === ACTION_SORT_CHANNELS) {
                    $this->plugin->sort_channels_order($media_url->group_id,true);
                } else if ($user_input->{ACTION_RESET_TYPE} === ACTION_SORT_GROUPS) {
                    $this->plugin->sort_groups_order(true);
                } else if ($user_input->{ACTION_RESET_TYPE} === ACTION_SORT_ALL) {
                    $this->plugin->sort_groups_order(true);
                    foreach ($this->plugin->get_groups_by_order() as $row) {
                        $this->plugin->sort_channels_order($row[COLUMN_GROUP_ID],true);
                    }
                }

                return $reload_action;

            case ACTION_ITEM_REMOVE:
                $this->plugin->erase_tv_history($media_url->channel_id);
                return Action_Factory::invalidate_epfs_folders($plugin_cookies);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                if ($media_url->group_id === TV_HISTORY_GROUP_ID) {
                    $this->clear_playback_points = true;
                    $this->plugin->clear_tv_history();
                } else if ($media_url->group_id === $fav_id) {
                    $this->plugin->change_tv_favorites(ACTION_ITEMS_CLEAR, null, $plugin_cookies);
                } else if ($media_url->group_id === TV_CHANGED_CHANNELS_GROUP_ID) {
                    $this->plugin->clear_changed_channels();
                } else {
                    return null;
                }
                return Action_Factory::invalidate_all_folders($plugin_cookies);

            case ACTION_ITEM_DELETE:
                hd_debug_print("MediaURL: " . $media_url);
                if ($is_sel_channel) {
                    hd_debug_print("Hide channel: " . $media_url->channel_id);
                    $this->plugin->set_channel_visible($media_url->channel_id, false);
                } else {
                    if ($media_url->group_id === TV_CHANGED_CHANNELS_GROUP_ID || $media_url->group_id === TV_HISTORY_GROUP_ID) {
                        return User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_CLEAR);
                    }
                    hd_debug_print("Hide group: " . $media_url->group_id);
                    $this->plugin->set_groups_visible($media_url->group_id, false);
                }

                return Action_Factory::invalidate_epfs_folders($plugin_cookies);

            case ACTION_CHANGE_EPG_SOURCE:
                hd_debug_print("Start event popup menu for epg source");
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_EPG_SOURCE => true));

            case ACTION_EPG_SOURCE_SELECTED:
                if (!isset($user_input->{LIST_IDX}) || $this->plugin->is_use_xmltv()) break;

                foreach ($this->plugin->get_selected_xmltv_ids() as $id) {
                    Epg_Manager_Xmltv::clear_epg_files($id);
                }
                $this->plugin->set_setting(PARAM_EPG_JSON_PRESET, $user_input->{LIST_IDX});
                return $reload_action;

            case ACTION_EPG_CACHE_ENGINE:
                hd_debug_print("Start event popup menu for epg source", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_EPG_CACHE_ENGINE => true));

            case ENGINE_XMLTV:
            case ENGINE_JSON:
                if ($this->plugin->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) === $user_input->control_id) {
                    return null;
                }
                hd_debug_print("Selected engine: $user_input->control_id", true);
                $this->plugin->set_setting(PARAM_EPG_CACHE_ENGINE, $user_input->control_id);
                return $reload_action;

            case ACTION_ITEMS_EDIT:
                return $this->plugin->do_edit_list_screen(static::ID, $user_input->action_edit, $media_url);

            case ACTION_PLUGIN_SETTINGS:
                return $this->plugin->show_protect_settings_dialog($this,
                    Action_Factory::open_folder(Starnet_Setup_Screen::make_controls_media_url_str(static::ID), TR::t('entry_setup'))
                );

            case ACTION_EDIT_PLAYLIST_SETTINGS:
                return $this->plugin->show_protect_settings_dialog($this,
                    Action_Factory::open_folder(Starnet_Setup_Playlist_Screen::make_controls_media_url_str(static::ID), TR::t('setup_playlist'))
                );

            case ACTION_PASSWORD_APPLY:
                return $this->plugin->apply_protect_settings_dialog($user_input);

            case ACTION_EDIT_CHANNEL_DLG:
                return $this->plugin->do_edit_channel_parameters($this, $media_url->channel_id);

            case ACTION_EDIT_CHANNEL_APPLY:
                $this->plugin->do_edit_channel_apply($user_input, $media_url->channel_id);
                return Action_Factory::invalidate_epfs_folders($plugin_cookies);

            case ACTION_INFO_DLG:
                return $this->plugin->do_show_subscription($this);

            case ACTION_ADD_MONEY_DLG:
                return $this->plugin->do_show_add_money();

            case GUI_EVENT_KEY_INFO:
                if (isset($media_url->channel_id)) {
                    return $this->plugin->do_show_channel_info($media_url->channel_id, false);
                }
                return null;

            case ACTION_SHORTCUT:
                if (!isset($user_input->{COLUMN_PLAYLIST_ID}) || $this->plugin->get_active_playlist_id() === $user_input->{COLUMN_PLAYLIST_ID}) {
                    return null;
                }

                $this->plugin->set_active_playlist_id($user_input->{COLUMN_PLAYLIST_ID});
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RELOAD:
                hd_debug_print("Action reload", true);
                $this->plugin->load_channels($plugin_cookies, true);
                safe_unlink(Starnet_Epfs_Handler::get_epfs_path(Starnet_Epfs_Handler::$epf_id));
                $actions[] = Action_Factory::refresh_entry_points();
                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                $actions[] = Action_Factory::change_behaviour($this->do_get_action_map());
                return Action_Factory::composite($actions);

            case ACTION_REFRESH_SCREEN:
                $actions[] = Action_Factory::refresh_entry_points();
                $actions[] = Action_Factory::change_behaviour($this->do_get_action_map());
                return Action_Factory::composite($actions);
        }

        return null;
    }

    /**
     * @param array $pane
     * @param array|null $rows_before
     * @param array|null $rows_after
     * @param int|null $min_row_index_for_y2
     * @return void
     */
    public function add_rows_to_pane(&$pane, $rows_before = null, $rows_after = null, $min_row_index_for_y2 = null)
    {
        if (is_array($rows_before)) {
            $pane[PluginRowsPane::rows] = array_merge($rows_before, $pane[PluginRowsPane::rows]);
        }

        if (is_array($rows_after)) {
            $pane[PluginRowsPane::rows] = array_merge($pane[PluginRowsPane::rows], $rows_after);
        }

        if (!is_null($min_row_index_for_y2)) {
            $pane[PluginRowsPane::min_row_index_for_y2] = $min_row_index_for_y2;
        }
    }

    /**
     * @param object $plugin_cookies
     * @return array|null
     */
    public function get_folder_view_for_epf(&$plugin_cookies)
    {
        hd_debug_print(null, true);

        $this->update_new_ui_settings();

        return $this->get_folder_view(MediaURL::decode(static::ID), $plugin_cookies);
    }

    /**
     * @return array
     */
    public function get_empty_rows_pane()
    {
        hd_debug_print(null, true);

        $defs[] = GComps_Factory::label_v2(GComp_Geom::place_center(), null, TR::t('err_empty_playlist'), 1, "#AFAFA0FF", 60);

        $rows[] = Rows_Factory::vgap_row(50);
        $rows[] = Rows_Factory::gcomps_row("single_row", $defs, null, 1920, 500);

        return Rows_Factory::pane($rows);
    }

    /**
     * @inheritDoc
     */
    public function get_rows_pane(MediaURL $media_url, $plugin_cookies)
    {
        hd_debug_print(null, true);

        if ($this->plugin->is_vod_playlist()) {
            return $this->get_empty_rows_pane();
        }

        $all_channels_rows = array();
        $all_channels_headers = array();
        $fav_headers = array();
        $favorites_rows = array();
        $history_headers = array();
        $history_rows = array();
        $changed_headers = array();
        $changed_rows = array();
        $dummy_rows = array();
        $dummy_headers = array();
        $this->create_row($dummy_rows, $dummy_headers, '__dummy__row__');
        foreach ($this->plugin->get_groups(PARAM_GROUP_SPECIAL, PARAM_ENABLED) as $group_row) {
            switch ($group_row[COLUMN_GROUP_ID]) {
                case TV_ALL_CHANNELS_GROUP_ID:
                    $this->get_all_channels_row($all_channels_rows, $all_channels_headers);
                    break;

                case TV_FAV_GROUP_ID:
                    $this->get_favorites_rows($favorites_rows,$fav_headers);
                    break;

                case TV_HISTORY_GROUP_ID:
                    $this->get_history_rows($history_rows,$history_headers);
                    break;

                case TV_CHANGED_CHANNELS_GROUP_ID:
                    $this->get_changed_channels_rows($changed_rows, $changed_headers);
                    break;
            }
        }

        $all_headers = array();
        if ($this->get_regular_rows($category_rows, $all_headers)) {
            $all_rows = array_merge($dummy_rows, $history_rows, $favorites_rows, $changed_rows, $all_channels_rows, $category_rows);
            if (!$this->show_continues) {
                $all_headers = array_merge($dummy_headers, $history_headers, $fav_headers, $changed_headers, $all_channels_headers, $all_headers);
            }
        }

        if (empty($all_rows)) {
            hd_debug_print("no category rows");
            return $this->get_empty_rows_pane();
        }

        return $this->create_row_pane($all_rows, $all_headers);
    }

    /**
     * @param array $rows
     * @param array $headers
     * @return array
     */
    private function create_row_pane($rows, $headers)
    {
        $pane = Rows_Factory::pane(
            $rows,
            $headers,
            Rows_Factory::focus(GCOMP_FOCUS_DEFAULT_CUT_IMAGE, GCOMP_FOCUS_DEFAULT_RECT),
            null,
            true,
            true,
            -1,
            null,
            null,
            RowsParams::hfactor,
            RowsParams::vfactor,
            $this->GetRowsItemsParams('vgravity'),
            RowsParams::vend_min_offset
        );

        Rows_Factory::pane_set_geometry(
            $pane,
            PaneParams::width,
            PaneParams::height,
            PaneParams::dx,
            PaneParams::dy,
            PaneParams::info_height,
            empty($history_rows) ? 1 : 2,
            PaneParams::width - PaneParams::info_dx,
            PaneParams::info_height - PaneParams::info_dy,
            PaneParams::info_dx,
            PaneParams::info_dy,
            PaneParams::vod_width,
            PaneParams::vod_height
        );

        $rowItemsParams = $this->GetRowsItemsParamsClass();
        $icon_prop = $this->GetRowsItemsParams(self::ICON_PROP);
        $width = (int)(RowsParams::width / $rowItemsParams::items_in_row);

        $def_params = Rows_Factory::variable_params(
            $width,
            $width,
            $rowItemsParams::def_icon_dx,
            $rowItemsParams::icon_width,
            $rowItemsParams::icon_width * $icon_prop,
            $rowItemsParams::def_icon_dy,
            $rowItemsParams::def_caption_dy,
            $rowItemsParams::def_caption_color,
            $rowItemsParams::caption_font_size
        );

        $sel_icon_width = $rowItemsParams::icon_width + $rowItemsParams::sel_zoom_delta;
        $sel_params = Rows_Factory::variable_params(
            $width,
            $width,
            $rowItemsParams::sel_icon_dx,
            $sel_icon_width,
            $sel_icon_width * $icon_prop,
            $rowItemsParams::sel_icon_dy,
            $rowItemsParams::sel_caption_dy,
            $rowItemsParams::sel_caption_color,
            $rowItemsParams::caption_font_size
        );

        $width_inactive = (int)(RowsParams::inactive_width / $rowItemsParams::items_in_row);
        $inactive_icon_width = $rowItemsParams::icon_width_inactive;
        $inactive_params = Rows_Factory::variable_params(
            $width_inactive,
            $width_inactive * $icon_prop,
            $rowItemsParams::inactive_icon_dx,
            $inactive_icon_width,
            $inactive_icon_width * $icon_prop,
            $rowItemsParams::inactive_icon_dy,
            $rowItemsParams::inactive_caption_dy,
            $rowItemsParams::inactive_caption_color,
            $rowItemsParams::caption_font_size
        );

        $params = Rows_Factory::item_params(
            $def_params,
            $sel_params,
            $inactive_params,
            get_image_path($this->GetRowsItemsParams(self::ICON_LOADING)),
            get_image_path($this->GetRowsItemsParams(self::ICON_FAILED)),
            $rowItemsParams::caption_max_num_lines,
            $rowItemsParams::caption_line_spacing,
            Rows_Factory::margins(6, 2, 2, 2)
        );

        Rows_Factory::set_item_params_template($pane, 'common', $params);

        return $pane;
    }

    /**
     * @param array $items
     * @param array $headers
     * @param string $row_id
     * @param string $title
     * @param string $caption
     * @param array|null $action
     * @param string|null $color
     * @return array
     */
    private function create_row($items, &$headers, $row_id, $title = '', $caption = '', $action = null, $color = null)
    {
        if (empty($items)) {
            return array();
        }

        $options = null;
        $header_id = null;
        if (!$this->show_continues) {
            $options = PLUGIN_ROW_OPT_FIRST_IN_CLUSTER;
            $header_id = $row_id;
            Rows_Factory::add_header($headers, $header_id, $caption, true);
        }

        $rows = array();
        $group_id = self::row_id_encoder(array('group_id' => $row_id));
        $title_id = self::row_id_encoder(array('title_id' => $row_id));
        $rows[] = Rows_Factory::title_row($title_id, $caption, $group_id, $color, $options);

        $rowItemsParams = $this->GetRowsItemsParamsClass();
        $icon_prop = $this->GetRowsItemsParams(self::ICON_PROP);
        $height = (int)(RowsParams::width * $icon_prop / $rowItemsParams::items_in_row);
        $inactive_height = (int)(RowsParams::inactive_width * $icon_prop / $rowItemsParams::items_in_row);

        for ($i = 0, $iMax = count($items); $i < $iMax; $i += $rowItemsParams::items_in_row) {
            $idx = (int)($i / $rowItemsParams::items_in_row);
            $row_items_id = array('group_id' => $row_id, 'row_idx' => $idx);
            $row_items = array_slice($items, $i, $rowItemsParams::items_in_row);
            $rows[] = Rows_Factory::regular_row(self::row_id_encoder($row_items_id), $row_items,
                'common', $title, $group_id, $header_id, $action, $height, $inactive_height);
        }

        return $rows;
    }

    /**
     * @param array $rows
     * @param array $headers
     */
    private function get_history_rows(&$rows, &$headers)
    {
        hd_debug_print(null, true);
        if ($this->clear_playback_points) {
            $this->clear_playback_points = false;
            return;
        }

        if (!$this->plugin->get_bool_setting(PARAM_SHOW_HISTORY)) {
            return;
        }

        // Fill view history data
        $now = time();
        $watched = array();
        foreach ($this->plugin->get_tv_history() as $channel_row) {
            $channel_id = $channel_row[COLUMN_CHANNEL_ID];
            $channel_ts = $channel_row[COLUMN_TIMESTAMP];
            $start_tm = $channel_row[COLUMN_TIME_START];
            $end_tm = $channel_row[COLUMN_TIME_END];
            $epg_len = $end_tm - $start_tm;
            $progress = 0;

            $title = $channel_row[COLUMN_TITLE];
            // program epg available
            if ($channel_ts > 0) {
                $title = format_datetime("d.m H:i", $channel_ts);
                if ($start_tm === 0 || $end_tm === 0) {
                    $prog_info = $this->plugin->get_epg_info($channel_id, $channel_ts);
                    $start_tm = $prog_info[PluginTvEpgProgram::start_tm_sec];
                    $epg_len = $prog_info[PluginTvEpgProgram::end_tm_sec] - $start_tm;
                }

                if ($channel_ts >= $now - $channel_row[COLUMN_ARCHIVE] * 86400 - 60 && $epg_len !== 0) {
                    $progress = max(0.01, min(1.0, round(($channel_ts - $start_tm) / $epg_len, 2)));
                }
            }

            $watched[(string)$channel_id] = array(
                'channel_id' => $channel_id,
                'archive_tm' => $channel_ts,
                'view_progress' => $progress,
                'program_title' => $title,
            );
        }

        // fill view history row items
        $rowItemsParams = $this->GetRowsItemsParamsClass();
        $icon_prop = $this->GetRowsItemsParams(self::ICON_PROP);
        $sticker_y = $rowItemsParams::icon_width * $icon_prop - $rowItemsParams::view_progress_height;
        $items = array();
        foreach ($watched as $item) {
            $channel_id = $item[COLUMN_CHANNEL_ID];
            $channel_row = $this->plugin->get_channel_info($channel_id);
            if ($channel_row === null) continue;

            $stickers = null;
            $icon = $this->plugin->get_channel_picon($channel_row, false);
            if ($item['view_progress'] > 0) {
                // item size 229x142
                if (!empty($item['program_icon_url'])) {
                    // add small channel logo
                    $rect = Rows_Factory::r(129, 0, 100, 64);
                    $stickers[] = Rows_Factory::add_regular_sticker_rect($rowItemsParams::fav_sticker_logo_bg_color, $rect);
                    $stickers[] = Rows_Factory::add_regular_sticker_image($icon, $rect);
                }

                // add progress indicator
                $stickers[] = Rows_Factory::add_regular_sticker_rect(
                    $rowItemsParams::view_total_color,
                    Rows_Factory::r(0, $sticker_y, $rowItemsParams::icon_width, $rowItemsParams::view_progress_height)
                ); // total

                $stickers[] = Rows_Factory::add_regular_sticker_rect(
                    $rowItemsParams::view_viewed_color,
                    Rows_Factory::r(0, $sticker_y, $rowItemsParams::icon_width * $item['view_progress'], $rowItemsParams::view_progress_height)
                ); // viewed
            }

            $row_id = array('group_id' => TV_HISTORY_GROUP_ID, 'channel_id' => $channel_id, 'archive_tm' => $item['archive_tm']);
            $items[] = Rows_Factory::add_regular_item(self::row_id_encoder($row_id), $icon, $item['program_title'], $stickers);
        }

        $rows = $this->create_row($items,
            $headers,
            TV_HISTORY_GROUP_ID,
            TR::t('tv_screen_continue'),
            TR::t('tv_screen_continue_view'),
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
            TitleRowsParams::history_caption_color
        );

        if (!empty($rows)) {
            hd_debug_print("added history: " . count($rows) . " rows", true);
        }
    }

    /**
     * @param array $rows
     * @param array $headers
     */
    private function get_favorites_rows(&$rows, &$headers)
    {
        hd_debug_print(null, true);
        if (!$this->plugin->get_bool_setting(PARAM_SHOW_FAVORITES)) {
            return;
        }

        $fav_id = $this->plugin->get_fav_id();
        $items = array();
        foreach ($this->plugin->get_channels_by_order($fav_id) as $channel_row) {
            if (empty($channel_row)) continue;

            $channel_id = $channel_row[COLUMN_CHANNEL_ID];
            $row_id = array('group_id' => $fav_id, 'channel_id' => $channel_id);
            $items[] = Rows_Factory::add_regular_item(
                self::row_id_encoder($row_id),
                $this->plugin->get_channel_picon($channel_row, false),
                $channel_row[COLUMN_TITLE]
            );
        }

        $caption = $this->plugin->get_bool_setting(PARAM_USE_COMMON_FAV, false) ? TR::t('plugin_common_favorites') : TR::t('plugin_favorites');
        $rows = $this->create_row($items, $headers,
            $fav_id,
            $caption,
            $caption,
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
            TitleRowsParams::fav_caption_color
        );

        if (!empty($rows)) {
            hd_debug_print("added favorites: " . count($rows) . " rows", true);
        }
    }

    /**
     * @param array $rows
     * @param array $headers
     */
    private function get_changed_channels_rows(&$rows, &$headers)
    {
        hd_debug_print(null, true);

        if (!$this->plugin->get_bool_setting(PARAM_SHOW_CHANGED_CHANNELS) || !$this->plugin->get_changed_channels_count(PARAM_CHANGED)) {
            return;
        }

        $rowItemsParams = $this->GetRowsItemsParamsClass();
        $bg = Rows_Factory::add_regular_sticker_rect(
            $rowItemsParams::fav_sticker_bg_color,
            Rows_Factory::r(0, 0, $rowItemsParams::fav_sticker_bg_width, $rowItemsParams::fav_sticker_bg_width)
        );
        $added_stickers[] = $bg;

        $added_stickers[] = Rows_Factory::add_regular_sticker_image(
            get_image_path('add.png'),
            Rows_Factory::r(0, 2, $rowItemsParams::fav_sticker_icon_width, $rowItemsParams::fav_sticker_icon_height)
        );

        $removed_stickers[] = $bg;
        $removed_stickers[] = Rows_Factory::add_regular_sticker_image(
            get_image_path('del.png'),
            Rows_Factory::r(0, 2, $rowItemsParams::fav_sticker_icon_width, $rowItemsParams::fav_sticker_icon_height)
        );

        $items = array();
        $group_id = TV_CHANGED_CHANNELS_GROUP_ID;
        foreach ($this->plugin->get_changed_channels(PARAM_NEW) as $channel_row) {
            if (empty($channel_row)) continue;

            $channel_id = $channel_row[COLUMN_CHANNEL_ID];
            $row_id = array('group_id' => $group_id, 'channel_id' => $channel_id);
            $items[] = Rows_Factory::add_regular_item(
                self::row_id_encoder($row_id),
                $this->plugin->get_channel_picon($channel_row, false),
                $channel_row[COLUMN_TITLE],
                $added_stickers
            );
        }

        $removed_channels = $this->plugin->get_changed_channels(PARAM_REMOVED);
        $failed_url = $this->GetRowsItemsParams(self::ICON_FAILED);
        foreach ($removed_channels as $channel_row) {
            if (empty($channel_row)) continue;

            $channel_id = $channel_row[COLUMN_CHANNEL_ID];
            $row_id = array('group_id' => $group_id, 'channel_id' => $channel_id);
            $items[] = Rows_Factory::add_regular_item(
                self::row_id_encoder($row_id),
                $failed_url,
                $channel_row[COLUMN_TITLE],
                $removed_stickers
            );
        }

        $rows = $this->create_row($items, $headers,
            TV_CHANGED_CHANNELS_GROUP_ID,
            TR::t('plugin_changed'),
            TR::t('plugin_changed'),
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
            TitleRowsParams::fav_caption_color
        );

        if (!empty($rows)) {
            hd_debug_print("added changed channels: " . count($rows) . " rows", true);
        }
    }

    /**
     * @param array $rows
     * @param array $headers
     */
    private function get_all_channels_row(&$rows, &$headers)
    {
        hd_debug_print(null, true);

        if (!$this->plugin->get_bool_setting(PARAM_SHOW_ALL)) {
            return;
        }

        $fav_id = $this->plugin->get_fav_id();
        $fav_stickers = $this->get_fav_stickers();
        $channels_order = $this->plugin->get_channels(TV_ALL_CHANNELS_GROUP_ID, PARAM_ENABLED, true);
        $fav_channels = $this->plugin->get_channels_order($fav_id);

        $items = array();
        foreach ($channels_order as $channel_row) {
            if (empty($channel_row)) continue;

            $channel_id = $channel_row[COLUMN_CHANNEL_ID];
            $row_id = array('group_id' => TV_ALL_CHANNELS_GROUP_ID, 'channel_id' => $channel_id);
            $items[] = Rows_Factory::add_regular_item(
                self::row_id_encoder($row_id),
                $this->plugin->get_channel_picon($channel_row, false),
                $channel_row[COLUMN_TITLE],
                in_array($channel_id, $fav_channels) ? $fav_stickers : null
            );
        }

        if ($this->plugin->get_bool_setting(PARAM_NEWUI_SHOW_CHANNEL_COUNT, false)) {
            $title = TR::t('plugin_all_channels__1', count($items));
        } else {
            $title = TR::t('plugin_all_channels');
        }

        $rows = $this->create_row($items,
            $headers,
            TV_ALL_CHANNELS_GROUP_ID,
            $title,
            TR::t('plugin_all_channels'),
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER)
        );

        if (!empty($rows)) {
            hd_debug_print("added all channels: " . count($rows) . " rows", true);
        }
    }

    /**
     * @param array $rows
     * @param array $headers
     * @return bool
     */
    private function get_regular_rows(&$rows, &$headers)
    {
        hd_debug_print(null, true);

        $action_enter = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);

        $fav_id = $this->plugin->get_fav_id();
        $fav_stickers = $this->get_fav_stickers();
        $fav_group = $this->plugin->get_channels_order($fav_id);
        $show_adult = $this->plugin->get_bool_setting(PARAM_SHOW_ADULT);
        $groups = $this->plugin->get_groups_by_order();
        $show_count = $this->plugin->get_bool_setting(PARAM_NEWUI_SHOW_CHANNEL_COUNT, false);

        foreach ($groups as $group_row) {
            if (!$show_adult && $group_row[COLUMN_ADULT] !== 0) continue;

            $group_id = $group_row[COLUMN_GROUP_ID];
            $items = array();
            foreach ($this->plugin->get_channels_by_order($group_id) as $channel_row) {
                if (!$show_adult && $channel_row[COLUMN_ADULT] !== 0) continue;

                $channel_id = $channel_row[COLUMN_CHANNEL_ID];
                $row_id = array('group_id' => $group_id, 'channel_id' => $channel_id);
                $items[] = Rows_Factory::add_regular_item(
                    self::row_id_encoder($row_id),
                    $this->plugin->get_channel_picon($channel_row, false),
                    $channel_row[COLUMN_TITLE],
                    in_array($channel_id, $fav_group) ? $fav_stickers : null
                );
            }

            if (empty($items)) continue;

            $title = $group_id;
            if ($show_count) {
                $title .= " (" . count($items) . ")";
            }

            $new_rows = $this->create_row($items, $headers, $group_id, $title, $group_id, $action_enter);

            foreach ($new_rows as $row) {
                $rows[] = $row;
            }
        }

        if (!empty($rows)) {
            hd_debug_print("added group channels: " . count($rows) . " rows", true);
            return true;
        }

        return false;
    }

    private function get_fav_stickers()
    {
        $rowItemsParams = $this->GetRowsItemsParamsClass();
        $fav_stickers[] = Rows_Factory::add_regular_sticker_rect(
            $rowItemsParams::fav_sticker_bg_color,
            Rows_Factory::r(
                $rowItemsParams::icon_width - $rowItemsParams::fav_sticker_bg_width,
                0,
                $rowItemsParams::fav_sticker_bg_width,
                $rowItemsParams::fav_sticker_bg_width
            )
        );

        $fav_stickers[] = Rows_Factory::add_regular_sticker_image(
            get_image_path($rowItemsParams::fav_sticker_icon_url),
            Rows_Factory::r(
                $rowItemsParams::icon_width - ($rowItemsParams::fav_sticker_bg_width + $rowItemsParams::fav_sticker_icon_width) / 2,
                ($rowItemsParams::fav_sticker_bg_height - $rowItemsParams::fav_sticker_icon_height) / 2,
                $rowItemsParams::fav_sticker_icon_width,
                $rowItemsParams::fav_sticker_icon_height
            )
        );

        return $fav_stickers;
    }

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array|null
     */
    private function do_get_info_children($media_url, $plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $fav_id = $this->plugin->get_fav_id();
        $group_id = safe_get_member($media_url, COLUMN_GROUP_ID);
        $channel_id = safe_get_member($media_url, COLUMN_CHANNEL_ID);
        $archive_tm = safe_get_member($media_url, 'archive_tm', -1);

        if (is_null($channel_id) || empty($group_id)) {
            return null;
        }

        $channel_row = $this->plugin->get_channel_info($channel_id);
        if (empty($channel_row)) {
            hd_debug_print("Unknown channel $channel_id");
            return null;
        }

        $title_num = 1;
        $defs = array();

        ///////////// Channel number /////////////////

        $pos = PaneParams::$ch_num_pos[$this->plugin->get_setting(PARAM_NEWUI_CHANNEL_POSITION, 0)];
        $defs[] = GComps_Factory::label(
            GComp_Geom::place_top_left(130, 50, $pos['x'], $pos['y']),
            null,
            $channel_row[COLUMN_CH_NUMBER],
            1,
            PaneParams::ch_num_font_color,
            PaneParams::ch_num_font_size,
            'ch_number'
        );

        ///////////// Channel title /////////////////

        $defs[] = GComps_Factory::label(
            GComp_Geom::place_top_left(PaneParams::info_width, PaneParams::prog_item_height),
            null,
            $channel_row[COLUMN_TITLE],
            1,
            PaneParams::ch_title_font_color,
            PaneParams::ch_title_font_size,
            'ch_title'
        );
        $next_pos_y = PaneParams::prog_item_height;

        ///////////// start_time, end_time, genre, country, person /////////////////

        $epg_data = $this->plugin->get_epg_info($channel_id, $archive_tm);
        $desc = safe_get_value($epg_data, PluginTvEpgProgram::description);
        if (!isset($epg_data[PluginTvEpgProgram::start_tm_sec])) {
            hd_debug_print("no epg data");
            if (!empty($desc)) {
                $geom = GComp_Geom::place_top_left(PaneParams::info_width, -1, 0, $next_pos_y);
                $defs[] = GComps_Factory::label($geom,
                    null,
                    $desc,
                    13 - $title_num,
                    PaneParams::prog_item_font_color,
                    PaneParams::prog_item_font_size,
                    'ch_desc',
                    array('line_spacing' => 6)
                );
            }
        } else {
            $program = (object)array();
            $program->time = sprintf("%s - %s",
                format_datetime('H:i', safe_get_value($epg_data, PluginTvEpgProgram::start_tm_sec)),
                format_datetime('H:i', safe_get_value($epg_data, PluginTvEpgProgram::end_tm_sec))
            );

            $sub_title = safe_get_value($epg_data, PluginTvExtEpgProgram::sub_title);
            if (!empty($sub_title)) {
                $geom = GComp_Geom::place_top_left(PaneParams::info_width, -1, 0, $next_pos_y);
                $defs[] = GComps_Factory::label($geom,
                    null,
                    $sub_title,
                    1,
                    PaneParams::prog_title_font_color,
                    PaneParams::prog_item_font_size,
                    'prog_sub_title');
                $next_pos_y += PaneParams::prog_item_height;
            }

            if (isset($epg_data[PluginTvEpgProgram::icon_url])) {
                $fanart_url = $epg_data[PluginTvEpgProgram::icon_url];
            }

            // duration
            $geom = GComp_Geom::place_top_left(PaneParams::info_width, PaneParams::prog_item_height, 0, $next_pos_y);
            $defs[] = GComps_Factory::label($geom,
                null,
                $program->time,
                1,
                PaneParams::prog_item_font_color,
                PaneParams::prog_item_font_size
            );
            $next_pos_y += PaneParams::prog_item_height;

            ///////////// Program title ////////////////

            $title = safe_get_value($epg_data, PluginTvEpgProgram::name);
            if (!empty($title)) {
                $lines = array_slice(explode("\n",
                    iconv('Windows-1251', 'UTF-8',
                        wordwrap(iconv('UTF-8', 'Windows-1251',
                            trim(preg_replace('/([!?])\.+\s*$/Uu', '$1', $title))),
                            40, "\n", true)
                    )),
                    0, 2);

                $prog_title = implode("\n", $lines);

                if (strlen($prog_title) < strlen($title)) {
                    $prog_title = $title;
                }

                $lines = min(2, count($lines));
                $geom = GComp_Geom::place_top_left(PaneParams::info_width + 100, PaneParams::prog_item_height, 0, $next_pos_y + ($lines > 1 ? 20 : 0));
                $defs[] = GComps_Factory::label($geom,
                    null,
                    $prog_title,
                    2,
                    PaneParams::prog_title_font_color,
                    PaneParams::prog_title_font_size,
                    'prog_title',
                    array('line_spacing' => 5)
                );
                $next_pos_y += (PaneParams::prog_item_height - 20) * $lines + ($lines > 1 ? 10 : 0);
                $title_num += $lines > 1 ? 1 : 0;
            } else {
                $title_num--;
            }

            ///////////// Description ////////////////

            if (!empty($desc)) {
                $geom = GComp_Geom::place_top_left(PaneParams::info_width, -1, 0, $next_pos_y + 5);
                $defs[] = GComps_Factory::label($geom,
                    null,
                    $desc,
                    10 - $title_num,
                    PaneParams::prog_item_font_color,
                    PaneParams::prog_item_font_size,
                    'prog_desc',
                    array('line_spacing' => 5)
                );
            }
        }

        // separator line
        $defs[] = GComps_Factory::get_rect_def(
            GComp_Geom::place_top_left(510, 4, 0, 590),
            null,
            PaneParams::separator_line_color
        );

        $dy_icon = 530;
        $dy_txt = $dy_icon - 4;
        $dx = 15;
        hd_debug_print("newUI: $group_id");
        $in_fav = $this->plugin->is_channel_in_order($fav_id, $channel_id);
        if ($group_id === TV_HISTORY_GROUP_ID || $group_id === TV_ALL_CHANNELS_GROUP_ID || $group_id === TV_CHANGED_CHANNELS_GROUP_ID) {

            // blue button image (D)
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_blue
            );

            $dx += 55;
            if ($group_id === TV_CHANGED_CHANNELS_GROUP_ID) {
                $btn_label = TR::t('clear_changed');
            } else {
                $btn_label = $in_fav ? TR::t('delete') : TR::t('add');
            }
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left(PaneParams::info_width, -1, $dx, $dy_txt), // label
                null,
                $btn_label,
                1,
                PaneParams::fav_btn_font_color,
                PaneParams::fav_btn_font_size
            );
        } else {
            $order = $this->plugin->get_channels_order($group_id);
            $is_first_channel = ($channel_id === reset($order));
            // green button image (B) 52x50
            $defs[] = GComps_Factory::get_image_def(
                GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_green,
                false,
                true,
                null,
                null,
                null,
                $is_first_channel ? 99 : 255
            );

            $dx += 55;
            // green button text
            $defs[] = GComps_Factory::label(
                GComp_Geom::place_top_left(PaneParams::info_width, -1, $dx, $dy_txt), // label
                null,
                (isset($plugin_cookies->toggle_move) && $plugin_cookies->toggle_move) ? TR::t('top') : TR::t('left'),
                1,
                $is_first_channel ? PaneParams::fav_btn_disabled_font_color : PaneParams::fav_btn_font_color,
                PaneParams::fav_btn_font_size
            );

            $is_last_channel = ($channel_id === end($order));
            $dx += 105;
            // yellow button image (C)
            $defs[] = GComps_Factory::get_image_def(
                GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_yellow,
                1,
                false,
                null,
                null,
                null,
                $is_last_channel ? 99 : 255
            );

            $dx += 55;
            // yellow button text
            $defs[] = GComps_Factory::label(
                GComp_Geom::place_top_left(PaneParams::info_width, -1, $dx, $dy_txt), // label
                null,
                (isset($plugin_cookies->toggle_move) && $plugin_cookies->toggle_move) ? TR::t('bottom') : TR::t('right'),
                1,
                $is_last_channel ? PaneParams::fav_btn_disabled_font_color : PaneParams::fav_btn_font_color,
                PaneParams::fav_btn_font_size
            );

            $dx += 105;
            // blue button image (D)
            $defs[] = GComps_Factory::get_image_def(
                GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_blue
            );

            $dx += 55;
            // blue button text
            $defs[] = GComps_Factory::label(
                GComp_Geom::place_top_left(PaneParams::info_width, -1, $dx, $dy_txt), // label
                null,
                $in_fav ? TR::t('delete') : TR::t('add'),
                1,
                PaneParams::fav_btn_font_color,
                PaneParams::fav_btn_font_size
            );
        }

        ///////////// Enclosing panel ////////////////

        $pane_def = GComps_Factory::get_panel_def('info_pane',
            GComp_Geom::place_top_left(PaneParams::pane_width, PaneParams::pane_height),
            null,
            $defs,
            GCOMP_OPT_PREPAINT
        );
        GComps_Factory::add_extra_var($pane_def, 'info_inf_dimmed', null, array('alpha' => 64));

        return array(
            'defs' => array($pane_def),
            'fanart_url' => empty($fanart_url) ? '' : $fanart_url,
            'adult' => $channel_row[COLUMN_ADULT],
        );
    }

    /**
     * @param object $user_input
     * @return array
     */
    private function do_popup_menu($user_input)
    {
        hd_debug_print(null, true);

        if (isset($user_input->selected_item_id)) {
            $media_url = self::row_id_decoder($user_input->selected_item_id);
        } else if (isset($user_input->selected_row_id)) {
            $media_url = self::row_id_decoder($user_input->selected_row_id);
        } else if (isset($user_input->item_id)) {
            $media_url = self::row_id_decoder($user_input->item_id);
        } else {
            $media_url = MediaURL::decode();
        }
        hd_debug_print($media_url, true);
        $fav_id = $this->plugin->get_fav_id();

        // show changing playlist and xmltv source in any place
        $menu_items = array();
        if (isset($user_input->{ACTION_CHANGE_EPG_SOURCE})) {
            $menu_items = $this->plugin->epg_source_menu($this);
        } else if (isset($user_input->{ACTION_EPG_CACHE_ENGINE})) {
            $menu_items = $this->plugin->epg_engine_menu($this);
        } else if (isset($user_input->{ACTION_SORT_POPUP})) {
            hd_debug_print("create sort menu", true);
            if (isset($media_url->group_id)) {
                hd_debug_print("sort group: $media_url->group_id", true);
                if ($this->plugin->get_group($media_url->group_id, PARAM_GROUP_ORDINARY) !== null) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_channels'),
                        null, array(ACTION_SORT_TYPE => ACTION_SORT_CHANNELS));
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_groups'),
                        null, array(ACTION_SORT_TYPE => ACTION_SORT_GROUPS));
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_channels_sort'),
                        null, array(ACTION_RESET_TYPE => ACTION_SORT_CHANNELS));
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_groups_sort'),
                        null, array(ACTION_RESET_TYPE => ACTION_SORT_GROUPS));
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_all_sort'),
                        null, array(ACTION_RESET_TYPE => ACTION_SORT_ALL));
                }
            }
        } else if (isset($user_input->selected_item_id)) {
            // popup menu for selected chennel in row
            hd_debug_print("in channels rows", true);
            if ($media_url->group_id === TV_HISTORY_GROUP_ID) {
                hd_debug_print("in history rows", true);
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_REMOVE, TR::t('delete'), "remove.png");
            } else if ($media_url->group_id === $fav_id && $this->plugin->is_full_size_remote()) {
                hd_debug_print("in favorites rows", true);
                $menu_items[] = $this->plugin->create_menu_item($this, PLUGIN_FAVORITES_OP_REMOVE, TR::t('delete_from_favorite'), "star.png");
            } else {
                hd_debug_print("Selected channel in row: $media_url->channel_id", true);
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_channel'), "remove.png");
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_EDIT_CHANNEL_DLG, TR::t('tv_screen_edit_channel'), "check.png");
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

                $menu_items[] = $this->plugin->create_menu_item($this, GUI_EVENT_KEY_INFO, TR::t('channel_info_dlg'), "info.png");
            }

            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
            $menu_items[] = $this->plugin->create_menu_item($this,
                ACTION_ITEMS_EDIT,
                TR::t('setup_channels_src_edit_playlists'),
                "m3u_file.png",
                array(CONTROL_ACTION_EDIT => Starnet_Edit_Playlists_Screen::SCREEN_EDIT_PLAYLIST));

            if (!$this->plugin->is_full_size_remote()) {
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                if ($media_url->group_id === $fav_id) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        PLUGIN_FAVORITES_OP_MOVE_UP, TR::t('left'), PaneParams::fav_button_green);
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        PLUGIN_FAVORITES_OP_MOVE_DOWN, TR::t('right'), PaneParams::fav_button_yellow);
                }

                $is_in_favorites = $this->plugin->is_channel_in_order($fav_id, $media_url->channel_id);
                $caption = $is_in_favorites ? TR::t('delete_from_favorite') : TR::t('add_to_favorite');
                $action = $is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $menu_items[] = $this->plugin->create_menu_item($this, $action, $caption, PaneParams::fav_button_blue);
            }
        } else {
            // popup menu for left side list
            hd_debug_print("in menu side", true);
            $refresh_menu = $this->plugin->refresh_playlist_menu($this);
            $group_id = safe_get_member($media_url, COLUMN_GROUP_ID);
            $menu_items = array_merge($refresh_menu, $menu_items, $this->plugin->common_categories_menu($this, $group_id, false));
        }

        return $menu_items;
    }

    private function GetRowsItemsParamsClass()
    {
        $suff = $this->show_caption ? "" : "n";
        return 'RowsItemsParams' . $this->channels_in_row . $suff;
    }

    private function GetRowsItemsParams($param_name)
    {
        $rClass = new ReflectionClass('RowsItemsParams');
        $array = $rClass->getConstants();

        $sq_param = $param_name . ($this->square_icons ? '_sq' : '');

        return (isset($array[$sq_param])) ? $array[$sq_param] : $array[$param_name];
    }

    private function update_new_ui_settings()
    {
        $this->show_caption = $this->plugin->get_bool_setting(PARAM_NEWUI_SHOW_CHANNEL_CAPTION);
        $this->channels_in_row = $this->plugin->get_setting(PARAM_NEWUI_ICONS_IN_ROW, 7);
        $this->square_icons = $this->plugin->get_bool_setting(PARAM_NEWUI_SQUARE_ICONS, false);
        $this->show_continues = $this->plugin->get_bool_setting(PARAM_NEWUI_SHOW_CONTINUES);
    }

    private function action_to_direction($action)
    {
        switch ($action) {
            case ACTION_ITEM_UP:
                $direction = Ordered_Array::UP;
                break;
            case ACTION_ITEM_DOWN:
                $direction = Ordered_Array::DOWN;
                break;
            case ACTION_ITEM_TOP:
                $direction = Ordered_Array::TOP;
                break;
            case ACTION_ITEM_BOTTOM:
                $direction = Ordered_Array::BOTTOM;
                break;
            default:
                $direction = null;
                break;
        }

        return $direction;
    }

    /**
     * @param string $item_id
     * @return MediaURL|array
     */
    protected static function row_id_decoder($item_id, $assoc = false)
    {
        $vars = array();
        foreach(explode(';', $item_id) as $part) {
            $ar = explode(':', $part);
            if (count($ar) == 2) {
                $vars[$ar[0]] = $ar[1];
            }
        }

        return $assoc ? $vars : MediaURL::make($vars);
    }

    /**
     * @param array $items
     * @return string
     */
    protected static function row_id_encoder($items)
    {
        return implode(';',
            array_map(function($k, $v) {
                return $k . ':' . $v;
                },
                array_keys($items), array_values($items)
            )
        );
    }
}
