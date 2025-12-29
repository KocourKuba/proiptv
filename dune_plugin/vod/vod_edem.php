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
        $vportal = $this->provider->GetProviderParameter(MACRO_VPORTAL);
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
        if (empty($movie_id)) {
            hd_debug_print("Movie ID is empty!");
            return null;
        }

        $post_params = array('cmd' => "flick", 'fid' => (int)$movie_id, 'offset' => 0, 'limit' => 0);
        $jsonData = $this->make_json_request($post_params, true);

        if ($jsonData === false) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        hd_debug_print(json_format_unescaped($jsonData), true);
        $movie = new Movie($movie_id, $this->plugin);
        $type = safe_get_value($jsonData, 'type');
        if ($type === 'stream') {
            $movie->add_series_data(self::fill_variants($movie_id, $jsonData));
        } else if ($type === 'multistream') {
            // collect series
            foreach (safe_get_value($jsonData, 'items', array()) as $item) {
                $fid = (int)safe_get_value($item, 'fid', -1);
                if ($fid === -1) continue;

                $post_params['fid'] = $fid;
                $episodeData = $this->make_json_request($post_params, true);
                if ($episodeData !== false) {
                    $movie->add_series_data(self::fill_variants($fid, $episodeData));
                }
            }
        } else {
            hd_debug_print("Unsupported type: '$type' for '$movie_id'");
            return null;
        }

        $rate_details = array();

        $age = safe_get_value($jsonData, 'agelimit');
        if (!empty($age)) {
            $rate_details[TR::t('vod_screen_age_limit')] = "$age+";
        }

        if (safe_get_value($jsonData, 'fhd', false)) {
            $rate_details['Full HD'] = TR::load('yes');
        }

        if (safe_get_value($jsonData, '4k', false)) {
            $rate_details['4K'] = TR::load('yes');
        }

        if (safe_get_value($jsonData, 'hdr', false)) {
            $rate_details['HDR'] = TR::load('yes');
        }

        $details = array();
        if (!empty($qualities)) {
            sort($qualities);
            $details[TR::t('vod_screen_quality')] = implode(',', array_unique($qualities));
        }

        $movie->set_data(
            safe_get_value($jsonData, 'title', 'no title'),      // caption
            '',         // caption_original
            safe_get_value($jsonData, 'description', ''),  // description
            safe_get_value($jsonData, 'img', ''),  // poster_url
            safe_get_value($jsonData, 'duration', ''),    // length
            safe_get_value($jsonData, 'year', ''),    // year
            '',          // director
            '',          // scenario
            '',            // actors
            '',            // genres
            '',            // rate_imdb,
            '',         // rate_kinopoisk
            '',            // rate_mpaa
            '',              // country
            '',              // budget
            $details, // details
            $rate_details // rate details
        );

        return $movie;
    }

    /**
     * @param string $movie_id
     * @param array $movieData
     * @return Movie_Series
     */
    protected static function fill_variants($movie_id, $movieData)
    {
        hd_debug_print("Default playback_url for {$movieData['title']}: {$movieData['url']}");
        $movie_serie = new Movie_Series($movie_id, $movieData['title'], new Movie_Playback_Url($movieData['url']));
        $variants = safe_get_value($movieData, 'variants', array());
        $movie_serie->description = TR::load('vod_screen_quality') . '|' . implode(',', array_diff(array_keys($variants), array('auto')));
        foreach ($variants as $key => $url) {
            if ($key !== 'auto') {
                $movie_serie->add_variant_data($key, new Movie_Variant($key, new Movie_Playback_Url($url)));
            }
        }

        return $movie_serie;
    }

    /**
     * @param array|null $params
     * @return bool|object|array
     */
    protected function make_json_request($params = null, $assoc = false)
    {
        $pairs = array();
        if ($params !== null) {
            $pairs = $params;
        }

        // fill default params
        $pairs['key'] = $this->vportal_key;
        $pairs['mac'] = "000000000000"; // dummy
        $pairs['app'] = "ProIPTV_dune_plugin";

        $curl_opt[CURLOPT_POST] = 1;
        $curl_opt[CURLOPT_HTTPHEADER][] = CONTENT_TYPE_JSON;
        $curl_opt[CURLOPT_POSTFIELDS] = $pairs;

        return $this->provider->execApiCommand(API_COMMAND_GET_VOD, null, $curl_opt, $assoc ? Curl_Wrapper::RET_ARRAY : Curl_Wrapper::RET_OBJECT);
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        hd_debug_print(null, true);

        $jsonData = $this->make_json_request(null, true);
        if ($jsonData === false) {
            hd_debug_print("Broken response");
            return false;
        }

        if (safe_get_value($jsonData, 'type') === 'error') {
            hd_debug_print("Error response: " . json_format_unescaped($jsonData), true);
        }

        $category_list = array();
        $category_index = array();

        foreach (safe_get_value($jsonData, 'items', array()) as $node) {
            $request = safe_get_value($node, 'request', array());
            if (!isset($request['fid'])) continue;

            $title = safe_get_value($node, 'title', 'no title');
            $cat = new Vod_Category((string)$request['fid'], $title);
            $category_list[] = $cat;
            $category_index[$cat->get_id()] = $cat;
        }

        $exist_filters = array();
        $controls = safe_get_value($jsonData, 'controls', array());
        $filters = safe_get_value($controls, 'filters', array());
        foreach ($filters as $filter) {
            $first = reset($filter['items']);
            $key = key(array_diff_key($first['request'], array('filter' => 'on')));
            $exist_filters[$key] = array('title' => $filter['title'], 'values' => array(-1 => TR::t('no')));
            foreach ($filter['items'] as $item) {
                $val = $item['request'][$key];
                $exist_filters[$key]['values'][$val] = $item['title'];
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
        $post_params = array('cmd' => "search", 'query' => $keyword);
        return $this->CollectSearchResult($keyword, $this->make_json_request($post_params, true));
    }

    /**
     * @param string $query_id
     * @param array $requestData
     * @return array
     */
    protected function CollectSearchResult($query_id, $requestData)
    {
        hd_debug_print("query_id: $query_id", true);
        $movies = array();
        if ($requestData === false) {
            return $movies;
        }

        $current_offset = $this->get_current_page($query_id);
        if ($current_offset < 0) {
            return $movies;
        }

        if (!isset($requestData['items'])) {
            hd_debug_print("No items in query! " . json_format_unescaped($requestData), true);
            return $movies;
        }

        foreach ($requestData['items'] as $entry) {
            $request = safe_get_value($entry, 'request');
            $type = safe_get_value($entry, 'type');
            if (empty($type)) continue;

            if ($type === 'next') {
                $this->get_next_page($query_id, safe_get_value($request, 'offset', $current_offset) - $current_offset);
            } else {
                $title = safe_get_value($entry, 'title');
                $movie = new Short_Movie(
                    safe_get_value($request, 'fid'),
                    $title,
                    safe_get_value($entry, 'imglr'),
                    TR::t('vod_screen_movie_info__3', $title, safe_get_value($entry, 'year'))
                );
                $movie->big_poster_url = safe_get_value($entry, 'img');
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
        return $this->CollectSearchResult($params, $this->make_json_request($post_params, true));
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        $page_idx = $this->get_current_page($query_id);
        if ($page_idx < 0) {
            return array();
        }

        $post_params = array('cmd' => "flicks", 'fid' => (int)$query_id, 'offset' => $page_idx, 'limit' => 50);
        return $this->CollectSearchResult($query_id, $this->make_json_request($post_params, true));
    }
}
