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
     * @var SQLite3
     */
    protected $epg_db;

    /**
     * @inheritDoc
     * @override
     */
    public function init($cache_dir, $url)
    {
        parent::init($cache_dir, $url);

        $this->index_ext = '.db';
    }

    /**
     * @inheritDoc
     * @override
     */
    public function get_picon($alias)
    {
        $picon = '';
        if ($this->open_sqlite_db()) {
            $alias = SQLite3::escapeString($alias);
            $qry = "SELECT distinct (picon) FROM picons INNER JOIN channels ON picons.picon_hash = channels.picon_hash WHERE alias = '$alias';";
            $picon = $this->epg_db->querySingle($qry);
        }

        return $picon;
    }

    /**
     * @inheritDoc
     * @override
     */
    public function load_program_index($channel)
    {
        $channel_position = array();

        try {
            if (!$this->open_sqlite_db()) {
                throw new Exception("EPG not indexed!");
            }

            $channel_title = $channel->get_title();
            $epg_ids = array_values($channel->get_epg_ids());

            $placeHolders = implode(',', array_fill(0, count($epg_ids), '?'));
            $stm = $this->epg_db->prepare("SELECT DISTINCT channel_id FROM channels WHERE alias IN ($placeHolders);");
            if ($stm !== false) {
                foreach ($epg_ids as $index => $val) {
                    $stm->bindValue($index + 1, mb_convert_case(SQLite3::escapeString($val), MB_CASE_LOWER, "UTF-8"));
                }

                $res = $stm->execute();
                if (!$res) {
                    throw new Exception("Query failed for epg's: " . raw_json_encode($epg_ids) . " ($channel_title)");
                }

                while ($row = $res->fetchArray(SQLITE3_NUM)) {
                    $epg_ids[] = (string)$row[0];
                }
            }

            $epg_ids = array_values(array_unique($epg_ids));
            hd_debug_print("Found epg_ids: " . raw_json_encode($epg_ids), true);
            $channel_id = $channel->get_id();
            if (!empty($epg_ids)) {
                hd_debug_print("Load position indexes for: $channel_id ($channel_title)", true);
                $placeHolders = implode(',', array_fill(0, count($epg_ids), '?'));
                $stmt = $this->epg_db->prepare("SELECT start, end FROM positions WHERE channel_id IN ($placeHolders);");
                if ($stmt !== false) {
                    foreach ($epg_ids as $index => $val) {
                        $stmt->bindValue($index + 1, SQLite3::escapeString($val));
                    }

                    $res = $stmt->execute();
                    if (!$res) {
                        throw new Exception("Query failed for epg's: " . raw_json_encode($epg_ids) . " ($channel_title)");
                    }

                    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                        $data = array();
                        foreach ($row as $key => $col) {
                            $data[$key] = $col;
                        }
                        $channel_position[] = $data;
                    }
                }
            }

            if (empty($channel_position)) {
                throw new Exception("No positions for channel $channel_id ($channel_title) and epg id's: ". raw_json_encode($epg_ids));
            }
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        hd_debug_print("Channel positions: " . raw_json_encode($channel_position), true);
        return $channel_position;
    }

    /**
     * @inheritDoc
     * @override
     */
    public function index_xmltv_channels()
    {
        try {
            if ($this->is_index_valid('channels') && $this->is_index_valid('picons')) {
                $channels = $this->epg_db->querySingle('SELECT count(*) FROM channels;');
                if (!empty($channels) && (int)$channels !== 0) {
                    hd_debug_print("EPG channels info already indexed", true);
                    return;
                }
            }

            $this->set_index_locked(true);

            hd_debug_print_separator();
            hd_debug_print("Start reindex channels and picons...");

            $t = microtime(true);

            $this->epg_db->exec('DROP TABLE IF EXISTS channels;');
            $this->epg_db->exec('CREATE TABLE channels (alias STRING not null, channel_id STRING not null, picon_hash STRING);');

            $this->epg_db->exec('DROP TABLE IF EXISTS picons;');
            $this->epg_db->exec('CREATE TABLE picons (picon_hash STRING UNIQUE PRIMARY KEY not null, picon STRING);');

            $this->epg_db->exec('PRAGMA journal_mode=MEMORY;');
            $this->epg_db->exec('BEGIN;');

            $stm_channels = $this->epg_db->prepare('INSERT OR REPLACE INTO channels(alias, channel_id, picon_hash) VALUES(:alias, :channel_id, :picon_hash);');
            $stm_channels->bindParam(":alias", $alias);
            $stm_channels->bindParam(":channel_id", $channel_id);
            $stm_channels->bindParam(":picon_hash", $picon_hash);

            $stm_picons = $this->epg_db->prepare('INSERT OR REPLACE INTO picons(picon_hash, picon) VALUES(:picon_hash, :picon);');
            $stm_picons->bindParam(":picon_hash", $picon_hash);
            $stm_picons->bindParam(":picon", $picon);

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

                foreach ($xml_node->getElementsByTagName('icon') as $tag) {
                    if (preg_match(HTTP_PATTERN, $tag->getAttribute('src'))) {
                        $picon = $tag->getAttribute('src');
                        if (!empty($picon)) {
                            $picon_hash = hash('crc32', $picon);
                            $stm_picons->execute();
                            break;
                        }
                    }
                }

                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $alias = mb_convert_case($tag->nodeValue, MB_CASE_LOWER, "UTF-8");
                    $stm_channels->execute();
                }
            }
            fclose($file);
            $this->epg_db->exec('COMMIT;');

            $result = $this->epg_db->querySingle('SELECT count(*) FROM channels;');
            $channels = empty($result) ? 0 : (int)$result;

            $result = $this->epg_db->querySingle('SELECT count(*) FROM picons;');
            $picons = empty($result) ? 0 : (int)$result;

            hd_debug_print("Total entries id's: $channels");
            hd_debug_print("Total known picons: $picons");
            hd_debug_print("Reindexing EPG channels done: " . (microtime(true) - $t) . " secs");
            hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
            HD::ShowMemoryUsage();
            hd_debug_print_separator();

        } catch (Exception $ex) {
            hd_debug_print("Reindexing EPG channels failed");
            print_backtrace_exception($ex);
        }

        $this->set_index_locked(false);
    }

    /**
     * @inheritDoc
     * @override
     */
    public function index_xmltv_positions()
    {
        if ($this->is_index_locked()) {
            hd_debug_print("File is indexing now, skipped");
            return;
        }

        try {
            if ($this->is_index_valid('picons')) {
                $total_pos = $this->epg_db->querySingle('SELECT count(*) FROM positions;');
                if (!empty($total_pos) && (int)$total_pos !== 0) {
                    hd_debug_print("EPG positions info already indexed", true);
                    return;
                }
            }

            hd_debug_print_separator();
            hd_debug_print("Start reindex positions...");

            $this->set_index_locked(true);

            $t = microtime(true);

            $this->epg_db->exec('DROP TABLE IF EXISTS positions;');
            $this->epg_db->exec('CREATE TABLE positions (channel_id STRING, start INTEGER, end INTEGER);');
            $this->epg_db->exec('PRAGMA journal_mode=MEMORY;');
            $this->epg_db->exec('BEGIN;');

            hd_debug_print("Begin transactions...");

            $stm = $this->epg_db->prepare('INSERT INTO positions(channel_id, start, end) VALUES(:channel_id, :start, :end);');
            $stm->bindParam(":channel_id", $prev_channel);
            $stm->bindParam(":start", $start_program_block);
            $stm->bindParam(":end", $tag_end_pos);

            $cached_file = $this->get_cached_filename();
            if (!file_exists($cached_file)) {
                throw new Exception("cache file not exist");
            }

            $file = $this->open_xmltv_file();

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
                        $this->epg_db->exec('COMMIT;BEGIN;');
                    }
                    $prev_channel = $channel_id;
                    $start_program_block = $tag_start_pos;
                }
            }

            hd_debug_print("End transactions...");
            $this->epg_db->exec('COMMIT;');

            $result = $this->epg_db->querySingle('SELECT count(channel_id) FROM positions;');
            $total_epg = empty($result) ? 0 : (int)$result;

            hd_debug_print("Total unique epg id's indexed: $total_epg");
            hd_debug_print("Reindexing EPG positions done: " . (microtime(true) - $t) . " secs");
            hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
            HD::ShowMemoryUsage();
            hd_debug_print_separator();
        } catch (Exception $ex) {
            hd_debug_print("Reindexing EPG positions failed");
            print_backtrace_exception($ex);
        }

        $this->set_index_locked(false);
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @inheritDoc
     * @override
     */
    protected function is_index_valid($name)
    {
        return $this->check_table($name);
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function check_index_version()
    {
        return true;
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function clear_memory_index()
    {
        if (!is_null($this->epg_db)) {
            $this->epg_db->close();
            $this->epg_db = null;
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// private methods

    /**
     * @param $name string
     * @return bool
     */
    private function check_table($name)
    {
        if ($this->open_sqlite_db()) {
            $table = $this->epg_db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='$name';");
            return !empty($table);
        }

        return false;
    }

    /**
     * open sqlite database
     * @return bool
     */
    private function open_sqlite_db()
    {
        if ($this->epg_db === null) {
            try {
                $index_name = $this->get_cache_stem("_epg$this->index_ext");
                hd_debug_print("Open db: $index_name", true);
                $this->epg_db = new SQLite3($index_name, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, '');
            } catch (Exception $ex) {
                print_backtrace_exception($ex);
            }
        }

        return $this->epg_db !== null;
    }
}
