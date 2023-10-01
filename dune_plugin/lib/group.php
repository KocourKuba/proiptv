<?php

interface Group
{
    /**
     * @return string
     */
    public function get_id();

    /**
     * @return string
     */
    public function get_title();

    /**
     * @return string
     */
    public function get_icon_url();

    /**
     * @param string $icon_url
     */
    public function set_icon_url($icon_url);

    /**
     * @return bool
     */
    public function is_special_group();

    /**
     * @return bool
     */
    public function is_adult_group();

    /**
     * @param bool $adult
     * @return void
     */
    public function set_adult($adult);

    /**
     * @return bool
     */
    public function is_disabled();

    /**
     * @param bool $disabled
     * @return void
     */
    public function set_disabled($disabled);

    /**
     * @return Hashed_Array
     */
    public function get_group_channels();

    /**
     * @return Ordered_Array
     */
    public function get_items_order();

    /**
     * @param Ordered_Array $order
     */
    public function set_items_order($order);

    /**
     * @return string;
     */
    public function get_media_url_str();
}
