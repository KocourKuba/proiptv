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

/**
 * TorrentTV VOD
 *
 * The server paginates the catalog itself: film_filter is the paginated catalog list
 * (page/page_size with an optional genre), so the page index is the film_filter page number
 * both for the root category and for genre browsing.
 * Play URLs (HLS .m3u8) come from film_alt_http together with the season/episode info;
 * film_alt_info gives the description, ratings, genres, crew and the poster.
 * Search is served by film_alt_search, which ignores page/page_size and returns up to 50 results.
 * film_alt_genres provides the VOD categories (the genre ids of all sections are merged into one list).
 * Filters are served by film_filter (type, genre, country, year range).
 */
class vod_ttv extends vod_standard
{
    const CMD_FILM_ALT_GENRES = 'film_alt_genres';
    const CMD_FILM_FILTER = 'film_filter';
    const CMD_FILM_FILTER_COUNTRIES = 'film_filter_countries';
    const CMD_FILM_ALT_INFO = 'film_alt_info';
    const CMD_FILM_ALT_HTTP = 'film_alt_http';
    const CMD_FILM_ALT_SEARCH = 'film_alt_search';
    const FILM_PAGE_SIZE = 21;

    const POSTER_DOMAIN_FILE = "ttv_poster_domain";

    /**
     * The path and exact body size (bytes) the TorrentTV app uses to probe image domains.
     * A host is only accepted when /ok.jpg downloads with exactly this size,
     * so a captive portal or ISP hijack page answering 200 never wins.
     */
    const CHECK_PATH = '/ok.jpg';
    const CHECK_SIZE = 86998;

    /**
     * Site domains in the app's failover order. The posters are hosted on the matching mdb.<site domain> hosts
     *
     * @var array
     */
    protected $image_domains = array('ttvrun.one', 'ttv.run', 'totv.one');

    /**
     * The first healthy poster host (scheme://mdb.<site domain>)
     *
     * @var string
     */
    protected $poster_domain = '';

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        if (!parent::init_vod($provider)) {
            return false;
        }

        $this->vod_filters = array("type", "genre", "country", "from", "to");
        $this->vod_quality = true;
        $this->poster_domain = $this->resolve_poster_domain();

        return $provider->request_provider_token();
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        hd_debug_print(null, true);

        $response = $this->make_json_request(self::CMD_FILM_ALT_GENRES);
        if ($response === false) {
            hd_debug_print("film_alt_genres request failed", true);
            HD::set_last_error("vod_last_error", TR::load_string('err_load_vod'));
            return false;
        }

        $category_list = array();
        $category_index = array();

        $cat = new Vod_Category(Vod_Category::FLAG_ALL_MOVIES, TR::t('vod_screen_all_movies'));
        $category_list[] = $cat;
        $category_index[$cat->get_id()] = $cat;

        // the sections (film, serial, animation) share the same genre id space,
        // so they are merged into one list. The 'All genres' entry without a
        // genre id and the '-1' favorites pseudo genre are skipped.
        $genre_values = array(-1 => TR::t('no'));
        if (isset($response->data)) {
            foreach ($response->data as $section) {
                if (!isset($section->genres)) continue;

                foreach ($section->genres as $genre) {
                    $id = isset($genre->query->genre_id) ? (int)$genre->query->genre_id : 0;
                    if ($id <= 0 || empty($genre->name) || isset($category_index[(string)$id])) continue;

                    $cat = new Vod_Category((string)$id, $genre->name);
                    $category_list[] = $cat;
                    $category_index[$cat->get_id()] = $cat;
                    $genre_values[$id] = $genre->name;
                }
            }
        }

        $exist_filters = array(
            'type' => array(
                'title' => TR::load_string('vod_filter_type'),
                'values' => array(
                    -1 => TR::t('no'),
                    'all' => TR::load_string('vod_filter_type_all'),
                    'film' => TR::load_string('vod_filter_type_film'),
                    'serial' => TR::load_string('vod_filter_type_serial'),
                    'animation' => TR::load_string('vod_filter_type_animation')
                )
            ),
            'genre' => array('title' => TR::load_string('genre'), 'values' => $genre_values),
            'from' => array('title' => TR::load_string('year_from'), 'text' => true),
            'to' => array('title' => TR::load_string('year_to'), 'text' => true)
        );

        // the country list for the filter comes from a dedicated endpoint
        $countries = $this->make_json_request(self::CMD_FILM_FILTER_COUNTRIES);
        if (!empty($countries->data)) {
            $country_values = array(-1 => TR::t('no'));
            foreach ($countries->data as $country) {
                $country = (string)$country;
                if (!empty($country)) {
                    $country_values[$country] = $country;
                }
            }

            $exist_filters['country'] = array('title' => TR::load_string('country'), 'values' => $country_values);
        }

        $this->set_filters($exist_filters);

        hd_debug_print("Categories read: " . count($category_list));
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

        $page_idx = $this->get_next_page($query_id);
        if ($page_idx < 0) {
            return array();
        }

        // without a genre film_filter serves the whole catalog, with genres_include the genre page
        $params = array('page' => $page_idx, 'page_size' => self::FILM_PAGE_SIZE);
        if ($query_id !== Vod_Category::FLAG_ALL_MOVIES && (int)$query_id > 0) {
            $params['genres_include[]'] = (int)$query_id;
        }

        $response = $this->make_json_request(self::CMD_FILM_FILTER, $params);
        return $this->CollectQueryResult($query_id, $page_idx, $response);
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        $response = $this->make_json_request(self::CMD_FILM_ALT_SEARCH, array('q' => $keyword));
        if (!isset($response->data)) {
            return array();
        }

        // film_alt_search ignores page/page_size, the search result is a single page
        $movies = array();
        foreach ($response->data as $film) {
            $movie = $this->make_short_movie($film);
            if (!is_null($movie)) {
                $movies[] = $movie;
            }
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
        $filter_params = array();
        foreach ($pairs as $pair) {
            // type:Films
            // genre:Drama
            // from:2000
            if (!preg_match("/^(.+):(.+)$/", $pair, $m)) continue;

            $filter = $this->get_filter($m[1]);
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
            return array();
        }

        $page_id = Vod_Category::FLAG_FILTER . $params;
        $page_idx = $this->get_next_page($page_id);
        if ($page_idx < 0) {
            return array();
        }

        $query = array('page' => $page_idx, 'page_size' => self::FILM_PAGE_SIZE);
        if (isset($filter_params['type'])) {
            $query['type'] = $filter_params['type'];
        }

        if (isset($filter_params['genre'])) {
            $query['genres_include[]'] = $filter_params['genre'];
        }

        if (isset($filter_params['country'])) {
            $query['countries_include[]'] = $filter_params['country'];
        }

        foreach (array('from' => 'year_from', 'to' => 'year_to') as $key => $param_name) {
            if (!isset($filter_params[$key])) continue;

            $year = (int)$filter_params[$key];
            if ($year >= 1900 && $year <= 2100) {
                $query[$param_name] = $year;
            }
        }

        $response = $this->make_json_request(self::CMD_FILM_FILTER, $query);
        return $this->CollectQueryResult($page_id, $page_idx, $response);
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("TryLoadMovie: $movie_id");

        if (empty($movie_id)) {
            hd_debug_print("Movie ID is empty!");
            return null;
        }

        $response = $this->make_json_request(self::CMD_FILM_ALT_HTTP, array('film_id' => $movie_id));
        if (empty($response->data)) {
            hd_debug_print("no files for movie: $movie_id");
            return null;
        }

        $files = array_values((array)$response->data);

        $info = $this->make_json_request(self::CMD_FILM_ALT_INFO, array('film_id' => $movie_id));
        $film = isset($info->data) ? $info->data : new stdClass();

        $name = isset($film->name) ? $film->name : '';
        $poster_url = isset($film->poster) ? $this->get_image_url($film->poster) : '';
        $short_movie = $this->get_cached_short_movie($movie_id);
        if (!is_null($short_movie)) {
            if (empty($poster_url)) {
                $poster_url = $short_movie->poster_url;
            }
            if (empty($name)) {
                $name = $short_movie->name;
            }
        }

        if (empty($name)) {
            $name = TR::load_string('no_title');
        }

        $quality_names = array();
        foreach ($files as $file) {
            $quality = self::make_quality_name($file);
            if (!empty($quality)) {
                $quality_names[$quality] = true;
            }
        }

        $rate_details = array();
        if (!empty($quality_names)) {
            $rate_details[TR::t('vod_screen_quality')] = implode(', ', array_keys($quality_names));
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movie->set_data(
            $name,                                                              // caption,
            '',                                                                 // caption_original,
            isset($film->description) ? $film->description : '',              // description,
            $poster_url,                                                        // poster_url,
            isset($files[0]->duration) ? round($files[0]->duration / 60) : '', // length,
            isset($film->year) ? $film->year : '',                              // year,
            self::get_person_names($film, array('режисс')),                     // director,
            self::get_person_names($film, array('сценар')),                     // scenario,
            isset($film->actors) ? HD::ArrayToStr((array)$film->actors) : '',   // actors,
            isset($film->genres) ? HD::ArrayToStr((array)$film->genres) : '',   // genres,
            isset($film->imdb_rating) ? $film->imdb_rating : '',                // rate_imdb,
            isset($film->kp_rating) ? $film->kp_rating : '',                    // rate_kinopoisk,
            '',                                                                 // rate_mpaa,
            isset($film->country) ? $film->country : '',                        // country,
            '',                                                                 // budget
            array(),                                                            // details
            $rate_details                                                       // rate details
        );

        $is_serial = false;
        foreach ($files as $file) {
            if (isset($file->season) && $file->season !== false && $file->season !== '') {
                $is_serial = true;
                break;
            }
        }

        if (!$is_serial) {
            // movie: the playback variants are the quality options
            $this->add_series($movie, $movie_id, $name, $files);
        } else {
            // series: files grouped by season/episode
            $seasons = array();
            foreach ($files as $file) {
                $season = isset($file->season) ? (int)$file->season : 0;
                $episode = isset($file->episode) ? (int)$file->episode : 0;
                $seasons[$season][$episode][] = $file;
            }

            ksort($seasons);
            foreach ($seasons as $season_id => $episodes) {
                $movie->add_season_data($season_id, TR::t('vod_screen_season__1', $season_id), '');

                ksort($episodes);
                foreach ($episodes as $episode => $episode_files) {
                    $series_id = isset($episode_files[0]->tfile_id) ? (string)$episode_files[0]->tfile_id : "$season_id:$episode";
                    $this->add_series($movie, $series_id, TR::t('vod_screen_series__1', $episode), $episode_files, $season_id);
                }
            }
        }

        return $movie;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Add series with the quality variants of the files
     *
     * @param Movie $movie
     * @param string $id
     * @param string $name
     * @param array $files
     * @param string $season_id
     * @return void
     * @throws Exception
     */
    protected function add_series($movie, $id, $name, $files, $season_id = '')
    {
        $url = isset($files[0]->url) ? $files[0]->url : '';
        hd_debug_print("movie playback_url: $url");

        if (count($files) < 2) {
            $movie->add_series_data($id, $name, '', $url, $season_id);
            return;
        }

        $qualities = array();
        foreach ($files as $file) {
            if (empty($file->url)) continue;

            $quality = self::make_quality_name($file);
            if (empty($quality)) {
                $quality = (string)(count($qualities) + 1);
            }

            // the same quality can be served by several files
            $key = $quality;
            for ($i = 2; isset($qualities[$key]); $i++) {
                $key = "$quality ($i)";
            }

            $qualities[$key] = new Movie_Variant("{$id}_$key", $key, $file->url);
        }

        $series_desc = TR::load_string('vod_screen_quality') . "|" . implode(", ", array_keys($qualities));
        $movie->add_series_with_variants_data($id, $name, $series_desc, $qualities, array(), $url, $season_id);
    }

    /**
     * Short movies of the catalog page. Stops paging at the last page.
     *
     * @param string $page_id
     * @param int $page_idx
     * @param bool|Object $response
     * @return array
     */
    protected function CollectQueryResult($page_id, $page_idx, $response)
    {
        $movies = array();

        if (!isset($response->data->result)) {
            hd_debug_print("bad catalog response", true);
            $this->set_next_page($page_id, -1);
            return $movies;
        }

        foreach ($response->data->result as $film) {
            $movie = $this->make_short_movie($film);
            if (!is_null($movie)) {
                $movies[] = $movie;
            }
        }

        $total_pages = isset($response->data->pages) ? (int)$response->data->pages : 0;
        if (empty($movies) || $page_idx >= $total_pages) {
            hd_debug_print("Last page: $total_pages", true);
            $this->set_next_page($page_id, -1);
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * @param Object $film
     * @return Short_Movie|null
     */
    protected function make_short_movie($film)
    {
        $name = empty($film->rus_name) ? (isset($film->name) ? $film->name : '') : $film->rus_name;
        if (empty($film->id) || empty($name)) {
            return null;
        }

        $poster = $this->get_image_url(isset($film->poster) ? $film->poster : (isset($film->img) ? $film->img : ''));
        $info = TR::t('vod_screen_movie_info__5',
            $name,
            isset($film->year) ? $film->year : '',
            isset($film->country) ? $film->country : '',
            '',
            isset($film->kp_rating) ? $film->kp_rating : '');

        return new Short_Movie((string)$film->id, $name, $poster, $info);
    }

    /**
     * Build an absolute poster URL. The image path is relative to the MDB host media root
     * (images/movie/...), so the resolved poster domain gets a /media/ prefix.
     * Absolute URLs pass through unchanged.
     *
     * @param string $img
     * @return string
     */
    protected function get_image_url($img)
    {
        if (empty($img) || strpos($img, 'http') === 0) {
            return $img;
        }

        $domain = empty($this->poster_domain) ? 'http://mdb.' . $this->image_domains[0] : $this->poster_domain;
        return $domain . '/media/' . ltrim($img, '/');
    }

    /**
     * Select the first reachable poster host: /ok.jpg must download with exactly CHECK_SIZE bytes.
     * Plain http is tried first: the posters are fetched by the firmware UI image loader,
     * which has no TLS trust store for these hosts. The result is cached for a day.
     *
     * @return string
     */
    protected function resolve_poster_domain()
    {
        $poster_domain = HD::get_cookie(self::POSTER_DOMAIN_FILE);
        if (!empty($poster_domain)) {
            return $poster_domain;
        }

        $poster_domain = 'http://mdb.' . $this->image_domains[0];
        foreach (array('http', 'https') as $scheme) {
            foreach ($this->image_domains as $domain) {
                if ($this->is_host_reachable("$scheme://mdb.$domain")) {
                    $poster_domain = "$scheme://mdb.$domain";
                    hd_debug_print("ttv poster host: $poster_domain");
                    HD::set_cookie(self::POSTER_DOMAIN_FILE, $poster_domain, time() + 86400);
                    return $poster_domain;
                }
            }
        }

        hd_debug_print("ttv poster hosts are not reachable, use default");
        return $poster_domain;
    }

    /**
     * Reachability check of the image host: GET /ok.jpg and require exactly CHECK_SIZE bytes.
     * Curl_Wrapper has long timeouts, so the curl is called directly with the short ones.
     *
     * @param string $host
     * @return bool
     */
    protected function is_host_reachable($host)
    {
        $cmd = get_platform_curl() . " -s -k --connect-timeout 3 --max-time 5 -o /dev/null -w '%{http_code} %{size_download}' "
            . escapeshellarg($host . self::CHECK_PATH);
        $result = trim((string)shell_exec($cmd));
        hd_debug_print("probe $host: $result", true);

        return $result === "200 " . self::CHECK_SIZE;
    }

    /**
     * Names of the film_alt_info persons of the role matched by a name stem.
     * The persons structure is a role-keyed object ('Режиссеры' => array of {id, rus_name, orig_name, photo}).
     *
     * @param Object $film
     * @param array $stems
     * @return string
     */
    protected static function get_person_names($film, $stems)
    {
        if (!isset($film->persons)) {
            return '';
        }

        foreach ((array)$film->persons as $role => $group) {
            if (!is_array($group)) continue;

            $role_norm = str_replace('ё', 'е', mb_strtolower((string)$role, 'UTF-8'));
            foreach ($stems as $stem) {
                if (strpos($role_norm, $stem) === false) continue;

                $names = array();
                foreach ($group as $person) {
                    $name = empty($person->rus_name) ? (isset($person->orig_name) ? $person->orig_name : '') : $person->rus_name;
                    if (!empty($name)) {
                        $names[] = $name;
                    }
                }

                return implode(', ', $names);
            }
        }

        return '';
    }

    /**
     * Quality label of the file: 'BDRIP 1080P'
     *
     * @param Object $file
     * @return string
     */
    protected static function make_quality_name($file)
    {
        $quality = isset($file->quality) ? strtoupper($file->quality) : '';
        $hd = isset($file->hd_standard) ? strtoupper($file->hd_standard) : '';

        return trim("$quality $hd");
    }

    /**
     * Execute a TorrentTV API command with the query params appended to the command template.
     * The session can be invalidated server side at any time ('incorrect' error); in this
     * case a new session is requested and the command is retried once.
     *
     * @param string $command
     * @param array $params
     * @return bool|Object
     */
    protected function make_json_request($command, $params = array())
    {
        if (!$this->provider->request_provider_token()) {
            return false;
        }

        $curl_opt = array();
        $add_params = '';
        foreach ($params as $key => $value) {
            $add_params .= '&' . $key . '=' . rawurlencode((string)$value);
        }

        if (!empty($add_params)) {
            $curl_opt[CURLOPT_CUSTOMREQUEST] = $add_params;
        }

        $response = $this->provider->execApiCommand($command, null, true, $curl_opt);
        if (isset($response->error) && $response->error === 'incorrect') {
            hd_debug_print("ttv session rejected for $command, request new session", true);
            if ($this->provider->request_provider_token(true)) {
                $response = $this->provider->execApiCommand($command, null, true, $curl_opt);
            }
        }

        if (!isset($response->success) || (int)$response->success !== 1) {
            hd_debug_print("Wrong response: " . json_encode($response));
            return false;
        }

        return $response;
    }
}
