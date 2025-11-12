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

class Starnet_Setup_Interface_Screen extends Abstract_Controls_Screen
{
    const ID = 'interface_setup';

    const CONTROL_SHOW_TV = 'show_tv';
    const CONTROL_AUTO_RESUME = 'auto_resume';
    const CONTROL_AUTO_PLAY = 'auto_play';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs($plugin_cookies);
    }

    /**
     * @param object $plugin_cookies
     * @return array
     */
    protected function do_get_control_defs(&$plugin_cookies)
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // Show in main screen
        if (!is_limited_apk()) {
            $show_tv = self::get_cookie_bool_param($plugin_cookies, self::CONTROL_SHOW_TV);
            hd_debug_print(self::CONTROL_SHOW_TV . ": $show_tv", true);
            Control_Factory::add_image_button($defs, $this, null,
                self::CONTROL_SHOW_TV, TR::t('setup_show_in_main'), SwitchOnOff::translate($show_tv),
                SwitchOnOff::to_image($show_tv), static::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // auto play
        $auto_play = self::get_cookie_bool_param($plugin_cookies, self::CONTROL_AUTO_PLAY, false);
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_AUTO_PLAY, TR::t('setup_autostart'), SwitchOnOff::translate($auto_play),
            SwitchOnOff::to_image($auto_play), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // auto resume
        $auto_resume = self::get_cookie_bool_param($plugin_cookies, self::CONTROL_AUTO_RESUME);
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_AUTO_RESUME, TR::t('setup_continue_play'), SwitchOnOff::translate($auto_resume),
            SwitchOnOff::to_image($auto_resume), static::CONTROLS_WIDTH);

        $ask_exit = $this->plugin->get_parameter(PARAM_ASK_EXIT, SwitchOnOff::on);
        hd_debug_print(PARAM_ASK_EXIT . ": $ask_exit", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_ASK_EXIT, TR::t('setup_ask_exit'), SwitchOnOff::translate($ask_exit),
            SwitchOnOff::to_image($ask_exit), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show separate VOD icon
        $show_vod_icon = $this->plugin->get_parameter(PARAM_SHOW_VOD_ICON, SwitchOnOff::off);
        hd_debug_print(PARAM_SHOW_VOD_ICON . ": $show_vod_icon", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_VOD_ICON, TR::t('setup_show_vod_icon'), SwitchOnOff::translate($show_vod_icon),
            SwitchOnOff::to_image($show_vod_icon), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // epg font size
        $font_size = $this->plugin->get_parameter(PARAM_EPG_FONT_SIZE, SwitchOnOff::off);
        hd_debug_print(PARAM_EPG_FONT_SIZE . ": $font_size", true);
        $font_ops_translated = array(SwitchOnOff::on => TR::t('setup_small'), SwitchOnOff::off => TR::t('setup_normal'));
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_EPG_FONT_SIZE, TR::t('setup_epg_font'), SwitchOnOff::translate_from($font_ops_translated, $font_size),
            SwitchOnOff::to_image($font_size), static::CONTROLS_WIDTH);

        if (!is_limited_apk()) {
            //////////////////////////////////////
            // sleep timer position
            $sleep_pos = $this->plugin->get_parameter(PARAM_SLEEP_TIMER_POS, 'top_right');
            hd_debug_print(PARAM_SLEEP_TIMER_POS . ": $sleep_pos", true);
            $pos_ops_translated = array('top_left' => TR::t('setup_top_left'), 'top_right' => TR::t('setup_top_right'));
            Control_Factory::add_combobox($defs, $this, null, PARAM_SLEEP_TIMER_POS,
                TR::t('setup_sleep_time_pos'), $sleep_pos, $pos_ops_translated, static::CONTROLS_WIDTH, true);

            //////////////////////////////////////
            // sleep timer countdown
            $sleep_countdown = $this->plugin->get_parameter(PARAM_SLEEP_TIMER_COUNTDOWN, 120);
            hd_debug_print(PARAM_SLEEP_TIMER_COUNTDOWN . ": $sleep_countdown", true);
            $countdown_ops_translated = array(60 => '60', 120 => '120', 180 => '180', 240 => '240', 300 => '300');
            Control_Factory::add_combobox($defs, $this, null, PARAM_SLEEP_TIMER_COUNTDOWN,
                TR::t('setup_sleep_time_show'), $sleep_countdown, $countdown_ops_translated, static::CONTROLS_WIDTH, true);
        }

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $control_id = $user_input->control_id;
        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                return self::make_return_action($parent_media_url);

            case self::CONTROL_SHOW_TV:
            case self::CONTROL_AUTO_PLAY:
            case self::CONTROL_AUTO_RESUME:
                self::toggle_cookie_param($plugin_cookies, $control_id);
                break;

            case PARAM_SHOW_VOD_ICON:
                $this->plugin->toggle_parameter($control_id);
                $enable_vod_icon = SwitchOnOff::to_def($this->plugin->is_vod_enabled() && $this->plugin->get_bool_parameter($control_id));
                $plugin_cookies->{$control_id} = $enable_vod_icon;
                hd_debug_print("Update cookie values: $enable_vod_icon", true);

                return Action_Factory::invalidate_all_folders(
                    $plugin_cookies,
                    null,
                    Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies))
                );

            case PARAM_ASK_EXIT:
            case PARAM_EPG_FONT_SIZE:
                $this->plugin->toggle_parameter($control_id, false);
                break;

            case PARAM_SLEEP_TIMER_POS:
            case PARAM_SLEEP_TIMER_COUNTDOWN:
                $this->plugin->set_parameter($control_id, $user_input->{$control_id});
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($plugin_cookies));
    }
}
