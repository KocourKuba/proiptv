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

require_once "starnet_tv_rows_screen.php";

require_once 'lib/dune_stb_api.php';
require_once "lib/epfs/config.php";
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
    protected static $dummy_epf_screen;

    /**
     * @var bool
     */
    protected static $no_internet_epfs_created = false;

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param Default_Dune_Plugin $plugin
     * @return void
     * @throws Exception
     */
    public static function init(Default_Dune_Plugin $plugin)
    {
        self::$enabled = HD::rows_api_support();
        if (!self::$enabled)
            return;

        self::$epf_id = $plugin->plugin_info['app_name'];
        self::$no_internet_epfs = self::$epf_id . '.no_internet';
        self::$dir_path = getenv('FS_PREFIX') . self::EPFS_PATH . self::$epf_id;
        self::$tv_rows_screen = new Starnet_Tv_Rows_Screen($plugin);

        $plugin->create_screen(self::$tv_rows_screen);

        self::$dummy_epf_screen = new Dummy_Epfs_Screen($plugin);
        $plugin->create_screen(self::$dummy_epf_screen);
    }

    /**
     * @param array|null $media_urls
     * @param array|null $post_action
     * @return array
     */
    public static function epfs_invalidate_folders($media_urls = null, $post_action = null)
    {
        self::update_all_epfs($plugin_cookies);

        if (self::$enabled) {
            $arr = array_merge(array(self::$epf_id), (is_array($media_urls) ? $media_urls : array()));
        } else {
            $arr = $media_urls;
        }

        return Action_Factory::invalidate_folders($arr, $post_action);
    }

    /**
     * @param bool $first_run
     * @param Object $plugin_cookies
     * @return array|null
     */
    public static function update_all_epfs(&$plugin_cookies, $first_run = false)
    {
        if (!self::$enabled)
            return null;

        if ($first_run)
            hd_debug_print("First run", true);

        self::ensure_no_internet_epfs_created($first_run, $plugin_cookies);

        $folder_view = self::$tv_rows_screen->get_folder_view_for_epf($plugin_cookies);

        if (!is_file(self::warmed_up_path())) {
            hd_debug_print("Cold run", true);
            file_put_contents(self::warmed_up_path(), '');
        }

        self::write_epf_view(self::$epf_id, $folder_view);

        return Action_Factory::status(0);
    }

    /**
     * @param bool $first_run
     * @param Object $plugin_cookies
     * @return void
     */
    private static function ensure_no_internet_epfs_created($first_run, &$plugin_cookies)
    {
        if (!self::$enabled || self::$no_internet_epfs_created)
            return;

        if ($first_run || !is_file(self::get_epf_path(self::$no_internet_epfs))) {
            self::write_epf_view(self::$no_internet_epfs, self::$dummy_epf_screen->get_folder_view_for_epf(true, $plugin_cookies));
        }

        self::$no_internet_epfs_created = true;
    }

    /**
     * @param string $epf_id
     * @return string
     */
    protected static function get_epf_path($epf_id)
    {
        return self::$dir_path . "/$epf_id.json";
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param string $epf_id
     * @param array|object $folder_view
     * @return void
     */
    protected static function write_epf_view($epf_id, $folder_view)
    {
        hd_debug_print(null, true);

        if ($folder_view === null) {
            return;
        }

        $path = self::get_epf_path($epf_id);
        $tmp_path = "$path.tmp";

        hd_debug_print("write epf path: $path", true);

        $res = file_put_contents($tmp_path, json_encode($folder_view));
        if (false === $res) {
            hd_debug_print("Failed to write tmp file: $tmp_path");
            return;
        }

        if (is_file($path)) {
            if (hash_file('md5', $tmp_path) === hash_file('md5', $path)) {
                unlink($tmp_path);
                hd_debug_print("$path is up to date", true);
                return;
            }

            unlink($path);
        }

        if (!rename($tmp_path, $path)) {
            hd_debug_print("Failed to rename $tmp_path to $path");
            unlink($tmp_path);
        }
    }

    public static function warmed_up_path()
    {
        return get_temp_path('epfs_warmed_up');
    }

    public static function async_worker_warmed_up_path()
    {
        return get_temp_path('async_worker_warmed_up');
    }

    public static function get_epfs_changed_path()
    {
        return get_temp_path('update_epfs_if_needed_flag');
    }

    ////////////////////////////////////////////////////////////////////////////

    protected static function get_ilang_path()
    {
        return self::$dir_path . '/ilang';
    }

    protected static function read_epfs_ts($id)
    {
        return is_file($path = self::get_epfs_ts_path($id)) ? file_get_contents($path) : '';
    }

    protected static function get_epfs_ts_path($id)
    {
        return self::$dir_path . "/{$id}_timestamp";
    }
}
