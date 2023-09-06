<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';
require_once 'lib/epg_manager.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Epg_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'epg_setup';

    const CONTROL_EPG_SOURCE_TYPE = 'epg_source_type';
    const CONTROL_XMLTV_EPG_IDX = 'xmltv_epg_idx';
    const CONTROL_CHANGE_XMLTV_CACHE_PATH = 'xmltv_cache_path';
    const CONTROL_ITEMS_CLEAR_EPG_CACHE = 'clear_epg_cache';
    const CONTROL_EPG_PARSE_ALL = 'epg_parse_all';

    ///////////////////////////////////////////////////////////////////////

    /**
     * EPG dialog defs
     * @param $plugin_cookies
     * @return array
     * @noinspection PhpUnusedParameterInspection
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        //hd_debug_print();
        $defs = array();

        $this->plugin->init_playlist();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        $source_type = $this->plugin->get_xmltv_source_type();

        //////////////////////////////////////
        // EPG Source
        $epg_source_ops[PARAM_EPG_SOURCE_INTERNAL] = '%tr%setup_internal';
        $epg_source_ops[PARAM_EPG_SOURCE_EXTERNAL] = '%tr%setup_external';
        Control_Factory::add_combobox($defs, $this, null,
            self::CONTROL_EPG_SOURCE_TYPE, TR::t('setup_epg_type'),
            $source_type, $epg_source_ops, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // XMLTV sources

        $order = $this->plugin->get_xmltv_sources();
        $sources = $order->get_order();
        $source_idx = $order->get_saved_pos();

        $display_path = array();
        foreach ($sources as $item) {
            $display_path[] = HD::string_ellipsis($item);
        }

        if (empty($display_path)) {
            Control_Factory::add_label($defs, TR::t('setup_xmltv_epg_source'), TR::t('no'));
        } else {
            if (count($display_path) > 1) {
                Control_Factory::add_combobox($defs, $this, null,
                    self::CONTROL_XMLTV_EPG_IDX, TR::t('setup_xmltv_epg_source'),
                    $source_idx, $display_path, self::CONTROLS_WIDTH, true);
            } else {
                Control_Factory::add_label($defs, TR::t('setup_xmltv_epg_source'), $display_path[0]);
            }

            Control_Factory::add_image_button($defs, $this, null, ACTION_RELOAD,
                TR::t('setup_reload_xmltv_epg'), TR::t('refresh'), get_image_path('refresh.png'), self::CONTROLS_WIDTH);
        }

        if ($source_type === PARAM_EPG_SOURCE_EXTERNAL) {
            Control_Factory::add_image_button($defs, $this, null, ACTION_ITEMS_EDIT,
                TR::t('setup_edit_xmltv_list'), TR::t('edit'), get_image_path('edit.png'), self::CONTROLS_WIDTH);
        }

        $parse_all = $this->plugin->get_setting(PARAM_EPG_PARSE_ALL, SetupControlSwitchDefs::switch_on);
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_EPG_PARSE_ALL, TR::t('setup_epg_parse_all'), SetupControlSwitchDefs::$on_off_translated[$parse_all],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$parse_all]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // EPG cache dir
        $xcache_dir = smb_tree::get_folder_info($this->plugin->get_parameter(PARAM_XMLTV_CACHE_PATH), get_data_path("epg_cache/"));
        $free_size = TR::t('setup_storage_info__1', HD::get_storage_size(dirname($xcache_dir)));
        $xcache_dir = HD::string_ellipsis($xcache_dir);
        if (is_apk()) {
            Control_Factory::add_label($defs, $free_size, $xcache_dir);
        } else {
            Control_Factory::add_image_button($defs, $this, null, self::CONTROL_CHANGE_XMLTV_CACHE_PATH,
                $free_size, $xcache_dir, get_image_path('folder.png'), self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // EPG cache
        $epg_cache_ops = array();
        $epg_cache_ops[1] = 1;
        $epg_cache_ops[2] = 2;
        $epg_cache_ops[3] = 3;
        $epg_cache_ops[5] = 5;
        $epg_cache_ops[7] = 7;

        $cache_ttl = $this->plugin->get_setting(PARAM_EPG_CACHE_TTL, 3);
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_EPG_CACHE_TTL, TR::t('setup_epg_cache_ttl'),
            $cache_ttl, $epg_cache_ops, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // clear epg cache
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_ITEMS_CLEAR_EPG_CACHE, TR::t('entry_epg_cache_clear'), TR::t('clear'),
            get_image_path('brush.png'), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // epg time shift
        /*
        $show_epg_shift_ops = array();
        for ($i = -11; $i < 12; $i++) {
            $show_epg_shift_ops[$i] = TR::t('setup_epg_shift__1', sprintf("%+03d", $i));
        }
        $show_epg_shift_ops[0] = TR::t('setup_epg_shift_default__1', "00");

        if (!isset($plugin_cookies->{PARAM_EPG_SHIFT})) {
            $plugin_cookies->{PARAM_EPG_SHIFT} = 0;
        }
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_EPG_SHIFT, TR::t('setup_epg_shift'),
            $plugin_cookies->{PARAM_EPG_SHIFT}, $show_epg_shift_ops, self::CONTROLS_WIDTH);
        */
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
            case self::CONTROL_EPG_SOURCE_TYPE:
                $new = $user_input->{$control_id};
                $this->plugin->set_xmltv_source_type($new);
                hd_debug_print("Selected epg source: $new");
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case self::CONTROL_XMLTV_EPG_IDX:
                $this->plugin->set_xmltv_source_idx($user_input->{$control_id});
                hd_debug_print("Selected xmltv epg idx: " . $user_input->{$control_id});
                $res = $this->plugin->epg_man->is_xmltv_cache_valid();
                if (!empty($res)) {
                    return Action_Factory::show_title_dialog(TR::t('err_load_xmltv_epg'));
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case self::CONTROL_CHANGE_XMLTV_CACHE_PATH:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'allow_network' => false,
                        'choose_folder' => static::ID,
                        'end_action' => ACTION_RELOAD,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_epg_xmltv_cache_caption'));

            case PARAM_EPG_CACHE_TTL:
            case PARAM_EPG_SHIFT:
                $this->plugin->set_setting($control_id, $user_input->{$control_id});
                break;

            case self::CONTROL_EPG_PARSE_ALL:
                $this->plugin->toggle_setting(PARAM_EPG_PARSE_ALL, SetupControlSwitchDefs::switch_off);
                break;

            case self::CONTROL_ITEMS_CLEAR_EPG_CACHE:
                $this->plugin->tv->unload_channels();
                $this->plugin->epg_man->clear_epg_cache();
                return Action_Factory::show_title_dialog(TR::t('entry_epg_cache_cleared'),
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies)));

            case ACTION_ITEMS_EDIT:
                $this->plugin->set_pospone_save();
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Edit_List_Screen::ID,
                        'source_window_id' => static::ID,
                        'edit_list' => Starnet_Edit_List_Screen::SCREEN_EDIT_EPG_LIST,
                        'end_action' => ACTION_RELOAD,
                        'cancel_action' => RESET_CONTROLS_ACTION_ID,
                        'postpone_save' => PLUGIN_SETTINGS,
                        'extension' => EPG_PATTERN,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_edit_xmltv_list'));

            case ACTION_RESET_DEFAULT:
                hd_debug_print(ACTION_RESET_DEFAULT);
                $this->plugin->epg_man->clear_all_epg_cache();

                $data = MediaURL::make(array('filepath' => get_data_path("epg_cache/")));
                $this->plugin->set_parameter(PARAM_XMLTV_CACHE_PATH, $data);
                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', get_data_path("epg_cache/")),
                    $action_reload, $data->filepath, self::CONTROLS_WIDTH);

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                hd_debug_print(ACTION_FOLDER_SELECTED . ": $data->filepath");
                $this->plugin->epg_man->clear_all_epg_cache();
                $this->plugin->set_parameter(PARAM_XMLTV_CACHE_PATH, smb_tree::set_folder_info( $data));
                $this->plugin->epg_man->init_cache_dir();

                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->caption),
                    $action_reload, $data->filepath, self::CONTROLS_WIDTH);

            case ACTION_RELOAD:
                hd_debug_print(ACTION_RELOAD);
                return $this->plugin->tv->reload_channels($this, $plugin_cookies,
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies)));
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
