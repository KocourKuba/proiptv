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

class Starnet_Vod_Filter_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'filter_screen';
    const FILTER_ICON_PATH = 'plugin_file://icons/icon_filter.png';

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(
            GUI_EVENT_KEY_ENTER => User_Input_Handler_Registry::create_action($this,
                ACTION_CREATE_FILTER, null, array(ACTION_FILTER => ACTION_OPEN_FOLDER)),
            GUI_EVENT_KEY_B_GREEN => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up')),
            GUI_EVENT_KEY_C_YELLOW => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down')),
            GUI_EVENT_KEY_D_BLUE => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete')),
            GUI_EVENT_KEY_POPUP_MENU => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $sel_ndx = $user_input->sel_ndx;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($user_input->selected_media_url)
                    && MediaURL::decode($user_input->selected_media_url)->genre_id !== Vod_Category::FLAG_FILTER) {

                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_EDIT, TR::t('edit'), "edit.png");
                    return Action_Factory::show_popup_menu($menu_items);
                }

                break;

            case ACTION_CREATE_FILTER:
                $media_url = MediaURL::decode($user_input->selected_media_url);
                if ($media_url->genre_id !== Vod_Category::FLAG_FILTER && $user_input->{ACTION_FILTER} === ACTION_OPEN_FOLDER) {
                    return Action_Factory::open_folder($user_input->selected_media_url);
                }

                if ($user_input->{ACTION_FILTER} === ACTION_ITEMS_EDIT) {
                    $filter_idx = $this->plugin->get_table_value_id(VOD_FILTER_LIST, $media_url->genre_id);
                } else {
                    $filter_idx = -1;
                }

                return $this->plugin->vod->AddFilterUI($this, $filter_idx);

            case ACTION_RUN_FILTER:
                $filter_string = $this->plugin->vod->CompileSaveFilterItem($user_input);
                if (empty($filter_string)) break;

                hd_debug_print("filter_screen filter string: $filter_string", true);
                if (isset($user_input->{ACTION_ITEMS_EDIT})) {
                    $idx = (int)$user_input->{ACTION_ITEMS_EDIT};
                } else {
                    $idx = -1;
                }
                $this->plugin->set_table_value(VOD_FILTER_LIST, $filter_string, $idx);

                return Action_Factory::invalidate_folders(
                    array($user_input->parent_media_url),
                    Action_Factory::open_folder(
                        Starnet_Vod_List_Screen::get_media_url_string(Vod_Category::FLAG_FILTER, $filter_string),
                        TR::t('filter')
                    )
                );

            case ACTION_ITEMS_EDIT:
                return User_Input_Handler_Registry::create_action($this,
                    ACTION_CREATE_FILTER,
                    null,
                    array(ACTION_FILTER => ACTION_ITEMS_EDIT)
                );

            case ACTION_ITEM_UP:
            case ACTION_ITEM_DOWN:
            case ACTION_ITEM_DELETE:
                if (!isset($user_input->selected_media_url)) break;

                $media_url = MediaURL::decode($user_input->selected_media_url);
                switch ($user_input->control_id) {
                    case ACTION_ITEM_UP:
                        $sel_ndx--;
                        if ($sel_ndx < 1) {
                            return null;
                        }
                        $this->plugin->arrange_table_values(VOD_FILTER_LIST, $media_url->genre_id, Ordered_Array::UP);
                        break;

                    case ACTION_ITEM_DOWN:
                        $max_sel = $this->plugin->get_all_table_values_count(VOD_FILTER_LIST) + 1;
                        $sel_ndx++;
                        if ($sel_ndx > $max_sel) {
                            return null;
                        }
                        $this->plugin->arrange_table_values(VOD_FILTER_LIST, $media_url->genre_id, Ordered_Array::DOWN);
                        break;

                    case ACTION_ITEM_DELETE:
                        $this->plugin->remove_table_value(VOD_FILTER_LIST, $media_url->genre_id);
                        break;
                }

                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
        }

        return null;
    }

    /**
     * Get MediaURL string representation (json encoded)
     * *
     * @param string $category
     * @return false|string
     */
    public static function get_media_url_string($category = '')
    {
        return MediaURL::encode(array('screen_id' => self::ID, 'group_id' => VOD_FILTER_GROUP_ID, 'category' => $category));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("MediaUrl: " . $media_url, true);

        $items[] = array(
            PluginRegularFolderItem::media_url => Starnet_Vod_List_Screen::get_media_url_string(
                Vod_Category::FLAG_FILTER, Vod_Category::FLAG_FILTER),
            PluginRegularFolderItem::caption => TR::t('vod_screen_new_filter'),
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::icon_path => self::FILTER_ICON_PATH,
                ViewItemParams::item_detailed_icon_path => self::FILTER_ICON_PATH,
            ),
        );

        foreach ($this->plugin->get_all_table_values(VOD_FILTER_LIST) as $item_row) {
            if (empty($item_row)) continue;

            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Vod_List_Screen::get_media_url_string(
                    Vod_Category::FLAG_FILTER, $item_row['item']),
                PluginRegularFolderItem::caption => TR::t('filter__1', $item_row['item']),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => self::FILTER_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => self::FILTER_ICON_PATH,
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
            $this->plugin->get_screen_view('list_1x11_small_info'),
        );
    }
}
