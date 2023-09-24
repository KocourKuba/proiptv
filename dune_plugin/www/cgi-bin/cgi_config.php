<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */
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
$LOG_FILE = 'do.log';

function hd_print($str)
{
    global $HD_NEW_LINE;

    global $LOG_FILE;
    $log = fopen(DuneSystem::$properties['tmp_dir_path'] . "/" . $LOG_FILE, 'ab+');
    fwrite($log, date("[Y-m-d H:i:s] ") . $str . $HD_NEW_LINE);
    fclose($log);
}

class epg_config
{
    static public $cache_dir;
    static public $cache_ttl;
    static public $cache_engine;
    static public $xmltv_url;

    /**
     * load plugin/playlist settings
     *
     * @return void
     */
    public static function load()
    {
        global $LOG_FILE;
        @unlink(DuneSystem::$properties['tmp_dir_path'] . "/" . $LOG_FILE);

        $parameters = HD::get_data_items('common.settings', true, false);

        if (!isset($parameters[PARAM_PLAYLISTS])) {
            hd_print("No playlist defined!");
            return;
        }

        $debug = isset($parameters[PARAM_ENABLE_DEBUG]) ? $parameters[PARAM_ENABLE_DEBUG] : SetupControlSwitchDefs::switch_off;
        set_debug_log($debug === SetupControlSwitchDefs::switch_on);

        self::$cache_dir = isset($parameters[PARAM_XMLTV_CACHE_PATH]) ? $parameters[PARAM_XMLTV_CACHE_PATH] : get_data_path(EPG_CACHE_SUBDIR);

        if (class_exists('SQLite3')) {
            self::$cache_engine = isset($parameters[PARAM_EPG_CACHE_ENGINE]) ? $parameters[PARAM_EPG_CACHE_ENGINE] : ENGINE_SQLITE;
        } else {
            self::$cache_engine = ENGINE_LEGACY;
        }

        $name = hash('crc32', $parameters[PARAM_PLAYLISTS]->get_selected_item()) . '.settings';
        if (!file_exists(get_data_path($name))) {
            hd_print("No settings for playlist!");
            return;
        }

        $settings = HD::get_data_items($name, true, false);

        self::$cache_ttl = isset($settings[PARAM_EPG_CACHE_TTL]) ? $settings[PARAM_EPG_CACHE_TTL] : 3;
        self::$xmltv_url = isset($settings[PARAM_CUR_XMLTV_SOURCE]) ? $settings[PARAM_CUR_XMLTV_SOURCE] : '';
        $LOG_FILE = Hashed_Array::hash(self::$xmltv_url) . ".log";
    }
}

header('Content-Type: text/html; charset=utf-8');

