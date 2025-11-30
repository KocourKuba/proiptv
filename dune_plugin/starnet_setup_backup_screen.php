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

        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_BACKUP, TR::t('setup_backup_settings'), TR::t('select_folder'), $folder_icon, static::CONTROLS_WIDTH);

        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_RESTORE, TR::t('setup_restore_settings'), TR::t('select_file'), $folder_icon, static::CONTROLS_WIDTH);

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
            hd_debug_print("user input control id or selected control id not set", true);
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
                        Starnet_Folder_Screen::PARAM_CHOOSE_FILE => self::ACTION_RESTORE_FILE_SELECTED,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                        Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('select_file'));

            case self::ACTION_BACKUP_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $msg = Default_Dune_Plugin::do_backup_settings($this->plugin, $data->{PARAM_FILEPATH}) ? TR::t('setup_copy_done') : TR::t('err_backup');
                $post_action = Action_Factory::show_title_dialog($msg);
                break;

            case self::ACTION_RESTORE_FILE_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                return $this->do_restore_settings($data->{Starnet_Folder_Screen::PARAM_CAPTION}, $data->{PARAM_FILEPATH});
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
        Epg_Manager_Json::clear_epg_files();
        Epg_Manager_Xmltv::clear_epg_files();
        $this->plugin->reset_playlist_db();
        $this->plugin->clear_playlist_cache(null);

        $temp_folder = get_temp_path("restore");
        delete_directory($temp_folder);
        $tmp_filename = get_temp_path($name);
        try {
            hd_debug_print("Copy $filename to $tmp_filename");
            if (!copy($filename, $tmp_filename)) {
                throw new Exception(error_get_last());
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

            for ($i = 0; $i < $unzip->numFiles; $i++) {
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

        foreach (array(".settings", ".db") as $ext) {
            foreach (glob_dir(get_data_path(), "/$ext$/i") as $file) {
                hd_debug_print("Rename $file to $file.prev");
                rename($file, "$file.prev");
            }
        }

        rename(get_data_path(CACHED_IMAGE_SUBDIR), get_data_path(CACHED_IMAGE_SUBDIR . '_prev'));

        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $src) {
            /** @noinspection PhpUndefinedMethodInspection */
            $dest = get_data_path($files->getSubPathname());
            if ($src->isDir()) {
                create_path($dest);
            } else {
                $mtime = filemtime($src);
                rename($src, $dest);
                touch($dest, $mtime);
            }
        }

        clearstatcache();

        array_map('unlink', glob(get_data_path('*.prev')));
        array_map('unlink', glob(get_data_path(CACHED_IMAGE_SUBDIR . '_prev/*')));
        rmdir(get_data_path(CACHED_IMAGE_SUBDIR . '_prev'));

        // force plugin to fully reinit
        $this->plugin->init_plugin(true);

        $actions[] = Action_Factory::show_title_dialog(TR::t('setup_restore_done'));
        $actions[] = Action_Factory::close_and_run();
        $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Entry_Handler::ID, ACTION_RELOAD);
        return Action_Factory::composite($actions);
    }
}
