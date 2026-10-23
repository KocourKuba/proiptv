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
 * TorrentTV VOD. The catalog is loaded page by page through the film_* API commands.
 *
 * get_vod (film_list): data.films[] {id, name, rus_name, img, year, country, kp_rating}, data.total_pages
 * film_filter: data.result[] {id, name, rus_name, poster, ...}, data.pages, data.count
 * film_alt_genres: data[] {genres[] {name, query {genre_id}}}
 * film_alt_info: data {name, description, poster, year, country, genres[], actors[], persons {role => [{rus_name, orig_name}]}, imdb_rating, kp_rating}
 * film_alt_http: data[] {url, quality, hd_standard, duration, season, episode, tfile_id}
 */
class vod_ttv extends vod_standard
{
    const CMD_FILM_ALT_GENRES = 'film_alt_genres';
    const CMD_FILM_FILTER = 'film_filter';
    const CMD_FILM_FILTER_COUNTRIES = 'film_filter_countries';
    const CMD_FILM_ALT_INFO = 'film_alt_info';
    const CMD_FILM_ALT_HTTP = 'film_alt_http';
    const CMD_FILM_LIST = API_COMMAND_GET_VOD;
    const FILM_PAGE_SIZE = 30;
    const SEARCH_PAGE_SIZE = 50;

    /**
     * Poster hosts are probed by the file that the TorrentTV app uses:
     * a host is accepted only if the file has exactly this size,
     * so a captive portal or a hijacked page answering 200 is never selected.
     */
    const CHECK_PATH = '/ok.jpg';
    const CHECK_SIZE = 86998;
    const COOKIE_POSTER_DOMAIN = 'ttv_poster_domain';

    /**
     * film_filter types shown as categories
     *
     * @var array
     */
    protected static $category_types = array('film', 'serial', 'animation');

    /**
     * Site domains, the posters are hosted on mdb.<site domain>
     *
     * @var array
     */
    protected static $image_domains = array('ttvrun.one', 'ttv.run', 'totv.one');

    /**
     * @var api_ttv
     */
    protected $provider;

    /**
     * @var string
     */
    protected $poster_domain = '';

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        if (parent::init_vod($provider)) {
            $this->vod_filters = array('type', 'genre', 'country', 'from', 'to');
            $this->vod_quality = true;

            return $this->provider->request_provider_token();
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        hd_debug_print(null, true);

        $category_list = array();
        $category_index = array();

        $response = $this->make_json_request(self::CMD_FILM_ALT_GENRES);
        if ($response === false) {
            HD::set_last_error($this->plugin->get_vod_error_name(), TR::load('err_load_vod'));
            return false;
        }

        $category = new Vod_Category(Vod_Category::FLAG_ALL_MOVIES, TR::load('vod_screen_all_movies'));
        $category_list[] = $category;
        $category_index[Vod_Category::FLAG_ALL_MOVIES] = $category;

        $type_values = array(-1 => TR::t('no'), 'all' => TR::load('vod_filter_type_all'));
        foreach (self::$category_types as $type) {
            $caption = TR::load("vod_filter_type_$type");
            $type_values[$type] = $caption;
            $category = new Vod_Category($type, $caption);
            $category_list[] = $category;
            $category_index[$type] = $category;
        }

        // sections (film, serial, animation) share the same genre ids, so they are merged into one list.
        // 'All genres' entry without genre id and '-1' favorites pseudo genre are skipped.
        $genre_values = array(-1 => TR::t('no'));
        foreach (self::to_list(safe_get_member($response, 'data')) as $section) {
            foreach (self::to_list(safe_get_member($section, 'genres')) as $genre) {
                $id = isset($genre->query->genre_id) ? (int)$genre->query->genre_id : 0;
                if ($id <= 0 || empty($genre->name) || isset($genre_values[$id])) continue;

                $genre_values[$id] = $genre->name;
                $category = new Vod_Category((string)$id, $genre->name);
                $category_list[] = $category;
                $category_index[(string)$id] = $category;
            }
        }

        $exist_filters = array(
            'type' => array('title' => TR::load('vod_filter_type'), 'values' => $type_values),
            'genre' => array('title' => TR::load('genre'), 'values' => $genre_values),
            'from' => array('title' => TR::load('year_from'), 'text' => true),
            'to' => array('title' => TR::load('year_to'), 'text' => true),
        );

        $response = $this->make_json_request(self::CMD_FILM_FILTER_COUNTRIES);
        $countries = self::to_list(safe_get_member($response, 'data'));
        if (!empty($countries)) {
            $country_values = array(-1 => TR::t('no'));
            foreach ($countries as $country) {
                $country = (string)$country;
                if (!empty($country)) {
                    $country_values[$country] = $country;
                }
            }
            $exist_filters['country'] = array('title' => TR::load('country'), 'values' => $country_values);
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

        $params = array('page' => $page_idx, 'page_size' => self::FILM_PAGE_SIZE);

        // film_list ignores the type, so the type categories are served by film_filter
        if (in_array($query_id, self::$category_types)) {
            $params['type'] = $query_id;
            return $this->CollectFilterResult($query_id, $page_idx, $this->make_json_request(self::CMD_FILM_FILTER, $params));
        }

        if ($query_id !== Vod_Category::FLAG_ALL_MOVIES) {
            $params['genre'] = (int)$query_id;
        }

        return $this->CollectListResult($query_id, $page_idx, $this->make_json_request(self::CMD_FILM_LIST, $params));
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        // search list is loaded only once by the movie list screen, so only the first page is requested
        $page_id = Vod_Category::FLAG_SEARCH . "_$keyword";
        $params = array('search' => $keyword, 'page' => 1, 'page_size' => self::SEARCH_PAGE_SIZE);

        return $this->CollectListResult($page_id, 1, $this->make_json_request(self::CMD_FILM_LIST, $params));
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($params)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $params");

        $filter_params = array();
        foreach (explode(",", $params) as $pair) {
            // type:Films
            // genre:Action
            // from:2020
            /** @var array $m */
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

        $page_id = Vod_Category::FLAG_FILTER . "_$params";
        $page_idx = $this->get_next_page($page_id);
        if ($page_idx < 0) {
            return array();
        }

        $query = array('page' => $page_idx, 'page_size' => self::FILM_PAGE_SIZE);

        // film_list ignores type, country and years, so only the genre filter can be served by it
        $type = safe_get_value($filter_params, 'type', 'all');
        if (isset($filter_params['genre']) && $type === 'all'
            && !isset($filter_params['country']) && !isset($filter_params['from']) && !isset($filter_params['to'])) {
            $query['genre'] = (int)$filter_params['genre'];
            return $this->CollectListResult($page_id, $page_idx, $this->make_json_request(self::CMD_FILM_LIST, $query));
        }

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

        return $this->CollectFilterResult($page_id, $page_idx, $this->make_json_request(self::CMD_FILM_FILTER, $query));
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);

        $response = $this->make_json_request(self::CMD_FILM_ALT_HTTP, array('film_id' => $movie_id));
        $files = array();
        foreach (self::to_list(safe_get_member($response, 'data')) as $file) {
            if (!empty($file->url)) {
                $files[] = $file;
            }
        }

        if (empty($files)) {
            hd_debug_print("failed to load movie files: $movie_id");
            return null;
        }

        $response = $this->make_json_request(self::CMD_FILM_ALT_INFO, array('film_id' => $movie_id));
        $film = isset($response->data) && is_object($response->data) ? $response->data : new stdClass();

        $name = safe_get_member($film, 'name', '');
        $poster_url = $this->get_image_url(safe_get_member($film, 'poster', ''));
        $short_movie = $this->get_cached_short_movie($movie_id);
        if (!is_null($short_movie)) {
            if (empty($name)) {
                $name = $short_movie->name;
            }

            if (empty($poster_url)) {
                $poster_url = $short_movie->poster_url;
            }
        }

        if (empty($name)) {
            $name = TR::load('no_title');
        }

        $movie = new Movie($movie_id, $this->plugin);

        $is_serial = false;
        foreach ($files as $file) {
            if (isset($file->season)) {
                $is_serial = true;
                break;
            }
        }

        $qualities = array();
        foreach ($files as $file) {
            $quality = self::get_quality_label($file);
            if (!empty($quality)) {
                $qualities[$quality] = $quality;
            }
        }

        if (!$is_serial) {
            $movie_serie = new Movie_Series($movie_id, $name, $files[0]->url);
            if (count($files) > 1) {
                self::add_qualities($movie_serie, $movie_id, $files);
            }
            $movie->add_series_data($movie_serie);
        } else {
            // files are grouped by season/episode, each episode can have several qualities
            $seasons = array();
            foreach ($files as $file) {
                $season = isset($file->season) ? (int)$file->season : 0;
                $episode = isset($file->episode) ? (int)$file->episode : 0;
                $seasons[$season][$episode][] = $file;
            }

            ksort($seasons);
            foreach ($seasons as $season_id => $episodes) {
                $movie->add_season_data(new Movie_Season($season_id));

                ksort($episodes);
                foreach ($episodes as $episode => $episode_files) {
                    $first = $episode_files[0];
                    $series_id = isset($first->tfile_id) ? (string)$first->tfile_id : "$season_id:$episode";
                    $movie_serie = new Movie_Series($series_id,
                        TR::t('vod_screen_series__1', $episode),
                        $first->url,
                        $season_id
                    );

                    if (count($episode_files) > 1) {
                        self::add_qualities($movie_serie, $series_id, $episode_files);
                    }
                    $movie->add_series_data($movie_serie);
                }
            }
        }

        $genres = implode(", ", self::to_list(safe_get_member($film, 'genres')));
        $actors = implode(", ", self::to_list(safe_get_member($film, 'actors')));

        $movie->set_data(
            $name,                                                  // caption,
            '',                                                     // caption_original,
            safe_get_member($film, 'description', ''),              // description,
            $poster_url,                                            // poster_url,
            isset($files[0]->duration) ? (int)round($files[0]->duration / 60) : '', // length,
            safe_get_member($film, 'year', ''),                     // year,
            self::get_person_names($film, array('режисс')),         // director,
            self::get_person_names($film, array('сценар')),         // scenario,
            $actors,                                                // actors,
            $genres,                                                // genres,
            safe_get_member($film, 'imdb_rating', ''),              // rate_imdb,
            safe_get_member($film, 'kp_rating', ''),                // rate_kinopoisk,
            '',                                                     // rate_mpaa,
            safe_get_member($film, 'country', ''),                  // country,
            '',                                                     // budget
            empty($qualities) ? array() : array(TR::t('vod_screen_quality') => implode(", ", $qualities)) // details
        );

        return $movie;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Collect film_list response: data.films[] and data.total_pages
     *
     * @param string $page_id
     * @param int $page_idx
     * @param object|bool $response
     * @return Short_Movie[]
     */
    protected function CollectListResult($page_id, $page_idx, $response)
    {
        $data = safe_get_member($response, 'data');
        $films = self::to_list(safe_get_member($data, 'films'));
        $total_pages = (int)safe_get_member($data, 'total_pages', 0);

        return $this->CollectMovies($page_id, $page_idx, $films, $total_pages, 'img');
    }

    /**
     * Collect film_filter response: data.result[] and data.pages
     *
     * @param string $page_id
     * @param int $page_idx
     * @param object|bool $response
     * @return Short_Movie[]
     */
    protected function CollectFilterResult($page_id, $page_idx, $response)
    {
        $data = safe_get_member($response, 'data');
        $films = self::to_list(safe_get_member($data, 'result'));
        $total_pages = (int)safe_get_member($data, 'pages', 0);

        return $this->CollectMovies($page_id, $page_idx, $films, $total_pages, 'poster');
    }

    /**
     * @param string $page_id
     * @param int $page_idx
     * @param array $films
     * @param int $total_pages
     * @param string $poster_field
     * @return Short_Movie[]
     */
    protected function CollectMovies($page_id, $page_idx, $films, $total_pages, $poster_field)
    {
        $movies = array();
        foreach ($films as $film) {
            if (!is_object($film)) continue;

            $name = empty($film->rus_name) ? safe_get_member($film, 'name') : $film->rus_name;
            if (empty($film->id) || empty($name)) continue;

            $movies[] = new Short_Movie(
                (string)$film->id,
                $name,
                $this->get_image_url(safe_get_member($film, $poster_field, '')),
                TR::t('vod_screen_movie_info__5',
                    $name,
                    safe_get_member($film, 'year', ''),
                    safe_get_member($film, 'country', ''),
                    '',
                    safe_get_member($film, 'kp_rating', '')
                )
            );
        }

        if (empty($films) || $page_idx >= $total_pages) {
            hd_debug_print("Last page: $page_idx");
            $this->set_next_page($page_id, -1);
        }

        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * Poster path is relative to the media root of the poster host, absolute urls are used as is.
     * The poster host is resolved on first use.
     *
     * @param string $img
     * @return string
     */
    protected function get_image_url($img)
    {
        if (empty($img) || strpos($img, 'http') === 0) {
            return $img;
        }

        if (empty($this->poster_domain)) {
            $this->poster_domain = $this->resolve_poster_domain();
        }

        return $this->poster_domain . '/media/' . ltrim($img, '/');
    }

    /**
     * Select the first poster host that returns the check file.
     * Plain http is tried first: the posters are loaded by the firmware image loader
     * that can't verify the certificates of these hosts.
     * The result is cached in the playlist cookies to not probe the hosts on every VOD start,
     * the fallback host is cached for a shorter time.
     *
     * @return string
     */
    protected function resolve_poster_domain()
    {
        $poster_domain = $this->plugin->get_cookie(self::COOKIE_POSTER_DOMAIN, true);
        if (!empty($poster_domain)) {
            hd_debug_print("cached poster host: $poster_domain", true);
            return $poster_domain;
        }

        $curl_wrapper = Curl_Wrapper::getInstance();
        foreach (array('http', 'https') as $scheme) {
            foreach (self::$image_domains as $domain) {
                $poster_domain = "$scheme://mdb.$domain";
                $curl_wrapper->reset();
                $curl_wrapper->set_connection_timeout(2);
                $curl_wrapper->set_download_timeout(4);
                $content = $curl_wrapper->download_content($poster_domain . self::CHECK_PATH);
                if ($content !== false && strlen($content) === self::CHECK_SIZE) {
                    hd_debug_print("poster host: $poster_domain");
                    $this->plugin->set_cookie(self::COOKIE_POSTER_DOMAIN, $poster_domain, time() + 86400);
                    return $poster_domain;
                }
            }
        }

        $poster_domain = 'http://mdb.' . self::$image_domains[0];
        hd_debug_print("poster hosts are not reachable, use default: $poster_domain");
        $this->plugin->set_cookie(self::COOKIE_POSTER_DOMAIN, $poster_domain, time() + 3600);
        return $poster_domain;
    }

    /**
     * @param Movie_Series $movie_serie
     * @param string $id
     * @param array $files
     * @return void
     */
    protected static function add_qualities($movie_serie, $id, $files)
    {
        foreach (self::make_quality_names($files) as $idx => $quality) {
            $movie_serie->qualities[$quality] = new Movie_Variant("{$id}_$idx", $quality, $files[$idx]->url);
        }
    }

    /**
     * Unique quality names of the files: 'BDRIP 1080P', 'BDRIP 1080P (2)'.
     * File without quality information is named by its number: '#3'
     *
     * @param array $files
     * @return array file index => quality name
     */
    protected static function make_quality_names($files)
    {
        $names = array();
        $used = array();
        foreach ($files as $idx => $file) {
            $name = self::get_quality_label($file);
            if (empty($name)) {
                $name = '#' . ($idx + 1);
            }

            if (isset($used[$name])) {
                $used[$name]++;
                $name .= " ($used[$name])";
            } else {
                $used[$name] = 1;
            }

            $names[$idx] = $name;
        }

        return $names;
    }

    /**
     * Quality label of the file: 'BDRIP 1080P'
     *
     * @param object $file
     * @return string
     */
    protected static function get_quality_label($file)
    {
        return trim(strtoupper(safe_get_member($file, 'quality', '') . ' ' . safe_get_member($file, 'hd_standard', '')));
    }

    /**
     * Provider can send the list as JSON array or as JSON object
     *
     * @param mixed $value
     * @return array
     */
    protected static function to_list($value)
    {
        if (is_array($value) || is_object($value)) {
            return array_values((array)$value);
        }

        return array();
    }

    /**
     * Names of the persons with the role matched by the name stem.
     * persons is an object keyed by the role name: {"Режиссеры": [{"rus_name": "", "orig_name": ""}]}
     *
     * @param object $film
     * @param array $stems
     * @return string
     */
    protected static function get_person_names($film, $stems)
    {
        if (!isset($film->persons) || !is_object($film->persons)) {
            return '';
        }

        foreach ((array)$film->persons as $role => $group) {
            $role = str_replace('ё', 'е', mb_strtolower((string)$role, 'UTF-8'));
            foreach ($stems as $stem) {
                if (strpos($role, $stem) === false) continue;

                $names = array();
                foreach (self::to_list($group) as $person) {
                    if (!is_object($person)) continue;

                    $name = empty($person->rus_name) ? safe_get_member($person, 'orig_name') : $person->rus_name;
                    if (!empty($name)) {
                        $names[] = $name;
                    }
                }

                return implode(", ", $names);
            }
        }

        return '';
    }

    /**
     * Execute API command with the query parameters appended to the command template
     *
     * @param string $command
     * @param array $params
     * @return bool|object
     */
    protected function make_json_request($command, $params = array())
    {
        $curl_opt = array();
        $add_params = '';
        foreach ($params as $key => $value) {
            $add_params .= '&' . $key . '=' . rawurlencode((string)$value);
        }

        if (!empty($add_params)) {
            $curl_opt[CURLOPT_CUSTOMREQUEST] = $add_params;
        }

        $data = $this->provider->execTtvCommand($command, $curl_opt);
        if (!isset($data->success) || (int)$data->success !== 1) {
            hd_debug_print("Wrong response: " . json_encode($data));
            return false;
        }

        return $data;
    }
}
