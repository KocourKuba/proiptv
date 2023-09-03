<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_History_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'history_setup';

    const SETUP_ACTION_HISTORY_CHANGE_FOLDER = 'history_change_folder';
    const SETUP_ACTION_COPY_TO_DATA = 'copy_to_data';
    const SETUP_ACTION_COPY_TO_PLUGIN = 'copy_to_plugin';

    ///////////////////////////////////////////////////////////////////////

    /**
     * defs for all controls on screen
     * @param $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        //hd_debug_print();
        $defs = array();

        $folder_icon = get_image_path('folder.png');
        $remove_icon = get_image_path('brush.png');
        $refresh_icon = get_image_path('refresh.png');

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // history

        $history_path = $this->get_history_path();
        hd_debug_print("history path: $history_path");
        $display_path = HD::string_ellipsis($history_path);

        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_HISTORY_CHANGE_FOLDER, TR::t('setup_history_folder_path'), $display_path, $folder_icon, self::CONTROLS_WIDTH);

        if ($history_path !== get_data_path()) {
            Control_Factory::add_image_button($defs, $this, null,
                self::SETUP_ACTION_COPY_TO_DATA, TR::t('setup_copy_to_data'), TR::t('apply'), $refresh_icon, self::CONTROLS_WIDTH);

            Control_Factory::add_image_button($defs, $this, null,
                self::SETUP_ACTION_COPY_TO_PLUGIN, TR::t('setup_copy_to_plugin'), TR::t('apply'), $refresh_icon, self::CONTROLS_WIDTH);
        }

        Control_Factory::add_image_button($defs, $this, null,
            ACTION_ITEMS_CLEAR, TR::t('setup_tv_history_clear'), TR::t('clear'), $remove_icon, self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs($plugin_cookies);
    }

    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);

        $action_reload = User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);
        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("Setup: changing $control_id value to $new_value");
        }

        switch ($control_id) {
            case self::SETUP_ACTION_HISTORY_CHANGE_FOLDER:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'choose_folder' => static::ID,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_history_folder_path'));

            case ACTION_RESET_DEFAULT:
                $data = MediaURL::make(array('filepath' => get_data_path()));
                hd_debug_print("do set history folder to default: $data->filepath");
                $this->set_history_path(get_data_path());
                return $action_reload;

            case self::SETUP_ACTION_COPY_TO_DATA:
                $history_path = $this->get_history_path();
                hd_debug_print("copy to: $history_path");
                if (!self::CopyData(get_data_path(), "/" . PARAM_TV_HISTORY_ITEMS ."$/", $history_path)) {
                    return Action_Factory::show_title_dialog(TR::t('err_copy'));
                }

                return Action_Factory::show_title_dialog(TR::t('setup_copy_done'), $action_reload);

            case self::SETUP_ACTION_COPY_TO_PLUGIN:
                $history_path = $this->get_history_path();
                hd_debug_print("copy to: " . get_data_path());
                if (!self::CopyData($history_path, "*" . PARAM_TV_HISTORY_ITEMS, get_data_path())) {
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
                $this->set_history_path($data->filepath);

                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->caption),
                    $action_reload, $data->filepath, self::CONTROLS_WIDTH);

            case ACTION_RELOAD:
                hd_debug_print("reload");
                return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies),
                    User_Input_Handler_Registry::create_action_screen(Starnet_Tv_Rows_Screen::ID, ACTION_REFRESH_SCREEN));
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }

    private function get_history_path()
    {
        return get_slash_trailed_path($this->plugin->get_parameters(PARAM_HISTORY_PATH, get_data_path()));
    }

    private function set_history_path($path)
    {
        $this->plugin->set_parameters(PARAM_HISTORY_PATH, get_slash_trailed_path($path));
    }

    public static function CopyData($sourcePath, $source_pattern, $destPath){
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
