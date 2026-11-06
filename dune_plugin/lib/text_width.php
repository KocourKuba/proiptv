<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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

require_once 'font_widths.php';

/**
 * Text width by the firmware font: the firmware does not tell how many lines a label takes
 */
class Text_Width
{
    // the advances of the font give a little less than the firmware draws
    const MARGIN = 1.03;
    const ELLIPSIS = '...';

    /**
     * @param string $text utf-8
     * @param int $font_size
     * @return int
     */
    public static function get($text, $font_size)
    {
        $widths = get_font_widths();
        $width = 0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return (int)ceil(strlen($text) * FONT_WIDTH_AVG * $font_size / 1000);
        }

        foreach ($chars as $char) {
            $width += round((isset($widths[$char]) ? $widths[$char] : FONT_WIDTH_AVG) * $font_size / 1000);
        }

        return (int)ceil($width * self::MARGIN);
    }

    /**
     * Lines of the text broken at spaces, the font size is not changed.
     * The last line of a text that does not fit ends with an ellipsis
     *
     * @param string $text utf-8
     * @param int $font_size
     * @param int $width
     * @param int $max_lines
     * @return string[]
     */
    public static function wrap($text, $font_size, $width, $max_lines)
    {
        $lines = array();
        $current = '';
        foreach (explode(' ', $text) as $word) {
            if ($word === '') continue;

            $try = $current === '' ? $word : "$current $word";
            if (self::get($try, $font_size) <= $width) {
                $current = $try;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                if (count($lines) >= $max_lines) {
                    $lines[$max_lines - 1] = self::cut($current, $font_size, $width);
                    return $lines;
                }
            }

            // a word wider than the line is cut by the label
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * @param string $text
     * @param int $font_size
     * @param int $width
     * @return string
     */
    protected static function cut($text, $font_size, $width)
    {
        $text = rtrim($text, ' ,|');
        while ($text !== '' && self::get($text . self::ELLIPSIS, $font_size) > $width) {
            $text = rtrim(mb_substr($text, 0, mb_strlen($text, 'UTF-8') - 1, 'UTF-8'), ' ,|');
        }

        return $text . self::ELLIPSIS;
    }
}
