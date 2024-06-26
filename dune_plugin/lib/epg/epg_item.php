<?php

interface Epg_Item
{
    /**
     * @return string
     */
    public function get_title();

    /**
     * @return string
     */
    public function get_description();

    /**
     * @return int UNIX time
     */
    public function get_start_time();

    /**
     * @return int UNIX time
     */
    public function get_finish_time();
}
