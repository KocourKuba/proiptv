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

require_once 'screen.php';
require_once 'user_input_handler.php';

abstract class Abstract_Screen implements Screen, User_Input_Handler
{
    const ID = 'abstract_screen';

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    ///////////////////////////////////////////////////////////////////////
    // User_Input_Handler interface

    /**
     * @inheritDoc
     */
    public function get_handler_id()
    {
        return static::get_id() . '_handler';
    }

    ///////////////////////////////////////////////////////////////////////
    // Screen interface

    /**
     * @inheritDoc
     */
    public function get_timer()
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public static function get_id()
    {
        return static::ID;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function get_next_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        return null;
    }

    ///////////////////////////////////////////////////////////////////////
    // common actions of the screens

    /**
     * Play the TV channel of the selected item
     *
     * @param MediaURL $selected_media_url
     * @param object $plugin_cookies
     * @return array|null
     */
    protected function play_tv_channel($selected_media_url, &$plugin_cookies)
    {
        try {
            $post_action = $this->plugin->tv_player_exec($selected_media_url);
        } catch (Exception $ex) {
            hd_debug_print("Channel can't be played");
            print_backtrace_exception($ex);
            return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'), TR::t('warn_msg2__1', $ex->getMessage()));
        }

        Starnet_Epfs_Handler::update_epfs_file($plugin_cookies);
        return $post_action;
    }

    /**
     * Close the screen and pass the action to another screen
     *
     * @param string $screen_id
     * @param string $action_id
     * @param array|null $params
     * @return array
     */
    protected static function close_and_run_screen_action($screen_id, $action_id, $params = null)
    {
        $actions[] = Action_Factory::close_and_run();
        $actions[] = User_Input_Handler_Registry::create_screen_action($screen_id, $action_id, null, $params);
        return Action_Factory::composite($actions);
    }

    /**
     * Error dialog of the last playlist loading error, the error is cleared
     *
     * @return array|null
     */
    protected static function get_playlist_error_action()
    {
        $error_msg = Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST);
        if (empty($error_msg)) {
            return null;
        }

        hd_debug_print("Playlist loading error: $error_msg");
        return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), $error_msg);
    }
}
