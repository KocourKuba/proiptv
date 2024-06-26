<?php
require_once 'epg_item.php';

class Default_Epg_Item implements Epg_Item
{
    /**
     * @var string
     */
    protected $_title;

    /**
     * @var string
     */
    protected $_description;

    /**
     * @var int
     */
    protected $_start_time;

    /**
     * @var int
     */
    protected $_finish_time;

    /**
     * @param string $title
     * @param string $description
     * @param int $start_time
     * @param int $finish_time
     */
    public function __construct($title, $description, $start_time, $finish_time)
    {
        $this->_title = $title;
        $this->_description = $description;
        $this->_start_time = $start_time;
        $this->_finish_time = $finish_time;
    }

    /**
     * @inheritDoc
     */
    public function get_title()
    {
        return $this->_title;
    }

    /**
     * @inheritDoc
     */
    public function get_description()
    {
        return $this->_description;
    }

    /**
     * @inheritDoc
     */
    public function get_start_time()
    {
        return $this->_start_time;
    }

    /**
     * @inheritDoc
     */
    public function get_finish_time()
    {
        return $this->_finish_time;
    }
}
