<?php
require_once 'vod_standard.php';

class vod_edem extends vod_standard
{
    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        $this->vod_filters = array("years", "genre");
        $this->vod_quality = true;
        parent::init_vod($provider);
    }

    /**
     * @param string $movie_id +/
     * @return Movie
     * @throws Exception
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print($movie_id);
        $movie = new Movie($movie_id, $this->plugin);
        $movieData = $this->make_json_request(array('cmd' => "flick", 'fid' => (int)$movie_id, 'offset' => 0, 'limit' => 0));

        if ($movieData === false) {
            hd_debug_print("failed to load movie: $movie_id");
            return $movie;
        }

        $series_desc = '';
        if ($movieData->type === 'multistream') {
            // collect series
            foreach ($movieData->items as $item) {
                $episodeData = $this->make_json_request(array('cmd' => "flick", 'fid' => (int)$item->fid, 'offset' => 0, 'limit' => 0));

                if (!isset($episodeData->variants)) {
                    $movie->add_series_data($item->fid, $item->title, '', $item->url);
                } else if (count((array)$episodeData->variants) === 1) {
                    $key = key($episodeData->variants);
                    $series_desc = ($key === 'auto' ? $key : $key . 'p');
                    $movie->add_series_data($item->fid, $item->title, $series_desc, $item->url);
                } else {
                    $variants_data = (array)$episodeData->variants;
                    $variants = array();
                    $qualities = '';
                    foreach ($variants_data as $key => $url) {
                        $quality = ($key === 'auto' ? $key : $key . 'p');
                        $variants[$key] = new Movie_Variant($item->fid . "_" . $key, $quality, $url);
                        if (!empty($qualities)) {
                            $qualities .= ",";
                        }
                        $qualities .= $quality;
                    }

                    $qualities = TR::load_string('setup_quality') . "|$qualities";
                    $series_desc = rtrim($qualities, ' ,\0');
                    $movie->add_series_variants_data($item->fid, $item->title, $series_desc, $variants, $item->url);
                }
            }
        } else if (!isset($movieData->variants)) {
            $movie->add_series_data($movie_id, $movieData->title, '', $movieData->url);
        } else if (count((array)$movieData->variants) === 1) {
            $key = key($movieData->variants);
            $series_desc = ($key === 'auto' ? $key : $key . 'p');
            $movie->add_series_data($movie_id, $movieData->title, $series_desc, $movieData->url);
        } else {
            $variants_data = (array)$movieData->variants;
            $variants = array();
            $qualities = '';
            foreach ($variants_data as $key => $url) {
                $quality = ($key === 'auto' ? $key : $key . 'p');
                $variants[$key] = new Movie_Variant($movie_id . "_" . $key, $quality, $url);
                if (!empty($qualities)) {
                    $qualities .= ",";
                }
                $qualities .= $quality;
            }

            $qualities = TR::load_string('setup_quality') . "|$qualities";
            $series_desc = rtrim($qualities, ' ,\0');
            $movie->add_series_variants_data($movie_id, $movieData->title, $series_desc, $variants, $movieData->url);
        }

        $movie->set_data(
            $movieData->title,// caption,
            $series_desc,// caption_original,
            isset($movieData->description) ? $movieData->description : '',// description,
            isset($movieData->img) ? $movieData->img : '',// poster_url,
            isset($movieData->duration) ? $movieData->duration : '',// length,
            isset($movieData->year) ? $movieData->year : '',// year,
            '',// director,
            '',// scenario,
            '',// actors,
            '',// genres,
            '',// rate_imdb,
            '',// rate_kinopoisk,
            isset($movieData->agelimit) ? $movieData->agelimit : '',// rate_mpaa,
            '',// country,
            ''// budget
        );

        return $movie;
    }

    /**
     * @param array &$category_list
     * @param array &$category_index
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        $doc = $this->make_json_request();
        if ($doc === false) {
            return;
        }

        $category_list = array();
        $category_index = array();

        if (isset($doc->items)) {
            foreach ($doc->items as $node) {
                $cat = new Vod_Category((string)$node->request->fid, (string)$node->title);
                $category_list[] = $cat;
                $category_index[$cat->get_id()] = $cat;
            }
        }

        $exist_filters = array();
        foreach ($doc->controls->filters as $filter) {
            $first = reset($filter->items);
            $key = key(array_diff_key((array)$first->request, array('filter' => 'on')));
            $exist_filters[$key] = array('title' => $filter->title, 'values' => array(-1 => TR::t('no')));
            foreach ($filter->items as $item) {
                $val = $item->request->{$key};
                $exist_filters[$key]['values'][$val] = $item->title;
            }
        }

        $this->set_filters($exist_filters);

        hd_debug_print("Categories read: " . count($category_list));
        hd_debug_print("Filters count: " . count($exist_filters));
    }

    /**
     * @param string $keyword
     * @return array
     * @throws Exception
     */
    public function getSearchList($keyword)
    {
        hd_debug_print("getSearchList $keyword");
        $searchRes = $this->make_json_request(array('cmd' => "search", 'query' => $keyword));

        return $searchRes === false ? array() : $this->CollectSearchResult($keyword, $searchRes);
    }

    /**
     * @param string $params
     * @return array|false
     * @throws Exception
     */
    public function getFilterList($params)
    {
        hd_debug_print("getFilterList: $params");
        $pairs = explode(" ", $params);
        $post_params = array();
        foreach ($pairs as $pair) {
            if (preg_match("/^(.+):(.+)$/", $pair, $m)) {
                $filter = $this->get_filter($m[1]);
                if ($filter !== null && !empty($filter['values'])) {
                    $item_idx = array_search($m[2], $filter['values']);
                    if ($item_idx !== false && $item_idx !== -1) {
                        if ($m[1] === 'years') {
                            $post_params[$m[1]] = (string)$item_idx;
                        } else {
                            $post_params[$m[1]] = (int)$item_idx;
                        }
                    }
                }
            }
        }

        if (empty($post_params)) {
            return false;
        }

        $post_params['filter'] = 'on';
        $post_params['offset'] = $this->get_next_page($params, 0);
        $json = $this->make_json_request($post_params);

        return $json === false ? array() : $this->CollectSearchResult($params, $json);
    }

    /**
     * @param string $query_id
     * @return array
     * @throws Exception
     */
    public function getMovieList($query_id)
    {
        $val = $this->get_next_page($query_id, 0);
        $post_params = array('cmd' => "flicks", 'fid' => (int)$query_id, 'offset' => $val, 'limit' => 50);
        $json = $this->make_json_request($post_params);

        return $json === false ? array() : $this->CollectSearchResult($query_id, $json);
    }

    /**
     * @param string $query_id
     * @param $json
     * @return array
     */
    protected function CollectSearchResult($query_id, $json)
    {
        hd_debug_print("query_id: $query_id");
        $movies = array();

        $current_offset = $this->get_next_page($query_id, 0);
        if ($current_offset < 0)
            return $movies;

        foreach ($json->items as $entry) {
            if ($entry->type === 'next') {
                $this->get_next_page($query_id, $entry->request->offset - $current_offset);
            } else {
                $movie = new Short_Movie($entry->request->fid, $entry->title, $entry->img);
                $movie->info = TR::t('vod_screen_movie_info__3', $entry->title, $entry->year, $entry->agelimit);
                $movies[] = $movie;
            }
        }
        if ($current_offset === $this->get_next_page($query_id, 0)) {
            $this->set_next_page($query_id, -1);
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * @param array &$defs
     * @param Starnet_Vod_Filter_Screen $parent
     * @param int $initial
     * @return bool
     */
    public function AddFilterUI(&$defs, $parent, $initial = -1)
    {
        $filters = array("years", "genre");
        hd_debug_print("AddFilterUI: $initial");
        $added = false;
        foreach ($filters as $name) {
            $filter = $this->get_filter($name);
            if ($filter === null) {
                hd_debug_print("AddFilterUI: no filters with '$name'");
                continue;
            }

            $values = $filter['values'];
            if (empty($values)) {
                hd_debug_print("AddFilterUI: no filters values for '$name'");
                continue;
            }

            $idx = $initial;
            if ($initial !== -1) {
                $pairs = explode(" ", $initial);
                foreach ($pairs as $pair) {
                    if (strpos($pair, $name . ":") !== false && preg_match("/^$name:(.+)/", $pair, $m)) {
                        $idx = array_search($m[1], $values) ?: -1;
                        break;
                    }
                }
            }

            Control_Factory::add_combobox($defs, $parent, null, $name,
                $filter['title'], $idx, $values, 600, true);

            Control_Factory::add_vgap($defs, 30);
            $added = true;
        }

        return $added;
    }

    /**
     * @param $user_input
     * @return string
     */
    public function CompileSaveFilterItem($user_input)
    {
        $filters = array("years", "genre");
        $compiled_string = "";
        foreach ($filters as $name) {
            $filter = $this->get_filter($name);
            if ($filter !== null && $user_input->{$name} !== -1) {
                if (!empty($compiled_string)) {
                    $compiled_string .= " ";
                }

                $compiled_string .= $name . ":" . $filter['values'][$user_input->{$name}];
            }
        }

        return $compiled_string;
    }

    /**
     * @param array|null $params
     * @param bool $to_array
     * @return false|mixed
     */
    protected function make_json_request($params = null, $to_array = false)
    {
        if (isset($this->embedded_account->vportal)) {
            $vportal = $this->embedded_account->vportal;
        } else {
            $vportal = $this->provider->getCredential(MACRO_VPORTAL);
        }

        if (empty($vportal)
            || !preg_match('/^portal::\[key:([^]]+)\](.+)$/', $vportal, $matches)) {
            hd_debug_print("incorrect or empty VPortal key");
            return false;
        }

        list(, $key, $url) = $matches;

        $pairs = array();
        if ($params !== null) {
            $pairs = $params;
        }

        // fill default params
        $pairs['key'] = $key;
        $pairs['mac'] = "000000000000"; // dummy
        $pairs['app'] = "ProIPTV_dune_plugin";

        $curl_opt = array
        (
            CURLOPT_HTTPHEADER => array("Content-Type: application/json"),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($pairs)
        );

        hd_debug_print("post_data: " . json_encode($pairs), true);

        return HD::DownloadJson($url, $to_array, $curl_opt);
    }
}
