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

class Epg_Manager
{
    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

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
     * contains memory epg cache
     * @var array
     */
    protected $epg_cache = array();

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
     * contains parsed epg for channel
     * @var array
     */
    public $xmltv_data;

    /**
     * contains index for current xmltv file
     * @var array
     */
    public $xmltv_index;

    /**
     * contains map of channel names to channel id
     * @var array
     */
    public $xmltv_channels;

    /**
     * contains map of channel id to picons
     * @var array
     */
    public $xmltv_picons;

    /**
     * @var array
     */
    public $delayed_epg = array();

    /**
     * @param string $cache_dir
     * @param int $cache_ttl
     * @return void
     */
    public function init_cache_dir($cache_dir, $cache_ttl)
    {
        $this->cache_dir = $cache_dir;
        $this->cache_ttl = $cache_ttl;
        create_path($this->cache_dir);
        hd_debug_print("cache dir: $this->cache_dir");
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * Set url/filepath to xmltv source
     *
     * @param $xmltv_url string
     */
    public function set_xmltv_url($xmltv_url)
    {
        if ($this->xmltv_url !== $xmltv_url) {
            $this->xmltv_url = $xmltv_url;
            hd_debug_print("xmltv url $this->xmltv_url");
            $this->url_hash = (empty($this->xmltv_url) ? '' : Hashed_Array::hash($this->xmltv_url));
            // reset memory cache and data
            $this->xmltv_picons = null;
            $this->xmltv_channels = null;
            unset($this->epg_cache, $this->xmltv_data, $this->xmltv_index);
            $this->epg_cache = array();
        }
    }

    /**
     * Get picon associated to epg id
     *
     * @param array $epg_ids list of epg id's
     * @return string|null
     */
    public function get_picon($epg_ids)
    {
        try {
            $epg_id = $this->get_channel_epg_id($epg_ids);
        } catch (Exception $e) {
            hd_debug_print($e->getMessage());
            return null;
        }

        if (!isset($this->xmltv_picons)) {
            hd_debug_print("Load picons from: " . $this->get_picons_index_name());
            $this->xmltv_picons = HD::ReadContentFromFile($this->get_picons_index_name());
        }

        return isset($this->xmltv_picons[$epg_id]) ? $this->xmltv_picons[$epg_id] : null;
    }

    /**
     * Try to load epg from cached file and store to memory cache
     *
     * @param Channel $channel
     * @param int $day_start_ts
     * @return array|false
     */
    public function get_day_epg_items(Channel $channel, $day_start_ts)
    {
        try {
            $epg_ids = $channel->get_epg_ids();
            if (empty($epg_ids)) {
                throw new Exception("EPG ID for channel {$channel->get_id()} ({$channel->get_title()}) not defined");
            }

            $epg_id = $this->get_channel_epg_id($epg_ids);

            if (isset($this->epg_cache[$epg_id][$day_start_ts])) {
                hd_debug_print("Load day EPG ID $epg_id ($day_start_ts) from memory cache");
                return $this->epg_cache[$epg_id][$day_start_ts];
            }

            hd_debug_print("Try to load EPG ID: '$epg_id' for channel '{$channel->get_id()}' ({$channel->get_title()})");
            if ($this->get_epg_data($epg_id, $program_epg) === false) {
                hd_debug_print("EPG still indexing");
                $this->delayed_epg[] = $channel->get_id();
                $day_epg[$day_start_ts][Epg_Params::EPG_END] = $day_start_ts + 86400;
                $day_epg[$day_start_ts][Epg_Params::EPG_NAME] = TR::load_string('epg_not_ready');
                $day_epg[$day_start_ts][Epg_Params::EPG_DESC] = TR::load_string('epg_not_ready_desc');
                return $day_epg;
            }

            $counts = count($program_epg);
            if ($counts === 0) {
                throw new Exception("Empty or no data for EPG ID: $epg_id");
            }

            hd_debug_print("Total $counts EPG entries loaded");

            // filter out epg only for selected day
            $day_end_ts = $day_start_ts + 86400;

            $date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
            $date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
            hd_debug_print("Fetch entries for from: $date_start_l to: $date_end_l", true);

            $day_epg = array();
            foreach ($program_epg as $time_start => $entry) {
                if ($time_start >= $day_start_ts && $time_start < $day_end_ts) {
                    $day_epg[$time_start] = $entry;
                }
            }

            if (empty($day_epg)) {
                throw new Exception("No EPG data for " . $channel->get_id());
            }

            hd_debug_print("Store day epg to memory cache", true);
            $this->epg_cache[$epg_id][$day_start_ts] = $day_epg;

            return $day_epg;

        } catch (Exception $ex) {
            hd_debug_print("Can't fetch EPG from source $this->xmltv_url : " . $ex->getMessage());
        }

        return false;
    }

    /**
     * Checks if xmltv source cached and not expired.
     * If not, try to download it and unpack if necessary
     *
     * @return string if downloaded xmltv file exists it empty, otherwise contain error message
     */
    public function is_xmltv_cache_valid()
    {
        try {
            if (empty($this->xmltv_url)) {
                throw new Exception("XMTLV EPG url not set");
            }

            $cached_xmltv_file = $this->get_cached_filename();
            hd_debug_print("Checking cached xmltv file: $cached_xmltv_file");
            if (!file_exists($cached_xmltv_file)) {
                hd_debug_print("Cached xmltv file not exist");
            } else {
                $check_time_file = filemtime($cached_xmltv_file);
                $max_cache_time = 3600 * 24 * $this->cache_ttl;
                if ($check_time_file && $check_time_file + $max_cache_time > time()) {
                    hd_debug_print("Cached file: $cached_xmltv_file is not expired " . date("Y-m-d H:s", $check_time_file));
                    return '';
                }

                hd_debug_print("clear cached file: $cached_xmltv_file");
                unlink($cached_xmltv_file);
            }

            $this->set_index_locked(true);

            hd_debug_print("Storage space in cache dir: " . HD::get_storage_size(dirname($cached_xmltv_file)));
            $tmp_filename = $cached_xmltv_file . '.tmp';
            $info = HD::http_save_document($this->xmltv_url, $tmp_filename);
            hd_debug_print("Fetched info: " . raw_json_encode($info));

            if (!file_exists($tmp_filename)) {
                throw new Exception("Failed to save $this->xmltv_url to $tmp_filename");
            }

            $downloaded_size = filesize($tmp_filename);
            if (isset($info['size_download']) && (int)$info['size_download'] !== (int)$downloaded_size) {
                throw new Exception("Declared file size {$info['size_download']} and downloaded $downloaded_size not match");
            }

            if (!isset($info['filetime'])) {
                hd_debug_print("Server returns wrong timestamp, bad configured server of something not good");
                $last_mod_file = time();
            } else {
                $last_mod_file = $info['filetime'];
            }

            hd_debug_print("Last changed time on server: " . date("Y-m-d H:s", $last_mod_file));

            if (preg_match("/\.(xml.gz|xml|xmltv|gz|zip)(?:\??.*)?$/i", $this->xmltv_url, $m)) {
                hd_debug_print("received file extension: " . raw_json_encode($m[1]));
                if (strcasecmp($m[1], 'gz') === 0 || strcasecmp($m[1], 'xml.gz') === 0) {
                    hd_debug_print("unpack $tmp_filename to $cached_xmltv_file");
                    $gz = gzopen($tmp_filename, 'rb');
                    if (!$gz) {
                        throw new Exception("Failed to open $tmp_filename for $this->xmltv_url");
                    }

                    $dest = fopen($cached_xmltv_file, 'wb');
                    if (!$dest) {
                        throw new Exception("Failed to open $cached_xmltv_file for $this->xmltv_url");
                    }

                    $res = stream_copy_to_stream($gz, $dest);
                    gzclose($gz);
                    fclose($dest);
                    unlink($tmp_filename);
                    if ($res === false) {
                        throw new Exception("Failed to unpack $tmp_filename to $cached_xmltv_file");
                    }
                    hd_debug_print("$res bytes written to $cached_xmltv_file");
                } else if (strcasecmp($m[1], 'zip') === 0) {
                    hd_debug_print("unzip $tmp_filename to $cached_xmltv_file");
                    $unzip = new ZipArchive();
                    $out = $unzip->open($tmp_filename);
                    if ($out !== true) {
                        throw new Exception(TR::t('err_unzip__2', $tmp_filename, $out));
                    }
                    $filename = $unzip->getNameIndex(0);
                    if (empty($filename)) {
                        $unzip->close();
                        throw new Exception(TR::t('err_empty_zip__1', $tmp_filename));
                    }

                    $unzip->extractTo($this->cache_dir);
                    $unzip->close();

                    rename($filename, $cached_xmltv_file);
                    $size = filesize($cached_xmltv_file);
                    hd_debug_print("$size bytes written to $cached_xmltv_file");
                } else {
                    hd_debug_print("rename $tmp_filename to $cached_xmltv_file");
                    if (file_exists($cached_xmltv_file)) {
                        unlink($cached_xmltv_file);
                    }
                    rename($tmp_filename, $cached_xmltv_file);
                }
            }

            $epg_index = $this->get_epg_index_name();
            if (file_exists($epg_index)) {
                hd_debug_print("clear cached epg index: $epg_index");
                unlink($epg_index);
            }

            $channels_index = $this->get_channels_index_name();
            if (file_exists($channels_index)) {
                hd_debug_print("clear cached channels index: $channels_index");
                unlink($channels_index);
            }

            $picons_index = $this->get_picons_index_name();
            if (file_exists($picons_index)) {
                hd_debug_print("clear cached picons index: $picons_index");
                unlink($picons_index);
            }
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
            if (!empty($tmp_filename) && file_exists($tmp_filename)) {
                unlink($tmp_filename);
            }
            $this->set_index_locked(false);
            return $ex->getMessage();
        }

        $this->set_index_locked(false);
        return '';
    }

    /**
     * indexing xmltv file to make channel to display-name map
     * and collect picons for channels
     *
     * @return void
     */
    public function index_xmltv_channels()
    {
        $res = $this->is_xmltv_cache_valid();
        if (!empty($res)) {
            hd_debug_print("Error load xmltv: $res");
            HD::set_last_error($res);
            return;
        }

        if ($this->is_index_locked()) {
            hd_debug_print("File is indexing now, skipped");
            return;
        }

        $channels_file = $this->get_channels_index_name();
        if (file_exists($channels_file)) {
            hd_debug_print("Load cache channels index: $channels_file");
            $this->xmltv_channels = HD::ReadContentFromFile($channels_file);
            return;
        }

        $channels_map = array();
        $picons_map = array();
        $t = microtime(1);

        try {
            $this->set_index_locked(true);

            hd_debug_print("Start reindex: $channels_file");

            $file_object = $this->open_xmltv_file();
            while (!$file_object->eof()) {
                $xml_str = $file_object->fgets();

                // stop parse channels mapping
                if (strpos($xml_str, "<programme") !== false) {
                    break;
                }

                if (strpos($xml_str, "<channel") === false) {
                    continue;
                }

                if (strpos($xml_str, "</channel") === false) {
                    while (!$file_object->eof()) {
                        $line = $file_object->fgets();
                        $xml_str .= $line . PHP_EOL;
                        if (strpos($line, "</channel") !== false) {
                            break;
                        }
                    }
                }

                $xml_node = new DOMDocument();
                $xml_node->loadXML($xml_str);
                foreach($xml_node->getElementsByTagName('channel') as $tag) {
                    $channel_id = $tag->getAttribute('id');
                }
                if (empty($channel_id)) continue;

                $channels_map[$channel_id] = $channel_id;
                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $channels_map[$tag->nodeValue] = $channel_id;
                }

                foreach ($xml_node->getElementsByTagName('icon') as $tag) {
                    $picon = $tag->getAttribute('src');
                    hd_debug_print("channel id: $channel_id picon: $picon", true);
                    if (preg_match("|https?://|", $picon)) {
                        $picons_map[$channel_id] = $picon;
                    }
                }
            }

            HD::StoreContentToFile($this->get_picons_index_name(), $picons_map);
            HD::StoreContentToFile($channels_file, $channels_map);
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
        }

        $this->set_index_locked(false);

        hd_debug_print("Reindexing EPG channels done: " . (microtime(1) - $t) . " secs");
        hd_debug_print("Total channels id's: " . count($channels_map));
        hd_debug_print("Total picons: " . count($picons_map));
        HD::ShowMemoryUsage();
    }

    /**
     * indexing xmltv epg info
     *
     * @return bool
     */
    public function index_xmltv_program()
    {
        $res = $this->is_xmltv_cache_valid();
        if (!empty($res)) {
            hd_debug_print("Error load xmltv: $res");
            HD::set_last_error($res);
            return false;
        }

        if ($this->is_index_locked()) {
            hd_debug_print("File is indexing now, skipped");
            return false;
        }

        $index_program = $this->get_epg_index_name();
        if (file_exists($index_program)) {
            hd_debug_print("Load cache program index: $index_program");
            $this->xmltv_index = HD::ReadContentFromFile($index_program);
            return false;
        }

        try {
            $this->set_index_locked(true);

            hd_debug_print("Start reindex: $index_program");

            $t = microtime(1);

            $file_object = $this->open_xmltv_file();

            $prev_channel = null;
            $xmltv_index = array();
            $xmltv_data = array();
            while (!$file_object->eof()) {
                $pos = $file_object->ftell();
                $line = $file_object->fgets();
                if (strpos($line, '<programme') === false) {
                    continue;
                }

                $ch_start = strpos($line, 'channel="', 11);
                if ($ch_start === false) {
                    continue;
                }

                $ch_start += 9;
                $ch_end = strpos($line, '"', $ch_start);
                if ($ch_end === false) {
                    continue;
                }

                $channel = substr($line, $ch_start, $ch_end - $ch_start);

                if ($prev_channel !== $channel) {
                    if (is_null($prev_channel)) {
                        $prev_channel = $channel;
                    } else {
                        if (!empty($xmltv_data)) {
                            $index_name = sprintf("%s_%s.index", $this->url_hash, Hashed_Array::hash($prev_channel));
                            $xmltv_index[$prev_channel] = $index_name;
                            HD::StoreContentToFile($this->cache_dir . DIRECTORY_SEPARATOR . $index_name, $xmltv_data);
                            unset($xmltv_data);

                            if (LogSeverity::$is_debug) {
                                $log_entries[] = $index_name;
                                if (count($log_entries) > 50) {
                                    hd_debug_print("Save program indexes: " . json_encode($log_entries));
                                    unset($log_entries);
                                }
                            }
                        }

                        $prev_channel = $channel;
                        $xmltv_data = array();
                    }
                }
                $xmltv_data[] = $pos;
            }

            if (!empty($prev_channel) && !empty($xmltv_data)) {
                $index_name = sprintf("%s_%s.index", $this->url_hash, Hashed_Array::hash($prev_channel));
                $xmltv_index[$prev_channel] = $index_name;
                hd_debug_print("Save index: $index_name", true);
                HD::StoreContentToFile($this->cache_dir . DIRECTORY_SEPARATOR . $index_name, $xmltv_data);
                unset($xmltv_data);
                $log_entries[] = $index_name;
                if (LogSeverity::$is_debug) {
                    hd_debug_print("Save program indexes: " . json_encode($log_entries));
                }
                unset($log_entries);
            }

            if (!empty($xmltv_index)) {
                hd_debug_print("Save index: $index_program", true);
                HD::StoreContentToFile($index_program, $xmltv_index);
                $this->xmltv_index = $xmltv_index;
            }

            hd_debug_print("Total unique epg id's indexed: " . count($xmltv_index));
            hd_debug_print("Reindexing EPG program done: " . (microtime(1) - $t) . " secs");
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
        }

        $this->set_index_locked(false);

        HD::ShowMemoryUsage();
        hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
        return true;
    }

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    public function clear_epg_cache()
    {
        $this->clear_epg_files($this->url_hash);
    }

    /**
     * clear memory cache and cache for selected xmltv source
     *
     * @param $uri
     * @return void
     */
    public function clear_epg_cache_by_uri($uri)
    {
        $this->clear_epg_files(Hashed_Array::hash($uri));
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
     * @return bool
     */
    public function is_index_done()
    {
        $done = $this->cache_dir . DIRECTORY_SEPARATOR . "done";
        return file_exists($done);
    }

    /**
     * @return void
     */
    public function clear_index_done()
    {
        $done = $this->cache_dir . DIRECTORY_SEPARATOR . "done";
        unlink($done);
    }

    /**
     * @return bool
     */
    public function is_index_locked()
    {
        $lock_file = $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_hash . ".lock";
        return file_exists($lock_file);
    }

    /**
     * @param bool $lock
     */
    public function set_index_locked($lock)
    {
        $lock_file = $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_hash . ".lock";
        if ($lock) {
            file_put_contents($lock_file, '');
        } else if (file_exists($lock_file)) {
            unlink($lock_file);
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    protected function clear_epg_files($filename)
    {
        unset($this->epg_cache, $this->xmltv_data, $this->xmltv_index);
        $this->epg_cache = array();

        if (empty($this->cache_dir)) {
            return;
        }

        hd_debug_print("clear cache files: $filename*");
        shell_exec('rm -f '. $this->cache_dir . DIRECTORY_SEPARATOR . "$filename*");
        flush();
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * @return string
     */
    protected function get_cached_filename()
    {
        return $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_hash . ".xmltv";
    }

    /**
     * @return string
     */
    protected function get_epg_index_name()
    {
        return $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_hash . ".index";
    }

    /**
     * @return string
     */
    protected function get_channels_index_name()
    {
        return $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_hash . "_channels.index";
    }

    /**
     * @return string
     */
    protected function get_picons_index_name()
    {
        return $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_hash . "_picons.index";
    }

    /**
     * @param $ids array
     * @return string
     * @throws Exception
     */
    protected function get_channel_epg_id($ids)
    {
        if (empty($this->url_hash)) {
            throw new Exception("xmltv file is not set");
        }

        if (empty($this->xmltv_channels)) {
            $index_file = $this->get_channels_index_name();
            $data = HD::ReadContentFromFile($index_file);
            if (false !== $data) {
                $this->xmltv_channels = $data;
            }

            if (empty($this->xmltv_channels)) {
                throw new Exception("load channels index failed '$index_file'");
            }
        }

        foreach ($ids as $id) {
            if (isset($this->xmltv_channels[$id])) {
                return $this->xmltv_channels[$id];
            }
        }

        throw new Exception("No mapped EPG exist");
    }

    /**
     * @param $id string
     * @param $ch_epg array
     * @return bool
     * @throws Exception
     */
    protected function get_epg_data($id, &$ch_epg)
    {
        if (empty($this->xmltv_index)) {
            if ($this->is_index_locked()) {
                return false;
            }

            $index_file = $this->get_epg_index_name();
            //hd_debug_print("load index from file '$index_file'");
            $data = HD::ReadContentFromFile($index_file);
            if (false !== $data) {
                $this->xmltv_index = $data;
            }

            if (empty($this->xmltv_index)) {
                throw new Exception("load index failed '$index_file'");
            }
        }

        if (!isset($this->xmltv_channels[$id])) {
            throw new Exception("xmltv index for epg $id is not exist");
        }

        $channel_id = $this->xmltv_channels[$id];
        if (!isset($this->xmltv_data[$channel_id])) {
            if (!isset($this->xmltv_index[$channel_id])) {
                throw new Exception("xmltv index for channel $channel_id is not exist");
            }

            $channel_index = $this->cache_dir . DIRECTORY_SEPARATOR . $this->xmltv_index[$channel_id];
            //hd_debug_print("Check channel $id index: $channel_index");
            if (!file_exists($channel_index)) {
                throw new Exception("index for channel $channel_id not found: $channel_index");
            }

            $this->xmltv_data[$channel_id] = HD::ReadContentFromFile($channel_index);
        }

        $file_object = $this->open_xmltv_file();
        $ch_epg = array();
        foreach ($this->xmltv_data[$channel_id] as $pos) {
            $xml_str = '';
            $file_object->fseek($pos);
            while (!$file_object->eof()) {
                $line = $file_object->fgets();
                $xml_str .= $line . PHP_EOL;
                if (strpos($line, "</programme") !== false) {
                    break;
                }
            }

            $xml_node = new DOMDocument();
            $xml_node->loadXML($xml_str);
            $xml = (array)simplexml_import_dom($xml_node);

            $program_start = strtotime((string)$xml['@attributes']['start']);
            $ch_epg[$program_start][Epg_Params::EPG_END] = strtotime((string)$xml['@attributes']['stop']);
            $ch_epg[$program_start][Epg_Params::EPG_NAME] = (string)$xml['title'];
            $ch_epg[$program_start][Epg_Params::EPG_DESC] = isset($xml['desc']) ? (string)$xml['desc'] : '';
        }

        ksort($ch_epg);
        return true;
    }

    /**
     * @return SplFileObject
     * @throws Exception
     */
    protected function open_xmltv_file()
    {
        $cached_file = $this->get_cached_filename();
        if (!file_exists($cached_file)) {
            throw new Exception("cache file not exist");
        }

        $file_object = new SplFileObject($cached_file);
        $file_object->setFlags(SplFileObject::DROP_NEW_LINE);
        $file_object->rewind();

        return $file_object;
    }
}
