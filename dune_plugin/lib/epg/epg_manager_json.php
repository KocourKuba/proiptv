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
    /**
     * contains current dune IP
     * @var string
     */
    protected $dune_ip;

    /**
     * contains memory epg cache
     * @var array
     */
    protected $epg_cache = array();

    /**
     * @inheritDoc
     * @override
     */
    public function get_day_epg_items(Channel $channel, $day_start_ts)
    {
        $day_epg = array();
        $epg_ids = $channel->get_epg_ids();

        try {
            $provider = $this->plugin->get_current_provider();
            if (is_null($provider)) {
                return null;
            }

            $all_presets = $this->plugin->get_epg_presets();
            $presets = $provider->getConfigValue(EPG_JSON_PRESETS);
            if (empty($presets)) {
                hd_debug_print("No preset for selected provider");
                return null;
            }

            $preset_idx = $this->plugin->get_setting(PARAM_EPG_JSON_PRESET, 0);
            if (!isset($presets[$preset_idx])) {
                hd_debug_print("Index $preset_idx not exist in provider preset list");
                return null;
            }
            $selected_preset = $presets[$preset_idx];
            hd_debug_print("selected preset: {$selected_preset[EPG_JSON_PRESET_NAME]}", true);
            $preset = $all_presets->get($selected_preset[EPG_JSON_PRESET_NAME]);
            if (empty($preset)) {
                hd_debug_print("{$selected_preset[EPG_JSON_PRESET_NAME]} not exist in plugin configuration");
                return null;
            }

            hd_debug_print("Preset json url: {$preset[EPG_JSON_SOURCE]}", true);
            $alias = empty($selected_preset[EPG_JSON_PRESET_ALIAS]) ? $provider->getId() : $selected_preset[EPG_JSON_PRESET_ALIAS];
            $epg_url = str_replace(array(MACRO_API, MACRO_PROVIDER), array($provider->getApiUrl(), $alias), $preset[EPG_JSON_SOURCE]);
            $epg_url = $provider->replace_macros($epg_url);

            $epg_url = str_replace(MACRO_TIMESTAMP, $day_start_ts, $epg_url);

            if (empty($epg_url)) {
                throw new Exception("No EPG url defined for current provider");
            }

            if (strpos($epg_url, MACRO_ID) !== false) {
                hd_debug_print("using ID: {$channel->get_id()}", true);
                $epg_url = str_replace(MACRO_ID, $channel->get_id(), $epg_url);
                $epg_ids[ATTR_TVG_ID] = $channel->get_id();
            }

            if (isset($selected_preset[EPG_JSON_EPG_MAP])) {
                $epg_id = $epg_ids[$selected_preset[EPG_JSON_EPG_MAP]];
            } else {
                $epg_id = '';
                $tvg_keys = array(ATTR_TVG_ID, ATTR_TVG_NAME, 'name', 'id');
                foreach ($tvg_keys as $key) {
                    if (isset($epg_ids[$key])) {
                        $epg_id = $epg_ids[$key];
                        break;
                    }
                }
            }

            if (empty($epg_id)) {
                throw new Exception("No EPG ID defined");
            }

            if (isset($this->epg_cache[$epg_id][$day_start_ts])) {
                hd_debug_print("Load day EPG ID $epg_id ($day_start_ts) from memory cache ");
                return $this->epg_cache[$epg_id][$day_start_ts];
            }

            $channel_id = $channel->get_id();
            $channel_title = $channel->get_title();

            $cur_time = $day_start_ts + get_local_time_zone_offset();
            $epg_date = gmdate('Y', $cur_time);
            $epg_url = str_replace(MACRO_YEAR, $epg_date, $epg_url);

            $epg_date = gmdate('m', $cur_time);
            $epg_url = str_replace(MACRO_MONTH, $epg_date, $epg_url);

            $epg_date = gmdate('d', $cur_time);
            hd_debug_print("using DAY: $epg_date", true);
            $epg_url = str_replace(MACRO_DAY, $epg_date, $epg_url);

            $epg_id = str_replace(' ', '%20', $epg_id);
            $epg_url = str_replace(array(MACRO_EPG_ID, '#'), array($epg_id, '%23'), $epg_url);
            $epg_cache_file = get_temp_path(Hashed_Array::hash($epg_url) . ".cache");

            hd_debug_print("Try to load EPG ID: '$epg_id' for channel '$channel_id' ($channel_title)");
            $from_cache = false;
            $all_epg = array();
            if (file_exists($epg_cache_file)) {
                $now = time();
                $max_check_time = 3600;
                $cache_expired = filemtime($epg_cache_file) + $max_check_time;
                if ($cache_expired > time()) {
                    $all_epg = parse_json_file($epg_cache_file);
                    $from_cache = true;
                    hd_debug_print("Loading all entries for EPG ID: '$epg_id' from file cache: $epg_cache_file");
                } else {
                    hd_debug_print("Cache expired at $cache_expired now $now");
                    unlink($epg_cache_file);
                }
            }

            if ($from_cache === false) {
                hd_debug_print("Fetching EPG ID: '$epg_id' from server: $epg_url");
                $all_epg = self::get_epg_json($epg_url, $provider, $preset);
                if (!empty($all_epg)) {
                    hd_debug_print("Save EPG ID: '$epg_id' to file cache $epg_cache_file");
                    store_to_json_file($epg_cache_file, $all_epg);
                }
            }

            $counts = count($all_epg);
            if ($counts === 0) {
                throw new Exception("Not EPG entries found");
            }

            hd_debug_print("Total $counts EPG entries loaded");
        } catch (Exception $ex) {
            hd_debug_print($ex->getMessage());
            return $this->getFakeEpg($channel, $day_start_ts, $day_epg);
        }

        // filter out epg only for selected day
        $day_end_ts = $day_start_ts + 86400;

        if (LogSeverity::$is_debug) {
            $date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
            $date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
            hd_debug_print("Fetch entries for from: $date_start_l to: $date_end_l");
        }

        foreach ($all_epg as $time_start => $entry) {
            if ($time_start >= $day_start_ts && $time_start < $day_end_ts) {
                $day_epg[$time_start] = $entry;
            }
        }

        if (!empty($day_epg)) {
            hd_debug_print("Store day epg to memory cache");
            $this->epg_cache[$epg_id][$day_start_ts] = $day_epg;
        }

        return $day_epg;
    }

    /**
     * @inheritDoc
     * @override
     */
    public function clear_current_epg_cache()
    {
        hd_debug_print(null, true);
        $this->epg_cache = array();
        $files = get_temp_path('*.cache');
        hd_debug_print("clear cache files: $files");
        shell_exec('rm -f ' . $files);
        clearstatcache();
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
        hd_debug_print("parser params: " . json_encode($parser_params), true);

        try {
            $opts = null;
            if (isset($preset[EPG_JSON_AUTH])) {
                $opts[CURLOPT_HTTPHEADER] = array($provider->replace_macros($preset[EPG_JSON_AUTH]));
            }
            $ch_data = HD::DownloadJson($url, true, $opts);
            if (empty($ch_data)) {
                hd_debug_print("Empty document returned.");
                return $channel_epg;
            }
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            return $channel_epg;
        }

        if (!empty($parser_params[Epg_Params::EPG_ROOT])) {
            foreach (explode('|', $parser_params[Epg_Params::EPG_ROOT]) as $level) {
                $epg_root = trim($level, "[]");
                $ch_data = $ch_data[$epg_root];
            }
        }

        // Possible need to add this to setup
        // disabling end can help problem with overlapping end/start EPG

        hd_debug_print("json epg root: " . $parser_params[Epg_Params::EPG_ROOT], true);
        hd_debug_print("json start: " . $parser_params[Epg_Params::EPG_START], true);
        hd_debug_print("json title: " . $parser_params[Epg_Params::EPG_NAME], true);
        hd_debug_print("json desc: " . $parser_params[Epg_Params::EPG_DESC], true);
        if (isset($parser_params[Epg_Params::EPG_ICON])) {
            hd_debug_print("json icon: " . $parser_params[Epg_Params::EPG_ICON], true);
        }

        // collect all program that starts after day start and before day end
        $prev_start = 0;
        foreach ($ch_data as $entry) {
            if (!isset($entry[$parser_params[Epg_Params::EPG_START]])) continue;

            $program_start = $entry[$parser_params[Epg_Params::EPG_START]];

            if ($prev_start !== 0) {
                $channel_epg[$prev_start][Epg_Params::EPG_END] = $program_start;
            }
            $prev_start = $program_start;

            if (isset($entry[$parser_params[Epg_Params::EPG_NAME]])) {
                $channel_epg[$program_start][Epg_Params::EPG_NAME] = HD::unescape_entity_string($entry[$parser_params[Epg_Params::EPG_NAME]]);
            } else {
                $channel_epg[$program_start][Epg_Params::EPG_NAME] = '';
            }

            if (isset($entry[$parser_params[Epg_Params::EPG_DESC]])) {
                $desc = HD::unescape_entity_string($entry[$parser_params[Epg_Params::EPG_DESC]]);
                $desc = str_replace('<br>', PHP_EOL, $desc);
                $channel_epg[$program_start][Epg_Params::EPG_DESC] = $desc;
            } else {
                $channel_epg[$program_start][Epg_Params::EPG_DESC] = '';
            }

            if (isset($parser_params[Epg_Params::EPG_ICON], $entry[$parser_params[Epg_Params::EPG_ICON]])) {
                $channel_epg[$program_start][Epg_Params::EPG_ICON] = $entry[$parser_params[Epg_Params::EPG_ICON]];
            } else {
                $channel_epg[$program_start][Epg_Params::EPG_ICON] = '';
            }
        }

        if ($prev_start !== 0) {
            $channel_epg[$prev_start][Epg_Params::EPG_END] = $prev_start + 3600; // fake end
        }

        ksort($channel_epg, SORT_NUMERIC);
        return $channel_epg;
    }
}
