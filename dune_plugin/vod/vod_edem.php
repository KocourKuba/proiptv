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

class vod_edem extends vod_standard
{
    /**
     * @var string
     */
    protected $vportal_key;

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        parent::init_vod($provider);

        $this->vod_filters = array("years", "genre");
        $this->vod_quality = true;
        $vportal = $this->provider->GetParameter(MACRO_VPORTAL);
        /** @var array $matches */
        if (empty($vportal) || !preg_match(VPORTAL_PATTERN, $vportal, $matches)) {
            hd_debug_print("Incorrect or empty VPortal data: $vportal");
            $show = false;
        } else {
            $commands = $this->provider->getApiCommands();
            list(, $this->vportal_key, $commands[API_COMMAND_GET_VOD]) = $matches;
            $this->provider->setApiCommands($commands);
            $show = true;
        }

        return $show;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);
        $movieData = $this->make_json_request(array('cmd' => "flick", 'fid' => (int)$movie_id, 'offset' => 0, 'limit' => 0));

        if ($movieData === false) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $qualities_str = '';
        if ($movieData->type === 'multistream') {
            // collect series
            foreach ($movieData->items as $item) {
                $episodeData = $this->make_json_request(array('cmd' => "flick", 'fid' => (int)$item->fid, 'offset' => 0, 'limit' => 0));

                $movie_serie = new Movie_Series($item->fid, $item->title, $item->url);
                if (isset($episodeData->variants) && count((array)$episodeData->variants) === 1) {
                    $movie_serie->description = $movie->to_string(key($episodeData->variants));
                } else {
                    $variants_data = (array)$episodeData->variants;
                    $qualities_str = '';
                    foreach ($variants_data as $key => $url) {
                        $movie_serie->qualities[(string)$key] = new Movie_Variant($item->fid . "_" . $key, $key, $url);
                        if (!empty($qualities_str)) {
                            $qualities_str .= ",";
                        }
                        $qualities_str .= ($key === 'auto' ? '' : $key);
                    }

                    $movie_serie->description = TR::load('vod_screen_quality') . "|" . rtrim($qualities_str, ' ,\0');
                }
                $movie->add_series_data($movie_serie);
            }
        } else {
            $movie_serie = new Movie_Series($movie_id, $movieData->title, $movieData->url);
            if (isset($movieData->variants) && count((array)$movieData->variants) === 1) {
                $movie_serie->description = $movie->to_string(key($movieData->variants));
            } else {
                $variants_data = (array)$movieData->variants;
                foreach ($variants_data as $key => $url) {
                    $movie_serie->qualities[$key] = new Movie_Variant($movie_id . "_" . $key, $key, $url);
                    if (!empty($qualities_str)) {
                        $qualities_str .= ",";
                    }
                    $qualities_str .= ($key === 'auto' ? '' : $key);
                }

                $qualities_str = rtrim($qualities_str, ' ,\0');
                $movie_serie->description = TR::load('vod_screen_quality') . "|$qualities_str";
            }
            $movie->add_series_data($movie_serie);
        }

        $age = !empty($movieData->agelimit) ? "$movieData->agelimit+" : '';
        $age_limit = empty($age) ? array() : array(TR::t('vod_screen_age_limit') => $age);

        $movie->set_data(
            $movieData->title,      // caption
            '',         // caption_original
            isset($movieData->description) ? $movieData->description : '',  // description
            isset($movieData->img) ? $movieData->img : '',  // poster_url
            isset($movieData->duration) ? $movieData->duration : '',    // length
            isset($movieData->year) ? $movieData->year : '',    // year
            '',          // director
            '',          // scenario
            '',            // actors
            '',            // genres
            '',            // rate_imdb,
            '',         // rate_kinopoisk
            '',            // rate_mpaa
            '',              // country
            '',              // budget
            array(TR::t('vod_screen_quality') => $qualities_str), // details
            $age_limit // rate details
        );

        return $movie;
    }

    /**
     * @param array|null $params
     * @return bool|object
     */
    protected function make_json_request($params = null)
    {
        $pairs = array();
        if ($params !== null) {
            $pairs = $params;
        }

        // fill default params
        $pairs['key'] = $this->vportal_key;
        $pairs['mac'] = "000000000000"; // dummy
        $pairs['app'] = "ProIPTV_dune_plugin";

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER][] = CONTENT_TYPE_JSON;
        $curl_opt[CURLOPT_POSTFIELDS] = json_encode($pairs);

        return $this->provider->execApiCommand(API_COMMAND_GET_VOD, null, true, $curl_opt);
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        $doc = $this->make_json_request();
        if ($doc === false) {
            return false;
        }

        hd_debug_print("doc: " . pretty_json_format($doc, true), true);
        if (isset($doc->type) && $doc->type === 'error') {
            hd_debug_print($doc->description, true);
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
        if (isset($doc->controls->filters)) {
            foreach ($doc->controls->filters as $filter) {
                $first = reset($filter->items);
                $key = key(array_diff_key((array)$first->request, array('filter' => 'on')));
                $exist_filters[$key] = array('title' => $filter->title, 'values' => array(-1 => TR::t('no')));
                foreach ($filter->items as $item) {
                    $val = $item->request->{$key};
                    $exist_filters[$key]['values'][$val] = $item->title;
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
        $searchRes = $this->make_json_request(array('cmd' => "search", 'query' => $keyword));

        return $searchRes === false ? array() : $this->CollectSearchResult($keyword, $searchRes);
    }

    /**
     * @param string $query_id
     * @param object $json
     * @return array
     */
    protected function CollectSearchResult($query_id, $json)
    {
        hd_debug_print("query_id: $query_id");
        $movies = array();

        $current_offset = $this->get_current_page($query_id);
        if ($current_offset < 0)
            return $movies;

        foreach ($json->items as $entry) {
            if ($entry->type === 'next') {
                $this->get_next_page($query_id, $entry->request->offset - $current_offset);
            } else {
                $movie = new Short_Movie(
                    $entry->request->fid,
                    $entry->title,
                    $entry->imglr,
                    TR::t('vod_screen_movie_info__3', $entry->title, $entry->year)
                );
                $movie->big_poster_url = $entry->img;
                $movies[] = $movie;
            }
        }

        if ($current_offset === $this->get_current_page($query_id)) {
            $this->set_next_page($query_id, -1);
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
        $post_params = array();
        foreach ($pairs as $pair) {
            /** @var array $m */
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
            return array();
        }

        $page_idx = $this->get_next_page($params);
        if ($page_idx < 0)
            return array();

        $post_params['filter'] = 'on';
        $post_params['offset'] = $page_idx;
        $json = $this->make_json_request($post_params);

        return $json === false ? array() : $this->CollectSearchResult($params, $json);
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        $page_idx = $this->get_next_page($query_id);
        if ($page_idx < 0)
            return array();

        $post_params = array('cmd' => "flicks", 'fid' => (int)$query_id, 'offset' => $page_idx, 'limit' => 50);
        $json = $this->make_json_request($post_params);

        return $json === false ? array() : $this->CollectSearchResult($query_id, $json);
    }
}
