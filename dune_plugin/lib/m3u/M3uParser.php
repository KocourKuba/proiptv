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
require_once 'lib/sql_wrapper.php';
require_once 'lib/ordered_array.php';

class M3uParser extends Json_Serializer
{
    /*
     * Attributes contains epg id
     * "tvg-id", "tvg-epgid"
     */
    public static $epg_id_attrs = array(ATTR_TVG_ID, ATTR_TVG_EPGID);

    /*
     * Possible epg id's attributes
     * "tvg-id", "tvg-epgid", "tvg-name", "name"
     */
    public static $epg_ids_attrs = array(ATTR_TVG_ID, ATTR_TVG_EPGID, ATTR_TVG_NAME, ATTR_CHANNEL_NAME);

    /**
     * @var string
     */
    protected $file_name;

    /**
     * @var Entry
     */
    protected $m3u_info;

    /**
     * @var array
     */
    protected $data_positions;

    /**
     * @var string
     */
    public $id_map = '';

    /**
     * @var string
     */
    public $id_parser = '';

    /**
     * @var string
     */
    public $icon_base_url = '';

    /**
     * @var array
     */
    public $icon_replace_pattern = array();

    /**
     * @var Perf_Collector
     */
    private $perf;

    public function __construct()
    {
        $this->perf = new Perf_Collector();
    }

    public function get_icon_base_url()
    {
        return $this->icon_base_url;
    }

    public function get_filename()
    {
        return $this->file_name;
    }

    /**
     * @return void
     */
    public function clear_data()
    {
        $this->m3u_info = null;
        $this->icon_base_url = '';
    }

    /**
     * @param string $file_name
     * @param bool $force
     */
    public function setPlaylist($file_name, $force = false)
    {
        if ($this->file_name !== $file_name || $force) {
            $this->clear_data();
            $this->file_name = null;
            try {
                if (empty($file_name)) {
                    throw new Exception("File name cannot be empty");
                }

                if (!file_exists($file_name)) {
                    throw new Exception("File not exists: $file_name");
                }

                $this->file_name = $file_name;
            } catch (Exception $ex) {
                hd_debug_print("Can't read file: $file_name");
                print_backtrace_exception($ex);
            }
        }
    }

    /**
     * @param string $file_name
     * @param bool $force
     */
    public function setVodPlaylist($file_name, $force = false)
    {
        if ($this->file_name !== $file_name || $force) {
            $this->clear_data();
            $this->file_name = null;
            try {
                if (empty($file_name)) {
                    throw new Exception("File name cannot be empty");
                }

                if (!file_exists($file_name)) {
                    throw new Exception("File not exists: $file_name");
                }

                $this->file_name = $file_name;
            } catch (Exception $ex) {
                hd_debug_print("Can't read file: $file_name");
                print_backtrace_exception($ex);
            }
        }
    }

    /**
     * @param array $params
     */
    public function setupParserParameters($params)
    {
        if (!empty($params)) {
            $this->id_map = isset($params['id_map']) ? $params['id_map'] : null;
            $this->id_parser = isset($params['id_parser']) ? $params['id_parser'] : null;
            $this->icon_replace_pattern = isset($params['icon_replace_pattern']) ? $params['icon_replace_pattern'] : null;
        }

        // replace patterns in playlist icon
        if (!empty($this->icon_replace_pattern)) {
            hd_debug_print("Using specific playlist icon replacement: " . json_encode($this->icon_replace_pattern), true);
        }
    }

    /**
     * Load m3u into the memory for faster parsing
     * But may cause OutOfMemory for large files
     *
     * @param bool $global
     * @return Entry
     */
    public function parseHeader($global = true)
    {
        hd_debug_print();
        if (!file_exists($this->file_name)) {
            hd_debug_print("Can't read file: $this->file_name");
            return new Entry();
        }

        $this->clear_data();

        hd_debug_print("Open: $this->file_name");
        $lines = file($this->file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $m3u_info = null;
        $entry = new Entry();
        foreach ($lines as $line) {
            $res = $this->parseLine($line, $entry);
            if ($res === -1) continue;
            if (!$entry->isM3U_Header()) break;

            if (isset($m3u_info)) {
                $m3u_info->mergeEntry($entry);
            } else {
                $m3u_info = $entry;
            }

            if (empty($this->icon_base_url)) {
                $this->icon_base_url = $m3u_info->getAnyEntryAttribute(ATTR_URL_LOGO, TAG_EXTM3U);
            }

            $m3u_info->updateArchive(TAG_EXTM3U);
            $m3u_info->updateCatchupType(TAG_EXTM3U);
            $m3u_info->updateCatchupSource(TAG_EXTM3U);

            $entry = new Entry();
        }

        $m3u_info = is_null($m3u_info) ? new Entry() : $m3u_info;
        if ($global) {
            $this->m3u_info = $m3u_info;
        }

        return $m3u_info;
    }

    /**
     * Load m3u into the memory for faster parsing
     * But may cause OutOfMemory for large files
     *
     * @param Sql_Wrapper $db
     * @return bool
     */
    public function parseIptvPlaylist($db)
    {
        hd_debug_print();

        $this->clear_data();

        if (empty($this->file_name)) {
            hd_debug_print("Empty playlist file name");
            return false;
        }

        if (!file_exists($this->file_name)) {
            hd_debug_print("Can't read file: $this->file_name");
            return false;
        }

        $query = "DROP TABLE IF EXISTS iptv.iptv_channels;";
        $query .= "CREATE TABLE IF NOT EXISTS iptv.iptv_channels
                    (hash TEXT KEY not null, ch_id TEXT, title TEXT, tvg_name TEXT,
                     epg_id TEXT, archive INTEGER DEFAULT 0, timeshift INTEGER DEFAULT 0, catchup TEXT, catchup_source TEXT, icon TEXT,
                     path TEXT, adult INTEGER default 0, parent_code TEXT, ext_params TEXT, group_id TEXT not null
                    );";
        $query .= "DROP TABLE IF EXISTS iptv.iptv_groups;";
        $query .= "CREATE TABLE IF NOT EXISTS iptv.iptv_groups (group_id TEXT PRIMARY KEY, icon TEXT, adult INTEGER default 0);";
        $db->exec_transaction($query);

        $entry_columns = array('hash', 'ch_id', 'title', 'tvg_name',
            'epg_id', 'archive', 'timeshift', 'catchup', 'catchup_source', 'icon',
            'path', 'adult', 'parent_code', 'ext_params', 'group_id');

        $stm_channels = $db->prepare_bind("INSERT OR IGNORE" , "iptv.iptv_channels", $entry_columns);

        hd_debug_print("Open: $this->file_name");
        $lines = file($this->file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $db->exec('BEGIN;');

        $groups_cache = array();
        $entry = new Entry();
        foreach ($lines as $line) {
            // if parsed line is not path or is not header tag parse next line
            switch ($this->parseLine($line, $entry)) {
                case 1: // parse done
                    $group_title = $entry->getGroupTitle();
                    $group_logo = $entry->getGroupLogo();
                    if (!isset($groups_cache[$group_title])) {
                        $adult = self::is_adult_group($group_title);
                        $groups_cache[$group_title] = array('icon' => $group_logo, 'adult' => $adult);
                    } else if (empty($groups_cache[$group_title]['icon']) && !empty($group_logo)) {
                        $groups_cache[$group_title]['icon'] = $group_logo;
                    }

                    if ($groups_cache[$group_title]['adult']) {
                        $adult_channel = $groups_cache[$group_title]['adult'];
                    } else {
                        $adult_channel = $entry->getAdult();
                    }

                    $stm_channels->bindValue(":hash", $entry->getHash());
                    $stm_channels->bindValue(":ch_id", $entry->getChannelId());
                    $stm_channels->bindValue(":title", $entry->getTitle());
                    $stm_channels->bindValue(":tvg_name", $entry->getEntryAttribute(ATTR_TVG_NAME, TAG_EXTINF));
                    $stm_channels->bindValue(":epg_id", $entry->getAnyEntryAttribute(self::$epg_id_attrs, TAG_EXTINF));
                    $stm_channels->bindValue(":archive", $entry->getArchive(), SQLITE3_INTEGER);
                    $stm_channels->bindValue(":timeshift", $entry->getTimeshift(), SQLITE3_INTEGER);
                    $stm_channels->bindValue(":catchup", $entry->getCatchupType());
                    $stm_channels->bindValue(":catchup_source", $entry->getCatchupSource());
                    $stm_channels->bindValue(":icon", $entry->getIcon());
                    $stm_channels->bindValue(":path", $entry->getPath());
                    $stm_channels->bindValue(":adult", $adult_channel);
                    $stm_channels->bindValue(":parent_code", $entry->getParentCode());
                    $stm_channels->bindValue(":ext_params", empty($ext_params) ? null : json_encode($entry->getExtParams()));
                    $stm_channels->bindValue(":group_id", $group_title);
                    $stm_channels->execute();

                    $entry = new Entry();
                    break;
                case 2: // parse m3u header done
                    if (isset($this->m3u_info)) {
                        $this->m3u_info->mergeEntry($entry);
                    } else {
                        $this->m3u_info = $entry;
                    }

                    if (empty($this->icon_base_url)) {
                        $this->icon_base_url = $this->m3u_info->getAnyEntryAttribute(ATTR_URL_LOGO, TAG_EXTM3U);
                    }

                    $this->m3u_info->updateArchive(TAG_EXTM3U);
                    $this->m3u_info->updateCatchupType(TAG_EXTM3U);
                    $this->m3u_info->updateCatchupSource(TAG_EXTM3U);

                    $entry = new Entry();
                    break;
                default: // parse fail or parse partial, continue parse with same entry
                    break;
            }
        }

        $db->exec('COMMIT;');

        $entry_groups = array('group_id', 'icon', 'adult');
        $stm_groups = $db->prepare_bind("INSERT OR IGNORE" , "iptv.iptv_groups", $entry_groups);
        $db->exec('BEGIN;');
        foreach ($groups_cache as $group_title => $group) {
            $stm_groups->bindValue(":group_id", $group_title);
            $stm_groups->bindValue(":icon", $group['icon']);
            $stm_groups->bindValue(":adult", $group['adult']);
            $stm_groups->execute();
        }
        $db->exec('COMMIT;');

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
     * @param Sql_Wrapper $db
     * @return bool
     */
    public function parseVodPlaylist($db)
    {
        if (empty($this->file_name)) {
            hd_debug_print("Empty playlist file name");
            return false;
        }

        if (!file_exists($this->file_name)) {
            hd_debug_print("Can't read file: $this->file_name");
            return false;
        }

        $db_name = LogSeverity::$is_debug ? "$this->file_name.db" : ":memory:";
        $db->exec("ATTACH DATABASE '$db_name' as vod;");
        $db->exec("PRAGMA journal_mode=MEMORY;");

        $db->exec("DROP TABLE IF EXISTS vod.vod_entries;");
        $db->exec("CREATE TABLE IF NOT EXISTS vod.vod_entries
                            (hash TEXT PRIMARY KEY NOT NULL, group_id TEXT not null, title TEXT not null, icon TEXT, path TEXT not null);");

        $db->exec('BEGIN;');

        $entry_indexes = array('hash', 'group_id', 'title', 'icon', 'path');
        $stm_index = $db->prepare_bind("INSERT OR IGNORE" , "vod.vod_entries", $entry_indexes);

        $this->perf->reset('start');

        hd_debug_print("Open: $this->file_name");
        $lines = file($this->file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $entry = new Entry();
        foreach ($lines as $line) {
            $res = $this->parseLineFast($line, $entry);
            switch ($res) {
                case 1:
                    $stm_index->bindValue(":hash", $entry->getHash());
                    $stm_index->bindValue(":group_id", $entry->getGroupTitle());
                    $stm_index->bindValue(":title", $entry->getTitle());
                    $stm_index->bindValue(":icon", $entry->getIcon());
                    $stm_index->bindValue(":path", $entry->getPath());
                    $stm_index->execute();
                    $entry = new Entry();
                    break;
                case 2:
                    if (isset($this->m3u_info)) {
                        $this->m3u_info->mergeEntry($entry);
                    } else {
                        $this->m3u_info = $entry;
                    }
                    $entry = new Entry();
                    break;
            }
        }

        $db->exec('COMMIT;');

        $this->perf->setLabel('end');
        $report = $this->perf->getFullReport();
        hd_debug_print_separator();
        hd_debug_print("IndexFile: {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_debug_print_separator();

        return true;
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
        foreach ($this->m3u_info->getEntryAttributes($tag) as $attr) {
            $attributes[] = $attr;
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
        $attr = $this->m3u_info->getAnyEntryAttribute($name, $tag);
        if (!empty($attr)) {
            return $attr;
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
        foreach ($attrs as $attr) {
            $val = $this->m3u_info->getEntryAttribute($attr, $tag);
            if (empty($val)) continue;

            if ($found_attr !== null) {
                $found_attr = $attr;
            }
            break;
        }

        return $val;
    }

    /**
     * @return Ordered_Array
     */
    public function getXmltvSources()
    {
        $xmltv_sources = new Ordered_Array();
        if (!empty($this->m3u_info)) {
            $arr = $this->m3u_info->getEpgSources();
            foreach ($arr as $value) {
                $urls = explode(',', $value);
                foreach ($urls as $url) {
                    if (!empty($url) && is_http($url) && !preg_match("/jtv.?\.zip$/", basename($url))) {
                        $xmltv_sources->add_item($url);
                    }
                }
            }
        }

        return $xmltv_sources;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////
    /// protected functions

    /**
     * Parse one line
     * return 1 pr 2 if line is a url or parsed tag is header tag
     * return 0 if parse tag fail and it not a header entry
     * return -1 if line is empty
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

        $tag = $entry->parseExtTag($line);
        if (!is_null($tag)) {
            return $entry->isM3U_Header() ? 2 : 0;
        }

        // all information parsed. Now can set additional parameters and update database

        // set url and it hash
        $entry->setHash(Hashed_Array::hash($line));
        $entry->setPath($line);
        $entry->updateTitle();
        $entry->updateChannelId($this->id_parser, $this->id_map);
        $entry->updateArchive(TAG_EXTINF, $this->m3u_info->getArchive());
        $entry->updateCatchupType(TAG_EXTINF);
        $entry->updateCatchupSource(TAG_EXTINF);
        $entry->updateTimeshift();
        $entry->updateIcon($this->icon_base_url, $this->icon_replace_pattern);
        $entry->updateExtParams();
        $entry->updateGroupTitle();
        $entry->updateGroupLogo($this->icon_base_url);

        return 1;
    }

    /**
     * Parse one line
     * return 1 pr 2 if line is a url or parsed tag is header tag
     * return 0 if parse tag fail and it not a header entry
     * return -1 if line is empty
     *
     * @param string $line
     * @param Entry& $entry
     * @return int
     */
    protected function parseLineFast($line, &$entry)
    {
        $line = trim($line);
        if (empty($line)) {
            return -1;
        }

        $tag = $entry->parseExtTag($line);
        if (!is_null($tag)) {
            return $entry->isM3U_Header() ? 2 : 0;
        }

        // all information parsed. Now can set additional parameters and update database
        $entry->setHash(Hashed_Array::hash($line));
        $entry->setPath($line);
        $entry->updateIcon($this->icon_base_url);
        $entry->updateGroupTitle();
        $entry->updateTitle();

        return 1;
    }

    /**
     * @return Entry
     */
    public function getM3uInfo()
    {
        return $this->m3u_info;
    }

    /**
     * @param string $group_id
     * @return int
     */
    public static function is_adult_group($group_id)
    {
        $lower_title = mb_strtolower($group_id, 'UTF-8');
        return (strpos($lower_title, "взрослы") !== false
            || strpos($lower_title, "adult") !== false
            || strpos($lower_title, "18+") !== false
            || strpos($lower_title, "xxx") !== false) ? 1 : 0;
    }
}
