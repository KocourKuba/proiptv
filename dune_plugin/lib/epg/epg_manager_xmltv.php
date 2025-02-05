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

require_once 'lib/hd.php';
require_once 'lib/hashed_array.php';
require_once 'lib/tr.php';

require_once 'epg_params.php';
require_once 'epg_indexer_classic.php';
require_once 'epg_indexer_sql.php';

class Epg_Manager_Xmltv
{
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
    protected $active_sources;

    /**
     * @var Epg_Indexer
     */
    protected $indexer;

    /**
     * @param Default_Dune_Plugin|null $plugin
     */
    public function __construct($plugin = null)
    {
        $this->plugin = $plugin;
        $this->active_sources = new Hashed_Array();
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
        @unlink($config_file);
        if ($config === false) {
            HD::set_last_error("xmltv_last_error", "Invalid config file for indexing");
            return false;
        }

        if (empty($config[PARAMS_XMLTV])) {
            return false;
        }

        $LOG_FILE = get_temp_path("{$config[PARAMS_XMLTV][PARAM_HASH]}_indexing.log");
        if (file_exists($LOG_FILE)) {
            @unlink($LOG_FILE);
        }

        date_default_timezone_set('UTC');

        set_debug_log($config[PARAM_ENABLE_DEBUG]);

        hd_print("Script config");
        hd_print("Log:         " . $LOG_FILE);
        hd_print("Cache dir:   " . $config[PARAM_CACHE_DIR]);
        hd_print("XMLTV param: " . json_encode($config[PARAMS_XMLTV]));
        hd_print("Process ID:  " . getmypid());

        $this->init_indexer();
        $this->indexer->set_cache_dir($config[PARAM_CACHE_DIR]);
        $this->indexer->set_pid(getmypid());
        $this->indexer->set_url_params($config[PARAMS_XMLTV]);
        $this->indexer->index_only_channels();
        $this->indexer->index_xmltv_positions();

        return true;
    }

    /**
     * Initialize indexer class
     */
    public function init_indexer()
    {
        if (class_exists('SQLite3')) {
            $this->indexer = new Epg_Indexer_Sql();
        } else {
            $this->indexer = new Epg_Indexer_Classic();
        }
    }

    /**
     * Set active sources (Hashed_Array of url params)
     *
     * @param Hashed_Array<array> $sources
     * @return void
     */
    public function set_active_sources($sources)
    {
        if ($sources->size() === 0) {
            hd_debug_print("No XMLTV source selected");
        } else {
            hd_debug_print("XMLTV sources selected: $sources");
        }

        $this->active_sources = $sources;
    }

    /**
     * Get active sources
     *
     * @return Hashed_Array
     */
    public function get_active_sources()
    {
        return $this->active_sources;
    }

    /**
     * @param int $flags
     * @return void
     */
    public function set_flags($flags)
    {
        $this->flags = $flags;
    }

    /**
     * Try to load epg from cached file
     *
     * @param Channel $channel
     * @param int $day_start_ts
     * @return array
     */
    public function get_day_epg_items(Channel $channel, $day_start_ts)
    {
        $any_lock = $this->is_any_index_locked();
        $day_epg = array();

        foreach($this->active_sources as $key => $params) {
            $this->indexer->set_url_params($params);
            if ($this->indexer->is_index_locked($key)) {
                hd_debug_print("EPG {$params[PARAM_URI]} still indexing, append to delayed queue channel id: " . $channel->get_id());
                $this->delayed_epg[] = $channel->get_id();
                continue;
            }

            // filter out epg only for selected day
            $day_end_ts = $day_start_ts + 86400;
            $date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
            $date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
            hd_debug_print("Fetch entries for from: $date_start_l ($day_start_ts) to: $date_end_l ($day_end_ts)", true);

            try {
                $positions = $this->indexer->load_program_index($channel);
                if (!empty($positions)) {
                    $cached_file = $this->plugin->get_cache_dir() . DIRECTORY_SEPARATOR . "{$params[PARAM_HASH]}.xmltv";
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

                                $day_epg[$program_start][Epg_Params::EPG_END] = $program_end;

                                $day_epg[$program_start][Epg_Params::EPG_NAME] = '';
                                foreach ($tag->getElementsByTagName('title') as $tag_title) {
                                    $day_epg[$program_start][Epg_Params::EPG_NAME] = $tag_title->nodeValue;
                                }

                                $day_epg[$program_start][Epg_Params::EPG_DESC] = '';
                                foreach ($tag->getElementsByTagName('desc') as $tag_desc) {
                                    $day_epg[$program_start][Epg_Params::EPG_DESC] = trim($tag_desc->nodeValue);
                                }

                                foreach ($tag->getElementsByTagName('icon') as $tag_icon) {
                                    $day_epg[$program_start][Epg_Params::EPG_ICON] = $tag_icon->getAttribute('src');
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
            if ($this->active_sources->size() === 0) {
                return array($day_start_ts => array(
                    Epg_Params::EPG_END => $day_start_ts + 86400,
                    Epg_Params::EPG_NAME => TR::load_string('epg_no_sources'),
                    Epg_Params::EPG_DESC => TR::load_string('epg_no_sources_desc'),
                ));
            }

            if ($any_lock !== false) {
                $this->delayed_epg = array_unique($this->delayed_epg);
                return array($day_start_ts => array(
                    Epg_Params::EPG_END => $day_start_ts + 86400,
                    Epg_Params::EPG_NAME => TR::load_string('epg_not_ready'),
                    Epg_Params::EPG_DESC => TR::load_string('epg_not_ready_desc'),
                ));
            }
            return $this->getFakeEpg($channel, $day_start_ts, $day_epg);
        }

        ksort($day_epg);

        return $day_epg;
    }

    /**
     * Get picon for channel
     *
     * @param array $aliases
     * @return string
     */
    public function get_picon($aliases)
    {
        return $this->indexer->get_picon($this->active_sources, $aliases);
    }

    /**
     * Check if any locks for active sources
     *
     * @return bool|array
     */
    public function is_any_index_locked()
    {
        $locks = array();
        $dirs = array();
        foreach ($this->active_sources->get_keys() as $key) {
            $dirs = safe_merge_array($dirs, glob($this->plugin->get_cache_dir() . DIRECTORY_SEPARATOR . "{$key}_*.lock", GLOB_ONLYDIR));
        }

        foreach ($dirs as $dir) {
            $locks[] = basename($dir);
        }
        return empty($locks) ? false : $locks;
    }

    /**
     * Import indexing log to plugin logs
     *
     * @param array|null $sources_hash
     * @return bool true if import successful and no other active locks, false if any active source is locked
     */
    public function import_indexing_log($sources_hash = null)
    {
        $has_locks = false;
        if (is_null($sources_hash)) {
            $sources_hash = $this->active_sources->get_keys();
        }

        foreach ($sources_hash as $hash) {
            if ($this->indexer->is_index_locked($hash)) {
                $has_locks = true;
                continue;
            }

            $index_log = get_temp_path("{$hash}_indexing.log");
            if (file_exists($index_log)) {
                hd_debug_print("Read epg indexing log $index_log...");
                hd_debug_print_separator();
                $logfile = @file_get_contents($index_log);
                foreach (explode(PHP_EOL, $logfile) as $l) {
                    hd_print(preg_replace("|^\[.+\]\s(.*)$|", "$1", rtrim($l)));
                }
                hd_debug_print_separator();
                hd_debug_print("Read finished");
                @unlink($index_log);
            }
        }

        return !$has_locks;
    }

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    public function clear_current_epg_cache()
    {
        hd_debug_print(null, true);
        $params = $this->indexer->get_url_params();
        $this->indexer->clear_epg_files($params[PARAM_HASH]);
    }

    /**
     * @return Epg_Indexer
     */
    public function &get_indexer()
    {
        return $this->indexer;
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

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param Channel $channel
     * @param int $day_start_ts
     * @param array $day_epg
     * @return array
     */
    protected function getFakeEpg(Channel $channel, $day_start_ts, $day_epg)
    {
        if (($this->flags & EPG_FAKE_EPG) && $channel->get_archive() !== 0) {
            hd_debug_print("Create fake data for non existing EPG data");
            for ($start = $day_start_ts, $n = 1; $start <= $day_start_ts + 86400; $start += 3600, $n++) {
                $day_epg[$start][Epg_Params::EPG_END] = $start + 3600;
                $day_epg[$start][Epg_Params::EPG_NAME] = TR::load_string('fake_epg_program') . " $n";
                $day_epg[$start][Epg_Params::EPG_DESC] = '';
            }
        } else {
            hd_debug_print("No EPG for channel: {$channel->get_id()}");
        }

        return $day_epg;
    }
}
