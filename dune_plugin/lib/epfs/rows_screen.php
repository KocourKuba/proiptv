<?php
require_once 'lib/screen.php';
require_once 'lib/user_input_handler.php';

interface Rows_Screen extends Screen
{
    public function get_rows_pane(MediaURL $media_url, $plugin_cookies);
    public function get_timer(MediaURL $media_url, $plugin_cookies);
}
