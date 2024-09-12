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
     * @var Hashed_Array
     */
    protected $active_sources;

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
        $this->active_sources = new Hashed_Array();
    }

    /**
     * @param string $cache_dir
     */
    public function init($cache_dir)
    {
        $this->cache_dir = $cache_dir;
        create_path($this->cache_dir);

        hd_debug_print("Indexer engine: " . get_class($this));
        hd_debug_print("Cache dir: $this->cache_dir");
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * @param int $pid
     * @return void
     */
    public function set_pid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * @return int
     */
    public function get_pid()
    {
        return $this->pid;
    }

    /**
     * @param string $url
     * @return void
     */
    public function set_url($url)
    {
        $this->xmltv_url = $url;
        $this->url_hash = Hashed_Array::hash($this->xmltv_url);
    }

    /**
     * @param Hashed_Array $urls
     * @return void
     */
    public function set_active_sources($urls)
    {
        $this->active_sources = $urls;
        if ($this->active_sources->size() === 0) {
            hd_debug_print("No XMLTV source selected");
        } else {
            hd_debug_print("XMLTV sources selected: $this->active_sources");
        }
    }

    /**
     * @return Hashed_Array
     */
    public function get_active_sources()
    {
        return $this->active_sources;
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
     * @return string
     */
    public function get_cache_dir()
    {
        return $this->cache_dir;
    }

    /**
     * Indexing xmltv file to make channel to display-name map
     * This function called from script only and plugin not available in this call
     * Parsing channels is cheap for all Dune variants
     *
     * @return void
     */
    public function index_all_channels()
    {
        foreach ($this->active_sources as $source) {
            $this->set_url($source);
            $this->index_only_channels();
        }
    }

    /**
     * Indexing xmltv file to make channel to display-name map and collect picons for channels.
     * This function called from script only and plugin not available in this call
     *
     * @return void
     */
    public function index_all()
    {
        $this->index_all_channels();
        foreach ($this->active_sources as $source) {
            $this->set_url($source);
            $this->index_xmltv_positions();
        }
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
        hd_debug_print("Indexing channels for: $this->xmltv_url", true);
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
        $indexed = $this->get_indexes_info();

        if (isset($indexed[self::INDEX_CHANNELS], $indexed[self::INDEX_PICONS], $indexed[self::INDEX_ENTRIES])
            && $indexed[self::INDEX_CHANNELS] && $indexed[self::INDEX_PICONS] && $indexed[self::INDEX_ENTRIES]) {
            hd_debug_print("Xmltv cache valid");
            return 0;
        }

        if (isset($indexed[self::INDEX_CHANNELS], $indexed[self::INDEX_PICONS])
            && $indexed[self::INDEX_CHANNELS] && $indexed[self::INDEX_PICONS]) {
            hd_debug_print("Xmltv cache channels and picons are valid");
            return 2;
        }

        hd_debug_print("Xmltv cache indexes are invalid");
        return 3;
    }

    /**
     * @param string|null $hash
     * @return string
     */
    public function get_cached_filename($hash = null)
    {
        return $this->get_cache_stem(".xmltv", $hash);
    }

    /**
     * @param string $ext
     * @param string|null $hash
     * @return string
     */
    public function get_cache_stem($ext, $hash = null)
    {
        return $this->cache_dir . DIRECTORY_SEPARATOR . (is_null($hash) ? $this->url_hash : $hash) . $ext;
    }

    /**
     * Download XMLTV source.
     *
     * @return int
     */
    public function download_xmltv_source()
    {
        if ($this->is_current_index_locked()) {
            hd_debug_print("File is indexing or downloading, skipped");
            return 0;
        }

        hd_debug_print_separator();

        $ret = -1;
        $this->perf->reset('start');

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
            $dl_time = $this->perf->getReportItemCurrent(Perf_Collector::TIME);
            $file_size = filesize($tmp_filename);
            $bps = $file_size / $dl_time;
            $si_prefix = array('B/s', 'KB/s', 'MB/s');
            $base = 1024;
            $class = min((int)log($bps, $base), count($si_prefix) - 1);
            $speed = sprintf('%1.2f', $bps / pow($base, $class)) . ' ' . $si_prefix[$class];

            hd_debug_print("Last changed time of local file: " . date("Y-m-d H:i", $file_time));
            hd_debug_print("Download $file_size bytes of xmltv source $this->xmltv_url done in: $dl_time secs (speed $speed)");

            if (file_exists($cached_file)) {
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
                    throw new Exception("Failed to unpack $tmp_filename (error code: $ret)");
                }
                clearstatcache();
                $size = filesize($cached_file);
                touch($cached_file, $file_time);
                hd_debug_print("$size bytes ungzipped to $cached_file in " . $this->perf->getReportItemCurrent(Perf_Collector::TIME, 'unpack') . " secs");
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
                hd_debug_print("$size bytes unzipped to $cached_file in " . $this->perf->getReportItemCurrent(Perf_Collector::TIME, 'unpack') . " secs");
            } else if (false !== mb_strpos($hdr, "<?xml")) {
                hd_debug_print("XML signature: " . substr($hdr, 0, 5), true);
                hd_debug_print("rename $tmp_filename to $cached_file");
                rename($tmp_filename, $cached_file);
                $size = filesize($cached_file);
                touch($cached_file, $file_time);
                hd_debug_print("$size bytes stored to $cached_file in " . $this->perf->getReportItemCurrent(Perf_Collector::TIME, 'unpack') . " secs");
            } else {
                hd_debug_print("Unknown signature: " . bin2hex($hdr), true);
                throw new Exception(TR::load_string('err_unknown_file_type'));
            }

            $ret = 1;
            $this->remove_indexes(array(self::INDEX_CHANNELS, self::INDEX_PICONS, self::INDEX_ENTRIES));
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
     * @param string $hash
     * @return bool
     */
    public function is_index_locked($hash)
    {
        $dirs = glob($this->cache_dir . DIRECTORY_SEPARATOR . $hash . "_*.lock", GLOB_ONLYDIR);
        return !empty($dirs);
    }

    /**
     * @return bool|array
     */
    public function is_any_index_locked()
    {
        $locks = array();
        $dirs = array();
        if ($this->active_sources->size() === 0) {
            $dirs = glob($this->cache_dir . DIRECTORY_SEPARATOR . "*_*.lock", GLOB_ONLYDIR);
        } else {
            foreach ($this->active_sources as $key => $value) {
                $dirs = safe_merge_array($dirs, glob($this->cache_dir . DIRECTORY_SEPARATOR . $key . "_*.lock", GLOB_ONLYDIR));
            }
        }

        foreach ($dirs as $dir) {
            $locks[] = basename($dir);
        }
        return empty($locks) ? false : $locks;
    }

    /**
     * @param bool $lock
     */
    public function set_index_locked($lock)
    {
        $lock_dir = $this->get_cache_stem("_$this->pid.lock");
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
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    public function clear_current_epg_files()
    {
        hd_debug_print(null, true);
        $this->clear_epg_files($this->url_hash);
    }

    /**
     * clear memory cache and cache for selected filename (hash) mask
     *
     * @param string $hash
     * @return void
     */
    public function clear_epg_files($hash)
    {
        hd_debug_print(null, true);
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

        $files = $this->cache_dir . DIRECTORY_SEPARATOR . "$hash*";
        hd_debug_print("clear epg files: $files");
        shell_exec('rm -rf ' . $files);
        clearstatcache();
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    public function clear_stalled_locks()
    {
        $locks = $this->is_any_index_locked();
        if ($locks !== false) {
            foreach ($locks as $lock) {
                $ar = explode('_', $lock);
                $pid = (int)end($ar);

                if ($pid !== 0 && !send_process_signal($pid, 0)) {
                    hd_debug_print("Remove stalled lock: $lock");
                    shell_exec("rmdir $this->cache_dir" . DIRECTORY_SEPARATOR . $lock);
                }
            }
        }
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
     * @param string|null $hash
     * @return array
     */
    abstract public function get_indexes_info($hash = null);

    /**
     * Clear memory index
     *
     * @param string $id
     * @return void
     */
    abstract protected function clear_memory_index($id = '');

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
     * @return bool
     */
    protected function is_current_index_locked()
    {
        $lock_dir = $this->get_cache_stem('.lock');
        return is_dir($lock_dir);
    }

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
