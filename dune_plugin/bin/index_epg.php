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

ini_set ('max_execution_time', 300);

if (!class_exists('DuneSystem')) {
    class DuneSystem
    {
        public static $properties = array();
    }
}

/**
 * The indexer emits a line per step of a job that runs for minutes, so the log handle is
 * held open instead of being reopened and closed for every line. The write is flushed so a
 * killed or hung indexer still leaves a complete log behind.
 *
 * @param string $str
 * @return void
 */
function hd_print($str)
{
    global $LOG_FILE;

    static $handle = null;
    static $handle_path = null;

    $out = date('[Y-m-d H:i:s] ') . $str . PHP_EOL;

    if (empty($LOG_FILE)) {
        echo $out;
        return;
    }

    // $LOG_FILE is assigned once the config is read, after the first few lines have already
    // gone to stdout - and the file is removed right after, so the handle is opened lazily
    // and reopened if the path ever changes.
    if ($handle_path !== $LOG_FILE) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        $handle = @fopen($LOG_FILE, 'ab');
        $handle_path = $LOG_FILE;
    }

    if ($handle === false || $handle === null) {
        echo $out;
        return;
    }

    fwrite($handle, $out);
    fflush($handle);
}

error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');

DuneSystem::$properties['plugin_name'] = getenv('PLUGIN_NAME');
DuneSystem::$properties['install_dir_path'] = getenv('PLUGIN_INSTALL_DIR_PATH');
DuneSystem::$properties['tmp_dir_path'] = getenv('PLUGIN_TMP_DIR_PATH');
DuneSystem::$properties['plugin_www_url'] = getenv('PLUGIN_WWW_URL');
DuneSystem::$properties['plugin_cgi_url'] = getenv('PLUGIN_CGI_URL');
DuneSystem::$properties['data_dir_path'] = getenv('PLUGIN_DATA_DIR_PATH');


$env = getenv('PHP_PATH');
if (empty($env)) {
    set_include_path(get_include_path() . PATH_SEPARATOR . DuneSystem::$properties['install_dir_path']);
} else {
    set_include_path($env);
}

list(, $config) = $argv;

require_once 'lib/epg/epg_manager_xmltv.php';

Epg_Manager_Xmltv::index_by_config($config);
