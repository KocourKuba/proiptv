<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code: Brigadir (forum.mydune.ru)
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

require_once 'archive.php';

class Archive_Cache
{
    protected static $archive_by_id;

    /**
     * @param Archive $archive
     * @return void
     */
    public static function set_archive(Archive $archive)
    {
        self::$archive_by_id[$archive->get_id()] = $archive;
    }

    /**
     * @param string $id
     * @return Archive|null
     */
    public static function get_archive_by_id($id)
    {
        return is_null(self::$archive_by_id) ? null : self::$archive_by_id[$id];
    }

    /**
     * @param string $id
     * @return void
     */
    public static function clear_archive($id)
    {
        if (is_null(self::$archive_by_id)) {
            return;
        }

        unset(self::$archive_by_id[$id]);
    }

    /**
     * @return void
     */
    public static function clear_all()
    {
        self::$archive_by_id = null;
    }
}
