<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
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

class Sleep_Timer
{
    const SLEEP_TIMER_SCRIPT = 'sleep_timer';

    const CONTROL_SLEEP_TIME_MIN = 'sleep_time_min';
    const CONTROL_SLEEP_TIME_SET = 'sleep_time_set';

    /**
     * @var string[]
     */
    protected static $timer_positions = array(
        'top_left' => array('dx' => 20, 'dy' => 20),
        'top_right' => array('dx' => 1610, 'dy' => 20),
    );

    /**
     * @var int
     */
    protected static $show_pos = 'top_right';

    /**
     * @var int
     */
    protected static $show_time = 120;

    /**
     * @var int
     */
    protected static $sleep_time = 0;

    /**
     * @var int
     */
    protected static $sleep_timer_op = 0;

    /**
     * return estimated time to sleep
     *
     * @return int
     */
    public static function get_sleep_timer()
    {
        return self::$sleep_time ? (self::$sleep_time - time()) : 0;
    }

    /**
     * @param string $show_pos
     * @return void
     */
    public static function set_show_pos($show_pos)
    {
        self::$show_pos = $show_pos;
    }

    /**
     * @param int $show_time
     * @return void
     */
    public static function set_show_time($show_time)
    {
        self::$show_time = $show_time;
    }

    /**
     * remember user selected operation
     *
     * @param int $op
     * @return void
     */
    public static function set_timer_op($op)
    {
        self::$sleep_timer_op = $op;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public static function show_sleep_timer_dialog($handler)
    {
        $defs = array();
        Control_Factory::add_combobox($defs, $handler, null, self::CONTROL_SLEEP_TIME_MIN, TR::t('sleep_after'),
            self::$sleep_timer_op, self::get_sleep_timer_ops(), 200);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, self::CONTROL_SLEEP_TIME_SET, TR::t('apply'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);

        $attrs['dialog_params'] = array('frame_style' => DIALOG_FRAME_STYLE_GLASS);
        return Action_Factory::show_dialog(TR::t('sleep_timer'), $defs, true,
            Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, $attrs);
    }

    /**
     * @param array $comps
     * @param object $user_input
     * @param bool $force
     * @return void
     */
    public static function create_estimated_timer_box(&$comps, $user_input, $force = false)
    {
        $timer = self::get_sleep_timer();
        if ($user_input->playback_browser_activated || (!$force && ($timer === 0 || $timer > self::$show_time))) {
            return;
        }

        $clock_pos_x = self::$timer_positions[self::$show_pos]['dx'];
        $clock_pos_y = self::$timer_positions[self::$show_pos]['dy'];
        $estimated = format_duration_seconds($timer);
        OSD_Component_Factory::add_content_box($comps, $clock_pos_x,$clock_pos_y, 230, 80);
        Action_Factory::add_osd_image($comps, get_image_path('clock.png'), $clock_pos_x + 20, $clock_pos_y + 15);
        Action_Factory::add_osd_text($comps, $estimated, $clock_pos_x + 80, $clock_pos_y + 15);
    }

    /**
     * set time in seconds before dune goes to standby
     *
     * @param int $sleep_timer_sec
     */
    public static function set_sleep_timer($sleep_timer_sec)
    {
        hd_debug_print("Set sleep_timer for $sleep_timer_sec");

        $script_file = get_temp_path(self::SLEEP_TIMER_SCRIPT . '.sh');
        $pid_file = get_temp_path(self::SLEEP_TIMER_SCRIPT . '.pid');
        if (file_exists($pid_file)) {
            $pid = file_get_contents($pid_file);
            shell_exec("kill $pid");
            unlink($pid_file);
            unlink($script_file);
        }

        if ($sleep_timer_sec === 0) {
            self::$sleep_time = 0;
            return;
        }

        $doc = "#!/bin/sh" . PHP_EOL;
        $doc .= "sleep $sleep_timer_sec" . PHP_EOL;
        $doc .= 'if [ -z "$HD_HTTP_LOCAL_PORT" ]; then HD_HTTP_LOCAL_PORT="80"; fi' . PHP_EOL;
        $doc .= 'wget --quiet -O - "http://127.0.0.1:$HD_HTTP_LOCAL_PORT/cgi-bin/do?cmd=standby"' . PHP_EOL;
        $doc .= "rm -- $pid_file" . PHP_EOL;
        $doc .= "rm -- $script_file" . PHP_EOL;
        file_put_contents($script_file, $doc);
        $command = "$script_file > /dev/null 2>&1 & echo $!";

        file_put_contents($pid_file, (int)shell_exec($command));
        self::$sleep_time = time() + $sleep_timer_sec;
    }

    protected static function get_sleep_timer_ops()
    {
        static $range = array(1, 2, 3, 4, 5, 10, 15, 30, 45, 60, 75, 90, 105, 120, 150, 180, 240, 300, 360);

        $ops = array(0 => TR::t('no'));
        foreach ($range as $val) {
            $ops[$val] = format_duration_minutes($val * 60, false);
        }
        return $ops;
    }
}
