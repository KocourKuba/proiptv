<?php

class Short_Movie
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $poster_url;

    /**
     * @var
     */
    public $info;

    /**
     * @param string $id
     * @param string $name
     * @param string $poster_url
     */
    public function __construct($id, $name, $poster_url)
    {
        if (is_null($id)) {
            hd_debug_print("id is null");
            return;
        }

        $this->id = $id;
        $this->name = $name;
        $this->poster_url = $poster_url;
    }
}
