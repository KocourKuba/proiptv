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

require_once 'lib/vod/vod_standard.php';
require_once 'lib/xtream/xtream_codes_api.php';

/**
 * xtream codes API
 * API support pagination, search
 * TV Shows can contain seasons
 */
class vod_sharavoz extends vod_standard
{
    const CATEGORY_MODE_TYPE = 'type';
    const CATEGORY_MODE_GENRE = 'genre';

    /**
     * @var xtream_codes_api
     */
    protected $xtream;

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct($plugin);

        $this->xtream = new xtream_codes_api();
    }

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        if (parent::init_vod($provider)) {
            $pass = $this->provider->GetProviderParameter(MACRO_PASSWORD);
            $vod_url = $this->provider->replace_macros($this->provider->getRawApiCommand(API_COMMAND_GET_VOD));
            $this->xtream->init($this->plugin, $vod_url, $pass, $pass, $this->playlist_id);
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function get_category_modes()
    {
        return array(
            self::CATEGORY_MODE_TYPE => TR::t('category'),
            self::CATEGORY_MODE_GENRE => TR::t('genre')
        );
    }

    /**
     * @inheritDoc
     */
    public function get_category_mode()
    {
        $mode = $this->provider->GetProviderParameter(PARAM_VOD_CATEGORY_MODE, self::CATEGORY_MODE_TYPE);
        return $mode === self::CATEGORY_MODE_GENRE ? $mode : self::CATEGORY_MODE_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function set_category_mode($mode)
    {
        $this->provider->SetProviderParameter(PARAM_VOD_CATEGORY_MODE, $mode);
        $this->category_index = null;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);

        if (empty($movie_id)) {
            hd_debug_print('Movie ID is empty!');
            return null;
        }

        $arr = explode('_', $movie_id);
        $stream_id = safe_get_value($arr, 0, $movie_id);
        $stream_type = safe_get_value($arr, 1, xtream_codes_api::VOD);

        $item = $this->xtream->get_stream_info($stream_id, $stream_type);

        if ($item === false) {
            hd_debug_print("failed to load movie: $stream_id from: $stream_type");
            return null;
        }

        $info = safe_get_value($item, 'info', array());

        // VOD response
        //
        // {
        //    "info": {
        //        "kinopoisk_url": "https://www.kinopoisk.ru/film/1130339/",
        //        "name": "Дивизион",
        //        "o_name": "Дивизион",
        //        "cover_big": "https://avatars.mds.yandex.net/get-kinopoisk-image/1704946/adf2412f-0f8d-4090-9381-dd96b5d0fcfb/x1000",
        //        "movie_image": "https://avatars.mds.yandex.net/get-kinopoisk-image/1704946/adf2412f-0f8d-4090-9381-dd96b5d0fcfb/x1000",
        //        "releasedate": 2019,
        //        "episode_run_time": 0,
        //        "youtube_trailer": "",
        //        "director": "Родриго Монте, Висенте Аморин",
        //        "actors": "Эром Кордейру, Силвиу Гиндани
        //        "cast": "",
        //        "description": "В 1997 году Рио-де-Жанейро потрясла волна похищений людей.
        //        "plot": "В 1997 году Рио-де-Жанейро потрясла волна похищений людей.
        //        "age": "16+",
        //        "rating_mpaa": "",
        //        "rating_count_kinopoisk": "48",
        //        "country": "Бразилия",
        //        "genre": "боевик, триллер, драма, криминал",
        //        "backdrop_path": [],
        //        "tmdb_id": "476299",
        //        "rating": "7.9",
        //        "duration_secs": 0,
        //        "duration": null,
        //        "container_extension": "mp4"
        //    },
        // }

        // Serials response
        //
        //{
        //    "info": {
        //        "name": "Уэнсдэй",
        //        "cover": "http://static.media24.cc/static/posters/304d86b6aeab.jpeg",
        //        "releaseDate": "2022",
        //        "episode_run_time": 45,
        //        "youtube_trailer": null,
        //        "director": "Тим Бёртон, Джеймс Маршалл, Ганджа Монтейру",
        //        "cast": "Дженна Ортега
        //        "plot": "Уэнсдэй, дочь Гомеса и Мортиши Аддамс, учится в академии Nevermore.
        //        "last_modified": 1705606092,
        //        "genre": "фэнтези, комедия, криминал, детектив",
        //        "category_id": 3,
        //        "backdrop_path": null
        //    },
        //    "episodes": {
        //        "1": [
        //            {
        //                "id": 26032,
        //                "episode_num": 1,
        //                "series_id": 3,
        //                "title": "Дочь среды - сестра беды",
        //                "container_extension": "mp4",
        //                "movie_image": "http://static.media24.cc/static/posters/26032.jpeg",
        //                "info": {},
        //                "custom_sid": "",
        //                "added": 1656766454,
        //                "season": 1,
        //                "direct_source": ""
        //            },
        //    },
        //

        $age = self::get_data_variant($info, 'age');
        $age_limit = empty($age) ? array() : array(TR::t('vod_screen_age_limit') => $age);

        $movie = new Movie($movie_id, $this->plugin);
        $movie->set_data(
            self::get_data_variant($info, 'name'), // name,
            self::get_data_variant($info, 'o_name'), // name_original,
            self::get_data_variant($info, array('plot', 'description')),  // description,
            self::get_data_variant($info, array('movie_image', 'cover')),  // poster_url,
            self::get_data_variant($info, array('duration', 'episode_run_time')), // length_min,
            self::get_data_variant($info, array('releasedate', 'releaseDate', 'release_date')), // year,
            self::get_data_variant($info, 'director'), // director_str,
            '', // scenario_str,
            self::get_data_variant($info, array('actors', 'cast')), // actors_str,
            self::get_data_variant($info, 'genre'), // genres_str,
            self::get_data_variant($info, 'rating'), // rate_imdb,
            '', //self::get_data_variant($info, 'rating_count_kinopoisk'), // rate_kinopoisk,
            '', // rate_mpaa,
            self::get_data_variant($info, 'country'), // country,
            '',
            array(),
            $age_limit // rate details
        );

        if ($stream_type === xtream_codes_api::VOD) {
            $ext = safe_get_value($info, 'container_extension');
            if (!empty($ext)) {
                // sharavoz incorrect fills container_extension with mp4
                // force to using m3u8 extension
                $stream_id .= ".m3u8";
            }
            $url = $this->xtream->get_stream_url($stream_id);
            hd_debug_print("movie playback_url: $url", true);
            $movie->add_series_data(new Movie_Series($movie_id, $info['name'], new Movie_Playback_Url($url)));
        } else if ($stream_type === xtream_codes_api::SERIES) {
            foreach (safe_get_value($item, 'episodes', array()) as $season_id => $season) {
                if (empty($season_id)) continue;

                $movie_season = new Movie_Season($season_id);
                $season_name = safe_get_value($season, 'name');
                if (!empty($season_name)) {
                    $movie_season->description = $season_name;
                }
                $movie->add_season_data($movie_season);

                foreach ($season as $episode) {
                    $id = safe_get_value($episode, 'id');
                    $ext = safe_get_value($episode, 'container_extension');
                    if (!empty($ext)) {
                        // sharavoz incorrect fills container_extension with mp4
                        // force to using m3u8 extension
                        $id .= ".m3u8";
                    }

                    $playback_url = new Movie_Playback_Url($this->xtream->get_stream_url($id));
                    hd_debug_print("episode playback_url: $playback_url->url", true);

                    $episode_num = safe_get_value($episode, 'episode_num');
                    $movie_serie = new Movie_Series($id, TR::t('vod_screen_series__1', $episode_num), $playback_url, $season_id );
                    $movie_serie->poster = safe_get_value($episode, 'movie_image');
                    $movie_serie->description = safe_get_value($episode, 'title');
                    $movie->add_series_data($movie_serie);
                }
            }
        }

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $mode = $this->get_category_mode();
        $groups = $this->get_catalog_groups($mode);

        $total = 0;
        foreach ($groups as $group) {
            $total += count($group[xtream_codes_api::VOD]) + count($group[xtream_codes_api::SERIES]);
        }

        // without any movie the lists were not loaded: the count is unknown, the categories are still shown
        $this->category_index = array();
        foreach ($groups as $key => $group) {
            $count = count($group[xtream_codes_api::VOD]) + count($group[xtream_codes_api::SERIES]);
            if ($total !== 0 && $count === 0) continue;

            $id = "$mode:$key";
            $caption = $total === 0 ? $group['name'] : "{$group['name']} ($count)";
            $this->category_index[$id] = new Vod_Category($id, $caption);
        }

        hd_debug_print('Categories read: ' . count($this->category_index));

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getMovieList: $query_id");

        $movies = array();

        // pagination is not used. This is a guard to process only one request
        if ($this->is_page_index_stopped($query_id)) {
            return $movies;
        }

        // genre:драма or type:Русские фильмы
        $arr = explode(':', $query_id, 2);
        $groups = $this->get_catalog_groups($arr[0]);
        if (count($arr) === 2 && isset($groups[$arr[1]])) {
            $group = $groups[$arr[1]];

            // the films from new to old. The series have no added time (last_modified
            // is the same for all of them), they follow the films in the server order
            $added = array();
            foreach ($group[xtream_codes_api::VOD] as $id => $stream) {
                $added[$id] = (float)safe_get_value($stream, 'added', 0);
            }

            arsort($added);
            foreach ($added as $id => $time) {
                $movies[] = $this->CreateShortMovie($group[xtream_codes_api::VOD][$id]);
            }

            foreach ($group[xtream_codes_api::SERIES] as $stream) {
                $movies[] = $this->CreateShortMovie($stream);
            }
        }

        $this->stop_page_index($query_id);

        hd_debug_print("Movies read for query: $query_id: " . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        $movies = array();

        // pagination is not used. This is a guard to process only one request
        if ($this->is_page_index_stopped($keyword)) {
            return $movies;
        }

        $enc_keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));

        $this->search(xtream_codes_api::VOD, $enc_keyword, $movies);
        $this->search(xtream_codes_api::SERIES, $enc_keyword, $movies);

        $this->stop_page_index($keyword);

        hd_debug_print('Movies found: ' . count($movies));

        return array_values($movies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param array $data
     * @param array|string $names
     * @return string
     */
    protected static function get_data_variant($data, $names)
    {
        $ret_val = '';
        if (is_array($names)) {
            foreach ($names as $name) {
                if (!empty($data[$name])) {
                    $ret_val = safe_get_value($data, $name);
                    break;
                }
            }
        } else {
            $ret_val = safe_get_value($data, $names);
        }

        return $ret_val;
    }

    /**
     * The whole catalog grouped by the type or the genre of the 'Type | Genre' categories.
     * The common film list has a row per category of a film, so one request gives the films
     * of any group. A series has one category, the one of its first genre, so in the genre
     * mode the series are grouped by the full genre list of the series.
     *
     * @param string $mode category mode
     * @return array group key (type name or genre name in lower case) =>
     *               array('name' => caption, 'vod' => array(id => stream), 'series' => array(id => stream)),
     *               the types in the server order, the genres sorted by name
     */
    protected function get_catalog_groups($mode)
    {
        $by_genre = $mode === self::CATEGORY_MODE_GENRE;
        $vod_type = xtream_codes_api::VOD;
        $series_type = xtream_codes_api::SERIES;

        $groups = array();
        $category_groups = array($vod_type => array(), $series_type => array());
        foreach (array($vod_type, $series_type) as $stream_type) {
            $categories = $this->xtream->get_categories($stream_type);
            if ($categories === false) continue;

            foreach ($categories as $item) {
                $pair = explode('|', $item['category_name']);
                if (count($pair) < 2) continue;

                $name = trim($pair[$by_genre ? 1 : 0]);
                $key = $by_genre ? mb_strtolower($name, 'UTF-8') : $name;
                if (!isset($groups[$key])) {
                    $groups[$key] = array('name' => $name, $vod_type => array(), $series_type => array());
                }

                $category_groups[$stream_type][$item['category_id']] = $key;
            }
        }

        $streams = $this->xtream->get_streams($vod_type);
        if ($streams !== false) {
            $owners = $category_groups[$vod_type];
            foreach ($streams as $stream) {
                if (isset($stream['stream_id'], $stream['category_id'], $owners[$stream['category_id']])) {
                    $groups[$owners[$stream['category_id']]][$vod_type][$stream['stream_id']] = $stream;
                }
            }
        }

        $streams = $this->xtream->get_streams($series_type);
        if ($streams !== false) {
            $owners = $category_groups[$series_type];
            foreach ($streams as $stream) {
                if (!isset($stream[COLUMN_SERIES_ID])) continue;

                $id = $stream[COLUMN_SERIES_ID];
                if ($by_genre) {
                    $genres = explode(',', mb_strtolower((string)safe_get_value($stream, 'genre'), 'UTF-8'));
                    foreach ($genres as $genre) {
                        $genre = trim($genre);
                        if (isset($groups[$genre])) {
                            $groups[$genre][$series_type][$id] = $stream;
                        }
                    }
                } else if (isset($stream['category_id'], $owners[$stream['category_id']])) {
                    $groups[$owners[$stream['category_id']]][$series_type][$id] = $stream;
                }
            }
        }

        if ($by_genre) {
            ksort($groups);
        }

        return $groups;
    }

    /**
     * @param array $movieData
     * @return Short_Movie
     */
    protected function CreateShortMovie($movieData)
    {
        $id = '-1';
        $icon = '';
        if (isset($movieData['stream_id'])) {
            $id = $movieData['stream_id'] . "_" . xtream_codes_api::VOD;
            $icon = (string)safe_get_value($movieData, 'stream_icon');
        } else if (isset($movieData[COLUMN_SERIES_ID])) {
            $id = safe_get_value($movieData, COLUMN_SERIES_ID) . "_" . xtream_codes_api::SERIES;
            $icon = safe_get_value($movieData, 'cover');
        }

        $name = safe_get_value($movieData, 'name');
        $movie = new Short_Movie($id, $name, $icon,
            Short_Movie::make_info($name, '', '', '', safe_get_value($movieData, 'rating'))
        );

        $this->plugin->vod->set_cached_short_movie($movie);

        return $movie;
    }

    /**
     * @param string $stream_type
     * @param string $keyword
     * @param array &$movies
     * @return void
     */
    protected function search($stream_type, $keyword, &$movies)
    {
        $streams = $this->xtream->get_streams($stream_type);
        if ($streams === false) {
            return;
        }

        foreach ($streams as $stream) {
            $name = safe_get_value($stream, 'name');
            if (empty($name)) continue;

            $search = utf8_encode(mb_strtolower($name, 'UTF-8'));
            if (strpos($search, $keyword) !== false) {
                $movie = $this->CreateShortMovie($stream);
                $movies[$movie->id] = $movie;
            }
        }
    }
}
