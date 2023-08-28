<?php

class GComp_Geom
{
    public static function geom($w, $h, $role=null, $align_def=null)
    {
        $arr = array(GCompGeometryDef::w => $w, GCompGeometryDef::h => $h);
        if ($role)
            $arr[GCompGeometryDef::role] = $role;
        if ($align_def)
            $arr[GCompGeometryDef::align_def] = $align_def;
        return $arr;
    }

    public static function align($x = 0, $y = 0,
        $use_base_width = null, $use_base_height = null,
        $halign = null, $valign = null, $base_halign = null, $base_valign = null,
        $base_to_prev = false, $base_id = null)
    {
        $arr = array();
        if ($x)
            $arr[GCompAlignDef::x] = $x;
        if ($y)
            $arr[GCompAlignDef::y] = $y;
        if ($use_base_width)
            $arr[GCompAlignDef::use_base_width] = $use_base_width;
        if ($use_base_height)
            $arr[GCompAlignDef::use_base_height] = $use_base_height;
        if ($halign)
            $arr[GCompAlignDef::halign] = $halign;
        if ($valign)
            $arr[GCompAlignDef::valign] = $valign;
        if ($base_halign)
            $arr[GCompAlignDef::base_halign] = $base_halign;
        if ($base_valign)
            $arr[GCompAlignDef::base_valign] = $base_valign;
        if ($base_to_prev)
            $arr[GCompAlignDef::base_to_prev] = $base_to_prev;
        if ($base_id)
            $arr[GCompAlignDef::base_id] = $base_id;
        return $arr;
    }

    public static function top($w=-1, $h=-1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_TOP);
    }

    public static function bottom($w=-1, $h=-1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_BOTTOM);
    }

    public static function left($w=-1, $h=-1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_LEFT);
    }

    public static function right($w=-1, $h=-1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_RIGHT);
    }

    public static function center($w=-1, $h=-1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_CENTER);
    }

    public static function place_center($w=-1, $h=-1, $x=0, $y=0, $base_id=null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_CENTER, VALIGN_CENTER, HALIGN_CENTER, VALIGN_CENTER, false, $base_id));
    }

    public static function place_top_left($w=-1, $h=-1, $x=0, $y=0, $base_id=null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                null, null, null, null, false, $base_id));
    }

    public static function place_top_center($w=-1, $h=-1, $x=0, $y=0, $base_id=null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_CENTER, VALIGN_TOP, HALIGN_CENTER, VALIGN_TOP, false, $base_id));
    }

    public static function place_top_right($w=-1, $h=-1, $x=0, $y=0, $base_id=null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_RIGHT, VALIGN_TOP, HALIGN_RIGHT, VALIGN_TOP, false, $base_id));
    }

    public static function place_left_center($w=-1, $h=-1, $x=0, $y=0, $base_id=null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_LEFT, VALIGN_CENTER, HALIGN_LEFT, VALIGN_CENTER, false, $base_id));
    }

    public static function place_top_left_by_center($w, $h, $x, $y)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_LEFT, VALIGN_CENTER, HALIGN_LEFT, VALIGN_TOP));
    }

    public static function place_top_left_same_width($w_diff=0, $h=-1, $x=0, $y=0)
    {
        return self::geom($w_diff, $h, null, self::align($x, $y, true));
    }

    public static function place_bottom_left($w=-1, $h=-1, $x=0, $y=0, $base_id=null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_LEFT, VALIGN_BOTTOM, HALIGN_LEFT, VALIGN_BOTTOM,
                false, $base_id));
    }

    public static function place_bottom_right($w=-1, $h=-1, $x=0, $y=0, $base_id=null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_RIGHT, VALIGN_BOTTOM, HALIGN_RIGHT, VALIGN_BOTTOM,
                false, $base_id));
    }

    public static function place_bottom_center($w=-1, $h=-1, $x=0, $y=0, $base_id=null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_CENTER, VALIGN_BOTTOM, HALIGN_CENTER, VALIGN_BOTTOM,
                false, $base_id));
    }

    public static function place_same_size($w_diff=0, $h_diff=0, $x=0, $y=0, $base_id=null)
    {
        return self::geom($w_diff, $h_diff, null,
            self::align($x, $y, true, true,
                null, null, null, null, false, $base_id));
    }

    ///////////////////////////////////////////////////////////////////////

    public static function place_below_left($w=-1, $h=-1, $x=0, $y=0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_LEFT, VALIGN_TOP, HALIGN_LEFT, VALIGN_BOTTOM, true));
    }

    public static function place_below_right($w=-1, $h=-1, $x=0, $y=0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_RIGHT, VALIGN_TOP, HALIGN_RIGHT, VALIGN_BOTTOM, true));
    }

    public static function place_below_center($w=-1, $h=-1, $x=0, $y=0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_CENTER, VALIGN_TOP, HALIGN_CENTER, VALIGN_BOTTOM, true));
    }

    public static function place_below_left_same_width($w_diff=0, $h=-1, $x=0, $y=0)
    {
        return self::geom($w_diff, $h, null,
            self::align($x, $y, true, null,
                HALIGN_LEFT, VALIGN_TOP, HALIGN_LEFT, VALIGN_BOTTOM, true));
    }

    public static function place_next_right_align_top($w=-1, $h=-1, $x=0, $y=0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_LEFT, VALIGN_TOP, HALIGN_RIGHT, VALIGN_TOP, true));
    }

    public static function place_next_right_align_center($w=-1, $h=-1, $x=0, $y=0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_LEFT, VALIGN_CENTER, HALIGN_RIGHT, VALIGN_CENTER, true));
    }

    public static function place_next_right_same_height($w=-1, $h_diff=-1, $x=0, $y=0)
    {
        return self::geom($w, $h_diff, null,
            self::align($x, $y, null, true,
                HALIGN_LEFT, VALIGN_CENTER, HALIGN_RIGHT, VALIGN_CENTER, true));
    }

    public static function place_next_left_align_top($w=-1, $h=-1, $x=0, $y=0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, null, null,
                HALIGN_RIGHT, VALIGN_TOP, HALIGN_LEFT, VALIGN_TOP, true));
    }
}
