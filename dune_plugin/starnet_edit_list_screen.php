<?php
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
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_debug_print($media_url->get_media_url_str());
        $actions = array();
        if ($this->get_edit_order($media_url)->size()) {
            if (isset($media_url->allow_order) && $media_url->allow_order) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            }
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
        } else if (is_android()) {
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_EMPTY, TR::t('edit_list_add'));
        }

        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, TR::t('add'));

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);

        return $actions;
    }

    /**
     * @param Object $user_input
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $order = $this->get_edit_order($parent_media_url);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:

                if (!isset($parent_media_url->postpone_save)) {
                    $need_reload = true;
                } else {
                    $need_reload = $this->plugin->is_durty($parent_media_url->postpone_save);
                    $this->plugin->set_pospone_save(false, $parent_media_url->postpone_save);
                }

                hd_debug_print("Need reload: " . var_export($need_reload, true));

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
                    return User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID);
                }

                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_PLAYLIST_DLG_APPLY);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CLEAR_APPLY);

            case self::ACTION_CLEAR_APPLY:
                if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    foreach ($order->get_order() as $item) {
                        $this->plugin->epg_man->clear_epg_cache_by_uri($item);
                    }
                }
                $order->clear();
                $user_input->sel_ndx = 0;
                $this->set_edit_order($parent_media_url, $order);
                return User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID);

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
                    $this->create_menu_item($this, $menu_items, self::ACTION_ADD_URL_DLG, TR::t('edit_list_internet_path'),"link.png");

                    $add_param['action'] = $parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST ? self::ACTION_FILE_PLAYLIST : self::ACTION_FILE_XMLTV;
                    $this->create_menu_item($this, $menu_items, self::ACTION_CHOOSE_FILE, TR::t('edit_list_file'),
                        $parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST ? "m3u_file.png" : "xmltv_file.png", $add_param);

                    $add_param['action'] = self::ACTION_FILE_TEXT_LIST;
                    $add_param['extension'] = 'txt|lst';
                    $this->create_menu_item($this, $menu_items, self::ACTION_CHOOSE_FILE, TR::t('edit_list_import_list'), "text_file.png", $add_param);

                    unset($add_param['action']);
                    $this->create_menu_item($this, $menu_items, self::ACTION_CHOOSE_FOLDER, TR::t('edit_list_folder_path'), "folder.png", $add_param);
                    $this->create_menu_item($this, $menu_items, GuiMenuItemDef::is_separator);
                    if ($order->size()) {
                        $this->create_menu_item($this, $menu_items, ACTION_ITEMS_SORT, TR::t('sort_items'), "sort.png");
                    }
                }

                if ($order->size()) {
                    $this->create_menu_item($this, $menu_items, ACTION_ITEMS_CLEAR, TR::t('clear'), "brush.png");
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
                        'allow_network' => !is_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('edit_list_file'));

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file->action === self::ACTION_FILE_TEXT_LIST) {
                    $lines = file($data->filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
                        return Action_Factory::show_title_dialog(TR::t('edit_list_empty_file'));
                    }

                    $old_count = $order->size();
                    $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (preg_match('|https?://|', $line)) {
                            $order->add_item($line);
                            hd_debug_print("imported: '$line'");
                        }
                    }

                    if ($old_count === $order->size()) {
                        return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
                    }

                    $this->set_edit_order($parent_media_url, $order);

                    return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $order->size() - $old_count, count($lines)),
                        User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID));
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
                        'allow_network' => !is_apk(),
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
                    User_Input_Handler_Registry::create_action($this, RESET_CONTROLS_ACTION_ID));

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
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_debug_print();
        //hd_debug_print($media_url->get_media_url_str());

        $order = $this->get_edit_order($media_url);
        $items = array();
        foreach ($order->get_order() as $item) {
            $title = $item;
            if ($media_url->edit_list === self::SCREEN_EDIT_CHANNELS) {
                if ($media_url->group_id === FAVORITES_GROUP_ID || $media_url->group_id === HISTORY_GROUP_ID) break;

                $channel = $this->plugin->tv->get_channel($item);
                if (is_null($channel)) continue;

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
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $item)),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewItemParams::item_detailed_info => $title,
                ),
            );
        }

        return $items;
    }

    /*
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_debug_print();
        //hd_debug_print($media_url->get_media_url_str());

        $folder_view = parent::get_folder_view($media_url, $plugin_cookies);

        if ($this->get_edit_order($media_url)->size() === 0) {
            $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] =
                TR::t('edit_list_add_prompt__3', 300, 300, DEF_LABEL_TEXT_COLOR_YELLOW);
        } else {
            $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = null;
        }

        return $folder_view;
    }
*/

    /**
     * @return array[]
     */
    public function get_folder_views()
    {
        return array(
            $this->plugin->get_screen_view('list_1x12_info'),
            $this->plugin->get_screen_view('list_2x12_info'),
            $this->plugin->get_screen_view('list_3x12_no_info'),
            $this->plugin->get_screen_view('icons_4x3_caption'),
            $this->plugin->get_screen_view('icons_5x3_caption'),
        );
    }

    /**
     * @param $media_url
     * @return Ordered_Array
     */
    private function get_edit_order($media_url)
    {
        hd_debug_print($media_url->get_media_url_str());

        switch ($media_url->edit_list) {
            case self::SCREEN_EDIT_PLAYLIST:
                $order = $this->plugin->get_playlists();
                break;
            case self::SCREEN_EDIT_EPG_LIST:
                $order = $this->plugin->get_xmltv_sources();
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
     * @param $media_url
     * @param $order
     * @return void
     */
    private function set_edit_order($media_url, $order)
    {
        //hd_debug_print($media_url->get_media_url_str());

        switch ($media_url->edit_list) {
            case self::SCREEN_EDIT_PLAYLIST:
                $this->plugin->set_playlists($order);
                break;
            case self::SCREEN_EDIT_EPG_LIST:
                $this->plugin->set_xmltv_sources($order);
                break;
            case self::SCREEN_EDIT_GROUPS:
                $this->plugin->set_disabled_groups($order);
                break;
            case self::SCREEN_EDIT_CHANNELS:
                $this->plugin->set_disabled_channels($order);
                break;
            default:
        }
    }
}
