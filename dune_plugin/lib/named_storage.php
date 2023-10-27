<?php

class Named_Storage
{
    /**
     * @return string
     */
    public function __toString()
    {
        return (string)raw_json_encode($this);
    }

    /**
     * @var string
     */
    public $type = '';

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var array
     */
    public $params = array();
}
