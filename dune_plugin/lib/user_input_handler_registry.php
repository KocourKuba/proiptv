<?php

require_once 'user_input_handler.php';

class User_Input_Handler_Registry
{
    /**
     * @var User_Input_Handler_Registry
     */
    private static $instance;

    /**
     * @var array|User_Input_Handler[]
     */
    private $handlers;

    private function __construct()
    {
        $this->handlers = array();
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
     * @param string $screen_id
     * @param string $name
     * @param string|null $caption
     * @param array|null $add_params
     * @return array
     */
    public static function create_action_screen($screen_id, $name, $caption = null, $add_params = null)
    {
        $handler = self::get_instance()->get_registered_handler($screen_id . "_handler");
        if (is_null($handler))
            return null;

        $params = array(
            'handler_id' => $handler->get_handler_id(),
            'control_id' => $name);
        if (isset($add_params)) {
            $params = array_merge($params, $add_params);
        }

        return array
        (
            GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
            GuiAction::caption => $caption,
            GuiAction::data => null,
            GuiAction::params => $params,
        );
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
        $params = array(
            'handler_id' => $handler->get_handler_id(),
            'control_id' => $name);
        if (isset($add_params)) {
            $params = array_merge($params, $add_params);
        }

        return array
        (
            GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
            GuiAction::caption => $caption,
            GuiAction::data => null,
            GuiAction::params => $params,
        );
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
        return array(
            GuiMenuItemDef::caption => $caption,
            GuiMenuItemDef::action => self::create_action($handler, $name, $caption, $add_params),
            GuiMenuItemDef::icon_url => $icon,
        );
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);
        if (!isset($user_input->handler_id)) {
            return null;
        }

        $handler_id = $user_input->handler_id;
        if (!isset($this->handlers[$handler_id])) {
            return null;
        }

        return $this->handlers[$handler_id]->handle_user_input($user_input, $plugin_cookies);
    }

    /**
     * @param User_Input_Handler $handler
     */
    public function register_handler(User_Input_Handler $handler)
    {
        $handler_id = $handler->get_handler_id();
        $this->handlers[$handler_id] = $handler;
    }

    /**
     * @param string $id
     * @return User_Input_Handler|null
     */
    public function get_registered_handler($id)
    {
        return isset($this->handlers[$id]) ? $this->handlers[$id] : null;
    }
}
