<?php

if (!defined("HD::INT_MIN")) {
    require_once 'dunelib/utils.php';
}

class DlUi
{
    public static function to_float_mb($v)
    {
        $len = strlen($v);
        if ($len <= 6)
            return $v <= 0 ? 0 : $v / 1048576.0;
        return substr($v, 0, $len - 6) / 1.048576 +
            substr($v, $len - 6) / 1048576.0;
    }
    
    public static function to_progress($pos, $len)
    {
        if ($len <= 1 || $pos <= 0)
            return 0;

        $pos = self::to_float_mb($pos);
        $len = self::to_float_mb($len);
        if ($len <= 1 || $pos < 0)
            return 0;
        if ($pos / $len >= 0.99)
            return '99.9';
        return sprintf("%.1f", $pos / $len * 100.0);
    }

    // NOTE: here f->pos and f->len can exceed integer limits, so we have
    // to workaround.
    public static function make_progress($f)
    {
        $status = HD::get($f, 'status', 'N');
        if ($status == 'O')
            return '100';

        $len = isset($f->len) ? (string) $f->len : '0';
        if ($status == 'N')
            $pos = isset($f->resume_pos) ? (string) $f->resume_pos : '0';
        else
            $pos = isset($f->pos) ? (string) $f->pos : '0';

        return self::to_progress($pos, $len);
    }

    public static function add_file_eitems(&$eitems, $f)
    {
        if (!$f)
            return;

        self::eitems_ensure_gap($eitems, 10);

        $status = HD::get($f, 'status', 'N');

        $arr = null;
        if ($status == 'O') {
            $arr = self::watched_eitem();
        } else if ($status != 'd' && $status != 'D') {
            $progress = self::make_progress($f);
            $text = "[$progress%]";
            $color = $status == 'E' ? 28 :
                (in_array($status, array('P', 'p')) ? 38 :
                 ($status == 'L' ? 34 : 8));
            $arr = array('type' => 'text', 'color' => $color,
                'size' => 'small', 'text' => "$text");

            $alt = $status == 'E' ? 'ERR' :
                ($status == 'p' || $status == 'P' ? 'PAUSE' : null);
            if ($alt)
                $arr['alt'] = $alt;
        }
        if ($arr) {
            array_unshift($eitems, $arr);
            array_unshift($eitems, 
                array('type' => 'gap', 'width' => 20));
        }

        $speed = HD::get($f, 'speed', 0);
        self::eitems_add_speed($eitems, $status, $speed);
    }

    public static function add_group_eitems(&$eitems, $gstat)
    {
        if (!$gstat)
            return;

        $status = HD::get($gstat, 'status', 'N');

        $arr = null;
        if ($status == 'O') {
            $arr = self::watched_eitem();
        } else if ($status != 'd') {
            $text = "[$gstat->progress%]";
            $color = $status == 'E' ? 28 :
                (in_array($status, array('P', 'p')) ? 38 :
                 ($status == 'L' ? 34 : 8));
            $arr = array('type' => 'text', 'color' => $color,
                'size' => 'small', 'text' => "$text");

            $alt = $status == 'E' ? 'ERR' :
                ($status == 'p' || $status == 'P' ? 'PAUSE' : null);
            if ($alt)
                $arr['alt'] = $alt;
        }
        if ($arr) {
            self::eitems_ensure_gap($eitems, 10);

            array_unshift($eitems, $arr);
            array_unshift($eitems, 
                array('type' => 'gap', 'width' => 20));
        }
        else
            return;

        $prefix = $gstat->files_str ? "$gstat->files_str:" : null;

        $with_speed = self::eitems_add_speed($eitems,
            $status, $gstat->speed, $prefix);

        if ($prefix && !$with_speed)
        {
            $color = 39;
            $arr = array('type' => 'text', 'color' => $color,
                'size' => 'small', 'text' => $prefix);

            array_unshift($eitems, $arr);
            array_unshift($eitems, 
                array('type' => 'gap', 'width' => 20));
        }
    }

    private static function eitems_ensure_gap(&$eitems, $width)
    {
        if (!$eitems)
            return;

        $left = &$eitems[0];
        if ($left['type'] == 'gap') {
            $left['width'] = $width;
        } else {
            array_unshift($eitems, 
                array('type' => 'gap', 'width' => $width));
        }
    }

    private static function watched_eitem()
    {
        return array('type' => 'icon',
            'url' => 'plugin_file://%shell_ext%/icons/watched.png',
            'width' => 41, 'height' => 31,
            'alt' => '100%');
    }

    private static function eitems_add_speed(&$eitems,
        $status, $speed, $prefix=null)
    {
        if (($status == 'L' || $status == 'p') && $speed >= 1024)
        {
            $kb = round($speed / 1024);
            $mb = $kb / 1024;
            if ($mb >= 1)
                $text = sprintf("%.1f MB/s", $mb);
            else
                $text = (int) $kb . " KB/s";

            if ($prefix)
                $text = "$prefix $text";

            $color = 39;
            $arr = array('type' => 'text', 'color' => $color,
                'size' => 'small', 'text' => "$text");

            array_unshift($eitems, $arr);
            array_unshift($eitems, 
                array('type' => 'gap', 'width' => 20));
            return true;
        }
        return false;
    }
}

?>
