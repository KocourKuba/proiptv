<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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

require_once 'lib/default_dune_plugin_fw.php';
require 'starnet_plugin.php';

Default_Dune_Plugin_Fw::$plugin_class_name = 'Starnet_Plugin';

/**
 * @throws Exception
 */
function __autoload($className)
{
    $path = __DIR__ . "/$className.php";
    if (file_exists($path)) {
        hd_debug_print("include $path");
        include($path);
        return;
    }

    $path = __DIR__ . "/lib/$className.php";
    if (file_exists($path)) {
        hd_debug_print("include lib $path");
        include($path);
        return;
    }

    $path = __DIR__ . "/vod/$className.php";
    if (file_exists($path)) {
        hd_debug_print("include vod $path");
        include($path);
        return;
    }

    $path = __DIR__ . "/api/$className.php";
    if (file_exists($path)) {
        hd_debug_print("include api $path");
        include($path);
    }
}
