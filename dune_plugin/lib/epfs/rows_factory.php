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

class Rows_Factory
{
    /**
     * @param array $rows
     * @param array $focus # GCompFocusDef
     * @param string $bg
     * @param bool $header_enabled
     * @param bool $single_list_navigation
     * @param int $initial_focus_header
     * @param string $initial_focus_item_id
     * @param string $initial_focus_row_id
     * @param double $hfactor
     * @param double $vfactor
     * @param double $vgravity
     * @param int $vend_min_offset
     * @return array
     */
    public static function pane($rows,
                                $headers = null,
                                $focus = null,
                                $bg = null,
                                $header_enabled = false,
                                $single_list_navigation = false,
                                $initial_focus_header = -1,
                                $initial_focus_item_id = null,
                                $initial_focus_row_id = null,
                                $hfactor = 1.0, $vfactor = 1.0, $vgravity = 0.0, $vend_min_offset = 0)
    {
        if (!$focus) {
            $focus = self::focus();
        }

        $arr[PluginRowsPane::rows] = $rows;
        $arr[PluginRowsPane::focus] = $focus;
        $arr[PluginRowsPane::header_enabled] = $header_enabled;
        $arr[PluginRowsPane::initial_focus_header] = $initial_focus_header;
        $arr[PluginRowsPane::horizontal_focus_freedom_factor] = $hfactor;
        $arr[PluginRowsPane::vertical_focus_freedom_factor] = $vfactor;
        $arr[PluginRowsPane::vertical_focus_gravity] = $vgravity;
        $arr[PluginRowsPane::vertical_focus_end_min_offset] = $vend_min_offset;
        $arr[PluginRowsPane::single_list_navigation] = $single_list_navigation;
        if ($bg)
            $arr[PluginRowsPane::bg] = $bg;
        //if ($initial_focus_item_id)
            $arr[PluginRowsPane::initial_focus_item_id] = $initial_focus_item_id;
        //if ($initial_focus_row_id)
            $arr[PluginRowsPane::initial_focus_row_id] = $initial_focus_row_id;

        if (!empty($headers)) {
            $arr[PluginRowsPane::headers] = $headers;
        }

        return $arr;
    }

    /**
     * @param string $focus_type
     * @param string $focus2_type
     * @return array
     */
    public static function focus($focus_type = GCOMP_FOCUS_SYSTEM, $focus2_type = GCOMP_FOCUS_NONE)
    {
        return array(
            GCompFocusDef::type => $focus_type,
            GCompFocusDef::type2 => $focus2_type
        );
    }

    /**
     * @param array $pane
     * @param int $w
     * @param int $h
     * @param int $x
     * @param int $y
     * @param int $y2
     * @param int $min_row_index_for_y2
     * @param int $info_w
     * @param int $info_h
     * @param int $info_x
     * @param int $info_y
     * @param int $vod_w
     * @param int $vod_h
     * @return void
     */
    public static function pane_set_geometry(&$pane, $w, $h, $x, $y,
                                             $y2 = 0, $min_row_index_for_y2 = 0,
                                             $info_w = 0, $info_h = 0, $info_x = 0, $info_y = 0,
                                             $vod_w = 0, $vod_h = 0)
    {
        $pane[PluginRowsPane::screen_r] = array('w' => $w, 'h' => $h, 'x' => $x, 'y' => $y);
        $pane[PluginRowsPane::screen_y2] = $y2;
        $pane[PluginRowsPane::min_row_index_for_y2] = $min_row_index_for_y2;
        $pane[PluginRowsPane::info_r] = array('w' => $info_w, 'h' => $info_h, 'x' => $info_x, 'y' => $info_y);
        $pane[PluginRowsPane::vod_r] = array('w' => $vod_w, 'h' => $vod_h, 'x' => $w - $vod_w, 'y' => 0);
    }

    public static function add_header(&$headers, $id, $title, $first_in_cluster = false)
    {
        $arr[PluginRowsHeader::id] = $id;
        $arr[PluginRowsHeader::title] = $title;
        if ($first_in_cluster) {
            $arr[PluginRowsHeader::first_in_cluster] = true;
        }

        $headers[] = $arr;
    }

    /**
     * @param int $height
     * @param int $inactive_height
     * @return array
     */
    public static function vgap_row($height, $inactive_height = -1)
    {
        return array(
            PluginRow::type => PLUGIN_ROW_TYPE_VGAP,
            PluginRow::height => $height,
            PluginRow::inactive_height => $inactive_height,
        );
    }

    /**
     * @param string $id
     * @param array $gcomp_defs
     * @param string $title
     * @param int $width
     * @param int $height
     * @param int $inactive_height
     * @param string $ui_state
     * @return array
     */
    public static function gcomps_row($id, $gcomp_defs,
                                      $title = '', $width = -1, $height = -1,
                                      $inactive_height = -1, $ui_state = null)
    {
        return array(
            PluginRow::type => PLUGIN_ROW_TYPE_GCOMPS,
            PluginRow::id => $id,
            PluginRow::title => $title,
            PluginRow::height => $height,
            PluginRow::inactive_height => $inactive_height,
            PluginRow::data => array(
                PluginGCompsRow::defs => $gcomp_defs,
                PluginGCompsRow::width => $width,
                PluginGCompsRow::ui_state => $ui_state,
            )
        );
    }

    /**
     * @param string $id
     * @param string $caption
     * @param string $group_id
     * @param int $width
     * @param int $height
     * @param string $color # RGBA format
     * @param int $font_size # size in pt
     * @param int $left
     * @param int $dy
     * @param int $active_dy
     * @param bool $fade_enabled
     * @param string $fade_color # RGBA format
     * @param string $lite_fade_color # RGBA format
     * @return array
     */
    public static function title_row($id, $caption,
                                     $group_id = '',
                                     $color = null,
                                     $options = null,
                                     $width = TitleRowsParams::width,
                                     $height = TitleRowsParams::height,
                                     $font_size = TitleRowsParams::font_size,
                                     $left = TitleRowsParams::left_padding,
                                     $dy = 0,
                                     $active_dy = 0,
                                     $fade_enabled = true,
                                     $fade_color = TitleRowsParams::fade_color,
                                     $lite_fade_color = TitleRowsParams::lite_fade_color)
    {
        $data[PluginTitleRow::caption] = $caption;
        $data[PluginTitleRow::color] = GComps_Factory::rgba_to_argb(is_null($color) ? TitleRowsParams::def_caption_color : $color);
        $data[PluginTitleRow::font_size] = $font_size;
        $data[PluginTitleRow::left] = $left;
        $data[PluginTitleRow::dy] = $dy;
        $data[PluginTitleRow::active_dy] = $active_dy;
        $data[PluginTitleRow::width] = $width;
        $data[PluginTitleRow::fade_enabled] = $fade_enabled;
        $data[PluginTitleRow::fade_color] = GComps_Factory::rgba_to_argb($fade_color);
        $data[PluginTitleRow::lite_fade_color] = GComps_Factory::rgba_to_argb($lite_fade_color);

        $arr[PluginRow::type] = PLUGIN_ROW_TYPE_TITLE;
        $arr[PluginRow::id] = $id;
        $arr[PluginRow::group_id] = $group_id;
        $arr[PluginRow::height] = $height;
        $arr[PluginRow::inactive_height] = 0;

        if ($options !== null)
            $arr[PluginRow::options] = $options;

        $arr[PluginRow::data] = $data;

        return $arr;
    }

    /**
     * @param array $pane
     * @param string $id
     * @param array $params
     * @return void
     */
    public static function set_item_params_template(&$pane, $id, $params)
    {
        $pane[PluginRowsPane::regular_item_params_templates][$id] = $params;
    }

    public static function regular_row($id, $items, $params_template_id, $title, $group_id, $header_id,
                                       $show_all_action = null,
                                       $height = 0,
                                       $inactive_height = 0,
                                       $width = RowsParams::full_width,
                                       $left_padding = RowsParams::left_padding,
                                       $inactive_left_padding = RowsParams::inactive_left_padding,
                                       $right_padding = RowsParams::right_padding,
                                       $hide_captions = false,
                                       $hide_icons = false,
                                       $fade_enabled = true,
                                       $focusable = null,
                                       $fade_icon_mix_color = RowsParams::fade_icon_mix_color,
                                       $fade_icon_mix_alpha = RowsParams::fade_icon_mix_alpha,
                                       $lite_fade_icon_mix_alpha = RowsParams::lite_fade_icon_mix_alpha,
                                       $fade_caption_color = RowsParams::fade_caption_color,
                                       $params = null)
    {
        $arr[PluginRow::type] = PLUGIN_ROW_TYPE_REGULAR;
        $arr[PluginRow::id] = $id;
        $arr[PluginRow::title] = $title;
        if ($header_id) {
            $arr[PluginRow::header_id] = $header_id;
        }
        $arr[PluginRow::group_id] = $group_id;
        $arr[PluginRow::height] = $height;
        $arr[PluginRow::inactive_height] = $inactive_height;
        if ($focusable) {
            $arr[PluginRow::focusable] = $focusable;
        }
        if ($show_all_action) {
            $arr[PluginRow::show_all_action] = $show_all_action;
        }

        $data[PluginRegularRow::item_params_template_id] = $params_template_id;
        $data[PluginRegularRow::width] = $width;
        $data[PluginRegularRow::left_padding] = $left_padding;
        $data[PluginRegularRow::inactive_left_padding] = $inactive_left_padding;
        $data[PluginRegularRow::right_padding] = $right_padding;
        $data[PluginRegularRow::hide_captions] = $hide_captions;
        $data[PluginRegularRow::hide_icons] = $hide_icons;
        $data[PluginRegularRow::fade_enabled] = $fade_enabled;
        $data[PluginRegularRow::fade_icon_mix_color] = GComps_Factory::rgba_to_argb($fade_icon_mix_color);
        $data[PluginRegularRow::fade_icon_mix_alpha] = $fade_icon_mix_alpha;
        $data[PluginRegularRow::lite_fade_icon_mix_alpha] = $lite_fade_icon_mix_alpha;
        $data[PluginRegularRow::fade_caption_color] = GComps_Factory::rgba_to_argb($fade_caption_color);
        $data[PluginRegularRow::items] = $items;

        if ($params) {
            $data[PluginRegularRow::item_params] = $params;
        }

        $arr[PluginRow::data] = $data;

        return $arr;
    }

    /**
     * @param string $id
     * @param string $icon_url
     * @param string $caption
     * @param array $stickers
     * @return array
     */
    public static function add_regular_item($id, $icon_url, $caption = null, $stickers = null)
    {
        $arr[PluginRegularItem::id] = $id;
        $arr[PluginRegularItem::icon_url] = $icon_url;

        if (isset($caption))
            $arr[PluginRegularItem::caption] = $caption;
        if (isset($stickers))
            $arr[PluginRegularItem::stickers] = $stickers;

        return $arr;
    }

    /**
     * @param int $width
     * @param int $height
     * @param int $dx
     * @param int $icon_width
     * @param int $icon_height
     * @param int $icon_dy
     * @param int $caption_dy
     * @param string $caption_color # RGBA format
     * @param int $caption_font_size # size in pt
     * @param int $sticker_width
     * @param int $sticker_height
     * @return array
     */
    public static function variable_params($width, $height,
                                           $dx = null, $icon_width = null,
                                           $icon_height = null, $icon_dy = null,
                                           $caption_dy = null, $caption_color = null, $caption_font_size = null,
                                           $sticker_width = null, $sticker_height = null)
    {
        $arr[PluginRegularItemVariableParams::width] = $width;
        $arr[PluginRegularItemVariableParams::height] = $height;

        if (isset($dx))
            $arr[PluginRegularItemVariableParams::dx] = $dx;
        if (isset($icon_width))
            $arr[PluginRegularItemVariableParams::icon_width] = $icon_width;
        if (isset($icon_height))
            $arr[PluginRegularItemVariableParams::icon_height] = $icon_height;
        if (isset($icon_dy))
            $arr[PluginRegularItemVariableParams::icon_dy] = $icon_dy;
        if (isset($caption_dy))
            $arr[PluginRegularItemVariableParams::caption_dy] = $caption_dy;
        if (isset($caption_color))
            $arr[PluginRegularItemVariableParams::caption_color] = GComps_Factory::rgba_to_argb($caption_color);
        if (isset($caption_font_size))
            $arr[PluginRegularItemVariableParams::caption_font_size] = $caption_font_size;
        if (isset($sticker_width))
            $arr[PluginRegularItemVariableParams::sticker_width] = $sticker_width;
        if (isset($sticker_height))
            $arr[PluginRegularItemVariableParams::sticker_height] = $sticker_height;
        return $arr;
    }

    /**
     * @param int $left
     * @param int $top
     * @param int $right
     * @param int $bottom
     * @return array
     */
    public static function margins($left, $top, $right, $bottom)
    {
        return array(
            PluginMargins::left => $left,
            PluginMargins::top => $top,
            PluginMargins::right => $right,
            PluginMargins::bottom => $bottom
        );
    }

    /**
     * @param array $def # PluginRegularItemVariableParams
     * @param array $sel # PluginRegularItemVariableParams
     * @param array $inactive # PluginRegularItemVariableParams
     * @param string $loading_url
     * @param string $load_failed_url
     * @param int $caption_max_num_lines
     * @param int $caption_line_spacing
     * @param array $sel_margins # PluginMargins
     * @return array
     */
    public static function item_params($def,
                                       $sel = null, $inactive = null,
                                       $loading_url = null, $load_failed_url = null,
                                       $caption_max_num_lines = null, $caption_line_spacing = null,
                                       $sel_margins = null)
    {
        $arr[PluginRegularItemParams::def] = $def;

        if (isset($sel))
            $arr[PluginRegularItemParams::sel] = $sel;
        if (isset($inactive))
            $arr[PluginRegularItemParams::inactive] = $inactive;
        if (isset($loading_url))
            $arr[PluginRegularItemParams::loading_url] = $loading_url;
        if (isset($load_failed_url))
            $arr[PluginRegularItemParams::load_failed_url] = $load_failed_url;
        if (isset($caption_max_num_lines))
            $arr[PluginRegularItemParams::caption_max_num_lines] = $caption_max_num_lines;
        if (isset($caption_line_spacing))
            $arr[PluginRegularItemParams::caption_line_spacing] = $caption_line_spacing;
        if (isset($sel_margins))
            $arr[PluginRegularItemParams::sel_margins] = $sel_margins;
        return $arr;
    }

    /**
     * @param int $x
     * @param int $y
     * @param int $w
     * @param int $h
     * @return array
     */
    public static function r($x, $y, $w, $h)
    {
        return array('w' => $w, 'h' => $h, 'x' => $x, 'y' => $y);
    }

    /**
     * @param string $icon_url
     * @param array $rect
     * @return array
     */
    public static function add_regular_sticker_image($icon_url, $rect)
    {
        return array(PluginRegularSticker::r => $rect, PluginRegularSticker::icon_url => $icon_url);
    }

    /**
     * @param string $text
     * @param array $rect
     * @return array
     */
    public static function add_regular_sticker_text($text, $rect)
    {
        return array(PluginRegularSticker::r => $rect, PluginRegularSticker::text => $text);
    }

    /**
     * @param string $color # RGBA format
     * @param array $rect
     * @return array
     */
    public static function add_regular_sticker_rect($color, $rect)
    {
        return array(
            PluginRegularSticker::r => $rect,
            PluginRegularSticker::color => GComps_Factory::rgba_to_argb($color)
        );
    }
}
