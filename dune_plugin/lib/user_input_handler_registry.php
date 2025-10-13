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

require_once 'user_input_handler.php';

class User_Input_Handler_Registry
{
    /**
     * @var User_Input_Handler_Registry
     */
    private static $instance;

    /**
     * @var User_Input_Handler[]
     */
    private $handlers;

    private function __construct()
    {
        $this->handlers = array();
    }

    /**
     * @param string $screen_id
     * @return User_Input_Handler|null
     */
    public function get_registered_handler($screen_id)
    {
        $handler_id = $screen_id . "_handler";

        return isset($this->handlers[$handler_id]) ? $this->handlers[$handler_id] : null;
    }

    /**
     * @return User_Input_Handler_Registry
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new User_Input_Handler_Registry();
        }
        return self::$instance;
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $name
     * @param string $caption
     * @param string|null $icon
     * @param array|null $add_params
     * @return array
     */
    public static function create_popup_item(User_Input_Handler $handler, $name, $caption, $icon = null, $add_params = null)
    {
        $arr[GuiMenuItemDef::caption] = $caption;
        $arr[GuiMenuItemDef::action] = self::create_action($handler, $name, $caption, $add_params);
        if ($icon)
            $arr[GuiMenuItemDef::icon_url] = $icon;

        return $arr;
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $name
     * @param string|null $caption
     * @param array|null $add_params
     * @return array
     */
    public static function create_action(User_Input_Handler $handler, $name, $caption = null, $add_params = null)
    {
        $params = array('handler_id' => $handler->get_handler_id(), 'control_id' => $name);
        if (isset($add_params)) {
            $params = array_merge($params, $add_params);
        }

        $arr[GuiAction::handler_string_id] = PLUGIN_HANDLE_USER_INPUT_ACTION_ID;
        if ($caption) {
            $arr[GuiAction::caption] = $caption;
        }
        $arr[GuiAction::params] = $params;

        return $arr;
    }

    /**
     * @param string $screen_id
     * @param string $name
     * @param string|null $caption
     * @param array|null $add_params
     * @return array
     */
    public static function create_screen_action($screen_id, $name, $caption = null, $add_params = null)
    {
        $handler = self::get_instance()->get_registered_handler($screen_id);
        if (is_null($handler)) {
            hd_debug_print(null, true);
            hd_debug_print("No handler registered for {$screen_id}_handler");
            return null;
        }

        return self::create_action($handler, $name, $caption, $add_params);
    }

    /**
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        if (isset($user_input->handler_id, $this->handlers[$user_input->handler_id])) {
            hd_debug_print("Call User Input handler: $user_input->handler_id", true);
            dump_input_handler($user_input);
            return $this->handlers[$user_input->handler_id]->handle_user_input($user_input, $plugin_cookies);
        } else {
            hd_debug_print("Unknown handler: $user_input->handler_id", true);
            dump_input_handler($user_input);
        }

        return null;
    }

    /**
     * @param User_Input_Handler $handler
     */
    public function register_handler(User_Input_Handler $handler)
    {
        $this->handlers[$handler->get_handler_id()] = $handler;
    }

    /**
     * @param string $handler_id
     */
    public function unregister_handler($handler_id)
    {
        if (isset($this->handlers[$handler_id])) {
            unset($this->handlers[$handler_id]);
        }
    }
}
