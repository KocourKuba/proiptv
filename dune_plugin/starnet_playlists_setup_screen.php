<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Playlists_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'channels_setup';

    const SETUP_ACTION_PLAYLIST_SOURCE = 'playlist_source';
    const SETUP_ACTION_CHOOSE_PL_FOLDER = 'choose_playlists_folder';
    const SETUP_ACTION_IMPORT_LIST = 'import_list';
    const SETUP_ACTION_CHANNELS_URL_PATH = 'channels_url_path';
    const SETUP_ACTION_ADD_URL_DLG = 'add_url_dialog';
    const SETUP_ACTION_REMOVE_PLAYLIST = 'remove_playlist';
    const SETUP_ACTION_CHANNELS_URL_APPLY = 'channels_url_apply';
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
        $web_icon = $this->plugin->get_image_path('web.png');
        $link_icon = $this->plugin->get_image_path('link.png');

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // playlists
        $playlist_idx = $this->plugin->get_parameters(PARAM_PLAYLIST_IDX, 0);
        $display_path = array();
        $playlists = $this->plugin->get_parameters(PARAM_PLAYLISTS, array());
        foreach ($playlists as $item) {
            $display_path[] = HD::string_ellipsis($item);
        }
        if (empty($display_path)) {
            Control_Factory::add_label($defs, TR::t('setup_channels_src_playlists'), TR::t('setup_channels_src_no_playlists'));
        } else if (count($display_path) > 1) {
            if ($playlist_idx >= count($display_path)) {
                $this->plugin->set_parameters(PARAM_PLAYLIST_IDX, 0);
            }
            Control_Factory::add_combobox($defs, $this, null, ACTION_CHANGE_PLAYLIST,
                TR::t('setup_channels_src_playlists'), $playlist_idx, $display_path, self::CONTROLS_WIDTH, true);
        } else {
            Control_Factory::add_label($defs, TR::t('setup_channels_src_playlists'), $display_path[0]);
            $this->plugin->set_parameters(PARAM_PLAYLIST_IDX, 0);
        }

        if (!empty($display_path)) {
            Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_REMOVE_PLAYLIST,
                TR::t('setup_channels_src_remove_playlist'), TR::t('delete'), $web_icon, self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // playlist import source
        $source_ops[1] = TR::t('setup_channels_src_direct');
        $source_ops[2] = TR::t('setup_channels_src_folder');
        $source_ops[3] = TR::t('setup_channels_src_list');
        $channels_source = $this->plugin->get_parameters(self::SETUP_ACTION_PLAYLIST_SOURCE, 1);

        Control_Factory::add_combobox($defs, $this, null, self::SETUP_ACTION_PLAYLIST_SOURCE,
            TR::t('setup_channels_src_combo'), $channels_source, $source_ops, self::CONTROLS_WIDTH, true);

        switch ($channels_source)
        {
            case 1: // internet url
                Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_ADD_URL_DLG,
                    TR::t('setup_channels_src_internet_path'), TR::t('setup_channels_add_caption'), $web_icon, self::CONTROLS_WIDTH);
                break;
            case 2: // m3u folder
                if (!is_apk()) {
                    Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_CHOOSE_PL_FOLDER,
                        TR::t('setup_channels_src_folder_path'), TR::t('setup_channels_src_folder'), $folder_icon, self::CONTROLS_WIDTH);
                }
                break;
            case 3: // user defined list
                Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_IMPORT_LIST,
                    TR::t('setup_channels_src_list'), TR::t('setup_channels_add_caption'), $link_icon, self::CONTROLS_WIDTH);
                break;
        }

        Control_Factory::add_vgap($defs, 10);

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

    /**
     * user remote input handler Implementation of UserInputHandler
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);

        $control_id = $user_input->control_id;
        $new_value = '';
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_print(__METHOD__ . ": Setup: changing $control_id value to $new_value");
        }

        switch ($control_id) {

            case ACTION_CHANGE_PLAYLIST:
                hd_print(__METHOD__ . ": Change playlist index: $new_value");
                $old_value = $this->plugin->get_parameters(PARAM_PLAYLIST_IDX, 0);
                $this->plugin->set_parameters(PARAM_PLAYLIST_IDX, $new_value);
                $action = $this->plugin->tv->reload_channels($this, $plugin_cookies);
                if ($action === null) {
                    $this->plugin->set_parameters(PARAM_PLAYLIST_IDX, $old_value);
                    return Action_Factory::show_title_dialog(TR::t('err_load_playlist'));
                }
                return $action;

            case self::SETUP_ACTION_REMOVE_PLAYLIST:
                $playlists = $this->plugin->get_parameters(PARAM_PLAYLISTS, array());
                hd_print(__METHOD__ . ": total playlists: " . count($playlists));
                $idx = $this->plugin->get_parameters(PARAM_PLAYLIST_IDX, 0);
                hd_print(__METHOD__ . ": removed index: $idx");
                if (isset($playlists[$idx])) {
                    $this->plugin->remove_settings();
                    unset($playlists[$idx]);
                    $playlists = array_values($playlists);
                    $this->plugin->set_parameters(PARAM_PLAYLISTS, $playlists);
                    if ($idx >= count($playlists)) {
                        $idx = count($playlists) - 1;
                        $this->plugin->set_parameters(PARAM_PLAYLIST_IDX, $idx);
                    }
                } else {
                    hd_print(__METHOD__ . ": Bad index: $idx");
                }
                break;

            case self::SETUP_ACTION_PLAYLIST_SOURCE:
                $this->plugin->set_parameters(self::SETUP_ACTION_PLAYLIST_SOURCE, $user_input->{$control_id});
                break;

            case self::SETUP_ACTION_ADD_URL_DLG:
                $defs = array();
                Control_Factory::add_vgap($defs, 20);
                Control_Factory::add_text_field($defs, $this, null, self::SETUP_ACTION_CHANNELS_URL_PATH, '',
                    'http://', false, false, false, true, self::CONTROLS_WIDTH);

                Control_Factory::add_vgap($defs, 50);

                Control_Factory::add_close_dialog_and_apply_button($defs, $this, null,
                    self::SETUP_ACTION_CHANNELS_URL_APPLY, TR::t('ok'), 300);
                Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
                Control_Factory::add_vgap($defs, 10);

                return Action_Factory::show_dialog(TR::t('setup_channels_src_link_caption'), $defs, true);

            case self::SETUP_ACTION_CHANNELS_URL_APPLY: // handle streaming settings dialog result
                if (isset($user_input->{self::SETUP_ACTION_CHANNELS_URL_PATH})
                    && preg_match("|https?://.+$|", $user_input->{self::SETUP_ACTION_CHANNELS_URL_PATH})) {

                    $pl = $user_input->{self::SETUP_ACTION_CHANNELS_URL_PATH};
                    $playlists = $this->plugin->get_parameters(PARAM_PLAYLISTS, array());

                    if (in_array($pl, $playlists)) {
                        return Action_Factory::show_title_dialog(TR::t('err_playlist_exist'));
                    }

                    $result = false;
                    $tmp_filename = get_temp_path('test_playlist.tmp');
                    try {
                        HD::http_save_document($pl, $tmp_filename);
                        $playlists[] = $pl;
                        hd_print(__METHOD__ . ": added new playlist: $pl");
                        $this->plugin->set_parameters(PARAM_PLAYLISTS, $playlists);
                        $result = true;
                    } catch (Exception $ex) {
                        hd_print(__METHOD__ . ": Failed to download playlist from: $pl");
                    }

                    if (file_exists($tmp_filename)) {
                        unlink($tmp_filename);
                    }

                    if (!$result) {
                        return Action_Factory::show_title_dialog(TR::t('err_load_playlist'));
                    }
                }
                break;

            case self::SETUP_ACTION_CHOOSE_PL_FOLDER:
                $media_url = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'parent_id' => self::ID,
                        'save_data' => self::ID,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('setup_channels_src_folder'));

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                smb_tree::set_folder_info($plugin_cookies, $data, PARAM_PLAYLIST_FOLDER);
                hd_print(__METHOD__ . ": " . ACTION_FOLDER_SELECTED . " $data->filepath");
                $files = preg_grep('/\.(m3u8?)$/i', glob("$data->filepath/*.*"));
                if (empty($files)) {
                    return Action_Factory::show_title_dialog(TR::t('setup_channels_src_no_playlists'));
                }

                $playlists = $this->plugin->get_parameters(PARAM_PLAYLISTS, array());
                $old_count = count($playlists);
                foreach ($files as $file) {
                    //hd_print("file: $file");
                    if (is_file($file) && !in_array($file, $playlists)) {
                        $playlists[] = $file;
                    }
                }

                $this->plugin->set_parameters(PARAM_PLAYLISTS, $playlists);
                return Action_Factory::show_title_dialog(TR::t('setup_channels_src_playlists_added__1', count($playlists) - $old_count),
                    User_Input_Handler_Registry::create_action($this, ACTION_RELOAD));

            case self::SETUP_ACTION_IMPORT_LIST:
                $media_url = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'save_file'	=> array(
                            'parent_id'	=> self::ID,
                            'action'	=> 'choose_file',
                            'arg'		=> 0,
                            'extension'	=> 'txt'
                        ),
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('setup_channels_src_playlists'));

            case ACTION_RELOAD:
                hd_print(__METHOD__ . ": reload");
                return $this->plugin->tv->reload_channels($this, $plugin_cookies);
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
