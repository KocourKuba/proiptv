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

class KnownCatchupSourceTags
{
    const cu_unknown     = 'unknown';
    const cu_default     = 'default'; // only replace variables
    const cu_shift       = 'shift'; // ?utc=startUnix&lutc=nowUnix
    const cu_append      = 'append'; // appending value specified in catchup-source attribute to the base channel url
    const cu_archive     = 'archive'; // ?archive=startUnix&archive_end=toUnix
    const cu_timeshift   = 'timeshift'; // timeshift=startUnix&timenow=nowUnix
    const cu_flussonic   = 'flussonic';
    const cu_xstreamcode = 'xs'; // xstream codes

    public static $catchup_tags = array(
        self::cu_default => array('default'),
        self::cu_shift => array('shift', 'archive'),
        self::cu_append => array('append'),
        self::cu_archive => array('archive'),
        self::cu_timeshift => array('timeshift'),
        self::cu_flussonic => array('flussonic', 'flussonic-hls', 'fs', 'flussonic-ts'),
        self::cu_xstreamcode => array('xc'),
    );

    /**
     * @param string $tag
     * @param mixed $value
     * @return bool
     */
    public static function is_tag($tag, $value)
    {
        if (!isset(self::$catchup_tags[$tag]))
            return false;

        if (is_array($value)) {
            foreach ($value as $item) {
                if (in_array($item, self::$catchup_tags[$tag])) {
                    return true;
                }
            }
            return false;
        }
        return in_array($value, self::$catchup_tags[$tag]);
    }
}
