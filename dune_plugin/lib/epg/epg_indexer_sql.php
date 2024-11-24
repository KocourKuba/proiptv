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

require_once 'epg_indexer.php';

class Epg_Indexer_Sql extends Epg_Indexer
{
    /**
     * @var SQLite3[]
     */
    protected $epg_db = array();

    public function __construct()
    {
        parent::__construct();
        $this->index_ext = '.db';
    }

    /**
     * @inheritDoc
     * @override
     */
    public function get_picon($active_sources, $aliases)
    {
        $placeHolders = '';
        foreach ($aliases as $alias) {
            if (empty($alias)) continue;
            if (!empty($placeHolders)) {
                $placeHolders .= ',';
            }
            $placeHolders .= "'" . SQLite3::escapeString($alias) . "'";
        }

        $res = '';
        if (empty($placeHolders)) {
            return $res;
        }

        $table_pic = self::INDEX_PICONS;
        $table_ch = self::INDEX_CHANNELS;
        $qry = "SELECT distinct (picon_url) FROM $table_pic INNER JOIN $table_ch ON $table_pic.picon_hash=$table_ch.picon_hash WHERE alias IN ($placeHolders);";

        foreach ($active_sources as $source) {
            $db = $this->open_sqlite_db($source[PARAM_HASH]);
            if (is_null($db) || $db === false) continue;

            $res = $db->querySingle($qry);
            if (!empty($res)) break;
        }

        return $res;
    }

    /**
     * @inheritDoc
     * @override
     */
    public function load_program_index($channel)
    {
        $channel_position = array();
        $table_ch = self::INDEX_CHANNELS;
        $table_pos = self::INDEX_ENTRIES;

        try {
            $db = $this->open_sqlite_db($this->xmltv_url_params[PARAM_HASH]);
            if (is_null($db)) {
                throw new Exception("Problem with open SQLite db! Possible url not set");
            }

            if (!$this->is_all_indexes_valid(array($table_ch, $table_pos))) {
                throw new Exception("EPG for {$this->xmltv_url_params[PARAM_URI]} not indexed!");
            }

            $channel_title = $channel->get_title();
            $epg_ids = array_values($channel->get_epg_ids());

            $placeHolders = implode(',', array_fill(0, count($epg_ids), '?'));
            $stm = $db->prepare("SELECT DISTINCT channel_id FROM $table_ch WHERE alias IN ($placeHolders);");
            if ($stm !== false) {
                foreach ($epg_ids as $index => $val) {
                    $stm->bindValue($index + 1, mb_convert_case(SQLite3::escapeString($val), MB_CASE_LOWER, "UTF-8"));
                }

                $res = $stm->execute();
                if (!$res) {
                    throw new Exception("Query failed for epg's: " . pretty_json_format($epg_ids) . " ($channel_title)");
                }

                while ($row = $res->fetchArray(SQLITE3_NUM)) {
                    $epg_ids[] = (string)$row[0];
                }
            }

            $epg_ids = array_values(array_unique($epg_ids));
            hd_debug_print("Found epg_ids: " . pretty_json_format($epg_ids), true);
            $channel_id = $channel->get_id();
            if (!empty($epg_ids)) {
                hd_debug_print("Load position indexes for: $channel_id ($channel_title)", true);
                $placeHolders = implode(',', array_fill(0, count($epg_ids), '?'));
                $stmt = $db->prepare("SELECT start, end FROM $table_pos WHERE channel_id IN ($placeHolders);");
                if ($stmt !== false) {
                    foreach ($epg_ids as $index => $val) {
                        $stmt->bindValue($index + 1, SQLite3::escapeString($val));
                    }

                    $res = $stmt->execute();
                    if (!$res) {
                        throw new Exception("Query failed for epg's: " . pretty_json_format($epg_ids) . " ($channel_title)");
                    }

                    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                        $data = array_map(function ($col) { return $col; }, $row);
                        $channel_position[] = $data;
                    }
                }
            }

            if (empty($channel_position)) {
                hd_debug_print("No positions for channel $channel_id ($channel_title) and epg id's: " . pretty_json_format($epg_ids));
            }
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        hd_debug_print("Channel positions: " . pretty_json_format($channel_position), true);
        return $channel_position;
    }

    /**
     * @inheritDoc
     * @override
     */
    public function index_xmltv_channels()
    {
        $url_hash = $this->xmltv_url_params[PARAM_HASH];
        try {
            $table_pic = self::INDEX_PICONS;
            $table_ch = self::INDEX_CHANNELS;
            $db = $this->open_sqlite_db($url_hash);
            if ($db === false) {
                hd_debug_print("File is indexing or downloading, skipped");
                return;
            }

            if (is_null($db)) {
                throw new Exception("Problem with open SQLite db! Possible url not set");
            }

            if ($this->is_all_indexes_valid(array($table_ch, $table_pic))) {
                $channels = $db->querySingle("SELECT count(*) FROM $table_ch;");
                if (!empty($channels) && (int)$channels !== 0) {
                    hd_debug_print("EPG channels info already indexed", true);
                    return;
                }
            }

            hd_debug_print("Start reindex channels and picons...");

            $this->perf->reset('reindex');

            $this->remove_indexes(array($table_ch, $table_pic));

            $this->set_index_locked($url_hash, true);

            $db->exec("CREATE TABLE $table_ch(alias STRING PRIMARY KEY not null, channel_id STRING not null, picon_hash STRING);");
            $db->exec("CREATE TABLE $table_pic(picon_hash STRING PRIMARY KEY not null, picon_url STRING);");

            $db->exec('PRAGMA journal_mode=MEMORY;');
            $db->exec('BEGIN;');

            $stm_channels = $db->prepare("INSERT OR REPLACE INTO $table_ch (alias, channel_id, picon_hash) VALUES(:alias, :channel_id, :picon_hash);");
            $stm_channels->bindParam(":alias", $alias);
            $stm_channels->bindParam(":channel_id", $channel_id);
            $stm_channels->bindParam(":picon_hash", $picon_hash);

            $stm_picons = $db->prepare("INSERT OR REPLACE INTO $table_pic (picon_hash, picon_url) VALUES(:picon_hash, :picon_url);");
            $stm_picons->bindParam(":picon_hash", $picon_hash);
            $stm_picons->bindParam(":picon_url", $picon_url);

            $cached_file = $this->cache_dir . DIRECTORY_SEPARATOR . $url_hash . ".xmltv";
            $file = self::open_xmltv_file($cached_file);
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
                foreach ($xml_node->getElementsByTagName('channel') as $tag) {
                    $channel_id = $tag->getAttribute('id');
                }

                if (empty($channel_id)) continue;

                foreach ($xml_node->getElementsByTagName('icon') as $tag) {
                    if (preg_match(HTTP_PATTERN, $tag->getAttribute('src'))) {
                        $picon_url = $tag->getAttribute('src');
                        if (!empty($picon_url)) {
                            $picon_hash = hash('md5', $picon_url);
                            $stm_picons->execute();
                            break;
                        }
                    }
                }

                $alias = $channel_id;
                $stm_channels->execute();

                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $alias = mb_convert_case($tag->nodeValue, MB_CASE_LOWER, "UTF-8");
                    $stm_channels->execute();
                }
            }
            fclose($file);
            $db->exec('COMMIT;');

            $result = $db->querySingle("SELECT count(*) FROM $table_ch;");
            $channels = empty($result) ? 0 : (int)$result;

            $result = $db->querySingle("SELECT count(*) FROM $table_pic;");
            $picons = empty($result) ? 0 : (int)$result;

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport('reindex');

            hd_debug_print("Total entries id's: $channels");
            hd_debug_print("Total known picons: $picons");
            hd_debug_print("Reindexing EPG channels done: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
            hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");

        } catch (Exception $ex) {
            hd_debug_print("Reindexing EPG channels failed");
            print_backtrace_exception($ex);
        }

        $this->set_index_locked($url_hash,false);
        hd_debug_print_separator();
    }

    /**
     * @inheritDoc
     * @override
     */
    public function index_xmltv_positions()
    {
        hd_debug_print("Indexing positions for: {$this->xmltv_url_params[PARAM_URI]}", true);
        $url_hash = $this->xmltv_url_params[PARAM_HASH];

        try {
            $db = $this->open_sqlite_db($url_hash);
            if ($db === false) {
                hd_debug_print("File is indexing now, skipped");
                return;
            }

            if (is_null($db)) {
                throw new Exception("Problem with open SQLite db! Possible url not set");
            }

            $table_pos = self::INDEX_ENTRIES;
            if ($this->is_all_indexes_valid(array($table_pos))) {
                $total_pos = $db->querySingle("SELECT count(*) FROM $table_pos;");
                if (!empty($total_pos) && (int)$total_pos !== 0) {
                    hd_debug_print("EPG positions info already indexed", true);
                    return;
                }
            }

            hd_debug_print("Start reindex positions...");

            $this->perf->reset('reindex');

            $this->remove_index($table_pos);

            $this->set_index_locked($url_hash, true);

            $db->exec("CREATE TABLE $table_pos (channel_id STRING PRIMARY KEY not null, start INTEGER, end INTEGER);");
            $db->exec('PRAGMA journal_mode=MEMORY;');
            $db->exec('BEGIN;');

            hd_debug_print("Begin transactions...");

            $stm = $db->prepare("INSERT INTO $table_pos (channel_id, start, end) VALUES(:channel_id, :start, :end);");
            $stm->bindParam(":channel_id", $prev_channel);
            $stm->bindParam(":start", $start_program_block);
            $stm->bindParam(":end", $tag_end_pos);

            $cached_file = $this->cache_dir . DIRECTORY_SEPARATOR . $url_hash . ".xmltv";
            if (!file_exists($cached_file)) {
                throw new Exception("cache file $cached_file not exist");
            }

            $file = self::open_xmltv_file($cached_file);

            $start_program_block = 0;
            $prev_channel = null;
            $i = 0;
            while (!feof($file)) {
                $tag_start_pos = ftell($file);
                $line = stream_get_line($file, 0, "</programme>");
                if ($line === false) break;

                $offset = strpos($line, '<programme');
                if ($offset === false) {
                    // check if end
                    $end_tv = strpos($line, "</tv>");
                    if ($end_tv !== false) {
                        $tag_end_pos = $end_tv + $tag_start_pos;
                        $stm->execute();
                        break;
                    }

                    // if open tag not found - skip chunk
                    continue;
                }

                // end position include closing tag!
                $tag_end_pos = ftell($file);
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
                    $prev_channel = $channel_id;
                    $start_program_block = $tag_start_pos;
                } else if ($prev_channel !== $channel_id) {
                    $tag_end_pos = $tag_start_pos;
                    $stm->execute();
                    if (($i % 100) === 0) {
                        $db->exec('COMMIT;BEGIN;');
                    }
                    $prev_channel = $channel_id;
                    $start_program_block = $tag_start_pos;
                }
            }

            hd_debug_print("End transactions...");
            $db->exec('COMMIT;');

            $result = $db->querySingle("SELECT count(channel_id) FROM $table_pos;");
            $total_epg = empty($result) ? 0 : (int)$result;

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport('reindex');

            hd_debug_print("Total unique epg id's indexed: $total_epg");
            hd_debug_print("Reindexing EPG positions done: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
            hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        } catch (Exception $ex) {
            hd_debug_print("Reindexing EPG positions failed");
            print_backtrace_exception($ex);
        }

        $this->set_index_locked($url_hash, false);
        hd_debug_print_separator();
    }

    /**
     * @inheritDoc
     * @override
     */
    public function remove_index($name)
    {
        $db = $this->open_sqlite_db($this->xmltv_url_params[PARAM_HASH]);
        if ($db === false) {
            hd_debug_print("Unable to drop table because index $name is locked");
            return false;
        }

        if (is_null($db)) {
            hd_debug_print("Problem with open SQLite db! Possible url not set");
        } else {
            hd_debug_print("Remove index: $name");
            $db->exec("DROP TABLE IF EXISTS $name;");
        }
        return true;
    }

    /**
     * @inheritDoc
     * @override
     */
    public function remove_indexes($names)
    {
        $db = $this->open_sqlite_db($this->xmltv_url_params[PARAM_HASH]);
        if ($db === false) {
            hd_debug_print("Unable to drop table because current index is locked");
            return;
        }

        if (is_null($db)) {
            hd_debug_print("Problem with open SQLite db! Possible url not set");
            return;
        }

        foreach ($names as $name) {
            $db->exec("DROP TABLE IF EXISTS $name;");
        }
    }

    /**
     * @inheritDoc
     * @override
     */
    public function get_indexes_info($hash = null)
    {
        hd_debug_print(null, true);
        $result = array(self::INDEX_CHANNELS => -1, self::INDEX_PICONS => -1, self::INDEX_ENTRIES => -1);

        $hash = is_null($hash) ? $this->xmltv_url_params[PARAM_HASH] : $hash;
        $db = $this->open_sqlite_db($hash);
        if ($db === false) {
            hd_debug_print("Unable to drop table because current index is locked");
            return $result;
        }

        if (is_null($db)) {
            hd_debug_print("Problem with open SQLite db! Possible url not set");
            return $result;
        }

        foreach ($result as $key => $name) {
            $res = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='$key';");
            if (!empty($res)) {
                $result[$key] = $db->querySingle("SELECT count(*) FROM $key;");
            }
        }
        return $result;
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @inheritDoc
     * @override
     */
    protected function is_all_indexes_valid($names)
    {
        hd_debug_print(null, true);

        $db = $this->open_sqlite_db($this->xmltv_url_params[PARAM_HASH]);
        if ($db === false) {
            hd_debug_print("Indexing is in process now");
            return false;
        }

        if (is_null($db)) {
            hd_debug_print("Problem with open SQLite db! Possible url not set");
            return false;
        }

        foreach ($names as $name) {
            if (!$db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='$name';")) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function clear_memory_index($id = null)
    {
        if (empty($id)) {
            foreach ($this->epg_db as $db) {
                $db->close();
            }
            $this->epg_db = array();
        } else if (isset($this->epg_db[$id])) {
            $this->epg_db[$id]->close();
            unset($this->epg_db[$id]);
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// private methods

    /**
     * open sqlite database
     * @param string $db_name
     * @return SQLite3|false|null
     */
    private function open_sqlite_db($db_name)
    {
        if (empty($db_name)) {
            hd_debug_print("No handler for empty url!");
            return null;
        }

        if ($this->is_index_locked($db_name)) {
            hd_debug_print("File is indexing or downloading, skipped");
            return false;
        }

        if (!isset($this->epg_db[$db_name])) {
            $index_name = $this->cache_dir . DIRECTORY_SEPARATOR . $db_name . $this->index_ext;
            hd_debug_print("Open db: $index_name", true);
            $flags = SQLITE3_OPEN_READWRITE;
            if (!file_exists($index_name)) {
                $flags |= SQLITE3_OPEN_CREATE;
            }
            $this->epg_db[$db_name] = new SQLite3($index_name, $flags, '');
            $this->epg_db[$db_name]->busyTimeout(60);
        }

        return $this->epg_db[$db_name];
    }
}
