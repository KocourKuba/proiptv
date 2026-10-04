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

require_once 'lib/abstract_edit_order_screen.php';

class Starnet_Edit_Channel_List_Screen extends Abstract_Edit_Order_Screen
{
    const ID = 'edit_channel_list';

    const PARAM_EDIT_LIST = 'edit_list';
    const PARAM_EDIT_CHANNELS = 'edit_channels';

    const ACTION_CUSTOM_DELETE = 'custom_delete';
    const ACTION_CUSTOM_STRING_DLG_APPLY = 'apply_custom_string_dlg';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map($media_url);
    }

    /**
     * @param MediaURL $media_url
     * @return array
     */
    protected function do_get_action_map($media_url)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $actions = array();
        // the channels of 'all channels' have no own order
        if ($media_url->group_id !== TV_ALL_CHANNELS_GROUP_ID) {
            $this->add_move_actions($actions);
        }

        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_RENAME_CHANNEL, TR::t('rename'));
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
        $channels_order = $this->plugin->get_channels_ids_by_order($selected_media_url->{PARAM_GROUP_ID}, $show_adult);
        $selected_channel = $selected_media_url->{PARAM_CHANNEL_ID};
        $parent_group = $parent_media_url->{PARAM_GROUP_ID};
        $parent_group_for_item = $selected_media_url->{PARAM_GROUP_ID};

        $selected_items = $this->get_items_to_move($channels_order, $selected_channel);
        $sel_ndx_top = array_search_id(reset($selected_items), $channels_order);
        $sel_ndx = safe_get_value($user_input, 'sel_ndx', 0);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return $this->close_edit_screen($parent_media_url);

            case GUI_EVENT_KEY_ENTER:
                $this->toggle_selected_item($selected_channel);
                break;

            case ACTION_RENAME_CHANNEL:
                return $this->do_edit_title_dlg($selected_channel);

            case ACTION_EDIT_TITLE_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->set_channel_title($selected_channel, isset($user_input->restore) ? null : $user_input->{CONTROL_EDIT_NAME});
                break;

            case ACTION_ITEM_TOGGLE_MOVE:
                $this->toggle_move_step();
                return Action_Factory::change_behaviour($this->do_get_action_map($selected_media_url));

            case ACTION_ITEM_UP:
            case ACTION_ITEM_DOWN:
            case ACTION_ITEM_PAGE_UP:
            case ACTION_ITEM_PAGE_DOWN:
            case ACTION_ITEM_TOP:
            case ACTION_ITEM_BOTTOM:
                $this->force_parent_reload = true;
                $channels_order = array_diff($channels_order, $selected_items);
                $pos = self::get_move_position($user_input->control_id, $sel_ndx_top, count($channels_order));
                if ($pos !== null) {
                    $sel_ndx = $this->update_channel_order($parent_group_for_item, $pos, $selected_channel, $selected_items, $channels_order);
                }
                break;

            case ACTION_ITEM_DELETE:
                // hide group
                $this->force_parent_reload = true;
                $this->plugin->set_channel_visible($selected_items, false);
                $this->selected_items = array();
                break;

            case ACTION_ITEMS_EDIT:
                $cnt = $this->plugin->get_channels_count($parent_group, PARAM_DISABLED);
                if ($cnt > 0) {
                    return $this->plugin->do_edit_list_screen(static::ID,
                        Starnet_Edit_Hidden_List_Screen::PARAM_HIDDEN_CHANNELS, $parent_group);
                }
                break;

            case ACTION_ITEMS_CLEAR:
                $this->selected_items = array();
                break;

            case ACTION_ITEMS_SORT:
                $this->force_parent_reload = true;
                $this->plugin->sort_channels_order($parent_group_for_item);
                break;

            case ACTION_RESET_ITEMS_SORT:
                $this->force_parent_reload = true;
                $this->plugin->sort_channels_order($parent_group_for_item, true);
                break;

            case ACTION_ITEM_DELETE_CHANNELS:
                $items = array(
                    TR::t('tv_screen_hide_plus') => "[\s(]\+\d",
                    TR::t('tv_screen_hide_orig') => "[Oo]rig|[Uu]ncomp",
                    TR::t('tv_screen_hide_50') => "\s50|\sFHD",
                    TR::t('tv_screen_hide_uhd') => "UHD|\s4[KkКк]|\s8[KkКк]",
                    TR::t('tv_screen_hide_sd') => "hide_sd",
                    TR::t('tv_screen_hide_string') => "custom_string"
                );
                foreach ($items as $key => $val) {
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_ITEM_DELETE_BY_STRING, $key, null, array('hide' => $val));
                }
                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_ITEM_DELETE_BY_STRING:
                if ($user_input->hide === 'hide_sd') {
                    $this->force_parent_reload = $this->plugin->hide_sd_channels($parent_group) !== 0;
                } else if ($user_input->hide !== 'custom_string') {
                    $this->force_parent_reload = $this->plugin->hide_channels_by_mask($user_input->hide, $parent_group) !== 0;
                } else {
                    $defs = array();
                    Control_Factory::add_text_field($defs, $this, self::ACTION_CUSTOM_DELETE, '',
                        $this->plugin->get_parameter(PARAM_CUSTOM_DELETE_STRING),
                        false, false, false, false, Control_Factory::DLG_CONTROLS_WIDTH);

                    Control_Factory::add_vgap($defs, 100);
                    Control_Factory::add_close_dialog_and_apply_button($defs, $this, self::ACTION_CUSTOM_STRING_DLG_APPLY, TR::t('ok'));
                    Control_Factory::add_cancel_button($defs);
                    Control_Factory::add_vgap($defs, 10);

                    return Action_Factory::show_dialog($defs, TR::t('tv_screen_hide_string'));
                }
                break;

            case self::ACTION_CUSTOM_STRING_DLG_APPLY:
                $custom_string = $user_input->{self::ACTION_CUSTOM_DELETE};
                if (!empty($custom_string)) {
                    $this->plugin->set_parameter(PARAM_CUSTOM_DELETE_STRING, $custom_string);
                    $this->force_parent_reload = $this->plugin->hide_channels_by_mask($custom_string, $parent_group, false) !== 0;
                }
                break;

            case ACTION_SORT_POPUP:
                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_ITEMS_SORT, TR::t('sort_channels'), 'sort.png');

                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_RESET_ITEMS_SORT, TR::t('reset_channels_sort'), 'brush.png');
                return Action_Factory::show_popup_menu($menu_items);

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($parent_group);

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

        $show_adult = $this->plugin->get_bool_setting(PARAM_SHOW_ADULT);
        // flipped once: in_array() per channel over these two is
        // O(channels * favorites) and O(channels * selected), and "select all"
        // on a big group makes the second list as long as the first
        $fav_ids = array_flip($this->plugin->get_channels_order($this->plugin->get_fav_id()));
        $selected_ids = array_flip($this->selected_items);

        if ($media_url->{PARAM_GROUP_ID} === TV_ALL_CHANNELS_GROUP_ID) {
            $help = '';
            $groups_order = $this->plugin->get_groups_by_order($show_adult);
        } else {
            $help = TR::load('edit_help');
            $groups_order = array();
            $group = $this->plugin->get_group($media_url->{PARAM_GROUP_ID}, PARAM_GROUP_ORDINARY, $show_adult);
            if (!empty($group)) {
                $groups_order[] = $group;
            }
        }

        $items = array();
        foreach ($groups_order as $group_row) {
            $group_id = $group_row[COLUMN_GROUP_ID];
            $channels_rows = $this->plugin->get_channels_by_order($group_id, $show_adult);
            foreach ($channels_rows as $channel_row) {
                $channel_id = $channel_row[COLUMN_CHANNEL_ID];
                $icon_url = $this->plugin->get_channel_picon($channel_row, true);
                $title = $channel_row[COLUMN_SHOW_TITLE];
                $selected = isset($selected_ids[$channel_id]);

                $detailed_info = TR::load('tv_screen_edit_ch_channel_info__1', $title) . $help;
                $items[] = array(
                    PluginRegularFolderItem::media_url => MediaURL::encode(array(PARAM_CHANNEL_ID => $channel_id, PARAM_GROUP_ID => $group_id)),
                    PluginRegularFolderItem::caption => $title,
                    PluginRegularFolderItem::starred => isset($fav_ids[$channel_id]),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::item_sticker => $selected ? Control_Factory::create_sticker(get_image_path('mark.png'),
                            -30, 0, 'left', 'center') : null,
                        ViewItemParams::item_caption_color => $selected ? DEF_LABEL_TEXT_COLOR_YELLOW : DEF_LABEL_TEXT_COLOR_WHITE,
                        ViewItemParams::icon_path => $icon_url,
                        ViewItemParams::item_detailed_icon_path => $icon_url,
                        ViewItemParams::item_detailed_info => $detailed_info,
                    ),
                );
            }
        }

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
    protected $folder_view_ids = array(
        VIEW_LIST_1X11_INFO,
    );

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// Protected methods

    /**
     * @param string $parent_group
     * @return array
     */
    protected function create_popup_menu($parent_group)
    {
        if ($parent_group != TV_ALL_CHANNELS_GROUP_ID) {
            if ($this->selected_items) {
                $menu_items[] = Control_Factory::menu_separator();
                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_ITEMS_CLEAR, TR::t('clear_selection'), 'brush.png');
                $menu_items[] = Control_Factory::menu_separator();
            }

            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                ACTION_ITEMS_SORT, TR::t('sort_channels'), 'sort.png');
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_RESET_ITEMS_SORT,
                TR::t('reset_channels_sort'), 'brush.png');
            $menu_items[] = Control_Factory::menu_separator();
        }

        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
            ACTION_ITEM_DELETE_CHANNELS, TR::t('tv_screen_hide_group_channels'), 'remove.png');

        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param string $selected_channel
     * @return array
     */
    public function do_edit_title_dlg($selected_channel)
    {
        hd_debug_print(null, true);
        $defs = array();

        Control_Factory::add_label($defs, TR::t('original'), $this->plugin->get_channel_title($selected_channel, true));
        Control_Factory::add_text_field($defs, $this, CONTROL_EDIT_NAME, TR::t('name'), $this->plugin->get_channel_title($selected_channel, false),
            false, false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, ACTION_EDIT_TITLE_APPLY, TR::t('ok'));
        Control_Factory::add_cancel_button($defs);
        Control_Factory::add_vgap($defs, 20);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, ACTION_EDIT_TITLE_APPLY, TR::t('restore'), array('restore' => true));
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($defs, TR::t('edit_list_edit_item'));
    }

    /**
     * @param string $group_id
     * @param int $offset
     * @param string $selected_channel
     * @param array $selected_items
     * @param array $channel_order
     * @return false|int|string
     */
    protected function update_channel_order($group_id, $offset, $selected_channel, $selected_items, $channel_order)
    {
        array_splice($channel_order, $offset, 0, $selected_items);
        $this->plugin->store_channels_order_rows($group_id, $channel_order);

        if (!in_array_id($selected_channel, $selected_items)) {
            $selected_channel = reset($selected_items);
        }
        return array_search_id($selected_channel, $channel_order);
    }
}
