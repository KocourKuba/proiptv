<?php

class KnownCatchupSourceTags
{
    const cu_unknown     = 'unknown';
    const cu_default     = 'default'; // only replace variables
    const cu_shift       = 'shift'; // ?utc=startUnix&lutc=nowUnix
    const cu_append      = 'append'; // appending value specified in catchup-source attribute to the base channel url
    const cu_archive     = 'archive'; // ?archive=startUnix&archive_end=toUnix
    const cu_timeshift   = 'timeshift'; // timeshift=startUnix&timenow=nowUnix
    const cu_flussonic   = 'flussonic';
    const cu_xstreamcode = 'xs'; // xstream codes

    public static $catchup_tags = array(
        self::cu_default => array('default'),
        self::cu_shift => array('shift', 'archive'),
        self::cu_append => array('append'),
        self::cu_archive => array('archive'),
        self::cu_timeshift => array('timeshift'),
        self::cu_flussonic => array('flussonic', 'flussonic-hls', 'fs', 'flussonic-ts'),
        self::cu_xstreamcode => array('xc'),
    );

    /**
     * @param $tag
     * @param mixed $value
     * @return bool
     */
    public static function is_tag($tag, $value)
    {
        if (!isset(self::$catchup_tags[$tag]))
            return false;

        if (is_array($value)) {
            foreach ($value as $item) {
                if (in_array($item, self::$catchup_tags[$tag])) {
                    return true;
                }
            }
            return false;
        }
        return in_array($value, self::$catchup_tags[$tag]);
    }
}
