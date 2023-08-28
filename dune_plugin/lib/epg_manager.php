<?php
require_once 'hd.php';
require_once 'epg_params.php';

class Epg_Manager
{
    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

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
     * contains parsed epg for channel
     * @var array
     */
    public $xmltv_data;

    /**
     * contains index for current xmltv file
     * @var array
     */
    public $xmltv_index;

    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param $xmltv_url string
     */
    public function set_xmltv_url($xmltv_url)
    {
        if ($this->xmltv_url !== $xmltv_url) {
            $this->xmltv_url = $xmltv_url;
            hd_print(__METHOD__ . ": xmltv url $this->xmltv_url");
            // reset memory cache and data
            unset($this->epg_cache, $this->xmltv_data, $this->xmltv_index);
            $this->epg_cache = array();
        }
    }

    /**
     * @param $plugin_cookies
     * @return string
     */
    public static function get_xcache_dir($plugin_cookies)
    {
        $xcache_dir = get_paved_path(smb_tree::get_folder_info($plugin_cookies, PARAM_XMLTV_CACHE_PATH, get_data_path("epg_cache")));
        if (substr($xcache_dir, -1) !== '/') {
            $xcache_dir .= '/';
        }
        return $xcache_dir;
    }

    /**
     * @param $plugin_cookies
     * @param $data MediaURL
     */
    public static function set_xcache_dir($plugin_cookies, $data)
    {
        smb_tree::set_folder_info($plugin_cookies, $data, PARAM_XMLTV_CACHE_PATH);
    }

    /**
     * @return string
     */
    public function get_xml_cached_filename()
    {
        return empty($this->xmltv_url) ? '' : hash('crc32', $this->xmltv_url);
    }

    /**
     * @return boolean
     */
    public function is_xml_cache_set()
    {
        $name = $this->get_xml_cached_filename();
        return !empty($name);
    }

    /**
     * @param $plugin_cookies
     * @return string
     */
    public function get_xml_cached_file($plugin_cookies)
    {
        return self::get_xcache_dir($plugin_cookies) . $this->get_xml_cached_filename() . '.xmltv';
    }

    /**
     * @param $plugin_cookies
     * @return string
     */
    public function get_xml_cached_file_index($plugin_cookies)
    {
        return self::get_xcache_dir($plugin_cookies) . $this->get_xml_cached_filename() . '.index';
    }

    /**
     * try to load epg from cache otherwise request it from server
     * store parsed response to the cache
     * @param Channel $channel
     * @param int $day_start_ts
     * @param &$plugin_cookies
     * @return array|false
     */
    public function get_day_epg_items(Channel $channel, $day_start_ts, &$plugin_cookies)
    {
        try {
            $epg_id = $channel->get_epg_id();
            if (empty($epg_id)) {
                throw new Exception("EPG ID not defined");
            }

            if (isset($this->epg_cache[$epg_id][$day_start_ts])) {
                hd_print(__METHOD__ . ": Load day EPG ID $epg_id ($day_start_ts) from memory cache ");
                return $this->epg_cache[$epg_id][$day_start_ts];
            }

            hd_print(__METHOD__ . ": Try to load EPG ID: '$epg_id' for channel '{$channel->get_id()}' ({$channel->get_title()})");
            $program_epg = $this->get_epg_xmltv($epg_id, $plugin_cookies);
            $counts = count($program_epg);
            if ($counts === 0) {
                throw new Exception("Empty or no EPG data for " . $channel->get_id());
            }

            //hd_print(__METHOD__ . ": Total $counts EPG entries loaded");

            // filter out epg only for selected day
            $day_end_ts = $day_start_ts + 86400;

            //$date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
            //$date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
            //hd_print(__METHOD__ . ": Fetch entries for from: $date_start_l to: $date_end_l");

            $day_epg = array();
            foreach ($program_epg as $time_start => $entry) {
                if ($time_start >= $day_start_ts && $time_start < $day_end_ts) {
                    $day_epg[$time_start] = $entry;
                }
            }

            if (empty($day_epg)) {
                throw new Exception("No EPG data for " . $channel->get_id());
            }

            //hd_print(__METHOD__ . ": Store day epg to memory cache");
            $this->epg_cache[$epg_id][$day_start_ts] = $day_epg;

            return $day_epg;

        } catch (Exception $ex) {
            hd_print(__METHOD__ . ": Can't fetch EPG from source $this->xmltv_url : " . $ex->getMessage());
        }

        return false;
    }


    /**
     * @param $cached_file string
     * @return SplFileObject
     * @throws Exception
     */
    protected static function open_xmltv_file($cached_file)
    {
        if (!file_exists($cached_file)) {
            throw new Exception("xmltv cache file not exist");
        }

        $file_object = new SplFileObject($cached_file);
        $file_object->setFlags(SplFileObject::DROP_NEW_LINE);
        $file_object->rewind();

        return $file_object;
    }

    /**
     * @param $id string
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_epg_xmltv($id, $plugin_cookies)
    {
        if (!$this->is_xml_cache_set()) {
            throw new Exception("xmltv file is not set");
        }

        if (empty($this->xmltv_index)) {
            $index_file = $this->get_xml_cached_file_index($plugin_cookies);
            //hd_print(__METHOD__ . ": load index from file '$index_file'");
            $data = json_decode(file_get_contents($index_file), true);
            if (false !== $data) {
                $this->xmltv_index = $data;
            }

            if (empty($this->xmltv_index)) {
                throw new Exception("load index failed '$index_file'");
            }
        }

        if (!isset($this->xmltv_data[$id])) {
            if (!isset($this->xmltv_index[$id])) {
                throw new Exception("xmltv index for channel $id is not exist");
            }

            $channel_index = self::get_xcache_dir($plugin_cookies) . $this->xmltv_index[$id];
            //hd_print(__METHOD__ . "Check channel $id index: $channel_index");
            if (!file_exists($channel_index)) {
                throw new Exception("index for channel $id not found: $channel_index");
            }

            $this->xmltv_data[$id] = json_decode(file_get_contents($channel_index), true);
        }

        $file_object = self::open_xmltv_file($this->get_xml_cached_file($plugin_cookies));
        $ch_epg = array();
        foreach ($this->xmltv_data[$id] as $pos) {
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

        return $ch_epg;
    }

    /**
     * @param $plugin_cookies
     * @return string if downloaded xmltv file exists it empty, otherwise contain error message
     */
    public function is_xmltv_cache_valid($plugin_cookies)
    {
        try {
            if (!$this->is_xml_cache_set()) {
                throw new Exception("Cached xmltv file is not set");
            }

            $cached_file = $this->get_xml_cached_file($plugin_cookies);
            $cached_file_index = $this->get_xml_cached_file_index($plugin_cookies);
            hd_print(__METHOD__ . ": Checking cached xmltv file: $cached_file");
            if (!file_exists($cached_file)) {
                hd_print(__METHOD__ . ": Cached xmltv file not exist");
            } else {
                $check_time_file = filemtime($cached_file);
                $max_cache_time = 3600 * 24 * (isset($plugin_cookies->{Starnet_Epg_Setup_Screen::SETUP_ACTION_EPG_CACHE_TTL})
                        ? $plugin_cookies->{Starnet_Epg_Setup_Screen::SETUP_ACTION_EPG_CACHE_TTL}
                        : 3);
                if ($check_time_file && $check_time_file + $max_cache_time > time()) {
                    hd_print(__METHOD__ . ": Cached file: $cached_file is not expired " . date("Y-m-d H:s", $check_time_file));
                    return '';
                }

                hd_print(__METHOD__ . ": clear cached file: $cached_file");
                unlink($cached_file);
            }

            if (empty($this->xmltv_url)) {
                throw new Exception("XMTLV EPG url not set");
            }

            hd_print(__METHOD__ . ": Storage space in cache dir: " . HD::get_storage_size(dirname($cached_file)));
            $tmp_filename = $cached_file . 'tmp';
            $last_mod_file = HD::http_save_document($this->xmltv_url, $tmp_filename);
            hd_print(__METHOD__ . ": Last changed time on server: " . date("Y-m-d H:s", $last_mod_file));

            if (preg_match("/\.(xml.gz|xml|gz|zip)(?:\??.*)?$/i", $this->xmltv_url, $m)) {
                if (strcasecmp($m[1], 'gz') || strcasecmp($m[1], 'xml.gz')) {
                    hd_print(__METHOD__ . ": unpack $tmp_filename");
                    $gz = gzopen($tmp_filename, 'rb');
                    if (!$gz) {
                        throw new Exception("Failed to open $tmp_filename");
                    }

                    $dest = fopen($cached_file, 'wb');
                    if (!$dest) {
                        throw new Exception("Failed to open $cached_file");
                    }

                    $res = stream_copy_to_stream($gz, $dest);
                    gzclose($gz);
                    fclose($dest);
                    unlink($tmp_filename);
                    if ($res === false) {
                        throw new Exception("Failed to unpack $tmp_filename to $cached_file");
                    }
                    hd_print(__METHOD__ . ": $res bytes written to $cached_file");
                } else if (strcasecmp($m[1], 'zip')) {
                    hd_print(__METHOD__ . ": unzip $tmp_filename");
                    $unzip = new ZipArchive();
                    $out = $unzip->open($tmp_filename);
                    if ($out !== true) {
                        throw new Exception("Failed to unzip $tmp_filename (error code: $out)");
                    }
                    $filename = $unzip->getNameIndex(0);
                    if (empty($filename)) {
                        $unzip->close();
                        throw new Exception("empty zip file $tmp_filename");
                    }

                    $unzip->extractTo(self::get_xcache_dir($plugin_cookies));
                    $unzip->close();

                    rename($filename, $cached_file);
                    $size = filesize($cached_file);
                    hd_print(__METHOD__ . ": $size bytes written to $cached_file");
                } else {
                    if (file_exists($cached_file)) {
                        unlink($cached_file);
                    }
                    rename($tmp_filename, $cached_file);
                }
            }

            if (file_exists($cached_file_index)) {
                hd_print(__METHOD__ . ": clear cached file index: $cached_file_index");
                unlink($cached_file_index);
            }
        } catch (Exception $ex) {
            hd_print(__METHOD__ . ": " . $ex->getMessage());
            return $ex->getMessage();
        }

        return '';
    }

    /**
     * @param $plugin_cookies
     * @param $epg_ids array
     */
    public function index_xmltv_file($plugin_cookies, $epg_ids)
    {
        $res = $this->is_xmltv_cache_valid($plugin_cookies);
        if (!empty($res)) {
            hd_print(__METHOD__ . ": Error load xmltv: $res");
            return;
        }

        $cached_file_index = $this->get_xml_cached_file_index($plugin_cookies);
        if (file_exists($cached_file_index)) {
            hd_print(__METHOD__ . ": Load cache file index: $cached_file_index");
            $this->xmltv_index = json_decode(file_get_contents($cached_file_index), true);
            return;
        }

        hd_print(__METHOD__ . ": Cached file index: $cached_file_index is not valid need reindex");

        $parse_all = $this->plugin->get_settings(PARAM_EPG_PARSE_ALL, SetupControlSwitchDefs::switch_off);
        $file_object = null;
        $cache_file = $this->get_xml_cached_filename();
        $cache_dir = self::get_xcache_dir($plugin_cookies);
        $lock_file = "$cache_dir$cache_file.lock";
        try {
            if (file_exists($lock_file)) {
                throw new Exception("File is indexed now, skipped");
            }

            file_put_contents($lock_file, '');

            $cached_path = $this->get_xml_cached_file($plugin_cookies);
            $file_object = self::open_xmltv_file($cached_path);
            hd_print(__METHOD__ . ": Reindexing $cached_path");
            $t = microtime(1);

            $prev_channel = '';
            $xmltv_index = array();
            $xmltv_data = array();
            while (!$file_object->eof()) {
                $pos = $file_object->ftell();
                $line = $file_object->fgets();
                if (strpos($line, "<programme") === false) {
                    continue;
                }

                $ch_start = strpos($line, 'channel="');
                if ($ch_start === false) {
                    continue;
                }

                $ch_start += 9;
                $ch_end = strpos($line, '"', $ch_start);
                if ($ch_end === false) {
                    continue;
                }

                $channel = substr($line, $ch_start, $ch_end - $ch_start);

                // if epg id not present in current channels list skip it
                if (!$parse_all && !is_null($epg_ids) && !isset($epg_ids[$channel])) {
                    continue;
                }

                if ($prev_channel !== $channel) {
                    if (empty($prev_channel)) {
                        $prev_channel = $channel;
                    } else {
                        if (!empty($xmltv_data)) {
                            $index_name = $cache_file . "_" . hash("crc32", $prev_channel) . '.index';
                            $xmltv_index[$prev_channel] = $index_name;
                            file_put_contents("$cache_dir$index_name", json_encode($xmltv_data));
                        }

                        $prev_channel = $channel;
                        unset($xmltv_data);
                        $xmltv_data = array();
                    }
                }
                $xmltv_data[] = $pos;
            }

            if (!empty($prev_channel) && !empty($xmltv_data)) {
                $index_name = $cache_file . "_" . hash("crc32", $prev_channel) . '.index';
                $xmltv_index[$prev_channel] = $index_name;
                file_put_contents("$cache_dir$index_name", json_encode($xmltv_data));
                unset($xmltv_data);
            }

            if (!empty($xmltv_index)) {
                hd_print(__METHOD__ . ": Save index: $cached_file_index");
                file_put_contents($cached_file_index, json_encode($xmltv_index));
                $this->xmltv_index = $xmltv_index;
            }

            hd_print(__METHOD__ . ": Reindexing XMLTV done: " . (microtime(1) - $t) . " secs");
            hd_print(__METHOD__ . ": Total epg id's indexed: " . count($xmltv_index));
        } catch (Exception $ex) {
            hd_print(__METHOD__ . ": " . $ex->getMessage());
        }

        if (!is_null($file_object)) {
            unset($file_object);
        }

        HD::ShowMemoryUsage();
        hd_print(__METHOD__ . ": Storage space in cache dir after reindexing: " . HD::get_storage_size($cache_dir));

        unlink($lock_file);
    }

    /**
     * clear memory cache
     * @param $plugin_cookies
     */
    public function clear_epg_cache($plugin_cookies)
    {
        unset($this->epg_cache, $this->xmltv_data, $this->xmltv_index);
        $this->epg_cache = array();

        $dir = self::get_xcache_dir($plugin_cookies);
        $path = "$dir{$this->get_xml_cached_filename()}*.*";
        hd_print(__METHOD__ . ": clear cache files: $path");
        foreach (glob($path) as $file) {
            if (!is_dir($file)) {
                unlink($file);
            }
        }
        HD::ShowMemoryUsage();
        hd_print(__METHOD__ . ": Storage space in cache dir: " . HD::get_storage_size($dir));
    }

    /**
     * clear memory cache
     * @param $plugin_cookies
     */
    public function clear_all_epg_cache($plugin_cookies)
    {
        unset($this->epg_cache, $this->xmltv_data, $this->xmltv_index);
        $this->epg_cache = array();

        $dir = self::get_xcache_dir($plugin_cookies);
        hd_print(__METHOD__ . ": clear entire cache dir: $dir");
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            if (!is_dir("$dir/$file")) {
                unlink("$dir/$file");
            }
        }
        HD::ShowMemoryUsage();
        hd_print(__METHOD__ . ": Storage space in cache dir: " . HD::get_storage_size($dir));
    }
}
