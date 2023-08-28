<?php
require_once 'lib/default_group.php';

class All_Channels_Group extends Default_Group
{
    const ALL_CHANNEL_GROUP_ICON_PATH = 'plugin_file://icons/all_folder.png';
    const ALL_CHANNEL_GROUP_CAPTION = 'plugin_all_channels';

    public function __construct()
    {
        parent::__construct(ALL_CHANNEL_GROUP_ID,
            TR::load_string(self::ALL_CHANNEL_GROUP_CAPTION),
            self::ALL_CHANNEL_GROUP_ICON_PATH);

        $this->_all_group = true;
    }
}
