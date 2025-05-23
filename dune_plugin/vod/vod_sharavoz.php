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

require_once 'vod_standard.php';
require_once 'lib/xtream/xtream_codes_api.php';

class vod_sharavoz extends vod_standard
{
    /**
     * @var xtream_codes_api
     */
    protected $xtream;

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
        parent::init_vod($provider);

        $pass = $this->provider->GetParameter(MACRO_PASSWORD);
        $this->xtream->init($this->provider->getRawApiCommand(API_COMMAND_GET_VOD), $pass, $pass);
        $this->xtream->reset_cache();

        return true;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);

        $arr = explode("_", $movie_id);
        $stream_id = safe_get_value($arr, 0, $movie_id);
        $stream_type = safe_get_value($arr, 1, xtream_codes_api::VOD);

        $item = $this->xtream->get_stream_info($stream_id, $stream_type);

        if ($item === false) {
            hd_debug_print("failed to load movie: $stream_id from: $stream_type");
            return null;
        }

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

        $age = self::get_data_variant($item->info, "age");
        $age_limit = empty($age) ? array() : array(TR::t('vod_screen_age_limit') => $age);

        $movie = new Movie($movie_id, $this->plugin);
        $movie->set_data(
            self::get_data_variant($item->info, "name"), // name,
            self::get_data_variant($item->info, "o_name"), // name_original,
            self::get_data_variant($item->info, array("plot", "description")),  // description,
            self::get_data_variant($item->info, array("movie_image", "cover")),  // poster_url,
            self::get_data_variant($item->info, array("duration", "episode_run_time")), // length_min,
            self::get_data_variant($item->info, array("releasedate", "releaseDate", "release_date")), // year,
            self::get_data_variant($item->info, "director"), // director_str,
            '', // scenario_str,
            self::get_data_variant($item->info, array("actors", "cast")), // actors_str,
            self::get_data_variant($item->info, "genre"), // genres_str,
            self::get_data_variant($item->info, "rating"), // rate_imdb,
            self::get_data_variant($item->info, "rating_count_kinopoisk"), // rate_kinopoisk,
            '', // rate_mpaa,
            self::get_data_variant($item->info, "country"), // country,
            '',
            array(),
            $age_limit // rate details
        );

        if ($stream_type === xtream_codes_api::VOD) {
            $id = $stream_id;
            /*
            if (!empty($item->info->container_extension)) {
                $id .= ".{$item->info->container_extension}";
            }
            */
            $url = $this->xtream->get_stream_url($id);
            hd_debug_print("movie playback_url: $url");
            $movie->add_series_data($movie_id, $item->info->name, '', $url);
        } else if ($stream_type === xtream_codes_api::SERIES) {
            foreach ($item->episodes as $season_id => $season) {
                $movie->add_season_data($season_id, !empty($season->name) ? $season->name : TR::t('vod_screen_season__1', $season_id), '');
                foreach ($season as $episode) {
                    $name = TR::t('vod_screen_series__2', $episode->episode_num, (empty($episode->title) ? "" : $episode->title));
                    $id = $episode->id;
                    if (!empty($episode->container_extension)) {
                        $id .= ".$episode->container_extension";
                    }
                    $url = $this->xtream->get_stream_url($id);
                    hd_debug_print("episode playback_url: $url");
                    $movie->add_series_data($episode->id, $name, '', $url, $season_id, $episode->movie_image);
                }
            }
        }

        return $movie;
    }

    /**
     * @param object $data
     * @param array|string $names
     * @return string
     */
    protected static function get_data_variant($data, $names)
    {
        $ret_val = '';
        if (is_array($names)) {
            foreach ($names as $name) {
                if (!empty($data->{$name})) {
                    $ret_val = $data->{$name};
                    break;
                }
            }
        } else if (!empty($data->{$names})) {
            $ret_val = $data->{$names};
        }

        return $ret_val;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        hd_debug_print(null, true);

        $category_tree = array();
        $this->parse_categories(xtream_codes_api::VOD, $category_tree);
        $this->parse_categories(xtream_codes_api::SERIES, $category_tree);

        $category_count = 0;
        foreach ($category_tree as $key => $value) {
            $category = new Vod_Category($key, $key);
            $gen_arr = array();
            foreach ($value as $sub_cat_name) {
                $sub_pair = explode("_", $sub_cat_name);
                hd_debug_print("Sub Category ($sub_pair[2]): $sub_pair[0] ($sub_pair[1])", true);
                $gen_arr[] = new Vod_Category($sub_pair[1] . "_" . $sub_pair[2], $sub_pair[0], $category);
            }

            $category->set_sub_categories($gen_arr);
            $category_count += count($gen_arr);

            $category_list[] = $category;
            $category_index[$category->get_id()] = $category;
        }

        hd_debug_print("Categories read: $category_count");

        return true;
    }

    /**
     * @param string $stream_type
     * @param array &$category_tree
     */
    protected function parse_categories($stream_type, &$category_tree)
    {
        $categories = $this->xtream->get_categories($stream_type);
        if ($categories !== false) {
            foreach ($categories as $item) {
                hd_debug_print("$item->category_id ($item->category_name)", true);
                $pair = explode("|", $item->category_name);

                $parent_id = trim($pair[0]);
                $query_id = trim($pair[1]) . "_" . $item->category_id . "_" . $stream_type;
                $category_tree[$parent_id][] = $query_id;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($query_id);

        $page_idx = $this->get_current_page($query_id);
        if ($page_idx < 0)
            return array();

        // Фильмы_1_vod
        $arr = explode("_", $query_id);
        $category_id = safe_get_value($arr, 1, $query_id);

        $vod_items = $this->xtream->get_streams($arr[2], $category_id);
        $pos = 0;
        $movies = array();
        foreach ($vod_items as $movie) {
            if ($pos++ < $page_idx) continue;

            $category = (string)$movie->category_id;
            if (empty($category)) {
                $category = TR::load('no_category');
            }

            if ($category_id === Vod_Category::FLAG_ALL_MOVIES || $category_id === $category) {
                $movies[] = self::CreateShortMovie($movie);
            }
        }
        $this->get_next_page($query_id, $pos - $page_idx);

        hd_debug_print("Movies read for query: $query_id: " . count($movies));
        return $movies;
    }

    /**
     * @param object $movie_obj
     * @return Short_Movie
     */
    protected static function CreateShortMovie($movie_obj)
    {
        $id = '-1';
        $icon = '';
        if (isset($movie_obj->stream_id)) {
            $id = $movie_obj->stream_id . "_" . xtream_codes_api::VOD;
            $icon = (string)$movie_obj->stream_icon;
        } else if (isset($movie_obj->series_id)) {
            $id = $movie_obj->series_id . "_" . xtream_codes_api::SERIES;
            $icon = (string)$movie_obj->cover;
        }

        return new Short_Movie(
            $id,
            (string)$movie_obj->name,
            $icon,
            TR::t('vod_screen_movie_info__2', $movie_obj->name, $movie_obj->rating)
        );
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print($keyword);

        $movies = array();

        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));

        $this->search(xtream_codes_api::VOD, $keyword, $movies);
        $this->search(xtream_codes_api::SERIES, $keyword, $movies);

        hd_debug_print("Movies found: " . count($movies));

        return array_values($movies);
    }

    protected function search($stream_type, $keyword, &$movies)
    {
        $categories = $this->xtream->get_categories($stream_type);
        if ($categories === false) {
            return;
        }

        $streams = $this->xtream->get_streams($stream_type);
        if ($streams === false) {
            return;
        }

        foreach ($streams as $stream) {
            $search = utf8_encode(mb_strtolower($stream->name, 'UTF-8'));
            if (strpos($search, $keyword) !== false) {
                $id = $stream_type === xtream_codes_api::SERIES ? $stream->series_id : $stream->stream_id;
                $movies[$id] = self::CreateShortMovie($stream);
            }
        }
    }
}
