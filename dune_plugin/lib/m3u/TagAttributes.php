<?php

class TagAttributes
{
    private $attributes = array();

    /**
     * @param string $line
     */
    public function __construct($line)
    {
        $this->parseAttributes($line);
    }

    /**
     * example string: tvg-ID="" tvg-name="Первый HD" tvg-logo="" group-title="Общие"
     *
     * @param string $attrString
     */
    private function parseAttributes($attrString)
    {
        preg_match_all('/([^=" ]+)=("(?:\"|[^"])*"|(?:\"|[^=" ])+)/', $attrString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $this->attributes[$match[1]] = trim($match[2], "\"");
        }
    }

    /**
     * @return array
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
        return isset($this->attributes[$name]) ? $this->attributes[$name] : '';
    }
}
