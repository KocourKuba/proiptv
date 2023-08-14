<?php
require_once 'TagAttributes.php';

class ExtM3U
{
    /**
     * @var TagAttributes
     */
    private $attributes;

    /**
     * @param string $line
     */
    public function __construct($line)
    {
        $this->parseData($line);
    }

    /**
     * #EXTM3U url-tvg="http://epg.101film.org/4.xmltv" x-tvg-url="http://epg.101film.org/4-light.xmltv" servers="[{`id`:`0`,`name`:`Auto Select`},{`id`:`1`,`name`:`Server CZ`},{`id`:`2`,`name`:`Server DE`},{`id`:`3`,`name`:`Server NL`},{`id`:`4`,`name`:`Server RU`}]" bitrates="[{`id`:`9`,`name`:`Hi`}]" acc_balance="0.8667" expiry_date="2023.06.21"
     * #EXTM3U url-tvg="http://epg.g-cdn.app/xmltv.xml.gz" catchup-type="shift"
     * #EXTM3U url-tvg="http://epg.fox-tv.fun/1.xmltv" x-tvg-url="http://epg.fox-tv.fun/1-light.xmltv"
     *
     * @param string $line
     */
    protected function parseData($line)
    {
        $attr = substr($line, 8); // #EXTM3U
        $this->parseAttributes($attr);
        return $this;
    }

    public function parseAttributes($data)
    {
        $this->attributes = new TagAttributes($data);
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
