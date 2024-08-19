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

require_once 'epg_indexer_interface.php';

require_once 'lib/hd.php';
require_once 'lib/hashed_array.php';
require_once 'lib/curl_wrapper.php';

abstract class Epg_Indexer implements Epg_Indexer_Interface
{
    const STREAM_CHUNK = 131072; // 128Kb
    const INDEX_PICONS = 'picons';
    const INDEX_CHANNELS = 'channels';
    const INDEX_POSITIONS = 'positions';

    /**
     * path where cache is stored
     * @var string
     */
    protected $cache_dir;

    /**
     * url to download XMLTV EPG
     * @var string
     */
    protected $xmltv_url;

    /**
     * hash of xmtlt_url
     * @var string
     */
    protected $url_hash;

    /**
     * @var string
     */
    protected $index_ext;

    /**
     * @var int
     */
    protected $cache_ttl;

    /**
     * @var string
     */
    protected $cache_type = XMLTV_CACHE_AUTO;

    /**
     * @var Curl_Wrapper
     */
    protected $curl_wrapper;

    public function __construct()
    {
        $this->curl_wrapper = new Curl_Wrapper();
    }

    /**
     * @param string $cache_dir
     * @param string $url
     */
    public function init($cache_dir, $url)
    {
        $this->cache_dir = $cache_dir;
        $this->xmltv_url = $url;
        $this->url_hash = Hashed_Array::hash($this->xmltv_url);

        create_path($this->cache_dir);

        hd_debug_print("Indexer engine: " . get_class($this));
        hd_debug_print("Cache dir: $this->cache_dir");
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
        hd_debug_print("XMLTV EPG url: $this->xmltv_url");
        hd_debug_print("EPG url hash: $this->url_hash");
    }

    /**
     * @param int $cache_ttl
     * @return void
     */
    public function set_cache_ttl($cache_ttl)
    {
        $this->cache_ttl = $cache_ttl;
    }

    /**
     * @param string $type
     * @return void
     */
    public function set_cache_type($type)
    {
        $this->cache_type = $type;
    }

    /**
     * indexing xmltv file to make channel to display-name map
     * and collect picons for channels
     *
     * @return void
     */
    public function index_only_channels()
    {
        $res = $this->is_xmltv_cache_valid();
        hd_debug_print("cache valid status: $res", true);
        switch ($res) {
            case 1:
                // downloaded xmltv file not exists or expired
                hd_debug_print("Download and indexing xmltv source");
                $this->download_xmltv_source();
                $this->index_xmltv_channels();
                break;
            case 3:
                // downloaded xmltv file exists, not expired but indexes for channels, picons and positions not exists
                hd_debug_print("Indexing xmltv source");
                $this->remove_index(self::INDEX_CHANNELS);
                $this->remove_index(self::INDEX_PICONS);
                $this->remove_index(self::INDEX_POSITIONS);
                $this->index_xmltv_channels();
                break;
            default:
                break;
        }
    }

    /**
     * Checks if xmltv source cached and not expired.
     * if xmltv url not set return -1 and set_last_error contains error message
     * if downloaded xmltv file exists and all indexes are present return 0
     * if downloaded xmltv file not exists or expired return 1
     * if downloaded xmltv file exists, not expired and indexes for channels and icons exists return 2
     * if downloaded xmltv file exists, not expired but all indexes not exists return 3
     *
     * @return int
     */
    public function is_xmltv_cache_valid()
    {
        hd_debug_print();

        if (empty($this->xmltv_url)) {
            $exception_msg = "XMTLV EPG url not set";
            hd_debug_print($exception_msg);
            HD::set_last_error("xmltv_last_error", $exception_msg);
            return -1;
        }

        HD::set_last_error("xmltv_last_error", null);
        $cached_file = $this->get_cached_filename();
        hd_debug_print("Checking cached xmltv file: $cached_file");
        if (!file_exists($cached_file)) {
            hd_debug_print("Cached xmltv file not exist");
            return 1;
        }

        $check_time_file = filemtime($cached_file);
        hd_debug_print("Xmltv cache last modified: " . date("Y-m-d H:i", $check_time_file));

        $expired = true;
        if ($this->cache_type === XMLTV_CACHE_AUTO) {
            $this->curl_wrapper->set_url($this->xmltv_url);
            if ($this->curl_wrapper->check_is_expired()) {
                $this->curl_wrapper->clear_cached_etag($this->xmltv_url);
            } else {
                $expired = false;
            }
        } else if (filesize($cached_file) !== 0) {
            $max_cache_time = 3600 * 24 * $this->cache_ttl;
            if ($check_time_file && $check_time_file + $max_cache_time > time()) {
                $expired = false;
            }
        }

        if ($expired) {
            hd_debug_print("Xmltv cache expired.");
            return 1;
        }

        hd_debug_print("Cached file: $cached_file is not expired");
        $channels_index_valid = $this->is_index_valid(self::INDEX_CHANNELS);
        $picons_index_valid = $this->is_index_valid(self::INDEX_PICONS);
        $pos_index_valid = $this->is_index_valid(self::INDEX_POSITIONS);

        if ($channels_index_valid && $picons_index_valid && $pos_index_valid) {
            hd_debug_print("Xmltv cache valid");
            return 0;
        }

        if ($channels_index_valid && $picons_index_valid) {
            hd_debug_print("Xmltv cache channels and picons are valid");
            return 2;
        }

        hd_debug_print("Xmltv cache indexes are invalid");
        return 3;
    }

    /**
     * @return string
     */
    public function get_cached_filename()
    {
        return $this->get_cache_stem(".xmltv");
    }

    /**
     * @param string $ext
     * @return string
     */
    public function get_cache_stem($ext)
    {
        return $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_hash . $ext;
    }

    /**
     * Check is selected index is valid
     *
     * @param string $name
     * @return bool
     */
    abstract protected function is_index_valid($name);

    /**
     * Download XMLTV source.
     *
     * @return int
     */
    public function download_xmltv_source()
    {
        if ($this->is_index_locked()) {
            hd_debug_print("File is indexing or downloading, skipped");
            return 0;
        }

        $ret = -1;
        $t = microtime(true);

        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
        $cached_file = $this->get_cached_filename();
        $tmp_filename = $cached_file . '.tmp';
        if (file_exists($tmp_filename)) {
            unlink($tmp_filename);
        }

        try {
            HD::set_last_error("xmltv_last_error", null);
            $this->set_index_locked(true);

            if (preg_match("/jtv.?\.zip$/", basename($this->xmltv_url))) {
                throw new Exception("Unsupported EPG format (JTV)");
            }

            $this->curl_wrapper->set_url($this->xmltv_url);
            $expired = $this->curl_wrapper->check_is_expired() || !file_exists($tmp_filename);
            if (!$expired) {
                hd_debug_print("File not changed, using cached file: $cached_file");
                $this->set_index_locked(false);
                hd_debug_print_separator();
                return 1;
            }

            $this->curl_wrapper->clear_cached_etag($this->xmltv_url);
            if (!$this->curl_wrapper->download_file($tmp_filename, true)) {
                throw new Exception("Ошибка скачивания $this->xmltv_url\n\n" . $this->curl_wrapper->get_raw_response_headers());
            }

            if ($this->curl_wrapper->get_response_code() !== 200) {
                throw new Exception("Ошибка скачивания $this->xmltv_url\n\n" . $this->curl_wrapper->get_raw_response_headers());
            }

            $file_time = filemtime($tmp_filename);
            $dl_time = microtime(true) - $t;
            $bps = filesize($tmp_filename) / $dl_time;
            $si_prefix = array('B/s', 'KB/s', 'MB/s');
            $base = 1024;
            $class = min((int)log($bps, $base), count($si_prefix) - 1);
            $speed = sprintf('%1.2f', $bps / pow($base, $class)) . ' ' . $si_prefix[$class];

            hd_debug_print("Last changed time of local file: " . date("Y-m-d H:i", $file_time));
            hd_debug_print("Download xmltv source $this->xmltv_url done in: $dl_time secs (speed $speed)");

            if (file_exists($cached_file)) {
                unlink($cached_file);
            }

            $t = microtime(true);

            $handle = fopen($tmp_filename, "rb");
            $hdr = fread($handle, 8);
            fclose($handle);

            if (0 === mb_strpos($hdr, "\x1f\x8b\x08")) {
                hd_debug_print("GZ signature: " . bin2hex(substr($hdr, 0, 3)), true);
                rename($tmp_filename, $cached_file . '.gz');
                $tmp_filename = $cached_file . '.gz';
                hd_debug_print("ungzip $tmp_filename to $cached_file");
                $cmd = "gzip -d $tmp_filename 2>&1";
                system($cmd, $ret);
                if ($ret !== 0) {
                    throw new Exception("Failed to unpack $tmp_filename (error code: $ret)");
                }
                flush();
                $size = filesize($cached_file);
                touch($cached_file, $file_time);
                hd_debug_print("$size bytes ungzipped to $cached_file in " . (microtime(true) - $t) . " secs");
            } else if (0 === mb_strpos($hdr, "\x50\x4b\x03\x04")) {
                hd_debug_print("ZIP signature: " . bin2hex(substr($hdr, 0, 4)), true);
                hd_debug_print("unzip $tmp_filename to $cached_file");
                $filename = trim(shell_exec("unzip -lq '$tmp_filename'|grep -E '[\d:]+'"));
                if (empty($filename)) {
                    throw new Exception(TR::t('err_empty_zip__1', $tmp_filename));
                }

                if (explode('\n', $filename) > 1) {
                    throw new Exception("Too many files in zip archive, wrong format??!\n$filename");
                }

                hd_debug_print("zip list: $filename");
                $cmd = "unzip -oq $tmp_filename -d $this->cache_dir 2>&1";
                system($cmd, $ret);
                unlink($tmp_filename);
                if ($ret !== 0) {
                    throw new Exception("Failed to unpack $tmp_filename (error code: $ret)");
                }
                flush();

                rename($filename, $cached_file);
                $size = filesize($cached_file);
                touch($cached_file, $file_time);
                hd_debug_print("$size bytes unzipped to $cached_file in " . (microtime(true) - $t) . " secs");
            } else if (false !== mb_strpos($hdr, "<?xml")) {
                hd_debug_print("XML signature: " . substr($hdr, 0, 5), true);
                hd_debug_print("rename $tmp_filename to $cached_file");
                rename($tmp_filename, $cached_file);
                $size = filesize($cached_file);
                touch($cached_file, $file_time);
                hd_debug_print("$size bytes stored to $cached_file in " . (microtime(true) - $t) . " secs");
            } else {
                hd_debug_print("Unknown signature: " . bin2hex($hdr), true);
                throw new Exception(TR::load_string('err_unknown_file_type'));
            }

            $ret = 1;
            $this->remove_index(self::INDEX_CHANNELS);
            $this->remove_index(self::INDEX_PICONS);
            $this->remove_index(self::INDEX_POSITIONS);
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            if (!empty($tmp_filename) && file_exists($tmp_filename)) {
                unlink($tmp_filename);
            }

            if (file_exists($cached_file)) {
                unlink($cached_file);
            }
        }

        $this->set_index_locked(false);

        hd_debug_print_separator();

        return $ret;
    }

    /**
     * @return bool
     */
    public function is_index_locked()
    {
        $lock_dir = $this->get_cache_stem('.lock');
        return is_dir($lock_dir);
    }

    /**
     * @param bool $lock
     */
    public function set_index_locked($lock)
    {
        $lock_dir = $this->get_cache_stem('.lock');
        if ($lock) {
            if (!create_path($lock_dir, 0644)) {
                hd_debug_print("Directory '$lock_dir' was not created");
            }
        } else if (is_dir($lock_dir)) {
            hd_debug_print("Unlock $lock_dir");
            @rmdir($lock_dir);
        }
    }

    /**
     * Remove is selected index
     *
     * @param string $name
     */
    abstract public function remove_index($name);

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    public function clear_current_epg_files()
    {
        $this->clear_epg_files($this->url_hash);
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * clear memory cache and cache for selected filename mask
     *
     * @return void
     */
    public function clear_epg_files($filename)
    {
        $this->clear_memory_index();

        if (empty($this->cache_dir)) {
            return;
        }

        $files = $this->cache_dir . DIRECTORY_SEPARATOR . "$filename*";
        hd_debug_print("clear epg files: $files");
        shell_exec('rm -f ' . $files);
        flush();
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * Clear memory index
     *
     * @return void
     */
    abstract protected function clear_memory_index();

    /**
     * @param Channel $channel
     * @return array
     */
    abstract protected function load_program_index($channel);

    /**
     * check version of index file
     *
     * @return bool
     */
    abstract protected function check_index_version();

    /**
     * @return resource
     * @throws Exception
     */
    protected function open_xmltv_file()
    {
        $cached_file = $this->get_cached_filename();
        if (!file_exists($cached_file)) {
            throw new Exception("cache file $cached_file not exist");
        }

        $file = fopen($cached_file, 'rb');
        if (!$file) {
            throw new Exception("can't open $cached_file");
        }

        return $file;
    }
}
