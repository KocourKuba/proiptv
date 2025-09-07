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

class Starnet_Setup_Playback_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'playback_setup';

    const CONTROL_AUTO_RESUME = 'auto_resume';
    const CONTROL_AUTO_PLAY = 'auto_play';
    const CONTROL_DUNE_FORCE_TS = 'dune_force_ts';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        return $this->do_get_control_defs($plugin_cookies);
    }

    /**
     * streaming parameters dialog defs
     * @param object $plugin_cookies
     * @return array
     */
    public function do_get_control_defs(&$plugin_cookies)
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // ext epg
        if (is_ext_epg_supported()) {
            $ext_epg = $this->plugin->get_setting(PARAM_SHOW_EXT_EPG, SwitchOnOff::on);
            Control_Factory::add_image_button($defs, $this, null,
                PARAM_SHOW_EXT_EPG, TR::t('setup_ext_epg'), SwitchOnOff::translate($ext_epg),
                SwitchOnOff::to_image($ext_epg), self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // auto play
        $auto_play = self::get_cookie_bool_param($plugin_cookies, self::CONTROL_AUTO_PLAY, false);
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_AUTO_PLAY, TR::t('setup_autostart'), SwitchOnOff::translate($auto_play),
            SwitchOnOff::to_image($auto_play), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // auto resume
        $auto_resume = self::get_cookie_bool_param($plugin_cookies, self::CONTROL_AUTO_RESUME);
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_AUTO_RESUME, TR::t('setup_continue_play'), SwitchOnOff::translate($auto_resume),
            SwitchOnOff::to_image($auto_resume), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Per channel zoom
        $per_channel_zoom = $this->plugin->get_setting(PARAM_PER_CHANNELS_ZOOM, SwitchOnOff::on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_PER_CHANNELS_ZOOM, TR::t('setup_per_channel_zoom'), SwitchOnOff::translate($per_channel_zoom),
            SwitchOnOff::to_image($per_channel_zoom), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Force detection stream
        $force_detection = $this->plugin->get_setting(PARAM_DUNE_FORCE_TS, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_DUNE_FORCE_TS, TR::t('setup_channels_dune_force_ts'), SwitchOnOff::translate($force_detection),
            SwitchOnOff::to_image($force_detection), self::CONTROLS_WIDTH);

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
        $show_delay_time_ops[2 * 60] = TR::t('setup_buffer_sec__1', "120");
        $show_delay_time_ops[3 * 60] = TR::t('setup_buffer_sec__1', "180");
        $show_delay_time_ops[5 * 60] = TR::t('setup_buffer_sec__1', "300");

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
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("Changing $control_id value to $new_value", true);
        }

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(
                        Starnet_Setup_Screen::ID,
                        RESET_CONTROLS_ACTION_ID,
                        null,
                        array('initial_sel_ndx' => $this->return_index)
                    )
                );

            case self::CONTROL_AUTO_PLAY:
            case self::CONTROL_AUTO_RESUME:
                self::toggle_cookie_param($plugin_cookies, $control_id);
                break;

            case PARAM_BUFFERING_TIME:
            case PARAM_ARCHIVE_DELAY_TIME:
                $this->plugin->set_setting($control_id, (int)$user_input->{$control_id});
                break;

            case PARAM_DUNE_FORCE_TS:
            case PARAM_PER_CHANNELS_ZOOM:
            case PARAM_SHOW_EXT_EPG:
                $this->plugin->toggle_setting($control_id);
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
