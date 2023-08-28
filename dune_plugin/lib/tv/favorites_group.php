<?php
require_once 'lib/default_group.php';

class Favorites_Group extends Default_Group
{
    const FAV_CHANNEL_GROUP_ICON_PATH = 'plugin_file://icons/favorite_folder.png';
    const FAV_CHANNEL_GROUP_CAPTION = 'plugin_favorites';

    public function __construct()
    {
        parent::__construct(FAV_CHANNEL_GROUP_ID,
            TR::load_string(self::FAV_CHANNEL_GROUP_CAPTION),
            self::FAV_CHANNEL_GROUP_ICON_PATH);

        $this->_favorite = true;
    }
}
