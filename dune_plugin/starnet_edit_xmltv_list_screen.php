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
    const ACTION_EXPORT_FOLDER_SELECTED = 'selected_export_folder';

    const CONTROL_ACTION_SOURCE = 'source';
    const CONTROL_CACHE_TIME = 'cache_time';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $source_id
     * @param array $add_params
     * @return MediaURL
     */
    public static function make_media_url($source_id, $add_params = array())
    {
        return MediaURL::make(array_merge(
            array(PARAM_SCREEN_ID => self::ID,
                PARAM_SOURCE_WINDOW_ID => $source_id,
                PARAM_SOURCE_MEDIA_URL_STR => $source_id,
                PARAM_WINDOW_COUNTER => 1),
            $add_params));
    }

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
        $actions[GUI_EVENT_KEY_PLAY] = User_Input_Handler_Registry::create_action($this, ACTION_INDEX_EPG);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);
        $actions[GUI_EVENT_KEY_INFO] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_INFO);

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
        $sel_idx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if (!$this->force_parent_reload) {
                    return Action_Factory::close_and_run();
                }

                $this->force_parent_reload = false;
                if ($parent_media_url->{PARAM_SOURCE_WINDOW_ID} === Starnet_Entry_Handler::ID) {
                    $target_action = Action_Factory::invalidate_all_folders($plugin_cookies);
                } else {
                    $target_action = User_Input_Handler_Registry::create_screen_action(
                        $parent_media_url->{PARAM_SOURCE_WINDOW_ID},
                        $parent_media_url->{PARAM_END_ACTION});
                }

                return Action_Factory::close_and_run($target_action);

            case GUI_EVENT_KEY_ENTER:
                if ($this->plugin->is_selected_xmltv_id($selected_id)) {
                    $this->plugin->remove_selected_xmltv_id($selected_id);
                } else {
                    $this->plugin->add_selected_xmltv_id($selected_id);
                }

                $this->force_parent_reload = true;
                break;

            case GUI_EVENT_TIMER:
                $epg_manager = $this->plugin->get_epg_manager();
                if ($epg_manager === null) {
                    return null;
                }

                clearstatcache();

                if (!isset($plugin_cookies->ticker)) {
                    $plugin_cookies->ticker = 0;
                }

                $playlist_id = $this->plugin->get_active_playlist_id();
                $res = $epg_manager->import_indexing_log($this->plugin->get_xmltv_sources_hash(XMLTV_SOURCE_ALL, $playlist_id));
                $post_action = Action_Factory::update_regular_folder($this->get_folder_range($parent_media_url, 0, $plugin_cookies),true);

                if ($res === 1) {
                    hd_debug_print("Logs imported. Timer stopped");
                    return Action_Factory::invalidate_all_folders($plugin_cookies, null, $post_action);
                }

                if ($res === 2) {
                    hd_debug_print("No imports. Timer stopped");
                    $last_error = HD::get_last_error($this->plugin->get_xmltv_error_name());
                    if (!empty($last_error)) {
                        return Action_Factory::show_title_dialog(TR::t('err_load_xmltv_source'), null, $last_error);
                    }
                    return null;
                }

                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                return Action_Factory::change_behaviour($actions, 1000, $post_action);

            case GUI_EVENT_KEY_INFO:
                return $this->do_show_xmltv_info($selected_id);

            case ACTION_SETTINGS:
                /** @var Named_Storage $item */
                hd_debug_print("item: " . $selected_id, true);

                $source = XMLTV_SOURCE_PLAYLIST;
                $item = $this->plugin->get_xmltv_source($this->plugin->get_active_playlist_id(), $selected_id);
                if (empty($item)) {
                    $source = XMLTV_SOURCE_EXTERNAL;
                    $item = $this->plugin->get_xmltv_source(null, $selected_id);
                }

                if (!empty($item)) {
                    hd_debug_print("source: $source, item: " . json_encode($item), true);
                    return $this->do_edit_url_dlg($source, $selected_id);
                }
                return null;

            case ACTION_INDEX_EPG:
                $this->plugin->safe_clear_selected_epg_cache($selected_id);
                $this->plugin->run_bg_epg_indexing($selected_id, INDEXING_ALL, true);
                $selected_sources = $this->plugin->get_selected_xmltv_ids();
                if (in_array($selected_id, $selected_sources)) {
                    $this->force_parent_reload = true;
                }
                return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 1000);

            case ACTION_CLEAR_CACHE:
                $this->plugin->safe_clear_selected_epg_cache($selected_id);
                $selected_sources = $this->plugin->get_selected_xmltv_ids();
                if (in_array($selected_id, $selected_sources)) {
                    $this->force_parent_reload = true;
                    $this->plugin->reset_channels_loaded();
                }
                break;

            case ACTION_ITEM_DELETE:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_ITEM_DLG_APPLY);

            case self::ACTION_REMOVE_ITEM_DLG_APPLY:
                hd_debug_print(null, true);
                if ($this->plugin->get_xmltv_source(null, $selected_id) === null) {
                    hd_debug_print("remove xmltv source: $selected_id", true);
                    return Action_Factory::show_error(false, TR::t('edit_list_title_cant_delete'));
                }

                $this->plugin->safe_clear_selected_epg_cache($selected_id);
                $this->plugin->remove_external_xmltv_source($selected_id);
                $this->plugin->cleanup_active_xmltv_source();
                $this->force_parent_reload = true;
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($selected_id);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                if ($this->plugin->get_epg_manager() === null) break;

                foreach ($this->plugin->get_xmltv_sources_hash(XMLTV_SOURCE_EXTERNAL, null) as $hash) {
                    $this->plugin->safe_clear_selected_epg_cache($hash);
                    $this->plugin->remove_external_xmltv_source($hash);
                }
                $this->plugin->cleanup_active_xmltv_source();
                $this->force_parent_reload = true;

                if ($this->plugin->get_xmltv_sources_count(null)) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ADD_URL_DLG:
                return $this->do_edit_url_dlg(XMLTV_SOURCE_EXTERNAL);

            case ACTION_ADD_TO_EXTERNAL_SOURCE:
                $item = $this->plugin->get_xmltv_source($this->plugin->get_active_playlist_id(), $selected_id);
                if (!empty($item)) {
                    unset($item[COLUMN_PLAYLIST_ID]);
                    $this->plugin->set_xmltv_source(null, $item);
                }
                break;

            case ACTION_URL_DLG_APPLY: // handle streaming settings dialog result
                return $this->apply_edit_url_dlg($user_input, $plugin_cookies);

            case ACTION_EXPORT:
                return $this->plugin->show_export_dialog($this, 'xmltv_sources_list.txt');

            case ACTION_EXPORT_APPLY_DLG:
                $media_url = Starnet_Folder_Screen::make_media_url(static::ID,
                    array(
                        Starnet_Folder_Screen::PARAM_CHOOSE_FOLDER => ACTION_FOLDER_SELECTED,
                        Starnet_Folder_Screen::PARAM_ADD_PARAMS => $user_input->{CONTROL_EDIT_NAME},
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                    )
                );
                return Action_Factory::open_folder($media_url->get_media_url_str(), TR::t('select_folder'));

            case ACTION_FOLDER_SELECTED:
                return $this->do_export_xmltv_sources($user_input);

            case ACTION_CHOOSE_FILE:
                $media_url = Starnet_Folder_Screen::make_media_url(static::ID,
                    array(
                        PARAM_EXTENSION => $user_input->{PARAM_EXTENSION},
                        Starnet_Folder_Screen::PARAM_CHOOSE_FILE => ACTION_FILE_SELECTED,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => ($user_input->{PARAM_SELECTED_ACTION} === self::ACTION_FILE_TEXT_LIST) && !is_limited_apk(),
                        Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                    )
                );

                return Action_Factory::open_folder($media_url->get_media_url_str(), TR::t('select_file'));

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
    protected function create_popup_menu($selected_id)
    {
        hd_debug_print(null, true);

        $menu_items = array();
        $menu_items[] = $this->plugin->create_menu_item($this, GUI_EVENT_KEY_INFO, TR::t('xmltv_info_dlg'), "info.png");
        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
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
            array(PARAM_SELECTED_ACTION => self::ACTION_FILE_TEXT_LIST, PARAM_EXTENSION => TEXT_FILE_PATTERN)
        );

        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_EXPORT, TR::t('export_list'));

        // Copy to external
        $item = $this->plugin->get_xmltv_source($this->plugin->get_active_playlist_id(), $selected_id);
        if (!empty($item)) {
            $menu_items[] = $this->plugin->create_menu_item($this,ACTION_ADD_TO_EXTERNAL_SOURCE, TR::t('edit_list_add_to_external_source'), "copy.png");
        }


        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('delete2'), "remove.png");
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

        $param[self::CONTROL_ACTION_SOURCE] = $source;
        if (empty($id)) {
            $window_title = TR::t('edit_list_add_url');
            $item = array();
            $cache_selected = XMLTV_CACHE_AUTO;
            $url = 'http://';
        } else {
            $param[CONTROL_ACTION_EDIT] = $id;
            $window_title = TR::t('edit_list_edit_item');
            $playlist_id = ($source & XMLTV_SOURCE_PLAYLIST) ? $this->plugin->get_active_playlist_id() : null;
            $item = $this->plugin->get_xmltv_source($playlist_id, $id);
            $cache_selected = safe_get_value($item, PARAM_CACHE, XMLTV_CACHE_AUTO);
            $url = safe_get_value($item, PARAM_URI, '');
        }

        if ($source & XMLTV_SOURCE_EXTERNAL) {
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
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, ACTION_URL_DLG_APPLY, TR::t('ok'), 300, $param);
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

        $source = $user_input->{self::CONTROL_ACTION_SOURCE};
        $playlist_id = ($source & XMLTV_SOURCE_PLAYLIST) ? $this->plugin->get_active_playlist_id() : null;
        if (isset($user_input->{CONTROL_ACTION_EDIT})) {
            // edit existing url
            $id = $user_input->{CONTROL_ACTION_EDIT};
            $item = $this->plugin->get_xmltv_source($playlist_id, $id);
        } else {
            $id = '';
            $item[PARAM_TYPE] = PARAM_LINK;
        }

        if (isset($user_input->{CONTROL_URL_PATH})) {
            $item[PARAM_URI] = $user_input->{CONTROL_URL_PATH};
            if (($source & XMLTV_SOURCE_EXTERNAL) && !is_proto_http($item[PARAM_URI])) {
                return Action_Factory::close_and_run(Action_Factory::show_title_dialog(TR::t('err_incorrect_url')));
            }
            $new_id = Hashed_Array::hash($item[PARAM_URI]);
        } else {
            $new_id = $id;
        }

        if (isset($user_input->{CONTROL_EDIT_NAME})) {
            $item[PARAM_NAME] = $user_input->{CONTROL_EDIT_NAME};
            if (empty($item[PARAM_NAME])) {
                $item[PARAM_NAME] = $item[PARAM_URI];
            }
        }

        $item[PARAM_HASH] = $new_id;
        $item[PARAM_CACHE] = $user_input->{self::CONTROL_CACHE_TIME};

        hd_debug_print("Save source ID: $new_id, old ID: $id, params: " . json_encode($item), true);

        if (empty($id)) {
            $this->plugin->safe_clear_selected_epg_cache($new_id);
            $this->plugin->set_xmltv_source(null, $item);
        } else {
            $this->plugin->safe_clear_selected_epg_cache($id);
            if ($id !== $new_id && ($source & XMLTV_SOURCE_EXTERNAL)) {
                $this->plugin->remove_external_xmltv_source($id);
                $this->plugin->set_xmltv_source($playlist_id, $item);
            } else {
                $this->plugin->update_xmltv_source($playlist_id, $item);
            }
        }

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
            $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx));
    }

    protected function selected_text_file($user_input)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});

        hd_debug_print("Choosed file: " . $selected_media_url->{PARAM_FILEPATH}, true);
        $lines = file($selected_media_url->{PARAM_FILEPATH}, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_empty_file'));
        }

        $old_count = $this->plugin->get_xmltv_sources_count(null);

        $new_count = $old_count;
        $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
        foreach ($lines as $line) {
            $line = trim($line);
            hd_debug_print("Load string: '$line'", true);
            /** @var array $m */
            if (preg_match(HTTP_PATTERN, $line, $m)) {
                $hash = Hashed_Array::hash($line);
                $name = $m[2];
                $link = $line;
            } else if (preg_match(PROVIDER_PATTERN, $line, $m)) {
                $hash = Hashed_Array::hash($m[2]);
                $name = $m[1];
                $link = $m[2];
            }

            if (empty($name) || empty($link) || empty($hash)) {
                hd_debug_print("line skipped: '$line'");
                continue;
            }

            if ($this->plugin->get_xmltv_source(null, $hash) !== null) {
                hd_debug_print("already exist: $hash", true);
                continue;
            }

            $new_count++;
            $item = array(
                PARAM_HASH => $hash,
                PARAM_TYPE => PARAM_LINK,
                PARAM_NAME => $name,
                PARAM_URI => $link,
                PARAM_CACHE => XMLTV_CACHE_AUTO
            );

            $this->plugin->set_xmltv_source(null, $item);
            hd_debug_print("import link: '$link' with name '$name'");
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
     * @param object $user_input
     * @return array
     */
    protected function do_export_xmltv_sources($user_input)
    {
        $list_sources = '';
        $sources = $this->plugin->get_external_xmltv_sources();
        foreach ($sources as $source) {
            $name = safe_get_value($source, PARAM_NAME);
            $link = safe_get_value($source, PARAM_URI);
            $list_sources .= "$name@$link" . PHP_EOL;
        }

        if (empty($list_sources)) {
            return Action_Factory::show_title_dialog(TR::t('err_error'));
        }

        $folder_screen = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
        $path = $folder_screen->{PARAM_FILEPATH} . '/' . $folder_screen->{Starnet_Folder_Screen::PARAM_ADD_PARAMS};
        file_put_contents($path, $list_sources);
        return null;
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

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
        $pl_sources = $this->plugin->get_xmltv_sources(XMLTV_SOURCE_PLAYLIST, $this->plugin->get_active_playlist_id());
        $all_sources->add_items($pl_sources);
        $ext_sources = $this->plugin->get_xmltv_sources(XMLTV_SOURCE_EXTERNAL, null);
        $all_sources->add_items($ext_sources);
        hd_debug_print("All XMLTV sources: " . $all_sources, true);

        $selected_sources = $this->plugin->get_selected_xmltv_ids();
        hd_debug_print("Selected sources: " . json_encode($selected_sources), true);
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
            $locked = $epg_manager->is_index_locked($key, INDEXING_ALL);
            if ($locked) {
                $title = file_exists($cached_xmltv_file) ? TR::t('edit_list_title_info__1', $title) : TR::t('edit_list_title_info_download__1', $title);
            } else if (file_exists($cached_xmltv_file)) {
                $check_time_file = filemtime($cached_xmltv_file);
                $dl_date = format_datetime('Y-m-d H:i', $check_time_file);
                $title = TR::t('edit_list_title_info__2', $title, $dl_date);

                $etag = Curl_Wrapper::get_cached_etag($item[PARAM_URI]);
                $info = TR::load('edit_list_cache_suport__1', TR::load(empty($etag) ? 'no' : 'yes'));

                if ($item[PARAM_CACHE] === XMLTV_CACHE_AUTO) {
                    $expired = TR::load('setup_epg_cache_type_auto');
                } else {
                    $max_cache_time = $check_time_file + 3600 * 24 * $item[PARAM_CACHE];
                    $expired = format_datetime('Y-m-d H:i', $max_cache_time);
                }

                $detailed_info = TR::load('edit_list_detail_info__4',
                    $item[PARAM_URI],
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
                    $icon_file = get_image_path($ext_sources->has($key) ? "both_file.png" : "m3u_file.png");
                }
            } else {
                $icon_file = get_image_path("link.png");
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $key)),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => ($order_key === false ? null : $sticker),
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

    /**
     * @param string $id
     * @return array|null
     */
    public function do_show_xmltv_info($id)
    {
        $epg_manager = $this->plugin->get_epg_manager();
        if ($epg_manager === null) {
            return null;
        }

        $cached_xmltv_file = $this->plugin->get_cache_dir() . '/' . "$id.xmltv";
        $locked = $epg_manager->is_index_locked($id, INDEXING_ALL);
        if ($locked || !file_exists($cached_xmltv_file)) {
            return Action_Factory::show_error(false, TR::t('edit_list_xmltv_not_ready'));
        }

        $params = $this->plugin->find_xmltv_source($id);
        if ($params[COLUMN_CACHE] === 'auto') {
            $cache =  TR::t('auto');
        } else {
            $cache =  TR::t('days__1', $params[COLUMN_CACHE]);
        }

        Control_Factory::add_smart_label($defs, null,
            sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s</text>",
                DEF_LABEL_TEXT_COLOR_GOLD, TR::t('name'),
                DEF_LABEL_TEXT_COLOR_WHITE, $params[COLUMN_NAME]),
            -30
        );
        Control_Factory::add_smart_label($defs, null,
            sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s</text>",
                DEF_LABEL_TEXT_COLOR_GOLD, TR::t('url'),
                DEF_LABEL_TEXT_COLOR_WHITE, $params[COLUMN_URI]),
            -30
        );
        Control_Factory::add_smart_label($defs, null,
            sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s</text>",
                DEF_LABEL_TEXT_COLOR_GOLD, TR::t('cache'),
                DEF_LABEL_TEXT_COLOR_WHITE, $cache),
            -30
        );
        Control_Factory::add_smart_label($defs, null,
            sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s</text>",
                DEF_LABEL_TEXT_COLOR_GOLD, TR::t('size'),
                DEF_LABEL_TEXT_COLOR_WHITE, HD::get_file_size($cached_xmltv_file)),
            -30
        );
        Control_Factory::add_smart_label($defs, null,
            sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s</text>",
                DEF_LABEL_TEXT_COLOR_GOLD, TR::t('download_date'),
                DEF_LABEL_TEXT_COLOR_WHITE, date('Y-m-d H:i', filemtime($cached_xmltv_file))),
            -30
        );

        Control_Factory::add_vgap($defs, 30);

        $stat = Epg_Manager_Xmltv::get_stat($cached_xmltv_file);
        if (!empty($stat)) {
            $sec = TR::load('sec');
            Control_Factory::add_smart_label($defs, null,
                sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s $sec</text>",
                    DEF_LABEL_TEXT_COLOR_GOLD, TR::t('download_time'),
                    DEF_LABEL_TEXT_COLOR_WHITE, $stat['download']),
                -30
            );
            Control_Factory::add_smart_label($defs, null,
                sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s $sec</text>",
                    DEF_LABEL_TEXT_COLOR_GOLD, TR::t('unpack_time'),
                    DEF_LABEL_TEXT_COLOR_WHITE, $stat['unpack']),
                -30
            );
            Control_Factory::add_smart_label($defs, null,
                sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s $sec</text>",
                    DEF_LABEL_TEXT_COLOR_GOLD, TR::t('index_channels_time'),
                    DEF_LABEL_TEXT_COLOR_WHITE, $stat['channels']),
                -30
            );
            Control_Factory::add_smart_label($defs, null,
                sprintf("<gap width=0/><text color=%s size=small>%s</text><gap width=20/><text color=%s size=small>%s $sec</text>",
                    DEF_LABEL_TEXT_COLOR_GOLD, TR::t('index_entries_time'),
                    DEF_LABEL_TEXT_COLOR_WHITE, $stat['entries']),
                -30
            );
            Control_Factory::add_vgap($defs, 30);
        }

        $indexes = $epg_manager->get_indexes_info($params);
        foreach ($indexes as $index => $cnt) {
            $cnt = ($cnt !== -1) ? $cnt : TR::t('err_error_no_data');
            Control_Factory::add_smart_label($defs, null,
                sprintf("<gap width=0/><text color=%s size=small>$index:</text><gap width=20/><text color=%s size=small>$cnt</text>",
                    DEF_LABEL_TEXT_COLOR_GOLD, DEF_LABEL_TEXT_COLOR_WHITE),
                -30
            );
        }

        Control_Factory::add_vgap($defs, 30);
        Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 250, true);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('xmltv_info_dlg'), $defs, true, 1000);
    }
}
