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
    /**
     * contains index for current xmltv file
     * @var SQLite3
     */
    public $xmltv_db_index;

    /**
     * @inheritDoc
     * @override
     */
    public function get_picons()
    {
        if (is_null($this->xmltv_db_index)) {
            return null;
        }

        $sql = "SELECT alias, picon FROM channels WHERE picon != '';";
        $stm = $this->xmltv_db_index->prepare($sql);
        $stm->bindParam(1, $channel_name);
        $res = $stm->execute();
        $picons = array();
        while($ar = $res->fetchArray(SQLITE3_ASSOC)) {
            $picons[$ar['alias']] = $ar['picon'];
        }

        return $picons;
    }

    /**
     * @inheritDoc
     * @override
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

        hd_debug_print("Total channels id's: $channels");
        hd_debug_print("Total picons: $picons");
        hd_debug_print("------------------------------------------------------------");
        hd_debug_print("Reindexing EPG channels done: " . (microtime(1) - $t) . " secs");

        HD::ShowMemoryUsage();
    }

    /**
     * @inheritDoc
     * @override
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
            hd_debug_print("------------------------------------------------------------");
            hd_debug_print("Reindexing EPG program done: " . (microtime(1) - $t) . " secs");
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
        }

        $this->set_index_locked(false);

        HD::ShowMemoryUsage();
        hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @inheritDoc
     * @override
     */
    protected function clear_index()
    {
        parent::clear_index();

        hd_debug_print("clear sqlite db");
        unset($this->xmltv_db_index);
        $this->xmltv_db_index = null;
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function load_program_index($channel)
    {
        if ($this->xmltv_db_index === null) {
            throw new Exception("EPG not indexed!");
        }

        $channel_title = $channel->get_title();
        $epg_ids = $channel->get_epg_ids();
        if (empty($epg_ids)) {
            $sql = "SELECT DISTINCT channel_id FROM channels WHERE alias=?;";
            $stm = $this->xmltv_db_index->prepare($sql);
            $stm->bindParam(1, $channel_title);

            $res = $stm->execute();
            // We expect that only one row returned!
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $epg_id = isset($row['channel_id']) ? $row['channel_id'] : null;
                if (empty($epg_id)) {
                    throw new Exception("No EPG defined for channel: {$channel->get_id()} ($channel_title)");
                }

                $epg_ids[] = $epg_id;
            }
        }

        hd_debug_print("epg id's: " . json_encode($epg_ids), true);
        $placeHolders = implode(',', array_fill(0, count($epg_ids), '?'));
        $sql = "SELECT pos FROM programs WHERE channel_id = (SELECT DISTINCT channel_id FROM channels WHERE channel_id IN ($placeHolders));";
        $stmt = $this->xmltv_db_index->prepare($sql);
        foreach ($epg_ids as $index => $val) {
            $stmt->bindValue($index + 1, $val);
        }

        $res = $stmt->execute();
        if (!$res) {
            throw new Exception("No data for epg {$channel->get_id()} ($channel_title)");
        }

        $positions = array();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $positions[] = $row['pos'];
        }

        return $positions;
    }

    /**
     * @return string
     */
    protected function get_db_filename()
    {
        return $this->get_cache_stem(".db");
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
