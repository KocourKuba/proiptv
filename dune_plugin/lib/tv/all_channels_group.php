<?php
require_once 'lib/default_group.php';

class All_Channels_Group extends Default_Group
{
    /**
     * @var Tv
     */
    private $tv;

    /**
     * @param Tv $tv
     * @param $id
     * @param string $title
     * @param string $icon_url
     */
    public function __construct(Tv $tv, $id, $title, $icon_url)
    {
        parent::__construct($id, $title, $icon_url);

        $this->tv = $tv;
        $this->is_all_group = true;
    }

    /**
     * @return Hashed_Array<Channel>
     */
    public function get_group_channels()
    {
        return $this->tv->get_channels();
    }
}
