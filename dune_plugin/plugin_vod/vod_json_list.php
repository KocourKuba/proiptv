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
 * Provider returns entire VOD in one json list, categories, search and filters are made from it.
 * The providers differ only in the layout of an item: id, year, genres and countries are read by the methods below.
 */
abstract class vod_json_list extends vod_standard
{
    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        if (parent::init_vod($provider)) {
            $this->vod_filters = array('genre', 'country', 'from', 'to');
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

        if (empty($movie_id)) {
            hd_debug_print('Movie ID is empty!');
            return null;
        }

        if (empty($this->vod_items)) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        foreach ($this->vod_items as $item) {
            if ($this->get_item_id($item) === $movie_id) {
                return $this->CreateMovie($movie_id, $item);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $perf = new Perf_Collector();
        $perf->reset('start');

        $response = $this->provider->execApiCommandResponseNoOpt(API_COMMAND_GET_VOD,
            Curl_Wrapper::RET_ARRAY, Curl_Wrapper::CACHE_RESPONSE);
        if (empty($response)) {
            $this->vod_items = false;
            $exception_msg = TR::load('err_load_vod') . "\n\n" . Curl_Wrapper::get_raw_response_headers();
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, $exception_msg);
            return false;
        }

        $this->vod_items = $response;
        $this->category_index = array();

        // all movies
        $cat_info = array(Vod_Category::FLAG_ALL_MOVIES => count($this->vod_items));
        $genres = array();
        $countries = array();
        $years = array();
        foreach ($this->vod_items as $item) {
            $category = $this->get_item_category($item);
            if (!array_key_exists($category, $cat_info)) {
                $cat_info[$category] = 0;
            }
            ++$cat_info[$category];

            // collect filters information
            $year = $this->get_item_year($item);
            $years[$year] = $year;
            $genres += $this->get_item_genres($item);
            foreach ($this->get_item_countries($item) as $country) {
                $countries[$country] = $country;
            }
        }

        foreach ($cat_info as $category => $movie_count) {
            $this->category_index[$category] = new Vod_Category($category,
                ($category === Vod_Category::FLAG_ALL_MOVIES) ? TR::t('vod_screen_all_movies__1', " ($movie_count)") : "$category ($movie_count)");
        }

        krsort($years);
        ksort($genres);
        ksort($countries);

        $exist_filters = array();
        $exist_filters['genre'] = array('title' => TR::t('genre'), 'values' => array(-1 => TR::t('no')));
        $exist_filters['country'] = array('title' => TR::t('country'), 'values' => array(-1 => TR::t('no')));
        $exist_filters['from'] = array('title' => TR::t('year_from'), 'values' => array(-1 => TR::t('no')));
        $exist_filters['to'] = array('title' => TR::t('year_to'), 'values' => array(-1 => TR::t('no')));

        $exist_filters['genre']['values'] += $genres;
        $exist_filters['country']['values'] += $countries;
        $exist_filters['from']['values'] += $years;
        $exist_filters['to']['values'] += $years;

        $this->set_filter_types($exist_filters);

        $perf->setLabel('end');
        $report = $perf->getFullReport();

        hd_debug_print('Categories read: ' . count($this->category_index));
        hd_debug_print('Total items loaded: ' . count($this->vod_items));
        hd_debug_print("Load time: {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_print_separator();

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getMovieList: $query_id");

        $arr = explode('_', $query_id);
        $category_id = isset($arr[1]) ? $arr[0] : $query_id;

        $movies = $this->collect_movies($query_id, function ($vod, $item) use ($category_id) {
            /** @var vod_json_list $vod */
            return $category_id === Vod_Category::FLAG_ALL_MOVIES || $category_id === $vod->get_item_category($item);
        });

        hd_debug_print("Movies read for query: $query_id - " . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        $enc_keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));
        $movies = $this->collect_movies($keyword, function ($vod, $item) use ($enc_keyword) {
            $name = safe_get_value($item, 'name');
            return !empty($name) && strpos(utf8_encode(mb_strtolower($name, 'UTF-8')), $enc_keyword) !== false;
        });

        hd_debug_print('Movies found: ' . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $query_id");

        $filter_params = $this->get_filter_params($query_id);
        $movies = $this->collect_movies($query_id, function ($vod, $item) use ($filter_params) {
            /** @var vod_json_list $vod */
            if (isset($filter_params['genre'])) {
                $genres = array_keys($vod->get_item_genres($item));
                if (empty($genres) || !in_array($filter_params['genre'], $genres)) {
                    return false;
                }
            }

            if (isset($filter_params['country'])) {
                $countries = $vod->get_item_countries($item);
                if (empty($countries) || !in_array($filter_params['country'], $countries)) {
                    return false;
                }
            }

            if (isset($filter_params['from']) || isset($filter_params['to'])) {
                $year = $vod->get_item_year($item);
                if ($year < safe_get_value($filter_params, 'from', ~PHP_INT_MAX) || $year > safe_get_value($filter_params, 'to', PHP_INT_MAX)) {
                    return false;
                }
            }

            return true;
        });

        hd_debug_print('Movies found: ' . count($movies));
        return $movies;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Short movies of the items accepted by the callback, the same movie only once.
     * Pagination is not used, the request is processed only once.
     *
     * @param string $query_id
     * @param callable $accept function(vod_json_list $vod, array $item): bool
     * @return Short_Movie[]
     */
    protected function collect_movies($query_id, $accept)
    {
        $movies = array();

        if (empty($this->vod_items)) {
            hd_debug_print('failed to load movies');
            return $movies;
        }

        if ($this->is_page_index_stopped($query_id)) {
            return $movies;
        }

        foreach ($this->vod_items as $item) {
            if (call_user_func($accept, $this, $item)) {
                $movie = $this->CreateShortMovie($item);
                $movies[$movie->id] = $movie;
            }
        }

        $this->stop_page_index($query_id);

        return array_values($movies);
    }

    /**
     * @param array $item
     * @return string
     */
    public function get_item_id($item)
    {
        if (isset($item['id'])) {
            return (string)$item['id'];
        }

        if (isset($item[COLUMN_SERIES_ID])) {
            return $item[COLUMN_SERIES_ID] . "_serial";
        }

        return '-1';
    }

    /**
     * @param array $item
     * @return string
     */
    public function get_item_category($item)
    {
        $category = safe_get_value($item, 'category');
        return empty($category) ? TR::load('no_category') : $category;
    }

    /**
     * @param array $item
     * @return int
     */
    public function get_item_year($item)
    {
        return (int)safe_get_value($item, array('info', 'year'), 0);
    }

    /**
     * @param array $item
     * @return array filter value => genre title
     */
    public function get_item_genres($item)
    {
        $genres = array();
        foreach (safe_get_value($item, array('info', 'genre'), array()) as $genre) {
            if (!empty($genre)) {
                $genres[$genre] = $genre;
            }
        }
        return $genres;
    }

    /**
     * @param array $item
     * @return string[]
     */
    public function get_item_countries($item)
    {
        $countries = array();
        foreach (safe_get_value($item, array('info', 'country'), array()) as $country) {
            if (!empty($country)) {
                $countries[] = $country;
            }
        }
        return $countries;
    }

    /**
     * @param string $movie_id
     * @param array $item
     * @return Movie
     */
    abstract protected function CreateMovie($movie_id, $item);

    /**
     * @param array $item
     * @return Short_Movie
     */
    abstract protected function CreateShortMovie($item);
}
