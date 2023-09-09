<?php

/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code: Brigadir (forum.mydune.ru)
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

require_once 'dune_stb_api.php';
require_once 'control_factory.php';

///////////////////////////////////////////////////////////////////////////////

class Control_Factory_Ext extends Control_Factory
{
    ///////////////////////////////////////////////////////////////////////////

    const DUNE_BASE_SKIN_PATH = '/firmware/skin';

    # Paths to skin cut images
    const scrollbar_inner_txt = 'gui_skin://cut_images/scrollbar_inner/scrollbar_inner.txt';
    const scrollbar_inner_bottom = 'gui_skin://cut_images/scrollbar_inner/scrollbar_inner_bottom.aai';
    const scrollbar_inner_center = 'gui_skin://cut_images/scrollbar_inner/scrollbar_inner_center.aai';
    const scrollbar_inner_top = 'gui_skin://cut_images/scrollbar_inner/scrollbar_inner_top.aai';
    const scrollbar_outer_txt = 'gui_skin://cut_images/scrollbar_outer/scrollbar_outer.txt';
    const scrollbar_outer_bottom = 'gui_skin://cut_images/scrollbar_outer/scrollbar_outer_bottom.aai';
    const scrollbar_outer_center = 'gui_skin://cut_images/scrollbar_outer/scrollbar_outer_center.aai';
    const scrollbar_outer_top = 'gui_skin://cut_images/scrollbar_outer/scrollbar_outer_top.aai';
    const progressbar_inner_txt = 'gui_skin://cut_images/progressbar_inner/progressbar_inner.txt';
    const progressbar_inner_center = 'gui_skin://cut_images/progressbar_inner/progressbar_inner_center.aai';
    const progressbar_inner_left = 'gui_skin://cut_images/progressbar_inner/progressbar_inner_left.aai';
    const progressbar_inner_right = 'gui_skin://cut_images/progressbar_inner/progressbar_inner_right.aai';
    const progressbar_outer_txt = 'gui_skin://cut_images/progressbar_outer/progressbar_outer.txt';
    const progressbar_outer_center = 'gui_skin://cut_images/progressbar_outer/progressbar_outer_center.aai';
    const progressbar_outer_left = 'gui_skin://cut_images/progressbar_outer/progressbar_outer_left.aai';
    const progressbar_outer_right = 'gui_skin://cut_images/progressbar_outer/progressbar_outer_right.aai';
    const sandwich_cover_txt = 'gui_skin://cut_images/sandwich_cover/sandwich_cover.txt';
    const sandwich_cover_left = 'gui_skin://cut_images/sandwich_cover/sandwich_cover_left.aai';
    const sandwich_cover_right = 'gui_skin://cut_images/sandwich_cover/sandwich_cover_right.aai';
    const sandwich_cover_top = 'gui_skin://cut_images/sandwich_cover/sandwich_cover_top.aai';
    const sandwich_cover_bottom = 'gui_skin://cut_images/sandwich_cover/sandwich_cover_bottom.aai';
    const sandwich_cover_top_left = 'gui_skin://cut_images/sandwich_cover/sandwich_cover_top_left.aai';
    const sandwich_cover_top_right = 'gui_skin://cut_images/sandwich_cover/sandwich_cover_top_right.aai';
    const sandwich_cover_bottom_left = 'gui_skin://cut_images/sandwich_cover/sandwich_cover_bottom_left.aai';
    const sandwich_cover_bottom_right = 'gui_skin://cut_images/sandwich_cover/sandwich_cover_bottom_right.aai';
    const sandwich_mask_txt = 'gui_skin://cut_images/sandwich_mask/sandwich_mask.txt';

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @var Control_Factory_Ext
     */
    private static $instance;

    ///////////////////////////////////////////////////////////////////////////

    private $dots;
    private $skin_path;

    # Arrays of cut images manifest
    protected $scrollbar_inner_manifest;
    protected $scrollbar_outer_manifest;
    protected $progressbar_inner_manifest;
    protected $progressbar_outer_manifest;
    protected $sandwich_cover_manifest;
    protected $sandwich_mask_manifest;
    protected $sandwich_cover_center;

    ///////////////////////////////////////////////////////////////////////////

    private function __construct()
    {
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @throws Exception
     */
    public static function init()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Control_Factory_Ext();
        }

        clearstatcache();

        self::$instance->skin_path = get_active_skin_path();

        # Paths to specific dir
        $scrollbar_inner_path = file_exists(str_replace('gui_skin:/', self::$instance->skin_path, self::scrollbar_inner_txt)) ? self::$instance->skin_path : self::DUNE_BASE_SKIN_PATH;
        $scrollbar_outer_path = file_exists(str_replace('gui_skin:/', self::$instance->skin_path, self::scrollbar_outer_txt)) ? self::$instance->skin_path : self::DUNE_BASE_SKIN_PATH;
        $progressbar_inner_path = file_exists(str_replace('gui_skin:/', self::$instance->skin_path, self::progressbar_inner_txt)) ? self::$instance->skin_path : self::DUNE_BASE_SKIN_PATH;
        $progressbar_outer_path = file_exists(str_replace('gui_skin:/', self::$instance->skin_path, self::progressbar_outer_txt)) ? self::$instance->skin_path : self::DUNE_BASE_SKIN_PATH;

        # Arrays of cut image manifest
        self::$instance->scrollbar_inner_manifest = parse_ini_file(str_replace('gui_skin:/', $scrollbar_inner_path, self::scrollbar_inner_txt));
        self::$instance->scrollbar_outer_manifest = parse_ini_file(str_replace('gui_skin:/', $scrollbar_outer_path, self::scrollbar_outer_txt));
        self::$instance->progressbar_inner_manifest = parse_ini_file(str_replace('gui_skin:/', $progressbar_inner_path, self::progressbar_inner_txt));
        self::$instance->progressbar_outer_manifest = parse_ini_file(str_replace('gui_skin:/', $progressbar_outer_path, self::progressbar_outer_txt));
        self::$instance->sandwich_cover_manifest = parse_ini_file(str_replace('gui_skin:/', self::DUNE_BASE_SKIN_PATH, self::sandwich_cover_txt));
        self::$instance->sandwich_mask_manifest = parse_ini_file(str_replace('gui_skin:/', self::DUNE_BASE_SKIN_PATH, self::sandwich_mask_txt));

        $dots_path = get_paved_path(get_temp_path('ex_controls/dots/'));

        if (isset(self::$instance->sandwich_mask_manifest['center_color'])) {
            $center_color = '0x20ffffff';
            self::$instance->sandwich_cover_center = $dots_path . "/$center_color.aai";

            if (!file_exists(self::$instance->sandwich_cover_center)) {
                $argb = str_split($center_color, 2);

                if (false === file_put_contents(self::$instance->sandwich_cover_center, pack("V2C4", 1, 1, hexdec($argb[4]), hexdec($argb[3]), hexdec($argb[2]), hexdec($argb[1])))) {
                    throw new Exception(get_class(self::$instance) . ': Attempt to write to the system drive failed!');
                }
            }
        }

        $indexed_colors_map = array_merge(array('0x5effffff'), func_get_args());

        foreach ($indexed_colors_map as $idx => $argb_color) {
            if (preg_match('/0x[0-9|a-f]{8}$/i', $argb_color)) {
                $argb = str_split($argb_color, 2);
                self::$instance->dots[$idx] = $dots_path . "/$argb_color.aai";

                if (false === file_put_contents(self::$instance->dots[$idx], pack("V2C4", 1, 1, hexdec($argb[4]), hexdec($argb[3]), hexdec($argb[2]), hexdec($argb[1])))) {
                    throw new Exception(get_class(self::$instance) . ': Attempt to write to the system drive failed!');
                }
            } else {
                unset($indexed_colors_map[$idx]);
                hd_debug_print("Warning in " . get_class(self::$instance) . "! Wrong colors map value $argb_color, color index $idx is skipped.");
            }
        }
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @throws Exception
     */
    public static function add_box(&$defs, $height, $viewport_width)
    {
        if (is_null(self::$instance)) {
            self::init();
        }

        $viewport_width = max(300, $viewport_width);

        $defs = self::set_controls($defs, $viewport_width, $height);
    }

    /**
     * @throws Exception
     */
    public static function add_file_tree_view(&$defs, $path, $viewport_width, $max_visible_lines)
    {
        if (is_null(self::$instance)) {
            self::init();
        }

        $viewport_height = 314;
        $viewport_width = max(300, $viewport_width);
        $max_visible_lines = min(12, max(4, $max_visible_lines));
        $defs = self::set_controls($defs, $viewport_width, $viewport_height);
        $path_arr = explode('/', $path);
        $c = count($path_arr);
        $path = '';
        $n = 0;

        foreach ($path_arr as $i => $dir) {
            if (empty($dir)) {
                continue;
            }

            if (empty($path)) {
                self::add_smart_label($defs, null, '<gap width=20/><icon>' . get_image_path('drive.png') . '</icon><text size=small> ' . $dir . '</text>');
            } else {
                if ((($c - $i + (int)!empty($n)) > $max_visible_lines)) {
                    if (empty($n)) {
                        self::add_smart_label($defs, null, '<gap width=50/><icon>' . get_image_path('pass.png') . '</icon><text size=small> </text>');
                        self::add_vgap($defs, -34);
                    }

                    $n++;
                    $path .= DIRECTORY_SEPARATOR . $dir;
                    continue;
                }

                self::add_smart_label($defs, null, '<gap width=' . (20 + 35 * ($i - $n)) . '/><icon>' . get_image_path((($i < $c - 1) ? 'folder.png' : 'file.png')) . '</icon><text size=small>' . $dir . '</text>');
            }

            self::add_vgap($defs, -34);
            $path .= DIRECTORY_SEPARATOR . $dir;
        }

        self::add_vgap($defs, 150);
    }

    /**
     * @throws Exception
     */
    public static function add_group_box(&$defs, &$control_defs, $title, $viewport_width, $dx = 0, $border_color_index = 0, $border_thickness = 3)
    {
        if (is_null(self::$instance)) {
            self::init();
        }

        $height = 0;

        if (!isset(self::$instance->dots[$border_color_index])) {
            $border_color_index = 0;
        }

        $dx = max(0, $dx);
        $viewport_width = max(300, $viewport_width);
        $border_thickness = min(30, max(1, $border_thickness));
        self::add_smart_label($defs, null, (empty($dx) ? '' : '<gap width=' . $dx . '/>') . '<icon width=50 height=' . $border_thickness . '>' . self::$instance->dots[$border_color_index] . '</icon>' . (is_null($title) ? '' : '<gap width=12/><text dy=-23>' . $title . '</text><gap width=12/>') . '<icon width=' . ($viewport_width - $dx) . ' height=' . $border_thickness . '>' . self::$instance->dots[$border_color_index] . '</icon>');
        self::add_vgap($defs, -20);
        $height -= 49;

        foreach ($control_defs as $def) {
            $defs[] = $def;
            $height -= ($def['kind'] === GUI_CONTROL_VGAP) ? $def['specific_def']['vgap'] : 69;
        }

        self::add_smart_label($defs, null, (empty($dx) ? '' : '<gap width=' . $dx . '/>') . '<icon width=' . $border_thickness . ' height=' . (0 - $height - $border_thickness) . ' dy=' . ($height + $border_thickness) . '>' . self::$instance->dots[$border_color_index] . '</icon><icon width=' . ($viewport_width - $border_thickness - $border_thickness - $dx) . ' height=' . $border_thickness . ' dy=' . (0 - $border_thickness) . '>' . self::$instance->dots[$border_color_index] . '</icon><icon width=' . $border_thickness . ' height=' . (0 - $height - $border_thickness) . ' dy=' . ($height + $border_thickness) . '>' . self::$instance->dots[$border_color_index] . '</icon>');
        self::add_vgap($defs, -49);
    }

    /**
     * @throws Exception
     */
    public static function add_progressbar(&$defs, $dx, $width, $pos_percent, $max_percent = 100)
    {
        if (is_null(self::$instance)) {
            self::init();
        }

        self::add_smart_label($defs, null, '<gap width=' . ($dx + self::$instance->progressbar_outer_manifest['left_extent']) . '/><icon>' . self::progressbar_outer_left . '</icon><icon width=' . ($width - self::$instance->progressbar_outer_manifest['left'] - self::$instance->progressbar_outer_manifest['right']) . ' height=' . self::$instance->progressbar_outer_manifest['height'] . '>' . self::progressbar_outer_center . '</icon><icon>' . self::progressbar_outer_right . '</icon>');
        $pos_percent = min($max_percent, max(0, $pos_percent));
        $inner_width = round(($pos_percent * ($width + self::$instance->progressbar_inner_manifest['left_extent'] + self::$instance->progressbar_inner_manifest['right_extent']) / $max_percent));

        if ($inner_width > 0) {
            self::add_vgap($defs, -69 - self::$instance->progressbar_inner_manifest['top_extent']);
            self::add_smart_label($defs, null, '<gap width=' . ($dx - self::$instance->progressbar_outer_manifest['left_extent'] - self::$instance->progressbar_inner_manifest['left_extent']) . '/><icon>' . self::progressbar_inner_left . '</icon><icon width=' . ($inner_width - self::$instance->progressbar_inner_manifest['left'] - self::$instance->progressbar_inner_manifest['right']) . ' height=' . self::$instance->progressbar_inner_manifest['height'] . '>' . self::progressbar_inner_center . '</icon><icon>' . self::progressbar_inner_right . '</icon>');
            self::add_vgap($defs, self::$instance->progressbar_inner_manifest['bottom_extent']);
        }
    }

    /**
     * @throws Exception
     */
    public static function add_scrollbar(&$defs, $viewport_width, $line_height, $num_of_visible_lines, $num_of_lines, $scroll_position)
    {
        if (is_null(self::$instance)) {
            self::init();
        }

        if ($num_of_lines <= $num_of_visible_lines) {
            return;
        }

        $padding_top = 0 - self::$instance->scrollbar_outer_manifest['top_extent'];
        $padding_bottom = 0 - self::$instance->scrollbar_outer_manifest['bottom_extent'];
        $height = $line_height * $num_of_visible_lines;
        $dx = $viewport_width - ((self::$instance->scrollbar_outer_manifest['width'] > self::$instance->scrollbar_inner_manifest['width']) ? self::$instance->scrollbar_outer_manifest['width'] : self::$instance->scrollbar_inner_manifest['width']);
        $gap = $dx - self::$instance->scrollbar_outer_manifest['left_extent'];
        $outer_height = $height - self::$instance->scrollbar_outer_manifest['top'] - self::$instance->scrollbar_outer_manifest['bottom'] + self::$instance->scrollbar_outer_manifest['top_extent'] + self::$instance->scrollbar_outer_manifest['bottom_extent'];

        self::add_vgap($defs, $padding_top - $height);
        self::add_smart_label($defs, null, '<gap width=' . $gap . '/><icon>' . self::scrollbar_outer_top . '</icon>');
        self::add_vgap($defs, self::$instance->scrollbar_outer_manifest['top'] - 69);
        self::add_smart_label($defs, null, '<gap width=' . $gap . '/><icon width=' . self::$instance->scrollbar_outer_manifest['width'] . ' height=' . $outer_height . '>' . self::scrollbar_outer_center . '</icon>');
        self::add_vgap($defs, $outer_height - 69);
        self::add_smart_label($defs, null, '<gap width=' . $gap . '/><icon>' . self::scrollbar_outer_bottom . '</icon>');
        self::add_vgap($defs, self::$instance->scrollbar_outer_manifest['bottom'] + $padding_bottom - 69);

        $padding_top = self::$instance->scrollbar_inner_manifest['top_extent'];
        $padding_bottom = self::$instance->scrollbar_inner_manifest['bottom_extent'];
        $gap = $dx - self::$instance->scrollbar_inner_manifest['left_extent'];
        $inner_height = $height + $padding_top + $padding_bottom - self::$instance->scrollbar_inner_manifest['top'] - self::$instance->scrollbar_inner_manifest['bottom'];
        $slider_height = max($inner_height - ((($num_of_lines - $num_of_visible_lines) * $line_height) / 2), self::$instance->scrollbar_inner_manifest['top'] + self::$instance->scrollbar_inner_manifest['bottom'] + 10);
        $slider_step = ($inner_height - $slider_height) / ($num_of_lines - $num_of_visible_lines);
        $vgap_inner = round(($slider_step * $scroll_position) - $inner_height);

        self::add_vgap($defs, $vgap_inner + $padding_top - self::$instance->scrollbar_inner_manifest['top'] - self::$instance->scrollbar_inner_manifest['bottom']);
        self::add_smart_label($defs, null, '<gap width=' . $gap . '/><icon>' . self::scrollbar_inner_top . '</icon>');
        self::add_vgap($defs, self::$instance->scrollbar_inner_manifest['top'] - 69);
        self::add_smart_label($defs, null, '<gap width=' . $gap . '/><icon width=' . self::$instance->scrollbar_inner_manifest['width'] . ' height=' . $slider_height . '>' . self::scrollbar_inner_center . '</icon>');
        self::add_vgap($defs, $slider_height - 69);
        self::add_smart_label($defs, null, '<gap width=' . $gap . '/><icon>' . self::scrollbar_inner_bottom . '</icon>');
        self::add_vgap($defs, abs($vgap_inner) - $slider_height - 69 - $padding_bottom + self::$instance->scrollbar_inner_manifest['bottom']);
    }

    # Возвращает отцентрованную по горизонтали кнопку.
    # Входные данные:
    #	$button_defs - массив описаний кнопки
    # 	$viewport_width - ширина вьюпорта (или диалогового окна)
    #
    # Example:
    #	Control_Factory::add_button($button_defs, ...);
    #	$defs[] = get_centered_button($button_defs, 1000);
    public static function get_centered_button($button_defs, $viewport_width)
    {
        $def = end($button_defs);
        $def[GuiControlDef::title] = str_repeat(' ', ($viewport_width - $def[GuiControlDef::specific_def][GuiButtonDef::width]) / (15 * 2));

        return $def;
    }

    /**
     * @param $defs
     * @param $viewport_width
     * @param $height
     * @return mixed
     */
    protected static function set_controls($defs, $viewport_width, $height)
    {
        self::add_smart_label($defs, null, '<gap width=5/><icon dy=8 width=' . ($viewport_width - 10) . ' height=' . ($height - 10) . '>' . self::$instance->sandwich_cover_center . '</icon>');
        self::add_vgap($defs, -69);
        self::add_smart_label($defs, null, '<icon>' . self::sandwich_cover_top_left . '</icon><icon width=' . ($viewport_width - self::$instance->sandwich_cover_manifest['left'] - self::$instance->sandwich_cover_manifest['right']) . ' height=' . self::$instance->sandwich_cover_manifest['top'] . '>' . self::sandwich_cover_top . '</icon><icon>' . self::sandwich_cover_top_right . '</icon>');
        self::add_vgap($defs, self::$instance->sandwich_cover_manifest['top'] - 69);
        self::add_smart_label($defs, null, '<icon width=' . self::$instance->sandwich_cover_manifest['left'] . ' height=' . ($height - self::$instance->sandwich_cover_manifest['top'] - self::$instance->sandwich_cover_manifest['bottom']) . '>' . self::sandwich_cover_left . '</icon><gap width=' . ($viewport_width - self::$instance->sandwich_cover_manifest['left'] - self::$instance->sandwich_cover_manifest['right']) . '/><icon width=' . self::$instance->sandwich_cover_manifest['right'] . ' height=' . ($height - self::$instance->sandwich_cover_manifest['top'] - self::$instance->sandwich_cover_manifest['bottom']) . '>' . self::sandwich_cover_right . '</icon>');
        self::add_vgap($defs, -self::$instance->sandwich_cover_manifest['top'] - self::$instance->sandwich_cover_manifest['bottom'] + $height - 69);
        self::add_smart_label($defs, null, '<icon>' . self::sandwich_cover_bottom_left . '</icon><icon width=' . ($viewport_width - self::$instance->sandwich_cover_manifest['left'] - self::$instance->sandwich_cover_manifest['right']) . ' height=' . self::$instance->sandwich_cover_manifest['bottom'] . '>' . self::sandwich_cover_bottom . '</icon><icon>' . self::sandwich_cover_bottom_right . '</icon>');
        self::add_vgap($defs, self::$instance->sandwich_cover_manifest['bottom'] - $height - 69 + 20);
        return $defs;
    }
}
