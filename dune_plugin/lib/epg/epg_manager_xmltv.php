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

class Epg_Manager_Xmltv
{
    const TABLE_PICONS = 'epg_picons';
    const TABLE_CHANNELS = 'epg_channels';
    const TABLE_ENTRIES = 'epg_entries';

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    /**
     * @var array
     */
    protected $delayed_epg = array();

    /**
     * @var int
     */
    protected $flags = 0;

    /**
     * @var Hashed_Array
     */
    protected $xmltv_sources;

    /**
     * path where cache is stored
     * @var string
     */
    protected $cache_dir;

    /**
     * url params to download XMLTV EPG
     * @var array
     */
    protected $xmltv_url_params;

    /**
     * @var Curl_Wrapper
     */
    protected $curl_wrapper;

    /**
     * @var int
     */
    protected $pid = 0;

    /**
     * @var Perf_Collector
     */
    protected $perf;

    /**
     * @var Sql_Wrapper[]
     */
    protected $epg_db = array();

    /**
     * @var Sql_Wrapper[]
     */
    protected $picons_db = array();

    public function __construct()
    {
        $this->curl_wrapper = new Curl_Wrapper();
        $this->perf = new Perf_Collector();
    }

    /**
     * Set and create cache dir
     *
     * @param string $cache_dir
     */
    public function set_cache_dir($cache_dir)
    {
        $this->cache_dir = get_slash_trailed_path($cache_dir);
        create_path($this->cache_dir);

        hd_debug_print("Indexer engine: " . get_class($this));
        hd_debug_print("Cache dir: $this->cache_dir");
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * @return Hashed_Array
     */
    public function get_sources()
    {
        return $this->xmltv_sources;
    }
    /**
     * Set url parameters: url, cache, hash
     *
     * @param array $url_param
     * @return void
     */
    public function set_url_params($url_param)
    {
        hd_debug_print(null, true);
        $this->xmltv_url_params = $url_param;
        if (preg_match("/jtv.?\.zip$/", basename(urldecode($this->xmltv_url_params[PARAM_URI])))) {
            hd_debug_print("Unsupported EPG format (JTV)");
            $this->xmltv_url_params[PARAM_URI] = '';
            $this->xmltv_url_params[PARAM_HASH] = '';
        }
    }

    /**
     * get curl wrapper
     *
     * @return Curl_Wrapper
     */
    public function get_curl_wrapper()
    {
        return $this->curl_wrapper;
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
     * @param Default_Dune_Plugin $plugin
     */
    public function init_by_plugin($plugin)
    {
        $this->plugin = $plugin;
        $this->xmltv_sources = $this->plugin->get_active_sources();
        $this->flags = $this->plugin->get_bool_setting(PARAM_FAKE_EPG, false) ? EPG_FAKE_EPG : 0;
        $this->set_cache_dir($this->plugin->get_cache_dir());
    }

    /**
     * Function to parse xmltv source in separate process
     * Only one XMLTV source must be sent via config
     *
     * @param $config_file
     * @return bool
     */
    public function index_by_config($config_file)
    {
        global $LOG_FILE;

        if (!file_exists($config_file)) {
            HD::set_last_error("xmltv_last_error", "Config file for indexing not exist");
            return false;
        }

        $config = json_decode(file_get_contents($config_file), true);
        if (!LogSeverity::$is_debug) {
            unlink($config_file);
        }
        if ($config === false) {
            HD::set_last_error("xmltv_last_error", "Invalid config file for indexing");
            return false;
        }

        if (empty($config[PARAMS_XMLTV])) {
            return false;
        }

        $LOG_FILE = get_temp_path("{$config[PARAMS_XMLTV][PARAM_HASH]}_indexing.log");
        if (file_exists($LOG_FILE) && !LogSeverity::$is_debug) {
            unlink($LOG_FILE);
        }

        date_default_timezone_set('UTC');

        set_debug_log($config[PARAM_ENABLE_DEBUG]);

        $this->pid = getmypid();
        $this->xmltv_sources = new Hashed_Array();
        $this->xmltv_url_params = $config[PARAMS_XMLTV];
        $this->set_cache_dir($config[PARAM_CACHE_DIR]);

        hd_print("Script config");
        hd_print("Log:         " . $LOG_FILE);
        hd_print("Cache dir:   " . $this->cache_dir);
        hd_print("XMLTV param: " . json_encode($this->xmltv_url_params));
        hd_print("Process ID:  " . $this->pid);
        hd_print("Index flag:  " . $config[PARAM_INDEXING_FLAG]);

        $this->check_and_index_xmltv_source($config[PARAM_INDEXING_FLAG]);

        return true;
    }

    /**
     * Set active sources (Hashed_Array of url params)
     *
     * @param Hashed_Array<array> $sources
     * @return void
     */
    public function set_xmltv_sources($sources)
    {
        if ($sources->size() === 0) {
            hd_debug_print("No XMLTV source selected");
        } else {
            hd_debug_print("XMLTV sources selected: $sources");
        }

        $this->xmltv_sources = $sources;
    }

    /**
     * Try to load epg from cached file
     *
     * @param array $channel_row
     * @param int $day_start_ts
     * @return array
     */
    public function get_day_epg_items($channel_row, $day_start_ts)
    {
        $any_lock = false;
        foreach ($this->xmltv_sources->get_keys() as $key) {
            $any_lock |= $this->is_index_locked($key, INDEXING_DOWNLOAD | INDEXING_ENTRIES);
        }

        $day_epg = array();
        $ext_epg = $this->plugin->get_bool_setting(PARAM_SHOW_EXT_EPG) && $this->plugin->is_ext_epg_exist();

        foreach ($this->xmltv_sources as $key => $params) {
            $this->xmltv_url_params = $params;
            if ($this->is_index_locked($key, INDEXING_DOWNLOAD | INDEXING_ENTRIES)) {
                hd_debug_print("EPG {$params[PARAM_URI]} still indexing, append to delayed queue channel id: {$channel_row[COLUMN_CHANNEL_ID]}");
                $this->delayed_epg[] = $channel_row[COLUMN_CHANNEL_ID];
                continue;
            }

            // filter out epg only for selected day
            $day_end_ts = $day_start_ts + 86400;
            if (LogSeverity::$is_debug) {
                $date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
                $date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
                hd_debug_print("Fetch entries for from: $date_start_l ($day_start_ts) to: $date_end_l ($day_end_ts)");
            }

            try {
                $positions = $this->load_program_index($channel_row);
                if (!empty($positions)) {
                    $cached_file = $this->cache_dir . $params[PARAM_HASH] . ".xmltv";
                    if (!file_exists($cached_file)) {
                        throw new Exception("cache file $cached_file not exist");
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

                                $day_epg[$program_start][PluginTvEpgProgram::end_tm_sec] = $program_end;
                                $day_epg[$program_start][PluginTvEpgProgram::name] = self::get_node_value($tag, 'title');
                                $day_epg[$program_start][PluginTvEpgProgram::description] = self::get_node_value($tag, 'desc');
                                $day_epg[$program_start][PluginTvEpgProgram::icon_url] = self::get_node_attribute($tag, 'icon', 'src');

                                if (!$ext_epg) continue;

                                $day_epg[$program_start][PluginTvExtEpgProgram::sub_title] = self::get_node_value($tag, 'sub-title');
                                $day_epg[$program_start][PluginTvExtEpgProgram::main_category] = self::get_node_value($tag, 'category');
                                $day_epg[$program_start][PluginTvExtEpgProgram::year] = self::get_node_value($tag, 'date');
                                $day_epg[$program_start][PluginTvExtEpgProgram::country] = self::get_node_value($tag, 'country');
                                foreach ($tag->getElementsByTagName('credits') as $sub_tag) {
                                    $day_epg[$program_start][PluginTvExtEpgProgram::director] = self::get_node_value($sub_tag, 'director');
                                    $day_epg[$program_start][PluginTvExtEpgProgram::producer] = self::get_node_value($sub_tag, 'producer');
                                    $day_epg[$program_start][PluginTvExtEpgProgram::actor] = self::get_node_value($sub_tag, 'actor');
                                    $day_epg[$program_start][PluginTvExtEpgProgram::presenter] = self::get_node_value($sub_tag, 'presenter'); //Ведущий
                                    $day_epg[$program_start][PluginTvExtEpgProgram::writer] = self::get_node_value($sub_tag, 'writer');
                                    $day_epg[$program_start][PluginTvExtEpgProgram::editor] = self::get_node_value($sub_tag, 'editor');
                                    $day_epg[$program_start][PluginTvExtEpgProgram::composer] = self::get_node_value($sub_tag, 'composer');
                                }
                            }
                        }

                        fclose($handle);

                        if (!empty($day_epg)) break;
                    }
                }
            } catch (Exception $ex) {
                print_backtrace_exception($ex);
            }
        }

        if (empty($day_epg)) {
            if ($this->xmltv_sources->size() === 0) {
                return array($day_start_ts => array(
                    PluginTvEpgProgram::end_tm_sec => $day_start_ts + 86400,
                    PluginTvEpgProgram::name => TR::load('epg_no_sources'),
                    PluginTvEpgProgram::description => TR::load('epg_no_sources_desc'),
                ));
            }

            if ($any_lock !== false) {
                $this->delayed_epg = array_unique($this->delayed_epg);
                return array($day_start_ts => array(
                    PluginTvEpgProgram::end_tm_sec => $day_start_ts + 86400,
                    PluginTvEpgProgram::name => TR::load('epg_not_ready'),
                    PluginTvEpgProgram::description => TR::load('epg_not_ready_desc'),
                ));
            }
            return $this->getFakeEpg($channel_row, $day_start_ts, $day_epg);
        }

        ksort($day_epg);

        return $day_epg;
    }

    /**
     * Get picon for channel
     *
     * @param $db_name
     * @param string $placeHolders
     * @return string
     */
    public function get_picon($db_name, $placeHolders)
    {
        if ($this->is_index_locked($db_name, INDEXING_DOWNLOAD | INDEXING_CHANNELS)) {
            hd_debug_print("File is indexing or downloading, skipped");
            return false;
        }

        $ch_table_name = self::TABLE_CHANNELS;
        $picons_table_name = self::TABLE_PICONS;

        $query = "SELECT DISTINCT picon_url FROM $picons_table_name
                    INNER JOIN $ch_table_name ON $picons_table_name.picon_hash=$ch_table_name.picon_hash
                    WHERE alias IN ($placeHolders);";

        $db = $this->open_sqlite_db($db_name, self::TABLE_CHANNELS, true);
        if ($db === false) {
            return false;
        }

        return $db->query_value($query);
    }

    /**
     * Import indexing log to plugin logs
     *
     * @param array|null $sources_hash
     * @return int 0 - if any active source is locked, 1 - if import successful and no other active locks, 2 - if no locks and no imports
     */
    public function import_indexing_log($sources_hash = null)
    {
        $has_locks = false;
        if (is_null($sources_hash)) {
            $sources_hash = $this->xmltv_sources->get_keys();
        }

        $has_imports = false;
        foreach ($sources_hash as $hash) {
            if ($this->is_index_locked($hash, INDEXING_ALL)) {
                $has_locks = true;
                continue;
            }

            $index_log = get_temp_path("{$hash}_indexing.log");
            if (file_exists($index_log)) {
                hd_debug_print("Read epg indexing log $index_log...");
                hd_debug_print_separator();
                $logfile = @file_get_contents($index_log);
                foreach (explode(PHP_EOL, $logfile) as $l) {
                    hd_print(preg_replace("|^\[[\d:-]+\s(.*)$|", "[$1", rtrim($l)));
                }
                hd_debug_print_separator();
                hd_debug_print("Read finished");
                unlink($index_log);
                $has_imports = true;
            }
        }

        return $has_locks ? 0 : ($has_imports ? 1 : 2);
    }

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    public function clear_current_epg_cache()
    {
        hd_debug_print(null, true);
        $this->clear_epg_files($this->xmltv_url_params[PARAM_HASH]);
    }

    /**
     * indexing xmltv file to make channel to display-name map and collect picons for channels
     * or index all if $full is true
     *
     * @param int $index_flag
     * @return void
     */
    public function check_and_index_xmltv_source($index_flag)
    {
        hd_debug_print(null, true);

        if (empty($this->xmltv_url_params[PARAM_URI]) || empty($this->xmltv_url_params[PARAM_HASH])) {
            $exception_msg = "XMTLV EPG url not set";
            HD::set_last_error("xmltv_last_error", $exception_msg);
            $index_log = get_temp_path("{$this->xmltv_url_params[PARAM_HASH]}_indexing.log");
            if (file_exists($index_log)) {
                unlink($index_log);
            }
            return;
        }

        $url = $this->xmltv_url_params[PARAM_URI];
        $hash = $this->xmltv_url_params[PARAM_HASH];

        $cache_ttl = !isset($this->xmltv_url_params[PARAM_CACHE]) ? XMLTV_CACHE_AUTO : $this->xmltv_url_params[PARAM_CACHE];

        HD::set_last_error("xmltv_last_error", null);

        $cached_file = $this->cache_dir . $hash . ".xmltv";
        $cached_db = $this->cache_dir . $hash . ".db";
        hd_debug_print("Checking cached xmltv file: $cached_file", true);
        $expired = true;
        if (!file_exists($cached_file) || !file_exists($cached_db)) {
            hd_debug_print("Cached xmltv file not exist");
        } else {
            $modify_time_file = filemtime($cached_file);
            hd_debug_print("Xmltv cache ($cache_ttl) last modified: " . date("Y-m-d H:i", $modify_time_file), true);

            if ($cache_ttl === XMLTV_CACHE_AUTO) {
                $this->curl_wrapper->set_url($url);
                if (!$this->curl_wrapper->check_is_expired()) {
                    $expired = false;
                } else if ($this->curl_wrapper->is_cached_etag()) {
                    $this->curl_wrapper->clear_cached_etag();
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
            hd_debug_print("Xmltv cache expired.");
            $this->reindex_xmltv(true, $index_flag);
            return;
        }

        hd_debug_print("Cached file: $cached_file is not expired");
        $indexed = $this->get_indexes_info();

        // index for picons has not verified because it always exist if channels index is present
        $channels_valid = ($indexed[self::TABLE_CHANNELS] !== -1);
        $entries_valid = ($indexed[self::TABLE_ENTRIES] !== -1);

        if ($channels_valid && ($index_flag & INDEXING_ENTRIES) === 0) {
            hd_debug_print("Xmltv channels index is valid");
            $this->clear_log($hash);
            return;
        }

        if ($channels_valid && $entries_valid) {
            hd_debug_print("Xmltv channels and entries index are valid");
            $this->clear_log($hash);
            return;
        }

        // downloaded xmltv file exists, not expired but indexes for channels, picons and positions not exists
        hd_debug_print("Index flag: $index_flag");
        $this->reindex_xmltv(false, $index_flag);
    }

    /**
     * Check if lock for specified cache is exist
     *
     * @param string $hash
     * @param int $index_flag
     * @return bool
     */
    public function is_index_locked($hash, $index_flag)
    {
        $locked = false;
        if ($index_flag & INDEXING_DOWNLOAD) {
            $dirs = (glob($this->cache_dir . $hash . "_*.lockd", GLOB_ONLYDIR));
            $locked |= !empty($dirs);
        }

        if ($index_flag & INDEXING_CHANNELS) {
            $dirs = (glob($this->cache_dir . $hash . "_*.lockc", GLOB_ONLYDIR));
            $locked |= !empty($dirs);
        }

        if ($index_flag & INDEXING_ENTRIES) {
            $dirs = (glob($this->cache_dir . $hash . "_*.locke", GLOB_ONLYDIR));
            $locked |= !empty($dirs);
        }
        return $locked;
    }

    /**
     * Check if any locks for all sources and return name of locks
     *
     * @return bool|array
     */
    public function get_any_index_locked()
    {
        $dirs = glob($this->cache_dir . "*.lock?", GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $locks[] = basename($dir);
        }

        return empty($locks) ? false : $locks;
    }

    /**
     * @param string $hash
     * @param int $index_flag
     */
    public function lock_index($hash, $index_flag)
    {
        if ($index_flag & INDEXING_DOWNLOAD) {
            $this->set_lock($this->cache_dir . $hash . "_$this->pid.lockd", true);
        }

        if ($index_flag & INDEXING_CHANNELS) {
            $this->set_lock($this->cache_dir . $hash . "_$this->pid.lockc", true);
        }

        if ($index_flag & INDEXING_ENTRIES) {
            $this->set_lock($this->cache_dir . $hash . "_$this->pid.locke", true);
        }
    }

    /**
     * @param string $hash
     * @param int $index_flag
     */
    public function unlock_index($hash, $index_flag)
    {
        if ($index_flag & INDEXING_DOWNLOAD) {
            $this->set_lock($this->cache_dir . $hash . "_$this->pid.lockd", false);
        }

        if ($index_flag & INDEXING_CHANNELS) {
            $this->set_lock($this->cache_dir . $hash . "_$this->pid.lockc", false);
        }

        if ($index_flag & INDEXING_ENTRIES) {
            $this->set_lock($this->cache_dir . $hash . "_$this->pid.locke", false);
        }
    }

    /**
     * clear memory cache and cache for selected filename (hash) mask
     *
     * @param string|null $hash
     * @return void
     */
    public function clear_epg_files($hash = null)
    {
        hd_debug_print(null, true);
        if (empty($hash)) {
            $this->curl_wrapper->clear_all_etag_cache();
        } else {
            $this->curl_wrapper->clear_cached_etag();
        }

        if (empty($hash)) {
            $this->epg_db = array();
        } else if (isset($this->epg_db[$hash])) {
            unset($this->epg_db[$hash]);
        }

        if (empty($this->cache_dir)) {
            return;
        }

        $mask = empty($hash) ? "*" : $hash;
        $dirs = glob($this->cache_dir . $mask . "_*.lock?", GLOB_ONLYDIR);
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

        $mask = empty($hash) ? "" : $hash;
        $files = $this->cache_dir . $mask . "*";
        hd_debug_print("clear epg files: $files");
        array_map('unlink', glob($files));
        clearstatcache();
        hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
    }

    /**
     * Get information about indexes
     * @return array
     */
    public function get_indexes_info($db_name = null)
    {
        hd_debug_print(null, true);
        $result = array(self::TABLE_CHANNELS => -1, self::TABLE_PICONS => -1, self::TABLE_ENTRIES => -1, 'epg_ids' => -1);

        $db_name = is_null($db_name) ? $this->xmltv_url_params[PARAM_HASH] : $db_name;

        foreach ($result as $key => $name) {
            if ($key === 'epg_ids') continue;

            $db = $this->open_sqlite_db($db_name, $key, true);
            if (empty($db)) {
                hd_debug_print("Problem with open SQLite db '$db_name' for read! Possible database not exist");
                continue;
            }

            if (!$db->is_table_exists($key)) continue;

            if ($key === self::TABLE_CHANNELS) {
                $result[$key] = $db->query_value("SELECT COUNT(DISTINCT channel_id) FROM $key;");
            } else if ($key === self::TABLE_PICONS) {
                $result[$key] = $db->query_value("SELECT COUNT(*) FROM $key;");
            } else if ($key === self::TABLE_ENTRIES) {
                $result[$key] = $db->query_value("SELECT COUNT(*) FROM $key;");
                $result['epg_ids'] = $db->query_value("SELECT COUNT(DISTINCT channel_id) FROM $key;");
            }
        }

        hd_debug_print("Found indexes: " . json_encode($result));
        return $result;
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param array $channel_row
     * @return array|null
     */
    protected function load_program_index($channel_row)
    {
        $channel_positions = array();

        if (!$this->is_all_indexes_valid(array(self::TABLE_CHANNELS, self::TABLE_ENTRIES))) {
            hd_debug_print("EPG for {$this->xmltv_url_params[PARAM_URI]} not indexed!");
            return $channel_positions;
        }

        $channel_id = $channel_row[COLUMN_CHANNEL_ID];
        $channel_title = $channel_row[COLUMN_TITLE];
        $epg_ids = array_unique(array_filter(array(
            $channel_row[M3uParser::COLUMN_EPG_ID],
            $channel_id,
            $channel_row[M3uParser::COLUMN_TVG_NAME],
            $channel_title))
        );

        $aliases = Sql_Wrapper::sql_make_list_from_values(array_map(function($value) {
            return mb_convert_case($value, MB_CASE_LOWER, "UTF-8");
        }, $epg_ids));

        hd_debug_print("Search for aliases: $aliases", true);

        $db_channels = $this->open_sqlite_db($this->xmltv_url_params[PARAM_HASH], self::TABLE_CHANNELS, true);
        if ($db_channels === false) {
            hd_debug_print("Problem with open SQLite channels db! Possible database not exist");
            return $channel_positions;
        }

        $table_channels = self::TABLE_CHANNELS;
        $query = "SELECT DISTINCT channel_id FROM $table_channels WHERE alias IN ($aliases);";
        $channel_ids = $db_channels->fetch_single_array($query, 'channel_id');
        if (empty($channel_ids)) {
            hd_debug_print("No channel_id found for aliases: $aliases");
        } else {
            hd_debug_print("Load position indexes for: $channel_id ($channel_title)", true);
            $db_entries = $this->open_sqlite_db($this->xmltv_url_params[PARAM_HASH], self::TABLE_ENTRIES, true);
            if ($db_entries === false) {
                hd_debug_print("Problem with open SQLite channels db! Possible database not exist");
                return $channel_positions;
            }

            $table_pos = self::TABLE_ENTRIES;
            $placeHolders = Sql_Wrapper::sql_make_list_from_values($channel_ids);
            $query = "SELECT start, end FROM $table_pos WHERE channel_id IN ($placeHolders);";
            $channel_positions = $db_entries->fetch_array($query);
            if (empty($channel_positions)) {
                hd_debug_print("No positions found for channel $channel_id ($channel_title) and channel id's: $placeHolders");
            } else {
                hd_debug_print("Channel positions: " . json_encode($channel_positions), true);
            }
        }

        return $channel_positions;
    }

    /**
     * @param array $channel_row
     * @param int $day_start_ts
     * @param array $day_epg
     * @return array
     */
    protected function getFakeEpg($channel_row, $day_start_ts, $day_epg)
    {
        if (($this->flags & EPG_FAKE_EPG) && $channel_row[M3uParser::COLUMN_ARCHIVE] !== 0) {
            hd_debug_print("Create fake data for non existing EPG data");
            for ($start = $day_start_ts, $n = 1; $start <= $day_start_ts + 86400; $start += 3600, $n++) {
                $day_epg[$start][PluginTvEpgProgram::end_tm_sec] = $start + 3600;
                $day_epg[$start][PluginTvEpgProgram::name] = TR::load('fake_epg_program') . " $n";
                $day_epg[$start][PluginTvEpgProgram::description] = '';
            }
        } else {
            hd_debug_print("No EPG for channel: {$channel_row[COLUMN_CHANNEL_ID]}");
        }

        return $day_epg;
    }

    /**
     * Check is all indexes is valid
     *
     * @param array $names
     * @return bool
     */
    protected function is_all_indexes_valid($names)
    {
        hd_debug_print(null, true);

        foreach ($names as $name) {
            $db = $this->open_sqlite_db($this->xmltv_url_params[PARAM_HASH], $name, true);
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

    ///////////////////////////////////////////////////////////////////////////////
    /// private methods

    private function set_lock($name, $lock)
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
     * open sqlite database
     * @param string $db_name
     * @param string $table_name
     * @param bool $readonly
     * @return Sql_Wrapper|bool
     */
    private function open_sqlite_db($db_name, $table_name, $readonly)
    {
        if ($table_name === self::TABLE_ENTRIES) {
            $db_name = $db_name . "_entries";
        }

        $db_file = $this->cache_dir . $db_name . ".db";
        // in read-only database can't be created
        if ($readonly && !file_exists($db_file)) {
            hd_debug_print("File '$db_file' not found");
            return false;
        }

        // if database not exist or requested mode is read-write create new database
        if (!isset($this->epg_db[$db_name]) || (!$readonly && $this->epg_db[$db_name]->is_readonly())) {
            $flags = $readonly ? SQLITE3_OPEN_READONLY : (SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $db = new Sql_Wrapper($db_file, $flags);
            if (!$db->is_valid()) {
                return false;
            }
            $this->epg_db[$db_name] = $db;
        }

        return $this->epg_db[$db_name];
    }

    /**
     * Download and index xmltv source
     *
     * @param bool $download
     * @param int $indexing_flag
     * @return void
     */
    private function reindex_xmltv($download, $indexing_flag)
    {
        hd_debug_print("Indexing xmltv");

        $url = $this->xmltv_url_params[PARAM_URI];
        $url_hash = $this->xmltv_url_params[PARAM_HASH];
        $cached_file = $this->cache_dir . $url_hash . ".xmltv";

        if (empty($url) || empty($url_hash)) {
            hd_debug_print("Url not set, skipped");
            return;
        }

        if ($download) {
            // download xmtv is denied if download or any indexing in process
            if ($this->is_index_locked($url_hash, INDEXING_ALL)) {
                hd_debug_print("File is indexing or downloading, skipped");
                return;
            }

            $this->lock_index($url_hash, INDEXING_DOWNLOAD);
            $success = false;
            try {
                HD::set_last_error("xmltv_last_error", null);

                if (preg_match("/jtv.?\.zip$/", basename(urldecode($url)))) {
                    hd_debug_print("Unsupported EPG format (JTV)");
                    throw new Exception("Unsupported EPG format (JTV)");
                }

                hd_debug_print("Download xmltv source: $url");
                hd_debug_print_separator();


                hd_debug_print("Storage space in cache dir: " . HD::get_storage_size($this->cache_dir));
                $tmp_filename = $cached_file . ".tmp";
                if (file_exists($tmp_filename)) {
                    unlink($tmp_filename);
                }

                $this->perf->reset('download');

                hd_debug_print("Download: $url");
                $this->curl_wrapper->set_url($url);
                $this->curl_wrapper->clear_cached_etag();
                if (!$this->curl_wrapper->download_file($tmp_filename, true)) {
                    throw new Exception("Can't exec curl");
                }

                $http_code = $this->curl_wrapper->get_response_code();
                if ($http_code !== 200) {
                    throw new Exception("Download error ($http_code) $url" . PHP_EOL . PHP_EOL
                        . $this->curl_wrapper->get_raw_response_headers());
                }

                $this->perf->setLabel('end_download');
                $file_time = filemtime($tmp_filename);
                $dl_time = $this->perf->getReportItem(Perf_Collector::TIME, 'download');
                $file_size = filesize($tmp_filename);
                $bps = $file_size / $dl_time;
                $si_prefix = array('B/s', 'KB/s', 'MB/s');
                $base = 1024;
                $class = min((int)log($bps, $base), count($si_prefix) - 1);
                $speed = sprintf('%1.2f', $bps / pow($base, $class)) . ' ' . $si_prefix[$class];

                hd_debug_print("ETag value: " . $this->curl_wrapper->get_cached_etag());
                hd_debug_print("Modify time:    " . date("Y-m-d H:i", $file_time));
                hd_debug_print("Download size:  $file_size bytes");
                hd_debug_print("Download time:  $dl_time secs");
                hd_debug_print("Download speed: $speed");
                hd_debug_print_separator();

                $this->perf->setLabel('unpack');
                $this->unpack_xmltv($tmp_filename, $cached_file);
                $this->perf->setLabel('end_unpack');
                $time = $this->perf->getReportItem(Perf_Collector::TIME, 'unpack');
                hd_debug_print("Unpack to:   $cached_file");
                hd_debug_print("Unpack time: $time secs");
                hd_debug_print_separator();

                $success = true;
            } catch (Exception $ex) {
                print_backtrace_exception($ex);
                if (!empty($tmp_filename) && file_exists($tmp_filename)) {
                    unlink($tmp_filename);
                }

                if (file_exists($cached_file)) {
                    unlink($cached_file);
                }
            }

            $this->unlock_index($url_hash, INDEXING_DOWNLOAD);

            if (!$success) {
                return;
            }
        }

        /////////////////////////////////////////////////////////////////
        /// Reindex channels and picons

        if ($indexing_flag & INDEXING_CHANNELS) {
            hd_debug_print("Start reindex channels and picons...");
            $this->lock_index($url_hash, INDEXING_CHANNELS);

            $success = false;
            $file = false;
            try {
                if (!file_exists($cached_file)) {
                    throw new Exception("Cache file $cached_file not exist");
                }

                $file = fopen($cached_file, 'rb');
                if (!$file) {
                    throw new Exception("Can't open file: $cached_file");
                }

                $db = $this->open_sqlite_db($url_hash, self::TABLE_CHANNELS, false);
                if ($db === false) {
                    throw new Exception("Can't open db: $url_hash");
                }

                $this->perf->reset('start_reindex_channels');

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
                    $line = stream_get_line($file, 0, "<channel ");
                    if (empty($line)) continue;

                    fseek($file, -9, SEEK_CUR);
                    $str = fread($file, 9);
                    if ($str !== "<channel ") continue;

                    $line = stream_get_line($file, 0, "</channel>");
                    if (empty($line)) continue;

                    $line = "<channel " . $line . "</channel>";

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

                    $query .= "INSERT OR REPLACE INTO $ch_table_name (alias, channel_id, picon_hash) VALUES($q_channel_id, $q_channel_id, '$picon_hash');";

                    foreach ($xml_node->getElementsByTagName('display-name') as $tag) {
                        $q_alias = Sql_Wrapper::sql_quote(mb_convert_case($tag->nodeValue, MB_CASE_LOWER, "UTF-8"));
                        $query .= "INSERT OR REPLACE INTO $ch_table_name (alias, channel_id, picon_hash) VALUES($q_alias, $q_channel_id, '$picon_hash');";
                    }
                }
                $db->exec_transaction($query);

                $res = $db->query_value("SELECT count(DISTINCT channel_id) FROM $ch_table_name;");
                $channels = empty($res) ? 0 : (int)$res;

                $res = $db->query_value("SELECT COUNT(*) FROM $picons_table_name;");
                $picons = empty($res) ? 0 : (int)$res;

                $this->perf->setLabel('end_reindex_channels');
                $report = $this->perf->getFullReport('start_reindex_channels');

                hd_debug_print("Total channels id's: $channels");
                hd_debug_print("Total known picons: $picons");
                hd_debug_print("Reindexing EPG channels done: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
                hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
                hd_debug_print_separator();
                $success = true;
            } catch (Exception $ex) {
                hd_debug_print($ex->getMessage());
            }

            if ($file) {
                fclose($file);
            }

            $this->unlock_index($url_hash, INDEXING_CHANNELS);
            if (!$success) {
                return;
            }
        }

        /////////////////////////////////////////////////////////////////
        /// Reindex positions

        if ($indexing_flag & INDEXING_ENTRIES) {
            $this->lock_index($url_hash, INDEXING_ENTRIES);

            $file = false;
            try {
                if (!file_exists($cached_file)) {
                    throw new Exception("Cache file $cached_file not exist");
                }

                $file = fopen($cached_file, 'rb');
                if (!$file) {
                    throw new Exception("Can't open file: $cached_file");
                }

                $db = $this->open_sqlite_db($url_hash, self::TABLE_ENTRIES, false);
                if ($db === false) {
                    throw new Exception("Can't open db: $url_hash");
                }

                hd_debug_print("Indexing positions for: $url", true);
                $this->perf->reset('start_reindex_entries');

                $pos_table_name = self::TABLE_ENTRIES;
                $query = "DROP TABLE IF EXISTS $pos_table_name;";
                $query .= "CREATE TABLE $pos_table_name (channel_id STRING not null, start INTEGER, end INTEGER, UNIQUE (channel_id, start) ON CONFLICT REPLACE);";
                $res = $db->exec_transaction($query);
                if (!$res) {
                    throw new Exception("Error transaction: $query");
                }

                hd_debug_print("Begin transactions...");
                $db->exec('BEGIN;');

                $stm = $db->prepare("INSERT INTO $pos_table_name (channel_id, start, end) VALUES(:channel_id, :start, :end);");
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
                    $tag_end_pos = ftell($file);
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
                        $stm->execute();
                        $prev_channel = $channel_id;
                        $start_program_block = $tag_start_pos;
                    }
                }

                hd_debug_print("End transactions...");
                $db->exec('COMMIT;');

                $res = $db->query_value("SELECT count(DISTINCT channel_id) FROM $pos_table_name;");
                $total_epg = empty($res) ? 0 : (int)$res;

                $res = $db->query_value("SELECT COUNT(*) FROM $pos_table_name;");
                $total_blocks = empty($res) ? 0 : (int)$res;

                $this->perf->setLabel('end_reindex_entries');
                $report = $this->perf->getFullReport('start_reindex_entries');

                hd_debug_print("Total unique epg id's indexed: $total_epg, total blocks: $total_blocks");
                hd_debug_print("Reindexing EPG positions done: {$report[Perf_Collector::TIME]} secs");
                hd_debug_print("Storage space in cache dir after reindexing: " . HD::get_storage_size($this->cache_dir));
                hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
                hd_debug_print_separator();
            } catch (Exception $ex) {
                hd_debug_print($ex->getMessage());
            }

            if ($file) {
                fclose($file);
            }

            $this->unlock_index($url_hash, INDEXING_ENTRIES);
        }
    }

    /**
     * @param string $tmp_filename
     * @param string $cached_file
     * @throws Exception
     */
    private function unpack_xmltv($tmp_filename, $cached_file)
    {
        if (file_exists($cached_file)) {
            hd_debug_print("Remove cached file: $cached_file");
            unlink($cached_file);
        }

        $file_time = filemtime($tmp_filename);
        $handle = fopen($tmp_filename, "rb");
        $hdr = fread($handle, 8);
        fclose($handle);

        if (0 === mb_strpos($hdr, "\x1f\x8b\x08")) {
            hd_debug_print("GZ signature: " . bin2hex(substr($hdr, 0, 3)), true);
            rename($tmp_filename, $cached_file . '.gz');
            $tmp_filename = $cached_file . '.gz';
            hd_debug_print("ungzip $tmp_filename to $cached_file");
            $cmd = "gzip -d $tmp_filename 2>&1";
            system($cmd, $ret);
            if ($ret !== 0) {
                throw new Exception("Failed to ungzip $tmp_filename (error code: $ret)");
            }
            clearstatcache();
            $size = filesize($cached_file);
            touch($cached_file, $file_time);
            hd_debug_print("$size bytes ungzipped to $cached_file in "
                . $this->perf->getReportItemCurrent(Perf_Collector::TIME, 'unpack') . " secs");
        } else if (0 === mb_strpos($hdr, "\x50\x4b\x03\x04")) {
            hd_debug_print("ZIP signature: " . bin2hex(substr($hdr, 0, 4)), true);
            hd_debug_print("unzip $tmp_filename to $cached_file");
            $filename = trim(shell_exec("unzip -lq '$tmp_filename'|grep -E '[\d:]+'"));
            if (empty($filename)) {
                throw new Exception(TR::t('err_empty_zip__1', $tmp_filename));
            }

            if (explode('\n', $filename) > 1) {
                throw new Exception("Too many files in zip archive, wrong format??!\n$filename");
            }

            hd_debug_print("zip list: $filename");
            $cmd = "unzip -oq $tmp_filename -d $this->cache_dir 2>&1";
            system($cmd, $ret);
            unlink($tmp_filename);
            if ($ret !== 0) {
                throw new Exception("Failed to unpack $tmp_filename (error code: $ret)");
            }
            clearstatcache();

            rename($filename, $cached_file);
            $size = filesize($cached_file);
            touch($cached_file, $file_time);
            hd_debug_print("$size bytes unzipped to $cached_file in "
                . $this->perf->getReportItemCurrent(Perf_Collector::TIME, 'unpack') . " secs");
        } else if (false !== mb_strpos($hdr, "<?xml")) {
            hd_debug_print("XML signature: " . substr($hdr, 0, 5), true);
            hd_debug_print("rename $tmp_filename to $cached_file");
            rename($tmp_filename, $cached_file);
            $size = filesize($cached_file);
            touch($cached_file, $file_time);
            hd_debug_print("$size bytes stored to $cached_file in "
                . $this->perf->getReportItemCurrent(Perf_Collector::TIME, 'unpack') . " secs");
        } else {
            hd_debug_print("Unknown signature: " . bin2hex($hdr), true);
            throw new Exception(TR::load('err_unknown_file_type'));
        }
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

    protected function lock_type($index_flag)
    {
        if ($index_flag & INDEXING_CHANNELS) {
            $ext = ".lockc";
        } else if ($index_flag & INDEXING_ENTRIES) {
            $ext = ".locke";
        } else {
            $ext = ".lock";
        }
        return $ext;
    }

    protected function clear_log($hash)
    {
        $index_log = get_temp_path("{$hash}_indexing.log");
        if (file_exists($index_log)) {
            unlink($index_log);
        }
    }
}
