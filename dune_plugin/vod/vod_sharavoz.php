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
require_once 'lib/xtream/xtream_codes_api.php';

class vod_sharavoz extends vod_standard
{
    /**
     * @var xtream_codes_api
     */
    protected $xtream;

    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct($plugin);

        $this->xtream = new xtream_codes_api();
    }

    /**
     * @inheritDoc
     */
    public function init_vod($provider)
    {
        parent::init_vod($provider);

        $pass = $this->provider->getCredential(MACRO_PASSWORD);
        $this->xtream->init($this->provider->getRawApiCommand(API_COMMAND_VOD), $pass, $pass);
        $this->xtream->reset_cache();

        return true;
    }

    /**
     * @param string $movie_id
     * @return Movie
     * @throws Exception
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);
        $item = $this->xtream->get_stream_info($movie_id);

        if ($item === false) {
            hd_debug_print("failed to load movie: $movie_id");
            return null;
        }

        $movie = new Movie($movie_id, $this->plugin);
        $movie->set_data(
            $item->info->name,                    // name,
            $item->info->o_name,                  // name_original,
            $item->info->plot,                    // description,
            $item->info->movie_image,             // poster_url,
            $item->info->duration,                // length_min,
            $item->info->releasedate,             // year,
            $item->info->director,                // director_str,
            '',                       // scenario_str,
            $item->info->actors,                  // actors_str,
            $item->info->genre,                   // genres_str,
            $item->rating,                        // rate_imdb,
            $item->info->rating_count_kinopoisk,  // rate_kinopoisk,
            $item->info->age,                     // rate_mpaa,
            $item->info->country,                 // country,
            ''                             // budget
        );

        $id = $movie_id;
        if (!empty($item->container_extension)) {
            $id .= ".$item->container_extension";
        }

        $url = $this->xtream->get_stream_url($id);
        hd_debug_print("movie playback_url: $url");
        $movie->add_series_data($movie_id, $item->info->name, '', $url);

        return $movie;
    }

    /**
     * @param array &$category_list
     * @param array &$category_index
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        $categories = $this->xtream->get_categories();
        if ($categories === false) {
            return;
        }

        $category_list = array();
        $category_index = array();

        $category_tree = array();
        foreach ($categories as $item) {
            hd_debug_print("$item->category_id ($item->category_name)", true);
            $pair = explode("|", $item->category_name);

            $parent_id = trim($pair[0]);
            $category_tree[$parent_id][] = trim($pair[1]) . "_$item->category_id";
        }

        $category_count = 0;
        foreach ($category_tree as $key => $value) {
            $category = new Vod_Category($key, $key);
            $gen_arr = array();
            foreach ($value as $sub_cat_name) {
                $sub_pair = explode("_", $sub_cat_name);
                hd_debug_print("Sub Category: $sub_pair[0] ($sub_pair[1])", true);
                $gen_arr[] = new Vod_Category($sub_pair[1], $sub_pair[0], $category);
            }

            $category->set_sub_categories($gen_arr);
            $category_count += count($gen_arr);

            $category_list[] = $category;
            $category_index[$category->get_id()] = $category;
        }
        hd_debug_print("Categories read: $category_count");
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $query_id
     * @return array
     * @throws Exception
     */
    public function getMovieList($query_id)
    {
        $movies = array();

        $arr = explode("_", $query_id);
        $category_id = ($arr === false) ? $query_id : $arr[1];

        $vod_items = $this->xtream->get_streams($category_id);

        $current_offset = $this->get_next_page($query_id, 0);
        $pos = 0;
        foreach ($vod_items as $movie) {
            if ($pos++ < $current_offset) continue;

            $category = $movie->category_id;
            if (empty($category)) {
                $category = TR::load_string('no_category');
            }

            if ($category_id === Vod_Category::FLAG_ALL || $category_id === $category) {
                $movies[] = self::CreateShortMovie($movie);
            }
        }
        $this->get_next_page($query_id, $pos - $current_offset);

        hd_debug_print("Movies read for query: $query_id - " . count($movies));
        return $movies;
    }

    /**
     * @param string $keyword
     * @return array
     * @throws Exception
     */
    public function getSearchList($keyword)
    {
        hd_debug_print($keyword);
        $categories = $this->xtream->get_categories();
        if ($categories === false) {
            return array();
        }

        $movies = array();
        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));
        foreach ($categories as $category) {
            $streams = $this->xtream->get_streams($category->category_id);
            if ($streams === false) continue;

            foreach ($streams as $stream) {
                $search = utf8_encode(mb_strtolower($stream->name, 'UTF-8'));
                if (strpos($search, $keyword) !== false) {
                    $movies[$stream->stream_id] = self::CreateShortMovie($stream);
                }
            }
        }

        $movies = array_values($movies);
        hd_debug_print("Movies found: " . count($movies));
        return $movies;
    }

    /**
     * @param Object $movie_obj
     * @return Short_Movie
     */
    protected static function CreateShortMovie($movie_obj)
    {
        $id = '-1';
        if (isset($movie_obj->stream_id)) {
            $id = (string)$movie_obj->stream_id;
        }

        $movie = new Short_Movie($id, (string)$movie_obj->name, (string)$movie_obj->stream_icon);
        $movie->info = TR::t('vod_screen_movie_info__2', $movie_obj->name, $movie_obj->rating);

        return $movie;
    }
}
