<?php
require_once 'TagAttributes.php';

class ExtInf
{
    /**
     * @var string
     */
    private $title;

    /**
     * @var TagAttributes
     */
    private $attributes;

    /**
     * @param string $line
     * @param bool $full
     */
    public function __construct($line, $full = true)
    {
        $this->parseData($line, $full);
    }

    /**
     * EXTINF format:
     * #EXTINF:<duration> [<attributes-list>], <title>
     * example:
     * #EXTINF:-1 tvg-name="Первый HD" tvg-logo="http://server/logo/1.jpg" group-title="Эфирные каналы",Первый канал HD
     *
     * @param string $line
     * @param bool $full
     */
    protected function parseData($line, $full)
    {
        $tmp = substr($line, 8); // #EXTINF:
        $split = explode(',', $tmp, 2);
        $this->setTitle(trim($split[1]));
        if ($full) {
            $this->parseAttributes($split[0]);
        }

        return $this;
    }

    public function parseAttributes($data)
    {
        $this->attributes = new TagAttributes($data);
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return TagAttributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     * @return string
     */
    public function getAttribute($name)
    {
        return empty($this->attributes) ? '' : $this->attributes->getAttribute($name);
    }
}
