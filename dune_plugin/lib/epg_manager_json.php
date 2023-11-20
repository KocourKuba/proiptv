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

require_once 'hd.php';
require_once 'epg_params.php';

class Epg_Manager_Json extends Epg_Manager
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
     */
    public function get_picons()
    {
        return null;
    }

    /**
     * try to load epg from cache otherwise request it from server
     * store parsed response to the cache
     * @param Channel $channel
     * @param int $day_start_ts
     * @return array|false
     */
    public function get_day_epg_items(Channel $channel, $day_start_ts)
    {
        $epg_ids = $channel->get_epg_ids();
        if (empty($epg_ids)) {
            hd_debug_print("EPG ID not defined");
            return false;
        }

        $provider = $this->plugin->get_current_provider();
        if (is_null($provider)) {
            hd_debug_print("Not supported provider");
            return false;
        }

        $preset_name = $provider->getProviderConfigValue('epg_preset');
        if (empty($preset_name)) {
            hd_debug_print("No preset for selected provider");
            return false;
        }

        $preset = $this->plugin->get_epg_preset($preset_name);
        if (empty($preset)) {
            hd_debug_print("Unknown preset: $preset");
            return false;
        }

        $epg_url = $preset['json_source'];
        hd_debug_print("using json_source: $epg_url", true);
        if (strpos($epg_url, '{PROVIDER}') !== false) {
            $epg_alias = $provider->getProviderConfigValue('epg_alias');
            $alias = empty($epg_alias) ? $provider->getId() : $epg_alias;
            hd_debug_print("using alias: $alias", true);
            $epg_url = str_replace('{PROVIDER}', $alias, $epg_url);
        }

        if (strpos($epg_url, '{TOKEN}') !== false) {
            $token = $provider->getCredential(MACRO_TOKEN);
            hd_debug_print("using token: $token", true);
            $epg_url = str_replace('{TOKEN}', $token, $epg_url);
        }

        if (strpos($epg_url, '{ID}') !== false) {
            hd_debug_print("using ID: {$channel->get_id()}", true);
            $epg_url = str_replace('{ID}', $channel->get_id(), $epg_url);
        }

        $day_epg = array();
        $key = 'tvg-id';
        if (!isset($epg_ids[$key])) {
            $key = 'tvg-name';
            if (!isset($epg_ids[$key])) {
                hd_debug_print("No EPG ID defined");
                return false;
            }
        }

        $epg_id = $epg_ids[$key];
        if (isset($this->epg_cache[$epg_id][$day_start_ts])) {
            hd_debug_print("Load day EPG ID $epg_id ($day_start_ts) from memory cache ");
            return $this->epg_cache[$epg_id][$day_start_ts];
        }

        $channel_id = $channel->get_id();
        $channel_title = $channel->get_title();
        hd_debug_print("Try to load EPG ID: '$epg_id' for channel '$channel_id' ($channel_title)");

        $cur_time = $day_start_ts + get_local_time_zone_offset();
        if (strpos($epg_url, '{YEAR}') !== false) {
            $epg_date = gmdate('Y', $cur_time);
            hd_debug_print("using YEAR: $epg_date", true);
            $epg_url = str_replace( '{YEAR}', $epg_date, $epg_url);
        }
        if (strpos($epg_url, '{MONTH}') !== false) {
            $epg_date = gmdate('m', $cur_time);
            hd_debug_print("using MONTH: $epg_date", true);
            $epg_url = str_replace( '{MONTH}', $epg_date, $epg_url);
        }
        if (strpos($epg_url, '{DAY}') !== false) {
            $epg_date = gmdate('d', $cur_time);
            hd_debug_print("using DAY: $epg_date", true);
            $epg_url = str_replace( '{DAY}', $epg_date, $epg_url);
        }

        $epg_id = str_replace(' ', '%20', $epg_id);
        $epg_url = str_replace(array('{EPG_ID}', '#'), array($epg_id, '%23'), $epg_url);
        $hash = hash('crc32', $epg_url);

        $epg_cache_file = $this->get_cache_stem("_$hash.cache");
        $from_cache = false;
        $all_epg = array();
        if (file_exists($epg_cache_file)) {
            $now = time();
            $cache_expired = filemtime($epg_cache_file) + 3600;
            if ($cache_expired > time()) {
                $all_epg = unserialize(file_get_contents($epg_cache_file));
                $from_cache = true;
                hd_debug_print("Loading all entries for EPG ID: '$epg_id' from file cache: $epg_cache_file");
            } else {
                hd_debug_print("Cache expired at $cache_expired now $now");
                unlink($epg_cache_file);
            }
        }

        if ($from_cache === false) {
            hd_debug_print("Fetching EPG ID: '$epg_id' from server");
            $all_epg = self::get_epg_json($epg_url, $preset);
            if (!empty($all_epg)) {
                hd_debug_print("Save EPG ID: '$epg_id' to file cache $epg_cache_file");
                file_put_contents($epg_cache_file, serialize($all_epg));
            }
        }

        $counts = count($all_epg);
        if ($counts !== 0) {
            hd_debug_print("Total $counts EPG entries loaded");

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
                return $day_epg;
            }
        }

        hd_debug_print("No EPG data for " . $channel->get_id());

        if (($this->flags & EPG_FAKE_EPG) && $channel->get_archive() !== 0) {
            hd_debug_print("Create fake data for non existing EPG data");
            for ($start = $day_start_ts, $n = 1; $start <= $day_start_ts + 86400; $start += 3600, $n++) {
                $day_epg[$start][Epg_Params::EPG_END] = $start + 3600;
                $day_epg[$start][Epg_Params::EPG_NAME] = TR::load_string('fake_epg_program') . " $n";
                $day_epg[$start][Epg_Params::EPG_DESC] = '';
            }
        } else {
            hd_debug_print("No EPG for channel");
        }

        return $day_epg;
    }

    /**
     * request server for epg and parse json response
     * @param string $url
     * @param array $parser_params
     * @return array
     */
    protected static function get_epg_json($url, $parser_params)
    {
        $channel_epg = array();
        hd_debug_print("parser params: " . json_encode($parser_params));

        try {
            $ch_data = HD::DownloadJson($url);
            if (empty($ch_data)) {
                hd_debug_print("Empty document returned.");
                return $channel_epg;
            }
        } catch (Exception $ex) {
            hd_debug_print("http exception: " . $ex->getMessage());
            return $channel_epg;
        }

        if (!empty($parser_params[Epg_Params::EPG_ROOT])) {
            foreach (explode('|', $parser_params[Epg_Params::EPG_ROOT]) as $level) {
                $epg_root = trim($level, "[]");
                $ch_data = $ch_data[$epg_root];
            }
        }

        hd_debug_print("json epg root: " . $parser_params[Epg_Params::EPG_ROOT], true);
        hd_debug_print("json start: " . $parser_params[Epg_Params::EPG_START], true);
        hd_debug_print("json end: " . $parser_params[Epg_Params::EPG_END], true);
        hd_debug_print("json title: " . $parser_params[Epg_Params::EPG_NAME], true);
        hd_debug_print("json desc: " . $parser_params[Epg_Params::EPG_DESC], true);

        // collect all program that starts after day start and before day end
        $prev_start = 0;
        $no_end = empty($parser_params[Epg_Params::EPG_END]);
        foreach ($ch_data as $entry) {
            $program_start = $entry[$parser_params[Epg_Params::EPG_START]];
            // prefill data to avoid undefined index notice
            $channel_epg[$program_start][Epg_Params::EPG_END] = 0;
            $channel_epg[$program_start][Epg_Params::EPG_NAME] = '';
            $channel_epg[$program_start][Epg_Params::EPG_DESC] = '';

            if ($no_end) {
                if ($prev_start !== 0) {
                    $channel_epg[$prev_start][Epg_Params::EPG_END] = $program_start;
                }
                $prev_start = $program_start;
            } else {
                $channel_epg[$program_start][Epg_Params::EPG_END] = (int)$entry[$parser_params[Epg_Params::EPG_END]];
            }

            if (isset($entry[$parser_params[Epg_Params::EPG_NAME]])) {
                $channel_epg[$program_start][Epg_Params::EPG_NAME] = HD::unescape_entity_string($entry[$parser_params[Epg_Params::EPG_NAME]]);
            }
            if (isset($entry[$parser_params[Epg_Params::EPG_DESC]])) {
                $desc = HD::unescape_entity_string($entry[$parser_params[Epg_Params::EPG_DESC]]);
                $desc = str_replace('<br>', PHP_EOL, $desc);
                $channel_epg[$program_start][Epg_Params::EPG_DESC] = $desc;
            }
        }

        if ($no_end && $prev_start !== 0) {
            $channel_epg[$prev_start][Epg_Params::EPG_END] = $prev_start + 3600; // fake end
        }

        ksort($channel_epg, SORT_NUMERIC);
        return $channel_epg;
    }
}
