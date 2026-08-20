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
require_once 'lib/list_utils.php';

class ChannelInfo
{
    /**
     * contains known channels epg_id and aliases
     * @var array
     */
    public $info = array();

    /**
     * contains known channels epg_id and aliases
     * @var int
     */
    public $expired = 0;
}

class Epg_Manager_Json extends Epg_Manager_Xmltv
{
    const EPG_ROOT = 'epg_root';
    const EPG_START = 'epg_start';
    const EPG_END = 'epg_end';
    const EPG_NAME = 'epg_name';
    const EPG_DESC = 'epg_desc';
    const EPG_URL = 'epg_url';
    const EPG_ICON = 'epg_icon';
    const EPG_TIME_FORMAT = 'epg_time_format';
    const EPG_TIMEZONE = 'epg_timezone';

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    /**
     * contains current dune IP
     * @var string
     */
    protected $dune_ip;

    /**
     * contains known channels epg_id and aliases
     * @var ChannelInfo[]
     */
    protected static $all_channels_info = array();

    public function __construct($plugin)
    {
        parent::__construct($plugin);
        $this->plugin = $plugin;
    }

    /**
     * @param api_default $provider
     * @param array $preset
     * @param int $day_start_ts
     * @param string $epg_id
     * @return string|null
     */
    public static function get_epg_url($provider, $preset, $day_start_ts, $epg_id)
    {
        if (empty($preset[EPG_JSON_PRESET_ALIAS])) {
            $provider_id = $provider->getId();
            hd_debug_print("Using id '$provider_id' for preset '{$preset[EPG_JSON_PRESET_NAME]}'");
        } else {
            $provider_id = $preset[EPG_JSON_PRESET_ALIAS];
            hd_debug_print("Using alias '$provider_id' for preset '{$preset[EPG_JSON_PRESET_NAME]}'");
        }
        $epg_url = str_replace(array(MACRO_API, MACRO_PROVIDER), array($provider->getApiUrl(), $provider_id), $preset[EPG_JSON_SOURCE]);
        $epg_url = $provider->replace_macros($epg_url);

        $epg_url = str_replace(MACRO_TIMESTAMP, $day_start_ts, $epg_url);

        if (strpos($epg_url, MACRO_ID) !== false) {
            hd_debug_print("using ID: $epg_id", true);
            $epg_url = str_replace(MACRO_ID, $epg_id, $epg_url);
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
    public function get_day_epg_items($channel_row, $day_start_ts)
    {
        $day_end_ts = $day_start_ts + 86400;
        $day_epg = array();
        $day_items = array();
        try {
            $provider = $this->plugin->get_active_provider();
            if (empty($provider)) {
                throw new Exception('Unknown active provider');
            }

            $presets_ids = $this->plugin->get_provider_epg_presets();
            if (empty($presets_ids)) {
                $day_epg['error'] = "No EPG preset";
                $day_epg['items'] = array();
                return $day_epg;
            }

            $epg_ids = self::get_epg_ids($channel_row);
            if (empty($epg_ids)) {
                throw new Exception("No EPG ID's defined");
            }

            // try to find in memory cache
            // in JSON engine only one EPG ID is available
            // epg id or mapped id always first
            $epg_id = $epg_ids[0];
            $preset_order = $this->plugin->get_selected_json_sources(true);
            hd_debug_print('EPG servers order: ' . json_format_unescaped($preset_order), true);
            foreach ($preset_order as $preset_id) {
                $config_preset = $this->plugin->get_configured_preset($preset_id);
                if (empty($config_preset)) {
                    hd_debug_print("EPG preset '$preset_id' is not configured");
                    continue;
                }

                $epg_id = $this->check_and_update_epg_id($provider->getId(), $epg_id, $epg_ids, $config_preset);
                if (empty($epg_id)) continue;

                $epg_url = self::get_epg_url($provider, $config_preset, $day_start_ts, $epg_id);
                if (empty($epg_url)) {
                    hd_debug_print("EPG url for preset '{$config_preset['name']}' is not generated");
                    continue;
                }

                hd_debug_print("EPG url: $epg_url");
                hd_debug_print("Try to load EPG ID: '$epg_id' for channel '{$channel_row[COLUMN_CHANNEL_ID]}' ({$channel_row[COLUMN_TITLE]})");

                $epg_cache_file = self::$cache_dir . $provider->get_provider_playlist_id() . "_" . Hashed_Array::hash($epg_url) . ".cache";
                hd_debug_print("Check cache file: $epg_cache_file");
                $now = time();
                if (file_exists($epg_cache_file)) {
                    $mtime = filemtime($epg_cache_file);
                    $cache_expired_at = $mtime + $this->plugin->get_setting(PARAM_EPG_CACHE_TIME, 1) * 3600;
                } else {
                    hd_debug_print("Cache file '$epg_cache_file' not found");
                    $cache_expired_at = 0;
                }

                if ($cache_expired_at > $now) {
                    $all_epg = json_decode(file_get_contents($epg_cache_file), true);
                    hd_debug_print("Loading all entries for EPG ID: '$epg_id' from file cache: $epg_cache_file");
                } else {
                    hd_debug_print("EPG cache $epg_cache_file expired " . ($now - $cache_expired_at) . " sec ago.");
                    safe_unlink($epg_cache_file);

                    if (!empty($channels_info) && !empty($channels_info['channels'])) {
                        // no need to spam server if epg_id not exist in known epg source
                        if (!in_array($epg_id, $channels_info['channels'])) continue;
                    }

                    hd_debug_print("Fetching EPG ID: '$epg_id' from server: $epg_url");
                    if (empty($config_preset[EPG_JSON_PARSER])) {
                        hd_debug_print('No EPG JSON parser preset found!');
                        continue;
                    }

                    $curl_wrapper = $this->plugin->setup_curl();
                    if (isset($config_preset[EPG_JSON_AUTH])) {
                        $opts[CURLOPT_HTTPHEADER] = array($provider->replace_macros($config_preset[EPG_JSON_AUTH]));
                        $curl_wrapper->set_options($opts);
                    }

                    $content = $curl_wrapper->download_content($epg_url, Curl_Wrapper::RET_ARRAY);

                    if (empty($content)) {
                        hd_debug_print('Empty document returned.');
                        continue;
                    }

                    hd_debug_print('Parse EPG Description.');
                    $all_epg = self::parse_epg_json($content, $config_preset);

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

                if (!self::check_epg_range($all_epg, $day_start_ts)) continue;

                if (LogSeverity::$is_debug) {
                    $date_start_l = format_datetime('Y-m-d H:i', $day_start_ts);
                    $date_end_l = format_datetime('Y-m-d H:i', $day_end_ts);
                    hd_debug_print("Fetch entries for from: $date_start_l to: $date_end_l");
                }

                // collect data only for selected day
                foreach ($all_epg as $program_start => $entry) {
                    if ($program_start < $day_start_ts && $entry[PluginTvEpgProgram::end_tm_sec] < $day_start_ts) continue;
                    if ($program_start >= $day_end_ts) break;

                    $day_items[$program_start] = $entry;
                }

                if (!empty($day_items)) break;
            }

            if (empty($day_items)) {
                hd_debug_print('No EPG entries for selected time in available range');
                throw new Exception(TR::load('err_no_epg_in_all_range'));
            }

            $day_items = self::check_epg_intervals($day_items);
        } catch (Exception $ex) {
            $day_epg['error'] = $ex->getMessage();
            $day_items = static::getFakeEpg($channel_row, $day_start_ts, $day_items);
        }

        $day_epg['items'] = $day_items;
        return $day_epg;
    }

    /**
     * @param array $channel_row
     * @return array
     */
    public static function get_epg_ids($channel_row)
    {
        $epg_ids = Default_Dune_Plugin::make_epg_ids($channel_row);

        $ids = array();
        if (isset($selected_preset[EPG_JSON_EPG_MAP])) {
            $ids[] = $epg_ids[$selected_preset[EPG_JSON_EPG_MAP]];
            hd_debug_print("EPG ID map: {$epg_ids[$selected_preset[EPG_JSON_EPG_MAP]]}", true);
        }

        foreach (array('epg_id', ATTR_TVG_ID, ATTR_TVG_NAME, 'name', 'id') as $key) {
            if (!empty($epg_ids[$key])) {
                $ids[] = $epg_ids[$key];
            }
        }

        return array_unique($ids);
    }

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param string $provider_id
     * @param string $epg_id
     * @param array $epg_ids
     * @param array $config_preset
     * @return array|false
     */
    protected function check_and_update_epg_id($provider_id, $epg_id, $epg_ids, $config_preset)
    {
        if ($config_preset[EPG_JSON_PRESET_NAME] !== 'proiptv') {
            return $epg_id;
        }

        if (!empty($config_preset[EPG_JSON_PRESET_ALIAS])) {
            $provider_id = $config_preset[EPG_JSON_PRESET_ALIAS];
        }

        if (!isset(self::$all_channels_info[$provider_id])
            || self::$all_channels_info[$provider_id]->expired < time()
            || empty(self::$all_channels_info[$provider_id])) {
            $curl_wrapper = $this->plugin->setup_curl();
            $channel_info_url = str_replace(MACRO_PROVIDER, $provider_id, $config_preset[EPG_JSON_SOURCE]);
            $channels_info_url = substr($channel_info_url, 0, strlen($channel_info_url) - strlen(basename($channel_info_url))) . 'channels_info.json';
            hd_debug_print("Fetching channels info from server: $channels_info_url");
            $ch_data = $curl_wrapper->download_content($channels_info_url,
                Curl_Wrapper::RET_ARRAY, Curl_Wrapper::USE_ETAG | Curl_Wrapper::CACHE_RESPONSE
            );

            self::$all_channels_info[$provider_id] = new ChannelInfo();

            if (empty($ch_data)) {
                self::$all_channels_info[$provider_id]->expired = PHP_INT_MAX;
                return $epg_id;
            }

            self::$all_channels_info[$provider_id]->info = $ch_data;
            self::$all_channels_info[$provider_id]->expired = time() + 4 * 3600;
        }

        $channels_info = self::$all_channels_info[$provider_id]->info;
        if (!empty($channels_info) && !in_array($epg_id, $channels_info['epg_id'])) {
            // this epg id is not known, try to find it in aliases (lower case)
            // channel info array contains alias as key and mapped epg id as value
            hd_debug_print("EPG ID '$epg_id' not found in known list", true);
            foreach ($epg_ids as $epg_id) {
                $alias = mb_convert_case($epg_id, MB_CASE_LOWER, "UTF-8");
                if (array_key_exists($alias, $channels_info['epg_aliases'])) {
                    // EPG not found by EPG ID and channel name
                    $epg_id_subst = $channels_info['epg_aliases'][$alias];
                    hd_debug_print("EPG ID for '$alias' found in known list: $epg_id_subst", true);
                    break;
                }
            }

            if (empty($epg_id_subst)) {
                hd_debug_print("No EPG id found in known aliases list", true);
                $epg_id = '';
            } else {
                $epg_id = $epg_id_subst;
            }
        }

        return $epg_id;
    }

    /**
     * parse json epg response
     * @param array $ch_data
     * @param array $preset
     * @return array
     */
    protected static function parse_epg_json($ch_data, $preset)
    {
        $channel_epg = array();
        $parser_params = $preset[EPG_JSON_PARSER];
        hd_debug_print('parser params: ' . json_format_unescaped($parser_params), true);

        $param_epg_root = safe_get_value($parser_params, self::EPG_ROOT);
        $param_epg_start = safe_get_value($parser_params, self::EPG_START);
        $param_epg_end = safe_get_value($parser_params, self::EPG_END);
        $param_epg_name = safe_get_value($parser_params, self::EPG_NAME);
        $param_epg_desc = safe_get_value($parser_params, self::EPG_DESC);
        $param_epg_icon = safe_get_value($parser_params, self::EPG_ICON);
        $param_epg_time_format = safe_get_value($parser_params, self::EPG_TIME_FORMAT);
        $param_epg_timezone = safe_get_value($parser_params, self::EPG_TIMEZONE, 0);

        hd_debug_print("json epg root:    $param_epg_root", true);
        hd_debug_print("json start:       $param_epg_start", true);
        hd_debug_print("json end:         $param_epg_end", true);
        hd_debug_print("json title:       $param_epg_name", true);
        hd_debug_print("json desc:        $param_epg_desc", true);
        hd_debug_print("json icon:        $param_epg_icon", true);
        hd_debug_print("json time format: $param_epg_time_format", true);
        hd_debug_print("json timezone:    $param_epg_timezone", true);

        if (!empty($param_epg_root)) {
            foreach (explode('|', $param_epg_root) as $level) {
                $epg_root = trim($level, "[]");
                $ch_data = $ch_data[$epg_root];
            }
        }

        $update_value = function (&$values, $v_name, &$entry, $e_name, $unescape = false, $default = '') {
            if (!isset($entry[$e_name])) {
                $values[$v_name] = $default;
            } else {
                $values[$v_name] = $unescape ? unescape_entity_string($entry[$e_name]) : $entry[$e_name];
                unset($entry[$e_name]);
            }
        };

        // collect all program that starts after day start and before day end
        $prev_start = -1;
        foreach ($ch_data as $entry) {
            if (!isset($entry[$param_epg_start])) {
                continue;
            }
            $program_start = (int)$entry[$param_epg_start];
            unset($entry[$param_epg_start]);

            if (!empty($param_epg_end) && isset($entry[$param_epg_end])) {
                $program_end = (int)$entry[$param_epg_end];
                unset($entry[$param_epg_end]);
            } else {
                $program_end = -1;
            }

            if (!empty($param_epg_time_format)) {
                $time_format = str_replace(
                    array(MACRO_YEAR, MACRO_MONTH, MACRO_DAY, MACRO_HOUR, MACRO_MIN),
                    array('Y', 'm', 'd', 'H', 'i'),
                    $parser_params[self::EPG_TIME_FORMAT]);

                $start = date_parse_from_format($time_format, $program_start);
                $program_start = gmmktime($start['hour'], $start['minute'], $start['second'], $start['month'], $start['day'], $start['year']);

                if ($program_end !== -1) {
                    $end = date_parse_from_format($time_format, $program_end);
                    $program_end = gmmktime($end['hour'], $end['minute'], $end['second'], $end['month'], $end['day'], $end['year']);
                }
            }

            if ($param_epg_timezone !== 0) {
                $program_start -= $param_epg_timezone * 3600;
            }

            $values = array();
            if ($program_end === -1) {
                if ($prev_start !== -1) {
                    $channel_epg[$prev_start][PluginTvEpgProgram::end_tm_sec] = $program_start;
                }
                $prev_start = $program_start;
            } else {
                $values[PluginTvEpgProgram::end_tm_sec] = $program_end;
            }

            $update_value($values, PluginTvEpgProgram::name, $entry, $param_epg_name, true, 'no name');
            $update_value($values, PluginTvEpgProgram::description, $entry, $param_epg_desc, true, 'no name');
            $update_value($values, PluginTvEpgProgram::icon_url, $entry, $param_epg_icon);
            if (self::$ext_epg_enabled) {
                $reformatted = self::reformat_description($values[PluginTvEpgProgram::description], $values[PluginTvEpgProgram::icon_url]);
                foreach ($reformatted as $key => $value) {
                    $values[$key] = $value;
                }
            }

            if (!empty($entry)) {
                foreach ($entry as $key => $value) {
                    if (isset($values[$key])) {
                        $values[$key] .= ',' . $value;
                    } else {
                        $values[$key] = $value;
                    }
                }
            }
            $channel_epg[$program_start] = $values;
        }

        if ($prev_start !== -1) {
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

        self::$all_channels_info = array();
        $files = self::$cache_dir . (empty($hash) ? '*.cache' : "$hash*.cache");
        hd_debug_print("clear cache files: $files");
        array_map('unlink', glob($files));
        clearstatcache();
    }
}
