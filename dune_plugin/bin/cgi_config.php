<?php

class DuneSystem
{
    public static $properties = array();
}

function get_value_of_global_variables ($name, $key)
{
    return (isset ($name[$key]) ) ? ($name[$key]) : ('');
}

error_reporting (E_ALL & ~E_NOTICE);

$HD_NEW_LINE = PHP_EOL;
$LOG_FILE = $argv[1];
$apk_subst = getenv('FS_PREFIX');
$ini_arr = @parse_ini_file('$apk_subst/tmp/run/versions.txt');
if (!empty($LOG_FILE)) {
    if (file_exists($LOG_FILE)) {
        @unlink($LOG_FILE);
    }
    date_default_timezone_set('UTC');
}

function hd_print($str)
{
    global $HD_NEW_LINE;
    global $LOG_FILE;

    if (!empty($LOG_FILE)) {
        $log_file = fopen($LOG_FILE, 'ab+');
        fwrite($log_file, date("[Y-m-d H:i:s] ") . $str . $HD_NEW_LINE);
        fclose($log_file);
    } else {
        echo date("[Y-m-d H:i:s] ") . $str . $HD_NEW_LINE;
    }
}

DuneSystem::$properties['plugin_version']   = $argv[2];
DuneSystem::$properties['plugin_name']      = get_value_of_global_variables ($_ENV, 'PLUGIN_NAME');
DuneSystem::$properties['install_dir_path'] = get_value_of_global_variables ($_ENV, 'PLUGIN_INSTALL_DIR_PATH');
DuneSystem::$properties['tmp_dir_path']     = get_value_of_global_variables ($_ENV, 'PLUGIN_TMP_DIR_PATH');
DuneSystem::$properties['plugin_www_url']   = get_value_of_global_variables ($_ENV, 'PLUGIN_WWW_URL');
DuneSystem::$properties['plugin_cgi_url']   = get_value_of_global_variables ($_ENV, 'PLUGIN_CGI_URL');
DuneSystem::$properties['data_dir_path']    = get_value_of_global_variables ($_ENV, 'PLUGIN_DATA_DIR_PATH');
