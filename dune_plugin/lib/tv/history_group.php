<?php
require_once 'lib/default_group.php';

class History_Group extends Default_Group
{
    const PLAYBACK_HISTORY_GROUP_ICON_PATH = 'plugin_file://icons/history_folder.png';
    const PLAYBACK_HISTORY_CAPTION  = 'plugin_history';

    public function __construct()
    {
        parent::__construct(PLAYBACK_HISTORY_GROUP_ID,
            TR::load_string(self::PLAYBACK_HISTORY_CAPTION),
            self::PLAYBACK_HISTORY_GROUP_ICON_PATH);

        $this->_history = true;
    }
}
