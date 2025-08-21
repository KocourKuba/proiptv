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

class vod_ipstream extends vod_standard
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
        $jsonItems = parse_json_file($this->get_vod_cache_file(), false);

        if ($jsonItems === false) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = null;
        foreach ($jsonItems as $item) {
            if (isset($item->id)) {
                $id = (string)$item->id;
            } else if (isset($item->series_id)) {
                $id = $item->series_id . "_serial";
            } else {
                $id = Hashed_Array::hash($item->name);
            }

            if ($id !== $movie_id) {
                continue;
            }

            $duration = "";
            if (isset($item->info->duration_secs)) {
                $duration = (int)$item->info->duration_secs / 60;
            } else if (isset($item->info->episode_run_time)) {
                $duration = (int)$item->info->episode_run_time;
            }

            $movie = new Movie($movie_id, $this->plugin);
            $movie->set_data(
                $item->name,                           // name,
                '',                        // name_original,
                $item->info->plot,                     // description,
                $item->info->poster,                   // poster_url,
                $duration,                             // length_min,
                $item->info->year,                     // year,
                HD::ArrayToStr($item->info->director), // director_str,
                '',                        // scenario_str,
                HD::ArrayToStr($item->info->cast),     // actors_str,
                HD::ArrayToStr($item->info->genre),    // genres_str,
                $item->info->rating,                   // rate_imdb,
                '',                       // rate_kinopoisk,
                '',                          // rate_mpaa,
                HD::ArrayToStr($item->info->country)   // country,
            );

            // case for serials
            if (isset($item->seasons)) {
                foreach ($item->seasons as $season) {
                    $movie_season = new Movie_Season($season->season);
                    if (!empty($season->info->plot)) {
                        $movie_season->description = $season->info->plot;
                    }
                    $movie->add_season_data($movie_season);

                    foreach ($season->episodes as $episode) {
                        hd_debug_print("movie playback_url: $episode->video");
                        $movie_serie = new Movie_Series("$season->season:$episode->episode",
                            TR::t('vod_screen_series__1', $episode->episode), $episode->video,
                            $season->season
                        );
                        $movie->add_series_data($movie_serie);
                    }
                }
            } else {
                hd_debug_print("movie playback_url: $item->video");
                $movie->add_series_data(new Movie_Series($movie_id, $item->name, $item->video));
            }

            break;
        }

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        if ($this->load_vod_json_full() === false) {
            return false;
        }

        $category_list = array();
        $category_index = array();
        $cat_info = array();

        // all movies
        $count = count($this->vod_items);
        $cat_info[Vod_Category::FLAG_ALL_MOVIES] = $count;
        $genres = array();
        $years = array();
        foreach ($this->vod_items as $movie) {
            $category = (string)$movie->category;
            if (empty($category)) {
                $category = TR::load('no_category');
            }

            if (!array_key_exists($category, $cat_info)) {
                $cat_info[$category] = 0;
            }

            ++$cat_info[$category];

            // collect filters information
            $years[(int)$movie->info->year] = $movie->info->year;
            foreach ($movie->info->genre as $genre) {
                $genres[$genre] = $genre;
            }
        }

        foreach ($cat_info as $category => $movie_count) {
            $cat = new Vod_Category($category,
                ($category === Vod_Category::FLAG_ALL_MOVIES) ? TR::t('vod_screen_all_movies__1', " ($movie_count)") : "$category ($movie_count)");
            $category_list[] = $cat;
            $category_index[$category] = $cat;
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

        hd_debug_print("Categories read: " . count($category_list));
        return true;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print($keyword);
        if ($this->vod_items === false) {
            hd_debug_print("failed to load movies");
            return array();
        }

        $movies = array();
        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));
        foreach ($this->vod_items as $item) {
            $search = utf8_encode(mb_strtolower($item->name, 'UTF-8'));
            if (strpos($search, $keyword) !== false) {
                $movies[] = self::CreateShortMovie($item);
            }
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * @param object $movie_obj
     * @return Short_Movie
     */
    protected static function CreateShortMovie($movie_obj)
    {
        if (isset($movie_obj->id)) {
            $id = (string)$movie_obj->id;
        } else if (isset($movie_obj->series_id)) {
            $id = $movie_obj->series_id . "_serial";
        } else {
            $id = Hashed_Array::hash($movie_obj->name);
        }

        $genres = HD::ArrayToStr($movie_obj->info->genre);
        $country = HD::ArrayToStr($movie_obj->info->country);

        return new Short_Movie(
            $id,
            $movie_obj->name,
            $movie_obj->info->poster,
            TR::t('vod_screen_movie_info__5', $movie_obj->name, $movie_obj->info->year, $country, $genres, $movie_obj->info->rating)
        );
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

            $category = $movie->category;
            if (empty($category)) {
                $category = TR::load('no_category');
            }

            if ($category_id === Vod_Category::FLAG_ALL_MOVIES || $category_id === $category) {
                $movies[] = self::CreateShortMovie($movie);
            }
        }
        $this->get_next_page($query_id, $pos - $page_idx);

        hd_debug_print("Movies read for query: $query_id - " . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($params)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $params");

        if ($this->vod_items === false) {
            hd_debug_print("failed to load movies");
            return array();
        }

        $movies = array();

        $pairs = explode(",", $params);
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
                $movies[] = self::CreateShortMovie($movie);
            }
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }
}
