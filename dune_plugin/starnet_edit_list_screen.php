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
    const ACTION_ADD_URL_DLG = 'add_url_dialog';
    const ACTION_URL_DLG_APPLY = 'url_dlg_apply';
    const ACTION_URL_PATH = 'url_path';

    const DLG_CONTROLS_WIDTH = 850;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $actions = array();
        if ($this->get_edit_order($media_url)->size()) {
            if (isset($media_url->allow_order) && $media_url->allow_order) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            }
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
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
        $order = $this->get_edit_order($parent_media_url);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if (!isset($parent_media_url->save_data)) {
                    $need_reload = true;
                } else {
                    $need_reload = $this->plugin->is_durty($parent_media_url->save_data);
                    $this->plugin->set_pospone_save(false, $parent_media_url->save_data);
                }

                hd_debug_print("Need reload: " . var_export($need_reload, true), true);

                return Action_Factory::replace_path(
                    $parent_media_url->windowCounter,
                    null,
                    User_Input_Handler_Registry::create_action_screen(
                        $parent_media_url->source_window_id,
                        $need_reload ? $parent_media_url->end_action : $parent_media_url->cancel_action)
                );

            case ACTION_ITEM_UP:
                $id = MediaURL::decode($user_input->selected_media_url)->id;
                if (!$order->arrange_item($id, Ordered_Array::UP))
                    return null;

                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }
                $this->set_edit_order($parent_media_url, $order);
                break;

            case ACTION_ITEM_DOWN:
                $id = MediaURL::decode($user_input->selected_media_url)->id;
                if (!$order->arrange_item($id, Ordered_Array::DOWN))
                    return null;

                $groups_cnt = $order->size();
                $user_input->sel_ndx++;
                if ($user_input->sel_ndx >= $groups_cnt) {
                    $user_input->sel_ndx = $groups_cnt - 1;
                }
                $this->set_edit_order($parent_media_url, $order);
                break;

            case ACTION_ITEM_DELETE:
                if ($parent_media_url->edit_list !== self::SCREEN_EDIT_PLAYLIST && $parent_media_url->edit_list !== self::SCREEN_EDIT_EPG_LIST) {
                    $id = MediaURL::decode($user_input->selected_media_url)->id;
                    $order->remove_item($id);
                    $this->set_edit_order($parent_media_url, $order);
                    return Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str()));
                }

                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_PLAYLIST_DLG_APPLY);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CLEAR_APPLY);

            case self::ACTION_CLEAR_APPLY:
                if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    foreach ($order as $item) {
                        $this->plugin->epg_man->clear_epg_cache_by_uri($item);
                    }
                }
                $order->clear();
                $user_input->sel_ndx = 0;
                $this->set_edit_order($parent_media_url, $order);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case self::ACTION_REMOVE_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_PLAYLIST_DLG_APPLY);

            case self::ACTION_REMOVE_PLAYLIST_DLG_APPLY:
                $order->remove_item_by_idx($user_input->sel_ndx);
                $this->set_edit_order($parent_media_url, $order);
                return User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID);

            case ACTION_ITEMS_SORT:
                $order->sort_order();
                $this->set_edit_order($parent_media_url, $order);
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items = array();
                if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST
                    || $parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {

                    $add_param = array('extension' => $parent_media_url->extension);
                    $menu_items[] = $this->plugin->create_menu_item($this, self::ACTION_ADD_URL_DLG, TR::t('edit_list_internet_path'),"link.png");

                    $add_param['action'] = $parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST ? self::ACTION_FILE_PLAYLIST : self::ACTION_FILE_XMLTV;
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        self::ACTION_CHOOSE_FILE, TR::t('select_file'),
                        $parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST ? "m3u_file.png" : "xmltv_file.png",
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
                    if ($order->size()) {
                        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_items'), "sort.png");
                    }
                }

                if ($order->size()) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear'), "brush.png");
                }

                return !empty($menu_items) ? Action_Factory::show_popup_menu($menu_items) : null;

            case self::ACTION_ADD_URL_DLG:
                $defs = array();
                Control_Factory::add_vgap($defs, 20);
                Control_Factory::add_text_field($defs, $this, null, self::ACTION_URL_PATH, '',
                    'http://', false, false, false, true, self::DLG_CONTROLS_WIDTH);

                Control_Factory::add_vgap($defs, 50);

                Control_Factory::add_close_dialog_and_apply_button($defs, $this, null,
                    self::ACTION_URL_DLG_APPLY, TR::t('ok'), 300);

                Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
                Control_Factory::add_vgap($defs, 10);

                return Action_Factory::show_dialog(TR::t('edit_link_caption'), $defs, true);

            case self::ACTION_URL_DLG_APPLY: // handle streaming settings dialog result
                if (isset($user_input->{self::ACTION_URL_PATH})
                    && preg_match("|https?://.+$|", $user_input->{self::ACTION_URL_PATH})) {

                    $pl = $user_input->{self::ACTION_URL_PATH};
                    if ($order->in_order($pl)) {
                        return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
                    }

                    $order->add_item($pl);
                    $this->set_edit_order($parent_media_url, $order);
                }
                return User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID);

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
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file->action === self::ACTION_FILE_TEXT_LIST) {
                    $lines = file($data->filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
                        return Action_Factory::show_title_dialog(TR::t('edit_list_empty_file'));
                    }

                    $old_count = $order->size();
                    $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
                    $error_log = array();
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (preg_match('|https?://|', $line)) {
                            if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
                                $this->plugin->epg_man->set_xmltv_url($line);
                                $res = $this->plugin->epg_man->is_xmltv_cache_valid();
                                $this->plugin->epg_man->set_xmltv_url(null);
                                if (!empty($res)) {
                                    $error_log[] = $res;
                                    continue;
                                }
                            } else if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
                                try {
                                    HD::http_get_document($line);
                                } catch (Exception $ex) {
                                    $error_log[] = $ex->getMessage();
                                    continue;
                                }
                            }

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

                    $this->set_edit_order($parent_media_url, $order);

                    return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $order->size() - $old_count, count($lines)),
                        Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str()))
                    );
                }

                if (($data->choose_file->action === self::ACTION_FILE_PLAYLIST || $data->choose_file->action === self::ACTION_FILE_XMLTV)
                    && !$order->add_item($data->filepath)) {

                    return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
                }
                break;

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
                $data = MediaURL::decode($user_input->selected_data);
                $file = glob_dir($data->filepath, "/\.$parent_media_url->extension$/i");
                if (empty($file)) {
                    return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
                }

                $old_count = $order->size();
                $order->add_items($file);
                $this->set_edit_order($parent_media_url, $order);

                return Action_Factory::show_title_dialog(TR::t('edit_list_added__1', $order->size() - $old_count),
                    Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str()))
                );

            case RESET_CONTROLS_ACTION_ID:
                return Action_Factory::change_behaviour(
                    $this->get_action_map($parent_media_url,$plugin_cookies),
                    0,
                    $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx)
                );
        }

        // refresh current screen
        return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $order = $this->get_edit_order($media_url);
        $items = array();
        foreach ($order as $item) {
            $title = $item;
            if ($media_url->edit_list === self::SCREEN_EDIT_CHANNELS) {
                if ($media_url->group_id === FAVORITES_GROUP_ID || $media_url->group_id === HISTORY_GROUP_ID) break;

                $channel = $this->plugin->tv->get_channel($item);
                if (is_null($channel)) continue;

                $icon_file = $channel->get_icon_url();
                if ($media_url->group_id !== ALL_CHANNEL_GROUP_ID) {

                    $group = $this->plugin->tv->get_group($media_url->group_id);
                    if (is_null($group) || ($channel = $group->get_group_channels()->get($item)) === null) continue;

                    $title = $channel->get_title();
                } else {
                    $title = $channel->get_title();
                    foreach($channel->get_groups() as $group) {
                        $title .= " | " . $group->get_title();
                    }
                }
            } else if ($media_url->edit_list === self::SCREEN_EDIT_GROUPS) {
                $group = $this->plugin->tv->get_group($item);
                if (is_null($group)) continue;

                $icon_file = $group->get_icon_url();
            } else {
                $icon_file = get_image_path("link.png");
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $item)),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $icon_file,
                    ViewItemParams::item_detailed_info => $title,
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

        if ($this->get_edit_order($media_url)->size() === 0) {
            $msg = is_apk()
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

    /**
     * @param MediaURL $media_url
     * @return Ordered_Array
     */
    private function get_edit_order($media_url)
    {
        hd_debug_print($media_url, true);

        switch ($media_url->edit_list) {
            case self::SCREEN_EDIT_PLAYLIST:
                $order = $this->plugin->get_playlists();
                break;

            case self::SCREEN_EDIT_EPG_LIST:
                $order = new Ordered_Array();
                foreach ($this->plugin->get_ext_xmltv_sources() as $source) {
                    $order->add_item($source);
                }
                break;

            case self::SCREEN_EDIT_GROUPS:
                $order = $this->plugin->get_disabled_groups();
                break;

            case self::SCREEN_EDIT_CHANNELS:
                $order = $this->plugin->get_disabled_channels();
                break;

            default:
                $order = new Ordered_Array();
        }

        return $order;
    }

    /**
     * @param MediaURL $media_url
     * @param Ordered_Array $order
     * @return void
     */
    private function set_edit_order($media_url, $order)
    {
        hd_debug_print($media_url, true);

        switch ($media_url->edit_list) {
            case self::SCREEN_EDIT_PLAYLIST:
                $this->plugin->set_playlists($order);
                break;

            case self::SCREEN_EDIT_EPG_LIST:
                $sources = new Hashed_Array();
                foreach ($order as $item) {
                    $sources->put($item);
                }
                $this->plugin->set_ext_xmltv_sources($sources);
                break;

            case self::SCREEN_EDIT_GROUPS:
                $this->plugin->set_disabled_groups($order);
                break;

            case self::SCREEN_EDIT_CHANNELS:
                $this->plugin->set_disabled_channels($order);
                break;

            default:
                hd_debug_print("Unknown edit list type");
        }
    }
}
