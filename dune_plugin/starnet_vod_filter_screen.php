<?php
require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_Vod_Filter_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'filter_screen';
    const FILTER_ICON_PATH = 'plugin_file://icons/icon_filter.png';

    const VOD_FILTER_ITEM = 'vod_filter_item';

    /**
     * @param string $category
     * @return false|string
     */
    public static function get_media_url_string($category = '')
    {
        return MediaURL::encode(array('screen_id' => self::ID, 'category' => $category));
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $actions = array();
        $add_params['filter_actions'] = 'open';
        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, ACTION_CREATE_FILTER, null, $add_params);

        $add_params['filter_actions'] = 'keyboard';
        $actions[GUI_EVENT_KEY_PLAY] = User_Input_Handler_Registry::create_action($this, ACTION_CREATE_FILTER, null, $add_params);

        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
        $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = Action_Factory::show_popup_menu(array());

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        switch ($user_input->control_id) {
            case ACTION_CREATE_FILTER:
                if (!isset($user_input->parent_media_url)) break;

                $media_url = MediaURL::decode($user_input->selected_media_url);
                if ($media_url->genre_id !== Vod_Category::FLAG_FILTER && $user_input->filter_actions !== 'keyboard') {
                    return Action_Factory::open_folder($user_input->selected_media_url);
                }

                if ($user_input->filter_actions === 'keyboard') {
                    $filter_string = $media_url->genre_id;
                } else {
                    $filter_items = $this->plugin->get_history(VOD_FILTER_LIST, new Ordered_Array());
                    $filter_string = $filter_items->size() === 0 ? "" : $filter_items[0];
                }

                $defs = array();
                if (false === $this->plugin->vod->AddFilterUI($defs, $this, $filter_string)) break;

                Control_Factory::add_close_dialog_and_apply_button($defs, $this, null, ACTION_RUN_FILTER, TR::t('ok'), 300);
                Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
                Control_Factory::add_vgap($defs, 10);

                return Action_Factory::show_dialog(TR::t('filter'), $defs, true);

            case ACTION_RUN_FILTER:
                $filter_string = $this->plugin->vod->CompileSaveFilterItem($user_input);
                if (empty($filter_string)) break;

                hd_debug_print("filter_screen filter string: $filter_string");
                $filter_items = &$this->plugin->get_history(VOD_FILTER_LIST, new Ordered_Array());
                $filter_items->insert_item($filter_string, false);
                $this->plugin->save_history(true);
                return Action_Factory::invalidate_folders(
                    array(self::get_media_url_string(FILTER_MOVIES_GROUP_ID)),
                    Action_Factory::open_folder(
                        Starnet_Vod_List_Screen::get_media_url_string(Vod_Category::FLAG_FILTER, $filter_string),
                        TR::t('filter__1', $filter_string)));

            case ACTION_ITEM_UP:
            case ACTION_ITEM_DOWN:
            case ACTION_ITEM_DELETE:
                if (!isset($user_input->selected_media_url)) break;

                $media_url = MediaURL::decode($user_input->selected_media_url);
                $filter_items = &$this->plugin->get_history(VOD_FILTER_LIST, new Ordered_Array());

                switch ($user_input->control_id) {
                    case ACTION_ITEM_UP:
                        $user_input->sel_ndx--;
                        $filter_items->arrange_item($media_url->genre_id, Ordered_Array::UP);
                        $this->set_changes();
                        break;

                    case ACTION_ITEM_DOWN:
                        $user_input->sel_ndx++;
                        $filter_items->arrange_item($media_url->genre_id, Ordered_Array::DOWN);
                        $this->set_changes();
                        break;

                    case ACTION_ITEM_DELETE:
                        $filter_items->remove_item($media_url->genre_id);
                        $this->set_changes();
                        break;
                }

                return Action_Factory::invalidate_folders(array(self::get_media_url_string(FILTER_MOVIES_GROUP_ID)));
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $items[] = array(
            PluginRegularFolderItem::media_url => Starnet_Vod_List_Screen::get_media_url_string(
                Vod_Category::FLAG_FILTER, Vod_Category::FLAG_FILTER),
            PluginRegularFolderItem::caption => TR::t('vod_screen_new_filter'),
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::icon_path => self::FILTER_ICON_PATH,
                ViewItemParams::item_detailed_icon_path => self::FILTER_ICON_PATH,
            ),
        );

        foreach ($this->plugin->get_history(VOD_FILTER_LIST, new Ordered_Array()) as $item) {
            if (!empty($item)) {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_Vod_List_Screen::get_media_url_string(
                        Vod_Category::FLAG_FILTER, $item),
                    PluginRegularFolderItem::caption => TR::t('filter__1', $item),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => self::FILTER_ICON_PATH,
                        ViewItemParams::item_detailed_icon_path => self::FILTER_ICON_PATH,
                    ),
                );
            }
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
            $this->plugin->get_screen_view('list_1x11_small_info'),
        );
    }
}
