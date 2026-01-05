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

class vod_korona extends vod_standard
{
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

        // movies_84636 or serials_84636
        hd_debug_print("TryLoadMovie: $movie_id");
        $arr = explode("_", $movie_id);
        $season_id = safe_get_value($arr, 1, $movie_id);

        $json = $this->make_json_request("/video/$season_id");

        if ($json === false || $json === null) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movieData = safe_get_value($json, 'data');
        if (isset($movieData['seasons'])) {
            // collect series
            foreach (safe_get_value($movieData, 'seasons', array()) as $season) {
                $season_id = safe_get_value($season, 'id');
                if (empty($season_id)) continue;

                $season_number = safe_get_value($season, 'number');
                $season_name = safe_get_value($season, 'name');
                $movie_season = new Movie_Season($season_id, $season_number);
                if (!empty($season_name)) {
                    $movie_season->description = $season_name;
                }
                $movie->add_season_data($movie_season);

                foreach (safe_get_value($season, 'series', array()) as $episode) {
                    $episode_id = safe_get_value($episode, 'id');
                    $episode_number = safe_get_value($episode, 'number');
                    $url = safe_get_value($episode, array('files', 0, 'url'));
                    hd_debug_print("episode playback_url: $url", true);
                    $playback_url = new Movie_Playback_Url($url);
                    $movie_serie = new Movie_Series($episode_id, TR::t('vod_screen_series__1', $episode_number), $playback_url, $season_id);
                    $movie_serie->description = safe_get_value($episode, 'name');
                    $movie->add_series_data($movie_serie);
                }
            }
        } else {
            $url = safe_get_value($movieData, array('files', 0, 'url'));
            hd_debug_print("movie playback_url: $url");
            $movie_serie = new Movie_Series($movie_id, safe_get_value($movieData, 'name'), new Movie_Playback_Url($url));
            $movie->add_series_data($movie_serie);
        }

        $movie->set_data(
            safe_get_value($movieData, 'name'),
            safe_get_value($movieData, 'original_name'),
            safe_get_value($movieData, 'description'),
            safe_get_value($movieData, 'poster'),
            safe_get_value($movieData, 'time'),
            safe_get_value($movieData, 'year'),
            safe_get_value($movieData, 'director'),
            '',
            safe_get_value($movieData, 'actors'),
            self::collect_genres($movieData),
            safe_get_value($movieData, 'rating'),
            '',
            '',
            safe_get_value($movieData, 'country')
        );

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $jsonItems = $this->make_json_request("/cat");
        if ($jsonItems === false || empty($jsonItems->data)) {
            return false;
        }

        $this->category_index = array();

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

            $this->category_index[$category->get_id()] = $category;
        }

        hd_debug_print("Categories read: " . count($this->category_index));
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getMovieList: $query_id");

        $arr = explode("_", $query_id);
        $genre_id = safe_get_value($arr, 1, $query_id);
        return $this->CollectQueryResult($query_id, $this->make_json_request("/genres/$genre_id?page=1&per_page=999999999"));
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        $enc_keyword = urlencode($keyword);
        return $this->CollectQueryResult($keyword, $this->make_json_request("/filter/by_name?name=$enc_keyword&page=1&per_page=999999999"));
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $query_id");

        $pairs = explode(",", $query_id);
        $filter_params = array();
        foreach ($pairs as $pair) {
            // country:USA
            // genre:action
            // year:2024
            /** @var array $m */
            if (!preg_match("/^(.+):(.+)$/", $pair, $m)) continue;

            $filter = $this->get_filter_type($m[1]);
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
        return $this->CollectQueryResult($page_id, $this->make_json_request("/filter"));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $query_id
     * @param array $json
     * @return array
     */
    protected function CollectQueryResult($query_id, $json)
    {
        if (empty($json)) {
            return array();
        }

        $movies = array();

        // pagination is not used. This is a guard to process only one request
        if ($this->is_page_index_stopped($query_id)) {
            return $movies;
        }

        foreach (safe_get_value($json, 'data', array()) as $entry) {
            $genresArray = array();
            foreach (safe_get_value($entry, 'genres', array()) as $genre) {
                $genresArray[] = safe_get_value($genre, 'title');
            }
            $name = safe_get_value($entry, 'name');
            if (!empty($name)) {
                $genre_str = implode(", ", $genresArray);
                $movie = new Short_Movie(
                    safe_get_value($entry, 'id'),
                    $name,
                    safe_get_value($entry, 'poster'),
                    TR::t('vod_screen_movie_info__5',
                        $name,
                        safe_get_value($entry, 'year'),
                        safe_get_value($entry, 'country'),
                        $genre_str,
                        safe_get_value($entry, 'rating'))
                );
                $this->plugin->vod->set_cached_short_movie($movie);
                $movies[] = $movie;
            }
        }

        hd_debug_print("Movies found: " . count($movies));
        $this->stop_page_index($query_id);

        return $movies;
    }

    protected static function collect_genres($entry)
    {
        $genres = array();
        foreach (safe_get_value($entry, 'genres', array()) as $genre) {
            if (!empty($genre)) {
                $genres[] = safe_get_value($genre, 'title');
            }
        }
        return implode(", ", $genres);
    }

    /**
     * @param string|null $params
     * @return bool|array
     */
    protected function make_json_request($params)
    {
        if (!$this->provider->request_provider_token()) {
            return false;
        }

        $curl_opt[CURLOPT_CUSTOMREQUEST] = $params;
        $jsonItems = $this->provider->execApiCommandResponse(API_COMMAND_GET_VOD, $curl_opt);
        if ($jsonItems === false) {
            $exception_msg = TR::load('err_load_vod') . "\n\n" . Curl_Wrapper::get_raw_response_headers();
            hd_debug_print($exception_msg);
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, $exception_msg);
            return false;
        }

        return $jsonItems;
    }
}
