<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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

/**
 * VOD of one playlist: its provider, VOD class and databases.
 * Default_Dune_Plugin keeps a context per playlist, the VOD queries use the dbs of the selected one.
 */
class Vod_Context
{
    /**
     * Playlist of the selected VOD context, set by Default_Dune_Plugin
     *
     * @var string|null
     */
    public static $selected_playlist_id;

    /**
     * Active playlist, the VOD screen urls made without a selected context belong to it
     *
     * @var string|null
     */
    public static $active_playlist_id;

    /**
     * @var string
     */
    public $playlist_id;

    /**
     * @var api_default|null null for a VOD playlist
     */
    public $provider;

    /**
     * @var vod_standard|null null if the playlist has no VOD
     */
    public $vod;

    /**
     * @var bool
     */
    public $enabled = false;

    /**
     * VOD playlist db (m3u VOD), opened by vod_standard when the categories are fetched
     *
     * @var Sql_Wrapper|null
     */
    public $sql_vod;

    /**
     * @var Sql_Wrapper|null
     */
    public $sql_history;

    /**
     * Settings db of the playlist, null if it is the active playlist settings db (shared with TV)
     *
     * @var Sql_Wrapper|null
     */
    public $sql_settings;

    /**
     * @param string $playlist_id
     */
    public function __construct($playlist_id)
    {
        $this->playlist_id = $playlist_id;
    }

    /**
     * Adds the playlist to the media url params of a VOD screen, the plugin selects the VOD context by it
     *
     * @param array $params media url params
     * @param string|null $playlist_id null - the playlist of the selected context or the active playlist
     * @return array
     */
    public static function add_playlist_id($params, $playlist_id = null)
    {
        if (empty($playlist_id)) {
            $playlist_id = empty(self::$selected_playlist_id) ? self::$active_playlist_id : self::$selected_playlist_id;
        }

        if (!empty($playlist_id)) {
            $params[PARAM_PLAYLIST_ID] = $playlist_id;
        }

        return $params;
    }

    /**
     * Close the own dbs, the shared settings db is not closed
     *
     * @return void
     */
    public function close()
    {
        foreach (array('sql_vod', 'sql_history', 'sql_settings') as $name) {
            if ($this->$name !== null) {
                $this->$name->close();
                $this->$name = null;
            }
        }

        $this->vod = null;
        $this->enabled = false;
    }
}
