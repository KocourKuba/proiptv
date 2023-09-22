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
            $this->clear_index();
        }
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

    /**
     * Get picons
     *
     * @return array|null
     */
    public function get_picons()
    {
        if (!isset($this->xmltv_picons)
            && file_exists(get_data_path('version'))
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
        $channel_id = $channel->get_id();

        if ($this->is_index_locked()) {
            hd_debug_print("EPG still indexing");
            $this->delayed_epg[] = $channel_id;
            return array($day_start_ts => array(
                Epg_Params::EPG_END  => $day_start_ts + 86400,
                Epg_Params::EPG_NAME => TR::load_string('epg_not_ready'),
                Epg_Params::EPG_DESC => TR::load_string('epg_not_ready_desc'),
                )
            );
        }

        $program_epg = array();

        try {
            if (empty($this->xmltv_index)) {
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

            if (empty($this->xmltv_channels)) {
                $index_file = $this->get_channels_index_name();
                $this->xmltv_channels = HD::ReadContentFromFile($index_file);
                if (empty($this->xmltv_channels)) {
                    hd_debug_print("load channels index failed '$index_file'");
                    $this->xmltv_channels = null;
                    throw new Exception("load channels index failed '$index_file'");
                }
            }

            $epg_id = null;
            foreach ($channel->get_epg_ids() as $id) {
                if (isset($this->xmltv_channels[$id])) {
                    $epg_id = $this->xmltv_channels[$id];
                    break;
                }
            }

            $channel_title = $channel->get_title();
            if (empty($epg_id)) {
                if (!isset($this->xmltv_channels[$channel_title])) {
                    hd_debug_print("No mapped EPG exist", true);
                    throw new Exception("No mapped EPG exist");
                }

                $epg_id = $this->xmltv_channels[$channel_title];
            }

            hd_debug_print("Try to load EPG ID: '$epg_id' for channel '$channel_id' ($channel_title)");
            if (!isset($this->xmltv_channels[$epg_id])) {
                throw new Exception("xmltv index for epg $epg_id is not exist");
            }

            $channel_id = $this->xmltv_channels[$epg_id];
            if (!isset($this->xmltv_data[$channel_id])) {
                if (!isset($this->xmltv_index[$channel_id])) {
                    throw new Exception("xmltv index for channel $channel_id is not exist");
                }

                $channel_index = $this->cache_dir . DIRECTORY_SEPARATOR . $this->xmltv_index[$channel_id];
                if (!file_exists($channel_index)) {
                    throw new Exception("index for channel $channel_id not found: $channel_index");
                }

                $content = HD::ReadContentFromFile($channel_index);
                if ($content === false) {
                    throw new Exception("index for channel $channel_id is broken");
                }
                $this->xmltv_data[$channel_id] = $content;
            }

            $file_object = $this->open_xmltv_file();
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
                $program_epg[$program_start][Epg_Params::EPG_END] = strtotime((string)$xml['@attributes']['stop']);
                $program_epg[$program_start][Epg_Params::EPG_NAME] = (string)$xml['title'];
                $program_epg[$program_start][Epg_Params::EPG_DESC] = isset($xml['desc']) ? (string)$xml['desc'] : '';
            }
        } catch (Exception $ex) {
            hd_print($ex->getMessage());
        }

        ksort($program_epg);

        $counts = count($program_epg);
        if ($counts === 0 && $channel->get_archive() === 0) {
            hd_debug_print("No EPG and no archives");
            return array();
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
            hd_debug_print("Create fake data for non existing EPG data");
            $n = 0;
            for ($start = $day_start_ts; $start <= $day_start_ts + 86400; $start += 3600) {
                $day_epg[$start][Epg_Params::EPG_END] = $start + 3600;
                $day_epg[$start][Epg_Params::EPG_NAME] = TR::load_string('fake_epg_program') . " " . ++$n;
                $day_epg[$start][Epg_Params::EPG_DESC] = '';
            }
        }

        return $day_epg;
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

            $t = microtime(1);
            hd_debug_print("Storage space in cache dir: " . HD::get_storage_size(dirname($cached_xmltv_file)));
            $tmp_filename = $cached_xmltv_file . '.tmp';
            $cmd = get_install_path('bin/https_proxy.sh') . " '$this->xmltv_url' '$tmp_filename'";
            hd_debug_print("Exec: $cmd", true);
            shell_exec($cmd);

            if (!file_exists($tmp_filename)) {
                throw new Exception("Failed to save $this->xmltv_url to $tmp_filename");
            }

            hd_debug_print("Last changed time on server: " . date("Y-m-d H:s", filemtime($tmp_filename)));

            if (file_exists($tmp_filename)) {
                $handle = fopen($tmp_filename, "rb");
                $hdr = fread($handle, 6);
                fclose($handle);
                if (0 === mb_strpos($hdr , "\x1f\x8b\x08")) {
                    hd_debug_print("ungzip $tmp_filename to $cached_xmltv_file");
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
                } else if (0 === mb_strpos($hdr, "\x50\x4b\x03\x04")) {
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
                } else if (0 === mb_strpos($hdr, "<?xml")) {
                    hd_debug_print("rename $tmp_filename to $cached_xmltv_file");
                    if (file_exists($cached_xmltv_file)) {
                        unlink($cached_xmltv_file);
                    }
                    rename($tmp_filename, $cached_xmltv_file);
                } else {
                    throw new Exception(TR::t('err_unknown_file_type__1', $tmp_filename));
                }
            } else {
                throw new Exception(TR::t('err_load_xmltv_epg'));
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

        hd_debug_print("------------------------------------------------------------");
        hd_debug_print("Update xmltv source $this->xmltv_url done: " . (microtime(1) - $t) . " secs");

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
        if (file_exists($channels_file) && file_exists(get_data_path('version'))
            && file_get_contents(get_data_path($this->url_hash . '_version')) > '1.8') {

            hd_debug_print("Load cache channels index: $channels_file");
            $this->xmltv_channels = HD::ReadContentFromFile($channels_file);
            return;
        }

        $this->xmltv_channels = array();
        $this->xmltv_picons = array();
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

                foreach ($xml_node->getElementsByTagName('icon') as $tag) {
                    $picon = $tag->getAttribute('src');
                    if (!preg_match("|https?://|", $picon)) {
                        $picon = '';
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

            HD::StoreContentToFile($this->get_picons_index_name(), $this->xmltv_picons);
            HD::StoreContentToFile($channels_file, $this->xmltv_channels);
            file_put_contents(get_data_path($this->url_hash . '_version'), $this->plugin->plugin_info['app_version']);
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
        }

        $this->set_index_locked(false);

        hd_debug_print("Total channels id's: " . count($this->xmltv_channels));
        hd_debug_print("Total picons: " . count($this->xmltv_picons));
        hd_debug_print("------------------------------------------------------------");
        hd_debug_print("Reindexing EPG channels done: " . (microtime(1) - $t) . " secs");

        HD::ShowMemoryUsage();
    }

    /**
     * indexing xmltv epg info
     *
     * @return void
     */
    public function index_xmltv_program()
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

        $cache_valid = false;
        $index_program = $this->get_epg_index_name();
        if (file_exists($index_program)) {
            hd_debug_print("Load cache program index: $index_program");
            $this->xmltv_index = HD::ReadContentFromFile($index_program);
            if ($this->xmltv_index !== false) {
                $cache_valid = true;
                foreach ($this->xmltv_index as $idx) {
                    if (!file_exists($this->cache_dir . DIRECTORY_SEPARATOR . $idx)) {
                        $cache_valid = false;
                        break;
                    }
                }
            }
        }

        if ($cache_valid) {
            return;
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
            hd_debug_print("------------------------------------------------------------");
            hd_debug_print("Reindexing EPG program done: " . (microtime(1) - $t) . " secs");
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

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    protected function clear_epg_files($filename)
    {
        $this->clear_index();

        if (empty($this->cache_dir)) {
            return;
        }

        hd_debug_print("clear cache files: $filename*");
        shell_exec('rm -f '. $this->cache_dir . DIRECTORY_SEPARATOR . "$filename*");
        flush();
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * @param string $ext
     * @return string
     */
    protected function get_cache_stem($ext)
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
     * @return string
     */
    protected function get_epg_index_name()
    {
        return $this->get_cache_stem(".index");
    }

    /**
     * @return string
     */
    protected function get_channels_index_name()
    {
        return $this->get_cache_stem("_channels.index");
    }

    /**
     * @return string
     */
    protected function get_picons_index_name()
    {
        return $this->get_cache_stem("_picons.index");
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

    /**
     * @return void
     */
    protected function clear_index()
    {
        $this->xmltv_picons = null;
        $this->xmltv_channels = null;
        unset($this->xmltv_data, $this->xmltv_index);
        $this->xmltv_data = null;
        $this->xmltv_index = null;
    }
}
