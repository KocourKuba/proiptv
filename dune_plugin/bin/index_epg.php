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

if (!class_exists('DuneSystem')) {
    class DuneSystem
    {
        public static $properties = array();
    }
}

function hd_print($str)
{
    global $LOG_FILE;

    if (!empty($LOG_FILE)) {
        $log_file = fopen($LOG_FILE, 'ab+');
        fwrite($log_file, date("[Y-m-d H:i:s] ") . $str . PHP_EOL);
        fclose($log_file);
    } else {
        echo date("[Y-m-d H:i:s] ") . $str . PHP_EOL;
    }
}

error_reporting(E_ALL & ~E_NOTICE);

$apk_subst = getenv('FS_PREFIX');
$LOG_FILE = getenv('PLUGIN_TMP_DIR_PATH') . "/error.log";

DuneSystem::$properties['plugin_name'] = getenv('PLUGIN_NAME');
DuneSystem::$properties['install_dir_path'] = getenv('PLUGIN_INSTALL_DIR_PATH');
DuneSystem::$properties['tmp_dir_path'] = getenv('PLUGIN_TMP_DIR_PATH');
DuneSystem::$properties['plugin_www_url'] = getenv('PLUGIN_WWW_URL');
DuneSystem::$properties['plugin_cgi_url'] = getenv('PLUGIN_CGI_URL');
DuneSystem::$properties['data_dir_path'] = getenv('PLUGIN_DATA_DIR_PATH');

set_include_path(get_include_path() . PATH_SEPARATOR . DuneSystem::$properties['install_dir_path']);


require_once 'lib/epg/epg_manager_xmltv.php';

list(, $config) = $argv;

$epg_manager = new Epg_Manager_Xmltv();
$epg_manager->index_by_config($config);
