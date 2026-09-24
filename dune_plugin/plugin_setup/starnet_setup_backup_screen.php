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

class Starnet_Setup_Backup_Screen extends Abstract_Controls_Screen
{
    const ID = 'backup_setup';

    const ACTION_RESTORE_FILE_SELECTED = 'restore_file_selected';
    const ACTION_BACKUP_FOLDER_SELECTED = 'backup_folder_selected';
    const CONTROL_BACKUP = 'backup';
    const CONTROL_RESTORE = 'restore';

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs();
    }

    /**
     * @return array
     */
    protected function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $defs = array();

        $folder_icon = get_image_path('folder.png');

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // backup

        Control_Factory::add_image_button($defs, $this, self::CONTROL_BACKUP, TR::t('setup_backup_settings'), TR::t('select_folder'), $folder_icon);
        Control_Factory::add_image_button($defs, $this, self::CONTROL_RESTORE, TR::t('setup_restore_settings'), TR::t('select_file'), $folder_icon);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        if (isset($user_input->control_id)) {
            $control_id = $user_input->control_id;
        } else if (isset($user_input->selected_control_id)) {
            $control_id = $user_input->selected_control_id;
        } else {
            hd_debug_print('user input control id or selected control id not set', true);
            return null;
        }

        $post_action = null;
        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return Action_Factory::close_and_run();

            case self::CONTROL_BACKUP:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        PARAM_EXTENSION => 'zip',
                        Starnet_Folder_Screen::PARAM_CHOOSE_FOLDER => self::ACTION_BACKUP_FOLDER_SELECTED,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('setup_backup_folder_path'));

            case self::CONTROL_RESTORE:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        PARAM_EXTENSION => 'zip',
                        Starnet_Folder_Screen::PARAM_CHOOSE_FILE => ACTION_FILE_SELECTED,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                        Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('select_file'));

            case self::ACTION_BACKUP_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $msg = Default_Dune_Plugin::do_backup_settings($data->{PARAM_FILEPATH}) ? TR::t('setup_copy_done') : TR::t('err_backup');
                $post_action = Action_Factory::show_title_dialog(TR::t('information'), $msg);
                break;

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $filename = basename($data->{PARAM_FILEPATH});
                if (preg_match('/^proiptv_backup_\d{1,2}\.\d{1,2}\.\d{3,4}_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}\.zip$/', $filename)) {
                    return $this->do_restore_settings($data->{PARAM_CAPTION}, $data->{PARAM_FILEPATH});
                }
                $post_action = Action_Factory::show_title_dialog(TR::t('information'), TR::t('err_bad_backup_file'));
        }

        return Action_Factory::reset_controls($this->do_get_control_defs(), $post_action);
    }

    /**
     * @param string $name
     * @param string $filename
     * @return array
     */
    protected function do_restore_settings($name, $filename)
    {
        // Nothing is destroyed until the archive is unpacked and verified below - a corrupt
        // or unreadable backup must leave the current settings untouched.
        $temp_folder = get_temp_path('restore');
        delete_directory($temp_folder);
        $tmp_filename = get_temp_path($name);
        try {
            hd_debug_print("Copy $filename to $tmp_filename");
            if (!copy($filename, $tmp_filename)) {
                throw new Exception(TR::t('err_copy__2', $filename, $tmp_filename));
            }

            $unzip = new ZipArchive();
            $out = $unzip->open($tmp_filename);
            if ($out !== true) {
                throw new Exception(TR::t('err_unzip__2', $tmp_filename, $out));
            }

            // Check if zip is empty
            $first_file = $unzip->getNameIndex(0);
            if (empty($first_file)) {
                $unzip->close();
                throw new Exception(TR::t('err_empty_zip__1', $tmp_filename));
            }

            if (!$unzip->extractTo($temp_folder)) {
                $unzip->close();
                throw new Exception(TR::t('err_unzip__2', basename($tmp_filename), $unzip->getStatusString()));
            }

            for ($i = 0; $i < $unzip->numFiles; ++$i) {
                $stat_index = $unzip->statIndex($i);
                touch("$temp_folder/{$stat_index['name']}", $stat_index['mtime']);
            }
            $unzip->close();
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            safe_unlink($tmp_filename);
            return Action_Factory::show_title_dialog(TR::t('err_restore'), $ex->getMessage());
        }

        safe_unlink($tmp_filename);

        // the archive is good - only now drop the current state
        Epg_Manager_Json::clear_epg_files();
        Epg_Manager_Xmltv::clear_epg_files();
        $this->plugin->reset_playlist_db();
        $this->plugin->reset_playlist_settings_db();
        $this->plugin->clear_playlist_cache(null);

        // keep the current databases and image cache aside so a failed restore can be rolled back
        $rollback = array();
        $ext = ".db";
        foreach (glob_dir(get_data_path(), "/$ext$/i") as $file) {
            hd_debug_print("Rename $file to $file.prev");
            if (rename($file, "$file.prev")) {
                $rollback["$file.prev"] = $file;
            } else {
                hd_debug_print("Failed to preserve $file");
            }
        }

        $cached_img = get_data_path(CACHED_IMAGE_SUBDIR);
        $cached_img_prev = get_data_path(CACHED_IMAGE_SUBDIR . '_prev');
        $cached_img_saved = is_dir($cached_img) && rename($cached_img, $cached_img_prev);

        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST);

        $failed = array();
        foreach ($files as $src) {
            /** @noinspection PhpUndefinedMethodInspection */
            $name = $files->getSubPathName();
            $dest = get_data_path($name);
            if ($src->isDir()) {
                create_path($dest);
            } else {
                if (preg_match('/lcfg_proiptv_epg_.+\.txt/i', $name)) {
                    $dest = "/config/$name";
                }
                $mtime = filemtime($src);
                // the temp dir and the data dir are usually on different mounts, so this is a
                // copy and not a rename - and touch() would happily create an empty file if the
                // move had failed, so it only runs once the file is actually there
                if (move_file((string)$src, $dest)) {
                    touch($dest, $mtime);
                } else {
                    $failed[] = $name;
                }
            }
        }

        clearstatcache();

        if (!empty($failed)) {
            hd_debug_print('Restore failed for: ' . implode(', ', $failed));
            // put the preserved copies back - a restored file that did make it is
            // overwritten by its original
            foreach ($rollback as $prev => $file) {
                safe_unlink($file);
                rename($prev, $file);
            }
            if ($cached_img_saved) {
                delete_directory($cached_img);
                rename($cached_img_prev, $cached_img);
            }

            $this->plugin->init_plugin(true);
            return Action_Factory::show_title_dialog(TR::t('err_restore'),
                TR::t('err_copy__2', implode(', ', $failed), get_data_path()));
        }

        foreach (array_keys($rollback) as $prev) {
            safe_unlink($prev);
        }
        if ($cached_img_saved) {
            delete_directory($cached_img_prev);
        }

        // force plugin to fully reinit
        $this->plugin->init_plugin(true);

        $actions[] = Action_Factory::show_title_dialog(TR::t('information'), TR::t('setup_restore_done'));
        $actions[] = Action_Factory::close_and_run();
        $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Entry_Handler::ID, ACTION_RELOAD);
        return Action_Factory::composite($actions);
    }
}
