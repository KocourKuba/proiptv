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

class Starnet_Edit_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'edit_list';

    const SCREEN_EDIT_PLAYLIST = 'playlist';
    const SCREEN_EDIT_EPG_LIST = 'epg_list';
    const SCREEN_EDIT_GROUPS = 'groups';
    const SCREEN_EDIT_CHANNELS = 'channels';

    const ACTION_FILE_PLAYLIST = 'play_list_file';
    const ACTION_FILE_XMLTV = 'xmltv_file';
    const ACTION_FILE_TEXT_LIST = 'text_list_file';

    const ACTION_CLEAR_APPLY = 'clear_apply';
    const ACTION_REMOVE_PLAYLIST_DLG = 'remove_playlist';
    const ACTION_REMOVE_PLAYLIST_DLG_APPLY = 'remove_playlist_apply';
    const ACTION_CHOOSE_FOLDER = 'choose_folder';
    const ACTION_CHOOSE_FILE = 'choose_file';
    const ACTION_EDIT_ITEM_DLG = 'add_url_dialog';
    const ACTION_URL_DLG_APPLY = 'url_dlg_apply';
    const CONTROL_URL_PATH = 'url_path';
    const CONTROL_SET_NAME = 'set_item_name';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $actions = array();
        if ($this->get_edit_order($media_url->edit_list)->size()) {
            if (isset($media_url->allow_order) && $media_url->allow_order) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            }

            $hidden = ($media_url->edit_list === self::SCREEN_EDIT_GROUPS || $media_url->edit_list === self::SCREEN_EDIT_CHANNELS);
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this,
                ACTION_ITEM_DELETE,
                $hidden ? TR::t('restore') : TR::t('delete'));
        }

        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, TR::t('add'));

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;
        $selected_media_url = MediaURL::decode($user_input->selected_media_url);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $reload = $this->set_changes(false);
                hd_debug_print("Need reload: " . var_export($reload, true), true);
                if (isset($parent_media_url->save_data)) {
                    $this->plugin->set_durty($reload, $parent_media_url->save_data);
                }

                return Action_Factory::replace_path(
                    $parent_media_url->windowCounter,
                    null,
                    User_Input_Handler_Registry::create_action_screen(
                        $parent_media_url->source_window_id,
                        $reload ? $parent_media_url->end_action : $parent_media_url->cancel_action)
                );

            case ACTION_ITEM_UP:
                $order = &$this->get_edit_order($edit_list);
                if (!$order->arrange_item($selected_media_url->id, Ordered_Array::UP))
                    return null;

                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }
                $this->set_changes();

                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);

            case ACTION_ITEM_DOWN:
                $order = &$this->get_edit_order($edit_list);
                if (!$order->arrange_item($selected_media_url->id, Ordered_Array::DOWN))
                    return null;

                $groups_cnt = $order->size();
                $user_input->sel_ndx++;
                if ($user_input->sel_ndx >= $groups_cnt) {
                    $user_input->sel_ndx = $groups_cnt - 1;
                }
                $this->set_changes();

                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);

            case ACTION_ITEM_DELETE:
                if ($edit_list !== self::SCREEN_EDIT_PLAYLIST && $edit_list !== self::SCREEN_EDIT_EPG_LIST) {
                    $item = MediaURL::decode($user_input->selected_media_url)->id;
                    $order = &$this->get_edit_order($edit_list);
                    $order->remove_item($item);
                    $this->set_changes();
                    $this->plugin->remove_playlist_name($item);
                    if ($order->size() === 0) {
                        return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                    }
                    return Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str()));
                }

                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_PLAYLIST_DLG_APPLY);

            case ACTION_ITEMS_SORT:
                $this->get_edit_order($edit_list)->sort_order();
                $this->set_changes();
                return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CLEAR_APPLY);

            case self::ACTION_CLEAR_APPLY:
                $order = &$this->get_edit_order($edit_list);
                if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    foreach ($order as $item) {
                        $this->plugin->get_epg_manager()->clear_epg_cache_by_uri($item);
                    }
                }

                if ($edit_list === self::SCREEN_EDIT_CHANNELS) {
                    if ($parent_media_url->group_id === ALL_CHANNEL_GROUP_ID) {
                        $order->clear();
                    } else {
                        $group = $this->plugin->tv->get_group($parent_media_url->group_id);
                        if (is_null($group)) break;

                        $order->remove_items($group->get_group_channels()->get_keys());
                    }
                } else {
                    $order->clear();
                }

                $user_input->sel_ndx = 0;
                $this->set_changes();

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case self::ACTION_REMOVE_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_PLAYLIST_DLG_APPLY);

            case self::ACTION_REMOVE_PLAYLIST_DLG_APPLY:
                return $this->apply_remove_playlist_dlg($user_input, $plugin_cookies);

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($user_input);

            case self::ACTION_EDIT_ITEM_DLG:
                return $this->do_edit_item_dlg($user_input);

            case self::ACTION_URL_DLG_APPLY: // handle streaming settings dialog result
                return $this->apply_edit_url_dlg($user_input, $plugin_cookies);

            case self::ACTION_CHOOSE_FILE:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => array(
                            'action' => $user_input->action,
                            'extension'	=> $user_input->extension,
                        ),
                        'allow_network' => ($user_input->action === self::ACTION_FILE_TEXT_LIST) && !is_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );

                return Action_Factory::open_folder($media_url_str, TR::t('select_file'));

            case ACTION_FILE_SELECTED:
                return $this->do_select_file($user_input, $plugin_cookies);

            case self::ACTION_CHOOSE_FOLDER:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_folder' => array(
                            'extension'	=> $user_input->extension,
                        ),
                        'allow_network' => false,
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );

                return Action_Factory::open_folder($media_url_str, TR::t('edit_list_src_folder'));

            case ACTION_FOLDER_SELECTED:
                return $this->do_select_folder($user_input);
        }

        return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();
        $edit_list = $media_url->edit_list;
        foreach ($this->get_edit_order($edit_list)->get_order() as $item) {
            $title = $item;
            $detailed_info = null;
            if ($edit_list === self::SCREEN_EDIT_CHANNELS) {
                if ($media_url->group_id === ALL_CHANNEL_GROUP_ID) {
                    $channel = $this->plugin->tv->get_channel($item);
                } else {
                    $group = $this->plugin->tv->get_group($media_url->group_id);
                    if (is_null($group)) continue;
                    $channel = $group->get_group_channels()->get($item);
                }

                if (is_null($channel)) continue;

                $icon_file = $channel->get_icon_url();
                $title = $channel->get_title();
            } else if ($edit_list === self::SCREEN_EDIT_GROUPS) {
                $group = $this->plugin->tv->get_group($item);
                if (is_null($group)) continue;

                $icon_file = $group->get_icon_url();
            } else if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                $icon_file = get_image_path("link.png");
                $detailed_info = $this->plugin->get_playlist_name($item);
            } else if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                $icon_file = get_image_path("link.png");
                $detailed_info = $this->plugin->get_xmltv_source_name($item);
            } else {
                continue;
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $item)),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $icon_file,
                    ViewItemParams::item_detailed_info => $detailed_info,
                ),
            );
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $folder_view = parent::get_folder_view($media_url, $plugin_cookies);

        if ($this->get_edit_order($media_url->edit_list)->size() === 0) {
            $msg = !is_apk()
                ? TR::t('edit_list_add_prompt__3', 100, 300, DEF_LABEL_TEXT_COLOR_YELLOW)
                : TR::t('edit_list_add_prompt_apk__3', 100, 300, DEF_LABEL_TEXT_COLOR_YELLOW);
            $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = $msg;
        } else {
            $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = null;
        }

        return $folder_view;
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
        );
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param string $edit_list
     * @return Ordered_Array
     */
    protected function &get_edit_order($edit_list)
    {
        hd_debug_print($edit_list, true);

        switch ($edit_list) {
            case self::SCREEN_EDIT_PLAYLIST:
                return $this->plugin->get_playlists();

            case self::SCREEN_EDIT_EPG_LIST:
                return $this->plugin->get_ext_xmltv_sources();

            case self::SCREEN_EDIT_GROUPS:
                return $this->plugin->tv->get_disabled_group_ids();

            case self::SCREEN_EDIT_CHANNELS:
                return $this->plugin->tv->get_disabled_channel_ids();
        }

        $order = new Ordered_Array();
        return $order;
    }

    /**
     * @param $user_input
     * @return array|null
     */
    protected function create_popup_menu($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;

        $menu_items = array();
        if ($edit_list === self::SCREEN_EDIT_PLAYLIST
            || $edit_list === self::SCREEN_EDIT_EPG_LIST) {

            if (isset($user_input->selected_media_url)) {
                $item = MediaURL::decode($user_input->selected_media_url)->id;

                $menu_items[] = $this->plugin->create_menu_item($this,
                    self::ACTION_EDIT_ITEM_DLG,
                    TR::t('edit_list_edit_item'),
                    "edit.png",
                    array('edit_action' => 'edit', 'edit_item' => $item)
                );

                if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    $name = $this->plugin->get_playlist_name($item);
                } else {
                    $name = $this->plugin->get_xmltv_source_name($item);
                }
                $menu_items[] = $this->plugin->create_menu_item($this,
                    self::ACTION_EDIT_ITEM_DLG,
                    TR::t('edit_list_add_name'),
                    "edit.png",
                    array('edit_action' => 'set_name', 'edit_item' => $name)
                );
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
            }

            $add_param = array('extension' => $parent_media_url->extension);
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_EDIT_ITEM_DLG,
                TR::t('edit_list_internet_path'),
                "link.png",
                array('edit_action' => 'add_url', 'edit_item' => 'http://')
            );

            $add_param['action'] = $edit_list === self::SCREEN_EDIT_PLAYLIST ? self::ACTION_FILE_PLAYLIST : self::ACTION_FILE_XMLTV;
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FILE, TR::t('select_file'),
                $edit_list === self::SCREEN_EDIT_PLAYLIST ? "m3u_file.png" : "xmltv_file.png",
                $add_param);

            $add_param['action'] = self::ACTION_FILE_TEXT_LIST;
            $add_param['extension'] = 'txt|lst';
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FILE,
                TR::t('edit_list_import_list'),
                "text_file.png",
                $add_param);

            unset($add_param['action']);
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FOLDER,
                TR::t('edit_list_folder_path'),
                "folder.png",
                $add_param);

            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        }

        if ($this->get_edit_order($edit_list)->size()) {
            $hidden = ($edit_list === self::SCREEN_EDIT_GROUPS || $edit_list === self::SCREEN_EDIT_CHANNELS);
            $menu_items[] = $this->plugin->create_menu_item($this,
                ACTION_ITEMS_CLEAR,
                $hidden ? TR::t('restore_all') : TR::t('clear'),
                "brush.png");
        }

        return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param $user_input
     * @return array|null
     */
    protected function do_edit_item_dlg($user_input)
    {
        hd_debug_print(null, true);

        $defs = array();
        Control_Factory::add_vgap($defs, 20);
        Control_Factory::add_text_field($defs, $this, null, self::CONTROL_URL_PATH, '',
            $user_input->edit_item, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this, array('edit_action' => $user_input->edit_action),
            self::ACTION_URL_DLG_APPLY, TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        switch ($user_input->edit_action) {
            case 'set_name':
                $name = TR::t('edit_list_add_name');
                break;
            case 'add_url':
                $name = TR::t('edit_list_internet_path');
                break;
            case 'edit':
                $name = TR::t('edit_list_edit_item');
                break;
            default:
                return null;
        }

        return Action_Factory::show_dialog($name, $defs, true);
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    protected function apply_edit_url_dlg($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        if (!isset($user_input->edit_action)) {
            return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx);
        }

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;
        $name = isset($user_input->{self::CONTROL_URL_PATH}) ? $user_input->{self::CONTROL_URL_PATH} : '';
        switch ($user_input->edit_action) {
            case 'set_name':
                $item = MediaURL::decode($user_input->selected_media_url)->id;
                if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    $this->plugin->set_playlist_name($item, $name);
                } else if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    $this->plugin->set_xmltv_source_name($item, $name);
                }
                break;

            case 'add_url':
                if (!preg_match(HTTP_PATTERN, $name)) {
                    return Action_Factory::show_title_dialog(TR::t('err_incorrect_url'));
                }

                $order = &$this->get_edit_order($edit_list);
                if ($order->in_order($name)) {
                    return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
                }

                $order->add_item($name);
                $this->set_changes();
                break;

            case 'edit':
                $item = MediaURL::decode($user_input->selected_media_url)->id;
                if (!preg_match(HTTP_PATTERN, $name)) {
                    return Action_Factory::show_title_dialog(TR::t('err_incorrect_url'));
                }

                $order = &$this->get_edit_order($edit_list);
                if ($order->in_order($name)) {
                    return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
                }

                $idx = $order->get_item_pos($item);
                $order->set_item_by_idx($idx, $name);
                $this->set_changes();
                break;
        }

        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url,$plugin_cookies), 0,
            $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx));
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array
     */
    protected function do_select_file($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $data = MediaURL::decode($user_input->selected_data);

        if ($data->choose_file->action === self::ACTION_FILE_TEXT_LIST) {
            hd_debug_print("Choosed file: $data->filepath", true);
            $lines = file($data->filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
                return Action_Factory::show_title_dialog(TR::t('edit_list_empty_file'));
            }

            $order = &$this->get_edit_order($parent_media_url->edit_list);
            $old_count = $order->size();
            $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
            $error_log = array();
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$order->in_order($line) && preg_match(HTTP_PATTERN, $line)) {
                    $order->add_item($line);
                    hd_debug_print("imported: '$line'");
                }
            }

            $post_action = null;
            if ($old_count === $order->size()) {
                $post_action = Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
            }

            if (!empty($error_log)) {
                return Action_Factory::show_title_dialog(TR::t('err_load_xmltv_epg'), $post_action, $error_log);
            }

            $this->set_changes();

            return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $order->size() - $old_count, count($lines)),
                Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str()))
            );
        }

        if ($data->choose_file->action === self::ACTION_FILE_PLAYLIST || $data->choose_file->action === self::ACTION_FILE_XMLTV) {
            $order = &$this->get_edit_order($parent_media_url->edit_list);
            if (!$order->add_item($data->filepath)) {
                return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
            }
            $this->set_changes();
        }

        return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @param $user_input
     * @return array
     */
    protected function do_select_folder($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_data = MediaURL::decode($user_input->selected_data);
        $file = glob_dir($selected_data->filepath, "/\.$parent_media_url->extension$/i");
        if (empty($file)) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        $order = &$this->get_edit_order($parent_media_url->edit_list);
        $old_count = $order->size();
        $order->add_items($file);
        $this->set_changes();

        return Action_Factory::show_title_dialog(TR::t('edit_list_added__1', $order->size() - $old_count),
            Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str()))
        );
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array
     */
    protected function apply_remove_playlist_dlg($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $order = &$this->get_edit_order($parent_media_url->edit_list);
        if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
            $item = $order->get_item_by_idx($user_input->sel_ndx);
            $this->plugin->get_epg_manager()->clear_epg_cache_by_uri($item);
        }
        $order->remove_item_by_idx($user_input->sel_ndx);
        $this->set_changes();

        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
            $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx));
    }
}
