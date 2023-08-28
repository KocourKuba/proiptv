<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_History_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'history_setup';

    const SETUP_ACTION_HISTORY_CHANGE_FOLDER = 'history_change_folder';
    const SETUP_ACTION_TV_HISTORY_CLEAR = 'history_clear_tv';
    const SETUP_ACTION_COPY_TO_DATA = 'copy_to_data';
    const SETUP_ACTION_COPY_TO_PLUGIN = 'copy_to_plugin';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return false|string
     */
    public static function get_media_url_str()
    {
        return MediaURL::encode(array('screen_id' => self::ID));
    }

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct(self::ID, $plugin);

        $plugin->create_screen($this);
    }

    /**
     * @return string
     */
    public function get_handler_id()
    {
        return self::ID . '_handler';
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * defs for all controls on screen
     * @param $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        //hd_print(__METHOD__);
        $defs = array();

        $folder_icon = $this->plugin->get_image_path('folder.png');
        $remove_icon = $this->plugin->get_image_path('brush.png');
        $refresh_icon = $this->plugin->get_image_path('refresh.png');

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // history

        $history_path = $this->get_history_path();
        hd_print(__METHOD__ . ": history path: $history_path");
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
            self::SETUP_ACTION_TV_HISTORY_CLEAR, TR::t('setup_tv_history_clear'), TR::t('clear'), $remove_icon, self::CONTROLS_WIDTH);

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
            hd_print(__METHOD__ . ": Setup: changing $control_id value to $new_value");
        }

        switch ($control_id) {
            case self::SETUP_ACTION_HISTORY_CHANGE_FOLDER:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'save_data' => self::ID,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_history_folder_path'));

            case ACTION_RESET_DEFAULT:
                $data = MediaURL::make(array('filepath' => get_data_path()));
                hd_print(__METHOD__ . ": do set history folder to default: $data->filepath");
                $this->set_history_path(get_data_path());
                return $action_reload;

            case self::SETUP_ACTION_COPY_TO_DATA:
                $history_path = $this->get_history_path();
                hd_print(__METHOD__ . ": copy to: $history_path");
                if (!self::CopyData(get_data_path("*" . TV_HISTORY_ITEMS), $history_path)) {
                    return Action_Factory::show_title_dialog(TR::t('err_copy'));
                }

                return Action_Factory::show_title_dialog(TR::t('setup_copy_done'), $action_reload);

            case self::SETUP_ACTION_COPY_TO_PLUGIN:
                $history_path = $this->get_history_path();
                hd_print(__METHOD__ . ": copy to: " . get_data_path());
                if (!self::CopyData($history_path . "*" . TV_HISTORY_ITEMS, get_data_path())) {
                    return Action_Factory::show_title_dialog(TR::t('err_copy'));
                }

                return Action_Factory::show_title_dialog(TR::t('setup_copy_done'), $action_reload);

            case self::SETUP_ACTION_TV_HISTORY_CLEAR:
                hd_print(__METHOD__ . ": do clear TV history");
                $this->plugin->playback_points->clear_points();

                return Action_Factory::show_title_dialog(TR::t('setup_history_cleared'), $action_reload);

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                hd_print(__METHOD__ . ": " . ACTION_FOLDER_SELECTED . " $data->filepath");
                $this->set_history_path($data->filepath);

                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->caption),
                    $action_reload, $data->filepath, self::CONTROLS_WIDTH);

            case ACTION_RELOAD:
                hd_print(__METHOD__ . ": reload");
                return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies),
                    User_Input_Handler_Registry::create_action_screen(Starnet_Tv_Rows_Screen::ID, ACTION_REFRESH_SCREEN));
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }

    private function get_history_path()
    {
        $path = $this->plugin->get_parameters(PARAM_HISTORY_PATH, get_data_path());
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }

        return $path;
    }

    private function set_history_path($path)
    {
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }
        $this->plugin->set_parameters(PARAM_HISTORY_PATH, $path);
    }

    public static function CopyData($sourcePath, $destPath){
        if (empty($sourcePath) || empty($destPath)) {
            hd_print(__METHOD__ . ": sourceDir = $sourcePath | destDir = $destPath");
            return false;
        }

        foreach (glob($sourcePath) as $file) {
            $dest_file = $destPath . basename($file);
            hd_print(__METHOD__ . ": copy $file to $dest_file");
            if (!copy($file, $dest_file))
                return false;
        }
        return true;
    }
}
