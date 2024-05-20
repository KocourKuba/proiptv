<?php
require_once 'vod_standard.php';

class vod_iptvonline extends vod_standard
{
    const API_ACTION_MOVIES = 'movies';
    const API_ACTION_SERIALS = 'serials';
    const API_ACTION_FILTERS = 'filters';

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        parent::init_vod($provider);

        $this->vod_filters = array("source", "year", "country", "genre");
        $this->vod_audio = true;

        return true;
    }

    /**
     * @param string $movie_id +/
     * @return Movie
     * @throws Exception
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        // movies_84636 or serials_84636
        $arr = explode("_", $movie_id);
        hd_debug_print("TryLoadMovie: category: $arr[0], id: $arr[1]");

        $params[self::VOD_GET_PARAM_PATH] = "/$arr[0]/$arr[1]";
        $json = $this->make_json_request($params);

        if ($json === false || $json === null) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movieData = $json->data;
        if ($arr[0] === self::API_ACTION_MOVIES) {
            $audios = array();
            foreach ($movieData->medias->audios as $item) {
                $key = $item->translate;
                $audios[$key] = new Movie_Variant($key, $key, $item->url);
            }
            $movie->add_series_with_variants_data($arr[1], $movieData->medias->title, '', array(), $audios, $movieData->medias->url);
        } else if ($arr[0] === self::API_ACTION_SERIALS) {
            // collect series
            foreach ($movieData->seasons as $season) {
                $movie->add_season_data($season->season,
                    empty($season->info->plot)
                        ? TR::t('vod_screen_season__1', $season->season)
                        : $season->title,
                    '');

                foreach ($season->episodes as $episode) {
                    hd_debug_print("movie playback_url: $episode->url");

                    $audios = array();
                    foreach ($episode->audios as $item) {
                        $key = $item->translate;
                        $audios[$key] = new Movie_Variant($key, $key, $item->url);
                    }

                    $movie->add_series_with_variants_data("$season->season:$episode->episode",
                        TR::t('vod_screen_series__1', $episode->episode),
                        $episode->title,
                        array(),
                        $audios,
                        $episode->url,
                        $season->season);
                }
            }
        }

        $movie->set_data(
            $movieData->ru_title,                     // caption,
            $movieData->orig_title,                   // caption_original,
            $movieData->plot,                         // description,
            $movieData->posters->big,                 // poster_url,
            $movieData->duration / 60,      // length,
            $movieData->year,                         // year,
            $movieData->director,                     // director,
            '',                           // scenario,
            $movieData->cast,                         // actors,
            self::collect_genres($movieData),         // genres,
            $movieData->imdb_rating,                  // rate_imdb,
            $movieData->kinopoisk_rating,             // rate_kinopoisk,
            '',                             // rate_mpaa,
            self::collect_countries($movieData),      // country,
            '',                                // budget
            array(),                                  // details
            array(TR::t('quality') => $movieData->quality)   // rate details
        );

        return $movie;
    }

    /**
     * @param array &$category_list
     * @param array &$category_index
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        $category_list = array();
        $category_index = array();

        $cat = new Vod_Category(self::API_ACTION_MOVIES, TR::t('vod_screen_all_movies'));
        $category_list[] = $cat;
        $category_index[$cat->get_id()] = $cat;
        $cat = new Vod_Category(self::API_ACTION_SERIALS, TR::t('vod_screen_all_serials'));
        $category_list[] = $cat;
        $category_index[$cat->get_id()] = $cat;

        $exist_filters = array();
        $params[self::VOD_GET_PARAM_PATH] = '/' . self::API_ACTION_FILTERS;
        $data = $this->make_json_request($params);
        if (!isset($data->success, $data->data->filter_by) || !$data->success) {
            hd_debug_print("Wrong response on filter request: " . json_encode($data), true);
            return false;
        }

        $exist_filters['source'] = array(
            'title' => TR::load_string('category'),
            'values' => array(
                self::API_ACTION_MOVIES => TR::load_string('vod_screen_all_movies'),
                self::API_ACTION_SERIALS => TR::load_string('vod_screen_all_serials')
            )
        );

        foreach ($data->data->filter_by as $filter) {
            if (!isset($filter->id)) continue;

            if (empty($filter->items)) {
                $exist_filters[$filter->id] = array('title' => $filter->title, 'text' => true);
            } else {
                $exist_filters[$filter->id] = array('title' => $filter->title, 'values' => array(-1 => TR::t('no')));
                foreach ($filter->items as $item) {
                    if ($item->enabled) {
                        $exist_filters[$filter->id]['values'][$item->id] = $item->title;
                    }
                }
            }
        }

        $this->set_filters($exist_filters);

        hd_debug_print("Categories read: " . count($category_list));
        hd_debug_print("Filters count: " . count($exist_filters));
        return true;
    }

    /**
     * @param string $keyword
     * @return array
     * @throws Exception
     */
    public function getSearchList($keyword)
    {
        hd_debug_print("getSearchList $keyword");

        $params[CURLOPT_POSTFIELDS] = array("search" => $keyword);

        $params[self::VOD_GET_PARAM_PATH] = "/" . self::API_ACTION_MOVIES . "?limit=100&page=1";
        $searchRes = $this->make_json_request($params);

        $movies = ($searchRes === false) ? array() : $this->CollectSearchResult(self::API_ACTION_MOVIES, $searchRes, 'search');

        $params[self::VOD_GET_PARAM_PATH] = "/" . self::API_ACTION_SERIALS . "?limit=100&page=1";
        $searchRes = $this->make_json_request($params);
        $serials = ($searchRes === false) ? array() : $this->CollectSearchResult(self::API_ACTION_SERIALS, $searchRes, 'search');

        return array_merge($movies, $serials);
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($params, $from_ndx)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $params, from ndx: $from_ndx");

        $pairs = explode(",", $params);
        $filter_params = array();
        foreach ($pairs as $pair) {
            // country:USA
            // genre:action
            // year:2024
            if (!preg_match("/^(.+):(.+)$/", $pair, $m)) continue;

            $filter = $this->get_filter($m[1]);
            if ($filter === null) continue;

            if (isset($filter['text'])) {
                $filter_params[$m[1]] = $m[2];
            } else if (!empty($filter['values'])) {
                $item_idx = array_search($m[2], $filter['values']);
                if ($item_idx !== false && $item_idx !== -1) {
                    $filter_params[$m[1]] = $item_idx;
                }
            }
        }

        if (empty($filter_params)) {
            return false;
        }

        $param_str = '';
        $query_id = self::API_ACTION_MOVIES;
        foreach ($filter_params as $key => $value) {
            if ($key === 'source') {
                $query_id = $value;
                continue;
            }

            if (!empty($param_str)) {
                $param_str .= "_";
            }
            $param_str .= "$key-$value";
        }

        $post_params[self::VOD_GET_PARAM_PATH] = "/$query_id?limit=100&page=1";
        $post_params[CURLOPT_POSTFIELDS]['features_hash'] = $param_str;
        $json = $this->make_json_request($post_params);

        return $json === false ? array() : $this->CollectSearchResult($query_id, $json, 'filter');
    }

    /**
     * @param string $query_id
     * @return array
     * @throws Exception
     */
    public function getMovieList($query_id)
    {
        $val = $this->get_next_page($query_id);
        $params[self::VOD_GET_PARAM_PATH] = "/$query_id?limit=100&page=$val";
        $json = $this->make_json_request($params);

        return ($json === false || $json === null) ? array() : $this->CollectSearchResult($query_id, $json);
    }

    /**
     * @param string $query_id
     * @param Object $json
     * @param $search string|null
     * @return array
     */
    protected function CollectSearchResult($query_id, $json, $search = null)
    {
        hd_debug_print(null, true);
        hd_debug_print("query_id: $query_id");

        $movies = array();

        $page_idx = is_null($search) ? $query_id : "{$query_id}_$search";
        $current_offset = $this->get_next_page($page_idx, 0);
        if ($current_offset < 0)
            return $movies;

        if (!isset($json->data->items))
            return $movies;

        $data = $json->data;
        foreach ($data->items as $entry) {
            $movie = new Short_Movie(
                "{$query_id}_$entry->id",
                $entry->ru_title,
                $entry->posters->medium,
                TR::t('vod_screen_movie_info__4', $entry->ru_title, $entry->year, self::collect_countries($entry), self::collect_genres($entry))
            );

            $movie->big_poster_url = $entry->posters->big;
            $movies[] = $movie;
        }

        if ($data->pagination->pages === $current_offset) {
            $this->set_next_page($page_idx, -1);
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * @param array|null $params
     * @return bool|object
     */
    protected function make_json_request($params = null)
    {
        if (!$this->provider->request_provider_token()) {
            return false;
        }

        $curl_opt = array();

        if (isset($params[self::VOD_GET_PARAM_PATH])) {
            $curl_opt[self::VOD_GET_PARAM_PATH] = $params[self::VOD_GET_PARAM_PATH];
        }

        if (isset($params[CURLOPT_POSTFIELDS])) {
            $curl_opt[CURLOPT_HTTPHEADER] = array("Content-Type: application/json; charset=utf-8");
            $curl_opt[CURLOPT_POSTFIELDS] = HD::escaped_json_encode($params[CURLOPT_POSTFIELDS]);
        }

        $data = $this->provider->execApiCommand(API_COMMAND_VOD, null, true, $curl_opt);
        if (!isset($data->success, $data->status) || !$data->success || $data->status !== 200) {
            hd_debug_print("Wrong response: " . json_encode($data));
            return false;
        }

        return $data;
    }

    protected static function collect_countries($entry)
    {
        $countries_str = '';
        if (isset($entry->countries)) {
            $countries = array();
            foreach ($entry->countries as $country) {
                if (!empty($country)) {
                    $countries[] = $country;
                }
            }
            $countries_str = implode(", ", $countries);
        }

        return $countries_str;
    }

    protected static function collect_genres($entry)
    {
        $genres_str = '';
        if (isset($entry->genres)) {
            $genres = array();
            foreach ($entry->genres as $genre) {
                if (!empty($genre)) {
                    $genres[] = $genre;
                }
            }
            $genres_str = implode(", ", $genres);
        }

        return $genres_str;
    }
}
