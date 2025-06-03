<?php
set_include_path(get_include_path() . PATH_SEPARATOR . "./php" . PATH_SEPARATOR . "./dune_plugin");
set_time_limit(0);

if (!class_exists('DuneSystem')) {
    class DuneSystem
    {
        public static $properties = array();
    }
}

include 'bootstrap.php';
include 'dune_api.php';
include 'dune_plugin.php';
include 'dune_plugin_fw.php';

spl_autoload_register(function ($className) {
    $directories = array(
        __DIR__,
        __DIR__ . '/lib',
        __DIR__ . '/vod',
        __DIR__ . '/api',
    );

    foreach ($directories as $dir) {
        $path = $dir . '/' . $className . '.php';
        if (file_exists($path)) {
            hd_debug_print("include $path");
            include $path;
            return;
        }
    }
});

require_once 'lib/default_dune_plugin_fw.php';

$HD_NEW_LINE = PHP_EOL;
$HD_HTTP_LOCAL_PORT = 80;
$PLUGIN_NAME = "proiptv";
$FS_PREFIX = "./dune_fs";

putenv("FS_PREFIX=$FS_PREFIX");
putenv("HD_HTTP_LOCAL_PORT=$HD_HTTP_LOCAL_PORT");
putenv("PHP_EXTERNAL=start /b C:\\php\\php5\\32\\php.exe");
putenv("PLUGIN_NAME=proiptv");
putenv("PLUGIN_INSTALL_DIR_PATH=./dune_plugin");
putenv("PLUGIN_DATA_DIR_PATH=$FS_PREFIX/flashdata/plugins_data/$PLUGIN_NAME");
putenv("PLUGIN_TMP_DIR_PATH=$FS_PREFIX/tmp/plugins/$PLUGIN_NAME");
putenv("PLUGIN_WWW_URL=http://127.0.0.1:$HD_HTTP_LOCAL_PORT/plugins/$PLUGIN_NAME/");
putenv("PLUGIN_CGI_URL=http://127.0.0.1:$HD_HTTP_LOCAL_PORT/cgi-bin/plugins/$PLUGIN_NAME/");

Default_Dune_Plugin_Fw::$plugin_class_name = 'Starnet_Plugin';

DuneSystem::$properties['plugin_name'] = getenv('PLUGIN_NAME');
DuneSystem::$properties['install_dir_path'] = getenv('PLUGIN_INSTALL_DIR_PATH');
DuneSystem::$properties['data_dir_path'] = getenv('PLUGIN_DATA_DIR_PATH');
DuneSystem::$properties['tmp_dir_path'] = getenv('PLUGIN_TMP_DIR_PATH');
DuneSystem::$properties['plugin_www_url'] = getenv('PLUGIN_WWW_URL');
DuneSystem::$properties['plugin_cgi_url'] = getenv('PLUGIN_CGI_URL');
$plugin_cookies = (Object)array();

require_once 'starnet_plugin.php';
