<?php
require_once 'short_movie.php';

abstract class Abstract_Vod
{
    /**
     * @var array|Short_Movie[]
     */
    protected $short_movie_by_id = array();

    /**
     * @var array|Movie[]
     */
    protected $movie_by_id = array();

    /**
     * @var array|bool[]
     */
    protected $failed_movie_ids = array();

    /**
     * @var array
     */
    protected $genres;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Short_Movie $short_movie
     */
    public function set_cached_short_movie(Short_Movie $short_movie)
    {
        $this->short_movie_by_id[$short_movie->id] = $short_movie;
    }

    /**
     * @param Movie $movie
     */
    public function set_cached_movie(Movie $movie)
    {
        $this->movie_by_id[$movie->id] = $movie;
        $this->set_cached_short_movie(new Short_Movie($movie->id, $movie->name, $movie->poster_url));
    }

    /**
     * @param string $movie_id
     */
    public function set_failed_movie_id($movie_id)
    {
        $this->failed_movie_ids[$movie_id] = true;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $movie_id
     * @return bool
     */
    public function is_failed_movie_id($movie_id)
    {
        return isset($this->failed_movie_ids[$movie_id]);
    }

    /**
     * @param string $movie_id
     * @return bool
     */
    public function has_cached_movie($movie_id)
    {
        return isset($this->movie_by_id[$movie_id]);
    }

    /**
     * @param string $movie_id
     * @return Movie|null
     */
    public function get_cached_movie($movie_id)
    {
        return isset($this->movie_by_id[$movie_id]) ? $this->movie_by_id[$movie_id] : null;
    }

    /**
     * @param string $movie_id
     * @return bool
     */
    public function has_cached_short_movie($movie_id)
    {
        return isset($this->short_movie_by_id[$movie_id]);
    }

    /**
     * @param string $movie_id
     * @return Short_Movie|null
     */
    public function get_cached_short_movie($movie_id)
    {
        return isset($this->short_movie_by_id[$movie_id]) ? $this->short_movie_by_id[$movie_id] : null;
    }

    /**
     * @return void
     */
    public function clear_movie_cache()
    {
        hd_debug_print("Abstract_Vod::clear_movie_cache: movie cache cleared");

        $this->short_movie_by_id = array();
        $this->movie_by_id = array();
        $this->failed_movie_ids = array();
    }

    public function clear_genre_cache()
    {
        unset($this->genres);
        $this->genres = null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $movie_id
     */
    public function ensure_movie_loaded($movie_id)
    {
        if (!isset($movie_id)) {
            hd_debug_print("Movie ID is not set");
            return;
        }

        if ($this->is_failed_movie_id($movie_id)) {
            hd_debug_print("No movie with ID: $movie_id");
            return;
        }

        $movie = $this->get_cached_movie($movie_id);
        if ($movie === null) {
            hd_debug_print("Movie $movie_id not in cache. Load info.");
            $this->try_load_movie($movie_id);
            hd_debug_print("Movie $movie_id loaded");
        } else {
            hd_debug_print("Movie $movie_id is in cache.");
        }
    }

    /**
     * @param string $movie_id
     * @return Movie|null
     */
    public function get_loaded_movie($movie_id)
    {
        $this->ensure_movie_loaded($movie_id);

        return $this->get_cached_movie($movie_id);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @return array|null
     */
    public function get_vod_info(MediaURL $media_url)
    {
        $movie = $this->get_loaded_movie($media_url->movie_id);
        if ($movie === null) {
            return null;
        }

        return $movie->get_movie_play_info($media_url);
    }

    /**
     * @param string $movie_id
     * @return mixed
     */
    abstract public function try_load_movie($movie_id);

    ///////////////////////////////////////////////////////////////////////
    // Genres.

    /**
     * @return array|null
     */
    protected function load_genres()
    {
        hd_debug_print("Not implemented.");
        return null;
    }

    /**
     * @param string $genre_id
     * @return string|null
     */
    public function get_genre_icon_url($genre_id)
    {
        hd_debug_print("Not implemented.");
        return null;
    }

    /**
     * @param string $genre_id
     * @return string|null
     */
    public function get_genre_media_url_str($genre_id)
    {
        hd_debug_print("Not implemented.");
        return null;
    }

    public function ensure_genres_loaded()
    {
        if ($this->genres !== null) {
            return;
        }

        $this->genres = $this->load_genres();

        if ($this->genres === null) {
            hd_debug_print("Not implemented.");
        }
    }

    /**
     * @return string[]|null
     */
    public function get_genre_ids()
    {
        if ($this->genres === null) {
            hd_debug_print("Not implemented.");
            return null;
        }

        return array_keys($this->genres);
    }

    /**
     * @param string $genre_id
     * @return string|null
     */
    public function get_genre_caption($genre_id)
    {
        if ($this->genres === null) {
            hd_debug_print("Not implemented.");
            return null;
        }

        return $this->genres[$genre_id];
    }

    /**
     * @return array|null
     */
    public function get_vod_genres_folder_views()
    {
        hd_debug_print("Not implemented.");
        return null;
    }
}
