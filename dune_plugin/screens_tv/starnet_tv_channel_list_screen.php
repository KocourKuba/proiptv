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
require_once 'lib/user_input_handler_registry.php';

class Starnet_Tv_Channel_List_Screen extends Abstract_Preloaded_Regular_Screen
{
    const ID = 'tv_channel_list';

    /**
     * Get MediaURL string representation (json encoded)
     * *
     * @param string $group_id
     * @return false|string
     */
    public static function make_group_media_url_str($group_id)
    {
        return MediaURL::encode(array(PARAM_SCREEN_ID => static::ID, PARAM_GROUP_ID => $group_id));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map($media_url);
    }

    protected function do_get_action_map(MediaURL $media_url)
    {
        hd_debug_print(null, true);

        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);

        $actions[GUI_EVENT_KEY_ENTER] = $action_play;
        $actions[GUI_EVENT_KEY_PLAY] = $action_play;
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_INFO] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_INFO);
        $actions[GUI_EVENT_KEY_SUBTITLE] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_SUBTITLE);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        $special_group = $this->plugin->get_group($media_url->{PARAM_GROUP_ID}, PARAM_GROUP_SPECIAL);
        if (empty($special_group) || $media_url->{PARAM_GROUP_ID} === TV_ALL_CHANNELS_GROUP_ID) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this,
                ACTION_ITEMS_EDIT, TR::t('tv_screen_edit_channels'));
        }

        $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this,
            ACTION_EDIT_CHANNEL_DLG, TR::t('tv_screen_edit_channel'));

        $add_to_favorite = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
        $actions[GUI_EVENT_KEY_D_BLUE] = $add_to_favorite;
        $actions[GUI_EVENT_KEY_DUNE] = $add_to_favorite;

        if (!is_limited_apk()) {
            // this key used to fire event from background xmltv indexing script
            $actions[EVENT_INDEXING_DONE] = User_Input_Handler_Registry::create_action($this, EVENT_INDEXING_DONE);
        }

        $this->plugin->add_shortcuts_handlers($this, $actions);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        if (!isset($user_input->selected_media_url)) {
            hd_debug_print('user input selected media url not set', true);
            return null;
        }

        $fav_id = $this->plugin->get_fav_id();
        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $channel_id = $selected_media_url->{PARAM_CHANNEL_ID};
        $sel_ndx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $actions[] = Action_Factory::close_and_run();
                if ($this->force_parent_reload) {
                    $this->force_parent_reload = false;
                    hd_debug_print('Force parent reload', true);
                    $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID, ACTION_INVALIDATE);
                }
                return Action_Factory::composite($actions);

            case GUI_EVENT_TIMER:
                $error_msg = Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST);
                if (!empty($error_msg)) {
                    hd_debug_print("Playlist loading error: $error_msg");
                    return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), $error_msg);
                }

                if (!is_limited_apk()) break;

                $actions[] = $this->plugin->get_import_xmltv_logs_actions($plugin_cookies);
                $actions[] = Action_Factory::change_behaviour($this->do_get_action_map($parent_media_url), 1000);
                return Action_Factory::composite($actions);

            case EVENT_INDEXING_DONE:
                return $this->plugin->get_import_xmltv_logs_actions($plugin_cookies);

            case GUI_EVENT_KEY_INFO:
                return $this->plugin->do_show_channel_info($this, $channel_id, true);

            case GUI_EVENT_KEY_SUBTITLE:
                $attrs['initial_sel_ndx'] = 2;
                return $this->plugin->do_show_channel_epg($this, $this->plugin->get_epg_info($channel_id, -1), $attrs);

            case PARAM_EPG_SHIFT_HOURS:
            case PARAM_EPG_SHIFT_MINS:
                hd_debug_print('Applying epg shift hours: ' . $user_input->{PARAM_EPG_SHIFT_HOURS}, true);
                hd_debug_print('Applying epg shift mins: ' . $user_input->{PARAM_EPG_SHIFT_MINS}, true);
                $this->plugin->set_channel_epg_shift($channel_id, $user_input->{PARAM_EPG_SHIFT_HOURS}, $user_input->{PARAM_EPG_SHIFT_MINS});
                if (!isset($new_value)) break;

                $attrs['initial_sel_ndx'] = $user_input->control_id === PARAM_EPG_SHIFT_HOURS ? 0 : 1;
                $actions[] = Action_Factory::close_dialog();
                $actions[] = Action_Factory::invalidate_folders(array($user_input->parent_media_url));
                $actions[] = $this->plugin->do_show_channel_epg($this, $this->plugin->get_epg_info($channel_id, -1), $attrs);
                return Action_Factory::composite($actions);

            case ACTION_PLAY_ITEM:
                try {
                    $post_action = $this->plugin->tv_player_exec($selected_media_url);
                } catch (Exception $ex) {
                    hd_debug_print("Channel can't played");
                    print_backtrace_exception($ex);
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'), TR::t('warn_msg2__1', $ex->getMessage()));
                }

                Starnet_Epfs_Handler::update_epfs_file($plugin_cookies);
                return $post_action;

            case ACTION_ADD_FAV:
                $this->force_parent_reload = true;
                $in_order = $this->plugin->is_channel_in_order($fav_id, $channel_id);
                $opt_type = $in_order ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_tv_favorites($opt_type, $channel_id, $plugin_cookies);
                break;

            case ACTION_NEW_SEARCH:
                return Action_Factory::close_dialog_and_run($this->plugin->do_search($this, $user_input->{ACTION_NEW_SEARCH}));

            case ACTION_JUMP_TO_CHANNEL:
                $sel_ndx = $user_input->number;
                break;

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                $ch_id = $channel_id;
                if (isset($user_input->{COLUMN_CHANNEL_ID})) {
                    $ch_id = $user_input->{COLUMN_CHANNEL_ID};
                }
                return Action_Factory::close_and_run($this->plugin->jump_to_channel($ch_id));

            case ACTION_ITEMS_EDIT:
                return $this->plugin->do_edit_list_screen(static::ID,
                    Starnet_Edit_Channel_List_Screen::PARAM_EDIT_CHANNELS, $parent_media_url->{PARAM_GROUP_ID});

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($user_input);

            case ACTION_EDIT_CHANNEL_DLG:
                return $this->plugin->do_edit_channel_parameters($this, $channel_id);

            case ACTION_EDIT_CHANNEL_APPLY:
                $this->plugin->do_edit_channel_apply($user_input, $channel_id);
                break;

            case ACTION_SHORTCUT:
                $actions[] = Action_Factory::close_and_run();
                $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID,
                    ACTION_SHORTCUT,
                    '',
                    array(COLUMN_PLAYLIST_ID => $user_input->{COLUMN_PLAYLIST_ID})
                );
                return Action_Factory::composite($actions);

            case ACTION_RELOAD:
                hd_debug_print('Action reload', true);
                $actions[] = Action_Factory::close_and_run();
                $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID, ACTION_RELOAD);
                return Action_Factory::composite($actions);

            case ACTION_INVALIDATE:
                $this->force_parent_reload = true;
                break;

            case ACTION_EMPTY:
                return null;
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();

        try {
            if (!$this->plugin->is_channels_loaded()) {
                throw new Exception('Channels not loaded!');
            }

            $show_adult = $this->plugin->get_bool_setting(PARAM_SHOW_ADULT);
            $groups_order = array();
            if ($media_url->{PARAM_GROUP_ID} === TV_ALL_CHANNELS_GROUP_ID) {
                $groups_order = $this->plugin->get_groups_by_order($show_adult);
            } else {
                $group = $this->plugin->get_group($media_url->{PARAM_GROUP_ID}, PARAM_GROUP_ORDINARY, $show_adult);
                if (!empty($group)) {
                    $groups_order[] = $group;
                }
            }

            $fav_id = $this->plugin->get_fav_id();
            $fav_ids = $this->plugin->get_channels_order($fav_id);

            foreach ($groups_order as $group_row) {
                $group_id = $group_row[COLUMN_GROUP_ID];
                $channels_rows = $this->plugin->get_channels_by_order($group_id, $show_adult);
                foreach ($channels_rows as $channel_row) {
                    $channel_id = $channel_row[COLUMN_CHANNEL_ID];
                    $icon_url = $this->plugin->get_channel_picon($channel_row, true);
                    $title = $channel_row[COLUMN_TITLE];
                    $archive = $channel_row[COLUMN_ARCHIVE];
                    $zoom = safe_get_value($channel_row, COLUMN_ZOOM, DuneVideoZoomPresets::not_set);
                    $epg_shift = format_duration_minutes((int)safe_get_value($channel_row, COLUMN_EPG_SHIFT, 0));

                    $epg_str = HD::ArrayToStr(array_values(Default_Dune_Plugin::make_epg_ids($channel_row)));
                    if ($zoom === DuneVideoZoomPresets::not_set || $zoom === null) {
                        $detailed_info = TR::t('tv_screen_channel_info__5', $title, $archive, $channel_id, $epg_str, $epg_shift);
                    } else {
                        $detailed_info = TR::t('tv_screen_channel_info__6', $title, $archive, $channel_id, $epg_str, $zoom, $epg_shift);
                    }

                    $items[] = array(
                        PluginRegularFolderItem::media_url => MediaURL::encode(array(PARAM_CHANNEL_ID => $channel_id, PARAM_GROUP_ID => $group_id)),
                        PluginRegularFolderItem::caption => $title,
                        PluginRegularFolderItem::starred => in_array($channel_id, $fav_ids),
                        PluginRegularFolderItem::view_item_params => array(
                            ViewItemParams::icon_path => $icon_url,
                            ViewItemParams::item_detailed_icon_path => $icon_url,
                            ViewItemParams::item_detailed_info => $detailed_info,
                        ),
                    );
                }
            }
        } catch (Exception $ex) {
            hd_debug_print('Failed collect folder items!');
            print_backtrace_exception($ex);
        }

        return $items;
    }

    ///////////////////////////////////////////////////////////////////////

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
    public function get_folder_views()
    {
        hd_debug_print(null, true);

        return array(
            $this->plugin->get_screen_view('icons_4x3_caption'),
            $this->plugin->get_screen_view('icons_4x3_no_caption'),
            $this->plugin->get_screen_view('icons_3x3_caption'),
            $this->plugin->get_screen_view('icons_3x3_no_caption'),
            $this->plugin->get_screen_view('icons_5x3_caption'),
            $this->plugin->get_screen_view('icons_5x3_no_caption'),
            $this->plugin->get_screen_view('icons_5x4_caption'),
            $this->plugin->get_screen_view('icons_5x4_no_caption'),

            $this->plugin->get_screen_view('icons_7x4_no_caption'),
            $this->plugin->get_screen_view('icons_7x4_caption'),

            $this->plugin->get_screen_view('list_1x11_small_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),
        );
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// Protected methods

    protected function create_popup_menu($user_input)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $parent_group = $parent_media_url->{PARAM_GROUP_ID};
        $channel_id = $selected_media_url->{PARAM_CHANNEL_ID};

        $menu_items[] = User_Input_Handler_Registry::create_popup_item_ext(
            $this->plugin->new_search($this),
            TR::t('search'), 'search.png');

        if ($parent_group === TV_ALL_CHANNELS_GROUP_ID) {
            $ch_id = isset($user_input->{COLUMN_CHANNEL_ID}) ? $user_input->{COLUMN_CHANNEL_ID} : $channel_id;
            $menu_items[] = User_Input_Handler_Registry::create_popup_item_ext(
                Action_Factory::close_and_run($this->plugin->jump_to_channel($ch_id)),
                TR::t('jump_to_channel'), 'goto.png');
        }

        $menu_items[] = Control_Factory::menu_separator();

        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
            GUI_EVENT_KEY_SUBTITLE, TR::t('channel_epg_dlg'), 'epg.png');
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
            GUI_EVENT_KEY_INFO, TR::t('channel_info_dlg'), 'info.png');

        return Action_Factory::show_popup_menu($menu_items);
    }
}
