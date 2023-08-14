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
        parent::__construct(self::ID, $plugin, $plugin->GET_TV_CHANNEL_LIST_FOLDER_VIEWS());

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

        if ((string)$media_url->group_id === Default_Dune_Plugin::ALL_CHANNEL_GROUP_ID) {
            $search_action = User_Input_Handler_Registry::create_action($this, ACTION_CREATE_SEARCH, TR::t('search'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = $search_action;
            $actions[GUI_EVENT_KEY_SEARCH] = $search_action;
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
        $channel_id = $media_url->channel_id;
        $channel = $this->plugin->tv->get_channel($channel_id);

        switch ($user_input->control_id) {
            case ACTION_PLAY_FOLDER:
                try {
                    $this->plugin->GenerateStreamUrl($plugin_cookies, -1, $channel);
                } catch (Exception $ex) {
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }

                return Action_Factory::tv_play($media_url);

            case ACTION_ADD_FAV:
                $opt_type = $this->plugin->tv->is_favorite_channel_id($channel_id, $plugin_cookies) ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->tv->change_tv_favorites($opt_type, $channel_id, $plugin_cookies);
                return Action_Factory::invalidate_folders(array(self::get_media_url_str($media_url->group_id)));

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
                $group = $this->plugin->tv->get_group($media_url->group_id);
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
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                $parent_media_url->group_id = $media_url->group_id;
                $range = $this->get_folder_range($parent_media_url, 0, $plugin_cookies);
                return Action_Factory::update_regular_folder($range, true, $ndx);

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items = array();
                if (is_android() && !is_apk()) {
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                        ACTION_EXTERNAL_PLAYER,
                        TR::t('vod_screen_external_player'),
                        'gui_skin://small_icons/playback.aai'
                    );
                    $menu_items[] = array(GuiMenuItemDef::is_separator => true,);
                }

                $zoom_data = HD::get_data_items(Starnet_Tv::CHANNELS_ZOOM, true);
                $current_idx = isset($zoom_data[$channel_id]) ? $zoom_data[$channel_id] : DuneVideoZoomPresets::not_set;

                hd_print(__METHOD__ . ": Current idx: $current_idx");
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
                    $zoom_data = HD::get_data_items(Starnet_Tv::CHANNELS_ZOOM, true);
                    if ($zoom_select === DuneVideoZoomPresets::not_set) {
                        hd_print(__METHOD__ . ": Zoom preset removed for channel: $channel_id");
                        unset ($zoom_data[$channel_id]);
                    } else {
                        hd_print(__METHOD__ . ": Zoom preset $zoom_select for channel: $channel_id");
                        $zoom_data[$channel_id] = $zoom_select;
                    }

                    HD::put_data_items(Starnet_Tv::CHANNELS_ZOOM, $zoom_data);
                }
                break;

            case ACTION_EXTERNAL_PLAYER:
                try {
                    $url = $this->plugin->GenerateStreamUrl($plugin_cookies, -1, $channel);
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
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Group $group
     * @param Channel $channel
     * @param $plugin_cookies
     * @return array
     */
    private function get_regular_folder_item($group, $channel, &$plugin_cookies)
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
            PluginRegularFolderItem::starred => $this->plugin->tv->is_favorite_channel_id($channel->get_id(), $plugin_cookies),
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
        try {
            $this->plugin->tv->ensure_channels_loaded($plugin_cookies);
        } catch (Exception $e) {
            hd_print(__METHOD__ . ": Failed loading playlist! " . $e->getMessage());
            return array();
        }

        $group = $this->plugin->tv->get_group($media_url->group_id);

        $items = array();

        foreach ($group->get_group_channels() as $channel) {
            $items[] = $this->get_regular_folder_item($group, $channel, $plugin_cookies);
        }

        return $items;
    }
}
