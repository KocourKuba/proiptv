<?php

require_once 'lib/default_dune_plugin.php';
require_once 'movie_series.php';
require_once 'movie_season.php';
require_once 'movie_variant.php';

class Movie implements User_Input_Handler
{
    const ID = 'movie';

    /**
     * @var string
     */
    public $id;

    /**
     * @var array
     */
    public $movie_info;

    /**
     * @var array|Movie_Season[]
     */
    public $seasons_list;

    /**
     * @var array|Movie_Series[]
     */
    public $series_list;

    /**
     * @var array|string[]
     */
    public $qualities_list;

    /**
     * @var array|string[]
     */
    public $audios_list;

    /**
     * @var Default_Dune_Plugin
     */
    private $plugin;

    /**
     * @param string $id
     * @param Default_Dune_Plugin $plugin
     * @throws Exception
     */
    public function __construct($id, $plugin)
    {
        if (is_null($id)) {
            hd_debug_print("Movie::id is null, create dummy movie");
            $id = "-1";
        }

        $this->id = (string)$id;
        $this->plugin = $plugin;
    }

    /**
     * @return string
     */
    public function get_handler_id()
    {
        return static::ID;
    }

    public function __sleep()
    {
        $vars = get_object_vars($this);
        unset($vars['plugin']);
        return array_keys($vars);
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        // handler_id => movie
        // control_id => playback_stop
        // osd_active => 1
        // plugin_vod_id => 99842
        // plugin_vod_series_ndx => 0
        // plugin_vod_stop_position => 43
        // plugin_vod_duration => 3075
        // plugin_vod_start_tm => 1696530358
        // plugin_vod_stop_tm => 1696530376
        // plugin_vod_prebuffering_end_tm => 1696530360
        // playback_end_of_stream => 0
        // playback_mode => plugin_vod
        // playback_browser_activated => 0
        // playback_stop_pressed => 1

        if (!isset($user_input->control_id) || $user_input->control_id !== GUI_EVENT_PLAYBACK_STOP) {
            return null;
        }

        $series_list = array_values($this->series_list);
        hd_debug_print("Series list: " . json_encode($series_list), true);
        $episode = $series_list[$user_input->plugin_vod_series_ndx];

        $watched = (isset($user_input->playback_end_of_stream) && (int)$user_input->playback_end_of_stream !== 0)
            || ($user_input->plugin_vod_duration - $user_input->plugin_vod_stop_position) < 60;

        $series_idx = empty($episode->id) ? $user_input->plugin_vod_series_ndx : $episode->id;
        hd_debug_print("add movie to history: id: $user_input->plugin_vod_id, series: $series_idx", true);

        $invalidate[] = Default_Dune_Plugin::get_group_media_url_str(VOD_GROUP_ID);
        $invalidate[] = Default_Dune_Plugin::get_group_media_url_str(VOD_HISTORY_GROUP_ID);

        if ($user_input->plugin_vod_id === VOD_LIST_GROUP_ID) {
            $movie_id = $episode->id;
            $invalidate[] = Default_Dune_Plugin::get_group_media_url_str(VOD_LIST_GROUP_ID);
        } else {
            $movie_id = $user_input->plugin_vod_id;
            $invalidate[] = Starnet_Vod_Series_List_Screen::make_vod_media_url_str($user_input->plugin_vod_id, $episode->season_id);
        }

        $this->plugin->set_vod_history(
            $movie_id,
            $series_idx,
            array(
                COLUMN_WATCHED => (int)$watched,
                COLUMN_POSITION => $user_input->plugin_vod_stop_position,
                COLUMN_DURATION => $user_input->plugin_vod_duration,
                COLUMN_TIMESTAMP => $user_input->plugin_vod_stop_tm
            )
        );

        return Action_Factory::invalidate_folders($invalidate);
    }

    /**
     * @param string $name
     * @param string $name_original
     * @param string $description
     * @param string $poster_url
     * @param string $length_min
     * @param string $year
     * @param string $directors_str
     * @param string $scenarios_str
     * @param string $actors_str
     * @param string $genres_str
     * @param string $rate_imdb
     * @param string $rate_kinopoisk
     * @param string $rate_mpaa
     * @param string $country
     * @param string $budget
     * @param array $details
     * @param array $rate_details
     */
    public function set_data(
        $name,
        $name_original,
        $description,
        $poster_url,
        $length_min,
        $year,
        $directors_str,
        $scenarios_str,
        $actors_str,
        $genres_str,
        $rate_imdb,
        $rate_kinopoisk,
        $rate_mpaa,
        $country,
        $budget = null,
        $details = array(),
        $rate_details = array())
    {
        $this->movie_info = array(
            PluginMovie::name => $this->to_string($name),
            PluginMovie::name_original => $this->to_string($name_original),
            PluginMovie::description => $this->to_string($description),
            PluginMovie::poster_url => $this->to_string($poster_url),
            PluginMovie::length_min => $this->to_int($length_min, -1),
            PluginMovie::year => $this->to_int($year, -1),
            PluginMovie::directors_str => $this->to_string($directors_str),
            PluginMovie::scenarios_str => $this->to_string($scenarios_str),
            PluginMovie::actors_str => $this->to_string($actors_str),
            PluginMovie::genres_str => $this->to_string($genres_str),
            PluginMovie::rate_imdb => $this->to_string($rate_imdb),
            PluginMovie::rate_kinopoisk => $this->to_string($rate_kinopoisk),
            PluginMovie::rate_mpaa => $this->to_string($rate_mpaa),
            PluginMovie::country => $this->to_string($country),
            PluginMovie::budget => $this->to_string($budget),
            PluginMovie::details => $details,
            PluginMovie::rate_details => $rate_details,
        );
    }

    /**
     * @param string|null $v
     * @return string
     */
    public function to_string($v)
    {
        return $v === null ? '' : (string)$v;
    }

    /**
     * @param string|int $v
     * @param string|int $default_value
     * @return int
     */
    private function to_int($v, $default_value)
    {
        $v = (string)$v;
        if (!is_numeric($v)) {
            return $default_value;
        }
        $v = (int)$v;
        return $v <= 0 ? $default_value : $v;
    }

    /**
     * @param Movie_Season $movie_season
     * @throws Exception
     */
    public function add_season_data($movie_season)
    {
        $this->seasons_list[] = $movie_season;
    }

    /**
     * @param Movie_Series $movie_series
     * @throws Exception
     */
    public function add_series_data($movie_series)
    {
        $this->series_list[$movie_series->id] = $movie_series;
        if (!empty($series->qualities)) {
            $this->qualities_list = array_keys($series->qualities);
        }
        if (!empty($series->audios)) {
            $this->audios_list = array_keys($series->audios);
        }
    }

    /**
     * @return bool
     */
    public function has_seasons()
    {
        return (is_array($this->seasons_list) && !empty($this->seasons_list));
    }

    /**
     * @return bool
     */
    public function has_series()
    {
        return (is_array($this->series_list) && !empty($this->series_list));
    }

    /**
     * @return bool
     */
    public function has_qualities()
    {
        if (!$this->has_series()) {
            return false;
        }

        $values = array_values($this->series_list);
        $val = $values[0];
        return isset($val->qualities) && count($val->qualities) > 1;
    }

    /**
     * @return bool
     */
    public function has_audios()
    {
        if (!$this->has_series()) {
            return false;
        }

        $values = array_values($this->series_list);
        $val = $values[0];
        return isset($val->audios) && count($val->audios) > 1;
    }

    /**
     * @param MediaURL $media_url
     * @return array
     */
    public function get_movie_play_info(MediaURL $media_url)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        if (!isset($media_url->screen_id)) {
            hd_debug_print("get_movie_play_info: List screen in media url not set: " . $media_url->get_raw_string());
            print_backtrace();
            return array();
        }

        switch ($media_url->screen_id) {
            case Starnet_Vod_Seasons_List_Screen::ID:
                if (!$this->has_seasons()) {
                    hd_debug_print("get_movie_play_info: Invalid movie: season list is empty");
                    print_backtrace();
                    return array();
                }
                $list = $this->series_list;
                break;

            case Starnet_Vod_Series_List_Screen::ID:
            case Starnet_Vod_Movie_Screen::ID:
                if (!$this->has_series()) {
                    hd_debug_print("get_movie_play_info: Invalid movie: series list is empty");
                    print_backtrace();
                    return array();
                }
                $list = $this->series_list;
                break;

            case Starnet_Vod_List_Screen::ID:
                if (empty($this->series_list)) {
                    hd_debug_print("get_movie_play_info: Invalid movie playlist: list is empty");
                    print_backtrace();
                    return array();
                }
                $list = $this->series_list;
                break;

            default:
                hd_debug_print("get_movie_play_info: Unknown list screen: $media_url->screen_id");
                print_backtrace();
                return array();
        }

        $sel_id = safe_get_member($media_url, 'episode_id');
        $series_array = array();
        $initial_series_ndx = 0;
        $variant = $this->plugin->get_setting(PARAM_VOD_DEFAULT_QUALITY, 'auto');
        $counter = 0; // series index. Not the same as the key of series list
        $initial_start_array = array();
        foreach ($list as $series) {
            if (isset($series->qualities)) {
                if (!array_key_exists($variant, $series->qualities)) {
                    $best_var = $series->qualities;
                    array_pop($best_var);
                    foreach ($best_var as $key => $var) {
                        $variant = $key;
                    }
                }

                if (isset($series->qualities[$variant])) {
                    $playback_url = $series->qualities[$variant]->playback_url;
                    $playback_url_is_stream_url = $series->qualities[$variant]->playback_url_is_stream_url;
                } else {
                    $playback_url = $series->playback_url;
                    $playback_url_is_stream_url = $series->playback_url_is_stream_url;
                }
            } else {
                $playback_url = $series->playback_url;
                $playback_url_is_stream_url = $series->playback_url_is_stream_url;
            }

            if (!is_null($sel_id) && $series->id === $sel_id) {
                $initial_series_ndx = $counter;
            }

            $pos = 0;
            $name = $series->name;
            $ids = explode(':', $media_url->movie_id);
            $movie_id = $ids[0];
            if ($media_url->screen_id === Starnet_Vod_List_Screen::ID) {
                $viewed_params = $this->plugin->get_vod_history_params($series->id, $series->id);
            } else {
                $viewed_params = $this->plugin->get_vod_history_params($movie_id, $series->id);
            }
            if (!empty($viewed_params) && $viewed_params[COLUMN_WATCHED] == 0 && $viewed_params[COLUMN_DURATION] != -1) {
                $name .= " [" . format_duration($viewed_params[COLUMN_POSITION]) . "]";

                $pos = $viewed_params[COLUMN_POSITION];
                if ($pos < 0) {
                    $pos = 0;
                }

                if ($pos > $viewed_params[COLUMN_DURATION]) {
                    $pos = 0;
                }
            }

            $initial_start_array[$counter] = $pos * 1000;
            $playback_url = HD::make_ts($playback_url);
            $dune_params = $this->plugin->collect_dune_params();
            if (!empty($dune_params)) {
                $magic = str_replace('=', ':', http_build_query($dune_params, null, ','));
                hd_debug_print("dune_params: $magic");
                $playback_url .= HD::DUNE_PARAMS_MAGIC . $magic;
            }

            hd_debug_print("Url: $playback_url", true);
            hd_debug_print("Playback movie: $media_url->movie_id, episode: $series->id ($variant)", true);
            $series_array[] = array(
                PluginVodSeriesInfo::name => $name,
                PluginVodSeriesInfo::playback_url => $playback_url,
                PluginVodSeriesInfo::playback_url_is_stream_url => $playback_url_is_stream_url,
            );

            $counter++;
        }

        $initial_start = 0;
        if (isset($initial_start_array[$initial_series_ndx])) {
            $initial_start = $initial_start_array[$initial_series_ndx];
        }
        hd_debug_print("starting vod index $initial_series_ndx at position $initial_start", true);

        $info = array(
            PluginVodInfo::id => $this->id,
            PluginVodInfo::name => $this->movie_info[PluginMovie::name],
            PluginVodInfo::description => $this->movie_info[PluginMovie::description],
            PluginVodInfo::poster_url => $this->movie_info[PluginMovie::poster_url],
            PluginVodInfo::series => $series_array,
            PluginVodInfo::initial_series_ndx => $initial_series_ndx,
            PluginVodInfo::buffering_ms => (int)$this->plugin->get_setting(PARAM_BUFFERING_TIME, 1000),
            PluginVodInfo::actions => $this->get_action_map(),
            PluginVodInfo::initial_position_ms => $initial_start,
        );

        hd_debug_print("info: " . json_encode($info), true);
        return $info;
    }

    public function get_action_map()
    {
        User_Input_Handler_Registry::get_instance()->register_handler($this);

        $actions = array();
        $actions[GUI_EVENT_PLAYBACK_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLAYBACK_STOP);

        return $actions;
    }
}
