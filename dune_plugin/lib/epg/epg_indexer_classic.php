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
    public function init($cache_dir, $url)
    {
        parent::init($cache_dir, $url);

        $this->index_ext = '.index';
    }

    /**
     * @inheritDoc
     * @override
     */
    public function load_program_index($channel)
    {
        try {
            $t = microtime(true);
            if (empty($this->xmltv_positions)) {
                $index_file = $this->get_index_name('positions');
                hd_debug_print("load positions index $$index_file");
                $data = HD::ReadContentFromFile($index_file);
                if (empty($data)) {
                    throw new Exception("load positions index failed '$index_file'");
                }
                $this->xmltv_positions = $data;
                HD::ShowMemoryUsage();
            }

            if (empty($this->xmltv_channels)) {
                $index_file = $this->get_index_name('channels');
                hd_debug_print("load channels index $$index_file");
                $this->xmltv_channels = HD::ReadContentFromFile($index_file);
                if (empty($this->xmltv_channels)) {
                    $this->xmltv_channels = null;
                    throw new Exception("load channels index failed '$index_file'");
                }
            }

            // try found channel_id by epg_id
            $epg_ids = $channel->get_epg_ids();
            foreach ($epg_ids as $epg_id) {
                $epg_id_lower = mb_convert_case($epg_id, MB_CASE_LOWER, "UTF-8");
                if (array_key_exists($epg_id_lower, $this->xmltv_channels)) {
                    $channel_id = $this->xmltv_channels[$epg_id_lower];
                    break;
                }
            }

            if (empty($channel_id)) {
                throw new Exception("index positions for epg '{$channel->get_title()}' is not exist");
            }

            if (!isset($this->xmltv_positions[$channel_id])) {
                throw new Exception("index positions for epg $channel_id is not exist");
            }

            hd_debug_print("Fetch positions "
                . count($this->xmltv_positions[$channel_id])
                . " for '$channel_id' by channel: '{$channel->get_title()}' ({$channel->get_id()}) done in: "
                . (microtime(true) - $t) . " secs");

            return $this->xmltv_positions[$channel_id];
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        return array();
    }

    /**
     * @inheritDoc
     * @override
     */
    public function get_picon($alias)
    {
        if (!isset($this->xmltv_picons)) {
            $name = $this->get_index_name('picons');
            hd_debug_print("Load picons from: $name");
            $this->xmltv_picons = HD::ReadContentFromFile($name);
        }

        return isset($this->xmltv_picons[$alias]) ? $this->xmltv_picons[$alias] : '';
    }

    /**
     * @inheritDoc
     * @override
     */
    public function index_xmltv_channels()
    {
        $channels_file = $this->get_index_name('channels');
        if (file_exists($channels_file)) {
            hd_debug_print("Load cache channels index: $channels_file");
            $this->xmltv_channels = HD::ReadContentFromFile($channels_file);
            return;
        }

        $this->xmltv_channels = array();
        $this->xmltv_picons = array();
        $t = microtime(true);

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
                foreach($xml_node->getElementsByTagName('channel') as $tag) {
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
                $this->xmltv_channels[$ls_channel] = $channel_id;
                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $alias = mb_convert_case($tag->nodeValue, MB_CASE_LOWER, "UTF-8");
                    $this->xmltv_channels[$alias] = $channel_id;
                    if (!empty($picon)) {
                        $this->xmltv_picons[$alias] = $picon;
                    }
                }
            }
            fclose($file);

            HD::StoreContentToFile($this->get_index_name('picons'), $this->xmltv_picons);
            HD::StoreContentToFile($channels_file, $this->xmltv_channels);

            hd_debug_print("Total entries id's: " . count($this->xmltv_channels));
            hd_debug_print("Total known picons: " . count($this->xmltv_picons));
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

        $cache_valid = false;
        $index_program = $this->get_index_name('positions');
        if (file_exists($index_program)) {
            hd_debug_print("Load cache program index: $index_program");
            $this->xmltv_positions = HD::ReadContentFromFile($index_program);
            if ($this->xmltv_positions !== false) {
                $cache_valid = true;
            }
        }

        if ($cache_valid) {
            return;
        }

        try {
            $this->set_index_locked(true);

            hd_debug_print_separator();
            hd_debug_print("Start reindex: $index_program");

            $t = microtime(true);

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
                hd_debug_print("Save index: $index_program", true);
                HD::StoreContentToFile($this->get_index_name('positions'), $xmltv_index);
                $this->xmltv_positions = $xmltv_index;
            }

            hd_debug_print("Total unique epg id's indexed: " . count($xmltv_index));
            hd_debug_print("Reindexing EPG program done: " . (microtime(true) - $t) . " secs");
            hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
            HD::ShowMemoryUsage();
            hd_debug_print_separator();
        } catch (Exception $ex) {
            hd_debug_print("Reindexing EPG positions failed");
            print_backtrace_exception($ex);
        }

        $this->set_index_locked(false);
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function clear_memory_index()
    {
        hd_debug_print("clear legacy index");

        $this->xmltv_picons = null;
        $this->xmltv_channels = null;
        $this->xmltv_positions = null;
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param $name string
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
    protected function is_index_valid($name)
    {
        $name = $this->get_index_name($name);
        return file_exists($name) && filesize($name) !== 0;
    }

    /**
     * @inheritDoc
     * @override
     */
    protected function check_index_version()
    {
        return file_exists(get_data_path($this->url_hash . '_version')) && file_get_contents(get_data_path($this->url_hash . '_version')) > '4.0.730';
    }
}
