<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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
require_once 'lib/jellyfin/jellyfin_api.php';

/**
 * JellyFin API
 * API support pagination, search
 * allow to select quality and one audio track
 * TV Shows can contain seasons
 */
class vod_mirkino extends vod_standard
{
    /**
     * Audio codecs played by Dune in MPEG-TS segments. Without AudioCodec the server infers it from the url
     * extension (mp3 for .ts segments) and transcodes every other audio track to mp3.
     * Audio in these codecs is copied, other codecs are transcoded to the first one (aac).
     */
    const HLS_AUDIO_CODECS = 'aac,ac3,eac3,mp3,mp2,dts';

    /**
     * @var jellyfin_api
     */
    protected $jfc;

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        if (parent::init_vod($provider)) {
            $this->vod_filters = array('source', 'genre', 'years');
            $vod_url = $this->provider->replace_macros($this->provider->getRawApiCommand(API_COMMAND_GET_VOD));

            $this->jfc = new jellyfin_api();
            $this->jfc->init($this->plugin, $vod_url, Default_Dune_Plugin::$plugin_info['app_version']);

            $access_info = array(
                MACRO_LOGIN => $this->provider->GetProviderParameter(MACRO_LOGIN),
                MACRO_PASSWORD => $this->provider->GetProviderParameter(MACRO_PASSWORD),
                PARAM_TOKEN => $this->plugin->get_cookie(PARAM_TOKEN),
                PARAM_USER_ID => $this->plugin->get_cookie(PARAM_USER_ID)
            );

            if ($this->jfc->login($access_info) !== false) {
                $this->plugin->set_cookie(PARAM_TOKEN, $this->jfc->get_access_token());
                $this->plugin->set_cookie(PARAM_USER_ID, $this->jfc->get_user_id());
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function get_vod_stream_url($playback_url, $plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("Playback url: $playback_url", true);

        $media_url = MediaURL::decode($playback_url);

        $query = array();
        if (isset($media_url->stream_id)) {
            $query['MediaSourceId'] = $media_url->stream_id;
        }

        // PlaybackInfo registers the play session, the stream is served only within it
        $info = $this->jfc->getItemPlaybackInfo($media_url->id, $query);
        if ($info === false) {
            hd_debug_print("Can't get response on Playback info");
        }

        if (isset($info['PlaySessionId'])) {
            $query['PlaySessionId'] = $info['PlaySessionId'];
        }

        // for requested media source the response contains only this source, otherwise the one selected by server
        $source = safe_get_value($info, array('MediaSources', 0), array());
        if (!isset($query['MediaSourceId']) && !empty($source['Id'])) {
            $query['MediaSourceId'] = $source['Id'];
        }

        if (safe_get_value($source, 'SupportsDirectStream', false)) {
            // original file as is: no load on server, all audio tracks are selectable in player.
            // HLS is not used when possible, it fails for alternate versions (other qualities) of the movie
            $url = $this->jfc->getStreamUrl($media_url->id, $query, self::get_stream_extension(safe_get_value($source, 'Container', '')));
        } else {
            $query['AudioCodec'] = self::HLS_AUDIO_CODECS;
            $url = $this->jfc->getPlayUrlMaster($media_url->id, $query);
        }
        hd_debug_print("Stream url: " . $url, true);

        $vod_url = make_ts($url);
        $dune_params = $this->plugin->collect_dune_params();
        if (!empty($dune_params)) {
            $magic = str_replace(array('=', '+'), array(':', '%20'), http_build_query($dune_params, null, ','));
            $vod_url .= DUNE_PARAMS_MAGIC . $magic;
        }

        return $vod_url;
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);

        if (empty($movie_id)) {
            hd_debug_print('Movie ID is empty!');
            return null;
        }

        list($item_id, $category_type) = explode('_', $movie_id) + array('', jellyfin_api::MOVIES);
        if (empty($item_id)) {
            hd_debug_print('Real movie ID is empty!');
            return null;
        }

        $movie_item = $this->jfc->getItemInfo($item_id);
        if (empty($movie_item)) {
            hd_debug_print("Failed to load movie: $item_id from: $category_type");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);

        $qualities_str = '';
        $movie_type = safe_get_value($movie_item, 'Type');
        hd_debug_print("movie type: $movie_type", true);
        if ($movie_type === jellyfin_api::MOVIES) {
            $name = safe_get_value($movie_item, 'Name', 'no name');
            $default_url = new Movie_Playback_Url(MediaURL::encode(array('id' => $item_id)), false);
            $movie_series = new Movie_Series($item_id, $name, $default_url);
            $movie->add_series_data($this->fill_series($movie_series, $item_id, safe_get_value($movie_item, 'MediaSources', array())));
            $qualities_str = implode(', ', $movie->get_qualities($item_id));
        } else if ($movie_type === jellyfin_api::SERIES) {
            $seasons = $this->jfc->getSeasons($item_id);
            $season_idx = 0;
            foreach (safe_get_value($seasons, 'Items', array()) as $season) {
                $season_id = safe_get_value($season, 'Id');
                if (empty($season_id)) continue;

                hd_debug_print("season id: $season_id", true);
                $movie_season = new Movie_Season($season_id, safe_get_value($season, 'IndexNumber', ++$season_idx));
                $movie_season->name = safe_get_value($season, 'Name');
                $movie_season->poster = $this->jfc->getItemImageUrl($season_id);
                $movie->add_season_data($movie_season);

                $episodes = $this->jfc->getEpisodes($item_id, $season_id);
                foreach (safe_get_value($episodes, 'Items', array()) as $episode) {
                    $episode_id = $episode['Id'];
                    if (empty($episode_id)) continue;

                    hd_debug_print("episode id: $episode_id", true);
                    // episodes list contains media sources, older servers may not return them
                    $media_sources = safe_get_value($episode, 'MediaSources');
                    if (empty($media_sources)) {
                        $media_sources = safe_get_value($this->jfc->getItemInfo($episode_id), 'MediaSources', array());
                    }

                    $default_url = new Movie_Playback_Url(MediaURL::encode(array('id' => $episode_id)), false);
                    $movie_series = new Movie_Series($episode_id,
                        TR::t('vod_screen_series__1', safe_get_value($episode, 'Name', 'no name')),
                        $default_url, $season_id
                    );
                    $movie_series->poster = $this->jfc->getItemImageUrl($episode_id);
                    $movie->add_series_data($this->fill_series($movie_series, $episode_id, $media_sources));

                    if (empty($qualities_str)) {
                        $qualities_str = implode(', ', $movie->get_qualities($episode_id));
                    }
                }
            }
        }
        // Director, Actor, Producer, Writer, Editor, Composer
        $persons = array();
        foreach (safe_get_value($movie_item, 'People', array()) as $person) {
            if (!isset($person['Type'], $person['Name'])) continue;

            $persons[$person['Type']][] = $person['Name'];
        }

        // RunTimeTicks are in 100 ns units. Tick counts exceed 32-bit int, json_decode returns them as float
        $length_min = '';
        $ticks = safe_get_value($movie_item, 'RunTimeTicks');
        if (!empty($ticks)) {
            $length_min = (int)round($ticks / 600000000);
        }

        // CriticRating is usually a percentage, but may already be in 10 point (imdb) scale
        $critic_rating = safe_get_value($movie_item, 'CriticRating');
        if (is_numeric($critic_rating) && $critic_rating > 10) {
            $critic_rating = round($critic_rating / 10, 1);
        }

        $movie->set_data(
            safe_get_value($movie_item, 'Name'), // name,
            safe_get_value($movie_item, 'OriginalTitle'), // name_original,
            safe_get_value($movie_item, 'Overview'),  // description,
            $this->jfc->getItemImageUrl($item_id),  // poster_url,
            $length_min, // length_min,
            safe_get_value($movie_item, 'ProductionYear'), // year,
            implode(', ', safe_get_value($persons, 'Director', array())),
            implode(', ', safe_get_value($persons, 'Writer', array())),
            implode(', ', safe_get_value($persons, 'Actor', array())),
            implode(', ', safe_get_value($movie_item, 'Genres', array())),
            $critic_rating, // rate_imdb,
            safe_get_value($movie_item, 'CommunityRating', ''), // rate_kinopoisk,
            safe_get_value($movie_item, 'OfficialRating', ''), // rate_mpaa,
            implode(', ', safe_get_value($movie_item, 'ProductionLocations', array())),
            '',
            array(TR::t('vod_screen_quality') => $qualities_str) // details
        );

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        $collections = safe_get_value($this->jfc->getUserViews(), 'Items', array());
        /*
        $exist_filters = array(
            'source' => array(
                'title' => TR::load('category'),
                'values' => array()),
            'genre' => array(
                'title' => TR::load('genre'),
                'values' => array(-1 => TR::t('no'))),
            'years' => array(
                'title' => TR::load('year'),
                'values' => array(-1 => TR::t('no'))),
        );

        $genres = array();
        $years = array();
        */

        $this->category_index = array();
        foreach ($collections as $collection) {
            if (safe_get_value($collection, 'Type') != "CollectionFolder") continue;

            $sid = $id = safe_get_value($collection, 'Id');
            $query_params = array('ParentId' => $id, 'StartIndex' => 0, 'Limit' => 1);
            $items = $this->jfc->getItems($query_params);
            $movie_count = safe_get_value($items, 'TotalRecordCount');
            if (empty($movie_count)) continue;

            $collection_type = safe_get_value($collection, 'CollectionType');
            hd_debug_print("Collection type: $collection_type");
            if ($collection_type === jellyfin_api::TVSHOWS_TYPE) {
                $sid .= '_' . jellyfin_api::SERIES;
            } else if (empty($collection_type) || $collection_type === jellyfin_api::MOVIES_TYPE) {
                $sid .= '_' . jellyfin_api::MOVIES;
            } else {
                continue;
            }

            $name = safe_get_value($collection, 'Name', 'no name');
            $icon = $this->jfc->getItemImageUrl($id, 'Primary', 400, 0, 'Jpg');
            $this->category_index[$id] = new Vod_Category($sid, $name . " ($movie_count)", null, $icon);
            /*
            // Not supported yet!!!
            $exist_filters['source']['values'][$id] = $name;
            $query_params = array('ParentId' => $id, 'recursive' => 'true');
            $jsonData = $this->jfc->getFilters($query_params);

            foreach (safe_get_value($jsonData, 'Genres', array()) as $filter) {
                $genres[$filter] = $filter;
            }

            foreach (safe_get_value($jsonData, 'Years', array()) as $filter) {
                $years[$filter] = $filter;
            }
            */
        }

        /*
        ksort($genres);
        krsort($years);

        $exist_filters['genre']['values'] += $genres;
        $exist_filters['years']['values'] += $years;

        $this->set_filter_types($exist_filters);
        */
        hd_debug_print('Categories read: ' . count($this->category_index));
        //hd_debug_print('Filters count: ' . count($exist_filters));

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getMovieList: $query_id");

        $movies = array();

        $page_idx = $this->get_current_page_index($query_id);
        if ($page_idx < 0) {
            return $movies;
        }

        list($category_id, $category_type) = explode('_', $query_id) + array($query_id, jellyfin_api::MOVIES);
        $query_params['ParentId'] = $category_id;
        $query_params['StartIndex'] = $page_idx * self::PAGE_LIMIT;
        $query_params['Limit'] = self::PAGE_LIMIT;
        $query_params['IncludeItemTypes'] = $category_type === jellyfin_api::MOVIES ? jellyfin_api::MOVIES : jellyfin_api::SERIES;

        $vod_items = $this->jfc->getItems($query_params);
        foreach (safe_get_value($vod_items, 'Items', array()) as $item) {
            $this->CreateShortMovie($item, $movies);
        }

        if (empty($movies)) {
            $this->stop_page_index($query_id);
        } else {
            $this->shift_next_page_index($query_id);
        }

        hd_debug_print("Movies read for query: $query_id: " . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        $query_id = mb_strtolower($keyword, 'UTF-8');

        $movies = array();
        $page_idx = $this->get_current_page_index($query_id);
        if ($page_idx < 0) {
            return $movies;
        }

        $query_params['SearchTerm'] = $keyword;
        $query_params['includeItemTypes'] = jellyfin_api::MOVIES . ','. jellyfin_api::SERIES;
        $query_params['recursive'] = 'true';
        $query_params['imageTypeLimit'] = 1;
        $query_params['StartIndex'] = $page_idx * self::PAGE_LIMIT;
        $query_params['Limit'] = self::PAGE_LIMIT;

        $vod_items = $this->jfc->getItems($query_params);
        foreach (safe_get_value($vod_items, 'Items', array()) as $item) {
            $this->CreateShortMovie($item, $movies);
        }

        if (empty($movies)) {
            $this->stop_page_index($query_id);
        } else {
            $this->shift_next_page_index($query_id);
        }

        hd_debug_print("Movies found for query: $query_id: " . count($movies));
        return $movies;
    }

    /**
     * @inheritDoc
     */
    public function getFilterList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $query_id");

        $movies = array();

        $page_idx = $this->get_current_page_index($query_id);
        if ($page_idx < 0) {
            return $movies;
        }

        $pairs = explode(',', $query_id);
        $query_params = array();
        foreach ($pairs as $pair) {
            /** @var array $m */
            if (!preg_match("/^([^:]+):(.+)$/", $pair, $m)) continue;

            $filter = $this->get_filter_type($m[1]);
            if ($filter === null) continue;
            if (!empty($filter['values'])) {
                $item_key = array_search($m[2], $filter['values']);
                if ($item_key !== false && $item_key !== -1) {
                    switch ($m[1]) {
                        case 'source':
                            $query_params['ParentId'] = $item_key;
                            break;
                        case 'genre':
                            $query_params['genres'] = $item_key;
                            break;
                        case 'years':
                            $query_params['years'] = $item_key;
                            break;
                    }
                }
            }
        }

        if (empty($query_params)) {
            return $movies;
        }

        $query_params['imageTypeLimit'] = 1;
        $query_params['StartIndex'] = $page_idx * self::PAGE_LIMIT;
        $query_params['Limit'] = self::PAGE_LIMIT;

        $vod_items = $this->jfc->getItems($query_params);
        foreach (safe_get_value($vod_items, 'Items', array()) as $item) {
            $this->CreateShortMovie($item, $movies);
        }

        if (!empty($movies)) {
            $this->shift_next_page_index($query_id);
        }

        hd_debug_print("Movies read for query: $query_id: " . count($movies));
        return $movies;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Extension for the direct stream url. Jellyfin reports container as ffprobe format names,
     * for example "mov,mp4,m4a,3gp,3g2,mj2" or "matroska,webm"
     *
     * @param string $container
     * @return string
     */
    protected static function get_stream_extension($container)
    {
        $formats = explode(',', strtolower($container));
        if (in_array('mp4', $formats)) {
            return 'mp4';
        }
        if (in_array('matroska', $formats) || in_array('mkv', $formats)) {
            return 'mkv';
        }
        if (in_array('mov', $formats)) {
            return 'mov';
        }
        return '';
    }

    /**
     * @param Movie_Series $movie_series
     * @param string $item_id
     * @param array $media_sources
     * @return Movie_Series
     */
    protected function fill_series($movie_series, $item_id, $media_sources)
    {
        /** @var Movie_Variant[] $qualities */
        $qualities = array();
        foreach ($media_sources as $source) {
            $stream_id = safe_get_value($source, 'Id');
            if (empty($stream_id)) continue;

            // audio track is selected in player: the direct stream contains all tracks of the source
            foreach (safe_get_value($source, 'MediaStreams', array()) as $stream) {
                if (strcasecmp(safe_get_value($stream, 'Type'), 'Video') !== 0) continue;

                $q_name = safe_get_value($stream, 'DisplayTitle');
                $media_url = MediaURL::encode(array('id' => $item_id, 'stream_id' => $stream_id));
                $quality = new Movie_Variant($q_name, new Movie_Playback_Url($media_url, false));
                $qualities[$q_name] = $quality;
                // default playback url for quality
                if ($stream_id == $item_id) {
                    $qualities['auto'] = $quality;
                }
                break;
            }
        }

        if (!empty($qualities)) {
            $movie_series->set_variants($qualities);
        }

        return $movie_series;
    }

    /**
     * @param array $movie_info
     * @param array $movies
     * @return void
     */
    protected function CreateShortMovie($movie_info, &$movies)
    {
        $id = safe_get_value($movie_info, 'Id');
        if (empty($id)) {
            return null;
        }
        $name = safe_get_value($movie_info, 'Name', 'no name');
        $type = safe_get_value($movie_info, 'Type', jellyfin_api::MOVIES);
        $rating = safe_get_value($movie_info, 'OfficialRating', 0);
        $icon = $this->jfc->getItemImageUrl($id);
        $movie = new Short_Movie("{$id}_$type", $name, $icon, TR::t('vod_screen_movie_info__2', $name, $rating));

        $this->plugin->vod->set_cached_short_movie($movie);

        $movies[] = $movie;
    }
}
