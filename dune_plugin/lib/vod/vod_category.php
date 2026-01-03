<?php

class Vod_Category
{
    const DEFAULT_ICON = 'plugin_file://icons/movies_group.png';

    const FLAG_ALL_MOVIES = '##allmovies##';
    const FLAG_ALL_SERIALS = '##allserials##';
    const FLAG_SEARCH = '##search##';
    const FLAG_FILTER = '##filter##';

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $caption;

    /**
     * @var string
     */
    public $icon_url;

    /**
     * @var array
     */
    public $sub_categories;

    /**
     * @var Vod_Category|null
     */
    public $parent;

    /**
     * @var string |null
     */
    public $url;

    /**
     * @param string $id
     * @param string $caption
     * @param Vod_Category|null $parent
     * @param string $icon_url
     * @param string|null $url
     */
    public function __construct($id, $caption, $parent = null, $icon_url = self::DEFAULT_ICON, $url = null)
    {
        $this->id = $id;
        $this->caption = $caption;
        $this->icon_url = empty($icon_url) ? self::DEFAULT_ICON : $icon_url;
        $this->parent = $parent;
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function get_caption()
    {
        return $this->caption;
    }

    /**
     * @return string
     */
    public function get_icon_path()
    {
        return $this->icon_url;
    }

    /**
     * @return array
     */
    public function get_sub_categories()
    {
        return $this->sub_categories;
    }

    /**
     * @param array $arr
     */
    public function set_sub_categories($arr)
    {
        $this->sub_categories = $arr;
    }

    /**
     * @return Vod_Category|null
     */
    public function get_parent()
    {
        return $this->parent;
    }

    /**
     * @return string|null
     */
    public function get_url()
    {
        return $this->url;
    }
}
