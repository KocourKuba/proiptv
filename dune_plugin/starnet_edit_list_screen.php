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

class Starnet_Edit_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'edit_list';

    const SCREEN_EDIT_PLAYLIST = 'playlist';
    const SCREEN_EDIT_EPG_LIST = 'epg_list';
    const SCREEN_EDIT_GROUPS = 'groups';
    const SCREEN_EDIT_CHANNELS = 'channels';

    const ACTION_FILE_PLAYLIST = 'play_list_file';
    const ACTION_FILE_XMLTV = 'xmltv_file';
    const ACTION_FILE_TEXT_LIST = 'text_list_file';

    const ACTION_CLEAR_APPLY = 'clear_apply';
    const ACTION_REMOVE_ITEM_DLG_APPLY = 'remove_item_apply';
    const ACTION_CHOOSE_FOLDER = 'choose_folder';
    const ACTION_CHOOSE_FILE = 'choose_file';
    const ACTION_ADD_URL_DLG = 'add_url_dialog';
    const ACTION_URL_DLG_APPLY = 'url_dlg_apply';
    const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';
    const ITEM_SET_NAME = 'set_name';
    const ITEM_EDIT = 'edit';

    const ACTION_ADD_PROVIDER_POPUP = 'add_provider';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $actions = array();
        $is_empty = true;
        if ($media_url->edit_list === self::SCREEN_EDIT_GROUPS) {
            $is_empty = $this->plugin->tv->get_disabled_group_ids()->size() === 0;
        } else if ($media_url->edit_list === self::SCREEN_EDIT_CHANNELS) {
            $is_empty = $this->plugin->tv->get_disabled_channel_ids()->size() === 0;
        } else if ($media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
            $is_empty = $this->plugin->get_playlists()->size() === 0;
        } else if ($media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
            $is_empty = $this->plugin->get_ext_xmltv_sources()->size() === 0;
        }

        if (!$is_empty) {
            if (isset($media_url->allow_order)) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            } else {
                $caption = $media_url->edit_list === self::SCREEN_EDIT_PLAYLIST ? TR::t('change_playlist') : TR::t('change_epg_source');
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_SET_CURRENT, $caption);
            }

            $hidden = ($media_url->edit_list === self::SCREEN_EDIT_GROUPS || $media_url->edit_list === self::SCREEN_EDIT_CHANNELS);
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this,
                ACTION_ITEM_DELETE,
                $hidden ? TR::t('restore') : TR::t('delete'));
        }

        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, TR::t('add'));

        $action_return = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_RETURN] = $action_return;
        $actions[GUI_EVENT_KEY_TOP_MENU] = $action_return;
        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);
        $actions[GUI_EVENT_KEY_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                $reload = $this->set_no_changes();
                hd_debug_print("Need reload: " . var_export($reload, true), true);
                if ($reload) {
                    $this->plugin->set_dirty(true, $parent_media_url->save_data);
                }

                $this->plugin->set_postpone_save(false, $parent_media_url->save_data);

                $post_action = User_Input_Handler_Registry::create_action_screen(
                    $parent_media_url->source_window_id,
                    $reload ? $parent_media_url->end_action : $parent_media_url->cancel_action
                );

                return Action_Factory::invalidate_folders(
                    $reload ? array($parent_media_url->source_media_url_str) : array(),
                    Action_Factory::close_and_run($post_action)
                );

            case GUI_EVENT_KEY_STOP:
                $this->force_save($user_input);
                break;

            case GUI_EVENT_KEY_ENTER:
                if ($edit_list !== self::SCREEN_EDIT_PLAYLIST && $edit_list !== self::SCREEN_EDIT_EPG_LIST) break;

                $this->force_save($user_input);

                $id = MediaURL::decode($user_input->selected_media_url)->id;
                $selected_media_url = MediaURL::decode($user_input->selected_media_url);

                if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    $item = $this->plugin->get_playlists()->get($id);
                } else {
                    $item = $this->plugin->get_ext_xmltv_sources()->get($id);
                }

                hd_debug_print("item: " . $item, true);
                if (($item->type === PARAM_LINK || empty($item->type))
                    && isset($item->params[PARAM_URI])
                    && preg_match(HTTP_PATTERN, $item->params[PARAM_URI])) {
                    return $this->do_edit_url_dlg($edit_list, $selected_media_url->id);
                }

                if ($item->type === PARAM_PROVIDER) {
                    return $this->plugin->do_edit_provider_dlg($this, $item->params[PARAM_PROVIDER], $selected_media_url->id);
                }
                return null;

            case ACTION_SET_CURRENT:
                $id = MediaURL::decode($user_input->selected_media_url)->id;
                if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    $this->plugin->set_active_playlist_key($id);
                } else if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    if ($id === $this->plugin->get_active_xmltv_source_key()) {
                        $this->plugin->set_active_xmltv_source_key(null);
                    } else {
                        $this->plugin->set_active_xmltv_source_key($id);
                    }
                } else {
                    return null;
                }

                $this->force_save($user_input);
                if ($this->plugin->tv->reload_channels($plugin_cookies) === 0) {
                    return Action_Factory::invalidate_all_folders($plugin_cookies,
                        Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
                }

                break;

            case ACTION_ITEM_UP:
                $selected_media_url = MediaURL::decode($user_input->selected_media_url);
                if (!$this->get_order($edit_list)->arrange_item($selected_media_url->id, Ordered_Array::UP)) {
                    return null;
                }

                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }

                $this->set_changes($parent_media_url->save_data);
                break;

            case ACTION_ITEM_DOWN:
                $selected_media_url = MediaURL::decode($user_input->selected_media_url);
                $order = $this->get_order($edit_list);
                if (!$order->arrange_item($selected_media_url->id, Ordered_Array::DOWN)) {
                    return null;
                }

                $groups_cnt = $order->size();
                $user_input->sel_ndx++;
                if ($user_input->sel_ndx >= $groups_cnt) {
                    $user_input->sel_ndx = $groups_cnt - 1;
                }

                $this->set_changes($parent_media_url->save_data);
                break;

            case ACTION_ITEM_DELETE:
                $item = MediaURL::decode($user_input->selected_media_url)->id;

                switch ($edit_list) {
                    case self::SCREEN_EDIT_PLAYLIST:
                    case self::SCREEN_EDIT_EPG_LIST:
                        return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_ITEM_DLG_APPLY);

                    case self::SCREEN_EDIT_CHANNELS:
                        $channel = $this->plugin->tv->get_channel($item);
                        $force_return = $this->plugin->tv->get_disabled_channel_ids()->size() === 0;
                        if (!is_null($channel)) {
                            hd_debug_print("restore channel: {$channel->get_title()} ({$channel->get_id()})", true);
                            $channel->set_disabled(false);
                        }
                        break;

                    case self::SCREEN_EDIT_GROUPS:
                        $group = $this->plugin->tv->get_group($item);
                        $force_return = $this->plugin->tv->get_disabled_group_ids()->size() === 0;
                        if (!is_null($group)) {
                            hd_debug_print("restore group: " . $group->get_id(), true);
                            $group->set_disabled(false);
                        }
                        break;

                    default:
                        hd_debug_print("unknown edit list");
                        return null;
                }

                $this->set_changes($parent_media_url->save_data);
                if ($force_return) {
                    return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                }
                break;

            case self::ACTION_REMOVE_ITEM_DLG_APPLY:
                hd_debug_print(null, true);

                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                $selected_media_url = MediaURL::decode($user_input->selected_media_url);
                $id = $selected_media_url->id;
                hd_debug_print("edit_list: $parent_media_url->edit_list", true);
                if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    hd_debug_print("remove xmltv source: $id", true);
                    if (!$this->plugin->get_ext_xmltv_sources()->has($id)) {
                        return Action_Factory::show_error(false, TR::t('edit_list_title_cant_delete'));
                    }
                    $this->plugin->get_epg_manager()->clear_epg_files($id);
                    $this->plugin->get_ext_xmltv_sources()->erase($id);
                } else if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    hd_debug_print("remove playlist settings: $id", true);
                    $this->plugin->remove_settings($id);
                    $this->plugin->get_playlists()->erase($id);
                }
                $this->set_changes($parent_media_url->save_data);

                return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
                    $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx));

            case ACTION_ITEMS_SORT:
                $this->get_order($edit_list)->sort_order();
                $this->set_changes($parent_media_url->save_data);
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($user_input);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, self::ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case self::ACTION_CONFIRM_CLEAR_DLG_APPLY:
                switch ($edit_list) {
                    case self::SCREEN_EDIT_EPG_LIST:
                        foreach ($this->plugin->get_ext_xmltv_sources() as $key) {
                            $this->plugin->get_epg_manager()->clear_epg_files($key);
                        }
                        $this->plugin->get_ext_xmltv_sources()->clear();
                        break;

                    case self::SCREEN_EDIT_PLAYLIST:
                        foreach ($this->plugin->get_playlists()->get_keys() as $key) {
                            $this->plugin->remove_settings($key);
                        }
                        $this->plugin->get_playlists()->clear();
                        break;

                    case self::SCREEN_EDIT_CHANNELS:
                        $group = $this->plugin->tv->get_any_group($parent_media_url->group_id);
                        if (is_null($group)) break;

                        /** @var Channel $channel */
                        foreach ($group->get_group_disabled_channels() as $channel) {
                            hd_debug_print("restore channel: {$channel->get_title()} ({$channel->get_id()})", true);
                            $channel->set_disabled(false);
                        }
                        break;

                    case self::SCREEN_EDIT_GROUPS:
                        /** @var Group $group */
                        foreach ($this->plugin->tv->get_groups($this->plugin->tv->get_disabled_group_ids()) as $group) {
                            hd_debug_print("restore group: " . $group->get_id(), true);
                            $group->set_disabled(false);
                        }
                        break;

                    default:
                        return null;
                }

                $this->set_changes($parent_media_url->save_data);

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case self::ACTION_ADD_URL_DLG:
                return $this->do_edit_url_dlg();

            case self::ACTION_URL_DLG_APPLY: // handle streaming settings dialog result
                return $this->apply_edit_url_dlg($user_input, $plugin_cookies);

            case self::ACTION_CHOOSE_FILE:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => $user_input->selected_action,
                        'extension'	=> $user_input->extension,
                        'allow_network' => ($user_input->selected_action === self::ACTION_FILE_TEXT_LIST) && !is_limited_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );

                return Action_Factory::open_folder($media_url_str, TR::t('select_file'));

            case ACTION_FILE_SELECTED:
                return $this->do_select_file($user_input, $plugin_cookies);

            case self::ACTION_CHOOSE_FOLDER:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_folder' => $user_input->control_id,
                        'extension'	=> $user_input->extension,
                        'allow_network' => false,
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );

                return Action_Factory::open_folder($media_url_str, TR::t('edit_list_src_folder'));

            case self::ACTION_ADD_PROVIDER_POPUP:
                $menu_items = $this->plugin->all_providers_menu($this);
                return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);

            case ACTION_EDIT_PROVIDER_DLG:
                return $this->plugin->do_edit_provider_dlg($this, $user_input->{PARAM_PROVIDER});

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
                $this->set_no_changes();
                $id = $this->plugin->apply_edit_provider_dlg($user_input);
                if ($id === null) {
                    return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
                }

                if (is_array($id)) {
                    return $id;
                }

                if ($this->plugin->get_active_playlist_key() === $id) {
                    if ($this->plugin->tv->reload_channels($plugin_cookies) === 0) {
                        return Action_Factory::invalidate_all_folders($plugin_cookies,
                            Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
                    }

                    return Action_Factory::invalidate_all_folders($plugin_cookies,
                        Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
                            $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx))
                    );
                }

                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);

            case ACTION_FOLDER_SELECTED:
                return $this->do_select_folder($user_input);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $edit_list = $media_url->edit_list;
        $items = array();
        if ($edit_list === self::SCREEN_EDIT_CHANNELS) {
            /** @var string $item */
            foreach ($this->plugin->tv->get_disabled_channel_ids() as $item) {
                if ($media_url->group_id === ALL_CHANNEL_GROUP_ID) {
                    $channel = $this->plugin->tv->get_channel($item);
                } else {
                    $group = $this->plugin->tv->get_group($media_url->group_id);
                    if (is_null($group)) continue;

                    $channel = $group->get_group_channels()->get($item);
                }

                if (!is_null($channel)) {
                    $items[] = self::add_item($item, $channel->get_title(), false, $channel->get_icon_url(), null);
                }
            }
        } else if ($edit_list === self::SCREEN_EDIT_GROUPS) {
            /** @var string $item */
            foreach ($this->plugin->tv->get_disabled_group_ids() as $item) {
                $group = $this->plugin->tv->get_group($item);
                if (!is_null($group)) {
                    $items[] = self::add_item($item, $group->get_title(), false, $group->get_icon_url(), null);
                }
            }
        } else if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
            /** @var Named_Storage $item */
            foreach ($this->plugin->get_playlists() as $key => $item) {
                $playlist = $this->plugin->get_playlist($key);
                $starred = ($key === $this->plugin->get_active_playlist_key());
                $title = empty($playlist->name) ? $playlist->params[PARAM_URI] : $playlist->name;
                if ($playlist->type === PARAM_PROVIDER) {
                    $provider = $this->plugin->get_provider($playlist->params[PARAM_PROVIDER]);
                    if (is_null($provider)) continue;

                    $icon_file = $provider->getLogo();
                    $title = $playlist->name;
                    if ($playlist->name !== $provider->getName()) {
                        $title .= " ({$provider->getName()})";
                    }
                    $detailed_info = $playlist->name;
                } else if ($playlist->type === PARAM_FILE) {
                    $detailed_info = null;
                    $icon_file = get_image_path("m3u_file.png");
                } else {
                    $detailed_info = "$playlist->name|{$playlist->params[PARAM_URI]}";
                    $icon_file = get_image_path("link.png");
                }

                $items[] = self::add_item($key, $title, $starred, $icon_file, $detailed_info);
            }
        } else if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
            $all_sources['pl'] = $this->plugin->get_playlist_xmltv_sources();
            $all_sources['ext'] = $this->plugin->get_ext_xmltv_sources();
            $active_key = $this->plugin->get_active_xmltv_source_key();
            $dupes = array();
            foreach ($all_sources as $idx => $source) {
                foreach ($source as $key => $item) {
                    if (isset($dupes[$key])) {
                        continue;
                    }

                    $dupes[$key] = '';
                    $cached_xmltv_file = $this->plugin->get_cache_dir() . DIRECTORY_SEPARATOR . "$key.xmltv";
                    $title = empty($item->name) ? $item->params[PARAM_URI] : $item->name;
                    if (file_exists($cached_xmltv_file)) {
                        $check_time_file = filemtime($cached_xmltv_file);
                        $dl_date = date("d.m H:i", $check_time_file);
                        $title = TR::t('edit_list_title_info__2', $title, $dl_date);
                        $detailed_info = TR::t('edit_list_detail_info__3', $item->name, $item->params[PARAM_URI], $dl_date);
                    } else {
                        $detailed_info = "$item->name|{$item->params[PARAM_URI]}";
                    }

                    if ($idx === 'pl') {
                        $icon_file = get_image_path("m3u_file.png" );
                    } else if ($item->type === PARAM_FILE) {
                        $icon_file = get_image_path("xmltv_file.png" );
                    } else {
                        $icon_file = get_image_path("link.png" );
                    }
                    $items[] = self::add_item($key, $title, $key === $active_key, $icon_file, $detailed_info);
                }
            }
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $folder_view = parent::get_folder_view($media_url, $plugin_cookies);

        $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = null;
        if (($media_url->edit_list === static::SCREEN_EDIT_PLAYLIST && $this->plugin->get_playlists()->size() === 0) ||
            ($media_url->edit_list === static::SCREEN_EDIT_EPG_LIST && $this->plugin->get_ext_xmltv_sources()->size() === 0)) {
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

    /**
     * @param Abstract_Screen $source_screen
     * @param string $action_edit
     * @param $selected_media_url
     * @return array|null
     */
    public static function get_caller_action($source_screen, $action_edit, $selected_media_url = null)
    {
        $params = array(
            'screen_id' => static::ID,
            'source_window_id' => $source_screen::ID,
            'source_media_url_str' => $source_screen::get_media_url_str(),
            'edit_list' => $action_edit,
            'windowCounter' => 1,
        );

        if ($action_edit === static::SCREEN_EDIT_CHANNELS ||
            $action_edit === static::SCREEN_EDIT_GROUPS) {
            $source_screen->plugin->set_postpone_save(true, PLUGIN_ORDERS);
            $params['save_data'] = PLUGIN_ORDERS;
            $params['end_action'] = ACTION_RELOAD;
            $params['cancel_action'] = ACTION_EMPTY;
        } else if ($action_edit === static::SCREEN_EDIT_PLAYLIST ||
            $action_edit === static::SCREEN_EDIT_EPG_LIST) {
            $source_screen->plugin->set_postpone_save(true, PLUGIN_PARAMETERS);
            $params['save_data'] = PLUGIN_PARAMETERS;
            $params['end_action'] = ACTION_REFRESH_SCREEN;
            $params['cancel_action'] = RESET_CONTROLS_ACTION_ID;
        }

        $sel_id = null;
        switch ($action_edit) {
            case static::SCREEN_EDIT_CHANNELS:
                if (!is_null($selected_media_url)) {
                    $params['group_id'] = $selected_media_url->group_id;
                }
                $title = TR::t('tv_screen_edit_hidden_channels');
                break;

            case static::SCREEN_EDIT_GROUPS:
                $title = TR::t('tv_screen_edit_hidden_group');
                break;

            case static::SCREEN_EDIT_PLAYLIST:
                $params['extension'] = PLAYLIST_PATTERN;
                $title = TR::t('setup_channels_src_edit_playlists');
                $sel_id = $source_screen->plugin->get_active_playlist_key();
                break;

            case static::SCREEN_EDIT_EPG_LIST:
                $params['extension'] = EPG_PATTERN;
                $title = TR::t('setup_edit_xmltv_list');
                break;

            default:
                return null;
        }

        return Action_Factory::open_folder(MediaURL::encode($params), $title, null, $sel_id);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    protected static function add_item($id, $title, $starred, $icon_file, $detailed_info)
    {
        return array(
            PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $id)),
            PluginRegularFolderItem::caption => $title,
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::item_sticker => ($starred ? Control_Factory::create_sticker(get_image_path('star_small.png'), -55, -2) : null),
                ViewItemParams::icon_path => $icon_file,
                ViewItemParams::item_detailed_info => $detailed_info,
                ViewItemParams::item_detailed_icon_path => $icon_file,
            ),
        );
    }

    protected function &get_order($edit_list)
    {
        if ($edit_list === self::SCREEN_EDIT_GROUPS) {
            $order = $this->plugin->tv->get_disabled_group_ids();
        } else if ($edit_list === self::SCREEN_EDIT_CHANNELS) {
            $order = $this->plugin->tv->get_disabled_channel_ids();
        } else {
            $order = new Ordered_Array();
            return $order;
        }

        return $order;
    }

    protected function get_hashed_order($edit_list)
    {
        if ($edit_list === static::SCREEN_EDIT_PLAYLIST) {
            $order = $this->plugin->get_playlists();
        } else if ($edit_list === static::SCREEN_EDIT_EPG_LIST) {
            $order = $this->plugin->get_ext_xmltv_sources();
        } else {
            $order = new Hashed_Array();
        }

        return $order;
    }

    /**
     * @param $user_input
     * @return array|null
     */
    protected function create_popup_menu($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;

        $menu_items = array();
        if ($edit_list === self::SCREEN_EDIT_PLAYLIST || $edit_list === self::SCREEN_EDIT_EPG_LIST) {
            // Add URL
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_ADD_URL_DLG,
                TR::t('edit_list_add_url'),
                "link.png"
            );

            // Add File
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FILE,
                TR::t('select_file'),
                $edit_list === self::SCREEN_EDIT_PLAYLIST ? "m3u_file.png" : "xmltv_file.png",
                array(
                    'selected_action' => ($edit_list === self::SCREEN_EDIT_PLAYLIST) ? self::ACTION_FILE_PLAYLIST : self::ACTION_FILE_XMLTV,
                    'extension' => $parent_media_url->extension
                )
            );

            // Add list file
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FILE,
                TR::t('edit_list_import_list'),
                "text_file.png",
                array(
                    'selected_action' => self::ACTION_FILE_TEXT_LIST,
                    'extension' => 'txt|lst'
                )
            );

            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FOLDER,
                TR::t('edit_list_folder_path'),
                "folder.png",
                array('extension' => $parent_media_url->extension)
            );

            // Add provider
            if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                $menu_items[] = $this->plugin->create_menu_item($this,
                    self::ACTION_ADD_PROVIDER_POPUP,
                    TR::t('edit_list_add_provider'),
                    "iptv.png");
            }

            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear'), "brush.png");
        } else {
            $order = $this->get_order($edit_list);
            if ($order->size() !== 0) {
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('restore_all'), "brush.png");
            }
        }

        return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param string $edit_list
     * @param string $id
     * @return array|null
     */
    protected function do_edit_url_dlg($edit_list = null, $id = '')
    {
        hd_debug_print(null, true);
        $defs = array();

        if ($edit_list !== null && !empty($id)) {
            $item = $this->get_hashed_order($edit_list)->get($id);
            if (is_null($item)) {
                return $defs;
            }

            $window_title = TR::t('edit_list_edit_item');
            $name = $item->name;
            $url = $item->params[PARAM_URI];
            $param = array(CONTROL_EDIT_ACTION => CONTROL_EDIT_ITEM);
        } else {
            $window_title = TR::t('edit_list_add_url');
            $name = '';
            $url = 'http://';
            $param = null;
        }

        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $this, null, CONTROL_EDIT_NAME, TR::t('name'),
            $name, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, TR::t('url'),
            $url, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this, $param,
            self::ACTION_URL_DLG_APPLY, TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($window_title, $defs, true);
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    protected function apply_edit_url_dlg($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;

        $name = isset($user_input->{CONTROL_EDIT_NAME}) ? $user_input->{CONTROL_EDIT_NAME} : '';
        $url = isset($user_input->{CONTROL_URL_PATH}) ? $user_input->{CONTROL_URL_PATH} : '';
        if (!preg_match(HTTP_PATTERN, $url)) {
            return Action_Factory::show_title_dialog(TR::t('err_incorrect_url'));
        }

        if (empty($name)) {
            if (($pos = strpos($name, '?')) !== false) {
                $name = substr($name, 0, $pos);
            }
            $name = ($edit_list === self::SCREEN_EDIT_PLAYLIST) ? basename($name) : $url;
        }

        $order = $this->get_hashed_order($edit_list);
        $id = MediaURL::decode($user_input->selected_media_url)->id;
        if (isset($user_input->{CONTROL_EDIT_ACTION})) {
            // edit existing url
            if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                $order->erase($id);
                $item = null;
                $this->plugin->get_epg_manager()->clear_epg_files($id);
            } else {
                $item = $order->get($id);
            }
        } else {
            $item = null;
        }

        if (is_null($item)) {
            $id = Hashed_Array::hash($url);
            while ($order->has($id)) {
                $id = Hashed_Array::hash("$id.$url");
            }
            $item = new Named_Storage();
        }

        if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
            try {
                $tmp_file = get_temp_path(Hashed_Array::hash($url));
                if (HD::http_save_https_proxy($url, $tmp_file) === false) {
                    throw new Exception("Can't download file: $url");
                }

                $contents = file_get_contents($tmp_file, false, null, 0, 512);
                if ($contents === false || strpos($contents, '#EXTM3U') === false) {
                    unlink($tmp_file);
                    throw new Exception("Bad M3U file: '$url'\n$contents");
                }
                unlink($tmp_file);
                hd_debug_print("Playlist: '$url' imported successfully");
            } catch (Exception $ex) {
                hd_debug_print("Problem with download playlist: " . $ex->getMessage());
                HD::set_last_error("pl_last_error", null);
                return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $ex->getMessage());
            }
        }

        $item->name = $name;
        $item->type = preg_match(HTTP_PATTERN, $url) ? PARAM_LINK : PARAM_FILE;
        $item->params[PARAM_URI] = $url;
        $order->set($id, $item);
        $this->set_changes($parent_media_url->save_data);

        $this->plugin->clear_playlist_cache($id);
        if (($this->plugin->get_active_playlist_key() === $id) && $this->plugin->tv->reload_channels($plugin_cookies) === 0) {
            return Action_Factory::invalidate_all_folders($plugin_cookies,
                Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
        }

        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url,$plugin_cookies), 0,
            $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx));
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array
     */
    protected function do_select_file($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_data);
        $edit_list = $parent_media_url->edit_list;

        $order = $this->get_hashed_order($edit_list);
        if ($selected_media_url->choose_file === self::ACTION_FILE_TEXT_LIST) {
            hd_debug_print("Choosed file: $selected_media_url->filepath", true);
            $lines = file($selected_media_url->filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
                return Action_Factory::show_title_dialog(TR::t('edit_list_empty_file'));
            }

            $old_count = $order->size();
            $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
            foreach ($lines as $line) {
                $line = trim($line);
                hd_debug_print("Load string: '$line'", true);
                $hash = Hashed_Array::hash($line);
                if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    $playlist = new Named_Storage();
                    if (preg_match(HTTP_PATTERN, $line, $m)) {
                        hd_debug_print("import link: '$line'", true);
                        try {
                            $tmp_file = get_temp_path(Hashed_Array::hash($line));
                            if (HD::http_save_https_proxy($line, $tmp_file) === false) {
                                throw new Exception("Can't download : $line");
                            }

                            if (file_exists($tmp_file)) {
                                $contents = file_get_contents($tmp_file, false, null, 0, 512);
                                if ($contents === false || strpos($contents, '#EXTM3U') === false) {
                                    unlink($tmp_file);
                                    throw new Exception("Bad M3U file: $line");
                                }
                                $playlist->type = PARAM_LINK;
                                $playlist->name = basename($m[2]);
                                $playlist->params[PARAM_URI] = $line;
                                unlink($tmp_file);
                            } else {
                                throw new Exception("Can't download file: $line");
                            }
                        } catch (Exception $ex) {
                            HD::set_last_error("pl_last_error", null);
                            hd_debug_print("Problem with download playlist: " . $ex->getMessage());
                            continue;
                        }
                    } else if (preg_match(PROVIDER_PATTERN, $line, $m)) {
                        hd_debug_print("import provider $m[1]:", true);
                        $provider = $this->plugin->get_provider($m[1]);
                        if (is_null($provider)) {
                            hd_debug_print("Unknown provider ID: $m[1]");
                            continue;
                        }
                        $playlist->type = PARAM_PROVIDER;
                        $playlist->params[PARAM_PROVIDER] = $m[1];
                        $playlist->name = $provider->getName();
                        $ext_vars = explode('|', $m[2]);
                        if (empty($ext_vars)) {
                            hd_debug_print("invalid provider_info: $m[2]", true);
                            continue;
                        }

                        $vars = explode(':', $ext_vars[0]);
                        if (empty($vars)) {
                            hd_debug_print("invalid provider_info: $ext_vars[0]", true);
                            continue;
                        }

                        hd_debug_print("parse imported provider_info: $ext_vars[0]", true);

                        switch ($provider->getType()) {
                            case PROVIDER_TYPE_PIN:
                                hd_debug_print("set pin: $vars[0]");
                                $playlist->params[MACRO_PASSWORD] = $vars[0];
                                break;

                            case PROVIDER_TYPE_LOGIN:
                            case PROVIDER_TYPE_LOGIN_TOKEN:
                            case PROVIDER_TYPE_LOGIN_STOKEN:
                                hd_debug_print("set login: $vars[0]", true);
                                $playlist->params[MACRO_LOGIN] = $vars[0];
                                hd_debug_print("set password: $vars[1]", true);
                                $playlist->params[MACRO_PASSWORD] = $vars[1];
                                break;

                            case PROVIDER_TYPE_EDEM:
                                if (isset($vars[1])) {
                                    hd_debug_print("set subdomain: $vars[0]", true);
                                    $playlist->params[MACRO_SUBDOMAIN] = $vars[0];
                                    hd_debug_print("set ottkey: $vars[1]", true);
                                    $playlist->params[MACRO_OTTKEY] = $vars[1];
                                } else {
                                    $playlist->params[MACRO_SUBDOMAIN] = 'junior.edmonst.net';
                                    hd_debug_print("set ottkey: $vars[0]", true);
                                    $playlist->params[MACRO_OTTKEY] = $vars[0];
                                }

                                if (!empty($ext_vars[1])) {
                                    if (!preg_match(VPORTAL_PATTERN, $ext_vars[1])) {
                                        return Action_Factory::show_title_dialog(TR::t('edit_list_bad_vportal'), null, TR::t('edit_list_bad_vportal_fmt'), 1000);
                                    }
                                    $playlist->params[MACRO_VPORTAL] = $ext_vars[1];
                                }
                                break;

                            default:
                                break;
                        }

                        $domains = $provider->GetDomains();
                        if (!empty($domains)) {
                            $playlist->params[CONFIG_DOMAINS] = key($domains);
                        }

                        $servers = $provider->getConfigValue(CONFIG_SERVERS);
                        if (!empty($servers)) {
                            $playlist->params[MACRO_SERVER_ID] = key($servers);
                        }

                        $devices = $provider->getConfigValue(CONFIG_DEVICES);
                        if (!empty($devices)) {
                            $playlist->params[MACRO_DEVICE_ID] = key($devices);
                        }

                        $qualities = $provider->getConfigValue(CONFIG_QUALITIES);
                        if (!empty($qualities)) {
                            $playlist->params[MACRO_QUALITY_ID] = key($qualities);
                        }

                        $hash = "{$provider->getId()}_$hash";
                    } else {
                        hd_debug_print("can't recognize: $line");
                        continue;
                    }

                    if ($order->has($hash)) {
                        hd_debug_print("already exist: $playlist", true);
                    } else {
                        hd_debug_print("imported playlist: $playlist", true);
                        $order->put($hash, $playlist);
                    }
                } else if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    if (preg_match(HTTP_PATTERN, $line, $m)) {
                        $old = $order->get($hash);
                        $item = new Named_Storage();
                        $item->params[PARAM_URI] = $line;
                        $item->name = $m[2];
                        if (is_null($old)) {
                            $order->put($hash, $item);
                            hd_debug_print("import link: '$line'");
                        } else if (!($old instanceof Hashed_Array)) {
                            $old_count--;
                            $order->set($hash, $item);
                            hd_debug_print("replace link: '$line'");
                        }
                    } else {
                        hd_debug_print("line skipped: '$line'");
                    }
                }
            }

            if ($old_count === $order->size()) {
                return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
            }

            $this->set_changes($parent_media_url->save_data);

            $window_title = ($edit_list === self::SCREEN_EDIT_PLAYLIST)
                ? TR::t('setup_channels_src_edit_playlists')
                : TR::t('setup_edit_xmltv_list');

            return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $order->size() - $old_count, count($lines)),
                Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str(), $window_title))
            );
        }

        if ($selected_media_url->choose_file === self::ACTION_FILE_PLAYLIST) {
            $hash = Hashed_Array::hash($selected_media_url->filepath);
            if ($order->has($hash)) {
                return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
            }

            $contents = file_get_contents($selected_media_url->filepath, false, null, 0, 512);
            if ($contents === false || strpos($contents, '#EXTM3U') === false) {
                hd_debug_print("Problem with import playlist: $selected_media_url->filepath");
                return Action_Factory::show_title_dialog(TR::t('err_bad_m3u_file'));
            }

            $playlist = new Named_Storage();
            $playlist->type = PARAM_FILE;
            $playlist->name = basename($selected_media_url->filepath);
            $playlist->params[PARAM_URI] = $selected_media_url->filepath;
            $order->put($hash, $playlist);
            $this->set_changes($parent_media_url->save_data);
        }

        if ($selected_media_url->choose_file === self::ACTION_FILE_XMLTV) {
            $hash = Hashed_Array::hash($selected_media_url->filepath);
            if ($order->has($hash)) {
                return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
            }

            $xmltv = new Named_Storage();
            $xmltv->type = PARAM_FILE;
            $xmltv->name = basename($selected_media_url->filepath);
            $xmltv->params[PARAM_URI] = $selected_media_url->filepath;
            $order->put($hash, $xmltv);
            $this->set_changes($parent_media_url->save_data);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @param $user_input
     * @return array
     */
    protected function do_select_folder($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_data);
        $files = glob_dir($selected_media_url->filepath, "/\.$parent_media_url->extension$/i");
        if (empty($files)) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        $edit_list = $parent_media_url->edit_list;
        $order = $this->get_hashed_order($edit_list);
        $old_count = $order->size();
        foreach ($files as $file) {
            $hash = Hashed_Array::hash($file);
            if ($order->has($hash)) continue;

            if ($user_input->selected_action === self::ACTION_FILE_PLAYLIST) {
                $contents = file_get_contents($file);
                if ($contents === false || strpos($contents, '#EXTM3U') === false) {
                    hd_debug_print("Problem with import playlist: $file");
                    continue;
                }
            }

            $playlist = new Named_Storage();
            $playlist->type = PARAM_FILE;
            $playlist->name = basename($file);
            $playlist->params[PARAM_URI] = $file;
            $order->put($hash, $playlist);
            $this->set_changes($parent_media_url->save_data);
        }

        $window_title = ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST)
            ? TR::t('setup_channels_src_edit_playlists')
            : TR::t('setup_edit_xmltv_list');

        return Action_Factory::show_title_dialog(TR::t('edit_list_added__1', $order->size() - $old_count),
            Action_Factory::close_and_run(
                Action_Factory::open_folder($parent_media_url->get_media_url_str(), $window_title))
        );
    }

    protected function force_save($user_input)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $this->plugin->set_postpone_save(false, $parent_media_url->save_data);
        $this->plugin->set_postpone_save(true, $parent_media_url->save_data);
        $this->set_no_changes();
    }
}
