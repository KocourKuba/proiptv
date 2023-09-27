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

require_once 'lib/hashed_array.php';
require_once 'lib/ordered_array.php';
require_once 'lib/default_group.php';
require_once 'lib/default_channel.php';
require_once 'lib/default_epg_item.php';

class Starnet_Tv implements User_Input_Handler
{
    const ID = 'tv';

    const DEFAULT_CHANNEL_ICON_PATH = 'plugin_file://icons/default_channel.png';

    ///////////////////////////////////////////////////////////////////////

    public static $tvg_id = array('tvg-id', 'tvg-name');
    public static $tvg_archive = array('catchup-days', 'catchup-time', 'timeshift', 'arc-timeshift', 'arc-time', 'tvg-rec');

    ///////////////////////////////////////////////////////////////////////

    /**
     * @var Starnet_Plugin
     */
    protected $plugin;

    /**
     * @var int
     */
    protected $playback_runtime;

    /**
     * @var string
     */
    protected $playback_url_is_stream_url;

    /**
     * @template Channel
     * @var Hashed_Array<string, Channel>
     */
    protected $channels;

    /**
     * @template Group
     * @var Hashed_Array<string, Group>
     */
    protected $groups;

    /**
     * @template Group
     * @var Hashed_Array<string, Group>
     */
    protected $special_groups;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Starnet_Plugin $plugin
     */
    public function __construct(Starnet_Plugin $plugin)
    {
        User_Input_Handler_Registry::get_instance()->register_handler($this);

        $this->plugin = $plugin;
        $this->playback_url_is_stream_url = false;
        $this->special_groups = new Hashed_Array();
    }

    /**
     * @inheritDoc
     */
    public static function get_handler_id()
    {
        return static::ID . '_handler';
    }

    /**
     * @return Hashed_Array<Channel>
     */
    public function get_channels()
    {
        return $this->channels;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $channel_id
     * @return Channel|mixed
     */
    public function get_channel($channel_id)
    {
        $channels = $this->get_channels();
        if ($channels === null) {
            hd_debug_print("Channels not loaded");
            return null;
        }

        return $this->channels->get($channel_id);
    }

    /**
     * @param string $channel_id
     * @param $group_id
     */
    public function disable_channel($channel_id, $group_id)
    {
        hd_debug_print("Hide channel: $channel_id");

        if (!is_null($channel = $this->get_channel($channel_id))) {
            $channel->set_disabled(true);
        }

        if(!is_null($group = $this->get_group($group_id))) {
            if ($group->is_special_group(ALL_CHANNEL_GROUP_ID)) {
                foreach ($this->plugin->tv->get_groups() as $group) {
                    $group->get_items_order()->remove_item($channel_id);
                }
            } else {
                $group->get_items_order()->remove_item($channel_id);
            }
        }
        $this->plugin->get_disabled_channels()->add_item($channel_id);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @template Group
     * @return  Hashed_Array<Group>
     */
    public function get_groups()
    {
        return $this->groups;
    }

    /**
     * @param string $group_id
     */
    public function disable_group($group_id)
    {
        hd_debug_print("Hide group: $group_id");
        $this->plugin->get_disabled_groups()->add_item($group_id);
        $this->plugin->get_groups_order()->remove_item($group_id);
        $this->plugin->save();

        if (($group = $this->groups->get($group_id)) !== null) {
            $group->set_disabled(true);
        }
    }

    /**
     * @return Hashed_Array
     */
    public function get_special_groups()
    {
        return $this->special_groups;
    }

    /**
     * @return Group
     */
    public function get_special_group($id)
    {
        return $this->special_groups->get($id);
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_action_map()
    {
        hd_debug_print(null, true);

        return array(
            GUI_EVENT_PLAYBACK_STOP => User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLAYBACK_STOP),
            GUI_EVENT_TIMER => User_Input_Handler_Registry::create_action($this,
                GUI_EVENT_TIMER,
                null,
                $this->plugin->get_epg_manager()->is_index_locked() ? array('locked' => true) : null),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (!isset($user_input->control_id))
            return null;

        switch ($user_input->control_id) {
            case GUI_EVENT_TIMER:
                if (isset($user_input->stop_play)) {
                    // rising after playback end + 100 ms
                    $this->plugin->invalidate_epfs();
                    return $this->plugin->update_epfs_data($plugin_cookies, array(Starnet_TV_History_Screen::ID));
                }

                if (isset($user_input->locked)) {
                    if ($this->plugin->get_epg_manager()->is_index_locked()) {
                        $new_actions = $this->get_action_map();
                        $new_actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this,
                            GUI_EVENT_TIMER,
                            null,
                            array('locked' => true));

                        return Action_Factory::change_behaviour($new_actions, 1000);
                    }

                    hd_debug_print("Refresh EPG");
                    $post_action = null;
                    foreach($this->plugin->get_epg_manager()->get_delayed_epg() as $channel) {
                        $day_start_ts = strtotime(date("Y-m-d")) + get_local_time_zone_offset();
                        $day_epg = $this->plugin->get_day_epg($channel, $day_start_ts, $plugin_cookies);
                        $post_action = Action_Factory::update_epg($channel, true, $day_start_ts, $day_epg, $post_action);
                    }
                    $this->plugin->get_epg_manager()->clear_delayed_epg();
                    return $post_action;
                }
                break;

            case GUI_EVENT_PLAYBACK_STOP:
                $this->plugin->get_playback_points()->update_point($user_input->plugin_tv_channel_id);

                if (!isset($user_input->playback_stop_pressed) && !isset($user_input->playback_power_off_needed)) break;

                $this->plugin->get_playback_points()->save();
                $new_actions = $this->get_action_map();
                $new_actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this,
                    GUI_EVENT_TIMER,
                    null,
                    array('stop_play' => true));

                return Action_Factory::change_behaviour($new_actions, 100);
        }

        return null;
    }

    /**
     * @param string $group_id
     * @return Group|null
     */
    public function get_group($group_id)
    {
        if (is_null($this->groups)) {
            hd_debug_print("Playlist not loaded yet. Groups not available");
            return null;
        }

        $group = $this->groups->get($group_id);
        if (is_null($group)) {
            $group = $this->special_groups->get($group_id);
            if (is_null($group)) {
                hd_debug_print("Unknown group: $group_id");
                return null;
            }
        }

        return $group;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return void
     */
    public function unload_channels()
    {
        hd_debug_print();
        unset($this->channels, $this->groups);
        $this->channels = null;
        $this->groups = null;
        $this->special_groups->clear();
        $this->plugin->invalidate_epfs();
    }

    /**
     * @param $plugin_cookies
     * @return int
     */
    public function load_channels($plugin_cookies)
    {
        if (!is_null($this->channels)) {
            return 1;
        }

        hd_debug_print();

        HD::set_last_error(null);

        $this->plugin->load(PLUGIN_SETTINGS, true);
        $this->plugin->get_epg_manager()->set_cache_ttl($this->plugin->get_setting(PARAM_EPG_CACHE_TTL, 3));
        $this->plugin->create_screen_views();

        if (!isset($plugin_cookies->pass_sex)) {
            $plugin_cookies->pass_sex = '0000';
        }

        /** @var Hashed_Array<string, string> $custom_group_icons */
        $custom_group_icons = $this->plugin->get_setting(PARAM_GROUPS_ICONS, new Hashed_Array());
        // convert absolute path to filename
        foreach($custom_group_icons as $key => $icon) {
            if (strpos($icon, DIRECTORY_SEPARATOR) !== false) {
                $icon = basename($icon);
                $custom_group_icons->set($key, $icon);
            }
        }

        // Favorites groupse
        $special_group = new Default_Group($this->plugin,
            FAVORITES_GROUP_ID,
            TR::load_string('plugin_favorites'),
            Default_Group::DEFAULT_FAVORITE_GROUP_ICON,
            PARAM_FAVORITES);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // History channels category
        $special_group = new Default_Group($this->plugin,
            HISTORY_GROUP_ID,
            TR::load_string('plugin_history'),
            Default_Group::DEFAULT_HISTORY_GROUP_ICON,
            null);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // History channels category
        $special_group = new Default_Group($this->plugin,
            CHANGED_CHANNELS_GROUP_ID,
            TR::load_string('plugin_changed'),
            Default_Group::DEFAULT_CHANGED_CHANNELS_GROUP_ICON,
            null);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // All channels category
        $special_group = new Default_Group($this->plugin,
            ALL_CHANNEL_GROUP_ID,
            TR::load_string('plugin_all_channels'),
            Default_Group::DEFAULT_ALL_CHANNELS_GROUP_ICON,
            null);
        $this->special_groups->set($special_group->get_id(), $special_group);

        $first_run = $this->plugin->get_known_channels()->size() === 0;

        /** @var Group $special_group */
        foreach ($this->special_groups as $special_group) {
            $group_icon = $custom_group_icons->get($special_group->get_id());
            if (!is_null($group_icon)) {
                $special_group->set_icon_url(get_cached_image_path($group_icon));
            }
        }

        // first check if playlist in cache
        if (!$this->plugin->init_playlist()) {
            return 0;
        }

        $this->groups = new Hashed_Array();
        $this->channels = new Hashed_Array();

        $this->plugin->get_playback_points()->load_points(true);

        $catchup['global'] = $this->plugin->get_m3u_parser()->getM3uInfo()->getCatchup();
        $global_catchup_source = $this->plugin->get_m3u_parser()->getM3uInfo()->getCatchupSource();
        $icon_url_base = $this->plugin->get_m3u_parser()->getHeaderAttribute('url-logo', Entry::TAG_EXTM3U);

        $sources = $this->plugin->get_all_xmltv_sources();
        $key = $this->plugin->get_active_xmltv_source_key();
        hd_debug_print("XMLTV active source key: $key");
        $source = $sources->get($key);
        if (is_null($source) && $sources->size()) {
            $sources->rewind();
            $source = $sources->current();
            if (!is_null($source)) {
                $this->plugin->set_active_xmltv_source($source);
                $this->plugin->set_active_xmltv_source_key($sources->key());
            } else {
                hd_debug_print("No xmltv source defined for this playlist");
            }
        } else {
            $this->plugin->set_active_xmltv_source($source);
        }

        $source = $this->plugin->get_active_xmltv_source();
        hd_debug_print("XMLTV source selected: $source");
        $this->plugin->get_epg_manager()->set_xmltv_url($source);
        $res = $this->plugin->get_epg_manager()->is_xmltv_cache_valid();
        if ($res !== -1) {
            if ($res === 0) {
                $this->plugin->get_epg_manager()->download_xmltv_source();
            }

            $this->plugin->get_epg_manager()->index_xmltv_channels();
        }

        hd_debug_print("Build categories and channels...");
        $t = microtime(true);

        $picons = $this->plugin->get_epg_manager()->get_picons();
        $user_catchup = $this->plugin->get_setting(PARAM_USER_CATCHUP, KnownCatchupSourceTags::cu_unknown);
        if ($user_catchup !== KnownCatchupSourceTags::cu_unknown) {
            $catchup['global'] = $user_catchup;
        }

        // suppress save after add group
        $this->plugin->set_pospone_save();

        // Collect categories from playlist
        $playlist_groups = new Ordered_Array();
        $pl_entries = $this->plugin->get_m3u_parser()->getM3uEntries();
        foreach ($pl_entries as $entry) {
            $title = $entry->getGroupTitle();
            if ($this->groups->has($title)) continue;

            // using title as id
            $group_icon = $custom_group_icons->get($title);
            if (!is_null($group_icon)) {
                $group_icon = get_cached_image_path($custom_group_icons->get($title));
            }
            $group = new Default_Group($this->plugin, $title, null, $group_icon);
            $adult = (strpos($title, "зрослы") !== false
                || strpos($title, "adult") !== false
                || strpos($title, "18+") !== false
                || strpos($title, "xxx") !== false);

            $group->set_adult($adult);
            if ($this->plugin->get_disabled_groups()->in_order($group->get_id())) {
                $group->set_disabled(true);
                hd_debug_print("Hidden category # $title");
            } else if (!$this->plugin->get_groups_order()->in_order($group->get_id())) {
                hd_debug_print("New    category # $title");
                $this->plugin->get_groups_order()->add_item($title);
            }

            $playlist_groups->add_item($title);

            // disable save
            $this->groups->set($group->get_id(), $group);
            $this->plugin->save();
        }

        // cleanup order if saved group removed from playlist
        if ($this->plugin->get_groups_order()->size() !== 0) {
            $orphans_groups = array_diff($this->plugin->get_groups_order()->get_order(), $playlist_groups->get_order());
            foreach ($orphans_groups as $group) {
                hd_debug_print("Remove orphaned group: $group");
                $this->plugin->get_groups_order()->remove_item($group);
                $this->plugin->get_disabled_groups()->remove_item($group);
            }
            $this->plugin->save();
        }
        unset($playlist_groups);

        // Read channels
        $playlist_group_channels = array();
        $number = 0;
        foreach ($pl_entries as $entry) {
            $channel_id = $entry->getEntryId();

            // if group is not registered it was disabled
            $channel_name = $entry->getEntryTitle();
            $group_title = $entry->getGroupTitle();
            if ($this->groups->has($group_title) === false) {
                hd_debug_print("Channel $channel_name in disabled group $group_title");
                continue;
            }

            if (empty($channel_name)) {
                hd_print("Bad entry: " . $entry);
                $channel_name = "no name";
            }

            $number++;

            /** @var Channel $channel */
            $channel = $this->channels->get($channel_id);
            if (is_null($channel)) {
                $epg_ids = $entry->getAllEntryAttributes(self::$tvg_id);
                if (!empty($epg_ids)) {
                    $epg_ids = array_unique(array_values($epg_ids));
                }

                $icon_url = $entry->getEntryIcon();
                if (!empty($icon_url_base) && !preg_match("|https?://|", $icon_url)) {
                    $icon_url = $icon_url_base . $icon_url;
                } else if (empty($icon_url)) {
                    $icon_url = isset($picons[$channel_name]) ? $picons[$channel_name] : self::DEFAULT_CHANNEL_ICON_PATH;
                }

                $used_tag = '';
                $archive = (int)$entry->getAnyEntryAttribute(self::$tvg_archive, Entry::TAG_EXTINF, $used_tag);
                if ($used_tag === 'catchup-time') {
                    $archive /= 86400;
                }

                $archive_url = '';
                if ($archive !== 0) {
                    $catchup['channel'] = $entry->getCatchup();
                    $archive_url = $entry->getCatchupSource();
                    if (empty($archive_url) && !empty($global_catchup_source)) {
                        $archive_url = $global_catchup_source;
                    }

                    if (empty($archive_url)) {
                        if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_shift, $catchup)) {
                            $archive_url = $entry->getPath()
                                . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                . 'utc=${start}&lutc=${timestamp}';
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_timeshift, $catchup)) {
                                $archive_url = $entry->getPath()
                                    . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                    . 'timeshift=${start}&timenow=${timestamp}';
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_archive, $catchup)) {
                            $archive_url = $entry->getPath()
                                . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                . 'archive=${start}&archive_end=${end}';
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_flussonic, $catchup)
                            && preg_match("#^(https?://[^/]+)/([^/]+)/([^/]+)\.(m3u8?|ts)(\?.+=.+)?$#", $entry->getPath(), $m)) {
                                $archive_url = "$m[1]/$m[2]/$m[3]-" . '${start}' . "-14400.$m[4]$m[5]";
                        } else if (KnownCatchupSourceTags::is_tag(KnownCatchupSourceTags::cu_xstreamcode, $catchup)
                            && preg_match("|^(https?://[^/]+)/(?:live/)?([^/]+)/([^/]+)/([^/.]+)(\.m3u8?)?$|", $entry->getPath(), $m)) {
                            $extension = $m[5] ?: '.ts';
                            $archive_url = "$m[1]/timeshift/$m[2]/$m[3]/240/{Y}-{m}-{d}:{H}-{M}/$m[4].$extension";
                        } else {
                            // if no info about catchup, use 'shift'
                            $archive_url = $entry->getPath()
                                . ((strpos($entry->getPath(), '?') !== false) ? '&' : '?')
                                . 'utc=${start}&lutc=${timestamp}';
                        }
                    } else if (!preg_match("|https?://|", $archive_url)){
                        $archive_url = $entry->getPath() . $archive_url;
                    }
                }

                $ext_params[PARAM_DUNE_PARAMS] = $this->plugin->get_setting(PARAM_DUNE_PARAMS);

                $ext_tag = $entry->getEntryTag(Entry::TAG_EXTHTTP);
                if ($ext_tag !== null && ($ext_http_values = json_decode($ext_tag->getTagValue(), true)) !== false) {
                    foreach ($ext_http_values as $key => $value) {
                        $ext_params[Entry::TAG_EXTHTTP][strtolower($key)] = $value;
                    }

                    if (isset($ext_params[Entry::TAG_EXTHTTP]['user-agent'])) {
                        hd_debug_print(Entry::TAG_EXTHTTP . " Channel: $channel_name uses custom User-Agent: '{$ext_params[Entry::TAG_EXTHTTP]['user-agent']}'", true);
                        $ch_useragent = "User-Agent: " . $ext_params[Entry::TAG_EXTHTTP]['user-agent'];
                    }
                }

                $ext_tag = $entry->getEntryTag(Entry::TAG_EXTVLCOPT);
                if ($ext_tag !== null) {
                    $ext_vlc_opts = array();
                    foreach ($ext_tag->getTagValues() as $value) {
                        $pair = explode('=', $value);
                        $ext_vlc_opts[strtolower(trim($pair[0]))] = trim($pair[1]);
                    }

                    if (isset($ext_vlc_opts['http-user-agent'])) {
                        hd_debug_print(Entry::TAG_EXTVLCOPT . " Channel: $channel_name uses custom User-Agent: '{$ext_vlc_opts['http-user-agent']}'", true);
                        $ch_useragent = "User-Agent: " . $ext_vlc_opts['http-user-agent'];
                    }

                    if (isset($ext_vlc_opts['dune-params'])) {
                        hd_debug_print(Entry::TAG_EXTVLCOPT . " Channel: $channel_name uses custom dune_params: '{$ext_vlc_opts['dune-params']}'", true);
                        foreach ($ext_vlc_opts['dune-params'] as $param) {
                            $param_pair = explode(':', $param);
                            if (empty($param_pair) || count($param_pair) < 2) continue;

                            $param_pair[0] = trim($param_pair[0]);
                            if (strpos($param_pair[1], ",,") !== false) {
                                $param_pair[1] = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $param_pair[1]);
                            } else {
                                $param_pair[1] = str_replace(",", ",,", $param_pair[1]);
                            }

                            $ext_params[PARAM_DUNE_PARAMS][$param_pair[0]] = $param_pair[1];
                            unset($ext_vlc_opts['dune-params']);
                        }
                    }

                    $ext_params[Entry::TAG_EXTVLCOPT] = $ext_vlc_opts;
                }

                if (!empty($ch_useragent)) {
                    // escape commas for dune_params
                    if (strpos($ch_useragent, ",,") !== false) {
                        $ch_useragent = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $ch_useragent);
                    } else {
                        $ch_useragent = str_replace(",", ",,", $ch_useragent);
                    }

                    $ch_useragent = rawurlencode("User-Agent: " . $ch_useragent);
                    if (isset($ext_params[PARAM_DUNE_PARAMS]['http_headers'])) {
                        $ext_params[PARAM_DUNE_PARAMS]['http_headers'] .= $ch_useragent;
                    } else {
                        $ext_params[PARAM_DUNE_PARAMS]['http_headers'] = $ch_useragent;
                    }

                    unset($ch_useragent);
                }

                /** @var Group $parent_group */
                $parent_group = $this->groups->get($group_title);
                $protected = false;
                $adult_code = $entry->getProtectedCode();
                if ((!empty($adult_code)) || $parent_group->is_adult_group()) {
                    $protected = !empty($plugin_cookies->pass_sex);
                }

                $group_logo = $entry->getEntryAttribute('group-logo');
                if (!empty($group_logo) && $parent_group->get_icon_url() === null) {
                    if (!preg_match("|^https?://|", $group_logo)) {
                        if (!empty($icon_url_base)) {
                            $group_logo = $icon_url_base . $group_logo;
                        } else {
                            $group_logo = Default_Group::DEFAULT_GROUP_ICON_PATH;
                        }
                    }

                    hd_debug_print("Set picon: $group_logo", true);
                    $parent_group->set_icon_url($group_logo);
                }

                $channel = new Default_Channel(
                    $channel_id,
                    $channel_name,
                    $icon_url,
                    $entry->getPath(),
                    $archive_url,
                    $archive,
                    $number,
                    $epg_ids,
                    $protected,
                    (int)$entry->getEntryAttribute('tvg-shift', Entry::TAG_EXTINF),
                    $ext_params
                );

                // ignore disabled channel
                if ($this->plugin->get_disabled_channels()->in_order($channel_id)) {
                    hd_debug_print("Channel $channel_name is disabled");
                    $channel->set_disabled(true);
                }

                $playlist_group_channels[$parent_group->get_id()][] = $channel_id;
                $this->channels->set($channel->get_id(), $channel);
                if ($first_run) {
                    $this->plugin->get_known_channels()->set($channel->get_id(), $channel->get_title());
                }

                foreach ($epg_ids as $epg_id) {
                    $epg_ids[$epg_id] = '';
                }

                // Link group and channel.
                $channel->add_group($parent_group);
                $parent_group->add_channel($channel);
            }
        }

        $no_changes = count($this->plugin->get_changed_channels(null)) === 0;

        $this->get_special_group(CHANGED_CHANNELS_GROUP_ID)->set_disabled($no_changes);

        // cleanup order if saved group removed from playlist
        // enable save for each group
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $orphans_channels = array_diff($group->get_items_order()->get_order(), $playlist_group_channels[$group->get_id()]);
            foreach ($orphans_channels as $channel_id) {
                hd_debug_print("Remove orphaned channel id: $channel_id");
                $group->get_items_order()->remove_item($channel_id);
            }
        }


        $this->plugin->set_pospone_save(false);

        hd_debug_print("Loaded channels: {$this->channels->size()}, hidden channels: {$this->plugin->get_disabled_channels()->size()}");
        hd_debug_print("Total groups: {$this->groups->size()}, hidden groups: " . ($this->groups->size() - $this->plugin->get_groups_order()->size()));
        hd_debug_print("------------------------------------------------------------");
        hd_debug_print("Load channels done: " . (microtime(true) - $t) . " secs");
        HD::ShowMemoryUsage();

        if ($this->plugin->get_epg_manager()->is_xmltv_cache_valid() === 1) {
            hd_debug_print("Run background indexing: {$this->plugin->get_active_xmltv_source()} ({$this->plugin->get_active_xmltv_source_key()})");
            $this->plugin->get_epg_manager()->start_bg_indexing();
            sleep(1);
        }

        return 2;
    }

    /**
     * @param $plugin_cookies
     * @return bool
     */
    public function reload_channels(&$plugin_cookies)
    {
        hd_debug_print();

        $this->unload_channels();
        return $this->load_channels($plugin_cookies) !== 0;
    }

    /**
     * @param string $channel_id
     * @param int $archive_ts
     * @param string $protect_code
     * @param $plugin_cookies
     * @return string
     */
    public function get_tv_playback_url($channel_id, $archive_ts, $protect_code, &$plugin_cookies)
    {
        try {
            if ($this->load_channels($plugin_cookies) === 0) {
                throw new Exception("Channels not loaded!");
            }

            $pass_sex = isset($plugin_cookies->pass_sex) ? $plugin_cookies->pass_sex : '0000';
            // get channel by hash
            $channel = $this->get_channel($channel_id);
            if ($protect_code !== $pass_sex && $channel->is_protected()) {
                throw new Exception("Wrong adult password");
            }

            if (!$channel->is_protected()) {
                $now = $channel->get_archive() > 0 ? time() : 0;
                $this->plugin->get_playback_points()->push_point($channel_id, ($archive_ts !== -1 ? $archive_ts : $now));
            }

            // update url if play archive or different type of the stream
            $url = $this->plugin->generate_stream_url($channel_id, $archive_ts);

            if ($this->plugin->get_bool_setting(PARAM_PER_CHANNELS_ZOOM)) {
                $zoom_preset = $this->plugin->get_channel_zoom($channel_id);
                if (!is_null($zoom_preset)) {
                    if (!is_android()) {
                        $zoom_preset = DuneVideoZoomPresets::normal;
                        hd_debug_print("zoom_preset: reset to normal $zoom_preset");
                    }

                    if ($zoom_preset !== DuneVideoZoomPresets::not_set) {
                        $url .= (strpos($url, "|||dune_params|||") === false ? "|||dune_params|||" : ",");
                        $url .= "zoom:$zoom_preset";
                    }
                }
            }
        } catch (Exception $ex) {
            hd_debug_print("Exception: " . $ex->getMessage());
            $url = '';
        }

        hd_debug_print($url);
        return $url;
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_tv_info(MediaURL $media_url, &$plugin_cookies)
    {
        $epg_font_size = $this->plugin->get_bool_parameter(PARAM_EPG_FONT_SIZE, false)
            ? PLUGIN_FONT_SMALL
            : PLUGIN_FONT_NORMAL;

        if ($this->load_channels($plugin_cookies) === 0) {
            hd_debug_print("Channels not loaded!");
            return array();
        }
        $this->playback_runtime = PHP_INT_MAX;

        $group_all = $this->get_special_group(ALL_CHANNEL_GROUP_ID);
        $show_all = !$group_all->is_disabled();
        $all_channels = new Hashed_Array();
        /** @var Group $group */
        foreach ($this->groups as $group) {
            if ($group->is_disabled()) continue;

            $group_id_arr = new Hashed_Array();
            if($show_all) {
                $group_id_arr->put(ALL_CHANNEL_GROUP_ID, '');
            }

            /** @var Group $group */
            foreach ($group->get_items_order() as $item) {
                $channel = $this->get_channel($item);
                if (is_null($channel)) continue;

                foreach ($channel->get_groups() as $in_group) {
                    $group_id_arr->put($in_group->get_id(), '');
                }
                if ($group_id_arr->size() === 0) continue;

                $all_channels->put(
                    $channel->get_id(),
                    array(
                        PluginTvChannel::id => $channel->get_id(),
                        PluginTvChannel::caption => $channel->get_title(),
                        PluginTvChannel::group_ids => $group_id_arr->get_keys(),
                        PluginTvChannel::icon_url => $channel->get_icon_url(),
                        PluginTvChannel::number => $channel->get_number(),

                        PluginTvChannel::have_archive => $channel->get_archive() > 0,
                        PluginTvChannel::is_protected => $channel->is_protected(),

                        // set default epg range
                        PluginTvChannel::past_epg_days => $channel->get_past_epg_days(),
                        PluginTvChannel::future_epg_days => $channel->get_future_epg_days(),

                        PluginTvChannel::archive_past_sec => $channel->get_archive_past_sec(),
                        PluginTvChannel::archive_delay_sec => $this->plugin->get_setting(PARAM_ARCHIVE_DELAY_TIME, 60),

                        // Buffering time
                        PluginTvChannel::buffering_ms => $this->plugin->get_setting(PARAM_BUFFERING_TIME, 1000),
                        PluginTvChannel::timeshift_hours => $channel->get_timeshift_hours(),

                        PluginTvChannel::playback_url_is_stream_url => $this->playback_url_is_stream_url,
                    )
                );
            }
        }

        $groups_order = array_merge($show_all ? array(ALL_CHANNEL_GROUP_ID) : array(),
            $this->plugin->get_groups_order()->get_order());

        $groups = array();
        /** @var Group $group */
        foreach ($groups_order as $id) {
            $group = $this->get_group($id);
            if (is_null($group) || $group->is_disabled()) continue;

            $group_icon = $group->get_icon_url();
            $groups[] = array(
                PluginTvGroup::id => $group->get_id(),
                PluginTvGroup::caption => $group->get_title(),
                PluginTvGroup::icon_url => is_null($group_icon) ? Default_Group::DEFAULT_GROUP_ICON_PATH: $group_icon
            );
        }

        if (isset($media_url->is_favorites)) {
            $initial_group_id = null;
            $initial_is_favorite = 1;
        } else {
            $initial_group_id = (string)$media_url->group_id;
            $initial_is_favorite = 0;
        }

        if (LogSeverity::$is_debug) {
            hd_debug_print("All groups: " . raw_json_encode($groups));
            hd_debug_print("All channels: " . raw_json_encode($all_channels->get_ordered_values()));
        }

        return array(
            PluginTvInfo::show_group_channels_only => true,

            PluginTvInfo::groups => $groups,
            PluginTvInfo::channels => $all_channels->get_ordered_values(),

            PluginTvInfo::favorites_supported => true,
            PluginTvInfo::favorites_icon_url => $this->get_special_group(FAVORITES_GROUP_ID)->get_icon_url(),

            PluginTvInfo::initial_channel_id => (string)$media_url->channel_id,
            PluginTvInfo::initial_group_id => $initial_group_id,

            PluginTvInfo::initial_is_favorite => $initial_is_favorite,
            PluginTvInfo::favorite_channel_ids => $this->plugin->get_favorites()->get_order(),

            PluginTvInfo::initial_archive_tm => isset($media_url->archive_tm) ? (int)$media_url->archive_tm : -1,

            PluginTvInfo::epg_font_size => $epg_font_size,

            PluginTvInfo::actions => $this->get_action_map(),
            PluginTvInfo::timer => Action_Factory::timer(1000),
        );
    }
}
