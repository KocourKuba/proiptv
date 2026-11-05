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

/**
 * Own API
 * server support pagination, search
 * TV Shows can contain seasons
 */
class vod_cbilling extends vod_standard
{
    const CATEGORY_MODE_TYPE = 'type';
    const CATEGORY_MODE_GENRE = 'genre';
    // the category id of the genre mode: the prefix and the id=count list of the genre in each type
    const GENRE_QUERY_PREFIX = 'genre:';

    /**
     * @var string
     */
    protected $server = '';

    /**
     * @var string
     */
    protected $token = '';

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        if (parent::init_vod($provider)) {
            $this->vod_filters = array('year_from', 'year_to');
            $filter_types = array();
            $filter_types['year_from'] = array('title' => TR::t('year_from'), 'text' => true);
            $filter_types['year_to'] = array('title' => TR::t('year_to'), 'text' => true);
            $this->set_filter_types($filter_types);

            $acc_data = $provider->execApiCommandResponseNoOpt(API_COMMAND_ACCOUNT_INFO);
            if (isset($acc_data['data'])) {
                $info_data = safe_get_value($acc_data, 'data');
                hd_debug_print('VOD Data: ' . json_encode($info_data));
                if (!empty($info_data)) {
                    $this->token = safe_get_value($info_data, 'private_token');
                    $is_ssl = safe_get_value($info_data, 'ssl', false);
                    $scheme = $is_ssl ? 'https' : 'http';
                    $server = safe_get_value($info_data, 'server');
                    if (empty($server)) {
                        $server = $provider->getApiCommand(API_COMMAND_GET_VOD);
                    }
                    $this->server = "$scheme://$server";
                    return true;
                }
            }
        }

        return false;

    }

    /**
     * @inheritDoc
     */
    public function get_category_modes()
    {
        return array(
            self::CATEGORY_MODE_TYPE => TR::t('category'),
            self::CATEGORY_MODE_GENRE => TR::t('genre')
        );
    }

    /**
     * @inheritDoc
     */
    public function get_category_mode()
    {
        $mode = $this->provider->GetProviderParameter(PARAM_VOD_CATEGORY_MODE, self::CATEGORY_MODE_TYPE);
        return $mode === self::CATEGORY_MODE_GENRE ? $mode : self::CATEGORY_MODE_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function set_category_mode($mode)
    {
        $this->provider->SetProviderParameter(PARAM_VOD_CATEGORY_MODE, $mode);
        $this->category_index = null;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("Try Load Movie: $movie_id");

        $response = $this->make_json_request("/video/$movie_id");
        $movieData = safe_get_value($response, 'data');
        if (empty($movieData)) {
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);

        $genresArray = array();
        foreach (safe_get_value($movieData, 'genres', array()) as $genre) {
            $genresArray[] = safe_get_value($genre, 'title');
        }

        $movie_name = safe_get_value($movieData, 'name');
        $movie->set_data(
            $movie_name,                                       // caption,
            safe_get_value($movieData, 'original_name'),// caption_original,
            safe_get_value($movieData, 'description'),  // description,
            safe_get_value($movieData, 'poster'),       // poster_url,
            safe_get_value($movieData, 'time'),         // length,
            safe_get_value($movieData, 'year'),         // year,
            safe_get_value($movieData, 'director'),     // director,
            '',                                    // scenario,
            safe_get_value($movieData, 'actors'),       // actors,
            array_to_str($genresArray),                      // genres,
            safe_get_value($movieData, 'rating'),       // rate_imdb,
            '',                                   // rate_kinopoisk,
            safe_get_value($movieData, 'age'),          // rate_mpaa,
            safe_get_value($movieData, 'country')       // country,
        );

        if (isset($movieData['seasons'])) {
            $seasons = safe_get_value($movieData, 'seasons', array());
            foreach ($seasons as $season) {
                $season_number = safe_get_value($season, 'number');
                if (empty($season_number)) continue;

                $movie_season = new Movie_Season($season_number);
                $season_name = safe_get_value($season, 'name');
                $season_original_name = safe_get_value($season, 'original_name');
                if (!empty($season_name)) {
                    $movie_season->description = $season_name;
                }

                if (!empty($season_original_name)) {
                    $movie_season->description .= (empty($season_name) ? $season_original_name : " ($season_original_name)");
                }
                $movie->add_season_data($movie_season);

                foreach (safe_get_value($season, 'series', array()) as $serie) {
                    $id = safe_get_value($serie, 'id');
                    if (empty($id)) continue;

                    $series_number = safe_get_value($serie, 'number');
                    $files = safe_get_value($serie, 'files');
                    $url = $this->server . $files[0]['url'] . "?token=$this->token";
                    hd_debug_print("episode playback_url: $url", true);
                    $movie_serie = new Movie_Series($id, TR::t('vod_screen_series__1', $series_number), new Movie_Playback_Url($url), $season_number);

                    if (!empty($series_number)) {
                        $movie_serie->description = $series_number;
                    }

                    $serie_original_name = safe_get_value($serie, 'original_name');
                    if (!empty($serie_original_name)) {
                        $movie_serie->description .= empty($series_number) ? $serie_original_name : " ($serie_original_name)";
                    }

                    $movie->add_series_data($movie_serie);
                }
            }
        } else {
            $files = safe_get_value($movieData, 'files');
            $url = $this->server . $files[0]['url'] . "?token=$this->token";
            hd_debug_print("movie playback_url: $url");
            if (!empty($movie_id)) {
                $movie->add_series_data(new Movie_Series($movie_id, $movie_name, new Movie_Playback_Url($url)));
            }
        }

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $jsonItems = $this->make_json_request(null);
        if ($jsonItems === false) {
            $exception_msg = TR::load('err_load_vod') . "\n\n" . Curl_Wrapper::get_raw_response_headers();
            hd_debug_print($exception_msg);
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, $exception_msg);
            return false;
        }

        $this->category_index = array();

        $nodes = safe_get_value($jsonItems, 'data', array());
        $total = 0;
        foreach ($nodes as $node) {
            $total += safe_get_value($node, 'count', 0);
        }

        // all movies
        $category = new Vod_Category(Vod_Category::FLAG_ALL_MOVIES, TR::t('vod_screen_all_movies__1', " ($total)"));
        $this->category_index[Vod_Category::FLAG_ALL_MOVIES] = $category;

        if ($this->get_category_mode() === self::CATEGORY_MODE_GENRE) {
            $this->add_genre_categories($nodes);
        } else {
            foreach ($nodes as $node) {
                $id = (string)safe_get_value($node, 'id');
                $count = safe_get_value($node, 'count');
                $this->category_index[$id] = new Vod_Category($id, safe_get_value($node, 'name') . " ($count)");
            }
        }

        hd_debug_print('Categories read: ' . count($this->category_index));
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getMovieList: $query_id");

        // page index start from 1
        $page_idx = $this->get_current_page_index($query_id, 1);
        if ($page_idx < 0) {
            return array();
        }

        if (strpos($query_id, self::GENRE_QUERY_PREFIX) === 0) {
            return $this->get_genre_page($query_id, $page_idx);
        }

        if ($query_id === Vod_Category::FLAG_ALL_MOVIES) {
            $url = "/filter/new?page=$page_idx&per_page=" .  self::PAGE_LIMIT;
        } else {
            $url = "/cat/$query_id?page=$page_idx&per_page=" . self::PAGE_LIMIT;
        }

        return $this->CollectQueryResult($query_id, $this->make_json_request($url));
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        // page index start from 1
        $page_idx = $this->get_current_page_index($keyword, 1);
        if ($page_idx < 0) {
            return array();
        }

        $url = "/filter/by_name?name=" . urlencode($keyword) . "&page=$page_idx&per_page=" . self::PAGE_LIMIT;
        // the search is cached as the other lists, an error body is not kept
        $json = $this->make_json_request($url);
        if (!isset($json['data'])) {
            Curl_Wrapper::drop_last_cached_response();
        }

        return $this->CollectQueryResult($keyword, $json);
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($query_id)
    {
        hd_debug_print(null, true);

        $pairs = explode(',', $query_id);
        $filter_params = array();
        $cur_year = date('Y', time());
        foreach ($pairs as $pair) {
            /** @var array $m */
            if (!preg_match("/^([^:]+):(.+)$/", $pair, $m)) continue;
            $filter = $this->get_filter_type($m[1]);
            if (isset($filter['text'])) {
                if ((int)$m[2] <= 0) {
                    $filter_params[$m[1]] = 1;
                } else if ((int)$m[2] > $cur_year) {
                    $filter_params[$m[1]] = $cur_year;
                } else {
                    $filter_params[$m[1]] = (int)$m[2];
                }
            }
        }

        // page index start from 1
        $page_idx = $this->get_current_page_index($query_id, 1);
        if ($page_idx < 0) {
            return array();
        }

        $start = empty($filter_params['year_from']) ? 1900 : $filter_params['year_from'];
        $end = $filter_params['year_to'];
        if (empty($end)) {
            $url = "/filter/year/$start?page=$page_idx&per_page=" . self::PAGE_LIMIT;
        } else {
            $url = "/filter/year/$start/$end?page=$page_idx&per_page=" . self::PAGE_LIMIT;
        }
        return $this->CollectQueryResult($query_id, $this->make_json_request($url, false));
    }
    ///////////////////////////////////////////////////////////////////////

    /**
     * Genre categories sorted by name. Each type has its own genres with their own ids
     * and film counts, so the genres of the types are merged by name.
     *
     * @param array $nodes types
     * @return void
     */
    protected function add_genre_categories($nodes)
    {
        $genres = array();
        foreach ($nodes as $node) {
            $id = (string)safe_get_value($node, 'id');
            $genre_data = $this->make_json_request("/cat/$id/genres");
            if ($genre_data === false) continue;

            foreach (safe_get_value($genre_data, 'data', array()) as $genre) {
                $count = (int)safe_get_value($genre, 'count', 0);
                if ($count <= 0) continue;

                // a title can end with a space
                $name = trim(safe_get_value($genre, 'title'));
                $key = mb_strtolower($name, 'UTF-8');
                if (!isset($genres[$key])) {
                    $genres[$key] = array('name' => $name, 'count' => 0, 'sources' => array());
                }

                $genres[$key]['count'] += $count;
                $genres[$key]['sources'][] = safe_get_value($genre, 'id') . "=$count";
            }
        }

        ksort($genres);
        foreach ($genres as $genre) {
            $id = self::GENRE_QUERY_PREFIX . implode(',', $genre['sources']);
            $this->category_index[$id] = new Vod_Category($id, "{$genre['name']} ({$genre['count']})");
        }
    }

    /**
     * A page of the merged genre. The server pages each genre of each type separately, a big page is
     * very slow and there is no request for several genres, so the page N of the merged genre
     * is made of the pages N of its genres, sorted from new to old inside the page.
     *
     * @param string $query_id
     * @param int $page_idx
     * @return Short_Movie[]
     */
    protected function get_genre_page($query_id, $page_idx)
    {
        $rows = array();
        $added = array();
        $has_next = false;
        foreach (explode(',', substr($query_id, strlen(self::GENRE_QUERY_PREFIX))) as $source) {
            $pair = explode('=', $source);
            if (count($pair) !== 2) continue;

            // the genre of this type has no such page
            if (($page_idx - 1) * self::PAGE_LIMIT >= (int)$pair[1]) continue;

            // by the count: a failed request of one genre does not end the whole list
            if ($page_idx * self::PAGE_LIMIT < (int)$pair[1]) {
                $has_next = true;
            }

            $json = $this->make_json_request("/genres/$pair[0]?page=$page_idx&per_page=" . self::PAGE_LIMIT);
            foreach (safe_get_value($json, 'data', array()) as $row) {
                $idx = count($rows);
                $rows[$idx] = $row;
                $added[$idx] = (int)safe_get_value($row, 'added', 0);
            }
        }

        arsort($added);
        $sorted = array();
        foreach ($added as $idx => $time) {
            $sorted[] = $rows[$idx];
        }

        $movies = $this->make_data_short_movies(array('data' => $sorted));
        if ($has_next && !empty($movies)) {
            $this->shift_next_page_index($query_id);
        } else {
            $this->stop_page_index($query_id);
        }

        hd_debug_print('Movies found: ' . count($movies));
        return $movies;
    }

    /**
     * @param string $url
     * @param bool $cache_response
     * @return bool|array
     */
    protected function make_json_request($url, $cache_response = true)
    {
        $curl_opt = array();

        if (!empty($url)) {
            $curl_opt[API_COMMAND_ADD_PARAMS] = $url;
        }

        $cache = $cache_response ? Curl_Wrapper::CACHE_RESPONSE : 0;
        return $this->provider->execApiCommandResponse(API_COMMAND_GET_VOD, $curl_opt, Curl_Wrapper::RET_ARRAY, $cache);
    }

    /**
     * @param string $query_id
     * @param array $json
     * @return array
     */
    protected function CollectQueryResult($query_id, $json)
    {
        $movies = $this->make_data_short_movies($json);

        $cur_page = safe_get_value($json, array('meta', 'current_page'), 1);
        $last_page = safe_get_value($json, array('meta', 'last_page'), 1);
        if ($cur_page < $last_page && !empty($movies)) {
            $this->shift_next_page_index($query_id);
        } else {
            $this->stop_page_index($query_id);
        }

        hd_debug_print('Movies found: ' . count($movies));
        return $movies;
    }
}
