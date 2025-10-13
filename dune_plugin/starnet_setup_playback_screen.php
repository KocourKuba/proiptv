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

class Starnet_Setup_Playback_Screen extends Abstract_Controls_Screen
{
    const ID = 'playback_setup';

    const CONTROL_DUNE_FORCE_TS = 'dune_force_ts';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs($media_url);
    }

    /**
     * @param MediaURL $media_url
     * @return array
     */
    protected function do_get_control_defs($media_url)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $defs = array();

        $playlist_id = isset($media_url->{PARAM_PLAYLIST_ID}) ? $media_url->{PARAM_PLAYLIST_ID} : $this->plugin->get_active_playlist_id();
        $params = $this->plugin->get_playlist_parameters($playlist_id);

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // Per channel zoom
        $per_channel_zoom = $this->plugin->get_setting(PARAM_PER_CHANNELS_ZOOM, SwitchOnOff::on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_PER_CHANNELS_ZOOM, TR::t('setup_per_channel_zoom'), SwitchOnOff::translate($per_channel_zoom),
            SwitchOnOff::to_image($per_channel_zoom), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Force detection stream
        $force_detection = $this->plugin->get_setting(PARAM_DUNE_FORCE_TS, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_DUNE_FORCE_TS, TR::t('setup_channels_dune_force_ts'), SwitchOnOff::translate($force_detection),
            SwitchOnOff::to_image($force_detection), static::CONTROLS_WIDTH);

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
            static::CONTROLS_WIDTH,
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
            static::CONTROLS_WIDTH,
            true);

        //////////////////////////////////////
        // catchup settings

        $catchup_ops[ATTR_CATCHUP_UNKNOWN] = TR::t('by_default');
        $catchup_ops[ATTR_CATCHUP_SHIFT] = ATTR_CATCHUP_SHIFT;
        $catchup_ops[ATTR_CATCHUP_FLUSSONIC] = ATTR_CATCHUP_FLUSSONIC;
        $catchup_idx = safe_get_value($params, PARAM_USER_CATCHUP, ATTR_CATCHUP_UNKNOWN);
        Control_Factory::add_combobox($defs, $this, null, PARAM_USER_CATCHUP,
            TR::t('setup_channels_archive_type'), $catchup_idx, $catchup_ops, static::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // UserAgent

        $user_agent = safe_get_value($params, PARAM_USER_AGENT, '');
        Control_Factory::add_text_field($defs, $this, null, PARAM_USER_AGENT, TR::t('setup_channels_user_agent'),
            $user_agent, false, false, false, true, static::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // enable/disable dune_params

        $enable_dune_params = safe_get_value($params, PARAM_USE_DUNE_PARAMS, SwitchOnOff::on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_USE_DUNE_PARAMS, TR::t('setup_channels_enable_dune_params'), SwitchOnOff::translate($enable_dune_params),
            SwitchOnOff::to_image($enable_dune_params), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // dune_params

        $dune_params_str = safe_get_value($params, PARAM_DUNE_PARAMS, '');
        if ($dune_params_str === '[]') {
            $dune_params_str = '';
        }
        $provider = $this->plugin->get_active_provider();
        if (empty($dune_params_str) && safe_get_value($params, PARAM_TYPE) === PARAM_PROVIDER) {
            $dune_params_str = $provider->getConfigValue(PARAM_DUNE_PARAMS);
        }

        Control_Factory::add_text_field($defs, $this, null, PARAM_DUNE_PARAMS, TR::t('setup_channels_dune_params'),
            $dune_params_str, false, false, false, true, static::CONTROLS_WIDTH, true);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $playlist_id = isset($parent_media_url->playlist_id) ? $parent_media_url->playlist_id : $this->plugin->get_active_playlist_id();

        $control_id = $user_input->control_id;
        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return self::make_return_action($parent_media_url);

            case PARAM_BUFFERING_TIME:
            case PARAM_ARCHIVE_DELAY_TIME:
                $this->plugin->set_setting($control_id, (int)$user_input->{$control_id});
                break;

            case PARAM_DUNE_FORCE_TS:
            case PARAM_PER_CHANNELS_ZOOM:
                $this->plugin->toggle_setting($control_id);
                break;

            case PARAM_USER_CATCHUP:
                $this->plugin->set_playlist_parameter($playlist_id, PARAM_URI, $user_input->{PARAM_USER_CATCHUP});
                break;

            case PARAM_USER_AGENT:
                $user_agent = $user_input->{PARAM_USER_AGENT};
                if (empty($user_agent)) {
                    hd_debug_print("Clear user agent parameter");
                    $this->plugin->set_playlist_parameter($playlist_id, PARAM_USER_AGENT, $user_agent);
                } else if ($user_agent !== HD::get_default_user_agent()) {
                    hd_debug_print("Set user agent parameter: $user_agent");
                    $this->plugin->set_playlist_parameter($playlist_id, PARAM_USER_AGENT, $user_agent);
                }
                $this->plugin->init_user_agent();
                break;

            case PARAM_USE_DUNE_PARAMS:
                $old_value = $this->plugin->get_playlist_parameter($playlist_id, PARAM_USE_DUNE_PARAMS, SwitchOnOff::on);
                $this->plugin->set_playlist_parameter($playlist_id, PARAM_USE_DUNE_PARAMS, SwitchOnOff::toggle($old_value));
                break;

            case PARAM_DUNE_PARAMS:
                $dune_params = $user_input->{PARAM_DUNE_PARAMS};
                $provider = $this->plugin->get_active_provider();
                if (!is_null($provider)) {
                    // do not update dune_params if they the same as config value
                    $config_dune_params = $provider->getConfigValue(PARAM_DUNE_PARAMS);
                    if ($dune_params === $config_dune_params) {
                        $dune_params = '';
                    }
                }

                $this->plugin->set_playlist_parameter($playlist_id, PARAM_DUNE_PARAMS, $dune_params);
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($parent_media_url));
    }
}
