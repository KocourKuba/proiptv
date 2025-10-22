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

class Starnet_Vod_Series_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
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
                if (is_null($movie)) break;

                $menu_items = array();
                if (!isset($this->qualities) || count($this->qualities) < 2) break;

                $current_quality = $this->plugin->get_setting(PARAM_VOD_DEFAULT_QUALITY, 'auto');

                $menu_items[] = $this->plugin->create_menu_item($this,
                    self::ACTION_QUALITY_SELECTED,
                    TR::t('by_default'),
                    $current_quality === 'auto' ? 'gui_skin://small_icons/video_settings.aai' : null,
                    array('quality' => 'auto')
                );

                foreach ($this->qualities as $key => $quality) {
                    if ($key === 'auto') continue;

                    $icon = null;
                    if ((string)$key === $current_quality) {
                        $icon = 'gui_skin://small_icons/video_settings.aai';
                    }
                    $menu_items[] = $this->plugin->create_menu_item($this, self::ACTION_QUALITY_SELECTED, $quality->name, $icon, array('quality' => $key));
                }

                return Action_Factory::show_popup_menu($menu_items);

            case self::ACTION_QUALITY_SELECTED:
                if (!isset($this->qualities)) {
                    break;
                }

                $quality = 'auto';
                foreach ($this->qualities as $key => $value) {
                    if ($user_input->quality === (string)$key) {
                        $quality = $user_input->quality;
                    }
                }

                $this->plugin->set_setting(PARAM_VOD_DEFAULT_QUALITY, $quality);
                $parent_url = MediaURL::decode($user_input->parent_media_url);
                return Action_Factory::change_behaviour($this->get_action_map($parent_url, $plugin_cookies));

            case ACTION_AUDIO:
                $movie = $this->plugin->vod->get_loaded_movie($selected_media_url->movie_id);
                if (is_null($movie)) break;

                $menu_items = array();
                if (!isset($this->audios[$selected_media_url->episode_id]) || count($this->audios[$selected_media_url->episode_id]) < 2) break;

                $audios['auto'] = TR::t('by_default');
                $audios = array_merge($audios, $this->audios[$selected_media_url->episode_id]);
                $selected_audio = safe_get_value($this->default_audio, $selected_media_url->movie_id, 'auto');
                foreach ($audios as $key => $audio) {
                    $name = ($key === 'auto') ? $audio : $audio->name;
                    $icon = null;
                    if ((string)$key === $selected_audio) {
                        $icon = 'gui_skin://small_icons/audiot_settings.aai';
                    }
                    $menu_items[] = $this->plugin->create_menu_item($this, self::ACTION_AUDIO_SELECTED, $name, $icon, array('audio' => $key));
                }

                return Action_Factory::show_popup_menu($menu_items);

            case self::ACTION_AUDIO_SELECTED:
                if (!isset($this->audios[$selected_media_url->episode_id])) {
                    break;
                }

                $audio = 'auto';
                foreach ($this->audios[$selected_media_url->episode_id] as $key => $value) {
                    if ($user_input->audio === (string)$key) {
                        $audio = $user_input->audio;
                        break;
                    }
                }

                $this->default_audio[$selected_media_url->movie_id] = $audio;

                $parent_url = MediaURL::decode($user_input->parent_media_url);
                return Action_Factory::change_behaviour($this->get_action_map($parent_url, $plugin_cookies));

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
                if (!is_limited_apk()) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_PLAY_ITEM,
                        TR::t('tv_screen_external_player'),
                        'play.png',
                        array('external' => true));

                    return Action_Factory::show_popup_menu($menu_items);
                }

                return null;
            default:
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);
        $actions = array(
            GUI_EVENT_KEY_ENTER => $action_play,
            GUI_EVENT_KEY_PLAY => $action_play,
        );

        if ($this->plugin->vod->getVodQuality()) {
            $movie = $this->plugin->vod->get_loaded_movie($media_url->movie_id);
            $variant = $this->plugin->get_setting(PARAM_VOD_DEFAULT_QUALITY, 'auto');
            if (!is_null($movie) && isset($movie->qualities_list) && count($movie->qualities_list) > 1) {
                $q_exist = (in_array($variant, $movie->qualities_list) ? "" : "? ");
                $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this,
                    ACTION_QUALITY,
                    TR::t('vod_screen_quality__1', "$q_exist$variant"));
            }
        }

        if ($this->plugin->vod->getVodAudio()) {
            $movie = $this->plugin->vod->get_loaded_movie($media_url->movie_id);
            if (!is_null($movie)) {
                $selected_audio = safe_get_value($this->default_audio, $media_url->movie_id, 'auto');
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this,
                    ACTION_AUDIO,
                    TR::t('vod_screen_audio__1', $selected_audio));
            }
        }

        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_WATCHED, TR::t('vod_screen_viewed_not_viewed'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);

        return $actions;
    }

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

        hd_debug_print("Series movie: " . pretty_json_format($movie), true);
        $items = array();
        foreach ($movie->series_list as $series_id => $episode) {
            if (isset($media_url->season_id) && $media_url->season_id !== $episode->season_id) continue;

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
                hd_debug_print("Qualities: " . json_encode($episode->qualities), true);
            }

            if (!empty($episode->audios)) {
                $this->audios[$episode->id] = $episode->audios;
                hd_debug_print("Audio: " . pretty_json_format($episode->audios), true);
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => self::make_vod_media_url_str($movie->id, $episode->season_id, $episode->id),
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
