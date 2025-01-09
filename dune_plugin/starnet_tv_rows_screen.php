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

require_once 'lib/playback_points.php';
require_once 'lib/epg/ext_epg_program.php';

require_once 'lib/epfs/abstract_rows_screen.php';
require_once 'lib/epfs/rows_factory.php';
require_once 'lib/epfs/gcomps_factory.php';
require_once 'lib/epfs/gcomp_geom.php';

class Starnet_Tv_Rows_Screen extends Abstract_Rows_Screen implements User_Input_Handler
{
    const ID = 'rows_epf';

    ///////////////////////////////////////////////////////////////////////////
    public $need_update_epf_mapping_flag = false;
    private $removed_playback_point;
    private $clear_playback_points = false;

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (isset($user_input->item_id)) {
            $media_url_str = $user_input->item_id;
            $media_url = MediaURL::decode($media_url_str);
        } else if ($user_input->control_id === ACTION_REFRESH_SCREEN) {
            $media_url = '';
            $media_url_str = '';
        } else {
            $media_url = $this->get_parent_media_url($user_input->parent_sel_state);
            $media_url_str = '';
            if (is_null($media_url)) {
                return null;
            }
        }

        $reload_action = User_Input_Handler_Registry::create_action($this,
            ACTION_RELOAD,
            null,
            array('reload_action' => Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST)
        );

        $control_id = $user_input->control_id;

        switch ($control_id) {
            case GUI_EVENT_TIMER:
                // rising after playback end + 100 ms
                $this->plugin->get_playback_points()->update_point(null);
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case GUI_EVENT_KEY_PLAY:
            case GUI_EVENT_KEY_ENTER:
                $tv_play_action = Action_Factory::tv_play($user_input->selected_item_id);

                if (isset($user_input->action_origin)) {
                    return Action_Factory::close_and_run(Starnet_Epfs_Handler::epfs_invalidate_folders(null, $tv_play_action));
                }

                $new_actions = array_merge(
                    $this->get_action_map($media_url, $plugin_cookies),
                    array(GUI_EVENT_TIMER => User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER)));

                return Action_Factory::change_behaviour($new_actions, 100, $tv_play_action);

            case GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE:
                if (!isset($user_input->item_id, $user_input->folder_key)) {
                    return null;
                }

                $info_children = $this->do_get_info_children(MediaURL::decode($media_url_str), $plugin_cookies);

                return Action_Factory::update_rows_info(
                    $user_input->folder_key,
                    $user_input->item_id,
                    $info_children['defs'],
                    empty($info_children['fanart_url']) ? get_image_path(PaneParams::vod_bg_url) : $info_children['fanart_url'],
                    get_image_path(PaneParams::vod_bg_url),
                    get_image_path(PaneParams::vod_mask_url),
                    array("plugin_tv://" . get_plugin_name() . "/$user_input->item_id")
                );

            case ACTION_SORT_POPUP:
                hd_debug_print("Start event popup menu for playlist");
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_SORT_POPUP => true));

            case GUI_EVENT_KEY_POPUP_MENU:
                return Action_Factory::show_popup_menu($this->do_popup_menu($user_input));

            case ACTION_ZOOM_POPUP_MENU:
                $menu_items = array();
                $zoom_data = $this->plugin->tv->get_channel_zoom($media_url->channel_id);
                foreach (DuneVideoZoomPresets::$zoom_ops_translated as $idx => $zoom_item) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_ZOOM_APPLY,
                        TR::load_string($zoom_item),
                        (strcmp($idx, $zoom_data) !== 0 ? null : "check.png"),
                        array(ACTION_ZOOM_SELECT => (string)$idx)
                    );
                }

                return Action_Factory::show_popup_menu($menu_items);

            case PLUGIN_FAVORITES_OP_ADD:
            case PLUGIN_FAVORITES_OP_REMOVE:
                if (!isset($media_url->group_id) || $media_url->group_id === HISTORY_GROUP_ID) break;

                if ($media_url->group_id === CHANGED_CHANNELS_GROUP_ID) {
                    $known_order = &$this->plugin->tv->get_known_channels();
                    $known_order->erase($media_url->channel_id);
                    $channel = $this->plugin->tv->get_channel($media_url->channel_id);
                    if (!is_null($channel)) {
                        $known_order->set($channel->get_id(), $channel->get_title());
                    }
                    $this->set_changes();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($this->plugin->tv->get_channel($media_url->channel_id) === null) break;

                $is_in_favorites = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID)->in_items_order($media_url->channel_id);
                $control_id = $is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;

                if ($this->plugin->tv->change_tv_favorites($control_id, $media_url->channel_id) === null) break;

                $this->set_changes();

                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_ITEM_TOGGLE_MOVE:
                $plugin_cookies->toggle_move = !$plugin_cookies->toggle_move;
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_ITEM_UP:
            case ACTION_ITEM_DOWN:
            case ACTION_ITEM_TOP:
            case ACTION_ITEM_BOTTOM:
                if (!isset($media_url->group_id)
                    || $media_url->group_id === HISTORY_GROUP_ID
                    || $media_url->group_id === ALL_CHANNEL_GROUP_ID
                    || $media_url->group_id === CHANGED_CHANNELS_GROUP_ID
                ) break;

                switch ($control_id) {
                    case ACTION_ITEM_UP:
                        $control_id = PLUGIN_FAVORITES_OP_MOVE_UP;
                        $direction = Ordered_Array::UP;
                        break;
                    case ACTION_ITEM_DOWN:
                        $control_id = PLUGIN_FAVORITES_OP_MOVE_DOWN;
                        $direction = Ordered_Array::DOWN;
                        break;
                    case ACTION_ITEM_TOP:
                        $direction = Ordered_Array::TOP;
                        break;
                    case ACTION_ITEM_BOTTOM:
                        $direction = Ordered_Array::BOTTOM;
                        break;
                    default:
                        return null;
                }

                if (!isset($user_input->selected_item_id) && $this->plugin->tv->get_groups_order()->arrange_item($media_url->group_id, $direction)) {
                    $this->set_changes();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($media_url->group_id === FAVORITES_GROUP_ID) {
                    if ($this->plugin->tv->change_tv_favorites($control_id, $media_url->channel_id) === null) {
                        break;
                    }

                    $this->set_changes();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                $group = $this->plugin->tv->get_group($media_url->group_id);
                if (is_null($group) || !$group->get_items_order()->arrange_item($media_url->channel_id, $direction)) {
                    break;
                }

                $this->set_changes();
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_ITEMS_SORT:
                $group = $this->plugin->tv->get_group($media_url->group_id);
                if (is_null($group) || !isset($user_input->{ACTION_SORT_TYPE})) {
                    return null;
                }

                if ($user_input->{ACTION_SORT_TYPE} === ACTION_SORT_CHANNELS) {
                    $group->sort_group_items();
                } else {
                    $this->plugin->tv->get_groups_order()->sort_order();
                }
                $this->set_changes();
                return $reload_action;

            case ACTION_RESET_ITEMS_SORT:
                if (!isset($user_input->{ACTION_RESET_TYPE})) {
                    return null;
                }

                /** @var Default_Channel $channel */
                if ($user_input->{ACTION_RESET_TYPE} === ACTION_SORT_CHANNELS) {
                    if (!is_null($sel_group = $this->plugin->tv->get_group($media_url->group_id))) {
                        $sel_group->sort_group_items(true);
                        $this->set_changes();
                    }
                } else if ($user_input->{ACTION_RESET_TYPE} === ACTION_SORT_GROUPS) {
                    $order = &$this->plugin->tv->get_groups_order();
                    $order->clear();
                    foreach ($this->plugin->tv->get_enabled_groups() as $group) {
                        $order->add_item($group->get_id());
                    }
                    $this->set_changes();
                } else if ($user_input->{ACTION_RESET_TYPE} === ACTION_SORT_ALL) {
                    $order = &$this->plugin->tv->get_groups_order();
                    $order->clear();
                    foreach ($this->plugin->tv->get_enabled_groups() as $group) {
                        $order->add_item($group->get_id());
                        $group->sort_group_items();
                    }
                    $this->set_changes();
                }

                return $reload_action;

            case ACTION_ITEM_REMOVE:
                $this->removed_playback_point = $media_url->get_raw_string();
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_ITEMS_CLEAR:
                hd_debug_print($media_url, true);
                if ($media_url->group_id === HISTORY_GROUP_ID) {
                    $this->clear_playback_points = true;
                    $this->plugin->get_playback_points()->clear_points();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($media_url->group_id === FAVORITES_GROUP_ID) {
                    $this->set_changes();
                    if ($this->plugin->tv->change_tv_favorites(ACTION_ITEMS_CLEAR, null) === null) {
                        break;
                    }
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($media_url->group_id === CHANGED_CHANNELS_GROUP_ID) {
                    $this->set_changes();
                    $all_channels = $this->plugin->tv->get_channels();
                    $order = &$this->plugin->tv->get_known_channels();
                    $this->plugin->tv->get_special_group(CHANGED_CHANNELS_GROUP_ID)->set_disabled(true);
                    $order->clear();
                    /** @var Default_Channel $channel */
                    foreach ($all_channels as $channel) {
                        $order->set($channel->get_id(), $channel->get_title());
                    }
                    $this->set_changes();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }
                break;

            case ACTION_ITEM_DELETE:
                if (!isset($user_input->selected_item_id)) {
                    $this->plugin->tv->disable_group($media_url->group_id);
                    $this->set_changes();
                } else if ($this->plugin->tv->disable_channel($media_url->channel_id, true)) {
                    $this->set_changes();
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_CHANGE_EPG_SOURCE:
                hd_debug_print("Start event popup menu for epg source");
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_EPG_SOURCE => true));

            case ACTION_EPG_SOURCE_SELECTED:
                if (!isset($user_input->{LIST_IDX}) || $this->plugin->get_setting(PARAM_EPG_CACHE_ENGINE) !== ENGINE_JSON) break;

                $epg_manager = $this->plugin->get_epg_manager();

                if ($epg_manager === null) {
                    return Action_Factory::show_title_dialog(TR::t('err_epg_manager'));
                }
                $epg_manager->clear_current_epg_cache();
                $this->plugin->set_setting(PARAM_EPG_JSON_PRESET, $user_input->{LIST_IDX});
                return $reload_action;

            case ACTION_EPG_CACHE_ENGINE:
                hd_debug_print("Start event popup menu for epg source", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_EPG_CACHE_ENGINE => true));

            case ACTION_CHANGE_PICONS_SOURCE:
                hd_debug_print("Start event popup menu for picons source", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_PICONS_SOURCE => true));

            case ENGINE_XMLTV:
            case ENGINE_JSON:
                if ($this->plugin->get_setting(PARAM_EPG_CACHE_ENGINE) !== $user_input->control_id) {
                    hd_debug_print("Selected engine: $user_input->control_id", true);
                    $this->plugin->tv->unload_channels();
                    $this->plugin->set_setting(PARAM_EPG_CACHE_ENGINE, $user_input->control_id);
                    $this->plugin->init_epg_manager();
                    return $reload_action;
                }
                break;

            case PLAYLIST_PICONS:
            case XMLTV_PICONS:
            case COMBINED_PICONS:
                if ($this->plugin->get_setting(PARAM_USE_PICONS) !== $user_input->control_id) {
                    hd_debug_print("Selected icons source: $user_input->control_id", true);
                    $this->plugin->tv->unload_channels();
                    $this->plugin->set_setting(PARAM_USE_PICONS, $user_input->control_id);
                    $this->plugin->init_epg_manager();
                    return $reload_action;
                }
                break;

            case ACTION_ITEMS_EDIT:
                $this->save_if_changed();
                return $this->plugin->do_edit_list_screen(self::ID, $user_input->action_edit);

            case ACTION_SETTINGS:
                $this->save_if_changed();
                return $this->plugin->show_protect_settings_dialog($this, ACTION_DO_SETTINGS);

            case ACTION_DO_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case ACTION_ZOOM_APPLY:
                $channel_id = $media_url->channel_id;
                if (isset($user_input->{ACTION_ZOOM_SELECT})) {
                    $zoom_select = $user_input->{ACTION_ZOOM_SELECT};
                    $this->plugin->tv->set_channel_zoom($channel_id, ($zoom_select !== DuneVideoZoomPresets::not_set) ? $zoom_select : null);
                }
                break;

            case ACTION_EXTERNAL_PLAYER:
            case ACTION_INTERNAL_PLAYER:
                $this->plugin->tv->set_channel_for_ext_player($media_url->channel_id, $user_input->control_id === ACTION_EXTERNAL_PLAYER);
                break;

            case ACTION_INFO_DLG:
                return $this->plugin->do_show_subscription($this);

            case ACTION_ADD_MONEY_DLG:
                return $this->plugin->do_show_add_money();

            case ACTION_EDIT_PROVIDER_DLG:
            case ACTION_EDIT_PROVIDER_EXT_DLG:
                $this->save_if_changed();
                return $this->plugin->show_protect_settings_dialog($this,
                    ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG)
                        ? ACTION_DO_EDIT_PROVIDER
                        : ACTION_DO_EDIT_PROVIDER_EXT);

            case ACTION_DO_EDIT_PROVIDER:
            case ACTION_DO_EDIT_PROVIDER_EXT:
                if ($user_input->control_id === ACTION_DO_EDIT_PROVIDER) {
                    $provider = $this->plugin->get_current_provider();
                    if (is_null($provider)) {
                        return null;
                    }
                    return $this->plugin->do_edit_provider_dlg($this, $provider->getId(), $provider->get_provider_playlist_id());
                }

                return $this->plugin->do_edit_provider_ext_dlg($this);

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
            case ACTION_EDIT_PROVIDER_EXT_DLG_APPLY:
                $this->set_no_changes();
                if ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG_APPLY) {
                    $id = $this->plugin->apply_edit_provider_dlg($user_input);
                } else {
                    $id = $this->plugin->apply_edit_provider_ext_dlg($user_input);
                }

                if ($id === false) {
                    return null;
                }

                if (is_array($id)) {
                    return $id;
                }

                return $reload_action;

            case GUI_EVENT_KEY_INFO:
                if (isset($media_url->channel_id)) {
                    return $this->plugin->do_show_channel_info($media_url->channel_id);
                }
                return null;

            case ACTION_RELOAD:
                hd_debug_print("Action reload", true);
                $this->save_if_changed();
                $force = false;
                if (isset($user_input->reload_action)) {
                    if ($user_input->reload_action === Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST) {
                        $force = true;
                    } else if ($user_input->reload_action === Starnet_Edit_List_Screen::SCREEN_EDIT_EPG_LIST) {
                        $epg_manager = $this->plugin->get_epg_manager();
                        if ($epg_manager === null) {
                            return Action_Factory::show_title_dialog(TR::t('err_epg_manager'));
                        }

                        $epg_manager->clear_current_epg_cache();
                        $res = $epg_manager->get_indexer()->download_xmltv_source();
                        if ($res === -1) {
                            return Action_Factory::show_title_dialog(TR::t('err_load_xmltv_epg'),
                                null,
                                HD::get_last_error("xmltv_last_error"));
                        }
                    }
                }

                if ($this->plugin->tv->reload_channels($plugin_cookies, $force) === 0) {
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error());
                    $post_action = Action_Factory::close_and_run(
                        Action_Factory::open_folder(self::ID, $this->plugin->create_plugin_title(), null, null, $post_action));

                    return Starnet_Epfs_Handler::epfs_invalidate_folders(null, $post_action);
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_REFRESH_SCREEN:
                if ($this->has_changes()) {
                    $this->plugin->save_orders(true);
                    $this->set_no_changes();
                }

                return Action_Factory::invalidate_all_folders($plugin_cookies);
        }

        return null;
    }

    /**
     * @param string $parent_sel_state
     * @return MediaURL|null
     */
    public function get_parent_media_url($parent_sel_state)
    {
        foreach (explode("\n", $parent_sel_state) as $line) {
            if (strpos($line, 'channel_id')) {
                return MediaURL::decode($line);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
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
        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, PLUGIN_FAVORITES_OP_ADD);
        $actions[GUI_EVENT_KEY_INFO] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_INFO);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE);
        $actions[GUI_EVENT_KEY_SELECT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE);

        return $actions;
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
     * @param Object $plugin_cookies
     * @return array|null
     */
    public function get_folder_view_for_epf(&$plugin_cookies)
    {
        hd_debug_print(null, true);

        if ($this->plugin->tv->load_channels($plugin_cookies) === 0) {
            hd_debug_print("Channels not loaded!");
        }

        return $this->get_folder_view(MediaURL::decode(static::ID), $plugin_cookies);
    }

    /**
     * @inheritDoc
     */
    public function get_rows_pane(MediaURL $media_url, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $rows = $this->create_rows(array(), json_encode(array('group_id' => '__dummy__row__')), '', '', null );

        $history_rows = $this->get_history_rows($plugin_cookies);
        if (!is_null($history_rows)) {
            hd_debug_print("added history: " . count($history_rows) . " rows", true);
            $rows = array_merge($rows, $history_rows);
        }

        $favorites_rows = $this->get_favorites_rows();
        if (!is_null($favorites_rows)) {
            hd_debug_print("added favorites: " . count($favorites_rows) . " rows", true);
            $rows = array_merge($rows, $favorites_rows);
        }

        $changed_rows = $this->get_changed_channels_rows();
        if (!is_null($changed_rows)) {
            hd_debug_print("added changed channels: " . count($changed_rows) . " rows", true);
            $rows = array_merge($rows, $changed_rows);
        }

        $all_channels_rows = $this->get_all_channels_row();
        if (!is_null($all_channels_rows)) {
            hd_debug_print("added all channels: " . count($all_channels_rows) . " rows", true);
            $rows = array_merge($rows, $all_channels_rows);
        }

        $category_rows = $this->get_regular_rows();
        if (is_null($category_rows)) {
            hd_debug_print("no category rows");
            return null;
        }

        $rows = array_merge($rows, $category_rows);
        hd_debug_print("added group channels: " . count($category_rows) . " rows", true);

        $pane = Rows_Factory::pane(
            $rows,
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
        $icon_prop = $this->GetRowsItemsParams('icon_prop');
        $width = RowsParams::width / $rowItemsParams::items_in_row;

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

        $width_inactive = RowsParams::inactive_width / $rowItemsParams::items_in_row;
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
            get_image_path($this->GetRowsItemsParams('icon_loading_url')),
            get_image_path($this->GetRowsItemsParams('icon_loading_failed_url')),
            $rowItemsParams::caption_max_num_lines,
            $rowItemsParams::caption_line_spacing,
            Rows_Factory::margins(6, 2, 2, 2)
        );

        Rows_Factory::set_item_params_template($pane, 'common', $params);

        return $pane;
    }

    /**
     * @param array $items
     * @param string $row_id
     * @param string $title
     * @param string $caption
     * @param array|null $action
     * @param string|null $color
     * @return array
     */
    private function create_rows($items, $row_id, $title, $caption, $action, $color = null)
    {
        $rows = array();
        $rows[] = Rows_Factory::title_row(
            $row_id,
            $caption,
            $row_id,
            TitleRowsParams::width,
            TitleRowsParams::height,
            is_null($color) ? TitleRowsParams::def_caption_color : $color,
            TitleRowsParams::font_size,
            TitleRowsParams::left_padding,
            0,
            0,
            true,
            TitleRowsParams::fade_color,
            TitleRowsParams::lite_fade_color
        );

        $rowItemsParams = $this->GetRowsItemsParamsClass();
        $icon_prop = $this->GetRowsItemsParams('icon_prop');
        $height = RowsParams::width / $rowItemsParams::items_in_row * $icon_prop;
        $inactive_height = RowsParams::inactive_width / $rowItemsParams::items_in_row * $icon_prop;
        for ($i = 0, $iMax = count($items); $i < $iMax; $i += $rowItemsParams::items_in_row) {
            $row_items = array_slice($items, $i, $rowItemsParams::items_in_row);
            $id = json_encode(array('row_ndx' => (int)($i / $rowItemsParams::items_in_row), 'row_id' => $row_id));
            $rows[] = Rows_Factory::regular_row(
                $id,
                $row_items,
                'common',
                null,
                $title,
                $row_id,
                RowsParams::full_width,
                $height,
                $inactive_height,
                RowsParams::left_padding,
                RowsParams::inactive_left_padding,
                RowsParams::right_padding,
                false,
                false,
                true,
                null,
                $action,
                RowsParams::fade_icon_mix_color,
                RowsParams::fade_icon_mix_alpha,
                RowsParams::lite_fade_icon_mix_alpha,
                RowsParams::fade_caption_color
            );
        }

        return $rows;
    }

    /**
     * @param Object $plugin_cookies
     * @return array|null
     */
    private function get_history_rows($plugin_cookies)
    {
        hd_debug_print(null, true);
        if (!$this->plugin->get_bool_setting(PARAM_SHOW_HISTORY)) {
            hd_debug_print("History group disabled");
            return null;
        }

        if ($this->clear_playback_points) {
            $this->clear_playback_points = false;
            return null;
        }

        // Fill view history data
        $now = time();
        $rows = array();
        $watched = array();
        $playback_points = $this->plugin->get_playback_points();
        if ($playback_points !== null) {
            foreach ($playback_points->get_all() as $channel_id => $channel_ts) {
                if (is_null($channel = $this->plugin->tv->get_channel($channel_id))) continue;

                $prog_info = $this->plugin->get_program_info($channel_id, $channel_ts, $plugin_cookies);
                $progress = 0;

                if (is_null($prog_info)) {
                    $title = $channel->get_title();
                } else {
                    // program epg available
                    $title = $prog_info[PluginTvEpgProgram::name];
                    if ($channel_ts > 0) {
                        $start_tm = $prog_info[PluginTvEpgProgram::start_tm_sec];
                        $epg_len = $prog_info[PluginTvEpgProgram::end_tm_sec] - $start_tm;
                        if ($channel_ts >= $now - $channel->get_archive_past_sec() - 60) {
                            $progress = max(0.01, min(1.0, round(($channel_ts - $start_tm) / $epg_len, 2)));
                        }
                    }
                }

                $watched[(string)$channel_id] = array(
                    'channel_id' => $channel_id,
                    'archive_tm' => $channel_ts,
                    'view_progress' => $progress,
                    'program_title' => $title,
                );
            }
        }

        // fill view history row items
        $rowItemsParams = $this->GetRowsItemsParamsClass();
        $icon_prop = $this->GetRowsItemsParams('icon_prop');
        $sticker_y = $rowItemsParams::icon_width * $icon_prop - $rowItemsParams::view_progress_height;
        $items = array();
        foreach ($watched as $item) {
            $channel = $this->plugin->tv->get_channel($item['channel_id']);
            if ($channel === null) continue;

            $id = json_encode(array('group_id' => HISTORY_GROUP_ID, 'channel_id' => $item['channel_id'], 'archive_tm' => $item['archive_tm']));
            if (isset($this->removed_playback_point) && $this->removed_playback_point === $id) {
                $this->removed_playback_point = null;
                $this->plugin->get_playback_points()->erase_point($item['channel_id']);
                continue;
            }

            $stickers = null;

            if ($item['view_progress'] > 0) {
                // item size 229x142
                if (!empty($item['program_icon_url'])) {
                    // add small channel logo
                    $rect = Rows_Factory::r(129, 0, 100, 64);
                    $stickers[] = Rows_Factory::add_regular_sticker_rect($rowItemsParams::fav_sticker_logo_bg_color, $rect);
                    $stickers[] = Rows_Factory::add_regular_sticker_image($channel->get_icon_url(), $rect);
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

            $items[] = Rows_Factory::add_regular_item($id, $channel->get_icon_url(), $item['program_title'], $stickers);
        }

        // create view history group
        if (!empty($items)) {
            $new_rows = $this->create_rows($items,
                json_encode(array('group_id' => HISTORY_GROUP_ID)),
                TR::t('tv_screen_continue'),
                TR::t('tv_screen_continue_view'),
                User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
                TitleRowsParams::history_caption_color
            );

            $rows = safe_merge_array($rows, $new_rows);
        }

        return $rows;
    }

    /**
     * @return array|null
     */
    private function get_favorites_rows()
    {
        hd_debug_print(null, true);

        $group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
        if (is_null($group)) {
            hd_debug_print("Favorites group not found");
            return null;
        }

        if ($group->is_disabled()) {
            hd_debug_print("Favorites group disabled");
            return null;
        }

        foreach ($group->get_items_order() as $channel_id) {
            $channel = $this->plugin->tv->get_channel($channel_id);
            if (is_null($channel) || $channel->is_disabled()) continue;

            $items[] = Rows_Factory::add_regular_item(
                json_encode(array('group_id' => FAVORITES_GROUP_ID, 'channel_id' => $channel_id)),
                $channel->get_icon_url(),
                $channel->get_title()
            );
        }

        if (empty($items)) {
            return null;
        }

        return $this->create_rows($items,
            json_encode(array('group_id' => FAVORITES_GROUP_ID)),
            $group->get_title(),
            $group->get_title(),
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
            TitleRowsParams::fav_caption_color
        );
    }

    /**
     * @return array|null
     */
    private function get_changed_channels_rows()
    {
        hd_debug_print(null, true);

        $group = $this->plugin->tv->get_special_group(CHANGED_CHANNELS_GROUP_ID);
        if (is_null($group)) {
            hd_debug_print("Changed channels group not found");
            return null;
        }

        if ($group->is_disabled()) {
            hd_debug_print("Changed channels group disabled");
            return null;
        }

        $changed = $this->plugin->tv->get_changed_channels_ids();
        if (empty($changed)) {
            return null;
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

        $new_channels = $this->plugin->tv->get_changed_channels_ids('new');
        /** @var Default_Channel $channel */
        foreach ($this->plugin->tv->get_filtered_channels($new_channels) as $channel) {
            if (is_null($channel) || $channel->is_disabled()) continue;

            $items[] = Rows_Factory::add_regular_item(
                json_encode(array('group_id' => CHANGED_CHANNELS_GROUP_ID, 'channel_id' => $channel->get_id())),
                $channel->get_icon_url(),
                $channel->get_title(),
                $added_stickers
            );
        }

        $removed_channels = $this->plugin->tv->get_changed_channels_ids('removed');
        $failed_url = $this->GetRowsItemsParams('icon_loading_failed_url');
        foreach ($removed_channels as $item) {
            $items[] = Rows_Factory::add_regular_item(
                json_encode(array('group_id' => CHANGED_CHANNELS_GROUP_ID, 'channel_id' => $item)),
                $failed_url,
                $this->plugin->tv->get_known_channels()->get($item),
                $removed_stickers
            );
        }

        if (empty($items)) {
            return null;
        }

        return $this->create_rows($items,
            json_encode(array('group_id' => CHANGED_CHANNELS_GROUP_ID)),
            $group->get_title(),
            $group->get_title(),
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
            TitleRowsParams::fav_caption_color
        );
    }

    /**
     * @return array|null
     */
    private function get_all_channels_row()
    {
        hd_debug_print(null, true);

        $all_channels_group = $this->plugin->tv->get_special_group(ALL_CHANNEL_GROUP_ID);
        if (is_null($all_channels_group)) {
            hd_debug_print("All channels group not found");
            return null;
        }

        if ($all_channels_group->is_disabled()) {
            hd_debug_print("All channels group disabled");
            return null;
        }

        $fav_stickers = $this->get_fav_stickers();

        $channels_order = $all_channels_group->get_group_enabled_channels();

        $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);

        $items = array();
        foreach ($channels_order as $channel) {
            $items[] = Rows_Factory::add_regular_item(
                json_encode(array('group_id' => ALL_CHANNEL_GROUP_ID, 'channel_id' => $channel->get_id())),
                $channel->get_icon_url(),
                $channel->get_title(),
                $fav_group->in_items_order($channel->get_id()) ? $fav_stickers : null
            );
        }

        if (empty($items)) {
            return null;
        }

        return $this->create_rows($items,
            json_encode(array('group_id' => ALL_CHANNEL_GROUP_ID)),
            $all_channels_group->get_title(),
            $all_channels_group->get_title(),
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER)
        );
    }

    /**
     * @return array|null
     */
    private function get_regular_rows()
    {
        hd_debug_print(null, true);

        $action_enter = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);

        $fav_stickers = $this->get_fav_stickers();

        $rows = array();
        $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
        /** @var Default_Group $group */
        /** @var Default_Channel $channel */
        $groups = $this->plugin->tv->get_groups()->filter_keys($this->plugin->tv->get_groups_order()->get_order());
        foreach ($groups as $group) {
            if (is_null($group)) continue;

            $group_id = $group->get_id();
            $items = array();
            foreach ($group->get_items_order() as $channel_id) {
                $channel = $this->plugin->tv->get_channel($channel_id);
                if (is_null($channel) || $channel->is_disabled()) continue;

                $items[] = Rows_Factory::add_regular_item(
                    json_encode(array('group_id' => $group_id, 'channel_id' => $channel->get_id())),
                    $channel->get_icon_url(),
                    $channel->get_title(),
                    $fav_group->in_items_order($channel->get_id()) ? $fav_stickers : null
                );
            }

            if (empty($items)) continue;

            $new_rows = $this->create_rows($items,
                json_encode(array('group_id' => $group_id)),
                $group->get_title(),
                $group->get_title(),
                $action_enter
            );

            foreach ($new_rows as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
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
     * @param Object $plugin_cookies
     * @return array|null
     */
    private function do_get_info_children($media_url, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $group_id = isset($media_url->group_id) ? $media_url->group_id : null;
        $channel_id = isset($media_url->channel_id) ? $media_url->channel_id : null;
        $archive_tm = isset($media_url->archive_tm) ? $media_url->archive_tm : -1;

        if (is_null($channel_id) || empty($group_id)) {
            return null;
        }

        $channel = $this->plugin->tv->get_channel($channel_id);
        if (is_null($channel)) {
            hd_debug_print("Unknown channel $channel_id");
            return null;
        }

        $title_num = 1;
        $defs = array();

        ///////////// Channel number /////////////////

        $pos = PaneParams::$ch_num_pos[$this->plugin->get_setting(PARAM_CHANNEL_POSITION, 0)];
        $defs[] = GComps_Factory::label(
            GComp_Geom::place_top_left(130, 50, $pos['x'], $pos['y']),
            null,
            $channel->get_number(),
            1,
            PaneParams::ch_num_font_color,
            PaneParams::ch_num_font_size,
            'ch_number'
        );

        ///////////// Channel title /////////////////

        $defs[] = GComps_Factory::label(
            GComp_Geom::place_top_left(PaneParams::info_width, PaneParams::prog_item_height),
            null,
            $channel->get_title(),
            1,
            PaneParams::ch_title_font_color,
            PaneParams::ch_title_font_size,
            'ch_title'
        );
        $next_pos_y = PaneParams::prog_item_height;

        ///////////// start_time, end_time, genre, country, person /////////////////

        $epg_data = $this->plugin->get_program_info($channel_id, $archive_tm, $plugin_cookies);
        if (is_null($epg_data)) {
            hd_debug_print("no epg data");
            $channel_desc = $channel->get_desc();
            if (!empty($channel_desc)) {
                $geom = GComp_Geom::place_top_left(PaneParams::info_width, -1, 0, $next_pos_y);
                $defs[] = GComps_Factory::label($geom,
                    null,
                    $channel_desc,
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
                gmdate('H:i', $epg_data[PluginTvEpgProgram::start_tm_sec] + get_local_time_zone_offset()),
                gmdate('H:i', $epg_data[PluginTvEpgProgram::end_tm_sec] + get_local_time_zone_offset())
            );
            //$program->year = preg_match('/\s+\((\d{4,4})\)$/', $epg_data[Ext_Epg_Program::main_category], $matches) ? $matches[1] : '';
            //$program->age = preg_match('/\s+\((\d{1,2}\+)\)$/', $epg_data[Ext_Epg_Program::main_category], $matches) ? $matches[1] : '';

            $title = $epg_data[PluginTvEpgProgram::name];
            $desc = (!empty($epg_data[Ext_Epg_Program::sub_title]) ? $epg_data[Ext_Epg_Program::sub_title] . "\n" : '') . $epg_data[PluginTvEpgProgram::description];
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

            if (!empty($title)) {
                $lines = array_slice(explode("\n",
                    iconv('Windows-1251', 'UTF-8',
                        wordwrap(iconv('UTF-8', 'Windows-1251',
                            trim(preg_replace('/([!?])\.+\s*$/Uu', '$1', $title))),
                            40, "\n", true)
                    )),
                    0, 2);

                $prog_title = implode("\n", $lines);

                if (strlen($prog_title) < strlen($title))
                    $prog_title = $title;

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
        $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
        $in_fav = $fav_group->in_items_order($channel_id);
        if ($group_id === HISTORY_GROUP_ID || $group_id === ALL_CHANNEL_GROUP_ID || $group_id === CHANGED_CHANNELS_GROUP_ID) {

            // blue button image (D)
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_blue
            );

            $dx += 55;
            if ($group_id === CHANGED_CHANNELS_GROUP_ID) {
                $btn_label = TR::load_string('clear_changed');
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
            /** @var Default_Group $group */
            if ($group_id === FAVORITES_GROUP_ID) {
                $group = $this->plugin->tv->get_special_group($group_id);
            } else {
                $group = $this->plugin->tv->get_group($group_id);
            }

            $order = $group->get_items_order()->get_order();

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
        );
    }

    /**
     * @param Object $user_input
     * @return array
     */
    private function do_popup_menu($user_input)
    {
        hd_debug_print(null, true);

        // Dump $user_input message for NewUI. So... What you smoke dear Dune developer?

        //selected_header => 1 - user in the leftmost list
        //selected_row_id => {"row_ndx":0,"row_id":"{\"group_id\":\"TV Group\"}"}
        //selected_header_id => H2 - stupid selected_title_ndx with H prefix...
        //selected_title_ndx => 2
        //parent_sel_state => - Why this stupid shit is used and sent to us?

        //selected_header => 0 - user in selected item in row
        //selected_row_id => {"row_ndx":0,"row_id":"{\"group_id\":\"TV Group\"}"}
        //selected_item_id => {"group_id":"TV Group","channel_id":"e2a79c7b"}
        //selected_title_ndx => 2
        //parent_sel_state => - Why this stupid shit is used and sent to us?

        // show changing playlist and xmltv source in any place
        $menu_items = array();
        if (isset($user_input->{ACTION_CHANGE_EPG_SOURCE})) {
            $menu_items = $this->plugin->epg_source_menu($this);
        } else if (isset($user_input->{ACTION_EPG_CACHE_ENGINE})) {
            $menu_items = $this->plugin->epg_engine_menu($this);
        } else if (isset($user_input->{ACTION_CHANGE_PICONS_SOURCE})) {
            $menu_items = $this->plugin->picons_source_menu($this);
        } else if (isset($user_input->{ACTION_SORT_POPUP})) {
            hd_debug_print("sort menu", true);
            $media_url = MediaURL::decode($user_input->selected_row_id);
            if (isset($media_url->row_id)) {
                $row_id = json_decode($media_url->row_id);
                if ($this->plugin->tv->get_group($row_id->group_id) !== null) {
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
            $media_url = MediaURL::decode($user_input->selected_item_id);
            hd_debug_print($media_url, true);
            if ($media_url->group_id === HISTORY_GROUP_ID) {
                hd_debug_print("in history rows", true);
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_REMOVE, TR::t('delete'), "remove.png");
            } else if ($media_url->group_id === FAVORITES_GROUP_ID && !is_limited_apk()) {
                hd_debug_print("in favorites rows", true);
                $menu_items[] = $this->plugin->create_menu_item($this, PLUGIN_FAVORITES_OP_REMOVE, TR::t('delete_from_favorite'), "star.png");
            } else {
                hd_debug_print("Selected channel in row: $media_url->channel_id", true);
                $channel_id = $media_url->channel_id;

                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_channel'), "remove.png");
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

                if (!is_limited_apk()) {
                    $is_external = $this->plugin->tv->get_channels_for_ext_player()->in_order($channel_id);
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_EXTERNAL_PLAYER,
                        TR::t('tv_screen_external_player'),
                        ($is_external ? "play.png" : null)
                    );

                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_INTERNAL_PLAYER,
                        TR::t('tv_screen_internal_player'),
                        ($is_external ? null : "play.png")
                    );

                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                }

                if ($this->plugin->get_bool_setting(PARAM_PER_CHANNELS_ZOOM)) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ZOOM_POPUP_MENU, TR::t('video_aspect_ratio'), "aspect.png");
                }

                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                $menu_items[] = $this->plugin->create_menu_item($this, GUI_EVENT_KEY_INFO, TR::t('channel_info_dlg'), "info.png");
            }

            if (is_limited_apk()) {
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                if ($media_url->group_id === FAVORITES_GROUP_ID) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        PLUGIN_FAVORITES_OP_MOVE_UP, TR::t('left'), PaneParams::fav_button_green);
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        PLUGIN_FAVORITES_OP_MOVE_DOWN, TR::t('right'), PaneParams::fav_button_yellow);
                }

                $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
                $is_in_favorites = $fav_group->in_items_order($media_url->channel_id);
                $caption = $is_in_favorites ? TR::t('delete_from_favorite') : TR::t('add_to_favorite');
                $action = $is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $menu_items[] = $this->plugin->create_menu_item($this, $action, $caption, PaneParams::fav_button_blue);
            }
        } else {
            // popup menu for left side list
            hd_debug_print("in menu side", true);
            $menu_items[] = $this->plugin->create_menu_item($this,
                ACTION_RELOAD,
                TR::t('playlist_name_msg__1', $this->plugin->get_active_playlist()->name),
                "refresh.png",
                array('reload_action' => Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST)
            );

            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

            $media_url = MediaURL::decode($user_input->selected_row_id);
            $row_id = json_decode($media_url->row_id);
            $group_id = isset($row_id->group_id) ? $row_id->group_id : null;
            $menu_items = array_merge($menu_items, $this->plugin->common_categories_menu($this, $group_id, false));
        }

        return $menu_items;
    }

    private function GetRowsItemsParamsClass()
    {
        $suff = $this->plugin->get_bool_setting(PARAM_SHOW_CHANNEL_CAPTION) ? "" : "n";
        return 'RowsItemsParams' . $this->plugin->get_setting(PARAM_ICONS_IN_ROW, 7) . $suff;
    }

    private function GetRowsItemsParams($param_name)
    {
        $rClass = new ReflectionClass('RowsItemsParams');
        $array = $rClass->getConstants();

        $sq_param = $param_name . ($this->plugin->get_bool_setting(PARAM_SQUARE_ICONS, false) ? '_sq' : '');

        return (isset($array[$sq_param])) ? $array[$sq_param] : $array[$param_name];
    }
}
