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
     * @var
     */
    public $type = '';

    /**
     * @var
     */
    public $name = '';

    /**
     * @var
     */
    public $value = '';
}
