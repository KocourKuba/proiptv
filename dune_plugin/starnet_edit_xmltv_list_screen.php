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

class Starnet_Edit_Xmltv_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'edit_xmltv_list';

    const SCREEN_EDIT_XMLTV_LIST = 'xmltv_list';
    const ACTION_FILE_TEXT_LIST = 'text_list_file';

    const ACTION_REMOVE_ITEM_DLG_APPLY = 'remove_item_apply';
    const ACTION_CHOOSE_FOLDER = 'choose_folder';
    const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';

    const CONTROL_CACHE_TIME = 'cache_time';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $action_return = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $action_select = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER, TR::t('select'));

        $actions = array();
        $actions[GUI_EVENT_KEY_B_GREEN] = $action_select;
        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS, TR::t('edit'));

        $actions[GUI_EVENT_KEY_RETURN] = $action_return;
        $actions[GUI_EVENT_KEY_TOP_MENU] = $action_return;
        $actions[GUI_EVENT_KEY_ENTER] = $action_select;
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $selected_id = isset($user_input->selected_media_url) ? MediaURL::decode($user_input->selected_media_url)->id : 0;

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;
        $sel_idx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if (!$this->force_parent_reload) {
                    return Action_Factory::close_and_run();
                }

                $this->force_parent_reload = false;
                return Action_Factory::invalidate_folders(
                    array($parent_media_url->source_media_url_str),
                    Action_Factory::close_and_run(
                        User_Input_Handler_Registry::create_action_screen(
                            $parent_media_url->source_window_id,
                            $parent_media_url->end_action,
                            null,
                            array(ACTION_RELOAD_SOURCE => $edit_list)
                        )
                    )
                );

            case GUI_EVENT_KEY_ENTER:
                $selected_sources = $this->plugin->get_selected_xmltv_sources();
                $offset = array_search($selected_id, $selected_sources);
                if ($offset !== false) {
                    hd_debug_print("Removed Source: $selected_id", true);
                    array_splice($selected_sources, $offset, 1);
                } else if ($this->plugin->get_xmltv_sources(XMLTV_SOURCE_ALL)->has($selected_id)) {
                    hd_debug_print("Added Source: $selected_id", true);
                    $selected_sources[] = $selected_id;
                }
                hd_debug_print("Updated Selected Sources: " . json_encode($selected_sources), true);

                $this->force_parent_reload = true;
                $this->plugin->set_selected_xmltv_sources($selected_sources);
                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies);

            case GUI_EVENT_TIMER:
                $epg_manager = $this->plugin->get_epg_manager();
                if ($epg_manager === null) {
                    return null;
                }

                clearstatcache();

                if (!isset($plugin_cookies->ticker)) {
                    $plugin_cookies->ticker = 0;
                }
                $res = $epg_manager->import_indexing_log($this->plugin->get_xmltv_sources_hash(XMLTV_SOURCE_ALL));
                $post_action = Action_Factory::update_regular_folder($this->get_folder_range($parent_media_url, 0, $plugin_cookies),true);

                if ($res !== false) {
                    hd_debug_print("Return post action. Timer stopped");
                    return $post_action;
                }

                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                return Action_Factory::change_behaviour($actions, 1000, $post_action);

            case ACTION_SETTINGS:
                /** @var Named_Storage $item */
                hd_debug_print("item: " . $selected_id, true);

                $source = XMLTV_SOURCE_EXTERNAL;
                $item = $this->plugin->get_xmltv_source(XMLTV_SOURCE_EXTERNAL, $selected_id);
                if (empty($item)) {
                    $source = XMLTV_SOURCE_PLAYLIST;
                    $item = $this->plugin->get_xmltv_source(XMLTV_SOURCE_PLAYLIST, $selected_id);
                }

                if (!empty($item)) {
                    hd_debug_print("source: $source - item: " . json_encode($item), true);
                    if ($item[PARAM_TYPE] === PARAM_LINK && isset($item[PARAM_URI]) && is_proto_http($item[PARAM_URI])) {
                        return $this->do_edit_url_dlg($source, $selected_id);
                    }
                }
                return null;

            case ACTION_INDEX_EPG:
                $this->plugin->run_bg_epg_indexing($selected_id);
                $selected_sources = $this->plugin->get_selected_xmltv_sources();
                if (in_array($selected_id, $selected_sources)) {
                    $this->force_parent_reload = true;
                }
                return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 500);

            case ACTION_CLEAR_CACHE:
                $this->plugin->safe_clear_selected_epg_cache($selected_id);
                $selected_sources = $this->plugin->get_selected_xmltv_sources();
                if (in_array($selected_id, $selected_sources)) {
                    $this->force_parent_reload = true;
                }
                break;

            case ACTION_ITEM_DELETE:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_ITEM_DLG_APPLY);

            case self::ACTION_REMOVE_ITEM_DLG_APPLY:
                hd_debug_print(null, true);

                hd_debug_print("edit_list: $parent_media_url->edit_list", true);
                if ($this->plugin->get_xmltv_source(XMLTV_SOURCE_EXTERNAL, $selected_id) === null) {
                    hd_debug_print("remove xmltv source: $selected_id", true);
                    return Action_Factory::show_error(false, TR::t('edit_list_title_cant_delete'));
                }

                $this->plugin->safe_clear_selected_epg_cache($selected_id);
                $selected_sources = $this->plugin->get_selected_xmltv_sources();
                $offset = array_search($selected_id, $selected_sources);
                if ($offset !== false) {
                    $selected_sources = array_splice($selected_sources, $offset, 1);
                    $this->plugin->set_selected_xmltv_sources($selected_sources);
                    $this->force_parent_reload = true;
                }
                $this->plugin->remove_xmltv_source(XMLTV_SOURCE_EXTERNAL, $selected_id);
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu();

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                if ($this->plugin->get_epg_manager() !== null) {
                    foreach ($this->plugin->get_xmltv_sources_hash(XMLTV_SOURCE_EXTERNAL) as $hash) {
                        $this->plugin->safe_clear_selected_epg_cache($hash);
                    }
                }
                $this->plugin->set_selected_xmltv_sources(array());
                foreach ($this->plugin->get_xmltv_sources(XMLTV_SOURCE_EXTERNAL) as $source) {
                    $this->plugin->safe_clear_selected_epg_cache($source);
                    $this->plugin->remove_xmltv_source(XMLTV_SOURCE_EXTERNAL, $source);
                }

                $this->force_parent_reload = true;

                if ($this->plugin->get_xmltv_sources_count(XMLTV_SOURCE_EXTERNAL) !== 0) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ADD_URL_DLG:
                return $this->do_edit_url_dlg(XMLTV_SOURCE_EXTERNAL, $edit_list);

            case ACTION_URL_DLG_APPLY: // handle streaming settings dialog result
                return $this->apply_edit_url_dlg($user_input, $plugin_cookies);

            case ACTION_CHOOSE_FILE:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => $user_input->selected_action,
                        'extension' => $user_input->extension,
                        'allow_network' => ($user_input->selected_action === self::ACTION_FILE_TEXT_LIST) && !is_limited_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );

                return Action_Factory::open_folder($media_url_str, TR::t('select_file'));

            case ACTION_FILE_SELECTED:
                hd_debug_print(null, true);
                return $this->selected_text_file($user_input);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_idx);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @return array|null
     */
    protected function create_popup_menu()
    {
        hd_debug_print(null, true);

        $menu_items = array();
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_INDEX_EPG, TR::t('entry_index_epg'), 'settings.png');
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_CLEAR_CACHE, TR::t('entry_epg_cache_clear'), 'brush.png');
        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

        // Add URL
        $menu_items[] = $this->plugin->create_menu_item($this,ACTION_ADD_URL_DLG, TR::t('edit_list_add_url'), "link.png");

        // Add list file
        $menu_items[] = $this->plugin->create_menu_item($this,
            ACTION_CHOOSE_FILE,
            TR::t('edit_list_import_list'),
            "text_file.png",
            array('selected_action' => self::ACTION_FILE_TEXT_LIST, 'extension' => 'txt|lst')
        );

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('delete'), "remove.png");
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear'), "brush.png");

        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param string $source
     * @param string $id
     * @return array|null
     */
    protected function do_edit_url_dlg($source, $id = '')
    {
        hd_debug_print(null, true);
        hd_debug_print("ID: $id, Source: $source", true);
        $defs = array();


        Control_Factory::add_vgap($defs, 20);

        $param[CONTROL_ACTION_SOURCE] = $source;
        if (empty($id)) {
            $window_title = TR::t('edit_list_add_url');
            $item = array();
            $cache_selected = XMLTV_CACHE_AUTO;
            $url = 'http://';
        } else {
            $param[CONTROL_ACTION_EDIT] = CONTROL_EDIT_ITEM;
            $window_title = TR::t('edit_list_edit_item');
            $item = $this->plugin->get_xmltv_source($source, $id);
            $cache_selected = safe_get_value($item, PARAM_CACHE, XMLTV_CACHE_AUTO);
            $url = safe_get_value($item, PARAM_URI, '');
        }

        if ($source === XMLTV_SOURCE_EXTERNAL) {
            $name = safe_get_value($item, PARAM_NAME);

            Control_Factory::add_label($defs, '', TR::t('name'), -10);
            Control_Factory::add_text_field($defs, $this, null, CONTROL_EDIT_NAME, '',
                $name, false, false, false, true, self::DLG_CONTROLS_WIDTH);

            Control_Factory::add_label($defs, '', TR::t('url'), -10);
            Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, '',
                $url, false, false, false, true, self::DLG_CONTROLS_WIDTH);
        }

        foreach (array(XMLTV_CACHE_AUTO, 0.25, 0.5, 1, 2, 3, 4, 5, 6, 7) as $value) {
            $opts[$value] = $value !== XMLTV_CACHE_AUTO ? $value : TR::t('auto');
        }
        Control_Factory::add_label($defs, '', TR::t('entry_epg_cache_time'), -10);
        Control_Factory::add_combobox($defs, $this, null, self::CONTROL_CACHE_TIME, '',
            $cache_selected, $opts, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, $param,ACTION_URL_DLG_APPLY, TR::t('ok'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($window_title, $defs, true);
    }

    /**
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    protected function apply_edit_url_dlg($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $name = safe_get_member($user_input, CONTROL_EDIT_NAME, '');
        $url = safe_get_member($user_input, CONTROL_URL_PATH, '');

        if (empty($name)) {
            $name = $url;
        }

        $source = $user_input->{CONTROL_ACTION_SOURCE};
        if (isset($user_input->{CONTROL_ACTION_EDIT}, $user_input->selected_media_url)) {
            // edit existing url
            $id = MediaURL::decode($user_input->selected_media_url)->id;
            $item = $this->plugin->get_xmltv_source($source, $id);
        } else {
            $id = Hashed_Array::hash($url);
            $item = array(
                PARAM_TYPE => PARAM_LINK,
                PARAM_NAME => $name,
                PARAM_URI => $url,
                PARAM_HASH => $id
            );
        }

        $item[PARAM_CACHE] = $user_input->{self::CONTROL_CACHE_TIME};

        if ($source === XMLTV_SOURCE_EXTERNAL && !is_proto_http($url)) {
            return Action_Factory::show_title_dialog(TR::t('err_incorrect_url'));
        }

        $this->plugin->set_xmltv_source($source, $item);
        $this->plugin->safe_clear_selected_epg_cache($id);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
            $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx));
    }

    protected function selected_text_file($user_input)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_data);

        hd_debug_print("Choosed file: $selected_media_url->filepath", true);
        $lines = file($selected_media_url->filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_empty_file'));
        }

        $old_count = $this->plugin->get_xmltv_sources_count(XMLTV_SOURCE_EXTERNAL);

        $new_count = $old_count;
        $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
        foreach ($lines as $line) {
            $line = trim($line);
            hd_debug_print("Load string: '$line'", true);
            $hash = Hashed_Array::hash($line);
            if (preg_match(HTTP_PATTERN, $line, $m)) {
                if ($this->plugin->get_xmltv_source(XMLTV_SOURCE_EXTERNAL, $hash) !== null) {
                    hd_debug_print("already exist: $hash", true);
                } else {
                    $new_count++;
                    $item = array(
                        PARAM_HASH => $hash,
                        PARAM_TYPE => PARAM_LINK,
                        PARAM_NAME => $m[2],
                        PARAM_URI => $line,
                        PARAM_CACHE => XMLTV_CACHE_AUTO
                    );
                    $this->plugin->set_xmltv_source(XMLTV_SOURCE_EXTERNAL, $item);
                    hd_debug_print("import link: '$line'");
                }
            } else {
                hd_debug_print("line skipped: '$line'");
            }
        }

        if ($old_count === $new_count) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $new_count - $old_count, count($lines)),
            Action_Factory::close_and_run(
                Action_Factory::open_folder($parent_media_url->get_media_url_str(), TR::t('setup_edit_xmltv_list'))
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        if (++$plugin_cookies->ticker > 3) {
            $plugin_cookies->ticker = 1;
        }

        $items = array();
        $epg_manager = $this->plugin->get_epg_manager();
        if ($epg_manager === null) {
            return $items;
        }

        $sticker = Control_Factory::create_sticker(get_image_path('star_small.png'), -55, -2);
        $all_sources = new Hashed_Array();
        $pl_sources = $this->plugin->get_xmltv_sources(XMLTV_SOURCE_PLAYLIST);
        $all_sources->add_items($pl_sources);
        $all_sources->add_items($this->plugin->get_xmltv_sources(XMLTV_SOURCE_EXTERNAL));

        $selected_sources = $this->plugin->get_selected_xmltv_sources();
        foreach ($all_sources as $key => $item) {
            $detailed_info = '';
            $order_key = false;
            $title = empty($item[PARAM_NAME]) ? $item[PARAM_URI] : $item[PARAM_NAME];
            if (empty($title)) {
                $title = "Unrecognized or bad xmltv entry";
            } else {
                $order_key = array_search($key, $selected_sources);
                $title = $order_key !== false ? "(" . ($order_key + 1) .  ") - $title" : $title;
            }

            $cached_xmltv_file = $this->plugin->get_cache_dir() . '/' . "$key.xmltv";
            $locked = $epg_manager->is_index_locked($key);
            if ($locked) {
                $title = file_exists($cached_xmltv_file) ? TR::t('edit_list_title_info__1', $title) : TR::t('edit_list_title_info_download__1', $title);
            } else if (file_exists($cached_xmltv_file)) {
                $size = HD::get_file_size($cached_xmltv_file);
                $check_time_file = filemtime($cached_xmltv_file);
                $dl_date = date("d.m H:i", $check_time_file);
                $title = TR::t('edit_list_title_info__2', $title, $dl_date);
                $info = '';
                foreach ($epg_manager->get_indexes_info($key) as $index => $cnt) {
                    $cnt = ($cnt !== -1) ? $cnt : TR::load('err_error_no_data');
                    $info .= "$index: $cnt|";
                }

                $etag = $epg_manager->get_curl_wrapper()->get_cached_etag($key);
                $info .= TR::load('edit_list_cache_suport__1',
                    empty($etag) ? TR::load('no') : TR::load('yes'));

                if ($item[PARAM_CACHE] === XMLTV_CACHE_AUTO) {
                    $expired = TR::load('setup_epg_cache_type_auto');
                } else {
                    $max_cache_time = $check_time_file + 3600 * 24 * $item[PARAM_CACHE];
                    $expired = date("d.m H:i", $max_cache_time);
                }

                $detailed_info = TR::load('edit_list_detail_info__5',
                    $item[PARAM_URI],
                    $size,
                    $dl_date,
                    $expired,
                    $info
                );
            }

            if (empty($detailed_info)) {
                if (isset($item[PARAM_URI])) {
                    $detailed_info = TR::t('edit_list_detail_info__2', $item[PARAM_URI], $item[PARAM_CACHE]);
                } else {
                    $detailed_info = $item[PARAM_NAME];
                }
            }

            if ($locked) {
                $icon_file = get_image_path("refresh$plugin_cookies->ticker.png");
                hd_debug_print("icon: $icon_file");
            } else if ($pl_sources->has($key)) {
                if (safe_get_value($item, PARAM_TYPE) === PARAM_CONF) {
                    $icon_file = get_image_path("config.png");
                } else {
                    $icon_file = get_image_path("m3u_file.png");
                }
            } else {
                $icon_file = get_image_path("link.png");
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $key)),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => ($order_key !== false ? $sticker: null),
                    ViewItemParams::icon_path => $icon_file,
                    ViewItemParams::item_detailed_info => $detailed_info,
                    ViewItemParams::item_detailed_icon_path => $icon_file,
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
        if ($this->plugin->get_xmltv_sources_count(XMLTV_SOURCE_EXTERNAL) === 0) {
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
    public function get_timer(MediaURL $media_url, $plugin_cookies)
    {
        return Action_Factory::timer(100);
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
