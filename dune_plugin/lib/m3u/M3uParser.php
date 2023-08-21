<?php

require_once 'Entry.php';

class M3uParser
{
    /**
     * @var string
     */
    private $file_name;

    /**
     * @var SplFileObject
     */
    private $m3u_file;

    /**
     * @var Entry[]
     */
    private $m3u_entries;

    /**
     * @var Entry
     */
    private $m3u_info;

    /**
     * @param string $file_name
     * @param bool $force
     */
    public function setupParser($file_name, $force = false)
    {
        if ($this->file_name !== $file_name || $force) {
            $this->m3u_file = null;
            $this->file_name = $file_name;
            unset($this->m3u_entries);
            $this->m3u_entries = array();
            $this->m3u_info = null;

            try {
                $file = new SplFileObject($file_name);
            } catch (Exception $ex) {
                hd_print(__METHOD__ . ": Can't read file: $file_name");
                return;
            }

            $file->setFlags(SplFileObject::DROP_NEW_LINE);

            $this->m3u_file = $file;
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
            hd_print(__METHOD__ . ": Bad file");
            return false;
        }

        $this->m3u_file->rewind();

        $t = microtime(1);
        $entry = new Entry();
        foreach($this->m3u_file as $line) {
            if (!$this->parseLine($line, $entry)) continue;

            // only one ExtM3U entry!
            if ($entry->isExtM3U()) {
                if ($this->m3u_info === null) {
                    $this->m3u_info = $entry;
                }
                continue;
            }

            $this->m3u_entries[] = $entry;
            $entry = new Entry();
        }

        hd_print(__METHOD__ . ": parseFile " . (microtime(1) - $t) . " secs");
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
            hd_print(__METHOD__ . ": Bad file");
            return $data;
        }

        $this->m3u_file->rewind();

        $t = microtime(1);
        $entry = new Entry();
        $pos = $this->m3u_file->ftell();
        while (!$this->m3u_file->eof()) {
            if (!$this->parseLine($this->m3u_file->fgets(), $entry)) continue;

            // only one ExtM3U entry!
            if ($entry->isExtM3U()) {
                if ($this->m3u_info === null) {
                    $this->m3u_info = $entry;
                }
                continue;
            }

            $group_name = $entry->getGroupTitle();
            if (!array_key_exists($group_name, $data)) {
                $data[$group_name] = array();
            }

            $data[$group_name][] = $pos;
            $entry = new Entry();
            $pos = $this->m3u_file->ftell();
        }

        hd_print(__METHOD__ . ": indexFile " . (microtime(1) - $t) . " secs");
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
        if (!file_exists($this->file_name)) {
            hd_print(__METHOD__ . ": Can't read file: $this->file_name");
            return false;
        }

        $t = microtime(1);
        $lines = file($this->file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $entry = new Entry();
        foreach($lines as $line) {
            if (!$this->parseLine($line, $entry)) continue;

            // only one ExtM3U entry!
            if ($entry->isExtM3U()) {
                if ($this->m3u_info === null) {
                    $this->m3u_info = $entry;
                }
            } else {
                $this->m3u_entries[] = $entry;
            }

            $entry = new Entry();
        }

        hd_print(__METHOD__ . ": parseInMemory " . (microtime(1) - $t) . " sec. Entries: " . $this->getEntriesCount());
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
            hd_print(__METHOD__ . ": Bad file");
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
            hd_print(__METHOD__ . ": Bad file");
            return null;
        }

        $this->m3u_file->fseek((int)$idx);
        while (!$this->m3u_file->eof()) {
            $line = trim($this->m3u_file->fgets());
            if (empty($line)) continue;

            if (!self::isTag($line)) break;

            if (self::isExtInf($line)) {
                $entry = new Entry();
                $entry->setExtInf(new ExtInf($line, false));
                return $entry->getTitle();
            }
        }

        return '';
    }

    ///////////////////////////////////////////////////////////

    /**
     * Parse one line
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

        if (self::isTag($line)) {
            if (self::isExtM3u($line)) {
                $entry->setExtM3u(new ExtM3U($line));
                return true;
            }

            if (self::isExtInf($line)) {
                $entry->setExtInf(new ExtInf($line));
            } else if (self::isExtGrp($line)) {
                $entry->setExtGrp(new ExtGrp($line));
            }
        } else {
            $entry->setPath($line);
            return true;
        }

        return false;
    }

    /**
     * @param string $line
     * @return bool
     */
    protected static function isTag($line)
    {
        return !empty($line) && $line[0] === '#';
    }

    /**
     * @param string $line
     * @return bool
     */
    protected static function isExtM3u($line)
    {
        return stripos($line, '#EXTM3U') === 0;
    }

    /**
     * @param string $line
     * @return bool
     */
    protected static function isExtInf($line)
    {
        return stripos($line, '#EXTINF:') === 0;
    }

    /**
     * @param string $line
     * @return bool
     */
    protected static function isExtGrp($line)
    {
        return stripos($line, '#EXTGRP:') === 0;
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
     * @return Entry
     */
    public function getM3uInfo()
    {
        return $this->m3u_info;
    }

    /**
     * @return array
     */
    public function getXmltvSources()
    {
        $xmltv_urls = array();
        foreach (array('url-tvg', 'x-tvg-url') as $attr) {
            $tag_value = $this->m3u_info->getAttribute($attr);
            $urls = explode(',', $tag_value);
            foreach ($urls as $key => $url) {
                if (!empty($url) && preg_match("|https?://|", $url)) {
                    hd_print(__METHOD__ . ": $attr-$key: $url");
                    $xmltv_urls[] = $url;
                }
            }
        }

        return $xmltv_urls;
    }
}
