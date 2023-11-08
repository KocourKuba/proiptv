<?php

require_once 'lib/default_dune_plugin.php';
require_once 'movie_series.php';
require_once 'movie_season.php';
require_once 'movie_variant.php';

class Movie implements User_Input_Handler
{
    const ID = 'movie';

    const WATCHED_FLAG = 'watched';
    const WATCHED_POSITION = 'position';
    const WATCHED_DURATION = 'duration';
    const WATCHED_DATE = 'date';

    /**
     * @var Default_Dune_Plugin
     */
    public $plugin;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string
     */
    public $name_original = '';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var string
     */
    public $poster_url = '';

    /**
     * @var int
     */
    public $length_min = -1;

    /**
     * @var int
     */
    public $year = 0;

    /**
     * @var string
     */
    public $directors_str = '';

    /**
     * @var string
     */
    public $scenarios_str = '';

    /**
     * @var string
     */
    public $actors_str = '';

    /**
     * @var string
     */
    public $genres_str = '';

    /**
     * @var string
     */
    public $rate_imdb = '';

    /**
     * @var string
     */
    public $rate_kinopoisk = '';

    /**
     * @var string
     */
    public $rate_mpaa = '';

    /**
     * @var string
     */
    public $country = '';

    /**
     * @var string
     */
    public $budget = '';

    /**
     * @var string
     */
    public $type = 'movie';

    /**
     * @var array|Movie_Season[]
     */
    public $season_list;

    /**
     * @var array|Movie_Series[]
     */
    public $series_list;

    /**
     * @var array|string[]
     */
    public $variants_list;

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
    public static function get_handler_id()
    {
        return self::ID;
    }

    public function get_action_map()
    {
        User_Input_Handler_Registry::get_instance()->register_handler($this);

        $actions = array();
        $actions[GUI_EVENT_PLAYBACK_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLAYBACK_STOP);

        return $actions;
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

        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (!isset($user_input->control_id) || $user_input->control_id !== GUI_EVENT_PLAYBACK_STOP)
            return null;

        $series_list = array_values($this->series_list);
        $episode = $series_list[$user_input->plugin_vod_series_ndx];

        $watched = (isset($user_input->playback_end_of_stream) && (int)$user_input->playback_end_of_stream !== 0)
                    || ($user_input->plugin_vod_duration - $user_input->plugin_vod_stop_position) < 60;

        $series_idx = empty($episode->id) ? $user_input->plugin_vod_series_ndx : $episode->id;
        $id = $user_input->plugin_vod_id;
        $history_item = new HistoryItem($watched, $user_input->plugin_vod_stop_position, $user_input->plugin_vod_duration, $user_input->plugin_vod_stop_tm);

        hd_debug_print("add movie to history: id: $id, series: $series_idx", true);
        $history_items = &$this->plugin->get_history(HISTORY_MOVIES);
        if ($history_items->has("$id:$series_idx")) {
            $history_items->erase("$id:$series_idx");
        }
        $history_items->set("$id:$series_idx", $history_item);

        $this->plugin->save_history(true);

        if (empty($episode->season_id)) {
            $series_media_url_str = Starnet_Vod_Series_List_Screen::get_media_url_string($user_input->plugin_vod_id);
        } else {
            $series_media_url_str = Starnet_Vod_Series_List_Screen::get_media_url_string($user_input->plugin_vod_id, $episode->season_id);
        }
        return Action_Factory::invalidate_folders(
            array(
                $series_media_url_str,
                Starnet_Vod_Category_List_Screen::get_media_url_string(VOD_GROUP_ID),
                Starnet_Vod_History_Screen::get_media_url_str()
            )
        );
    }

    /**
     * @param $v
     * @return string
     */
    private function to_string($v)
    {
        return $v === null ? '' : (string)$v;
    }

    /**
     * @param $v
     * @param $default_value
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
     * @param string $name
     * @param string $name_original
     * @param string $description
     * @param string $poster_url
     * @param string $length_min
     * @param int $year
     * @param string $directors_str
     * @param string $scenarios_str
     * @param string $actors_str
     * @param string $genres_str
     * @param string $rate_imdb
     * @param string $rate_kinopoisk
     * @param string $rate_mpaa
     * @param string $country
     * @param string $budget
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
        $budget)
    {
        $this->name = $this->to_string($name);
        $this->name_original = $this->to_string($name_original);
        $this->description = $this->to_string($description);
        $this->poster_url = $this->to_string($poster_url);
        $this->length_min = $this->to_int($length_min, -1);
        $this->year = $this->to_int($year, -1);
        $this->directors_str = $this->to_string($directors_str);
        $this->scenarios_str = $this->to_string($scenarios_str);
        $this->actors_str = $this->to_string($actors_str);
        $this->genres_str = $this->to_string($genres_str);
        $this->rate_imdb = $this->to_string($rate_imdb);
        $this->rate_kinopoisk = $this->to_string($rate_kinopoisk);
        $this->rate_mpaa = $this->to_string($rate_mpaa);
        $this->country = $this->to_string($country);
        $this->budget = $this->to_string($budget);
    }

    /**
     * @param string $id
     * @param string $name
     * @param string $season_url
     * @throws Exception
     */
    public function add_season_data($id, $name, $season_url)
    {
        $season = new Movie_Season($id);
        $season->name = $this->to_string($name);
        $season->season_url = $this->to_string($season_url);
        $this->season_list[] = $season;
    }

    /**
     * @param string $id
     * @param string $name
     * @param string $description
     * @param string $playback_url
     * @param bool $playback_url_is_stream_url
     * @throws Exception
     */
    public function add_series_data($id, $name, $description, $playback_url, $season_id = '', $playback_url_is_stream_url = true)
    {
        $series = new Movie_Series($id);
        $series->name = $this->to_string($name);
        $series->series_desc = $this->to_string($description);
        $series->season_id = $this->to_string($season_id);
        $series->playback_url = $this->to_string($playback_url);
        $series->playback_url_is_stream_url = $playback_url_is_stream_url;

        $this->series_list[$id] = $series;
    }

    /**
     * @param $id
     * @param $name
     * @param $description
     * @param $playback_url
     * @param $variants
     * @param $season_id
     * @param $playback_url_is_stream_url
     * @throws Exception
     */
    public function add_series_variants_data($id, $name, $description, $variants, $playback_url = '', $season_id = '', $playback_url_is_stream_url = true)
    {
        $series = new Movie_Series($id);
        $series->name = $this->to_string($name);
        $series->series_desc = $this->to_string($description);
        $series->variants = $variants;
        $series->season_id = $this->to_string($season_id);
        $series->playback_url = $this->to_string($playback_url);
        $series->playback_url_is_stream_url = $playback_url_is_stream_url;
        $this->series_list[$id] = $series;
        $this->variants_list = array_keys($variants);
    }

    /**
     * @return array
     */
    public function get_movie_array()
    {
        return array(
            PluginMovie::name => $this->name,
            PluginMovie::name_original => $this->name_original,
            PluginMovie::description => $this->description,
            PluginMovie::poster_url => $this->poster_url,
            PluginMovie::length_min => $this->length_min,
            PluginMovie::year => $this->year,
            PluginMovie::directors_str => $this->directors_str,
            PluginMovie::scenarios_str => $this->scenarios_str,
            PluginMovie::actors_str => $this->actors_str,
            PluginMovie::genres_str => $this->genres_str,
            PluginMovie::rate_imdb => $this->rate_imdb,
            PluginMovie::rate_kinopoisk => $this->rate_kinopoisk,
            PluginMovie::rate_mpaa => $this->rate_mpaa,
            PluginMovie::country => $this->country,
            PluginMovie::budget => $this->budget
        );
    }

    /**
     * @return bool
     */
    public function has_variants()
    {
        if (empty($this->series_list)) {
            return false;
        }

        $values = array_values($this->series_list);
        $val = $values[0];
        return isset($val->variants) && count($val->variants) > 1;
    }

    /**
     * @param MediaURL $media_url
     * @return array
     */
    public function get_movie_play_info(MediaURL $media_url)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url->get_media_url_str(), true);

        if (!isset($media_url->screen_id)) {
            hd_debug_print("get_movie_play_info: List screen in media url not set: " . $media_url->get_raw_string());
            print_backtrace();
            return array();
        }

        switch ($media_url->screen_id) {
            case Starnet_Vod_Seasons_List_Screen::ID:
                if (!is_array($this->season_list) || count($this->season_list) === 0) {
                    hd_debug_print("get_movie_play_info: Invalid movie: season list is empty");
                    print_backtrace();
                    return array();
                }
                $list = $this->series_list;
                break;
            case Starnet_Vod_Series_List_Screen::ID:
            case Starnet_Vod_Movie_Screen::ID:
                if (!is_array($this->series_list) || count($this->series_list) === 0) {
                    hd_debug_print("get_movie_play_info: Invalid movie: series list is empty");
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

        $sel_id = isset($media_url->episode_id) ? $media_url->episode_id : null;
        $series_array = array();
        $initial_series_ndx = 0;
        $variant = $this->plugin->get_parameter(PARAM_VOD_DEFAULT_VARIANT, 'auto');
        $counter = 0; // series index. Not the same as the key of series list
        $initial_start_array = array();
        foreach ($list as $series) {
            if (isset($series->variants)) {
                if (!array_key_exists($variant, $series->variants)) {
                    $best_var = $series->variants;
                    array_pop($best_var);
                    foreach ($best_var as $key => $var) {
                        $variant = $key;
                    }
                }

                if (isset($series->variants[$variant])) {
                    $playback_url = $series->variants[$variant]->playback_url;
                    $playback_url_is_stream_url = $series->variants[$variant]->playback_url_is_stream_url;
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
            $viewed_series = $this->plugin->get_history(HISTORY_MOVIES)->get("$media_url->movie_id:$series->id");

            if (!is_null($viewed_series) && $viewed_series->watched === false && $viewed_series->duration !== -1) {
                $name .= " [" . format_duration($viewed_series->position) . "]";

                $pos = $viewed_series->position;
                if ($pos < 0)
                    $pos = 0;

                if ($pos > $viewed_series->duration)
                    $pos = 0;
            }

            $initial_start_array[$counter] = $pos * 1000;
            $playback_url = $this->plugin->vod->UpdateDuneParams($playback_url);
            hd_debug_print("Playback movie: $media_url->movie_id, episode: $series->id ($variant)", true);
            hd_debug_print("Url: $playback_url from $initial_start_array[$counter]", true);
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

        return array(
            PluginVodInfo::id => $this->id,
            PluginVodInfo::name => $this->name,
            PluginVodInfo::description => $this->description,
            PluginVodInfo::poster_url => $this->poster_url,
            PluginVodInfo::series => $series_array,
            PluginVodInfo::initial_series_ndx => $initial_series_ndx,
            PluginVodInfo::buffering_ms => (int)$this->plugin->get_parameter(PARAM_BUFFERING_TIME,1000),
            PluginVodInfo::actions => $this->get_action_map(),
            PluginVodInfo::initial_position_ms => $initial_start,
        );
    }
}
