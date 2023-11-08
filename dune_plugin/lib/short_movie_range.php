<?php

class Short_Movie_Range
{
    /**
     * @var int
     */
    public $from_ndx;

    /**
     * @var int
     */
    public $total;

    /**
     * @var array|mixed
     */
    public $short_movies;

    /**
     * @param int $from_ndx
     * @param int $total
     * @param array|null $short_movies
     */
    public function __construct($from_ndx, $total, $short_movies = null)
    {
        $this->from_ndx = (int)$from_ndx;
        $this->total = (int)$total;
        $this->short_movies = $short_movies === null ? array() : $short_movies;
    }
}
