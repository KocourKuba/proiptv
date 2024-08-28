<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

require_once 'json_serializer.php';

/**
 * @property string|null $screen_id // Screen ID, used to find screen handler
 * @property string|null $channel_id // Channel ID
 * @property string|null $group_id // Group ID
 * @property string|null $row_id // row id (used in NewUI)
 * @property string|null $source_window_id // Window ID called this screen
 * @property string|null $source_media_url_str // Media Url string of window called this screen
 * @property string|null $save_data // parameter used to save data
 * @property string|null $type // Folder type (used in Starnet_Folder_Screen)
 * @property string|null $id // ID
 * @property mixed|null $choose_folder // Action 'choose folder'
 * @property mixed|null $choose_file // Action 'choose file'
 * @property string|null $filepath // real path of selected folder or file
 * @property string|null $windowCounter // Index of current window, start from 1
 * @property string|null $end_action // action called for parent window
 * @property string|null $cancel_action // action called for parent window
 * @property string|null $extension // pattern for show files with specified extension
 * @property string|null $caption // Caption of the selected media url
 * @property bool|null $allow_network // Allow to use network folders NFS/SMB for Starnet_Folder_Screen
 * @property bool|null $allow_image_lib // Allow to use image lib folders for Starnet_Folder_Screen
 * @property string|null $nfs_protocol // symbolic name of NFS protocol
 * @property bool|null $is_favorite // Is selected media url point to the favorite folder
 * @property int|mixed|null $archive_tm // timestamp of the archive position playback, -1 live broadcast
 * @property string|null $err // error
 * @property string|null $ip_path // ip address or name of NFS/SMB server, used in Starnet_Folder_Screen
 * @property string|null $no_internet // is not valid EPS screen
 * @property string|null $user // login, used in Starnet_Folder_Screen
 * @property string|null $password // password, used in Starnet_Folder_Screen
 * @property string|null $edit_list // type of Starnet_Edit_List_Screen (playlist, epg, hidden groups/channels)
 * @property bool|null $allow_order // allow order items, used in Starnet_Edit_List_Screen
 * @property bool|null $deny_edit // deny edit items, used in Starnet_Edit_List_Screen
 * @property string|null $postpone_save // name of controlled postpone save status
 * @property bool|null $allow_reset // show reset to default button and call action ACTION_RESET_DEFAULT
 * @property string|null movie_id // Movie ID
 * @property string|null category_id // Movie Category ID
 * @property string|null season_id // Season ID
 * @property string|null episode_id // Episode ID
 * @property string|null genre_id // Movie Genre ID
 * @property string|null name // search name
 */
class MediaURL extends Json_Serializer
{
    // Original media-url string.
    /**
     * @var mixed|null
     */
    protected $str;

    // If media-url string contains map, it's decoded here.
    // Null otherwise.
    protected $map;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $str
     * @param array|null $map
     */
    private function __construct($str, $map)
    {
        $this->str = $str;
        $this->map = $map;
    }

    ///////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////

    /**
     * @param array $m
     * @param bool $raw_encode
     * @return MediaURL
     */
    public static function make($m, $raw_encode = false)
    {
        return self::decode(self::encode($m, $raw_encode));
    }

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
     * @return false|string
     */
    public static function encode($m, $raw_encode = false)
    {
        return $raw_encode ? pretty_json_format($m) : json_encode($m);
    }

    /**
     * @param mixed $key
     */
    public function __unset($key)
    {
        if (is_null($this->map)) {
            return;
        }

        unset($this->map->{$key});
    }

    /**
     * @param mixed $key
     * @return mixed|null
     */
    public function __get($key)
    {
        if (is_null($this->map)) {
            return null;
        }

        return isset($this->map->{$key}) ? $this->map->{$key} : null;
    }

    ///////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        if (is_null($this->map)) {
            $this->map = (object)array();
        }

        $this->map->{$key} = $value;
    }

    /**
     * @param mixed $key
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
    public function __toString()
    {
        return (string)MediaUrl::encode($this->map, true);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public function get_raw_string()
    {
        return $this->str;
    }

    /**
     * @param bool $raw_encode
     * @return string
     */
    public function get_media_url_str($raw_encode = false)
    {
        return MediaUrl::encode($this->map, $raw_encode);
    }
}
