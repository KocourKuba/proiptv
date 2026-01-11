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

class vod_glanz extends vod_standard
{
    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        if (parent::init_vod($provider)) {
            $this->vod_filters = array("genre", "from", "to");
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("Try Load Movie: $movie_id");

        if (empty($movie_id)) {
            hd_debug_print("Movie ID is empty!");
            return null;
        }

        if (empty($this->vod_items)) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = null;
        foreach ($this->vod_items as $item) {
            if (isset($item['id'])) {
                $id = (string)$item['id'];
            } else {
                $id = '-1';
            }

            if ($id !== $movie_id) {
                continue;
            }

            $genres = array();
            foreach (safe_get_value($item, 'genres', array()) as $genre) {
                $title = safe_get_value($genre, 'title');
                if (!empty($title)) {
                    $genres[] = $title;
                }
            }
            $genres_str = implode(", ", $genres);

            $movie = new Movie($movie_id, $this->plugin);
            $name = safe_get_value($item, 'name');
            $movie->set_data(
                $name,            // name,
                safe_get_value($item, 'o_name'),          // name_original,
                safe_get_value($item, 'description'),     // description,
                safe_get_value($item, 'cover'),           // poster_url,
                '',           // length_min,
                safe_get_value($item, 'year'),            // year,
                safe_get_value($item, 'director'),        // director_str,
                '',         // scenario_str,
                safe_get_value($item, 'actors'),          // actors_str,
                $genres_str,            // genres_str,
                '',           // rate_imdb,
                '',        // rate_kinopoisk,
                '',           // rate_mpaa,
                safe_get_value($item, 'country')          // country,
            );

            $url = safe_get_value($item, 'url');
            hd_debug_print("movie playback_url: $url");
            $movie->add_series_data(new Movie_Series($movie_id, $name, new Movie_Playback_Url($url)));
            break;
        }

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $perf = new Perf_Collector();
        $perf->reset('start');

        $response = $this->provider->execApiCommandResponseNoOpt(API_COMMAND_GET_VOD, Curl_Wrapper::RET_ARRAY | Curl_Wrapper::CACHE_RESPONSE);
        if (empty($response)) {
            $this->vod_items = false;
            $exception_msg = TR::load('err_load_vod') . "\n\n" . Curl_Wrapper::get_raw_response_headers();
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, $exception_msg);
            return false;
        }

        $this->vod_items = $response;
        $count = count($this->vod_items);
        $this->category_index = array();
        $cat_info = array();

        // all movies
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
            $year = (int)safe_get_value($movie, 'year');
            $years[$year] = $year;
            foreach (safe_get_value($movie, 'genres', array()) as $genre) {
                $id = (int)safe_get_value($genre, 'id');
                $title = safe_get_value($genre, 'title');
                if (!empty($title) && !empty($id)) {
                    $genres[$id] = $title;
                }
            }
        }

        foreach ($cat_info as $category => $movie_count) {
            $this->category_index[$category] = new Vod_Category($category,
                ($category === Vod_Category::FLAG_ALL_MOVIES) ? TR::t('vod_screen_all_movies__1', "($movie_count)") : "$category ($movie_count)");
        }

        ksort($genres);
        krsort($years);

        $exist_filters = array();
        $exist_filters['genre'] = array('title' => TR::t('genre'), 'values' => array(-1 => TR::t('no')));
        $exist_filters['from'] = array('title' => TR::t('year_from'), 'values' => array(-1 => TR::t('no')));
        $exist_filters['to'] = array('title' => TR::t('year_to'), 'values' => array(-1 => TR::t('no')));

        $exist_filters['genre']['values'] += $genres;
        $exist_filters['from']['values'] += $years;
        $exist_filters['to']['values'] += $years;

        $this->set_filter_types($exist_filters);

        $perf->setLabel('end');
        $report = $perf->getFullReport();

        hd_debug_print("Categories read: " . count($this->category_index));
        hd_debug_print("Total items loaded: " . count($this->vod_items));
        hd_debug_print("Load time: {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_print_separator();

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

        if (empty($this->vod_items)) {
            hd_debug_print("failed to load movies");
            return $movies;
        }

        // pagination is not used. This is a guard to process only one request
        if ($this->is_page_index_stopped($query_id)) {
            return $movies;
        }

        $arr = explode('_', $query_id);
        $category_id = isset($arr[1]) ? $arr[0] : $query_id;

        foreach ($this->vod_items as $item) {
            $category = safe_get_value($item, 'category');
            if (empty($category)) {
                $category = TR::load('no_category');
            }

            if ($category_id === Vod_Category::FLAG_ALL_MOVIES || $category_id === $category) {
                $movie = $this->CreateShortMovie($item);
                $movies[$movie->id] = $movie;
            }
        }

        $this->stop_page_index($query_id);

        hd_debug_print("Movies read for query: $query_id - " . count($movies));
        return array_values($movies);
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        $movies = array();
        if (empty($this->vod_items)) {
            hd_debug_print("failed to load movies");
            return $movies;
        }

        // pagination is not used. This is a guard to process only one request
        if ($this->is_page_index_stopped($keyword)) {
            return $movies;
        }

        $enc_keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));
        foreach ($this->vod_items as $item) {
            $name = safe_get_value($item, 'name');
            if (empty($name)) continue;

            $search = utf8_encode(mb_strtolower($name, 'UTF-8'));
            if (strpos($search, $enc_keyword) !== false) {
                $movies[] = $this->CreateShortMovie($item);
            }
        }

        $this->stop_page_index($keyword);

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $query_id");

        $movies = array();

        if (empty($this->vod_items)) {
            hd_debug_print("failed to load movies");
            return $movies;
        }

        // pagination is not used. This is a guard to process only one request
        if ($this->is_page_index_stopped($query_id)) {
            return $movies;
        }

        $pairs = explode(",", $query_id);
        $post_params = array();
        foreach ($pairs as $pair) {
            /** @var array $m */
            if (preg_match("/^(.+):(.+)$/", $pair, $m)) {
                $filter = $this->get_filter_type($m[1]);
                if ($filter !== null && !empty($filter['values'])) {
                    $item_idx = array_search($m[2], $filter['values']);
                    if ($item_idx !== false && $item_idx !== -1) {
                        $post_params[$m[1]] = (int)$item_idx;
                    }
                }
            }
        }

        foreach ($this->vod_items as $movie) {
            $match_genre = !isset($post_params['genre']);
            if (!$match_genre) {
                foreach (safe_get_value($movie, 'genres', array()) as $genre) {
                    if (!isset($post_params['genre']) || (int)$genre['id'] === $post_params['genre']) {
                        $match_genre = true;
                        break;
                    }
                }
            }

            $match_year = false;
            $year_from = safe_get_value($post_params, 'from', ~PHP_INT_MAX);
            $year_to = safe_get_value($post_params, 'to', PHP_INT_MAX);

            $year = (int)safe_get_value($movie, 'year');
            if ($year >= $year_from && $year <= $year_to) {
                $match_year = true;
            }

            if ($match_year && $match_genre) {
                $movies[] = $this->CreateShortMovie($movie);
            }
        }

        $this->stop_page_index($query_id);

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param array $movieData
     * @return Short_Movie
     */
    protected function CreateShortMovie($movieData)
    {
        if (isset($movieData['id'])) {
            $id = (string)$movieData['id'];
        } else {
            $id = '-1';
        }

        $genres = array();
        foreach (safe_get_value($movieData, 'genres', array()) as $genre) {
            $title = safe_get_value($genre, 'title');
            if (!empty($title)) {
                $genres[] = $title;
            }
        }
        $genres_str = implode(", ", $genres);

        $name = safe_get_value($movieData, 'name');
        $movie = new Short_Movie(
            $id,
            safe_get_value($movieData, 'name'),
            safe_get_value($movieData, 'cover'),
            TR::t('vod_screen_movie_info__4',
                $name,
                safe_get_value($movieData, 'year'),
                safe_get_value($movieData, 'country'),
                $genres_str)
        );

        $this->plugin->vod->set_cached_short_movie($movie);

        return $movie;
    }
}
