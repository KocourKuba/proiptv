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

require_once 'hd.php';
require_once 'epg_params.php';
require_once 'hashed_array.php';
require_once 'tr.php';

class Epg_Manager
{
    const STREAM_CHUNK = 131072; // 128Kb

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    /**
     * Version of the used cache scheme (plugin version)
     * @var string
     */
    protected $version;

    /**
     * path where cache is stored
     * @var string
     */
    protected $cache_dir;

    /**
     * @var int
     */
    protected $cache_ttl;

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
     * contains program index for current xmltv file
     * @var array
     */
    protected $xmltv_positions;

    /**
     * contains map of channel names to channel id
     * @var array
     */
    protected $xmltv_channels;

    /**
     * contains map of channel id to picons
     * @var array
     */
    protected $xmltv_picons;

    /**
     * @var array
     */
    protected $delayed_epg = array();

    /**
     * @var int
     */
    protected $flags = 0;

    /**
     * @var string
     */
    protected $index_ext = '.index';

    /**
     * @param string $version
     * @param string $cache_dir
     * @param string $name
     * @param Default_Dune_Plugin|null $plugin
     */
    public function __construct($version, $cache_dir, $name, $plugin = null)
    {
        $this->version = $version;
        $this->xmltv_url = $name;
        $this->cache_dir = $cache_dir;
        $this->plugin = $plugin;

        create_path($this->cache_dir);

        hd_debug_print("Engine: " . get_class($this));
        hd_debug_print("Cache dir: $this->cache_dir");
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));

        $this->url_hash = (empty($this->xmltv_url) ? '' : Hashed_Array::hash($this->xmltv_url));
        hd_debug_print("XMLTV EPG url: $this->xmltv_url ($this->url_hash)");
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
     * @param int $flags
     * @return void
     */
    public function set_flags($flags)
    {
        $this->flags = $flags;
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
        } else if (is_dir($lock_dir)){
            hd_debug_print("Unlock $lock_dir");
            @rmdir($lock_dir);
        }
    }

    /**
     * Get picons
     *
     * @return array|null
     */
    public function get_picons()
    {
        if (!isset($this->xmltv_picons)
            && file_exists(get_data_path($this->url_hash . '_version'))
            && file_get_contents(get_data_path($this->url_hash . '_version')) > '1.8') {

            hd_debug_print("Load picons from: " . $this->get_picons_index_name());
            $this->xmltv_picons = HD::ReadContentFromFile($this->get_picons_index_name());
        }

        return $this->xmltv_picons;
    }

    /**
     * Try to load epg from cached file
     *
     * @param Channel $channel
     * @param int $day_start_ts
     * @return array
     */
    public function get_day_epg_items(Channel $channel, $day_start_ts)
    {
        $t = microtime(true);

        // filter out epg only for selected day
        $day_epg = array();
        $day_end_ts = $day_start_ts + 86400;
        $date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
        $date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
        hd_debug_print("Fetch entries for from: $date_start_l ($day_start_ts) to: $date_end_l ($day_end_ts)", true);

        try {
            $positions = $this->load_program_index($channel);
            if (!empty($positions)) {
                $t = microtime(true);
                $cached_file = $this->get_cached_filename();
                if (!file_exists($cached_file)) {
                    throw new Exception("cache file not exist");
                }

                $handle = fopen($cached_file, 'rb');
                if ($handle) {
                    foreach ($positions as $pos) {
                        fseek($handle, $pos['start']);

                        $xml_str = "<tv>" . fread($handle, $pos['end'] - $pos['start']) . "</tv>";

                        $xml_node = new DOMDocument();
                        $xml_node->loadXML($xml_str);
                        foreach ($xml_node->getElementsByTagName('programme') as $tag) {
                            $program_start = strtotime($tag->getAttribute('start'));
                            if ($program_start < $day_start_ts) continue;
                            if ($program_start >= $day_end_ts) break;

                            $day_epg[$program_start][Epg_Params::EPG_END] = strtotime($tag->getAttribute('stop'));

                            $day_epg[$program_start][Epg_Params::EPG_NAME] = '';
                            foreach ($tag->getElementsByTagName('title') as $tag_title) {
                                $day_epg[$program_start][Epg_Params::EPG_NAME] = $tag_title->nodeValue;
                            }

                            $day_epg[$program_start][Epg_Params::EPG_DESC] = '';
                            foreach ($tag->getElementsByTagName('desc') as $tag_desc) {
                                $day_epg[$program_start][Epg_Params::EPG_DESC] = $tag_desc->nodeValue;
                            }
                        }
                    }

                    fclose($handle);
                }
                hd_debug_print("Fetch data from XMLTV cache in: " . (microtime(true) - $t) . " secs");
            }
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
        }

        if (empty($day_epg)) {
            $lock = $this->is_index_locked();
            hd_debug_print("index is locked: " . var_export($lock, true));
            if ($lock) {
                hd_debug_print("EPG still indexing");
                $this->delayed_epg[] = $channel->get_id();
                return array($day_start_ts => array(
                    Epg_Params::EPG_END => $day_start_ts + 86400,
                    Epg_Params::EPG_NAME => TR::load_string('epg_not_ready'),
                    Epg_Params::EPG_DESC => TR::load_string('epg_not_ready_desc'),
                ));
            }

            return $this->getFakeEpg($channel, $day_start_ts, $day_epg);
        }

        hd_debug_print("Total EPG entries loaded: " . count($day_epg));
        ksort($day_epg);
        hd_debug_print("Entries collected in: " . (microtime(true) - $t) . " secs");

        return $day_epg;
    }


    /**
     * @param Channel $channel
     * @param $day_start_ts
     * @param array $day_epg
     * @return array
     */
    protected function getFakeEpg(Channel $channel, $day_start_ts, array $day_epg)
    {
        if (($this->flags & EPG_FAKE_EPG) && $channel->get_archive() !== 0) {
            hd_debug_print("Create fake data for non existing EPG data");
            for ($start = $day_start_ts, $n = 1; $start <= $day_start_ts + 86400; $start += 3600, $n++) {
                $day_epg[$start][Epg_Params::EPG_END] = $start + 3600;
                $day_epg[$start][Epg_Params::EPG_NAME] = TR::load_string('fake_epg_program') . " $n";
                $day_epg[$start][Epg_Params::EPG_DESC] = '';
            }
        } else {
            hd_debug_print("No EPG for channel: {$channel->get_id()}");
        }

        return $day_epg;
    }

    /**
     * Checks if xmltv source cached and not expired.
     * if xmltv url not set return -1 and set_last_error contains error message
     * if downloaded xmltv file exists and all indexes are present return 0
     * if downloaded xmltv file not exists or expired return 1
     * if downloaded xmltv file exists, not expired but indexes not exists return 2
     *
     * @return int
     */
    public function is_xmltv_cache_valid()
    {
        hd_debug_print();

        if (empty($this->xmltv_url)) {
            $msg = "XMTLV EPG url not set";
            hd_debug_print("XMTLV EPG url not set");
            HD::set_last_error("xmltv_last_error", $msg);
            return -1;
        }

        HD::set_last_error("xmltv_last_error", null);
        $cached_xmltv_file = $this->get_cached_filename();
        hd_debug_print("Checking cached xmltv file: $cached_xmltv_file");
        $channels_index = $this->get_channels_index_name();
        $epg_index = $this->get_positions_index_name();
        $picons_index = $this->get_picons_index_name();

        if (file_exists($cached_xmltv_file)) {
            $check_time_file = filemtime($cached_xmltv_file);
            $max_cache_time = 3600 * 24 * $this->cache_ttl;
            if ($check_time_file && $check_time_file + $max_cache_time > time()) {
                hd_debug_print("Cached file: $cached_xmltv_file is not expired "
                    . date("Y-m-d H:i", $check_time_file)
                    . " date expiration: " . date("Y-m-d H:i", $check_time_file + $max_cache_time));
                return (file_exists($epg_index) && file_exists($channels_index)) ? 0 : 2;
            }

            hd_debug_print("clear cached file: $cached_xmltv_file");
            unlink($cached_xmltv_file);
        } else {
            hd_debug_print("Cached xmltv file not exist");
        }

        if (file_exists($channels_index)) {
            hd_debug_print("clear cached channels index: $channels_index");
            unlink($channels_index);
        }

        if (file_exists($epg_index)) {
            hd_debug_print("clear cached epg index: $epg_index");
            unlink($epg_index);
        }

        if (file_exists($picons_index)) {
            hd_debug_print("clear cached picons index: $picons_index");
            unlink($picons_index);
        }

        return 1;
    }

    /**
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

        try {
            HD::set_last_error("xmltv_last_error", null);
            $this->set_index_locked(true);

            if (preg_match("/jtv.?\.zip$/", basename($this->xmltv_url))) {
                throw new Exception("Unsupported EPG format (JTV)");
            }

            hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
            $cached_xmltv_file = $this->get_cached_filename();
            $tmp_filename = $cached_xmltv_file . '.tmp';
            if (file_exists($tmp_filename)) {
                unlink($tmp_filename);
            }

            if (HD::http_save_https_proxy($this->xmltv_url, $tmp_filename) === false) {
                throw new Exception("Failed to download $this->xmltv_url");
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

            $t = microtime(true);

            $handle = fopen($tmp_filename, "rb");
            $hdr = fread($handle, 8);
            fclose($handle);

            if (0 === mb_strpos($hdr , "\x1f\x8b\x08")) {
                hd_debug_print("GZ signature: " . bin2hex(substr($hdr, 0, 3)), true);
                rename($tmp_filename, $cached_xmltv_file . '.gz');
                $tmp_filename = $cached_xmltv_file . '.gz';
                hd_debug_print("ungzip $tmp_filename to $cached_xmltv_file");
                $cmd = "gzip -d $tmp_filename 2>&1";
                system($cmd, $ret);
                if ($ret !== 0) {
                    throw new Exception("Failed to unpack $tmp_filename (error code: $ret)");
                }
                $size = filesize($cached_xmltv_file);
                touch($cached_xmltv_file, $file_time);
                hd_debug_print("$size bytes ungzipped to $cached_xmltv_file in " . (microtime(true) - $t) . " secs");
            } else if (0 === mb_strpos($hdr, "\x50\x4b\x03\x04")) {
                hd_debug_print("ZIP signature: " . bin2hex(substr($hdr, 0, 4)), true);
                hd_debug_print("unzip $tmp_filename to $cached_xmltv_file");
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

                rename($filename, $cached_xmltv_file);
                $size = filesize($cached_xmltv_file);
                touch($cached_xmltv_file, $file_time);
                hd_debug_print("$size bytes unzipped to $cached_xmltv_file in " . (microtime(true) - $t) . " secs");
            } else if (false !== mb_strpos($hdr, "<?xml")) {
                hd_debug_print("XML signature: " . substr($hdr, 0, 5), true);
                hd_debug_print("rename $tmp_filename to $cached_xmltv_file");
                if (file_exists($cached_xmltv_file)) {
                    unlink($cached_xmltv_file);
                }
                rename($tmp_filename, $cached_xmltv_file);
                $size = filesize($cached_xmltv_file);
                touch($cached_xmltv_file, $file_time);
                hd_debug_print("$size bytes stored to $cached_xmltv_file in " . (microtime(true) - $t) . " secs");
            } else {
                hd_debug_print("Unknown signature: " . bin2hex($hdr), true);
                throw new Exception(TR::load_string('err_unknown_file_type'));
            }

            $ret = 1;
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
            if (!empty($tmp_filename) && file_exists($tmp_filename)) {
                unlink($tmp_filename);
            }
        }

        $this->set_index_locked(false);

        hd_debug_print_separator();

        return $ret;
    }

    /**
     * indexing xmltv file to make channel to display-name map
     * and collect picons for channels
     *
     * @return void
     */
    public function index_xmltv_channels()
    {
        $channels_file = $this->get_channels_index_name();
        $version_file = $this->get_cache_stem('_version');
        if (file_exists($channels_file) && file_exists($version_file)
            && file_get_contents($version_file) > '2.1') {

            hd_debug_print("Load cache channels index: $channels_file");
            $this->xmltv_channels = HD::ReadContentFromFile($channels_file);
            return;
        }

        $this->xmltv_channels = array();
        $this->xmltv_picons = array();
        $t = microtime(true);

        try {
            $this->set_index_locked(true);

            hd_debug_print("Start reindex: $channels_file");

            $file = $this->open_xmltv_file();
            while (!feof($file)) {
                $line = stream_get_line($file, self::STREAM_CHUNK, "<channel ");
                if (empty($line)) continue;

                fseek($file, -9, SEEK_CUR);
                $str = fread($file, 9);
                if ($str !== "<channel ") continue;

                $line = stream_get_line($file, self::STREAM_CHUNK, "</channel>");
                if (empty($line)) continue;

                $line = "<channel $line</channel>";

                $xml_node = new DOMDocument();
                $xml_node->loadXML($line);
                foreach($xml_node->getElementsByTagName('channel') as $tag) {
                    $channel_id = $tag->getAttribute('id');
                }

                if (empty($channel_id)) continue;

                $picon = '';
                foreach ($xml_node->getElementsByTagName('icon') as $tag) {
                    if (preg_match(HTTP_PATTERN, $tag->getAttribute('src'))) {
                        $picon = $tag->getAttribute('src');
                        break;
                    }
                }

                $this->xmltv_channels[$channel_id] = $channel_id;
                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $this->xmltv_channels[$tag->nodeValue] = $channel_id;
                    if (!empty($picon)) {
                        $this->xmltv_picons[$tag->nodeValue] = $picon;
                    }
                }
            }
            fclose($file);

            HD::StoreContentToFile($this->get_picons_index_name(), $this->xmltv_picons);
            HD::StoreContentToFile($channels_file, $this->xmltv_channels);
            file_put_contents($version_file, $this->version);
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
        }

        $this->set_index_locked(false);

        hd_debug_print("Total channels id's: " . count($this->xmltv_channels));
        hd_debug_print("Total picons: " . count($this->xmltv_picons));
        hd_debug_print("Reindexing EPG channels done: " . (microtime(true) - $t) . " secs");
        hd_debug_print_separator();

        HD::ShowMemoryUsage();
    }

    /**
     * indexing xmltv epg info
     *
     * @return void
     */
    public function index_xmltv_positions()
    {
        if ($this->is_index_locked()) {
            hd_debug_print("File is indexing now, skipped");
            return;
        }

        $cache_valid = false;
        $index_program = $this->get_positions_index_name();
        if (file_exists($index_program)) {
            hd_debug_print("Load cache program index: $index_program");
            $this->xmltv_positions = HD::ReadContentFromFile($index_program);
            if ($this->xmltv_positions !== false) {
                $cache_valid = true;
            }
        }

        if ($cache_valid) {
            return;
        }

        try {
            $this->set_index_locked(true);

            hd_debug_print("Start reindex: $index_program");

            $t = microtime(true);

            $file = $this->open_xmltv_file();

            $start_program_block = 0;
            $prev_channel = null;
            $xmltv_index = array();
            while (!feof($file)) {
                $tag_start_pos = ftell($file);
                $line = stream_get_line($file, self::STREAM_CHUNK, "</programme>");
                if ($line === false) break;

                $offset = strpos($line, '<programme');
                if ($offset === false) {
                    // check if end
                    $end_tv = strpos($line, "</tv>");
                    if ($end_tv !== false) {
                        $tag_end_pos = $end_tv + $tag_start_pos;
                        $xmltv_index[$prev_channel][] = array('start' => $start_program_block, 'end' => $tag_end_pos);
                        break;
                    }

                    // if open tag not found - skip chunk
                    continue;
                }

                // append position of open tag to file position of chunk
                $tag_start_pos += $offset;
                // calculate channel id
                $ch_start = strpos($line, 'channel="', $offset);
                if ($ch_start === false) {
                    continue;
                }

                $ch_start += 9;
                $ch_end = strpos($line, '"', $ch_start);
                if ($ch_end === false) {
                    continue;
                }

                $channel_id = substr($line, $ch_start, $ch_end - $ch_start);
                if (empty($channel_id)) continue;

                if ($prev_channel === null) {
                    // first entrance. Need to remember channel id
                    $prev_channel = $channel_id;
                    $start_program_block = $tag_start_pos;
                } else if ($prev_channel !== $channel_id) {
                    // next channel. need to remember start programs block for channel
                    $xmltv_index[$prev_channel][] = array('start' => $start_program_block, 'end' => $tag_start_pos);
                    $prev_channel = $channel_id;
                    $start_program_block = $tag_start_pos;
                }
            }

            if (!empty($xmltv_index)) {
                hd_debug_print("Save index: $index_program", true);
                HD::StoreContentToFile($this->get_positions_index_name(), $xmltv_index);
                $this->xmltv_positions = $xmltv_index;
            }

            hd_debug_print("Total unique epg id's indexed: " . count($xmltv_index));
            hd_debug_print("Reindexing EPG program done: " . (microtime(true) - $t) . " secs");
            hd_debug_print_separator();
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
        }

        $this->set_index_locked(false);

        HD::ShowMemoryUsage();
        hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @param null $url
     * @return void
     */
    public function clear_epg_cache($url = null)
    {
        $this->clear_epg_files(is_null($url) ? $this->url_hash : $url);
    }

    /**
     * clear memory cache and entire cache folder
     *
     * @return void
     */
    public function clear_all_epg_cache()
    {
        $this->clear_epg_files('');
    }

    /**
     * clear indexes
     *
     * @return void
     */
    public function clear_epg_cache_indexes()
    {
        $this->clear_epg_files("*.$this->index_ext");
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    public function clear_epg_files($filename)
    {
        $this->clear_index();

        if (empty($this->cache_dir)) {
            return;
        }

        $files = $this->cache_dir . DIRECTORY_SEPARATOR . "$filename*";
        hd_debug_print("clear cache files: $files");
        shell_exec('rm -f '. $files);
        flush();
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
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
     * @return string
     */
    protected function get_cached_filename()
    {
        return $this->get_cache_stem(".xmltv");
    }

    /**
     * @return string|null
     */
    protected function get_positions_index_name()
    {
        return $this->get_cache_stem("_positions$this->index_ext");
    }

    /**
     * @return string|null
     */
    protected function get_channels_index_name()
    {
        return $this->get_cache_stem("_channels$this->index_ext");
    }

    /**
     * @return string
     */
    protected function get_picons_index_name()
    {
        return $this->get_cache_stem("_picons$this->index_ext");
    }

    /**
     * @return resource
     * @throws Exception
     */
    protected function open_xmltv_file()
    {
        $cached_file = $this->get_cached_filename();
        if (!file_exists($cached_file)) {
            throw new Exception("cache file not exist");
        }

        $file = fopen($cached_file, 'rb');
        if (!$file) {
            throw new Exception("can't open $cached_file");
        }

        return $file;
    }

    /**
     * @return void
     */
    protected function clear_index()
    {
        hd_debug_print("clear legacy index");

        $this->xmltv_picons = null;
        $this->xmltv_channels = null;
        $this->xmltv_positions = null;
    }

    /**
     * @return array
     */
    public function get_delayed_epg()
    {
        return $this->delayed_epg;
    }

    /**
     */
    public function clear_delayed_epg()
    {
        $this->delayed_epg = array();
    }

    /**
     * @param Channel $channel
     * @return array
     */
    protected function load_program_index($channel)
    {
        try {
            $t = microtime(true);
            if (empty($this->xmltv_positions)) {
                $index_file = $this->get_positions_index_name();
                hd_debug_print("load positions index $$index_file");
                $data = HD::ReadContentFromFile($index_file);
                if (empty($data)) {
                    throw new Exception("load positions index failed '$index_file'");
                }
                $this->xmltv_positions = $data;
                HD::ShowMemoryUsage();
            }

            if (empty($this->xmltv_channels)) {
                $index_file = $this->get_channels_index_name();
                hd_debug_print("load channels index $$index_file");
                $this->xmltv_channels = HD::ReadContentFromFile($index_file);
                if (empty($this->xmltv_channels)) {
                    $this->xmltv_channels = null;
                    throw new Exception("load channels index failed '$index_file'");
                }
            }

            // try found channel_id by epg_id
            $epg_ids = $channel->get_epg_ids();
            foreach ($epg_ids as $epg_id) {
                if (isset($this->xmltv_channels[$epg_id])) {
                    $channel_id = $this->xmltv_channels[$epg_id];
                    break;
                }
            }

            if (empty($channel_id)) {
                throw new Exception("index positions for epg '{$channel->get_title()}' is not exist");
            }

            if (!isset($this->xmltv_positions[$channel_id])) {
                throw new Exception("index positions for epg $channel_id is not exist");
            }

            hd_debug_print("Fetch positions "
                . count($this->xmltv_positions[$channel_id])
                . " for '$channel_id' by channel: '{$channel->get_title()}' ({$channel->get_id()}) done in: "
                . (microtime(true) - $t) . " secs");

            return $this->xmltv_positions[$channel_id];
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
        }

        return array();
    }

    public function import_indexing_log()
    {
        $index_log = $this->get_cache_stem('.log');
        if (file_exists($index_log)) {
            hd_debug_print("Read epg indexing log $index_log...");
            $logfile = @file_get_contents($index_log);
            foreach (explode(PHP_EOL, $logfile) as $l) {
                hd_print(preg_replace("|^\[.+\]\s(.*)$|", "$1", rtrim($l)));
            }
            hd_debug_print("Read finished");
            unlink($index_log);
        }
    }
}
