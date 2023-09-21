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
     * contains index for current xmltv file
     * @var SQLite3
     */
    public $xmltv_db_index;

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
            hd_debug_print("xmltv url: $this->xmltv_url");
            $this->url_hash = (empty($this->xmltv_url) ? '' : Hashed_Array::hash($this->xmltv_url));
            unset($this->xmltv_db_index);
            $this->xmltv_db_index = null;
        }
    }

    /**
     * @return bool
     */
    public function is_index_locked()
    {
        return file_exists($this->get_cache_stem(".lock"));
    }

    /**
     * @param bool $lock
     */
    public function set_index_locked($lock)
    {
        $lock_file = $this->get_cache_stem(".lock");
        if ($lock) {
            file_put_contents($lock_file, '');
        } else if (file_exists($lock_file)) {
            unlink($lock_file);
        }
    }

    /**
     * Get picon associated to epg id
     *
     * @param string $channel_name list of epg id's
     * @return string|null
     */
    public function get_picon($channel_name)
    {
        if (is_null($this->xmltv_db_index)) {
            return null;
        }

        $picon = $this->xmltv_db_index->querySingle("SELECT picon FROM channels WHERE alias='$channel_name';");
        // We expect that only one row returned!
        return (is_null($picon) || $picon === false) ? null : $picon;
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
            if ($this->xmltv_db_index === null) {
                throw new Exception("EPG not indexed!");
            }

            $epg_ids = $channel->get_epg_ids();
            if (empty($epg_ids)) {
                $epg_id = $this->xmltv_db_index->querySingle("SELECT DISTINCT channel_id FROM channels WHERE alias='{$channel->get_title()}';");
                if (is_null($epg_id) || $epg_id === false) {
                    throw new Exception("No EPG defined for channel: $channel_id ({$channel->get_title()})");
                }

                $epg_ids[] = $epg_id;
            }

            hd_debug_print("epg id's: " . json_encode($epg_ids));
            $placeHolders = implode(',' , array_fill(0, count($epg_ids), '?'));
            $sql = "SELECT pos FROM programs WHERE channel_id = (SELECT DISTINCT channel_id FROM channels WHERE channel_id IN ($placeHolders));";
            $stmt = $this->xmltv_db_index->prepare($sql);
            foreach ($epg_ids as $index => $val) {
                $stmt->bindValue($index + 1, $val);
            }

            $res = $stmt->execute();
            if (!$res) {
                throw new Exception("No data for epg $channel_id ({$channel->get_title()})");
            }

            hd_debug_print("Try to load EPG for channel '$channel_id' ({$channel->get_title()})");
            $file_object = $this->open_xmltv_file();
            while($row = $res->fetchArray(SQLITE3_NUM)){
                $xml_str = '';
                $file_object->fseek($row[0]);
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

            $epg_index = $this->get_db_filename();
            if ($this->xmltv_db_index !== null) {
                $this->xmltv_db_index->exec("UPDATE status SET channels=-1, programs=-1");
                hd_debug_print("Reset index status: $epg_index");
            } else if (file_exists($epg_index)){
                hd_debug_print("Remove index: $epg_index");
                unlink($epg_index);
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

        $this->open_db();
        $channels = $this->xmltv_db_index->querySingle("SELECT channels FROM status;");
        if (!is_null($channels) && $channels !== false && $channels !== -1) {
            hd_debug_print("EPG channels info already indexed", true);
            return;
        }

        $t = microtime(1);

        try {
            $this->set_index_locked(true);

            hd_debug_print("Start reindex channels...");
            $this->open_db(true);

            $this->xmltv_db_index->exec('BEGIN;');

            $sql = "INSERT OR REPLACE INTO channels(alias, channel_id, picon) VALUES(?, ?, ?);";
            $stm_alias = $this->xmltv_db_index->prepare($sql);
            $stm_alias->bindParam(1, $alias);
            $stm_alias->bindParam(2, $channel_id);
            $stm_alias->bindParam(3, $picon);

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

                $picon = '';
                foreach ($xml_node->getElementsByTagName('icon') as $tag) {
                    if (preg_match("|https?://|", $tag->getAttribute('src'))) {
                        $picon = $tag->getAttribute('src');
                    }
                }

                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $alias = $tag->nodeValue;
                    $stm_alias->execute();
                }
            }
            $this->xmltv_db_index->exec('COMMIT;');
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
        }

        $this->set_index_locked(false);

        $channels = $this->xmltv_db_index->querySingle("SELECT count(*) FROM channels;");
        if (is_null($channels) || $channels === false) {
            $channels = 0;
        }

        $this->xmltv_db_index->exec("UPDATE status SET channels='$channels';");

        $picons = $this->xmltv_db_index->querySingle("SELECT count(DISTINCT picon) FROM channels WHERE picon != '';");
        $picons = (is_null($picons) || $picons === false ? 0 : $picons);

        hd_debug_print("Reindexing EPG channels done: " . (microtime(1) - $t) . " secs");
        hd_debug_print("Total channels id's: $channels");
        hd_debug_print("Total picons: $picons");

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

        $this->open_db();
        $programs = $this->xmltv_db_index->querySingle("SELECT programs FROM status;");
        if ($programs !== -1) {
            return;
        }

        try {
            $this->set_index_locked(true);

            hd_debug_print("Start reindex programs...");

            $t = microtime(1);

            $this->open_db(false, true);
            $this->xmltv_db_index->exec('BEGIN;');
            $sql = "INSERT INTO programs(pos, channel_id) VALUES(?, ?);";
            $stm = $this->xmltv_db_index->prepare($sql);
            $stm->bindParam(1, $pos);
            $stm->bindParam(2, $channel_id);

            $file_object = $this->open_xmltv_file();
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

                $channel_id = substr($line, $ch_start, $ch_end - $ch_start);
                if (!empty($channel_id)) {
                    $stm->execute();
                }
            }

            $this->xmltv_db_index->exec('COMMIT;');

            $program_entries = $this->xmltv_db_index->querySingle("SELECT count(DISTINCT channel_id) FROM programs;");
            $this->xmltv_db_index->exec("UPDATE status SET programs='$program_entries';");
            hd_debug_print("Total unique epg id's indexed: $program_entries");
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
        $this->xmltv_db_index = null;
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
        return $this->get_cache_stem(".xmltv");
    }

    /**
     * @return string
     */
    protected function get_db_filename()
    {
        return $this->get_cache_stem(".db");
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
    public function open_db($drop_channels = false, $drop_programs = false)
    {
        if ($this->xmltv_db_index !== null) {
            return;
        }

        $index_name = $this->get_db_filename();
        if (file_exists($index_name)) {
            hd_debug_print("Open index db: $index_name");
            if ($this->xmltv_db_index === null) {
                $this->xmltv_db_index = new SQLite3($index_name, SQLITE3_OPEN_READWRITE, null);
            }
        } else {
            hd_debug_print("Creating index db: $index_name");
            $this->xmltv_db_index = new SQLite3($index_name, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, null);
            $this->xmltv_db_index->exec("CREATE TABLE status (channels INTEGER, programs INTEGER);");
            $this->xmltv_db_index->exec("INSERT INTO status(channels, programs) VALUES (-1, -1);");
            $drop_programs = $drop_channels = true;
        }

        if ($drop_channels) {
            $this->xmltv_db_index->exec('BEGIN;');
            $this->xmltv_db_index->exec("DROP TABLE IF EXISTS channels;");
            $this->xmltv_db_index->exec("CREATE TABLE channels (alias STRING PRIMARY KEY NOT NULL, channel_id STRING, picon STRING);");
            $this->xmltv_db_index->exec("UPDATE status SET channels=-1;");
            $this->xmltv_db_index->exec('COMMIT;');
        }

        if ($drop_programs) {
            $this->xmltv_db_index->exec('BEGIN;');
            $this->xmltv_db_index->exec("DROP TABLE IF EXISTS programs;");
            $this->xmltv_db_index->exec("CREATE TABLE programs (pos INTEGER UNIQUE, channel_id STRING);");
            $this->xmltv_db_index->exec("UPDATE status SET programs=-1;");
            $this->xmltv_db_index->exec('COMMIT;');
        }
    }
}
