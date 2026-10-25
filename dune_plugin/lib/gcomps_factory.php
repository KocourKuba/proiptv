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

class GComps_Factory
{
    /** convert standard RGBA representation of the color
     * to DUNE ARGB GComps representation
     *
     * @param string $rgba # RGBA color
     * @return string # ARGB color
     */
    public static function rgba_to_argb($rgba)
    {
        if (empty($rgba))
            return $rgba;

        $part = substr($rgba, 1);
        return '#' . substr($part, -2) . substr($part, 0, -2);
    }

    /**
     * @param array $geom # GCompGeometryDef
     * @param array|null $margins # GCompMarginsDef
     * @param string $text
     * @param int $max_num_lines
     * @param string $color # RGBA format
     * @param string $font_size # size in pt
     * @param string|null $id
     * @param array|null $attrs
     * @return array
     */
    public static function label($geom, $margins, $text,
                                 $max_num_lines = 1, $color = "#FFFFE0FF",
                                 $font_size = 36, $id = null, $attrs = null)
    {
        $arr2 = self::getSpecificDefs($attrs, $text, $color, $font_size, $max_num_lines);
        $arr = array(
            GComponentDef::id => $id,
            GComponentDef::geom_def => $geom,
            GComponentDef::kind => GCOMPONENT_TTF_LABEL,
            GComponentDef::specific_def => $arr2,
        );

        if ($margins)
            $arr[GComponentDef::margins_def] = $margins;

        return $arr;
    }

    /**
     * @param array $attrs
     * @param string $text
     * @param string $color # RGBA format
     * @param string $font_size # size in pt
     * @param int $max_num_lines
     * @return array
     */
    protected static function getSpecificDefs($attrs, $text, $color, $font_size, $max_num_lines)
    {
        $line_spacing = isset($attrs['line_spacing']) ? $attrs['line_spacing'] : null;
        $base_font_size = isset($attrs['base_font_size']) ? $attrs['base_font_size'] : null;
        $halign = isset($attrs['halign']) ? $attrs['halign'] : null;

        $arr2 = array(
            GCompTtfLabelDef::text => $text,
            GCompTtfLabelDef::text_color => self::rgba_to_argb($color),
            GCompTtfLabelDef::font_size => $font_size,
        );
        if (isset($max_num_lines) && $max_num_lines !== 1)
            $arr2[GCompTtfLabelDef::max_num_lines] = $max_num_lines;
        if (isset($line_spacing))
            $arr2[GCompTtfLabelDef::line_spacing] = $line_spacing;
        if ($base_font_size)
            $arr2[GCompTtfLabelDef::base_font_size] = $base_font_size;
        if ($halign)
            $arr2[GCompTtfLabelDef::halign] = $halign;
        return $arr2;
    }

    /**
     * @param array $geom # GCompGeometryDef
     * @param array|null $margins # GCompMarginsDef
     * @param string $text
     * @param int $max_num_lines
     * @param string $color # RGBA format
     * @param string $font_size # size in pt
     * @param string|null $id
     * @param array|null $attrs
     * @return array
     */
    public static function label_v2($geom, $margins, $text,
                                    $max_num_lines = 1, $color = "#FFFFE0FF",
                                    $font_size = 36, $id = null, $attrs = null)
    {
        $arr2 = self::getSpecificDefs($attrs, $text, $color, $font_size, $max_num_lines);

        $arr = array(
            GComponentDef::id => $id,
            GComponentDef::geom_def => $geom,
            GComponentDef::options => GCOMP_OPT_TTF_LAYOUT_FIX,
            GComponentDef::kind => GCOMPONENT_TTF_LABEL,
            GComponentDef::specific_def => $arr2,
        );
        if ($margins)
            $arr[GComponentDef::margins_def] = $margins;
        return $arr;
    }

    /**
     * @param array $geom # GCompGeometryDef
     * @param array|null $margins # GCompMarginsDef
     * @param string $color # RGBA format
     * @return array
     */
    public static function get_rect_def($geom, $margins, $color)
    {
        return array(
            GComponentDef::geom_def => $geom,
            GComponentDef::margins_def => $margins,
            GComponentDef::kind => GCOMPONENT_RECT,
            GComponentDef::specific_def => array(
                GCompRectDef::color => self::rgba_to_argb($color),
            ),
        );
    }

    /**
     * @param array $geom # GCompGeometryDef
     * @param array|null $margins # GCompMarginsDef
     * @param string $url
     * @param bool $keep_aspect_ratio
     * @param bool $upscale_enabled
     * @param string|null $not_loaded_url
     * @param string|null $load_failed_url
     * @param string|null $low_quality_url
     * @param int $alpha # 0-255
     * @param int $mix_alpha # 0-255
     * @param int $mix_color # 0-255
     * @return array
     */
    public static function get_image_def($geom, $margins, $url,
                                         $keep_aspect_ratio = false, $upscale_enabled = true,
                                         $not_loaded_url = null, $load_failed_url = null, $low_quality_url = null,
                                         $alpha = null, $mix_alpha = null, $mix_color = null)
    {
        return array(
            GComponentDef::geom_def => $geom,
            GComponentDef::margins_def => $margins,
            GComponentDef::kind => GCOMPONENT_IMAGE,
            GComponentDef::specific_def => array(
                GCompImageDef::url => $url,
                GCompImageDef::keep_aspect_ratio => $keep_aspect_ratio,
                GCompImageDef::upscale_enabled => $upscale_enabled,
                GCompImageDef::not_loaded_url => $not_loaded_url,
                GCompImageDef::load_failed_url => $load_failed_url,
                GCompImageDef::low_quality_url => $low_quality_url,
                GCompImageDef::alpha => $alpha,
                GCompImageDef::mix_alpha => $mix_alpha,
                GCompImageDef::mix_color => $mix_color,
            ),
        );
    }

    /**
     * @param array $geom # GCompGeometryDef
     * @param array|null $margins # GCompMarginsDef
     * @param string $scroll_pane_id
     * @param bool $vertical
     * @return array
     */
    public static function get_scrollbar_def($geom, $margins, $scroll_pane_id, $vertical = true)
    {
        return array(
            GComponentDef::geom_def => $geom,
            GComponentDef::margins_def => $margins,
            GComponentDef::kind => GCOMPONENT_SCROLLBAR,
            GComponentDef::specific_def => array(
                GCompScrollbarDef::scroll_pane_id => $scroll_pane_id,
                GCompScrollbarDef::vertical => $vertical,
            ),
        );
    }

    /**
     * @param int $x
     * @param int $y
     * @param string|null $id
     * @return array
     */
    public static function get_view_position_def($x, $y, $id = null)
    {
        return array(
            GCompViewPositionDef::x => $x,
            GCompViewPositionDef::y => $y,
            GCompViewPositionDef::id => $id);
    }

    /**
     * @param string $id
     * @param array $geom # GCompGeometryDef
     * @param array|null $margins # GCompMarginsDef
     * @param array $children # GComponentDefList
     * @param int $options
     * @return array
     */
    public static function get_panel_def($id, $geom, $margins, $children, $options = 0)
    {
        return array(
            GComponentDef::id => $id,
            GComponentDef::geom_def => $geom,
            GComponentDef::margins_def => $margins,
            GComponentDef::options => $options,
            GComponentDef::kind => GCOMPONENT_PANEL,
            GComponentDef::specific_def => array(GCompPanelDef::children => $children),
        );
    }

    /**
     * @param string $id
     * @param array $geom # GCompGeometryDef
     * @param array|null $margins # GCompMarginsDef
     * @param array $children # GComponentDefList
     * @param array|null $view_position # GCompViewPositionDef
     * @return array
     */
    public static function get_vertical_spane_def($id, $geom, $margins, $children, $view_position = null)
    {
        return array(
            GComponentDef::id => $id,
            GComponentDef::geom_def => $geom,
            GComponentDef::margins_def => $margins,
            GComponentDef::kind => GCOMPONENT_SCROLL_PANE,
            GComponentDef::specific_def => array(
                GCompScrollPaneDef::panel => array(GCompPanelDef::children => $children),
                GCompScrollPaneDef::view_use_base_width => true,
                GCompScrollPaneDef::view_position => $view_position,
            ),
        );
    }

    /**
     * @param array $comp_defs
     * @param array|null $ui_state # GCompUiStateDef
     * @param string|null $background_url
     * @param string|null $background_color # RGBA format
     * @param string|null $not_loaded_background_url
     * @param bool $async_loading_background
     * @param int $playback_bg_alpha
     * @param array|null $fit_def # ImageFitDef
     * @param string|null $small_state_text
     * @param bool $opaque_background
     * @return array
     */
    public static function get_window_def($comp_defs,
                                          $ui_state = null,
                                          $background_url = null,
                                          $background_color = null,
                                          $not_loaded_background_url = null,
                                          $async_loading_background = false,
                                          $playback_bg_alpha = -1,
                                          $fit_def = null,
                                          $small_state_text = null,
                                          $opaque_background = false)
    {
        return array(
            GCompWindowDef::background_color => self::rgba_to_argb($background_color),
            GCompWindowDef::background_url => $background_url,
            GCompWindowDef::async_loading_background => $async_loading_background,
            GCompWindowDef::comp_defs => $comp_defs,
            GCompWindowDef::ui_state => $ui_state,
            GCompWindowDef::not_loaded_background_url => $not_loaded_background_url,
            GCompWindowDef::playback_bg_alpha => $playback_bg_alpha,
            GCompWindowDef::background_fit_def => $fit_def,
            GCompWindowDef::small_state_text => $small_state_text,
            GCompWindowDef::opaque_background => $opaque_background,
        );
    }

    /**
     * @param array $def
     * @param string $ref_id
     * @param array|null $geom # GCompGeometryDef
     * @param array|null $props # MY_Properties
     * @return void
     */
    public static function add_extra_var(&$def, $ref_id, $geom, $props = null)
    {
        $def[GComponentDef::extra_variant_defs][] = array(
            GCompVariantDef::ref_id => $ref_id,
            GCompVariantDef::geom_def => $geom,
            GCompVariantDef::props => $props,
        );
    }

    ////////////////////////////////////////////////////////////

    /**
     * @param string $id
     * @param array|null $geom # GCompGeometryDef
     * @param array|null $props # MY_Properties
     * @param array|null $view_position # GCompViewPositionDef
     * @param bool $selected
     * @param array|null $children # GComponentDefList
     * @param array|null $transition # GCompTransition
     * @return array
     */
    public static function get_change_def($id, $geom,
                                          $props = null, $view_position = null,
                                          $selected = false, $children = null,
                                          $transition = GCOMP_TRANSITION_DEFAULT)
    {
        return array(
            ChangeGCompDef::id => $id,
            ChangeGCompDef::geom_def => $geom,
            ChangeGCompDef::props => $props,
            ChangeGCompDef::view_position => $view_position,
            ChangeGCompDef::selected => $selected,
            ChangeGCompDef::children => $children,
            ChangeGCompDef::transition => $transition,
        );
    }
}

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
    public static function place_left_center($w = -1, $h = -1, $x = 0, $y = 0, $base_id = null)
    {
        return self::geom($w, $h, null,
            self::align($x, $y, false, false,
                HALIGN_LEFT, VALIGN_CENTER, HALIGN_LEFT, VALIGN_CENTER, false, $base_id));
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
}
