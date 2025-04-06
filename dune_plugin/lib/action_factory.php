<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
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

class Action_Factory
{
    /**
     * @param string|null $media_url_str
     * @param string|null $caption
     * @param string|null $id
     * @param int|null $sel_id
     * @param array|null $post_action
     * @param bool $keep_osd_context
     * @return array
     */
    public static function open_folder($media_url_str = null, $caption = null, $id = null, $sel_id = null, $post_action = null, $keep_osd_context = false)
    {
        $action = array(
            GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
            GuiAction::data => array(
                PluginOpenFolderActionData::media_url => $media_url_str,
                PluginOpenFolderActionData::caption => $caption
            )
        );

        if (!is_null($id))
            $action[GuiAction::data][PluginOpenFolderActionData::id] = $id;

        if (!is_null($sel_id))
            $action[GuiAction::data][PluginOpenFolderActionData::sel_id] = $sel_id;

        if (!is_null($post_action))
            $action[GuiAction::data][PluginOpenFolderActionData::post_action] = $post_action;

        if ($keep_osd_context)
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
            $action[GuiAction::params] = array('selected_media_url' => $media_url);
        } else if (is_object($media_url)) {
            $action[GuiAction::data] = array(
                PluginTvPlayActionData::initial_group_id => isset($media_url->group_id) ? $media_url->group_id : null,
                PluginTvPlayActionData::initial_channel_id => isset($media_url->channel_id) ? $media_url->channel_id : null,
                PluginTvPlayActionData::initial_is_favorite => isset($media_url->is_favorite) && $media_url->is_favorite,
                PluginTvPlayActionData::initial_archive_tm => isset($media_url->archive_tm) ? (int)$media_url->archive_tm : -1,
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
            GuiAction::data => array(PluginVodPlayActionData::vod_info => $vod_info)
        );
    }

    /**
     * @param string $group_id
     * @param string $channel_id
     * @return array
     */
    public static function select_channel_id($group_id, $channel_id)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_TV_SELECT_CHANNEL_ACTION_ID,
            GuiAction::data => array(
                PluginTvSelectChannelActionData::group_id => $group_id,
                PluginTvSelectChannelActionData::channel_id => $channel_id,
                PluginTvSelectChannelActionData::is_favorite => false
            )
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
                PluginShowErrorActionData::msg_lines => $msg_lines
            ),
            GuiAction::params => null
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
     * @param array|null $post_action
     * @return array
     */
    public static function close_dialog_and_run($post_action)
    {
        return array(
            GuiAction::handler_string_id => CLOSE_DIALOG_AND_RUN_ACTION_ID,
            GuiAction::caption => null,
            GuiAction::data => array(CloseDialogAndRunActionData::post_action => $post_action),
            GuiAction::params => null
        );
    }

    /**
     * @param string $title
     * @param array|null $post_action
     * @param array|string|null $multiline
     * @param int $preferred_width
     * @return array
     */
    public static function show_title_dialog($title, $post_action = null, $multiline = '', $preferred_width = 0)
    {
        $defs = array();

        $text = '';
        if ($preferred_width === 0) {
            $preferred_width = (int)mb_strlen($title, 'UTF-8') * 40;
            if (!empty($multiline)) {
                if (is_array($multiline)) {
                    $lines = $multiline;
                    $text = implode("\n", $multiline);
                } else {
                    $text = $multiline;
                    $lines = explode("\n", $multiline);
                }
                foreach ($lines as $line) {
                    $px = mb_strlen($line, 'UTF-8') * 40;
                    if ($px > $preferred_width) {
                        $preferred_width = (int)$px;
                    }
                }
            }
        }

        Control_Factory::add_multiline_label($defs, '', $text, 15);
        Control_Factory::add_custom_close_dialog_and_apply_buffon($defs, 'close_button', TR::t('ok'), $post_action, 300);

        return self::show_dialog($title, $defs, false, $preferred_width);
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

        return array(
            GuiAction::handler_string_id => SHOW_DIALOG_ACTION_ID,
            GuiAction::caption => null,
            GuiAction::data => array(
                ShowDialogActionData::title => $title,
                ShowDialogActionData::defs => $defs,
                ShowDialogActionData::close_by_return => $close_by_return,
                ShowDialogActionData::preferred_width => $preferred_width,
                ShowDialogActionData::max_height => $max_height,
                ShowDialogActionData::min_item_title_width => $min_item_title_width,
                ShowDialogActionData::initial_sel_ndx => $initial_sel_ndx,
                ShowDialogActionData::actions => $actions,
                ShowDialogActionData::timer => $timer,
                ShowDialogActionData::params => $dialog_params
            ),
            GuiAction::params => null
        );
    }

    /**
     * Confirmation dialog
     * @return array
     */
    public static function show_confirmation_dialog($title, $handler, $action, $multiline = null, $preferred_width = 0, $add_params = null, $attrs = array())
    {
        $defs = array();

        if ($multiline !== null) {
            Control_Factory::add_multiline_label($defs, '', $multiline, 15);
        }

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, $action, TR::t('yes'), 300, $add_params);
        Control_Factory::add_close_dialog_button($defs, TR::t('no'), 300);

        return self::show_dialog($title, $defs, false, $preferred_width, $attrs);
    }

    /**
     * @param int $status
     * @return array
     */
    public static function status($status)
    {
        return array(
            GuiAction::handler_string_id => STATUS_ACTION_ID,
            GuiAction::caption => null,
            GuiAction::data => array(StatusActionData::status => $status),
            GuiAction::params => null
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
                ShowPopupMenuActionData::selected_menu_item_index => $sel_ndx
            )
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
            GuiAction::data => array(
                PluginUpdateFolderActionData::range => $range,
                PluginUpdateFolderActionData::need_refresh => $need_refresh,
                PluginUpdateFolderActionData::sel_ndx => (int)$sel_ndx
            )
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
                ResetControlsActionData::post_action => $post_action
            )
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
                PluginClearArchiveCacheActionData::post_action => $post_action
            )
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
        if ($erase_count === null) {
            return $post_action;
        }

        return array(
            GuiAction::handler_string_id => PLUGIN_REPLACE_PATH_ACTION_ID,
            GuiAction::data => array(
                PluginReplacePathActionData::erase_count => $erase_count,
                PluginReplacePathActionData::elements => $elements,
                PluginReplacePathActionData::post_action => $post_action
            )
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
                ChangeBehaviourActionData::post_action => $post_action
            )
        );
    }

    public static function edit_list_config($config_id, $title, $all_items, $checked_ids = null, $groups = null,
                                            $options = null, $close_item_params = null, $sel_id = null, $post_action = null)
    {
        if (!defined('EditListConfigActionData::config_id')) {
            return null;
        }

        $arr = array(
            EditListConfigActionData::config_id => $config_id,
            EditListConfigActionData::title => $title,
            EditListConfigActionData::all_items => $all_items,
            EditListConfigActionData::post_action => $post_action,
        );

        if (!is_null($checked_ids)) {
            $arr[EditListConfigActionData::checked_ids] = $checked_ids;
        }

        if (!is_null($groups)) {
            $arr[EditListConfigActionData::groups] = $groups;
        }

        if (!is_null($options)) {
            $arr[EditListConfigActionData::options] = $options;
        }

        if (!is_null($sel_id)) {
            $arr[EditListConfigActionData::sel_id] = $sel_id;
        }

        if (defined('EditListConfigActionData::close_item_params') && !is_null($close_item_params)) {
            // r24?
            $arr[EditListConfigActionData::close_item_params] = $close_item_params;
        }

        return array(GuiAction::handler_string_id => EDIT_LIST_CONFIG_ACTION_ID, GuiAction::data => $arr);
    }

    public static function change_list_config($config_id, $set_default = null, $def_id = null, $ids_to_check = null,
                                              $ids_to_uncheck = null, $ids_to_def = null, $order = null, $post_action = null)
    {
        if (!defined('ChangeListConfigActionData::config_id')) {
            return null;
        }

        $arr = array(
            ChangeListConfigActionData::config_id => $config_id,
            ChangeListConfigActionData::post_action => $post_action,
        );

        if (!is_null($set_default)) {
            $arr[ChangeListConfigActionData::set_default] = $set_default;
        }

        if (!is_null($def_id)) {
            $arr[ChangeListConfigActionData::def_id] = $def_id;
        }

        if (!is_null($ids_to_check)) {
            $arr[ChangeListConfigActionData::ids_to_check] = $ids_to_check;
        }

        if (!is_null($ids_to_uncheck)) {
            $arr[ChangeListConfigActionData::ids_to_uncheck] = $ids_to_uncheck;
        }

        if (!is_null($ids_to_def)) {
            $arr[ChangeListConfigActionData::ids_to_def] = $ids_to_def;
        }

        if (defined('ChangeListConfigActionData::order') && !is_null($order)) {
            // r24?
            $arr[ChangeListConfigActionData::order] = $order;
        }

        return array(GuiAction::handler_string_id => CHANGE_LIST_CONFIG_ACTION_ID, GuiAction::data => $arr,);
    }

    public static function change_list_config_reset_to_default($config_id, $post_action)
    {
        return self::change_list_config($config_id, true, null, null, null, null, null, $post_action);
    }

    public static function change_list_config_replace_order($config_id, $order, $post_action)
    {
        return self::change_list_config($config_id, false, null, null, null, null, $order, $post_action);
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
                LaunchMediaUrlActionData::post_action => $post_action
            )
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
            GuiAction::params => null
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
            GuiAction::data => array(ShowMainScreenActionData::post_action => $post_action)
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
            GuiAction::params => $params
        );
    }

    /**
     * Used to invalidate classic folders and NewUI
     *
     * @param object $plugin_cookies
     * @param array|null $post_action
     * @return array
     */
    public static function invalidate_all_folders($plugin_cookies, $media_urls = null, $post_action = null)
    {
        $media_urls = is_array($media_urls) ? $media_urls : array();
        $post_action = self::invalidate_epfs_folders($plugin_cookies, $post_action);
        return self::invalidate_folders($media_urls, $post_action, true);
    }

    /**
     * Used to invalidate only NewUI
     *
     * @param object $plugin_cookies
     * @param array|null $post_action
     * @return array
     */
    public static function invalidate_epfs_folders($plugin_cookies, $post_action = null)
    {
        if (Starnet_Epfs_Handler::$enabled) {
            $post_action = self::invalidate_folders(array(Starnet_Epfs_Handler::$epf_id), $post_action);
        }

        Starnet_Epfs_Handler::update_epfs_file($plugin_cookies);
        return $post_action;
    }


    /**
     * Used to invalidate only classic folders
     *
     * @param array $media_urls
     * @param array $post_action
     * @return array
     */
    public static function invalidate_folders($media_urls, $post_action = null, $all_except = false)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_INVALIDATE_FOLDERS_ACTION_ID,
            GuiAction::data => array(
                PluginInvalidateFoldersActionData::media_urls => $media_urls,
                PluginInvalidateFoldersActionData::post_action => $post_action,
                PluginInvalidateFoldersActionData::all_except => $all_except
            )
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
                PluginUpdateInfoBlockActionData::post_action => $post_action
            )
        );
    }

    /**
     * Update cached epg info
     *
     * @param string $channel_id
     * @param bool $clear
     * @param int $day_start_tm_sec
     * @param string $programs
     * @param array $post_action
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
                PluginUpdateEpgActionData::post_action => $post_action
            )
        );
    }

    /**
     * @param array $post_action
     * @return array
     */
    public static function update_tv_info($post_action = null)
    {
        return array(
            GuiAction::handler_string_id => UPDATE_TV_INFO_ACTION_ID,
            GuiAction::data => array(UpdateTvInfoActionData::post_action => $post_action)
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
     * @param array $comps
     * @param array $post_action
     * @return array
     */
    public static function update_osd($comps, $post_action = null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_UPDATE_OSD_ACTION_ID,
            GuiAction::data => array(
                PluginUpdateOsdActionData::components => $comps,
                PluginUpdateOsdActionData::post_action => $post_action
            )
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
                ChangeSettingsActionData::post_action => $post_action
            )
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

        return array(
            GuiAction::handler_string_id => CHANGE_SETTINGS_ACTION_ID,
            GuiAction::data => array(
                ChangeSettingsActionData::restart_gui => true,
                ChangeSettingsActionData::post_action => null
            )
        );
    }

    public static function clear_rows_info_cache($post_action = null)
    {
        return array(
            GuiAction::handler_string_id => PLUGIN_UPDATE_ROWS_INFO_ACTION_ID,
            GuiAction::data => array(
                PluginUpdateRowsInfoActionData::clear_cache => true,
                PluginUpdateRowsInfoActionData::post_action => $post_action
            )
        );
    }

    public static function update_rows_info($folder_key, $item_id, $info_defs,
                                            $bg_url = null, $nl_bg_url = null, $mask_url = null, $playback_urls = array(), $post_action = null)
    {
        $info = array(
            PluginRowsInfo::folder_key => $folder_key,
            PluginRowsInfo::item_id => $item_id,
            PluginRowsInfo::info_defs => $info_defs,
            PluginRowsInfo::bg_url => $bg_url,
            PluginRowsInfo::nl_bg_url => $nl_bg_url,
            PluginRowsInfo::mask_url => $mask_url,
            PluginRowsInfo::playback_urls => $playback_urls
        );

        return array(
            GuiAction::handler_string_id => PLUGIN_UPDATE_ROWS_INFO_ACTION_ID,
            GuiAction::data => array(
                PluginUpdateRowsInfoActionData::info => $info,
                PluginUpdateRowsInfoActionData::post_action => $post_action
            )
        );
    }
}
