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

class TR
{
    public static function t()
    {
        $num_args = func_num_args();
        $arg_list = func_get_args();
        if ($num_args > 1) {
            $params = '';
            for ($i = 1; $i < $num_args; $i++) {
                $params .= "<p>$arg_list[$i]</p>";
            }
            $str = "%ext%<key_local>$arg_list[0]$params</key_local>";
        } else if ($num_args === 1) {
            $str = "%tr%$arg_list[0]";
        } else {
            $str = '';
        }
        return $str;
    }

    /**
     * @param $key string
     */
    public static function g($key)
    {
        $num_args = func_num_args();
        $arg_list = func_get_args();
        if ($num_args > 1) {
            $params = '';
            for ($i = 1; $i < $num_args; $i++) {
                $params .= "<p>$arg_list[$i]</p>";
            }
            $str = "%ext%<key_global>$arg_list[0]$params</key_global>";
            hd_debug_print($str);
        } else if ($num_args === 1) {
            $str = "%tr%$arg_list[0]";
        } else {
            $str = '';
        }
        return $str;
    }

    /**
     * @param $string_key
     * @return string constant in the system language by key
     */
    public static function load_string($string_key)
    {
        $lang = 'english';
        if (file_exists('/config/settings.properties')) {
            $sys_settings = parse_ini_file('/config/settings.properties', false, INI_SCANNER_RAW);
            if ($sys_settings !== false) {
                $lang = $sys_settings['interface_language'];
            }
        }

        $lang_file = self::get_translation_filename($lang);
        if (empty($lang_file)) {
            return '';
        }

        if (($lang_txt = file_get_contents($lang_file)) && preg_match("/^$string_key\\s*=(.*)$/m", $lang_txt, $m)) {
            return trim($m[1]);
        }

        return $string_key;
    }

    protected static function get_translation_filename($lang)
    {
        $lang_file = get_install_path("translations/dune_language_$lang.txt");
        if (file_exists($lang_file)) {
            return $lang_file;
        }

        $lang_file = get_install_path("translations/dune_language_english.txt");
        if (file_exists($lang_file)) {
            return $lang_file;
        }

        return '';
    }
}
