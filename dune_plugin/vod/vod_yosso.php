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

require_once 'vod_standard.php';
require_once 'lib/jellyfin/jellyfin_api.php';

class vod_yosso extends vod_standard
{
    const PAGE_LIMIT = 50;

    /**
     * @var jellyfin_api
     */
    protected $jfc;

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        parent::init_vod($provider);

        $this->vod_quality = true;
        $this->vod_audio = true;
        $curl_wrapper = Curl_Wrapper::getInstance();
        $this->plugin->set_curl_timeouts($curl_wrapper);

        $vod_url = $this->provider->replace_macros($this->provider->getRawApiCommand(API_COMMAND_GET_VOD));
        $this->jfc = new jellyfin_api($this->plugin, $vod_url, $this->plugin->plugin_info['app_version']);

        $login = $this->provider->GetProviderParameter(MACRO_LOGIN);
        $pass = $this->provider->GetProviderParameter(MACRO_PASSWORD);

        return $this->jfc->login($login, $pass);
    }

    /**
     * @inheritDoc
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);

        $arr = explode('_', $movie_id);
        $real_id = safe_get_value($arr, 0, $movie_id);

        $item = $this->jfc->getItemInfo($real_id);
        if (empty($item)) {
            $category_type = safe_get_value($arr, 1, jellyfin_api::VOD);
            hd_debug_print("failed to load movie: $real_id from: $category_type");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);

        $qualities_str = '';
        if ($item['Type'] === jellyfin_api::VOD) {
            $name = safe_get_value($item, 'Name', 'no name');
            $default_url = new Movie_Playback_Url($this->jfc->getPlayUrl($real_id));
            $movie_series = new Movie_Series($real_id, $name, $default_url);
            $movie->add_series_data($this->fill_series($movie_series, $real_id, safe_get_value($item, 'MediaSources', array())));
            $qualities_str = implode(',', $movie->get_qualities($real_id));
        } else if ($item['Type'] === jellyfin_api::SERIES) {
            foreach ($this->jfc->getSeasons($real_id) as $season) {
                $season_id = $season['Id'];
                if (empty($season_id)) continue;

                $movie_season = new Movie_Season($season_id, $season['IndexNumber']);
                $movie_season->name = safe_get_value($season, 'Name');
                $movie_season->poster = $this->jfc->getItemImageUrl($season_id);
                $movie->add_season_data($movie_season);

                $all_qualities = array();
                foreach ($this->jfc->getEpisodes($real_id, $season_id) as $episode) {
                    $episode_id = $episode['Id'];
                    if (empty($episode_id)) continue;

                    $item = $this->jfc->getItemInfo($episode_id);

                    $default_url = $this->jfc->getPlayUrl($episode_id, $episode, $episode);
                    hd_debug_print("episode playback_url: $default_url");
                    $movie_series = new Movie_Series($episode_id,
                        TR::t('vod_screen_series__1', safe_get_value($episode, 'Name', 'no name')),
                        new Movie_Playback_Url($default_url), $season_id
                    );
                    $movie->add_series_data($this->fill_series($movie_series, $real_id, safe_get_value($item, 'MediaSources', array())));
                    $all_qualities = safe_merge_array($all_qualities, array_values($movie->get_qualities($episode_id)));
                }
                $qualities_str = implode(',', $all_qualities);
            }
        }
        // Director, Actor, Producer, Writer, Editor, Composer
        $persons = array();
        foreach (safe_get_value($item, 'People', array()) as $person) {
            if (!isset($person['Type'], $person['Name'])) continue;

            $persons[$person['Type']][] = $person['Name'];
        }

        $rate_details = array();
        if (isset($item['OfficialRating'])) {
            $rate_details['Official:'] = $item['OfficialRating'];
        }
        if (isset($item['CommunityRating'])) {
            $rate_details['Community:'] = $item['CommunityRating'];
        }
        if (isset($item['CriticRating'])) {
            $rate_details['Critic:'] = $item['CriticRating'];
        }

        $movie->set_data(
            safe_get_value($item, 'Name'), // name,
            safe_get_value($item, 'OriginalTitle'), // name_original,
            safe_get_value($item, 'Overview'),  // description,
            $this->jfc->getItemImageUrl($real_id),  // poster_url,
            (int)safe_get_value($item, 'RunTimeTicks') / 60 / 1000000, // length_min,
            safe_get_value($item, 'ProductionYear'), // year,
            implode(', ', safe_get_value($persons, 'Director', array())),
            implode(', ', safe_get_value($persons, 'Writer', array())),
            implode(', ', safe_get_value($persons, 'Actor', array())),
            implode(', ', safe_get_value($item, 'Genres', array())),
            '',
            '', // rate_kinopoisk,
            '', // rate_mpaa,
            implode(', ', safe_get_value($item, 'ProductionLocations', array())),
            '',
            array(TR::t('vod_screen_quality') => $qualities_str), // details
            $rate_details
        );

        return $movie;
    }

    /**
     * @inheritDoc
     */
    public function get_vod_playback_url($series_id, $quality = 'auto', $audio = 'auto')
    {
        $media_source = array();

        if ($quality !== 'auto') {
            $media_source['Id'] = $quality;
        }

        if ($audio === 'auto') {
            $audio = -1;
        }

        return $this->jfc->getPlayUrl($series_id, $media_source, $audio);
    }

    /**
     * @param Movie_Series $movie_series
     * @param string $real_id
     * @param array $media_sources
     * @return Movie_Series
     */
    protected function fill_series($movie_series, $real_id, $media_sources)
    {
        $qualities_str = implode(',', array_map(function($item) {
            return $item['Name'];
        }, $media_sources));

        $movie_series->description = TR::load('vod_screen_quality') . "|$qualities_str";

        foreach ($media_sources as $source) {
            $stream_id = safe_get_value($source, 'Id');
            if (empty($stream_id)) continue;

            $quality = safe_get_value($source, 'Name');
            // default playback url for quality
            $quality = new Movie_Variant($quality, new Movie_Playback_Url($this->jfc->getPlayUrl($real_id, $source)));
            foreach ($source['MediaStreams'] as $stream) {
                if ($stream['Type'] === 'Audio') {
                    $audio_name = safe_get_value($stream, 'DisplayTitle');
                    $audio = new Movie_Variant($audio_name, new Movie_Playback_Url($stream['Index'], false));
                    $quality->add_variant_data($stream['Index'], $audio);
                }
            }

            if ($stream_id == $real_id) {
                $movie_series->add_variant_data('auto', $quality);
            }
            $movie_series->add_variant_data($stream_id, $quality);
        }

        return $movie_series;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        hd_debug_print(null, true);

        $collections = safe_get_value($this->jfc->getUserViews(), 'Items', array());
        foreach ($collections as $collection) {
            if (safe_get_value($collection, 'Type') != "CollectionFolder") continue;

            $sid = $id = safe_get_value($collection, 'Id');
            hd_debug_print("Collection type: " . safe_get_value($collection, 'CollectionType'));
            if (safe_get_value($collection, 'CollectionType') === jellyfin_api::TVSHOWS) {
                $sid .= '_' . jellyfin_api::SERIES;
            } else {
                $sid .= '_' . jellyfin_api::VOD;
            }

            $query = array('ParentId' => $id, 'StartIndex' => 0, 'Limit' => 1);
            $items = $this->jfc->getItems($query);
            $movie_count = safe_get_value($items, 'TotalRecordCount');
            if (empty($movie_count)) continue;

            $name = safe_get_value($collection, 'Name', 'no name') . " ($movie_count)";
            $icon = $this->jfc->getItemImageUrl($id, 'Primary', 400, 0, 'Jpg');
            $cat = new Vod_Category($sid, $name, null, $icon);
            $category_list[] = $cat;
            $category_index[$id] = $cat;
        }

        hd_debug_print("Categories read: " . count($category_list));

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($query_id);

        $movies = array();
        $page_idx = $this->get_current_page($query_id);
        if ($page_idx < 0) {
            return $movies;
        }

        $arr = explode('_', $query_id);
        $category_id = safe_get_value($arr, 0, $query_id);
        $category_type = safe_get_value($arr, 1, jellyfin_api::VOD);

        $query = array('ParentId' => $category_id, 'StartIndex' => $page_idx * self::PAGE_LIMIT, 'Limit' => self::PAGE_LIMIT);
        if ($category_type === jellyfin_api::VOD) {
            $vod_items = $this->jfc->getMovies($query);
        } else {
            $vod_items = $this->jfc->getSeries($query);
        }

        foreach ($vod_items as $item) {
            $movie = $this->CreateShortMovie($item);
            if (!empty($movie)) {
                $movies[] = $movie;
            }
        }

        if (!empty($movies)) {
            $this->get_next_page($query_id);
        }

        hd_debug_print("Movies read for query: $query_id: " . count($movies));
        return $movies;
    }

    /**
     * @param array $movie_info
     * @return Short_Movie
     */
    protected function CreateShortMovie($movie_info)
    {
        $id = safe_get_value($movie_info, 'Id');
        if (empty($id)) {
            return null;
        }
        $name = safe_get_value($movie_info, 'Name', 'no name');
        $type = safe_get_value($movie_info, 'Type', jellyfin_api::VOD);
        $rating = safe_get_value($movie_info, 'OfficialRating', 0);
        $icon = $this->jfc->getItemImageUrl($id);
        return new Short_Movie("{$id}_$type", $name, $icon, TR::t('vod_screen_movie_info__2', $name, $rating));
    }

    /**
     * @inheritDoc
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print($keyword);

        $movies = array();

        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));

        $query['Search'] = $keyword;
        $items = $this->jfc->getItems($query);
        foreach ($items as $item) {
            $movie = $this->CreateShortMovie($item);
            if (!empty($movie)) {
                $movies[$item['Id']] = $movie;
            }
        }

        hd_debug_print("Movies found: " . count($movies));

        return array_values($movies);
    }
}
