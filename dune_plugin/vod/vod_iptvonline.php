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

class vod_iptvonline extends vod_standard
{
    const API_ACTION_MOVIES = 'movies';
    const API_ACTION_SERIALS = 'serials';
    const API_ACTION_FILTERS = 'filters';
    const API_ACTION_SEARCH = 'search';
    const API_ACTION_FILTER = 'filter';

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
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        // movies_84636 or serials_84636
        $arr = explode("_", $movie_id);
        hd_debug_print("TryLoadMovie: category: $arr[0], id: $arr[1]");

        $params[CURLOPT_CUSTOMREQUEST] = "/$arr[0]/$arr[1]";
        $json = $this->make_json_request($params);

        if ($json === false || $json === null) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movieData = $json->data;
        if ($arr[0] === self::API_ACTION_MOVIES && !isset($movieData->seasons)) {
            $audios = array();
            foreach ($movieData->medias->audios as $item) {
                $key = $item->translate;
                $audios[$key] = new Movie_Variant($key, $key, $item->url);
            }
            $movie->add_series_with_variants_data($arr[1], $movieData->medias->title, '', array(), $audios, $movieData->medias->url);
        } else if ($arr[0] === self::API_ACTION_SERIALS || isset($movieData->seasons)) {
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
     * @param array|null $params
     * @return bool|object
     */
    protected function make_json_request($params = null)
    {
        if (!$this->provider->request_provider_token()) {
            return false;
        }

        $curl_opt = array();

        if (isset($params[CURLOPT_CUSTOMREQUEST])) {
            $curl_opt[CURLOPT_CUSTOMREQUEST] = $params[CURLOPT_CUSTOMREQUEST];
        }

        if (isset($params[CURLOPT_POSTFIELDS])) {
            $curl_opt[CURLOPT_HTTPHEADER] = array("Content-Type: application/json; charset=utf-8");
            $curl_opt[CURLOPT_POSTFIELDS] = escaped_raw_json_encode($params[CURLOPT_POSTFIELDS]);
        }

        $data = $this->provider->execApiCommand(API_COMMAND_GET_VOD, null, true, $curl_opt);
        if (!isset($data->success, $data->status) || !$data->success || $data->status !== 200) {
            hd_debug_print("Wrong response: " . json_encode($data));
            return false;
        }

        return $data;
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

    /**
     * @inheritDoc
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
        $params[CURLOPT_CUSTOMREQUEST] = '/' . self::API_ACTION_FILTERS;
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
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print("getSearchList $keyword");

        $params[CURLOPT_POSTFIELDS] = array("search" => $keyword);

        $movies = array();
        $page_id = self::API_ACTION_MOVIES . "_" . self::API_ACTION_SEARCH;
        $page_idx = $this->get_next_page($page_id);
        if ($page_idx < 0)
            return $movies;

        $params[CURLOPT_CUSTOMREQUEST] = "/" . self::API_ACTION_MOVIES . "?limit=100&page=$page_idx";
        $searchRes = $this->make_json_request($params);

        $movies = ($searchRes === false) ? array() : $this->CollectSearchResult(self::API_ACTION_MOVIES, $searchRes, self::API_ACTION_SEARCH);

        $page_id = self::API_ACTION_SERIALS . "_" . self::API_ACTION_SEARCH;
        $page_idx = $this->get_next_page($page_id);
        if ($page_idx < 0)
            return $movies;

        $params[CURLOPT_CUSTOMREQUEST] = "/" . self::API_ACTION_SERIALS . "?limit=100&page=$page_idx";
        $searchRes = $this->make_json_request($params);
        $serials = ($searchRes === false) ? array() : $this->CollectSearchResult(self::API_ACTION_SERIALS, $searchRes, self::API_ACTION_SEARCH);

        return array_merge($movies, $serials);
    }

    /**
     * @param string $query_id
     * @param Object $json
     * @param string|null $search
     * @return array
     */
    protected function CollectSearchResult($query_id, $json, $search = null)
    {
        hd_debug_print(null, true);
        hd_debug_print("query_id: $query_id");

        $movies = array();
        if (!isset($json->data->items))
            return $movies;

        $page_id = is_null($search) ? $query_id : "{$query_id}_$search";
        $current_idx = $this->get_current_page($page_id);
        if ($current_idx < 0)
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

        if ($data->pagination->pages === $current_idx) {
            hd_debug_print("Last page: {$data->pagination->pages}");
            $this->set_next_page($page_id, -1);
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($params)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $params");

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

        $page_id = $query_id . "_" . self::API_ACTION_FILTER;
        $page_idx = $this->get_next_page($page_id);
        if ($page_idx < 0)
            return array();

        hd_debug_print("filter page_idx:  $page_idx");

        $post_params[CURLOPT_CUSTOMREQUEST] = "/$query_id?limit=100&page=$page_idx";
        $post_params[CURLOPT_POSTFIELDS]['features_hash'] = $param_str;
        $json = $this->make_json_request($post_params);

        return $json === false ? array() : $this->CollectSearchResult($query_id, $json, self::API_ACTION_FILTER);
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        $page_idx = $this->get_next_page($query_id);
        $params[CURLOPT_CUSTOMREQUEST] = "/$query_id?limit=100&page=$page_idx";
        $json = $this->make_json_request($params);

        return ($json === false || $json === null) ? array() : $this->CollectSearchResult($query_id, $json);
    }
}
