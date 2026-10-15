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
 *
 * The server paginates the catalog itself (21 films per page) and supports searching by name,
 * so the page index is the film_list page number. Play URLs (HLS .m3u8) come from film_alt_http
 * together with the season/episode info; film_info gives the description, ratings, genres and crew.
 * The film_info response has no poster, so posters come from the cached Short_Movie entries.
 * Poster paths from film_list are relative to the MDB host media root (mdb.<site domain>/media/):
 * the site hosts serve the SPA shell for /images/* paths and cannot be used for posters.
 * The catalog is never fetched in bulk: film_genres provides the VOD categories shown in the
 * category list and each category is loaded page by page from film_list with the genre param.
 * Filters are served by film_filter (type, genre, country, year range) using the genre ids
 * from film_genres and the country list from film_filter_countries.
 */
class vod_ttv extends vod_standard
{
    const CMD_FILM_LIST = 'film_list';
    const CMD_FILM_GENRES = 'film_genres';
    const CMD_FILM_FILTER = 'film_filter';
    const CMD_FILM_FILTER_COUNTRIES = 'film_filter_countries';
    const CMD_FILM_INFO = 'film_info';
    const CMD_FILM_ALT_HTTP = 'film_alt_http';
    const FILM_PAGE_SIZE = 21;

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
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $response = $this->make_json_request(self::CMD_FILM_GENRES, array());
        if ($response === false || (int)safe_get_value($response, 'success') !== 1) {
            hd_debug_print('film_genres request failed', true);
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, TR::load('err_load_vod'));
            return false;
        }

        $this->category_index = array();
        $genres = safe_get_value($response, 'data', array());
        if (!is_array($genres)) {
            $genres = array();
        }

        $genre_values = array(-1 => TR::t('no'));
        foreach ($genres as $genre) {
            $id = (int)safe_get_value($genre, 'id');
            $name = safe_get_value($genre, 'name');
            if ($id <= 0 || empty($name)) continue;

            $caption = $name;
            $count = (int)safe_get_value($genre, 'count', 0);
            if ($count > 0) {
                $caption .= " ($count)";
            }

            $this->category_index[(string)$id] = new Vod_Category((string)$id, $caption);
            $genre_values[(string)$id] = $name;
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

        // the page index is the 1-based page number of the film_list request
        $page = $this->get_current_page_index($query_id, 1);
        if ($page < 0) {
            return array();
        }

        $params = array('page' => $page);
        $genre_id = (int)$query_id;
        if ($genre_id > 0) {
            $params['genre'] = $genre_id;
        }

        $response = $this->make_json_request(self::CMD_FILM_LIST, $params);
        return $this->CollectQueryResult($query_id, $page, $response);
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

        $response = $this->make_json_request(self::CMD_FILM_LIST, array('page' => $page, 'search' => $keyword), false);
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
        $info_response = $this->make_json_request(self::CMD_FILM_INFO, array('film_id' => $movie_id));
        if ($info_response !== false && (int)safe_get_value($info_response, 'success') === 1) {
            $film = safe_get_value($info_response, 'film', array());
        }

        $info = safe_get_value($film, 'info', array());
        $rating = safe_get_value($film, 'rating', array());

        $name = safe_get_value($info, 'rus_name');
        if (empty($name)) {
            $name = safe_get_value($info, 'name');
        }

        $poster_url = '';
        $short_movie = $this->plugin->vod->get_cached_short_movie($movie_id);
        if (!is_null($short_movie)) {
            $poster_url = $short_movie->poster_url;
            if (empty($name)) {
                $name = $short_movie->name;
            }
        }

        if (empty($name)) {
            $name = TR::t('no_title');
        }

        $directors = self::get_persons($film, 'directors');
        $writers = self::get_persons($film, 'writers');

        $genres = array();
        $genres_raw = safe_get_value($film, 'genres', array());
        if (is_array($genres_raw)) {
            foreach ($genres_raw as $genre) {
                $genres[] = safe_get_value($genre, 'name');
            }
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
            safe_get_value($info, 'orig_name', ''), // caption_original
            safe_get_value($film, 'description', ''), // description
            $poster_url,    // poster_url
            (int)round((int)safe_get_value($files[0], 'duration', 0) / 60), // length_min
            safe_get_value($info, 'year', ''),  // year
            $directors,     // director
            $writers,       // scenario
            '',             // actors
            implode(', ', $genres),     // genres
            safe_get_value($rating, array('imdb', 'rating'), ''),  // rate_imdb
            safe_get_value($rating, array('kp', 'rating'), ''),    // rate_kinopoisk
            '',             // rate_mpaa
            '',             // country
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
     * Normalizes the film_filter response to the film_list shape so the common
     * result collector can be reused: data.result[] becomes data.films[] and
     * the page count is data.pages instead of data.total_pages.
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

                    // film_filter calls the poster field 'poster', film_list 'img'
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
     * Short movies of the film_list page and the pagination control.
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
            hd_debug_print('bad film_list response', true);
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
     * The persons list of film_info: an array of {id, name} objects or a plain comma
     * separated string depending on the film.
     *
     * @param array $film
     * @param string $key
     * @return string
     */
    protected static function get_persons($film, $key)
    {
        $persons = safe_get_value($film, array('persons', $key), array());
        if (is_array($persons)) {
            $names = array();
            foreach ($persons as $person) {
                $name = safe_get_value($person, 'name');
                if (!empty($name)) {
                    $names[] = $name;
                }
            }

            return implode(', ', $names);
        }

        return is_string($persons) ? $persons : '';
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
        return $this->provider->execApiCommandResponse($command, $curl_opt, Curl_Wrapper::RET_ARRAY, $cache);
    }
}
