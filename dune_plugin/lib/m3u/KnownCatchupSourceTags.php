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

require_once 'lib/m3u/M3uTags.php';

class KnownCatchupSourceTags
{
    /**
     * @param string $tag
     * @param mixed $value
     * @return bool
     */
    public static function is_tag($tag, $value)
    {
        static $catchup_tags = array(
            ATTR_CATCHUP_DEFAULT => array(ATTR_CATCHUP_DEFAULT), // only replace variables
            ATTR_CATCHUP_SHIFT => array(ATTR_CATCHUP_SHIFT), // ?utc=startUnix&lutc=nowUnix
            ATTR_CATCHUP_APPEND => array(ATTR_CATCHUP_APPEND), // appending value specified in catchup-source attribute to the base channel url
            ATTR_CATCHUP_ARCHIVE => array(ATTR_CATCHUP_ARCHIVE), // ?archive=startUnix&archive_end=toUnix
            ATTR_TIMESHIFT => array(ATTR_TIMESHIFT), // timeshift=startUnix&timenow=nowUnix
            ATTR_CATCHUP_FLUSSONIC => array(ATTR_CATCHUP_FLUSSONIC, ATTR_CATCHUP_FLUSSONIC_HLS, ATTR_CATCHUP_FS, ATTR_CATCHUP_FLUSSONIC_TS),
            ATTR_CATCHUP_XTREAM_CODES => array(ATTR_CATCHUP_XTREAM_CODES),
        );

        if (!isset($catchup_tags[$tag])) {
            return false;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (in_array($item, $catchup_tags[$tag])) {
                    return true;
                }
            }
            return false;
        }

        return in_array($value, $catchup_tags[$tag]);
    }
}
