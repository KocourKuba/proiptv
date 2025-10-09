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
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $actions[GUI_EVENT_KEY_ENTER] = Action_Factory::open_folder();
        $actions[GUI_EVENT_KEY_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_CLEAR);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        $parent_media_url = MediaURL::decode(safe_get_member($user_input, 'parent_media_url', ''));
        $sel_media_url = MediaURL::decode(safe_get_member($user_input, 'selected_media_url', ''));
        $group_id = safe_get_member($sel_media_url, COLUMN_GROUP_ID);

        switch ($user_input->control_id) {
            case ACTION_RELOAD:
                hd_debug_print("reload categories");
                $this->clear_vod();
                $range = $this->get_folder_range($parent_media_url, 0, $plugin_cookies);
                return Action_Factory::update_regular_folder($range, true, -1);

            case GUI_EVENT_TIMER:
                $error = Default_Dune_Plugin::get_last_error(LAST_ERROR_VOD_LIST);
                if (empty($error)) break;

                return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $error);

            case ACTION_INVALIDATE:
                $range = $this->get_folder_range($parent_media_url, 0, $plugin_cookies);
                return Action_Factory::update_regular_folder($range, true, -1);

            case GUI_EVENT_KEY_POPUP_MENU:
                $title = TR::t('playlist_name_msg__1', TR::t(VOD_GROUP_CAPTION));
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RELOAD, $title, "refresh.png");
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                if ($group_id === VOD_FAV_GROUP_ID && $this->plugin->get_order_count(VOD_FAV_GROUP_ID)) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "brush.png");
                } else if ($group_id === VOD_HISTORY_GROUP_ID && $this->plugin->get_all_vod_history_count() !== 0) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_history'), "brush.png");
                } else if ($group_id === VOD_LIST_GROUP_ID && $this->plugin->get_order_count(VOD_LIST_GROUP_CAPTION)) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_list'), "brush.png");
                }
                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                if ($group_id === VOD_HISTORY_GROUP_ID) {
                    $this->plugin->clear_all_vod_history();
                    return User_Input_Handler_Registry::create_action($this, ACTION_INVALIDATE);
                }

                if ($group_id === VOD_FAV_GROUP_ID) {
                    $this->plugin->change_vod_favorites(ACTION_ITEMS_CLEAR, null);
                    return User_Input_Handler_Registry::create_action($this, ACTION_INVALIDATE);
                }

                if ($group_id === VOD_LIST_GROUP_ID) {
                    $this->plugin->remove_channels_order(VOD_LIST_GROUP_ID);
                    $this->plugin->vod->toggle_special_group(VOD_LIST_GROUP_ID, true);
                    return User_Input_Handler_Registry::create_action($this, ACTION_INVALIDATE);
                }
                break;


            default:
                break;
        }

        return null;
    }

    /**
     * Clear vod information
     * @return void
     */
    public function clear_vod()
    {
        unset($this->category_list, $this->category_index);
        $this->plugin->vod->clear_movie_cache();
        $vod_cache = $this->plugin->vod->get_vod_cache_file();
        if (file_exists($vod_cache)) {
            unlink($vod_cache);
        }

        $this->plugin->get_sql_playlist()->detachDatabase('vod');
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        if (!isset($this->category_index, $this->category_list)) {
            if (!$this->plugin->vod->fetchVodCategories($this->category_list, $this->category_index)) {
                hd_debug_print("Error: Fetch categories");
                return array();
            }
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
                foreach ($this->plugin->vod->get_special_groups() as $group) {
                    if (empty($group) || $group[ACTION_DISABLED]) continue;

                    $skip = false;
                    $color = DEF_LABEL_TEXT_COLOR_WHITE;
                    $item_detailed_info = $group[COLUMN_TITLE];

                    switch ($group[COLUMN_GROUP_ID]) {
                        case VOD_FAV_GROUP_ID:
                            $cnt = $this->plugin->get_order_count(VOD_FAV_GROUP_ID);
                            if (!$cnt) {
                                $skip = true;
                                break;
                            }

                            $color = DEF_LABEL_TEXT_COLOR_GOLD;
                            $item_detailed_info = TR::t('vod_screen_group_info__2', TR::t(VOD_FAV_GROUP_CAPTION), $cnt);
                            break;

                        case VOD_HISTORY_GROUP_ID:
                            $cnt = $this->plugin->get_all_vod_history_count();
                            if ($cnt === 0) {
                                $skip = true;
                                break;
                            }

                            $color = DEF_LABEL_TEXT_COLOR_TURQUOISE;
                            $item_detailed_info = TR::t('vod_screen_group_info__2', TR::t(VOD_HISTORY_GROUP_CAPTION), $cnt);
                            break;

                        case VOD_LIST_GROUP_ID:
                            if (!$this->plugin->is_m3u_vod()) {
                                $skip = true;
                                break;
                            }
                            $cnt = $this->plugin->get_order_count(VOD_LIST_GROUP_ID);
                            if (!$cnt) {
                                $skip = true;
                                break;
                            }

                            $color = DEF_LABEL_TEXT_COLOR_VIOLET;
                            $item_detailed_info = TR::t('vod_screen_group_info__2', TR::t('movie_list'), $cnt);
                            break;

                        case VOD_FILTER_GROUP_ID:
                            $color = DEF_LABEL_TEXT_COLOR_LIGHTGREEN;
                            $item_detailed_info = TR::t('filters');
                            break;

                        case VOD_SEARCH_GROUP_ID:
                            $color = DEF_LABEL_TEXT_COLOR_LIGHTGREEN;
                            $item_detailed_info = TR::t(VOD_SEARCH_GROUP_CAPTION);
                            break;

                        default:
                            break;
                    }

                    if ($skip) continue;

                    hd_debug_print("special group: " . Default_Dune_Plugin::get_group_mediaurl_str($group[COLUMN_GROUP_ID]), true);

                    $items[] = array(
                        PluginRegularFolderItem::media_url => Default_Dune_Plugin::get_group_mediaurl_str($group[COLUMN_GROUP_ID]),
                        PluginRegularFolderItem::caption => TR::t($group[COLUMN_TITLE]),
                        PluginRegularFolderItem::view_item_params => array(
                            ViewItemParams::item_caption_color => $color,
                            ViewItemParams::icon_path => $group[COLUMN_ICON],
                            ViewItemParams::item_detailed_icon_path => $group[COLUMN_ICON],
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
                $media_url_str = self::make_custom_media_url_str($category_id);
            } else if ($category_id === Vod_Category::FLAG_ALL_MOVIES
                || $category_id === Vod_Category::FLAG_ALL_SERIALS
                || $category_id === Vod_Category::FLAG_SEARCH
                || $category_id === Vod_Category::FLAG_FILTER) {
                // special category id's
                $media_url_str = Starnet_Vod_Movie_List_Screen::make_custom_media_url_str($category_id, null);
            } else if ($category->get_parent() !== null) {
                $media_url_str = Starnet_Vod_Movie_List_Screen::make_custom_media_url_str($category->get_parent()->get_id(), $category_id);
            } else {
                $media_url_str = Starnet_Vod_Movie_List_Screen::make_custom_media_url_str($category_id, null);
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => $media_url_str,
                PluginRegularFolderItem::caption => $category->get_caption(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_caption_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewItemParams::icon_path => $category->get_icon_path(),
                    ViewItemParams::item_detailed_icon_path => $category->get_icon_path(),
                )
            );
        }

        return $items;
    }

    /**
     * Get MediaURL string representation (json encoded)
     * *
     * @param string $category_id
     * @return false|string
     */
    public static function make_custom_media_url_str($category_id)
    {
        return MediaURL::encode(array(PARAM_SCREEN_ID => static::ID, 'group_id' => VOD_GROUP_ID, 'category_id' => $category_id,));
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
