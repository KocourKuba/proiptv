<?php

class Action_Factory
{
    /**
     * @param null $media_url_str
     * @param string|null $caption
     * @param null $id
     * @param null $sel_id
     * @param null $post_action
     * @param bool $keep_osd_context
     * @return array
     */
    public static function open_folder($media_url_str = null, $caption = null, $id = null, $sel_id = null, $post_action = null, $keep_osd_context = false)
    {
        $action =
            array
            (
                GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
                GuiAction::data => array
                    (
                        PluginOpenFolderActionData::media_url => $media_url_str,
                        PluginOpenFolderActionData::caption => $caption,
                    )
            );

        if (!is_null($id) && defined('PluginOpenFolderActionData::id'))
            $action[GuiAction::data][PluginOpenFolderActionData::id] = $id;

        if (!is_null($sel_id) && defined('PluginOpenFolderActionData::sel_id'))
            $action[GuiAction::data][PluginOpenFolderActionData::sel_id] = $sel_id;

        if (!is_null($post_action) && defined('PluginOpenFolderActionData::post_action'))
            $action[GuiAction::data][PluginOpenFolderActionData::post_action] = $post_action;

        if ($keep_osd_context && defined('PluginOpenFolderActionData::keep_osd_context'))
            $action[GuiAction::data][PluginOpenFolderActionData::keep_osd_context] = $keep_osd_context;

        return $action;
    }

    /**
     * @param MediaURL|string|null $media_url
     * @return array
     */
    public static function tv_play($media_url = null)
    {
        $action = array(GuiAction::handler_string_id => PLUGIN_TV_PLAY_ACTION_ID);

        if (is_null($media_url))
            return $action;

        if (is_string($media_url)) {
            //hd_print("tv_play str: " . $media_url);
            $action[GuiAction::params] = array('selected_media_url' => $media_url);
        } else if (is_object($media_url)) {
            //hd_print("tv_play MediaUrl: " . $media_url->get_media_url_str());
            $action[GuiAction::data] = array(
                PluginTvPlayActionData::initial_group_id => isset($media_url->group_id) ? $media_url->group_id : null,
                PluginTvPlayActionData::initial_channel_id => isset($media_url->channel_id) ? $media_url->channel_id : null,
                PluginTvPlayActionData::initial_is_favorite => isset($media_url->is_favorite) && $media_url->is_favorite,
                PluginTvPlayActionData::initial_archive_tm => isset($media_url->archive_tm) ? (int) $media_url->archive_tm : -1,
            );
        }

        return $action;
    }

    /**
     * @return array
     */
    public static function vod_play($vod_info = null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
            GuiAction::data => array(
                PluginVodPlayActionData::vod_info => $vod_info,
            ),
        );
    }

    /**
     * @param bool $fatal
     * @param string $title
     * @param string|null $msg_lines
     * @return array
     */
    public static function show_error($fatal, $title, $msg_lines = null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_SHOW_ERROR_ACTION_ID,
            GuiAction::caption => null,
            GuiAction::data => array(
                PluginShowErrorActionData::fatal => $fatal,
                PluginShowErrorActionData::title => $title,
                PluginShowErrorActionData::msg_lines => $msg_lines,
            ),
            GuiAction::params => null,
        );
    }

    /**
     * @param string $title
     * @param array &$defs
     * @param bool $close_by_return
     * @param int $preferred_width
     * @param array $attrs
     * @return array
     */
    public static function show_dialog($title, $defs, $close_by_return = false, $preferred_width = 0, $attrs = array())
    {
        $initial_sel_ndx = isset($attrs['initial_sel_ndx']) ? $attrs['initial_sel_ndx'] : -1;
        $actions = isset($attrs['actions']) ? $attrs['actions'] : null;
        $timer = isset($attrs['timer']) ? $attrs['timer'] : null;
        $min_item_title_width = isset($attrs['min_item_title_width']) ? $attrs['min_item_title_width'] : 0;
        $max_height = isset($attrs['max_height']) ? $attrs['max_height'] : 0;
        $dialog_params = isset($attrs['dialog_params']) ? $attrs['dialog_params'] : array();

        return array
        (
            GuiAction::handler_string_id => SHOW_DIALOG_ACTION_ID,
            GuiAction::caption => null,
            GuiAction::data =>
                array
                (
                    ShowDialogActionData::title => $title,
                    ShowDialogActionData::defs => $defs,
                    ShowDialogActionData::close_by_return => $close_by_return,
                    ShowDialogActionData::preferred_width => $preferred_width,
                    ShowDialogActionData::max_height => $max_height,
                    ShowDialogActionData::min_item_title_width => $min_item_title_width,
                    ShowDialogActionData::initial_sel_ndx => $initial_sel_ndx,
                    ShowDialogActionData::actions => $actions,
                    ShowDialogActionData::timer => $timer,
                    ShowDialogActionData::params => $dialog_params,
                ),
            GuiAction::params => null,
        );
    }

    /**
     * @param array|null $post_action
     * @return array
     */
    public static function close_dialog_and_run($post_action)
    {
        return array
        (
            GuiAction::handler_string_id => CLOSE_DIALOG_AND_RUN_ACTION_ID,
            GuiAction::caption => null,
            GuiAction::data =>
                array
                (
                    CloseDialogAndRunActionData::post_action => $post_action,
                ),
            GuiAction::params => null,
        );
    }

    /**
     * @return array
     */
    public static function close_dialog()
    {
        return self::close_dialog_and_run(null);
    }

    /**
     * @param string $title
     * @param array|null $post_action
     * @param string|null $multiline
     * @param int $preferred_width
     * @return array
     */
    public static function show_title_dialog($title, $post_action = null, $multiline = null, $preferred_width = 0)
    {
        $defs = array();

        if ($multiline !== null) {
            if ($preferred_width === 0) {
                foreach (explode("\n", $multiline) as $line) {
                    $px = mb_strlen($line, 'UTF-8') * 21;
                    if ($px > $preferred_width)
                        $preferred_width = (int)$px;
                }
            }

            Control_Factory::add_multiline_label($defs, '', $multiline, 15);
        }
        Control_Factory::add_custom_close_dialog_and_apply_buffon($defs, 'close_button', TR::t('ok'), 300, $post_action);

        return self::show_dialog($title, $defs, false, $preferred_width);
    }

    /**
     * Confirmation dialog
     * @return array
     */
    public static function show_confirmation_dialog($title, $handler, $action, $multiline = null, $preferred_width = 0)
    {
        $defs = array();

        if ($multiline !== null) {
            Control_Factory::add_multiline_label($defs, '', $multiline, 15);
        }

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, null, $action, TR::t('yes'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('no'), 300);

        return self::show_dialog($title, $defs, false, $preferred_width);
    }

    /**
     * @param int $delay_ms
     * @return array
     */
    public static function timer($delay_ms)
    {
        return array(GuiTimerDef::delay_ms => $delay_ms);
    }

    /**
     * @param string $status
     * @return array
     */
    public static function status($status)
    {
        return array(
            GuiAction::handler_string_id => STATUS_ACTION_ID,
            GuiAction::caption => null,
            GuiAction::data => array(StatusActionData::status => $status,),
            GuiAction::params => null,
        );
    }

    /**
     * @param array $media_urls
     * @param array $post_action
     * @return array
     */
    public static function invalidate_folders($media_urls, $post_action = null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_INVALIDATE_FOLDERS_ACTION_ID,
            GuiAction::data => array(
                PluginInvalidateFoldersActionData::media_urls => $media_urls,
                PluginInvalidateFoldersActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param array $menu_items
     * @param int $sel_ndx
     * @return array
     */
    public static function show_popup_menu($menu_items, $sel_ndx = 0)
    {
        return array(
            GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
            GuiAction::data => array(
                ShowPopupMenuActionData::menu_items => $menu_items,
                ShowPopupMenuActionData::selected_menu_item_index => $sel_ndx,
            ),
        );
    }

    /**
     * @param array $range
     * @param bool $need_refresh
     * @param int $sel_ndx
     * @return array
     */
    public static function update_regular_folder($range, $need_refresh = false, $sel_ndx = -1)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_UPDATE_FOLDER_ACTION_ID,
            GuiAction::data => array
            (
                PluginUpdateFolderActionData::range => $range,
                PluginUpdateFolderActionData::need_refresh => $need_refresh,
                PluginUpdateFolderActionData::sel_ndx => (int)$sel_ndx,
            ),
        );
    }

    /**
     * @param array &$defs
     * @param array $post_action
     * @param int $initial_sel_ndx
     * @return array
     */
    public static function reset_controls($defs, $post_action = null, $initial_sel_ndx = -1)
    {
        return array(
            GuiAction::handler_string_id => RESET_CONTROLS_ACTION_ID,
            GuiAction::data => array(
                ResetControlsActionData::defs => $defs,
                ResetControlsActionData::initial_sel_ndx => $initial_sel_ndx,
                ResetControlsActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param string|null $archive_id
     * @param array $post_action
     * @return array
     */
    public static function clear_archive_cache($archive_id = null, $post_action = null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_CLEAR_ARCHIVE_CACHE_ACTION_ID,
            GuiAction::data => array(
                PluginClearArchiveCacheActionData::archive_id => $archive_id,
                PluginClearArchiveCacheActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param int|null $erase_count
     * @param string|null $elements
     * @param array|null $post_action
     * @return array
     */
    public static function replace_path($erase_count = null, $elements = null, $post_action = null)
    {
        //hd_print("replace_path: erase_count: $erase_count,  elements: $elements, post_action: " . json_encode($post_action));
        if ($erase_count === null || is_newer_versions() === false) {
            return $post_action;
        }

        return array(
            GuiAction::handler_string_id => PLUGIN_REPLACE_PATH_ACTION_ID,
            GuiAction::data => array(
                PluginReplacePathActionData::erase_count => $erase_count,
                PluginReplacePathActionData::elements => $elements,
                PluginReplacePathActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param array $actions
     * @param int $timer
     * @param array|null $post_action
     * @return array
     */
    public static function change_behaviour($actions, $timer = 0, $post_action = null)
    {
        return array(
            GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
            GuiAction::data => array(
                ChangeBehaviourActionData::actions => $actions,
                ChangeBehaviourActionData::timer => self::timer($timer),
                ChangeBehaviourActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param string $url
     * @param array|null $post_action
     * @return array
     */
    public static function launch_media_url($url, $post_action = null)
    {
        return array(
            GuiAction::handler_string_id => LAUNCH_MEDIA_URL_ACTION_ID,
            GuiAction::data => array(
                LaunchMediaUrlActionData::url => $url,
                LaunchMediaUrlActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param array|null $post_action
     * @return array
     */
    public static function close_and_run($post_action = null)
    {
        return array(
            GuiAction::handler_string_id => CLOSE_AND_RUN_ACTION_ID,
            GuiAction::caption => null,
            GuiAction::data => array(CloseAndRunActionData::post_action => $post_action,),
            GuiAction::params => null,
        );
    }

    /**
     * @param array|null $post_action
     * @return array
     */
    public static function show_main_screen($post_action = null)
    {
        return array(
            GuiAction::handler_string_id => SHOW_MAIN_SCREEN_ACTION_ID,
            GuiAction::data => array(ShowMainScreenActionData::post_action => $post_action,),
        );
    }

    /**
     * @param array $params
     * @return array
     */
    public static function handle_user_input($params)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
            GuiAction::caption => null,
            GuiAction::data => null,
            GuiAction::params => $params,
        );
    }

    /**
     * @param string $text_above
     * @param string|null $text_color
     * @param bool $text_halo
     * @param int $text_y_offset
     * @param array $post_action
     * @return array
     */
    public static function update_info_block(
        $text_above, $text_color = null, $text_halo = false, $text_y_offset = 0,
        $post_action = null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_UPDATE_INFO_BLOCK_ACTION_ID,
            GuiAction::data => array(
                PluginUpdateInfoBlockActionData::text_above => $text_above,
                PluginUpdateInfoBlockActionData::text_color => $text_color,
                PluginUpdateInfoBlockActionData::text_halo => $text_halo,
                PluginUpdateInfoBlockActionData::text_y_offset => $text_y_offset,
                PluginUpdateInfoBlockActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param $channel_id
     * @param $clear
     * @param $day_start_tm_sec
     * @param $programs
     * @param $post_action
     * @return array
     */
    public static function update_epg($channel_id, $clear, $day_start_tm_sec = 0, $programs = null, $post_action = null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_UPDATE_EPG_ACTION_ID,
            GuiAction::data => array(
                PluginUpdateEpgActionData::channel_id => $channel_id,
                PluginUpdateEpgActionData::clear => $clear,
                PluginUpdateEpgActionData::day_start_tm_sec => $day_start_tm_sec,
                PluginUpdateEpgActionData::programs => $programs,
                PluginUpdateEpgActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param array &$comps
     * @param string $image_url
     * @param int $x
     * @param int $y
     * @param int $image_width
     * @param int $image_height
     */
    public static function add_osd_image(&$comps, $image_url, $x, $y, $image_width = 0, $image_height = 0)
    {
        $comps[] = array(
            PluginOsdComponent::image_url => $image_url,
            PluginOsdComponent::x => $x,
            PluginOsdComponent::y => $y,
            PluginOsdComponent::image_width => $image_width,
            PluginOsdComponent::image_height => $image_height
        );
    }

    /**
     * @param array &$comps
     * @param string $text
     * @param int $x
     * @param int $y
     * @param string $text_font_size
     * @param string $text_color
     * @param bool $text_halo
     */
    public static function add_osd_text(&$comps, $text, $x, $y, $text_font_size = PLUGIN_FONT_NORMAL, $text_color = DEF_LABEL_TEXT_COLOR_WHITE, $text_halo = false)
    {
        $comps[] = array(
            PluginOsdComponent::text => $text,
            PluginOsdComponent::x => $x,
            PluginOsdComponent::y => $y,
            PluginOsdComponent::text_font_size => $text_font_size,
            PluginOsdComponent::text_color => $text_color,
            PluginOsdComponent::text_halo => $text_halo
        );
    }

    /**
     * @param $comps
     * @param array $post_action
     * @return array
     */
    public static function update_osd($comps, $post_action = null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_UPDATE_OSD_ACTION_ID,
            GuiAction::data => array(
                PluginUpdateOsdActionData::components => $comps,
                PluginUpdateOsdActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param array|null $settings
     * @param bool $reboot
     * @param bool $restart_gui
     * @param array|null $post_action
     * @return array
     */
    public static function change_settings($settings, $reboot, $restart_gui, $post_action = null)
    {
        return array(
            GuiAction::handler_string_id => CHANGE_SETTINGS_ACTION_ID,
            GuiAction::data => array(
                ChangeSettingsActionData::settings => $settings,
                ChangeSettingsActionData::reboot => $reboot,
                ChangeSettingsActionData::restart_gui => $restart_gui,
                ChangeSettingsActionData::post_action => $post_action,
            ),
        );
    }

    /**
     * @param bool $reboot
     * @return array
     */
    public static function restart($reboot = false)
    {
        if ($reboot !== false) {
            exec('reboot');
        }

        if (defined('CHANGE_SETTINGS_ACTION_ID')) {
            return array(
                GuiAction::handler_string_id => CHANGE_SETTINGS_ACTION_ID,
                GuiAction::data => array(
                    ChangeSettingsActionData::restart_gui => true,
                    ChangeSettingsActionData::post_action => null)
            );
        }

        if (defined('RESTART_ACTION_ID')) {
            return array(
                GuiAction::handler_string_id => RESTART_ACTION_ID,
                GuiAction::data => array(RestartActionData::reboot => false));
        }

        exec('killall shell');
        return array();
    }

    public static function clear_rows_info_cache($post_action=null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_UPDATE_ROWS_INFO_ACTION_ID,
            GuiAction::data => array(
                PluginUpdateRowsInfoActionData::clear_cache => true,
                PluginUpdateRowsInfoActionData::post_action => $post_action,
                ),
        );
    }

    public static function update_rows_info($folder_key, $item_id, $info_defs,
        $bg_url = null, $nl_bg_url = null, $mask_url = null, $playback_urls = null, $post_action = null)
    {
        $info = array(
            PluginRowsInfo::folder_key => $folder_key,
            PluginRowsInfo::item_id => $item_id,
            PluginRowsInfo::info_defs => $info_defs,
            PluginRowsInfo::bg_url => $bg_url,
            PluginRowsInfo::nl_bg_url => $nl_bg_url,
            PluginRowsInfo::mask_url => $mask_url,
            PluginRowsInfo::playback_urls => $playback_urls,
        );

        return array(
            GuiAction::handler_string_id => PLUGIN_UPDATE_ROWS_INFO_ACTION_ID,
            GuiAction::data => array(
                    PluginUpdateRowsInfoActionData::info => $info,
                    PluginUpdateRowsInfoActionData::post_action => $post_action,
                ),
        );
    }
}
