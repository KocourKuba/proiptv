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

// class for working with config edit list
class List_Utils
{
    public static function config_file_path($config_id)
    {
        return getenv('FS_PREFIX') . "/config/lcfg_$config_id.txt";
    }

    /**
     * @param string $config_id
     * @return array
     */
    public static function read_config_file($config_id)
    {
        $path = self::config_file_path($config_id);

        $cfg = array();
        if (!is_file($path)) {
            return $cfg;
        }

        $flags = FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES;
        $lines = file($path, $flags);
        if (!$lines) {
            return $cfg;
        }

        $is_order = false;
        $changes = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) == 0) continue;

            if ($is_order) {
                $cfg[$line] = isset($changes[$line]) ? $changes[$line] : 1;
                continue;
            }

            if (0 === strpos($line, '---')) {
                $is_order = true;
                continue;
            }

            $id = substr($line, 1);
            if ($line[0] === '+' || $line[0] === '-') {
                $changes[$id] = ($line[0] === '-') ? 0 : 1;
            }
        }
        return $cfg;
    }

    /**
     * @param string $config_id
     * @param array $cfg
     * @return void
     */
    public static function write_config_file($config_id, $cfg)
    {
        hd_debug_print("Write config file: " . json_format_unescaped($cfg), true);
        $cfg_path = self::config_file_path($config_id);
        $enabled = '';
        $ordering = '';
        foreach ($cfg as $key => $value) {
            $enabled .= ($value ? '+' : '-') . $key . PHP_EOL;
            $ordering .= $key . PHP_EOL;
        }

        if (!empty($enabled)) {
            $enabled .= '---' . PHP_EOL;
        }

        $content = $enabled . $ordering;

        if (!empty($content)) {
            file_put_contents($cfg_path, $content);
        }
    }
}
