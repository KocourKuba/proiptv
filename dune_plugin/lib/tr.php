<?php

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
            hd_print($str);
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
        if ($sys_settings = parse_ini_file('/config/settings.properties', false, INI_SCANNER_RAW)) {
            $lang_file = self::get_translation_filename($sys_settings['interface_language']);
            if (empty($lang_file))
                return '';

            if (($lang_txt = file_get_contents($lang_file)) && preg_match("/^$string_key\\s*=(.*)$/m", $lang_txt, $m))
                return trim($m[1]);
        }

        hd_print(__METHOD__ . "Value for key '$string_key' is not found!");

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
