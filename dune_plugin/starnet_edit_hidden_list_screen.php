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

class Starnet_Edit_Hidden_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'edit_hidden_list';

    const SCREEN_EDIT_HIDDEN_GROUPS = 'groups';
    const SCREEN_EDIT_HIDDEN_CHANNELS = 'channels';

    const ACTION_CLEAR_APPLY = 'clear_apply';
    const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';

    ///////////////////////////////////////////////////////////////////////

    protected $force_parent_reload = false;

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $actions = array();

        // hidden groups or channels
        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('restore'));
        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_CLEAR, TR::t('restore_all'));
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $selected_id = isset($user_input->selected_media_url) ? MediaURL::decode($user_input->selected_media_url)->id : 0;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                hd_debug_print("Need reload: " . var_export($this->force_parent_reload, true), true);

                $post_action = User_Input_Handler_Registry::create_action_screen(
                    $parent_media_url->source_window_id,
                    $this->force_parent_reload ? $parent_media_url->end_action : $parent_media_url->cancel_action,
                    null,
                    array('reload_action' => $parent_media_url->edit_list)
                );
                hd_debug_print("post action: " . pretty_json_format($post_action));

                $reload = $this->force_parent_reload;
                $this->force_parent_reload = false;
                return Action_Factory::invalidate_folders(
                    $reload ? array($parent_media_url->source_media_url_str) : array(),
                    Action_Factory::close_and_run($post_action)
                );

            case ACTION_ITEM_DELETE:
                if ($parent_media_url->edit_list === self::SCREEN_EDIT_HIDDEN_CHANNELS) {
                    $this->plugin->set_channel_visible($selected_id, 0);
                    $this->plugin->change_channels_order($parent_media_url->group_id, $selected_id, false);
                    $force_return = $this->plugin->get_channels_order_count($parent_media_url->group_id) === 0;
                    hd_debug_print("restore channel: " . $selected_id, true);
                } else if ($parent_media_url->edit_list === self::SCREEN_EDIT_HIDDEN_GROUPS) {
                    $this->plugin->set_groups_visible($selected_id, 0);
                    $force_return = $this->plugin->get_groups_count(false, true) === 0;
                    hd_debug_print("restore group: " . $selected_id, true);
                } else {
                    hd_debug_print("unknown edit list");
                    return null;
                }

                if (!$force_return) break;

                $this->force_parent_reload = true;
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, self::ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case self::ACTION_CONFIRM_CLEAR_DLG_APPLY:
                if ($parent_media_url->edit_list === self::SCREEN_EDIT_HIDDEN_CHANNELS) {
                    $this->plugin->set_channel_visible($this->plugin->get_channels($parent_media_url->group_id, 1), false);
                } else if ($parent_media_url->edit_list === self::SCREEN_EDIT_HIDDEN_GROUPS) {
                    $this->plugin->set_groups_visible($this->plugin->get_groups(false, true), false);
                } else {
                    return null;
                }

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("MediaUrl: " . $media_url, true);

        $items = array();
        if ($media_url->edit_list === self::SCREEN_EDIT_HIDDEN_CHANNELS) {
            foreach ($this->plugin->get_channels($media_url->group_id, 1) as $channel_row) {
                if (empty($channel_row)) continue;

                $items[] = self::add_item(
                    $channel_row['channel_id'],
                    $channel_row['title'],
                    false,
                    empty($channel_row['icon']) ? DEFAULT_CHANNEL_ICON_PATH : $channel_row['icon'],
                    null
                );
            }
        }

        if ($media_url->edit_list === self::SCREEN_EDIT_HIDDEN_GROUPS) {
            foreach ($this->plugin->get_groups(false, true) as $group_row) {
                if (empty($group_row)) continue;

                $items[] = self::add_item(
                    $group_row['group_id'],
                    $group_row['title'],
                    false,
                    empty($group_row['icon']) ? DEFAULT_GROUP_ICON : $group_row['icon'],
                    null
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
    public function get_folder_views()
    {
        hd_debug_print(null, true);

        return array(
            $this->plugin->get_screen_view('list_1x11_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),
        );
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    protected static function add_item($id, $title, $starred, $icon_file, $detailed_info)
    {
        return array(
            PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $id)),
            PluginRegularFolderItem::caption => $title,
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::item_sticker => ($starred ? Control_Factory::create_sticker(get_image_path('star_small.png'), -55, -2) : null),
                ViewItemParams::icon_path => $icon_file,
                ViewItemParams::item_detailed_info => $detailed_info,
                ViewItemParams::item_detailed_icon_path => $icon_file,
            ),
        );
    }
}
