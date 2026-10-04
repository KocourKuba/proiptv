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

require_once 'plugin_vod/vod_json_list.php';

/**
 * Returns entire VOD in one json file
 * internal support filter, search
 * TV Shows can contain seasons
 */
class vod_sharaclub extends vod_json_list
{
    /**
     * VOD is available only when the account has it
     *
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        if (parent::init_vod($provider)) {
            $json_data = $provider->execApiCommandResponseNoOpt(API_COMMAND_ACCOUNT_INFO);
            $data = safe_get_value($json_data, array('data', 'vod'));
            return !empty($data);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function CreateMovie($movie_id, $item)
    {
        $duration = "";
        $movie_info = safe_get_value($item, 'info');
        if (isset($movie_info['duration_secs'])) {
            $duration = safe_get_value($movie_info, 'duration_secs', 0) / 60;
        } else if (isset($movie_info['episode_run_time'])) {
            $duration = safe_get_value($movie_info, 'episode_run_time', 0);
        }

        $name = safe_get_value($item, 'name');
        $age = safe_get_value($movie_info, 'adult');
        $age_limit = empty($age) ? array() : array(TR::t('vod_screen_age_limit') => "$age+");

        $movie = new Movie($movie_id, $this->plugin);
        $movie->set_data(
            $name,                                // name,
            '',                       // name_original,
            safe_get_value($movie_info, 'plot'), // description,
            safe_get_value($movie_info, 'poster'),   // poster_url,
            $duration,                            // length_min,
            safe_get_value($movie_info, 'year'), // year,
            safe_get_value($movie_info, 'director'), // director_str,
            '',                       // scenario_str,
            safe_get_value($movie_info, 'cast'), // actors_str,
            array_to_str(safe_get_value($movie_info, 'genre', array())),   // genres_str,
            safe_get_value($movie_info, 'rating'), // rate_imdb,
            '',                      // rate_kinopoisk,
            '',
            array_to_str(safe_get_value($movie_info, 'country', array())), // country,
            '',
            array(), // details
            $age_limit // rate details
        );

        // case for serials
        if (isset($item['seasons'])) {
            foreach (safe_get_value($item, 'seasons', array()) as $season) {
                $season_name = safe_get_value($season, 'season');
                if (empty($season_name)) continue;

                $movie_season = new Movie_Season($season_name);

                $season_info = safe_get_value($season, 'info');
                $overview = safe_get_value($season_info, 'overview');
                if (!empty($overview)) {
                    $movie_season->description = $overview;
                }

                $air_date = safe_get_value($season_info, 'air_date');
                if (!empty($air_date)) {
                    $title = empty($movie_season->description) ? $movie_season->name : $movie_season->description;
                    $movie_season->description = TR::t('vod_screen_air_date__2', $title, $air_date);
                }

                $movie_season->poster = safe_get_value($season_info, 'poster');

                $movie->add_season_data($movie_season);

                foreach (safe_get_value($season, 'episodes', array()) as $episode) {
                    $url = safe_get_value($episode, 'video');
                    hd_debug_print("episode playback_url: $url", true);
                    $movie_serie = new Movie_Series(safe_get_value($episode, 'id'),
                        TR::t('vod_screen_series__1', safe_get_value($episode, 'episode')),
                        new Movie_Playback_Url($url),
                        $season_name
                    );
                    $movie->add_series_data($movie_serie);
                }
            }
        } else {
            $url = safe_get_value($item, 'video');
            hd_debug_print("movie playback_url: $url");
            $movie->add_series_data(new Movie_Series($movie_id, $name, new Movie_Playback_Url($url)));
        }

        return $movie;
    }

    /**
     * @inheritDoc
     */
    protected function CreateShortMovie($item)
    {
        $name = safe_get_value($item, 'name');
        $info = safe_get_value($item, 'info');
        $movie_info = TR::t('vod_screen_movie_info__5',
            $name,
            safe_get_value($info, 'year'),
            array_to_str(safe_get_value($info, 'country', array())),
            array_to_str(safe_get_value($info, 'genre', array())),
            safe_get_value($info, 'rating')
        );
        $movie = new Short_Movie($this->get_item_id($item), $name, safe_get_value($info, 'poster'), $movie_info);

        $this->plugin->vod->set_cached_short_movie($movie);

        return $movie;
    }
}
