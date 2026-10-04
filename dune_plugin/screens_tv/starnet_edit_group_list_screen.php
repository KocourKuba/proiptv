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

class Starnet_Edit_Group_List_Screen extends Abstract_Edit_Order_Screen
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
        return $this->do_get_action_map();
    }

    /**
     * @return array
     */
    protected function do_get_action_map()
    {
        hd_debug_print(null, true);

        $actions = array();
        $this->add_move_actions($actions);

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

        $selected_items = $this->get_items_to_move($group_order, $selected_group);
        $sel_ndx_top = array_search_id(reset($selected_items), $group_order);
        $sel_ndx = safe_get_value($user_input, 'sel_ndx', 0);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return $this->close_edit_screen($parent_media_url);

            case GUI_EVENT_KEY_ENTER:
                $this->toggle_selected_item($selected_group);
                break;

            case ACTION_RENAME_GROUP:
                return $this->do_edit_title_dlg($selected_group);

            case ACTION_EDIT_TITLE_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->set_group_title($selected_group, isset($user_input->restore) ? null : $user_input->{CONTROL_EDIT_NAME});
                break;

            case ACTION_ITEM_TOGGLE_MOVE:
                $this->toggle_move_step();
                return Action_Factory::change_behaviour($this->do_get_action_map());

            case ACTION_ITEM_UP:
            case ACTION_ITEM_DOWN:
            case ACTION_ITEM_PAGE_UP:
            case ACTION_ITEM_PAGE_DOWN:
            case ACTION_ITEM_TOP:
            case ACTION_ITEM_BOTTOM:
                $this->force_parent_reload = true;
                $group_order = array_diff($group_order, $selected_items);
                $pos = self::get_move_position($user_input->control_id, $sel_ndx_top, count($group_order));
                if ($pos !== null) {
                    $sel_ndx = $this->update_order($pos, $selected_group, $selected_items, $group_order);
                }
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

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $group = $this->plugin->get_group($selected_media_url->{PARAM_GROUP_ID}, PARAM_ALL);
                if (is_null($group)) break;

                $this->plugin->set_setting(PARAM_RECENT_IMAGE_FOLDER, get_noslash_trailed_path(dirname($data->{PARAM_FILEPATH})));
                $cached_image_name = $this->plugin->get_active_playlist_id() . '_' . $data->{PARAM_CAPTION};
                $cached_image_path = get_cached_image_path($cached_image_name);
                hd_print('copy from: ' . $data->{PARAM_FILEPATH} . " to: $cached_image_path");
                if (!copy($data->{PARAM_FILEPATH}, $cached_image_path)) {
                    return Action_Factory::show_title_dialog(TR::t('error'), TR::t('err_copy'));
                }

                hd_debug_print("Assign icon: $cached_image_name to group: " . $selected_media_url->{PARAM_GROUP_ID});
                $this->force_parent_reload = true;
                $this->plugin->set_group_icon($selected_media_url->{PARAM_GROUP_ID}, $cached_image_name);
                return Action_Factory::refresh_entry_points($this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx));

            case ACTION_RESET_DEFAULT:
                hd_debug_print("Reset icon for group: " . $selected_media_url->{PARAM_GROUP_ID} . " to default");
                $this->force_parent_reload = true;
                $icon = $selected_media_url->{PARAM_GROUP_ID} === TV_ALL_CHANNELS_GROUP_ID ? TV_ALL_CHANNELS_GROUP_ICON : '';
                $this->plugin->set_group_icon($selected_media_url->{PARAM_GROUP_ID}, $icon);
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu();

            case ACTION_INVALIDATE:
                $this->force_parent_reload = true;
                break;

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
        $help = TR::load('edit_help');
        $show_adult = $this->plugin->get_bool_setting(PARAM_SHOW_ADULT);
        // flipped once, see the note in handle_user_input()
        $selected_map = array_flip($this->selected_items);
        foreach ($this->plugin->get_groups_by_order($show_adult) as $group_row) {
            $icon = get_cached_image(safe_get_value($group_row, COLUMN_ICON, DEFAULT_GROUP_ICON));
            $selected = isset($selected_map[$group_row[COLUMN_GROUP_ID]]);
            $detailed_info = TR::load('tv_screen_edit_ch_channel_info__1', $group_row[COLUMN_TITLE]) . $help;
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
                    ViewItemParams::item_detailed_info => $detailed_info,
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
    protected $folder_view_ids = array(
        VIEW_LIST_1X11_INFO,
    );

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// Protected methods

    /**
     * @return array
     */
    protected function create_popup_menu()
    {
        if ($this->selected_items) {
            $menu_items[] = Control_Factory::menu_separator();
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                ACTION_ITEMS_CLEAR, TR::t('clear_selection'), 'brush.png');
            $menu_items[] = Control_Factory::menu_separator();
        }

        $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
            array(
                PARAM_EXTENSION => IMAGE_PREVIEW_PATTERN,
                PARAM_RECENT_FOLDER => $this->plugin->get_setting(PARAM_RECENT_IMAGE_FOLDER, ''),
                Starnet_Folder_Screen::PARAM_CHOOSE_FILE => ACTION_FILE_SELECTED,
                Starnet_Folder_Screen::PARAM_RESET_ACTION => ACTION_RESET_DEFAULT,
                Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                Starnet_Folder_Screen::PARAM_ALLOW_IMAGE_LIB => true,
                Starnet_Folder_Screen::PARAM_READ_ONLY => true,
            )
        );
        $menu_items[] = User_Input_Handler_Registry::create_popup_item_ext(
            Action_Factory::open_folder($media_url, TR::t('select_file')),
            TR::t('change_group_icon'), 'image.png');
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
            ACTION_RESET_DEFAULT, TR::t('reset_default'), 'image.png');

        $menu_items[] = Control_Factory::menu_separator();
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
            ACTION_ITEMS_SORT, TR::t('sort_groups'), 'sort.png');
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_RESET_ITEMS_SORT,
            TR::t('reset_groups_sort'), 'brush.png');


        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param string $selected_group
     * @return array
     */
    public function do_edit_title_dlg($selected_group)
    {
        hd_debug_print(null, true);
        $defs = array();

        Control_Factory::add_label($defs, TR::t('original'), $selected_group);
        Control_Factory::add_text_field($defs, $this, CONTROL_EDIT_NAME, TR::t('name'), $this->plugin->get_group_title($selected_group),
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

        if (!in_array_id($selected_group, $selected_items)) {
            $selected_group = reset($selected_items);
        }
        return array_search_id($selected_group, $group_order);
    }
}
