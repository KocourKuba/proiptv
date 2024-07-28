<?php

interface User_Input_Handler
{
    /**
     * @return string
     */
    public static function get_handler_id();

    /**
     * @param Object $user_input
     * @param Object $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies);
}
