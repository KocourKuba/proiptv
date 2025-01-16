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

// class to implement json serialization directly to file to avoid encode large arrays in memory
class Json_Stream_Encoder
{
    protected static $_messages = array(
        JSON_ERROR_NONE             => 'No error has occurred',
        JSON_ERROR_DEPTH            => 'The maximum stack depth has been exceeded',
        JSON_ERROR_STATE_MISMATCH   => 'Invalid or malformed JSON',
        JSON_ERROR_CTRL_CHAR        => 'Control character error, possibly incorrectly encoded',
        JSON_ERROR_SYNTAX           => 'Syntax error',
        JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    );

    const BEGIN_OBJECT = '{';
    const END_OBJECT = '}';
    const BEGIN_ARRAY = '[';
    const END_ARRAY = ']';
    const DELIMITER = ',';

    /**
     * @param string $filename
     * @param mixed $value
     * @param int $options
     * @return bool
     */
    public static function encodeToFile($filename, $value, $options = 0)
    {
        $stream = fopen($filename, 'w');
        if (false === $stream) {
            hd_debug_print("Failed to open file $filename for writing");
            return false;
        }

        self::encodeToStream($stream, $value, $options);
        fclose($stream);

        return true;
    }

    /**
     * @param resource $stream
     * @param mixed $value
     * @param int $options
     * @return void
     */
    public static function encodeToStream($stream, $value, $options = 0)
    {
        if (is_array($value)) {
            if (self::isAssoc($value)) {
                fwrite($stream, self::BEGIN_OBJECT, 1);
                foreach ($value as $key => $element) {
                    fwrite($stream, "\"$key\":");
                    self::encodeToStream($stream, $element, $options);
                    fwrite($stream, self::DELIMITER, 1);
                }
                fseek($stream, -1, SEEK_CUR);
                fwrite($stream, self::END_OBJECT, 1);
            } else {
                fwrite($stream, self::BEGIN_ARRAY, 1);
                foreach ($value as $element) {
                    self::encodeToStream($stream, $element, $options);
                    fwrite($stream, self::DELIMITER, 1);
                }
                fseek($stream, -1, SEEK_CUR);
                fwrite($stream, self::END_ARRAY, 1);
            }
        } else {
            fwrite($stream, json_encode($value, $options));
        }
    }

    /**
     * Determines if an array is associative.
     * An array is "associative" if it doesn't have sequential numerical keys beginning with zero.
     *
     * @param  array $array
     * @return bool
     */
    public static function isAssoc($array)
    {
        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }

    public static function getArraySize($array)
    {
        $size = 0;
        foreach($array as $element) {
            if (is_array($element)) {
                $size += self::getArraySize($element);
            } else {
                $size += strlen($element);
            }
        }

        return $size;
    }
}
