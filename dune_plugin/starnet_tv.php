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
require_once 'lib/epg/default_epg_item.php';
require_once 'lib/m3u/KnownCatchupSourceTags.php';
require_once 'vod/vod_standard.php';

class Starnet_Tv implements User_Input_Handler
{
    const ID = 'tv';

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

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Starnet_Plugin $plugin
     */
    public function __construct(Starnet_Plugin $plugin)
    {
        User_Input_Handler_Registry::get_instance()->register_handler($this);

        $this->plugin = $plugin;
        $this->playback_url_is_stream_url = false;
    }

    /**
     * @inheritDoc
     */
    public function get_handler_id()
    {
        return static::ID . '_handler';
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (!isset($user_input->control_id))
            return null;

        switch ($user_input->control_id) {
            case GUI_EVENT_TIMER:
                $post_action = null;
                $epg_manager = $this->plugin->get_epg_manager();
                if ($epg_manager === null) {
                    return null;
                }

                clearstatcache();
                $res = $epg_manager->import_indexing_log();
                if ($res === false) {
                    return Action_Factory::change_behaviour($this->get_action_map(), 1000);
                }
                hd_debug_print("delayed epg: " . json_encode($epg_manager->get_delayed_epg()));

                $delayed_queue = $epg_manager->get_delayed_epg();
                $epg_manager->clear_delayed_epg();
                foreach ($delayed_queue as $channel_id) {
                    hd_debug_print("Refresh EPG for channel ID: $channel_id");
                    $day_start_ts = strtotime(date("Y-m-d")) + get_local_time_zone_offset();
                    $day_epg = $this->plugin->get_day_epg($channel_id, $day_start_ts, $plugin_cookies);
                    $post_action = Action_Factory::update_epg($channel_id, true, $day_start_ts, $day_epg, $post_action);
                }

                return $post_action;

            case GUI_EVENT_PLAYBACK_STOP:
                $channel = $this->plugin->get_channel_info($user_input->plugin_tv_channel_id, true);
                if (safe_get_value($channel, M3uParser::COLUMN_ADULT, 0) != 0) break;

                $this->plugin->update_tv_history($user_input->plugin_tv_channel_id);

                if (isset($user_input->playback_stop_pressed) || isset($user_input->playback_power_off_needed)) {
                    return Action_Factory::invalidate_folders(array(Starnet_Tv_Groups_Screen::ID));
                }
        }

        return null;
    }

    public function get_action_map()
    {
        hd_debug_print(null, true);

        return array(
            GUI_EVENT_PLAYBACK_STOP => User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLAYBACK_STOP),
            GUI_EVENT_TIMER => User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER),
        );
    }

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array
     */
    public function get_tv_info(MediaURL $media_url, &$plugin_cookies)
    {
        if (!$this->plugin->load_channels($plugin_cookies)) {
            hd_debug_print("Channels not loaded!");
            return array();
        }

        $this->playback_runtime = PHP_INT_MAX;

        $buffering = $this->plugin->get_setting(PARAM_BUFFERING_TIME, 1000);
        $archive_delay = $this->plugin->get_setting(PARAM_ARCHIVE_DELAY_TIME, 60);
        $group_all = $this->plugin->get_group(TV_ALL_CHANNELS_GROUP_ID, PARAM_GROUP_SPECIAL);
        $pass_sex = $this->plugin->get_parameter(PARAM_ADULT_PASSWORD);

        $groups_order = array_merge(empty($group_all) ? array() : array($group_all), $this->plugin->get_groups_by_order());
        $groups = array();
        foreach ($groups_order as $group_row) {
            if (empty($group_row)
                || ($group_row[COLUMN_GROUP_ID] !== TV_ALL_CHANNELS_GROUP_ID && $this->plugin->get_channels_order_count($group_row[COLUMN_GROUP_ID]) === 0)) {
                continue;
            }

            $title = $group_row[COLUMN_GROUP_ID] !== TV_ALL_CHANNELS_GROUP_ID ? $group_row[COLUMN_TITLE] : TR::t(TV_ALL_CHANNELS_GROUP_CAPTION);
            $groups[] = array(
                PluginTvGroup::id => $group_row[COLUMN_GROUP_ID],
                PluginTvGroup::caption => $title,
                PluginTvGroup::icon_url => get_cached_image(safe_get_value($group_row, COLUMN_ICON, DEFAULT_GROUP_ICON))
            );
        }

        $ch_num = 1;
        $all_channels = array();
        foreach ($this->plugin->get_groups_order() as $group_id) {
            $group_id_arr = array();
            if (!empty($group_all)) {
                $group_id_arr[TV_ALL_CHANNELS_GROUP_ID] = '';
            }

            foreach ($this->plugin->get_channels_by_order($group_id) as $channel_row) {
                if (empty($channel_row)) continue;

                $group_id_arr[$group_id] = '';
                $archive = $channel_row[M3uParser::COLUMN_ARCHIVE];
                $all_channels[$channel_row[COLUMN_CHANNEL_ID]] = array(
                    PluginTvChannel::id => $channel_row[COLUMN_CHANNEL_ID],
                    PluginTvChannel::caption => $channel_row[COLUMN_TITLE],
                    PluginTvChannel::group_ids => array_keys($group_id_arr),
                    PluginTvChannel::icon_url => safe_get_value($channel_row, COLUMN_ICON, DEFAULT_CHANNEL_ICON_PATH),
                    PluginTvChannel::number => $ch_num++,

                    PluginTvChannel::have_archive => $archive > 0,
                    PluginTvChannel::is_protected => empty($pass_sex) ? 0 : $channel_row[M3uParser::COLUMN_ADULT],

                    PluginTvChannel::past_epg_days => $archive,
                    PluginTvChannel::future_epg_days => 7, // set default future epg range

                    PluginTvChannel::archive_past_sec => $archive * 86400,
                    PluginTvChannel::archive_delay_sec => $archive_delay,

                    // Buffering time
                    PluginTvChannel::buffering_ms => $buffering,
                    PluginTvChannel::timeshift_hours => $channel_row[M3uParser::COLUMN_TIMESHIFT],

                    PluginTvChannel::playback_url_is_stream_url => $this->playback_url_is_stream_url,
                    PluginTvChannel::ext_epg_enabled => true,
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

        $fav_group = $this->plugin->get_group(TV_FAV_GROUP_ID, PARAM_GROUP_SPECIAL);

        $tv_info = array(
            PluginTvInfo::show_group_channels_only => true,

            PluginTvInfo::groups => $groups,
            PluginTvInfo::channels => array_values($all_channels),

            PluginTvInfo::favorites_supported => true,
            PluginTvInfo::favorites_icon_url => $fav_group[COLUMN_ICON],

            PluginTvInfo::initial_channel_id => (string)$media_url->channel_id,
            PluginTvInfo::initial_group_id => $initial_group_id,

            PluginTvInfo::initial_is_favorite => $initial_is_favorite,
            PluginTvInfo::favorite_channel_ids => $this->plugin->get_channels_order(TV_FAV_GROUP_ID),

            PluginTvInfo::initial_archive_tm => isset($media_url->archive_tm) ? (int)$media_url->archive_tm : -1,

            PluginTvInfo::epg_font_size => $this->plugin->get_bool_parameter(PARAM_EPG_FONT_SIZE, false)
                ? PLUGIN_FONT_SMALL
                : PLUGIN_FONT_NORMAL,

            PluginTvInfo::actions => $this->get_action_map(),
            PluginTvInfo::timer => Action_Factory::timer(1000),
        );

        if ($this->plugin->get_bool_setting(PARAM_SHOW_EXT_EPG) && $this->plugin->is_ext_epg_exist()) {
            $playlist_id = $this->plugin->get_active_playlist_id();
            $content = '';
            foreach ($all_channels as $k => $v) {
                $content .= "$k=$playlist_id-$k" . PHP_EOL;
            }

            if (!empty($content) && file_put_contents(get_temp_path("channel_ids.txt"), $content) !== false) {
                $tv_info[PluginTvInfo::ext_epg_enabled] = true;
                $tv_info[PluginTvInfo::ext_epg_base_url] = get_noslash_trailed_path(get_plugin_cgi_url());
                $tv_info[PluginTvInfo::ext_epg_channel_ids_url] = get_plugin_cgi_url("channels");
            }
        }

        return $tv_info;
    }

    /**
     * @param string $channel_id
     * @return array
     */
    public function jump_to_channel($channel_id)
    {
        $channel = $this->plugin->get_channel_info($channel_id, true);
        if (empty($channel)) {
            return null;
        }

        $group_id = $channel[COLUMN_GROUP_ID];
        $pos = array_search($channel_id, $this->plugin->get_channels_order($group_id));
        return Action_Factory::close_and_run(
            Action_Factory::open_folder(
                Starnet_Tv_Channel_List_Screen::get_media_url_string($group_id),
                $group_id,
                null,
                null,
                User_Input_Handler_Registry::create_action_screen(
                    Starnet_Tv_Channel_List_Screen::ID,
                    ACTION_JUMP_TO_CHANNEL,
                    null,
                    array('number' => $pos)
                )
            )
        );
    }
}
