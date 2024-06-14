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
    const CONTROL_ADULT_PASS_DLG = 'adult_pass_dialog';
    const ACTION_ADULT_PASS_DLG_APPLY = 'adult_pass_apply';
    const ACTION_SETTINGS_PASS_DLG_APPLY = 'settings_pass_apply';

    protected $return_index = array('initial_sel_ndx' => 13);

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        return $actions;
    }

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
        // adult channel password
        Control_Factory::add_image_button($defs, $this, array('adult' => true), self::CONTROL_ADULT_PASS_DLG,
            TR::t('setup_adult_title'), TR::t('setup_adult_change'), get_image_path('text.png'), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Settings protection
        Control_Factory::add_image_button($defs, $this, array('adult' => false), self::CONTROL_ADULT_PASS_DLG,
            TR::t('setup_settings_protection_title'), TR::t('setup_adult_change'), get_image_path('text.png'), self::CONTROLS_WIDTH);

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
            hd_debug_print("Setup: changing $control_id value to $new_value", true);
        }

        switch ($control_id) {
            case GUI_EVENT_KEY_RETURN:
            return Action_Factory::close_and_run(
                User_Input_Handler_Registry::create_action_screen(
                    Starnet_Setup_Screen::ID, RESET_CONTROLS_ACTION_ID, null, $this->return_index)
            );

            case self::CONTROL_BACKUP:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_folder' => $user_input->control_id,
                        'extension'	=> 'zip',
                        'allow_network' => !is_limited_apk(),
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_backup_folder_path'));

            case self::CONTROL_RESTORE:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => $user_input->control_id,
                        'extension'	=> 'zip',
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

                    return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->caption),
                        $action_reload, $data->filepath, self::CONTROLS_WIDTH);
                }

                if ($data->choose_folder === self::CONTROL_BACKUP) {
                    if (HD::do_backup_settings($this->plugin, $data->filepath) === false) {
                        return Action_Factory::show_title_dialog(TR::t('err_backup'));
                    }

                    return Action_Factory::show_title_dialog(TR::t('setup_copy_done'),
                        User_Input_Handler_Registry::create_action($this, ACTION_RELOAD));
                }

                break;

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file === self::CONTROL_RESTORE) {
                    return $this->do_restore_settings($data->caption, $data->filepath);
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
                try {
                    HD::copy_data(get_data_path('history'), "/" . PARAM_TV_HISTORY_ITEMS ."$/", $history_path);
                } catch (Exception $ex) {
                    print_backtrace_exception($ex);
                    return Action_Factory::show_title_dialog(TR::t('err_copy'), null, $ex->getMessage());
                }

                return Action_Factory::show_title_dialog(TR::t('setup_copy_done'), $action_reload);

            case self::CONTROL_COPY_TO_PLUGIN:
                hd_debug_print("copy to: " . get_data_path());
                try {
                    HD::copy_data($this->plugin->get_history_path(), "/" . PARAM_TV_HISTORY_ITEMS ."$/", get_data_path('history'));
                } catch (Exception $ex) {
                    print_backtrace_exception($ex);
                    return Action_Factory::show_title_dialog(TR::t('err_copy'), null, $ex->getMessage());
                }

                return Action_Factory::show_title_dialog(TR::t('setup_copy_done'), $action_reload);

            case self::CONTROL_ADULT_PASS_DLG: // show pass dialog
                return $this->do_get_pass_control_defs($user_input->adult);

            case self::ACTION_ADULT_PASS_DLG_APPLY: // handle pass dialog result
                $need_reload = false;
                $param = $user_input->adult ? PARAM_ADULT_PASSWORD : PARAM_SETTINGS_PASSWORD;
                $pass = $this->plugin->get_parameter($param);
                hd_debug_print("pass: $param ($pass == $user_input->pass1)", true);
                if ($user_input->pass1 !== (string)$pass) {
                    $msg = TR::t('err_wrong_old_password');
                } else if (empty($user_input->pass2)) {
                    $this->plugin->set_parameter($param, '');
                    $msg = TR::t('setup_pass_disabled');
                    $need_reload = $user_input->adult;
                } else if ($user_input->pass1 !== $user_input->pass2) {
                    $this->plugin->set_parameter($param, $user_input->pass2);
                    $msg = TR::t('setup_pass_changed');
                    $need_reload = $user_input->adult;
                } else {
                    $msg = TR::t('setup_pass_not_changed');
                }

                if ($need_reload) {
                    $this->plugin->tv->reload_channels($plugin_cookies);
                }

                return Action_Factory::show_title_dialog($msg,
                    Action_Factory::reset_controls($this->do_get_control_defs()));

            case PARAM_ENABLE_DEBUG:
                $this->plugin->toggle_parameter(PARAM_ENABLE_DEBUG, false);
                $debug = $this->plugin->get_bool_parameter(PARAM_ENABLE_DEBUG);
                set_debug_log($debug);
                hd_debug_print("Debug logging: " . var_export($debug, true));
                break;

            case ACTION_RELOAD:
                hd_debug_print("reload");
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }

    /**
     * @param string $name
     * @param string $filename
     * @return array
     */
    protected function do_restore_settings($name, $filename)
    {
        $this->plugin->clear_all_epg_cache();
        $this->plugin->clear_playlist_cache();

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

            $unzip->extractTo($temp_folder);
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

        foreach (glob_dir(get_data_path(), "/\.settings$/i") as $file) {
            rename($file, "$file.prev");
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

        flush();

        shell_exec('rm -f '. get_data_path('*.prev'));
        shell_exec('rm -f '. get_data_path(CACHED_IMAGE_SUBDIR . '_prev/*'));
        rmdir(get_data_path(CACHED_IMAGE_SUBDIR . '_prev'));

        $this->plugin->load_parameters(true);
        $this->plugin->remove_parameter(PARAM_CACHE_PATH);
        $this->plugin->set_bool_parameter(PARAM_ENABLE_DEBUG, false);

        $this->plugin->init_plugin(true);

        return Action_Factory::show_title_dialog(
            TR::t('setup_restore_done'),
            Action_Factory::close_and_run(
                Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_action_screen(Starnet_Tv_Groups_Screen::ID, ACTION_RELOAD)
                )
            )
        );
    }

    /**
     * adult pass dialog defs
     * @param $adult bool
     * @return array
     */
    public function do_get_pass_control_defs($adult)
    {
        hd_debug_print(null, true);

        $defs = array();

        $pass1 = '';
        $pass2 = '';

        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $this, null, 'pass1', TR::t('setup_old_pass'),
            $pass1, true, true, false, true, 500);
        Control_Factory::add_text_field($defs, $this, null, 'pass2', TR::t('setup_new_pass'),
            $pass2, true, true, false, true, 500);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this, array("adult" => $adult), self::ACTION_ADULT_PASS_DLG_APPLY, TR::t('ok'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        $title = $adult ? TR::t('setup_adult_password') : TR::t('setup_settings_protection');
        return Action_Factory::show_dialog($title, $defs, true);
    }
}
