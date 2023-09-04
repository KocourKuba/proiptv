<?php
require_once 'lib/hashed_array.php';
require_once 'lib/ordered_array.php';
require_once 'lib/tv/default_channel.php';
require_once 'lib/tv/all_channels_group.php';
require_once 'lib/tv/favorites_group.php';
require_once 'lib/tv/history_group.php';
require_once 'lib/tv/default_epg_item.php';
require_once 'starnet_setup_screen.php';

class Starnet_Tv implements User_Input_Handler
{
    const ID = 'tv';

    ///////////////////////////////////////////////////////////////////////

    public static $tvg_id = array('tvg-id', 'tvg-name');
    public static $tvg_archive = array('catchup-days', 'catchup-time', 'timeshift', 'arc-timeshift', 'arc-time', 'tvg-rec');

    ///////////////////////////////////////////////////////////////////////

    /**
     * @var Starnet_Plugin
     */
    protected $plugin;

    /**
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
     * @var Hashed_Array<Channel>
     */
    protected $channels;

    /**
     * @template Group
     * @var Hashed_Array<Group>
     */
    protected $groups;

    /**
     * @template Group
     * @var Hashed_Array<Group>
     */
    protected $special_groups;

    /**
     * @var Ordered_Array
     */
    protected $groups_order;

    /**
     * @var Ordered_Array
     */
    protected $disabled_groups;

    /**
     * @var Ordered_Array
     */
    protected $disabled_channels;

    /**
     * @var Hashed_Array
     */
    protected $channels_zoom;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Starnet_Plugin $plugin
     */
    public function __construct(Starnet_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->playback_url_is_stream_url = false;

        User_Input_Handler_Registry::get_instance()->register_handler($this);
    }

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
     * @return Group
     */
    public function get_special_group($id)
    {
        return $this->special_groups->get($id);
    }

    /**
     * @return Ordered_Array
     */
    public function get_groups_order()
    {
        if (is_null($this->groups_order)) {
            $this->groups_order = new Ordered_Array($this->plugin, PARAM_GROUPS_ORDER);
        }
        return $this->groups_order;
    }

    /**
     * @return Ordered_Array
     */
    public function get_disabled_groups()
    {
        if (is_null($this->disabled_groups)) {
            $this->disabled_groups = new Ordered_Array($this->plugin, PARAM_DISABLED_GROUPS);
        }
        return $this->disabled_groups;
    }

    /**
     * @param string $group_id
     */
    public function disable_group($group_id)
    {
        $this->get_disabled_groups()->add_item($group_id);
        $this->get_groups_order()->remove_item($group_id);

        if (($group = $this->groups->get($group_id)) !== null) {
            $group->set_disabled(true);
        }
    }

    /**
     * @return Ordered_Array
     */
    public function get_disabled_channels()
    {
        if (is_null($this->disabled_channels)) {
            $this->disabled_channels = new Ordered_Array($this->plugin, PARAM_DISABLED_CHANNELS);
        }
        return $this->disabled_channels;
    }

    /**
     * @return Hashed_Array
     */
    public function get_channels_zoom()
    {
        if (is_null($this->channels_zoom)) {
            $this->channels_zoom = $this->plugin->get_settings(PARAM_CHANNELS_ZOOM, new Hashed_Array());
        }
        return $this->channels_zoom;
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
        if ($preset === null) {
            $this->get_channels_zoom()->erase($channel_id);
        } else {
            $this->get_channels_zoom()->set_by_id($channel_id, $preset);
        }

        $this->plugin->set_settings(PARAM_CHANNELS_ZOOM, $this->get_channels_zoom());
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_action_map()
    {
        return array(
            GUI_EVENT_PLAYBACK_STOP => User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLAYBACK_STOP)
        );
    }

    /**
     * @throws Exception
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);

        if (!isset($user_input->control_id))
            return null;

        switch ($user_input->control_id) {
            case GUI_EVENT_TIMER:
                // rising after playback end + 100 ms
                Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
                return Starnet_Epfs_Handler::invalidate_folders(array(Starnet_TV_History_Screen::ID));

            case GUI_EVENT_PLAYBACK_STOP:
                $this->plugin->playback_points->update_point($user_input->plugin_tv_channel_id);

                if (!isset($user_input->playback_stop_pressed) && !isset($user_input->playback_power_off_needed)) break;

                $this->plugin->playback_points->save();
                $new_actions = $this->get_action_map();
                $new_actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);
                return Action_Factory::change_behaviour($new_actions, 100);
        }

        return null;
    }

    /**
     * @param string $group_id
     * @return Group|mixed
     */
    public function get_group($group_id)
    {
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
        $this->channels = null;
        $this->groups = null;
        $this->special_groups = null;

        $this->groups_order = null;
        $this->disabled_groups = null;
        $this->disabled_channels = null;
        $this->channels_zoom = null;
    }

    /**
     * @param $plugin_cookies
     * @return bool
     */
    public function load_channels($plugin_cookies)
    {
        if (isset($this->channels)) {
            return true;
        }

        hd_debug_print();

        if (!isset($plugin_cookies->pass_sex)) {
            $plugin_cookies->pass_sex = '0000';
        }

        $this->groups = new Hashed_Array();
        $this->channels = new Hashed_Array();
        $this->special_groups = new Hashed_Array();

        // All channels category
        $this->special_groups->put(new All_Channels_Group());

        // Favorites group
        $this->special_groups->put(new Favorites_Group($this->plugin));

        // History channels category
        $this->special_groups->put(new History_Group());

        // first check if playlist in cache
        if (!$this->plugin->init_playlist()) {
            return false;
        }

        $this->plugin->playback_points->load_points(true);

        $catchup['global'] = $this->plugin->m3u_parser->getM3uInfo()->getCatchup();
        $global_catchup_source = $this->plugin->m3u_parser->getM3uInfo()->getCatchupSource();
        $icon_url_base = $this->plugin->m3u_parser->getHeaderAttribute('url-logo', TAG_EXTM3U);
        $this->plugin->update_xmltv_source();

        $user_catchup = $this->plugin->get_settings(PARAM_USER_CATCHUP, KnownCatchupSourceTags::cu_unknown);
        if ($user_catchup !== KnownCatchupSourceTags::cu_unknown) {
            $catchup['global'] = $user_catchup;
        }

        // suppress save after add group
        $this->get_groups_order()->set_save_delay(true);

        // Collect categories from playlist
        $playlist_groups = new Ordered_Array();
        $pl_entries = $this->plugin->m3u_parser->getM3uEntries();
        foreach ($pl_entries as $entry) {
            $title = $entry->getGroupTitle();
            if ($this->groups->has($title)) continue;

            // using title as id
            $group_logo = $entry->getEntryAttribute('group-logo');
            $group = new Default_Group($this->plugin, null, $title, empty($group_logo) ? null : $group_logo);
            $adult = (strpos($title, "зрослы") !== false
                || strpos($title, "adult") !== false
                || strpos($title, "18+") !== false
                || strpos($title, "xxx") !== false);

            $group->set_adult($adult);
            if ($this->get_disabled_groups()->in_order($group->get_id())) {
                $group->set_disabled(true);
                hd_debug_print("Hidden category # $title");
            } else if (!$this->get_groups_order()->in_order($group->get_id())) {
                hd_debug_print("New    category # $title");
                $this->get_groups_order()->add_item($title);
//            } else {
//                hd_debug_print("Known category # $title");
            }

            $playlist_groups->add_item($title);

            // disable save
            $group->get_items_order()->set_save_delay(true);
            $this->groups->put($group);
        }

        // cleanup order if saved group removed from playlist
        if ($this->get_groups_order()->size() !== 0) {
            $orphans_groups = array_diff($this->get_groups_order()->get_order(), $playlist_groups->get_order());
            foreach ($orphans_groups as $group) {
                hd_debug_print("Remove orphaned group: $group");
                $this->get_groups_order()->remove_item($group);
                $this->get_disabled_groups()->remove_item($group);
            }
        }
        unset($playlist_groups);

        // enable save
        $this->get_groups_order()->set_save_delay(false);
        $this->get_groups_order()->save();

        // Read channels
        $playlist_group_channels = array();
        $epg_ids = array();
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
            if (!is_null($channel)) {
                foreach ($channel->get_groups() as $group) {
                    hd_debug_print("Channel id: $channel_id ($channel_name) already exist in category: {$group->get_title()}");
                }
            } else {
                //hd_debug_print("attributes: " . serialize($entry->getAttributes()));
                $epg_ids = $entry->getAllEntryAttributes(self::$tvg_id);
                $epg_ids[] = $channel_name;
                if (!empty($epg_ids)) {
                    $epg_ids = array_unique($epg_ids);
                }

                $icon_url = $entry->getEntryIcon();
                if (empty($icon_url)) {
                    $icon_url = $this->plugin->epg_man->get_picon($epg_ids);
                    if (empty($icon_url)) {
                        //hd_debug_print("picon for $channel_name not found");
                        $icon_url = "plugin_file://icons/default_channel.png";
                    }
                } else if (!empty($icon_url_base) && !preg_match("|https?://|", $icon_url)) {
                    $icon_url = $icon_url_base . $icon_url;
                }

                $used_tag = '';
                $archive = (int)$entry->getAnyEntryAttribute(self::$tvg_archive, TAG_EXTINF, $used_tag);
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
                        }
                    } else if (!preg_match("|https?://|", $archive_url)){
                        $archive_url = $entry->getPath() . $archive_url;
                    } else {
                        $archive = 0;
                    }
                }

                $ext_params[PARAM_DUNE_PARAMS] = $this->plugin->get_settings(PARAM_DUNE_PARAMS);

                $ext_tag = $entry->getEntryTag(TAG_EXTHTTP);
                if ($ext_tag !== null && ($ext_http_values = json_decode($ext_tag->getTagValue(), true)) !== false) {
                    foreach ($ext_http_values as $key => $value) {
                        $ext_params[TAG_EXTHTTP][strtolower($key)] = $value;
                    }

                    if (isset($ext_params[TAG_EXTHTTP]['user-agent'])) {
                        //hd_debug_print(TAG_EXTHTTP . " Channel: $channel_name uses custom User-Agent: '{$ext_params[TAG_EXTHTTP]['user-agent']}'");
                        $ch_useragent = "User-Agent: " . $ext_params[TAG_EXTHTTP]['user-agent'];
                    }
                }

                $ext_tag = $entry->getEntryTag(TAG_EXTVLCOPT);
                if ($ext_tag !== null) {
                    foreach ($ext_tag->getTagValues() as $value) {
                        $pair = explode('=', $value);
                        $ext_params[TAG_EXTVLCOPT][strtolower(trim($pair[0]))] = trim($pair[1]);
                    }

                    if (isset($ext_params[TAG_EXTVLCOPT]['http-user-agent'])) {
                        //hd_debug_print(TAG_EXTVLCOPT . " Channel: $channel_name uses custom User-Agent: '{$ext_params[TAG_EXTVLCOPT]['http-user-agent']}'");
                        $ch_useragent = "User-Agent: " . $ext_params[TAG_EXTVLCOPT]['http-user-agent'];
                    }
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
                if ((!empty($adult_code)) || (!is_null($parent_group) && $parent_group->is_adult_group())) {
                    $protected = !empty($plugin_cookies->pass_sex);
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
                    (int)$entry->getEntryAttribute('tvg-shift', TAG_EXTINF),
                    $ext_params
                );

                // ignore disabled channel
                if ($this->get_disabled_channels()->in_order($channel_id)) {
                    hd_debug_print("Channel $channel_name is disabled");
                    $channel->set_disabled(true);
                }

                //hd_debug_print("channel: " . $channel->get_title());
                $playlist_group_channels[$parent_group->get_id()][] = $channel_id;
                $this->channels->put($channel);

                foreach ($epg_ids as $epg_id) {
                    $epg_ids[$epg_id] = '';
                }

                // Link group and channel.
                $channel->add_group($parent_group);
                $parent_group->add_channel($channel);
            }
        }

        // cleanup order if saved group removed from playlist
        // enable save for each group
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $orphans_channels = array_diff($group->get_items_order()->get_order(), $playlist_group_channels[$group->get_id()]);
            foreach ($orphans_channels as $channel_id) {
                hd_debug_print("Remove orphaned channel id: $channel_id");
                $group->get_items_order()->remove_item($channel_id);
            }

            $group->get_items_order()->set_save_delay(false);
            $group->get_items_order()->save();
        }

        hd_debug_print("Loaded channels: {$this->channels->size()}, hidden channels: {$this->get_disabled_channels()->size()}");
        hd_debug_print("Total groups: {$this->groups->size()}, hidden groups: " . ($this->groups->size() - $this->get_groups_order()->size()));

        $this->plugin->epg_man->index_xmltv_file($epg_ids);

        Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);

        return true;
    }

    /**
     * @param User_Input_Handler $handler
     * @param $plugin_cookies
     * @param $post_action
     * @return array
     */
    public function reload_channels(User_Input_Handler $handler, &$plugin_cookies, $post_action = null)
    {
        hd_debug_print();
        $this->plugin->clear_playlist_cache();
        $this->unload_channels();
        if (!$this->load_channels($plugin_cookies)) {
            hd_debug_print("Channels not loaded!");
            return null;
        }

        Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
        return Starnet_Epfs_Handler::invalidate_folders(array(
                Starnet_Tv_Groups_Screen::ID,
                Starnet_Tv_Channel_List_Screen::ID,
                Starnet_Playlists_Setup_Screen::ID),
            $post_action !== null ? $post_action : User_Input_Handler_Registry::create_action($handler, RESET_CONTROLS_ACTION_ID)
        );
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
        //hd_debug_print("channel: $channel_id archive_ts: $archive_ts, protect code: $protect_code");

        try {
            if (!$this->load_channels($plugin_cookies)) {
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
                $this->plugin->playback_points->push_point($channel_id, ($archive_ts !== -1 ? $archive_ts : $now));
            }

            // update url if play archive or different type of the stream
            $url = $this->plugin->generate_stream_url($channel_id, $archive_ts);

            $zoom_preset = $this->get_channel_zoom($channel_id);
            if (!is_null($zoom_preset) && !is_android() && !is_apk()) {
                $zoom_preset = DuneVideoZoomPresets::normal;
                hd_debug_print("zoom_preset: reset to normal $zoom_preset");
            }

            if (!is_null($zoom_preset)) {
                $url .= (strpos($url, "|||dune_params|||") === false ? "|||dune_params|||" : ",");
                $url .= "zoom:$zoom_preset";
            }

            hd_debug_print($url);
        } catch (Exception $ex) {
            hd_debug_print("Exception: " . $ex->getMessage());
            $url = '';
        }

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
        $font_size = $this->plugin->get_parameters(PARAM_EPG_FONT_SIZE, SetupControlSwitchDefs::switch_off);
        $show_all = (!isset($plugin_cookies->{Starnet_Interface_Setup_Screen::CONTROL_SHOW_ALL})
                || $plugin_cookies->{Starnet_Interface_Setup_Screen::CONTROL_SHOW_ALL} === SetupControlSwitchDefs::switch_on);
        //$t = microtime(1);

        if (!$this->load_channels($plugin_cookies)) {
            hd_debug_print("Channels not loaded!");
            return array();
        }
        $this->playback_runtime = PHP_INT_MAX;

        $channels = array();

        foreach ($this->channels as $channel) {
            if ($channel->is_disabled()) continue;

            $group_id_arr = array();

            if ($show_all) {
                $group_id_arr[] = ALL_CHANNEL_GROUP_ID;
            }

            $in_groups = 0;
            foreach ($channel->get_groups() as $group) {
                if ($group->is_disabled()) continue;

                $group_id_arr[] = $group->get_id();
                $in_groups++;
            }

            if ($in_groups === 0) continue;

            $channels[] = array(
                PluginTvChannel::id => $channel->get_id(),
                PluginTvChannel::caption => $channel->get_title(),
                PluginTvChannel::group_ids => $group_id_arr,
                PluginTvChannel::icon_url => $channel->get_icon_url(),
                PluginTvChannel::number => $channel->get_number(),

                PluginTvChannel::have_archive => $channel->get_archive() > 0,
                PluginTvChannel::is_protected => $channel->is_protected(),

                // set default epg range
                PluginTvChannel::past_epg_days => $channel->get_past_epg_days(),
                PluginTvChannel::future_epg_days => $channel->get_future_epg_days(),

                PluginTvChannel::archive_past_sec => $channel->get_archive_past_sec(),
                PluginTvChannel::archive_delay_sec => (isset($plugin_cookies->delay_time) ? $plugin_cookies->delay_time : 60),

                // Buffering time
                PluginTvChannel::buffering_ms => (isset($plugin_cookies->buf_time) ? $plugin_cookies->buf_time : 1000),
                PluginTvChannel::timeshift_hours => $channel->get_timeshift_hours(),

                PluginTvChannel::playback_url_is_stream_url => $this->playback_url_is_stream_url,
            );
        }

        $groups = array();
        if ($show_all) {
            $group = $this->get_special_group(ALL_CHANNEL_GROUP_ID);
            if (!is_null($group)) {
                $groups[] = array
                (
                    PluginTvGroup::id => $group->get_id(),
                    PluginTvGroup::caption => $group->get_title(),
                    PluginTvGroup::icon_url => $group->get_icon_url()
                );
            }
        }

        /** @var Group $group */
        foreach ($this->get_groups_order()->get_order() as $id) {
            $group = $this->groups->get($id);
            if ($group !== null && !$group->is_disabled()) {
                $groups[] = array
                (
                    PluginTvGroup::id => $group->get_id(),
                    PluginTvGroup::caption => $group->get_title(),
                    PluginTvGroup::icon_url => $group->get_icon_url()
                );
            }
        }

        if (isset($media_url->is_favorites)) {
            $initial_group_id = null;
            $initial_is_favorite = 1;
        } else {
            $initial_group_id = (string)$media_url->group_id;
            $initial_is_favorite = 0;
        }

        //'groups: ' . count($groups) . ' channels: ' . count($channels));
        //'Info loaded at ' . (microtime(1) - $t) . ' secs');

        return array(
            PluginTvInfo::show_group_channels_only => true,

            PluginTvInfo::groups => $groups,
            PluginTvInfo::channels => $channels,

            PluginTvInfo::favorites_supported => true,
            PluginTvInfo::favorites_icon_url => Favorites_Group::FAV_CHANNEL_GROUP_ICON_PATH,

            PluginTvInfo::initial_channel_id => (string)$media_url->channel_id,
            PluginTvInfo::initial_group_id => $initial_group_id,

            PluginTvInfo::initial_is_favorite => $initial_is_favorite,
            PluginTvInfo::favorite_channel_ids => $this->plugin->get_favorites()->get_order(),

            PluginTvInfo::initial_archive_tm => isset($media_url->archive_tm) ? (int)$media_url->archive_tm : -1,

            PluginTvInfo::epg_font_size => ($font_size === SetupControlSwitchDefs::switch_on) ? PLUGIN_FONT_SMALL : PLUGIN_FONT_NORMAL,

            PluginTvInfo::actions => $this->get_action_map(),
            PluginTvInfo::timer => Action_Factory::timer(1000),
        );
    }
}
