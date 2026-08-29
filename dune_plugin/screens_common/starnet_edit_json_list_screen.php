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
require_once 'lib/user_input_handler_registry.php';

class Starnet_Edit_Json_List_Screen extends Abstract_Preloaded_Regular_Screen
{
    const ID = 'edit_json_list';

    const SCREEN_EDIT_JSON_LIST = 'json_list';
    const ACTION_REMOVE_ITEM_DLG_APPLY = 'remove_item_apply';
    const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';

    const CONTROL_CACHE_TIME = 'cache_time';
    const CONTROL_DOMAIN = 'domain';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map();
    }

    protected function do_get_action_map()
    {
        hd_debug_print(null, true);

        $action_return = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_EDIT_JSON_SETTINGS_DLG, TR::t('edit'));
        $actions[GUI_EVENT_KEY_RETURN] = $action_return;
        $actions[GUI_EVENT_KEY_TOP_MENU] = $action_return;
        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER, TR::t('select'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_CLEAR_CACHE);
        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $selected_id = isset($user_input->selected_media_url) ? MediaURL::decode($user_input->selected_media_url)->id : 0;

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $sel_idx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                $actions[] = Action_Factory::close_and_run();
                if ($this->force_parent_reload && isset($parent_media_url->{PARAM_SOURCE_WINDOW_ID}, $parent_media_url->{PARAM_END_ACTION})) {
                    $this->force_parent_reload = false;
                    $source_window = safe_get_value($parent_media_url, PARAM_SOURCE_WINDOW_ID);
                    $end_action = safe_get_value($parent_media_url, PARAM_END_ACTION);
                    hd_debug_print("Force parent reload: $source_window action: $end_action", true);

                    if ($source_window === Starnet_Entry_Handler::ID) {
                        $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                    } else {
                        $actions[] = User_Input_Handler_Registry::create_screen_action($source_window, $end_action);
                    }
                }

                return Action_Factory::composite($actions);

            case GUI_EVENT_KEY_ENTER:
                $params = $this->plugin->get_config_preset($selected_id);
                $provider = $this->plugin->get_active_provider();
                if ($provider) {
                    $provider_presets = $provider->get_provider_epg_preset_names();
                    if (!in_array($selected_id, $provider_presets) && isset($params[EPG_JSON_PRESET_PRIVATE])) {
                        return Action_Factory::show_title_dialog(TR::t('error'), TR::t('err_private_epg_server'));
                    }
                }

                $this->force_parent_reload = true;
                if ($this->plugin->is_selected_json_source($selected_id)) {
                    $this->plugin->remove_selected_json_source($selected_id);
                } else {
                    $this->plugin->add_selected_json_source($selected_id);
                }
                break;

            case ACTION_EDIT_JSON_SETTINGS_DLG:
                hd_debug_print('item: ' . $selected_id, true);
                return $this->do_edit_settings_dlg($selected_id);

            case ACTION_EDIT_JSON_SETTINGS_DLG_APPLY:
                if (isset($user_input->{self::CONTROL_DOMAIN})) {
                    $this->plugin->set_json_source_domain($selected_id, $user_input->{self::CONTROL_DOMAIN});
                }
                if (isset($user_input->{self::CONTROL_CACHE_TIME})) {
                    $this->plugin->set_json_source_cache($selected_id, $user_input->{self::CONTROL_CACHE_TIME});
                }
                break;

            case ACTION_CLEAR_CACHE:
            case ACTION_CALL_CLEAR_ALL_EPG:
                Epg_Manager_Json::clear_epg_files($user_input->control_id === ACTION_CLEAR_CACHE ? Hashed_Array::hash($selected_id) : null);
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($selected_id);

            case ACTION_CHECK_EPG:
                return $this->do_check_epg_ids(array($selected_id));

            case ACTION_CHECK_SELECTED_EPG:
                return $this->do_check_epg_ids($this->plugin->get_selected_json_sources());
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_idx);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// Protected methods

    /**
     * @param string $id
     * @return array|null
     */
    protected function create_popup_menu($id)
    {
        hd_debug_print(null, true);

        $menu_items = array();
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, GUI_EVENT_KEY_ENTER, TR::t('select_enter'), 'check.png');

        if ($this->plugin->is_channels_loaded()) {
            $menu_items[] = Control_Factory::menu_separator();

            if (Epg_Manager_Json::is_proiptv_epg_preset($this->plugin->get_config_preset($id))) {
                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_CHECK_EPG, TR::t('entry_epg_check_ids'), 'search.png');
            }

            $checked = 0;
            foreach ($this->plugin->get_selected_json_sources() as $selected_source) {
                if (Epg_Manager_Json::is_proiptv_epg_preset_name($selected_source)) {
                    $checked++;
                }
            }
            if ($checked) {
                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_CHECK_SELECTED_EPG, TR::t('entry_epg_selected_check_ids'), 'search.png');
            }
        }

        $menu_items[] = Control_Factory::menu_separator();

        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_CLEAR_CACHE, TR::t('entry_epg_cache_clear_menu'), 'brush.png');
        $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_CALL_CLEAR_ALL_EPG, TR::t('entry_epg_cache_clear_all'), 'brush.png');

        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param array $selected_sources
     * @return array|null
     */
    protected function do_check_epg_ids($selected_sources)
    {
        if (empty($selected_sources)) {
            return null;
        }

        $pl_epg_info = $this->plugin->get_playlist_epg_info();

        $source_names = array();
        $search_epg_id = array();
        $search_aliases = array();
        $found = array();
        foreach ($selected_sources as $source) {
            $config_preset = $this->plugin->get_config_preset($source);
            if (!Epg_Manager_Json::is_proiptv_epg_preset($config_preset)
                || !Epg_Manager_Json::load_channels_info($config_preset)) {
                continue;
            }

            $source_names[] = $source;
            $source_ids = Epg_Manager_Json::get_channels_info($config_preset);
            if (isset($source_ids[COLUMN_EPG_ID])) {
                $search_epg_id = array_merge($search_epg_id, $source_ids[COLUMN_EPG_ID]);
                foreach ($pl_epg_info as $info) {
                    if (in_array($info[COLUMN_EPG_ID], $source_ids[COLUMN_EPG_ID])) {
                        $found[] = $info[COLUMN_CHANNEL_ID];
                    }
                }
            }

            if (isset($source_ids[COLUMN_EPG_ALIASES])) {
                $ids = array_keys($source_ids[COLUMN_EPG_ALIASES]);
                $search_aliases = array_merge($search_aliases, $ids);
                foreach ($pl_epg_info as $info) {
                    if (in_array(to_lower($info[COLUMN_TITLE]), $ids) !== false) {
                        $found[] = $info[COLUMN_CHANNEL_ID];
                    }
                    if (in_array(to_lower($info[COLUMN_TVG_NAME]), $ids) !== false) {
                        $found[] = $info[COLUMN_CHANNEL_ID];
                    }
                }
            }
        }

        $total_channels = count($pl_epg_info);
        $found_channels = count(array_unique($found));
        $pl_epg_ids_cnt = count(array_unique(array_filter(extract_column($pl_epg_info, COLUMN_EPG_ID))));
        $pl_epg_aliases_cnt = count(array_merge(
            array_filter(extract_column($pl_epg_info, COLUMN_TITLE)),
            array_filter(extract_column($pl_epg_info, COLUMN_TVG_NAME))
        ));

        $search_epg_id_cnt = count(array_unique($search_epg_id));
        $search_aliases_cnt = count(array_unique($search_aliases));

        $defs = array();
        $fmt = '<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s</text>';

        Control_Factory::add_smart_label($defs,
            sprintf($fmt, DEF_LABEL_TEXT_COLOR_GOLD, TR::load('channels_total'),
                DEF_LABEL_TEXT_COLOR_WHITE, $total_channels),
            -30
        );
        Control_Factory::add_smart_label($defs,
            sprintf($fmt, DEF_LABEL_TEXT_COLOR_GOLD, TR::load('playlist_epg_id'),
                DEF_LABEL_TEXT_COLOR_WHITE, $pl_epg_ids_cnt),
            -30
        );
        Control_Factory::add_smart_label($defs,
            sprintf($fmt, DEF_LABEL_TEXT_COLOR_GOLD, TR::load('playlist_channel_names'),
                DEF_LABEL_TEXT_COLOR_WHITE, $pl_epg_aliases_cnt),
            -30
        );
        Control_Factory::add_smart_label($defs,
            sprintf($fmt, DEF_LABEL_TEXT_COLOR_GOLD, TR::load('known_search_id'),
                DEF_LABEL_TEXT_COLOR_WHITE, $search_epg_id_cnt),
            -30
        );
        Control_Factory::add_smart_label($defs,
            sprintf($fmt, DEF_LABEL_TEXT_COLOR_GOLD, TR::load('known_aliases'),
                DEF_LABEL_TEXT_COLOR_WHITE, $search_aliases_cnt),
            -30
        );
        Control_Factory::add_smart_label($defs,
            sprintf($fmt, DEF_LABEL_TEXT_COLOR_GOLD, TR::load('found_epg'),
                DEF_LABEL_TEXT_COLOR_WHITE, $found_channels),
            -30
        );
        Control_Factory::add_smart_label($defs,
            sprintf($fmt, DEF_LABEL_TEXT_COLOR_GOLD, TR::load('search_in'),
                DEF_LABEL_TEXT_COLOR_WHITE, implode(', ', $source_names)),
            -30
        );

        Control_Factory::add_vgap($defs, 30);
        Control_Factory::add_ok_button($defs, true);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($defs, TR::t('epg_check_dlg'));
    }

    /**
     * @param string $id
     * @return array|null
     */
    protected function do_edit_settings_dlg($id)
    {
        hd_debug_print(null, true);
        hd_debug_print("ID: $id", true);

        $preset = $this->plugin->get_config_preset($id);
        if (empty($preset)) {
            return null;
        }

        $cache_time = $this->plugin->get_json_source_cache($id);
        $cache_time = empty($cache_time) ? 3 : $cache_time;

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        foreach (array(1, 2, 3, 6, 12, 24, 48, 72, 96, 120, 144, 168) as $hour) {
            $caching_range[$hour] = TR::t('setup_cache_time_h__1', $hour);
        }
        Control_Factory::add_label($defs, '', TR::t('setup_cache_time_epg'), -10);
        Control_Factory::add_combobox($defs, $this, self::CONTROL_CACHE_TIME, '', $cache_time,
            $caching_range, Control_Factory::DLG_CONTROLS_WIDTH);

        if (!empty($preset[EPG_JSON_PRESET_DOMAINS])) {
            $domain = $this->plugin->get_json_source_domain($id);
            $domain = empty($domain) ? reset($preset[EPG_JSON_PRESET_DOMAINS]) : $domain;
            Control_Factory::add_label($defs, '', TR::t('api_domain'), -10);
            Control_Factory::add_combobox($defs, $this, self::CONTROL_DOMAIN, '', $domain,
                $preset[EPG_JSON_PRESET_DOMAINS], Control_Factory::DLG_CONTROLS_WIDTH);
        }

        Control_Factory::add_vgap($defs, 50);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, ACTION_EDIT_JSON_SETTINGS_DLG_APPLY, TR::t('ok'));
        Control_Factory::add_cancel_button($defs);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($defs, TR::t('edit_list_edit_item'));
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $items = array();
        $epg_manager = $this->plugin->get_json_epg_manager();
        if ($epg_manager === null) {
            return $items;
        }

        $provider = $this->plugin->get_active_provider();
        $provider_presets = empty($provider) ? array() : $provider->get_provider_epg_preset_names();
        hd_debug_print('Provider sources: ' . implode(', ', $provider_presets), true);
        $selected_sources = $this->plugin->get_selected_json_sources();
        hd_debug_print('Selected sources: ' . implode(', ', $selected_sources), true);

        $sticker = Control_Factory::create_sticker(get_image_path('star_small.png'), -55, -2);
        foreach ($this->plugin->get_config_presets() as $key => $item) {
            $order_key = array_search($key, $selected_sources);
            if (in_array($key, $provider_presets) !== false) {
                $icon = get_image_path('engine2.png');
            } else if (isset($item[EPG_JSON_PRESET_PRIVATE])) {
                $icon = get_image_path('key.png');
            } else {
                $icon = get_image_path('link.png');
            }

            $files = glob(Epg_Manager_Json::get_cache_dir() . Hashed_Array::hash($key) . '_*.json');
            $total_size = 0;
            foreach ($files as $file) {
                $total_size += filesize($file);
            }

            $domain = $this->plugin->get_json_source_domain($key);
            $cache_time = $this->plugin->get_json_source_cache($key);
            if (empty($domain)) {
                $detailed_info = TR::load('epg_screen_info__3', $cache_time, count($files), format_size($total_size));
            } else {
                $detailed_info = TR::load('epg_screen_info__4', $domain, $cache_time, count($files), format_size($total_size));
            }

            $title = $order_key !== false ? "(" . ($order_key + 1) . ") - $key" : $key;
            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array(PARAM_SCREEN_ID => static::ID, 'id' => $key)),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => ($order_key === false ? null : $sticker),
                    ViewItemParams::icon_path => $icon,
                    ViewItemParams::item_detailed_info => $detailed_info,
                    ViewItemParams::item_detailed_icon_path => $icon,
                ),
            );
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
        if (!$this->plugin->get_xmltv_sources_count(null)) {
            $msg = is_limited_apk()
                ? TR::t('edit_list_add_prompt_apk__3', 100, 300, DEF_LABEL_TEXT_COLOR_YELLOW)
                : TR::t('edit_list_add_prompt__3', 100, 300, DEF_LABEL_TEXT_COLOR_YELLOW);
            $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = $msg;
        }

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
}
