<?php

class Movie_Season
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
    public $season_url = '';

    /**
     * @var array
     */
    public $series;

    /**
     * @param string $id
     * @throws Exception
     */
    public function __construct($id)
    {
        if (is_null($id)) {
            print_backtrace();
            throw new Exception("Movie_Season::id is not set");
        }
        $this->id = (string)$id;
    }
}
