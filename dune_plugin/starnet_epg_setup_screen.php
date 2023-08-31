<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';
require_once 'lib/epg_manager.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Epg_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'epg_setup';

    const SETUP_ACTION_EPG_SOURCE = 'epg_source';
    const SETUP_ACTION_XMLTV_EPG_IDX = 'xmltv_epg_idx';
    const SETUP_ACTION_CHANGE_XMLTV_CACHE_PATH = 'xmltv_cache_path';
    const SETUP_ACTION_EPG_CACHE_TTL = 'epg_cache_ttl';
    const SETUP_ACTION_ITEMS_CLEAR_EPG_CACHE = 'clear_epg_cache';
    const SETUP_ACTION_EPG_SHIFT = 'epg_shift';
    const SETUP_ACTION_EPG_PARSE_ALL = 'epg_parse_all';
    const SETUP_ACTION_EPG_URL_PATH = 'epg_url_path';
    const SETUP_ACTION_EPG_URL_APPLY = 'epg_url_apply';

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
     * EPG dialog defs
     * @param $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        //hd_print(__METHOD__);
        $defs = array();

        $this->plugin->init_playlist();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // EPG Source
        $epg_source_ops[PARAM_EPG_SOURCE_INTERNAL] = '%tr%setup_internal';
        $epg_source_ops[PARAM_EPG_SOURCE_EXTERNAL] = '%tr%setup_external';
        $source = $this->plugin->get_settings(PARAM_EPG_SOURCE, PARAM_EPG_SOURCE_INTERNAL);
        Control_Factory::add_combobox($defs, $this, null,
            self::SETUP_ACTION_EPG_SOURCE, TR::t('setup_epg_type'),
            $source, $epg_source_ops, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // XMLTV sources

        if ($source === PARAM_EPG_SOURCE_INTERNAL) {
            $sources = $this->plugin->m3u_parser->getXmltvSources();
        } else {
            $order = new Ordered_Array($this->plugin, PARAM_CUSTOM_XMLTV_SOURCES);
            $sources = $order->get_order();
        }

        $source_idx = $this->plugin->get_settings(PARAM_EPG_IDX, array());
        $idx = isset($source_idx[$source]) ? $source_idx[$source] : 0;

        $display_path = array();
        foreach ($sources as $item) {
            $display_path[] = HD::string_ellipsis($item);
        }

        if (empty($display_path)) {
            Control_Factory::add_label($defs, TR::t('setup_xmltv_epg_source'), TR::t('no'));
        } else {
            if (count($display_path) > 1) {
                Control_Factory::add_combobox($defs, $this, null,
                    self::SETUP_ACTION_XMLTV_EPG_IDX, TR::t('setup_xmltv_epg_source'),
                    $idx, $display_path, self::CONTROLS_WIDTH, true);
            } else {
                Control_Factory::add_label($defs, TR::t('setup_xmltv_epg_source'), $display_path[0]);
                $source_idx[$source] = 0;
                $this->plugin->set_parameters(PARAM_EPG_IDX, $source_idx);
            }

            Control_Factory::add_image_button($defs, $this, null, ACTION_RELOAD,
                TR::t('setup_reload_xmltv_epg'), TR::t('refresh'), $this->plugin->get_image_path('refresh.png'), self::CONTROLS_WIDTH);
        }

        if ($source === PARAM_EPG_SOURCE_EXTERNAL) {
            Control_Factory::add_image_button($defs, $this, null, ACTION_ITEMS_EDIT,
                TR::t('setup_edit_xmltv_list'), TR::t('edit'), $this->plugin->get_image_path('edit.png'), self::CONTROLS_WIDTH);
        }

        $parse_all = $this->plugin->get_settings(PARAM_EPG_PARSE_ALL, SetupControlSwitchDefs::switch_on);
        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_EPG_PARSE_ALL, TR::t('setup_epg_parse_all'), SetupControlSwitchDefs::$on_off_translated[$parse_all],
            $this->plugin->get_image_path(SetupControlSwitchDefs::$on_off_img[$parse_all]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // EPG cache dir
        $xcache_dir = smb_tree::get_folder_info($this->plugin->get_parameters(PARAM_XMLTV_CACHE_PATH), get_data_path("epg_cache/"));
        $free_size = TR::t('setup_storage_info__1', HD::get_storage_size(dirname($xcache_dir)));
        $xcache_dir = HD::string_ellipsis($xcache_dir);
        if (is_apk()) {
            Control_Factory::add_label($defs, $free_size, $xcache_dir);
        } else {
            Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_CHANGE_XMLTV_CACHE_PATH,
                $free_size, $xcache_dir, $this->plugin->get_image_path('folder.png'), self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // EPG cache
        $epg_cache_ops = array();
        $epg_cache_ops[1] = 1;
        $epg_cache_ops[2] = 2;
        $epg_cache_ops[3] = 3;
        $epg_cache_ops[5] = 5;
        $epg_cache_ops[7] = 7;

        $cache_ttl = $this->plugin->get_settings(PARAM_EPG_CACHE_TTL, 3);
        Control_Factory::add_combobox($defs, $this, null,
            self::SETUP_ACTION_EPG_CACHE_TTL, TR::t('setup_epg_cache_ttl'),
            $cache_ttl, $epg_cache_ops, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // clear epg cache
        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_ITEMS_CLEAR_EPG_CACHE, TR::t('entry_epg_cache_clear'), TR::t('clear'),
            $this->plugin->get_image_path('brush.png'), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // epg time shift
        /*
        $show_epg_shift_ops = array();
        for ($i = -11; $i < 12; $i++) {
            $show_epg_shift_ops[$i] = TR::t('setup_epg_shift__1', sprintf("%+03d", $i));
        }
        $show_epg_shift_ops[0] = TR::t('setup_epg_shift_default__1', "00");

        if (!isset($plugin_cookies->{self::SETUP_ACTION_EPG_SHIFT})) {
            $plugin_cookies->{self::SETUP_ACTION_EPG_SHIFT} = 0;
        }
        Control_Factory::add_combobox($defs, $this, null,
            self::SETUP_ACTION_EPG_SHIFT, TR::t('setup_epg_shift'),
            $plugin_cookies->{self::SETUP_ACTION_EPG_SHIFT}, $show_epg_shift_ops, self::CONTROLS_WIDTH);
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
            hd_print(__METHOD__ . ": Setup: changing $control_id value to $new_value");
        }

        switch ($control_id) {
            case self::SETUP_ACTION_EPG_SOURCE:
                $new = $user_input->{$control_id};
                $this->plugin->set_settings(PARAM_EPG_SOURCE, $new);
                hd_print(__METHOD__ . ": Selected epg source: $new");
                break;

            case self::SETUP_ACTION_XMLTV_EPG_IDX:
                $source = $this->plugin->get_settings(PARAM_EPG_SOURCE, PARAM_EPG_SOURCE_INTERNAL);
                $source_idx = $this->plugin->get_settings(PARAM_EPG_IDX, array());
                $source_idx[$source] = $user_input->{$control_id};
                $this->plugin->set_settings(PARAM_EPG_IDX, $source_idx);
                hd_print(__METHOD__ . ": Selected xmltv epg idx: $source_idx[$source] for $source");
                $res = $this->plugin->epg_man->is_xmltv_cache_valid();
                if (!empty($res)) {
                    return Action_Factory::show_title_dialog(TR::t('err_load_xmltv_epg'));
                }

                $this->plugin->update_xmltv_source();
                break;

            case self::SETUP_ACTION_CHANGE_XMLTV_CACHE_PATH:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'parent_id' => self::ID,
                        'allow_network' => false,
                        'choose_folder' => self::ID,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_epg_xmltv_cache_caption'));

            case self::SETUP_ACTION_EPG_CACHE_TTL:
                $this->plugin->set_parameters(PARAM_EPG_CACHE_TTL, $user_input->{$control_id});
                break;

            case self::SETUP_ACTION_EPG_SHIFT:
                $this->plugin->set_settings(PARAM_EPG_SHIFT, $user_input->{$control_id});
                break;

            case self::SETUP_ACTION_EPG_PARSE_ALL:
                $this->plugin->toggle_setting(PARAM_EPG_PARSE_ALL, SetupControlSwitchDefs::switch_off);
                break;

            case self::SETUP_ACTION_ITEMS_CLEAR_EPG_CACHE:
                $this->plugin->tv->unload_channels();
                $this->plugin->epg_man->clear_epg_cache();
                return Action_Factory::show_title_dialog(TR::t('entry_epg_cache_cleared'),
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies)));

            case ACTION_ITEMS_EDIT:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Edit_List_Screen::ID,
                        'source_window_id' => self::ID,
                        'edit_list' => Starnet_Edit_List_Screen::SCREEN_TYPE_EPG_LIST,
                        'end_action' => ACTION_RELOAD,
                        'extension' => EPG_PATTERN,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_edit_xmltv_list'));

            case ACTION_RESET_DEFAULT:
                hd_print(__METHOD__ . ": " . ACTION_RESET_DEFAULT);
                $this->plugin->epg_man->clear_all_epg_cache();

                $data = MediaURL::make(array('filepath' => get_data_path("epg_cache/")));
                $this->plugin->set_parameters(PARAM_XMLTV_CACHE_PATH, smb_tree::set_folder_info( $data));
                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', get_data_path("epg_cache/")),
                    $action_reload, $data->filepath, self::CONTROLS_WIDTH);

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                hd_print(__METHOD__ . ": " . ACTION_FOLDER_SELECTED . " $data->filepath");
                $this->plugin->epg_man->clear_all_epg_cache();
                $this->plugin->set_parameters(PARAM_XMLTV_CACHE_PATH, smb_tree::set_folder_info( $data));
                $this->plugin->epg_man->init_cache_dir();

                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->caption),
                    $action_reload, $data->filepath, self::CONTROLS_WIDTH);

            case ACTION_RELOAD:
                hd_print(__METHOD__ . ": " . ACTION_RELOAD);
                $this->plugin->tv->unload_channels();
                $this->plugin->update_xmltv_source();
                return $this->plugin->tv->reload_channels($this, $plugin_cookies);
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
