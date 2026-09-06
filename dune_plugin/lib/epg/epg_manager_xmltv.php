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

require_once 'starnet_epfs_handler.php';
require_once 'lib/tr.php';
require_once 'lib/hd.php';
require_once 'lib/hashed_array.php';
require_once 'lib/curl_wrapper.php';
require_once 'lib/sql_wrapper.php';
require_once 'lib/perf_collector.php';
require_once 'lib/dune_last_error.php';
require_once 'lib/epg/ext_epg_program.php';

class Epg_Manager_Xmltv
{
    const TABLE_PICONS = 'epg_picons';
    const TABLE_CHANNELS = 'epg_channels';
    const TABLE_ENTRIES = 'epg_entries';
    const TABLE_STAT = 'epg_stat';
    const CREATE_CHANNELS_TABLE = 'CREATE TABLE epg_channels (alias TEXT PRIMARY KEY not null, channel_id TEXT not null, picon_hash TEXT);';
    const CREATE_PICONS_TABLE = 'CREATE TABLE epg_picons (picon_hash TEXT PRIMARY KEY not null, picon_url TEXT);';
    const CREATE_ENTRIES_TABLE = 'CREATE TABLE epg_entries (channel_id STRING not null, start INTEGER, end INTEGER, UNIQUE (channel_id, start) ON CONFLICT REPLACE);';
    const CREATE_STAT_TABLE = 'CREATE TABLE IF NOT EXISTS epg_stat (name TEXT PRIMARY KEY, value REAL);';

    protected static $index_flags = array(INDEXING_DOWNLOAD, INDEXING_CHANNELS, INDEXING_ENTRIES);

    /**
     * path where cache is stored
     * @var string
     */
    protected static $cache_dir;

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
    protected static $delayed_epg = array();

    /**
     * @param Hashed_Array<string, array> $sources
     * @return void
     */
    public static function update_active_sources($sources)
    {
        self::$xmltv_sources = $sources;
    }

    /**
     * returns list of requested epg when indexing in process
     *
     * @return array
     */
    public static function get_delayed_epg()
    {
        return self::$delayed_epg;
    }

    /**
     * clear all delayed epg
     */
    public static function clear_delayed_epg()
    {
        self::$delayed_epg = array();
    }

    /**
     * Load epg for specified day
     *
     * @param array $channel_row
     * @param int $day_start_ts timestamp for day start in local time
     * @param string $found_in source where epg is found
     * @return array of entries started from day start for entire day
     */
    public function get_day_epg_items($channel_row, $day_start_ts, &$found_in)
    {
        $day_epg = array();
        $channel_id = safe_get_value($channel_row, COLUMN_CHANNEL_ID);
        if (empty($channel_id)) {
            return array();
        }

        $day_end_ts = $day_start_ts + 86400;

        $error_message = '';
        if (self::$xmltv_sources->is_empty()) {
            $error_message = TR::load('epg_no_sources_desc');
        }

        $day_items = array();
        $has_locks = false;
        foreach (self::$xmltv_sources as $key => $params) {
            hd_debug_print("Looking in XMLTV source: {$params[PARAM_URI]} ({$params[PARAM_HASH]})");
            if (self::is_index_locked($key, INDEXING_DOWNLOAD | INDEXING_ENTRIES)) {
                hd_debug_print("EPG {$params[PARAM_URI]} still indexing, append to delayed queue channel id: $channel_id");
                self::$delayed_epg[] = $channel_id;
                $has_locks = true;
                continue;
            }

            // filter out epg only for selected day
            if (LogSeverity::$is_debug) {
                $date_start_l = format_datetime('Y-m-d H:i', $day_start_ts);
                $date_end_l = format_datetime('Y-m-d H:i', $day_end_ts);
                hd_debug_print("Fetch entries for from: $date_start_l ($day_start_ts) to: $date_end_l ($day_end_ts)");
            }

            try {
                $first_in_range = PHP_INT_MAX - 1;
                $last_in_range = 0;

                $positions = self::load_program_index($params, $channel_row);
                if (!empty($positions)) {
                    $cached_file = self::$cache_dir . $params[PARAM_HASH] . ".xmltv";
                    if (!file_exists($cached_file)) {
                        throw new Exception("get_day_epg_items: cache file $cached_file not exist");
                    }

                    $update_ext_epg = function ($tag_name, $node_name, $tag, &$item) {
                        $value = Epg_Manager_Xmltv::get_node_value($tag, $node_name);
                        if (!empty($value)) {
                            if (isset($item[$tag_name])) {
                                $item[$tag_name] .= ',' . $value;
                            } else {
                                $item[$tag_name] = $value;
                            }
                        }
                    };

                    $collect_ext_epg = function ($tag_name, $node_name, $tag, &$item) {
                        $value = Epg_Manager_Xmltv::get_node_values($tag, $node_name);
                        if (!empty($value)) {
                            if (isset($item[$tag_name])) {
                                $item[$tag_name] = array_merge($item[$tag_name], $value);
                            } else {
                                $item[$tag_name] = $value;
                            }
                        }
                    };

                    $handle = fopen($cached_file, 'rb');
                    if (!$handle) {
                        throw new Exception("Unable to open: cached file $cached_file");
                    }

                    foreach ($positions as $pos) {
                        fseek($handle, $pos['start']);
                        $length = $pos['end'] - $pos['start'];
                        if ($length <= 0) continue;

                        $xml_str = "<tv>" . fread($handle, $pos['end'] - $pos['start']) . "</tv>";

                        $xml_node = new DOMDocument();
                        if ($xml_node->loadXML($xml_str, LIBXML_NOWARNING | LIBXML_NOERROR) === false) {
                            foreach (libxml_get_errors() as $error) {
                                display_xml_error($error, $xml_str);
                            }
                            libxml_clear_errors();
                            continue;
                        }

                        foreach ($xml_node->getElementsByTagName('programme') as $tag) {
                            $program_start = strtotime($tag->getAttribute('start'));
                            $program_end = strtotime($tag->getAttribute('stop'));
                            $first_in_range = min($program_start, $first_in_range);
                            $last_in_range = max($program_end, $last_in_range);

                            if ($program_start < $day_start_ts && $program_end < $day_start_ts) continue;
                            if ($program_start >= $day_end_ts) break;

                            $desc = unescape_entity_string(self::get_node_value($tag, 'desc'));
                            $icon = self::get_node_attribute($tag, 'icon', 'src');
                            $epg_items = array();
                            $epg_items[PluginTvEpgProgram::end_tm_sec] = $program_end;
                            $epg_items[PluginTvEpgProgram::name] = self::get_node_value($tag, 'title');
                            $epg_items[PluginTvEpgProgram::description] = $desc;
                            if (!empty($icon)) {
                                $epg_items[PluginTvEpgProgram::icon_url] = $icon;
                            }

                            $update_ext_epg(PluginTvExtEpgProgram::sub_title, 'sub-title', $tag, $epg_items);
                            $update_ext_epg(PluginTvExtEpgProgram::main_category, 'category', $tag, $epg_items);
                            $update_ext_epg(PluginTvExtEpgProgram::year, 'date', $tag, $epg_items);
                            $update_ext_epg(PluginTvExtEpgProgram::country, 'country', $tag, $epg_items);

                            $collect_ext_epg(PluginTvExtEpgProgram::icons, 'image', $tag, $epg_items);
                            foreach ($tag->getElementsByTagName('credits') as $sub_tag) {
                                $collect_ext_epg(PluginTvExtEpgProgram::director, 'director', $sub_tag, $epg_items);
                                $collect_ext_epg(PluginTvExtEpgProgram::producer, 'producer', $sub_tag, $epg_items);
                                $collect_ext_epg(PluginTvExtEpgProgram::actor, 'actor', $sub_tag, $epg_items);
                                $collect_ext_epg(PluginTvExtEpgProgram::presenter, 'presenter', $sub_tag, $epg_items);
                                $collect_ext_epg(PluginTvExtEpgProgram::writer, 'writer', $sub_tag, $epg_items);
                                $collect_ext_epg(PluginTvExtEpgProgram::editor, 'editor', $sub_tag, $epg_items);
                                $collect_ext_epg(PluginTvExtEpgProgram::composer, 'composer', $sub_tag, $epg_items);
                            }

                            if (!empty($epg_items)) {
                                $day_items[$program_start] = $epg_items;
                            }
                        }

                        fclose($handle);

                        if (!empty($day_items)) break;
                    }
                }

                if (!empty($day_items) && ($day_start_ts > $last_in_range || $day_end_ts < $first_in_range)) {
                    $first = format_datetime('Y-m-d H:i', $first_in_range);
                    $last = format_datetime('Y-m-d H:i', $last_in_range);
                    $error_message = "Selected time is out of range. Available EPG time range: $first ($first_in_range) - $last ($last_in_range)";
                    hd_debug_print($error_message);
                    $day_items = array();
                    continue;
                }
                if (!empty($day_items)) {
                    $found_in = "[XMLTV] - '{$params[PARAM_NAME]}'";
                    break;
                }
            } catch (Exception $ex) {
                $error_message = $ex->getMessage();
                print_backtrace_exception($ex);
                $day_items = array();
            }
        }

        self::$delayed_epg = array_unique(self::$delayed_epg);

        ksort($day_items);

        if (empty($day_items)) {
            if ($has_locks && !empty(self::$delayed_epg)) {
                hd_debug_print('Delayed epg: ' . json_format_unescaped(self::$delayed_epg), true);
                $day_items = array($day_start_ts => array(
                    PluginTvEpgProgram::end_tm_sec => $day_end_ts,
                    PluginTvEpgProgram::name => TR::load('epg_not_ready'),
                    PluginTvEpgProgram::description => TR::load('epg_not_ready_desc'))
                );
            } else if (empty($error_message)) {
                $day_epg['error'] = TR::load('epg_not_exist');
            } else {
                $day_epg['error'] = $error_message;
            }
        }

        $day_epg[PARAM_ITEMS] = $day_items;

        return $day_epg;
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// public static methods

    /**
     * @return Hashed_Array<string, array>
     */
    public static function get_sources()
    {
        return self::$xmltv_sources;
    }

    /**
     * Set active sources (Hashed_Array of url params)
     *
     * @param Hashed_Array<string, array> $sources
     * @return void
     */
    public static function set_xmltv_sources($sources)
    {
        if ($sources->is_empty()) {
            hd_debug_print('No XMLTV source selected');
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
            return false;
        }

        if (!Epg_Manager_Xmltv::is_all_indexes_valid($db_name, array(self::TABLE_CHANNELS))) {
            return false;
        }

        $ch_table_name = self::TABLE_CHANNELS;
        $picons_table_name = self::TABLE_PICONS;

        $query = sprintf('SELECT DISTINCT %s FROM %s INNER JOIN %s ON %s.%s=%s.%s WHERE %s IN (%s);', COLUMN_PICON_URL,
            $picons_table_name, $ch_table_name, $picons_table_name, COLUMN_PICON_HASH, $ch_table_name, COLUMN_PICON_HASH, COLUMN_ALIAS, $placeHolders);

        $db = self::open_sqlite_db($db_name, true);
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
                throw new Exception('Config file for indexing not exist');
            }

            $config = json_decode(file_get_contents($config_file), true);
            if (!LogSeverity::$is_debug) {
                safe_unlink($config_file);
            }
            if ($config === false) {
                Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, "Invalid config file for indexing");
                throw new Exception('Invalid config file for indexing');
            }

            if (empty($config[PARAM_XMLTV])) {
                throw new Exception('Empty XMLTV config for indexing');
            }

            $LOG_FILE = get_temp_path("{$config[PARAM_XMLTV][PARAM_HASH]}_indexing.log");
            if (!LogSeverity::$is_debug) {
                safe_unlink($LOG_FILE);
            }

            date_default_timezone_set('UTC');

            set_debug_log($config[PARAM_COOKIE_ENABLE_DEBUG]);

            self::set_cache_dir($config[PARAM_CACHE_DIR]);

            hd_print('Script config');
            hd_print('Log:         ' . $LOG_FILE);
            hd_print('Cache dir:   ' . self::$cache_dir);
            hd_print('Index flag:  ' . $config[PARAM_INDEXING_FLAG]);
            hd_print('XMLTV param: ' . json_format_unescaped($config[PARAM_XMLTV]));
            hd_print('PHP_PATH:    ' . get_include_path());

            self::reindex_xmltv($config[PARAM_XMLTV], $config[PARAM_INDEXING_FLAG]);
        } catch (Exception $ex) {
            hd_debug_print($ex);
            Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, $ex->getMessage());
        }

        if (is_limited_apk()) {
            return;
        }

        if (self::check_active_plugin_folder()) {
            hd_print('Rise finishing event: ' . DuneIrControl::$key_codes[EVENT_INDEXING_DONE]);
            shell_exec('echo ' . DuneIrControl::$key_codes[EVENT_INDEXING_DONE] . ' > /proc/ir/button');
        } else {
            hd_print('Plugin not active. Do not notify them');
        }
    }

    /**
     * check xmltv source and return required flags for indexing
     * or -1 in case of error
     *
     * @param array $params
     * @param int $index_flag index to be checked
     * @return int
     */
    public static function check_xmltv_source($params, $index_flag)
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
        if (Epg_Manager_Xmltv::is_index_locked($hash, INDEXING_ALL)) {
            hd_print("Index '$hash' is locked");
            return 0;
        }

        $cache_ttl = !isset($params[PARAM_CACHE]) ? XMLTV_CACHE_AUTO : $params[PARAM_CACHE];

        Dune_Last_Error::clear_last_error(LAST_ERROR_XMLTV);

        $cached_file = self::$cache_dir . $hash . ".xmltv";
        $cached_db = self::$cache_dir . $hash . ".db";
        hd_debug_print_separator();
        hd_debug_print("Checking '$hash' cached xmltv file: $cached_file", true);
        hd_debug_print("Index flag: $index_flag", true);

        $expired = true;
        if (!file_exists($cached_file) || !file_exists($cached_db)) {
            hd_debug_print('Cached xmltv file not exist');
        } else {
            $modify_time_file = filemtime($cached_file);
            hd_debug_print("Xmltv cache ($cache_ttl) last modified: " . date('Y-m-d H:i', $modify_time_file), true);

            if ($cache_ttl === XMLTV_CACHE_AUTO) {
                $curl_wrapper = Curl_Wrapper::getInstance();

                $etag = Curl_Wrapper::get_cached_etag($url);
                if (empty($etag)) {
                    hd_debug_print('No ETag value');
                } else {
                    $res = $curl_wrapper->download_file($url, false, Curl_Wrapper::USE_ETAG);
                    if ($res === false) {
                        return -1;
                    }
                    $code = Curl_Wrapper::get_http_code();
                    hd_debug_print("http code: $code", true);
                    $expired = !($code === 304 || ($code === 200 && Curl_Wrapper::get_response_header('etag') === $etag));
                }

                if ($expired) {
                    Curl_Wrapper::clear_cached_etag($url);
                }
            } else if (filesize($cached_file) !== 0) {
                $max_cache_time = 3600 * 24 * $cache_ttl;
                $expired_time = $modify_time_file + $max_cache_time;
                hd_debug_print('Xmltv cache expired at: ' . date('Y-m-d H:i', $expired_time), true);
                if ($modify_time_file && $expired_time > time()) {
                    $expired = false;
                }
            }
        }

        $new_index_flag = 0;
        if ($expired) {
            self::clear_epg_files($hash);
            $new_index_flag |= INDEXING_DOWNLOAD;
            hd_debug_print("Xmltv cache '$hash' expired. Indexing flags: " . $index_flag, true);
            $channels_valid = false;
            $entries_valid = false;
        } else {
            hd_debug_print("Cached file '$hash': $cached_file is not expired");
            $indexed = self::get_indexes_info($params);
            // index for picons has not verified because it always exist if channels index is present
            $channels_valid = ($indexed[self::TABLE_CHANNELS] !== -1);
            $entries_valid = ($indexed[self::TABLE_ENTRIES] !== -1);
        }

        if (!$entries_valid && ($index_flag & INDEXING_ENTRIES) !== 0) {
            hd_debug_print("Xmltv entries '$hash' not valid");
            $new_index_flag |= INDEXING_ENTRIES;
        }

        if (!$channels_valid) {
            hd_debug_print("Xmltv channels '$hash' not valid");
            $new_index_flag |= INDEXING_CHANNELS;
        }

        if ($new_index_flag === 0) {
            hd_debug_print("Xmltv channels and entries index '$hash' are valid");
            self::clear_log($hash);
            return 0;
        }

        // downloaded xmltv file exists, not expired but indexes for channels, picons and positions not exists
        hd_debug_print("Result index flag: $index_flag for '$hash'");
        hd_debug_print_separator();
        return $new_index_flag;
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

        $db = self::open_sqlite_db($params[PARAM_HASH], true);
        if (empty($db)) {
            return $result;
        }

        foreach ($result as $key => $name) {
            if ($key === 'epg_ids' || !$db->is_table_exists($key)) continue;

            if ($key === self::TABLE_CHANNELS) {
                $result[$key] = (int)$db->query_value(sprintf('SELECT COUNT(DISTINCT %s) FROM %s;', COLUMN_CHANNEL_ID, $key));
            } else if ($key === self::TABLE_PICONS) {
                $result[$key] = (int)$db->query_value(sprintf('SELECT COUNT(*) FROM %s;', $key));
            } else if ($key === self::TABLE_ENTRIES) {
                $result[$key] = (int)$db->query_value(sprintf("SELECT COUNT(*) FROM %s;", $key));
                $result['epg_ids'] = (int)$db->query_value(sprintf("SELECT COUNT(DISTINCT %s) FROM $key;", COLUMN_CHANNEL_ID));
            }
        }

        hd_debug_print('Indexes info: ' . json_format_unescaped($result));
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
        hd_debug_print('Indexing xmltv');

        $url = $params[PARAM_URI];
        $url_hash = $params[PARAM_HASH];

        if (empty($url) || empty($url_hash)) {
            hd_debug_print('Url not set, skipped');
            return;
        }

        $new_flag = Epg_Manager_Xmltv::check_xmltv_source($params, $indexing_flag);
        if ($new_flag === 0) {
            return;
        }

        $perf = new Perf_Collector();
        $perf->reset('start');

        $cached_file = self::$cache_dir . $url_hash . ".xmltv";
        $params[PARAM_EPG_CACHE_PATH] = $cached_file;

        /// download source
        if ($indexing_flag & INDEXING_DOWNLOAD) {
            hd_debug_print('Download xmltv');
            // download xmtv is denied if download or any indexing in process
            if (self::is_index_locked($url_hash, INDEXING_ALL)) {
                hd_debug_print('File is indexing or downloading, skipped');
                return;
            }

            Dune_Last_Error::clear_last_error(LAST_ERROR_XMLTV);

            if (preg_match("/jtv.?\.zip$/", basename(urldecode($url)))) {
                $msg = 'Unsupported EPG format (JTV)';
                hd_debug_print($msg);
                Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, $msg);
                return;
            }

            self::lock_index($url_hash, INDEXING_DOWNLOAD);
            $success = false;

            try {
                $perf->setLabel('start_download');
                self::download_xmltv($params);
                $perf->setLabel('start_unpack');
                self::unpack_xmltv($params);
                $success = true;
            } catch (Exception $ex) {
                hd_debug_print($ex->getMessage());
                Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, $ex->getMessage());
                safe_unlink($cached_file);
            }

            self::unlock_index($url_hash, INDEXING_DOWNLOAD);

            if (!$success) {
                return;
            }
        }

        if (!file_exists($cached_file)) {
            hd_debug_print("reindex_xmltv_channels: Cache file $cached_file not exist");
            return;
        }

        $file = fopen($cached_file, 'rb');
        if (!$file) {
            hd_debug_print("reindex_xmltv_channels: Can't open file: $cached_file");
            return;
        }

        $db = self::open_sqlite_db($url_hash, false);
        if ($db === false) {
            fclose($file);
            hd_debug_print("reindex_xmltv_channels: Can't open db: $url_hash");
            return;
        }

        /// Reindex channels and picons
        if ($indexing_flag & INDEXING_CHANNELS) {
            hd_debug_print('Start index channels and picons...');
            self::lock_index($url_hash, INDEXING_CHANNELS);

            libxml_use_internal_errors(true);
            $perf->setLabel('start_channels');

            $ch_table_name = self::TABLE_CHANNELS;
            $picons_table_name = self::TABLE_PICONS;

            $query = sprintf('DROP TABLE IF EXISTS %s;', $ch_table_name);
            $query .= sprintf('DROP TABLE IF EXISTS %s;', $picons_table_name);
            $query .= self::CREATE_CHANNELS_TABLE;
            $query .= self::CREATE_PICONS_TABLE;
            $res = $db->exec_transaction($query);
            if (!$res) {
                fclose($file);
                $msg = "Error transaction: $query";
                hd_debug_print($msg);
                Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, $msg);
                self::unlock_index($url_hash, INDEXING_CHANNELS);
                return;
            }

            $query = '';
            $last_buffer = '';
            while (!feof($file)) {
                // search for open tag <channel>
                $chunk = fread($file, 8192);
                $buffer = $last_buffer . $chunk;
                $pos = strpos($buffer, '<channel id');
                if ($pos === false) {
                    $last_buffer = $chunk;
                    continue;
                }

                // calculate start position in file and seek to + length of searched tag
                $last_buffer = '';
                $start_pos = ftell($file) - strlen($buffer) + $pos;
                fseek($file, $start_pos + 11);

                // read content until closed tag found
                $line = '';
                while (!feof($file)) {
                    // search for closing tag </channel>
                    $chunk = fread($file, 8192);
                    $buffer = $last_buffer . $chunk;
                    $pos = strpos($buffer, '</channel>');
                    if ($pos === false) {
                        $last_buffer = $chunk;
                        continue;
                    }

                    $last_buffer = '';
                    // calculate end position in file
                    $end_pos = ftell($file) - strlen($buffer) + $pos + 10;
                    // seek to start position and read found text
                    fseek($file, $start_pos);
                    $line = fread($file, $end_pos - $start_pos);
                    break;
                }
                if (feof($file) || empty($line)) continue;

                $xml_node = new DOMDocument();
                if ($xml_node->loadXML($line, LIBXML_NOWARNING | LIBXML_NOERROR) === false) {
                    foreach (libxml_get_errors() as $error) {
                        display_xml_error($error, $line);
                    }
                    libxml_clear_errors();
                    continue;
                }
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
                            $query .= sprintf('INSERT OR REPLACE INTO %s (%s,%s) VALUES(%s, %s);', $picons_table_name,
                                COLUMN_PICON_HASH, COLUMN_PICON_URL, Sql_Wrapper::sql_quote($picon_hash), Sql_Wrapper::sql_quote($picon_url));
                            break;
                        }
                    }
                }

                $q_picon_hash = Sql_Wrapper::sql_quote($picon_hash);
                $q_alias = Sql_Wrapper::sql_quote(to_lower($channel_id));
                $query .= sprintf('INSERT OR IGNORE INTO %s (%s,%s,%s) VALUES(%s,%s,%s);',
                    $ch_table_name, COLUMN_ALIAS, COLUMN_CHANNEL_ID, COLUMN_PICON_HASH, $q_alias, $q_channel_id, $q_picon_hash);

                foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                    $q_alias = Sql_Wrapper::sql_quote(to_lower($tag->nodeValue));
                    $query .= sprintf('INSERT OR IGNORE INTO %s (%s,%s,%s) VALUES(%s,%s,%s);',
                        $ch_table_name, COLUMN_ALIAS, COLUMN_CHANNEL_ID, COLUMN_PICON_HASH, $q_alias, $q_channel_id, $q_picon_hash);
                }
            }
            $db->exec_transaction($query);

            $channels = (int)$db->query_value(sprintf('SELECT count(DISTINCT %s) FROM %s;', COLUMN_CHANNEL_ID, $ch_table_name));
            $picons = (int)$db->query_value(sprintf("SELECT COUNT(*) FROM %s;", $picons_table_name));

            $perf->setLabel('end_channels');
            $report = $perf->getFullReport('start_channels', 'end_channels');

            hd_debug_print("Total channels id's: $channels");
            hd_debug_print("Total known picons:  $picons");
            hd_debug_print("Reindexing channels: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print("Memory usage:        {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
            hd_debug_print('Storage space:       ' . HD::get_storage_size(self::$cache_dir));
            hd_print_separator();

            self::update_stat($params, 'channels', $report[Perf_Collector::TIME]);
            self::unlock_index($url_hash, INDEXING_CHANNELS);
            libxml_use_internal_errors(false);
        }

        /// Reindex positions
        if ($indexing_flag & INDEXING_ENTRIES) {
            hd_debug_print('Start indexing entries...');
            self::lock_index($url_hash, INDEXING_ENTRIES);

            hd_debug_print("Indexing positions for: '$cached_file' by '$url'", true);
            $perf->setLabel('start_reindex_entries');

            $query = sprintf("DROP TABLE IF EXISTS %s;", self::TABLE_ENTRIES);
            $query .= self::CREATE_ENTRIES_TABLE;
            $res = $db->exec_transaction($query);
            if (!$res) {
                fclose($file);
                $msg = "Error transaction: $query";
                hd_debug_print($msg);
                Dune_Last_Error::set_last_error(LAST_ERROR_XMLTV, $msg);
                self::unlock_index($url_hash, INDEXING_ENTRIES);
                return;
            }

            hd_debug_print('Begin transactions...', true);
            $db->exec('BEGIN;');

            $query = sprintf('INSERT INTO %s (%s, %s, %s) VALUES(:%s, :%s, :%s);',
                self::TABLE_ENTRIES, COLUMN_CHANNEL_ID, COLUMN_START, COLUMN_END, COLUMN_CHANNEL_ID, COLUMN_START, COLUMN_END);
            $stm = $db->prepare($query);
            /** @var string $prev_channel */
            /** @var int $start_program_block */
            /** @var int $tag_end_pos */
            $stm->bindParam(':channel_id', $prev_channel);
            $stm->bindParam(':start', $start_program_block);
            $stm->bindParam(':end', $tag_end_pos);

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

            hd_debug_print('End transactions...', true);
            $db->exec('COMMIT;');

            fclose($file);
            self::unlock_index($url_hash, INDEXING_ENTRIES);

            $total_epg = (int)$db->query_value(sprintf('SELECT count(DISTINCT %s) FROM %s;', COLUMN_CHANNEL_ID, self::TABLE_ENTRIES));
            $total_blocks = (int)$db->query_value(sprintf('SELECT COUNT(*) FROM %s;', self::TABLE_ENTRIES));

            $perf->setLabel('end_reindex_entries');
            $report = $perf->getFullReport('start_reindex_entries', 'end_reindex_entries');

            hd_debug_print("Total unique epg id's indexed: $total_epg, total blocks: $total_blocks");
            hd_debug_print("Reindexing entries: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print("Memory usage:       {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
            hd_debug_print('Storage space:      ' . HD::get_storage_size(self::$cache_dir));
            hd_print_separator();

            self::update_stat($params, 'entries', $report[Perf_Collector::TIME]);
        }

        if ($perf->getLabelsCount() > 1) {
            $report = $perf->getFullReport();
            hd_debug_print("Reindexing XMLTV source done: {$report[Perf_Collector::TIME]} secs");
            hd_print_separator();
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
                hd_print_separator();
                $logfile = file_get_contents($index_log);
                foreach (explode(PHP_EOL, $logfile) as $l) {
                    hd_print(preg_replace("|^\[[\d:-]+\s(.*)$|", "[$1", rtrim($l)));
                }
                hd_print_separator();
                hd_debug_print('Read finished');
                safe_unlink($index_log);
                $has_imports = true;
            }

            $error_log = get_temp_path("{$hash}_bg_error.log");
            if (file_exists($error_log)) {
                $error_file = file_get_contents($error_log);
                if (!empty($error_file)) {
                    hd_debug_print("Read indexing error log $error_log...");
                    hd_print_separator();
                    foreach (explode(PHP_EOL, $error_file) as $l) {
                        if (!empty($l)) {
                            hd_print($l);
                        }
                    }
                    hd_print_separator();
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

    public static function update_stat($params, $tag, $time)
    {
        $db = self::open_sqlite_db($params[PARAM_HASH], false);
        if (empty($db)) {
            return;
        }

        $query = self::CREATE_STAT_TABLE;
        $db->exec($query);

        $query = sprintf('INSERT OR REPLACE INTO %s (%s,%s) VALUES (%s, %f);',
            self::TABLE_STAT, COLUMN_NAME, COLUMN_VALUE, Sql_Wrapper::sql_quote($tag), $time);
        $db->exec($query);
    }

    public static function get_stat($params)
    {
        $db = self::open_sqlite_db($params[PARAM_HASH], true);
        if (empty($db)) {
            return array();
        }

        $query = sprintf('SELECT %s, %s FROM %s;', COLUMN_NAME, COLUMN_VALUE, self::TABLE_STAT);
        $values = $db->fetch_array($query);

        $stat = array();
        foreach ($values as $v) {
            $stat[$v[COLUMN_NAME]] = floatval($v[COLUMN_VALUE]);
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

        if (empty(self::$cache_dir)) {
            hd_debug_print('Cache directory not set');
            return;
        }

        $dirs = glob(self::get_lock_name($hash, 0, '*'), GLOB_ONLYDIR);
        $locks = array();
        foreach ($dirs as $dir) {
            hd_debug_print("Found locks: $dir");
            $locks[] = $dir;
        }

        foreach ($locks as $lock) {
            $ar = explode('_', basename($lock));
            $pid = (int)end($ar);

            if ($pid !== 0 && send_process_signal($pid, 0)) {
                hd_debug_print("Kill process $pid");
                shell_exec("kill $pid");
            }
            hd_debug_print("Remove lock: $lock");
            delete_directory($lock);
        }

        self::clear_log($hash);

        if (empty($hash)) {
            self::$epg_db = array();
        } else if (isset(self::$epg_db[$hash])) {
            unset(self::$epg_db[$hash]);
        }

        Curl_Wrapper::clear_cached_etag($hash, true);

        $files = self::$cache_dir . $hash . "*";
        hd_debug_print("clear epg files: $files");
        array_map('unlink', glob($files));

        clearstatcache();
        hd_debug_print('Storage space:  ' . HD::get_storage_size(self::$cache_dir));
    }

    public static function get_all_xmltv_channels($params)
    {
        $db = self::open_sqlite_db($params[PARAM_HASH], true);
        if ($db === false) {
            hd_debug_print("Problem with open SQLite db: '{$params[PARAM_HASH]}.db'! Possible database not exist");
            return array();
        }

        if (!self::is_all_indexes_valid($params[PARAM_HASH], array(self::TABLE_CHANNELS, self::TABLE_ENTRIES))) {
            hd_debug_print("EPG for {$params[PARAM_URI]} not indexed!");
            return array();
        }

        $query = sprintf('SELECT DISTINCT ch.%s FROM %s AS ch JOIN %s AS ent WHERE ch.%s=ent.%s ORDER BY ch.%s;',
            COLUMN_CHANNEL_ID, self::TABLE_CHANNELS, self::TABLE_ENTRIES, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID);

        return $db->fetch_array($query, COLUMN_CHANNEL_ID);
    }

    public static function get_all_xmltv_aliases($params)
    {
        $db = self::open_sqlite_db($params[PARAM_HASH], true);
        if ($db === false) {
            hd_debug_print("Problem with open SQLite db: '{$params[PARAM_HASH]}.db'! Possible database not exist");
            return array();
        }

        if (!self::is_all_indexes_valid($params[PARAM_HASH], array(self::TABLE_CHANNELS, self::TABLE_ENTRIES))) {
            hd_debug_print("EPG for {$params[PARAM_URI]} not indexed!");
            return array();
        }

        $query = sprintf('SELECT DISTINCT ch.%s, ch.%s FROM %s AS ch JOIN %s AS ent WHERE ch.%s != ch.%s AND ch.%s=ent.%s ORDER BY ch.%s;',
            COLUMN_ALIAS, COLUMN_CHANNEL_ID, self::TABLE_CHANNELS, self::TABLE_ENTRIES,
            COLUMN_ALIAS, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, COLUMN_ALIAS);

        return $db->fetch_array($query);
    }

    public static function get_all_xmltv_ids($params)
    {
        $db = self::open_sqlite_db($params[PARAM_HASH], true);
        if ($db === false) {
            hd_debug_print("Problem with open SQLite db: '{$params[PARAM_HASH]}.db'! Possible database not exist");
            return array();
        }

        if (!self::is_all_indexes_valid($params[PARAM_HASH], array(self::TABLE_CHANNELS, self::TABLE_ENTRIES))) {
            hd_debug_print("EPG for {$params[PARAM_URI]} not indexed!");
            return array();
        }

        $query = sprintf('SELECT DISTINCT ch.%s, ch.%s FROM %s AS ch JOIN %s AS ent WHERE ch.%s != ch.%s AND ch.%s=ent.%s ORDER BY ch.%s;',
            COLUMN_CHANNEL_ID, COLUMN_ALIAS, self::TABLE_CHANNELS, self::TABLE_ENTRIES,
            COLUMN_ALIAS, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, COLUMN_ALIAS);

        $rows = $db->fetch_array($query);
        $channels[COLUMN_EPG_ID] = array_unique(extract_column($rows, COLUMN_CHANNEL_ID));
        $channels[COLUMN_EPG_ALIASES] = extract_column($rows, COLUMN_ALIAS);

        return $channels;
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected static methods

    /**
     * Set and create cache dir
     *
     * @param string $cache_dir
     */
    public static function set_cache_dir($cache_dir)
    {
        self::$cache_dir = get_slash_trailed_path($cache_dir);
        create_path(self::$cache_dir);

        hd_print_separator();
        hd_print('Cache folder:            ' . self::$cache_dir);
        hd_print('Storage space:           ' . HD::get_storage_size(self::$cache_dir));
    }

    /**
     * @return string
     */
    public static function get_cache_dir()
    {
        return self::$cache_dir;
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
            delete_directory($name);
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
    protected static function load_program_index($params, $channel_row)
    {
        $channel_positions = array();

        if (!self::is_all_indexes_valid($params[PARAM_HASH], array(self::TABLE_CHANNELS, self::TABLE_ENTRIES))) {
            hd_debug_print("EPG for {$params[PARAM_URI]} not indexed!");
            return $channel_positions;
        }

        $aliases = Sql_Wrapper::sql_make_list_from_values(
            array_unique(Default_Dune_Plugin::make_epg_ids($channel_row))
        );

        hd_debug_print("Search for aliases: $aliases", true);

        $db = self::open_sqlite_db($params[PARAM_HASH], true);
        if ($db === false) {
            hd_debug_print("Problem with open SQLite db: '{$params[PARAM_HASH]}.db'! Possible database not exist");
            return $channel_positions;
        }

        if ($db->is_table_exists(self::TABLE_CHANNELS)) {
            $query = sprintf('SELECT DISTINCT %s FROM %s WHERE %s IN (%s);',
                COLUMN_CHANNEL_ID, self::TABLE_CHANNELS, COLUMN_ALIAS, $aliases);
            $channel_ids = $db->fetch_array($query, COLUMN_CHANNEL_ID);
        }

        if (empty($channel_ids)) {
            hd_debug_print("No channel_id found for aliases: $aliases");
            return $channel_positions;
        }

        $channel_id = $channel_row[COLUMN_CHANNEL_ID];
        $channel_title = $channel_row[COLUMN_TITLE];
        hd_debug_print("Found EPG id's: " . json_format_unescaped($channel_ids), true);
        hd_debug_print("Load position indexes for: $channel_id ($channel_title)", true);

        if ($db->is_table_exists(self::TABLE_ENTRIES)) {
            $query = sprintf('SELECT %s, %s FROM %s WHERE %s;',
                COLUMN_START, COLUMN_END, self::TABLE_ENTRIES, Sql_Wrapper::sql_make_where_clause($channel_ids, COLUMN_CHANNEL_ID));
            $channel_positions = $db->fetch_array($query);
        }

        if (empty($channel_positions)) {
            $ids = Sql_Wrapper::sql_make_list_from_values($channel_ids);
            hd_debug_print("No positions found for channel $channel_id ($channel_title) and channel id's: $ids");
        }

        return $channel_positions;
    }

    /**
     * Check is all indexes is valid
     *
     * @param string $hash
     * @param array $names
     * @return bool
     */
    protected static function is_all_indexes_valid($hash, $names)
    {
        $db = self::open_sqlite_db($hash, true);
        if ($db === false) {
            return false;
        }

        foreach ($names as $name) {
            if (!$db->is_table_exists($name)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param DOMElement $node
     * @param string $name
     * @return string
     */
    public static function get_node_value($node, $name)
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

    /**
     * @param DOMElement $node
     * @param string $name
     * @return array
     */
    public static function get_node_values($node, $name)
    {
        $values = array();
        foreach ($node->getElementsByTagName($name) as $element) {
            if (!empty($element->nodeValue)) {
                $values[] = $element->nodeValue;
            }
        }

        return $values;
    }

    /**
     * @param DOMElement $node
     * @param string $name
     * @param string $attribute
     * @return string
     */
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
        array_map('unlink', glob(get_temp_path("{$hash}_*.log")));
    }

    /**
     * open sqlite database
     * @param string $db_name
     * @param bool $readonly
     * @return Sql_Wrapper|bool
     */
    protected static function open_sqlite_db($db_name, $readonly)
    {
        $db_file = self::$cache_dir . $db_name . ".db";
        // in read-only database can't be created
        if ($readonly && !file_exists($db_file)) {
            return false;
        }

        // if database not exist or requested mode is read-write create new database
        if (!isset(self::$epg_db[$db_name]) || (!$readonly && self::$epg_db[$db_name]->is_readonly())) {
            hd_debug_print("Open new wrapper for: '$db_file'", true);
            if (isset(self::$epg_db[$db_name])) {
                self::$epg_db[$db_name]->get_db()->close();
                unset(self::$epg_db[$db_name]);
            }
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
        $cached_file = $params[PARAM_EPG_CACHE_PATH];

        hd_debug_print("Download xmltv source: $url");
        hd_debug_print('Storage space:  ' . HD::get_storage_size(self::$cache_dir));

        $perf = new Perf_Collector();

        $perf->reset('start_download');

        $tmp_filename = $cached_file . ".tmp";
        safe_unlink($tmp_filename);
        safe_unlink("$cached_file.stat");

        hd_debug_print("Download: $url");
        Curl_Wrapper::clear_cached_etag($url);

        $curl_wrapper = Curl_Wrapper::getInstance();
        $curl_wrapper->set_connect_timeout($params[PARAM_CURL_CONNECT_TIMEOUT]);
        $curl_wrapper->set_download_timeout($params[PARAM_CURL_DOWNLOAD_TIMEOUT]);
        if (!$curl_wrapper->download_file($url, $tmp_filename, Curl_Wrapper::USE_ETAG)) {
            $http_code = Curl_Wrapper::get_http_code();
            if (Curl_Wrapper::get_error_no() !== 0) {
                $msg = "CURL errno: " . Curl_Wrapper::get_error_no() . "\n" . Curl_Wrapper::get_error_desc() . "\nHTTP code: $http_code";
            } else {
                $msg = "HTTP request failed ($http_code)";
            }

            safe_unlink($tmp_filename);
            throw new Exception("Can't download file\n$msg");
        }

        $http_code = Curl_Wrapper::get_http_code();
        if ($http_code !== 200) {
            safe_unlink($tmp_filename);
            throw new Exception("Download error ($http_code) $url\n\n" . Curl_Wrapper::get_raw_response_headers());
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

        hd_debug_print('ETag value:     ' . trim(Curl_Wrapper::get_cached_etag($url), '"'));
        hd_debug_print('Modify time:    ' . date('Y-m-d H:i', $file_time));
        hd_debug_print("Download size:  $file_size bytes");
        hd_debug_print("Download time:  $dl_time secs");
        hd_debug_print("Download speed: $speed");
        hd_debug_print('Storage space:  ' . HD::get_storage_size(self::$cache_dir));
        hd_print_separator();
        self::update_stat($params, 'download', $dl_time);
        self::update_stat($params, 'download_size', $file_size);
    }

    /**
     * @param array $params
     * @throws Exception
     */
    protected static function unpack_xmltv($params)
    {
        $cached_file = $params[PARAM_EPG_CACHE_PATH];
        hd_debug_print("Remove cached file: $cached_file");
        safe_unlink($cached_file);

        $perf = new Perf_Collector();
        $perf->reset('start_unpack');

        $tmp_filename = $cached_file . ".tmp";
        if (!file_exists($tmp_filename)) {
            throw new Exception("$tmp_filename is not exists");
        }

        $file_time = filemtime($tmp_filename);
        $handle = fopen($tmp_filename, "rb");
        $hdr = fread($handle, 8);
        fclose($handle);

        if (0 === mb_strpos($hdr, "\x1f\x8b\x08")) {
            hd_debug_print('GZ signature:  ' . bin2hex(substr($hdr, 0, 3)), true);
            $gz_filename = $cached_file . '.gz';
            if (!rename($tmp_filename, $gz_filename)) {
                throw new Exception("Failed to rename $tmp_filename to $gz_filename");
            }
            $tmp_filename = $gz_filename;
            hd_debug_print("ungzip $tmp_filename to $cached_file");
            $cmd = "gzip -d $tmp_filename 2>&1";
            /** @var int $ret */
            $out = system($cmd, $ret);
            if ($ret > 1) {
                throw new Exception("Failed to unpack $tmp_filename (error code: $ret)\n$out");
            }
            if ($ret === 1 && file_exists($cached_file)) {
                hd_debug_print("Unpack $tmp_filename with error code: $ret\n$out");
            }
            clearstatcache();
            $size = filesize($cached_file);
            if ($size === 0) {
                safe_unlink($cached_file);
                throw new Exception('Unpacked file empty!');
            }
            touch($cached_file, $file_time);
            $action = 'UnGZip:';
        } else if (0 === mb_strpos($hdr, "\x50\x4b\x03\x04")) {
            hd_debug_print('ZIP signature: ' . bin2hex(substr($hdr, 0, 4)), true);
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
            hd_debug_print('XML signature: ' . substr($hdr, 0, 5), true);
            rename($tmp_filename, $cached_file);
            $size = filesize($cached_file);
            touch($cached_file, $file_time);
            $action = 'Copy:  ';
        } else {
            hd_debug_print('Unknown signature: ' . bin2hex($hdr), true);
            throw new Exception(TR::load('err_unknown_file_type'));
        }

        $unpack_time = $perf->getReportItemCurrent(Perf_Collector::TIME);
        hd_debug_print("Cached file:   $cached_file");
        hd_debug_print("$action        $size bytes");
        hd_debug_print("Time:          $unpack_time secs");
        hd_debug_print('Storage space: ' . HD::get_storage_size(self::$cache_dir));
        hd_print_separator();

        self::update_stat($params, 'unpack', $unpack_time);
        self::update_stat($params, 'unpack_size', $size);
    }

    protected static function check_active_plugin_folder()
    {
        $port = getenv('HD_HTTP_LOCAL_PORT');
        if (empty($port)) {
            $port = 80;
        }

        $status = json_decode(shell_exec('wget -q -O - "http://127.0.0.1:' . $port . '/cgi-bin/do?cmd=ui_state&result_syntax=json"'));

        $navigator_newui_top = safe_get_value($status->ui_state->screen, 'navigator_top_item_id');
        $folder_type = safe_get_value($status->ui_state->screen, 'folder_type');
        $folder_id = safe_get_value($status->ui_state->screen, 'folder_id');

        // "folder_type": "PluginRows.proiptv",
        // "folder_type": "PluginRegular.proiptv",
        $is_our_screen = strpos($folder_type, '.proiptv') !== false;

        // Special case if user select only TV top menu without dive down to plugin folders and only my plugin is set as main TV
        // "navigator_top_item_id": "plugin:shell_ext:tv",
        // "folder_type": "RowsMenu",
        // "folder_id": "rows_menu://home",
        $is_newui_top = Starnet_Epfs_Handler::get_current_epfs_plugin() === get_plugin_name()
            && $navigator_newui_top == 'plugin:shell_ext:tv'
            && $folder_type == 'RowsMenu'
            && $folder_id == 'rows_menu://home';

        // Check if some channel is playing now. But need to be sure that is our channels
        $is_play = (isset($status->playback_state) && $status->playback_state === "playing");

        return $is_play || $is_our_screen || $is_newui_top;
    }
}
