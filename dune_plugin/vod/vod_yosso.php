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
        $vod_url = $this->provider->replace_macros($this->provider->getRawApiCommand(API_COMMAND_GET_VOD));
        $this->jfc = new jellyfin_api();
        $this->jfc->init($this->plugin, $vod_url, $this->plugin->plugin_info['app_version']);

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

        if (empty($movie_id)) {
            hd_debug_print("Movie ID is empty!");
            return null;
        }

        list($real_id, $category_type) = explode('_', $movie_id) + array('', jellyfin_api::VOD);
        if (empty($real_id)) {
            hd_debug_print("Real movie ID is empty!");
            return null;
        }

        $movie_item = $this->jfc->getItemInfo($real_id);
        if (empty($movie_item)) {
            hd_debug_print("Failed to load movie: $real_id from: $category_type");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);

        $qualities_str = '';
        $movie_type = safe_get_value($movie_item, 'Type');
        hd_debug_print("movie type: $movie_type", true);
        if ($movie_type === jellyfin_api::VOD) {
            $name = safe_get_value($movie_item, 'Name', 'no name');
            $default_url = new Movie_Playback_Url($this->jfc->getPlayUrl($real_id));
            $movie_series = new Movie_Series($real_id, $name, $default_url);
            $movie->add_series_data($this->fill_series($movie_series, $real_id, safe_get_value($movie_item, 'MediaSources', array())));
            $qualities_str = implode(', ', $movie->get_qualities($real_id));
        } else if ($movie_type === jellyfin_api::SERIES) {
            foreach ($this->jfc->getSeasons($real_id) as $season) {
                $season_id = $season['Id'];
                if (empty($season_id)) continue;

                hd_debug_print("season id: $season_id", true);
                $movie_season = new Movie_Season($season_id, $season['IndexNumber']);
                $movie_season->name = safe_get_value($season, 'Name');
                $movie_season->poster = $this->jfc->getItemImageUrl($season_id);
                $movie->add_season_data($movie_season);

                $episodes = $this->jfc->getEpisodes($real_id, $season_id);
                if (empty($episodes)) continue;

                foreach ($episodes as $episode) {
                    $episode_id = $episode['Id'];
                    if (empty($episode_id)) continue;

                    hd_debug_print("episode id: $season_id", true);
                    $episode_item = $this->jfc->getItemInfo($episode_id);

                    $default_url = $this->jfc->getPlayUrl($episode_id);
                    hd_debug_print("episode playback_url: $default_url");
                    $movie_series = new Movie_Series($episode_id,
                        TR::t('vod_screen_series__1', safe_get_value($episode, 'Name', 'no name')),
                        new Movie_Playback_Url($default_url), $season_id
                    );
                    $movie_series->poster = $this->jfc->getItemImageUrl($episode_id);
                    $movie->add_series_data($this->fill_series($movie_series, $real_id, safe_get_value($episode_item, 'MediaSources', array())));

                    if (empty($quality_str)) {
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

        $rate_details = array();
        if (isset($movie_item['OfficialRating'])) {
            $rate_details['Official:'] = $movie_item['OfficialRating'];
        }
        if (isset($movie_item['CommunityRating'])) {
            $rate_details['Community:'] = $movie_item['CommunityRating'];
        }
        if (isset($movie_item['CriticRating'])) {
            $rate_details['Critic:'] = $movie_item['CriticRating'];
        }

        $movie->set_data(
            safe_get_value($movie_item, 'Name'), // name,
            safe_get_value($movie_item, 'OriginalTitle'), // name_original,
            safe_get_value($movie_item, 'Overview'),  // description,
            $this->jfc->getItemImageUrl($real_id),  // poster_url,
            (int)safe_get_value($movie_item, 'RunTimeTicks') / 60 / 1000000, // length_min,
            safe_get_value($movie_item, 'ProductionYear'), // year,
            implode(', ', safe_get_value($persons, 'Director', array())),
            implode(', ', safe_get_value($persons, 'Writer', array())),
            implode(', ', safe_get_value($persons, 'Actor', array())),
            implode(', ', safe_get_value($movie_item, 'Genres', array())),
            '',
            '', // rate_kinopoisk,
            '', // rate_mpaa,
            implode(', ', safe_get_value($movie_item, 'ProductionLocations', array())),
            '',
            array(TR::t('vod_screen_quality') => $qualities_str), // details
            $rate_details
        );

        return $movie;
    }

    /**
     * @param Movie_Series $movie_series
     * @param string $real_id
     * @param array $media_sources
     * @return Movie_Series
     */
    protected function fill_series($movie_series, $real_id, $media_sources)
    {
        foreach ($media_sources as $source) {
            $stream_id = safe_get_value($source, 'Id');
            if (empty($stream_id)) continue;

            foreach (safe_get_value($source, 'MediaStreams', array()) as $stream) {
                if (strcasecmp(safe_get_value($stream, 'Type'), 'Video') === 0) {
                    $name = safe_get_value($stream, 'DisplayTitle');
                    $quality = new Movie_Variant($name, new Movie_Playback_Url($this->jfc->getDownloadUrl($stream_id)));
                    // default playback url for quality
                    if ($stream_id == $real_id) {
                        $movie_series->add_variant_data('auto', $quality);
                    }
                    $movie_series->add_variant_data($stream_id, $quality);
                    break;
                }
            }
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

        list($category_id, $category_type) = explode('_', $query_id) + array($query_id, jellyfin_api::VOD);
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
        hd_debug_print("getSearchList $keyword");

        $query_id = mb_strtolower($keyword, 'UTF-8');

        $movies = array();
        $page_idx = $this->get_current_page($query_id);
        if ($page_idx < 0) {
            return $movies;
        }

        $query['SearchTerm'] = $keyword;
        $query['includeItemTypes'] = jellyfin_api::VOD . ','. jellyfin_api::SERIES;
        $query['recursive'] = 'true';
        $query['imageTypeLimit'] = 1;
        $query['StartIndex'] = $page_idx * self::PAGE_LIMIT;
        $query['Limit'] = self::PAGE_LIMIT;

        $vod_items = $this->jfc->getItems($query);
        foreach (safe_get_value($vod_items, 'Items', array()) as $item) {
            $movie = $this->CreateShortMovie($item);
            if (!empty($movie)) {
                $movies[] = $movie;
            }
        }

        if (!empty($movies)) {
            $this->get_next_page($query_id);
        }

        hd_debug_print("Movies found for query: $query_id: " . count($movies));
        return $movies;
    }
}
