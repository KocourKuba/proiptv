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

class Starnet_Setup_Interface_NewUI_Screen extends Abstract_Controls_Screen
{
    const ID = 'interface_newui_setup';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs();
    }

    /**
     * interface dialog defs
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
        // Square icons
        $square_icon = $this->plugin->get_setting(PARAM_NEWUI_SQUARE_ICONS, SwitchOnOff::on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_NEWUI_SQUARE_ICONS, TR::t('tv_screen_toggle_icons_aspect'), SwitchOnOff::translate($square_icon),
            SwitchOnOff::to_image($square_icon), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Channel position in NewUI
        $channel_position[0] = TR::t('setup_channel_bottom_left');
        $channel_position[1] = TR::t('setup_channel_top_left');
        $channel_position[2] = TR::t('setup_channel_top_right');
        $channel_position[3] = TR::t('setup_channel_bottom_right');
        $ch_pos = $this->plugin->get_setting(PARAM_NEWUI_CHANNEL_POSITION, 0);
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_NEWUI_CHANNEL_POSITION, TR::t('setup_channel_position'),
            $ch_pos, $channel_position, static::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // Channels in rows in NewUI
        $icons_in_row[5] = '5';
        $icons_in_row[6] = '6';
        $icons_in_row[7] = '7';
        $icon_idx = $this->plugin->get_setting(PARAM_NEWUI_ICONS_IN_ROW, 7);
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_NEWUI_ICONS_IN_ROW, TR::t('setup_icons_in_row'),
            $icon_idx, $icons_in_row, static::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // Show caption
        $show_caption = $this->plugin->get_setting(PARAM_NEWUI_SHOW_CHANNEL_CAPTION, SwitchOnOff::on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_NEWUI_SHOW_CHANNEL_CAPTION, TR::t('setup_show_caption'), SwitchOnOff::translate($show_caption),
            SwitchOnOff::to_image($show_caption), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Show channel count
        $show_count = $this->plugin->get_setting(PARAM_NEWUI_SHOW_CHANNEL_COUNT, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_NEWUI_SHOW_CHANNEL_COUNT, TR::t('setup_show_channel_count'), SwitchOnOff::translate($show_count),
            SwitchOnOff::to_image($show_count), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Clusters
        $continues = $this->plugin->get_setting(PARAM_NEWUI_SHOW_CONTINUES, SwitchOnOff::on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_NEWUI_SHOW_CONTINUES, TR::t('setup_show_continues'), SwitchOnOff::translate($continues),
            SwitchOnOff::to_image($continues), static::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $control_id = $user_input->control_id;
        $post_action = null;
        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                $post_action = self::make_return_action($parent_media_url);

                if ($this->force_parent_reload) {
                    $this->force_parent_reload = false;
                    hd_debug_print("Force parent reload", true);
                    $post_action = Action_Factory::invalidate_all_folders($plugin_cookies, null, $post_action);
                }
                return $post_action;

            case PARAM_NEWUI_CHANNEL_POSITION:
            case PARAM_NEWUI_ICONS_IN_ROW:
                $this->plugin->set_setting($control_id, $user_input->{$control_id});
                $this->force_parent_reload = true;
                break;

            case PARAM_NEWUI_SQUARE_ICONS:
            case PARAM_NEWUI_SHOW_CHANNEL_CAPTION:
            case PARAM_NEWUI_SHOW_CHANNEL_COUNT:
            case PARAM_NEWUI_SHOW_CONTINUES:
                $this->plugin->toggle_setting($control_id, false);
                $this->force_parent_reload = true;
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs(), $post_action);
    }
}
