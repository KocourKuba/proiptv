<?php

# Playback Points
#
# Idea: Brigadir (forum.mydune.ru)
# Modification: sharky72

require_once 'lib/mediaurl.php';
require_once 'lib/dune_stb_api.php';

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

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
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
                return;
            }

            $this->points = HD::get_items($path);
            //hd_debug_print(count($points) . " from: $storage");
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
            hd_debug_print(count($this->points) . " to: $path");
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
        //hd_debug_print();

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
                //hd_debug_print("channel_id $id at time mark: {$this->points[$id]}");
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

                hd_debug_print("channel_id $channel_id time mark: $archive_ts");
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
        $path = $this->plugin->get_parameters(PARAM_HISTORY_PATH, get_data_path());
        if (!is_dir($path)) {
            hd_debug_print("load path not exist: $path");
            return '';
        }

        return $path . $this->plugin->get_playlist_hash() . PARAM_TV_HISTORY_ITEMS;
    }
}
