<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

function get_value_of_global_variables ($name, $key)
{
    return (isset ($name[$key]) ) ? ($name[$key]) : ('');
}

function hd_print($str)
{
    global $HD_NEW_LINE;
    global $LOG_FILE;

    $log = fopen($LOG_FILE, 'ab+');
    fwrite($log, date("[Y-m-d H:i:s] ") . $str . $HD_NEW_LINE);
    fclose($log);
}

class DuneSystem
{
    public static $properties = array();
}

error_reporting (E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');
set_include_path(get_include_path(). PATH_SEPARATOR . get_value_of_global_variables ($_ENV, 'PLUGIN_INSTALL_DIR_PATH'));

$HD_NEW_LINE = PHP_EOL;
$LOG_FILE = $argv[1];
@unlink($LOG_FILE);

DuneSystem::$properties['plugin_name']      = get_value_of_global_variables ($_ENV, 'PLUGIN_NAME');
DuneSystem::$properties['install_dir_path'] = get_value_of_global_variables ($_ENV, 'PLUGIN_INSTALL_DIR_PATH');
DuneSystem::$properties['tmp_dir_path']     = get_value_of_global_variables ($_ENV, 'PLUGIN_TMP_DIR_PATH');
DuneSystem::$properties['plugin_www_url']   = get_value_of_global_variables ($_ENV, 'PLUGIN_WWW_URL');
DuneSystem::$properties['plugin_cgi_url']   = get_value_of_global_variables ($_ENV, 'PLUGIN_CGI_URL');
DuneSystem::$properties['data_dir_path']    = get_value_of_global_variables ($_ENV, 'PLUGIN_DATA_DIR_PATH');
