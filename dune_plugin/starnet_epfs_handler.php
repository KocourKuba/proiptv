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

////////////////////////////////////////////////////////////////////////////////

require_once "screens_tv/starnet_tv_rows_screen.php";

require_once 'lib/dune_stb_api.php';
require_once "lib/epfs/config.php";
require_once "lib/epfs/rows_json_writer.php";
require_once "lib/epfs/dummy_epfs_screen.php";

////////////////////////////////////////////////////////////////////////////////

class Starnet_Epfs_Handler
{
    const EPFS_PATH = '/flashdata/plugins_epfs/';

    /**
     * @var string
     */
    public static $epf_id;

    /**
     * @var bool
     */
    public static $enabled;

    /**
     * @var string
     */
    protected static $dir_path;

    /**
     * @var string
     */
    protected static $no_internet_epfs;

    /**
     * @var Starnet_Tv_Rows_Screen
     */
    protected static $tv_rows_screen;

    /**
     * @var Dummy_Epfs_Screen
     */
    protected static $dummy_epfs_screen;

    /**
     * @var bool
     */
    protected static $no_internet_epfs_created = false;

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param Default_Dune_Plugin $plugin
     * @param object $plugin_cookies
     * @return void
     */
    public static function init(Default_Dune_Plugin $plugin, $plugin_cookies)
    {
        $newui_support = HD::rows_api_support();
        $newui_enabled = get_cookie_bool_param($plugin_cookies, PARAM_COOKIE_ENABLE_NEWUI);
        self::$enabled = $newui_support && $newui_enabled;
        self::$epf_id = Default_Dune_Plugin::$plugin_info['app_name'];
        self::$no_internet_epfs = self::$epf_id . '.no_internet';
        self::$dir_path = getenv('FS_PREFIX') . self::EPFS_PATH . self::$epf_id;

        // Setup all variables before exit
        if (!self::$enabled) {
            return;
        }

        self::$tv_rows_screen = new Starnet_Tv_Rows_Screen($plugin);

        $plugin->create_screen(self::$tv_rows_screen);

        self::$dummy_epfs_screen = new Dummy_Epfs_Screen($plugin);
        $plugin->create_screen(self::$dummy_epfs_screen);
    }

    /**
     * @param object $plugin_cookies
     * @return array|null
     */
    public static function update_epfs_file(&$plugin_cookies)
    {
        if (!self::$enabled) {
            return null;
        }

        hd_debug_print(null, true);

        self::ensure_no_internet_epfs_created($plugin_cookies);

        if (!self::write_epfs_view_streamed(self::$epf_id, $plugin_cookies)) {
            // nothing to stream, or the pane turned out to be the empty one
            self::write_epfs_view(self::$epf_id, self::$tv_rows_screen->get_folder_view_for_epf($plugin_cookies));
        }

        return Action_Factory::status(0);
    }

    /**
     * Writes the rows pane straight to the epfs file, one row at a time.
     *
     * The pane for a large playlist is tens of MB of PHP arrays before
     * json_encode() makes a string of the same size again; streaming it keeps
     * only one row alive at a time. The file is compared with the existing one
     * by md5 exactly as write_epfs_view() does, except the comparison happens
     * after the temp file is written rather than before.
     *
     * @param string $epfs_id
     * @param object $plugin_cookies
     * @return bool false when the caller should fall back to the in-memory path
     */
    protected static function write_epfs_view_streamed($epfs_id, &$plugin_cookies)
    {
        $folder_view = self::$tv_rows_screen->get_streaming_folder_view($plugin_cookies);
        if ($folder_view === null) {
            return false;
        }

        $path = self::get_epfs_path($epfs_id);
        $tmp_path = "$path.tmp";

        $res = Rows_Json_Writer::write_to_file($tmp_path, $folder_view, self::$tv_rows_screen);

        if ($res === false) {
            safe_unlink($tmp_path);
            return false;
        }

        if (!$res['produced']) {
            // no category rows - the pane is the empty one, small enough to
            // build the ordinary way
            hd_debug_print('no category rows, not streamed', true);
            safe_unlink($tmp_path);
            return false;
        }

        if (is_file($path) && $res['md5'] === hash_file('md5', $path)) {
            hd_debug_print("$path is up to date", true);
            safe_unlink($tmp_path);
            return true;
        }

        safe_unlink($path);
        if (!rename($tmp_path, $path)) {
            hd_debug_print("Failed to rename $tmp_path to $path");
            safe_unlink($tmp_path);
            return false;
        }

        hd_debug_print("written epf path: $path ({$res['rows']} rows)", true);

        return true;
    }

    /**
     * @return void
     */
    public static function clear_epfs_file()
    {
        hd_debug_print(null, true);
        safe_unlink(self::get_epfs_path(self::$epf_id));
        safe_unlink(self::get_epfs_path(self::$no_internet_epfs));
    }

    /**
     * @param object $plugin_cookies
     * @return void
     */
    private static function ensure_no_internet_epfs_created(&$plugin_cookies)
    {
        if (!self::$enabled) {
            return;
        }

        hd_debug_print(null, true);
        if (self::$no_internet_epfs_created) {
            return;
        }

        $first_run = false;
        if (is_file(self::first_run_path())) {
            $first_run = true;
            unlink(self::first_run_path());
            hd_debug_print('First run', true);
        }

        if ($first_run || !is_file(self::get_epfs_path(self::$no_internet_epfs))) {
            self::write_epfs_view(self::$no_internet_epfs, self::$dummy_epfs_screen->get_folder_view_for_epf(true, $plugin_cookies));
        }

        self::$no_internet_epfs_created = true;
    }

    /**
     * @param string $epf_id
     * @return string
     */
    public static function get_epfs_path($epf_id)
    {
        return self::$dir_path . "/$epf_id.json";
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param string $epfs_id
     * @param array|object $folder_view
     * @return void
     */
    protected static function write_epfs_view($epfs_id, $folder_view)
    {
        hd_debug_print(null, true);

        if ($folder_view === null) {
            return;
        }

        $path = self::get_epfs_path($epfs_id);
        $tmp_path = "$path.tmp";

        $new_json = json_encode($folder_view);
        $md5 = md5($new_json);

        // Do not load existing json to memory to check equality
        if (is_file($path)) {
            if ($md5 === hash_file('md5', $path)) {
                hd_debug_print("$path is up to date", true);
                return;
            }

            safe_unlink($path);
        }

        $res = file_put_contents($tmp_path, $new_json);
        if (false === $res) {
            hd_debug_print("Failed to write tmp file: $tmp_path");
            return;
        }

        if (!rename($tmp_path, $path)) {
            hd_debug_print("Failed to rename $tmp_path to $path");
            safe_unlink($tmp_path);
        }

        hd_debug_print("written epf path: $path", true);
    }

    /**
     * @return string
     */
    public static function warmed_up_path()
    {
        return get_temp_path('epfs_warmed_up');
    }

    /**
     * @return string
     */
    public static function first_run_path()
    {
        return get_temp_path('epfs_first_run');
    }

    /**
     * @return string
     */
    public static function async_worker_warmed_up_path()
    {
        return get_temp_path('async_worker_warmed_up');
    }

    /**
     * @return string
     */
    public static function get_epfs_changed_path()
    {
        return get_temp_path('update_epfs_if_needed_flag');
    }

    /**
     * @return string
     */
    public static function get_current_epfs_plugin()
    {
        $config = getenv('FS_PREFIX') . self::EPFS_PATH . 'epf_mapping.txt';
        foreach (readlines($config) as $line) {
            if (strncmp($line, 'shell_ext:tv', 12) !== 0) {
                return trim(substr($line, 12), ' =');
            }
        }
        return "";
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    protected static function get_ilang_path()
    {
        return self::$dir_path . '/ilang';
    }

    /**
     * @param string $id
     * @return string
     */
    protected static function read_epfs_ts($id)
    {
        return is_file($path = self::get_epfs_ts_path($id)) ? file_get_contents($path) : '';
    }

    /**
     * @param string $id
     * @return string
     */
    protected static function get_epfs_ts_path($id)
    {
        return self::$dir_path . "/{$id}_timestamp";
    }
}
