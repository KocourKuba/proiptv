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

class DuneSystem
{
    public static $properties = array();
}

function get_value_of_global_variables($name, $key)
{
    return (isset ($name[$key])) ? ($name[$key]) : ('');
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

error_reporting(E_ALL & ~E_NOTICE);

DuneSystem::$properties['plugin_name'] = get_value_of_global_variables($_ENV, 'PLUGIN_NAME');
DuneSystem::$properties['install_dir_path'] = get_value_of_global_variables($_ENV, 'PLUGIN_INSTALL_DIR_PATH');
DuneSystem::$properties['tmp_dir_path'] = get_value_of_global_variables($_ENV, 'PLUGIN_TMP_DIR_PATH');
DuneSystem::$properties['plugin_www_url'] = get_value_of_global_variables($_ENV, 'PLUGIN_WWW_URL');
DuneSystem::$properties['plugin_cgi_url'] = get_value_of_global_variables($_ENV, 'PLUGIN_CGI_URL');
DuneSystem::$properties['data_dir_path'] = get_value_of_global_variables($_ENV, 'PLUGIN_DATA_DIR_PATH');

$HD_NEW_LINE = PHP_EOL;
$LOG_FILE = DuneSystem::$properties['tmp_dir_path'] . "/error.log";
$apk_subst = getenv('FS_PREFIX');
$ini_arr = @parse_ini_file("$apk_subst/tmp/run/versions.txt");
