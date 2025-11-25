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

require_once 'lib/tr.php';
require_once 'lib/hd.php';
require_once 'lib/hashed_array.php';
require_once 'lib/curl_wrapper.php';
require_once 'lib/sql_wrapper.php';
require_once 'lib/perf_collector.php';
require_once 'lib/dune_last_error.php';

class Epg_Manager_Xmltv
{
    const TABLE_PICONS = 'epg_picons';
    const TABLE_CHANNELS = 'epg_channels';
    const TABLE_ENTRIES = 'epg_entries';

    protected static $index_flags = array(INDEXING_DOWNLOAD, INDEXING_CHANNELS, INDEXING_ENTRIES);

    /**
     * path where cache is stored
     * @var string
     */
    protected static $cache_dir;

    /**
     * contains memory epg cache
     * @var array
     */
    protected static $epg_cache = array();

    /**
     * @var Sql_Wrapper[]
     */
    protected static $epg_db = array();

    /**
     * @var Hashed_Array
     */
    protected static $xmltv_sources;

    /**
     * @var int
     */
    protected static $flags = 0;

    /**
     * @var bool
     */
    protected static $ext_epg_enabled;

    /**
     * @var array
     */
    protected $delayed_epg = array();

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct($plugin)
    {
        self::$ext_epg_enabled = is_ext_epg_supported() && $plugin->get_bool_setting(PARAM_SHOW_EXT_EPG);
        self::$flags = $plugin->get_bool_setting(PARAM_FAKE_EPG, false) ? EPG_FAKE_EPG : 0;
        self::$xmltv_sources = $plugin->get_active_sources();
        self::set_cache_dir($plugin->get_cache_dir());
        self::clear_epg_memory_cache();
    }

    /**
     * returns list of requested epg when indexing in process
     *
     * @return array
     */
    public function get_delayed_epg()
    {
        return $this->delayed_epg;
    }

    /**
     * clear all delayed epg
     */
    public function clear_delayed_epg()
    {
        $this->delayed_epg = array();
    }

    /**
     * @param object $provider
     * @param array $channel_row
     * @param int $day_start_ts
     * @param string $epg_id
     * @param array $preset
     * @return string|null
     */
    public function get_epg_url($provider, $channel_row, $day_start_ts, &$epg_id, &$preset)
    {
        return null;
    }

    /**
     * Try to load epg from cached file
     *
     * @param array $channel_row
     * @param int $day_start_ts timestamp for day start in local time
     * @param bool $cached
     * @return array of entries started from day start for entire day
     */
    public function get_day_epg_items($channel_row, $day_start_ts, &$cached)
    {
        $day_epg = array();
        $channel_id = safe_get_value($channel_row, COLUMN_CHANNEL_ID);
        if (empty($channel_id)) {
            return array();
        }

        $cached = false;
        if (isset(static::$epg_cache[$channel_id][$day_start_ts])) {
            hd_debug_print("Load day Channel ID $channel_id from day start: ($day_start_ts) "
                . format_datetime("Y-m-d H:i", $day_start_ts) . " from memory cache ");
            $cached = true;
            $day_epg['items'] = static::$epg_cache[$channel_id][$day_start_ts];
            return $day_epg;
        }

        $day_end_ts = $day_start_ts + 86400;

        $items = array();
        foreach (self::$xmltv_sources as $key => $params) {
            hd_debug_print("Looking in XMLTV source: {$params[PARAM_URI]}");
            if (self::is_index_locked($key, INDEXING_DOWNLOAD | INDEXING_ENTRIES)) {
                hd_debug_print("EPG {$params[PARAM_URI]} still indexing, append to delayed queue channel id: $channel_id");
                $this->delayed_epg[] = $channel_id;
                continue;
            }

            // filter out epg only for selected day
            if (LogSeverity::$is_debug) {
                $date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
                $date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
                hd_debug_print("Fetch entries for from: $date_start_l ($day_start_ts) to: $date_end_l ($day_end_ts)");
            }

            try {
                $positions = $this->load_program_index($params, $channel_row);
                if (!empty($positions)) {
                    $cached_file = self::$cache_dir . $params[PARAM_HASH] . ".xmltv";
                    if (!file_exists($cached_file)) {
                        throw new Exception("get_day_epg_items: cache file $cached_file not exist");
                    }

                    $handle = fopen($cached_file, 'rb');
                    if ($handle) {
                        foreach ($positions as $pos) {
                            fseek($handle, $pos['start']);
                            $length = $pos['end'] - $pos['start'];
                            if ($length <= 0) continue;

                            $xml_str = "<tv>" . fread($handle, $pos['end'] - $pos['start']) . "</tv>";

                            $xml_node = new DOMDocument();
                            $res = $xml_node->loadXML($xml_str);
                            if ($res === false) {
                                throw new Exception("Exception in line: $xml_str");
                            }

                            foreach ($xml_node->getElementsByTagName('programme') as $tag) {
                                $program_start = strtotime($tag->getAttribute('start'));
                                $program_end = strtotime($tag->getAttribute('stop'));
                                if ($program_start < $day_start_ts && $program_end < $day_start_ts) continue;
                                if ($program_start >= $day_end_ts) break;

                                $items[$program_start][PluginTvEpgProgram::end_tm_sec] = $program_end;
                                $items[$program_start][PluginTvEpgProgram::name] = self::get_node_value($tag, 'title');
                                $items[$program_start][PluginTvEpgProgram::description] = HD::unescape_entity_string(self::get_node_value($tag, 'desc'));
                                $items[$program_start][PluginTvEpgProgram::icon_url] = self::get_node_attribute($tag, 'icon', 'src');

                                if (!self::$ext_epg_enabled) continue;

                                $items[$program_start][PluginTvExtEpgProgram::sub_title] = self::get_node_value($tag, 'sub-title');
                                $items[$program_start][PluginTvExtEpgProgram::main_category] = self::get_node_value($tag, 'category');
                                $items[$program_start][PluginTvExtEpgProgram::year] = self::get_node_value($tag, 'date');
                                $items[$program_start][PluginTvExtEpgProgram::country] = self::get_node_value($tag, 'country');
                                foreach ($tag->getElementsByTagName('credits') as $sub_tag) {
                                    $items[$program_start][PluginTvExtEpgProgram::director] = self::get_node_value($sub_tag, 'director');
                                    $items[$program_start][PluginTvExtEpgProgram::producer] = self::get_node_value($sub_tag, 'producer');
                                    $items[$program_start][PluginTvExtEpgProgram::actor] = self::get_node_value($sub_tag, 'actor');
                                    $items[$program_start][PluginTvExtEpgProgram::presenter] = self::get_node_value($sub_tag, 'presenter'); //Ведущий
                                    $items[$program_start][PluginTvExtEpgProgram::writer] = self::get_node_value($sub_tag, 'writer');
                                    $items[$program_start][PluginTvExtEpgProgram::editor] = self::get_node_value($sub_tag, 'editor');
                                    $items[$program_start][PluginTvExtEpgProgram::composer] = self::get_node_value($sub_tag, 'composer');
                                }
                                foreach ($tag->getElementsByTagName('image') as $sub_tag) {
                                    if (!empty($sub_tag->nodeValue)) {
                                        $items[$program_start][PluginTvExtEpgProgram::icon_urls][] = $sub_tag->nodeValue;
                                    }
                                }
                            }
                        }

                        fclose($handle);

                        if (!empty($items)) break;
                    }
                }
            } catch (Exception $ex) {
                print_backtrace_exception($ex);
            }
        }

        $this->delayed_epg = array_unique($this->delayed_epg);

        if (empty($items)) {
            if (self::$xmltv_sources->size() === 0) {
                $items = self::getFakeEpg($channel_row, $day_start_ts, $items);
                if (empty($items)) {
                    $items = array($day_start_ts => array(
                        PluginTvEpgProgram::end_tm_sec => $day_end_ts,
                        PluginTvEpgProgram::name => TR::load('epg_no_sources'),
                        PluginTvEpgProgram::description => TR::load('epg_no_sources_desc'))
                    );
                }
            } else if (!empty($this->delayed_epg) && self::get_any_index_locked() !== false) {
                hd_debug_print("Delayed epg: " . json_encode($this->delayed_epg));
                $items = array($day_start_ts => array(
                    PluginTvEpgProgram::end_tm_sec => $day_end_ts,
                    PluginTvEpgProgram::name => TR::load('epg_not_ready'),
                    PluginTvEpgProgram::description => TR::load('epg_not_ready_desc'))
                );
            } else {
                $items = self::getFakeEpg($channel_row, $day_start_ts, $items);
            }
        } else {
            hd_debug_print("Store day epg to memory cache");
            self::$epg_cache[$channel_id][$day_start_ts] = $items;
            ksort($items);
        }
        $day_epg['items'] = $items;

        return $day_epg;
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// public static methods

    /**
     * @return Hashed_Array
     */
    public static function get_sources()
    {
        return self::$xmltv_sources;
    }

    /**
     * Set active sources (Hashed_Array of url params)
     *
     * @param Hashed_Array<array> $sources
     * @return void
     */
    public static function set_xmltv_sources($sources)
    {
        if ($sources->size() === 0) {
            hd_debug_print("No XMLTV source selected");
        } else {
            hd_debug_print("XMLTV sources selected: $sources");
        }

        self::$xmltv_sources = $sources;
    }

    /**
     * Get picon for channel
     *
     * @param $db_name
     * @param string $placeHolders
     * @return string
     */
    public static function get_picon($db_name, $placeHolders)
    {
        if (self::is_index_locked($db_name, INDEXING_DOWNLOAD | INDEXING_CHANNELS)) {
            hd_debug_print("File is indexing or downloading, skipped");
            return false;
        }

        $ch_table_name = self::TABLE_CHANNELS;
        $picons_table_name = self::TABLE_PICONS;

        $query = "SELECT DISTINCT picon_url FROM $picons_table_name
                    INNER JOIN $ch_table_name ON $picons_table_name.picon_hash=$ch_table_name.picon_hash
                    WHERE alias IN ($placeHolders);";

        $db = self::open_sqlite_db($db_name, self::TABLE_CHANNELS, true);
        if ($db === false) {
            return false;
        }

        return $db->query_value($query);
    }

    /**
     * Function to parse xmltv source in separate process
     * Only one XMLTV source must be sent via config
     * Plugin not available at this time!
     *
     * @param $config_file
     * @return void
     */
    public static function index_by_config($config_file)
    {
        global $LOG_FILE;

        try {
            if (!file_exists($config_file)) {
                Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, "Config file for indexing not exist");
                throw new Exception("Config file for indexing not exist");
            }

            $config = json_decode(file_get_contents($config_file), true);
            if (!LogSeverity::$is_debug) {
                safe_unlink($config_file);
            }
            if ($config === false) {
                Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, "Invalid config file for indexing");
                throw new Exception("Invalid config file for indexing");
            }

            if (empty($config[PARAMS_XMLTV])) {
                throw new Exception("Empty XMLTV config for indexing");
            }

            $LOG_FILE = get_temp_path("{$config[PARAMS_XMLTV][PARAM_HASH]}_indexing.log");
            if (!LogSeverity::$is_debug) {
                safe_unlink($LOG_FILE);
            }

            date_default_timezone_set('UTC');

            set_debug_log($config[PARAM_COOKIE_ENABLE_DEBUG]);

            self::set_cache_dir($config[PARAM_CACHE_DIR]);

            hd_print("Script config");
            hd_print("Log:         " . $LOG_FILE);
            hd_print("Cache dir:   " . self::$cache_dir);
            hd_print("Index flag:  " . $config[PARAM_INDEXING_FLAG]);
            hd_print("XMLTV param: " . json_encode($config[PARAMS_XMLTV]));

            self::reindex_xmltv($config[PARAMS_XMLTV], $config[PARAM_INDEXING_FLAG]);
        } catch (Exception $exception) {
            hd_debug_print($exception);
        }

        if (is_limited_apk()) {
            return;
        }

        $port = getenv('HD_HTTP_LOCAL_PORT');
        if (empty($port)) {
            $port = 80;
        }
        $res = shell_exec("wget -q -O - \"http://127.0.0.1:$port/cgi-bin/do?cmd=ui_state&result_syntax=json\"");
        $status = json_decode($res);
        if (isset($status->ui_state->screen->folder_type) && strpos($status->ui_state->screen->folder_type, ".proiptv") !== false) {
            hd_print("Rise finishing event: " . DuneIrControl::$key_codes[EVENT_INDEXING_DONE]);
            shell_exec('echo ' . DuneIrControl::$key_codes[EVENT_INDEXING_DONE] . ' > /proc/ir/button');
        } else {
            hd_print("Plugin not active. Do not notify them");
        }
    }

    /**
     * check xmltv source and return required flags for indexing
     *
     * @param Default_Dune_Plugin $plugin
     * @param array $params
     * @param int $index_flag
     * @return int
     */
    public static function check_xmltv_source($plugin, $params, $index_flag)
    {
        hd_debug_print(null, true);

        if (empty($params[PARAM_URI]) || empty($params[PARAM_HASH])) {
            $exception_msg = "XMTLV EPG url not set";
            Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, $exception_msg);
            $index_log = get_temp_path("{$params[PARAM_HASH]}_indexing.log");
            safe_unlink($index_log);
            return 0;
        }

        $url = $params[PARAM_URI];
        $hash = $params[PARAM_HASH];

        $cache_ttl = !isset($params[PARAM_CACHE]) ? XMLTV_CACHE_AUTO : $params[PARAM_CACHE];

        Dune_Last_Error::clear_last_error(LAST_ERROR_XMLTV);

        $cached_file = self::$cache_dir . $hash . ".xmltv";
        $cached_db = self::$cache_dir . $hash . ".db";
        hd_debug_print("Checking cached xmltv file: $cached_file", true);
        $expired = true;
        if (!file_exists($cached_file) || !file_exists($cached_db)) {
            hd_debug_print("Cached xmltv file not exist");
        } else {
            $modify_time_file = filemtime($cached_file);
            hd_debug_print("Xmltv cache ($cache_ttl) last modified: " . date("Y-m-d H:i", $modify_time_file), true);

            if ($cache_ttl === XMLTV_CACHE_AUTO) {
                $curl_wrapper = Curl_Wrapper::getInstance();
                $plugin->set_curl_timeouts($curl_wrapper);
                if (!$curl_wrapper->check_is_expired($url)) {
                    $expired = false;
                } else if (Curl_Wrapper::is_cached_etag($url)) {
                    Curl_Wrapper::clear_cached_etag($url);
                }
            } else if (filesize($cached_file) !== 0) {
                $max_cache_time = 3600 * 24 * $cache_ttl;
                $expired_time = $modify_time_file + $max_cache_time;
                hd_debug_print("Xmltv cache expired at: " . date("Y-m-d H:i", $expired_time), true);
                if ($modify_time_file && $expired_time > time()) {
                    $expired = false;
                }
            }
        }

        if ($expired) {
            self::clear_epg_files($hash);
            $index_flag |= INDEXING_DOWNLOAD;
            hd_debug_print("Xmltv cache expired. Indexing flags: " . $index_flag, true);
            $channels_valid = false;
            $entries_valid = false;
        } else {
            hd_debug_print("Cached file: $cached_file is not expired");
            $indexed = self::get_indexes_info($params);
            // index for picons has not verified because it always exist if channels index is present
            $channels_valid = ($indexed[self::TABLE_CHANNELS] !== -1);
            $entries_valid = ($indexed[self::TABLE_ENTRIES] !== -1);
        }


        if ($channels_valid) {
            if (($index_flag & INDEXING_ENTRIES) === 0) {
                hd_debug_print("Xmltv channels index is valid");
                self::clear_log($hash);
                return 0;
            }

            if ($entries_valid) {
                hd_debug_print("Xmltv channels and entries index are valid");
                self::clear_log($hash);
                return 0;
            }
        }

        if (!$channels_valid) {
            hd_debug_print("Xmltv channels not valid");
            $index_flag |= INDEXING_CHANNELS;
        }

        if (!$entries_valid && ($index_flag & INDEXING_ENTRIES) !== 0) {
            hd_debug_print("Xmltv entries not valid");
            $index_flag |= INDEXING_ENTRIES;
        }

        // downloaded xmltv file exists, not expired but indexes for channels, picons and positions not exists
        hd_debug_print("Index flag: $index_flag");
        return $index_flag;
    }

    /**
     * Get information about indexes
     *
     * @param array $params
     * @return array
     */
    public static function get_indexes_info($params)
    {
        hd_debug_print(null, true);
        $result = array(self::TABLE_CHANNELS => -1, self::TABLE_PICONS => -1, self::TABLE_ENTRIES => -1, 'epg_ids' => -1);

        $db_name = $params[PARAM_HASH];

        foreach ($result as $key => $name) {
            if ($key === 'epg_ids') continue;

            $db = self::open_sqlite_db($db_name, $key, true);
            if (empty($db) || !$db->is_table_exists($key)) continue;

            if ($key === self::TABLE_CHANNELS) {
                $result[$key] = (int)$db->query_value("SELECT COUNT(DISTINCT channel_id) FROM $key;");
            } else if ($key === self::TABLE_PICONS) {
                $result[$key] = (int)$db->query_value("SELECT COUNT(*) FROM $key;");
            } else if ($key === self::TABLE_ENTRIES) {
                $result[$key] = (int)$db->query_value("SELECT COUNT(*) FROM $key;");
                $result['epg_ids'] = (int)$db->query_value("SELECT COUNT(DISTINCT channel_id) FROM $key;");
            }
        }

        hd_debug_print("Indexes info: " . json_encode($result));
        return $result;
    }

    /**
     * Download and index xmltv source
     *
     * @param array $params
     * @param int $indexing_flag
     * @return void
     */
    public static function reindex_xmltv($params, $indexing_flag)
    {
        hd_debug_print("Indexing xmltv");

        $url = $params[PARAM_URI];
        $url_hash = $params[PARAM_HASH];

        if (empty($url) || empty($url_hash)) {
            hd_debug_print("Url not set, skipped");
            return;
        }

        $perf = new Perf_Collector();
        $perf->reset('start');

        $cached_file = self::$cache_dir . $url_hash . ".xmltv";
        $params[PARAM_CACHE_PATH] = $cached_file;

        /// download source
        if ($indexing_flag & INDEXING_DOWNLOAD) {
            hd_debug_print("Download xmltv");
            // download xmtv is denied if download or any indexing in process
            if (self::is_index_locked($url_hash, INDEXING_ALL)) {
                hd_debug_print("File is indexing or downloading, skipped");
                return;
            }

            Dune_Last_Error::clear_last_error(LAST_ERROR_XMLTV);

            self::lock_index($url_hash, INDEXING_DOWNLOAD);
            $success = false;

            try {
                if (preg_match("/jtv.?\.zip$/", basename(urldecode($url)))) {
                    hd_debug_print("Unsupported EPG format (JTV)");
                    throw new Exception("Unsupported EPG format (JTV)");
                }

                $perf->setLabel('start_download');
                self::download_xmltv($params);
                $perf->setLabel('start_unpack');
                self::unpack_xmltv($params);
                $success = true;
            } catch (Exception $ex) {
                Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, $ex->getMessage());
                print_backtrace_exception($ex);
                $tmp_filename = $cached_file . ".tmp";
                safe_unlink($tmp_filename);
                safe_unlink($cached_file);
            }

            self::unlock_index($url_hash, INDEXING_DOWNLOAD);

            if (!$success) {
                return;
            }
        }

        /// Reindex channels and picons
        if ($indexing_flag & INDEXING_CHANNELS) {
            hd_debug_print("Start index channels and picons...");
            self::lock_index($url_hash, INDEXING_CHANNELS);

            $success = false;
            $file = false;
            try {
                if (!file_exists($cached_file)) {
                    throw new Exception("reindex_xmltv_channels: Cache file $cached_file not exist");
                }

                $file = fopen($cached_file, 'rb');
                if (!$file) {
                    throw new Exception("reindex_xmltv_channels: Can't open file: $cached_file");
                }

                $db = self::open_sqlite_db($url_hash, self::TABLE_CHANNELS, false);
                if ($db === false) {
                    throw new Exception("reindex_xmltv_channels: Can't open db: $url_hash");
                }

                $perf->setLabel('start_channels');

                $ch_table_name = self::TABLE_CHANNELS;
                $picons_table_name = self::TABLE_PICONS;

                $query = "DROP TABLE IF EXISTS $ch_table_name;";
                $query .= "DROP TABLE IF EXISTS $picons_table_name;";
                $query .= "CREATE TABLE $ch_table_name (alias TEXT PRIMARY KEY not null, channel_id TEXT not null, picon_hash TEXT);";
                $query .= "CREATE TABLE $picons_table_name (picon_hash TEXT PRIMARY KEY not null, picon_url TEXT);";
                $res = $db->exec_transaction($query);
                if (!$res) {
                    throw new Exception("Error transaction: $query");
                }

                $query = '';
                while (!feof($file)) {
                    $line = stream_get_line($file, 0, "</channel>");
                    if (empty($line)) continue;
                    $pos = strpos($line, "<channel ");
                    if ($pos === false) continue;
                    if ($pos !== 0) {
                        $line = substr($line, $pos);
                    }

                    $line = $line . "</channel>";

                    $xml_node = new DOMDocument();
                    $xml_node->loadXML($line);
                    foreach ($xml_node->getElementsByTagName('channel') as $tag) {
                        $channel_id = $tag->getAttribute('id');
                    }

                    if (empty($channel_id)) continue;

                    $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
                    $picon_hash = '';
                    foreach ($xml_node->getElementsByTagName('icon') as $tag) {
                        if (is_proto_http($tag->getAttribute('src'))) {
                            $picon_url = $tag->getAttribute('src');
                            if (!empty($picon_url)) {
                                $picon_hash = md5($picon_url);
                                $q_url = Sql_Wrapper::sql_quote($picon_url);
                                $query .= "INSERT OR REPLACE INTO $picons_table_name (picon_hash, picon_url) VALUES('$picon_hash', $q_url);";
                                break;
                            }
                        }
                    }

                    $q_picon_hash = Sql_Wrapper::sql_quote($picon_hash);
                    $q_alias = Sql_Wrapper::sql_quote(mb_convert_case($channel_id, MB_CASE_LOWER, "UTF-8"));
                    $query .= "INSERT OR IGNORE INTO $ch_table_name (alias, channel_id, picon_hash) VALUES($q_alias, $q_channel_id, $q_picon_hash);";

                    foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                        $q_alias = Sql_Wrapper::sql_quote(mb_convert_case($tag->nodeValue, MB_CASE_LOWER, "UTF-8"));
                        $query .= "INSERT OR IGNORE INTO $ch_table_name (alias, channel_id, picon_hash) VALUES($q_alias, $q_channel_id, $q_picon_hash);";
                    }
                }
                $db->exec_transaction($query);

                $channels = (int)$db->query_value("SELECT count(DISTINCT channel_id) FROM $ch_table_name;");
                $picons = (int)$db->query_value("SELECT COUNT(*) FROM $picons_table_name;");

                $perf->setLabel('end_channels');
                $report = $perf->getFullReport('start_channels', 'end_channels');

                hd_debug_print("Total channels id's: $channels");
                hd_debug_print("Total known picons:  $picons");
                hd_debug_print("Reindexing channels: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Memory usage:        {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
                hd_debug_print("Storage space:       " . HD::get_storage_size(self::$cache_dir));
                hd_debug_print_separator();

                self::update_stat($cached_file, 'channels', $report[Perf_Collector::TIME]);
                $success = true;
            } catch (Exception $ex) {
                hd_debug_print($ex->getMessage());
            }

            if ($file) {
                fclose($file);
            }

            self::unlock_index($url_hash, INDEXING_CHANNELS);
            if (!$success) {
                return;
            }
        }

        /// Reindex positions
        if ($indexing_flag & INDEXING_ENTRIES) {
            hd_debug_print("Start indexing entries...");
            self::lock_index($url_hash, INDEXING_ENTRIES);

            $file = false;
            try {
                if (!file_exists($cached_file)) {
                    throw new Exception("reindex_xmltv_entries: Cache file $cached_file not exist");
                }

                $file = fopen($cached_file, 'rb');
                if (!$file) {
                    throw new Exception("reindex_xmltv_entries: Can't open file: $cached_file");
                }

                $db = self::open_sqlite_db($url_hash, self::TABLE_ENTRIES, false);
                if ($db === false) {
                    throw new Exception("reindex_xmltv_entries: Can't open db: $url_hash");
                }

                hd_debug_print("Indexing positions for: $url", true);
                $perf->setLabel('start_reindex_entries');

                $pos_table_name = self::TABLE_ENTRIES;
                $query = "DROP TABLE IF EXISTS $pos_table_name;";
                $query .= "CREATE TABLE $pos_table_name (channel_id STRING not null, start INTEGER, end INTEGER, UNIQUE (channel_id, start) ON CONFLICT REPLACE);";
                $res = $db->exec_transaction($query);
                if (!$res) {
                    throw new Exception("Error transaction: $query");
                }

                hd_debug_print("Begin transactions...", true);
                $db->exec('BEGIN;');

                $stm = $db->prepare("INSERT INTO $pos_table_name (channel_id, start, end) VALUES(:channel_id, :start, :end);");
                /** @var string $prev_channel */
                /** @var int $start_program_block */
                /** @var int $tag_end_pos */
                $stm->bindParam(":channel_id", $prev_channel);
                $stm->bindParam(":start", $start_program_block);
                $stm->bindParam(":end", $tag_end_pos);

                $start_program_block = 0;
                $prev_channel = null;
                fseek($file, 0);
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
                    // $tag_end_pos = ftell($file);
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
                        $res = $stm->execute();
                        if ($res === false) {
                            hd_debug_print("Error inserting position start: $start_program_block end: $tag_end_pos for channel: $prev_channel");
                        }
                        $prev_channel = $channel_id;
                        $start_program_block = $tag_start_pos;
                    }
                }

                hd_debug_print("End transactions...", true);
                $db->exec('COMMIT;');

                $total_epg = (int)$db->query_value("SELECT count(DISTINCT channel_id) FROM $pos_table_name;");
                $total_blocks = (int)$db->query_value("SELECT COUNT(*) FROM $pos_table_name;");

                $perf->setLabel('end_reindex_entries');
                $report = $perf->getFullReport('start_reindex_entries', 'end_reindex_entries');

                hd_debug_print("Total unique epg id's indexed: $total_epg, total blocks: $total_blocks");
                hd_debug_print("Reindexing entries: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Memory usage:       {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
                hd_debug_print("Storage space:      " . HD::get_storage_size(self::$cache_dir));
                hd_debug_print_separator();

                self::update_stat($cached_file, 'entries', $report[Perf_Collector::TIME]);
            } catch (Exception $ex) {
                hd_debug_print($ex->getMessage());
            }

            if ($file) {
                fclose($file);
            }

            self::unlock_index($url_hash, INDEXING_ENTRIES);
        }

        if ($perf->getLabelsCount() > 1) {
            $report = $perf->getFullReport();
            hd_debug_print("Reindexing XMLTV source done: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print_separator();
        }
    }

    /**
     * Import indexing log to plugin logs
     *
     * @param array $sources_hash
     * @return int  0 - if no locks and no imports,
     *              1 - if all import successful and no other active locks,
     *              2 - if any active source is locked
     *             -1 - if no locks and no imports but has error
     *             -2 - if import successful and no other active locks but some error occurred
     */
    public static function import_indexing_log($sources_hash)
    {
        $has_locks = false;
        if (empty($sources_hash)) {
            return 0;
        }

        $has_imports = false;
        foreach ($sources_hash as $hash) {
            if (self::is_index_locked($hash, INDEXING_ALL)) {
                $has_locks = true;
                continue;
            }

            $index_log = get_temp_path("{$hash}_indexing.log");
            if (file_exists($index_log)) {
                hd_debug_print("Read epg indexing log $index_log...");
                hd_debug_print_separator();
                $logfile = file_get_contents($index_log);
                foreach (explode(PHP_EOL, $logfile) as $l) {
                    hd_print(preg_replace("|^\[[\d:-]+\s(.*)$|", "[$1", rtrim($l)));
                }
                hd_debug_print_separator();
                hd_debug_print("Read finished");
                safe_unlink($index_log);
                $has_imports = true;
            }

            $error_log = get_temp_path("{$hash}_bg_error.log");
            if (file_exists($error_log)) {
                $error_file = file_get_contents($error_log);
                if (!empty($error_file)) {
                    hd_debug_print("Read indexing error log $error_log...");
                    hd_debug_print_separator();
                    foreach (explode(PHP_EOL, $error_file) as $l) {
                        if (!empty($l)) {
                            hd_print($l);
                        }
                    }
                    hd_debug_print_separator();
                    $has_imports = true;
                }
            }
            safe_unlink($error_log);
        }

        if ($has_locks) {
            return 2;
        }

        $last_error = Dune_Last_Error::get_last_error(LAST_ERROR_XMLTV, false);

        if ($has_imports) {
            return empty($last_error) ? 1 : -1;
        }

        return empty($last_error) ? 0 : -2;
    }

    public static function get_stat($cached_file)
    {
        $stat = array();
        $stat_file = $cached_file . '.stat';
        if (file_exists($stat_file)) {
            $stat = json_decode(file_get_contents($stat_file), true);
        }
        return $stat;
    }

    /**
     * Check if lock for specified cache is exist
     *
     * @param string $hash
     * @param int $index_flag
     * @return bool
     */
    public static function is_index_locked($hash, $index_flag)
    {
        $locked = false;
        foreach (self::$index_flags as $flag) {
            if ($index_flag & $flag) {
                $dirs = glob(self::get_lock_name($hash, $flag, '*'), GLOB_ONLYDIR);
                $locked |= !empty($dirs);
            }
        }

        return $locked;
    }

    /**
     * Check if any locks for all sources and return name of locks
     *
     * @return bool|array
     */
    public static function get_any_index_locked()
    {
        $dirs = glob(self::get_lock_name('', 0), GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $locks[] = basename($dir);
        }

        return empty($locks) ? false : $locks;
    }

    /**
     * clear cache for selected filename (hash) mask
     *
     * @param string|null $hash
     * @return void
     */
    public static function clear_epg_files($hash = '')
    {
        hd_debug_print(null, true);

        self::clear_epg_memory_cache();

        if (empty(self::$cache_dir)) {
            hd_debug_print("Cache directory not set");
            return;
        }

        if (empty($hash)) {
            self::$epg_db = array();
        } else if (isset(self::$epg_db[$hash])) {
            unset(self::$epg_db[$hash]);
        }

        Curl_Wrapper::clear_cached_etag_by_hash($hash);

        $dirs = glob(self::get_lock_name($hash, 0, '*'), GLOB_ONLYDIR);
        $locks = array();
        foreach ($dirs as $dir) {
            hd_debug_print("Found locks: $dir");
            $locks[] = $dir;
        }

        if (!empty($locks)) {
            foreach ($locks as $lock) {
                $ar = explode('_', basename($lock));
                $pid = (int)end($ar);

                if ($pid !== 0 && send_process_signal($pid, 0)) {
                    hd_debug_print("Kill process $pid");
                    send_process_signal($pid, -9);
                }
                hd_debug_print("Remove lock: $lock");
                rmdir($lock);
            }
        }

        $files = self::$cache_dir . $hash . "*";
        hd_debug_print("clear epg files: $files");
        array_map('unlink', glob($files));
        clearstatcache();
        hd_debug_print("Storage space:  " . HD::get_storage_size(self::$cache_dir));
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected static methods

    /**
     * Set and create cache dir
     *
     * @param string $cache_dir
     */
    protected static function set_cache_dir($cache_dir)
    {
        self::$cache_dir = get_slash_trailed_path($cache_dir);
        create_path(self::$cache_dir);

        hd_debug_print("Cache dir:      " . self::$cache_dir);
        hd_debug_print("Storage space:  " . HD::get_storage_size(self::$cache_dir));
    }

    /**
     * Clear memory cache
     * @return void
     */
    protected static function clear_epg_memory_cache()
    {
        self::$epg_cache = array();
    }

    /**
     * @param string $hash
     * @param int $index_flag
     */
    protected static function lock_index($hash, $index_flag)
    {
        foreach (self::$index_flags as $flag) {
            if ($index_flag & $flag) {
                self::set_lock(self::get_lock_name($hash, $flag), true);
            }
        }
    }

    /**
     * @param string $hash
     * @param int $index_flag
     */
    protected static function unlock_index($hash, $index_flag)
    {
        foreach (self::$index_flags as $flag) {
            if ($index_flag & $flag) {
                self::set_lock(self::get_lock_name($hash, $flag), false);
            }
        }
    }

    protected static function set_lock($name, $lock)
    {
        if ($lock) {
            if (!create_path($name, 0644)) {
                hd_debug_print("Directory '$name' was not created");
            } else {
                hd_debug_print("Lock $name");
            }
        } else if (is_dir($name)) {
            hd_debug_print("Unlock $name");
            rmdir($name);
            clearstatcache();
        }
    }

    /**
     * If $hash is empty return glob mask for all locks in cache dir *.?lock
     * If $index_flag == 0 return glob mask for any lock for $hash and $pid .?lock
     * @param string $hash
     * @param int $index_flag
     * @param string $pid
     * @return string
     */
    protected static function get_lock_name($hash, $index_flag, $pid = '')
    {
        $pid = empty($pid) ? getmypid() : $pid;

        if ($index_flag === INDEXING_DOWNLOAD) {
            $ext = self::$cache_dir . $hash . "_$pid.dlock";
        } else if ($index_flag === INDEXING_CHANNELS) {
            $ext = self::$cache_dir . $hash . "_$pid.clock";
        } else if ($index_flag === INDEXING_ENTRIES) {
            $ext = self::$cache_dir . $hash . "_$pid.elock";
        } else if (empty($hash)) {
            $ext = self::$cache_dir . "*.?lock";
        } else {
            $ext = self::$cache_dir . $hash . "_$pid.?lock";
        }

        return $ext;
    }

    /**
     * @param array $params
     * @param array $channel_row
     * @return array|null
     */
    protected function load_program_index($params, $channel_row)
    {
        $channel_positions = array();

        if (!$this->is_all_indexes_valid($params, array(self::TABLE_CHANNELS, self::TABLE_ENTRIES))) {
            hd_debug_print("EPG for {$params[PARAM_URI]} not indexed!");
            return $channel_positions;
        }

        $channel_id = $channel_row[COLUMN_CHANNEL_ID];
        $channel_title = $channel_row[COLUMN_TITLE];
        $epg_ids = array_unique(array_filter(array(
            $channel_row[COLUMN_EPG_ID],
            $channel_id,
            $channel_row[COLUMN_TVG_NAME],
            $channel_title))
        );

        $aliases = Sql_Wrapper::sql_make_list_from_values(array_map(function($value) {
            return mb_convert_case($value, MB_CASE_LOWER, "UTF-8");
        }, $epg_ids));

        hd_debug_print("Search for aliases: $aliases", true);

        $db_channels = self::open_sqlite_db($params[PARAM_HASH], self::TABLE_CHANNELS, true);
        if ($db_channels === false) {
            hd_debug_print("Problem with open SQLite channels db! Possible database not exist");
            return $channel_positions;
        }

        $table_channels = self::TABLE_CHANNELS;
        $query = "SELECT DISTINCT channel_id FROM $table_channels WHERE alias IN ($aliases);";
        $channel_ids = $db_channels->fetch_single_array($query, COLUMN_CHANNEL_ID);
        if (empty($channel_ids)) {
            hd_debug_print("No channel_id found for aliases: $aliases");
            return $channel_positions;
        }

        hd_debug_print("Load position indexes for: $channel_id ($channel_title)", true);
        $db_entries = self::open_sqlite_db($params[PARAM_HASH], self::TABLE_ENTRIES, true);
        if ($db_entries === false) {
            hd_debug_print("Problem with open SQLite channels db! Possible database not exist");
            return $channel_positions;
        }

        $table_pos = self::TABLE_ENTRIES;
        $where = Sql_Wrapper::sql_make_where_clause($channel_ids, COLUMN_CHANNEL_ID);
        $query = "SELECT start, end FROM $table_pos WHERE $where;";
        $channel_positions = $db_entries->fetch_array($query);
        if (empty($channel_positions)) {
            $ids = Sql_Wrapper::sql_make_list_from_values($channel_ids);
            hd_debug_print("No positions found for channel $channel_id ($channel_title) and channel id's: $ids");
        } else {
            hd_debug_print("Channel positions: " . json_encode($channel_positions), true);
        }

        return $channel_positions;
    }

    /**
     * Check is all indexes is valid
     *
     * @param array $params
     * @param array $names
     * @return bool
     */
    protected function is_all_indexes_valid($params, $names)
    {
        hd_debug_print(null, true);

        foreach ($names as $name) {
            $db = self::open_sqlite_db($params[PARAM_HASH], $name, true);
            if ($db === false) {
                hd_debug_print("Database not exist");
                return false;
            }

            if (!$db->is_table_exists($name)) {
                hd_debug_print("Table '$name' not exist");
                return false;
            }
        }
        return true;
    }

    protected static function get_node_value($node, $name)
    {
        $value = '';
        foreach ($node->getElementsByTagName($name) as $element) {
            if (!empty($element->nodeValue)) {
                $value = $element->nodeValue;
                break;
            }
        }

        return $value;
    }

    protected static function get_node_attribute($node, $name, $attribute)
    {
        $value = '';
        foreach ($node->getElementsByTagName($name) as $element) {
            $value = $element->getAttribute($attribute);
            break;
        }

        return $value;
    }

    protected static function clear_log($hash)
    {
        safe_unlink(get_temp_path("{$hash}_indexing.log"));
    }

    /**
     * @param array $channel_row
     * @param int $day_start_ts
     * @param array $day_epg
     * @return array
     */
    protected static function getFakeEpg($channel_row, $day_start_ts, $day_epg)
    {
        if ((self::$flags & EPG_FAKE_EPG) && $channel_row[COLUMN_ARCHIVE] !== 0) {
            hd_debug_print("Create fake data for non existing EPG data");
            for ($start = $day_start_ts, $n = 1; $start <= $day_start_ts + 86400; $start += 3600, $n++) {
                $day_epg[$start][PluginTvEpgProgram::end_tm_sec] = $start + 3600;
                $day_epg[$start][PluginTvEpgProgram::name] = TR::load('fake_epg_program') . " $n";
                $day_epg[$start][PluginTvEpgProgram::description] = '';
            }
        }

        return $day_epg;
    }

    /**
     * open sqlite database
     * @param string $db_name
     * @param string $table_name
     * @param bool $readonly
     * @return Sql_Wrapper|bool
     */
    protected static function open_sqlite_db($db_name, $table_name, $readonly)
    {
        if ($table_name === self::TABLE_ENTRIES) {
            $db_name = $db_name . "_entries";
        }

        $db_file = self::$cache_dir . $db_name . ".db";
        // in read-only database can't be created
        if ($readonly && !file_exists($db_file)) {
            hd_debug_print("File '$db_file' for '$db_name' not found");
            return false;
        }

        // if database not exist or requested mode is read-write create new database
        if (!isset(self::$epg_db[$db_name]) || (!$readonly && self::$epg_db[$db_name]->is_readonly())) {
            $flags = $readonly ? SQLITE3_OPEN_READONLY : (SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $db = new Sql_Wrapper($db_file, $flags);
            if (!$db->is_valid()) {
                return false;
            }
            self::$epg_db[$db_name] = $db;
        }

        return self::$epg_db[$db_name];
    }

    /**
     * @param array $params
     * @throws Exception
     */
    protected static function download_xmltv($params)
    {
        $url = $params[PARAM_URI];
        $cached_file = $params[PARAM_CACHE_PATH];

        hd_debug_print("Download xmltv source: $url");
        hd_debug_print("Storage space:  " . HD::get_storage_size(self::$cache_dir));

        $perf = new Perf_Collector();

        $perf->reset('start_download');

        $tmp_filename = $cached_file . ".tmp";
        safe_unlink($tmp_filename);
        safe_unlink("$cached_file.stat");

        hd_debug_print("Download: $url");
        Curl_Wrapper::clear_cached_etag($url);

        $curl_wrapper = Curl_Wrapper::getInstance();
        $curl_wrapper->set_connection_timeout($params[PARAM_CURL_CONNECT_TIMEOUT]);
        $curl_wrapper->set_download_timeout($params[PARAM_CURL_DOWNLOAD_TIMEOUT]);
        if (!$curl_wrapper->download_file($url, $tmp_filename, true)) {
            $http_code = $curl_wrapper->get_http_code();
            if ($curl_wrapper->get_error_no() !== 0) {
                $msg = "CURL errno: {$curl_wrapper->get_error_no()}\n{$curl_wrapper->get_error_desc()}\nHTTP code: $http_code";
            } else {
                $msg = "HTTP request failed ($http_code)\n\n" . $curl_wrapper->get_raw_response_headers();
            }

            throw new Exception("Can't download file\n$msg");
        }

        $http_code = $curl_wrapper->get_http_code();
        if ($http_code !== 200) {
            throw new Exception("Download error ($http_code) $url\n\n" . $curl_wrapper->get_raw_response_headers());
        }

        $perf->setLabel('end_download');
        $file_time = filemtime($tmp_filename);
        $dl_time = $perf->getReportItem(Perf_Collector::TIME, 'start_download', 'end_download');
        $file_size = filesize($tmp_filename);
        $bps = $file_size / $dl_time;
        $si_prefix = array('B/s', 'KB/s', 'MB/s');
        $base = 1024;
        $class = min((int)log($bps, $base), count($si_prefix) - 1);
        $speed = sprintf('%1.2f', $bps / pow($base, $class)) . ' ' . $si_prefix[$class];

        hd_debug_print("ETag value:     " . trim(Curl_Wrapper::get_cached_etag($url), '"'));
        hd_debug_print("Modify time:    " . date("Y-m-d H:i", $file_time));
        hd_debug_print("Download size:  $file_size bytes");
        hd_debug_print("Download time:  $dl_time secs");
        hd_debug_print("Download speed: $speed");
        hd_debug_print("Storage space:  " . HD::get_storage_size(self::$cache_dir));
        hd_debug_print_separator();
        self::update_stat($cached_file, 'download', $dl_time);
    }

    /**
     * @param array $params
     * @throws Exception
     */
    protected static function unpack_xmltv($params)
    {
        $cached_file = $params[PARAM_CACHE_PATH];
        hd_debug_print("Remove cached file: $cached_file");
        safe_unlink($cached_file);

        $perf = new Perf_Collector();
        $perf->reset('start_unpack');

        $tmp_filename = $cached_file . ".tmp";
        $file_time = filemtime($tmp_filename);
        $handle = fopen($tmp_filename, "rb");
        $hdr = fread($handle, 8);
        fclose($handle);

        if (0 === mb_strpos($hdr, "\x1f\x8b\x08")) {
            hd_debug_print("GZ signature:  " . bin2hex(substr($hdr, 0, 3)), true);
            rename($tmp_filename, $cached_file . '.gz');
            $tmp_filename = $cached_file . '.gz';
            $cmd = "gzip -d $tmp_filename 2>&1";
            /** @var int $ret */
            system($cmd, $ret);
            if ($ret !== 0) {
                throw new Exception("Failed to ungzip $tmp_filename (error code: $ret)");
            }
            clearstatcache();
            $size = filesize($cached_file);
            touch($cached_file, $file_time);
            $action = 'UnGZip:';
        } else if (0 === mb_strpos($hdr, "\x50\x4b\x03\x04")) {
            hd_debug_print("ZIP signature: " . bin2hex(substr($hdr, 0, 4)), true);
            $filename = trim(shell_exec("unzip -lq '$tmp_filename'|grep -E '[\d:]+'"));
            if (empty($filename)) {
                throw new Exception(TR::t('err_empty_zip__1', $tmp_filename));
            }

            if (explode('\n', $filename) > 1) {
                throw new Exception("Too many files in zip archive, wrong format??!\n$filename");
            }

            hd_debug_print("zip list: $filename");
            $cmd = "unzip -oq $tmp_filename -d " . self::$cache_dir . " 2>&1";
            /** @var int $ret */
            system($cmd, $ret);
            safe_unlink($tmp_filename);
            if ($ret !== 0) {
                throw new Exception("Failed to unpack $tmp_filename (error code: $ret)");
            }
            clearstatcache();

            rename($filename, $cached_file);
            $size = filesize($cached_file);
            touch($cached_file, $file_time);
            $action = 'UnZip: ';
        } else if (false !== mb_strpos($hdr, "<?xml")) {
            hd_debug_print("XML signature: " . substr($hdr, 0, 5), true);
            rename($tmp_filename, $cached_file);
            $size = filesize($cached_file);
            touch($cached_file, $file_time);
            $action = 'Copy:  ';
        } else {
            hd_debug_print("Unknown signature: " . bin2hex($hdr), true);
            throw new Exception(TR::load('err_unknown_file_type'));
        }

        $unpack_time = $perf->getReportItemCurrent(Perf_Collector::TIME);
        hd_debug_print("Cached file:   $cached_file");
        hd_debug_print("$action        $size bytes");
        hd_debug_print("Time:          $unpack_time secs");
        hd_debug_print("Storage space: " . HD::get_storage_size(self::$cache_dir));
        hd_debug_print_separator();

        self::update_stat($cached_file, 'unpack', $unpack_time);
    }

    protected static function update_stat($cached_file, $tag, $time)
    {
        $stat_file = $cached_file . '.stat';
        if (file_exists($stat_file)) {
            $stat = json_decode(file_get_contents($stat_file), true);
        }
        $stat[$tag] = $time;
        file_put_contents($stat_file, json_encode($stat));
    }
}
