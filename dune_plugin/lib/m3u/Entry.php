<?php

require_once 'ExtInf.php';
require_once 'ExtGrp.php';
require_once 'ExtM3U.php';

class Entry
{
    /**
     * @var ExtM3U|null
     */
    private $extM3U;

    /**
     * @var ExtInf|null
     */
    private $extInf;

    /**
     * @var ExtGrp|null
     */
    private $extGrp;

    /**
     * @var string
     */
    private $path = '';

    /**
     * @var string
     */
    private $group_title = '';

    /**
     * @return boolean
     */
    public function isExtM3U()
    {
        return !is_null($this->extM3U);
    }

    /**
     * @return ExtM3U|null
     */
    public function getExtM3U()
    {
        return $this->extM3U;
    }

    /**
     * @param ExtM3U $extM3U
     */
    public function setExtM3U(ExtM3U $extM3U)
    {
        $this->extM3U = $extM3U;
    }

    /**
     * @return boolean
     */
    public function isExtInf()
    {
        return !is_null($this->extInf);
    }

    /**
     * @return ExtInf|null
     */
    public function getExtInf()
    {
        return $this->extInf;
    }

    /**
     * @param ExtInf $extInf
     */
    public function setExtInf(ExtInf $extInf)
    {
        $this->extInf = $extInf;
    }

    /**
     * @return boolean
     */
    public function isExtGrp()
    {
        return !is_null($this->extGrp);
    }

    /**
     * @return ExtGrp|null
     */
    public function getExtGrp()
    {
        return $this->extGrp;
    }

    /**
     * @param ExtGrp $extGrp
     */
    public function setExtGrp(ExtGrp $extGrp)
    {
        $this->extGrp = $extGrp;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->isExtInf() ? $this->extInf->getTitle() : '';
    }

    /**
     * @return string
     */
    public function getGroupTitle()
    {
        if (empty($this->group_title)) {
            $this->group_title = $this->isExtGrp() ? $this->extGrp->getGroup() : '';
            if (empty($this->group_title)) {
                $this->group_title = $this->getAttribute('group-title');
                if (empty($this->group_title) || $this->group_title === "null") {
                    $this->group_title = TR::load_string('no_category');
                }
            }
        }
        return $this->group_title;
    }

    /**
     * @return string
     */
    public function getEntryId()
    {
        /*
         * Tags used to get entry ID
		 * "CUID", "channel-id", "tvg-chno", "tvg-name", "name",
         */
        static $tags = array("CUID", "channel-id", "tvg-chno", "tvg-name");

        $tag = $this->getAnyAttribute($tags);

        return empty($tag) ? $this->getTitle() : $tag;
    }

    /**
     * @return string
     */
    public function getEntryIcon()
    {
        /*
         * attributes contains picon information
		 * "tvg-logo", "url-logo"
         */
        static $tags = array("tvg-logo", "url-logo");

        return $this->getAnyAttribute($tags);
    }

    /**
     * @return string
     */
    public function getCatchup()
    {
        /*
         * attributes contains catchup information
		 * "catchup", "catchup-type"
         */
        static $tags = array("catchup", "catchup-type");

        return $this->getAnyAttribute($tags);
    }

    /**
     * @return string
     */
    public function getCatchupSource()
    {
        /*
         * attributes contains catchup information
		 * "catchup", "catchup-type"
         */
        static $tags = array("catchup-source", "catchup-template");

        return $this->getAnyAttribute($tags);
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        if ($this->isExtInf()) {
            return $this->extInf->getAttributes()->getAttributes();
        }

        if ($this->isExtM3U()) {
            return $this->extM3U->getAttributes()->getAttributes();
        }

        return array();
    }

    /**
     * @param string $attr
     * @return string
     */
    public function getAttribute($attr)
    {
        if ($this->isExtInf()) {
            return $this->extInf->getAttribute($attr);
        }

        if ($this->isExtM3U()) {
            return $this->extM3U->getAttribute($attr);
        }

        return '';
    }

    /**
     * @param array $attrs
     * @return string
     */
    public function getAnyAttribute($attrs, &$found_attr = null)
    {
        foreach ($attrs as $attr) {
            $val = $this->getAttribute($attr);
            if (empty($val)) continue;

            if ($found_attr !== null) {
                $found_attr = $attr;
            }
            return $val;
        }

        return '';
    }
}
