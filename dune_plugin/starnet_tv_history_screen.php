<?php
require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_TV_History_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_history';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $actions = array();

        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);
        $actions[GUI_EVENT_KEY_ENTER]  = $action_play;
        $actions[GUI_EVENT_KEY_PLAY]   = $action_play;
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

        if ($this->plugin->playback_points->size() !== 0) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_CLEAR, TR::t('clear_history'));
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
            $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        }

        return $actions;
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        dump_input_handler($user_input);

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $media_url = MediaURL::decode($user_input->selected_media_url);
        $channel_id = $media_url->channel_id;
        $sel_ndx = $user_input->sel_ndx;

        switch ($user_input->control_id)
		{
            case ACTION_PLAY_ITEM:
                try {
                    $post_action = $this->plugin->player_exec($media_url);
                } catch (Exception $ex) {
                    hd_debug_print("Movie can't played, exception info: " . $ex->getMessage());
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }

                return $this->plugin->update_epfs_data($plugin_cookies, null, $post_action);

			case ACTION_ITEM_DELETE:
                $this->plugin->playback_points->erase_point($channel_id);
				$parent_media_url = MediaURL::decode($user_input->parent_media_url);
				$sel_ndx++;
				if ($sel_ndx < 0)
					$sel_ndx = 0;
                $this->plugin->invalidate_epfs();
                break;

            case ACTION_ITEMS_CLEAR:
                $this->plugin->playback_points->clear_points();
                $sel_ndx = 0;
                $this->plugin->invalidate_epfs();

                break;

			case ACTION_ADD_FAV:
				$is_favorite = $this->plugin->get_favorites()->in_order($channel_id);
				$opt_type = $is_favorite ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
				$message = $is_favorite ? TR::t('deleted_from_favorite') : TR::t('added_to_favorite');
				return Action_Factory::show_title_dialog($message,
                    $this->plugin->change_tv_favorites($opt_type, $channel_id));

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_history'), "brush.png");

                return Action_Factory::show_popup_menu($menu_items);

            case GUI_EVENT_KEY_RETURN:
                return $this->plugin->update_epfs_data($plugin_cookies, null, Action_Factory::close_and_run());
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        hd_debug_print($media_url->get_media_url_str());

        $items = array();
        $now = time();
        foreach ($this->plugin->playback_points->get_all() as $channel_id => $channel_ts) {
            if (is_null($channel = $this->plugin->tv->get_channel($channel_id))) continue;

            $prog_info = $this->plugin->get_program_info($channel_id, $channel_ts, $plugin_cookies);
            $description = '';
            if (is_null($prog_info)) {
                $title = $channel->get_title();
            } else {
                // program epg available
                $title = $prog_info[PluginTvEpgProgram::name];
                if ($channel_ts > 0) {
                    $start_tm = $prog_info[PluginTvEpgProgram::start_tm_sec];
                    $epg_len = $prog_info[PluginTvEpgProgram::end_tm_sec] - $start_tm;
                    $description = "{$channel->get_title()}|{$prog_info[PluginTvEpgProgram::description]}";
                    if ($channel_ts >= $now - $channel->get_archive_past_sec() - 60) {
                        $progress = max(0.01, min(1.0, round(($channel_ts - $start_tm) / $epg_len, 2))) * 100;
                        $title = "$title | " . date("j.m H:i", $channel_ts) . " [$progress%]";
                    }
                }
            }

            $items[] = array
            (
                PluginRegularFolderItem::media_url => MediaURL::encode(
                    array(
                        'channel_id' => $channel_id,
                        'group_id' => HISTORY_GROUP_ID,
                        'archive_tm' => $channel_ts
                    )
                ),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $channel->get_icon_url(),
                    ViewItemParams::item_detailed_icon_path => $channel->get_icon_url(),
                    ViewItemParams::item_detailed_info => $description,
                ),
                PluginRegularFolderItem::starred => false,
            );
        }

        return $items;
    }

    /**
     * @return array[]
     */
    public function get_folder_views()
    {
        return array(
            $this->plugin->get_screen_view('list_1x10_info'),
            $this->plugin->get_screen_view('list_1x12_info'),
        );
    }
}
