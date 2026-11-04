<?php

if (!defined("HD::INT_MIN")) {
    require_once 'dunelib/utils.php';
}

class DlState
{
    const GSTAT_PATH = '/tmp/run/dl_gstat.txt';
    const STATE_PATH = '/tmp/run/dl_state.txt';
    const BASE_DIR_PATH = '/flashdata/dl_cache';
    const BASE_TMP_DIR_PATH = '/tmp/run/dl_cache';

    public $gstat = array();
    public $gstat_mtime = -2;

    public $main_state = null;

    public $cur_g;
    public $all_groups;
    public $all_groups_loaded = false;

    public function __construct() {
        $this->cur_g = self::st_initializer();
        $this->all_groups = array();
    }

    public static function st_initializer() {
        return (object) array(
            'group_id' => null, 'name' => '?', 'total' => 0,
            'status' => '', 'mtime' => -2, 'f_by_path' => null);
    }

    public function reload_main_state() {
        $path = self::STATE_PATH;
        $arr = null;
        if (is_file($path))
            $arr = HD::read_props_file($path, null);
        if (!$arr)
            $arr = array();

        if (isset($this->main_state) && $arr == $this->main_state)
            return false;

        $this->main_state = $arr;
        return true;
    }

    public function reload_gstat()
    {
        $path = self::GSTAT_PATH;
        clearstatcache(true, $path);

        if (!is_file($path)) {
            $this->gstat_mtime = -1;
            $changed = (bool) $this->gstat;
            $this->gstat = array();
            return $changed;
        }

        $mtime = filemtime($path);
        if ($mtime === false)
            $mtime = -1;

        if ($mtime == $this->gstat_mtime)
            return false;

        $this->gstat_mtime = $mtime;
        $this->gstat = array();

        $lines = file($path);
        foreach ($lines as $line)
        {
            $line = rtrim($line);
            list ($st, $files_str, $progress, $speed, $id) = explode(' ', $line, 5);
            if ($st == 'd' || $st == 'D') {
                unset($this->gstat[$id]);
            } else {
                $this->gstat[$id] = (object) array(
                    'status' => $st, 'files_str' => $files_str,
                    'progress' => $progress, 'speed' => $speed);
            }
        }

        return true;
    }

    private static function get_torrent_state_path($group_id, $is_tmp=-1)
    {
        if ($is_tmp == -1)
        {
            clearstatcache();
            $path = self::get_torrent_state_path($group_id, 1);
            if (!is_file($path))
                $path = self::get_torrent_state_path($group_id, 0);
            return $path;
        }

        $dir = $is_tmp ? self::BASE_TMP_DIR_PATH : self::BASE_DIR_PATH;
        return "$dir/$group_id.json";
    }

    public function sync_state($group_id)
    {
        if ($group_id != $this->cur_g->group_id)
            $this->reload_state($group_id);
    }

    private static function clear_g($group_id, &$g)
    {
        $changed = $group_id != $g->group_id || $g->f_by_path;
        $g->group_id = $group_id;
        $g->name = '?';
        $g->total = 0;
        $g->status = '';
        $g->mtime = -1;
        $g->f_by_path = null;
        if ($changed)
            $g->changed = true;
        return $changed;
    }

    public function all_state_to_current_group()
    {
        $cur_g = $this->cur_g;
        if (!$cur_g->group_id)
            return;

        $g2 = HD::get($this->all_groups, $cur_g->group_id);
        if (!$g2) {
            self::clear_g($cur_g->group_id, $cur_g);
            return;
        }

        if ($g2->mtime != $cur_g->mtime)
            $cur_g->changed = true;

        $cur_g->name = $g2->name;
        $cur_g->total = $g2->total;
        $cur_g->status = $g2->status;
        $cur_g->mtime = $g2->mtime;
        $cur_g->f_by_path = $g2->f_by_path;
    }

    public static function load_group_params($group_id)
    {
        $path = self::get_torrent_state_path($group_id, -1);
        if (!is_file($path))
            return null;

        $g = json_decode(file_get_contents($path));
        return HD::get($g, 'params');
    }

    public static function load_state_for_group($group_id, &$g)
    {
        $path = self::get_torrent_state_path($group_id, -1);
        return self::load_state_from_path($group_id, $path, $g);
    }

    private static function load_state_from_path($group_id, $path, &$g)
    {
        if (!is_file($path))
            return self::clear_g($group_id, $g);

        $mtime = filemtime($path);
        if ($mtime === false)
            $mtime = -1;

        if ($group_id == $g->group_id && $mtime == $g->mtime)
            return false;

        $g->group_id = $group_id;
        $g->name = '?';
        $g->total = 0;
        $g->status = '';
        $g->mtime = $mtime;
        $g->f_by_path = null;
        $state = json_decode(file_get_contents($path));
        if ($state) {
            $g->name = $state->name;
            $g->status = HD::get($state, 'status', 'N');
            foreach ($state->files as $f) {
                $status = HD::get($f, 'status', 'N');
                if ($status != 'D')
                    $g->f_by_path[$f->path] = $f;
            }

            if (isset($state->total))
                $g->total = $state->total;

            if (!self::group_is_valid($g)) {
                hd_print("W: group is invalid: $group_id");
                self::clear_g($group_id, $g);
            }
        }

        $g->changed = true;
        return true;
    }

    public function reload_state($group_id)
    {
        $path = self::get_torrent_state_path($group_id, -1);
        return self::load_state_from_path($group_id, $path, $this->cur_g);
    }

    public function reload_state_tracking_changes($group_id)
    {
        $this->reload_state($group_id);
        $changed = $this->cur_g->changed;
        $this->cur_g->changed = false;
        return $changed;
    }

    public static function get_status($g, $file_path=null)
    {
        if (!$g)
            return '';

        if (!isset($file_path))
            return HD::get($g, 'status', 'N');

        $f = HD::get($g->f_by_path, $file_path);
        if (!$f)
            return '';
        return HD::get($f, 'status', 'N');
    }

    ///////////////////////////////////////////////////////////////////////

    public function all_state_reset() {
        $this->all_groups = array();
        $this->all_groups_loaded = false;
    }

    public function all_state_reload() {
        $this->all_state_reset();
        $this->all_state_load();
    }

    public function all_state_ensure_loaded() {
        if (!$this->all_groups_loaded)
            $this->all_state_load();
    }

    public function all_state_load()
    {
        $dirs = array(self::BASE_TMP_DIR_PATH, self::BASE_DIR_PATH);

        $changed = false;
        $gidset = array();
        foreach ($dirs as $dir)
        {
            // Missing before dl_manager first runs.
            $fnames = @scandir($dir);
            if (!is_array($fnames))
                continue;
            foreach ($fnames as $fname)
            {
                if ($fname[0] == '.')
                    continue;

                $len = strlen($fname);
                if ($len < 6 || substr($fname, $len - 5) != '.json')
                    continue;

                $group_id = substr($fname, 0, $len - 5);

                if (isset($gidset[$group_id]))
                    continue;
                $gidset[$group_id] = 1;

                $path = "$dir/$fname";

                $group_is_new = false;
                if (!isset($this->all_groups[$group_id]))
                {
                    $this->all_groups[$group_id] = self::st_initializer();
                    $group_is_new = true;
                }

                $res = self::load_state_from_path(
                    $group_id, $path, $this->all_groups[$group_id]);

                $g = $this->all_groups[$group_id];
                if (!self::group_is_valid($g)) {
                    unset($this->all_groups[$group_id]);
                    continue;
                }

                if ($group_is_new)
                    hd_print("I: group appeared: $group_id");

                if ($res)
                {
                    if (!$group_is_new)
                        hd_print("I: group changed: $group_id");
                    $changed = true;
                }
            }
        }

        foreach ($this->all_groups as $group_id => $g)
        {
            if (isset($gidset[$group_id]))
                continue;

            hd_print("I: group disappeared: $group_id");
            unset($this->all_groups[$group_id]);
            $changed = true;
        }

        if ($changed)
        {
            uasort($this->all_groups, 'DlState::cmp_groups');

            hd_print("D: groups after reload: ".count($this->all_groups));
        }

        return $changed;
    }

    private static function group_is_valid($g)
    {
        return $g && $g->mtime > 0 && $g->total > 0 && $g->f_by_path;
    }

    public static function cmp_groups($g1, $g2)
    {
        // TODO
        return strcmp($g1->group_id, $g2->group_id);
    }

    ///////////////////////////////////////////////////////////////////////

    private static function api_call_no_args($cmd_type) {
        $id = (string) microtime(true) . ".$cmd_type.in";
        $input_path = "/tmp/run/dl_input/$id";
        hd_print("Send: $cmd_type()");

        $in = array('cmd_type' => $cmd_type);
        HD::write_to_file_using_tmp($input_path, json_encode($in));
    }

    public static function start_ts() {
        self::api_call_no_args('start_ts');
    }

    public static function change_dl_folder() {
        self::api_call_no_args('change_dl_folder');
    }

    public static function test_dl_folder() {
        self::api_call_no_args('test_dl_folder');
    }

    public static function add_download_file(&$files,
        $path, $url, $size_mb)
    {
        $files[] = array(
            'path' => $path, 'url' => $url, 'size_mb' => $size_mb);
    }

    public static function add_download(
        $group_id, $group_name, $group_total, $ext_ts, $files, $params=null)
    {
        $id = md5($group_id);
        $id = (string) microtime(true) . "." . $id . ".in";
        $input_path = "/tmp/run/dl_input/$id";
        hd_print("Download: ext_ts '$ext_ts', input_path $input_path");

        foreach ($files as &$f) {
            $f['ext_ts'] = $ext_ts;
        }

        $in = array(
            'cmd_type' => 'add',
            'id' => $group_id,
            'name' => $group_name,
            'total' => $group_total,
            'files' => $files);
        if ($params)
            $in['params'] = $params;

        HD::write_to_file_using_tmp($input_path, json_encode($in));
    }

    private static function do_handle_download_op($cmd_type,
        $group_id, $file_paths=null)
    {
        $gstr = md5($group_id);
        $id = (string) microtime(true) . ".$cmd_type.$gstr.in";
        $input_path = "/tmp/run/dl_input/$id";

        $in = array(
            'cmd_type' => $cmd_type,
            'group_id' => $group_id);
        if ($file_paths)
            $in['file_paths'] = $file_paths;

        HD::write_to_file_using_tmp($input_path, json_encode($in));
    }

    public static function remove_download($group_id, $file_paths=null) {
        return self::do_handle_download_op('remove',
            $group_id, $file_paths);
    }
    public static function pause_download($group_id, $file_paths=null) {
        return self::do_handle_download_op('pause',
            $group_id, $file_paths);
    }
    public static function resume_download($group_id, $file_paths=null) {
        return self::do_handle_download_op('resume',
            $group_id, $file_paths);
    }
}

?>
