<?php
error_reporting (E_ALL);

function get_value_of_global_variables ($name, $key)
{
    return (isset ($name[$key]) ) ? ($name[$key]) : ('');
}

class DuneSystem
{
    public static $properties = array ();
}

DuneSystem::$properties['plugin_name']      = get_value_of_global_variables ($_ENV, 'PLUGIN_NAME');
DuneSystem::$properties['install_dir_path'] = get_value_of_global_variables ($_ENV, 'PLUGIN_INSTALL_DIR_PATH');
DuneSystem::$properties['tmp_dir_path']     = get_value_of_global_variables ($_ENV, 'PLUGIN_TMP_DIR_PATH');
DuneSystem::$properties['plugin_www_url']   = get_value_of_global_variables ($_ENV, 'PLUGIN_WWW_URL');
DuneSystem::$properties['plugin_cgi_url']   = get_value_of_global_variables ($_ENV, 'PLUGIN_CGI_URL');
DuneSystem::$properties['data_dir_path']    = get_value_of_global_variables ($_ENV, 'PLUGIN_DATA_DIR_PATH');

set_include_path(get_include_path(). PATH_SEPARATOR . DuneSystem::$properties['install_dir_path']);
require_once 'lib/ordered_array.php';
require_once 'lib/hashed_array.php';

$HD_NEW_LINE = PHP_EOL;
$HD_OB_PREFIX = null;

function hd_print($str)
{
    global $HD_NEW_LINE, $HD_OB_PREFIX;
    if (isset($HD_OB_PREFIX))
    {
        echoLog($HD_OB_PREFIX . $str . $HD_NEW_LINE);
        ob_flush();
    }
    else
        echoLog($str . $HD_NEW_LINE);
}

function echoLog($str)
{
    $log = fopen(DuneSystem::$properties['tmp_dir_path'] . "/do.log", 'ab+');
    fwrite($log, date("[Y-m-d H:i:s] ") . $str);
    fclose($log);
    echo "$str<br>";
}

class epg_config
{
    static public $cache_dir;
    static public $cache_ttl;
    static public $xmltv_url;

    /**
     * load plugin/playlist settings
     *
     * @return void
     */
    public static function load()
    {
        @unlink(DuneSystem::$properties['tmp_dir_path'] . "/do.log");

        $parameters = HD::get_data_items('common.settings', true, false);

        if (!isset($parameters[PARAM_PLAYLISTS])) {
            echoLog("No playlist defined!");
            return;
        }

        $debug = isset($parameters[PARAM_ENABLE_DEBUG]) ? $parameters[PARAM_ENABLE_DEBUG] : SetupControlSwitchDefs::switch_off;
        set_debug_log($debug === SetupControlSwitchDefs::switch_on);

        self::$cache_dir = isset($parameters[PARAM_XMLTV_CACHE_PATH]) ? $parameters[PARAM_XMLTV_CACHE_PATH] : get_data_path("epg_cache");

        $name = hash('crc32', $parameters[PARAM_PLAYLISTS]->get_selected_item()) . '.settings';
        if (!file_exists(get_data_path($name))) {
            echoLog("No settings for playlist!");
            return;
        }

        $settings = HD::get_data_items($name, true, false);

        self::$cache_ttl = isset($settings[PARAM_EPG_CACHE_TTL]) ? $settings[PARAM_EPG_CACHE_TTL] : 3;
        self::$xmltv_url = isset($settings[PARAM_CUR_XMLTV_SOURCE]) ? $settings[PARAM_CUR_XMLTV_SOURCE] : '';
    }
}

header('Content-Type: text/html; charset=utf-8');
