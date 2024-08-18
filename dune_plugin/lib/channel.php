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

interface Channel
{
    /**
     * unique id for channel. typically hash
     * @return string
     */
    public function get_id();

    /**
     * channel title
     * @return string
     */
    public function get_title();

    /**
     * channel description
     * @return string
     */
    public function get_desc();

    /**
     * channel set description
     * @param string $desc
     */
    public function set_desc($desc);

    /**
     * icon uri
     * @return string
     */
    public function get_icon_url();

    /**
     * @return Group
     */
    public function get_parent_group();

    /**
     * internal number
     * @return int
     */
    public function get_number();

    /**
     * is channel support archive playback
     * @return int
     */
    public function get_archive();

    /**
     * is protected (adult)
     * @return bool
     */
    public function is_protected();

    /**
     * is disabled (hidden)
     * @return bool
     */
    public function is_disabled();

    /**
     * set disabled (hide)
     * @param bool $disabled
     * @return void
     */
    public function set_disabled($disabled);

    /**
     * primary EPG source
     * @return array
     */
    public function get_epg_ids();

    /**
     * @return int
     */
    public function get_past_epg_days();

    /**
     * @return int
     */
    public function get_future_epg_days();

    /**
     * @return int
     */
    public function get_archive_past_sec();

    /**
     * @return int
     */
    public function get_archive_delay_sec();

    /**
     * timeshift for epg to this channel
     * @return int
     */
    public function get_timeshift_hours();

    /**
     * custom streaming url
     * @return string
     */
    public function get_url();

    /**
     * streaming url for archive
     * @return string
     */
    public function get_archive_url();

    /**
     * catchup type for channel
     * @return string
     */
    public function get_catchup();

    /**
     * additional parameters
     * @return array
     */
    public function get_ext_params();
}
