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
    public $description = '';

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
    public $poster = '';

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
     * @param string $id
     * @param string $name
     * @param string $playback_url
     * @param string $season_id
     * @throws Exception
     */
    public function __construct($id, $name, $playback_url, $season_id = '')
    {
        if (is_null($id)) {
            print_backtrace();
            throw new Exception("Movie_Series::id is null");
        }

        $this->id = (string)$id;
        $this->name = $name;
        $this->playback_url = (string)$playback_url;
        $this->season_id = (string)$season_id;
    }
}
