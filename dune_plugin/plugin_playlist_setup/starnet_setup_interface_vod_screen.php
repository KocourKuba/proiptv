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

/**
 * VOD settings of the playlist (r25+): the NewUI view of the movie list and its movie info.
 * Opened from the playlist setup it edits the settings of that playlist. Opened from the media libraries
 * the media url has the playlist of the media library and its VOD context is used, the active playlist is not changed.
 */
class Starnet_Setup_Interface_Vod_Screen extends Abstract_Controls_Screen
{
    const ID = 'interface_vod_setup';

    ///////////////////////////////////////////////////////////////////////

    protected $force_parent_reload = false;

    /**
     * Get MediaURL string representation (json encoded)
     *
     * @param string $parent_id
     * @param int $return_index
     * @param string|null $playlist_id playlist of the media library
     * @return false|string
     */
    public static function make_controls_media_url_str($parent_id, $return_index = -1, $playlist_id = null)
    {
        return MediaURL::encode(
            array(
                PARAM_SCREEN_ID => static::ID,
                PARAM_SOURCE_WINDOW_ID => $parent_id,
                PARAM_RETURN_INDEX => $return_index,
                PARAM_PLAYLIST_ID => $playlist_id
            )
        );
    }

    /**
     * @param string|null $playlist_id playlist of the media library, empty - opened from the playlist setup
     * @param string $name
     * @return string SwitchOnOff value
     */
    protected function read_switch($playlist_id, $name)
    {
        if (empty($playlist_id)) {
            return $this->plugin->get_setting($name, SwitchOnOff::off);
        }

        // a fresh php process has no selected VOD context
        $this->plugin->select_vod_playlist($playlist_id);
        return $this->plugin->get_vod_setting($name, SwitchOnOff::off);
    }

    /**
     * VOD settings defs
     * @return array
     */
    protected function do_get_control_defs($media_url = null, &$plugin_cookies = null)
    {
        hd_debug_print(null, true);

        $playlist_id = safe_get_value($media_url, PARAM_PLAYLIST_ID);

        $defs = array();
        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // NewUI view of the movie list
        $newui_view = $this->read_switch($playlist_id, PARAM_VOD_NEWUI_VIEW);
        Control_Factory::add_image_button($defs, $this, PARAM_VOD_NEWUI_VIEW,
            TR::t('setup_vod_newui_view'), SwitchOnOff::translate($newui_view), SwitchOnOff::to_image($newui_view));

        //////////////////////////////////////
        // Full movie info in the NewUI view, only if this view is on. M3U VOD has no more info than its movie list
        $provider = empty($playlist_id) ? $this->plugin->get_active_provider() : $this->plugin->get_provider($playlist_id);
        if (SwitchOnOff::to_bool($newui_view) && !is_null($provider) && !Default_Dune_Plugin::is_provider_m3u_vod($provider)) {
            $full_info = $this->read_switch($playlist_id, PARAM_VOD_NEWUI_FULL_INFO);
            Control_Factory::add_image_button($defs, $this, PARAM_VOD_NEWUI_FULL_INFO,
                TR::t('setup_vod_full_info'), SwitchOnOff::translate($full_info), SwitchOnOff::to_image($full_info));
        }

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $control_id = $user_input->control_id;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $playlist_id = safe_get_value($parent_media_url, PARAM_PLAYLIST_ID);

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $reload = $this->force_parent_reload;
                $this->force_parent_reload = false;
                $post_action = self::make_return_action($parent_media_url);

                if ($reload) {
                    hd_debug_print('Force parent reload', true);
                    // VOD of a media library has nothing to do with the TV pane of NewUI (epfs), TV may be not loaded at all
                    return empty($playlist_id)
                        ? Action_Factory::invalidate_all_folders($plugin_cookies, null, $post_action)
                        : Action_Factory::invalidate_folders(array(), $post_action, true);
                }
                return $post_action;

            case PARAM_VOD_NEWUI_VIEW:
            case PARAM_VOD_NEWUI_FULL_INFO:
                // the opened movie lists are built again with the new setting
                $this->force_parent_reload = true;
                if (empty($playlist_id)) {
                    $this->plugin->toggle_setting($control_id, false);
                } else {
                    $old_value = $this->read_switch($playlist_id, $control_id);
                    $this->plugin->set_vod_setting($control_id, SwitchOnOff::to_def(!SwitchOnOff::to_bool($old_value)));
                }
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($parent_media_url));
    }
}
