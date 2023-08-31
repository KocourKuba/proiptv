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
     * path where cache is stored
     * @var string
     */
    protected $cache_dir;

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

    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->init_cache_dir();
    }

    public function init_cache_dir()
    {
        $this->cache_dir = smb_tree::get_folder_info($this->plugin->get_parameters(PARAM_XMLTV_CACHE_PATH), get_data_path("epg_cache/"));
        create_path($this->cache_dir);
        hd_print(__METHOD__ . ": cache dir: $this->cache_dir");
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
            hd_print(__METHOD__ . ": xmltv url $this->xmltv_url");
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
            return null;
        }

        if (!isset($this->xmltv_picons)) {
            hd_print(__METHOD__ . ": Load picons from: " . $this->get_picons_index_name());
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
                hd_print(__METHOD__ . ": Load day EPG ID $epg_id ($day_start_ts) from memory cache ");
                return $this->epg_cache[$epg_id][$day_start_ts];
            }

            hd_print(__METHOD__ . ": Try to load EPG ID: '$epg_id' for channel '{$channel->get_id()}' ({$channel->get_title()})");
            $program_epg = $this->get_epg_data($epg_id);
            $counts = count($program_epg);
            if ($counts === 0) {
                throw new Exception("Empty or no data for EPG ID: $epg_id");
            }

            hd_print(__METHOD__ . ": Total $counts EPG entries loaded");

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
     * Checks if xmltv source cached and not expired.
     * If not, try to download it and unpack if necessary
     *
     * @return string if downloaded xmltv file exists it empty, otherwise contain error message
     */
    public function is_xmltv_cache_valid()
    {
        try {
            if (!$this->is_xml_cache_set()) {
                throw new Exception("Cached xmltv file is not set");
            }

            $cached_xmltv_file = $this->get_cached_filename();
            hd_print(__METHOD__ . ": Checking cached xmltv file: $cached_xmltv_file");
            if (!file_exists($cached_xmltv_file)) {
                hd_print(__METHOD__ . ": Cached xmltv file not exist");
            } else {
                $check_time_file = filemtime($cached_xmltv_file);
                $max_cache_time = 3600 * 24 * $this->plugin->get_settings(PARAM_EPG_CACHE_TTL, 3);
                if ($check_time_file && $check_time_file + $max_cache_time > time()) {
                    hd_print(__METHOD__ . ": Cached file: $cached_xmltv_file is not expired " . date("Y-m-d H:s", $check_time_file));
                    return '';
                }

                hd_print(__METHOD__ . ": clear cached file: $cached_xmltv_file");
                unlink($cached_xmltv_file);
            }

            if (empty($this->xmltv_url)) {
                throw new Exception("XMTLV EPG url not set");
            }

            hd_print(__METHOD__ . ": Storage space in cache dir: " . HD::get_storage_size(dirname($cached_xmltv_file)));
            $tmp_filename = $cached_xmltv_file . '.tmp';
            $last_mod_file = HD::http_save_document($this->xmltv_url, $tmp_filename);
            hd_print(__METHOD__ . ": Last changed time on server: " . date("Y-m-d H:s", $last_mod_file));

            if (preg_match("/\.(xml.gz|xml|xmltv|gz|zip)(?:\??.*)?$/i", $this->xmltv_url, $m)) {
                if (strcasecmp($m[1], 'gz') || strcasecmp($m[1], 'xml.gz')) {
                    hd_print(__METHOD__ . ": unpack $tmp_filename");
                    $gz = gzopen($tmp_filename, 'rb');
                    if (!$gz) {
                        throw new Exception("Failed to open $tmp_filename");
                    }

                    $dest = fopen($cached_xmltv_file, 'wb');
                    if (!$dest) {
                        throw new Exception("Failed to open $cached_xmltv_file");
                    }

                    $res = stream_copy_to_stream($gz, $dest);
                    gzclose($gz);
                    fclose($dest);
                    unlink($tmp_filename);
                    if ($res === false) {
                        throw new Exception("Failed to unpack $tmp_filename to $cached_xmltv_file");
                    }
                    hd_print(__METHOD__ . ": $res bytes written to $cached_xmltv_file");
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

                    $unzip->extractTo($this->cache_dir);
                    $unzip->close();

                    rename($filename, $cached_xmltv_file);
                    $size = filesize($cached_xmltv_file);
                    hd_print(__METHOD__ . ": $size bytes written to $cached_xmltv_file");
                } else {
                    if (file_exists($cached_xmltv_file)) {
                        unlink($cached_xmltv_file);
                    }
                    rename($tmp_filename, $cached_xmltv_file);
                }
            }

            $epg_index = $this->get_epg_index_name();
            if (file_exists($epg_index)) {
                hd_print(__METHOD__ . ": clear cached epg index: $epg_index");
                unlink($epg_index);
            }

            $channels_index = $this->get_channels_index_name();
            if (file_exists($channels_index)) {
                hd_print(__METHOD__ . ": clear cached channels index: $channels_index");
                unlink($channels_index);
            }

            $picons_index = $this->get_picons_index_name();
            if (file_exists($picons_index)) {
                hd_print(__METHOD__ . ": clear cached picons index: $picons_index");
                unlink($picons_index);
            }
        } catch (Exception $ex) {
            hd_print(__METHOD__ . ": " . $ex->getMessage());
            return $ex->getMessage();
        }

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
            hd_print(__METHOD__ . ": Error load xmltv: $res");
            return;
        }

        $channels_file = $this->get_channels_index_name();
        if (file_exists($channels_file)) {
            hd_print(__METHOD__ . ": Load cache channels index: $channels_file");
            $this->xmltv_channels = HD::ReadContentFromFile($channels_file);
            return;
        }

        hd_print(__METHOD__ . ": Channels index: $channels_file is not valid need reindex");

        $cache_file = $this->get_internal_name();
        $lock_file = "$this->cache_dir$cache_file.lock";
        $channels_map = array();
        $picons_map = array();
        $t = microtime(1);
        try {
            if (file_exists($lock_file)) {
                throw new Exception("File is indexed now, skipped");
            }

            file_put_contents($lock_file, '');

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
                    //hd_print(__METHOD__ . "channel id: $channel_id picon: $picon");
                    if (preg_match("|https?://|", $picon)) {
                        $picons_map[$channel_id] = $picon;
                    }
                }
            }

            HD::StoreContentToFile($channels_file, $channels_map);
            HD::StoreContentToFile($this->get_picons_index_name(), $picons_map);

        } catch (Exception $ex) {
            hd_print(__METHOD__ . ": " . $ex->getMessage());
        }

        if (file_exists($lock_file)) {
            unlink($lock_file);
        }

        hd_print(__METHOD__ . ": Reindexing channels info done: " . (microtime(1) - $t) . " secs");
        hd_print(__METHOD__ . ": Total channels id's: " . count($channels_map));
        hd_print(__METHOD__ . ": Total picons: " . count($picons_map));

        HD::ShowMemoryUsage();
        hd_print(__METHOD__ . ": Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * indexing xmltv epg info
     *
     * @param $epg_ids array
     * @return void
     */
    public function index_xmltv_file($epg_ids)
    {
        $res = $this->is_xmltv_cache_valid();
        if (!empty($res)) {
            hd_print(__METHOD__ . ": Error load xmltv: $res");
            return;
        }

        $channels_file = $this->get_channels_index_name();
        $index_file = $this->get_epg_index_name();
        if (file_exists($channels_file) && file_exists($index_file)) {
            hd_print(__METHOD__ . ": Load cache channels index: $channels_file");
            $this->xmltv_channels = HD::ReadContentFromFile($channels_file);
            hd_print(__METHOD__ . ": Load cache file index: $index_file");
            $this->xmltv_index = HD::ReadContentFromFile($index_file);
            return;
        }

        hd_print(__METHOD__ . ": Cached file index: $index_file is not valid need reindex");

        $parse_all = $this->plugin->get_settings(PARAM_EPG_PARSE_ALL, SetupControlSwitchDefs::switch_off);
        $cache_file = $this->get_internal_name();
        $cache_dir = $this->cache_dir;
        $lock_file = "$cache_dir$cache_file.lock";
        try {
            if (file_exists($lock_file)) {
                throw new Exception("File is indexed now, skipped");
            }

            file_put_contents($lock_file, '');

            $this->index_xmltv_channels();

            $t = microtime(1);

            $file_object = $this->open_xmltv_file();

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
                            HD::StoreContentToFile("$cache_dir$index_name", $xmltv_data);
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
                HD::StoreContentToFile("$cache_dir$index_name", $xmltv_data);
                unset($xmltv_data);
            }

            if (!empty($xmltv_index)) {
                hd_print(__METHOD__ . ": Save index: $index_file");
                HD::StoreContentToFile($index_file, $xmltv_index);
                $this->xmltv_index = $xmltv_index;
            }

            hd_print(__METHOD__ . ": Reindexing EPG done: " . (microtime(1) - $t) . " secs");
            hd_print(__METHOD__ . ": Total unique epg id's indexed: " . count($xmltv_index));
        } catch (Exception $ex) {
            hd_print(__METHOD__ . ": " . $ex->getMessage());
        }

        if (file_exists($lock_file)) {
            unlink($lock_file);
        }

        HD::ShowMemoryUsage();
        hd_print(__METHOD__ . ": Storage space in cache dir after reindexing: " . HD::get_storage_size($cache_dir));
    }

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    public function clear_epg_cache()
    {
        unset($this->epg_cache, $this->xmltv_data, $this->xmltv_index);
        $this->epg_cache = array();

        hd_print(__METHOD__ . ": clear cache files: {$this->get_internal_name()}*");
        foreach (glob_dir($this->cache_dir, "/^{$this->get_internal_name()}.*$/i") as $file) {
            unlink($file);
        }

        hd_print(__METHOD__ . ": Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * clear memory cache and cache for selected xmltv source
     *
     * @param $uri
     * @return void
     */
    public function clear_epg_cache_by_uri($uri)
    {
        unset($this->epg_cache, $this->xmltv_data, $this->xmltv_index);
        $this->epg_cache = array();

        $filename = hash('crc32', $uri);
        hd_print(__METHOD__ . ": clear cache files: $filename*");
        foreach (glob_dir($this->cache_dir, "/^$filename.*$/i") as $file) {
            unlink($file);
        }

        hd_print(__METHOD__ . ": Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * clear memory cache and entire cache folder
     *
     * @return void
     */
    public function clear_all_epg_cache()
    {
        unset($this->epg_cache, $this->xmltv_data, $this->xmltv_index);
        $this->epg_cache = array();

        $dir = $this->cache_dir;
        hd_print(__METHOD__ . ": clear entire cache dir: $dir");
        foreach (glob_dir($this->cache_dir) as $file) {
            unlink($file);
        }

        hd_print(__METHOD__ . ": Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @return boolean
     */
    protected function is_xml_cache_set()
    {
        $name = $this->get_internal_name();
        return !empty($name);
    }

    /**
     * @return string
     */
    protected function get_internal_name()
    {
        return empty($this->xmltv_url) ? '' : hash('crc32', $this->xmltv_url);
    }

    /**
     * @return string
     */
    protected function get_cached_filename()
    {
        return "$this->cache_dir{$this->get_internal_name()}.xmltv";
    }

    /**
     * @return string
     */
    protected function get_epg_index_name()
    {
        return "$this->cache_dir{$this->get_internal_name()}.index";
    }

    /**
     * @return string
     */
    protected function get_channels_index_name()
    {
        return "$this->cache_dir{$this->get_internal_name()}_channels.index";
    }

    /**
     * @return string
     */
    protected function get_picons_index_name()
    {
        return "$this->cache_dir{$this->get_internal_name()}_picons.index";
    }

    /**
     * @param $ids array
     * @return string
     * @throws Exception
     */
    protected function get_channel_epg_id($ids)
    {
        if (!$this->is_xml_cache_set()) {
            throw new Exception("xmltv file is not set");
        }

        if (empty($this->xmltv_channels)) {
            $index_file = $this->get_channels_index_name();
            //hd_print(__METHOD__ . ": load index from file '$index_file'");
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
     * @return array
     * @throws Exception
     */
    protected function get_epg_data($id)
    {
        if (empty($this->xmltv_index)) {
            $index_file = $this->get_epg_index_name();
            //hd_print(__METHOD__ . ": load index from file '$index_file'");
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

            $channel_index = $this->cache_dir . $this->xmltv_index[$channel_id];
            //hd_print(__METHOD__ . "Check channel $id index: $channel_index");
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

        return $ch_epg;
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
