<?php
require_once 'lib/abstract_preloaded_regular_screen.php';
require_once 'starnet_setup_screen.php';
require_once 'starnet_playlists_setup_screen.php';

class Starnet_Tv_Groups_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_groups';

    const ACTION_CONFIRM_DLG_APPLY = 'apply_dlg';
    const ACTION_EPG_SETTINGS = 'epg_settings';
    const ACTION_CHANNELS_SETTINGS = 'channels_settings';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        // if token not set force to open setup screen
        //hd_debug_print();

        if (!$this->plugin->tv->load_channels($plugin_cookies)) {
            hd_debug_print("Channels not loaded!");
        }

        $actions = array();

        $actions[GUI_EVENT_KEY_ENTER]      = User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER);
        $actions[GUI_EVENT_KEY_PLAY]       = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_FOLDER);
        $actions[GUI_EVENT_KEY_RETURN]     = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

        $order = $this->plugin->tv->get_groups_order();
        if (!is_null($order) && $order->size() !== 0) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        }

        $actions[GUI_EVENT_KEY_D_BLUE]     = User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS, TR::t('entry_setup'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        return $actions;
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);
        $min_sel = $this->plugin->get_special_groups_count($plugin_cookies);
        $sel_idx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case ACTION_OPEN_FOLDER:
            case ACTION_PLAY_FOLDER:
                $post_action = $this->update_epfs_data($plugin_cookies,
                    null,
                    $user_input->control_id === ACTION_OPEN_FOLDER ? Action_Factory::open_folder() : Action_Factory::tv_play());

                $has_error = $this->plugin->get_last_error();
                if (!empty($has_error)) {
                    $this->plugin->set_last_error('');
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_load_any'), $post_action, $has_error);
                }

                return $post_action;

            case ACTION_ITEM_UP:
                $sel_media_url = MediaURL::decode($user_input->selected_media_url);
                if (!$this->plugin->tv->get_groups_order()->arrange_item($sel_media_url->group_id, Ordered_Array::UP))
                    return null;

                $sel_idx--;
                if ($sel_idx < $min_sel) {
                    $sel_idx = $min_sel;
                }
                $this->invalidate_epfs();

                break;

            case ACTION_ITEM_DOWN:
                $sel_media_url = MediaURL::decode($user_input->selected_media_url);
                if (!$this->plugin->tv->get_groups_order()->arrange_item($sel_media_url->group_id, Ordered_Array::DOWN))
                    return null;

                $groups_cnt = $min_sel + $this->plugin->tv->get_groups_order()->size();
                $sel_idx++;
                if ($sel_idx >= $groups_cnt) {
                    $sel_idx = $groups_cnt - 1;
                }
                $this->invalidate_epfs();

                break;

            case ACTION_ITEM_DELETE:
                $sel_media_url = MediaURL::decode($user_input->selected_media_url);
                hd_debug_print("Hide $sel_media_url->group_id");
                $this->plugin->tv->disable_group($sel_media_url->group_id);
                $this->invalidate_epfs();

                break;

            case ACTION_ITEMS_SORT:
                $this->plugin->tv->get_groups_order()->sort_order();
                $this->invalidate_epfs();

                break;

            case ACTION_ITEMS_EDIT:
                $this->plugin->set_pospone_save();
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Edit_List_Screen::ID,
                        'source_window_id' => static::ID,
                        'edit_list' => Starnet_Edit_List_Screen::SCREEN_EDIT_GROUPS,
                        'end_action' => ACTION_RELOAD,
                        'cancel_action' => ACTION_REFRESH_SCREEN,
                        'postpone_save' => 'settings',
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('tv_screen_edit_hidden_group'));

            case ACTION_ITEMS_EDIT . "2":
                $this->plugin->set_pospone_save();
                $sel_media_url = MediaURL::decode($user_input->selected_media_url);
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Edit_List_Screen::ID,
                        'source_window_id' => static::ID,
                        'edit_list' => Starnet_Edit_List_Screen::SCREEN_EDIT_CHANNELS,
                        'group_id' => $sel_media_url->group_id,
                        'end_action' => ACTION_RELOAD,
                        'cancel_action' => ACTION_REFRESH_SCREEN,
                        'postpone_save' => 'settings',
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('tv_screen_edit_hidden_channels'));

            case ACTION_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case self::ACTION_CHANNELS_SETTINGS:
                return Action_Factory::open_folder(Starnet_Playlists_Setup_Screen::get_media_url_str(), TR::t('tv_screen_playlists_setup'));

            case self::ACTION_EPG_SETTINGS:
                return Action_Factory::open_folder(Starnet_Epg_Setup_Screen::get_media_url_str(), TR::t('setup_epg_settings'));

            case self::ACTION_CONFIRM_DLG_APPLY:
                return Starnet_Epfs_Handler::invalidate_folders(null, Action_Factory::close_and_run());

            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($sel_media_url->group_id) && $sel_media_url->group_id !== ALL_CHANNEL_GROUP_ID) {
                    $this->create_menu_item($this, $menu_items, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_group'),"hide.png");
                }

                $this->create_menu_item($this, $menu_items, ACTION_ITEMS_SORT, TR::t('sort_items'), "sort.png");
                $this->create_menu_item($this, $menu_items, GuiMenuItemDef::is_separator);

                if ($this->plugin->tv->get_disabled_groups()->size()) {
                    $this->create_menu_item($this, $menu_items, ACTION_ITEMS_EDIT, TR::t('tv_screen_edit_hidden_group'), "edit.png");
                }

                if (isset($sel_media_url->group_id)) {
                    $has_hidden = false;
                    if ($sel_media_url->group_id === ALL_CHANNEL_GROUP_ID) {
                        $has_hidden = $this->plugin->tv->get_disabled_channels()->size() !== 0;
                    } else if (($group = $this->plugin->tv->get_group($sel_media_url->group_id)) !== null) {
                        $has_hidden = $group->get_group_channels()->size() !== $group->get_items_order()->size();
                    }

                    if ($has_hidden) {
                        $this->create_menu_item($this, $menu_items, ACTION_ITEMS_EDIT . "2", TR::t('tv_screen_edit_hidden_channels'),"edit.png");
                    }
                }

                $this->create_menu_item($this, $menu_items, GuiMenuItemDef::is_separator);
                $this->create_menu_item($this, $menu_items, self::ACTION_CHANNELS_SETTINGS, TR::t('tv_screen_playlists_setup'),"playlist.png");
                $this->create_menu_item($this, $menu_items,self::ACTION_EPG_SETTINGS, TR::t('setup_epg_settings'),"epg.png");

                return Action_Factory::show_popup_menu($menu_items);

            case GUI_EVENT_KEY_RETURN:
                if (isset($plugin_cookies->{Starnet_Interface_Setup_Screen::CONTROL_ASK_EXIT})
                    && $plugin_cookies->{Starnet_Interface_Setup_Screen::CONTROL_ASK_EXIT} === SetupControlSwitchDefs::switch_off) {
                    return $this->update_epfs_data($plugin_cookies, null, Action_Factory::close_and_run());
                }

                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CONFIRM_DLG_APPLY);

            case ACTION_RELOAD:
                hd_debug_print("reload");
                $this->plugin->tv->unload_channels();
                break;

            case ACTION_REFRESH_SCREEN:
                break;

            default:
                return null;
        }

        return $this->update_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $sel_idx);
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_debug_print("get_all_folder_items");
        $items = array();
        if (!$this->plugin->tv->load_channels($plugin_cookies)) {
            hd_debug_print("Channels not loaded!");
            return $items;
        }

        $show_all = $this->plugin->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::CONTROL_SHOW_ALL);
        $show_favorites = $this->plugin->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::CONTROL_SHOW_FAVORITES);
        $show_history = $this->plugin->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::CONTROL_SHOW_HISTORY);

        /** @var Group $group */
        if ($show_favorites) {
            $group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
            if (!is_null($group)) {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_Tv_Favorites_Screen::get_media_url_str(),
                    PluginRegularFolderItem::caption => $group->get_title(),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => $group->get_icon_url(),
                        ViewItemParams::item_detailed_icon_path => $group->get_icon_url()
                        )
                    );
            }
        }

        if ($show_history) {
            $group = $this->plugin->tv->get_special_group(HISTORY_GROUP_ID);
            if (!is_null($group)) {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_TV_History_Screen::get_media_url_str(),
                    PluginRegularFolderItem::caption => $group->get_title(),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => $group->get_icon_url(),
                        ViewItemParams::item_detailed_icon_path => $group->get_icon_url()
                    )
                );
            }
        }

        if ($show_all) {
            $group = $this->plugin->tv->get_special_group(ALL_CHANNEL_GROUP_ID);
            if (!is_null($group)) {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_Tv_Channel_List_Screen::get_media_url_string(ALL_CHANNEL_GROUP_ID),
                    PluginRegularFolderItem::caption => $group->get_title(),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => $group->get_icon_url(),
                        ViewItemParams::item_detailed_icon_path => $group->get_icon_url(),
                        ViewItemParams::item_detailed_info => TR::t('tv_screen_group_info__3',
                            $group->get_title(),
                            $this->plugin->tv->get_channels()->size(),
                            $this->plugin->tv->get_disabled_channels()->size()
                        ),
                    )
                );
            }
        }

        /** @var Group $group */
        foreach ($this->plugin->tv->get_groups_order()->get_order() as $item) {
            //hd_debug_print("group: {$group->get_title()} , icon: {$group->get_icon_url()}");
            $group = $this->plugin->tv->get_group($item);
            if (is_null($group) || $group->is_disabled()) continue;

            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Tv_Channel_List_Screen::get_media_url_string($group->get_id()),
                PluginRegularFolderItem::caption => $group->get_title(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $group->get_icon_url(),
                    ViewItemParams::item_detailed_icon_path => $group->get_icon_url(),
                    ViewItemParams::item_detailed_info => TR::t('tv_screen_group_info__3',
                        $group->get_title(),
                        $group->get_group_channels()->size(),
                        $group->get_group_channels()->size() - $group->get_items_order()->size()
                    ),
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                ),
            );
        }

        //hd_debug_print("Loaded items " . count($items));
        return $items;
    }

    /**
     * @return array[]
     */
    public function get_folder_views()
    {
        return array(

            // 1x12 list view with info
            array
            (
                PluginRegularFolderView::async_icon_loading => false,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_details => true,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 55,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_width => 1100
                ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            // 3x10 list view
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 10,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => false,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_caption_width => 485,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_dx => 50,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            // small with caption
            array
            (
                PluginRegularFolderView::async_icon_loading => false,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 3,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => Default_Dune_Plugin::SANDWICH_BASE,
                    ViewParams::sandwich_mask => Default_Dune_Plugin::SANDWICH_MASK,
                    ViewParams::sandwich_cover => Default_Dune_Plugin::SANDWICH_COVER,
                    ViewParams::sandwich_width => Default_Dune_Plugin::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => Default_Dune_Plugin::TV_SANDWICH_HEIGHT,
                    ViewParams::content_box_padding_left => 70,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.2,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            // 4x3 with title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => Default_Dune_Plugin::SANDWICH_BASE,
                    ViewParams::sandwich_mask => Default_Dune_Plugin::SANDWICH_MASK,
                    ViewParams::sandwich_cover => Default_Dune_Plugin::SANDWICH_COVER,
                    ViewParams::sandwich_width => Default_Dune_Plugin::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => Default_Dune_Plugin::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.25,
                    ViewItemParams::icon_sel_scale_factor => 1.5,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),
        );
    }
}
