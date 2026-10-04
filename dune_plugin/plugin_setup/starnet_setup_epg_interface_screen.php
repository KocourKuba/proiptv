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

class Starnet_Setup_Epg_Interface_Screen extends Abstract_Controls_Screen
{
    const ID = 'epg_interface_setup';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return array
     */
    protected function do_get_control_defs($media_url = null, &$plugin_cookies = null)
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        $font_ops_translated = array(SwitchOnOff::on => TR::t('setup_small'), SwitchOnOff::off => TR::t('setup_normal'));

        //////////////////////////////////////
        // epg font size
        $epg_font_size = $this->plugin->get_parameter(PARAM_EPG_FONT_SIZE, SwitchOnOff::off);
        hd_debug_print(PARAM_EPG_FONT_SIZE . ": $epg_font_size", true);
        Control_Factory::add_image_button($defs, $this, PARAM_EPG_FONT_SIZE,
            TR::t('setup_epg_font'), SwitchOnOff::translate_from($font_ops_translated, $epg_font_size), SwitchOnOff::to_image($epg_font_size));

        //////////////////////////////////////
        // epg info window on INFO key in playback
        $epg_info_window = $this->plugin->get_parameter(PARAM_EPG_INFO_WINDOW, SwitchOnOff::on);
        hd_debug_print(PARAM_EPG_INFO_WINDOW . ": $epg_info_window", true);
        Control_Factory::add_image_button($defs, $this, PARAM_EPG_INFO_WINDOW,
            TR::t('setup_epg_info_window'), SwitchOnOff::translate($epg_info_window), SwitchOnOff::to_image($epg_info_window));

        //////////////////////////////////////
        // stream info of the epg info window: from the player or measured by ffmpeg, the ATV boxes have no ffmpeg
        if (!is_limited_apk()) {
            $stream_check = $this->plugin->get_parameter(PARAM_EPG_INFO_STREAM_CHECK, SwitchOnOff::off);
            hd_debug_print(PARAM_EPG_INFO_STREAM_CHECK . ": $stream_check", true);
            $stream_ops_translated = array(
                SwitchOnOff::on => TR::t('setup_epg_info_stream_ffmpeg'),
                SwitchOnOff::off => TR::t('setup_epg_info_stream_player')
            );
            Control_Factory::add_image_button($defs, $this, PARAM_EPG_INFO_STREAM_CHECK, TR::t('setup_epg_info_stream'),
                SwitchOnOff::translate_from($stream_ops_translated, $stream_check), SwitchOnOff::to_image($stream_check));
        }

        //////////////////////////////////////
        // group/channel font size
        $group_font_size = $this->plugin->get_parameter(PARAM_GROUP_FONT_SIZE, SwitchOnOff::off);
        hd_debug_print(PARAM_GROUP_FONT_SIZE . ": $group_font_size", true);
        Control_Factory::add_image_button($defs, $this, PARAM_GROUP_FONT_SIZE,
            TR::t('setup_group_font'), SwitchOnOff::translate_from($font_ops_translated, $group_font_size), SwitchOnOff::to_image($group_font_size));

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

            case PARAM_EPG_INFO_WINDOW:
                $this->plugin->toggle_parameter($control_id);
                break;

            case PARAM_EPG_FONT_SIZE:
            case PARAM_GROUP_FONT_SIZE:
            case PARAM_EPG_INFO_STREAM_CHECK:
                $this->plugin->toggle_parameter($control_id, false);
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }
}
