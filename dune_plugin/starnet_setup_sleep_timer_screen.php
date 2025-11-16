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

class Starnet_Setup_Sleep_Timer_Screen extends Abstract_Controls_Screen
{
    const ID = 'sleep_timer_setup';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs();
    }

    /**
     * @return array
     */
    protected function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

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

        //////////////////////////////////////
        // sleep timer step
        $sleep_step = $this->plugin->get_parameter(PARAM_SLEEP_TIMER_STEP, 60);
        hd_debug_print(PARAM_SLEEP_TIMER_STEP . ": $sleep_step", true);
        $step_ops_translated = array(30 => '0.5', 60 => '1', 120 => '2', 300 => '5', 600 => '10');
        Control_Factory::add_combobox($defs, $this, null, PARAM_SLEEP_TIMER_STEP,
            TR::t('setup_sleep_time_step'), $sleep_step, $step_ops_translated, static::CONTROLS_WIDTH, true);

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

            case PARAM_SLEEP_TIMER_POS:
            case PARAM_SLEEP_TIMER_COUNTDOWN:
            case PARAM_SLEEP_TIMER_STEP:
                $this->plugin->set_parameter($control_id, $user_input->{$control_id});
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }
}
