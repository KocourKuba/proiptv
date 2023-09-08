<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Streaming_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'stream_setup';

    const CONTROL_AUTO_RESUME = 'auto_resume';
    const CONTROL_AUTO_PLAY = 'auto_play';

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
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        dump_input_handler($user_input);

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("Setup: changing $control_id value to $new_value", LOG_LEVEL_DEBUG);
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
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
