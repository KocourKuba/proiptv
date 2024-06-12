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

require_once 'epg_manager.php';

class Epg_Manager_Sql extends Epg_Manager
{
    protected $index_ext = '.db';

    /**
     * @inheritDoc
     * @override
     */
    public function get_picons()
    {
        $picons = array();
        $filedb = $this->open_sqlite_db(false);
        if (!is_null($filedb)) {
            $res = $filedb->query('SELECT alias, picon FROM channels WHERE picon != "";');
            if ($res) {
                while($columns = $res->fetchArray(SQLITE3_ASSOC)) {
                    $picons[$columns['alias']] = $columns['picon'];
                }
            }
            $filedb->close();
        }

        return $picons;
    }

    /**
     * @inheritDoc
     * @override
     */
    public function index_xmltv_channels()
    {
        try {
            $filedb = $this->open_sqlite_db(false);
            if (!is_null($filedb)) {
                $channels = $filedb->querySingle("SELECT count(*) FROM channels;");
                if (!empty($channels) && (int)$channels !== 0) {
                    hd_debug_print("EPG channels info already indexed", true);
                    $filedb->close();
                    return;
                }

                $filedb->close();
                $filedb = null;
            }

            hd_debug_print("Start reindex channels...");

            $this->set_index_locked(true);

            $t = microtime(true);

            $filedb = $this->open_sqlite_db(false, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            if (is_null($filedb)) {
                throw new Exception("Fatal problem: can't create database!");
            }

            $filedb->exec('DROP TABLE IF EXISTS channels;');
            $filedb->exec('CREATE TABLE channels (alias STRING, channel_id STRING, picon STRING);');
            $filedb->exec('PRAGMA journal_mode=MEMORY;');
            $filedb->exec('BEGIN;');

            $stm = $filedb->prepare('INSERT OR REPLACE INTO channels(alias, channel_id, picon) VALUES(:alias, :channel_id, :picon);');
            $stm->bindParam(":alias", $alias);
            $stm->bindParam(":channel_id", $channel_id);
            $stm->bindParam(":picon", $picon);

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
                    }
                }

                $this->xmltv_channels[$channel_id] = $channel_id;
                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $alias = $tag->nodeValue;
                    $stm->execute();
                }
            }
            fclose($file);
            $filedb->exec('COMMIT;');

            $result = $filedb->querySingle('SELECT count(*) FROM channels;');
            $channels = empty($result) ? 0 : (int)$result;

            $result = $filedb->querySingle('SELECT count(DISTINCT picon) FROM channels WHERE picon != "";');
            $picons = empty($result) ? 0 : (int)$result;

            $filedb->close();

            hd_debug_print("Total channels id's: $channels");
            hd_debug_print("Total picons: $picons");
            hd_debug_print("Reindexing EPG channels done: " . (microtime(true) - $t) . " secs");
            hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
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
        try {
            $filedb = $this->open_sqlite_db(true);
            if (!is_null($filedb)) {
                $total_pos = $filedb->querySingle('SELECT count(*) FROM positions;');
                if (!empty($total_pos) && (int)$total_pos !== 0) {
                    hd_debug_print("EPG positions info already indexed", true);
                    return;
                }
                $filedb->close();
                $filedb = null;
            }

            hd_debug_print("Start reindex positions...");

            $this->set_index_locked(true);

            $t = microtime(true);

            $filedb = $this->open_sqlite_db(true,SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            if (is_null($filedb)) {
                throw new Exception("Fatal problem: can't create database!");
            }

            $filedb->exec('DROP TABLE IF EXISTS positions;');
            $filedb->exec('CREATE TABLE positions (channel_id STRING, start INTEGER, end INTEGER);');
            $filedb->exec('PRAGMA journal_mode=WAL;');
            $filedb->exec('BEGIN;');

            hd_debug_print("Begin transactions...");

            $stm = $filedb->prepare('INSERT INTO positions(channel_id, start, end) VALUES(:channel_id, :start, :end);');
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
                        $filedb->exec('COMMIT;');
                        $filedb->exec('BEGIN;');
                    }
                    $prev_channel = $channel_id;
                    $start_program_block = $tag_start_pos;
                }
            }

            hd_debug_print("End transactions...");
            $filedb->exec('COMMIT;');

            $result = $filedb->querySingle('SELECT count(channel_id) FROM positions;');
            $total_epg = empty($result) ? 0 : (int)$result;

            hd_debug_print("Total unique epg id's indexed: $total_epg");
            hd_debug_print("Reindexing EPG positions done: " . (microtime(true) - $t) . " secs");
            hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
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
    protected function clear_index()
    {
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function load_program_index($channel)
    {
        $channel_position = array();

        try {
            $pos_db = $this->open_sqlite_db(true);
            if (is_null($pos_db)) {
                throw new Exception("EPG not indexed!");
            }

            $channel_title = $channel->get_title();
            $epg_ids = array_values($channel->get_epg_ids());

            $channels_db = $this->open_sqlite_db(false);
            if (!is_null($channels_db)) {
                $placeHolders = implode(',', array_fill(0, count($epg_ids), '?'));
                $stm = $channels_db->prepare("SELECT DISTINCT channel_id FROM channels WHERE alias IN ($placeHolders);");
                if ($stm !== false) {
                    foreach ($epg_ids as $index => $val) {
                        $stm->bindValue($index + 1, $val);
                    }

                    $res = $stm->execute();
                    if (!$res) {
                        throw new Exception("Query failed for epg's: " . raw_json_encode($epg_ids) . " ($channel_title)");
                    }

                    while ($row = $res->fetchArray(SQLITE3_NUM)) {
                        $epg_ids[] = (string)$row[0];
                    }
                }
            }

            $epg_ids = array_values(array_unique($epg_ids));
            hd_debug_print("Found epg_ids: " . json_encode($epg_ids), true);
            $channel_id = $channel->get_id();
            if (!empty($epg_ids)) {
                hd_debug_print("Load position indexes for: $channel_id ($channel_title), search epg id's: " . raw_json_encode($epg_ids), true);
                $placeHolders = implode(',', array_fill(0, count($epg_ids), '?'));
                $stmt = $pos_db->prepare("SELECT start, end FROM positions WHERE channel_id IN ($placeHolders);");
                if ($stmt !== false) {
                    foreach ($epg_ids as $index => $val) {
                        $stmt->bindValue($index + 1, $val);
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
     * @param $db_type - true positions db, false - channels db
     * @param int $mode
     * @return SQLite3|null
     */
    protected function open_sqlite_db($db_type, $mode = SQLITE3_OPEN_READONLY)
    {
        try {
            $index_name = $db_type ? $this->get_positions_index_name() : $this->get_channels_index_name();
            if (file_exists($index_name)) {
                hd_debug_print("Open db: $index_name", true);
                return new SQLite3($index_name, $mode, '');
            }

            if ($mode & SQLITE3_OPEN_CREATE) {
                hd_debug_print("Create db: $index_name", true);
                return new SQLite3($index_name, $mode, '');
            }

            hd_debug_print("Database $index_name is not exist!");
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        return null;
    }
}
