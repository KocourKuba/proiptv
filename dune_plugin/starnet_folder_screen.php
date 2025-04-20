<?php
/**
 * The MIT License (MIT)
 *
 * @Author: Andrii Kopyniak
 * Modification and improvements: sharky72 (https://github.com/KocourKuba)
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

require_once 'lib/abstract_regular_screen.php';
require_once 'lib/user_input_handler_registry.php';
require_once 'lib/smb_tree.php';
require_once 'lib/hd.php';
require_once 'lib/curl_wrapper.php';

class Starnet_Folder_Screen extends Abstract_Regular_Screen implements User_Input_Handler
{
    const ID = 'file_list';
    const ACTION_FS = 'fs_action';
    const ACTION_SELECT_FOLDER = 'select_folder';
    const ACTION_RESET_FOLDER = 'reset_folder';
    const ACTION_CREATE_FOLDER = 'create_folder';
    const ACTION_GET_FOLDER_NAME_DLG = 'get_folder_name';
    const ACTION_DO_MKDIR = 'do_mkdir';
    const ACTION_SMB_SETUP = 'smb_setup';
    const ACTION_NEW_SMB_DATA = 'new_smb_data';
    const ACTION_SAVE_SMB_SETUP = 'save_smb_setup';
    const ACTION_RELOAD_IMAGE_FOLDER = 'reload_image_folder';

    const SELECTED_TYPE_NFS = 'nfs';
    const SELECTED_TYPE_SMB = 'smb';
    const SELECTED_TYPE_FOLDER = 'folder';
    const SELECTED_TYPE_FILE = 'file';
    const SELECTED_TYPE_IMAGE_LIB = 'imagelib';

    const MNT_PATH = '/tmp/mnt';
    const STORAGE_PATH = '/tmp/mnt/storage';
    const NETWORK_PATH = '/tmp/mnt/network';
    const SMB_PATH = '/tmp/mnt/smb';
    const NFS_PATH = '/tmp/mnt/nfs';
    const SDCARD_PATH = '/sdcard';
    const IMAGELIB_PATH = '/imagelib';

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $actions = array();

        $fs_action = User_Input_Handler_Registry::create_action($this, self::ACTION_FS);
        $actions[GUI_EVENT_KEY_ENTER] = $fs_action;
        $actions[GUI_EVENT_KEY_SETUP] = Action_Factory::replace_path($media_url->windowCounter);

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);

        if (empty($media_url->filepath)) {
            $allow_network = safe_get_member($media_url, 'allow_network', false);
            $allow_image_lib = safe_get_member($media_url, 'allow_image_lib', false);

            if ($allow_network && !is_android()) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_SMB_SETUP, TR::t('folder_screen_smb_settings'));
            }

            if ($media_url->allow_reset) {
                $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_RESET_FOLDER, TR::t('reset_default'));
            }

            if ($allow_image_lib) {
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_RELOAD_IMAGE_FOLDER, TR::t('refresh'));
            }
        } else if ($media_url->filepath !== self::STORAGE_PATH &&
            $media_url->filepath !== self::NETWORK_PATH &&
            $media_url->filepath !== self::SMB_PATH &&
            $media_url->filepath !== self::SDCARD_PATH . "/DuneHD" &&
            $media_url->filepath !== self::IMAGELIB_PATH) {

            if ($media_url->choose_folder !== false) {
                $actions[GUI_EVENT_KEY_A_RED] = User_Input_Handler_Registry::create_action($this,
                    ACTION_OPEN_FOLDER, TR::t('folder_screen_open_folder'));

                if (!isset($media_url->read_only)) {
                    $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this,
                        self::ACTION_GET_FOLDER_NAME_DLG, TR::t('folder_screen_create_folder'));
                }

                $select_folder = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_SELECT_FOLDER, TR::t('select_folder'));

                $actions[GUI_EVENT_KEY_D_BLUE] = $select_folder;
                $actions[GUI_EVENT_KEY_SELECT] = $select_folder;
            }

            $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);
        }

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);

        switch ($user_input->control_id) {
            case GUI_EVENT_TIMER:
                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                if (isset($parent_media_url->filepath)
                    && $parent_media_url->filepath !== self::SMB_PATH
                    && $parent_media_url->filepath !== self::NETWORK_PATH) {
                    $invalidate = Action_Factory::invalidate_all_folders($plugin_cookies, array($user_input->parent_media_url));
                } else {
                    $invalidate = null;
                }

                return Action_Factory::change_behaviour($actions, 1000, $invalidate);

            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if (!isset($parent_media_url->end_action)) {
                    return Action_Factory::close_and_run();
                }

                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(
                        $parent_media_url->source_window_id,
                        $parent_media_url->end_action,
                        null,
                        array('action_id' => $parent_media_url->action_id)
                    )
                );

            case self::ACTION_FS:
                return $this->do_action_fs($user_input);

            case self::ACTION_SELECT_FOLDER:
                return $this->do_select_folder($user_input);

            case self::ACTION_RESET_FOLDER:
                return $this->do_reset_folder($user_input);

            case self::ACTION_RELOAD_IMAGE_FOLDER:
                return $this->do_reload_folder($user_input, $plugin_cookies);

            case self::ACTION_GET_FOLDER_NAME_DLG:
                return $this->do_get_folder_name_dlg();

            case self::ACTION_CREATE_FOLDER:
                return Action_Factory::close_dialog_and_run(User_Input_Handler_Registry::create_action($this, self::ACTION_DO_MKDIR));

            case self::ACTION_DO_MKDIR:
                return $this->do_mkdir($user_input, $plugin_cookies);

            case ACTION_OPEN_FOLDER:
                return $this->do_open_folder($user_input);

            case self::ACTION_NEW_SMB_DATA:
                return $this->do_new_smb_data($user_input);

            case self::ACTION_SMB_SETUP:
                return $this->do_smb_setup($plugin_cookies);

            case self::ACTION_SAVE_SMB_SETUP:
                return $this->do_save_smb_setup($user_input, $plugin_cookies);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("from_ndx: $from_ndx, MediaURL: " . $media_url->get_media_url_str(true), true);

        if (empty($media_url->filepath)) {
            $dir = array(self::IMAGELIB_PATH, self::MNT_PATH);
            if (is_android()) {
                $dir[] = self::SDCARD_PATH;
            }
        } else {
            $dir[] = $media_url->filepath;
        }

        $err = false;
        $source_window_id = safe_get_member($media_url, 'source_window_id', false);
        $allow_network = safe_get_member($media_url, 'allow_network', false);
        $allow_image_lib = safe_get_member($media_url, 'allow_image_lib', false);
        $windowCounter = safe_get_member($media_url, 'windowCounter', 1) + 1;
        $ip_path = safe_get_member($media_url, 'ip_path', false);
        $nfs_protocol = safe_get_member($media_url, 'nfs_protocol', false);
        $user = safe_get_member($media_url, 'user', false);
        $password = safe_get_member($media_url, 'password', false);
        $choose_folder = safe_get_member($media_url, 'choose_folder', false);
        $choose_file = safe_get_member($media_url, 'choose_file', false);
        $action_id = safe_get_member($media_url, 'action_id', false);

        $items = array();
        hd_debug_print("dir: " . json_encode($dir), true);
        foreach ($this->get_file_list($plugin_cookies, $dir, !$choose_file) as $item_type => $item) {
            if (isset($media_url->filepath) && is_array($item)) {
                ksort($item);
            }

            foreach ($item as $k => $v) {
                hd_debug_print("folder key: $k, value: " . json_encode($v), true);
                $detailed_icon = '';
                if ($item_type === self::SELECTED_TYPE_SMB) {
                    $caption = $v['foldername'];
                    $filepath = $k;
                    $icon_file = self::get_folder_icon('smb_folder');
                    $info = TR::t('folder_screen_smb__2', $caption, $v['ip']);
                    $type = self::SELECTED_TYPE_FOLDER;
                    $ip_path = $v['ip'];
                    if (!empty($v['user'])) {
                        $user = $v['user'];
                    }
                    if (!empty($v['password'])) {
                        $password = $v['password'];
                    }
                    if (isset($v['err'])) {
                        $info = TR::t('err_error_smb__1', $v['err']);
                        $err = $v['err'];
                    }
                } else if ($item_type === self::SELECTED_TYPE_NFS) {
                    $caption = $v['foldername'];
                    $filepath = $k;
                    $icon_file = self::get_folder_icon('nfs_folder');
                    $info = TR::t('folder_screen_nfs__2', $caption, $v['ip']);
                    $type = self::SELECTED_TYPE_FOLDER;
                    $ip_path = $v['ip'];
                    $nfs_protocol = $v['protocol'];
                    if (isset($v['err'])) {
                        $info = TR::t('err_error_nfs__1', $v['err']);
                        $err = $v['err'];
                    }
                } else if ($item_type === self::SELECTED_TYPE_IMAGE_LIB) {
                    $caption = $v['foldername'];
                    $filepath = $k;
                    $icon_file = self::get_folder_icon('imagelib');
                    $type = self::SELECTED_TYPE_FOLDER;
                    $info = TR::t('folder_screen_folder__1', $caption);
                } else if ($item_type === self::SELECTED_TYPE_FOLDER) {
                    if ($k === 'network') {
                        if (!$allow_network) continue;
                        $caption = 'NFS';
                    } else if ($k === 'smb') {
                        if (!$allow_network) continue;
                        $caption = 'SMB';
                    } else if ($k === 'storage') {
                        $caption = TR::load('storage');
                    } else if ($k === 'internal') {
                        $caption = TR::load('internal');
                    } else if ($k === 'imagelib') {
                        if (!$allow_image_lib) continue;
                        $caption = TR::load('image_libs');
                    } else {
                        $caption = $k;
                    }

                    if ($media_url->choose_folder !== false) {
                        if (isset($media_url->extension)) {
                            $info = TR::t('folder_screen_select_file_shows__1', $caption);
                        } else {
                            $info = TR::t('folder_screen_select__1', $caption);
                        }
                    } else {
                        $info = TR::t('folder_screen_folder__1', $caption);
                    }
                    $filepath = $v['filepath'];
                    $icon_file = self::get_folder_icon($k, $filepath);
                    $type = $item_type;
                } else if ($item_type === self::SELECTED_TYPE_FILE) {
                    if (!isset($media_url->extension)) {
                        continue;
                    }

                    $caption = $k;
                    $filepath = $v['filepath'];
                    $size = HD::get_filesize_str($v['size']);
                    $icon_file = self::get_file_icon($filepath);
                    $path_parts = pathinfo($caption);
                    $info = TR::t('folder_screen_select_file__2', $caption, $size);
                    $detailed_icon = $icon_file;

                    if (!isset($path_parts['extension']) || !preg_match("/^$media_url->extension$/i", $path_parts['extension'])) {
                        // skip extension not in allowed list
                        continue;
                    }

                    $type = $media_url->extension;
                } else {
                    // Unknown type. Not supported - not shown
                    continue;
                }

                hd_debug_print("folder type: $item_type folder caption: $caption, path: $filepath, icon: $icon_file", true);
                if (empty($detailed_icon)) {
                    $detailed_icon = str_replace('small_icons', 'large_icons', $icon_file);
                }

                hd_debug_print("detailed icon: $detailed_icon", true);
                $items[] = array(
                    PluginRegularFolderItem::caption => $caption,
                    PluginRegularFolderItem::media_url => MediaURL::encode(
                        array(
                            'screen_id' => static::ID,
                            'caption' => $caption,
                            'source_window_id' => $source_window_id,
                            'filepath' => $filepath,
                            'type' => $type,
                            'ip_path' => $ip_path,
                            'user' => $user,
                            'password' => $password,
                            'nfs_protocol' => $nfs_protocol,
                            'err' => $err,
                            'choose_folder' => $choose_folder,
                            'choose_file' => $choose_file,
                            'action_id' => $action_id,
                            'extension' => $media_url->extension,
                            'windowCounter' => $windowCounter
                        )
                    ),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => $icon_file,
                        ViewItemParams::item_detailed_info => $info,
                        ViewItemParams::item_detailed_icon_path => $detailed_icon
                    )
                );
            }
        }

        if (empty($items)) {
            if (isset($media_url->extension)) {
                $info = TR::t('folder_screen_select_file_shows__1', $media_url->caption);
            } else {
                $info = TR::t('folder_screen_select__1', $media_url->caption);
            }
            $items[] = array(
                PluginRegularFolderItem::media_url => '',
                PluginRegularFolderItem::caption => '',
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => 'gui_skin://small_icons/info.aai',
                    ViewItemParams::item_detailed_icon_path => 'gui_skin://large_icons/info.aai',
                    ViewItemParams::item_detailed_info => $info,
                )
            );
        }

        return array(
            PluginRegularFolderRange::total => count($items),
            PluginRegularFolderRange::more_items_available => false,
            PluginRegularFolderRange::from_ndx => 0,
            PluginRegularFolderRange::count => count($items),
            PluginRegularFolderRange::items => $items
        );
    }

    /**
     * @inheritDoc
     */
    public function get_folder_views()
    {
        return array(
            $this->plugin->get_screen_view('list_1x11_small_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('icons_5x3_caption'),
            $this->plugin->get_screen_view('icons_4x3_caption'),
        );
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param object $plugin_cookies
     * @param array $path
     * @param bool $show_empty
     * @return array
     */
    protected function get_file_list($plugin_cookies, $path, $show_empty = true)
    {
        hd_debug_print(null, true);
        hd_debug_print(json_encode($path), true);

        if (!is_array($path)) {
            $dirs[] = $path;
        } else {
            $dirs = $path;
        }

        $smb_shares = new smb_tree();
        $fileData['folder'] = array();
        $fileData['file'] = array();
        foreach ($dirs as $dir) {
            hd_debug_print("get_file_list dir: $dir", true);
            if ($dir === self::SMB_PATH) {
                if (is_limited_apk()) {
                    $info = 1;
                } else {
                    $info = safe_get_member($plugin_cookies, self::ACTION_SMB_SETUP, 1);
                }

                $s['smb'] = $smb_shares->get_mount_all_smb($info);
                hd_debug_print("smb: " . json_encode($s));
                return $s;
            }

            if ($dir === self::NETWORK_PATH) {
                $s['nfs'] = smb_tree::get_mount_nfs();
                hd_debug_print("nfs: " . json_encode($s));
                return $s;
            }

            $imagelib_path = get_temp_path('imagelib/');
            if ($dir === $imagelib_path) {
                $s = array();
                foreach ($this->plugin->get_image_libs()->get_values() as $item) {
                    $img_path = "$imagelib_path{$item['name']}";
                    create_path($img_path);
                    $s['imagelib'][$img_path]['foldername'] = $item['name'];
                }
                return $s;
            }

            if ($dir === self::SDCARD_PATH) {
                $fileData['folder']['internal']['filepath'] = $dir . '/';
            } else if ($dir === self::IMAGELIB_PATH) {
                $fileData['folder']['imagelib']['filepath'] = get_temp_path('imagelib/');
            } else if ($handle = opendir($dir)) {
                hd_debug_print("opendir: $dir", true);
                if (basename(dirname($dir)) === 'imagelib') {
                    foreach ($this->plugin->get_image_libs()->get_values() as $lib) {
                        if (basename($dir) !== $lib['name']) continue;

                        $need_download = false;
                        $files = glob("$dir/*");
                        if (empty($files)) {
                            $need_download = true;
                        }

                        $package_name = get_temp_path($lib['package']);
                        if ($need_download && !file_exists($package_name)) {
                            list($res, $log) = Curl_Wrapper::simple_download_file($lib['url'], $package_name);
                            if (!$res) {
                                hd_debug_print("can't download image pack: $package_name\n\n$log");
                                break;
                            }
                        }

                        $cmd = "unzip -oq '$package_name' -d '$dir' 2>&1";
                        system($cmd, $ret);
                        if ($ret !== 0) {
                            hd_debug_print("Failed to unpack $package_name (error code: $ret)");
                            break;
                        }
                    }
                }

                while (false !== ($file = readdir($handle))) {
                    if ($file === "." || $file === ".." || strtolower($file) === 'lost.dir') continue;

                    $absolute_filepath = $dir . '/' . $file;
                    if (is_dir($absolute_filepath) === false) {
                        $fileData['file'][$file]['size'] = filesize($absolute_filepath);
                        $fileData['file'][$file]['filepath'] = $absolute_filepath;
                    } else if ($absolute_filepath !== self::NFS_PATH && $absolute_filepath !== self::MNT_PATH . "/D") {
                        $files = glob("$absolute_filepath/*");
                        if (empty($files) && !$show_empty) continue;

                        $fileData['folder'][$file]['filepath'] = $absolute_filepath;
                    }
                }
                closedir($handle);
            }
        }
        return $fileData;
    }

    /**
     * @param string $folder_type
     * @param string $filepath
     * @return string
     */
    protected static function get_folder_icon($folder_type, $filepath = "")
    {
        if ($folder_type === 'storage') {
            $folder_icon = get_image_path('hdd_device.png');
        } else if ($folder_type === 'internal') {
            $folder_icon = get_image_path('internal_storage.png');
        } else if ($folder_type === 'smb') {
            $folder_icon = get_image_path('smb_folder.png');
        } else if ($folder_type === 'smb_folder') {
            $folder_icon = get_image_path('smb_folder.png');
        } else if ($folder_type === 'network') {
            $folder_icon = get_image_path('nfs_folder.png');
        } else if ($folder_type === 'imagelib') {
            $folder_icon = get_image_path('image_folder.png');
        } else if (preg_match("|" . self::STORAGE_PATH . "/usb_storage_[^/]+$|", $filepath)) {
            $folder_icon = get_image_path('usb_device.png');
        } else if (preg_match("|" . self::STORAGE_PATH . "/[^/]+$|", $filepath)) {
            $folder_icon = get_image_path('hdd_device.png');
        } else {
            $folder_icon = get_image_path('folder_icon.png');
        }

        hd_debug_print("folder type: $folder_type, icon: $folder_icon", true);
        return $folder_icon;
    }

    /**
     * @param string $ref
     * @return string
     */
    protected static function get_file_icon($ref)
    {
        if (preg_match('/\.(' . AUDIO_PATTERN . ')$/i', $ref)) {
            $file_icon = get_image_path('audio_file.png');
        } else if (preg_match('/\.(' . VIDEO_PATTERN . ')$/i', $ref)) {
            $file_icon = get_image_path('video_file.png');
        } else if (preg_match('/\.(' . IMAGE_PREVIEW_PATTERN . ')$/i', $ref)) {
            $file_icon = $ref;
        } else if (preg_match('/\.(' . IMAGE_PATTERN . ')$/i', $ref)) {
            $file_icon = get_image_path('image_file.png');
        } else if (preg_match('/\.(' . PLAYLIST_PATTERN . ')$/i', $ref)) {
            $file_icon = get_image_path('playlist_file.png');
        } else if (preg_match('/\.(' . EPG_PATTERN . ')$/i', $ref)) {
            $file_icon = 'gui_skin://small_icons/subtitles_settings.aai';
        } else if (preg_match('/\.txt$/i', $ref)) {
            $file_icon = 'gui_skin://small_icons/language_settings.aai';
        } else if (preg_match('/\.zip$/i', $ref)) {
            $file_icon = get_image_path('zip_file.png');
        } else {
            $file_icon = get_image_path('unknown_file.png');
        }

        return $file_icon;
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function do_action_fs($user_input)
    {
        hd_debug_print(null, true);

        $selected_url = MediaURL::decode($user_input->selected_media_url);
        if ($selected_url->type === self::SELECTED_TYPE_FOLDER) {
            $caption = $selected_url->caption;
            if ($selected_url->err === false) {
                return Action_Factory::open_folder($user_input->selected_media_url, $caption);
            }

            $defs = array();
            if ($selected_url->nfs_protocol !== false) {
                Control_Factory::add_multiline_label($defs, TR::t('err_mount'), $selected_url->err, 3);
                Control_Factory::add_label($defs, TR::t('folder_screen_nfs'), $selected_url->caption);
                Control_Factory::add_label($defs, TR::t('folder_screen_nfs_ip'), $selected_url->ip_path);
                Control_Factory::add_label($defs, TR::t('folder_screen_nfs_protocol'), $selected_url->nfs_protocol);
                Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 300);
                return Action_Factory::show_dialog(TR::t('err_error_nfs'), $defs, true);
            }

            Control_Factory::add_multiline_label($defs, TR::t('err_mount'), $selected_url->err, 4);
            Control_Factory::add_label($defs, TR::t('folder_screen_smb'), $selected_url->caption);
            Control_Factory::add_label($defs, TR::t('folder_screen_smb_ip'), $selected_url->ip_path);

            if (strpos($selected_url->err, "Permission denied") !== false) {
                $user = safe_get_member($selected_url, 'user', '');
                $password = safe_get_member($selected_url, 'password', '');
                $this->GetSMBAccessDefs($defs, $user, $password);
            } else {
                Control_Factory::add_label($defs, '', '');
                Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 300);
            }
            return Action_Factory::show_dialog(TR::t('err_error_smb'), $defs, true, 1100);
        }

        if ($selected_url->choose_file !== false && $selected_url->extension === $selected_url->type) {
            $post_action = User_Input_Handler_Registry::create_screen_action($selected_url->source_window_id, ACTION_FILE_SELECTED,
                '', array('selected_data' => $selected_url->get_media_url_str()));

            return Action_Factory::replace_path(MediaURL::decode($user_input->parent_media_url)->windowCounter, null, $post_action);
        }

        return null;
    }

    /**
     * @param array &$defs
     * @param string $user
     * @param string $password
     */
    protected function GetSMBAccessDefs(&$defs, $user, $password)
    {
        hd_debug_print(null, true);

        Control_Factory::add_text_field($defs, $this, null,
            'new_user',
            TR::t('folder_screen_user'),
            $user, false, false, false, true, 500
        );

        Control_Factory::add_text_field($defs, $this, null,
            'new_pass',
            TR::t('folder_screen_password'),
            $password, false, false, false, true, 500
        );

        Control_Factory::add_custom_close_dialog_and_apply_button($defs,
            self::ACTION_NEW_SMB_DATA,
            TR::t('ok'),
            300,
            User_Input_Handler_Registry::create_action($this, self::ACTION_NEW_SMB_DATA)
        );

        Control_Factory::add_close_dialog_button($defs, TR::t('apply'), 300);
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function do_select_folder($user_input)
    {
        hd_debug_print(null, true);

        $selected_url = MediaURL::decode($user_input->selected_media_url);
        $parent_url = MediaURL::decode($user_input->parent_media_url);

        if ($selected_url->type === self::SELECTED_TYPE_FOLDER) {
            $url = $selected_url;
        } else if ($selected_url->type === self::SELECTED_TYPE_FILE) {
            $url = $parent_url;
        } else {
            return null;
        }

        $post_action = null;
        if ($url->choose_folder !== false) {
            $post_action = User_Input_Handler_Registry::create_screen_action($url->source_window_id,
                ACTION_FOLDER_SELECTED,
                '',
                array('selected_data' => $url->get_media_url_str()));
        }

        return Action_Factory::replace_path($parent_url->windowCounter, null, $post_action);
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function do_reset_folder($user_input)
    {
        hd_debug_print(null, true);

        $parent_url = MediaURL::decode($user_input->parent_media_url);
        $selected_url = MediaURL::decode($user_input->selected_media_url);

        $url = isset($selected_url->filepath) ? $selected_url : $parent_url;

        $post_action = User_Input_Handler_Registry::create_screen_action($url->source_window_id,
            ACTION_RESET_DEFAULT,
            '',
            array('selected_data' => $url->get_media_url_str()));

        return Action_Factory::replace_path($parent_url->windowCounter, null, $post_action);
    }

    /**
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    protected function do_reload_folder($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        array_map('unlink', glob(get_data_path('*.zip')));
        delete_directory(get_temp_path('imagelib'));

        clearstatcache();

        return Action_Factory::invalidate_all_folders($plugin_cookies, array($user_input->parent_media_url));
    }

    /**
     * @return array
     */
    protected function do_get_folder_name_dlg()
    {
        hd_debug_print(null, true);

        $defs = array();
        Control_Factory::add_text_field($defs,
            $this, null,
            self::ACTION_CREATE_FOLDER, '',
            '', false, false, true, true, 1230, false, true
        );
        Control_Factory::add_vgap($defs, 500);
        return Action_Factory::show_dialog(TR::t('folder_screen_choose_name'), $defs, true);
    }

    /**
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array
     */
    protected function do_mkdir($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $parent_url = MediaURL::decode($user_input->parent_media_url);

        if (!create_path($parent_url->filepath . '/' . $user_input->{self::ACTION_CREATE_FOLDER})) {
            return Action_Factory::show_title_dialog(TR::t('err_cant_create_folder'));
        }
        return Action_Factory::invalidate_all_folders($plugin_cookies, array($user_input->parent_media_url));
    }

    /**
     * @param object $user_input
     * @return array
     */
    protected function do_open_folder($user_input)
    {
        $storage_pattern = "|^" . self::STORAGE_PATH . "/|";
        $smb_pattern = "|^" . self::SMB_PATH . "/\d|";

        hd_debug_print(null, true);
        $parent_url = MediaURL::decode($user_input->parent_media_url);
        $path = $parent_url->filepath;
        hd_debug_print("open_folder: $path");
        if (preg_match($storage_pattern, $path)) {
            $path = preg_replace($storage_pattern, 'storage_name://', $path);
        } else if (isset($parent_url->ip_path)) {
            if (preg_match("|^" . self::SMB_PATH . "/|", $path)) {
                if ($parent_url->user !== false && $parent_url->password !== false) {
                    $smb_path = preg_replace($smb_pattern, str_replace('//', '', $parent_url->ip_path), $path);
                    $path = "smb://$parent_url->user:$parent_url->password@$smb_path";
                } else {
                    $path = "smb:" . preg_replace($smb_pattern, $parent_url->ip_path, $path);
                }
            } else if ($parent_url->nfs_protocol !== false && preg_match("|^" . self::NETWORK_PATH . "/|", $path)) {
                $prot = ($parent_url->nfs_protocol === 'tcp') ? 'nfs-tcp://' : 'nfs-udp://';
                $path = $prot . preg_replace("|^" . self::NETWORK_PATH . "/\d|", $parent_url->ip_path . ':/', $path);
            }
        }

        $url = 'embedded_app://{name=file_browser}{url=' . $path . '}{caption=File Browser}';
        hd_debug_print("smt_tree::open_folder launch url: $url", true);
        return Action_Factory::launch_media_url($url);
    }

    /**
     * @param object $user_input
     * @return array
     */
    protected function do_new_smb_data($user_input)
    {
        hd_debug_print(null, true);

        $selected_url = MediaURL::decode($user_input->selected_media_url);

        $new_ip_smb[$selected_url->ip_path]['foldername'] = $selected_url->caption;
        $new_ip_smb[$selected_url->ip_path]['user'] = $user_input->new_user;
        $new_ip_smb[$selected_url->ip_path]['password'] = $user_input->new_pass;
        $q = smb_tree::get_mount_smb($new_ip_smb);
        $key = 'err_' . $selected_url->caption;
        if (isset($q[$key])) {
            $defs = $this->do_get_mount_smb_err_defs($q[$key]['err'],
                $selected_url->caption,
                $selected_url->ip_path,
                $user_input->new_user,
                $user_input->new_pass);
            return Action_Factory::show_dialog(TR::t('err_error_smb'), $defs, true, 1100);
        }

        $caption = $selected_url->caption;
        $selected_url_str = MediaURL::encode(
            array(
                'screen_id' => static::ID,
                'caption' => $selected_url->caption,
                'source_window_id' => $selected_url->source_window_id,
                'filepath' => key($q),
                'type' => $selected_url->type,
                'ip_path' => $selected_url->ip_path,
                'user' => $user_input->new_user,
                'password' => $user_input->new_pass,
                'nfs_protocol' => false,
                'err' => false,
                'choose_folder' => $selected_url->choose_folder,
                'choose_file' => $selected_url->choose_file,
                'extension' => $selected_url->extension,
            )
        );

        return Action_Factory::open_folder($selected_url_str, $caption);
    }

    /**
     * @param string $err
     * @param string $caption
     * @param string $ip_path
     * @param string $user
     * @param string $password
     * @return array
     */
    protected function do_get_mount_smb_err_defs($err, $caption, $ip_path, $user, $password)
    {
        hd_debug_print(null, true);

        $defs = array();
        Control_Factory::add_multiline_label($defs, TR::t('err_mount'), $err, 4);
        Control_Factory::add_label($defs, TR::t('folder_screen_smb'), $caption);
        Control_Factory::add_label($defs, TR::t('folder_screen_smb_ip'), $ip_path);
        if (strpos($err, "Permission denied") !== false) {
            $this->GetSMBAccessDefs($defs, $user, $password);
        } else {
            Control_Factory::add_label($defs, '', '');
            Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 300);
        }
        return $defs;
    }

    /**
     * @param object $plugin_cookies
     * @return array
     */
    protected function do_smb_setup($plugin_cookies)
    {
        hd_debug_print(null, true);

        $attrs['dialog_params'] = array('frame_style' => DIALOG_FRAME_STYLE_GLASS);
        $smb_view = safe_get_member($plugin_cookies, self::ACTION_SMB_SETUP, 1);

        $smb_view_ops[1] = TR::t('folder_screen_net_folders');
        $smb_view_ops[2] = TR::t('folder_screen_net_folders_smb');
        $smb_view_ops[3] = TR::t('folder_screen_search_smb');

        $defs = array();
        Control_Factory::add_combobox($defs, $this, null,
            'smb_view', TR::t('folder_screen_show'),
            $smb_view, $smb_view_ops, 0
        );

        $save_smb_setup = User_Input_Handler_Registry::create_action($this, self::ACTION_SAVE_SMB_SETUP);
        Control_Factory::add_custom_close_dialog_and_apply_button($defs, '_do_save_smb_setup', TR::t('apply'), 250, $save_smb_setup);

        return Action_Factory::show_dialog(TR::t('folder_screen_search_smb_setup'), $defs, true, 1000, $attrs);
    }

    /**
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array
     */
    protected function do_save_smb_setup($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $smb_view_ops = array();
        $smb_view = 1;
        $smb_view_ops[1] = TR::load('folder_screen_net_folders');
        $smb_view_ops[2] = TR::load('folder_screen_net_folders_smb');
        $smb_view_ops[3] = TR::load('folder_screen_search_smb');
        if (isset($user_input->smb_view)) {
            $smb_view = $user_input->smb_view;
            $plugin_cookies->{self::ACTION_SMB_SETUP} = $user_input->smb_view;
        }

        return Action_Factory::show_title_dialog(TR::t('folder_screen_used__1', $smb_view_ops[$smb_view]));
    }
}
