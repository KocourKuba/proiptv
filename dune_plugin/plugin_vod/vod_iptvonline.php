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

class vod_iptvonline extends vod_standard
{
    const REQUEST_TEMPLATE = "/movies?limit=100&page=%s&category=%s";

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        if (parent::init_vod($provider)) {
            $this->vod_filters = array("source", "year", "country", "genre");
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

        // movies_84636 or serials_84636
        $arr = explode("_", $movie_id);
        if (empty($arr[1])) {
            hd_debug_print("Movie ID is empty!");
            return null;
        }
        hd_debug_print("TryLoadMovie: category: movies, id: $arr[1]");

        $params[CURLOPT_CUSTOMREQUEST] = "/movies/$arr[1]";
        $json = $this->make_json_request($params);

        if ($json === false) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movieData = safe_get_value($json, 'data', array());
        if ($arr[0] === API_ACTION_MOVIE) {
            $medias = safe_get_value($movieData, 'medias', array());
            $url = safe_get_value($medias, 'url');
            hd_debug_print("movie playback_url: $url", true);

            $playback_url = new Movie_Playback_Url($url);
            $movie_series = new Movie_Series($arr[1], $medias['title'], $playback_url);
            $quality = new Movie_Variant($medias['title'], $playback_url);
            foreach (safe_get_value($medias, 'audios', array()) as $item) {
                $key = safe_get_value($item, 'translate');
                if (!empty($key)) {
                    hd_debug_print("url for audio '$key' - {$item['url']}", true);
                    $quality->add_variant_data($key, new Movie_Variant($key, new Movie_Playback_Url($item['url'])));
                }
            }
            $movie_series->add_variant_data('auto', $quality);

            $movie->add_series_data($movie_series);
        } else if ($arr[0] === API_ACTION_SERIAL) {
            // collect series
            foreach (safe_get_value($movieData, 'seasons', array()) as $season) {
                $season_id = safe_get_value($season, 'season');
                if (empty($season_id)) continue;

                $movie_season = new Movie_Season($season_id);
                $movie_season->description = safe_get_value($season, 'title');
                $movie->add_season_data($movie_season);

                foreach (safe_get_value($season, 'episodes', array()) as $episode) {
                    $url = safe_get_value($episode, 'url');

                    hd_debug_print("episode playback_url: $url", true);
                    $series_id = "{$season['season']}:{$episode['episode']}";
                    $series_name = TR::load('vod_screen_series__1', $episode['episode']);
                    $playback_url = new Movie_Playback_Url($url);
                    $movie_series = new Movie_Series($series_id, $series_name, $playback_url, $season['season']);
                    $qualty = new Movie_Variant($series_name, $playback_url);
                    foreach (safe_get_value($episode, 'audios', array()) as $item) {
                        $key = safe_get_value($item, 'translate');
                        if (!empty($key)) {
                            hd_debug_print("url for audio '$key' - {$item['url']}", true);
                            $qualty->add_variant_data($key, new Movie_Variant($key, new Movie_Playback_Url($item['url'])));
                        }
                    }
                    $movie_season->description = safe_get_value($episode, 'title');
                    $movie_series->add_variant_data($series_id, $qualty);
                    $movie->add_series_data($movie_series);
                }
            }
        }

        $details = array();
        $qualities = safe_get_value($movieData, 'quality');
        if (!empty($qualities)) {
            $details[TR::t('vod_screen_quality')] = $qualities;
        }

        hd_debug_print("Result movie: " . $movie, true);
        $movie->set_data(
            $movieData['ru_title'],                   // caption,
            $movieData['orig_title'],                 // caption_original,
            $movieData['plot'],                       // description,
            $movieData['posters']['big'],             // poster_url,
            $movieData['duration'] / 60,    // length,
            $movieData['year'],                       // year,
            $movieData['director'],                   // director,
            '',                           // scenario,
            $movieData['cast'],                       // actors,
            implode(',', safe_get_value($movieData, 'genres', array())),         // genres,
            $movieData['imdb_rating'],                // rate_imdb,
            $movieData['kinopoisk_rating'],           // rate_kinopoisk,
            '',                             // rate_mpaa,
            implode(',', safe_get_value($movieData, 'countries', array())),      // country,
            '',                                // budget
            $details                                  // details
        );

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $this->category_index = array();

        $this->category_index[API_ACTION_MOVIE] = new Vod_Category(API_ACTION_MOVIE, TR::t('vod_screen_all_movies'));
        $this->category_index[API_ACTION_SERIAL] = new Vod_Category(API_ACTION_SERIAL, TR::t('vod_screen_all_serials'));

        $exist_filters = array();
        $params[CURLOPT_CUSTOMREQUEST] = '/' . API_ACTION_FILTERS;
        $data = $this->make_json_request($params);
        if ($data === false || !isset($data['data']['filter_by'])) {
            hd_debug_print("Wrong response on filter request: " . json_format_unescaped($data));
            return false;
        }

        $exist_filters['source'] = array(
            'title' => TR::load('category'),
            'values' => array(
                API_ACTION_MOVIE => TR::load('vod_screen_all_movies'),
                API_ACTION_SERIAL => TR::load('vod_screen_all_serials')
            )
        );

        foreach (safe_get_value($data['data'], 'filter_by', array()) as $filter) {
            $id = safe_get_value($filter, 'id');
            if (empty($id)) continue;

            $items = safe_get_value($filter, 'items', array());
            if (empty($items)) {
                $exist_filters[$id] = array('title' => $filter['title'], 'text' => true);
            } else {
                $exist_filters[$id] = array('title' => $filter['title'], 'values' => array(-1 => TR::t('no')));
                foreach ($items as $item) {
                    if ($item['enabled']) {
                        $exist_filters[$id]['values'][$item['id']] = $item['title'];
                    }
                }
            }
        }

        $this->set_filter_types($exist_filters);

        hd_debug_print("Categories read: " . count($this->category_index));
        hd_debug_print("Filters count: " . count($exist_filters));
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
        $params[CURLOPT_CUSTOMREQUEST] = sprintf(self::REQUEST_TEMPLATE, $page_idx, $query_id);
        $json = $this->make_json_request($params);

        return $this->CollectQueryResult($query_id, $json);
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        // Using method GET! but send parameters via POST fields
        $params[CURLOPT_POSTFIELDS] = array("search" => $keyword);

        $movies = array();
        $page_id = API_ACTION_MOVIE . "_" . API_ACTION_SEARCH;
        // page index start from 1
        $page_idx = $this->get_current_page_index($page_id, 1);
        if ($page_idx >= 0) {
            $params[CURLOPT_CUSTOMREQUEST] = sprintf(self::REQUEST_TEMPLATE, $page_idx, API_ACTION_MOVIE);
            $searchRes = $this->make_json_request($params);
            $movies = $this->CollectQueryResult(API_ACTION_MOVIE, $searchRes, API_ACTION_SEARCH);
        }

        $page_id = API_ACTION_SERIAL . "_" . API_ACTION_SEARCH;
        $page_idx = $this->get_current_page_index($page_id, 1);
        if ($page_idx < 0) {
            return $movies;
        }

        $params[CURLOPT_CUSTOMREQUEST] = sprintf(self::REQUEST_TEMPLATE, $page_idx, API_ACTION_SERIAL);
        $searchRes = $this->make_json_request($params);
        $serials = $this->CollectQueryResult(API_ACTION_SERIAL, $searchRes, API_ACTION_SEARCH);

        return safe_merge_array($movies, $serials);
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
        $page_idx = $this->get_current_page_index($page_id, 1);
        if ($page_idx < 0) {
            return array();
        }

        hd_debug_print("filter page_idx:  $page_idx");

        // Using method GET! but send parameters via POST fields
        $post_params[CURLOPT_CUSTOMREQUEST] = sprintf(self::REQUEST_TEMPLATE, $page_idx, $query_id);
        $post_params[CURLOPT_POSTFIELDS]['features_hash'] = $param_str;
        $json = $this->make_json_request($post_params);

        return $this->CollectQueryResult($query_id, $json, API_ACTION_FILTER);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param array|null $params
     * @return bool|array
     */
    protected function make_json_request($params = null)
    {
        $curl_opt = array();

        if (isset($params[CURLOPT_CUSTOMREQUEST])) {
            $curl_opt[CURLOPT_CUSTOMREQUEST] = $params[CURLOPT_CUSTOMREQUEST];
        }

        if (isset($params[CURLOPT_POST])) {
            $curl_opt[CURLOPT_POST] = $params[CURLOPT_POST];
        }

        if (isset($params[CURLOPT_POSTFIELDS])) {
            $curl_opt[CURLOPT_HTTPHEADER][] = CONTENT_TYPE_JSON;
            $curl_opt[CURLOPT_POSTFIELDS] = $params[CURLOPT_POSTFIELDS];
        }

        $data = $this->provider->execApiCommandResponse(API_COMMAND_GET_VOD, $curl_opt);
        if (!isset($data['success'], $data['status']) || !$data['success'] || $data['status'] !== 200) {
            hd_debug_print("Wrong response: " . json_format_unescaped($data));
            return false;
        }

        return $data;
    }

    /**
     * @param string $query_id
     * @param array $json
     * @param string|null $search
     * @return array
     */
    protected function CollectQueryResult($query_id, $json, $search = null)
    {
        hd_debug_print(null, true);
        hd_debug_print("query_id: $query_id");

        if (empty($json)) {
            return array();
        }

        $movies = array();
        if (!isset($json['data']['items'])) {
            return $movies;
        }

        $page_id = is_null($search) ? $query_id : "{$query_id}_$search";
        $current_idx = $this->get_current_page_index($page_id);
        if ($current_idx < 0) {
            return $movies;
        }

        $items = safe_get_value($json, array('data', 'items'), array());
        foreach ($items as $entry) {
            $ru_title = safe_get_value($entry, 'ru_title');
            $posters = safe_get_value($entry, 'posters', array());
            $movie = new Short_Movie(
                "{$query_id}_{$entry['id']}",
                $ru_title,
                safe_get_value($posters, 'medium'),
                TR::t('vod_screen_movie_info__4',
                    $ru_title,
                    $entry['year'],
                    implode(',', safe_get_value($entry, 'countries', array())),
                    implode(',', safe_get_value($entry, 'genres', array()))
                )
            );

            $movie->big_poster_url = safe_get_value($posters, 'big');
            $this->plugin->vod->set_cached_short_movie($movie);
            $movies[] = $movie;
        }

        $page = safe_get_value($json, array('data', 'pagination', 'pages'));
        if ($page === $current_idx) {
            hd_debug_print("Last page: $page");
            $this->stop_page_index($page_id);
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }
}
