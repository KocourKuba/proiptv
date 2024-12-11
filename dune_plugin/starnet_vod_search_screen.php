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

class Starnet_Vod_Search_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'search_screen';
    const SEARCH_ICON_PATH = 'plugin_file://icons/icon_search.png';

    /**
     * @param string $category
     * @return false|string
     */
    public static function get_media_url_str($category = '')
    {
        return MediaURL::encode(array('screen_id' => self::ID, 'category' => $category));
    }

    /**
     * @param MediaURL $media_url
     * @param Object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(
            GUI_EVENT_KEY_ENTER => User_Input_Handler_Registry::create_action($this,
                ACTION_CREATE_SEARCH, null, array(ACTION_SEARCH => ACTION_OPEN_FOLDER)),
            GUI_EVENT_KEY_B_GREEN => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up')),
            GUI_EVENT_KEY_C_YELLOW => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down')),
            GUI_EVENT_KEY_D_BLUE => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete')),
            GUI_EVENT_KEY_RETURN => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN),
            GUI_EVENT_KEY_POPUP_MENU => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU),
            GUI_EVENT_KEY_STOP => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if ($this->has_changes()) {
                    $this->plugin->save_history(true);
                    $this->set_no_changes();
                }

                return Action_Factory::close_and_run();

            case ACTION_CREATE_SEARCH:
                if (!isset($user_input->parent_media_url)) break;

                $media_url = MediaURL::decode($user_input->selected_media_url);
                if ($media_url->genre_id !== Vod_Category::FLAG_SEARCH && $user_input->{ACTION_SEARCH} === ACTION_OPEN_FOLDER) {
                    return Action_Factory::open_folder($user_input->selected_media_url);
                }

                if ($user_input->{ACTION_SEARCH} === ACTION_ITEMS_EDIT) {
                    $search_string = $media_url->genre_id;
                } else {
                    /** @var Ordered_Array $search_items */
                    $search_items = $this->plugin->get_history(VOD_SEARCH_LIST, new Ordered_Array());
                    $search_items->rewind();
                    $search_string = $search_items->size() === 0 ? "" : $search_items->current();
                }

                $defs = array();
                Control_Factory::add_text_field($defs,
                    $this, null, ACTION_NEW_SEARCH, '',
                    $search_string, false, false, true, true, 1300, false, true);
                Control_Factory::add_vgap($defs, 500);

                return Action_Factory::show_dialog(TR::t('search'), $defs, true);

            case ACTION_NEW_SEARCH:
                return Action_Factory::close_dialog_and_run(
                    User_Input_Handler_Registry::create_action($this, ACTION_RUN_SEARCH));

            case ACTION_RUN_SEARCH:
                $search_string = $user_input->{ACTION_NEW_SEARCH};
                hd_debug_print("search string: $search_string", true);
                /** @var Ordered_Array $search_items */
                $search_items = &$this->plugin->get_history(VOD_SEARCH_LIST, new Ordered_Array());
                $search_items->insert_item($search_string, false);
                $this->plugin->save_history(true);
                $action = Action_Factory::open_folder(
                    Starnet_Vod_List_Screen::get_media_url_string(Vod_Category::FLAG_SEARCH, $search_string),
                    TR::t('search__1', ": $search_string"));

                return Action_Factory::invalidate_folders(array($user_input->parent_media_url), $action);

            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($user_input->selected_media_url)
                    && MediaURL::decode($user_input->selected_media_url)->genre_id !== Vod_Category::FLAG_SEARCH) {

                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_EDIT, TR::t('edit'), "edit.png");
                    return Action_Factory::show_popup_menu($menu_items);
                }

                break;

            case ACTION_ITEMS_EDIT:
                return User_Input_Handler_Registry::create_action($this,
                    ACTION_CREATE_SEARCH,
                    null,
                    array(ACTION_SEARCH => ACTION_ITEMS_EDIT)
                );

            case ACTION_ITEM_UP:
            case ACTION_ITEM_DOWN:
            case ACTION_ITEM_DELETE:
                if (!isset($user_input->selected_media_url)) break;

                $media_url = MediaURL::decode($user_input->selected_media_url);
                /** @var Ordered_Array $search_items */
                $search_items = &$this->plugin->get_history(VOD_SEARCH_LIST, new Ordered_Array());
                switch ($user_input->control_id) {
                    case ACTION_ITEM_UP:
                        $user_input->sel_ndx--;
                        $search_items->arrange_item($media_url->genre_id, Ordered_Array::UP);
                        $this->set_changes();
                        break;

                    case ACTION_ITEM_DOWN:
                        $user_input->sel_ndx++;
                        $search_items->arrange_item($media_url->genre_id, Ordered_Array::DOWN);
                        $this->set_changes();
                        break;

                    case ACTION_ITEM_DELETE:
                        $search_items->remove_item($media_url->genre_id);
                        $this->set_changes();
                        break;
                }

                return Action_Factory::invalidate_folders(array($user_input->parent_media_url));
        }

        return null;
    }

    /**
     * @param string $group_id
     * @return false|string
     */
    public static function get_media_url_string($group_id)
    {
        return MediaURL::encode(array('screen_id' => static::ID, 'group_id' => $group_id));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param Object $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("MediaUrl: " . $media_url, true);

        $items[] = array(
            PluginRegularFolderItem::media_url => Starnet_Vod_List_Screen::get_media_url_string(
                Vod_Category::FLAG_SEARCH, Vod_Category::FLAG_SEARCH),
            PluginRegularFolderItem::caption => TR::t('new_search'),
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::icon_path => self::SEARCH_ICON_PATH,
                ViewItemParams::item_detailed_icon_path => self::SEARCH_ICON_PATH,
            ),
        );

        foreach ($this->plugin->get_history(VOD_SEARCH_LIST, new Ordered_Array()) as $item) {
            if (empty($item)) continue;

            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Vod_List_Screen::get_media_url_string(
                    Vod_Category::FLAG_SEARCH, $item),
                PluginRegularFolderItem::caption => TR::t('search__1', ": $item"),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => self::SEARCH_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => self::SEARCH_ICON_PATH,
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
