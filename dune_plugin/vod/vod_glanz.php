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

require_once 'vod_standard.php';

class vod_glanz extends vod_standard
{
    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        parent::init_vod($provider);

        $this->vod_filters = array("genre", "from", "to");

        return true;
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
            $item = (object)$item;
            if (isset($item->id)) {
                $id = (string)$item->id;
            } else {
                $id = '-1';
            }

            if ($id !== $movie_id) {
                continue;
            }

            $genres = array();
            foreach ($item->genres as $genre) {
                $genre = (object)$genre;
                if (!empty($genre->title)) {
                    $genres[] = $genre->title;
                }
            }
            $genres_str = implode(", ", $genres);

            $movie = new Movie($movie_id, $this->plugin);
            $movie->set_data(
                $item->name,            // name,
                $item->o_name,          // name_original,
                $item->description,     // description,
                $item->cover,           // poster_url,
                '',           // length_min,
                $item->year,            // year,
                $item->director,        // director_str,
                '',         // scenario_str,
                $item->actors,          // actors_str,
                $genres_str,            // genres_str,
                '',           // rate_imdb,
                '',        // rate_kinopoisk,
                '',           // rate_mpaa,
                $item->country          // country,
            );

            hd_debug_print("movie playback_url: $item->url");
            $movie->add_series_data(new Movie_Series($movie_id, $item->name, new Movie_Playback_Url($item->url)));
            break;
        }

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        $perf = new Perf_Collector();
        $perf->reset('start');

        if ($this->load_vod_json_full(true) === false) {
            return false;
        }

        $count = count($this->vod_items);
        $this->category_index = array();
        $cat_info = array();

        // all movies
        $cat_info[Vod_Category::FLAG_ALL_MOVIES] = $count;
        $genres = array();
        $years = array();
        foreach ($this->vod_items as $movie) {
            $movie = (object)$movie;
            $category = (string)$movie->category;
            if (empty($category)) {
                $category = TR::load('no_category');
            }

            if (!array_key_exists($category, $cat_info)) {
                $cat_info[$category] = 0;
            }

            ++$cat_info[$category];

            // collect filters information
            $years[(int)$movie->year] = $movie->year;
            foreach ($movie->genres as $genre) {
                $genre = (object)$genre;
                if (!empty($genre->title) && !empty($genre->id)) {
                    $genres[(int)$genre->id] = $genre->title;
                }
            }
        }

        foreach ($cat_info as $category => $movie_count) {
            $cat = new Vod_Category($category,
                ($category === Vod_Category::FLAG_ALL_MOVIES) ? TR::t('vod_screen_all_movies__1', "($movie_count)") : "$category ($movie_count)");
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

        $perf->setLabel('end');
        $report = $perf->getFullReport();

        hd_debug_print("Categories read: " . count($this->category_index));
        hd_debug_print("Total items loaded: " . count($this->vod_items));
        hd_debug_print("Load time: {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_debug_print_separator();

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print("getSearchList $keyword");

        $movies = array();
        if ($this->vod_items === false) {
            hd_debug_print("failed to load movies");
            return $movies;
        }

        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));
        foreach ($this->vod_items as $item) {
            $item = (object)$item;
            $search = utf8_encode(mb_strtolower($item->name, 'UTF-8'));
            if (strpos($search, $keyword) !== false) {
                $movies[] = $this->CreateShortMovie($item);
            }
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * @param object $movie_obj
     * @return Short_Movie
     */
    protected function CreateShortMovie($movie_obj)
    {
        if (isset($movie_obj->id)) {
            $id = (string)$movie_obj->id;
        } else {
            $id = '-1';
        }

        $genres = array();
        foreach ($movie_obj->genres as $genre) {
            $genre = (object)$genre;
            if (!empty($genre->title)) {
                $genres[] = $genre->title;
            }
        }
        $genres_str = implode(", ", $genres);

        $movie = new Short_Movie(
            $id,
            $movie_obj->name,
            $movie_obj->cover,
            TR::t('vod_screen_movie_info__4', $movie_obj->name, $movie_obj->year, $movie_obj->country, $genres_str)
        );

        $this->plugin->vod->set_cached_short_movie($movie);

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        $movies = array();

        if ($this->vod_items === false) {
            hd_debug_print("failed to load movies");
            return $movies;
        }

        $arr = explode('_', $query_id);
        $category_id = isset($arr[1]) ? $arr[0] : $query_id;

        $page_idx = $this->get_current_page($query_id);
        if ($page_idx < 0)
            return array();

        $pos = 0;
        foreach ($this->vod_items as $movie) {
            if ($pos++ < $page_idx) continue;

            $movie = (object)$movie;
            $category = $movie->category;
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
                        $post_params[$m[1]] = (int)$item_idx;
                    }
                }
            }
        }

        foreach ($this->vod_items as $movie) {
            $movie = (object)$movie;
            $match_genre = !isset($post_params['genre']);
            if (!$match_genre) {
                foreach ($movie->genres as $genre) {
                    if (!isset($post_params['genre']) || (int)$genre['id'] === $post_params['genre']) {
                        $match_genre = true;
                        break;
                    }
                }
            }

            $match_year = false;
            $year_from = safe_get_value($post_params, 'from', ~PHP_INT_MAX);
            $year_to = safe_get_value($post_params, 'to', PHP_INT_MAX);

            if ((int)$movie->year >= $year_from && (int)$movie->year <= $year_to) {
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
