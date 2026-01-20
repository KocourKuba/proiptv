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

class vod_cbilling extends vod_standard
{
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
            $acc_data = $provider->execApiCommandResponseNoOpt(API_COMMAND_ACCOUNT_INFO, Curl_Wrapper::RET_ARRAY);
            if (isset($acc_data['data'])) {
                $info_data = safe_get_value($acc_data, 'data');
                hd_debug_print("VOD Data: " . json_encode($info_data));
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
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("Try Load Movie: $movie_id");

        $params[API_COMMAND_ADD_PARAMS] = "/video/$movie_id";
        $response = $this->provider->execApiCommandResponse(API_COMMAND_GET_VOD, $params, Curl_Wrapper::RET_ARRAY | Curl_Wrapper::CACHE_RESPONSE);
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
            $movie_name,// caption,
            safe_get_value($movieData, 'original_name'),// caption_original,
            safe_get_value($movieData, 'description'),// description,
            safe_get_value($movieData, 'poster'),// poster_url,
            safe_get_value($movieData, 'time'),// length,
            safe_get_value($movieData, 'year'),// year,
            safe_get_value($movieData, 'director'),// director,
            '',// scenario,
            safe_get_value($movieData, 'actors'),// actors,
            implode(", ", $genresArray),// $xml->genres,
            safe_get_value($movieData, 'rating'),// rate_imdb,
            '',// rate_kinopoisk,
            safe_get_value($movieData, 'age'),// rate_mpaa,
            safe_get_value($movieData, 'country')// country,
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

        $jsonItems = $this->provider->execApiCommandResponseNoOpt(API_COMMAND_GET_VOD, Curl_Wrapper::RET_ARRAY | Curl_Wrapper::CACHE_RESPONSE);
        if ($jsonItems === false) {
            $exception_msg = TR::load('err_load_vod') . "\n\n" . Curl_Wrapper::get_raw_response_headers();
            hd_debug_print($exception_msg);
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, $exception_msg);
            return false;
        }

        $this->category_index = array();

        $category = new Vod_Category(Vod_Category::FLAG_ALL_MOVIES, '');
        $this->category_index[Vod_Category::FLAG_ALL_MOVIES] = $category;
        $total = 0;
        foreach (safe_get_value($jsonItems, 'data', array()) as $node) {
            $id = (string)safe_get_value($node, 'id');
            $count = safe_get_value($node, 'count');
            $category = new Vod_Category($id, safe_get_value($node, 'name') . " ($count)");
            $total += $count;

            // fetch genres for category
            $params[API_COMMAND_ADD_PARAMS] = "/cat/$id/genres";
            $genreData = $this->provider->execApiCommandResponse(API_COMMAND_GET_VOD, $params, Curl_Wrapper::RET_ARRAY | Curl_Wrapper::CACHE_RESPONSE);
            if ($genreData === false) {
                continue;
            }

            $gen_arr = array();
            foreach (safe_get_value($genreData, 'data', array()) as $genre) {
                $genre_id = (string)safe_get_value($genre, 'id');
                $gen_arr[] = new Vod_Category($genre_id, safe_get_value($genre, 'title'), $category);
            }

            $category->set_sub_categories($gen_arr);
            $this->category_index[$id] = $category;
        }

        // all movies
        $category = new Vod_Category(Vod_Category::FLAG_ALL_MOVIES, TR::t('vod_screen_all_movies__1', " ($total)"));
        $this->category_index[Vod_Category::FLAG_ALL_MOVIES] = $category;

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

        // page index start from 1
        $page_idx = $this->get_current_page_index($query_id, 1);
        if ($page_idx < 0) {
            return array();
        }

        if ($query_id === Vod_Category::FLAG_ALL_MOVIES) {
            $params[API_COMMAND_ADD_PARAMS] = "/filter/new?page=$page_idx&per_page=50";
        } else {
            $arr = explode("_", $query_id);
            $genre_id = safe_get_value($arr, 1, $query_id);
            $params[API_COMMAND_ADD_PARAMS] = "/genres/$genre_id?page=$page_idx&per_page=50";
        }

        return $this->CollectQueryResult($query_id,
            $this->provider->execApiCommandResponse(API_COMMAND_GET_VOD, $params, Curl_Wrapper::RET_ARRAY | Curl_Wrapper::CACHE_RESPONSE));
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

        $params[API_COMMAND_ADD_PARAMS] = "/filter/by_name?name=" . urlencode($keyword) . "&page=$page_idx&per_page=50";
        return $this->CollectQueryResult($keyword, $this->provider->execApiCommandResponse(API_COMMAND_GET_VOD, $params, Curl_Wrapper::RET_ARRAY));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $query_id
     * @param array $json
     * @return array
     */
    protected function CollectQueryResult($query_id, $json)
    {
        $movies = array();
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
                    safe_get_value($entry, 'name'),
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

        $cur_page = safe_get_value($json, array('meta', 'current_page'), 1);
        $last_page = safe_get_value($json, array('meta', 'last_page'), 1);
        if ($cur_page < $last_page && !empty($movies)) {
            $this->shift_next_page_index($query_id);
        } else {
            $this->stop_page_index($query_id);
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }
}
