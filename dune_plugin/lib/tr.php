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
                $params .= "<p>" . self::strip_param($arg_list[$i]) . "</p>";
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
     * @param string $key
     */
    public static function g($key)
    {
        $num_args = func_num_args();
        $arg_list = func_get_args();
        if ($num_args > 1) {
            $params = '';
            for ($i = 1; $i < $num_args; $i++) {
                $params .= "<p>" . self::strip_param($arg_list[$i]) . "</p>";
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
     * Load translation by key and format it if additional arguments passed as parameters
     *
     * @param string $string_key can be as format
     * @return string constant in the system language by key
     */
    public static function load($string_key)
    {
        static $lang_file = '';
        if (empty($lang_file)) {
            $lang_file = self::get_translation_filename(self::get_current_language());
            if (empty($lang_file)) {
                hd_debug_print("Error loading language file $lang_file");
                $lang_file = 'x';
            }
        }

        static $lang_txt = '';
        if (empty($lang_txt)) {
            $lang_txt = file_get_contents($lang_file);
            if (empty($lang_txt)) {
                hd_debug_print("Error loading language file $lang_file");
                $lang_txt = 'x';
            } else {
                hd_debug_print("Loaded language file $lang_file, size: " . strlen($lang_txt));
            }
        }

        /** @var array $m */
        if (preg_match("/^$string_key\\s*=(.*)$/m", $lang_txt, $m)) {
            $args = func_get_args();
            array_shift($args);
            return vsprintf(trim($m[1]), $args);
        }

        hd_debug_print("Not found value for key '$string_key' in '$lang_file'!");
        return $string_key;
    }

    /**
     * Convert internal DuneHD translation format string to translated string
     * if format string contains arguments they must passed as parameters
     *
     * @param string $tr_fmt
     * @return string
     */
    public static function translate($tr_fmt)
    {
        if (strpos($tr_fmt, '%ext%') === false && strpos($tr_fmt, '%tr%') === false) {
            return $tr_fmt;
        }

        $xml = simplexml_load_string(self::strip_param($tr_fmt));
        $ar = (array)$xml;
        return self::load((string)$xml, $ar['p']);
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

    public static function get_current_language()
    {
        $lang = 'english';
        if (file_exists('/config/settings.properties')) {
            $sys_settings = parse_ini_file('/config/settings.properties', false, INI_SCANNER_RAW);
            if ($sys_settings !== false) {
                $lang = $sys_settings['interface_language'];
            }
        }

        return $lang;
    }

    public static function get_system_language_string_value($string_key)
    {
        # Returns a string constant in the system language by key

        $lang = self::get_current_language();
        static $lang_txt = '';
        if (empty($lang_txt)) {
            $lang_file = "/firmware/translations/dune_language_$lang.txt";
            $lang_txt = file_get_contents($lang_file);
            if (empty($lang_txt)) {
                hd_debug_print("Error loading language file $lang_file");
                $lang_txt = 'x';
            } else {
                hd_debug_print("Loaded language file $lang_file, size: " . strlen($lang_txt));
            }
        }

        /** @var array $m */
        if (preg_match("/^$string_key\\s*=(.*)$/m", $lang_txt, $m)) {
            $args = func_get_args();
            array_shift($args);
            return vsprintf(trim($m[1]), $args);
        }

        hd_debug_print("Not found value for key '$string_key'!");
        return '';
    }

    private static function strip_param($v)
    {
        if (0 === strpos($v, '%tr%')) {
            return "<key_local>" . substr($v, 4) . "</key_local>";
        }
        if (0 === strpos($v, '%ext%')) {
            return substr($v, 5);
        }

        return $v;
    }
}
