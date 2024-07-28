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
     * @param string $id
     * @return Channel
     */
    public function get_group_channel($id);

    /**
     * @return Hashed_Array
     */
    public function get_group_enabled_channels();

    /**
     * @return Hashed_Array
     */
    public function get_group_disabled_channels();

    /**
     * @return Ordered_Array
     */
    public function get_items_order();

    /**
     * @param Ordered_Array $order
     */
    public function set_items_order($order);

    /**
     * @param string $id
     * @return bool
     */
    public function in_items_order($id);

    /**
     * @return string;
     */
    public function get_media_url_str();
}
