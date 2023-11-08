<?php

class Movie_Variant
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
    public $playback_url = '';

    /**
     * @var bool
     */
    public $playback_url_is_stream_url = true;

    /**
     * @param $id string
     * @throws Exception
     */
    public function __construct($id, $name, $playback_url, $playback_url_is_stream_url = true)
    {
        if (is_null($id)) {
            print_backtrace();
            throw new Exception("Movie_Variant::id is null");
        }

        $this->id = (string)$id;
        $this->name = (string)$name;
        $this->playback_url = (string)$playback_url;
        $this->playback_url_is_stream_url = $playback_url_is_stream_url;
    }
}
