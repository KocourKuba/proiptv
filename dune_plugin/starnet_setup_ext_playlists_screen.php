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
require_once 'lib/m3u/KnownCatchupSourceTags.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Setup_Ext_Playlists_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'ext_playlist_setup';

    ///////////////////////////////////////////////////////////////////////

    /**
     * Get MediaURL string representation (json encoded)
     *
     * @param string $parent_id
     * @param int $return_index
     * @param string $playlist_id
     * @return false|string
     */
    public static function make_custom_media_url_str($parent_id, $return_index = -1, $playlist_id = null)
    {
        return MediaURL::encode(
            array(
                PARAM_SCREEN_ID => static::ID,
                PARAM_SOURCE_WINDOW_ID => $parent_id,
                PARAM_PLAYLIST_ID => $playlist_id,
                PARAM_RETURN_INDEX => $return_index
            )
        );
    }

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
        hd_debug_print($media_url, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        $parent_media_url = MediaURL::decode($media_url);
        $playlist_id = isset($parent_media_url->{PARAM_PLAYLIST_ID}) ? $parent_media_url->{PARAM_PLAYLIST_ID} : $this->plugin->get_active_playlist_id();
        $params = $this->plugin->get_playlist_parameters($playlist_id);

        //////////////////////////////////////
        // catchup settings

        $catchup_ops[ATTR_CATCHUP_UNKNOWN] = TR::t('by_default');
        $catchup_ops[ATTR_CATCHUP_SHIFT] = ATTR_CATCHUP_SHIFT;
        $catchup_ops[ATTR_CATCHUP_FLUSSONIC] = ATTR_CATCHUP_FLUSSONIC;
        $catchup_idx = safe_get_value($params, PARAM_USER_CATCHUP, ATTR_CATCHUP_UNKNOWN);
        Control_Factory::add_combobox($defs, $this, null, PARAM_USER_CATCHUP,
            TR::t('setup_channels_archive_type'), $catchup_idx, $catchup_ops, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // UserAgent

        $user_agent = safe_get_value($params, PARAM_USER_AGENT, '');
        Control_Factory::add_text_field($defs, $this, null, PARAM_USER_AGENT, TR::t('setup_channels_user_agent'),
            $user_agent, false, false, false, true, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // enable/disable dune_params

        $enable_dune_params = safe_get_value($params, PARAM_USE_DUNE_PARAMS, SwitchOnOff::on);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_USE_DUNE_PARAMS, TR::t('setup_channels_enable_dune_params'), SwitchOnOff::translate($enable_dune_params),
            SwitchOnOff::to_image($enable_dune_params), self::CONTROLS_WIDTH);

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
            $dune_params_str, false, false, false, true, self::CONTROLS_WIDTH, true);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $post_action = null;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $playlist_id = isset($parent_media_url->playlist_id) ? $parent_media_url->playlist_id : $this->plugin->get_active_playlist_id();

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                return self::make_return_action($parent_media_url);

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

        return Action_Factory::reset_controls($this->get_control_defs(MediaURL::decode($user_input->parent_media_url), $plugin_cookies), $post_action);
    }
}
