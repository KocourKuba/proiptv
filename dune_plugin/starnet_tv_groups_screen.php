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

require_once 'lib/abstract_preloaded_regular_screen.php';
require_once 'starnet_setup_screen.php';

class Starnet_Tv_Groups_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_groups';

    const ACTION_CONFIRM_DLG_APPLY = 'apply_dlg';
    const ACTION_EPG_SETTINGS = 'epg_settings';
    const ACTION_CHANNELS_SETTINGS = 'channels_settings';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $res = $this->plugin->tv->load_channels($plugin_cookies);
        if ($res === 0) {
            hd_debug_print("Channels not loaded!");
        } else if ($res === 2) {
            hd_debug_print("Channels reloaded!");
            $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);
        }

        $actions = array();

        $actions[GUI_EVENT_KEY_ENTER]      = User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER);
        $actions[GUI_EVENT_KEY_PLAY]       = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_FOLDER);

        $actions[GUI_EVENT_KEY_RETURN]     = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU]   = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);

        $order = $this->plugin->get_groups_order();
        if (!is_null($order) && $order->size() !== 0) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        }

        $actions[GUI_EVENT_KEY_D_BLUE]     = User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS, TR::t('entry_setup'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $sel_ndx = $user_input->sel_ndx;
        if (isset($user_input->selected_media_url)) {
            $sel_media_url = MediaURL::decode($user_input->selected_media_url);
        } else {
            $sel_media_url = MediaURL::make(array());
        }

        switch ($user_input->control_id)
        {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if ($this->plugin->get_bool_parameter(PARAM_ASK_EXIT)) {
                    return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CONFIRM_DLG_APPLY);
                }

                $this->plugin->save(PLUGIN_PARAMETERS);
                return Action_Factory::close_and_run();

            case ACTION_OPEN_FOLDER:
            case ACTION_PLAY_FOLDER:
                $post_action = $this->plugin->update_epfs_data($plugin_cookies,
                    null,
                    $user_input->control_id === ACTION_OPEN_FOLDER ? Action_Factory::open_folder() : Action_Factory::tv_play());

                $has_error = HD::get_last_error();
                if (!empty($has_error)) {
                    HD::set_last_error(null);
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_load_any'), $post_action, $has_error, self::DLG_CONTROLS_WIDTH);
                }

                return $post_action;

            case ACTION_ITEM_UP:
                if (!$this->plugin->get_groups_order()->arrange_item($sel_media_url->group_id, Ordered_Array::UP))
                    return null;

                $min_sel = $this->plugin->get_special_groups_count();
                $sel_ndx--;
                if ($sel_ndx < $min_sel) {
                    $sel_ndx = $min_sel;
                }

                $this->plugin->save();
                $this->plugin->invalidate_epfs();
                break;

            case ACTION_ITEM_DOWN:
                if (!$this->plugin->get_groups_order()->arrange_item($sel_media_url->group_id, Ordered_Array::DOWN))
                    return null;

                $groups_cnt = $this->plugin->get_special_groups_count() + $this->plugin->get_groups_order()->size();
                $sel_ndx++;
                if ($sel_ndx >= $groups_cnt) {
                    $sel_ndx = $groups_cnt - 1;
                }

                $this->plugin->save();
                $this->plugin->invalidate_epfs();
                break;

            case ACTION_ITEM_DELETE:
                $this->plugin->tv->disable_group($sel_media_url->group_id);
                $this->plugin->invalidate_epfs();
                break;

            case ACTION_ITEMS_SORT:
                $this->plugin->get_groups_order()->sort_order();
                $this->plugin->save();
                $this->plugin->invalidate_epfs();
                break;

            case ACTION_RESET_ITEMS_SORT:
                $this->plugin->get_groups_order()->clear();
                $this->plugin->save();
                $this->plugin->invalidate_epfs();
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_ITEMS_EDIT:
                $this->plugin->set_pospone_save();
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Edit_List_Screen::ID,
                        'source_window_id' => static::ID,
                        'edit_list' => $user_input->action_edit,
                        'group_id' => ($user_input->action_edit === Starnet_Edit_List_Screen::SCREEN_EDIT_CHANNELS) ? $sel_media_url->group_id : null,
                        'end_action' => ACTION_RELOAD,
                        'cancel_action' => ACTION_REFRESH_SCREEN,
                        'save_data' => PLUGIN_SETTINGS,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str,
                    ($user_input->action_edit === Starnet_Edit_List_Screen::SCREEN_EDIT_CHANNELS)
                        ? TR::t('tv_screen_edit_hidden_channels')
                        : TR::t('tv_screen_edit_hidden_group'));

            case ACTION_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case self::ACTION_CHANNELS_SETTINGS:
                return Action_Factory::open_folder(Starnet_Playlists_Setup_Screen::get_media_url_str(), TR::t('tv_screen_playlists_setup'));

            case self::ACTION_EPG_SETTINGS:
                return Action_Factory::open_folder(Starnet_Epg_Setup_Screen::get_media_url_str(), TR::t('setup_epg_settings'));

            case self::ACTION_CONFIRM_DLG_APPLY:
                $this->plugin->save(PLUGIN_PARAMETERS);
                return Starnet_Epfs_Handler::invalidate_folders(null, Action_Factory::close_and_run());

            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($user_input->{ACTION_CHANGE_PLAYLIST})) {
                    $menu_items = $this->plugin->playlist_menu($this);
                } else if (isset($user_input->{ACTION_CHANGE_EPG_SOURCE})) {
                    $menu_items = $this->plugin->epg_source_menu($this);
                } else {
                    $menu_items = $this->common_menu(isset($sel_media_url->group_id) ? $sel_media_url->group_id : null);
                }

                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_CHANGE_PLAYLIST:
                hd_debug_print("Start event popup menu for playlist");
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_PLAYLIST => true));

            case ACTION_PLAYLIST_SELECTED:
                if (!isset($user_input->list_idx)) break;

                $this->plugin->set_playlists_idx($user_input->list_idx);
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_CHANGE_EPG_SOURCE:
                hd_debug_print("Start event popup menu for epg source");
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_EPG_SOURCE => true));

            case ACTION_EPG_SOURCE_SELECTED:
                if (!isset($user_input->list_idx)) break;

                $this->plugin->set_active_xmltv_source_key($user_input->list_idx);
                $xmltv_source = $this->plugin->get_all_xmltv_sources()->get($user_input->list_idx);
                $this->plugin->set_active_xmltv_source($xmltv_source);
                $this->plugin->tv->unload_channels();
                $this->plugin->tv->load_channels($plugin_cookies);

                return Action_Factory::invalidate_all_folders($plugin_cookies);

            case ACTION_CHANGE_GROUP_ICON:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => array(
                            'action' => $user_input->control_id,
                            'extension'	=> 'png|jpg',
                        ),
                        'allow_network' => !is_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('select_file'));

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file->action === ACTION_CHANGE_GROUP_ICON) {
                    $group = $this->plugin->tv->get_group($sel_media_url->group_id);
                    if (is_null($group)) break;

                    $cached_image_name = "{$this->plugin->get_current_playlist_hash()}_$data->caption";
                    $cached_image = get_cached_image_path($cached_image_name);
                    hd_print("copy from: $data->filepath to: $cached_image");
                    if (!copy($data->filepath, $cached_image)) {
                        return Action_Factory::show_title_dialog(TR::t('err_copy'));
                    }

                    $old_cached_image = $group->get_icon_url();
                    $group->set_icon_url($cached_image);
                    hd_debug_print("Assign icon: $cached_image to group: $sel_media_url->group_id");

                    /** @var Hashed_Array $group_icons */
                    $group_icons = $this->plugin->get_setting(PARAM_GROUPS_ICONS, new Hashed_Array());
                    $group_icons->set($sel_media_url->group_id, $cached_image_name);
                    $this->plugin->save();

                    /** @var Group $known_group */
                    if (strpos($old_cached_image, 'plugin_file://') === false) break;

                    foreach ($this->plugin->tv->get_groups() as $known_group) {
                        $icon_path = $known_group->get_icon_url();
                        if (strpos($icon_path, 'plugin_file://') !== false) {
                            $icons[] = $icon_path;
                        }
                    }
                    if (isset($icons) && !in_array($old_cached_image, $icons)) {
                        unlink($old_cached_image);
                    }
                }
                break;

            case ACTION_RESET_DEFAULT:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file->action === ACTION_CHANGE_GROUP_ICON) {
                    $group = $this->plugin->tv->get_group($sel_media_url->group_id);
                    if (is_null($group)) break;

                    hd_debug_print("Reset icon for group: $sel_media_url->group_id to default", true);

                    if ($group->is_special_group(FAVORITES_GROUP_ID)) {
                        $group->set_icon_url(Default_Group::DEFAULT_FAVORITE_GROUP_ICON);
                    } else if ($group->is_special_group(HISTORY_GROUP_ID)) {
                        $group->set_icon_url(Default_Group::DEFAULT_HISTORY_GROUP_ICON);
                    } else if ($group->is_special_group(ALL_CHANNEL_GROUP_ID)) {
                        $group->set_icon_url(Default_Group::DEFAULT_ALL_CHANNELS_GROUP_ICON);
                    } else if ($group->is_special_group(CHANGED_CHANNELS_GROUP_ID)) {
                        $group->set_icon_url(Default_Group::DEFAULT_CHANGED_CHANNELS_GROUP_ICON);
                    } else {
                        $group->set_icon_url(Default_Group::DEFAULT_GROUP_ICON_PATH);
                    }
                    /** @var Hashed_Array<string> $group_icons */
                    $group_icons = $this->plugin->get_setting(PARAM_GROUPS_ICONS, new Hashed_Array());
                    $group_icons->erase($sel_media_url->group_id);
                    $this->plugin->save();
                }
                break;

            case ACTION_RELOAD:
                $this->plugin->tv->unload_channels();
                $this->plugin->tv->load_channels($plugin_cookies);
                return Action_Factory::invalidate_all_folders($plugin_cookies,
                    Action_Factory::close_and_run(
                        Action_Factory::open_folder(self::ID, $this->plugin->create_plugin_title())));

            case ACTION_REFRESH_SCREEN:
            default:
                return null;
        }

        return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();
        $res = $this->plugin->tv->load_channels($plugin_cookies);
        if ($res === 0) {
            hd_debug_print("Channels not loaded!");
            return $items;
        }

        /** @var Group $group */
        foreach ($this->plugin->tv->get_special_groups() as $group) {
            if (is_null($group)) continue;

            hd_debug_print("group: {$group->get_title()} disabled: " . var_export($group->is_disabled(), true), true);

            if ($group->is_disabled()) continue;

            if ($group->is_special_group(ALL_CHANNEL_GROUP_ID)) {
                $item_detailed_info = TR::t('tv_screen_group_info__3',
                    $group->get_title(),
                    $this->plugin->tv->get_channels()->size(),
                    $this->plugin->get_disabled_channels()->size());
            } else if ($group->is_special_group(HISTORY_GROUP_ID)) {
                $item_detailed_info = TR::t('tv_screen_group_info__2',
                    $group->get_title(),
                    $this->plugin->get_playback_points()->size());
            } else if ($group->is_special_group(CHANGED_CHANNELS_GROUP_ID)) {
                $item_detailed_info = TR::t('tv_screen_group_changed_info__3',
                    $group->get_title(),
                    count($this->plugin->get_changed_channels('new')),
                    count($this->plugin->get_changed_channels('removed')));
            } else {
                $item_detailed_info = TR::t('tv_screen_group_info__2',
                    $group->get_title(),
                    $group->get_items_order()->size());
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => $group->get_media_url_str(),
                PluginRegularFolderItem::caption => $group->get_title(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $group->get_icon_url(),
                    ViewItemParams::item_detailed_icon_path => $group->get_icon_url(),
                    ViewItemParams::item_detailed_info => $item_detailed_info,
                    )
                );
        }

        /** @var Group $group */
        foreach ($this->plugin->get_groups_order() as $item) {
            $group = $this->plugin->tv->get_group($item);
            if (is_null($group) || $group->is_disabled()) continue;

            $group_url = $group->get_icon_url() !== null ? $group->get_icon_url() : Default_Group::DEFAULT_GROUP_ICON_PATH;
            $items[] = array(
                PluginRegularFolderItem::media_url => $group->get_media_url_str(),
                PluginRegularFolderItem::caption => $group->get_title(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $group_url,
                    ViewItemParams::item_detailed_icon_path => $group_url,
                    ViewItemParams::item_detailed_info => TR::t('tv_screen_group_info__3',
                        $group->get_title(),
                        $group->get_group_channels()->size(),
                        $group->get_group_channels()->size() - $group->get_items_order()->size()
                    ),
                ),
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
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),

            $this->plugin->get_screen_view('icons_4x3_caption'),
            $this->plugin->get_screen_view('icons_4x3_no_caption'),
            $this->plugin->get_screen_view('icons_3x3_caption'),
            $this->plugin->get_screen_view('icons_3x3_no_caption'),
            $this->plugin->get_screen_view('icons_5x3_caption'),
            $this->plugin->get_screen_view('icons_5x3_no_caption'),
            $this->plugin->get_screen_view('icons_5x4_caption'),
            $this->plugin->get_screen_view('icons_5x4_no_caption'),
        );
    }

    /**
     * @param string $group_id
     * @return array
     */
    protected function common_menu($group_id)
    {
        $menu_items = array();

        if (!is_null($group_id) && !in_array($group_id, array(ALL_CHANNEL_GROUP_ID, HISTORY_GROUP_ID, FAVORITES_GROUP_ID))) {
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_group'), "hide.png");
        }

        if (!empty($menu_items)) {
            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        }

        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_items'), "sort.png");
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_sort_default'), "brush.png");

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_CHANGE_GROUP_ICON, TR::t('change_group_icon'), "image.png");

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

        $cnt = count($menu_items);
        if ($this->plugin->get_disabled_groups()->size() !== 0) {
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_EDIT,
                TR::t('tv_screen_edit_hidden_group'), "edit.png", array('action_edit' => Starnet_Edit_List_Screen::SCREEN_EDIT_GROUPS));
        }

        if (!is_null($group_id)) {
            $has_hidden = false;
            if ($group_id === ALL_CHANNEL_GROUP_ID) {
                $has_hidden = $this->plugin->get_disabled_channels()->size() !== 0;
                hd_debug_print("Disabled channels: " . $this->plugin->get_disabled_channels()->size());
            } else if (($group = $this->plugin->tv->get_group($group_id)) !== null) {
                $has_hidden = $group->get_group_channels()->size() !== $group->get_items_order()->size();
                hd_debug_print("Group channels: " . $group->get_group_channels()->size() . " Channels order: " . $group->get_items_order()->size(), true);
            }

            if ($has_hidden) {
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_EDIT,
                    TR::t('tv_screen_edit_hidden_channels'), "edit.png", array('action_edit' => Starnet_Edit_List_Screen::SCREEN_EDIT_CHANNELS) );
            }
        }

        if (count($menu_items) !== $cnt) {
            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        }

        if ($this->plugin->get_playlists()->size()) {
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_CHANGE_PLAYLIST, TR::t('change_playlist'), "playlist.png");
        }

        if ($this->plugin->get_all_xmltv_sources()->size()) {
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_CHANGE_EPG_SOURCE, TR::t('change_epg_source'), "epg.png");
        }

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RELOAD, TR::t('refresh'), "refresh.png");

        return $menu_items;
    }
}
