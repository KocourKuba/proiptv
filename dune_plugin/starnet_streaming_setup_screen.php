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

class Starnet_Streaming_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'stream_setup';

    const CONTROL_AUTO_RESUME = 'auto_resume';
    const CONTROL_AUTO_PLAY = 'auto_play';
    const CONTROL_HISTORY_CHANGE_FOLDER = 'history_change_folder';
    const CONTROL_COPY_TO_DATA = 'copy_to_data';
    const CONTROL_COPY_TO_PLUGIN = 'copy_to_plugin';

    ///////////////////////////////////////////////////////////////////////

    /**
     * streaming parameters dialog defs
     * @param $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        $defs = array();

        $folder_icon = get_image_path('folder.png');
        $remove_icon = get_image_path('brush.png');
        $refresh_icon = get_image_path('refresh.png');

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // auto play
        if (!isset($plugin_cookies->{self::CONTROL_AUTO_PLAY}))
            $plugin_cookies->{self::CONTROL_AUTO_PLAY} = SetupControlSwitchDefs::switch_off;

        $value = $plugin_cookies->{self::CONTROL_AUTO_PLAY};
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_AUTO_PLAY, TR::t('setup_autostart'), SetupControlSwitchDefs::$on_off_translated[$value],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$value]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // auto resume
        if (!isset($plugin_cookies->{self::CONTROL_AUTO_RESUME}))
            $plugin_cookies->{self::CONTROL_AUTO_RESUME} = SetupControlSwitchDefs::switch_on;

        $value = $plugin_cookies->{self::CONTROL_AUTO_RESUME};
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_AUTO_RESUME, TR::t('setup_continue_play'),  SetupControlSwitchDefs::$on_off_translated[$value],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$value]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // buffering time
        $show_buf_time_ops = array();
        $show_buf_time_ops[1000] = TR::t('setup_buffer_sec_default__1', "1");
        $show_buf_time_ops[0] = TR::t('setup_buffer_no');
        $show_buf_time_ops[500] = TR::t('setup_buffer_sec__1', "0.5");
        $show_buf_time_ops[2000] = TR::t('setup_buffer_sec__1', "2");
        $show_buf_time_ops[3000] = TR::t('setup_buffer_sec__1', "3");
        $show_buf_time_ops[5000] = TR::t('setup_buffer_sec__1', "5");
        $show_buf_time_ops[10000] = TR::t('setup_buffer_sec__1', "10");

        $buffering = $this->plugin->get_setting(PARAM_BUFFERING_TIME, 1000);
        hd_debug_print("Current buffering: $buffering");
        Control_Factory::add_combobox($defs,
            $this,
            null,
            PARAM_BUFFERING_TIME,
            TR::t('setup_buffer_time'),
            $buffering,
            $show_buf_time_ops,
            self::CONTROLS_WIDTH,
            true);

        //////////////////////////////////////
        // archive delay time
        $show_delay_time_ops = array();
        $show_delay_time_ops[60] = TR::t('setup_buffer_sec_default__1', "60");
        $show_delay_time_ops[10] = TR::t('setup_buffer_sec__1', "10");
        $show_delay_time_ops[20] = TR::t('setup_buffer_sec__1', "20");
        $show_delay_time_ops[30] = TR::t('setup_buffer_sec__1', "30");
        $show_delay_time_ops[2*60] = TR::t('setup_buffer_sec__1', "120");
        $show_delay_time_ops[3*60] = TR::t('setup_buffer_sec__1', "180");
        $show_delay_time_ops[5*60] = TR::t('setup_buffer_sec__1', "300");

        $delay = $this->plugin->get_setting(PARAM_ARCHIVE_DELAY_TIME, 60);
        hd_debug_print("Current archive delay: $delay");
        Control_Factory::add_combobox($defs,
            $this,
            null,
            PARAM_ARCHIVE_DELAY_TIME,
            TR::t('setup_delay_time'),
            $delay,
            $show_delay_time_ops,
            self::CONTROLS_WIDTH,
            true);

        //////////////////////////////////////
        // history

        $history_path = $this->plugin->get_history_path();
        hd_debug_print("history path: $history_path");
        $display_path = HD::string_ellipsis(get_slash_trailed_path($history_path));

        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_HISTORY_CHANGE_FOLDER, TR::t('setup_history_folder_path'), $display_path, $folder_icon, self::CONTROLS_WIDTH);

        if (!$this->plugin->is_history_path_default()) {
            Control_Factory::add_image_button($defs, $this, null,
                self::CONTROL_COPY_TO_DATA, TR::t('setup_copy_to_data'), TR::t('apply'), $refresh_icon, self::CONTROLS_WIDTH);

            Control_Factory::add_image_button($defs, $this, null,
                self::CONTROL_COPY_TO_PLUGIN, TR::t('setup_copy_to_plugin'), TR::t('apply'), $refresh_icon, self::CONTROLS_WIDTH);
        }

        Control_Factory::add_image_button($defs, $this, null,
            ACTION_ITEMS_CLEAR, TR::t('setup_tv_history_clear'), TR::t('clear'), $remove_icon, self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        return $this->do_get_control_defs($plugin_cookies);
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        dump_input_handler($user_input);

        $action_reload = User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);
        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("Setup: changing $control_id value to $new_value");
        }

        switch ($control_id) {
            case self::CONTROL_AUTO_PLAY:
            case self::CONTROL_AUTO_RESUME:
                hd_debug_print("$control_id: " . $plugin_cookies->{$control_id}, LOG_LEVEL_DEBUG);
                $plugin_cookies->{$control_id} = ($plugin_cookies->{$control_id} === SetupControlSwitchDefs::switch_off)
                    ? SetupControlSwitchDefs::switch_on
                    : SetupControlSwitchDefs::switch_off;
                break;

            case PARAM_BUFFERING_TIME:
            case PARAM_ARCHIVE_DELAY_TIME:
                hd_debug_print("$control_id: " . $user_input->{$control_id}, LOG_LEVEL_DEBUG);
                $this->plugin->set_setting($control_id, (int)$user_input->{$control_id});
                break;

            case self::CONTROL_HISTORY_CHANGE_FOLDER:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'choose_folder' => static::ID,
                        'allow_network' => !is_apk(),
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_history_folder_path'));

            case ACTION_RESET_DEFAULT:
                $data = MediaURL::make(array('filepath' => get_data_path()));
                hd_debug_print("do set history folder to default: $data->filepath");
                $this->plugin->set_history_path();
                return $action_reload;

            case self::CONTROL_COPY_TO_DATA:
                $history_path = $this->plugin->get_history_path();
                hd_debug_print("copy to: $history_path");
                if (!$this->CopyData(get_data_path('history'), "/" . PARAM_TV_HISTORY_ITEMS ."$/", $history_path)) {
                    return Action_Factory::show_title_dialog(TR::t('err_copy'));
                }

                return Action_Factory::show_title_dialog(TR::t('setup_copy_done'), $action_reload);

            case self::CONTROL_COPY_TO_PLUGIN:
                hd_debug_print("copy to: " . get_data_path());
                if (!$this->CopyData($this->plugin->get_history_path(), "*" . PARAM_TV_HISTORY_ITEMS, get_data_path('history'))) {
                    return Action_Factory::show_title_dialog(TR::t('err_copy'));
                }

                return Action_Factory::show_title_dialog(TR::t('setup_copy_done'), $action_reload);

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("do clear TV history");
                $this->plugin->playback_points->clear_points();

                return Action_Factory::show_title_dialog(TR::t('setup_history_cleared'), $action_reload);

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                hd_debug_print(ACTION_FOLDER_SELECTED . " $data->filepath");
                $this->plugin->set_history_path($data->filepath);

                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->caption),
                    $action_reload, $data->filepath, self::CONTROLS_WIDTH);

            case ACTION_RELOAD:
                hd_debug_print("reload");
                return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies),
                    User_Input_Handler_Registry::create_action_screen(Starnet_Tv_Rows_Screen::ID, ACTION_REFRESH_SCREEN));
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }

    private function CopyData($sourcePath, $source_pattern, $destPath){
        if (empty($sourcePath) || empty($destPath)) {
            hd_debug_print("One of is empty: sourceDir = $sourcePath | destDir = $destPath", LOG_LEVEL_ERROR);
            return false;
        }

        if (!create_path($destPath)) {
            hd_debug_print("Can't create destination folder: $destPath", LOG_LEVEL_ERROR);
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
