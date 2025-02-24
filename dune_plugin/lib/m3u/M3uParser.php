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
    const IPTV_DB = 'iptv';
    const VOD_DB = 'vod';
    const S_CHANNELS_TABLE = 'iptv_channels';
    const S_GROUPS_TABLE = 'iptv_groups';

    const CHANNELS_TABLE = 'iptv.iptv_channels';
    const GROUPS_TABLE = 'iptv.iptv_groups';
    const VOD_TABLE = 'vod.vod_entries';

    const COLUMN_PARSED_ID = 'parsed_id';
    const COLUMN_CUID = 'cuid';
    const COLUMN_EPG_ID = 'epg_id';
    const COLUMN_TVG_NAME = 'tvg_name';
    const COLUMN_ARCHIVE = 'archive';
    const COLUMN_TIMESHIFT = 'timeshift';
    const COLUMN_CATCHUP = 'catchup';
    const COLUMN_CATCHUP_SOURCE = 'catchup_source';
    const COLUMN_PATH = 'path';
    const COLUMN_ADULT = 'adult';
    const COLUMN_PARENT_CODE = 'parent_code';
    const COLUMN_EXT_PARAMS = 'ext_params';

    private $channels_table = self::CHANNELS_TABLE;
    private $groups_table = self::GROUPS_TABLE;
    private $vod_table = self::VOD_TABLE;

    /*
    * Map attributes to database columns
    */
    public static $id_to_column_mapper = array(
        ATTR_PARSED_ID      => self::COLUMN_PARSED_ID, // channel id found by regex parser
        ATTR_CUID           => self::COLUMN_CUID,      // attributes "CUID", "channel-id", "ch-id", "tvg-chno", "ch-number",
        ATTR_TVG_ID         => self::COLUMN_EPG_ID,    // attribute tvg-id
        ATTR_TVG_NAME       => self::COLUMN_TVG_NAME,  // attribute tvg-name
        ATTR_CHANNEL_NAME   => COLUMN_TITLE,           // channel title
        ATTR_CHANNEL_HASH   => COLUMN_HASH,            // url hash
    );

    public static $mapper_ops = array();

    /*
     * Attributes contains epg id
     * "tvg-id", "tvg-epgid"
     */
    public static $epg_id_attrs = array(ATTR_TVG_ID, ATTR_TVG_EPGID);

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
            $this->id_parser = safe_get_value($params, 'id_parser');
            $this->icon_replace_pattern = safe_get_value($params, 'icon_replace_pattern');
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
     * @return int|false
     */
    public function parseIptvPlaylist($db)
    {
        hd_debug_print();

        $this->clear_data();

        $init_channels = array(
            COLUMN_HASH => 'TEXT PRIMARY KEY NOT NULL',
            COLUMN_TITLE => 'TEXT',
            self::COLUMN_PARSED_ID => 'TEXT',
            self::COLUMN_CUID => 'TEXT',
            self::COLUMN_TVG_NAME => 'TEXT',
            self::COLUMN_EPG_ID => 'TEXT',
            self::COLUMN_ARCHIVE => 'INTEGER DEFAULT 0',
            self::COLUMN_TIMESHIFT => 'INTEGER DEFAULT 0',
            self::COLUMN_CATCHUP => 'TEXT',
            self::COLUMN_CATCHUP_SOURCE => 'TEXT',
            COLUMN_ICON => 'TEXT',
            self::COLUMN_PATH => 'TEXT',
            self::COLUMN_ADULT => 'INTEGER DEFAULT 0',
            self::COLUMN_PARENT_CODE => 'TEXT',
            COLUMN_GROUP_ID => 'TEXT NOT NULL',
        );

        $init_groups = array(
            COLUMN_GROUP_ID => 'TEXT PRIMARY KEY NOT NULL',
            COLUMN_ICON => 'TEXT',
            self::COLUMN_ADULT => 'INTEGER DEFAULT 0',
        );

        $channels_columns = Sql_Wrapper::make_table_columns($init_channels);
        $channels_groups = Sql_Wrapper::make_table_columns($init_groups);

        $query = "DROP TABLE IF EXISTS $this->channels_table;";
        $query .= "CREATE TABLE IF NOT EXISTS $this->channels_table ($channels_columns);";
        $query .= "DROP TABLE IF EXISTS $this->groups_table;";
        $query .= "CREATE TABLE IF NOT EXISTS $this->groups_table ($channels_groups);";
        $db->exec_transaction($query);

        if (empty($this->file_name)) {
            hd_debug_print("Empty playlist file name");
            return false;
        }

        if (!file_exists($this->file_name)) {
            hd_debug_print("Can't read file: $this->file_name");
            return false;
        }

        $stm_channels = $db->prepare_bind("INSERT OR IGNORE", $this->channels_table, array_keys($init_channels));

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

                    $ext_params = $entry->getExtParams();

                    $stm_channels->bindValue(":" . COLUMN_HASH, $entry->getHash());
                    $stm_channels->bindValue(":" . COLUMN_TITLE, $entry->getTitle());
                    $stm_channels->bindValue(":" . self::COLUMN_PARSED_ID, $entry->getParsedId());
                    $stm_channels->bindValue(":" . self::COLUMN_CUID, $entry->getCUID());
                    $stm_channels->bindValue(":" . self::COLUMN_TVG_NAME, $entry->getEntryAttribute(ATTR_TVG_NAME, TAG_EXTINF));
                    $stm_channels->bindValue(":" . self::COLUMN_EPG_ID, $entry->getAnyEntryAttribute(self::$epg_id_attrs, TAG_EXTINF));
                    $stm_channels->bindValue(":" . self::COLUMN_ARCHIVE, $entry->getArchive(), SQLITE3_INTEGER);
                    $stm_channels->bindValue(":" . self::COLUMN_TIMESHIFT, $entry->getTimeshift(), SQLITE3_INTEGER);
                    $stm_channels->bindValue(":" . self::COLUMN_CATCHUP, $entry->getCatchupType());
                    $stm_channels->bindValue(":" . self::COLUMN_CATCHUP_SOURCE, $entry->getCatchupSource());
                    $stm_channels->bindValue(":" . COLUMN_ICON, $entry->getIcon());
                    $stm_channels->bindValue(":" . self::COLUMN_PATH, $entry->getPath());
                    $stm_channels->bindValue(":" . self::COLUMN_ADULT, $adult_channel);
                    $stm_channels->bindValue(":" . self::COLUMN_PARENT_CODE, $entry->getParentCode());
                    $stm_channels->bindValue(":" . self::COLUMN_EXT_PARAMS, empty($ext_params) ? null : json_encode($ext_params));
                    $stm_channels->bindValue(":" . COLUMN_GROUP_ID, $group_title);
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

        $stm_groups = $db->prepare_bind("INSERT OR IGNORE", $this->groups_table, array_keys($init_groups));
        $db->exec('BEGIN;');
        foreach ($groups_cache as $group_title => $group) {
            $stm_groups->bindValue(":" . COLUMN_GROUP_ID, $group_title);
            $stm_groups->bindValue(":" . COLUMN_ICON, $group[COLUMN_ICON]);
            $stm_groups->bindValue(":" . self::COLUMN_ADULT, $group[self::COLUMN_ADULT]);
            $stm_groups->execute();
        }
        $db->exec('COMMIT;');

        return $db->query_value("SELECT COUNT(*) FROM $this->channels_table;");
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

        $init_vod = array(
            COLUMN_HASH => 'TEXT PRIMARY KEY NOT NULL',
            COLUMN_GROUP_ID => 'TEXT NOT NULL',
            COLUMN_TITLE => 'TEXT NOT NULL',
            COLUMN_ICON => 'TEXT',
            self::COLUMN_PATH => 'TEXT NOT NULL',
        );
        $vod_columns = Sql_Wrapper::make_table_columns($init_vod);

        $this->perf->reset('start');

        $db_name = LogSeverity::$is_debug ? "$this->file_name.db" : ":memory:";
        $db->exec("ATTACH DATABASE '$db_name' AS " . self::VOD_DB);

        $query = "DROP TABLE IF EXISTS $this->vod_table;";
        $query .= "CREATE TABLE IF NOT EXISTS $this->vod_table ($vod_columns);";
        $db->exec($query);

        $stm_index = $db->prepare_bind("INSERT OR IGNORE", $this->vod_table, array_keys($init_vod));

        hd_debug_print("Open: $this->file_name");
        $lines = file($this->file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $db->exec('BEGIN;');

        $entry = new Entry();
        foreach ($lines as $line) {
            $res = $this->parseLineFast($line, $entry);
            switch ($res) {
                case 1:
                    $stm_index->bindValue(":" . COLUMN_HASH, $entry->getHash());
                    $stm_index->bindValue(":" . COLUMN_GROUP_ID, $entry->getGroupTitle());
                    $stm_index->bindValue(":" . COLUMN_TITLE, $entry->getTitle());
                    $stm_index->bindValue(":" . COLUMN_ICON, $entry->getIcon());
                    $stm_index->bindValue(":" . self::COLUMN_PATH, $entry->getPath());
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
        $lines = null;

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
                    if (!empty($url) && is_proto_http($url) && !preg_match("/jtv.?\.zip$/", basename($url))) {
                        $xmltv_sources->add_item($url);
                    }
                }
            }
        }

        return $xmltv_sources;
    }

    /**
     * @param Sql_Wrapper $db
     * @return array
     */
    static public function detectBestChannelId($db)
    {
        hd_debug_print(null, true);

        $stat = array();

        $table = M3uParser::CHANNELS_TABLE;
        $cnt = $db->query_value("SELECT COUNT(*) FROM $table;");
        if (empty($cnt)) {
            return $stat;
        }

        foreach (self::$id_to_column_mapper as $key => $value) {
            $query = "SELECT sum(cnt - 1) AS dupes
                FROM (SELECT $value, COUNT($value) AS cnt
                      FROM $table GROUP BY $value HAVING cnt > 0 ORDER BY cnt DESC);";
            $res = $db->query_value($query);

            if ($res === false || $res === null) {
                $res = -1;
            }

            $res = ($res > 0) ? $res - 1 : $res;
            hd_debug_print("Key '$key' => '$value' dupes count: $res");

            $stat[$key] = $res;
        }

        return $stat;
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
        if (!is_supported_proto($line)) {
            return 0;
        }

        // set url and it hash
        $entry->setHash(Hashed_Array::hash($line));
        $entry->setPath($line);
        $entry->updateTitle();
        $entry->updateParsedId($this->id_parser);
        $entry->updateCUID();
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
