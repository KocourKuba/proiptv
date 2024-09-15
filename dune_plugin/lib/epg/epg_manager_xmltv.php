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
     * @var Epg_Indexer
     */
    protected $indexer;

    /**
     * @param Default_Dune_Plugin|null $plugin
     */
    public function __construct($plugin = null)
    {
        $this->plugin = $plugin;
    }

    /**
     * Function to parse xmltv source in soparate process
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

        $config = json_decode(file_get_contents($config_file));
        @unlink($config_file);
        if ($config === false) {
            HD::set_last_error("xmltv_last_error", "Invalid config file for indexing");
            return false;
        }

        if (empty($config->xmltv_urls)) {
            return false;
        }

        $sources = Hashed_Array::from_array($config->xmltv_urls);
        $LOG_FILE = get_temp_path($sources->key() . "_indexing.log");
        if (file_exists($LOG_FILE)) {
            @unlink($LOG_FILE);
        }
        date_default_timezone_set('UTC');

        set_debug_log($config->debug);

        hd_print("Script config");
        hd_print("Log: $LOG_FILE");
        hd_print("XMLTV sources: " . json_encode($config->xmltv_urls));
        hd_print("Cache type: $config->cache_type");
        hd_print("Cache TTL: $config->cache_ttl");
        hd_print("Process ID:");

        $this->init_indexer($config->cache_dir);
        $this->indexer->set_pid(getmypid());
        $this->indexer->set_active_sources($sources);
        $this->indexer->set_cache_type($config->cache_type);
        $this->indexer->set_cache_ttl($config->cache_ttl);
        $this->indexer->index_all();

        return true;
    }

    /**
     * @param string $cache_dir
     */
    public function init_indexer($cache_dir)
    {
        if (class_exists('SQLite3')) {
            $this->indexer = new Epg_Indexer_Sql();
        } else {
            $this->indexer = new Epg_Indexer_Classic();
        }

        $this->indexer->init($cache_dir);
        if ($this->plugin) {
            $flags = 0;
            $flags |= $this->plugin->get_bool_parameter(PARAM_FAKE_EPG, false) ? EPG_FAKE_EPG : 0;
            $this->set_flags($flags);
            $this->indexer->set_cache_ttl($this->plugin->get_setting(PARAM_EPG_CACHE_TTL, 3));
            $this->indexer->set_cache_type($this->plugin->get_setting(PARAM_EPG_CACHE_TYPE, XMLTV_CACHE_AUTO));
        }
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
        $any_lock = $this->indexer->is_any_index_locked();
        $day_epg = array();
        $active_sources = $this->plugin->get_active_xmltv_sources();
        foreach($active_sources as $key => $source) {
            if ($this->indexer->is_index_locked($key)) {
                hd_debug_print("EPG $source still indexing, append to delayed queue channel id: " . $channel->get_id());
                $this->delayed_epg[] = $channel->get_id();
                continue;
            }

            $this->indexer->set_url($source);
            // filter out epg only for selected day
            $day_end_ts = $day_start_ts + 86400;
            $date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
            $date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
            hd_debug_print("Fetch entries for from: $date_start_l ($day_start_ts) to: $date_end_l ($day_end_ts)", true);

            try {
                $positions = $this->indexer->load_program_index($channel);
                if (!empty($positions)) {
                    $cached_file = $this->indexer->get_cached_filename();
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
                                if ($program_start < $day_start_ts) continue;
                                if ($program_start >= $day_end_ts) break;

                                $day_epg[$program_start][Epg_Params::EPG_END] = strtotime($tag->getAttribute('stop'));

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
     * Import indexing log to plugin logs
     *
     * @param array|null $sources
     * @return bool true if import successful and no other active locks, false if any active source is locked
     */
    public function import_indexing_log($sources = null)
    {
        $has_locks = false;
        if (is_null($sources)) {
            $sources = $this->indexer->get_active_sources()->get_order();
        }

        foreach ($sources as $source) {
            if ($this->indexer->is_index_locked($source)) {
                $has_locks = true;
                continue;
            }

            $index_log = get_temp_path("{$source}_indexing.log");
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
        $this->indexer->clear_current_epg_files();
    }

    /**
     * clear memory cache and cache for selected filename (hash) mask
     * if hash is empty clear all cache
     *
     * @param string $hash
     * @return void
     */
    public function clear_selected_epg_cache($hash)
    {
        $this->indexer->clear_epg_files($hash);
    }

    /**
     * @return Epg_Indexer
     */
    public function get_indexer()
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
