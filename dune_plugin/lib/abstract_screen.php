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

abstract class Abstract_Screen implements Screen
{
    const ID = 'abstract_screen';

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    ///////////////////////////////////////////////////////////////////////
    // static methods

    /**
     * This is not override of User_Input_Handler interface!
     * It helper method to call inherited classes
     * that implements User_Input_Handler interface
     *
     * @return string
     */
    public function get_handler_id()
    {
        return static::get_id() . '_handler';
    }

    ///////////////////////////////////////////////////////////////////////
    // Screen interface

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return mixed|null
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    public function get_timer(MediaURL $media_url, $plugin_cookies)
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
}
