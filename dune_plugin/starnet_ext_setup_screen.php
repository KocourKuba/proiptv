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

require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Ext_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'ext_setup';

    const CONTROL_BACKUP = 'backup';
    const CONTROL_RESTORE = 'restore';
    const CONTROL_HISTORY_CHANGE_FOLDER = 'change_history_folder';
    const CONTROL_COPY_TO_DATA = 'copy_to_data';
    const CONTROL_COPY_TO_PLUGIN = 'copy_to_plugin';
    const ACTION_FILE_RESTORE = 'restore_file';
    const ACTION_BACKUP_FOLDER = 'backup_folder';
    const ACTION_HISTORY_FOLDER = 'history_folder';

    ///////////////////////////////////////////////////////////////////////

    /**
     * defs for all controls on screen
     * @return array
     */
    public function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $defs = array();

        $folder_icon = get_image_path('folder.png');
        $refresh_icon = get_image_path('refresh.png');

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // backup

        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_BACKUP, TR::t('setup_backup_settings'), TR::t('select_folder'), $folder_icon, self::CONTROLS_WIDTH);

        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_RESTORE, TR::t('setup_restore_settings'), TR::t('select_file'), $folder_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // history

        $history_path = $this->plugin->get_history_path();
        hd_debug_print("history path: $history_path");
        $display_path = HD::string_ellipsis(get_slash_trailed_path($history_path));

        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_HISTORY_CHANGE_FOLDER, TR::t('setup_history_folder_path'), $display_path, $folder_icon, self::CONTROLS_WIDTH);

        $path = $this->plugin->get_parameter(PARAM_HISTORY_PATH);
        if (!is_null($path)) {
            Control_Factory::add_image_button($defs, $this, null,
                self::CONTROL_COPY_TO_DATA, TR::t('setup_copy_to_data'), TR::t('apply'), $refresh_icon, self::CONTROLS_WIDTH);

            Control_Factory::add_image_button($defs, $this, null,
                self::CONTROL_COPY_TO_PLUGIN, TR::t('setup_copy_to_plugin'), TR::t('apply'), $refresh_icon, self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // debugging

        $debug_state = $this->plugin->get_parameter(PARAM_ENABLE_DEBUG, SetupControlSwitchDefs::switch_off);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_ENABLE_DEBUG, TR::t('setup_debug'), SetupControlSwitchDefs::$on_off_translated[$debug_state],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$debug_state]), self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        return $this->do_get_control_defs();
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $action_reload = User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("Setup: changing $control_id value to $new_value");
        }

        switch ($control_id) {
            case self::CONTROL_BACKUP:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_folder' => array(
                            'action' => self::ACTION_BACKUP_FOLDER,
                            'extension'	=> 'zip',
                        ),
                        'allow_network' => !is_apk(),
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_history_folder_path'));

            case self::CONTROL_RESTORE:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => array(
                            'action' => self::ACTION_FILE_RESTORE,
                            'extension'	=> 'zip',
                        ),
                        'allow_network' => !is_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('select_file'));

            case self::CONTROL_HISTORY_CHANGE_FOLDER:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_folder' => array(
                            'action' => self::ACTION_HISTORY_FOLDER,
                        ),
                        'allow_reset' => true,
                        'allow_network' => !is_apk(),
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_history_folder_path'));

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_folder->action === self::ACTION_HISTORY_FOLDER) {
                    hd_debug_print(ACTION_FOLDER_SELECTED . " $data->filepath");
                    $this->plugin->set_history_path($data->filepath);

                    return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->caption),
                        $action_reload, $data->filepath, self::CONTROLS_WIDTH);
                }

                if ($data->choose_folder->action === self::ACTION_BACKUP_FOLDER) {
                    return $this->do_backup_settings($data);
                }

                break;

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file->action === self::ACTION_FILE_RESTORE) {
                    return $this->do_restore_settings($data, $plugin_cookies);
                }
                break;

            case ACTION_RESET_DEFAULT:
                $data = MediaURL::make(array('filepath' => get_data_path()));
                hd_debug_print("do set history folder to default: $data->filepath");
                $this->plugin->set_history_path();
                return $action_reload;

            case self::CONTROL_COPY_TO_DATA:
                $history_path = $this->plugin->get_history_path();
                hd_debug_print("copy to: $history_path");
                if (!$this->copy_data(get_data_path('history'), "/" . PARAM_TV_HISTORY_ITEMS ."$/", $history_path)) {
                    return Action_Factory::show_title_dialog(TR::t('err_copy'));
                }

                return Action_Factory::show_title_dialog(TR::t('setup_copy_done'), $action_reload);

            case self::CONTROL_COPY_TO_PLUGIN:
                hd_debug_print("copy to: " . get_data_path());
                if (!$this->copy_data($this->plugin->get_history_path(), "*" . PARAM_TV_HISTORY_ITEMS, get_data_path('history'))) {
                    return Action_Factory::show_title_dialog(TR::t('err_copy'));
                }

                return Action_Factory::show_title_dialog(TR::t('setup_copy_done'), $action_reload);

            case PARAM_ENABLE_DEBUG:
                $this->plugin->toggle_parameter(PARAM_ENABLE_DEBUG, SetupControlSwitchDefs::switch_off);
                $debug = $this->plugin->get_parameter(PARAM_ENABLE_DEBUG, SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on;
                set_debug_log($debug);
                hd_debug_print("Debug logging: " . var_export($debug, true));
                break;

            case ACTION_RELOAD:
                hd_debug_print("reload");
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }


    private function copy_data($sourcePath, $source_pattern, $destPath){
        if (empty($sourcePath) || empty($destPath)) {
            hd_debug_print("One of is empty: sourceDir = $sourcePath | destDir = $destPath");
            return false;
        }

        if (!create_path($destPath)) {
            hd_debug_print("Can't create destination folder: $destPath");
            return false;
        }

        foreach (glob_dir($sourcePath, $source_pattern) as $file) {
            $dest_file = $destPath . $file;
            hd_debug_print("copy $file to $dest_file");
            if (!copy($file, $dest_file))
                return false;
        }
        return true;
    }

    /**
     * @param $plugin_cookies
     * @param MediaURL $data
     * @return array
     */
    protected function do_restore_settings(MediaURL $data, $plugin_cookies)
    {
        $temp_folder = get_temp_path("restore");
        delete_directory($temp_folder);
        $tmp_filename = get_temp_path($data->caption);
        try {
            if (!copy($data->filepath, $tmp_filename))
                return Action_Factory::show_title_dialog(TR::t('err_copy'));

            $unzip = new ZipArchive();
            $out = $unzip->open($tmp_filename);
            if ($out !== true) {
                throw new Exception(TR::t('err_unzip__2', $tmp_filename, $out));
            }
            $filename = $unzip->getNameIndex(0);
            if (empty($filename)) {
                $unzip->close();
                throw new Exception(TR::t('err_empty_zip__1', $tmp_filename));
            }

            $unzip->extractTo($temp_folder);
            $unzip->close();
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
            return Action_Factory::show_title_dialog(TR::t('err_restore'), null, $ex->getMessage());
        }

        $back_folders = array('', 'cached_img');
        $tmp_backup = get_temp_path('backup');
        delete_directory($tmp_backup);
        create_path($tmp_backup);
        foreach ($back_folders as $folder) {
            $folder_path = get_slash_trailed_path($tmp_backup . DIRECTORY_SEPARATOR . $folder);
            create_path($folder_path);
            foreach (glob_dir(get_data_path($folder)) as $file) {
                $dest = $folder_path . basename($file);
                hd_debug_print("Copy: $file to $dest");
                copy($file, $dest);
                unlink($file);
            }
        }

        $dest = get_data_path();
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                /** @noinspection PhpUndefinedMethodInspection */
                create_path($dest . $files->getSubPathname());
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                copy($file, $dest . $files->getSubPathname());
            }
        }

        flush();

        $this->plugin->init_plugin();
        $this->plugin->tv->reload_channels($plugin_cookies);

        return Action_Factory::show_title_dialog(TR::t('setup_copy_done'),
            Action_Factory::invalidate_all_folders($plugin_cookies, $this->plugin->get_screens(),
                Action_Factory::reset_controls($this->do_get_control_defs())));
    }

    /**
     * @param MediaURL $data
     * @return array
     */
    protected function do_backup_settings(MediaURL $data)
    {
        hd_debug_print(ACTION_FOLDER_SELECTED . " $data->filepath");
        hd_debug_print("copy to: $data->filepath");

        $timestamp = format_datetime('Y-m-d_H-i', time());
        $zip_file_name = "proiptv_backup_{$this->plugin->plugin_info['app_version']}_$timestamp.zip";
        $zip_file = get_temp_path($zip_file_name);

        try {
            $zip = new ZipArchive();
            if (!$zip->open($zip_file, ZipArchive::CREATE)) {
                throw new Exception(TR::t("err_create_zip__1", $zip_file));
            }

            $rootPath = get_data_path();
            $zip->addFile("{$rootPath}common.settings", "common.settings");
            foreach ($this->plugin->get_playlists() as $playlist) {
                $name = Hashed_Array::hash($playlist) . ".settings";
                $zip->addFile("$rootPath$name", $name);
            }

            $added_folders = array("{$rootPath}cached_img");
            /** @var SplFileInfo[] $files */
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath),
                RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                if ($file->isDir()) continue;

                $filePath = $file->getRealPath();
                foreach ($added_folders as $folder) {
                    if (0 === strncmp($filePath, $folder, strlen($folder))) {
                        $relativePath = substr($filePath, strlen($rootPath));
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }

            $zip->close();
            if (!copy($zip_file, "$data->filepath/$zip_file_name")) {
                throw new Exception(TR::t('err_copy__2'), $zip_file, "$data->filepath/$zip_file_name");
            }
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
            return Action_Factory::show_title_dialog(TR::t('err_backup'), null, $ex->getMessage());
        }

        unlink($zip_file);
        flush();

        return Action_Factory::show_title_dialog(TR::t('setup_copy_done'),
            User_Input_Handler_Registry::create_action($this, ACTION_RELOAD));
    }

    private function CopyData($sourcePath, $source_pattern, $destPath){
        if (empty($sourcePath) || empty($destPath)) {
            hd_debug_print("sourceDir = $sourcePath | destDir = $destPath");
            return false;
        }

        foreach (glob_dir($sourcePath, $source_pattern) as $file) {
            $dest_file = $destPath . $file;
            hd_debug_print("copy $file to $dest_file");
            if (!copy($file, $dest_file))
                return false;
        }
        return true;
    }
}
