<?php

class HistoryItem
{
    /**
     * @var bool $watched
     */
    public $watched;

    /**
     * @var int $position
     */
    public $position;

    /**
     * @var int $duration
     */
    public $duration;

    /**
     * @var int $date
     */
    public $date;

    public function __construct($watched, $position, $duration, $date)
    {
        $this->watched = $watched;
        $this->position = $position;
        $this->duration = $duration;
        $this->date = $date;
    }
}
