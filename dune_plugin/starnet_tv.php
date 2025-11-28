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
require_once 'lib/osd_component_factory.php';
require_once 'lib/sleep_timer.php';
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
        if (!is_limited_apk() && $this->plugin->get_bool_parameter(PARAM_SLEEP_TIMER_ENABLED)) {
            // this key used to fire event from background xmltv indexing script
            $actions[EVENT_INDEXING_DONE] = User_Input_Handler_Registry::create_action($this, EVENT_INDEXING_DONE);
            $actions[GUI_EVENT_KEY_A_RED] = User_Input_Handler_Registry::create_action($this, ACTION_SLEEP_TIMER_CLEAR);
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_SLEEP_TIMER_ADD);
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_SLEEP_TIMER);
        }

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

        // handler_id => tv_handler
        // control_id => playback_stop
        // osd_active => 1
        // plugin_tv_channel_id => 204
        // plugin_tv_group_id => group1
        // plugin_tv_gmt => -1
        // playback_end_of_stream => 0
        // play_mode => plugin_tv
        // playback_browser_activated => 0
        // playback_stop_pressed => 1

        $browser_active = isset($user_input->playback_browser_activated) && (int)$user_input->playback_browser_activated === 1;
        switch ($user_input->control_id) {
            case GUI_EVENT_TIMER:
                if (is_limited_apk()) {
                    return $this->plugin->get_import_xmltv_logs_actions($plugin_cookies, Action_Factory::change_behaviour($this->get_action_map(), 1000));
                }

                $sleep_timer = Sleep_Timer::get_sleep_timer();
                hd_debug_print("Sleep time remaining: $sleep_timer seconds", true);

                $comps = array();
                Sleep_Timer::create_estimated_timer_box($sleep_timer, $comps, $user_input);
                $post_action = $sleep_timer ? Action_Factory::change_behaviour($this->get_action_map(), 1000) : null;
                return Action_Factory::update_osd($comps, $post_action);

            case EVENT_INDEXING_DONE:
                return $this->plugin->get_import_xmltv_logs_actions($plugin_cookies);

            case GUI_EVENT_PLAYBACK_STOP:
                $this->plugin->update_tv_history($user_input->plugin_tv_channel_id);
                if (isset($user_input->playback_stop_pressed)) {
                    Sleep_Timer::set_sleep_timer(0);
                }

                if (isset($user_input->playback_stop_pressed) || isset($user_input->playback_power_off_needed)) {
                    return Action_Factory::invalidate_all_folders($plugin_cookies);
                }
                break;

            case ACTION_SLEEP_TIMER:
                if ($browser_active) {
                    return Action_Factory::run_default(GUI_EVENT_KEY_C_YELLOW);
                }

                return Sleep_Timer::show_sleep_timer_dialog($this);

            case Sleep_Timer::CONTROL_SLEEP_TIME_SET:
                if ($browser_active) break;

                $min = (int)$user_input->{Sleep_Timer::CONTROL_SLEEP_TIME_MIN};
                Sleep_Timer::set_timer_op($min);
                Sleep_Timer::set_sleep_timer($min * 60);
                return Action_Factory::change_behaviour($this->get_action_map(), 1000);

            case ACTION_SLEEP_TIMER_ADD:
                if ($browser_active) {
                    return Action_Factory::run_default(GUI_EVENT_KEY_B_GREEN);
                }

                $comps = array();
                $step = $this->plugin->get_parameter(PARAM_SLEEP_TIMER_STEP, 60);
                $sleep_timer = Sleep_Timer::get_sleep_timer() + $step;
                Sleep_Timer::set_sleep_timer($sleep_timer);
                Sleep_Timer::create_estimated_timer_box($sleep_timer, $comps, $user_input, true);
                return Action_Factory::update_osd($comps, Action_Factory::change_behaviour($this->get_action_map(), 1000));

            case ACTION_SLEEP_TIMER_CLEAR:
                if ($browser_active) {
                    return Action_Factory::run_default(GUI_EVENT_KEY_A_RED);
                }

                Sleep_Timer::set_sleep_timer(0);
                $comps = array();
                Sleep_Timer::create_estimated_timer_box(0, $comps, $user_input, true);
                return Action_Factory::update_osd($comps, Action_Factory::change_behaviour($this->get_action_map(), 1000));
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

        Sleep_Timer::set_show_pos($this->plugin->get_parameter(PARAM_SLEEP_TIMER_POS, 'top_right'));
        Sleep_Timer::set_show_time($this->plugin->get_parameter(PARAM_SLEEP_TIMER_COUNTDOWN, 120));
        Sleep_Timer::set_timer_power($this->plugin->get_parameter(PARAM_SLEEP_TIMER_POWER, 0));

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
        $epg_font_size = $this->plugin->get_bool_parameter(PARAM_EPG_FONT_SIZE, false);
        $group_font_size = $this->plugin->get_bool_parameter(PARAM_GROUP_FONT_SIZE, false);
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

            PluginTvInfo::epg_font_size => $epg_font_size ? PLUGIN_FONT_SMALL : PLUGIN_FONT_NORMAL,

            PluginTvInfo::epg_day_use_local_tz => USE_TZ_LOCAL,
            PluginTvInfo::epg_day_shift_sec => 0,

            PluginTvInfo::actions => $this->get_action_map(),
            PluginTvInfo::timer => Action_Factory::timer(1000),
        );

        if ($epg_font_size) {
            $tv_info[PluginTvInfo::epg_page_size] = 16;
        }

        if ($group_font_size) {
            $tv_info[PluginTvInfo::groups_text_size] = 28;
            $tv_info[PluginTvInfo::groups_page_size] = 16;
            $tv_info[PluginTvInfo::channels_text_size] = 28;
            $tv_info[PluginTvInfo::channels_page_size] = 16;
        }

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
}
