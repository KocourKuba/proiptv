<?php

require_once 'lib/default_dune_plugin_fw.php';
require 'starnet_plugin.php';

Default_Dune_Plugin_Fw::$plugin_class_name = 'Starnet_Plugin';

/**
 * @throws Exception
 */
function __autoload($className) {
    hd_print("__autoload class $className");

    $path = __DIR__ . "/$className.php";
    if (file_exists($path)) {
        hd_print("include $path");
        include($path);
        return;
    }

    $path = __DIR__ . "/lib/$className.php";
    if (file_exists($path)) {
        hd_print("include $path");
        include($path);
        return;
    }

    hd_print("$className.php not found");
}
