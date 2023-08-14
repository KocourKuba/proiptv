<?php

interface User_Input_Handler
{
    /**
     * @return string
     */
    public function get_handler_id();

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies);
}
