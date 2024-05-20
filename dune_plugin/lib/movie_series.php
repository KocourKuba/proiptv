<?php

class Movie_Series
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string
     */
    public $series_desc = '';

    /**
     * @var string
     */
    public $season_id = '';

    /**
     * @var string
     */
    public $playback_url = '';

    /**
     * @var string
     */
    public $movie_image = '';

    /**
     * @var array
     */
    public $qualities;

    /**
     * @var array
     */
    public $audios;

    /**
     * @var bool
     */
    public $playback_url_is_stream_url = true;

    /**
     * @param $id string
     * @throws Exception
     */
    public function __construct($id)
    {
        if (is_null($id)) {
            print_backtrace();
            throw new Exception("Movie_Series::id is null");
        }

        $this->id = (string)$id;
    }
}
