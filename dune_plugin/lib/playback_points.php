<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * @Idea: Brigadir (forum.mydune.ru)
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

require_once 'mediaurl.php';
require_once 'dune_stb_api.php';

///////////////////////////////////////////////////////////////////////////////

class Playback_Points
{
    /**
     * @var string
     */
    private $curr_point_id;

    /**
     * @var array
     */
    private $points;

    /**
     * @var Default_Dune_Plugin
     */
    private $plugin;

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->load_points();
    }

    /**
     * @return array
     */
    public function get_all()
    {
        return $this->points;
    }

    /**
     * @return int
     */
    public function size()
    {
        return count($this->points);
    }

    /**
     * @param bool $force
     * @return void
     */
    public function load_points($force = false)
    {
        if (!isset($this->points) || $force) {
            $path = $this->get_playback_points_file();
            if (empty($path)) {
                $this->points = array();
                return;
            }

            $this->points = HD::get_items($path);
            hd_debug_print(count($this->points) . " from: $path", true);
            while (count($this->points) > 7) {
                array_pop($this->points);
            }
        }
    }

    /**
     * @return void
     */
    public function save()
    {
        $path = $this->get_playback_points_file();
        if (empty($path)) {
            return;
        }

        if (count($this->points) !== 0) {
            //hd_debug_print(count($this->points) . " to: $path");
            HD::put_items($path, $this->points);
        } else if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @param string|null $id
     */
    public function update_point($id)
    {
        //hd_debug_print($id);

        if ($this->curr_point_id === null && $id === null)
            return;

        // update point for selected channel
        $id = ($id !== null) ? $id : $this->curr_point_id;

        if (isset($this->points[$id])) {
            $player_state = get_player_state_assoc();
            if (isset($player_state['playback_state'], $player_state['playback_position'])
                && ($player_state['playback_state'] === PLAYBACK_PLAYING || $player_state['playback_state'] === PLAYBACK_STOPPED)) {

                // if channel does support archive do not update current point
                $this->points[$id] += ($this->points[$id] !== 0) ? $player_state['playback_position'] : 0;
                hd_debug_print("channel_id $id at time mark: {$this->points[$id]}", true);
            }
        }
    }

    /**
     * @param string $channel_id
     * @param integer $archive_ts
     */
    public function push_point($channel_id, $archive_ts)
    {
        $player_state = get_player_state_assoc();
        if (isset($player_state['player_state']) && $player_state['player_state'] !== 'navigator') {
            if (!isset($player_state['last_playback_event']) || ($player_state['last_playback_event'] !== PLAYBACK_PCR_DISCONTINUITY)) {

                //hd_debug_print("channel_id $channel_id time mark: $archive_ts");
                $this->curr_point_id = $channel_id;

                if (isset($this->points[$channel_id])) {
                    unset($this->points[$channel_id]);
                }
                $this->points = array($channel_id => $archive_ts) + $this->points;
                if (count($this->points) > 7) {
                    array_pop($this->points);
                }
                $this->save();
            }
        }
    }

    /**
     * @param string $id
     */
    public function erase_point($id)
    {
        hd_debug_print("erase $id");
        unset($this->points[$id]);
        $this->save();
    }

    /**
     * @return void
     */
    public function clear_points()
    {
        hd_debug_print();
        $this->points = array();
        $this->save();
    }
    ///////////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    private function get_playback_points_file()
    {
        $path = $this->plugin->get_history_path();
        if (!create_path($path)) {
            hd_debug_print("History path not exist: $path");
            return '';
        }

        return get_slash_trailed_path($path) . $this->plugin->get_playlist_hash() . PARAM_TV_HISTORY_ITEMS;
    }
}
