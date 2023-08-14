<?php

class ExtGrp
{
    /**
     * @var string
     */
    private $group;

    /**
     * @param string $lineStr
     */
    public function __construct($lineStr)
    {
        $this->parseData($lineStr);
    }

    /**
     * EXTINF format:
     * #EXTINF:<duration> [<attributes-list>], <title>
     * example:
     * #EXTINF:-1 tvg-name="Первый HD" tvg-logo="http://server/logo/1.jpg" group-title="Эфирные каналы",Первый канал HD
     *
     * @param string $lineStr
     */
    protected function parseData($lineStr)
    {
        $name = trim(substr($lineStr, 8)); // #EXTINF
        $this->setGroup($name);
    }

    /**
     * @param $group
     * @return ExtGrp
     */
    public function setGroup($group)
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Group Name
     * @return string
     */
    public function getGroup()
    {
        return empty($this->group) ? '' : $this->group;
    }
}
