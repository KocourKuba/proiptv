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
        parent::init_vod($provider);

        $data = $provider->execApiCommand(API_COMMAND_ACCOUNT_INFO);
        if (!isset($data->data)) {
            return false;
        }

        $data = $data->data;
        if (!isset($data->vod) || $data->vod === false) {
            return false;
        }

        if (isset($data->private_token)) {
            $this->token = $data->private_token;
        }

        $scheme = (isset($data->ssl) && $data->ssl) ? "https://" : "http://";
        $this->server = $scheme . (isset($data->server) ? $data->server : $provider->getApiCommand(API_COMMAND_GET_VOD));
        return true;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);
        $params[CURLOPT_CUSTOMREQUEST] = "/video/$movie_id";
        $response = $this->provider->execApiCommand(API_COMMAND_GET_VOD, null, 1, $params);
        if (!isset($response->data)) {
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movieData = $response->data;

        $genresArray = array();
        foreach ($movieData->genres as $genre) {
            $genresArray[] = $genre->title;
        }

        $movie->set_data(
            $movieData->name,// caption,
            $movieData->original_name,// caption_original,
            $movieData->description,// description,
            $movieData->poster,// poster_url,
            $movieData->time,// length,
            $movieData->year,// year,
            $movieData->director,// director,
            '',// scenario,
            $movieData->actors,// actors,
            implode(", ", $genresArray),// $xml->genres,
            $movieData->rating,// rate_imdb,
            '',// rate_kinopoisk,
            $movieData->age,// rate_mpaa,
            $movieData->country// country,
        );

        if (isset($movieData->seasons)) {
            foreach ($movieData->seasons as $season) {
                $movie_season = new Movie_Season($season->number);
                if (!empty($season->name)) {
                    $movie_season->description = $season->name;
                }
                if (!empty($season->original_name)) {
                    $movie_season->description .= empty($season->name) ? $season->original_name : " ($season->original_name)";
                }
                $movie->add_season_data($movie_season);

                foreach ($season->series as $serie) {
                    $url = $this->server . $serie->files[0]->url . "?token=$this->token";
                    hd_debug_print("episode playback_url: $url");
                    $movie_serie = new Movie_Series($serie->id,
                        TR::t('vod_screen_series__1', $serie->number),
                        new Movie_Playback_Url($url),
                        $season->number
                    );

                    if (!empty($serie->name)) {
                        $movie_serie->description = $serie->name;
                    }
                    if (!empty($movie_serie->original_name)) {
                        $movie_serie->description .= empty($serie->name) ? $serie->original_name : " ($serie->original_name)";
                    }

                    $movie->add_series_data($movie_serie);
                }
            }
        } else {
            $url = $this->server . $movieData->files[0]->url . "?token=$this->token";
            hd_debug_print("movie playback_url: $url");
            $movie->add_series_data(new Movie_Series($movie_id,
                $movieData->name,
                new Movie_Playback_Url($url))
            );
        }

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        $jsonItems = $this->provider->execApiCommand(API_COMMAND_GET_VOD);
        if ($jsonItems === false) {
            $exception_msg = TR::load('err_load_vod') . "\n\n" . Curl_Wrapper::get_raw_response_headers();
            hd_debug_print($exception_msg);
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, $exception_msg);
            return false;
        }

        $category_list = array();
        $category_index = array();

        $total = 0;
        foreach ($jsonItems->data as $node) {
            $id = (string)$node->id;
            $category = new Vod_Category($id, "$node->name ($node->count)");
            $total += $node->count;

            // fetch genres for category
            $params[CURLOPT_CUSTOMREQUEST] = "/cat/$id/genres";
            $genres = $this->provider->execApiCommand(API_COMMAND_GET_VOD, null, 1, $params);
            if ($genres === false) {
                continue;
            }

            $gen_arr = array();
            if (isset($genres->data)) {
                foreach ($genres->data as $genre) {
                    $gen_arr[] = new Vod_Category((string)$genre->id, (string)$genre->title, $category);
                }
            }

            $category->set_sub_categories($gen_arr);

            $category_list[] = $category;
            $category_index[$category->get_id()] = $category;
        }

        // all movies
        $category = new Vod_Category(Vod_Category::FLAG_ALL_MOVIES, TR::t('vod_screen_all_movies__1', " ($total)"));
        array_unshift($category_list, $category);
        $category_index[Vod_Category::FLAG_ALL_MOVIES] = $category;

        hd_debug_print("Categories read: " . count($category_list));
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        $page_idx = $this->get_next_page($keyword);
        if ($page_idx < 0)
            return array();

        $params[CURLOPT_CUSTOMREQUEST] = "/filter/by_name?name=" . urlencode($keyword) . "&page=$page_idx";
        $response = $this->provider->execApiCommand(API_COMMAND_GET_VOD, null, 1, $params);
        return $response === false ? array() : $this->CollectSearchResult($response);
    }

    /**
     * @param object $json
     * @return array
     */
    protected function CollectSearchResult($json)
    {
        $movies = array();

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
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print($query_id);
        $page_idx = $this->get_next_page($query_id);
        if ($page_idx < 0)
            return array();

        if ($query_id === Vod_Category::FLAG_ALL_MOVIES) {
            $params[CURLOPT_CUSTOMREQUEST] = "/filter/new?page=$page_idx";
        } else {
            $arr = explode("_", $query_id);
            $genre_id = safe_get_value($arr, 1, $query_id);
            $params[CURLOPT_CUSTOMREQUEST] = "/genres/$genre_id?page=$page_idx";
        }

        $response = $this->provider->execApiCommand(API_COMMAND_GET_VOD, null, 1, $params);
        return $response === false ? array() : $this->CollectSearchResult($response);
    }
}
