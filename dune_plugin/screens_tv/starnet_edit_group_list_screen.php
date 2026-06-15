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

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map($plugin_cookies);
    }

    protected function do_get_action_map($plugin_cookies)
    {
        hd_debug_print(null, true);

        if (isset($plugin_cookies->toggle_move) && $plugin_cookies->toggle_move) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOP, TR::t('top'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_BOTTOM, TR::t('bottom'));
        } else {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        }
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_SELECT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode(safe_get_value($user_input, 'selected_media_url'));
        $sel_ndx = safe_get_value($user_input, 'sel_ndx', 0);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $target_action = null;
                if ($this->force_parent_reload && isset($parent_media_url->{PARAM_SOURCE_WINDOW_ID}, $parent_media_url->{PARAM_END_ACTION})) {
                    $this->force_parent_reload = false;
                    $source_window = safe_get_value($parent_media_url, PARAM_SOURCE_WINDOW_ID);
                    $end_action = safe_get_value($parent_media_url, PARAM_END_ACTION);
                    hd_debug_print("Force parent reload: $source_window action: $end_action", true);
                    $target_action = User_Input_Handler_Registry::create_screen_action($source_window, $end_action);
                }

                return Action_Factory::close_and_run($target_action);

            case ACTION_ITEM_TOGGLE_MOVE:
                $plugin_cookies->toggle_move = !$plugin_cookies->toggle_move;
                $actions = $this->do_get_action_map($plugin_cookies);
                return Action_Factory::change_behaviour($actions);

            case ACTION_ITEM_UP:
                $this->force_parent_reload = true;
                if (--$sel_ndx < 0) {
                    return null;
                }

                $this->plugin->arrange_groups_order_rows($selected_media_url->group_id, Ordered_Array::UP);
                break;

            case ACTION_ITEM_DOWN:
                $max_sel = $this->get_visible_groups_count() - 1;
                if (++$sel_ndx > $max_sel) {
                    return null;
                }

                hd_debug_print("sel_idx: $sel_ndx");
                $this->plugin->arrange_groups_order_rows($selected_media_url->group_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEM_TOP:
                if ($sel_ndx === 0) {
                    return null;
                }

                $this->force_parent_reload = true;
                $sel_ndx = 0;
                $this->plugin->arrange_groups_order_rows($selected_media_url->group_id, Ordered_Array::TOP);
                break;

            case ACTION_ITEM_BOTTOM:
                $max_sel = $this->get_visible_groups_count() - 1;
                if ($sel_ndx === $max_sel) {
                    return null;
                }

                $this->force_parent_reload = true;
                $sel_ndx = $max_sel;
                $this->plugin->arrange_groups_order_rows($selected_media_url->group_id, Ordered_Array::BOTTOM);
                break;

            case ACTION_ITEM_DELETE:
                // hide group
                $this->force_parent_reload = true;
                $this->plugin->set_groups_visible($selected_media_url->group_id, false);
                break;

            case ACTION_ITEMS_SORT:
                $this->force_parent_reload = true;
                $this->plugin->sort_groups_order();
                break;

            case ACTION_RESET_ITEMS_SORT:
                if (!isset($user_input->{ACTION_RESET_TYPE})) {
                    return null;
                }

                switch ($user_input->{ACTION_RESET_TYPE}) {
                    case ACTION_SORT_CHANNELS:
                        $this->plugin->sort_channels_order($selected_media_url->group_id, true);
                        break;

                    case ACTION_SORT_GROUPS:
                        $this->plugin->sort_groups_order(true);
                        break;

                    case ACTION_SORT_ALL:
                        $this->plugin->sort_groups_order(true);
                        foreach ($this->plugin->get_groups_by_order() as $row) {
                            $this->plugin->sort_channels_order($row[COLUMN_GROUP_ID], true);
                        }
                        break;

                    default:
                        return null;
                }

                $this->force_parent_reload = true;
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_group'), "hide.png");

                if (isset($user_input->{ACTION_SORT_POPUP})) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_groups'));
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_groups_sort'),
                        null, array(ACTION_RESET_TYPE => ACTION_SORT_GROUPS));
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_all_sort'),
                        null, array(ACTION_RESET_TYPE => ACTION_SORT_ALL));
                } else {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_SORT_POPUP, TR::t('sort_popup_menu'), "sort.png");
                }

                return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);

            case ACTION_SORT_POPUP:
                hd_debug_print('Start event popup menu for playlist', true);
                return User_Input_Handler_Registry::create_action(
                    $this,
                    GUI_EVENT_KEY_POPUP_MENU,
                    null,
                    array(ACTION_SORT_POPUP => true)
                );

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
            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(
                    array(PARAM_SCREEN_ID => static::ID, 'group_id' => $group_row[COLUMN_GROUP_ID])),
                PluginRegularFolderItem::caption => $group_row[COLUMN_TITLE],
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_caption_color => DEF_LABEL_TEXT_COLOR_WHITE,
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
    /// Private methods

    private function get_visible_groups_count()
    {
        return $this->plugin->get_groups_count(PARAM_GROUP_ORDINARY, PARAM_ENABLED);
    }
}
