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

class Starnet_Setup_Download_Screen extends Abstract_Controls_Screen
{
    const ID = 'download_setup';

    const CONTROL_ITEMS_CLEAR_FILE_CACHE = 'clear_file_cache';

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
        // Curl connect timeout
        foreach (array(30, 60, 90, 120, 180, 240, 300) as $sec) {
            $time_range[$sec] = $sec;
        }
        $params = array();
        Control_Factory::add_combobox($defs, $this, PARAM_CURL_CONNECT_TIMEOUT, TR::t('setup_connect_timeout'),
            $this->plugin->get_parameter(PARAM_CURL_CONNECT_TIMEOUT, 30),
            $time_range, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);

        //////////////////////////////////////
        // Curl download timeout
        Control_Factory::add_combobox($defs, $this, PARAM_CURL_DOWNLOAD_TIMEOUT, TR::t('setup_download_timeout'),
            $this->plugin->get_parameter(PARAM_CURL_DOWNLOAD_TIMEOUT, 120),
            $time_range, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);

        //////////////////////////////////////
        // Curl cache time
        foreach (array(1, 2, 3, 6, 12, 24) as $hour) {
            $cache_range[$hour] = $hour;
        }
        Control_Factory::add_combobox($defs, $this, PARAM_CURL_FILE_CACHE_TIME, TR::t('setup_cache_time'),
            $this->plugin->get_parameter(PARAM_CURL_FILE_CACHE_TIME, 1),
            $cache_range, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);

        //////////////////////////////////////
        // Clear Curl cache
        Control_Factory::add_image_button($defs, $this, self::CONTROL_ITEMS_CLEAR_FILE_CACHE,
            TR::t('setup_clear_cache'), TR::t('clear'), get_image_path('remove.png')
        );

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $control_id = $user_input->control_id;
        $post_action = null;
        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                return self::make_return_action($parent_media_url);

            case PARAM_CURL_CONNECT_TIMEOUT:
            case PARAM_CURL_DOWNLOAD_TIMEOUT:
            case PARAM_CURL_FILE_CACHE_TIME:
                $this->plugin->set_parameter($control_id, $user_input->{$control_id});
                break;

            case self::CONTROL_ITEMS_CLEAR_FILE_CACHE:
                Curl_Wrapper::getInstance()->clear_cache(true);
                return Action_Factory::show_title_dialog(TR::t('setup_cache_cleared'));
        }

        return Action_Factory::reset_controls($this->do_get_control_defs(), $post_action);
    }
}
