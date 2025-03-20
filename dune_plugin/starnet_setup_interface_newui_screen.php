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

class Starnet_Setup_Interface_NewUI_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'interface_newui_setup';

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
        return $this->do_get_control_defs();
    }

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
        // Square icons
        $square_icon = $this->plugin->get_setting(PARAM_NEWUI_SQUARE_ICONS, SwitchOnOff::on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_NEWUI_SQUARE_ICONS, TR::t('tv_screen_toggle_icons_aspect'), SwitchOnOff::translate($square_icon),
            get_image_path(SwitchOnOff::to_image($square_icon)), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Channel position in NewUI
        $channel_position[0] = TR::t('setup_channel_bottom_left');
        $channel_position[1] = TR::t('setup_channel_top_left');
        $channel_position[2] = TR::t('setup_channel_top_right');
        $channel_position[3] = TR::t('setup_channel_bottom_right');
        $ch_pos = $this->plugin->get_setting(PARAM_NEWUI_CHANNEL_POSITION, 0);
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_NEWUI_CHANNEL_POSITION, TR::t('setup_channel_position'),
            $ch_pos, $channel_position, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // Channels in rows in NewUI
        $icons_in_row[5] = '5';
        $icons_in_row[6] = '6';
        $icons_in_row[7] = '7';
        $icon_idx = $this->plugin->get_setting(PARAM_NEWUI_ICONS_IN_ROW, 7);
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_NEWUI_ICONS_IN_ROW, TR::t('setup_icons_in_row'),
            $icon_idx, $icons_in_row, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // Show caption
        $show_caption = $this->plugin->get_setting(PARAM_NEWUI_SHOW_CHANNEL_CAPTION, SwitchOnOff::on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_NEWUI_SHOW_CHANNEL_CAPTION, TR::t('setup_show_caption'), SwitchOnOff::translate($show_caption),
            get_image_path(SwitchOnOff::to_image($show_caption)), self::CONTROLS_WIDTH);

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

        $post_action = null;
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

            case PARAM_NEWUI_CHANNEL_POSITION:
            case PARAM_NEWUI_ICONS_IN_ROW:
                $this->plugin->set_setting($control_id, $user_input->{$control_id});
                $post_action = Action_Factory::invalidate_all_folders($plugin_cookies);
                break;

            case PARAM_NEWUI_SQUARE_ICONS:
            case PARAM_NEWUI_SHOW_CHANNEL_CAPTION:
                $this->plugin->toggle_setting($control_id, false);
                $post_action = Action_Factory::invalidate_all_folders($plugin_cookies);
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs(), $post_action);
    }
}
