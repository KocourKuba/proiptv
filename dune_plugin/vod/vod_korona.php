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

class vod_korona extends vod_standard
{
    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        parent::init_vod($provider);

        //$this->vod_filters = array("source", "year", "country", "genre");

        return true;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        // movies_84636 or serials_84636
        hd_debug_print("TryLoadMovie: $movie_id");
        $arr = explode("_", $movie_id);
        $id = safe_get_value($arr, 1, $movie_id);

        $json = $this->make_json_request("/video/$id");

        if ($json === false || $json === null) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movieData = $json->data;
        if (isset($movieData->seasons)) {
            // collect series
            foreach ($movieData->seasons as $season) {
                $movie_season = new Movie_Season($season->id, $season->number);
                if (!empty($season->name)) {
                    $movie_season->description = $season->name;
                }
                $movie->add_season_data($movie_season);

                foreach ($season->series as $episode) {
                    hd_debug_print("movie playback_url: {$episode->files['url']}");
                    $movie_serie = new Movie_Series($episode->id, TR::t('vod_screen_series__1', $episode->number), $episode->files[0]->url, $season->id);
                    $movie_serie->description = $episode->name;
                    $movie->add_series_data($movie_serie);
                }
            }
        } else {
            hd_debug_print("movie playback_url: {$movieData->files[0]->url}");
            $movie_serie = new Movie_Series($movie_id, $movieData->name, $movieData->files[0]->url);
            $movie->add_series_data($movie_serie);
        }

        $movie->set_data(
            $movieData->name,
            $movieData->original_name,
            $movieData->description,
            $movieData->poster,
            $movieData->time,
            $movieData->year,
            $movieData->director,
            '',
            $movieData->actors,
            self::collect_genres($movieData),
            $movieData->rating,
            '',
            '',
            $movieData->country
        );

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        $jsonItems = $this->make_json_request("/cat");
        if ($jsonItems === false || empty($jsonItems->data)) {
            return false;
        }

        $category_list = array();
        $category_index = array();

        foreach ($jsonItems->data as $node) {
            $id = (string)$node->id;
            $category = new Vod_Category($id, "$node->name ($node->count)");

            // fetch genres for category
            $genres = $this->make_json_request("/cat/$id/genres");
            if ($genres === false) {
                continue;
            }

            $gen_arr = array();
            if (isset($genres->data)) {
                foreach ($genres->data as $genre) {
                    $gen_arr[] = new Vod_Category((string)$genre->id, "$genre->title ($genre->count)", $category);
                }
            }

            $category->set_sub_categories($gen_arr);

            $category_list[] = $category;
            $category_index[$category->get_id()] = $category;
        }

        hd_debug_print("Categories read: " . count($category_list));
        return true;
    }
    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print("getSearchList $keyword");
        $keyword = urlencode($keyword);
        $searchRes = $this->make_json_request("/filter/by_name?name=$keyword&page=1&per_page=999999999");

        return ($searchRes === false) ? array() : $this->CollectSearchResult($searchRes, $searchRes);
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
            /** @var array $m */
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
        $query_id = API_ACTION_MOVIE;
        foreach ($filter_params as $key => $value) {
            if ($key === 'source') {
                $query_id = $value;
                continue;
            }

            if ($key === 'year' && !empty($value)) {
                $values = explode('-', $value);
                if (count($values) === 1) {
                    $value = "$value-$value";
                }
            }

            if (!empty($param_str)) {
                $param_str .= "_";
            }

            $param_str .= "$key-$value";
        }

        $page_id = $query_id . "_" . API_ACTION_FILTER;
        $page_idx = $this->get_next_page($page_id);
        if ($page_idx < 0) {
            return array();
        }

        hd_debug_print("filter page_idx:  $page_idx");

        $jsonItems = $this->make_json_request("/filter");

        return $jsonItems === false ? array() : $this->CollectSearchResult($query_id, $jsonItems);
    }

    /**
     * @param string $query_id
     * @param object $json
     * @return array
     */
    protected function CollectSearchResult($query_id, $json)
    {
        $movies = array();

        $page_id = $query_id;
        $current_idx = $this->get_current_page($page_id);
        if ($current_idx < 0)
            return $movies;

        foreach ($json->data as $entry) {
            $genresArray = array();
            if (isset($entry->genres)) {
                foreach ($entry->genres as $genre) {
                    $genresArray[] = $genre->title;
                }
            }
            if (isset($entry->name)) {
                $genre_str = implode(", ", $genresArray);
                $movies[] = new Short_Movie(
                    $entry->id,
                    $entry->name,
                    $entry->poster,
                    TR::t('vod_screen_movie_info__5', $entry->name, $entry->year, $entry->country, $genre_str, $entry->rating)
                );
            }
        }

        hd_debug_print("Movies found: " . count($movies));
        $this->set_next_page($page_id, -1);

        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print($query_id);
        $this->get_next_page($query_id);
        $arr = explode("_", $query_id);
        $genre_id = safe_get_value($arr, 1, $query_id);
        $response = $this->make_json_request("/genres/$genre_id?page=1&per_page=999999999");
        return $response === false ? array() : $this->CollectSearchResult($query_id, $response);
    }

    protected static function collect_genres($entry)
    {
        $genres_str = '';
        if (isset($entry->genres)) {
            $genres = array();
            foreach ($entry->genres as $genre) {
                if (!empty($genre)) {
                    $genres[] = $genre->title;
                }
            }
            $genres_str = implode(", ", $genres);
        }

        return $genres_str;
    }

    /**
     * @param string|null $params
     * @return bool|object
     */
    protected function make_json_request($params)
    {
        if (!$this->provider->request_provider_token()) {
            return false;
        }

        $curl_opt[CURLOPT_CUSTOMREQUEST] = $params;
        $jsonItems = $this->provider->execApiCommand(API_COMMAND_GET_VOD, null, true, $curl_opt);
        if ($jsonItems === false) {
            $exception_msg = TR::load('err_load_vod') . "\n\n" . $this->provider->getCurlWrapper()->get_raw_response_headers();
            hd_debug_print($exception_msg);
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, $exception_msg);
            return false;
        }

        if (LogSeverity::$is_debug) {
            $command_url = $this->provider->getApiCommand(API_COMMAND_GET_VOD) . $params;
            file_put_contents(get_temp_path(Hashed_Array::hash($command_url) . '.json'),
                pretty_json_format($jsonItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $jsonItems;
    }
}
