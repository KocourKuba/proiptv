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

        $provider_data = $provider->getProviderData();
        if (is_null($provider_data)) {
            $show = false;
        } else {
            $show = isset($provider_data['vod']) && $provider_data['vod'] !== false;
        }

        if (!$show) {
            return false;
        }

        if (isset($provider_data['private_token'])) {
            $this->token = $provider_data['private_token'];
        }

        $scheme = (isset($provider_data['ssl']) && $provider_data['ssl']) ? "https://" : "http://";
        $this->server = $scheme . (isset($provider_data['server']) ? $provider_data['server'] : $this->vod_source);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);
        $json = HD::DownloadJson("$this->vod_source/video/$movie_id", false);
        if ($json === false) {
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
        $jsonItems = HD::DownloadJson($this->vod_source, false);
        if ($jsonItems === false) {
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
            $genres = HD::DownloadJson("$this->vod_source/cat/$id/genres", false);
            if ($genres === false) {
                continue;
            }

            $gen_arr = array();
            foreach ($genres->data as $genre) {
                $gen_arr[] = new Vod_Category((string)$genre->id, (string)$genre->title, $category);
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
        $url = "$this->vod_source/filter/by_name?name=" . urlencode($keyword) . "&page=" . $this->get_next_page($keyword);
        $searchRes = HD::DownloadJson($url, false);
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
            $url = "$this->vod_source/filter/new?page=$val";
        } else {
            $arr = explode("_", $query_id);
            if ($arr === false) {
                $genre_id = $query_id;
            } else {
                $genre_id = $arr[1];
            }

            $url = "$this->vod_source/genres/$genre_id?page=$val";
        }

        $categories = HD::DownloadJson($url, false);
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
                $movie = new Short_Movie($entry->id, $entry->name, $entry->poster);
                $genre_str = implode(", ", $genresArray);
                $movie->info = TR::t('vod_screen_movie_info__5', $entry->name, $entry->year, $entry->country, $genre_str, $entry->rating);
                $movies[] = $movie;
            }
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }
}
