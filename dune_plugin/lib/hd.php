<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Some code imported from various authors of dune plugins
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

require_once 'dune_stb_api.php';
require_once 'dune_plugin_constants.php';

class HD
{
    // QR code images and the room they take in a dialog
    const QR_CODE_SIZE = 450;

    /**
     * @var string
     */
    private static $default_user_agent;

    /**
     * @var string
     */
    private static $plugin_user_agent;

    /**
     * @var string
     */
    private static $plugin_manifest_info;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param mixed $opts
     * @param int $ident
     * @return void
     */
    public static function print_array($opts, $ident = 0)
    {
        if (is_array($opts)) {
            foreach ($opts as $k => $v) {
                if (is_array($v)) {
                    hd_debug_print(str_repeat(' ', $ident) . "$k : array");
                    self::print_array($v, $ident + 4);
                } else {
                    hd_debug_print(str_repeat(' ', $ident) . "$k : $v");
                }
            }
        } else {
            hd_debug_print(str_repeat(' ', $ident) . $opts);
        }
    }

    ///////////////////////////////////////////////////////////////////////

    public static function get_manifest_info_value($name)
    {
        if (empty(self::$plugin_manifest_info)) {
            self::$plugin_manifest_info = get_plugin_manifest_info();
        }

        return isset(self::$plugin_manifest_info[$name]) ? self::$plugin_manifest_info[$name] : '';
    }

    /**
     * @return string
     */
    public static function get_default_user_agent()
    {
        if (empty(self::$default_user_agent))
            self::http_init();

        return self::$default_user_agent;
    }

    /**
     * @return void
     */
    public static function http_init()
    {
        if (!empty(self::$default_user_agent))
            return;

        self::$default_user_agent = "DuneHD/1.0";

        $extra_useragent = "";
        $sysinfo = @file(getenv('FS_PREFIX') ."/tmp/sysinfo.txt", FILE_IGNORE_NEW_LINES);
        if ($sysinfo !== false) {
            foreach ($sysinfo as $line) {
                if (strpos($line, 'product_id:') !== false ||
                    strpos($line, 'firmware_version:') !== false) {
                    $line = trim($line);

                    if (empty($extra_useragent))
                        $extra_useragent = " (";
                    else
                        $extra_useragent .= "; ";

                    $extra_useragent .= $line;
                }
            }

            if (!empty($extra_useragent)) {
                $extra_useragent .= '; ' . HD::get_manifest_info_value('app_caption') . ' ' . HD::get_manifest_info_value('app_version') . ')';
            }
        }

        self::$default_user_agent .= $extra_useragent;

        hd_debug_print('HTTP UserAgent: ' . self::$default_user_agent);
    }

    /**
     * @param string $user_agent
     * @return void
     */
    public static function set_dune_user_agent($user_agent)
    {
        self::$plugin_user_agent = $user_agent;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public static function get_dune_user_agent()
    {
        if (empty(self::$default_user_agent))
            self::http_init();

        return (empty(self::$plugin_user_agent) || self::$default_user_agent === self::$plugin_user_agent) ? self::$default_user_agent : self::$plugin_user_agent;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $path
     * @param int|null $arg
     * @return array|string
     */
    public static function get_storage_size($path, $arg = null)
    {
        $path = get_noslash_trailed_path($path);
        if (!is_dir($path)) {
            return 'Unknown';
        }

        $d[0] = disk_free_space($path);
        $d[1] = disk_total_space($path);
        foreach ($d as $bytes) {
            $size[] = format_size($bytes);
        }

        if ($arg !== null) {
            $arr['str'] = $size[0] . '/' . $size[1];
            $arr['free_space'] = ($arg < $d[0]);
            return $arr;
        }
        return $size[0] . ' (' . $size[1] . ')';
    }

    /**
     * @param string $path
     * @param bool $preserve_keys
     * @param bool $json
     * @return array|mixed
     */
    public static function get_data_items($path, $preserve_keys = true, $json = true)
    {
        return self::get_items(get_data_path($path), $preserve_keys, $json);
    }

    /**
     * @param string $path
     * @param bool $preserve_keys
     * @param bool $json
     * @return array|mixed
     */
    public static function get_items($path, $preserve_keys = true, $json = true)
    {
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $items = $json ? json_decode($contents, true) : unserialize($contents);
            $items = is_null($items) ? array() : $items;
        } else {
            //hd_debug_print("$path not exist");
            $items = array();
        }

        return $preserve_keys ? $items : array_values($items);
    }

    /**
     * @param string $path
     * @param mixed $items
     * @param bool $json
     * @return void
     */
    public static function put_data_items($path, $items, $json = true)
    {
        self::put_items(get_data_path($path), $items, $json);
    }

    /**
     * @param string $path
     * @param mixed $items
     * @param bool $json
     * @return void
     */
    public static function put_items($path, $items, $json = true)
    {
        if (file_put_contents($path, $json ? json_encode($items) : serialize($items)) === false) {
            hd_debug_print("Failed to save $path");
        }
    }

    /**
     * @param string $path
     * @return void
     */
    public static function erase_data_items($path)
    {
        self::erase_items(get_data_path($path));
    }

    /**
     * @param string $path
     * @return void
     */
    public static function erase_items($path)
    {
        safe_unlink($path);
    }

    /**
     * @param string $path
     * @return false|string
     */
    public static function get_data_item($path)
    {
        $full_path = get_data_path($path);
        return file_exists($full_path) ? file_get_contents($full_path) : '';
    }

    /**
     * @param string $path
     * @param mixed $item
     * @return void
     */
    public static function put_data_item($path, $item)
    {
        file_put_contents(get_data_path($path), $item);
    }

    /**
     * @param string $sourcePath absoulute path where files will be searched
     * @param string $source_pattern regex pattern to match files
     * @param string $destPath absolute path to destination folder
     * @return void
     * @throws Exception
     */
    public static function copy_data($sourcePath, $source_pattern, $destPath)
    {
        if (empty($sourcePath) || empty($destPath)) {
            $msg = "One of is empty: sourceDir = $sourcePath | destDir = $destPath";
            hd_debug_print($msg);
            throw new Exception($msg);
        }

        if (!create_path($destPath)) {
            $msg = "Can't create destination folder: $destPath";
            hd_debug_print($msg);
            throw new Exception($msg);
        }

        foreach (glob_dir($sourcePath, $source_pattern) as $file) {
            $dest_file = get_slash_trailed_path($destPath) . basename($file);
            hd_debug_print("copy $file to $dest_file");
            if (!copy($file, $dest_file)) {
                throw new Exception(error_get_last());
            }
        }
    }

    /**
     * @param string $string
     * @return string|null
     */
    public static function detect_encoding($string)
    {
        static $list = array('utf-8', 'windows-1251', 'windows-1252', 'ASCII');

        foreach ($list as $item) {
            try {
                $sample = @iconv($item, $item, $string);
            } catch (Exception $ex) {
                print_backtrace_exception($ex);
                continue;
            }

            if (md5($sample) === md5($string)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Set cookie with expired time (timestamp).
     * If $persistent is true cookie stored to plugin data path
     *
     * @param string $filename file name without path
     * @param bool $persistent [optional] is stored in persistent file storage
     * @return void
     */
    public static function clear_cookie($filename, $persistent = false)
    {
        $file_path = $persistent ? get_data_path($filename) : get_temp_path($filename);
        safe_unlink($file_path);
    }

    /**
     * Download the file into the plugin temp folder once, it is taken from there afterwards
     *
     * @param string $url
     * @param string $file_name name in the temp folder
     * @return string|null path of the file, null if it can not be downloaded
     */
    public static function get_temp_download($url, $file_name)
    {
        $path = get_temp_path($file_name);
        if (file_exists($path) || Curl_Wrapper::getInstance()->download_file($url, $path)) {
            return $path;
        }

        hd_debug_print("Failed to download $url");
        return null;
    }

    /**
     * QR code image of the text, made by api.qrserver.com and kept in the temp folder
     *
     * @param string $data
     * @param string $file_name name in the temp folder, the extension selects the image format (png or jpg)
     * @return string|null path of the image
     */
    public static function get_qr_code($data, $file_name)
    {
        $format = strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpg';
        $url = sprintf('http://api.qrserver.com/v1/create-qr-code/?size=%1$dx%1$d&format=%2$s&data=%3$s',
            self::QR_CODE_SIZE, $format, urlencode($data));
        return self::get_temp_download($url, $file_name);
    }

    /**
     * QR code image for a dialog
     *
     * @param array $defs
     * @param string $image
     * @return void
     */
    public static function add_qr_code_defs(&$defs, $image)
    {
        $size = self::QR_CODE_SIZE;
        Control_Factory::add_smart_label($defs, "<gap width=25/><icon width=$size height=$size>$image</icon>");
        Control_Factory::add_vgap($defs, $size);
    }

    /**
     * Copy the playlist file or download the playlist link
     *
     * @param string $uri
     * @param string $dest
     * @param bool $is_file
     * @return void
     * @throws Exception err_load_playlist with the reason
     */
    public static function fetch_playlist($uri, $dest, $is_file)
    {
        if ($is_file) {
            $res = copy($uri, $dest);
        } else {
            $res = Curl_Wrapper::getInstance()->download_file($uri, $dest);
        }

        if ($res) {
            return;
        }

        if ($is_file) {
            $error = error_get_last();
            $reason = "Copy error: {$error['type']}\n{$error['message']}";
        } else {
            $reason = "Error code: " . Curl_Wrapper::get_error_no() . "\n" . Curl_Wrapper::get_error_desc();
        }

        throw new Exception(TR::load('err_load_playlist') . " '$uri'\n$reason");
    }

    /**
     * @param string $path
     * @param string $uri source of the file, for the message
     * @param bool $remove_bad remove the file when it is not a playlist
     * @return void
     * @throws Exception err_bad_m3u_file with the beginning of the file
     */
    public static function check_m3u_file($path, $uri, $remove_bad = false)
    {
        $contents = file_get_contents($path, false, null, 0, 1024);
        if (M3uParser::is_valid_m3u($contents)) {
            return;
        }

        if ($remove_bad) {
            safe_unlink($path);
        }

        throw new Exception(TR::load('err_bad_m3u_file') . " '$uri'\n\n" . substr($contents, 0, 512));
    }

    /**
     * Patch system or custom palette for default system color
     *
     * @param string $error
     * @return array|false
     */
    public static function color_palette_patch(&$error)
    {
        global $dune_default_colors_values;

        $error = '';
        clearstatcache();

        $skin_path = get_active_skin_path();
        $skin_config = "$skin_path/dune_skin_config.xml";
        if (!file_exists($skin_config)) {
            $error = "'$skin_config' does not exist";
            return false;
        }

        $origin_skin_config = file_get_contents($skin_config);

        $dom = new DomDocument();
        $dom->load($skin_config);
        $color = $dom->getElementsByTagName('color');

        foreach ($color as $item) {
            $color_index = null;
            $color_value = null;
            foreach ($item->attributes as $attrName => $attrNode) {
                if ($attrName == 'index') {
                    $color_index = $attrNode->value;
                }
                else if ($attrName == 'value') {
                    $color_value = $attrNode->value;
                }

                if (is_null($color_index) || is_null($color_value)) continue;

                if (isset($dune_default_colors_values[$color_index])) {
                    $attrNode->ownerElement->setAttribute('value', $dune_default_colors_values[$color_index]);
                }
            }
        }

        $reboot_action = Action_Factory::restart(true);
        $xml = $dom->saveXML();
        // cut <?xml> tag
        $patched_skin_config = substr($xml, strpos($xml, '?>') + 2);

        $fw_path = getenv('FS_PREFIX') . '/firmware';
        $fw_len = strlen($fw_path);
        if (strncmp($skin_path, $fw_path, $fw_len) === 0
            && (!isset($skin_path[$fw_len]) || $skin_path[$fw_len] === '/')) {
            // copy system skin to custom skin
            $custom_skin_path = preg_replace('/(.*\/(flashdata|persistfs)).*$/', "$1", get_data_path()) . '/dune_skin';
            hd_debug_print("New custom skin path: $custom_skin_path");

            // clear existing custom skin
            delete_directory($custom_skin_path);
            if (!create_path($custom_skin_path)) {
                $error = 'The directory for the custom skin in the system store is not available!';
                hd_debug_print("$error Process was terminated");
                return false;
            }

            foreach (glob("$skin_path/*") as $file) {
                $file = realpath($file);
                $basename = basename($file);

                if (is_dir($file)) {
                    recursive_copy($file, "$custom_skin_path/$basename");
                } else if ($basename == 'dune_skin_config.xml') {
                    if (!file_put_contents("$custom_skin_path/$basename", $patched_skin_config)) {
                        $error = "An unexpected error occurred when saving to save the 'dune_skin_config.xml'!";
                        hd_debug_print("$error The process was terminated");
                        return false;
                    }
                } else if (!copy($file, "$custom_skin_path/$basename")) {
                    $error = 'In the process of copying a skin file error occurred';
                    hd_debug_print("$error The process was terminated");
                    return false;
                }
            }

            $system_settings = get_shell_settings();
            if (!empty($system_settings)) {
                $system_settings['gui_skin'] = 'custom';
                $system_settings['appearance'] = 'custom';
                $reboot_action = Action_Factory::change_settings($system_settings, false, true);
            }
        } else if (!file_put_contents($skin_config, $patched_skin_config)) {
            $error = "An unexpected error occurred when saving to save the '$skin_config'";
            hd_debug_print("$error The process was terminated");
            return false;
        }

        create_path(get_data_path('skin_backup'));
        @file_put_contents(get_data_path('skin_backup/') . md5($patched_skin_config), $origin_skin_config);
        return Action_Factory::show_main_screen($reboot_action);
    }

    /**
     * @return array|null
     */
    public static function color_palette_restore()
    {
        $skin_config = get_active_skin_path() . '/dune_skin_config.xml';
        $hash = md5(file_get_contents($skin_config));
        $backup_storage_path = get_data_path('skin_backup');

        if (!file_exists($skin_config)) {
            hd_debug_print('Skin config file does not exist!');
            return null;
        }

        if (!file_exists($backup_storage_path)) {
            hd_debug_print('Backup storage path does not exist!');
            return null;
        }

        foreach (glob($backup_storage_path . '/*') as $file) {
            if (basename($file) !== $hash) continue;

            if (copy($file, $skin_config)) {
                safe_unlink($file);
            }

            hd_print('Skin colors restored succesfull!');
            break;
        }

        return Action_Factory::show_main_screen(Action_Factory::restart(true));
    }
}
