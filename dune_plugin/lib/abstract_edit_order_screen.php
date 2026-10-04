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

require_once 'abstract_preloaded_regular_screen.php';
require_once 'user_input_handler_registry.php';

/**
 * List where the selected items are moved by LEFT/RIGHT, by one item, by a page or to the edge (RED switches the step),
 * ENTER adds or removes the item to the selection
 */
abstract class Abstract_Edit_Order_Screen extends Abstract_Preloaded_Regular_Screen
{
    const PAGE_SIZE = 11; // see list_1x11_info

    protected $selected_items = array();

    // 0 - by one item, 1 - by a page, 2 - to the edge
    protected $toggle_move = 0;

    /**
     * LEFT/RIGHT move actions of the current step and the RED key switching the step
     *
     * @param array $actions
     * @return void
     */
    protected function add_move_actions(&$actions)
    {
        switch ($this->toggle_move) {
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
    }

    /**
     * Next move step
     *
     * @return void
     */
    protected function toggle_move_step()
    {
        if (++$this->toggle_move > 2) {
            $this->toggle_move = 0;
        }
    }

    /**
     * Items to move: the selection in the order of the list, or the current item without a selection
     *
     * @param array $order ids of the list in their order
     * @param string $current id of the current item
     * @return array
     */
    protected function get_items_to_move($order, $current)
    {
        if (empty($this->selected_items)) {
            return array($current);
        }

        // flipped once: in_array() per entry over the selection is
        // O(entries * selected), and "select all" makes the two equal
        $selected_map = array_flip($this->selected_items);
        $new_selected = array();
        foreach ($order as $item) {
            if (isset($selected_map[$item])) {
                $new_selected[] = $item;
            }
        }

        return $this->selected_items = $new_selected;
    }

    /**
     * ENTER adds the item to the selection or removes it
     *
     * @param string $item
     * @return void
     */
    protected function toggle_selected_item($item)
    {
        $pos = array_search_id($item, $this->selected_items);
        if ($pos !== false) {
            array_splice($this->selected_items, $pos, 1);
        } else {
            $this->selected_items[] = $item;
        }
    }

    /**
     * New position of the moved items in the list without them
     *
     * @param string $action ACTION_ITEM_UP, ACTION_ITEM_DOWN, ACTION_ITEM_PAGE_UP, ACTION_ITEM_PAGE_DOWN, ACTION_ITEM_TOP or ACTION_ITEM_BOTTOM
     * @param int $top position of the first moved item in the list
     * @param int $rest_count count of the items not moved
     * @return int|null null - the items can't be moved further
     */
    protected static function get_move_position($action, $top, $rest_count)
    {
        switch ($action) {
            case ACTION_ITEM_UP:
                return $top - 1 < 0 ? null : $top - 1;

            case ACTION_ITEM_DOWN:
                return $top + 1 > $rest_count ? null : $top + 1;

            case ACTION_ITEM_PAGE_UP:
                return $top == 0 ? null : max(0, $top - static::PAGE_SIZE);

            case ACTION_ITEM_PAGE_DOWN:
                return min($top + static::PAGE_SIZE, $rest_count);

            case ACTION_ITEM_TOP:
                return 0;

            case ACTION_ITEM_BOTTOM:
                return $rest_count;
        }

        return null;
    }

    /**
     * RETURN: close the screen, the parent screen is reloaded when the list is changed
     *
     * @param MediaURL $parent_media_url
     * @return array
     */
    protected function close_edit_screen($parent_media_url)
    {
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
    }
}
