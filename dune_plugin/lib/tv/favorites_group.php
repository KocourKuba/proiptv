<?php
require_once 'lib/default_group.php';

class Favorites_Group extends Default_Group
{
    const FAV_CHANNEL_GROUP_ICON_PATH = 'plugin_file://icons/favorite_folder.png';
    const FAV_CHANNEL_GROUP_CAPTION = 'plugin_favorites';

    public function __construct($plugin)
    {
        parent::__construct($plugin,
            FAV_CHANNEL_GROUP_ID,
            TR::load_string(self::FAV_CHANNEL_GROUP_CAPTION),
            self::FAV_CHANNEL_GROUP_ICON_PATH);

        $this->_channels_order->set_callback($this->plugin, PARAM_FAVORITES);
        $this->_favorite = true;
    }
}
