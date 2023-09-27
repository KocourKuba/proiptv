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

    const SELECTED_TYPE_NFS = 'nfs';
    const SELECTED_TYPE_SMB = 'smb';
    const SELECTED_TYPE_FOLDER = 'folder';
    const SELECTED_TYPE_FILE = 'file';

    private $counter = 0;

    /**
     * @param string $caption
     * @param string $source_window_id
     * @param string $filepath
     * @param string $type
     * @param string $ip_path
     * @param string $user
     * @param string $password
     * @param string $nfs_protocol
     * @param string $err
     * @param array $choose_folder
     * @param string $choose_file
     * @param int|null $windowCounter
     * @return false|string
     */
    protected static function get_media_url_string($caption, $source_window_id, $filepath, $type, $ip_path, $user, $password, $nfs_protocol, $err, $choose_folder, $choose_file, $windowCounter = null)
    {
        return MediaURL::encode
        (
            array
            (
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
                'windowCounter' => $windowCounter
            )
        );
    }

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

        if (empty($media_url->filepath)) {
            if ($media_url->allow_network) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_SMB_SETUP, TR::t('folder_screen_smb_settings'));
            }

            if ($media_url->allow_reset) {
                $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this,
                    self::ACTION_RESET_FOLDER, TR::t('reset_default'));
            }
        } else if ($media_url->filepath !== '/tmp/mnt/storage' &&
            $media_url->filepath !== '/tmp/mnt/network' &&
            $media_url->filepath !== '/tmp/mnt/smb' &&
            $media_url->filepath !== '/sdcard/DuneHD') {

            if (isset($media_url->choose_folder) && $media_url->choose_folder !== false) {
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

            if (isset($media_url->choose_file) && $media_url->choose_file !== false) {
                $actions[GUI_EVENT_KEY_D_BLUE] = $fs_action;
            }

            $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);
        }

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("from_ndx: $from_ndx, MediaURL: $media_url", true);

        $err = false;
        $source_window_id = isset($media_url->source_window_id) ? $media_url->source_window_id : false;
        $dir = empty($media_url->filepath) ? (is_android() ? array("/tmp/mnt", "/sdcard") : "/tmp/mnt") : $media_url->filepath;
        $allow_network = !isset($media_url->allow_network) || $media_url->allow_network;
        $windowCounter = isset($media_url->windowCounter) ? $media_url->windowCounter + 1 : 2;
        $ip_path = isset($media_url->ip_path) ? $media_url->ip_path : false;
        $nfs_protocol = isset($media_url->nfs_protocol) ? $media_url->nfs_protocol : false;
        $user = isset($media_url->user) ? $media_url->user : false;
        $password = isset($media_url->password) ? $media_url->password : false;
        $choose_folder = isset($media_url->choose_folder) ? $media_url->choose_folder : false;
        $choose_file = isset($media_url->choose_file) ? $media_url->choose_file : false;

        $items = array();
        foreach (self::get_file_list($plugin_cookies, $dir) as $item_type => $item) {
            if (isset($media_url->filepath)) {
                ksort($item);
            }

            foreach ($item as $k => $v) {
                $detailed_icon = '';
                if ($item_type === self::SELECTED_TYPE_SMB) {
                    $caption = $v['foldername'];
                    $filepath = $k;
                    $icon_file = self::get_folder_icon('smb_folder', $filepath);
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
                    $icon_file = self::get_folder_icon('smb_folder', $filepath);
                    $info = TR::t('folder_screen_nfs__2', $caption, $v['ip']);
                    $type = self::SELECTED_TYPE_FOLDER;
                    $ip_path = $v['ip'];
                    $nfs_protocol = $v['protocol'];
                    if (isset($v['err'])) {
                        $info = TR::t('err_error_nfs__1', $v['err']);
                        $err = $v['err'];
                    }
                } else if ($item_type === self::SELECTED_TYPE_FOLDER) {
                    if ($k === 'network') {
                        if (!$allow_network) continue;
                        $caption = 'NFS';
                    } else if ($k === 'smb') {
                        if (!$allow_network) continue;
                        $caption = 'SMB';
                    } else if ($k === 'storage') {
                        $caption = TR::t('storage');
                    } else if ($k === 'internal') {
                        $caption = TR::t('internal');
                    } else {
                        $caption = $k;
                    }

                    if (isset($media_url->choose_folder)) {
                        if (isset($media_url->choose_folder->extension)) {
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
                    if (!isset($media_url->choose_file->extension)
                        && !isset($media_url->choose_folder->extension)) {
                        continue;
                    }

                    $caption = $k;
                    $filepath = $v['filepath'];
                    $size = HD::get_filesize_str($v['size']);
                    $icon_file = self::get_file_icon($filepath);
                    $info = TR::t('folder_screen_file__2', $caption, $size);
                    $type = $item_type;
                    $path_parts = pathinfo($caption);
                    if (isset($media_url->choose_file->extension)) {
                        $info = TR::t('folder_screen_select_file__2', $caption, $size);
                        $detailed_icon = $icon_file;

                        if (!isset($path_parts['extension'])
                            || !preg_match("/^{$media_url->choose_file->extension}$/i", $path_parts['extension'])) {
                            // skip extension not in allowed list
                            continue;
                        }

                        $type = $media_url->choose_file->extension;
                    }
                } else {
                    // Unknown type. Not supported - not shown
                    continue;
                }

                hd_debug_print("folder type: $item_type folder caption: $caption, path: $filepath, icon: $icon_file", true);
                if (empty($detailed_icon)) {
                    $detailed_icon = str_replace('small_icons', 'large_icons', $icon_file);
                }

                hd_debug_print("detailed icon: $detailed_icon", true);
                $items[] = array
                (
                    PluginRegularFolderItem::caption => $caption,
                    PluginRegularFolderItem::media_url => self::get_media_url_string(
                        $caption,
                        $source_window_id,
                        $filepath,
                        $type,
                        $ip_path,
                        $user,
                        $password,
                        $nfs_protocol,
                        $err,
                        $choose_folder,
                        $choose_file,
                        $windowCounter
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
            if (isset($media_url->choose_folder->extension) || isset($media_url->choose_file->extension)) {
                $info = TR::t('folder_screen_select_file_shows__1', $media_url->caption);
            } else {
                $info = TR::t('folder_screen_select__1', $media_url->caption);
            }
            $items[] = array(
                PluginRegularFolderItem::caption => '',
                PluginRegularFolderItem::media_url => '',
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => 'gui_skin://small_icons/info.aai',
                    ViewItemParams::item_detailed_icon_path => 'gui_skin://large_icons/info.aai',
                    ViewItemParams::item_detailed_info => $info,
                )
            );
        }

        //hd_debug_print("folder items: " . count($items));
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
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        switch ($user_input->control_id) {
            case GUI_EVENT_TIMER:
                $parent_url = MediaURL::decode($user_input->parent_media_url);
                $actions = $this->get_action_map($parent_url, $plugin_cookies);
                if (isset($parent_url->filepath)
                    && $parent_url->filepath !== '/tmp/mnt/smb'
                    && $parent_url->filepath !== '/tmp/mnt/network') {
                    $invalidate = Starnet_Epfs_Handler::invalidate_folders(array($user_input->parent_media_url));
                } else {
                    $invalidate = null;
                }

                return Action_Factory::change_behaviour($actions, 1000, $invalidate);

            case self::ACTION_FS:
                return $this->do_action_fs($user_input);

            case self::ACTION_SELECT_FOLDER:
                return $this->do_select_folder($user_input);

            case self::ACTION_RESET_FOLDER:
                return $this->do_reset_folder($user_input);

            case self::ACTION_GET_FOLDER_NAME_DLG:
                return $this->do_get_folder_name_dlg();

            case self::ACTION_CREATE_FOLDER:
                return Action_Factory::close_dialog_and_run(User_Input_Handler_Registry::create_action($this, self::ACTION_DO_MKDIR));

            case self::ACTION_DO_MKDIR:
                return $this->do_mkdir($user_input);

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
    public function get_folder_views()
    {
        hd_debug_print(null, true);

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
     * @param $plugin_cookies
     * @param array $path
     * @return array
     */
    protected static function get_file_list($plugin_cookies, $path)
    {
        hd_debug_print(null, true);

        if (!is_array($path)) {
            $dirs[] = $path;
        } else {
            $dirs = $path;
        }

        $smb_shares = new smb_tree();
        $fileData['folder'] = array();
        $fileData['file'] = array();
        foreach ($dirs as $dir) {
            if ($dir === '/tmp/mnt/smb') {
                $info = isset($plugin_cookies->{self::ACTION_SMB_SETUP}) ? (int)$plugin_cookies->{self::ACTION_SMB_SETUP} : 1;
                $s['smb'] = $smb_shares->get_mount_all_smb($info);
                return $s;
            }

            if ($dir === '/tmp/mnt/network') {
                $s['nfs'] = $smb_shares::get_mount_nfs();
                return $s;
            }

            if ($dir === '/sdcard') {
                $fileData['folder']['internal']['filepath'] = $dir . DIRECTORY_SEPARATOR;
            } else if ($handle = opendir($dir)) {
                $bug_kind = get_bug_platform_kind();
                while (false !== ($file = readdir($handle))) {
                    if ($file === "." || $file === "..") continue;

                    $absolute_filepath = $dir . DIRECTORY_SEPARATOR . $file;
                    $is_match = preg_match('|^/tmp/mnt/smb/|', $absolute_filepath);
                    $is_dir = $bug_kind && $is_match ? (bool)trim(shell_exec("test -d \"$absolute_filepath\" && echo 1 || echo 0")) : is_dir($absolute_filepath);

                    if ($is_dir === false) {
                        $fileData['file'][$file]['size'] = ($bug_kind && $is_match) ? '' : filesize($absolute_filepath);
                        $fileData['file'][$file]['filepath'] = $absolute_filepath;
                    } else if ($absolute_filepath !== '/tmp/mnt/nfs' && $absolute_filepath !== '/tmp/mnt/D') {
                        $fileData['folder'][$file]['filepath'] = $absolute_filepath;
                    }
                }
                closedir($handle);
            }
        }
        return $fileData;
    }

    /**
     * @param string $ref
     * @return string
     */
    protected static function get_file_icon($ref)
    {
        if (preg_match('/\.(' . AUDIO_PATTERN . ')$/i', $ref)) {
            $file_icon = 'gui_skin://small_icons/audio_file.aai';
        } else if (preg_match('/\.(' . VIDEO_PATTERN . ')$/i', $ref)) {
            $file_icon = 'gui_skin://small_icons/video_file.aai';
        } else if (preg_match('/\.(' . IMAGE_PREVIEW_PATTERN . ')$/i', $ref)) {
            $file_icon = $ref;
        } else if (preg_match('/\.(' . IMAGE_PATTERN . ')$/i', $ref)) {
            $file_icon = 'gui_skin://small_icons/image_file.aai';
        } else if (preg_match('/\.(' . PLAYLIST_PATTERN . ')$/i', $ref)) {
            $file_icon = 'gui_skin://small_icons/playlist_file.aai';
        } else if (preg_match('/\.(' . EPG_PATTERN . ')$/i', $ref)) {
            $file_icon = 'gui_skin://small_icons/subtitles_settings.aai';
        } else if (preg_match('/\.txt$/i', $ref)) {
            $file_icon = 'gui_skin://small_icons/language_settings.aai';
        } else if (preg_match('/\.zip$/i', $ref)) {
            $file_icon = 'gui_skin://small_icons/apps.aai';
        } else {
            $file_icon = 'gui_skin://small_icons/unknown_file.aai';
        }

        return $file_icon;
    }

    /**
     * @param string $folder_type
     * @param string $filepath
     * @return string
     */
    protected static function get_folder_icon($folder_type, $filepath)
    {
        if ($folder_type === 'storage') {
            $folder_icon = "gui_skin://small_icons/sd_card.aai";
        } else if ($folder_type === 'internal') {
            $folder_icon = "gui_skin://small_icons/system_storage.aai";
        } else if ($folder_type === 'smb') {
            $folder_icon = "gui_skin://small_icons/smb.aai";
        } else if ($folder_type === 'smb_folder') {
            $folder_icon = "gui_skin://small_icons/network_folder.aai";
        } else if ($folder_type === 'network') {
            $folder_icon = "gui_skin://small_icons/network.aai";
        } else if (preg_match("|/tmp/mnt/storage/usb_storage_[^/]+$|", $filepath)) {
            $folder_icon = "gui_skin://small_icons/usb.aai";
        } else if (preg_match("|/tmp/mnt/storage/[^/]+$|", $filepath)) {
            $folder_icon = "gui_skin://small_icons/hdd.aai";
        } else {
            $folder_icon = "gui_skin://small_icons/folder.aai";
        }

        return $folder_icon;
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
        if (strpos("Permission denied", $err) !== false) {
            $this->GetSMBAccessDefs($defs, $user, $password);
        } else {
            Control_Factory::add_label($defs, '', '');
            Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 300);
        }
        return $defs;
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
            $user, 0, 0, 0, 1, 500
        );

        Control_Factory::add_text_field($defs, $this, null,
            'new_pass',
            TR::t('folder_screen_password'),
            $password, 0, 0, 0, 1, 500
        );

        Control_Factory::add_custom_close_dialog_and_apply_buffon($defs,
            self::ACTION_NEW_SMB_DATA, TR::t('ok'), 300,
            User_Input_Handler_Registry::create_action($this, self::ACTION_NEW_SMB_DATA)
        );

        Control_Factory::add_close_dialog_button($defs, TR::t('apply'), 300);
    }

    /**
     * @param $user_input
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

            if (strpos("Permission denied", $selected_url->err) !== false) {
                $user = isset($selected_url->user) ? $selected_url->user : '';
                $password = isset($selected_url->password) ? $selected_url->password : '';
                $this->GetSMBAccessDefs($defs, $user, $password);
            } else {
                Control_Factory::add_label($defs, '', '');
                Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 300);
            }
            return Action_Factory::show_dialog(TR::t('err_error_smb'), $defs, true, 1100);
        }

        if ($selected_url->choose_file !== false && $selected_url->choose_file->extension === $selected_url->type) {
            $post_action = User_Input_Handler_Registry::create_action_screen($selected_url->source_window_id, ACTION_FILE_SELECTED,
                '', array('selected_data' => $selected_url->get_media_url_str()));

            return Action_Factory::replace_path(MediaURL::decode($user_input->parent_media_url)->windowCounter, null, $post_action);
        }

        return null;
    }

    /**
     * @param $user_input
     * @return array
     */
    protected function do_open_folder($user_input)
    {
        hd_debug_print(null, true);

        $parent_url = MediaURL::decode($user_input->parent_media_url);
        $path = $parent_url->filepath;
        hd_debug_print("open_folder: $path");
        if (preg_match('|^/tmp/mnt/storage/|', $path)) {
            $path = preg_replace('|^/tmp/mnt/storage/|', 'storage_name://', $path);
        } else if (isset($parent_url->ip_path)) {
            if (preg_match('|^/tmp/mnt/smb/|', $path)) {
                if ($parent_url->user !== false && $parent_url->password !== false) {
                    $path = 'smb://' . $parent_url->user . ':' . $parent_url->password . '@' . preg_replace("|^/tmp/mnt/smb/\d|", str_replace('//', '', $parent_url->ip_path), $path);
                } else {
                    $path = 'smb:' . preg_replace("|^/tmp/mnt/smb/\d|", $parent_url->ip_path, $path);
                }
            } else if ($parent_url->nfs_protocol !== false && preg_match('|^/tmp/mnt/network/|', $path)) {
                $prot = ($parent_url->nfs_protocol === 'tcp') ? 'nfs-tcp://' : 'nfs-udp://';
                $path = $prot . preg_replace("|^/tmp/mnt/network/\d|", $parent_url->ip_path . ':/', $path);
            }
        }

        $url = 'embedded_app://{name=file_browser}{url=' . $path . '}{caption=File Browser}';
        hd_debug_print("smt_tree::open_folder launch url: $url", true);
        return Action_Factory::launch_media_url($url);
    }

    /**
     * @param $user_input
     * @return array
     */
    protected function do_new_smb_data($user_input)
    {
        hd_debug_print(null, true);

        $selected_url = MediaURL::decode($user_input->selected_media_url);

        $smb_shares = new smb_tree();
        $new_ip_smb[$selected_url->ip_path]['foldername'] = $selected_url->caption;
        $new_ip_smb[$selected_url->ip_path]['user'] = $user_input->new_user;
        $new_ip_smb[$selected_url->ip_path]['password'] = $user_input->new_pass;
        $q = $smb_shares::get_mount_smb($new_ip_smb);
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
        $selected_url_str = self::get_media_url_string(
            $selected_url->caption,
            $selected_url->source_window_id,
            key($q),
            $selected_url->type,
            $selected_url->ip_path,
            $user_input->new_user,
            $user_input->new_pass,
            false,
            false,
            $selected_url->choose_folder,
            $selected_url->choose_file
        );
        return Action_Factory::open_folder($selected_url_str, $caption);
    }

    /**
     * @param $plugin_cookies
     * @return array
     */
    protected function do_smb_setup($plugin_cookies)
    {
        hd_debug_print(null, true);

        $attrs['dialog_params'] = array('frame_style' => DIALOG_FRAME_STYLE_GLASS);
        $smb_view = isset($plugin_cookies->{self::ACTION_SMB_SETUP}) ? (int)$plugin_cookies->{self::ACTION_SMB_SETUP} : 1;

        $smb_view_ops[1] = TR::t('folder_screen_net_folders');
        $smb_view_ops[2] = TR::t('folder_screen_net_folders_smb');
        $smb_view_ops[3] = TR::t('folder_screen_search_smb');

        $defs = array();
        Control_Factory::add_combobox($defs, $this, null,
            'smb_view', TR::t('folder_screen_show'),
            $smb_view, $smb_view_ops, 0
        );

        $save_smb_setup = User_Input_Handler_Registry::create_action($this, self::ACTION_SAVE_SMB_SETUP);
        Control_Factory::add_custom_close_dialog_and_apply_buffon($defs,
            '_do_save_smb_setup', TR::t('apply'), 250, $save_smb_setup
        );

        return Action_Factory::show_dialog(TR::t('folder_screen_search_smb_setup'), $defs, true, 1000, $attrs);
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array
     */
    protected function do_save_smb_setup($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $smb_view_ops = array();
        $smb_view = 1;
        $smb_view_ops[1] = TR::load_string('folder_screen_net_folders');
        $smb_view_ops[2] = TR::load_string('folder_screen_net_folders_smb');
        $smb_view_ops[3] = TR::load_string('folder_screen_search_smb');
        if (isset($user_input->smb_view)) {
            $smb_view = $user_input->smb_view;
            $plugin_cookies->{self::ACTION_SMB_SETUP} = $user_input->smb_view;
        }

        return Action_Factory::show_title_dialog(TR::t('folder_screen_used__1', $smb_view_ops[$smb_view]));
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
            '', 0, 0, 1, 1, 1230, false, true
        );
        Control_Factory::add_vgap($defs, 500);
        return Action_Factory::show_dialog(TR::t('folder_screen_choose_name'), $defs, true);
    }

    /**
     * @param $user_input
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
            $post_action = User_Input_Handler_Registry::create_action_screen($url->source_window_id,
                ACTION_FOLDER_SELECTED,
                '',
                array('selected_data' => $url->get_media_url_str()));
        }

        return Action_Factory::replace_path($parent_url->windowCounter, null, $post_action);
    }

    /**
     * @param $user_input
     * @return array
     */
    protected function do_mkdir($user_input)
    {
        hd_debug_print(null, true);

        $parent_url = MediaURL::decode($user_input->parent_media_url);

        if (!create_path($parent_url->filepath . DIRECTORY_SEPARATOR . $user_input->{self::ACTION_CREATE_FOLDER})) {
            return Action_Factory::show_title_dialog(TR::t('err_cant_create_folder'));
        }
        return Starnet_Epfs_Handler::invalidate_folders(array($user_input->parent_media_url));
    }

    /**
     * @param $user_input
     * @return array|null
     */
    protected function do_reset_folder($user_input)
    {
        hd_debug_print(null, true);

        $parent_url = MediaURL::decode($user_input->parent_media_url);
        $selected_url = MediaURL::decode($user_input->selected_media_url);

        $url = isset($selected_url->filepath) ? $selected_url : $parent_url;

        $post_action = User_Input_Handler_Registry::create_action_screen($url->source_window_id,
            ACTION_RESET_DEFAULT,
            '',
            array('selected_data' => $url->get_media_url_str()));

        return Action_Factory::replace_path($parent_url->windowCounter, null, $post_action);
    }
}
