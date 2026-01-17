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

require_once 'epg_manager_xmltv.php';

class Epg_Manager_Json extends Epg_Manager_Xmltv
{
    const EPG_ROOT = 'epg_root';
    const EPG_START = 'epg_start';
    const EPG_NAME = 'epg_name';
    const EPG_DESC = 'epg_desc';
    const EPG_URL = 'epg_url';

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    /**
     * contains current dune IP
     * @var string
     */
    protected $dune_ip;

    public function __construct($plugin)
    {
        parent::__construct($plugin);
        $this->plugin = $plugin;
    }

    /**
     * @param api_default $provider
     * @param array $preset
     * @param array $channel_row
     * @param int $day_start_ts
     * @param string $epg_id
     * @return string|null
     */
    public static function get_epg_url($provider, $preset, $channel_row, $day_start_ts, $epg_id)
    {
        $alias = empty($preset[EPG_JSON_PRESET_ALIAS]) ? $provider->getId() : $preset[EPG_JSON_PRESET_ALIAS];
        hd_debug_print("Using alias '$alias' for preset '{$preset[EPG_JSON_PRESET_NAME]}'");
        $epg_url = str_replace(array(MACRO_API, MACRO_PROVIDER), array($provider->getApiUrl(), $alias), $preset[EPG_JSON_SOURCE]);
        $epg_url = $provider->replace_macros($epg_url);

        $epg_url = str_replace(MACRO_TIMESTAMP, $day_start_ts, $epg_url);

        if (strpos($epg_url, MACRO_ID) !== false) {
            hd_debug_print("using ID: {$channel_row[COLUMN_CHANNEL_ID]}", true);
            $epg_url = str_replace(MACRO_ID, $channel_row[COLUMN_CHANNEL_ID], $epg_url);
        }

        $cur_time = from_local_time_zone_offset($day_start_ts);
        $epg_date = gmdate('Y', $cur_time);
        $epg_url = str_replace(MACRO_YEAR, $epg_date, $epg_url);

        $epg_date = gmdate('m', $cur_time);
        $epg_url = str_replace(MACRO_MONTH, $epg_date, $epg_url);

        $epg_date = gmdate('d', $cur_time);
        $epg_url = str_replace(MACRO_DAY, $epg_date, $epg_url);

        $epg = str_replace(array('%28', '%29'), array('(', ')'), rawurlencode($epg_id));
        return str_replace(array(MACRO_EPG_ID, '#'), array($epg, '%23'), $epg_url);
    }

    /**
     * @inheritDoc
     * @override
     */
    public function get_day_epg_items($channel_row, $day_start_ts, &$cached)
    {
        $cached = false;

        $day_epg = array();
        $items = array();
        try {
            $provider = $this->plugin->get_active_provider();
            if (is_null($provider)) {
                $day_epg['error'] = "No provider found";
                $day_epg['items'] = array();
                return $day_epg;
            }

            $epg_id = self::get_epg_id($channel_row);
            if (empty($epg_id)) {
                throw new Exception("No EPG ID defined");
            }

            // try to find in memory cache
            // in JSON engine only one EPG ID is available
            $day_start_ts_str = format_datetime("Y-m-d H:i", $day_start_ts);
            if (isset(static::$epg_cache[$epg_id][$day_start_ts])) {
                hd_debug_print("EPG memory cache: Load EPG ID: $epg_id for day start: $day_start_ts ($day_start_ts_str)");
                $day_epg['items'] = static::$epg_cache[$epg_id][$day_start_ts];
                return $day_epg;
            }

            foreach ($provider->getConfigValue(EPG_JSON_PRESETS, array()) as $preset) {
                $config_preset = $this->plugin->get_configured_preset($preset);
                if (empty($config_preset)) {
                    continue;
                }

                $epg_url = Epg_Manager_Json::get_epg_url($provider, $config_preset, $channel_row, $day_start_ts, $epg_id);
                if (empty($epg_url)) {
                    hd_debug_print("EPG url for preset '{$config_preset['name']}' is not generated");
                    continue;
                }

                hd_debug_print("EPG url: $epg_url");
                hd_debug_print("Try to load EPG ID: '$epg_id' for channel '{$channel_row[COLUMN_CHANNEL_ID]}' ({$channel_row[COLUMN_TITLE]})");

                $epg_cache_file = self::$cache_dir . $provider->get_provider_playlist_id() . "_" . Hashed_Array::hash($epg_url) . ".cache";
                hd_debug_print("Check cache file: $epg_cache_file");
                $from_cache = false;
                $all_epg = array();
                if (file_exists($epg_cache_file)) {
                    $now = time();
                    $mtime = filemtime($epg_cache_file);
                    $cache_expired = $mtime + $this->plugin->get_setting(PARAM_EPG_CACHE_TIME, 1) * 3600;
                    if ($cache_expired > time()) {
                        $all_epg = parse_json_file($epg_cache_file);
                        $from_cache = true;
                        hd_debug_print("Loading all entries for EPG ID: '$epg_id' from file cache: $epg_cache_file");
                    } else {
                        hd_debug_print("EPG cache $epg_cache_file expired " . ($now - $cache_expired) . " sec ago. Timestamp $mtime. Remove cache file");
                        safe_unlink($epg_cache_file);
                    }
                }

                if ($from_cache === false) {
                    hd_debug_print("Fetching EPG ID: '$epg_id' from server: $epg_url");
                    $all_epg = self::get_epg_json($epg_url, $provider, $config_preset);
                    if (!empty($all_epg)) {
                        hd_debug_print("Save EPG ID: '$epg_id' to file cache $epg_cache_file");
                        store_to_json_file($epg_cache_file, $all_epg);
                    }
                }

                $counts = count($all_epg);
                if ($counts === 0) {
                    hd_debug_print("No EPG entries found for '$epg_url'");
                    continue;
                }

                hd_debug_print("Total $counts EPG entries loaded");

                $first_tm = key($all_epg);
                $first = format_datetime("Y-m-d H:i", $first_tm);
                $last_tm = $all_epg[key(array_slice($all_epg, -1, 1, true))][PluginTvEpgProgram::end_tm_sec];
                $last = format_datetime("Y-m-d H:i", $last_tm);
                hd_debug_print("Entries time range: $first ($first_tm) - $last ($last_tm)");
                // filter out epg only for selected day
                $day_end_ts = $day_start_ts + 86400;

                if ($day_start_ts > $last_tm || $day_end_ts < $first_tm) {
                    hd_debug_print("Selected time is out of range. Available EPG time range: $first - $last");
                    continue;
                }

                if (LogSeverity::$is_debug) {
                    $date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
                    $date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
                    hd_debug_print("Fetch entries for from: $date_start_l to: $date_end_l");
                }

                foreach ($all_epg as $program_start => $entry) {
                    if ($program_start < $day_start_ts && $entry[PluginTvEpgProgram::end_tm_sec] < $day_start_ts) continue;
                    if ($program_start >= $day_end_ts) break;

                    $items[$program_start] = $entry;
                }

                if (empty($items)) {
                    hd_debug_print("No EPG entries for selected time in available range");
                    continue;
                }

                hd_debug_print("Memory cache: Store EPG ID: $epg_id for day start: $day_start_ts ($day_start_ts_str)");
                self::$epg_cache[$epg_id][$day_start_ts] = $items;
            }
            if (empty($items)) {
                throw new Exception(TR::load('err_no_epg_in_all_range'));
            }
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
            $day_epg['error'] = $ex->getMessage();
            $items = static::getFakeEpg($channel_row, $day_start_ts, $items);
        }
        $day_epg['items'] = $items;
        return $day_epg;
    }

    /**
     * @param array $channel_row
     * @return string
     */
    public static function get_epg_id($channel_row)
    {
        $epg_ids = Default_Dune_Plugin::make_epg_ids($channel_row);
        if (empty($epg_ids[ATTR_TVG_NAME])) {
            $epg_ids[ATTR_TVG_NAME] = $channel_row[COLUMN_TITLE];
        }

        if (empty($epg_ids[ATTR_TVG_ID])) {
            $epg_ids[ATTR_TVG_ID] = $channel_row[COLUMN_CHANNEL_ID];
        }

        if (isset($selected_preset[EPG_JSON_EPG_MAP])) {
            $epg_id = $epg_ids[$selected_preset[EPG_JSON_EPG_MAP]];
            hd_debug_print("EPG ID map: $epg_id", true);
        } else {
            $epg_id = '';
            foreach (array('epg_id', ATTR_TVG_ID, ATTR_TVG_NAME, 'name', 'id') as $key) {
                if (!empty($epg_ids[$key])) {
                    $epg_id = $epg_ids[$key];
                    break;
                }
            }
            hd_debug_print("Found epg id: '$epg_id'", true);
        }
        return $epg_id;
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * request server for epg and parse json response
     * @param string $url
     * @param api_default $provider
     * @param array $preset
     * @return array
     */
    protected static function get_epg_json($url, $provider, $preset)
    {
        $channel_epg = array();

        if (empty($preset[EPG_JSON_PARSER])) {
            return $channel_epg;
        }

        $parser_params = $preset[EPG_JSON_PARSER];
        hd_debug_print("parser params: " . json_format_unescaped($parser_params), true);

        try {
            $opts = null;
            if (isset($preset[EPG_JSON_AUTH])) {
                $opts[CURLOPT_HTTPHEADER] = array($provider->replace_macros($preset[EPG_JSON_AUTH]));
            }
            $ch_data = Curl_Wrapper::getInstance()->download_content($url, Curl_Wrapper::RET_ARRAY | Curl_Wrapper::CACHE_RESPONSE);
            if ($ch_data === false) {
                return $channel_epg;
            }

            if (empty($ch_data)) {
                hd_debug_print("Empty document returned.");
                return $channel_epg;
            }
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            return $channel_epg;
        }

        if (!empty($parser_params[self::EPG_ROOT])) {
            foreach (explode('|', $parser_params[self::EPG_ROOT]) as $level) {
                $epg_root = trim($level, "[]");
                $ch_data = $ch_data[$epg_root];
            }
        }

        hd_debug_print("json epg root: " . $parser_params[self::EPG_ROOT], true);
        hd_debug_print("json start: " . $parser_params[self::EPG_START], true);
        hd_debug_print("json title: " . $parser_params[self::EPG_NAME], true);
        hd_debug_print("json desc: " . $parser_params[self::EPG_DESC], true);
        if (isset($parser_params[self::EPG_URL])) {
            hd_debug_print("json icon: " . $parser_params[self::EPG_URL], true);
        }

        // collect all program that starts after day start and before day end
        $prev_start = 0;
        foreach ($ch_data as $entry) {
            if (!isset($entry[$parser_params[self::EPG_START]])) continue;

            $program_start = $entry[$parser_params[self::EPG_START]];

            if ($prev_start !== 0) {
                $channel_epg[$prev_start][PluginTvEpgProgram::end_tm_sec] = $program_start;
            }
            $prev_start = $program_start;

            $channel_epg[$program_start][PluginTvEpgProgram::name] = HD::unescape_entity_string(safe_get_value($entry, $parser_params[self::EPG_NAME], ''));

            $desc = HD::unescape_entity_string(safe_get_value($entry, $parser_params[self::EPG_DESC], ''));
            if (!empty($desc)) {
                $desc = str_replace(array('<br>', "<'>br>"), PHP_EOL, $desc);
            }
            $channel_epg[$program_start][PluginTvEpgProgram::description] = $desc;

            $channel_epg[$program_start][PluginTvEpgProgram::icon_url] = safe_get_value($entry, safe_get_value($parser_params, self::EPG_URL), '');
        }

        if ($prev_start !== 0) {
            $channel_epg[$prev_start][PluginTvEpgProgram::end_tm_sec] = $prev_start + 3600; // fake end
        }

        ksort($channel_epg, SORT_NUMERIC);
        return $channel_epg;
    }

    /**
     * @inheritDoc
     * @override
     */
    public static function clear_epg_files($hash = '')
    {
        hd_debug_print(null, true);

        self::clear_epg_memory_cache();
        $files = self::$cache_dir . (empty($hash) ? '*.cache' : "$hash*.cache");
        hd_debug_print("clear cache files: $files");
        array_map('unlink', glob($files));
        clearstatcache();
    }
}
