<?php
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

        $response = $provider->execApiCommand(API_COMMAND_INFO);
        if ($response === false) {
            return false;
        }

        hd_debug_print(API_COMMAND_INFO . ' ' . serialize($response));
        $data = HD::decodeResponse(false, $response);
        if ($data === false || !isset($data->data)) {
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
        $this->server = $scheme . (isset($data->server) ? $data->server : $provider->getApiCommand(API_COMMAND_VOD));
        return true;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);
        $response = $this->provider->execApiCommand(API_COMMAND_VOD, null, "/video/$movie_id");
        if ($response === false) {
            hd_debug_print("Failed request: " . API_COMMAND_VOD . "param: /video/$movie_id");
            return null;
        }

        $json = HD::decodeResponse(false, $response);
        if ($json === false || !isset($json->data)) {
            hd_debug_print("Wrong response on command: " . API_COMMAND_VOD);
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movieData = $json->data;

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
            $movieData->country,// country,
            ''// budget
        );

        if (isset($movieData->seasons)) {
            foreach ($movieData->seasons as $season) {
                $movie->add_season_data($season->number, !empty($season->name) ? $season->name : TR::t('vod_screen_season__1', $season->number), '');
                foreach ($season->series as $episode) {
                    $name = TR::t('vod_screen_series__2', $episode->number, (empty($episode->name) ? "" : $episode->name));
                    $movie->add_series_data($episode->id,
                        $name,
                        '',
                        "$this->server{$episode->files[0]->url}?token=$this->token",
                        $season->number
                    );
                }
            }
        } else {
            $movie->add_series_data($movie_id,
                $movieData->name,
                '',
                "$this->server{$movieData->files[0]->url}?token=$this->token"
            );
        }

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        $response = $this->provider->execApiCommand(API_COMMAND_VOD);
        if ($response === false) {
            hd_debug_print("Failed request: " . API_COMMAND_VOD);
            return;
        }

        $jsonItems = HD::decodeResponse(false, $response);
        if ($jsonItems === false || !isset($jsonItems->data)) {
            hd_debug_print("Wrong response on command: " . API_COMMAND_VOD);
            return;
        }

        $category_list = array();
        $category_index = array();

        $total = 0;
        foreach ($jsonItems->data as $node) {
            $id = (string)$node->id;
            $category = new Vod_Category($id, "$node->name ($node->count)");
            $total += $node->count;

            // fetch genres for category
            $response = $this->provider->execApiCommand(API_COMMAND_VOD, null, "/cat/$id/genres");
            if ($response === false) {
                hd_debug_print("Failed request: " . API_COMMAND_VOD . "param: /cat/$id/genres");
                continue;
            }

            $genres = HD::decodeResponse(false, $response);
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
        $category = new Vod_Category(Vod_Category::FLAG_ALL, TR::t('vod_screen_all_movies__1', " ($total)"));
        array_unshift($category_list, $category);
        $category_index[Vod_Category::FLAG_ALL] = $category;

        hd_debug_print("Categories read: " . count($category_list));
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        $params = "/filter/by_name?name=" . urlencode($keyword) . "&page=" . $this->get_next_page($keyword);
        $response = $this->provider->execApiCommand(API_COMMAND_VOD, null, $params);
        if ($response === false) {
            return array();
        }
        $searchRes = HD::decodeResponse(false, $response);
        return $searchRes === false ? array() : $this->CollectSearchResult($searchRes);
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print($query_id);
        $val = $this->get_next_page($query_id);

        if ($query_id === Vod_Category::FLAG_ALL) {
            $params = "/filter/new?page=$val";
        } else {
            $arr = explode("_", $query_id);
            if ($arr === false) {
                $genre_id = $query_id;
            } else {
                $genre_id = $arr[1];
            }

            $params = "/genres/$genre_id?page=$val";
        }

        $response = $this->provider->execApiCommand(API_COMMAND_VOD, null, $params);
        if ($response === false) {
            return array();
        }

        $categories = HD::decodeResponse(false, $response);
        return $categories === false ? array() : $this->CollectSearchResult($categories);
    }

    /**
     * @param $json
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
}
