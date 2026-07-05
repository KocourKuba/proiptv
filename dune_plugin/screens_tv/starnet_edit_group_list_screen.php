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
require_once 'lib/user_input_handler_registry.php';

class Starnet_Edit_Group_List_Screen extends Abstract_Preloaded_Regular_Screen
{
    const ID = 'edit_group_list';

    const PARAM_EDIT_LIST = 'edit_list';
    const PARAM_EDIT_GROUPS = 'edit_groups';
    const PAGE_SIZE = 11; // see list_1x11_info

    protected $selected_items = array();
    protected $toggle_move = 0;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map();
    }

    protected function do_get_action_map()
    {
        hd_debug_print(null, true);

        switch($this->toggle_move) {
            case 0:
                $actions[GUI_EVENT_KEY_LEFT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP);
                $actions[GUI_EVENT_KEY_RIGHT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN);
                $actions[GUI_EVENT_KEY_A_RED] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('move_step'));
            break;
            case 1:
                $actions[GUI_EVENT_KEY_LEFT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_PAGE_UP);
                $actions[GUI_EVENT_KEY_RIGHT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_PAGE_DOWN);
                $actions[GUI_EVENT_KEY_A_RED] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('move_page'));
                break;
            case 2:
                $actions[GUI_EVENT_KEY_LEFT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOP);
                $actions[GUI_EVENT_KEY_RIGHT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_BOTTOM);
                $actions[GUI_EVENT_KEY_A_RED] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('move_edge'));
                break;
        }

        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_RENAME_GROUP, TR::t('rename'));
        $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_EDIT, TR::t('restore'));

        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('hide'));
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_CLEAR);
        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode(safe_get_value($user_input, 'selected_media_url'));
        $show_adult = $this->plugin->get_bool_setting(PARAM_SHOW_ADULT);
        $group_order = $this->plugin->get_groups_ids_by_order($show_adult);
        $selected_group = $selected_media_url->{PARAM_GROUP_ID};

        if (empty($this->selected_items)) {
            $selected_items[] = $selected_group;
        } else {
            $new_selected = array();
            foreach($group_order as $item) {
                if (in_array($item, $this->selected_items)) {
                    $new_selected[] = $item;
                }
            }
            $selected_items = $this->selected_items = $new_selected;
        }
        $sel_ndx_top = array_search(reset($selected_items), $group_order);
        $sel_ndx = safe_get_value($user_input, 'sel_ndx', 0);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $target_action = null;
                $this->selected_items = array();
                if ($this->force_parent_reload && isset($parent_media_url->{PARAM_SOURCE_WINDOW_ID}, $parent_media_url->{PARAM_END_ACTION})) {
                    $this->force_parent_reload = false;
                    $source_window = safe_get_value($parent_media_url, PARAM_SOURCE_WINDOW_ID);
                    $end_action = safe_get_value($parent_media_url, PARAM_END_ACTION);
                    hd_debug_print("Force parent reload: $source_window action: $end_action", true);
                    $target_action = User_Input_Handler_Registry::create_screen_action($source_window, $end_action);
                }

                hd_debug_print($target_action, true);
                return Action_Factory::close_and_run($target_action);

            case GUI_EVENT_KEY_ENTER:
                $pos = array_search($selected_group, $this->selected_items);
                if ($pos !== false) {
                    array_splice($this->selected_items, $pos, 1);
                } else {
                    $this->selected_items[] = $selected_group;
                }
                break;

            case ACTION_RENAME_GROUP:
                return $this->plugin->do_edit_title_dlg($this, $this->plugin->get_group_title($selected_group));

            case ACTION_EDIT_TITLE_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->set_group_title($selected_group, $user_input->{CONTROL_EDIT_NAME});
                break;

            case ACTION_ITEM_TOGGLE_MOVE:
                if (++$this->toggle_move > 2) {
                    $this->toggle_move = 0;
                }
                $actions = $this->do_get_action_map();
                return Action_Factory::change_behaviour($actions);

            case ACTION_ITEM_UP:
                $this->force_parent_reload = true;
                if (--$sel_ndx_top < 0) {
                    break;
                }

                $group_order = array_diff($group_order, $selected_items);
                $sel_ndx = $this->update_order($sel_ndx_top, $selected_group, $selected_items, $group_order);
                break;

            case ACTION_ITEM_DOWN:
                $this->force_parent_reload = true;
                $group_order = array_diff($group_order, $selected_items);
                if (++$sel_ndx_top > count($group_order)) {
                    break;
                }

                $sel_ndx = $this->update_order($sel_ndx_top, $selected_group, $selected_items, $group_order);
                break;

            case ACTION_ITEM_PAGE_UP:
                $this->force_parent_reload = true;
                if ($sel_ndx_top == 0) {
                    break;
                }

                $sel_ndx_top -= self::PAGE_SIZE;
                if ($sel_ndx_top < 0) {
                    $sel_ndx_top = 0;
                }

                $group_order = array_diff($group_order, $selected_items);
                $sel_ndx = $this->update_order($sel_ndx_top, $selected_group, $selected_items, $group_order);
                break;

            case ACTION_ITEM_PAGE_DOWN:
                $this->force_parent_reload = true;
                $sel_ndx_top += self::PAGE_SIZE;
                $group_order = array_diff($group_order, $selected_items);
                $max = count($group_order);
                if ($sel_ndx_top > $max) {
                    $sel_ndx_top = $max;
                }

                $sel_ndx = $this->update_order($sel_ndx_top, $selected_group, $selected_items, $group_order);
                break;

            case ACTION_ITEM_TOP:
                $this->force_parent_reload = true;
                $group_order = array_diff($group_order, $selected_items);
                $sel_ndx = $this->update_order(0, $selected_group, $selected_items, $group_order);
                break;

            case ACTION_ITEM_BOTTOM:
                $this->force_parent_reload = true;
                $group_order = array_diff($group_order, $selected_items);
                $sel_ndx = $this->update_order(count($group_order), $selected_group, $selected_items, $group_order);
                break;

            case ACTION_ITEM_DELETE:
                // hide group
                $this->force_parent_reload = true;
                $this->plugin->set_groups_visible($selected_items, false);
                $this->selected_items = array();
                break;

            case ACTION_ITEMS_EDIT:
                return $this->plugin->do_edit_list_screen(static::ID, Starnet_Edit_Hidden_List_Screen::PARAM_HIDDEN_GROUPS);

            case ACTION_ITEMS_CLEAR:
                $this->selected_items = array();
                break;

            case ACTION_ITEMS_SORT:
                $this->force_parent_reload = true;
                $this->plugin->sort_groups_order();
                break;

            case ACTION_RESET_ITEMS_SORT:
                $this->force_parent_reload = true;
                $this->plugin->sort_groups_order(true);
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu();

            case ACTION_EMPTY:
            default:
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();
        $show_adult = $this->plugin->get_bool_setting(PARAM_SHOW_ADULT);
        foreach ($this->plugin->get_groups_by_order($show_adult) as $group_row) {
            $icon = get_cached_image(safe_get_value($group_row, COLUMN_ICON, DEFAULT_GROUP_ICON));
            $selected = in_array($group_row[COLUMN_GROUP_ID], $this->selected_items);
            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(
                    array(PARAM_SCREEN_ID => static::ID, PARAM_GROUP_ID => $group_row[COLUMN_GROUP_ID])),
                PluginRegularFolderItem::caption => $group_row[COLUMN_TITLE],
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => $selected ? Control_Factory::create_sticker(get_image_path('mark.png'),
                        -30, 0, 'left', 'center') : null,
                    ViewItemParams::item_caption_color => $selected ? DEF_LABEL_TEXT_COLOR_YELLOW : DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewItemParams::icon_path => $icon,
                    ViewItemParams::item_detailed_icon_path => $icon,
                )
            );
        }

        hd_debug_print('Total items: ' . count($items), true);
        return $items;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $folder_view = parent::get_folder_view($media_url, $plugin_cookies);
        $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = null;

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
        );
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// Protected methods

    protected function create_popup_menu()
    {
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, GUI_EVENT_KEY_ENTER, TR::t('select_enter'), 'mark.png');

        if ($this->selected_items) {
            $menu_items[] = Control_Factory::menu_separator();
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                ACTION_ITEMS_CLEAR, TR::t('clear_selection'), 'brush.png');
        }

        $menu_items[] = Control_Factory::menu_separator();
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
            ACTION_ITEMS_SORT, TR::t('sort_groups'), 'sort.png');
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_RESET_ITEMS_SORT,
            TR::t('reset_groups_sort'), 'brush.png');


        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param int $offset
     * @param string $selected_group
     * @param array $selected_items
     * @param array $group_order
     * @return false|int|string
     */
    protected function update_order($offset, $selected_group, $selected_items, $group_order)
    {
        array_splice($group_order, $offset, 0, $selected_items);
        $this->plugin->store_groups_order_rows($group_order);

        if (!in_array($selected_group, $selected_items)) {
            $selected_group = reset($selected_items);
        }
        return array_search($selected_group, $group_order);
    }
}
