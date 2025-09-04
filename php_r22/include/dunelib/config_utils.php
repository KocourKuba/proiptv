<?php

require_once 'dunelib/utils.php';
require_once 'dunelib/dune_languages.php';

class ConfigUtils
{
    private static $cfg_mtime = -2;
    private static $loc_mtime = -2;

    private static $cfg = null;
    private static $loc = null;

    private static function get_cfg_mtime()
    {
        $path = "/config/settings.properties";
        return is_file($path) ? filemtime($path) : -1;
    }

    private static function get_loc_mtime()
    {
        $path1 = "/tmp/location_info.properties";
        $path2 = "/config/location_info.properties";
        return is_file($path1) ? filemtime($path1) :
            (is_file($path2) ? filemtime($path2) : -1);
    }

    public static function get_config()
    {
        $mtime = self::get_cfg_mtime();
        if ($mtime != self::$cfg_mtime)
        {
            self::$cfg_mtime = $mtime;
            self::$cfg = self::load_config();
        }
        return self::$cfg;
    }

    public static function get_location_info()
    {
        $mtime = self::get_loc_mtime();
        if ($mtime != self::$loc_mtime)
        {
            self::$loc_mtime = $mtime;
            self::$loc = self::load_location_info();
        }
        return self::$loc;
    }

    public static function set_dirty()
    {
        self::$cfg_mtime = -2;
        self::$loc_mtime = -2;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function load_config()
    {
        $cfg = (object) array(
            'interface_language' => 'english',
            'noncustom_interface_language' => 'english',
            'ui_type' => 'type3',
            'widget_country' => '',
            'widget_city' => '',
            'network_connection' => '',
            'kartina_tv_login' => '',
            'kartina_tv_password' => '',
            'kartina_tv_interface_type' => '',
            'time_format' => '24',
            'content_details_at_the_top' => 'yes',
            'auto_watched_percent' => "90%",
            'auto_watched_time_left' => "30s",
            'watched_marks_format' => "percents",
            'enter_on_file_behaviour_new' => "playlist",
            'play_on_file_behaviour' => "playlist",
        );

        $path = "/config/settings.properties";
        $lines = is_file($path) ? file($path) : array();
        foreach ($lines as $line)
        {
            $pos = strpos($line, "=");
            if ($pos === false || $line[0] == '#')
                continue;

            $key = trim(substr($line, 0, $pos));
            if (isset($cfg->$key))
                $cfg->$key = trim(substr($line, $pos + 1));
        }

        if ($cfg->interface_language == 'custom')
            $cfg->interface_language = $cfg->noncustom_interface_language;

        $cfg->lang_code = DuneLanguages::get_code_by_id($cfg->interface_language);
        if (!isset($cfg->lang_code)) // should not happen
        {
            hd_print("Warning: unknown language: '$cfg->interface_language'");
            $cfg->interface_language = "english";
            $cfg->lang_code = "en";
        }

        return $cfg;
    }

    public static function load_location_info()
    {
        $country = "";
        $city = "";
        $country_code_2 = "WW";

        $location_info_path = null;
        if (is_file("/tmp/location_info.properties"))
            $location_info_path = "/tmp/location_info.properties";
        else if (is_file("/config/location_info.properties"))
            $location_info_path = "/config/location_info.properties";

        if (isset($location_info_path))
        {
            $lines = file($location_info_path,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line)
            {
                $pos = strpos($line, " = ");
                if ($pos === false)
                    continue;

                $key = substr($line, 0, $pos);
                $val = trim(substr($line, $pos + 3));
                if ($key == 'country')
                    $country = $val;
                else if ($key == 'country_code_2' && $val)
                    $country_code_2 = $val;
                else if ($key == 'city')
                    $city = $val;
            }
        }

        return (object) array(
            'country' => $country,
            'country_code_2' => $country_code_2,
            'city' => $city,
        );
    }

    public static function load_plugin_cookies($plugin_name)
    {
        $map = (object) array();

        $path = "/config/${plugin_name}_plugin_cookies.properties";
        if (is_file($path))
        {
            foreach (file($path) as $line)
            {
                $pos = strpos($line, "=");
                if ($pos === false)
                    continue;

                $key = trim(substr($line, 0, $pos));
                if ($key !== '')
                    $map->$key = trim(substr($line, $pos + 1));
            }
        }

        return $map;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function load_firmware_features()
    {
        $path = "/tmp/firmware_features.txt";

        $ffset = array();
        foreach (HD::readlines($path) as $ff)
            $ffset[$ff] = 1;
        return $ffset;
    }

    public static function load_product_and_fw()
    {
        $res = (object) array(
            'firmware_version' => '',
            'product' => '',
            'platform_kind' => '',
            'android_platform' => '',
        );
        $path = "/tmp/run/versions.txt";
        foreach (HD::readlines($path) as $line)
        {
            $pos = strpos($line, '=');
            if (!$pos)
                continue;
            $key = substr($line, 0, $pos);
            if (isset($res->$key))
                $res->$key = substr($line, $pos + 1);
        }

        $res->platform_kind_group =
            self::platform_kind_to_group($res->platform_kind);

        if (HD::is_fw_apk())
        {
            $res->android_platform =
                file_get_contents('/firmware/config/fw_apk_platform.txt');
        }

        return $res;
    }

    private static function platform_kind_to_group($pk)
    {
        if (strlen($pk) == 4 && $pk[0] == '8' && ctype_digit($pk[1]))
            return substr($pk, 0, 2) . "xx";
        return $pk;
    }
}

?>
