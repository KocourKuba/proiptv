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

class Starnet_Category_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'category_setup';

    protected $return_index = array('initial_sel_ndx' => 4);

    ///////////////////////////////////////////////////////////////////////

    /**
     * interface dialog defs
     * @return array
     */
    public function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // show all channels category
        $show_all = $this->plugin->get_parameter(PARAM_SHOW_ALL, SetupControlSwitchDefs::switch_on);
        hd_debug_print(PARAM_SHOW_ALL . ": $show_all", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_ALL, TR::t('setup_show_all_channels'), SetupControlSwitchDefs::$on_off_translated[$show_all],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_all]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show favorites category
        $show_fav = $this->plugin->get_parameter(PARAM_SHOW_FAVORITES, SetupControlSwitchDefs::switch_on);
        hd_debug_print(PARAM_SHOW_FAVORITES . ": $show_fav", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_FAVORITES, TR::t('setup_show_favorites'), SetupControlSwitchDefs::$on_off_translated[$show_fav],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_fav]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show history category
        $show_history = $this->plugin->get_parameter(PARAM_SHOW_HISTORY, SetupControlSwitchDefs::switch_on);
        hd_debug_print(PARAM_SHOW_HISTORY . ": $show_history", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_HISTORY, TR::t('setup_show_history'), SetupControlSwitchDefs::$on_off_translated[$show_history],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_history]), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show changed channels category
        $show_changed = $this->plugin->get_parameter(PARAM_SHOW_CHANGED_CHANNELS, SetupControlSwitchDefs::switch_on);
        hd_debug_print(PARAM_SHOW_CHANGED_CHANNELS . ": $show_changed", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_CHANGED_CHANNELS, TR::t('setup_show_changed_channels'), SetupControlSwitchDefs::$on_off_translated[$show_changed],
            get_image_path(SetupControlSwitchDefs::$on_off_img[$show_changed]), self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        return $this->do_get_control_defs();
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("changing $control_id value to $new_value", true);
        }

        switch ($control_id) {
            case GUI_EVENT_KEY_RETURN:
                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_action_screen(
                        Starnet_Setup_Screen::ID, RESET_CONTROLS_ACTION_ID, null, $this->return_index)
                );

            case PARAM_SHOW_ALL:
            case PARAM_SHOW_FAVORITES:
            case PARAM_SHOW_HISTORY:
            case PARAM_SHOW_CHANGED_CHANNELS:
                $this->plugin->save_settings();
                $this->plugin->toggle_parameter($control_id);
                $this->plugin->tv->reload_channels($plugin_cookies);

                return Starnet_Epfs_Handler::invalidate_folders(
                    array(Starnet_Tv_Groups_Screen::ID),
                    Action_Factory::reset_controls($this->do_get_control_defs())
                );
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }
}
