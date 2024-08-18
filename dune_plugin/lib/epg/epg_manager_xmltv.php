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
    const PARSER_CONFIG_NAME = 'parse_config.json';
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
     * @return bool
     */
    public function init_by_config()
    {
        global $LOG_FILE;

        $config_file = get_temp_path(self::PARSER_CONFIG_NAME);
        if (!file_exists($config_file)) {
            HD::set_last_error("xmltv_last_error", "Config file for indexing not exist");
            return false;
        }

        $config = json_decode(file_get_contents($config_file));
        if ($config === false) {
            HD::set_last_error("xmltv_last_error", "Invalid config file for indexing");
            @unlink($config_file);
            return false;
        }

        $LOG_FILE = $config->log_file;
        if (!empty($config->log_file)) {
            if (file_exists($config->log_file)) {
                @unlink($config->log_file);
            }
            date_default_timezone_set('UTC');
        }

        set_debug_log($config->debug);

        hd_print("Script start");
        hd_print("Log: $config->log_file");
        hd_print("XMLTV source: $config->xmltv_url");
        hd_print("Cache TTL: $config->cache_ttl");

        $this->init_indexer($config->cache_dir, $config->xmltv_url);
        $this->indexer->set_cache_ttl($config->cache_ttl);
        $this->indexer->set_cache_type($config->cache_type);

        return true;
    }

    /**
     * @param string $cache_dir
     * @param string $url
     */
    public function init_indexer($cache_dir, $url)
    {
        if (class_exists('SQLite3')) {
            $this->indexer = new Epg_Indexer_Sql();
        } else {
            $this->indexer = new Epg_Indexer_Classic();
        }

        $this->indexer->init($cache_dir, $url);

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
        $t = microtime(true);

        $lock = $this->indexer->is_index_locked();
        hd_debug_print("index is locked: " . var_export($lock, true));
        if ($lock) {
            hd_debug_print("EPG still indexing");
            $this->delayed_epg[] = $channel->get_id();
            return array($day_start_ts => array(
                Epg_Params::EPG_END => $day_start_ts + 86400,
                Epg_Params::EPG_NAME => TR::load_string('epg_not_ready'),
                Epg_Params::EPG_DESC => TR::load_string('epg_not_ready_desc'),
            ));
        }

        // filter out epg only for selected day
        $day_epg = array();
        $day_end_ts = $day_start_ts + 86400;
        $date_start_l = format_datetime("Y-m-d H:i", $day_start_ts);
        $date_end_l = format_datetime("Y-m-d H:i", $day_end_ts);
        hd_debug_print("Fetch entries for from: $date_start_l ($day_start_ts) to: $date_end_l ($day_end_ts)", true);

        $xml_str = '';
        try {
            $positions = $this->indexer->load_program_index($channel);
            if (!empty($positions)) {
                $t = microtime(true);
                $cached_file = $this->indexer->get_cached_filename();
                if (!file_exists($cached_file)) {
                    throw new Exception("cache file $cached_file not exist");
                }

                $handle = fopen($cached_file, 'rb');
                if ($handle) {
                    foreach ($positions as $pos) {
                        fseek($handle, $pos['start']);

                        $xml_str = "<tv>" . fread($handle, $pos['end'] - $pos['start']) . "</tv>";

                        $xml_node = new DOMDocument();
                        $xml_node->loadXML($xml_str);
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
                }
                hd_debug_print("Fetch data from XMLTV cache in: " . (microtime(true) - $t) . " secs");
            }
        } catch (Exception $ex) {
            hd_debug_print("Exception in line: $xml_str");
            print_backtrace_exception($ex);
        }

        if (empty($day_epg)) {
            return $this->getFakeEpg($channel, $day_start_ts, $day_epg);
        }

        hd_debug_print("Total EPG entries loaded: " . count($day_epg));
        ksort($day_epg);
        hd_debug_print("Entries collected in: " . (microtime(true) - $t) . " secs");

        return $day_epg;
    }

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

    /**
     * Import indexing log to plugin logs
     *
     * @return void
     */
    public function import_indexing_log()
    {
        $index_log = $this->indexer->get_cache_stem('.log');
        if (file_exists($index_log)) {
            hd_debug_print("Read epg indexing log $index_log...");
            hd_debug_print_separator();
            $logfile = @file_get_contents($index_log);
            foreach (explode(PHP_EOL, $logfile) as $l) {
                hd_print(preg_replace("|^\[.+\]\s(.*)$|", "$1", rtrim($l)));
            }
            hd_debug_print_separator();
            hd_debug_print("Read finished");
            unlink($index_log);
        } else {
            hd_debug_print("Log to import $index_log not exist");
        }
    }

    /**
     * Start indexing in background and return immediately
     *
     * @return void
     */
    public function start_bg_indexing()
    {
        if (is_null($this->plugin)) {
            hd_debug_print("plugin not set");
            return;
        }

        $res = $this->indexer->is_xmltv_cache_valid();
        if ($res === -1) {
            hd_debug_print("XMLTV not set or problem with download");
            return;
        }

        if ($res === 0) {
            hd_debug_print("Indexing not required");
            return;
        }

        $config = array(
            'debug' => LogSeverity::$is_debug,
            'log_file' => $this->indexer->get_cache_stem('.log'),
            'cache_dir' => $this->plugin->get_cache_dir(),
            'cache_ttl' => $this->plugin->get_setting(PARAM_EPG_CACHE_TTL, 3),
            'cache_type' => $this->plugin->get_setting(PARAM_EPG_CACHE_TYPE, XMLTV_CACHE_AUTO),
            'xmltv_url' => $this->plugin->get_active_xmltv_source(),
        );

        file_put_contents(get_temp_path(self::PARSER_CONFIG_NAME), json_encode($config));

        $cmd = get_install_path('bin/cgi_wrapper.sh') . " 'index_epg.php' &";
        hd_debug_print("exec: $cmd", true);
        exec($cmd);
        sleep(1);
    }

    public function index_all()
    {
        $start = microtime(true);

        $this->indexer->index_only_channels();
        $this->indexer->index_xmltv_positions();

        hd_print("Script execution time: " . format_duration(round(1000 * (microtime(true) - $start))));
    }

    /**
     * clear memory cache and cache for current xmltv source
     *
     * @return void
     */
    public function clear_epg_cache()
    {
        $this->get_indexer()->clear_current_epg_files();
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

    ///////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     */
    public function clear_delayed_epg()
    {
        $this->delayed_epg = array();
    }
}
