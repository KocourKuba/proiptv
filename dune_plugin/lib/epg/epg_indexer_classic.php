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

class Epg_Indexer_Classic extends Epg_Indexer
{
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
     * @inheritDoc
     * @override
     */
    public function init($cache_dir)
    {
        parent::init($cache_dir);

        $this->index_ext = '.index';
    }

    /**
     * @inheritDoc
     * @override
     */
    public function load_program_index($channel)
    {
        try {
            $this->perf->reset('start');

            if (empty($this->xmltv_positions[$this->url_hash])) {
                $index_file = $this->get_index_name(self::INDEX_POSITIONS);
                hd_debug_print("load positions index $$index_file");
                $data = parse_json_file($index_file);
                if (empty($data)) {
                    throw new Exception("load positions index failed '$index_file'");
                }
                $this->xmltv_positions[$this->url_hash] = $data;
                $this->perf->setLabel('end_load');

                $report = $this->perf->getFullReport();
                hd_debug_print("Load time: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
            }

            if (empty($this->xmltv_channels[$this->url_hash])) {
                $index_file = $this->get_index_name(self::INDEX_CHANNELS);
                hd_debug_print("load channels index $$index_file");
                $this->xmltv_channels[$this->url_hash] = parse_json_file($index_file);
                if (empty($this->xmltv_channels[$this->url_hash])) {
                    unset($this->xmltv_channels[$this->url_hash]);
                    throw new Exception("load channels index failed '$index_file'");
                }

                $this->perf->setLabel('end_load');
                $report = $this->perf->getFullReport();
                hd_debug_print("Load time: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
            }

            $this->perf->setLabel('fetch');
            // try found channel_id by epg_id
            $epg_ids = $channel->get_epg_ids();
            foreach ($epg_ids as $epg_id) {
                $epg_id_lower = mb_convert_case($epg_id, MB_CASE_LOWER, "UTF-8");
                if (array_key_exists($epg_id_lower, $this->xmltv_channels[$this->url_hash])) {
                    $channel_id = $this->xmltv_channels[$this->url_hash][$epg_id_lower];
                    break;
                }
            }

            if (empty($channel_id)) {
                throw new Exception("index positions for epg '{$channel->get_title()}' is not exist");
            }

            if (!isset($this->xmltv_positions[$this->url_hash][$channel_id])) {
                throw new Exception("index positions for epg $channel_id is not exist");
            }

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport('fetch');

            hd_debug_print("Fetch positions "
                . count($this->xmltv_positions[$this->url_hash][$channel_id])
                . " for '$channel_id' by channel: '{$channel->get_title()}' ({$channel->get_id()}) done in: {$report[Perf_Collector::TIME]} secs");

            return $this->xmltv_positions[$this->url_hash][$channel_id];
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        return array();
    }

    /**
     * @inheritDoc
     * @override
     */
    public function get_picon($aliases)
    {
        foreach ($this->active_sources as $source) {
            $this->set_url($source);
            foreach ($aliases as $alias) {
                if (!empty($alias) && isset($this->xmltv_picons[$this->url_hash][$alias])) {
                    return $this->xmltv_picons[$this->url_hash][$alias];
                }
            }
        }

        return '';
    }

    /**
     * @inheritDoc
     * @override
     */
    public function index_xmltv_channels()
    {
        if ($this->is_current_index_locked()) {
            hd_debug_print("File is indexing or downloading, skipped");
            return;
        }

        $this->perf->reset('start');

        $channels_file = $this->get_index_name(self::INDEX_CHANNELS);
        $picons_file = $this->get_index_name(self::INDEX_PICONS);
        if ($this->is_all_indexes_valid(array(self::INDEX_CHANNELS, self::INDEX_PICONS))) {
            hd_debug_print("Load cache channels and picons index: $channels_file");
            $this->xmltv_channels[$this->url_hash] = parse_json_file($channels_file);
            $this->xmltv_picons[$this->url_hash] = parse_json_file($picons_file);

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport();
            hd_debug_print("ParseFile: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
            hd_debug_print_separator();
            return;
        }

        $this->xmltv_channels[$this->url_hash] = array();
        $this->xmltv_picons[$this->url_hash] = array();
        $this->perf->setLabel('reindex');

        try {
            $this->set_index_locked(true);

            hd_debug_print_separator();
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
                foreach ($xml_node->getElementsByTagName('channel') as $tag) {
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

                $ls_channel = mb_convert_case($channel_id, MB_CASE_LOWER, "UTF-8");
                $this->xmltv_channels[$this->url_hash][$ls_channel] = $channel_id;
                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $alias = mb_convert_case($tag->nodeValue, MB_CASE_LOWER, "UTF-8");
                    $this->xmltv_channels[$this->url_hash][$alias] = $channel_id;
                    if (!empty($picon)) {
                        $this->xmltv_picons[$this->url_hash][$alias] = $picon;
                    }
                }
            }
            fclose($file);

            store_to_json_file($channels_file, $this->xmltv_channels[$this->url_hash]);
            store_to_json_file($picons_file, $this->xmltv_picons[$this->url_hash]);

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport('reindex');
            hd_debug_print("Total entries id's: " . count($this->xmltv_channels[$this->url_hash]));
            hd_debug_print("Total known picons: " . count($this->xmltv_picons[$this->url_hash]));
            hd_debug_print("Reindexing EPG channels done: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
            hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        } catch (Exception $ex) {
            hd_debug_print("Reindexing EPG channels failed");
            print_backtrace_exception($ex);
        }

        $this->set_index_locked(false);
        hd_debug_print_separator();
    }

    /**
     * @inheritDoc
     * @override
     */
    public function index_xmltv_positions()
    {
        hd_debug_print("Indexing positions for: $this->xmltv_url", true);

        if ($this->is_current_index_locked()) {
            hd_debug_print("File is indexing now, skipped");
            return;
        }

        $this->perf->reset('start');

        $cache_valid = false;
        $positions_file = $this->get_index_name(self::INDEX_POSITIONS);
        if ($this->is_all_indexes_valid(array(self::INDEX_POSITIONS))) {
            hd_debug_print("Load cache program index: $positions_file");
            $this->xmltv_positions[$this->url_hash] = parse_json_file($positions_file);

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport();

            hd_debug_print("Load time: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");

            if ($this->xmltv_positions[$this->url_hash] !== false) {
                $cache_valid = true;
            }
        }

        if ($cache_valid) {
            return;
        }

        try {

            hd_debug_print("Start reindex: $positions_file");

            $this->perf->setLabel('reindex');

            $this->remove_index(self::INDEX_POSITIONS);

            $this->set_index_locked(true);
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
                hd_debug_print("Save index: $positions_file", true);
                store_to_json_file($this->get_index_name(self::INDEX_POSITIONS), $xmltv_index);
                $this->xmltv_positions[$this->url_hash] = $xmltv_index;
            }

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport('reindex');
            hd_debug_print("Total unique epg id's indexed: " . count($xmltv_index));
            hd_debug_print("Reindexing EPG program done: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
            hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        } catch (Exception $ex) {
            hd_debug_print("Reindexing EPG positions failed");
            print_backtrace_exception($ex);
        }

        $this->set_index_locked(false);
        hd_debug_print_separator();
    }

    /**
     * @inheritDoc
     * @override
     */
    public function remove_index($name)
    {
        $name = $this->get_index_name($name);
        if (file_exists($name)) {
            hd_debug_print("Remove index: $name");
            unlink($name);
        }
        return true;
    }

    /**
     * @inheritDoc
     * @override
     */
    public function remove_indexes($names)
    {
        foreach ($names as $name) {
            $filename = $this->get_index_name($name);
            if (file_exists($filename)) {
                hd_debug_print("Remove index: $filename");
                @unlink($filename);
            }
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param string $name
     * @return string
     */
    protected function get_index_name($name)
    {
        return $this->get_cache_stem("_$name$this->index_ext");
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function get_indexes_valid($names)
    {
        foreach ($names as $name) {
            $name = $this->get_index_name($name);
            $result[] = file_exists($name) && filesize($name) !== 0;
        }

        return empty($result) ? false : $result;
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function is_all_indexes_valid($names)
    {
        foreach ($names as $name) {
            $name = $this->get_index_name($name);
            if (!file_exists($name) || filesize($name) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function clear_memory_index($id = '')
    {
        hd_debug_print("clear legacy index");

        if (empty($id)) {
            $this->xmltv_picons = array();
            $this->xmltv_channels = array();
            $this->xmltv_positions = array();
        } else {
            unset($this->xmltv_picons[$id], $this->xmltv_channels[$id], $this->xmltv_positions[$id]);
        }
    }
}
