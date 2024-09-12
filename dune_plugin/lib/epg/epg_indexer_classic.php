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
     * contains indexes for xmltv file (hash)
     * @var array
     */
    protected $xmltv_indexes;

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

            if (empty($this->xmltv_indexes[$this->url_hash][self::INDEX_ENTRIES])) {
                $index_file = $this->get_index_name(self::INDEX_ENTRIES);
                hd_debug_print("load positions index $$index_file");
                $data = parse_json_file($index_file);
                if ($data === false) {
                    throw new Exception("load positions index failed '$index_file'");
                }
                $this->xmltv_indexes[$this->url_hash][self::INDEX_ENTRIES] = $data;
                $this->perf->setLabel('end_load');

                $report = $this->perf->getFullReport();
                hd_debug_print("Load time: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
            }

            if (empty($this->xmltv_indexes[$this->url_hash][self::INDEX_CHANNELS])) {
                $index_file = $this->get_index_name(self::INDEX_CHANNELS);
                hd_debug_print("load channels index $$index_file");
                $data = parse_json_file($index_file);
                if ($data === false) {
                    throw new Exception("load channels index failed '$index_file'");
                }

                $this->xmltv_indexes[$this->url_hash][self::INDEX_CHANNELS] = $data;
                $this->perf->setLabel('end_load');

                $report = $this->perf->getFullReport();
                hd_debug_print("Load time: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
            }

            $this->perf->setLabel('fetch');
            // try found channel_id by epg_id
            $epg_ids = $channel->get_epg_ids();
            $channels = $this->xmltv_indexes[$this->url_hash][self::INDEX_CHANNELS];
            foreach ($epg_ids as $epg_id) {
                $epg_id_lower = mb_convert_case($epg_id, MB_CASE_LOWER, "UTF-8");
                if (array_key_exists($epg_id_lower, $channels)) {
                    $channel_id = $channels[$epg_id_lower];
                    break;
                }
            }

            if (empty($channel_id)) {
                throw new Exception("index positions for epg '{$channel->get_title()}' is not exist");
            }

            $positions = $this->xmltv_indexes[$this->url_hash][self::INDEX_ENTRIES];
            if (!isset($positions[$channel_id])) {
                throw new Exception("index positions for epg $channel_id is not exist");
            }

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport('fetch');

            hd_debug_print("Fetch positions "
                . count($positions[$channel_id])
                . " for '$channel_id' by channel: '{$channel->get_title()}' ({$channel->get_id()}) done in: {$report[Perf_Collector::TIME]} secs");

            return $positions[$channel_id];
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
            if (empty($this->xmltv_indexes[$this->url_hash][self::INDEX_PICONS])) continue;

            $picons = $this->xmltv_indexes[$this->url_hash][self::INDEX_PICONS];
            foreach ($aliases as $alias) {
                if (!empty($alias) && isset($picons[$alias])) {
                    return $picons[$alias];
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

        if (!isset($this->xmltv_indexes[$this->url_hash][self::INDEX_CHANNELS], $this->xmltv_indexes[$this->url_hash][self::INDEX_PICONS])
            && $this->is_all_indexes_valid(array(self::INDEX_CHANNELS, self::INDEX_PICONS))) {
            hd_debug_print("Load cache channels and picons index: $channels_file");
            $data = parse_json_file($channels_file);
            $success = true;
            if ($data !== false) {
                $this->xmltv_indexes[$this->url_hash][self::INDEX_CHANNELS] = $data;
            } else {
                hd_debug_print("load positions index failed '$channels_file'");
                $success = false;
            }

            $data = parse_json_file($picons_file);
            if ($data !== false) {
                $this->xmltv_indexes[$this->url_hash][self::INDEX_PICONS] = $data;
            } else {
                hd_debug_print("load positions index failed '$picons_file'");
                $success = false;
            }

            if ($success) {
                $this->perf->setLabel('end');
                $report = $this->perf->getFullReport();
                hd_debug_print("ParseFile: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
                hd_debug_print_separator();
                return;
            }
        }

        $this->xmltv_indexes[$this->url_hash][self::INDEX_CHANNELS] = array();
        $this->xmltv_indexes[$this->url_hash][self::INDEX_PICONS] = array();

        $this->perf->setLabel('reindex');

        try {
            $this->set_index_locked(true);

            hd_debug_print_separator();
            hd_debug_print("Start reindex: $channels_file");

            $channels = array();
            $picons = array();
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
                $channels[$ls_channel] = $channel_id;
                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $alias = mb_convert_case($tag->nodeValue, MB_CASE_LOWER, "UTF-8");
                    $channels[$alias] = $channel_id;
                    if (!empty($picon)) {
                        $picons[$alias] = $picon;
                    }
                }
            }
            fclose($file);

            store_to_json_file($channels_file, $channels);
            store_to_json_file($picons_file, $picons);

            $this->xmltv_indexes[$this->url_hash][self::INDEX_CHANNELS] = $channels;
            $this->xmltv_indexes[$this->url_hash][self::INDEX_PICONS] = $picons;

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport('reindex');
            hd_debug_print("Total entries id's: " . count($channels));
            hd_debug_print("Total known picons: " . count($picons));
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

        $positions_file = $this->get_index_name(self::INDEX_ENTRIES);
        if (empty($this->xmltv_indexes[$this->url_hash][self::INDEX_ENTRIES]) && $this->is_all_indexes_valid(array(self::INDEX_ENTRIES))) {
            hd_debug_print("Try load cache program index: $positions_file");
            $success = true;
            $data = parse_json_file($positions_file);
            if ($data !== false) {
                $this->xmltv_indexes[$this->url_hash][self::INDEX_ENTRIES] = $data;
            } else {
                hd_debug_print("load positions index failed '$positions_file'");
                $success = false;
            }

            $this->perf->setLabel('end');
            $report = $this->perf->getFullReport();

            if ($success) {
                hd_debug_print("Load time: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
                return;
            }
        }

        try {

            hd_debug_print("Start reindex: $positions_file");

            $this->perf->setLabel('reindex');

            $this->remove_index(self::INDEX_ENTRIES);

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
                store_to_json_file($this->get_index_name(self::INDEX_ENTRIES), $xmltv_index);
                $this->xmltv_indexes[$this->url_hash][self::INDEX_ENTRIES] = $xmltv_index;
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

    /**
     * @inheritDoc
     * @override
     */
    public function get_indexes_info($hash = null)
    {
        $result = array(self::INDEX_CHANNELS => -1, self::INDEX_PICONS => -1, self::INDEX_ENTRIES => -1);
        $hash = $hash === null ? $this->url_hash : $hash;
        foreach ($result as $index => $name) {
            if (isset($this->xmltv_indexes[$hash][$index])) {
                $result[$index] = count($this->xmltv_indexes[$hash][$index]);
                continue;
            }

            $filename = $this->get_cache_stem("_$index$this->index_ext", $hash);
            if (file_exists($filename) && filesize($filename) !== 0) {
                $data = parse_json_file($filename);
                if ($data !== false) {
                    $this->xmltv_indexes[$hash][$index] = $data;
                    $result[$index] = count($data);
                } else {
                    hd_debug_print("Failed to load index: $filename");
                }
            }
        }

        return $result;
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
            $this->xmltv_indexes = array();
        } else {
            unset($this->xmltv_indexes[$id]);
        }
    }
}
