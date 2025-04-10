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

class Starnet_Setup_Ext_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'ext_setup';

    const CONTROL_HISTORY_CHANGE_FOLDER = 'change_history_folder';
    const CONTROL_COPY_TO_DATA = 'copy_to_data';
    const CONTROL_COPY_TO_PLUGIN = 'copy_to_plugin';
    const CONTROL_ADULT_PASS_DLG = 'adult_pass_dialog';
    const ACTION_ADULT_PASS_DLG_APPLY = 'adult_pass_apply';
    const ACTION_SETTINGS_PASS_DLG_APPLY = 'settings_pass_apply';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
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
            CONTROL_BACKUP, TR::t('setup_backup_settings'), TR::t('select_folder'), $folder_icon, self::CONTROLS_WIDTH);

        Control_Factory::add_image_button($defs, $this, null,
            CONTROL_RESTORE, TR::t('setup_restore_settings'), TR::t('select_file'), $folder_icon, self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // history

        $history_path = $this->plugin->get_history_path();
        hd_debug_print("history path: $history_path");
        $display_path = HD::string_ellipsis($history_path);
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
        // adult channel password
        Control_Factory::add_image_button($defs, $this, array('adult' => true), self::CONTROL_ADULT_PASS_DLG,
            TR::t('setup_adult_title'), TR::t('setup_adult_change'), get_image_path('text.png'), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Settings protection
        Control_Factory::add_image_button($defs, $this, array('adult' => false), self::CONTROL_ADULT_PASS_DLG,
            TR::t('setup_settings_protection_title'), TR::t('setup_adult_change'), get_image_path('text.png'), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Settings full size remote
        if (is_limited_apk()) {
            $remote = $this->plugin->get_parameter(PARAM_FULL_SIZE_REMOTE, SwitchOnOff::off);
            Control_Factory::add_image_button($defs, $this, null,
                PARAM_FULL_SIZE_REMOTE, TR::t('setup_settings_full_remote'), SwitchOnOff::translate($remote),
                get_image_path(SwitchOnOff::to_image($remote)), self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // debugging

        $debug_state = safe_get_member($plugin_cookies, PARAM_ENABLE_DEBUG, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_ENABLE_DEBUG, TR::t('setup_debug'), SwitchOnOff::translate($debug_state),
            get_image_path(SwitchOnOff::to_image($debug_state)), self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("Setup: changing $control_id value to $new_value", true);
        }

        $post_action = null;

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(
                        Starnet_Setup_Screen::ID,
                        RESET_CONTROLS_ACTION_ID,
                        null,
                        array('initial_sel_ndx' => $this->return_index)
                    )
                );

            case CONTROL_BACKUP:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_folder' => $user_input->control_id,
                        'extension' => 'zip',
                        'allow_network' => !is_limited_apk(),
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_backup_folder_path'));

            case CONTROL_RESTORE:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => $user_input->control_id,
                        'extension' => 'zip',
                        'allow_network' => !is_limited_apk(),
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
                        'choose_folder' => $user_input->control_id,
                        'allow_reset' => true,
                        'allow_network' => !is_limited_apk(),
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_history_folder_path'));

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_folder === self::CONTROL_HISTORY_CHANGE_FOLDER) {
                    hd_debug_print(ACTION_FOLDER_SELECTED . " $data->filepath");
                    $this->plugin->set_history_path($data->filepath);

                    $post_action = Action_Factory::show_title_dialog(
                        TR::t('folder_screen_selected_folder__1', $data->caption),
                        null,
                        $data->filepath,
                        self::CONTROLS_WIDTH
                    );
                    break;
                }

                if ($data->choose_folder === CONTROL_BACKUP) {
                    $msg = HD::do_backup_settings($this->plugin, $data->filepath) ? TR::t('setup_copy_done') : TR::t('err_backup');
                    $post_action = Action_Factory::show_title_dialog($msg);
                }

                break;

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file === CONTROL_RESTORE) {
                    return $this->do_restore_settings($data->caption, $data->filepath);
                }
                break;

            case ACTION_RESET_DEFAULT:
                $data = MediaURL::make(array('filepath' => get_data_path()));
                hd_debug_print("do set history folder to default: $data->filepath");
                $this->plugin->set_history_path();
                break;

            case self::CONTROL_COPY_TO_DATA:
                $history_path = $this->plugin->get_history_path();
                hd_debug_print("copy to: $history_path");
                try {
                    HD::copy_data(get_data_path('history'), "/_" . PARAM_TV_HISTORY_ITEMS . "$/", $history_path);
                    $post_action = Action_Factory::show_title_dialog(TR::t('setup_copy_done'));
                } catch (Exception $ex) {
                    print_backtrace_exception($ex);
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_copy'), null, $ex->getMessage());
                }

                break;

            case self::CONTROL_COPY_TO_PLUGIN:
                hd_debug_print("copy to: " . get_data_path());
                try {
                    HD::copy_data($this->plugin->get_history_path(), '_' . PARAM_TV_HISTORY_ITEMS . '$/', get_data_path(HISTORY_SUBDIR));
                    $post_action = Action_Factory::show_title_dialog(TR::t('setup_copy_done'));
                } catch (Exception $ex) {
                    print_backtrace_exception($ex);
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_copy'), null, $ex->getMessage());
                }
                break;

            case self::CONTROL_ADULT_PASS_DLG: // show pass dialog
                return $this->do_get_pass_control_defs($user_input->adult);

            case self::ACTION_ADULT_PASS_DLG_APPLY: // handle pass dialog result
                $param = $user_input->adult ? PARAM_ADULT_PASSWORD : PARAM_SETTINGS_PASSWORD;
                $old_pass = $this->plugin->get_parameter($param);
                if (empty($old_pass)) {
                    if (!empty($user_input->pass2)) {
                        $msg = TR::t('setup_pass_changed');
                        $this->plugin->set_parameter($param, $user_input->pass2);
                    } else {
                        $msg = TR::t('setup_pass_not_changed');
                    }
                } else if ($user_input->pass1 !== $old_pass) {
                    $msg = TR::t('err_wrong_old_password');
                } else if (empty($user_input->pass2)) {
                    $msg = TR::t('setup_pass_disabled');
                    $this->plugin->set_parameter($param, '');
                } else if ($user_input->pass1 !== $user_input->pass2) {
                    $msg = TR::t('setup_pass_changed');
                    $this->plugin->set_parameter($param, $user_input->pass2);
                } else {
                    $msg = TR::t('setup_pass_not_changed');
                }
                hd_debug_print("pass: $param, old pass: $old_pass, new pass: $user_input->pass2", true);

                $post_action = Action_Factory::show_title_dialog($msg);
                break;

            case PARAM_FULL_SIZE_REMOTE:
                $this->plugin->toggle_parameter(PARAM_FULL_SIZE_REMOTE);
                break;

            case PARAM_ENABLE_DEBUG:
                $debug = SwitchOnOff::to_bool(self::toggle_cookie_param($plugin_cookies,PARAM_ENABLE_DEBUG));
                set_debug_log($debug);
                hd_debug_print("Debug logging: " . var_export($debug, true));
                break;
        }

        return Action_Factory::reset_controls(
            $this->get_control_defs(MediaURL::decode($user_input->parent_media_url), $plugin_cookies),
            $post_action
        );
    }

    /**
     * @param string $name
     * @param string $filename
     * @return array
     */
    protected function do_restore_settings($name, $filename)
    {
        $this->plugin->safe_clear_selected_epg_cache('');
        $this->plugin->clear_playlist_cache();
        $this->plugin->reset_playlist_db();

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
            if (file_exists($tmp_filename)) {
                unlink($tmp_filename);
            }
            return Action_Factory::show_title_dialog(TR::t('err_restore'), null, $ex->getMessage());
        }

        unlink($tmp_filename);

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

        $this->plugin->init_plugin(true);

        return Action_Factory::show_title_dialog(
            TR::t('setup_restore_done'),
            Action_Factory::replace_path(2, null,
                    User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID, ACTION_RELOAD)
            )
        );
    }

    /**
     * adult pass dialog defs
     * @param bool $adult
     * @return array
     */
    protected function do_get_pass_control_defs($adult)
    {
        hd_debug_print(null, true);

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        $old_pass = $this->plugin->get_parameter($adult ? PARAM_ADULT_PASSWORD : PARAM_SETTINGS_PASSWORD);
        if (!empty($old_pass)) {
            Control_Factory::add_text_field($defs, $this, null, 'pass1', TR::t('setup_old_pass'),
                '', true, true, false, true, 500);
        }

        Control_Factory::add_text_field($defs, $this, null, 'pass2', TR::t('setup_new_pass'),
            '', true, true, false, true, 500);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this, self::ACTION_ADULT_PASS_DLG_APPLY, TR::t('ok'), 300, array("adult" => $adult));
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        $title = $adult ? TR::t('setup_adult_password') : TR::t('setup_settings_protection');
        return Action_Factory::show_dialog($title, $defs, true);
    }
}
