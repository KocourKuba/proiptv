<?php
require_once 'vod_standard.php';

class vod_sharaclub extends vod_standard
{
    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        $this->vod_filters = array("genre", "from", "to");
        parent::init_vod($provider);
    }

    /**
     * @param string $movie_id
     * @return Movie
     * @throws Exception
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print($movie_id);
        $movie = new Movie($movie_id, $this->plugin);
        $jsonItems = HD::parse_json_file(self::get_vod_cache_file());

        if ($jsonItems === false) {
            hd_debug_print("failed to load movie: $movie_id");
            return $movie;
        }

        foreach ($jsonItems as $item) {
            $id = '-1';
            if (isset($item->id)) {
                $id = (string)$item->id;
            } else if (isset($item->series_id)) {
                $id = $item->series_id . "_serial";
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

            $genres = HD::ArrayToStr($item->info->genre);
            $country = HD::ArrayToStr($item->info->country);

            $movie->set_data(
                $item->name,            // name,
                '',          // name_original,
                $item->info->plot,      // description,
                $item->info->poster,    // poster_url,
                $duration,              // length_min,
                $item->info->year,      // year,
                $item->info->director,  // director_str,
                '',           // scenario_str,
                $item->info->cast,      // actors_str,
                $genres,                // genres_str,
                $item->info->rating,    // rate_imdb,
                '',         // rate_kinopoisk,
                '',            // rate_mpaa,
                $country,               // country,
                ''               // budget
            );

            // case for serials
            if (isset($item->seasons)) {
                foreach ($item->seasons as $season) {
                    $movie->add_season_data($season->season,
                        !empty($season->info->name) ? $season->info->name : TR::t('vod_screen_season__1', $season->season), '');
                    foreach ($season->episodes as $episode) {
                        hd_debug_print("movie playback_url: $episode->video");
                        $movie->add_series_data($episode->id, TR::t('vod_screen_series__1', $episode->episode), '', $episode->video, $season->season);
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
        $jsonItems = HD::DownloadJson($this->GetVodListUrl(), false);
        if ($jsonItems === false) {
            return;
        }

        HD::StoreContentToFile(self::get_vod_cache_file(), $jsonItems);

        $category_list = array();
        $category_index = array();
        $cat_info = array();

        // all movies
        $count = count($jsonItems);
        $cat_info[Vod_Category::FLAG_ALL] = $count;
        $genres = array();
        $years = array();
        foreach ($jsonItems as $movie) {
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
        $movies = array();
        $jsonItems = HD::parse_json_file(self::get_vod_cache_file());
        if ($jsonItems === false) {
            hd_debug_print("failed to load movies");
            return $movies;
        }

        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));
        foreach ($jsonItems as $item) {
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
        $movies = array();

        $jsonItems = HD::parse_json_file(self::get_vod_cache_file());
        if ($jsonItems === false) {
            hd_debug_print("failed to load movies");
            return $movies;
        }

        $arr = explode("_", $query_id);
        if ($arr === false) {
            $category_id = $query_id;
        } else {
            $category_id = $arr[0];
        }

        $current_offset = $this->get_next_page($query_id, 0);
        $pos = 0;
        foreach ($jsonItems as $movie) {
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
     * @param string $params
     * @return array
     * @throws Exception
     */
    public function getFilterList($params)
    {
        hd_debug_print($params);
        $movies = array();

        $jsonItems = HD::parse_json_file(self::get_vod_cache_file());
        if ($jsonItems === false) {
            hd_debug_print("failed to load movies");
            return $movies;
        }

        $pairs = explode(",", $params);
        $post_params = array();
        foreach ($pairs as $pair) {
            if (preg_match("/^(.+):(.+)$/", $pair, $m)) {
                hd_debug_print("Filter: $m[1] Value: $m[2]");
                $filter = $this->get_filter($m[1]);
                if ($filter !== null && !empty($filter['values'])) {
                    $item_idx = array_search($m[2], $filter['values']);
                    if ($item_idx !== false && $item_idx !== -1) {
                        $post_params[$m[1]] = $item_idx;
                        hd_debug_print("Param: $item_idx");
                    }
                }
            }
        }

        foreach ($jsonItems as $movie) {
            $match_genre = !isset($post_params['genre']);
            $info = $movie->info;
            if (!$match_genre) {
                foreach ($info->genre as $genre) {
                    if (!isset($post_params['genre']) || $genre === $post_params['genre']) {
                        $match_genre = true;
                        break;
                    }
                }
            }

            $match_year = false;
            $year_from = isset($post_params['from']) ? $post_params['from'] : ~PHP_INT_MAX;
            $year_to = isset($post_params['to']) ? $post_params['to'] : PHP_INT_MAX;

            if ((int)$info->year >= $year_from && (int)$info->year <= $year_to) {
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
        $id = '-1';
        if (isset($movie_obj->id)) {
            $id = (string)$movie_obj->id;
        } else if (isset($movie_obj->series_id)) {
            $id = $movie_obj->series_id . "_serial";
        }

        $info = $movie_obj->info;
        $genres = HD::ArrayToStr($info->genre);
        $country = HD::ArrayToStr($info->country);
        $movie = new Short_Movie($id, (string)$movie_obj->name, (string)$info->poster);
        $movie->info = TR::t('vod_screen_movie_info__5', $movie_obj->name, $info->year, $country, $genres, $info->rating);

        return $movie;
    }

    /**
     * @param array &$defs
     * @param Starnet_Vod_Filter_Screen $parent
     * @param int $initial
     * @return bool
     */
    public function AddFilterUI(&$defs, $parent, $initial = -1)
    {
        $filters = array("genre", "from", "to");
        hd_debug_print($initial);
        $added = false;
        Control_Factory::add_vgap($defs, 20);
        foreach ($filters as $name) {
            $filter = $this->get_filter($name);
            if ($filter === null) {
                hd_debug_print("no filters with '$name'");
                continue;
            }

            $values = $filter['values'];
            if (empty($values)) {
                hd_debug_print("no filters values for '$name'");
                continue;
            }

            $idx = $initial;
            if ($initial !== -1) {
                $pairs = explode(" ", $initial);
                foreach ($pairs as $pair) {
                    if (strpos($pair, $name . ":") !== false && preg_match("/^$name:(.+)/", $pair, $m)) {
                        $idx = array_search($m[1], $values) ?: -1;
                        break;
                    }
                }
            }

            Control_Factory::add_combobox($defs, $parent, null, $name,
                $filter['title'], $idx, $values, 600, true);

            Control_Factory::add_vgap($defs, 30);
            $added = true;
        }

        return $added;
    }

    /**
     * @param $user_input
     * @return string
     */
    public function CompileSaveFilterItem($user_input)
    {
        $filters = array("genre", "from", "to");
        $compiled_string = "";
        foreach ($filters as $name) {
            $filter = $this->get_filter($name);
            if ($filter !== null && $user_input->{$name} !== -1) {
                if (!empty($compiled_string)) {
                    $compiled_string .= ",";
                }

                $compiled_string .= $name . ":" . $filter['values'][$user_input->{$name}];
            }
        }

        return $compiled_string;
    }

    protected static function get_vod_cache_file()
    {
        return get_temp_path("playlist_vod.json");
    }
}
