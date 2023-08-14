<?php

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
                                $focus = null, $bg = null,
                                $header_enabled = false, $single_list_navigation = false,
                                $initial_focus_header = -1,
                                $initial_focus_item_id = null, $initial_focus_row_id = null,
                                $hfactor = 1.0, $vfactor = 1.0, $vgravity = 0.0, $vend_min_offset = 0)
    {
        if (!$focus)
            $focus = self::focus();
        $arr = array(
            PluginRowsPane::rows => $rows,
            PluginRowsPane::focus => $focus,
            PluginRowsPane::bg => $bg,
            PluginRowsPane::header_enabled => $header_enabled,
            PluginRowsPane::initial_focus_header => $initial_focus_header,
            PluginRowsPane::initial_focus_item_id => $initial_focus_item_id,
            PluginRowsPane::initial_focus_row_id => $initial_focus_row_id,
            PluginRowsPane::horizontal_focus_freedom_factor => $hfactor,
            PluginRowsPane::vertical_focus_freedom_factor => $vfactor,
            PluginRowsPane::vertical_focus_gravity => $vgravity,
            PluginRowsPane::vertical_focus_end_min_offset => $vend_min_offset,
        );
        if (defined('PluginRowsPane::single_list_navigation'))
            $arr[PluginRowsPane::single_list_navigation] = $single_list_navigation;

        return $arr;
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
                                      $title = null, $width = -1, $height = -1,
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
                PluginGCompsRow::ui_state => $ui_state,
                PluginGCompsRow::width => $width,
            ));
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
                                     $group_id = null, $width = null, $height = null,
                                     $color = null, $font_size = null,
                                     $left = null, $dy = null, $active_dy = null,
                                     $fade_enabled = false, $fade_color = null, $lite_fade_color = null)
    {
        $arr = array(
            PluginRow::type => PLUGIN_ROW_TYPE_TITLE,
            PluginRow::id => $id,
            PluginRow::group_id => $group_id,
            PluginRow::height => $height,
            PluginRow::inactive_height => 0,
            PluginRow::data => array(
                PluginTitleRow::caption => $caption,
                PluginTitleRow::color => GComps_Factory::rgba_to_argb($color),
                PluginTitleRow::font_size => $font_size,
                PluginTitleRow::left => $left,
                PluginTitleRow::dy => $dy,
                PluginTitleRow::active_dy => $active_dy,
                PluginTitleRow::width => $width,
                PluginTitleRow::fade_enabled => $fade_enabled,
                PluginTitleRow::fade_color => GComps_Factory::rgba_to_argb($fade_color),
                PluginTitleRow::lite_fade_color => GComps_Factory::rgba_to_argb($lite_fade_color),
            ));

        if (defined('PluginTitleRow::fade_enabled'))
            $arr[PluginTitleRow::fade_enabled] = $fade_enabled;
        if (defined('PluginTitleRow::fade_color'))
            $arr[PluginTitleRow::fade_color] = GComps_Factory::rgba_to_argb($fade_color);
        if (defined('PluginTitleRow::lite_fade_color'))
            $arr[PluginTitleRow::lite_fade_color] = GComps_Factory::rgba_to_argb($lite_fade_color);

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

    public static function regular_row($id, $items,
                                       $params_template_id = null, $params = null, $title = null,
                                       $group_id = null, $width = null, $height = null, $inactive_height = null,
                                       $left_padding = null, $inactive_left_padding = null, $right_padding = null,
                                       $hide_captions = null, $hide_icons = null,
                                       $fade_enabled = null, $focusable = null,
                                       $show_all_action = null,
                                       $fade_icon_mix_color = null,
                                       $fade_icon_mix_alpha = null,
                                       $lite_fade_icon_mix_alpha = null,
                                       $fade_caption_color = null)
    {
        $data = array(PluginRegularRow::items => $items);

        $data[PluginRegularRow::item_params_template_id] = $params_template_id;
        $data[PluginRegularRow::item_params] = $params;
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

        $arr = array(
            PluginRow::type => PLUGIN_ROW_TYPE_REGULAR,
            PluginRow::id => $id,
            PluginRow::data => $data
        );

        $arr[PluginRow::title] = $title;
        $arr[PluginRow::group_id] = $group_id;
        $arr[PluginRow::height] = $height;
        $arr[PluginRow::inactive_height] = $inactive_height;
        $arr[PluginRow::focusable] = $focusable;
        $arr[PluginRow::show_all_action] = $show_all_action;

        return $arr;
    }

    /**
     * @param array $items
     * @param string $id
     * @param string $icon_url
     * @param string $caption
     * @param array $stickers
     */
    public static function add_regular_item(&$items, $id, $icon_url, $caption = null, $stickers = null)
    {
        $arr = array(
            PluginRegularItem::id => $id,
            PluginRegularItem::icon_url => $icon_url
        );

        if (isset($caption))
            $arr[PluginRegularItem::caption] = $caption;
        if (isset($stickers))
            $arr[PluginRegularItem::stickers] = $stickers;

        $items[] = $arr;
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
        $arr = array(
            PluginRegularItemVariableParams::width => $width,
            PluginRegularItemVariableParams::height => $height
        );

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
        $arr = array(PluginRegularItemParams::def => $def);

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
     * @param array|null $stickers
     * @param string $icon_url
     * @param array $rect
     * @return void
     */
    public static function add_regular_sticker_image(&$stickers, $icon_url, $rect)
    {
        $stickers[] = array(
            PluginRegularSticker::r => $rect,
            PluginRegularSticker::icon_url => $icon_url
        );
    }

    /**
     * @param array $stickers
     * @param string $text
     * @param array $rect
     * @return void
     */
    public static function add_regular_sticker_text(&$stickers, $text, $rect)
    {
        $stickers[] = array(
            PluginRegularSticker::r => $rect,
            PluginRegularSticker::text => $text
        );
    }

    /**
     * @param array|null $stickers
     * @param string $color # RGBA format
     * @param array $rect
     * @return void
     */
    public static function add_regular_sticker_rect(&$stickers, $color, $rect)
    {
        $stickers[] = array(
            PluginRegularSticker::r => $rect,
            PluginRegularSticker::color => GComps_Factory::rgba_to_argb($color)
        );
    }
}
