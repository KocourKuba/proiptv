<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Interface_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'interface_setup';

    const SETUP_ACTION_SHOW_TV = 'show_tv';
    const SETUP_ACTION_SHOW_ALL = 'show_all';
    const SETUP_ACTION_SHOW_FAVORITES = 'show_favorites';
    const SETUP_ACTION_SHOW_HISTORY = 'show_history';

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
     * interface dialog defs
     * @param $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        //hd_print(__METHOD__);
        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // Show in main screen
        if (!is_apk()) {
            if (!isset($plugin_cookies->{self::SETUP_ACTION_SHOW_TV})) {
                $plugin_cookies->{self::SETUP_ACTION_SHOW_TV} = SetupControlSwitchDefs::switch_on;
            }
            Control_Factory::add_image_button($defs, $this, null,
                self::SETUP_ACTION_SHOW_TV, TR::t('setup_show_in_main'), self::$on_off_ops[$plugin_cookies->{self::SETUP_ACTION_SHOW_TV}],
                $this->plugin->get_image_path(self::$on_off_img[$plugin_cookies->{self::SETUP_ACTION_SHOW_TV}]), self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // show all channels category
        if (!isset($plugin_cookies->{self::SETUP_ACTION_SHOW_ALL})) {
            $plugin_cookies->{self::SETUP_ACTION_SHOW_ALL} = SetupControlSwitchDefs::switch_on;
        }

        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_SHOW_ALL, TR::t('setup_show_all_channels'), self::$on_off_ops[$plugin_cookies->{self::SETUP_ACTION_SHOW_ALL}],
            $this->plugin->get_image_path(self::$on_off_img[$plugin_cookies->{self::SETUP_ACTION_SHOW_ALL}]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show favorites category
        if (!isset($plugin_cookies->{self::SETUP_ACTION_SHOW_FAVORITES})) {
            $plugin_cookies->{self::SETUP_ACTION_SHOW_FAVORITES} = SetupControlSwitchDefs::switch_on;
        }

        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_SHOW_FAVORITES, TR::t('setup_show_favorites'), self::$on_off_ops[$plugin_cookies->{self::SETUP_ACTION_SHOW_FAVORITES}],
            $this->plugin->get_image_path(self::$on_off_img[$plugin_cookies->{self::SETUP_ACTION_SHOW_FAVORITES}]), self::CONTROLS_WIDTH);


        //////////////////////////////////////
        // show history category
        if (!isset($plugin_cookies->{self::SETUP_ACTION_SHOW_HISTORY})) {
            $plugin_cookies->{self::SETUP_ACTION_SHOW_HISTORY} = SetupControlSwitchDefs::switch_on;
        }

        Control_Factory::add_image_button($defs, $this, null,
            self::SETUP_ACTION_SHOW_HISTORY, TR::t('setup_show_history'), self::$on_off_ops[$plugin_cookies->{self::SETUP_ACTION_SHOW_HISTORY}],
            $this->plugin->get_image_path(self::$on_off_img[$plugin_cookies->{self::SETUP_ACTION_SHOW_HISTORY}]), self::CONTROLS_WIDTH);

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

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_print(__METHOD__ . ": changing $control_id value to $new_value");
        }

        switch ($control_id) {
            case self::SETUP_ACTION_SHOW_TV:
                if (!is_apk()) {
                    self::toggle_param($plugin_cookies, $control_id);
                }
                break;

            case self::SETUP_ACTION_SHOW_ALL:
            case self::SETUP_ACTION_SHOW_FAVORITES:
            case self::SETUP_ACTION_SHOW_HISTORY:
                self::toggle_param($plugin_cookies, $control_id);
                return $this->plugin->tv->reload_channels($this, $plugin_cookies);
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }

    private static function toggle_param($plugin_cookies, $param)
    {
        $plugin_cookies->{$param} = ($plugin_cookies->{$param} === SetupControlSwitchDefs::switch_off)
            ? SetupControlSwitchDefs::switch_on
            : SetupControlSwitchDefs::switch_off;

        hd_print(__METHOD__ . ": $param: " . $plugin_cookies->{$param});
    }
}
