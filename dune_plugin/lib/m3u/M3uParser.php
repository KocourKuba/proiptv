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
     * @var Entry
     */
    protected $m3u_info;

    /**
     * @var Ordered_Array
     */
    protected $xmltv_sources;

    /**
     * @var SplFileObject
     */
    private $m3u_file;

    public function __construct()
    {
        $this->m3u_info = new Entry();
    }

    /**
     * @param string $file_name
     * @param bool $force
     */
    public function setupParser($file_name, $force = false)
    {
        if ($this->file_name !== $file_name || $force) {
            $this->m3u_file = null;
            $this->file_name = $file_name;
            $this->clear_data();

            try {
                if (!empty($this->file_name)){
                    $file = new SplFileObject($this->file_name);
                    $file->setFlags(SplFileObject::DROP_NEW_LINE);
                    $this->m3u_file = $file;
                }
            } catch (Exception $ex) {
                hd_debug_print("Can't read file: $this->file_name");
                return;
            }
        }
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

        $this->clear_data();

        $this->m3u_file->rewind();

        $t = microtime(true);

        $entry = new Entry();
        foreach($this->m3u_file as $line) {
            // something wrong or not supported
            if ($this->parseLine($line, $entry)) {
                // stream url
                $this->m3u_entries[] = $entry;
                $entry = new Entry();
            }
        }

        hd_debug_print("------------------------------------------------------------");
        hd_debug_print("parseFile " . (microtime(true) - $t) . " secs");
        return true;
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
        $data = array();
        if ($this->m3u_file === null) {
            hd_debug_print("Bad file");
            return $data;
        }

        $this->clear_data();

        $this->m3u_file->rewind();

        $t = microtime(true);

        $entry = new Entry();
        $pos = $this->m3u_file->ftell();
        while (!$this->m3u_file->eof()) {
            if ($this->parseLineFast($this->m3u_file->fgets(), $entry)) {
                $group_name = $entry->getGroupTitle();
                if (!array_key_exists($group_name, $data)) {
                    $data[$group_name] = array();
                }

                $data[$group_name][] = $pos;
                $entry = new Entry();
                $pos = $this->m3u_file->ftell();
            }
        }

        hd_debug_print("------------------------------------------------------------");
        hd_debug_print("indexFile " . (microtime(true) - $t) . " secs");
        return $data;
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

        $t = microtime(true);

        hd_debug_print("Open: $this->file_name");
        $lines = file($this->file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $entry = new Entry();
        foreach($lines as $line) {
            // if parsed line is not path or is not header tag parse next line
            if ($this->parseLine($line, $entry)) {
                if ($entry->isM3U_Header()) {
                    $this->m3u_info = $entry;
                } else {
                    $this->m3u_entries[] = $entry;
                }
                $entry = new Entry();
            }
        }

        hd_debug_print("------------------------------------------------------------");
        hd_debug_print("parseInMemory " . (microtime(true) - $t) . " sec.");
        return true;
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

            if (stripos($line, Entry::TAG_EXTINF) === 0) {
                $entry = new Entry();
                $tag = new ExtTagDefault();
                $entry->addTag($tag->parseFullData($line));
                return $entry->getEntryTitle();
            }
        }

        return '';
    }

    ///////////////////////////////////////////////////////////

    /**
     * Parse one line
     * return true if line is a url or parsed tag is header tag
     *
     * @param string $line
     * @param Entry& $entry
     * @return bool
     */
    protected function parseLine($line, &$entry)
    {
        $line = trim($line);
        if (empty($line)) {
            return false;
        }

        $tag = $entry->parseExtTag($line, true);
        if (is_null($tag)) {
            // untagged line must be a stream url
            $entry->setPath($line);
            return true;
        }

        return $entry->isM3U_Header();
    }

    /**
     * Parse one line
     * return true if line is a url or parsed tag is header tag
     *
     * @param string $line
     * @param Entry& $entry
     * @return bool
     */
    protected function parseLineFast($line, &$entry)
    {
        $line = trim($line);
        if (empty($line)) {
            return false;
        }

        $tag = $entry->parseExtTag($line, false);
        hd_debug_print(json_encode($tag), true);
        if (is_null($tag)) {
            // untagged line must be a stream url
            $entry->setPath($line);
            return true;
        }

        return $entry->isM3U_Header();
    }

    /**
     * @return Entry
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
        return $this->m3u_info->getEntryAttributes($tag);
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
        return is_array($name) ? $this->m3u_info->getAnyEntryAttribute($name, $tag) : $this->m3u_info->getEntryAttribute($name, $tag);
    }

    /**
     * @return Ordered_Array
     */
    public function getXmltvSources()
    {
        if (is_null($this->xmltv_sources)) {
            $this->xmltv_sources = new Ordered_Array();
            foreach ($this->m3u_info->getEpgSources() as $value) {
                $urls = explode(',', $value);
                foreach ($urls as $url) {
                    if (!empty($url) && preg_match(HTTP_PATTERN, $url)) {
                        $this->xmltv_sources->add_item($url);
                    }
                }
            }
        }

        return $this->xmltv_sources;
    }

    /**
     * @return void
     */
    protected function clear_data()
    {
        unset($this->m3u_entries, $this->m3u_info);
        $this->m3u_entries = array();
        $this->m3u_info = new Entry();
        $this->xmltv_sources = null;
    }
}
