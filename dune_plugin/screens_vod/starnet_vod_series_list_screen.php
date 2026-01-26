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

require_once 'lib/abstract_preloaded_regular_screen.php';
require_once 'lib/user_input_handler_registry.php';

class Starnet_Vod_Series_List_Screen extends Abstract_Preloaded_Regular_Screen
{
    const ID = 'vod_series';
    const ACTION_QUALITY_SELECTED = 'select_quality';
    const ACTION_AUDIO_SELECTED = 'select_audio';

    /**
     * @var array
     */
    protected $qualities;

    /**
     * @var array
     */
    protected $audios;

    /**
     * @var array
     */
    protected $default_audio;

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map($media_url);
    }

    protected function do_get_action_map(MediaURL $media_url)
    {
        hd_debug_print($media_url, true);
        // {"screen_id":"vod_series","movie_id":"movie_118762"}
        // {"screen_id":"vod_series","movie_id":"serial_84649","season_id":"1"}
        $movie = $this->plugin->vod->get_loaded_movie($media_url->movie_id);
        if (is_null($movie)) {
            return array();
        }

        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);
        $actions[GUI_EVENT_KEY_ENTER] = $action_play;
        $actions[GUI_EVENT_KEY_PLAY] = $action_play;

        // movie_id is move id or season id, episode_id not available here, need to collect info from all series
        $q_variant = $this->plugin->get_setting(PARAM_VOD_DEFAULT_QUALITY, 'auto');
        hd_debug_print("Default Quality: $q_variant");
        $a_variant = $this->plugin->get_setting(PARAM_VOD_DEFAULT_AUDIO, 'auto');
        hd_debug_print("Default Audio: $a_variant");

        $season_id = safe_get_value($media_url, 'season_id');
        $qualities = $movie->collect_all_qualities($season_id);
        $audios = $movie->collect_all_audios($season_id);

        if (count($qualities) > 1) {
            if ($q_variant == 'auto') {
                $cur_quality = TR::load('by_default');
            } else {
                $cur_quality = isset($qualities[$q_variant]) ? $qualities[$q_variant] : "???";
            }
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this,
                ACTION_QUALITY,
                TR::t('vod_screen_quality__1', $cur_quality));
        }

        if (count($audios) > 1) {
            if ($a_variant == 'auto') {
                $cur_audio = TR::load('by_default');
            } else {
                $cur_audio = isset($audios[$a_variant]) ? $audios[$a_variant] : "???";
            }
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this,
                ACTION_AUDIO,
                TR::t('vod_screen_audio__1', $cur_audio));
        }

        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_WATCHED, TR::t('vod_screen_viewed_not_viewed'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);

        return $actions;
    }

    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        if (!isset($user_input->selected_media_url)) {
            hd_debug_print("user input selected media url not set", true);
            return null;
        }

        $selected_media_url = MediaURL::decode($user_input->selected_media_url);

        switch ($user_input->control_id) {
            case ACTION_PLAY_ITEM:
                try {
                    $vod_info = $this->plugin->vod->get_vod_info($selected_media_url);
                    $post_action = $this->plugin->vod->vod_player_exec($vod_info, isset($user_input->external));
                } catch (Exception $ex) {
                    hd_debug_print("Movie can't played");
                    print_backtrace_exception($ex);
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'), TR::t('warn_msg2__1', $ex->getMessage()));
                }

                return $post_action;

            case ACTION_QUALITY:
                $movie = $this->plugin->vod->get_loaded_movie($selected_media_url->movie_id);
                if (is_null($movie) || !$movie->has_qualities($selected_media_url->episode_id)) break;

                $cur_quality = $this->plugin->get_setting(PARAM_VOD_DEFAULT_QUALITY, 'auto');
                hd_debug_print("Default Quality: $cur_quality");
                $movie_quality = $movie->get_qualities($selected_media_url->episode_id);
                unset($movie_quality['auto']);
                $qualities = safe_merge_array(array('auto' => TR::t('by_default')), $movie_quality);
                foreach ($qualities as $key => $quality_name) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        self::ACTION_QUALITY_SELECTED,
                        $quality_name,
                        $key == $cur_quality ? 'gui_skin://small_icons/video_settings.aai' : null,
                        array('quality' => $key)
                    );
                }

                return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);

            case self::ACTION_QUALITY_SELECTED:
                $this->plugin->set_setting(PARAM_VOD_DEFAULT_QUALITY, $user_input->quality);
                $parent_url = MediaURL::decode($user_input->parent_media_url);
                return Action_Factory::change_behaviour($this->do_get_action_map($parent_url));

            case ACTION_AUDIO:
                $movie = $this->plugin->vod->get_loaded_movie($selected_media_url->movie_id);
                if (is_null($movie)) break;

                hd_debug_print("Loaded movie " . $movie);
                $cur_quality = $this->plugin->get_setting(PARAM_VOD_DEFAULT_QUALITY, 'auto');
                hd_debug_print("Current quality: $cur_quality");

                $audios['auto'] = TR::t('by_default');
                $movie_audios = $movie->get_audios($selected_media_url->episode_id, $cur_quality);
                unset($movie_audios['auto']);
                $audios = safe_merge_array($audios, $movie_audios);
                if (count($audios) < 2) break;

                $cur_audio = $this->plugin->get_setting(PARAM_VOD_DEFAULT_AUDIO, 'auto');
                foreach ($audios as $key => $audio_name) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        self::ACTION_AUDIO_SELECTED,
                        $audio_name,
                        $key == $cur_audio ? 'gui_skin://small_icons/audio_settings.aai' : null,
                        array('audio' => $key)
                    );
                }

                return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);

            case self::ACTION_AUDIO_SELECTED:
                $this->plugin->set_setting(PARAM_VOD_DEFAULT_AUDIO, $user_input->audio);
                $parent_url = MediaURL::decode($user_input->parent_media_url);
                return Action_Factory::change_behaviour($this->do_get_action_map($parent_url));

            case ACTION_WATCHED:
                $movie = $this->plugin->vod->get_loaded_movie($selected_media_url->movie_id);
                if (is_null($movie)) break;

                $value = $this->plugin->get_vod_history_params($selected_media_url->movie_id, $selected_media_url->episode_id, COLUMN_WATCHED);

                if ($value) {
                    $this->plugin->remove_vod_history_part($selected_media_url->movie_id, $selected_media_url->episode_id);
                } else {
                    $this->plugin->set_vod_history(
                        $selected_media_url->movie_id,
                        $selected_media_url->episode_id,
                        array(COLUMN_WATCHED => 1, COLUMN_TIMESTAMP => time())
                    );
                }

                return Action_Factory::invalidate_folders(array(
                        $user_input->parent_media_url,
                        Default_Dune_Plugin::get_group_media_url_str(VOD_HISTORY_GROUP_ID)
                    )
                );

            case GUI_EVENT_KEY_POPUP_MENU:
                if (is_limited_apk()) break;

                $menu_items[] = $this->plugin->create_menu_item($this,
                    ACTION_PLAY_ITEM,
                    TR::t('tv_screen_external_player'),
                    'play.png',
                    array('external' => true));

                return Action_Factory::show_popup_menu($menu_items);

            default:
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Get MediaURL string representation (json encoded)
     * *
     * @param string $movie_id
     * @param string|null $season_id
     * @param string|null $episode_id
     * @return false|string
     */
    public static function make_vod_media_url_str($movie_id, $season_id = null, $episode_id = null)
    {
        $arr = array(PARAM_SCREEN_ID => static::ID, 'movie_id' => $movie_id);
        if (!empty($season_id)) {
            $arr['season_id'] = $season_id;
        }

        if (!empty($episode_id)) {
            $arr['episode_id'] = $episode_id;
        }

        return MediaURL::encode($arr);
    }
    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $this->qualities = array();
        $this->audios = array();

        $movie = $this->plugin->vod->get_loaded_movie($media_url->movie_id);
        if (is_null($movie) || !$movie->has_series()) {
            hd_debug_print("Movie not loaded: $media_url->movie_id");
            return array();
        }

        hd_debug_print("Series movie: " . $movie, true);
        $items = array();
        foreach ($movie->get_series_list() as $series_id => $episode) {
            if (isset($media_url->season_id) && $media_url->season_id !== $episode->season_id) continue;

            hd_debug_print("series_id: $series_id episode_name: $episode->name", true);
            $viewed_params = $this->plugin->get_vod_history_params($media_url->movie_id, $series_id);
            $color = 15;
            $info = $episode->name;
            if (!empty($viewed_params)) {
                if ($viewed_params[COLUMN_WATCHED]) {
                    $date = format_datetime("d.m.Y H:i", $viewed_params[COLUMN_TIMESTAMP]);
                    $info = TR::t('vod_screen_viewed__2', $episode->name, $date);
                } else if ($viewed_params[COLUMN_DURATION] !== -1) {
                    $info = TR::t('vod_screen_viewed__4',
                        $episode->name,
                        format_duration_seconds($viewed_params[COLUMN_POSITION]),
                        format_duration_seconds($viewed_params[COLUMN_DURATION]),
                        format_datetime("d.m.Y H:i", $viewed_params[COLUMN_TIMESTAMP])
                    );
                }
                $color = 5;
            }

            if (!empty($episode->qualities)) {
                $this->qualities = $episode->qualities;
            }

            if (!empty($episode->audios)) {
                $this->audios[$episode->id] = $episode->audios;
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => self::make_vod_media_url_str($movie->get_id(), $episode->season_id, $episode->id),
                PluginRegularFolderItem::caption => $info,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => 'gui_skin://small_icons/movie.aai',
                    ViewItemParams::item_detailed_info => empty($episode->description) ? $episode->name : $episode->description,
                    ViewItemParams::item_detailed_icon_path => empty($episode->poster) ? 'gui_skin://large_icons/movie.aai' : $episode->poster,
                    ViewItemParams::item_caption_color => $color,
                ),
            );
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_views()
    {
        hd_debug_print(null, true);

        return array(
            $this->plugin->get_screen_view('list_1x11_small_info'),
            $this->plugin->get_screen_view('list_1x11_info'),
        );
    }
}
