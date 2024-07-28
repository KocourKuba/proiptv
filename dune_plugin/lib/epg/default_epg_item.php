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

require_once 'epg_item.php';

class Default_Epg_Item implements Epg_Item
{
    /**
     * @var string
     */
    protected $_title;

    /**
     * @var string
     */
    protected $_description;

    /**
     * @var int
     */
    protected $_start_time;

    /**
     * @var int
     */
    protected $_finish_time;

    /**
     * @param string $title
     * @param string $description
     * @param int $start_time
     * @param int $finish_time
     */
    public function __construct($title, $description, $start_time, $finish_time)
    {
        $this->_title = $title;
        $this->_description = $description;
        $this->_start_time = $start_time;
        $this->_finish_time = $finish_time;
    }

    /**
     * @inheritDoc
     */
    public function get_title()
    {
        return $this->_title;
    }

    /**
     * @inheritDoc
     */
    public function get_description()
    {
        return $this->_description;
    }

    /**
     * @inheritDoc
     */
    public function get_start_time()
    {
        return $this->_start_time;
    }

    /**
     * @inheritDoc
     */
    public function get_finish_time()
    {
        return $this->_finish_time;
    }
}
