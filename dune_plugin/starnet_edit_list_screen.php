<?php
require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_Edit_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'edit_list';

    const ACTION_PLAYLIST = 'playlist';
    const ACTION_EPG_LIST = 'epg_list';
    const ACTION_GROUPS = 'groups';
    const ACTION_CHANNELS = 'channels';

    const DLG_CONTROLS_WIDTH = 850;

    const SETUP_ACTION_CLEAR_APPLY = 'clear_apply';
    const SETUP_ACTION_REMOVE_PLAYLIST_DLG = 'remove_playlist';
    const SETUP_ACTION_REMOVE_PLAYLIST_APPLY = 'remove_playlist_apply';
    const SETUP_ACTION_CHOOSE_FOLDER = 'choose_folder';
    const SETUP_ACTION_IMPORT_LIST = 'import_list';
    const SETUP_ACTION_ADD_URL_DLG = 'add_url_dialog';
    const SETUP_ACTION_URL_DLG_APPLY = 'url_dlg_apply';
    const SETUP_ACTION_URL_PATH = 'url_path';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct(self::ID, $plugin);

        $plugin->create_screen($this);
    }

    /**
     * @return string
     */
    public function get_handler_id()
    {
        return self::ID . '_handler';
    }

    /**
     * @param string $id
     * @return false|string
     */
    public static function get_media_url_str($id)
    {
        return MediaURL::encode(array('screen_id' => self::ID, 'id' => $id));
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_print(__METHOD__);
        $actions = array();

        if ($media_url->edit_list === self::ACTION_PLAYLIST) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        }
        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
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
        dump_input_handler(__METHOD__, $user_input);
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
                if ($parent_media_url->edit_list === self::ACTION_PLAYLIST) {
                    return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::SETUP_ACTION_REMOVE_PLAYLIST_APPLY);
                }

                $id = MediaURL::decode($user_input->selected_media_url)->id;
                $order->remove_item($id);
                break;

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::SETUP_ACTION_CLEAR_APPLY);

            case self::SETUP_ACTION_CLEAR_APPLY:
                $order->clear();
                $user_input->sel_ndx = 0;
                break;

            case self::SETUP_ACTION_REMOVE_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::SETUP_ACTION_REMOVE_PLAYLIST_APPLY);

            case self::SETUP_ACTION_REMOVE_PLAYLIST_APPLY:
                $order->remove_item_by_idx($user_input->sel_ndx);
                break;

            case ACTION_ITEMS_SORT:
                $order->sort_order();
                break;

            case GUI_EVENT_KEY_RETURN:
                $post_action = User_Input_Handler_Registry::create_action_screen($parent_media_url->source_window_id, $parent_media_url->end_action);
                return Action_Factory::replace_path($parent_media_url->windowCounter, null, $post_action);

            case GUI_EVENT_KEY_POPUP_MENU:
                if ($parent_media_url->edit_list === self::ACTION_PLAYLIST
                    || $parent_media_url->edit_list === self::ACTION_EPG_LIST) {

                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        self::SETUP_ACTION_ADD_URL_DLG,
                        TR::t('edit_list_internet_path'),
                        $this->plugin->get_image_path('link.png')
                    );
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        self::SETUP_ACTION_CHOOSE_FOLDER,
                        TR::t('edit_list_folder_path'),
                        $this->plugin->get_image_path('folder.png')
                    );
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        self::SETUP_ACTION_IMPORT_LIST,
                        TR::t('edit_list_import_list'),
                        $this->plugin->get_image_path('web_files.png')
                    );

                    $menu_items[] = array(GuiMenuItemDef::is_separator => true,);

                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        ACTION_ITEMS_SORT,
                        TR::t('sort_items'),
                        $this->plugin->get_image_path('sort.png')
                    );
                }

                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_ITEMS_CLEAR,
                    TR::t('clear'),
                    $this->plugin->get_image_path('brush.png')
                );

                return Action_Factory::show_popup_menu($menu_items);

            case self::SETUP_ACTION_ADD_URL_DLG:
                $defs = array();
                Control_Factory::add_vgap($defs, 20);
                Control_Factory::add_text_field($defs, $this, null, self::SETUP_ACTION_URL_PATH, '',
                    'http://', false, false, false, true, self::DLG_CONTROLS_WIDTH);

                Control_Factory::add_vgap($defs, 50);

                Control_Factory::add_close_dialog_and_apply_button($defs, $this, null,
                    self::SETUP_ACTION_URL_DLG_APPLY, TR::t('ok'), 300);

                Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
                Control_Factory::add_vgap($defs, 10);

                return Action_Factory::show_dialog(TR::t('edit_link_caption'), $defs, true);

            case self::SETUP_ACTION_URL_DLG_APPLY: // handle streaming settings dialog result
                if (isset($user_input->{self::SETUP_ACTION_URL_PATH})
                    && preg_match("|https?://.+$|", $user_input->{self::SETUP_ACTION_URL_PATH})) {

                    $pl = $user_input->{self::SETUP_ACTION_URL_PATH};
                    if ($order->in_order($pl)) {
                        return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
                    }
                    $order->add_item($pl);
                }
                break;

            case self::SETUP_ACTION_CHOOSE_FOLDER:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'parent_id' => self::ID,
                        'save_data' => self::ID,
                        'allow_network' => !is_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('edit_list_src_folder'));

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                hd_print(__METHOD__ . ": " . ACTION_FOLDER_SELECTED . " $data->filepath");
                $files = preg_grep($parent_media_url->extension, glob("$data->filepath/*.*"));
                if (empty($files)) {
                    return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
                }

                $old_count = $order->size();
                foreach ($files as $file) {
                    //hd_print("file: $file");
                    if (is_file($file) && !$order->in_order($file)) {
                        $order->add_item($file);
                    }
                }

                return Action_Factory::show_title_dialog(TR::t('edit_list_added__1', $order->size() - $old_count),
                    Action_Factory::invalidate_folders(array(self::ID), Action_Factory::update_regular_folder(
                        $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
                        true,
                        $user_input->sel_ndx))
                    );

            case self::SETUP_ACTION_IMPORT_LIST:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'parent_id'	=> self::ID,
                        'save_file'	=> array(
                            'action'	=> 'choose_file',
                            'arg'		=> 0,
                            'extension'	=> 'txt',
                        ),
                        'allow_network' => !is_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('edit_list_src_list'));

            default:
                return null;
        }

        // refresh current screen
        return Action_Factory::invalidate_folders(array(self::ID), Action_Factory::update_regular_folder(
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
        //hd_print(__METHOD__);
        //hd_print(__METHOD__ . $media_url->get_media_url_str());

        $order = $this->get_edit_order($media_url);
        $items = array();
        foreach ($order->get_order() as $item) {
            //hd_print("order item media url: " . self::get_media_url_str($item));
            $title = $item;
            if ($media_url->edit_list === self::ACTION_CHANNELS) {
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
                PluginRegularFolderItem::media_url => self::get_media_url_str($item),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewItemParams::item_detailed_info => $title,
                ),
            );
        }

        //hd_print("Loaded items " . count($items));
        return $items;
    }

    /**
     * @return array[]
     */
    public function get_folder_views()
    {
        return array(

            // 1x12 list view with info
            array
            (
                PluginRegularFolderView::async_icon_loading => false,
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
                PluginRegularFolderView::async_icon_loading => false,

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
        //hd_print(__METHOD__ . ": media url: " . $media_url->get_media_url_str());

        switch ($media_url->edit_list) {
            case self::ACTION_PLAYLIST:
                $order = $this->plugin->get_playlists();
                break;
            case self::ACTION_EPG_LIST:
                $order = new Ordered_Array();
                $order->set_callback($this->plugin, PARAM_CUSTOM_XMLTV_SOURCES);
                break;
            case self::ACTION_GROUPS:
                $order = $this->plugin->tv->get_disabled_groups();
                break;
            case self::ACTION_CHANNELS:
                $order = $this->plugin->tv->get_disabled_channels();
                break;
            default:
                $order = new Ordered_Array();
        }

        return $order;
    }
}
