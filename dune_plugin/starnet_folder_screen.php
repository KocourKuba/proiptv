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
    const ACTION_RESET = 'reset_folder';
    const ACTION_CREATE_FOLDER = 'create_folder';
    const ACTION_GET_FOLDER_NAME_DLG = 'get_folder_name';
    const ACTION_DO_MKDIR = 'do_mkdir';
    const ACTION_SMB_SETUP = 'smb_setup';
    const ACTION_NEW_SMB_DATA = 'new_smb_data';
    const ACTION_SAVE_SMB_SETUP = 'save_smb_setup';
    const ACTION_RELOAD_IMAGE_FOLDER = 'reload_image_folder';

    const SELECTED_TYPE_NFS = 'nfs';
    const SELECTED_TYPE_SMB = 'smb';
    const SELECTED_TYPE_SMB_FOLDER = 'smb_folder';
    const SELECTED_TYPE_NFS_FOLDER = 'network';
    const SELECTED_TYPE_STORAGE = 'storage';
    const SELECTED_TYPE_INTERNAL = 'internal';
    const SELECTED_TYPE_FOLDER = 'folder';
    const SELECTED_TYPE_FILE = 'file';
    const SELECTED_TYPE_IMAGE_LIB = 'imagelib';

    const MOUNT_ROOT_PATH = '/tmp/mnt';
    const STORAGE_MOUNT_PATH = '/tmp/mnt/storage';
    const NETWORK_MOUNT_PATH = '/tmp/mnt/network';
    const SMB_MOUNT_PATH = '/tmp/mnt/smb';
    const NFS_MOUNT_PATH = '/tmp/mnt/nfs';
    const SDCARD_PATH = '/sdcard';
    const IMAGELIB_PATH = '/imagelib';

    const PARAM_CAPTION = 'caption';
    const PARAM_CHOOSE_FOLDER = 'choose_folder';
    const PARAM_CHOOSE_FILE = 'choose_file';
    const PARAM_RESET_ACTION = 'reset_action';
    const PARAM_ADD_PARAMS = 'add_params';
    const PARAM_SELECTED_DATA = 'selected_data';
    const PARAM_ALLOW_NETWORK = 'allow_network';
    const PARAM_ALLOW_IMAGE_LIB = 'allow_image_lib';
    const PARAM_READ_ONLY = 'read_only';
    const PARAM_NEW_USER = 'new_user';
    const PARAM_NEW_PASSWORD = 'new_password';
    const PARAM_SIZE = 'size';

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
        $actions[GUI_EVENT_KEY_SETUP] = Action_Factory::replace_path($media_url->{PARAM_WINDOW_COUNTER});

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);

        if (empty($media_url->{PARAM_FILEPATH})) {
            $allow_network = safe_get_member($media_url, self::PARAM_ALLOW_NETWORK, false);
            if ($allow_network && !is_android()) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_SMB_SETUP, TR::t('folder_screen_smb_settings'));
            }

            if ($media_url->{self::PARAM_RESET_ACTION}) {
                $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_RESET, TR::t('reset_default'));
            }

            $allow_image_lib = safe_get_member($media_url, self::PARAM_ALLOW_IMAGE_LIB, false);
            if ($allow_image_lib) {
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_RELOAD_IMAGE_FOLDER, TR::t('refresh'));
            }
        } else {
            if ($media_url->{self::PARAM_CHOOSE_FOLDER} !== false) {
                $actions[GUI_EVENT_KEY_A_RED] = User_Input_Handler_Registry::create_action($this,
                    ACTION_OPEN_FOLDER, TR::t('folder_screen_open_folder'));

                if (!isset($media_url->{self::PARAM_READ_ONLY})) {
                    $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this,
                        self::ACTION_GET_FOLDER_NAME_DLG, TR::t('folder_screen_create_folder'));
                }

                $select_folder = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_SELECT_FOLDER, TR::t('select_folder'));

                $actions[GUI_EVENT_KEY_D_BLUE] = $select_folder;
                $actions[GUI_EVENT_KEY_SELECT] = $select_folder;
            }

            if ($media_url->{PARAM_FILEPATH} !== self::STORAGE_MOUNT_PATH &&
                $media_url->{PARAM_FILEPATH} !== self::NETWORK_MOUNT_PATH &&
                $media_url->{PARAM_FILEPATH} !== self::SMB_MOUNT_PATH &&
                $media_url->{PARAM_FILEPATH} !== self::SDCARD_PATH . "/DuneHD" &&
                $media_url->{PARAM_FILEPATH} !== self::IMAGELIB_PATH) {

                $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);
            }
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
                if (isset($parent_media_url->{PARAM_FILEPATH})
                    && $parent_media_url->{PARAM_FILEPATH} !== self::SMB_MOUNT_PATH
                    && $parent_media_url->{PARAM_FILEPATH} !== self::NETWORK_MOUNT_PATH) {
                    $invalidate = Action_Factory::invalidate_all_folders($plugin_cookies, array($user_input->parent_media_url));
                } else {
                    $invalidate = null;
                }

                return Action_Factory::change_behaviour($actions, 1000, $invalidate);

            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if (!isset($parent_media_url->{PARAM_END_ACTION})) {
                    return Action_Factory::close_and_run();
                }

                hd_debug_print("Call parent: " .
                $parent_media_url->{PARAM_SOURCE_WINDOW_ID} . " action: ". $parent_media_url->{PARAM_END_ACTION}, true);
                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(
                        $parent_media_url->{PARAM_SOURCE_WINDOW_ID},
                        $parent_media_url->{PARAM_END_ACTION},
                        null,
                        array(PARAM_ACTION_ID => $parent_media_url->{PARAM_ACTION_ID})
                    )
                );

            case self::ACTION_FS:
                return $this->do_action_fs($user_input);

            case self::ACTION_SELECT_FOLDER:
                return $this->do_select_folder($user_input);

            case self::ACTION_RESET:
                return $this->do_reset_action($user_input);

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
        hd_debug_print("from_ndx: $from_ndx, MediaURL: " . $media_url->get_media_url_string(true), true);

        $new_media_url = $media_url->duplicate();
        $new_media_url->{PARAM_WINDOW_COUNTER}++;
        $new_media_url->{smb_tree::PARAM_ERR} = false;

        $filepath = isset($media_url->{PARAM_FILEPATH}) ? $media_url->{PARAM_FILEPATH} : false;
        if (empty($filepath)) {
            $dir = array(self::IMAGELIB_PATH, self::MOUNT_ROOT_PATH);
            if (is_android()) {
                $dir[] = self::SDCARD_PATH;
            }
        } else {
            $dir[] = $filepath;
        }

        hd_debug_print("dir: " . json_encode($dir), true);
        $files_list = $this->get_file_list($plugin_cookies, $dir, !safe_get_member($media_url, self::PARAM_CHOOSE_FILE, false));

        $items = array();
        foreach ($files_list as $item_type => $item) {
            if (!empty($filepath) && is_array($item)) {
                ksort($item);
            }

            foreach ($item as $k => $v) {
                hd_debug_print("folder key: $k, value: " . json_encode($v), true);
                $detailed_icon = '';
                if ($item_type === self::SELECTED_TYPE_SMB) {
                    $caption = $v[smb_tree::PARAM_FOLDERNAME];
                    $filepath = $k;
                    $icon_file = self::get_folder_icon(self::SELECTED_TYPE_SMB_FOLDER);
                    $info = TR::t('folder_screen_smb__2', $caption, $v[smb_tree::PARAM_IP]);
                    $type = self::SELECTED_TYPE_FOLDER;
                    $new_media_url->{smb_tree::PARAM_IP} = $v[smb_tree::PARAM_IP];
                    if (!empty($v[smb_tree::PARAM_USER])) {
                        $new_media_url->{smb_tree::PARAM_USER} = $v[smb_tree::PARAM_USER];
                    }
                    if (!empty($v[smb_tree::PARAM_PASSWORD])) {
                        $new_media_url->{smb_tree::PARAM_PASSWORD} = $v[smb_tree::PARAM_PASSWORD];
                    }
                    if (isset($v[smb_tree::PARAM_ERR])) {
                        $info = TR::t('err_error_smb__1', $v[smb_tree::PARAM_ERR]);
                        $new_media_url->{smb_tree::PARAM_ERR} = $v[smb_tree::PARAM_ERR];
                    }
                } else if ($item_type === self::SELECTED_TYPE_NFS) {
                    $caption = $v[smb_tree::PARAM_FOLDERNAME];
                    $filepath = $k;
                    $icon_file = self::get_folder_icon('nfs_folder');
                    $info = TR::t('folder_screen_nfs__2', $caption, $v[smb_tree::PARAM_IP]);
                    $type = self::SELECTED_TYPE_FOLDER;
                    $new_media_url->{smb_tree::PARAM_IP} = $v[smb_tree::PARAM_IP];
                    $new_media_url->{smb_tree::PARAM_PROTOCOL} = $v[smb_tree::PARAM_PROTOCOL];
                    if (isset($v[smb_tree::PARAM_ERR])) {
                        $info = TR::t('err_error_nfs__1', $v[smb_tree::PARAM_ERR]);
                        $new_media_url->{smb_tree::PARAM_ERR} = $v[smb_tree::PARAM_ERR];
                    }
                } else if ($item_type === self::SELECTED_TYPE_IMAGE_LIB) {
                    $caption = $v[smb_tree::PARAM_FOLDERNAME];
                    $filepath = $k;
                    $icon_file = self::get_folder_icon(self::SELECTED_TYPE_IMAGE_LIB);
                    $type = self::SELECTED_TYPE_FOLDER;
                    $info = TR::t('folder_screen_folder__1', $caption);
                } else if ($item_type === self::SELECTED_TYPE_FOLDER) {
                    $allow_network = safe_get_member($new_media_url, self::PARAM_ALLOW_NETWORK, false);
                    if ($k === self::SELECTED_TYPE_NFS_FOLDER) {
                        if (!$allow_network) continue;
                        $caption = 'NFS';
                    } else if ($k === self::SELECTED_TYPE_SMB) {
                        if (!$allow_network) continue;
                        $caption = 'SMB';
                    } else if ($k === self::SELECTED_TYPE_STORAGE) {
                        $caption = TR::load('storage');
                    } else if ($k === self::SELECTED_TYPE_INTERNAL) {
                        $caption = TR::load('internal');
                    } else if ($k === self::SELECTED_TYPE_IMAGE_LIB) {
                        if (!safe_get_member($new_media_url, self::PARAM_ALLOW_IMAGE_LIB, false)) continue;
                        $caption = TR::load('image_libs');
                    } else {
                        $caption = $k;
                    }

                    if ($media_url->{self::PARAM_CHOOSE_FOLDER} !== false) {
                        if (empty($new_media_url->{PARAM_EXTENSION})) {
                            $info = TR::t('folder_screen_select__1', $caption);
                        } else {
                            $info = TR::t('folder_screen_select_file_shows__1', $caption);
                        }
                    } else {
                        $info = TR::t('folder_screen_folder__1', $caption);
                    }
                    $filepath = $v[PARAM_FILEPATH];
                    $icon_file = self::get_folder_icon($k, $filepath);
                    $type = $item_type;
                } else if ($item_type === self::SELECTED_TYPE_FILE) {
                    if (empty($new_media_url->{PARAM_EXTENSION})) {
                        continue;
                    }

                    $caption = $k;
                    $filepath = $v[PARAM_FILEPATH];
                    $size = HD::get_filesize_str($v[self::PARAM_SIZE]);
                    $icon_file = self::get_file_icon($filepath);
                    $path_parts = pathinfo($caption);
                    $info = TR::t('folder_screen_select_file__2', $caption, $size);
                    $detailed_icon = $icon_file;

                    if (!isset($path_parts[PARAM_EXTENSION]) || !preg_match("/^" . $new_media_url->{PARAM_EXTENSION} . "$/i", $path_parts[PARAM_EXTENSION])) {
                        // skip extension not in allowed list
                        continue;
                    }

                    $type = $new_media_url->{PARAM_EXTENSION};
                } else {
                    // Unknown type. Not supported - not shown
                    continue;
                }

                hd_debug_print("folder type: $item_type folder caption: $caption, path: $filepath, icon: $icon_file", true);
                if (empty($detailed_icon)) {
                    $detailed_icon = str_replace('small_icons', 'large_icons', $icon_file);
                }

                $new_media_url->{self::PARAM_CAPTION} = $caption;
                $new_media_url->{PARAM_FILEPATH} = $filepath;
                $new_media_url->{PARAM_TYPE} = $type;
                hd_debug_print("detailed icon: $detailed_icon", true);
                $items[] = array(
                    PluginRegularFolderItem::caption => $caption,
                    PluginRegularFolderItem::media_url => $new_media_url->get_media_url_string(),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => $icon_file,
                        ViewItemParams::item_detailed_info => $info,
                        ViewItemParams::item_detailed_icon_path => $detailed_icon
                    )
                );
            }
        }

        if (empty($items)) {
            if (isset($media_url->{PARAM_EXTENSION})) {
                $info = TR::t('folder_screen_select_file_shows__1', $media_url->{self::PARAM_CAPTION});
            } else {
                $info = TR::t('folder_screen_select__1', $media_url->{self::PARAM_CAPTION});
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
        $fileData[self::SELECTED_TYPE_FOLDER] = array();
        $fileData[self::SELECTED_TYPE_FILE] = array();
        foreach ($dirs as $dir) {
            hd_debug_print("get_file_list dir: $dir", true);
            if ($dir === self::SMB_MOUNT_PATH) {
                if (is_limited_apk()) {
                    $info = 1;
                } else {
                    $info = safe_get_member($plugin_cookies, self::ACTION_SMB_SETUP, 1);
                }

                $s[self::SELECTED_TYPE_SMB] = $smb_shares->get_mount_all_smb($info);
                hd_debug_print("smb: " . json_encode($s));
                return $s;
            }

            if ($dir === self::NETWORK_MOUNT_PATH) {
                $s[self::SELECTED_TYPE_NFS] = smb_tree::get_mount_nfs();
                hd_debug_print("nfs: " . json_encode($s));
                return $s;
            }

            $imagelib_path = get_temp_path('imagelib/');
            if ($dir === $imagelib_path) {
                $s = array();
                foreach ($this->plugin->get_image_libs()->get_values() as $item) {
                    $img_path = "$imagelib_path{$item[PARAM_NAME]}";
                    create_path($img_path);
                    $s[self::SELECTED_TYPE_IMAGE_LIB][$img_path][smb_tree::PARAM_FOLDERNAME] = $item[PARAM_NAME];
                }
                return $s;
            }

            if ($dir === self::SDCARD_PATH) {
                $fileData[self::SELECTED_TYPE_FOLDER][self::SELECTED_TYPE_INTERNAL][PARAM_FILEPATH] = $dir . '/';
            } else if ($dir === self::IMAGELIB_PATH) {
                $fileData[self::SELECTED_TYPE_FOLDER][self::SELECTED_TYPE_IMAGE_LIB][PARAM_FILEPATH] = get_temp_path('imagelib/');
            } else if ($handle = @opendir($dir)) {
                hd_debug_print("opendir: $dir", true);
                if (basename(dirname($dir)) === self::SELECTED_TYPE_IMAGE_LIB) {
                    foreach ($this->plugin->get_image_libs()->get_values() as $lib) {
                        if (basename($dir) !== $lib[PARAM_NAME]) continue;

                        $need_download = false;
                        $files = glob("$dir/*");
                        if (empty($files)) {
                            $need_download = true;
                        }

                        $package_name = get_temp_path($lib['package']);
                        if ($need_download && !file_exists($package_name)) {
                            $res = Curl_Wrapper::simple_download_file($lib['url'], $package_name);
                            if (!$res) {
                                hd_debug_print("can't download image pack: $package_name");
                                break;
                            }
                        }

                        $cmd = "unzip -oq '$package_name' -d '$dir' 2>&1";
                        /** @var int $ret */
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
                        $fileData[self::SELECTED_TYPE_FILE][$file][self::PARAM_SIZE] = filesize($absolute_filepath);
                        $fileData[self::SELECTED_TYPE_FILE][$file][PARAM_FILEPATH] = $absolute_filepath;
                    } else if ($absolute_filepath !== self::NFS_MOUNT_PATH && $absolute_filepath !== self::MOUNT_ROOT_PATH . "/D") {
                        $files = glob("$absolute_filepath/*");
                        if (empty($files) && !$show_empty) continue;

                        $fileData[self::SELECTED_TYPE_FOLDER][$file][PARAM_FILEPATH] = $absolute_filepath;
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
        if ($folder_type === self::SELECTED_TYPE_STORAGE) {
            $folder_icon = get_image_path('hdd_device.png');
        } else if ($folder_type === self::SELECTED_TYPE_INTERNAL) {
            $folder_icon = get_image_path('internal_storage.png');
        } else if ($folder_type === self::SELECTED_TYPE_SMB) {
            $folder_icon = get_image_path('smb_folder.png');
        } else if ($folder_type === self::SELECTED_TYPE_SMB_FOLDER) {
            $folder_icon = get_image_path('smb_folder.png');
        } else if ($folder_type === self::SELECTED_TYPE_NFS_FOLDER) {
            $folder_icon = get_image_path('nfs_folder.png');
        } else if ($folder_type === self::SELECTED_TYPE_IMAGE_LIB) {
            $folder_icon = get_image_path('image_folder.png');
        } else if (preg_match("|" . self::STORAGE_MOUNT_PATH . "/usb_storage_[^/]+$|", $filepath)) {
            $folder_icon = get_image_path('usb_device.png');
        } else if (preg_match("|" . self::STORAGE_MOUNT_PATH . "/[^/]+$|", $filepath)) {
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

        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        if ($selected_media_url->{PARAM_TYPE} === self::SELECTED_TYPE_FOLDER) {
            $caption = $selected_media_url->{self::PARAM_CAPTION};
            if ($selected_media_url->{smb_tree::PARAM_ERR} === false) {
                return Action_Factory::open_folder($user_input->selected_media_url, $caption);
            }

            $defs = array();
            if ($selected_media_url->{smb_tree::PARAM_NFS_PROTOCOL} !== false) {
                Control_Factory::add_multiline_label($defs, TR::t('err_mount'), $selected_media_url->{smb_tree::PARAM_ERR}, 3);
                Control_Factory::add_label($defs, TR::t('folder_screen_nfs'), $selected_media_url->{self::PARAM_CAPTION});
                Control_Factory::add_label($defs, TR::t('folder_screen_nfs_ip'), $selected_media_url->{smb_tree::PARAM_IP_PATH});
                Control_Factory::add_label($defs, TR::t('folder_screen_nfs_protocol'), $selected_media_url->{smb_tree::PARAM_NFS_PROTOCOL});
                Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 300);
                return Action_Factory::show_dialog(TR::t('err_error_nfs'), $defs, true);
            }

            Control_Factory::add_multiline_label($defs, TR::t('err_mount'), $selected_media_url->{smb_tree::PARAM_ERR}, 4);
            Control_Factory::add_label($defs, TR::t('folder_screen_smb'), $selected_media_url->{self::PARAM_CAPTION});
            Control_Factory::add_label($defs, TR::t('folder_screen_smb_ip'), $selected_media_url->{smb_tree::PARAM_IP_PATH});

            if (strpos($selected_media_url->{smb_tree::PARAM_ERR}, "Permission denied") !== false) {
                $user = safe_get_member($selected_media_url, smb_tree::PARAM_USER, '');
                $password = safe_get_member($selected_media_url, smb_tree::PARAM_PASSWORD, '');
                $this->GetSMBAccessDefs($defs, $user, $password);
            } else {
                Control_Factory::add_label($defs, '', '');
                Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 300);
            }
            return Action_Factory::show_dialog(TR::t('err_error_smb'), $defs, true, 1100);
        }

        if ($selected_media_url->{self::PARAM_CHOOSE_FILE} !== false && $selected_media_url->{PARAM_EXTENSION} === $selected_media_url->{PARAM_TYPE}) {
            $post_action = User_Input_Handler_Registry::create_screen_action($selected_media_url->{PARAM_SOURCE_WINDOW_ID},
                $selected_media_url->{self::PARAM_CHOOSE_FILE},
                '',
                array(self::PARAM_SELECTED_DATA => $selected_media_url->get_media_url_string()));

            return Action_Factory::replace_path(MediaURL::decode($user_input->parent_media_url)->{PARAM_WINDOW_COUNTER}, null, $post_action);
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
            self::PARAM_NEW_USER,
            TR::t('folder_screen_user'),
            $user, false, false, false, true, 500
        );

        Control_Factory::add_text_field($defs, $this, null,
            self::PARAM_NEW_PASSWORD,
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

        if ($selected_url->{PARAM_TYPE} === self::SELECTED_TYPE_FOLDER) {
            $url = $selected_url;
        } else if ($selected_url->{PARAM_TYPE} === self::SELECTED_TYPE_FILE) {
            $url = $parent_url;
        } else {
            return null;
        }

        $post_action = null;
        if ($url->{self::PARAM_CHOOSE_FOLDER} !== false) {
            $post_action = User_Input_Handler_Registry::create_screen_action(
                $selected_url->{PARAM_SOURCE_WINDOW_ID},
                $url->{self::PARAM_CHOOSE_FOLDER},
                '',
                array(self::PARAM_SELECTED_DATA => $url->get_media_url_string()));
        }

        return Action_Factory::replace_path($parent_url->{PARAM_WINDOW_COUNTER}, null, $post_action);
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function do_reset_action($user_input)
    {
        hd_debug_print(null, true);

        $parent_url = MediaURL::decode($user_input->parent_media_url);
        $selected_url = MediaURL::decode($user_input->selected_media_url);

        $url = isset($selected_url->{PARAM_FILEPATH}) ? $selected_url : $parent_url;

        $post_action = User_Input_Handler_Registry::create_screen_action($url->{PARAM_SOURCE_WINDOW_ID},
            $selected_url->{self::PARAM_RESET_ACTION},
            '',
            array(self::PARAM_SELECTED_DATA => $url->get_media_url_string()));

        return Action_Factory::replace_path($parent_url->{PARAM_WINDOW_COUNTER}, null, $post_action);
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

        if (!create_path($parent_url->{PARAM_FILEPATH} . '/' . $user_input->{self::ACTION_CREATE_FOLDER})) {
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
        $storage_pattern = "|^" . self::STORAGE_MOUNT_PATH . "/|";
        $smb_pattern = "|^" . self::SMB_MOUNT_PATH . "/\d|";

        hd_debug_print(null, true);
        $parent_url = MediaURL::decode($user_input->parent_media_url);
        $path = $parent_url->{PARAM_FILEPATH};
        hd_debug_print("open_folder: $path");
        if (preg_match($storage_pattern, $path)) {
            $path = preg_replace($storage_pattern, 'storage_name://', $path);
        } else if (isset($parent_url->{smb_tree::PARAM_IP_PATH})) {
            if (preg_match("|^" . self::SMB_MOUNT_PATH . "/|", $path)) {
                if ($parent_url->{smb_tree::PARAM_USER} !== false && $parent_url->{smb_tree::PARAM_PASSWORD} !== false) {
                    $smb_path = preg_replace($smb_pattern, str_replace('//', '', $parent_url->{smb_tree::PARAM_IP_PATH}), $path);
                    $path = "smb://" . $parent_url->{smb_tree::PARAM_USER} . ':' . $parent_url->{smb_tree::PARAM_PASSWORD} . "@$smb_path";
                } else {
                    $path = "smb:" . preg_replace($smb_pattern, $parent_url->{smb_tree::PARAM_IP_PATH}, $path);
                }
            } else if ($parent_url->{smb_tree::PARAM_NFS_PROTOCOL} !== false && preg_match("|^" . self::NETWORK_MOUNT_PATH . "/|", $path)) {
                $prot = ($parent_url->{smb_tree::PARAM_NFS_PROTOCOL} === smb_tree::PROTOCOL_TCP) ? 'nfs-tcp://' : 'nfs-udp://';
                $path = $prot . preg_replace("|^" . self::NETWORK_MOUNT_PATH . "/\d|", $parent_url->{smb_tree::PARAM_IP_PATH} . ':/', $path);
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

        $ip_path = $selected_url->{smb_tree::PARAM_IP_PATH};
        $new_ip_smb[$ip_path][smb_tree::PARAM_FOLDERNAME] = $selected_url->{self::PARAM_CAPTION};
        $new_ip_smb[$ip_path][smb_tree::PARAM_USER] = $user_input->{self::PARAM_NEW_USER};
        $new_ip_smb[$ip_path][smb_tree::PARAM_PASSWORD] = $user_input->{self::PARAM_NEW_PASSWORD};
        $q = smb_tree::get_mount_smb($new_ip_smb);
        $key = 'err_' . $selected_url->{PARAM_CANCEL_ACTION};
        if (isset($q[$key])) {
            $defs = $this->do_get_mount_smb_err_defs($q[$key][smb_tree::PARAM_ERR],
                $selected_url->{self::PARAM_CAPTION},
                $ip_path,
                $user_input->{self::PARAM_NEW_USER},
                $user_input->{self::PARAM_NEW_PASSWORD});
            return Action_Factory::show_dialog(TR::t('err_error_smb'), $defs, true, 1100);
        }

        $selected_url->{PARAM_FILEPATH} = key($q);
        $selected_url->{smb_tree::PARAM_USER} = $user_input->{self::PARAM_NEW_USER};
        $selected_url->{smb_tree::PARAM_PASSWORD} = $user_input->{self::PARAM_NEW_PASSWORD};
        $selected_url->{smb_tree::PARAM_NFS_PROTOCOL} = false;
        $selected_url->{smb_tree::PARAM_ERR} = false;

        return Action_Factory::open_folder($selected_url->get_media_url_string(), $selected_url->{self::PARAM_CAPTION});
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
