<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
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

require_once 'tr.php';
require_once 'mediaurl.php';
require_once 'user_input_handler_registry.php';
require_once 'action_factory.php';
require_once 'control_factory.php';
require_once 'control_factory_ext.php';
require_once 'catchup_params.php';
require_once 'epg_manager.php';
require_once 'm3u/M3uParser.php';

class Default_Dune_Plugin implements DunePlugin
{
    const AUTHOR_LOGO = "ProIPTV by sharky72  [ ´¯¤¤¯(ºº)¯¤¤¯` ]";
    const SANDWICH_BASE = 'gui_skin://special_icons/sandwich_base.aai';
    const SANDWICH_MASK = 'cut_icon://{name=sandwich_mask}';
    const SANDWICH_COVER = 'cut_icon://{name=sandwich_cover}';

    /////////////////////////////////////////////////////////////////////////////
    // views variables
    const TV_SANDWICH_WIDTH = 245;
    const TV_SANDWICH_HEIGHT = 140;

    /**
     * @var array
     */
    public $plugin_info;

    /**
     * @var M3uParser
     */
    public $m3u_parser;

    /**
     * @var Epg_Manager
     */
    public $epg_man;

    /**
     * @var Starnet_Tv
     */
    public $tv;

    /**
     * @var Playback_Points
     */
    public $playback_points;

    /**
     * @var array
     */
    protected $screens;

    /**
     * @var array
     */
    protected $screens_views;

    /**
     * @var string
     */
    protected $last_error;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var array
     */
    protected $postpone_save;

    /**
     * @var array
     */
    protected $is_durty;

    /**
     * @var bool
     */
    protected $need_update_epfs = false;

    ///////////////////////////////////////////////////////////////////////

    protected function __construct()
    {
        $this->load(PLUGIN_PARAMETERS, true);

        $this->postpone_save = array(PLUGIN_PARAMETERS => false, PLUGIN_SETTINGS => false);
        $this->is_durty = array(PLUGIN_PARAMETERS => false, PLUGIN_SETTINGS => false);

        $this->plugin_info = get_plugin_manifest_info();
        $this->m3u_parser = new M3uParser();
        $this->epg_man = new Epg_Manager($this);
        $debug = $this->get_parameter(PARAM_ENABLE_DEBUG, SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on;
        set_log_level($debug ? LOG_LEVEL_DEBUG: LOG_LEVEL_INFO);

        $this->create_screen_views();
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Screen support.
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * @param $object
     * @return void
     */
    public function create_screen($object)
    {
        if (!is_null($object) && method_exists($object, 'get_id')) {
            //'' . get_class($object));
            $this->add_screen($object);
            User_Input_Handler_Registry::get_instance()->register_handler($object);
        } else {
            hd_debug_print(get_class($object) . ": Screen class is illegal. get_id method not defined!", LOG_LEVEL_ERROR);
        }
    }

    /**
     * @return void
     */
    public function invalidate_epfs()
    {
        $this->need_update_epfs = true;
    }

    /**
     * @param $plugin_cookies
     * @param array|null $media_urls
     * @param null $post_action
     * @return array
     */
    public function update_epfs_data($plugin_cookies, $media_urls = null, $post_action = null)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        if ($this->need_update_epfs) {
            $this->save();
            $this->need_update_epfs = false;
            Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
        }
        return Starnet_Epfs_Handler::invalidate_folders($media_urls, $post_action);
    }

    /**
     * @param Screen $scr
     * @return void
     */
    protected function add_screen(Screen $scr)
    {
        if (isset($this->screens[$scr->get_id()])) {
            hd_debug_print("Error: screen (id: " . $scr->get_id() . ") already registered.", LOG_LEVEL_WARN);
        } else {
            $this->screens[$scr->get_id()] = $scr;
        }
    }

    /**
     * @return array
     */
    public function get_screens()
    {
        return $this->screens;
    }

    /**
     * @param string $screen_id
     * @return Screen
     * @throws Exception
     */
    protected function get_screen_by_id($screen_id)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        if (isset($this->screens[$screen_id])) {
            hd_debug_print("'$screen_id'", LOG_LEVEL_DEBUG);
            return $this->screens[$screen_id];
        }

        hd_debug_print("Error: no screen with id '$screen_id' found.", LOG_LEVEL_ERROR);
        HD::print_backtrace();
        throw new Exception('Screen not found');
    }

    /**
     * @param MediaURL $media_url
     * @return Screen
     * @throws Exception
     */
    protected function get_screen_by_url(MediaURL $media_url)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        $screen_id = isset($media_url->screen_id) ? $media_url->screen_id : $media_url->get_raw_string();

        return $this->get_screen_by_id($screen_id);
    }

    ///////////////////////////////////////////////////////////////////////////
    //
    // DunePlugin implementations
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        return User_Input_Handler_Registry::get_instance()->handle_user_input($user_input, $plugin_cookies);
    }

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_folder_view($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        $decoded_media_url = MediaURL::decode($media_url);
        return $this->get_screen_by_url($decoded_media_url)->get_folder_view($decoded_media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_next_folder_view($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        $decoded_media_url = MediaURL::decode($media_url);

        return $this->get_screen_by_url($decoded_media_url)->get_next_folder_view($decoded_media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param int $from_ndx
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_regular_folder_items($media_url, $from_ndx, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        $decoded_media_url = MediaURL::decode($media_url);

        return $this->get_screen_by_url($decoded_media_url)->get_folder_range($decoded_media_url, $from_ndx, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_tv_info($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        if (is_null($this->tv)) {
            hd_debug_print("TV is not supported", LOG_LEVEL_ERROR);
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        $decoded_media_url = MediaURL::decode($media_url);

        return $this->tv->get_tv_info($decoded_media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return string
     * @throws Exception
     */
    public function get_tv_stream_url($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        if (is_null($this->tv)) {
            hd_debug_print("TV is not supported", LOG_LEVEL_ERROR);
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $media_url;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $channel_id
     * @param int $archive_tm_sec
     * @param string $protect_code
     * @param $plugin_cookies
     * @return string
     * @throws Exception
     */
    public function get_tv_playback_url($channel_id, $archive_tm_sec, $protect_code, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        if (is_null($this->tv)) {
            hd_debug_print("TV is not supported", LOG_LEVEL_ERROR);
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        return $this->tv->get_tv_playback_url($channel_id, $archive_tm_sec, $protect_code, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $channel_id
     * @param int $day_start_tm_sec
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_day_epg($channel_id, $day_start_tm_sec, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        if (is_null($this->tv)) {
            hd_debug_print("TV is not supported", LOG_LEVEL_ERROR);
            HD::print_backtrace();
            throw new Exception('TV is not supported');
        }

        try {
            // get channel by hash
            $channel = $this->tv->get_channel($channel_id);
        } catch (Exception $ex) {
            hd_debug_print("Can't get channel with ID: $channel_id", LOG_LEVEL_WARN);
            return array();
        }

        $day_epg = array();
        // correct day start to local timezone
        $day_start_tm_sec -= get_local_time_zone_offset();

        hd_debug_print("day_start timestamp: $day_start_tm_sec (" . format_datetime("Y-m-d H:i", $day_start_tm_sec) . ")", LOG_LEVEL_DEBUG);
        $day_epg_items = $this->epg_man->get_day_epg_items($channel, $day_start_tm_sec);
        if ($day_epg_items !== false) {
            // get personal time shift for channel
            $time_shift = 3600 * ($channel->get_timeshift_hours() + (isset($plugin_cookies->epg_shift) ? $plugin_cookies->epg_shift : 0));
            hd_debug_print("EPG time shift $time_shift", LOG_LEVEL_DEBUG);
            foreach ($day_epg_items as $time => $value) {
                $tm_start = (int)$time + $time_shift;
                $tm_end = (int)$value[Epg_Params::EPG_END] + $time_shift;
                $day_epg[] = array
                (
                    PluginTvEpgProgram::start_tm_sec => $tm_start,
                    PluginTvEpgProgram::end_tm_sec => $tm_end,
                    PluginTvEpgProgram::name => $value[Epg_Params::EPG_NAME],
                    PluginTvEpgProgram::description => $value[Epg_Params::EPG_DESC],
                );

                hd_debug_print(format_datetime("m-d H:i", $tm_start) . " - " . format_datetime("m-d H:i", $tm_end) . " {$value[Epg_Params::EPG_NAME]}", LOG_LEVEL_DEBUG);
            }
        }

        return $day_epg;
    }

    /**
     * @override DunePlugin
     * @param $channel_id
     * @param $program_ts
     * @param $plugin_cookies
     * @return mixed|null
     * @throws Exception
     */
    public function get_program_info($channel_id, $program_ts, $plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        $program_ts = ($program_ts > 0 ? $program_ts : time());
        hd_debug_print("channel ID: $channel_id at time $program_ts " . format_datetime("Y-m-d H:i", $program_ts), LOG_LEVEL_DEBUG);
        $day_epg = $this->get_day_epg($channel_id,
            strtotime(date("Y-m-d", $program_ts)) + get_local_time_zone_offset(),
            $plugin_cookies);

        foreach ($day_epg as $item) {
            if ($program_ts >= $item[PluginTvEpgProgram::start_tm_sec] && $program_ts < $item[PluginTvEpgProgram::end_tm_sec]) {
                return $item;
            }
        }

        hd_debug_print("No entries found for time $program_ts", LOG_LEVEL_WARN);
        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $op_type
     * @param string $channel_id
     * @param $plugin_cookies
     * @return array
     */
    public function change_tv_favorites($op_type, $channel_id, &$plugin_cookies = null)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        if (is_null($this->tv)) {
            hd_debug_print("TV is not supported", LOG_LEVEL_ERROR);
            HD::print_backtrace();
            return array();
        }

        $favorites = $this->get_favorites();
        switch ($op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                if ($favorites->add_item($channel_id)) {
                    hd_debug_print("Add channel $channel_id to favorites", LOG_LEVEL_DEBUG);
                }
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                if ($favorites->remove_item($channel_id)) {
                    hd_debug_print("Remove channel $channel_id from favorites", LOG_LEVEL_DEBUG);
                }
                break;

            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $favorites->arrange_item($channel_id, Ordered_Array::UP);
                break;

            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $favorites->arrange_item($channel_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("Clear favorites", LOG_LEVEL_DEBUG);
                $favorites->clear();
                break;
        }

        $this->invalidate_epfs();

        return Starnet_Epfs_Handler::invalidate_folders(array(
                Starnet_Tv_Favorites_Screen::ID,
                Starnet_Tv_Channel_List_Screen::get_media_url_string(ALL_CHANNEL_GROUP_ID))
        );
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_vod_info($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        hd_debug_print("VOD is not supported", LOG_LEVEL_ERROR);

        HD::print_backtrace();
        throw new Exception("VOD is not supported");
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @override DunePlugin
     * @param string $media_url
     * @param $plugin_cookies
     * @return string
     */
    public function get_vod_stream_url($media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        hd_debug_print("VOD is not supported", LOG_LEVEL_ERROR);

        return '';
    }

    ///////////////////////////////////////////////////////////////////////
    // Settings storage methods
    //

    /**
     * Block or release save settings action
     * If released will perform save action
     *
     * @param bool $snooze
     * @param string $item
     */
    public function set_pospone_save($snooze = true, $item = PLUGIN_SETTINGS)
    {
        $this->postpone_save[$item] = $snooze;
        if (!$snooze)
            $this->save($item);
    }

    /**
     * Is settings contains unsaved changes
     *
     * @return bool
     */
    public function is_durty($item = PLUGIN_SETTINGS)
    {
        return $this->is_durty[$item];
    }

    /**
     * Get settings for selected playlist
     *
     * @param string $type
     * @param mixed|null $default
     * @return mixed
     */
    public function get_setting($type, $default = null)
    {
        $this->load(PLUGIN_SETTINGS);

        if (!isset($this->settings[$type])) {
            $this->settings[$type] = $default;
        } else {
            $default_type = gettype($default);
            $param_type = gettype($this->settings[$type]);
            if ($default_type === 'object' && $param_type !== $default_type) {
                hd_debug_print("Settings type requested: $default_type. But $param_type loaded. Reset to default", LOG_LEVEL_DEBUG);
                $this->settings[$type] = $default;
            }
        }

        return $this->settings[$type];
    }

    /**
     * Set settings for selected playlist
     *
     * @param string $type
     * @param mixed $val
     */
    public function set_setting($type, $val)
    {
        $this->settings[$type] = $val;
        $this->is_durty[PLUGIN_SETTINGS] = true;
        $this->save();
    }

    /**
     * Remove setting for selected playlist
     *
     * @param string $type
     */
    public function remove_setting($type)
    {
        unset($this->settings[$type]);
        $this->save();
    }

    /**
     * Remove settings for selected playlist
     */
    public function remove_settings()
    {
        unset($this->settings);
        $hash = $this->get_playlist_hash();
        hd_debug_print("remove $hash.settings", LOG_LEVEL_DEBUG);
        HD::erase_data_items("$hash.settings");
        foreach (glob_dir(get_cached_image_path(), "/^$hash.*$/i") as $file) {
            unlink($file);
        }
    }

    ///////////////////////////////////////////////////////////////////////
    // Parameters storage methods

    /**
     * load plugin/playlist settings
     *
     * @param string $item
     * @param bool $force
     * @return void
     */
    public function load($item, $force = false)
    {
        if ($force) {
            unset($this->{$item});
        }

        $name = (($item === PLUGIN_SETTINGS) ? $this->get_playlist_hash() : 'common') . '.settings';

        if (!isset($this->{$item})) {
            hd_debug_print("Load: $name", LOG_LEVEL_DEBUG);
            $this->{$item} = HD::get_data_items($name, true, false);
            foreach ($this->{$item} as $key => $param) hd_debug_print("$key => $param", LOG_LEVEL_DEBUG);
        }
    }

    /**
     * save plugin/playlist settings
     *
     * @return void
     */
    public function save($item = PLUGIN_SETTINGS)
    {
        $name = ($item === PLUGIN_SETTINGS ? $this->get_playlist_hash() : 'common') . '.settings';

        if (!isset($this->{$item})) {
            hd_debug_print("this->$item is not set!", LOG_LEVEL_DEBUG);
        } else if (!$this->postpone_save[$item]) {
            HD::put_data_items($name, $this->{$item}, false);
            hd_debug_print("Save: $name:", LOG_LEVEL_DEBUG);
            $this->is_durty[$item] = false;
        }
    }

    /**
     * Get plugin parameters
     * Parameters does not depend on playlists and used globally
     *
     * @param string $type
     * @param mixed|null $default
     * @return mixed
     */
    public function get_parameter($type, $default = null)
    {
        $this->load(PLUGIN_PARAMETERS);

        if (!isset($this->parameters[$type])) {
            hd_debug_print("load default $type", LOG_LEVEL_DEBUG);
            $this->parameters[$type] = $default;
        } else {
            $default_type = gettype($default);
            $param_type = gettype($this->parameters[$type]);
            if ($default_type === 'object' && $param_type !== $default_type) {
                hd_debug_print("Parameter type requested: $default_type. But $param_type loaded. Reset to default", LOG_LEVEL_DEBUG);
                $this->parameters[$type] = $default;
            }
        }

        return $this->parameters[$type];
    }

    /**
     * set plugin parameters
     *
     * @param string $type
     * @param mixed $val
     */
    public function set_parameter($type, $val)
    {
        $this->parameters[$type] = $val;
        $this->is_durty[PLUGIN_PARAMETERS] = true;
        $this->save(PLUGIN_PARAMETERS);
    }

    public function toggle_setting($param, $default)
    {
        $old = $this->get_setting($param, $default);
        $new = ($old === SetupControlSwitchDefs::switch_on)
            ? SetupControlSwitchDefs::switch_off
            : SetupControlSwitchDefs::switch_on;
        $this->set_setting($param, $new);
    }

    public function toggle_parameter($param, $default)
    {
        $old = $this->get_parameter($param, $default);
        $new = ($old === SetupControlSwitchDefs::switch_on)
            ? SetupControlSwitchDefs::switch_off
            : SetupControlSwitchDefs::switch_on;
        $this->set_parameter($param, $new);
    }

    ///////////////////////////////////////////////////////////////////////
    // Methods

    /**
     * Initialize and parse selected playlist
     *
     * @return bool
     */
    public function init_playlist()
    {
        // first check if playlist in cache
        if ($this->get_playlists()->size() === 0)
            return false;

        $this->init_user_agent();

        $force = false;
        $tmp_file = $this->get_playlist_cache();
        if (file_exists($tmp_file)) {
            $mtime = filemtime($tmp_file);
            if (time() - $mtime > 3600) {
                hd_debug_print("Playlist cache expired. Forcing reload");
                $force = true;
            }
        } else {
            $force = true;
        }

        try {
            if ($force !== false) {
                $url = $this->get_playlists()->get_selected_item();
                if (empty($url)) {
                    hd_debug_print("Tv playlist not defined");
                    throw new Exception("Tv playlist not defined");
                }

                hd_debug_print("m3u playlist: $url");
                if (preg_match('|https?://|', $url)) {
                    $contents = HD::http_get_document($url);
                } else {
                    $contents = @file_get_contents($url);
                    if ($contents === false) {
                        throw new Exception("Can't read playlist: $url");
                    }
                }
                file_put_contents($tmp_file, $contents);
            }

            //  Is already parsed?
            $this->m3u_parser->setupParser($tmp_file, $force);
            if ($this->m3u_parser->getEntriesCount() === 0) {
                if (!$this->m3u_parser->parseInMemory()) {
                    $this->set_last_error("Ошибка чтения плейлиста!");
                    throw new Exception("Can't read playlist");
                }

                $count = $this->m3u_parser->getEntriesCount();
                if ($count === 0) {
                    $this->set_last_error("Пустой плейлист!");
                    hd_debug_print($this->last_error);
                    $this->clear_playlist_cache();
                    throw new Exception("Empty playlist");
                }

                hd_debug_print("Total entries loaded from playlist m3u file: $count");
                HD::ShowMemoryUsage();
            }
        } catch (Exception $ex) {
            hd_debug_print("Unable to load tv playlist: " . $ex->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @return void
     */
    public function init_user_agent()
    {
        $user_agent = $this->get_setting(PARAM_USER_AGENT, HD::get_dune_user_agent());
        if ($user_agent !== HD::get_dune_user_agent()) {
            HD::set_dune_user_agent($user_agent);
        }
    }

    /**
     * remove all settings and clear cache when uninstall plugin
     */
    public function uninstall_plugin()
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        $this->epg_man->clear_all_epg_cache();

        if ($this->get_parameter(PARAM_HISTORY_PATH) === get_data_path()) {
            $this->playback_points->clear_points();
        }

        foreach (array_keys($this->settings) as $hash) {
            unset($this->settings[$hash]);
            hd_debug_print("remove $hash.settings", LOG_LEVEL_DEBUG);
            HD::erase_data_items("$hash.settings");
        }

        HD::erase_data_items("common.settings");
    }

    /**
     * @return Ordered_Array
     */
    public function get_playlists()
    {
        return $this->get_parameter(PARAM_PLAYLISTS, new Ordered_Array());
    }

    /**
     * @var Ordered_Array $playlists
     */
    public function set_playlists($playlists)
    {
        $this->set_parameter(PARAM_PLAYLISTS, $playlists);
    }

    /**
     * $param int $idx
     * @return string
     */
    public function get_playlist($idx)
    {
        return $this->get_playlists()->get_item_by_idx($idx);
    }

    /**
     * @return string
     */
    public function get_playlist_hash()
    {
        return Hashed_Array::hash($this->get_playlists()->get_selected_item());
    }

    /**
     * @return string
     */
    public function get_playlist_cache()
    {
        return get_temp_path($this->get_playlist_hash() . "_playlist.m3u8");
    }

    /**
     * Clear downloaded playlist
     * @return void
     */
    public function clear_playlist_cache()
    {
        $tmp_file = $this->get_playlist_cache();
        if (file_exists($tmp_file)) {
            hd_debug_print("remove $tmp_file", LOG_LEVEL_DEBUG);
            copy($tmp_file, $tmp_file . ".m3u");
            unlink($tmp_file);
        }
    }

    /**
     * @return Ordered_Array
     */
    public function get_favorites()
    {
        return $this->tv->get_special_group(FAVORITES_GROUP_ID)->get_items_order();
    }

    public function get_special_groups_count()
    {
        $groups_cnt = 0;
        if (!$this->is_special_groups_disabled(PARAM_SHOW_ALL)) $groups_cnt++;
        if (!$this->is_special_groups_disabled(PARAM_SHOW_FAVORITES)) $groups_cnt++;
        if (!$this->is_special_groups_disabled(PARAM_SHOW_HISTORY)) $groups_cnt++;

        return $groups_cnt;
    }

    public function is_special_groups_disabled($id)
    {
        return $this->get_parameter($id, SetupControlSwitchDefs::switch_on) !== SetupControlSwitchDefs::switch_on;
    }

    /**
     * get external xmltv sources
     *
     * @return Hashed_Array
     */
    public function get_ext_xmltv_sources()
    {
        return $this->get_parameter(PARAM_EXT_XMLTV_SOURCES, new Hashed_Array());
    }

    /**
     * set external xmltv sources
     *
     * @param Hashed_Array $xmltv_sources
     * @return void
     */
    public function set_ext_xmltv_sources($xmltv_sources)
    {
        $this->set_parameter(PARAM_EXT_XMLTV_SOURCES, $xmltv_sources);
    }

    /**
     * get all xmltv source
     *
     * @return Hashed_Array
     */
    public function get_all_xmltv_sources()
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        /** @var Hashed_Array $sources */
        $xmltv_sources = new Hashed_Array();
        foreach ($this->m3u_parser->getXmltvSources() as $source) {
            $xmltv_sources->put($source);
        }
        foreach ($this->get_ext_xmltv_sources() as $key => $source) {
            $xmltv_sources->put($source, $key);
        }

        foreach ($xmltv_sources as $source) {
            hd_debug_print($source, LOG_LEVEL_DEBUG);
        }
        return $xmltv_sources;
    }

    /**
     * @return string
     */
    public function get_active_xmltv_source_key()
    {
        return $this->get_parameter(PARAM_XMLTV_SOURCE_KEY, '');
    }

    /**
     * @param string $key
     * @return void
     */
    public function set_active_xmltv_source_key($key)
    {
        $this->set_parameter(PARAM_XMLTV_SOURCE_KEY, $key);
    }

    /**
     * @return Ordered_Array
     */
    public function get_groups_order()
    {
        return $this->get_setting(PARAM_GROUPS_ORDER, new Ordered_Array());
    }

    /**
     * @return Ordered_Array
     */
    public function get_disabled_groups()
    {
        return $this->get_setting(PARAM_DISABLED_GROUPS, new Ordered_Array());
    }

    /**
     * @param Ordered_Array $groups
     * @return void
     */
    public function set_disabled_groups($groups)
    {
        $this->set_setting(PARAM_DISABLED_GROUPS, $groups);
    }

    /**
     * @return Ordered_Array
     */
    public function get_disabled_channels()
    {
        return $this->get_setting(PARAM_DISABLED_CHANNELS, new Ordered_Array());
    }

    /**
     * @param Ordered_Array $channels
     * @return void
     */
    public function set_disabled_channels($channels)
    {
        $this->set_setting(PARAM_DISABLED_CHANNELS, $channels);
    }

    /**
     * @return Hashed_Array
     */
    public function get_channels_zoom()
    {
        return $this->get_setting(PARAM_CHANNELS_ZOOM, new Hashed_Array());
    }


    /**
     * @return string
     */
    public function get_channel_zoom($channel_id)
    {
        $zoom = $this->get_channels_zoom()->get($channel_id);
        return is_null($zoom) ? DuneVideoZoomPresets::not_set : $zoom;
    }

    /**
     * @param string $channel_id
     * @param string|null $preset
     * @return void
     */
    public function set_channel_zoom($channel_id, $preset)
    {
        $channels_zoom = $this->get_channels_zoom();
        if ($preset === null) {
            $channels_zoom->erase($channel_id);
        } else {
            $channels_zoom->set($channel_id, $preset);
        }

        $this->set_setting(PARAM_CHANNELS_ZOOM, $channels_zoom);
    }

    /**
     * @return Ordered_Array
     */
    public function get_channels_for_ext_player()
    {
        return $this->get_setting(PARAM_CHANNEL_PLAYER, new Ordered_Array());
    }

    /**
     * @return bool
     */
    public function is_channel_for_ext_player($channel_id)
    {
        return $this->get_channels_for_ext_player()->in_order($channel_id);
    }

    /**
     * @param string $channel_id
     * @param bool $external
     * @return void
     */
    public function set_channel_for_ext_player($channel_id, $external)
    {
        $ext_player = $this->get_channels_for_ext_player();

        if ($external) {
            $ext_player->add_item($channel_id);
        } else {
            $ext_player->remove_item($channel_id);
        }

        $this->set_setting(PARAM_CHANNEL_PLAYER, $ext_player);
    }

    /**
     * Generate url from template with macros substitution
     * Make url ts wrapped
     * @param string $channel_id
     * @param int $archive_ts
     * @return string
     * @throws Exception
     */
    public function generate_stream_url($channel_id, $archive_ts = -1)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        $channel = $this->tv->get_channel($channel_id);
        if (is_null($channel)) {
            throw new Exception("Channel with id: $channel_id not found");
        }

        // replace all macros
        if ((int)$archive_ts <= 0) {
            $stream_url = $channel->get_url();
        } else {
            $now = time();
            $replaces[catchup_params::CU_START] = $archive_ts;
            $replaces[catchup_params::CU_UTC] = $archive_ts;
            $replaces[catchup_params::CU_CURRENT_UTC] = $now;
            $replaces[catchup_params::CU_TIMESTAMP] = $now;
            $replaces[catchup_params::CU_END] = $now;
            $replaces[catchup_params::CU_UTCEND] = $now;
            $replaces[catchup_params::CU_OFFSET] = $now - $archive_ts;
            $replaces[catchup_params::CU_DURATION] = 14400;
            $replaces[catchup_params::CU_DURMIN] = 240;
            $replaces[catchup_params::CU_YEAR]  = $replaces[catchup_params::CU_START_YEAR]  = date('Y', $archive_ts);
            $replaces[catchup_params::CU_MONTH] = $replaces[catchup_params::CU_START_MONTH] = date('m', $archive_ts);
            $replaces[catchup_params::CU_DAY]   = $replaces[catchup_params::CU_START_DAY]   = date('d', $archive_ts);
            $replaces[catchup_params::CU_HOUR]  = $replaces[catchup_params::CU_START_HOUR]  = date('H', $archive_ts);
            $replaces[catchup_params::CU_MIN]   = $replaces[catchup_params::CU_START_MIN]   = date('M', $archive_ts);
            $replaces[catchup_params::CU_SEC]   = $replaces[catchup_params::CU_START_SEC]   = date('S', $archive_ts);
            $replaces[catchup_params::CU_END_YEAR]  = date('Y', $now);
            $replaces[catchup_params::CU_END_MONTH] = date('m', $now);
            $replaces[catchup_params::CU_END_DAY]   = date('d', $now);
            $replaces[catchup_params::CU_END_HOUR]  = date('H', $now);
            $replaces[catchup_params::CU_END_MIN]   = date('M', $now);
            $replaces[catchup_params::CU_END_SEC]   = date('S', $now);

            $stream_url = $channel->get_archive_url();
            foreach ($replaces as $key => $value) {
                $stream_url = str_replace($key, $value, $stream_url);
            }
        }

        if (empty($stream_url)) {
            throw new Exception("Empty url!");
        }

        $ext_params = $channel->get_ext_params();
        if (isset($ext_params[PARAM_DUNE_PARAMS])) {
            //hd_debug_print("Additional dune params: $dune_params");

            $dune_params = "";
            foreach ($ext_params[PARAM_DUNE_PARAMS] as $key => $value) {
                if (!empty($dune_params)) {
                    $dune_params .= ",";
                }

                $dune_params .= "$key:$value";
            }

            if (!empty($dune_params)) {
                $stream_url .= "|||dune_params|||$dune_params";
            }
        }

        return HD::make_ts($stream_url);
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Misc.
    //
    ///////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public function get_last_error()
    {
        return $this->last_error;
    }

    /**
     * @param string $error
     */
    public function set_last_error($error)
    {
        $this->last_error = $error;
    }

    /**
     * @param array $defs
     */
    public function create_setup_header(&$defs)
    {
        Control_Factory::add_vgap($defs, -10);
        Control_Factory::add_label($defs, self::AUTHOR_LOGO,
            " v.{$this->plugin_info['app_version']} [{$this->plugin_info['app_release_date']}]",
            20);
    }

    /**
     * @param MediaURL $media_url
     * @param int $archive_ts
     * @throws Exception
     */
    public function player_exec($media_url, $archive_ts = -1)
    {
        $url = $this->generate_stream_url($media_url->channel_id, $archive_ts);

        if (!$this->is_channel_for_ext_player($media_url->channel_id)) {
            return Action_Factory::tv_play($media_url);
        }

        $url = str_replace("ts://", "", $url);
        $param_pos = strpos($url, '|||dune_params');
        $url =  $param_pos!== false ? substr($url, 0, $param_pos) : $url;
        $cmd = 'am start -d "' . $url . '" -t "video/*" -a android.intent.action.VIEW 2>&1';
        hd_debug_print("play movie in the external player: $cmd");
        exec($cmd, $output);
        hd_debug_print("external player exec result code" . HD::ArrayToStr($output));
        return null;
    }

    /**
     * @return string
     */
    public function get_background_image()
    {
        $background = $this->get_setting(PARAM_PLUGIN_BACKGROUND, $this->plugin_info['app_background']);
        if (!file_exists($background)) {
            $background = $this->plugin_info['app_background'];
        }

        return $background;
    }

    /**
     * @param string|null $path
     * @return void
     */
    public function set_background_image($path)
    {
        if (is_null($path) || !file_exists($path)) {
            $this->set_setting(PARAM_PLUGIN_BACKGROUND, $this->plugin_info['app_background']);
        } else {
            $this->set_setting(PARAM_PLUGIN_BACKGROUND, $path);
        }
    }

    ///////////////////////////////////////////////////////////////////////
    //
    // Screen views parameters
    //
    ///////////////////////////////////////////////////////////////////////

    public function get_screen_view($name)
    {
        return isset($this->screens_views[$name]) ? $this->screens_views[$name] : array();
    }

    /**
     * @param User_Input_Handler $handler
     * @param string $action_id
     * @param string $caption
     * @param string $icon
     * @param $add_params array|null
     * @return array
     */
    public function create_menu_item($handler, $action_id, $caption = null, $icon = null, $add_params = null)
    {
        if ($action_id === GuiMenuItemDef::is_separator) {
            return array($action_id => true);
        }

        return User_Input_Handler_Registry::create_popup_item($handler,
            $action_id, $caption, ($icon === null) ? null : get_image_path($icon), $add_params);
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function playlist_menu($handler)
    {
        $menu_items = array();

        $cur = $this->get_playlists()->get_saved_pos();
        foreach ($this->get_playlists() as $key => $playlist) {
            if ($key !== 0 && ($key % 15) === 0)
                $menu_items[] = $this->create_menu_item($handler, GuiMenuItemDef::is_separator);

            if (($pos = strpos($playlist, '?')) !== false) {
                $playlist = substr($playlist, 0, $pos);
            }

            $menu_items[] = $this->create_menu_item($handler,
                ACTION_PLAYLIST_SELECTED,
                HD::string_ellipsis($playlist),
                ($cur !== $key) ? null : "check.png",
                array('list_idx' => $key));
        }

        return $menu_items;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array
     */
    public function epg_source_menu($handler)
    {
        $menu_items = array();

        $sources = $this->get_all_xmltv_sources();
        $source_key = $this->get_active_xmltv_source_key();

        foreach ($sources as $key => $item) {
            $menu_items[] = $this->create_menu_item($handler,
                ACTION_EPG_SOURCE_SELECTED,
                HD::string_ellipsis($item),
                ($source_key === $key) ? "check.png" : null,
                array('list_idx' => $key)
            );
        }

        return $menu_items;
    }

    public function create_screen_views()
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);

        $background = $this->get_background_image();

        $this->screens_views = array(

            // 1x10 title list view with right side icon
            'list_1x10_info' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 10,
                    ViewParams::paint_icon_selection_box=> true,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewParams::help_line_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,
                    ViewParams::background_path=> $background,
                    ViewParams::background_order => 0,
                    ViewParams::item_detailed_info_text_color => 11,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                    ViewParams::zoom_detailed_icon => false,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_width => 70,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::item_caption_width => 1100,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            'list_1x12_info' => array(
                PluginRegularFolderView::async_icon_loading => false,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::item_caption_dx => 30,
                    ViewItemParams::item_caption_width => 1100,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'list_2x12_info' => array(
                PluginRegularFolderView::async_icon_loading => false,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 2,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::item_caption_dx => 74,
                    ViewItemParams::item_caption_width => 550,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'list_3x12_no_info' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_details => false,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::item_caption_dx => 97,
                    ViewItemParams::item_caption_width => 600,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_5x3_caption' => array(
                PluginRegularFolderView::async_icon_loading => false,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 3,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::content_box_padding_left => 70,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.2,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            // 5x4 without title
            'icons_5x4_no_caption' => array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 4,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            // 4x4 without title
            'icons_4x4_no_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 4,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_4x3_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.25,
                    ViewItemParams::icon_sel_scale_factor => 1.5,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            'icons_3x3_no_caption' => array(
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => self::SANDWICH_BASE,
                    ViewParams::sandwich_mask => self::SANDWICH_MASK,
                    ViewParams::sandwich_cover => self::SANDWICH_COVER,
                    ViewParams::sandwich_width => self::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => self::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.25,
                    ViewItemParams::icon_sel_scale_factor => 1.5,
                    ViewItemParams::icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

        );
    }
}
