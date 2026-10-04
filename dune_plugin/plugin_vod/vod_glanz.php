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
 * year, country (comma separated) and genres (id + title) are on the top level of an item
 */
class vod_glanz extends vod_json_list
{
    /**
     * @inheritDoc
     */
    public function get_item_year($item)
    {
        return (int)safe_get_value($item, 'year');
    }

    /**
     * @inheritDoc
     */
    public function get_item_genres($item)
    {
        $genres = array();
        foreach (safe_get_value($item, 'genres', array()) as $genre) {
            $id = (int)safe_get_value($genre, 'id');
            $title = safe_get_value($genre, 'title');
            if (!empty($title) && !empty($id)) {
                $genres[$id] = $title;
            }
        }
        return $genres;
    }

    /**
     * @inheritDoc
     */
    public function get_item_countries($item)
    {
        $countries = array();
        foreach (explode(',', safe_get_value($item, 'country')) as $country) {
            $country = trim($country);
            if (!empty($country)) {
                $countries[] = $country;
            }
        }
        return $countries;
    }

    /**
     * @inheritDoc
     */
    protected function CreateMovie($movie_id, $item)
    {
        $movie = new Movie($movie_id, $this->plugin);
        $name = safe_get_value($item, 'name');
        $movie->set_data(
            $name,                                     // name,
            safe_get_value($item, 'o_name'),           // name_original,
            safe_get_value($item, 'description'),      // description,
            safe_get_value($item, 'cover'),            // poster_url,
            '',                                        // length_min,
            safe_get_value($item, 'year'),             // year,
            safe_get_value($item, 'director'),         // director_str,
            '',                                        // scenario_str,
            safe_get_value($item, 'actors'),           // actors_str,
            array_to_str($this->get_genre_titles($item)), // genres_str,
            '',                                        // rate_imdb,
            '',                                        // rate_kinopoisk,
            '',                                        // rate_mpaa,
            safe_get_value($item, 'country')           // country,
        );

        $url = safe_get_value($item, 'url');
        hd_debug_print("movie playback_url: $url");
        $movie->add_series_data(new Movie_Series($movie_id, $name, new Movie_Playback_Url($url)));

        return $movie;
    }

    /**
     * @inheritDoc
     */
    protected function CreateShortMovie($item)
    {
        $name = safe_get_value($item, 'name');
        $movie = new Short_Movie(
            $this->get_item_id($item),
            $name,
            safe_get_value($item, 'cover'),
            TR::t('vod_screen_movie_info__4',
                $name,
                safe_get_value($item, 'year'),
                safe_get_value($item, 'country'),
                implode(', ', $this->get_genre_titles($item)))
        );

        $this->plugin->vod->set_cached_short_movie($movie);

        return $movie;
    }

    /**
     * Titles of all genres of the item, also of the genres without id
     *
     * @param array $item
     * @return string[]
     */
    protected function get_genre_titles($item)
    {
        $genres = array();
        foreach (safe_get_value($item, 'genres', array()) as $genre) {
            $title = safe_get_value($genre, 'title');
            if (!empty($title)) {
                $genres[] = $title;
            }
        }
        return $genres;
    }
}
