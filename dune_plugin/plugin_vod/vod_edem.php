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
        if (parent::init_vod($provider)) {
            $this->vod_filters = array("years", "genre");
            $show = false;
            $vportal = $this->provider->GetProviderParameter(MACRO_VPORTAL);
            /** @var array $matches */
            if (empty($vportal) || !preg_match(VPORTAL_PATTERN, $vportal, $matches)) {
                hd_debug_print("Incorrect or empty VPortal data: $vportal");
            } else {
                $commands = $this->provider->getApiCommands();
                list(, $this->vportal_key, $commands[API_COMMAND_GET_VOD]) = $matches;
                $this->provider->setApiCommands($commands);
                $show = true;
            }

            return $show;
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

        if (empty($movie_id)) {
            hd_debug_print("Movie ID is empty!");
            return null;
        }

        $post_params = array('cmd' => "flick", 'fid' => (int)$movie_id, 'offset' => 0, 'limit' => 0);
        $jsonData = $this->make_json_request($post_params);

        if (empty($jsonData)) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $type = safe_get_value($jsonData, 'type');
        if ($type === 'stream') {
            $movie->add_series_data(self::fill_variants($movie_id, $jsonData));
            $qualities_str = implode(', ', $movie->get_qualities($movie_id));
        } else if ($type === 'multistream') {
            // collect series
            foreach (safe_get_value($jsonData, 'items', array()) as $item) {
                $fid = (int)safe_get_value($item, 'fid', -1);
                if ($fid === -1) continue;

                $post_params['fid'] = $fid;
                $episodeData = $this->make_json_request($post_params);
                hd_debug_print("Episode data: " . json_format_unescaped($episodeData));
                if (!empty($episodeData)) {
                    $movie->add_series_data(self::fill_variants($fid, $episodeData));
                    if (empty($quality_str)) {
                        $qualities_str = implode(', ', $movie->get_qualities($fid));
                    }
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
        if (!empty($qualities_str)) {
            $details[TR::t('vod_screen_quality')] = $qualities_str;
        }

        $movie->set_data(
            safe_get_value($jsonData, 'title', TR::t('no_title')),      // caption
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
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $jsonData = $this->make_json_request(null);
        if (empty($jsonData)) {
            hd_debug_print("Broken response");
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, "Unknown response from server!");
            return false;
        }

        if (safe_get_value($jsonData, 'type') === 'error') {
            hd_debug_print("Error response: " . json_format_unescaped($jsonData), true);
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, safe_get_value($jsonData, 'description'));
        }

        $this->category_index = array();

        foreach (safe_get_value($jsonData, 'items', array()) as $node) {
            $request = safe_get_value($node, 'request', array());
            $fid = safe_get_value($request, 'fid');
            if (is_null($fid) || isset($request['vc'])) continue;

            $title = safe_get_value($node, 'title', TR::t('no_title'));
            $cat = new Vod_Category((string)$fid, $title);
            $this->category_index[$cat->get_id()] = $cat;
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

        $current_offset = $this->get_current_page_index($query_id);
        if ($current_offset < 0) {
            return array();
        }

        $post_params = array('cmd' => "flicks", 'fid' => (int)$query_id, 'limit' => 50, 'offset' => $current_offset);
        $movies = $this->CollectQueryResult($query_id, $this->make_json_request($post_params));
        if ($current_offset === $this->get_current_page_index($query_id)) {
            $this->stop_page_index($query_id);
        }
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        if ($this->is_page_index_stopped($keyword)) {
            return array();
        }

        $post_params = array('cmd' => "search", 'query' => $keyword);
        $movies = $this->CollectQueryResult($keyword, $this->make_json_request($post_params, false));
        // Search request return all found data without limit
        $this->stop_page_index($keyword);
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $query_id");

        $pairs = explode(",", $query_id);
        $post_params = array();
        foreach ($pairs as $pair) {
            /** @var array $m */
            if (!preg_match("/^(.+):(.+)$/", $pair, $m)) continue;
            $filter = $this->get_filter_type($m[1]);
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

        if (empty($post_params)) {
            return array();
        }

        // Filter response limited by 30 items by default
        $current_offset = $this->get_current_page_index($query_id);
        if ($current_offset < 0) {
            return array();
        }

        $post_params['filter'] = 'on';
        $post_params['limit'] = 50;
        $post_params['offset'] = $current_offset;
        $movies = $this->CollectQueryResult($query_id, $this->make_json_request($post_params, false));
        if ($current_offset === $this->get_current_page_index($query_id)) {
            $this->stop_page_index($query_id);
        }
        return $movies;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $query_id
     * @param array $requestData
     * @return array
     */
    protected function CollectQueryResult($query_id, $requestData)
    {
        hd_debug_print("query_id: $query_id", true);
        $movies = array();
        if ($requestData === false || $requestData === null) {
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
                $current_offset = $this->get_current_page_index($query_id);
                $next_offset = safe_get_value($request, 'offset', $current_offset);
                $this->shift_next_page_index($query_id, $next_offset - $current_offset);
            } else {
                $title = safe_get_value($entry, 'title');
                $movie = new Short_Movie(
                    safe_get_value($request, 'fid'),
                    $title,
                    safe_get_value($entry, 'imglr'),
                    TR::t('vod_screen_movie_info__3', $title, safe_get_value($entry, 'year'))
                );
                $movie->big_poster_url = safe_get_value($entry, 'img');
                $this->plugin->vod->set_cached_short_movie($movie);
                $movies[] = $movie;
            }
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
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
     * @param bool $cache_response
     * @return bool|array
     */
    protected function make_json_request($params, $cache_response = true)
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

        $decode = Curl_Wrapper::RET_ARRAY;
        if ($cache_response) {
            $decode |= Curl_Wrapper::CACHE_RESPONSE;
        }
        return $this->provider->execApiCommandResponse(API_COMMAND_GET_VOD, $curl_opt, $decode);
    }
}
