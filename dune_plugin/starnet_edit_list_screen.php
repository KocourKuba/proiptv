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
    const ACTION_EDIT_ITEM_DLG = 'add_url_dialog';
    const ACTION_URL_DLG_APPLY = 'url_dlg_apply';
    const CONTROL_URL_PATH = 'url_path';
    const CONTROL_EDIT_NAME = 'set_item_name';
    const CONTROL_EDIT_ACTION = 'edit_action';
    const CONTROL_EDIT_ITEM = 'edit_item';
    const ITEM_SET_NAME = 'set_name';
    const ITEM_EDIT = 'edit';

    const ACTION_ADD_URL_DLG = 'add_url';
    const ACTION_ADD_PROVIDER_POPUP = 'add_provider';
    const ACTION_EDIT_PROVIDER_DLG_APPLY = 'select_provider_apply';
    const CONTROL_LOGIN = 'login';
    const CONTROL_PASSWORD = 'password';
    const CONTROL_OTT_SUBDOMAIN = 'subdomain';
    const CONTROL_OTT_KEY = 'ottkey';
    const CONTROL_DEVICE = 'device';
    const CONTROL_SERVER = 'server';
    const CONTROL_QUALITY = 'quality';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $actions = array();
        if ($this->get_edit_order($media_url->edit_list)->size()) {
            if (isset($media_url->allow_order)) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
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
                    $reload ? array($parent_media_url->source_media_url_str) : null,
                    Action_Factory::replace_path($parent_media_url->windowCounter, null, $post_action)
                );

            case GUI_EVENT_KEY_STOP:
                $this->force_save($user_input);
                break;

            case GUI_EVENT_KEY_ENTER:
                if ($edit_list !== self::SCREEN_EDIT_PLAYLIST && $edit_list !== self::SCREEN_EDIT_EPG_LIST) break;

                $this->force_save($user_input);

                $id = MediaURL::decode($user_input->selected_media_url)->id;
                $selected_media_url = MediaURL::decode($user_input->selected_media_url);
                $user_input->{self::CONTROL_EDIT_ITEM} = $selected_media_url->id;

                /** @var Named_Storage $order */
                $item = $this->get_edit_order($edit_list)->get($id);

                hd_debug_print("item: " . $item);
                if ($item->type === PARAM_LINK && isset($item->params['uri']) && preg_match(HTTP_PATTERN, $item->params['uri'])) {
                    return $this->do_edit_url_dlg($user_input);
                }

                if ($item->type === PARAM_PROVIDER) {
                    return $this->do_edit_provider_dlg($user_input);
                }
                return null;

            case ACTION_ITEM_UP:
                $order = &$this->get_edit_order($edit_list);
                $selected_media_url = MediaURL::decode($user_input->selected_media_url);
                if (!$order->arrange_item($selected_media_url->id, Ordered_Array::UP)) {
                    return null;
                }

                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }

                $this->set_changes($parent_media_url->save_data);
                break;

            case ACTION_ITEM_DOWN:
                $order = &$this->get_edit_order($edit_list);
                $selected_media_url = MediaURL::decode($user_input->selected_media_url);
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
                        if (!is_null($channel)) {
                            hd_debug_print("restore channel: {$channel->get_title()} ({$channel->get_id()})");
                            $channel->set_disabled(false);
                        }
                        break;

                    case self::SCREEN_EDIT_GROUPS:
                        $group = $this->plugin->tv->get_group($item);
                        if (!is_null($group)) {
                            hd_debug_print("restore group: " . $group->get_id());
                            $group->set_disabled(false);
                        }
                        break;

                    default:
                        hd_debug_print("unknown edit list");
                        return null;
                }

                $this->set_changes($parent_media_url->save_data);
                if ($this->get_edit_order($edit_list)->size() === 0) {
                    return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                }
                break;

            case self::ACTION_REMOVE_ITEM_DLG_APPLY:
                hd_debug_print(null, true);

                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                $selected_media_url = MediaURL::decode($user_input->selected_media_url);
                hd_debug_print("edit_list: $parent_media_url->edit_list");
                /** @var Hashed_Array $order */
                $order = &$this->get_edit_order($parent_media_url->edit_list);
                if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    hd_debug_print("remove xmltv source: $selected_media_url->id", true);
                    $this->plugin->get_epg_manager()->clear_epg_files($selected_media_url->id);
                } else if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    hd_debug_print("remove playlist settings: $selected_media_url->id", true);
                    $this->plugin->remove_settings($selected_media_url->id);
                }
                $order->erase($selected_media_url->id);
                $this->set_changes($parent_media_url->save_data);

                return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
                    $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx));

            case ACTION_ITEMS_SORT:
                $this->get_edit_order($edit_list)->sort_order();
                $this->set_changes($parent_media_url->save_data);
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($user_input);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CLEAR_APPLY);

            case self::ACTION_CLEAR_APPLY:
                switch ($edit_list) {
                    case self::SCREEN_EDIT_EPG_LIST:
                    case self::SCREEN_EDIT_PLAYLIST:
                        /** @var Hashed_Array $order */
                        $order = $this->get_edit_order($edit_list);
                        /** @var Named_Storage $item */
                        foreach ($order->get_keys() as $key) {
                            hd_debug_print("item: $key");
                            if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                                $this->plugin->get_epg_manager()->clear_epg_files($key);
                            } else if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                                $this->plugin->remove_settings($key);
                            }
                        }
                        $order->clear();
                        break;

                    case self::SCREEN_EDIT_CHANNELS:
                        $group = $this->plugin->tv->get_any_group($parent_media_url->group_id);
                        if (is_null($group)) break;

                        /** @var Channel $channel */
                        foreach ($group->get_group_disabled_channels() as $channel) {
                            hd_debug_print("restore channel: {$channel->get_title()} ({$channel->get_id()})");
                            $channel->set_disabled(false);
                        }
                        break;

                    case self::SCREEN_EDIT_GROUPS:
                        /** @var Group $group */
                        foreach ($this->plugin->tv->get_groups($this->get_edit_order($edit_list)) as $group) {
                            hd_debug_print("restore group: " . $group->get_id());
                            $group->set_disabled(false);
                        }
                        break;

                    default:
                        return null;
                }

                $this->set_changes($parent_media_url->save_data);

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case self::ACTION_ADD_URL_DLG:
            case self::ACTION_EDIT_ITEM_DLG:
                return $this->do_edit_url_dlg($user_input);

            case self::ACTION_URL_DLG_APPLY: // handle streaming settings dialog result
                return $this->apply_edit_url_dlg($user_input, $plugin_cookies);

            case self::ACTION_CHOOSE_FILE:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => array(
                            'action' => $user_input->action,
                            'extension'	=> $user_input->extension,
                        ),
                        'allow_network' => ($user_input->action === self::ACTION_FILE_TEXT_LIST) && !is_apk(),
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
                        'choose_folder' => array(
                            'extension'	=> $user_input->extension,
                        ),
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
                return $this->do_edit_provider_dlg($user_input);

            case self::ACTION_EDIT_PROVIDER_DLG_APPLY:
                return $this->apply_edit_provider_dlg($user_input, $plugin_cookies);

            case ACTION_FOLDER_SELECTED:
                return $this->do_select_folder($user_input);
        }

        return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();
        $edit_list = $media_url->edit_list;
        /** @var Hashed_Array|Ordered_Array $order */
        $order = &$this->get_edit_order($edit_list);
        foreach ($order as $key => $item) {
            $detailed_info = null;
            if ($edit_list === self::SCREEN_EDIT_CHANNELS) {
                // Ordered_Array
                /** @var string $item */
                $id = $item;
                if ($media_url->group_id === ALL_CHANNEL_GROUP_ID) {
                    $channel = $this->plugin->tv->get_channel($item);
                } else {
                    $group = $this->plugin->tv->get_group($media_url->group_id);
                    if (is_null($group)) continue;
                    $channel = $group->get_group_channels()->get($item);
                }

                if (is_null($channel)) continue;

                $title = $channel->get_title();
                $icon_file = $channel->get_icon_url();
            } else if ($edit_list === self::SCREEN_EDIT_GROUPS) {
                // Ordered_Array
                /** @var string $item */
                $id = $item;
                $group = $this->plugin->tv->get_group($item);
                if (is_null($group)) continue;

                $title = $group->get_title();
                $icon_file = $group->get_icon_url();
            } else if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                // Hashed_Array
                /** @var Named_Storage $item */
                $id = $key;
                $playlist = $this->plugin->get_playlist($id);
                $icon_file = get_image_path("link.png");
                $title = empty($playlist->name) ? $playlist->params['uri'] : $playlist->name;
                if ($playlist->type === PARAM_PROVIDER) {
                    $provider = $this->plugin->init_provider($playlist);
                    if (is_null($provider)) continue;

                    $icon_file = $provider->getLogo();
                    $title = $provider->getName();
                    $detailed_info = TR::t('edit_list_detail_info__2', $playlist->name, '');
                } else if ($playlist->type === PARAM_LINK) {
                    $detailed_info = TR::t('edit_list_detail_info__2', $playlist->name, $playlist->params['uri']);
                }
            } else if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                // Hashed_Array
                /** @var Named_Storage $item */
                $id = $key;
                $title = empty($item->name) ? $item->params['uri'] : $item->name;
                $detailed_info = TR::t('edit_list_detail_info__2', $item->name, $item->params['uri']);
                $icon_file = get_image_path("link.png");
            } else {
                continue;
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $id)),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
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
        hd_debug_print($media_url, true);

        $folder_view = parent::get_folder_view($media_url, $plugin_cookies);

        if ($this->get_edit_order($media_url->edit_list)->size() === 0) {
            $msg = !is_apk()
                ? TR::t('edit_list_add_prompt__3', 100, 300, DEF_LABEL_TEXT_COLOR_YELLOW)
                : TR::t('edit_list_add_prompt_apk__3', 100, 300, DEF_LABEL_TEXT_COLOR_YELLOW);
            $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = $msg;
        } else {
            $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = null;
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

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param string $edit_list
     * @return Ordered_Array|Hashed_Array
     */
    protected function &get_edit_order($edit_list)
    {
        hd_debug_print($edit_list, true);

        switch ($edit_list) {
            case self::SCREEN_EDIT_PLAYLIST:
                return $this->plugin->get_playlists();

            case self::SCREEN_EDIT_EPG_LIST:
                return $this->plugin->get_ext_xmltv_sources();

            case self::SCREEN_EDIT_GROUPS:
                return $this->plugin->tv->get_disabled_group_ids();

            case self::SCREEN_EDIT_CHANNELS:
                return $this->plugin->tv->get_disabled_channel_ids();
        }

        $order = new Ordered_Array();
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
            $add_param = array('extension' => $parent_media_url->extension);
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_ADD_URL_DLG,
                TR::t('edit_list_add_url'),
                "link.png");

            // Add File
            $add_param['action'] = $edit_list === self::SCREEN_EDIT_PLAYLIST ? self::ACTION_FILE_PLAYLIST : self::ACTION_FILE_XMLTV;
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FILE, TR::t('select_file'),
                $edit_list === self::SCREEN_EDIT_PLAYLIST ? "m3u_file.png" : "xmltv_file.png",
                $add_param);

            // Add list file
            $add_param['action'] = self::ACTION_FILE_TEXT_LIST;
            $add_param['extension'] = 'txt|lst';
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FILE,
                TR::t('edit_list_import_list'),
                "text_file.png",
                $add_param);

            unset($add_param['action']);
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FOLDER,
                TR::t('edit_list_folder_path'),
                "folder.png",
                $add_param);

            // Add provider
            if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                $menu_items[] = $this->plugin->create_menu_item($this,
                    self::ACTION_ADD_PROVIDER_POPUP,
                    TR::t('edit_list_add_provider'),
                    "iptv.png");
            }

            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear'), "brush.png");
        } else if ($this->get_edit_order($edit_list)->size()) {
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('restore_all'), "brush.png");
        }

        return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param $user_input
     * @return array|null
     */
    protected function do_edit_url_dlg($user_input)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);
        $defs = array();

        if (isset($user_input->{self::CONTROL_EDIT_ITEM})) {
            $order = $this->get_edit_order(MediaURL::decode($user_input->parent_media_url)->edit_list);
            /** @var Named_Storage $item */
            $item = $order->get($user_input->{self::CONTROL_EDIT_ITEM});
            if (is_null($item)) {
                return $defs;
            }
            $window_title = TR::t('edit_list_edit_item');
            $name = $item->name;
            $url = $item->params['uri'];
            $param = array(self::CONTROL_EDIT_ACTION => self::CONTROL_EDIT_ITEM);
        } else {
            $window_title = TR::t('edit_list_add_url');
            $name = '';
            $url = 'http://';
            $param = null;
        }

        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $this, null, self::CONTROL_EDIT_NAME, TR::t('name'),
            $name, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_text_field($defs, $this, null, self::CONTROL_URL_PATH, TR::t('url'),
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

        /** @var Hashed_Array $order */
        $order = &$this->get_edit_order($edit_list);

        $url = isset($user_input->{self::CONTROL_URL_PATH}) ? $user_input->{self::CONTROL_URL_PATH} : '';
        $name = isset($user_input->{self::CONTROL_EDIT_NAME}) ? $user_input->{self::CONTROL_EDIT_NAME} : '';

        if (empty($name)) {
            if (($pos = strpos($name, '?')) !== false) {
                $name = substr($name, 0, $pos);
            }
            $name = ($edit_list === self::SCREEN_EDIT_PLAYLIST) ? basename($name) : $url;
        }

        if (isset($user_input->{self::CONTROL_EDIT_ACTION})) {
            // edit existing url
            $id = MediaURL::decode($user_input->selected_media_url)->id;
            /** @var Named_Storage $playlist */
            $playlist = $order->get($id);
            if (is_null($playlist)) {
                $playlist = new Named_Storage();
            }
        } else {
            // new url
            $id = Hashed_Array::hash($url);
            while ($order->has($id)) {
                $id = Hashed_Array::hash("$id.$url");
            }
            $playlist = new Named_Storage();
        }

        $playlist->name = $name;
        $playlist->params['uri'] = $url;
        $playlist->type = preg_match(HTTP_PATTERN, $url) ? PARAM_LINK : PARAM_FILE;
        $order->set($id, $playlist);
        $this->set_changes($parent_media_url->save_data);

        $this->plugin->clear_playlist_cache($id);
        if (($this->plugin->get_active_playlist_key() === $id) && $this->plugin->tv->reload_channels() === 0) {
            return Action_Factory::invalidate_all_folders($plugin_cookies,
                Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
        }

        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url,$plugin_cookies), 0,
            $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx));
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
        $data = MediaURL::decode($user_input->selected_data);

        if ($data->choose_file->action === self::ACTION_FILE_TEXT_LIST) {
            hd_debug_print("Choosed file: $data->filepath", true);
            $lines = file($data->filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
                return Action_Factory::show_title_dialog(TR::t('edit_list_empty_file'));
            }

            $order = &$this->get_edit_order($parent_media_url->edit_list);
            $old_count = $order->size();
            $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
            foreach ($lines as $line) {
                $line = trim($line);
                $hash = Hashed_Array::hash($line);
                if (!$order->has($hash)) {
                    hd_debug_print("Load string: '$line'", true);
                    $playlist = new Named_Storage();
                    if (preg_match(HTTP_PATTERN, $line, $m)) {
                        hd_debug_print("import link: '$line'", true);
                        if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
                            try{
                                $content = HD::http_get_document($line);
                                if (strpos($content, '#EXTM3U') !== 0) {
                                    throw new Exception("Bad M3U file: $line");
                                }
                                $playlist->type = PARAM_LINK;
                                $playlist->name = basename($m[2]);
                                $playlist->params['uri'] = $line;
                            } catch (Exception $ex) {
                                hd_debug_print("Problem with download playlist: " . $ex->getMessage());
                                continue;
                            }
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
                        $vars = explode(':', $m[2]);
                        if (empty($vars)) {
                            hd_debug_print("invalid provider_info: $m[2]", true);
                            continue;
                        }

                        hd_debug_print("parse imported provider_info: $m[2]", true);

                        switch ($provider->getProviderType()) {
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
                                hd_debug_print("set subdomain: $vars[0]", true);
                                $playlist->params[MACRO_SUBDOMAIN] = $vars[0];
                                hd_debug_print("set ottkey: $vars[0]", true);
                                $playlist->params[MACRO_OTTKEY] = $vars[1];
                                break;
                        }

                        if (count($servers = $provider->getServers())) {
                            $playlist->params[MACRO_SERVER] = key($servers);
                        }

                        if (count($devices = $provider->getDevices())) {
                            $playlist->params[MACRO_DEVICE] = key($devices);
                        }

                        if (count($qualities = $provider->getQualities())) {
                            $playlist->params[MACRO_QUALITY] = key($qualities);
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
                }
            }

            if ($old_count === $order->size()) {
                return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
            }

            $this->set_changes($parent_media_url->save_data);

            $window_title = ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST)
                ? TR::t('setup_channels_src_edit_playlists')
                : TR::t('setup_edit_xmltv_list');

            return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $order->size() - $old_count, count($lines)),
                Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str(), $window_title))
            );
        }

        if ($data->choose_file->action === self::ACTION_FILE_PLAYLIST || $data->choose_file->action === self::ACTION_FILE_XMLTV) {
            $order = &$this->get_edit_order($parent_media_url->edit_list);
            $hash = Hashed_Array::hash($data->filepath);
            if ($order->has($hash)) {
                return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
            }

            $playlist = new Named_Storage();
            $playlist->type = PARAM_FILE;
            $playlist->name = basename($data->filepath);
            $playlist->params['uri'] = $data->filepath;
            $order->put($hash, $playlist);
            $this->set_changes($parent_media_url->save_data);
        }

        return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @param $user_input
     * @return array
     */
    protected function do_select_folder($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_data = MediaURL::decode($user_input->selected_data);
        $files = glob_dir($selected_data->filepath, "/\.$parent_media_url->extension$/i");
        if (empty($files)) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        $order = &$this->get_edit_order($parent_media_url->edit_list);
        $old_count = $order->size();
        foreach ($files as $file) {
            $hash = Hashed_Array::hash($file);
            if ($order->has($hash)) continue;

            $playlist = new Named_Storage();
            $playlist->type = PARAM_FILE;
            $playlist->name = basename($file);
            $playlist->params['uri'] = $file;
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

    /**
     * @param $user_input
     * @return array|null
     */
    protected function do_edit_provider_dlg($user_input)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        $provider = null;
        $id = '';
        if (isset($user_input->{PARAM_PROVIDER})) {
            // add new provider
            $provider = $this->plugin->get_provider($user_input->{PARAM_PROVIDER});
            hd_debug_print("new provider : $provider", true);
        } else if (isset($user_input->{self::CONTROL_EDIT_ITEM})) {
            // edit existing provider
            $id = $user_input->{self::CONTROL_EDIT_ITEM};
            $playlist = $this->plugin->get_playlist($id);
            if (!is_null($playlist)) {
                hd_debug_print("playlist info : $playlist", true);
                $provider = $this->plugin->init_provider($playlist);
                hd_debug_print("existing provider : $provider", true);
            }
        }

        if (is_null($provider)) {
            return $defs;
        }

        Control_Factory::add_text_field($defs, $this, null,
            self::CONTROL_EDIT_NAME, TR::t('name'), $provider->getName(),
            false, false, false, true, self::DLG_CONTROLS_WIDTH);

        switch ($provider->getProviderType()) {
            case PROVIDER_TYPE_PIN:
                Control_Factory::add_text_field($defs, $this, null,
                    self::CONTROL_PASSWORD, TR::t('password'), $provider->getCredential(MACRO_PASSWORD),
                    false, true, false, true, self::DLG_CONTROLS_WIDTH);
                break;

            case PROVIDER_TYPE_LOGIN:
            case PROVIDER_TYPE_LOGIN_TOKEN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
                Control_Factory::add_text_field($defs, $this, null,
                    self::CONTROL_LOGIN, TR::t('login'), $provider->getCredential(MACRO_LOGIN),
                    false, false, false, true, self::DLG_CONTROLS_WIDTH);
                Control_Factory::add_text_field($defs, $this, null,
                    self::CONTROL_PASSWORD, TR::t('password'), $provider->getCredential(MACRO_PASSWORD),
                    false, true, false, true, self::DLG_CONTROLS_WIDTH);
                break;

            case PROVIDER_TYPE_EDEM:
                Control_Factory::add_text_field($defs, $this, null,
                    self::CONTROL_OTT_SUBDOMAIN, TR::t('subdomain'), $provider->getCredential(MACRO_SUBDOMAIN),
                    false, false, false, true, self::DLG_CONTROLS_WIDTH);
                Control_Factory::add_text_field($defs, $this, null,
                    self::CONTROL_OTT_KEY, TR::t('ottkey'), $provider->getCredential(MACRO_OTTKEY),
                    false, true, false, true, self::DLG_CONTROLS_WIDTH);
                break;

            default:
                return null;
        }

        $servers = $provider->getServers();
        if (!empty($servers)) {
            $idx = $provider->getCredential(MACRO_SERVER);
            if (empty($idx)) {
                $idx = key($servers);
            }

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_SERVER,
                TR::t('server'), $idx, $servers, self::DLG_CONTROLS_WIDTH, true);
        }

        $devices = $provider->getDevices();
        if (!empty($devices)) {
            $idx = $provider->getCredential(MACRO_DEVICE);
            if (empty($idx)) {
                $idx = key($devices);
            }

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_DEVICE,
                TR::t('device'), $idx, $devices, self::DLG_CONTROLS_WIDTH, true);
        }

        $qualities = $provider->getQualities();
        if (!empty($qualities)) {
            $idx = $provider->getCredential(MACRO_QUALITY);
            if (empty($idx)) {
                $idx = key($qualities);
            }

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_QUALITY,
                TR::t('quality'), $idx, $qualities, self::DLG_CONTROLS_WIDTH, true);
        }

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this,
            array(PARAM_PROVIDER => $provider->getId(), self::CONTROL_EDIT_ITEM => $id),
            self::ACTION_EDIT_PROVIDER_DLG_APPLY,
            TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog("{$provider->getName()} ({$provider->getId()})", $defs, true);
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    protected function apply_edit_provider_dlg($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $provider = $this->plugin->get_provider($user_input->{PARAM_PROVIDER});
        if (is_null($provider)) return null;

        $item = new Named_Storage();
        $item->type = PARAM_PROVIDER;
        $item->name = $user_input->{self::CONTROL_EDIT_NAME};

        $params[PARAM_PROVIDER] = $user_input->{PARAM_PROVIDER};
        $id = $user_input->{self::CONTROL_EDIT_ITEM};
        switch ($provider->getProviderType()) {
            case PROVIDER_TYPE_PIN:
                $params[MACRO_PASSWORD] = $user_input->{self::CONTROL_PASSWORD};
                $id = empty($id) ? Hashed_Array::hash($params[MACRO_PASSWORD]) : $id;
                break;

            case PROVIDER_TYPE_LOGIN:
            case PROVIDER_TYPE_LOGIN_TOKEN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
                $params[MACRO_LOGIN] = $user_input->{self::CONTROL_LOGIN};
                $params[MACRO_PASSWORD] = $user_input->{self::CONTROL_PASSWORD};
                $id = empty($id) ? Hashed_Array::hash($params[MACRO_LOGIN].$params[MACRO_PASSWORD]) : $id;
                break;

            case PROVIDER_TYPE_EDEM:
                $params[MACRO_SUBDOMAIN] = $user_input->{self::CONTROL_OTT_SUBDOMAIN};
                $params[MACRO_OTTKEY] = $user_input->{self::CONTROL_OTT_KEY};
                $id = empty($id) ? Hashed_Array::hash($params[MACRO_SUBDOMAIN].$params[MACRO_OTTKEY]) : $id;
                break;

            default:
                return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx);
        }

        if (isset($user_input->{self::CONTROL_SERVER})) {
            $params[MACRO_SERVER] = $user_input->{self::CONTROL_SERVER};
        }

        if (isset($user_input->{self::CONTROL_DEVICE})) {
            $params[MACRO_DEVICE] = $user_input->{self::CONTROL_DEVICE};
        }

        if (isset($user_input->{self::CONTROL_QUALITY})) {
            $params[MACRO_QUALITY] = $user_input->{self::CONTROL_QUALITY};
        }

        $item->params = $params;

        hd_debug_print("compiled provider info: $item->name, provider params: " . json_encode($item->params), true);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $this->plugin->get_playlists()->set($id, $item);
        $this->plugin->set_dirty(true, $parent_media_url->save_data);
        $this->force_save($user_input);

        $this->plugin->clear_playlist_cache($id);
        if (($this->plugin->get_active_playlist_key() === $id) && $this->plugin->tv->reload_channels() === 0) {
            return Action_Factory::invalidate_all_folders($plugin_cookies,
                Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
        }

        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url,$plugin_cookies), 0,
            $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx));
    }

    protected function force_save($user_input)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $this->plugin->set_postpone_save(false, $parent_media_url->save_data);
        $this->plugin->set_postpone_save(true, $parent_media_url->save_data);
        $this->set_no_changes();
    }
}
