<?php
require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_Tv_Channel_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_channel_list';

    const ACTION_NEW_SEARCH = 'new_search';
    const ACTION_CREATE_SEARCH = 'create_search';
    const ACTION_RUN_SEARCH = 'run_search';
    const ACTION_JUMP_TO_CHANNEL = 'jump_to_channel';

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
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_debug_print($media_url->get_raw_string());

        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_FOLDER);
        $action_settings = User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS);
        $show_popup = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        $actions = array(
            GUI_EVENT_KEY_ENTER      => $action_play,
            GUI_EVENT_KEY_PLAY       => $action_play,
            GUI_EVENT_KEY_POPUP_MENU => $show_popup,
            GUI_EVENT_KEY_SETUP      => $action_settings,
        );
        $actions[GUI_EVENT_KEY_RETURN]     = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

        if ((string)$media_url->group_id === ALL_CHANNEL_GROUP_ID) {
            $search_action = User_Input_Handler_Registry::create_action($this, self::ACTION_CREATE_SEARCH, TR::t('search'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = $search_action;
            $actions[GUI_EVENT_KEY_SEARCH] = $search_action;
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
        } else if (!is_null($group = $this->plugin->tv->get_group($media_url->group_id)) && $group->get_items_order()->size() !== 0) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
        }

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

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        $media_url = MediaURL::decode($user_input->selected_media_url);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $channel_id = $media_url->channel_id;
        $parent_group_id = $media_url->group_id;
        $group = $this->plugin->tv->get_group($parent_group_id);
        $channel = $this->plugin->tv->get_channel($channel_id);
        $sel_ndx = $user_input->sel_ndx;

        if (is_null($group) || is_null($channel))
            return null;

        switch ($user_input->control_id) {
            case ACTION_PLAY_FOLDER:
                try {
                    $this->plugin->generate_stream_url(-1, $channel);
                } catch (Exception $ex) {
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }

                return $this->update_epfs_data($plugin_cookies, Action_Factory::tv_play($media_url));

            case ACTION_ADD_FAV:
                $opt_type = $this->plugin->get_favorites()->in_order($channel_id) ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_tv_favorites($opt_type, $channel_id, $plugin_cookies);
                $this->need_update_epfs = true;
                break;

            case ACTION_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case self::ACTION_CREATE_SEARCH:
                $defs = array();
                Control_Factory::add_text_field($defs, $this, null, self::ACTION_NEW_SEARCH, '',
                    $channel->get_title(), false, false, true, true, 1300, false, true);
                Control_Factory::add_vgap($defs, 500);
                return Action_Factory::show_dialog(TR::t('tv_screen_search_channel'), $defs, true, 1300);

            case self::ACTION_NEW_SEARCH:
                return Action_Factory::close_dialog_and_run(User_Input_Handler_Registry::create_action($this, self::ACTION_RUN_SEARCH));

            case self::ACTION_RUN_SEARCH:
                $defs = array();
                $find_text = $user_input->{self::ACTION_NEW_SEARCH};
                $q = false;
                foreach ($group->get_group_channels() as $idx => $tv_channel) {
                    $ch_title = $tv_channel->get_title();
                    $s = mb_stripos($ch_title, $find_text, 0, "UTF-8");
                    if ($s !== false) {
                        $q = true;
                        hd_debug_print("found channel: $ch_title, idx: " . $idx);
                        $add_params['number'] = $idx;
                        Control_Factory::add_close_dialog_and_apply_button_title($defs, $this, $add_params,
                            self::ACTION_JUMP_TO_CHANNEL, '', $ch_title, 900);
                    }
                }

                if ($q === false) {
                    Control_Factory::add_multiline_label($defs, '', TR::t('tv_screen_not_found'), 6);
                    Control_Factory::add_vgap($defs, 20);
                    Control_Factory::add_close_dialog_and_apply_button_title($defs, $this, null,
                        self::ACTION_CREATE_SEARCH, '', TR::t('new_search'), 300);
                }

                return Action_Factory::show_dialog(TR::t('search'), $defs, true);

            case self::ACTION_JUMP_TO_CHANNEL:
                return $this->update_current_folder($parent_media_url->group_id, $plugin_cookies, $user_input->number);

            case ACTION_ITEM_UP:
                if (!$group->get_items_order()->arrange_item($channel_id, Ordered_Array::UP))
                    return null;

                $sel_ndx--;
                if ($sel_ndx < 0) {
                    $sel_ndx = 0;
                }
                $this->need_update_epfs = true;
                break;

            case ACTION_ITEM_DOWN:
                if (!$group->get_items_order()->arrange_item($channel_id, Ordered_Array::DOWN))
                    return null;

                $groups_cnt = $group->get_items_order()->size();
                $sel_ndx++;
                if ($sel_ndx >= $groups_cnt) {
                    $sel_ndx = $groups_cnt - 1;
                }
                $this->need_update_epfs = true;
                break;

            case ACTION_ITEM_DELETE:
                hd_debug_print("Hide $channel_id");
                $channel->set_disabled(true);
                if ($group->is_all_channels_group()) {
                    foreach ($this->plugin->tv->get_groups() as $group) {
                        $group->get_items_order()->remove_item($channel_id);
                    }
                } else {
                    $group->get_items_order()->remove_item($channel_id);
                }

                $this->plugin->tv->get_disabled_channels()->add_item($channel_id);
                $this->need_update_epfs = true;
                break;

            case ACTION_ITEMS_SORT:
                $group->get_items_order()->sort_order();
                $this->need_update_epfs = true;
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items = array();

                $this->create_menu_item($menu_items, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_channel'),"remove.png");

                if (!$group->is_all_channels_group()) {
                    $this->create_menu_item($menu_items, ACTION_ITEMS_SORT, TR::t('sort_items'),"sort.png");
                }

                if (is_android() && !is_apk()) {
                    $this->create_menu_item($menu_items, GuiMenuItemDef::is_separator);
                    $this->create_menu_item($menu_items, ACTION_EXTERNAL_PLAYER, TR::t('tv_screen_external_player'), "play.png");
                    $this->create_menu_item($menu_items, GuiMenuItemDef::is_separator);
                }

                $zoom_data = $this->plugin->tv->get_channel_zoom($channel_id);
                foreach (DuneVideoZoomPresets::$zoom_ops as $idx => $zoom_item) {
                    $this->create_menu_item($menu_items, ACTION_ZOOM_APPLY, TR::t($zoom_item),
                        strcmp($idx, $zoom_data) !== 0 ? null : "aspect.png",
                        array(ACTION_ZOOM_SELECT => (string)$idx));
                }

                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_ZOOM_APPLY:
                if (isset($user_input->{ACTION_ZOOM_SELECT})) {
                    $zoom_select = $user_input->{ACTION_ZOOM_SELECT};
                    $this->plugin->tv->set_channel_zoom($channel_id, ($zoom_select !== DuneVideoZoomPresets::not_set) ? $zoom_select : null);
                }
                break;

            case ACTION_EXTERNAL_PLAYER:
                try {
                    $url = $this->plugin->generate_stream_url(-1, $channel);
                    $url = str_replace("ts://", "", $url);
                    $param_pos = strpos($url, '|||dune_params');
                    $url =  $param_pos!== false ? substr($url, 0, $param_pos) : $url;
                    $cmd = 'am start -d "' . $url . '" -t "video/*" -a android.intent.action.VIEW 2>&1';
                    hd_debug_print("play movie in the external player: $cmd");
                    exec($cmd, $output);
                    hd_debug_print("external player exec result code" . HD::ArrayToStr($output));
                } catch (Exception $ex) {
                    hd_debug_print("Movie can't played, exception info: " . $ex->getMessage());
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }
                break;

            case ACTION_RELOAD:
                hd_debug_print("reload");
                $this->plugin->tv->unload_channels();
                return $this->update_current_folder($user_input, $parent_group_id);

            case GUI_EVENT_KEY_RETURN:
                return $this->update_epfs_data($plugin_cookies, Action_Factory::close_and_run());
        }

        return $this->update_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Group $group
     * @param Channel $channel
     * @return array
     */
    private function get_regular_folder_item($group, $channel)
    {
        $zoom_data = $this->plugin->tv->get_channel_zoom($channel->get_id());
        if ($zoom_data === DuneVideoZoomPresets::not_set) {
            $detailed_info = TR::t('tv_screen_channel_info__2', $channel->get_title(), $channel->get_archive());
        } else {
            $detailed_info = TR::t('tv_screen_channel_info__3',
                $channel->get_title(), $channel->get_archive(),
                TR::load_string(DuneVideoZoomPresets::$zoom_ops[$zoom_data]));
        }

        return array
        (
            PluginRegularFolderItem::media_url => MediaURL::encode(array('channel_id' => $channel->get_id(), 'group_id' => $group->get_id())),
            PluginRegularFolderItem::caption => $channel->get_title(),
            PluginRegularFolderItem::view_item_params => array
            (
                ViewItemParams::icon_path => $channel->get_icon_url(),
                ViewItemParams::item_detailed_icon_path => $channel->get_icon_url(),
                ViewItemParams::item_detailed_info => $detailed_info,
            ),
            PluginRegularFolderItem::starred => $this->plugin->get_favorites()->in_order($channel->get_id()),
        );
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_debug_print("media url: " . $media_url->get_media_url_str());

        $items = array();

        try {
            if (!$this->plugin->tv->load_channels($plugin_cookies)) {
                throw new Exception("Channels not loaded!");
            }

            $this_group = $this->plugin->tv->get_group($media_url->group_id);
            if (is_null($this_group)) {
                throw new Exception("Group $media_url->group_id not found");
            }

            /** @var Channel $channel */
            if ($this_group->is_all_channels_group()) {
                foreach($this->plugin->tv->get_channels() as $channel) {
                    if ($channel->is_disabled()) continue;

                    foreach ($channel->get_groups() as $group) {
                        if ($group->is_disabled()) continue;

                        $items[] = $this->get_regular_folder_item($this_group, $channel);
                    }
                }
            } else {
                foreach ($this_group->get_items_order()->get_order() as $item) {
                    $channel = $this->plugin->tv->get_channel($item);
                    //hd_debug_print("channel: " . str_replace(chr(0), ' ', serialize($channel)));;
                    if (is_null($channel) || $channel->is_disabled()) continue;

                    $items[] = $this->get_regular_folder_item($this_group, $channel);
                }
            }
        } catch (Exception $e) {
            hd_debug_print("Failed collect folder items! " . $e->getMessage());
        }

        return $items;
    }

    /**
     * @return array[]
     */
    public function get_folder_views()
    {
        $square_icons = ($this->plugin->get_settings(PARAM_SQUARE_ICONS, SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on);
        $background = $this->plugin->plugin_info['app_background'];

        return array(
            // 4x3 with title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
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

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 3x3 without title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
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

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 4x4 without title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 4,
                    ViewParams::background_path => $background,
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
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 5x4 without title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 4,
                    ViewParams::background_path => $background,
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
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 2x10 title list view with right side icon
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 2,
                    ViewParams::num_rows => 10,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => $square_icons ? 60 : 84,
                    ViewItemParams::icon_height => $square_icons ? 60 : 48,
                    ViewItemParams::item_caption_width => 485,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_SMALL,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 1x10 title list view with right side icon
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 10,
                    ViewParams::paint_icon_selection_box=> true,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => $square_icons ? 60 : 84,
                    ViewItemParams::icon_height => $square_icons ? 60 : 48,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,
                    ViewParams::background_path=> $background,
                    ViewParams::background_order => 0,
                    ViewParams::item_detailed_info_text_color => 11,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::zoom_detailed_icon => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::icon_dx => 26,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_width => 1060,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),
        );
    }

    /**
     * @param $menu_items array
     * @param $action_id string
     * @param $caption string
     * @param $icon string
     * @param $add_params array|null
     * @return void
     */
    private function create_menu_item(&$menu_items, $action_id, $caption = null, $icon = null, $add_params = null)
    {
        if ($action_id === GuiMenuItemDef::is_separator) {
            $menu_items[] = array($action_id => true);
        } else {
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                $action_id, $caption, ($icon === null) ? null : get_image_path($icon), $add_params);
        }
    }
}
