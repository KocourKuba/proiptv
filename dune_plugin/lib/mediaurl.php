<?php

/**
 * @property mixed|null $screen_id // Screen ID, used to find screen handler
 * @property mixed|null $channel_id // Channel ID
 * @property mixed|null $group_id // Group ID
 * @property string|null $source_window_id // Window ID called this screen
 * @property mixed|null $id // ID
 * @property mixed|null $type // Type of selected media url or extension of selected file
 * @property mixed|null $choose_folder // Action 'choose folder'
 * @property mixed|null $choose_file // Action 'choose file'
 * @property mixed|null $filepath // real path of selected folder or file
 * @property mixed|null $windowCounter // Index of current window, start from 1
 * @property mixed|null $end_action // action called for parent window
 * @property mixed|null $cancel_action // action called for parent window
 * @property mixed|null $extension // pattern for show files with specified extension
 * @property mixed|null $caption // Caption of the selected media url
 * @property bool|null  $allow_network // Allow to use network folders NFS/SMB for Starnet_Folder_Screen
 * @property mixed|null $nfs_protocol // symbolic name of NFS protocol
 * @property bool|null $is_favorite // Is selected media url point to the favorite folder
 * @property int|mixed|null $archive_tm // timestamp of the archive position playback, -1 live broadcast
 * @property mixed|null $err // error
 * @property mixed|null $ip_path // ip address or name of NFS/SMB server, used in Starnet_Folder_Screen
 * @property mixed|null $no_internet // is not valid EPS screen
 * @property mixed|null $user // login, used in Starnet_Folder_Screen
 * @property mixed|null $password // password, used in Starnet_Folder_Screen
 * @property mixed|null $edit_list // type of Starnet_Edit_List_Screen (playlist, epg, hidden groups/channels)
 * @property bool|null $allow_order // allow order items, used in Starnet_Edit_List_Screen
 * @property string|null $postpone_save // name of controlled postpone save status
 */
class MediaURL
{
    // Original media-url string.
    /**
     * @var mixed|null
     */
    private $str;

    // If media-url string contains map, it's decoded here.
    // Null otherwise.
    private $map;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param $str string
     * @param $map array|null
     */
    private function __construct($str, $map)
    {
        $this->str = $str;
        $this->map = $map;
    }

    ///////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////

    /**
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        if (is_null($this->map)) {
            $this->map = (object)array();
        }

        $this->map->{$key} = $value;
    }

    /**
     * @param $key
     */
    public function __unset($key)
    {
        if (is_null($this->map)) {
            return;
        }

        unset($this->map->{$key});
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function __get($key)
    {
        if (is_null($this->map)) {
            return null;
        }

        return isset($this->map->{$key}) ? $this->map->{$key} : null;
    }

    /**
     * @param $key
     * @return bool
     */
    public function __isset($key)
    {
        if (is_null($this->map)) {
            return false;
        }

        return isset($this->map->{$key});
    }

    ///////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public function get_raw_string()
    {
        return $this->str;
    }

    /**
     * @return string
     */
    public function get_media_url_str()
    {
        return MediaUrl::encode($this->map);
    }

    ///////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////

    /**
     * @param array $m
     * @param bool $raw_encode
     * @return false|string
     */
    public static function encode($m, $raw_encode = false)
    {
        return $raw_encode ? raw_json_encode($m) : json_encode($m);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $s
     * @return MediaURL
     */
    public static function decode($s = '')
    {
        if (strpos($s, '{') !== 0) {
            return new MediaURL($s, null);
        }

        return new MediaURL($s, json_decode($s));
    }

    /**
     * @param array $m
     * @param bool $raw_encode
     * @return MediaURL
     */
    public static function make($m, $raw_encode = false)
    {
        return self::decode(self::encode($m, $raw_encode));
    }
}
