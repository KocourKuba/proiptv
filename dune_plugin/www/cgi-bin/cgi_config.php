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
require_once 'lib/hd.php';

$HD_NEW_LINE = PHP_EOL;
$LOG_FILE = 'do.log';

function hd_print($str)
{
    global $HD_NEW_LINE;
    global $LOG_FILE;

    $log = fopen(DuneSystem::$properties['tmp_dir_path'] . "/" . $LOG_FILE, 'ab+');
    fwrite($log, $str . $HD_NEW_LINE);
    fclose($log);
}

function get_uri_parameters()
{
    $query = getenv("QUERY_STRING");
    $out_arr = array();

    $params = explode("&", $query);
    if (!is_array($params)) {
        $params = array($params);
    }

    foreach ($params as $val_arg) {
        $args = explode("=", $val_arg);
        if (is_array($args)) {
            $out_arr[$args[0]] = $args[1];
        }
    }

    return $out_arr;
}

header('Content-Type: text/html; charset=utf-8');

