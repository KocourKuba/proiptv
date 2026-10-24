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
 * TorrentTV VOD
 */
class vod_ttv extends vod_standard
{
    const CMD_FILM_ALT_GENRES = 'film_alt_genres';
    const CMD_FILM_FILTER = 'film_filter';
    const CMD_FILM_FILTER_COUNTRIES = 'film_filter_countries';
    const CMD_FILM_ALT_INFO = 'film_alt_info';
    const CMD_FILM_ALT_HTTP = 'film_alt_http';
    const CMD_FILM_ALT_SEARCH = 'film_alt_search';
    const CMD_FILM_LIST = 'film_list';
    const FILM_PAGE_SIZE = 30;
    const CATEGORY_MODE_GENRE = 'genre';
    const CATEGORY_MODE_TYPE = 'type';

    /**
     * film_filter types shown as categories in the type category mode
     *
     * @var array
     */
    protected static $category_types = array('film', 'serial', 'animation');

    /**
     * The path and exact body size (bytes) the TorrentTV app uses to probe
     * image domains (DomainResolver.CHECK_PATH / EXPECTED_SIZE). Both the
     * site hosts and the mdb.<site> poster hosts serve this file, so the
     * same probe selects either. A host is only accepted when /ok.jpg
     * downloads with exactly this size, so a captive portal or ISP hijack
     * page answering 200 never wins.
     */
    const CHECK_PATH = '/ok.jpg';
    const CHECK_SIZE = 86998;

    /**
     * Site domains in the app's failover order (DomainResolver.SITE_DOMAINS:
     * ttvrun.one, ttv.run, totv.one). The posters are hosted on the matching
     * mdb.<site domain> hosts (DomainResolver.MDB_DOMAINS), so the list is
     * used to derive the poster hosts to probe.
     *
     * @var array
     */
    protected $image_domains = array('ttvrun.one', 'ttv.run', 'totv.one');

    /**
     * The first healthy poster host (scheme://mdb.<site domain>), resolved
     * at VOD initialization with the same /ok.jpg probe the app's
     * DomainResolver performs on its MDB domains.
     *
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
            $this->poster_domain = $this->resolve_poster_domain();
            return $provider->request_provider_token(true);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function get_category_modes()
    {
        return array(
            self::CATEGORY_MODE_GENRE => TR::t('genre'),
            self::CATEGORY_MODE_TYPE => TR::t('vod_filter_type')
        );
    }

    /**
     * @inheritDoc
     */
    public function get_category_mode()
    {
        $mode = $this->provider->GetProviderParameter(PARAM_VOD_CATEGORY_MODE, self::CATEGORY_MODE_GENRE);
        return $mode === self::CATEGORY_MODE_TYPE ? self::CATEGORY_MODE_TYPE : self::CATEGORY_MODE_GENRE;
    }

    /**
     * @inheritDoc
     */
    public function set_category_mode($mode)
    {
        $this->provider->SetProviderParameter(PARAM_VOD_CATEGORY_MODE, $mode);
        $this->category_index = null;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $response = $this->make_json_request(self::CMD_FILM_ALT_GENRES, array());
        if ($response === false || (int)safe_get_value($response, 'success') !== 1) {
            hd_debug_print('film_alt_genres request failed', true);
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, TR::load('err_load_vod'));
            return false;
        }

        $this->category_index = array();
        $sections = safe_get_value($response, 'data', array());
        if (!is_array($sections)) {
            $sections = array();
        }

        // in the type mode the categories are the film_filter types with their film count,
        // the genres are still collected for the filter
        $by_type = $this->get_category_mode() === self::CATEGORY_MODE_TYPE;
        if ($by_type) {
            foreach (self::$category_types as $type) {
                $caption = TR::load("vod_filter_type_$type");
                $count = $this->get_type_film_count($type);
                if ($count >= 0) {
                    $caption .= " ($count)";
                }
                $this->category_index[$type] = new Vod_Category($type, $caption);
            }
        }

        // the sections (film, serial, animation) share the same genre id space,
        // so they are merged into one list. The 'All genres' entry without a
        // genre id and the '-1' favorites pseudo genre are skipped.
        $genre_values = array(-1 => TR::t('no'));
        foreach ($sections as $section) {
            $genres = safe_get_value($section, 'genres', array());
            if (!is_array($genres)) continue;

            foreach ($genres as $genre) {
                $query = safe_get_value($genre, 'query', array());
                $id = (int)safe_get_value($query, 'genre_id');
                $name = safe_get_value($genre, 'name');
                if ($id <= 0 || empty($name) || isset($genre_values[(string)$id])) continue;

                if (!$by_type) {
                    $this->category_index[(string)$id] = new Vod_Category((string)$id, $name);
                }
                $genre_values[(string)$id] = $name;
            }
        }

        // filter types for the film_filter request
        $exist_filters = array(
            'type' => array(
                'title' => TR::t('vod_filter_type'),
                'values' => array(
                    -1 => TR::t('no'),
                    'all' => TR::t('vod_filter_type_all'),
                    'film' => TR::t('vod_filter_type_film'),
                    'serial' => TR::t('vod_filter_type_serial'),
                    'animation' => TR::t('vod_filter_type_animation')
                )
            ),
            'genre' => array('title' => TR::t('genre'), 'values' => $genre_values),
            'from' => array('title' => TR::t('year_from'), 'text' => true),
            'to' => array('title' => TR::t('year_to'), 'text' => true)
        );

        // the country list for the filter comes from a dedicated endpoint
        $countries_response = $this->make_json_request(self::CMD_FILM_FILTER_COUNTRIES, array());
        if ($countries_response !== false && (int)safe_get_value($countries_response, 'success') === 1) {
            $countries = safe_get_value($countries_response, 'data', array());
            if (is_array($countries) && !empty($countries)) {
                $country_values = array(-1 => TR::t('no'));
                foreach ($countries as $country) {
                    $country = (string)$country;
                    if (!empty($country)) {
                        $country_values[$country] = $country;
                    }
                }

                $exist_filters['country'] = array('title' => TR::t('country'), 'values' => $country_values);
            }
        }

        $this->set_filter_types($exist_filters);

        hd_debug_print('Categories read: ' . count($this->category_index));
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getMovieList: $query_id");

        // the page index is the 1-based page number of the film_list/film_filter request
        $page = $this->get_current_page_index($query_id, 1);
        if ($page < 0) {
            return array();
        }

        $params = array('page' => $page, 'page_size' => self::FILM_PAGE_SIZE);
        $is_type = in_array($query_id, self::$category_types, true);
        $genre_id = $is_type ? 0 : (int)$query_id;

        // film_list serves the whole catalog and the genre browsing page (genre param),
        // but ignores the type, so the type browsing page is served by film_filter
        if (!$is_type && $this->provider->hasApiCommand(self::CMD_FILM_LIST)) {
            if ($genre_id > 0) {
                $params['genre'] = $genre_id;
            }

            $response = $this->make_json_request(self::CMD_FILM_LIST, $params);
            return $this->CollectQueryResult($query_id, $page, $response);
        }

        if ($is_type) {
            $params['type'] = $query_id;
        } else if ($genre_id > 0) {
            $params['genres_include[]'] = $genre_id;
        }

        $response = $this->make_json_request(self::CMD_FILM_FILTER, $params);
        return $this->CollectFilterResult($query_id, $page, $response);
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        $page = $this->get_current_page_index($keyword, 1);
        if ($page < 0) {
            return array();
        }

        if (!$this->provider->hasApiCommand(self::CMD_FILM_LIST)) {
            $response = $this->make_json_request(self::CMD_FILM_ALT_SEARCH, array('q' => $keyword), false);
            return $this->CollectSearchResult($keyword, $page, $response);
        }

        $response = $this->make_json_request(self::CMD_FILM_LIST,
            array('search' => $keyword, 'page' => $page, 'page_size' => self::FILM_PAGE_SIZE), false);
        // film_list already has the common shape: data.films with the relative 'img' poster path
        // and data.total_pages
        return $this->CollectQueryResult($keyword, $page, $response);
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $query_id");

        $filter_params = $this->get_filter_params($query_id);
        if (empty($filter_params)) {
            return array();
        }

        // the page index is the 1-based page number of the film_filter request
        $page = $this->get_current_page_index($query_id, 1);
        if ($page < 0) {
            return array();
        }

        $params = array('page' => $page, 'page_size' => self::FILM_PAGE_SIZE);

        // a genre-only filter is served by film_list like the genre category: it returns
        // all films of the genre. film_list ignores type, country and years, so any other
        // filter is served by film_filter
        $type = safe_get_value($filter_params, 'type', 'all');
        if (isset($filter_params['genre']) && $type === 'all'
            && !isset($filter_params['country']) && !isset($filter_params['from']) && !isset($filter_params['to'])
            && $this->provider->hasApiCommand(self::CMD_FILM_LIST)) {
            $params['genre'] = (int)$filter_params['genre'];
            $response = $this->make_json_request(self::CMD_FILM_LIST, $params);
            return $this->CollectQueryResult($query_id, $page, $response);
        }

        if (isset($filter_params['type'])) {
            $params['type'] = $filter_params['type'];
        }

        if (isset($filter_params['genre'])) {
            $params['genres_include[]'] = $filter_params['genre'];
        }

        if (isset($filter_params['country'])) {
            $params['countries_include[]'] = $filter_params['country'];
        }

        foreach (array('from' => 'year_from', 'to' => 'year_to') as $key => $param_name) {
            if (!isset($filter_params[$key])) continue;

            $year = (int)$filter_params[$key];
            if ($year >= 1900 && $year <= 2100) {
                $params[$param_name] = $year;
            }
        }

        $response = $this->make_json_request(self::CMD_FILM_FILTER, $params);
        return $this->CollectFilterResult($query_id, $page, $response);
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

        $response = $this->make_json_request(self::CMD_FILM_ALT_HTTP, array('film_id' => $movie_id));
        if ($response === false || (int)safe_get_value($response, 'success') !== 1) {
            hd_debug_print("failed to load movie files: $movie_id");
            return null;
        }

        $files = safe_get_value($response, 'data', array());
        if (!is_array($files) || empty($files)) {
            hd_debug_print("no files for movie: $movie_id");
            return null;
        }

        $film = array();
        $info_response = $this->make_json_request(self::CMD_FILM_ALT_INFO, array('film_id' => $movie_id));
        if ($info_response !== false && (int)safe_get_value($info_response, 'success') === 1) {
            $film = safe_get_value($info_response, 'data', array());
        }

        $name = safe_get_value($film, 'name');
        $poster_url = safe_get_value($film, 'poster');
        $short_movie = $this->plugin->vod->get_cached_short_movie($movie_id);
        if (!is_null($short_movie)) {
            if (empty($poster_url)) {
                $poster_url = $short_movie->poster_url;
            }
            if (empty($name)) {
                $name = $short_movie->name;
            }
        }

        if (empty($name)) {
            $name = TR::t('no_title');
        }

        $directors = self::get_person_names($film, array('режисс'));
        $writers = self::get_person_names($film, array('сценар'));
        $actors = self::get_actor_names($film);

        $genres = safe_get_value($film, 'genres', array());
        if (!is_array($genres)) {
            $genres = array();
        }

        $quality_names = array();
        foreach ($files as $file) {
            $quality = self::make_quality_name($file);
            if (!empty($quality)) {
                $quality_names[$quality] = true;
            }
        }

        $details = array();
        if (!empty($quality_names)) {
            $details[TR::t('vod_screen_quality')] = implode(', ', array_keys($quality_names));
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movie->set_data(
            $name,      // caption
            '',         // caption_original: film_alt_info has no original title
            safe_get_value($film, 'description', ''), // description
            $poster_url,    // poster_url
            (int)round((int)safe_get_value($files[0], 'duration', 0) / 60), // length_min
            safe_get_value($film, 'year', ''),  // year
            $directors,     // director
            $writers,       // scenario
            $actors,        // actors
            implode(', ', $genres),     // genres
            safe_get_value($film, 'imdb_rating', ''),   // rate_imdb
            safe_get_value($film, 'kp_rating', ''),     // rate_kinopoisk
            '',             // rate_mpaa
            safe_get_value($film, 'country', ''),   // country
            '',             // budget
            $details        // details
        );

        $is_serial = false;
        foreach ($files as $file) {
            if (safe_get_value($file, 'season') !== false) {
                $is_serial = true;
                break;
            }
        }

        if (!$is_serial) {
            // movie: the playback variants are the quality options
            $first = $files[0];
            $movie_series = new Movie_Series($movie_id, $name, new Movie_Playback_Url(safe_get_value($first, 'url')));
            if (count($files) > 1) {
                foreach ($files as $file) {
                    $quality = self::make_quality_name($file);
                    if (!empty($quality)) {
                        $movie_series->add_variant_data($quality, new Movie_Variant($quality, new Movie_Playback_Url(safe_get_value($file, 'url'))));
                    }
                }
            }
            $movie->add_series_data($movie_series);
        } else {
            // series: files grouped by season/episode
            $seasons = array();
            foreach ($files as $file) {
                $season = (int)safe_get_value($file, 'season', 0);
                $episode = (int)safe_get_value($file, 'episode', 0);
                $seasons[$season]['episodes'][$episode][] = $file;
            }

            ksort($seasons);
            foreach ($seasons as $season_id => $season) {
                $movie->add_season_data(new Movie_Season($season_id));

                ksort($season['episodes']);
                foreach ($season['episodes'] as $episode => $episode_files) {
                    $first = $episode_files[0];
                    $series_id = (string)safe_get_value($first, 'tfile_id', $season_id . '_' . $episode);
                    $series_name = TR::t('vod_screen_series__1', $episode);
                    $movie_series = new Movie_Series($series_id, $series_name, new Movie_Playback_Url(safe_get_value($first, 'url')), (string)$season_id);

                    if (count($episode_files) > 1) {
                        foreach ($episode_files as $file) {
                            $quality = self::make_quality_name($file);
                            if (!empty($quality)) {
                                $movie_series->add_variant_data($quality, new Movie_Variant($quality, new Movie_Playback_Url(safe_get_value($file, 'url'))));
                            }
                        }
                    }

                    $movie->add_series_data($movie_series);
                }
            }
        }

        return $movie;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Number of films of the film_filter type: data.count of the response,
     * one film per page keeps the response small.
     *
     * @param string $type
     * @return int film count or -1 if the request failed
     */
    protected function get_type_film_count($type)
    {
        $response = $this->make_json_request(self::CMD_FILM_FILTER, array('page' => 1, 'page_size' => 1, 'type' => $type));
        if ($response === false || (int)safe_get_value($response, 'success') !== 1) {
            hd_debug_print("film_filter count request failed for type: $type", true);
            return -1;
        }

        $data = safe_get_value($response, 'data', array());
        return is_array($data) ? (int)safe_get_value($data, 'count', 0) : 0;
    }

    /**
     * Normalizes the film_filter response to the common movie list shape so the
     * shared result collector can be reused: data.result[] becomes data.films[]
     * and the page count is data.pages instead of data.total_pages.
     *
     * @param string $query_id
     * @param int $page
     * @param bool|array $response
     * @return Short_Movie[]
     */
    protected function CollectFilterResult($query_id, $page, $response)
    {
        if (is_array($response) && (int)safe_get_value($response, 'success') === 1) {
            $data = safe_get_value($response, 'data', array());
            if (!is_array($data)) {
                $data = array();
            }

            $films = array();
            $result = safe_get_value($data, 'result', array());
            if (is_array($result)) {
                foreach ($result as $film) {
                    if (!is_array($film)) continue;

                    // film_filter calls the poster field 'poster', the collector 'img'
                    $poster = safe_get_value($film, 'poster');
                    if (!empty($poster)) {
                        $film['img'] = $poster;
                    }

                    $films[] = $film;
                }
            }

            $data['films'] = $films;
            $data['total_pages'] = (int)safe_get_value($data, 'pages', 0);
            $response['data'] = $data;
        }

        return $this->CollectQueryResult($query_id, $page, $response);
    }

    /**
     * Normalizes the film_alt_search response to the common movie list shape so the
     * shared result collector can be reused. The endpoint ignores page/page_size and
     * returns up to 50 results, so the search result is a single page.
     *
     * @param string $query_id
     * @param int $page
     * @param bool|array $response
     * @return Short_Movie[]
     */
    protected function CollectSearchResult($query_id, $page, $response)
    {
        if (is_array($response) && (int)safe_get_value($response, 'success') === 1) {
            $items = safe_get_value($response, 'data', array());
            if (!is_array($items)) {
                $items = array();
            }

            $films = array();
            foreach ($items as $film) {
                if (!is_array($film)) continue;

                // film_alt_search calls the poster field 'poster', the collector 'img'
                $poster = safe_get_value($film, 'poster');
                if (!empty($poster)) {
                    $film['img'] = $poster;
                }

                $films[] = $film;
            }

            $response['data'] = array('films' => $films, 'total_pages' => 1);
        }

        return $this->CollectQueryResult($query_id, $page, $response);
    }

    /**
     * Short movies of the catalog page and the pagination control.
     *
     * @param string $query_id
     * @param int $page
     * @param bool|array $response
     * @return Short_Movie[]
     */
    protected function CollectQueryResult($query_id, $page, $response)
    {
        $movies = array();

        if ($response === false || (int)safe_get_value($response, 'success') !== 1) {
            hd_debug_print('bad catalog response', true);
            $this->stop_page_index($query_id);
            return $movies;
        }

        $data = safe_get_value($response, 'data', array());
        if (!is_array($data)) {
            $data = array();
        }

        $total_pages = (int)safe_get_value($data, 'total_pages', 0);
        $films = safe_get_value($data, 'films', array());
        if (!is_array($films)) {
            $films = array();
        }

        foreach ($films as $film) {
            $movie = $this->make_short_movie($film);
            if (is_null($movie)) continue;

            $this->plugin->vod->set_cached_short_movie($movie);
            $movies[] = $movie;
        }

        if (empty($films) || $page >= $total_pages) {
            $this->stop_page_index($query_id);
        } else {
            $this->shift_next_page_index($query_id);
        }

        hd_debug_print('Movies found: ' . count($movies));
        return $movies;
    }

    /**
     * @param array $film
     * @return Short_Movie|null
     */
    protected function make_short_movie($film)
    {
        $id = safe_get_value($film, 'id');
        $name = safe_get_value($film, 'rus_name');
        if (empty($name)) {
            $name = safe_get_value($film, 'name');
        }

        if (empty($id) || empty($name)) {
            return null;
        }

        $poster = $this->get_image_url(safe_get_value($film, 'img'));
        $info = TR::t('vod_screen_movie_info__5',
            $name,
            safe_get_value($film, 'year'),
            safe_get_value($film, 'country'),
            '',
            safe_get_value($film, 'kp_rating'));

        return new Short_Movie((string)$id, $name, $poster, $info);
    }

    /**
     * Build an absolute poster URL. The img field is a path relative to the
     * MDB host media root (images/movie/...), so the resolved poster domain
     * gets a /media/ prefix. Absolute URLs pass through unchanged.
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
     * Select the first reachable poster host like the app's DomainResolver
     * probes its MDB domains: /ok.jpg must download with exactly CHECK_SIZE
     * bytes. Unlike the app, plain http is tried first: the posters are
     * fetched by the firmware UI image loader, which has no TLS trust store
     * for these hosts (the device curl fails without -k), and the images are
     * not sensitive. Keep the first host over http if nothing responds.
     *
     * @return string
     */
    protected function resolve_poster_domain()
    {
        foreach (array('http', 'https') as $scheme) {
            foreach ($this->image_domains as $domain) {
                $poster_host = "mdb.$domain";
                if ($this->is_host_reachable($scheme, $poster_host)) {
                    $poster_domain = "$scheme://$poster_host";
                    hd_debug_print("ttv poster host: $poster_domain");
                    return $poster_domain;
                }
            }
        }

        hd_debug_print('ttv poster hosts are not reachable, use default');
        return 'http://mdb.' . $this->image_domains[0];
    }

    /**
     * Reachability check of the image host: GET /ok.jpg and require exactly
     * CHECK_SIZE bytes, the same probe the app's DomainResolver.checkDomain
     * performs. The byte-size check rejects any hijacked/redirected 200 page.
     *
     * @param string $scheme
     * @param string $domain
     * @return bool
     */
    protected function is_host_reachable($scheme, $domain)
    {
        $curl_wrapper = Curl_Wrapper::getInstance();
        $curl_wrapper->set_connect_timeout(3);
        $curl_wrapper->set_download_timeout(5);

        $content = $curl_wrapper->download_file("$scheme://$domain" . self::CHECK_PATH, null);

        return $content !== false && strlen($content) === self::CHECK_SIZE;
    }

    /**
     * Names of the film_alt_info persons of the role matched by a name stem.
     * The persons structure is a role-keyed object ('Режиссеры' => array of
     * {id, rus_name, orig_name, photo} objects). The stem is matched against
     * the role name normalized to lower case with ё folded to е.
     *
     * @param array $film
     * @param array $stems
     * @return string
     */
    protected static function get_person_names($film, $stems)
    {
        $persons = safe_get_value($film, 'persons', array());
        if (!is_array($persons)) {
            return '';
        }

        foreach ($persons as $role => $group) {
            if (!is_array($group)) continue;

            $role_norm = str_replace('ё', 'е', mb_strtolower((string)$role, 'UTF-8'));
            foreach ($stems as $stem) {
                if (strpos($role_norm, $stem) === false) continue;

                $names = array();
                foreach ($group as $person) {
                    $name = safe_get_value($person, 'rus_name');
                    if (empty($name)) {
                        $name = safe_get_value($person, 'orig_name');
                    }

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
     * Top actors of the film_alt_info data: an array of name strings.
     *
     * @param array $film
     * @return string
     */
    protected static function get_actor_names($film)
    {
        $actors = safe_get_value($film, 'actors', array());
        if (!is_array($actors)) {
            return '';
        }

        return implode(', ', $actors);
    }

    /**
     * Quality label of the file: 'BDRIP 1080P'
     *
     * @param array $file
     * @return string
     */
    protected static function make_quality_name($file)
    {
        $quality = strtoupper(safe_get_value($file, 'quality', ''));
        $hd = strtoupper(safe_get_value($file, 'hd_standard', ''));
        if (empty($quality) && empty($hd)) {
            return '';
        }

        return trim("$quality $hd");
    }

    /**
     * Execute a TorrentTV API command with the query params appended to the command template.
     * The session can be invalidated server side at any time ('incorrect' error); in this
     * case a new session is requested and the command is retried once.
     *
     * @param string $command
     * @param array $params
     * @param bool $cache_response
     * @return bool|array
     */
    protected function make_json_request($command, $params, $cache_response = true)
    {
        $curl_opt = array();

        $add_params = '';
        foreach ((array)$params as $key => $value) {
            $add_params .= '&' . $key . '=' . rawurlencode((string)$value);
        }

        if (!empty($add_params)) {
            $curl_opt[API_COMMAND_ADD_PARAMS] = $add_params;
        }

        $cache = $cache_response ? Curl_Wrapper::CACHE_RESPONSE : 0;
        $response = $this->provider->execApiCommandResponse($command, $curl_opt, Curl_Wrapper::RET_ARRAY, $cache);
        if (is_array($response)
            && (int)safe_get_value($response, 'success') !== 1
            && safe_get_value($response, 'error') === 'incorrect') {
            hd_debug_print("ttv session rejected for $command, request new session", true);
            $this->plugin->remove_cookie(PARAM_SESSION_ID);
            if ($this->provider->request_provider_token(true)) {
                $response = $this->provider->execApiCommandResponse($command, $curl_opt, Curl_Wrapper::RET_ARRAY, $cache);
            }
        }

        // an error envelope is a valid json body, keep it out of the response cache
        if ($cache && is_array($response) && (int)safe_get_value($response, 'success') !== 1) {
            Curl_Wrapper::drop_last_cached_response();
        }

        return $response;
    }
}
