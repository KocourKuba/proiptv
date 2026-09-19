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

class GComp_Geom
{
    /**
     * @param int $w
     * @param int $h
     * @return array
     */
    public static function top($w = -1, $h = -1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_TOP);
    }

    /**
     * @param int $w
     * @param int $h
     * @param mixed|null $role
     * @param array|null $align_def
     * @return array
     */
    public static function geom($w, $h, $role = null, $align_def = null)
    {
        $arr = array(GCompGeometryDef::w => $w, GCompGeometryDef::h => $h);
        if ($role)
            $arr[GCompGeometryDef::role] = $role;
        if ($align_def)
            $arr[GCompGeometryDef::align_def] = $align_def;
        return $arr;
    }

    /**
     * @param int $w
     * @param int $h
     * @return array
     */
    public static function bottom($w = -1, $h = -1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_BOTTOM);
    }

    /**
     * @param int $w
     * @param int $h
     * @return array
     */
    public static function left($w = -1, $h = -1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_LEFT);
    }

    /**
     * @param int $w
     * @param int $h
     * @return array
     */
    public static function right($w = -1, $h = -1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_RIGHT);
    }

    /**
     * @param int $w
     * @param int $h
     * @return array
     */
    public static function center($w = -1, $h = -1)
    {
        return self::geom($w, $h, GCOMP_LAYOUT_CENTER);
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @param string|null $base_id
     * @return array
     */
    public static function place_center($w = -1, $h = -1, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_CENTER, VALIGN_CENTER, HALIGN_CENTER, VALIGN_CENTER, false, $base_id));
    }

    /**
     * @param int $x
     * @param int $y
     * @param bool $use_base_width
     * @param bool $use_base_height
     * @param mixed $halign
     * @param mixed $valign
     * @param mixed $base_halign
     * @param mixed $base_valign
     * @param bool $base_to_prev
     * @param string|null $base_id
     * @return array
     */
    public static function align($x = 0, $y = 0,
                                 $use_base_width = false, $use_base_height = false,
                                 $halign = HALIGN_LEFT, $valign = VALIGN_TOP, $base_halign = HALIGN_LEFT, $base_valign = VALIGN_TOP,
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

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @param string|null $base_id
     * @return array
     */
    public static function place_top_left($w = -1, $h = -1, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_LEFT, VALIGN_TOP, HALIGN_LEFT, VALIGN_TOP, false, $base_id));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @param string|null $base_id
     * @return array
     */
    public static function place_top_center($w = -1, $h = -1, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_CENTER, VALIGN_TOP, HALIGN_CENTER, VALIGN_TOP, false, $base_id));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @param string|null $base_id
     * @return array
     */
    public static function place_top_right($w = -1, $h = -1, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_RIGHT, VALIGN_TOP, HALIGN_RIGHT, VALIGN_TOP, false, $base_id));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @param string|null $base_id
     * @return array
     */
    public static function place_left_center($w = -1, $h = -1, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_LEFT, VALIGN_CENTER, HALIGN_LEFT, VALIGN_CENTER, false, $base_id));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_top_left_by_center($w, $h, $x, $y)
    {
        return self::geom($w, $h, null, self::align($x, $y, false, false, HALIGN_LEFT, VALIGN_CENTER));
    }

    /**
     * @param int $w_diff
     * @param int $h
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_top_left_same_width($w_diff = 0, $h = -1, $x = 0, $y = 0)
    {
        return self::geom($w_diff, $h, null, self::align($x, $y, true));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @param string|null $base_id
     * @return array
     */
    public static function place_bottom_left($w = -1, $h = -1, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_LEFT, VALIGN_BOTTOM, HALIGN_LEFT, VALIGN_BOTTOM,
                false, $base_id));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @param string|null $base_id
     * @return array
     */
    public static function place_bottom_right($w = -1, $h = -1, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_RIGHT, VALIGN_BOTTOM, HALIGN_RIGHT, VALIGN_BOTTOM,
                false, $base_id));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @param string|null $base_id
     * @return array
     */
    public static function place_bottom_center($w = -1, $h = -1, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_CENTER, VALIGN_BOTTOM, HALIGN_CENTER, VALIGN_BOTTOM,
                false, $base_id));
    }

    /**
     * @param int $w_diff
     * @param int $h_diff
     * @param int $x
     * @param int $y
     * @param string|null $base_id
     * @return array
     */
    public static function place_same_size($w_diff = 0, $h_diff = 0, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w_diff, $h_diff, null,
            self::align($x, $y, true, true,
                null, null, null, null, false, $base_id));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_below_left($w = -1, $h = -1, $x = 0, $y = 0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_LEFT, VALIGN_TOP, HALIGN_LEFT, VALIGN_BOTTOM, true));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_below_right($w = -1, $h = -1, $x = 0, $y = 0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_RIGHT, VALIGN_TOP, HALIGN_RIGHT, VALIGN_BOTTOM, true));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_below_center($w = -1, $h = -1, $x = 0, $y = 0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_CENTER, VALIGN_TOP, HALIGN_CENTER, VALIGN_BOTTOM, true));
    }

    /**
     * @param int $w_diff
     * @param int $h
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_below_left_same_width($w_diff = 0, $h = -1, $x = 0, $y = 0)
    {
        return self::geom($w_diff, $h, null,
            self::align($x, $y, true, false,
                HALIGN_LEFT, VALIGN_TOP, HALIGN_LEFT, VALIGN_BOTTOM, true));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_next_right_align_top($w = -1, $h = -1, $x = 0, $y = 0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_LEFT, VALIGN_TOP, HALIGN_RIGHT, VALIGN_TOP, true));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_next_right_align_center($w = -1, $h = -1, $x = 0, $y = 0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_LEFT, VALIGN_CENTER, HALIGN_RIGHT, VALIGN_CENTER, true));
    }

    /**
     * @param int $w
     * @param int $h_diff
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_next_right_same_height($w = -1, $h_diff = -1, $x = 0, $y = 0)
    {
        return self::geom($w, $h_diff, null,
            self::align($x, $y, false, true,
                HALIGN_LEFT, VALIGN_CENTER, HALIGN_RIGHT, VALIGN_CENTER, true));
    }

    /**
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @return array
     */
    public static function place_next_left_align_top($w = -1, $h = -1, $x = 0, $y = 0)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_RIGHT, VALIGN_TOP, HALIGN_LEFT, VALIGN_TOP, true));
    }
}
