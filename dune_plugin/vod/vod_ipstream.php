<?php
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
     * @param string $movie_id
     * @return Movie
     * @throws Exception
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);
        $jsonItems = HD::parse_json_file($this->get_vod_cache_file());

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
                HD::ArrayToStr($item->info->country),  // country,
                ''                              // budget
            );

            // case for serials
            if (isset($item->seasons)) {
                foreach ($item->seasons as $season) {
                    $movie->add_season_data($season->season,
                        empty($season->info->plot)
                            ? TR::t('vod_screen_season__1', $season->season)
                            : $season->info->plot
                        ,'');

                    foreach ($season->episodes as $episode) {
                        hd_debug_print("movie playback_url: $episode->video");
                        $movie->add_series_data("$season->season:$episode->episode",
                            TR::t('vod_screen_series__1', $episode->episode), '', $episode->video, $season->season);
                    }
                }
            } else {
                hd_debug_print("movie playback_url: $item->video");
                $movie->add_series_data($movie_id, $item->name, '', $item->video);
            }

            break;
        }

        return $movie;
    }

    /**
     * @param array &$category_list
     * @param array &$category_index
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        if ($this->load_vod_json_full() === false) {
            return;
        }

        $category_list = array();
        $category_index = array();
        $cat_info = array();

        // all movies
        $count = count($this->vod_items);
        $cat_info[Vod_Category::FLAG_ALL] = $count;
        $genres = array();
        $years = array();
        foreach ($this->vod_items as $movie) {
            $category = (string)$movie->category;
            if (empty($category)) {
                $category = TR::load_string('no_category');
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
                ($category === Vod_Category::FLAG_ALL) ? TR::t('vod_screen_all_movies__1', " ($movie_count)") : "$category ($movie_count)");
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
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $keyword
     * @return array
     * @throws Exception
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
     * @param string $query_id
     * @return array
     * @throws Exception
     */
    public function getMovieList($query_id)
    {
        if ($this->vod_items === false) {
            hd_debug_print("failed to load movies");
            return array();
        }

        $movies = array();
        $arr = explode("_", $query_id);
        if ($arr === false) {
            $category_id = $query_id;
        } else {
            $category_id = $arr[0];
        }

        $current_offset = $this->get_next_page($query_id, 0);
        $pos = 0;
        foreach ($this->vod_items as $movie) {
            if ($pos++ < $current_offset) continue;

            $category = $movie->category;
            if (empty($category)) {
                $category = TR::load_string('no_category');
            }

            if ($category_id === Vod_Category::FLAG_ALL || $category_id === $category) {
                $movies[] = self::CreateShortMovie($movie);
            }
        }
        $this->get_next_page($query_id, $pos - $current_offset);

        hd_debug_print("Movies read for query: $query_id - " . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($params, $from_ndx)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $params, from ndx: $from_ndx");

        if ($this->vod_items === false) {
            hd_debug_print("failed to load movies");
            return array();
        }

        if ($from_ndx !== 0) {
            // lazy load not supported
            return array();
        }

        $movies = array();

        $pairs = explode(",", $params);
        $post_params = array();
        foreach ($pairs as $pair) {
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
            $year_from = isset($post_params['from']) ? $post_params['from'] : ~PHP_INT_MAX;
            $year_to = isset($post_params['to']) ? $post_params['to'] : PHP_INT_MAX;

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

    /**
     * @param Object $movie_obj
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
            (string)$movie_obj->name,
            (string)$movie_obj->info->poster,
            TR::t('vod_screen_movie_info__5', $movie_obj->name, $movie_obj->info->year, $country, $genres, $movie_obj->info->rating)
        );
    }
}
