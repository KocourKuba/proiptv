<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Playlists_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'channels_setup';

    const SETUP_ACTION_RESET_PLAYLIST_DLG = 'reset_playlist';
    const SETUP_ACTION_RESET_PLAYLIST_APPLY = 'reset_playlist_apply';
    ///////////////////////////////////////////////////////////////////////

    private static $on_off_ops = array
    (
        SetupControlSwitchDefs::switch_on => '%tr%yes',
        SetupControlSwitchDefs::switch_off => '%tr%no',
    );

    private static $on_off_img = array
    (
        SetupControlSwitchDefs::switch_on => 'on.png',
        SetupControlSwitchDefs::switch_off => 'off.png',
    );

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
        hd_print(__METHOD__);
        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // playlists
        $playlist_idx = $this->plugin->get_playlists_idx();
        $display_path = array();
        foreach ($this->plugin->get_playlists()->get_order() as $item) {
            $display_path[] = HD::string_ellipsis($item);
        }
        if (empty($display_path)) {
            Control_Factory::add_label($defs, TR::t('setup_channels_src_playlists'), TR::t('setup_channels_src_no_playlists'));
        } else if (count($display_path) > 1) {
            if ($playlist_idx >= count($display_path)) {
                $this->plugin->set_playlists_idx(0);
            }
            Control_Factory::add_combobox($defs, $this, null, ACTION_CHANGE_PLAYLIST,
                TR::t('setup_channels_src_playlists'), $playlist_idx, $display_path, self::CONTROLS_WIDTH, true);
        } else {
            Control_Factory::add_label($defs, TR::t('setup_channels_src_playlists'), $display_path[0]);
            $this->plugin->set_playlists_idx(0);
        }

        //////////////////////////////////////
        // playlist import source

        Control_Factory::add_image_button($defs, $this, null,
            ACTION_ITEMS_EDIT, TR::t('setup_channels_src_edit_playlists'), TR::t('edit'),
            $this->plugin->get_image_path('edit.png'), self::CONTROLS_WIDTH);

        $catchup_ops[KnownCatchupSourceTags::cu_unknown] = TR::t('by_default');
        $catchup_ops[KnownCatchupSourceTags::cu_default] = KnownCatchupSourceTags::cu_default;
        $catchup_ops[KnownCatchupSourceTags::cu_shift] = KnownCatchupSourceTags::cu_shift;
        $catchup_ops[KnownCatchupSourceTags::cu_append] = KnownCatchupSourceTags::cu_append;
        $catchup_ops[KnownCatchupSourceTags::cu_flussonic] = KnownCatchupSourceTags::cu_flussonic;
        $catchup_ops[KnownCatchupSourceTags::cu_xstreamcode] = KnownCatchupSourceTags::cu_xstreamcode;
        $catchup_idx = $this->plugin->get_parameters(PARAM_USER_CATCHUP, KnownCatchupSourceTags::cu_unknown);
        Control_Factory::add_combobox($defs, $this, null, PARAM_USER_CATCHUP,
            TR::t('setup_channels_archive_type'), $catchup_idx, $catchup_ops, self::CONTROLS_WIDTH, true);

        $square_icons = $this->plugin->get_settings(PARAM_SQUARE_ICONS, SetupControlSwitchDefs::switch_off);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SQUARE_ICONS, TR::t('setup_channels_square_icons'), self::$on_off_ops[$square_icons],
            $this->plugin->get_image_path(self::$on_off_img[$square_icons]), self::CONTROLS_WIDTH);

        Control_Factory::add_image_button($defs, $this, null, self::SETUP_ACTION_RESET_PLAYLIST_DLG,
            TR::t('setup_channels_src_reset_playlist'), TR::t('clear'),
            $this->plugin->get_image_path('brush.png'), self::CONTROLS_WIDTH);

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
        dump_input_handler(__METHOD__, $user_input);

        $control_id = $user_input->control_id;
        $new_value = '';
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_print(__METHOD__ . ": changing $control_id value to $new_value");
        }

        switch ($control_id) {

            case ACTION_CHANGE_PLAYLIST:
                hd_print(__METHOD__ . ": Change playlist index: $new_value");
                $old_value = $this->plugin->get_playlists_idx();
                $this->plugin->set_playlists_idx($new_value);
                $action = $this->plugin->tv->reload_channels($this, $plugin_cookies);
                if ($action === null) {
                    $this->plugin->set_playlists_idx($old_value);
                    return Action_Factory::show_title_dialog(TR::t('err_load_playlist'));
                }
                return $action;

            case ACTION_ITEMS_EDIT:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Edit_List_Screen::ID,
                        'source_window_id' => self::ID,
                        'end_action' => ACTION_RELOAD,
                        'extension' => '|\.(m3u8?)$|i',
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_channels_src_edit_playlists'));

            case PARAM_USER_CATCHUP:
                $this->plugin->set_settings(PARAM_USER_CATCHUP, $new_value);
                break;

            case PARAM_SQUARE_ICONS:
                $old_val = $this->plugin->get_settings(PARAM_SQUARE_ICONS, SetupControlSwitchDefs::switch_off);
                hd_print(__METHOD__ . ": old " . PARAM_SQUARE_ICONS . ": $old_val");
                $new_val = ($old_val === SetupControlSwitchDefs::switch_off)
                    ? SetupControlSwitchDefs::switch_on
                    : SetupControlSwitchDefs::switch_off;

                $this->plugin->set_settings(PARAM_SQUARE_ICONS, $new_val);
                hd_print(__METHOD__ . ": new " . PARAM_SQUARE_ICONS . ": $new_val");
                break;

            case self::SETUP_ACTION_RESET_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::SETUP_ACTION_RESET_PLAYLIST_APPLY);

            case self::SETUP_ACTION_RESET_PLAYLIST_APPLY: // handle streaming settings dialog result
                $this->plugin->remove_settings();
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RELOAD:
                hd_print(__METHOD__ . ": reload");
                return $this->plugin->tv->reload_channels($this, $plugin_cookies);
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
