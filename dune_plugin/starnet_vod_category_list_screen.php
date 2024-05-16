<?php
require_once 'lib/abstract_preloaded_regular_screen.php';
require_once 'lib/vod_category.php';
require_once 'starnet_vod_list_screen.php';

class Starnet_Vod_Category_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'vod_category_list';

    /**
     * @var Vod_Category[]
     */
    private $category_list;

    /**
     * @var array
     */
    private $category_index;

    /**
     * @param string $category_id
     * @return false|string
     */
    public static function get_media_url_string($category_id)
    {
        return MediaURL::encode(array('screen_id' => static::ID, 'group_id' => VOD_GROUP_ID, 'category_id' => $category_id,));
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(
            GUI_EVENT_KEY_ENTER    => Action_Factory::open_folder(),
            GUI_EVENT_KEY_C_YELLOW => User_Input_Handler_Registry::create_action($this, ACTION_RELOAD, TR::t('vod_screen_reload_playlist')),
            GUI_EVENT_KEY_STOP     => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        if ($user_input->control_id === ACTION_RELOAD) {
            hd_debug_print("reload categories");
            $this->clear_vod();
            $media_url = MediaURL::decode($user_input->parent_media_url);
            $range = $this->get_folder_range($media_url, 0, $plugin_cookies);
            return Action_Factory::update_regular_folder($range, true, -1);
        }

        return null;
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url->get_media_url_str(), true);

        if (is_null($this->category_index) || is_null($this->category_list)) {
            $this->plugin->vod->fetchVodCategories($this->category_list, $this->category_index);
        }

        $category_list = $this->category_list;

        $items = array();

        if (isset($media_url->category_id)) {
            if ($media_url->category_id !== VOD_GROUP_ID) {
                if (!isset($this->category_index[$media_url->category_id])) {
                    hd_debug_print("Error: parent category (id: $media_url->category_id) not found.");
                    return array();
                }

                $parent_category = $this->category_index[$media_url->category_id];
                $category_list = $parent_category->get_sub_categories();
            } else {
                /** @var Group $group */
                foreach ($this->plugin->vod->get_special_groups() as $group) {
                    if (is_null($group)) continue;

                    hd_debug_print("group: '{$group->get_title()}' disabled: " . var_export($group->is_disabled(), true), true);
                    if ($group->is_disabled()) continue;

                    switch ($group->get_id()) {
                        case FAVORITES_MOVIE_GROUP_ID:
                            $color = DEF_LABEL_TEXT_COLOR_GOLD;
                            $item_detailed_info = TR::t('vod_screen_group_info__2', $group->get_title(), $group->get_items_order()->size());
                            break;

                        case HISTORY_MOVIES_GROUP_ID:
                            $color = DEF_LABEL_TEXT_COLOR_TURQUOISE;
                            $item_detailed_info = TR::t('vod_screen_group_info__2', $group->get_title(), $this->plugin->get_history(HISTORY_MOVIES)->size());
                            break;

                        default:
                            $color = DEF_LABEL_TEXT_COLOR_LIGHTGREEN;
                            $item_detailed_info = $group->get_title();
                            break;
                    }

                    hd_debug_print("special group: " . $group->get_media_url_str(), true);

                    $items[] = array(
                        PluginRegularFolderItem::media_url => $group->get_media_url_str(),
                        PluginRegularFolderItem::caption => $group->get_title(),
                        PluginRegularFolderItem::view_item_params => array(
                            ViewItemParams::item_caption_color => $color,
                            ViewItemParams::icon_path => $group->get_icon_url(),
                            ViewItemParams::item_detailed_icon_path => $group->get_icon_url(),
                            ViewItemParams::item_detailed_info => $item_detailed_info,
                        )
                    );
                }
            }
        }

        if (empty($category_list)) {
            return $items;
        }

        foreach ($category_list as $category) {
            $category_id = $category->get_id();
            if (!is_null($category->get_sub_categories())) {
                $media_url_str = self::get_media_url_string($category_id);
            } else if ($category_id === Vod_Category::FLAG_ALL
                || $category_id === Vod_Category::FLAG_SEARCH
                || $category_id === Vod_Category::FLAG_FILTER) {
                // special category id's
                $media_url_str = Starnet_Vod_List_Screen::get_media_url_string($category_id, null);
            } else if ($category->get_parent() !== null) {
                $media_url_str = Starnet_Vod_List_Screen::get_media_url_string($category->get_parent()->get_id(), $category_id);
            } else {
                $media_url_str = Starnet_Vod_List_Screen::get_media_url_string($category_id, null);
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => $media_url_str,
                PluginRegularFolderItem::caption => $category->get_caption(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $category->get_icon_path(),
                    ViewItemParams::item_detailed_icon_path => $category->get_icon_path(),
                )
            );
        }

        return $items;
    }

    /**
     * Clear vod information
     * @return void
     */
    public function clear_vod()
    {
        unset($this->category_list, $this->category_index);
        $this->plugin->vod->clear_movie_cache();
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
}
