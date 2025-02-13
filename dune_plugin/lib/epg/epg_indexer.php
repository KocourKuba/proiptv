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
require_once 'lib/perf_collector.php';

abstract class Epg_Indexer implements Epg_Indexer_Interface
{
    const STREAM_CHUNK = 131072; // 128Kb
    const INDEX_PICONS = 'epg_picons';
    const INDEX_CHANNELS = 'epg_channels';
    const INDEX_ENTRIES = 'epg_entries';

    /**
     * path where cache is stored
     * @var string
     */
    protected $cache_dir;

    /**
     * url params to download XMLTV EPG
     * @var array
     */
    protected $xmltv_url_params;

    /**
     * @var string
     */
    protected $index_ext;

    /**
     * @var Curl_Wrapper
     */
    protected $curl_wrapper;

    /**
     * @var int
     */
    protected $pid = 0;

    /**
     * @var Perf_Collector
     */
    protected $perf;

    public function __construct()
    {
        $this->curl_wrapper = new Curl_Wrapper();
        $this->perf = new Perf_Collector();
    }

    /**
     * Set and create cache dir
     *
     * @param string $cache_dir
     */
    public function set_cache_dir($cache_dir)
    {
        $this->cache_dir = $cache_dir;
        create_path($this->cache_dir);

        hd_debug_print("Indexer engine: " . get_class($this));
        hd_debug_print("Cache dir: $this->cache_dir");
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * Set PID of the process that index xmltv source
     *
     * @param int $pid
     * @return void
     */
    public function set_pid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * Get current PID
     *
     * @return int
     */
    public function get_pid()
    {
        return $this->pid;
    }

    /**
     * Set url parameters: url, cache, hash
     *
     * @param array $url_param
     * @return void
     */
    public function set_url_params($url_param)
    {
        hd_debug_print(null, true);
        $this->xmltv_url_params = $url_param;
    }

    /**
     * get url parameters
     *
     * @return array
     */
    public function get_url_params()
    {
        return $this->xmltv_url_params;
    }

    /**
     * get curl wrapper
     *
     * @return Curl_Wrapper
     */
    public function get_curl_wrapper()
    {
        return $this->curl_wrapper;
    }

    /**
     * indexing xmltv file to make channel to display-name map
     * and collect picons for channels
     *
     * @return void
     */
    public function index_only_channels()
    {
        hd_debug_print(null, true);

        $res = $this->is_xmltv_cache_valid();
        hd_debug_print("cache valid status: $res", true);
        hd_debug_print("Indexing channels for: {$this->xmltv_url_params[PARAM_URI]}", true);
        switch ($res) {
            case 1:
                // downloaded xmltv file not exists or expired
                hd_debug_print("Download and indexing xmltv source");
                $this->remove_indexes(array(self::INDEX_CHANNELS, self::INDEX_PICONS, self::INDEX_ENTRIES));
                if ($this->download_xmltv_source() === 1) {
                    $this->index_xmltv_channels();
                }
                break;
            case 3:
                // downloaded xmltv file exists, not expired but indexes for channels, picons and positions not exists
                hd_debug_print("Indexing xmltv source");
                $this->remove_indexes(array(self::INDEX_CHANNELS, self::INDEX_PICONS, self::INDEX_ENTRIES));
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
        hd_debug_print(null, true);

        if (empty($this->xmltv_url_params) || !isset($this->xmltv_url_params[PARAM_URI])) {
            $exception_msg = "XMTLV EPG url not set";
            hd_debug_print($exception_msg);
            HD::set_last_error("xmltv_last_error", $exception_msg);
            return -1;
        }

        $url = $this->xmltv_url_params[PARAM_URI];
        $hash = $this->xmltv_url_params[PARAM_HASH];
        $cache_ttl = !isset($this->xmltv_url_params[PARAM_CACHE]) ? XMLTV_CACHE_AUTO : $this->xmltv_url_params[PARAM_CACHE];

        HD::set_last_error("xmltv_last_error", null);
        $cached_file = $this->cache_dir . DIRECTORY_SEPARATOR . $hash . ".xmltv";
        hd_debug_print("Checking cached xmltv file: $cached_file");
        if (!file_exists($cached_file)) {
            hd_debug_print("Cached xmltv file not exist");
            return 1;
        }

        $modify_time_file = filemtime($cached_file);
        hd_debug_print("Xmltv cache ($cache_ttl) last modified: " . date("Y-m-d H:i", $modify_time_file));

        $expired = true;
        if ($cache_ttl === XMLTV_CACHE_AUTO) {
            $this->curl_wrapper->set_url($url);
            if ($this->curl_wrapper->check_is_expired()) {
                $this->curl_wrapper->clear_cached_etag();
            } else {
                $expired = false;
            }
        } else if (filesize($cached_file) !== 0) {
            $max_cache_time = 3600 * 24 * $cache_ttl;
            $expired_time = $modify_time_file + $max_cache_time;
            hd_debug_print("Xmltv cache expired at: " . date("Y-m-d H:i", $expired_time), true);
            if ($modify_time_file && $expired_time > time()) {
                $expired = false;
            }
        }

        if ($expired) {
            hd_debug_print("Xmltv cache expired.");
            return 1;
        }

        hd_debug_print("Cached file: $cached_file is not expired");
        $indexed = $this->get_indexes_info();

        hd_debug_print("Indexes status: " . json_encode($indexed), true);
        // index for picons has not verified because it always exist if channels index is present
        if (isset($indexed[self::INDEX_CHANNELS], $indexed[self::INDEX_ENTRIES])
            && $indexed[self::INDEX_CHANNELS] && $indexed[self::INDEX_ENTRIES]) {
            hd_debug_print("All xmltv indexes are valid");
            return 0;
        }

        if (isset($indexed[self::INDEX_CHANNELS]) && $indexed[self::INDEX_CHANNELS]) {
            hd_debug_print("Xmltv channels index is valid");
            return 2;
        }

        hd_debug_print("All xmltv indexes are invalid");
        return 3;
    }

    /**
     * Download XMLTV source.
     *
     * @return int
     */
    public function download_xmltv_source()
    {
        $url = $this->xmltv_url_params[PARAM_URI];
        $url_hash = $this->xmltv_url_params[PARAM_HASH];
        if ($this->is_index_locked($url_hash)) {
            hd_debug_print("File is indexing or downloading, skipped");
            return 0;
        }

        hd_debug_print_separator();

        $ret = -1;
        $this->perf->reset('start');

        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
        $cached_file = $this->cache_dir . DIRECTORY_SEPARATOR . $url_hash . ".xmltv";
        $tmp_filename = $cached_file . '.tmp';
        if (file_exists($tmp_filename)) {
            unlink($tmp_filename);
        }

        try {
            HD::set_last_error("xmltv_last_error", null);
            $this->set_index_locked($url_hash, true);

            if (preg_match("/jtv.?\.zip$/", basename(urldecode($url)))) {
                throw new Exception("Unsupported EPG format (JTV)");
            }

            $this->curl_wrapper->set_url($url);
            $expired = !file_exists($cached_file) || $this->curl_wrapper->check_is_expired();
            if (!$expired) {
                hd_debug_print("File not changed, using cached file: $cached_file");
                $this->set_index_locked($url_hash, false);
                return 1;
            }

            $this->curl_wrapper->clear_cached_etag();
            if (!$this->curl_wrapper->download_file($tmp_filename, true)) {
                throw new Exception("Can't exec curl");
            }

            $http_code = $this->curl_wrapper->get_response_code();
            if ($http_code !== 200) {
                throw new Exception("Ошибка скачивания ($http_code) $url\n\n"
                    . $this->curl_wrapper->get_raw_response_headers());
            }

            $file_time = filemtime($tmp_filename);
            $dl_time = $this->perf->getReportItemCurrent(Perf_Collector::TIME);
            $file_size = filesize($tmp_filename);
            $bps = $file_size / $dl_time;
            $si_prefix = array('B/s', 'KB/s', 'MB/s');
            $base = 1024;
            $class = min((int)log($bps, $base), count($si_prefix) - 1);
            $speed = sprintf('%1.2f', $bps / pow($base, $class)) . ' ' . $si_prefix[$class];

            hd_debug_print("ETag value: " . $this->curl_wrapper->get_cached_etag());
            hd_debug_print("Last changed time of local file: " . date("Y-m-d H:i", $file_time));
            hd_debug_print("Download $file_size bytes of xmltv source $url done in: $dl_time secs (speed $speed)");

            if (file_exists($cached_file)) {
                hd_debug_print("Remove cached file: $cached_file");
                unlink($cached_file);
            }

            $this->perf->setLabel('unpack');

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
                    throw new Exception("Failed to ungzip $tmp_filename (error code: $ret)");
                }
                clearstatcache();
                $size = filesize($cached_file);
                touch($cached_file, $file_time);
                hd_debug_print("$size bytes ungzipped to $cached_file in "
                    . $this->perf->getReportItemCurrent(Perf_Collector::TIME, 'unpack') . " secs");
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
                clearstatcache();

                rename($filename, $cached_file);
                $size = filesize($cached_file);
                touch($cached_file, $file_time);
                hd_debug_print("$size bytes unzipped to $cached_file in "
                    . $this->perf->getReportItemCurrent(Perf_Collector::TIME, 'unpack') . " secs");
            } else if (false !== mb_strpos($hdr, "<?xml")) {
                hd_debug_print("XML signature: " . substr($hdr, 0, 5), true);
                hd_debug_print("rename $tmp_filename to $cached_file");
                rename($tmp_filename, $cached_file);
                $size = filesize($cached_file);
                touch($cached_file, $file_time);
                hd_debug_print("$size bytes stored to $cached_file in "
                    . $this->perf->getReportItemCurrent(Perf_Collector::TIME, 'unpack') . " secs");
            } else {
                hd_debug_print("Unknown signature: " . bin2hex($hdr), true);
                throw new Exception(TR::load_string('err_unknown_file_type'));
            }

            $ret = 1;
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            if (!empty($tmp_filename) && file_exists($tmp_filename)) {
                unlink($tmp_filename);
            }

            if (file_exists($cached_file)) {
                unlink($cached_file);
            }
        }

        $this->set_index_locked($url_hash, false);

        if ($ret === 1) {
            $this->remove_indexes(array(self::INDEX_CHANNELS, self::INDEX_PICONS, self::INDEX_ENTRIES));
        }

        hd_debug_print_separator();

        return $ret;
    }

    /**
     * Check if lock for specified cache is exist
     *
     * @param string $hash
     * @return bool
     */
    public function is_index_locked($hash)
    {
        $dirs = glob($this->cache_dir . DIRECTORY_SEPARATOR . $hash . "_*.lock", GLOB_ONLYDIR);
        return !empty($dirs);
    }

    /**
     * @param string $hash
     * @param bool $lock
     */
    public function set_index_locked($hash, $lock)
    {
        $lock_dir = $this->cache_dir . DIRECTORY_SEPARATOR . $hash . "_$this->pid.lock";
        if ($lock) {
            if (!create_path($lock_dir, 0644)) {
                hd_debug_print("Directory '$lock_dir' was not created");
            } else {
                hd_debug_print("Lock $lock_dir");
            }
        } else if (is_dir($lock_dir)) {
            hd_debug_print("Unlock $lock_dir");
            shell_exec("rm -rf $lock_dir");
            clearstatcache();
        }
    }

    /**
     * clear memory cache and cache for selected filename (hash) mask
     *
     * @param string|null $hash
     * @return void
     */
    public function clear_epg_files($hash = null)
    {
        hd_debug_print(null, true);
        if (empty($hash)) {
            $this->curl_wrapper->clear_all_etag_cache();
        } else {
            $this->curl_wrapper->clear_cached_etag();
        }
        $this->clear_memory_index($hash);

        if (empty($this->cache_dir)) {
            return;
        }

        $dirs = glob($this->cache_dir . DIRECTORY_SEPARATOR . (empty($hash) ? "*" : $hash) . "_*.lock", GLOB_ONLYDIR);
        $locks = array();
        foreach ($dirs as $dir) {
            hd_debug_print("Found locks: $dir");
            $locks[] = $dir;
        }

        if (!empty($locks)) {
            foreach ($locks as $lock) {
                $ar = explode('_', basename($lock));
                $pid = (int)end($ar);

                if ($pid !== 0 && send_process_signal($pid, 0)) {
                    hd_debug_print("Kill process $pid");
                    send_process_signal($pid, -9);
                }
                hd_debug_print("Remove lock: $lock");
                shell_exec("rm -rf $lock");
            }
        }

        $files = $this->cache_dir . DIRECTORY_SEPARATOR . (empty($hash) ? '' : $hash) ."*";
        hd_debug_print("clear epg files: $files");
        shell_exec('rm -rf ' . $files);
        clearstatcache();
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// abstract methods

    /**
     * Remove is selected index
     *
     * @param string $name
     * @return bool
     */
    abstract public function remove_index($name);

    /**
     * Remove is selected index
     *
     * @param array $names
     */
    abstract public function remove_indexes($names);

    /**
     * Get information about indexes
     * @return array
     */
    abstract public function get_indexes_info($hash = null);

    /**
     * Clear memory index
     *
     * @param string|null $id
     * @return void
     */
    abstract protected function clear_memory_index($id = null);

    /**
     * @param Channel $channel
     * @return array
     */
    abstract protected function load_program_index($channel);

    /**
     * Check is all indexes is valid
     *
     * @param array $names
     * @return bool
     */
    abstract protected function is_all_indexes_valid($names);

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param string $filename
     * @return resource
     * @throws Exception
     */
    static protected function open_xmltv_file($filename)
    {
        if (!file_exists($filename)) {
            throw new Exception("cache file $filename not exist");
        }

        $file = fopen($filename, 'rb');
        if (!$file) {
            throw new Exception("can't open $filename");
        }

        return $file;
    }
}
