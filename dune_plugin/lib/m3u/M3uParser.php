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

require_once 'Entry.php';
require_once 'lib/perf_collector.php';

class M3uParser extends Json_Serializer
{
    /**
     * @var string
     */
    protected $file_name;

    /**
     * @var Entry[]
     */
    protected $m3u_entries;

    /**
     * @var Entry[]
     */
    protected $m3u_info;

    /**
     * @var array
     */
    protected $data_positions;

    /**
     * @var Ordered_Array
     */
    protected $xmltv_sources;

    /**
     * @var bool
     */
    protected $store_matches = false;

    /**
     * @var string
     */
    protected $id_map = '';

    /**
     * @var string
     */
    protected $id_parser = '';

    /**
     * @var string
     */
    protected $icon_base_url = '';

    /**
     * @var array
     */
    protected $icon_replace_pattern = array();

    /**
     * @var SplFileObject
     */
    private $m3u_file;

    /**
     * @var Perf_Collector
     */
    private $perf;

    /**
     * Attributes contains picon information
     * "tvg-logo", "url-logo"
     */
    static $icon_attrs = array(ATTR_TVG_LOGO, ATTR_URL_LOGO);

    public function __construct()
    {
        $this->perf = new Perf_Collector();
    }

    public function get_icon_base_url()
    {
        return $this->icon_base_url;
    }

    /**
     * @return void
     */
    protected function clear_data()
    {
        unset($this->m3u_entries, $this->m3u_info);
        $this->m3u_entries = array();
        $this->m3u_info = array();
        $this->data_positions = array();
        $this->xmltv_sources = null;
        $this->icon_base_url = '';
    }

    /**
     * @param string $file_name
     * @param bool $force
     */
    public function assignPlaylist($file_name, $force = false)
    {
        if ($this->file_name !== $file_name || $force) {
            $this->m3u_file = null;
            $this->file_name = $file_name;
            $this->clear_data();

            try {
                if (!empty($this->file_name)) {
                    $file = new SplFileObject($this->file_name);
                    $file->setFlags(SplFileObject::DROP_NEW_LINE);
                    $this->m3u_file = $file;
                }
            } catch (Exception $ex) {
                hd_debug_print("Can't read file: $this->file_name");
                print_backtrace_exception($ex);
                return;
            }
        }
    }

    /**
     * @param string $id_map
     * @param string $id_parser
     * @param array $icon_replace_pattern
     * @param bool $store_matches
     */
    public function setupParserParameters($id_map, $id_parser, $icon_replace_pattern, $store_matches)
    {
        if (!empty($id_parser)) {
            hd_debug_print("Using specific ID parser: $id_parser", true);
            if ($store_matches) {
                hd_debug_print("Using specific icon template", true);
            }
        }

        if (!empty($id_map)) {
            hd_debug_print("Using specific ID mapping: $id_map", true);
        }

        if (empty($id_map) && empty($id_parser)) {
            hd_debug_print("No specific ID mapping or URL parser", true);
        }

        // replace patterns in playlist icon
        if (!empty($icon_replace_pattern)) {
            hd_debug_print("Using specific playlist icon replacement: " . json_encode($icon_replace_pattern), true);
        }

        $this->id_map = $id_map;
        $this->id_parser = $id_parser;
        $this->icon_replace_pattern = $icon_replace_pattern;
        $this->store_matches = $store_matches;
    }

    /**
     * Parse m3u by seeks file, slower and
     * less memory consumption for large m3u files
     * But still may cause memory exhausting
     *
     * @return bool
     */
    public function parseFile()
    {
        if ($this->m3u_file === null) {
            hd_debug_print("Bad file");
            return false;
        }

        $this->m3u_file->rewind();

        $this->perf->reset('start');

        $entry = new Entry();
        foreach ($this->m3u_file as $line) {
            // something wrong or not supported
            switch ($this->parseLine($line, $entry)) {
                case 1: // parse done
                    $this->m3u_entries[] = $entry;
                    $entry = new Entry();
                    break;
                case 2: // parse m3u header done
                    $this->m3u_info[] = $entry;
                    $entry = new Entry();
                    break;
                default: // parse fail or parse partial, continue parse with same entry
                    break;
            }
        }

        $this->perf->setLabel('end');
        $report = $this->perf->getFullReport();
        hd_debug_print_separator();
        hd_debug_print("ParseFile: {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_debug_print_separator();

        return true;
    }

    /**
     * Parse one line
     * return true if line is a url or parsed tag is header tag
     *
     * @param string $line
     * @param Entry& $entry
     * @return int
     */
    protected function parseLine($line, &$entry)
    {
        $line = trim($line);
        if (empty($line)) {
            return -1;
        }

        $tag = $entry->parseExtTag($line, true);
        if (is_null($tag)) {
            // untagged line must be a stream url
            $entry->setPath($line);

            // all information parsed. Now can set additional conversion
            $this->updateEntry($entry);

            return 1;
        }

        return $entry->isM3U_Header() ? 2 : 0;
    }

    /**
     * Indexing m3u. Low memory consumption.
     * Faster speed for random access to each entry
     * Can be used with HUGE m3u files
     *
     * Returns array of groups each contains
     * array of file positions for each entries
     *
     * @return array[]
     */
    public function indexFile()
    {
        if (!empty($this->data_positions)) {
            return $this->data_positions;
        }

        if ($this->m3u_file === null) {
            hd_debug_print("Bad file");
            return array();
        }

        $this->perf->reset('start');

        $this->m3u_file->rewind();
        $entry = new Entry();
        $pos = $this->m3u_file->ftell();
        while (!$this->m3u_file->eof()) {
            if (!$this->parseLine($this->m3u_file->fgets(), $entry)) continue;

            $group_name = $entry->getGroupTitle();
            if (!array_key_exists($group_name, $this->data_positions)) {
                $this->data_positions[$group_name] = array();
            }

            $this->data_positions[$group_name][] = $pos;
            $entry = new Entry();
            $pos = $this->m3u_file->ftell();
        }

        $this->perf->setLabel('end');
        $report = $this->perf->getFullReport();
        hd_debug_print_separator();
        hd_debug_print("IndexFile: {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_debug_print_separator();

        return $this->data_positions;
    }

    /**
     * Load m3u into the memory for faster parsing
     * But may cause OutOfMemory for large files
     *
     * @return bool
     */
    public function parseInMemory()
    {
        hd_debug_print();
        if (!file_exists($this->file_name)) {
            hd_debug_print("Can't read file: $this->file_name");
            return false;
        }

        $this->clear_data();

        $this->perf->reset('start');

        hd_debug_print("Open: $this->file_name");
        $lines = file($this->file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $entry = new Entry();
        foreach ($lines as $line) {
            // if parsed line is not path or is not header tag parse next line
            switch ($this->parseLine($line, $entry)) {
                case 1: // parse done
                    $this->m3u_entries[] = $entry;
                    $entry = new Entry();
                    break;
                case 2: // parse m3u header done
                    $this->m3u_info[] = $entry;
                    if (!empty($this->icon_base_url)) {
                        $this->icon_base_url = $this->getHeaderAttribute(ATTR_URL_LOGO, TAG_EXTM3U);
                    }
                    $entry = new Entry();
                    break;
                default: // parse fail or parse partial, continue parse with same entry
                    break;
            }
        }

        $this->perf->setLabel('end');
        $report = $this->perf->getFullReport();
        hd_debug_print("ParseInMemory: {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_debug_print_separator();

        return true;
    }

    ///////////////////////////////////////////////////////////

    /**
     * Update specific entry values
     *
     * @param Entry $entry
     */
    public function updateEntry(&$entry)
    {
        // set channel id
        $entry->updateChannelId($this->id_parser, $this->id_map);

        // make full url for icon if used base url
        $icon = $entry->getAnyEntryAttribute(self::$icon_attrs);
        if (!empty($this->icon_base_url) && !preg_match(HTTP_PATTERN, $icon)) {
            $icon = $this->icon_base_url . $icon;
        }

        // Apply replacement pattern
        if (!empty($this->icon_replace_pattern)) {
            foreach ($this->icon_replace_pattern as $pattern) {
                $icon = preg_replace($pattern['search'], $pattern['replace'], $icon);
            }
        }
        $entry->setChannelIcon($icon);

        // set group logo
        $group_logo = $entry->getEntryAttribute(ATTR_GROUP_LOGO);
        if (empty($group_logo) && !empty($this->icon_base_url) && !preg_match(HTTP_PATTERN, $group_logo)) {
            $group_logo = $this->icon_base_url . $group_logo;
        }
        $entry->setGroupIcon($group_logo);

        // set group title
        $entry->updateGroupTitle();

        // set channel archive
        $entry->updateArchiveLength();

        // set channel EPG IDs
        $entry->updateEpgIds();
    }

    /**
     * get entry by idx
     *
     * @param int $idx
     * @return Entry
     */
    public function getEntryByIdx($idx)
    {
        if ($this->m3u_file === null) {
            hd_debug_print("Bad file");
            return null;
        }
        $this->m3u_file->fseek((int)$idx);
        $entry = new Entry();
        while (!$this->m3u_file->eof()) {
            if ($this->parseLine($this->m3u_file->fgets(), $entry)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * get entry by idx
     *
     * @param int $idx
     * @return string
     */
    public function getTitleByIdx($idx)
    {
        if ($this->m3u_file === null) {
            hd_debug_print("Bad file");
            return null;
        }

        $this->m3u_file->fseek((int)$idx);
        while (!$this->m3u_file->eof()) {
            $line = trim($this->m3u_file->fgets());
            if (empty($line)) continue;

            if ($line[0] !== '#') break;

            if (stripos($line, TAG_EXTINF) === 0) {
                $entry = new Entry();
                $tag = new ExtTagDefault();
                $entry->addTag($tag->parseFullData($line));
                return $entry->getEntryTitle();
            }
        }

        return '';
    }

    /**
     * @return Entry[]
     */
    public function getM3uInfo()
    {
        return $this->m3u_info;
    }

    /**
     * @return Entry[]
     */
    public function getM3uEntries()
    {
        return $this->m3u_entries;
    }

    /**
     * @return int
     */
    public function getEntriesCount()
    {
        return count($this->m3u_entries);
    }

    /**
     * Returns headers attributes from specified tag
     * If tag not specified try to search in all available tags
     *
     * @param string|null $tag
     * @return array|null
     */
    public function getHeaderAttributes($tag = null)
    {
        $attributes = array();
        foreach ($this->m3u_info as $entry) {
            foreach ($entry->getEntryAttributes($tag) as $attr) {
                $attributes[] = $attr;
            }
        }
        return array_unique($attributes);
    }

    /**
     * Returns headers attribute from specified tag
     * If tag not specified try to search specified attribute in all available tags
     *
     * @param string|array $name
     * @param string|null $tag
     * @return string|null
     */
    public function getHeaderAttribute($name, $tag = null)
    {
        foreach ($this->m3u_info as $entry) {
            $attr = $entry->getAnyEntryAttribute($name, $tag);
            if (!empty($attr)) {
                return $attr;
            }
        }

        return "";
    }

    /**
     * @param string|array $attrs
     * @param null $tag
     * @param null $found_attr
     * @return string
     */
    public function getAnyHeaderAttribute($attrs, $tag = null, &$found_attr = null)
    {
        if (!is_array($attrs)) {
            $attrs = array($attrs);
        }

        $val = '';
        foreach ($this->m3u_info as $entry) {
            foreach ($attrs as $attr) {
                $val = $entry->getEntryAttribute($attr, $tag);
                if (empty($val)) continue;

                if ($found_attr !== null) {
                    $found_attr = $attr;
                }
                break;
            }
        }

        return $val;
    }

    /**
     * @return Ordered_Array
     */
    public function getXmltvSources()
    {
        if (is_null($this->xmltv_sources)) {
            $this->xmltv_sources = new Ordered_Array();
            if (is_array($this->m3u_info)) {
                foreach ($this->m3u_info as $entry) {
                    $arr = $entry->getEpgSources();
                    foreach ($arr as $value) {
                        $urls = explode(',', $value);
                        foreach ($urls as $url) {
                            if (!empty($url) && preg_match(HTTP_PATTERN, $url)) {
                                $this->xmltv_sources->add_item($url);
                            }
                        }
                    }
                }
            }
        }

        return $this->xmltv_sources;
    }

    public function detectBestChannelId()
    {
        if ($this->getEntriesCount() === 0) {
            return ATTR_CHANNEL_HASH;
        }

        $statistics = array(
            ATTR_CHANNEL_ID => array('stat' => 0, 'items' => array()),
            ATTR_TVG_ID => array('stat' => 0, 'items' => array()),
            ATTR_TVG_NAME => array('stat' => 0, 'items' => array()),
            ATTR_CHANNEL_NAME => array('stat' => 0, 'items' => array()),
            ATTR_CHANNEL_HASH => array('stat' => 0, 'items' => array())
        );

        foreach ($this->getM3uEntries() as $entry) {
            foreach ($statistics as $name => $pair) {
                $val = $entry->getEntryAttribute($name);
                $val = empty($val) ? 'dupe' : $val;
                if (array_key_exists($val, $pair['items'])) {
                    ++$statistics[$name]['stat'];
                } else {
                    $statistics[$name]['items'][$val] = '';
                }
            }
        }

        $min_key = '';
        $min_dupes = PHP_INT_MAX;
        foreach ($statistics as $name => $pair) {
            hd_debug_print("attr: $name dupes: {$pair['stat']}", true);
            if ($pair['stat'] < $min_dupes) {
                $min_key = $name;
                $min_dupes = $pair['stat'];
            }
        }

        return (empty($min_key) || $min_key === ATTR_CHANNEL_ID) ? ATTR_CHANNEL_HASH : $min_key;
    }
}
