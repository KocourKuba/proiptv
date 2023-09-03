<?php
require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_Edit_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'edit_list';

    const SCREEN_TYPE_PLAYLIST = 'playlist';
    const SCREEN_TYPE_EPG_LIST = 'epg_list';
    const SCREEN_TYPE_GROUPS = 'groups';
    const SCREEN_TYPE_CHANNELS = 'channels';

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
        //hd_debug_print();
        $actions = array();
        if ($this->get_edit_order($media_url)->size()) {
            if ($media_url->edit_list === self::SCREEN_TYPE_PLAYLIST) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            }
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
        } else if (is_android()) {
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_EMPTY, TR::t('edit_list_add'));
        }

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, TR::t('add'));
        return $actions;
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $order = $this->get_edit_order($parent_media_url);

        switch ($user_input->control_id) {
            case ACTION_ITEM_UP:
                $id = MediaURL::decode($user_input->selected_media_url)->id;
                if (!$order->arrange_item($id, Ordered_Array::UP))
                    return null;

                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }
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
                break;

            case ACTION_ITEM_DELETE:
                if ($parent_media_url->edit_list === self::SCREEN_TYPE_PLAYLIST) {
                    return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_PLAYLIST_DLG_APPLY);
                }

                $id = MediaURL::decode($user_input->selected_media_url)->id;
                $order->remove_item($id);
                break;

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CLEAR_APPLY);

            case self::ACTION_CLEAR_APPLY:
                if ($parent_media_url->edit_list === self::SCREEN_TYPE_EPG_LIST) {
                    foreach ($order->get_order() as $item) {
                        $this->plugin->epg_man->clear_epg_cache_by_uri($item);
                    }
                }
                $order->clear();
                $user_input->sel_ndx = 0;
                break;

            case self::ACTION_REMOVE_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_PLAYLIST_DLG_APPLY);

            case self::ACTION_REMOVE_PLAYLIST_DLG_APPLY:
                $order->remove_item_by_idx($user_input->sel_ndx);
                break;

            case ACTION_ITEMS_SORT:
                $order->sort_order();
                break;

            case GUI_EVENT_KEY_RETURN:
                $post_action = User_Input_Handler_Registry::create_action_screen($parent_media_url->source_window_id, $parent_media_url->end_action);
                return Action_Factory::replace_path($parent_media_url->windowCounter, null, $post_action);

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items = array();
                if ($parent_media_url->edit_list === self::SCREEN_TYPE_PLAYLIST
                    || $parent_media_url->edit_list === self::SCREEN_TYPE_EPG_LIST) {

                    $add_param = array('extension' => $parent_media_url->extension);
                    $this->create_menu_item($menu_items, self::ACTION_ADD_URL_DLG, TR::t('edit_list_internet_path'),"link.png");

                    $add_param['action'] = $parent_media_url->edit_list === self::SCREEN_TYPE_PLAYLIST ? self::ACTION_FILE_PLAYLIST : self::ACTION_FILE_XMLTV;
                    $this->create_menu_item($menu_items, self::ACTION_CHOOSE_FILE, TR::t('edit_list_file'),
                        $parent_media_url->edit_list === self::SCREEN_TYPE_PLAYLIST ? "m3u_file.png" : "xmltv_file.png", $add_param);

                    $add_param['action'] = self::ACTION_FILE_TEXT_LIST;
                    $add_param['extension'] = 'txt|lst';
                    $this->create_menu_item($menu_items, self::ACTION_CHOOSE_FILE, TR::t('edit_list_import_list'), "text_file.png", $add_param);

                    unset($add_param['action']);
                    $this->create_menu_item($menu_items, self::ACTION_CHOOSE_FOLDER, TR::t('edit_list_folder_path'), "folder.png", $add_param);
                    $this->create_menu_item($menu_items, GuiMenuItemDef::is_separator);
                    if ($order->size()) {
                        $this->create_menu_item($menu_items, ACTION_ITEMS_SORT, TR::t('sort_items'), "sort.png");
                    }
                }

                if ($order->size()) {
                    $this->create_menu_item($menu_items, ACTION_ITEMS_CLEAR, TR::t('clear'), "brush.png");
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
                }
                break;

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
                        hd_debug_print("imported: '$line'");
                        if (preg_match('|https?://|', $line)) {
                            $order->add_item($line);
                        }
                    }

                    if ($old_count === $order->size()) {
                        return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
                    }

                    return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $order->size() - $old_count, count($lines)),
                        Action_Factory::invalidate_folders(array(static::ID), Action_Factory::update_regular_folder(
                            $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
                            true,
                            $user_input->sel_ndx))
                    );
                }

                if ($data->choose_file->action !== self::ACTION_FILE_PLAYLIST && $data->choose_file->action !== self::ACTION_FILE_XMLTV) break;

                if (!$order->add_item($data->filepath)) {
                    return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
                }

                $user_input->sel_ndx = $order->size() - 1;
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

                return Action_Factory::show_title_dialog(TR::t('edit_list_added__1', $order->size() - $old_count),
                    Action_Factory::invalidate_folders(array(static::ID), Action_Factory::update_regular_folder(
                        $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
                        true,
                        $user_input->sel_ndx))
                    );

            default:
                return null;
        }

        // refresh current screen
        return Action_Factory::invalidate_folders(array(static::ID), Action_Factory::update_regular_folder(
            $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
            true,
            $user_input->sel_ndx)
        );
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
            if ($media_url->edit_list === self::SCREEN_TYPE_CHANNELS) {
                if ($media_url->group_id === FAV_CHANNEL_GROUP_ID || $media_url->group_id === PLAYBACK_HISTORY_GROUP_ID) break;

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

        //hd_debug_print("Loaded items " . count($items));
        return $items;
    }

    /*
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print();
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

            // 1x12 list view with info
            array
            (
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_details => false,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                ),
                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => false,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_caption_dx => 20,
                    ViewItemParams::item_caption_width => 1700,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            // 3x10 list view
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 10,
                    ViewParams::paint_details => false,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => false,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_caption_dx => 20,
                    ViewItemParams::item_caption_width => 485,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),
        );
    }

    /**
     * @param $media_url
     * @return Ordered_Array
     */
    private function get_edit_order($media_url)
    {
        //hd_debug_print($media_url->get_media_url_str());

        switch ($media_url->edit_list) {
            case self::SCREEN_TYPE_PLAYLIST:
                $order = $this->plugin->get_playlists();
                break;
            case self::SCREEN_TYPE_EPG_LIST:
                $order = new Ordered_Array($this->plugin, PARAM_CUSTOM_XMLTV_SOURCES);
                break;
            case self::SCREEN_TYPE_GROUPS:
                $order = $this->plugin->tv->get_disabled_groups();
                break;
            case self::SCREEN_TYPE_CHANNELS:
                $order = $this->plugin->tv->get_disabled_channels();
                break;
            default:
                $order = new Ordered_Array();
        }

        return $order;
    }

    /**
     * @param $menu_items array
     * @param $action_id string
     * @param $caption string
     * @param $icon string
     * @param $add_params array|null
     * @return void
     */
    private function create_menu_item(&$menu_items, $action_id, $caption = null, $icon = null, $add_params = null)
    {
        if ($action_id === GuiMenuItemDef::is_separator) {
            $menu_items[] = array($action_id => true);
        } else {
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                $action_id, $caption, ($icon === null) ? null : get_image_path($icon), $add_params);
        }
    }
}
