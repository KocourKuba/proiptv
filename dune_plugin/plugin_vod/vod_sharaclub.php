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

require_once 'lib/vod/vod_standard.php';

class vod_sharaclub extends vod_standard
{
    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        parent::init_vod($provider);

        $this->vod_filters = array("genre", "from", "to");

        $json_data = $provider->execApiCommandResponseNoOpt(API_COMMAND_ACCOUNT_INFO);
        $data = safe_get_value($json_data, array('data', 'vod'));
        return !empty($data);
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);

        if (empty($movie_id)) {
            hd_debug_print("Movie ID is empty!");
            return null;
        }

        if ($this->vod_items === false) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = null;
        foreach ($this->vod_items as $item) {
            $id = '-1';
            if (isset($item['id'])) {
                $id = (string)safe_get_value($item, 'id');
            } else if (isset($item['series_id'])) {
                $id = safe_get_value($item, 'series_id') . "_serial";
            }
            if ($id !== $movie_id) {
                continue;
            }

            $duration = "";
            $movie_info = safe_get_value($item, 'info');
            if (isset($movie_info['duration_secs'])) {
                $duration = safe_get_value($movie_info, 'duration_secs', 0) / 60;
            } else if (isset($movie_info['episode_run_time'])) {
                $duration = safe_get_value($movie_info, 'episode_run_time', 0);
            }

            $name = safe_get_value($item, 'name');
            $age = safe_get_value($movie_info, 'adult');
            $age_limit = empty($age) ? array() : array(TR::t('vod_screen_age_limit') => "$age+");

            $movie = new Movie($movie_id, $this->plugin);
            $movie->set_data(
                $name,                                // name,
                '',                       // name_original,
                safe_get_value($movie_info, 'plot'), // description,
                safe_get_value($movie_info, 'poster'),   // poster_url,
                $duration,                            // length_min,
                safe_get_value($movie_info, 'year'), // year,
                safe_get_value($movie_info, 'director'), // director_str,
                '',                       // scenario_str,
                safe_get_value($movie_info, 'cast'), // actors_str,
                HD::ArrayToStr(safe_get_value($movie_info, 'genre', array())),   // genres_str,
                safe_get_value($movie_info, 'rating'), // rate_imdb,
                '',                      // rate_kinopoisk,
                '',
                HD::ArrayToStr(safe_get_value($movie_info, 'country', array())), // country,
                '',
                array(), // details
                $age_limit // rate details
            );

            // case for serials
            if (isset($item['seasons'])) {
                foreach (safe_get_value($item['seasons'], array()) as $season) {
                    $season_name = safe_get_value($season, 'season');
                    if (empty($season_name)) continue;

                    $movie_season = new Movie_Season($season_name);

                    $season_info = safe_get_value($season, 'info');
                    $overview = safe_get_value($season_info, 'overview');
                    if (!empty($overview)) {
                        $movie_season->description = $overview;
                    }

                    $air_date = safe_get_value($season_info, 'air_date');
                    if (!empty($air_date)) {
                        $title = empty($movie_season->description) ? $movie_season->name : $movie_season->description;
                        $movie_season->description = TR::t('vod_screen_air_date__2', $title, $air_date);
                    }

                    $movie_season->poster = safe_get_value($season_info, 'poster');

                    $movie->add_season_data($movie_season);

                    foreach (safe_get_value($season, 'episodes', array()) as $episode) {
                        $url = safe_get_value($episode, 'video');
                        hd_debug_print("episode playback_url: $url", true);
                        $movie_serie = new Movie_Series(safe_get_value($episode, 'id'),
                            TR::t('vod_screen_series__1', safe_get_value($episode, 'episode')),
                            new Movie_Playback_Url($url),
                            $season->season
                        );
                        $movie->add_series_data($movie_serie);
                    }
                }
            } else {
                $url = safe_get_value($item, 'video');
                hd_debug_print("movie playback_url: $url");
                $movie->add_series_data(new Movie_Series($movie_id, $name, new Movie_Playback_Url($url)));
            }

            break;
        }

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        $response = $this->provider->execApiCommandResponseNoOpt(API_COMMAND_GET_VOD);
        if ($response !== false) {
            $this->vod_items = $response;
        } else {
            $this->vod_items = false;
            $exception_msg = TR::load('err_load_vod') . "\n\n" . Curl_Wrapper::get_raw_response_headers();
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, $exception_msg);
            return false;
        }

        $this->category_index = array();
        $cat_info = array();

        // all movies
        $count = count($this->vod_items);
        $cat_info[Vod_Category::FLAG_ALL_MOVIES] = $count;
        $genres = array();
        $years = array();
        foreach ($this->vod_items as $movie) {
            $category = safe_get_value($movie, 'category');
            if (empty($category)) {
                $category = TR::load('no_category');
            }

            if (!array_key_exists($category, $cat_info)) {
                $cat_info[$category] = 0;
            }

            ++$cat_info[$category];

            // collect filters information
            $year = safe_get_value($movie, array('info', 'year'), 0);
            $years[$year] = $year;
            foreach (safe_get_value($movie, array('info', 'genre')) as $genre) {
                $genres[$genre] = $genre;
            }
        }

        foreach ($cat_info as $category => $movie_count) {
            $cat = new Vod_Category($category,
                ($category === Vod_Category::FLAG_ALL_MOVIES) ? TR::t('vod_screen_all_movies__1', " ($movie_count)") : "$category ($movie_count)");
            $this->category_index[$category] = $cat;
        }

        ksort($genres);
        krsort($years);

        $filters = array();
        $filters['genre'] = array('title' => TR::t('genre'), 'values' => array(-1 => TR::t('no')));
        $filters['from'] = array('title' => TR::t('year_from'), 'values' => array(-1 => TR::t('no')));
        $filters['to'] = array('title' => TR::t('year_to'), 'values' => array(-1 => TR::t('no')));

        $filters['genre']['values'] += $genres;
        $filters['from']['values'] += $years;
        $filters['to']['values'] += $years;

        $this->set_filters($filters);

        hd_debug_print("Categories read: " . count($this->category_index));
        return true;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print("getSearchList $keyword");

        if ($this->vod_items === false) {
            hd_debug_print("failed to load movies");
            return array();
        }

        $movies = array();
        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));
        foreach ($this->vod_items as $item) {
            $search = utf8_encode(mb_strtolower(safe_get_value($item, 'name'), 'UTF-8'));
            if (strpos($search, $keyword) !== false) {
                $movies[] = $this->CreateShortMovie($item);
            }
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * @param array $movieData
     * @return Short_Movie
     */
    protected function CreateShortMovie($movieData)
    {
        $id = '-1';
        if (isset($movieData['id'])) {
            $id = (string)$movieData['id'];
        } else if (isset($movieData['series_id'])) {
            $id = $movieData['series_id'] . "_serial";
        }

        $name = safe_get_value($movieData, 'name');
        $info = safe_get_value($movieData, 'info');
        $genres = HD::ArrayToStr(safe_get_value($info, 'genre'));
        $country = HD::ArrayToStr(safe_get_value($info, 'country'));
        $movie_info = TR::t('vod_screen_movie_info__5',
            safe_get_value($info, 'year'),
            $name,
            $country,
            $genres,
            safe_get_value($info, 'rating')
        );
        $movie = new Short_Movie($id, $name, safe_get_value($info, 'poster'), $movie_info);

        $this->plugin->vod->set_cached_short_movie($movie);

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        if ($this->vod_items === false) {
            hd_debug_print("failed to load movies");
            return array();
        }

        $page_idx = $this->get_current_page($query_id);
        if ($page_idx < 0)
            return array();

        $movies = array();
        $arr = explode("_", $query_id);
        $category_id = isset($arr[1]) ? $arr[0] : $query_id;

        $pos = 0;
        foreach ($this->vod_items as $movie) {
            if ($pos++ < $page_idx) continue;

            $category = safe_get_value($movie, 'category');
            if (empty($category)) {
                $category = TR::load('no_category');
            }

            if ($category_id === Vod_Category::FLAG_ALL_MOVIES || $category_id === $category) {
                $movies[] = $this->CreateShortMovie($movie);
            }
        }
        $this->get_next_page($query_id, $pos - $page_idx);

        hd_debug_print("Movies read for query: $query_id - " . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $query_id");

        if ($this->vod_items === false) {
            hd_debug_print("failed to load movies");
            return array();
        }

        $movies = array();

        $pairs = explode(",", $query_id);
        $post_params = array();
        foreach ($pairs as $pair) {
            /** @var array $m */
            if (preg_match("/^(.+):(.+)$/", $pair, $m)) {
                $filter = $this->get_filter($m[1]);
                if ($filter !== null && !empty($filter['values'])) {
                    $item_idx = array_search($m[2], $filter['values']);
                    if ($item_idx !== false && $item_idx !== -1) {
                        $post_params[$m[1]] = $filter['values'][$item_idx];
                    }
                }
            }
        }

        foreach ($this->vod_items as $movie) {
            if (isset($post_params['genre'])) {
                $match_genre = in_array($post_params['genre'], $movie->info->genre);
            } else {
                $match_genre = true;
            }

            $match_year = false;
            $year_from = safe_get_value($post_params, 'from', ~PHP_INT_MAX);
            $year_to = safe_get_value($post_params, 'to', PHP_INT_MAX);

            if ((int)$movie->info->year >= $year_from && (int)$movie->info->year <= $year_to) {
                $match_year = true;
            }

            if ($match_year && $match_genre) {
                $movies[] = $this->CreateShortMovie($movie);
            }
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }
}
