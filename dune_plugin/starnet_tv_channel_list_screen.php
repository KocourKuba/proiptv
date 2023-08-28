<?php
require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_Tv_Channel_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_channel_list';

    /**
     * @param string $group_id
     * @return false|string
     */
    public static function get_media_url_str($group_id)
    {
        return MediaURL::encode(
            array
            (
                'screen_id' => self::ID,
                'group_id' => $group_id,
            ));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct(self::ID, $plugin);

        $plugin->create_screen($this);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_print(__METHOD__ . ": " . $media_url->get_raw_string());

        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_FOLDER);
        $action_settings = User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS);
        $show_popup = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        $actions = array(
            GUI_EVENT_KEY_ENTER      => $action_play,
            GUI_EVENT_KEY_PLAY       => $action_play,
            GUI_EVENT_KEY_POPUP_MENU => $show_popup,
            GUI_EVENT_KEY_SETUP      => $action_settings,
        );

        if ((string)$media_url->group_id === ALL_CHANNEL_GROUP_ID) {
            $search_action = User_Input_Handler_Registry::create_action($this, ACTION_CREATE_SEARCH, TR::t('search'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = $search_action;
            $actions[GUI_EVENT_KEY_SEARCH] = $search_action;
        } else {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        }

        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));

        return $actions;
    }

    /**
     * @return string
     */
    public function get_handler_id()
    {
        return self::ID . '_handler';
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

                return Action_Factory::tv_play($media_url);

            case ACTION_ADD_FAV:
                $opt_type = $this->plugin->tv->get_favorites()->in_order($channel_id) ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_tv_favorites($opt_type, $channel_id, $plugin_cookies);
                return Action_Factory::invalidate_folders(array(self::get_media_url_str($parent_group_id)));

            case ACTION_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case ACTION_CREATE_SEARCH:
                $defs = array();
                Control_Factory::add_text_field($defs, $this, null, ACTION_NEW_SEARCH, '',
                    $channel->get_title(), false, false, true, true, 1300, false, true);
                Control_Factory::add_vgap($defs, 500);
                return Action_Factory::show_dialog(TR::t('tv_screen_search_channel'), $defs, true, 1300);

            case ACTION_NEW_SEARCH:
                return Action_Factory::close_dialog_and_run(User_Input_Handler_Registry::create_action($this, ACTION_RUN_SEARCH));

            case ACTION_RUN_SEARCH:
                $defs = array();
                $find_text = $user_input->{ACTION_NEW_SEARCH};
                $q = false;
                foreach ($group->get_group_channels() as $idx => $tv_channel) {
                    $ch_title = $tv_channel->get_title();
                    $s = mb_stripos($ch_title, $find_text, 0, "UTF-8");
                    if ($s !== false) {
                        $q = true;
                        hd_print(__METHOD__ . ": found channel: $ch_title, idx: " . $idx);
                        $add_params['number'] = $idx;
                        Control_Factory::add_close_dialog_and_apply_button_title($defs, $this, $add_params,
                            ACTION_JUMP_TO_CHANNEL, '', $ch_title, 900);
                    }
                }

                if ($q === false) {
                    Control_Factory::add_multiline_label($defs, '', TR::t('tv_screen_not_found'), 6);
                    Control_Factory::add_vgap($defs, 20);
                    Control_Factory::add_close_dialog_and_apply_button_title($defs, $this, null,
                        ACTION_CREATE_SEARCH, '', TR::t('new_search'), 300);
                }

                return Action_Factory::show_dialog(TR::t('search'), $defs, true);

            case ACTION_JUMP_TO_CHANNEL:
                $ndx = (int)$user_input->number;
                $parent_media_url->group_id = $parent_group_id;
                return Action_Factory::update_regular_folder(
                    $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
                    true,
                    $ndx);

            case ACTION_ITEM_UP:
                if (!$group->get_items_order()->arrange_item($channel_id, Ordered_Array::UP))
                    return null;

                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }
                return $this->update_current_folder($user_input, $parent_group_id);

            case ACTION_ITEM_DOWN:
                if (!$group->get_items_order()->arrange_item($channel_id, Ordered_Array::DOWN))
                    return null;

                $groups_cnt = $group->get_items_order()->size();
                $user_input->sel_ndx++;
                if ($user_input->sel_ndx >= $groups_cnt) {
                    $user_input->sel_ndx = $groups_cnt - 1;
                }
                return $this->update_current_folder($user_input, $parent_group_id);

            case ACTION_ITEM_DELETE:
                hd_print(__METHOD__ . ": Hide $channel_id");
                $channel->set_disabled(true);
                if ($group->is_all_channels_group()) {
                    foreach ($this->plugin->tv->get_groups() as $group) {
                        $group->get_items_order()->remove_item($channel_id);
                    }
                } else {
                    $group->get_items_order()->remove_item($channel_id);
                }

                $this->plugin->tv->get_disabled_channels()->add_item($channel_id);

                return $this->update_current_folder($user_input, $parent_group_id);

            case ACTION_ITEMS_SORT:
                $group->get_items_order()->sort_order();
                return $this->update_current_folder($user_input, $parent_group_id);

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items = array();

                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_ITEM_DELETE,
                    TR::t('tv_screen_hide_channel'),
                    $this->plugin->get_image_path('remove.png')
                );

                if (!$group->is_all_channels_group()) {
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        ACTION_ITEMS_SORT,
                        TR::t('sort_items'),
                        $this->plugin->get_image_path('sort.png')
                    );
                }

                if (is_android() && !is_apk()) {
                    $menu_items[] = array(GuiMenuItemDef::is_separator => true,);
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        ACTION_EXTERNAL_PLAYER,
                        TR::t('vod_screen_external_player'),
                        'gui_skin://small_icons/playback.aai'
                    );
                    $menu_items[] = array(GuiMenuItemDef::is_separator => true,);
                }

                $zoom_data = $this->plugin->get_settings(PARAM_CHANNELS_ZOOM, array());
                $current_idx = isset($zoom_data[$channel_id]) ? $zoom_data[$channel_id] : DuneVideoZoomPresets::not_set;
                foreach (DuneVideoZoomPresets::$zoom_ops as $idx => $zoom_item) {
                    $add_param[ACTION_ZOOM_SELECT] = (string)$idx;

                    $icon_url = null;
                    if ((string)$idx === (string)$current_idx) {
                        $icon_url = "gui_skin://button_icons/proceed.aai";
                    }
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_ZOOM_APPLY,
                        $zoom_item, $icon_url, $add_param);
                }

                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_ZOOM_APPLY:
                if (isset($user_input->{ACTION_ZOOM_SELECT})) {

                    $zoom_select = $user_input->{ACTION_ZOOM_SELECT};
                    $zoom_data = $this->plugin->get_settings(PARAM_CHANNELS_ZOOM, array());
                    if ($zoom_select === DuneVideoZoomPresets::not_set) {
                        hd_print(__METHOD__ . ": Zoom preset removed for channel: $channel_id");
                        unset ($zoom_data[$channel_id]);
                    } else {
                        hd_print(__METHOD__ . ": Zoom preset $zoom_select for channel: $channel_id");
                        $zoom_data[$channel_id] = $zoom_select;
                    }

                    $this->plugin->set_settings(PARAM_CHANNELS_ZOOM, $zoom_data);
                }
                break;

            case ACTION_EXTERNAL_PLAYER:
                try {
                    $url = $this->plugin->generate_stream_url(-1, $channel);
                    $url = str_replace("ts://", "", $url);
                    $param_pos = strpos($url, '|||dune_params');
                    $url =  $param_pos!== false ? substr($url, 0, $param_pos) : $url;
                    $cmd = 'am start -d "' . $url . '" -t "video/*" -a android.intent.action.VIEW 2>&1';
                    hd_print(__METHOD__ . ": play movie in the external player: $cmd");
                    exec($cmd, $output);
                    hd_print(__METHOD__ . ": external player exec result code" . HD::ArrayToStr($output));
                } catch (Exception $ex) {
                    hd_print(__METHOD__ . ": Movie can't played, exception info: " . $ex->getMessage());
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }
                break;

            case ACTION_RELOAD:
                hd_print(__METHOD__ . ": reload");
                $this->plugin->tv->unload_channels();
                return $this->update_current_folder($user_input, $parent_group_id);
                //return $this->plugin->tv->reload_channels($this, $plugin_cookies, $this->update_current_folder($user_input, $parent_group_id));
        }

        return null;
    }

    public function update_current_folder($user_input, $group_id)
    {
        Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);

        return Action_Factory::invalidate_folders(
            array(Starnet_Tv_Groups_Screen::ID, self::get_media_url_str($group_id)),
            Starnet_Epfs_Handler::invalidate_folders(array($user_input->parent_media_url),
                Action_Factory::close_and_run(
                    Action_Factory::open_folder(
                        $user_input->parent_media_url,
                        null,
                        null,
                        null,
                        Action_Factory::update_regular_folder(
                            $this->get_folder_range(MediaURL::decode($user_input->parent_media_url), 0, $plugin_cookies),
                            true,
                            $user_input->sel_ndx)
                    )
                )
            )
        );
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Group $group
     * @param Channel $channel
     * @return array
     */
    private function get_regular_folder_item($group, $channel)
    {
        return array
        (
            PluginRegularFolderItem::media_url => MediaURL::encode(array('channel_id' => $channel->get_id(), 'group_id' => $group->get_id())),
            PluginRegularFolderItem::caption => $channel->get_title(),
            PluginRegularFolderItem::view_item_params => array
            (
                ViewItemParams::icon_path => $channel->get_icon_url(),
                ViewItemParams::item_detailed_icon_path => $channel->get_icon_url(),
            ),
            PluginRegularFolderItem::starred => $this->plugin->tv->get_favorites()->in_order($channel->get_id()),
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
        hd_print(__METHOD__ . ": media url: " . $media_url->get_media_url_str());
        HD::print_backtrace();

        $items = array();

        try {
            $this->plugin->tv->ensure_channels_loaded($plugin_cookies);
            $this_group = $this->plugin->tv->get_group($media_url->group_id);
            if (is_null($this_group)) {
                throw new Exception('group not found');
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
                    //hd_print("channel: " . str_replace(chr(0), ' ', serialize($channel)));;
                    if ($channel->is_disabled()) continue;

                    $items[] = $this->get_regular_folder_item($this_group, $channel);
                }
            }
        } catch (Exception $e) {
            hd_print(__METHOD__ . ": Failed collect folder items! " . $e->getMessage());
        }

        return $items;
    }

    /**
     * @return array[]
     */
    public function get_folder_views()
    {
        return array(
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
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
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
                    ViewItemParams::icon_width => $this->plugin->get_settings(PARAM_SQUARE_ICONS) ? 60 : 84,
                    ViewItemParams::icon_height => $this->plugin->get_settings(PARAM_SQUARE_ICONS) ? 60 : 48,
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
                    ViewItemParams::icon_width => $this->plugin->get_settings(PARAM_SQUARE_ICONS) ? 60 : 84,
                    ViewItemParams::icon_height => $this->plugin->get_settings(PARAM_SQUARE_ICONS) ? 60 : 48,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,
                    ViewParams::background_path=> $this->plugin->plugin_info['app_background'],
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
}
