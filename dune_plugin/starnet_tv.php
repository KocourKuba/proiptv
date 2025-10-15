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

    public function get_action_map()
    {
        hd_debug_print(null, true);

        $actions[GUI_EVENT_PLAYBACK_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLAYBACK_STOP);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        if (!isset($user_input->control_id)) {
            hd_debug_print("user input control id not set", true);
            return null;
        }

        switch ($user_input->control_id) {
            case GUI_EVENT_TIMER:
                clearstatcache();
                $res = Epg_Manager_Xmltv::import_indexing_log($this->plugin->get_selected_xmltv_ids());

                if ($res === 0) {
                    hd_debug_print("No imports. Timer stopped");
                    return null;
                }

                if ($res === -1) {
                    return Action_Factory::show_title_dialog(TR::t('err_load_xmltv_source'),
                        null,
                        Default_Dune_Plugin::get_last_error(LAST_ERROR_XMLTV));
                }

                $post_action = null;
                if ($res === 1 || $res === -2) {
                    if ($this->plugin->get_bool_setting(PARAM_PICONS_DELAY_LOAD, false)) {
                        $post_action = Action_Factory::invalidate_all_folders($plugin_cookies, null, $post_action);
                    }

                    $epg_manager = $this->plugin->get_epg_manager();
                    if ($epg_manager === null) {
                        return null;
                    }

                    $delayed_queue = $epg_manager->get_delayed_epg();
                    $epg_manager->clear_delayed_epg();
                    foreach ($delayed_queue as $channel_id) {
                        hd_debug_print("Refresh EPG for channel ID: $channel_id");
                        $day_start_ts = from_local_time_zone_offset(strtotime(date("Y-m-d")));
                        $day_epg = $this->plugin->get_day_epg($channel_id, $day_start_ts, $plugin_cookies);
                        $post_action = Action_Factory::update_epg($channel_id, true, $day_start_ts, $day_epg,
                            $post_action, $this->plugin->is_ext_epg_enabled() && !empty($day_epg));
                    }

                    return $post_action;
                }

                if ($res === 2) {
                    return Action_Factory::change_behaviour($this->get_action_map(), 1000);
                }

                break;

            case GUI_EVENT_PLAYBACK_STOP:
                $this->plugin->update_tv_history($user_input->plugin_tv_channel_id);

                if (isset($user_input->playback_stop_pressed) || isset($user_input->playback_power_off_needed)) {
                    return Action_Factory::invalidate_all_folders($plugin_cookies);
                }
                break;
        }

        return null;
    }

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array
     */
    public function get_tv_info(MediaURL $media_url, &$plugin_cookies)
    {
        if (!$this->plugin->is_channels_loaded() && !$this->plugin->load_channels($plugin_cookies)) {
            hd_debug_print("Channels not loaded!");
            return array();
        }

        $buffering = $this->plugin->get_setting(PARAM_BUFFERING_TIME, 1000);
        $archive_delay = $this->plugin->get_setting(PARAM_ARCHIVE_DELAY_TIME, 60);
        $pass_sex = $this->plugin->get_parameter(PARAM_ADULT_PASSWORD);
        $show_all = $this->plugin->get_bool_setting(PARAM_SHOW_ALL);

        $groups = array();

        if ($show_all) {
            $groups[] = array(
                PluginTvGroup::id => TV_ALL_CHANNELS_GROUP_ID,
                PluginTvGroup::caption => TR::t('plugin_all_channels'),
                PluginTvGroup::icon_url => get_cached_image($this->plugin->get_group_icon(TV_ALL_CHANNELS_GROUP_ID))
            );
        }

        $fav_id = $this->plugin->get_fav_id();
        $all_channels = array();
        foreach ($this->plugin->get_groups_by_order() as $group_row) {
            $group_id = $group_row[COLUMN_GROUP_ID];
            if (!$this->plugin->get_order_count($group_id)) {
                continue;
            }

            $groups[] = array(
                PluginTvGroup::id => $group_id,
                PluginTvGroup::caption => $group_row[COLUMN_TITLE],
                PluginTvGroup::icon_url => get_cached_image(safe_get_value($group_row, COLUMN_ICON, DEFAULT_GROUP_ICON))
            );

            $group_id_arr = array();
            if ($show_all) {
                $group_id_arr[TV_ALL_CHANNELS_GROUP_ID] = '';
            }

            foreach ($this->plugin->get_channels_by_order($group_id) as $channel_row) {
                if (empty($channel_row)) continue;

                $group_id_arr[$group_id] = '';
                $archive = $channel_row[COLUMN_ARCHIVE];
                $all_channels[$channel_row[COLUMN_CHANNEL_ID]] = array(
                    PluginTvChannel::id => $channel_row[COLUMN_CHANNEL_ID],
                    PluginTvChannel::caption => $channel_row[COLUMN_TITLE],
                    PluginTvChannel::group_ids => array_keys($group_id_arr),
                    PluginTvChannel::icon_url => $this->plugin->get_channel_picon($channel_row, true),
                    PluginTvChannel::number => $channel_row[COLUMN_CH_NUMBER],

                    PluginTvChannel::have_archive => $archive > 0,
                    PluginTvChannel::is_protected => empty($pass_sex) ? 0 : $channel_row[COLUMN_ADULT],

                    PluginTvChannel::past_epg_days => $archive,
                    PluginTvChannel::future_epg_days => 7, // set default future epg range

                    PluginTvChannel::archive_past_sec => $archive * 86400,
                    PluginTvChannel::archive_delay_sec => $archive_delay,

                    // Buffering time
                    PluginTvChannel::buffering_ms => $buffering,
                    PluginTvChannel::timeshift_hours => $channel_row[COLUMN_TIMESHIFT],

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

        $show_fav = $this->plugin->get_bool_setting(PARAM_SHOW_FAVORITES);
        $fav_icon = $this->plugin->get_group_icon(TV_FAV_GROUP_ID);
        $tv_info = array(
            PluginTvInfo::show_group_channels_only => true,

            PluginTvInfo::groups => $groups,
            PluginTvInfo::channels => array_values($all_channels),

            PluginTvInfo::favorites_supported => $show_fav,
            PluginTvInfo::favorites_icon_url => get_cached_image($fav_icon),

            PluginTvInfo::initial_channel_id => (string)$media_url->channel_id,
            PluginTvInfo::initial_group_id => $initial_group_id,

            PluginTvInfo::initial_is_favorite => $initial_is_favorite,
            PluginTvInfo::favorite_channel_ids => $this->plugin->get_channels_order($fav_id),

            PluginTvInfo::initial_archive_tm => isset($media_url->archive_tm) ? (int)$media_url->archive_tm : -1,

            PluginTvInfo::epg_font_size => $this->plugin->get_bool_parameter(PARAM_EPG_FONT_SIZE, false)
                ? PLUGIN_FONT_SMALL
                : PLUGIN_FONT_NORMAL,

            PluginTvInfo::actions => $this->get_action_map(),
            PluginTvInfo::timer => Action_Factory::timer(1000),
        );

        if ($this->plugin->is_ext_epg_enabled()) {
            $playlist_id = $this->plugin->get_active_playlist_id();
            $content = '';
            foreach ($all_channels as $k => $v) {
                $content .= sprintf("%s=%s-%s", $k, $playlist_id, Hashed_Array::hash($k)) . PHP_EOL;
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
        hd_debug_print(null, true);

        $channel = $this->plugin->get_channel_info($channel_id);
        if (empty($channel)) {
            hd_debug_print("Unknown channel id: $channel_id", true);
            return null;
        }

        $group_id = $channel[COLUMN_GROUP_ID];
        $pos = array_search($channel_id, $this->plugin->get_channels_order($group_id));
        return Action_Factory::open_folder(
            Default_Dune_Plugin::get_group_media_url_str($group_id),
            $group_id,
            null,
            null,
            User_Input_Handler_Registry::create_screen_action(
                Starnet_Tv_Channel_List_Screen::ID,
                ACTION_JUMP_TO_CHANNEL,
                null,
                array('number' => $pos)
            )
        );
    }
}
