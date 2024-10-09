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

// Config for embeddable plugin folders
// Colors represented in RGBA format for best view in IDE

class PaneParams
{
	const dx						    = 0;
	const dy						    = 0;
	const width					        = 1920;
	const height					    = 1080;
	const info_dx					    = 100;
	const info_dy					    = 40;
    const pane_width                    = 1790;
    const pane_height                   = 640;
    const group_list_width              = 350;
	const info_width				    = 670;
	const info_height				    = 640;
	const vod_width				        = 1120;
	const vod_height				    = 630;
	const vod_bg_url				    = '/bg.jpg';
	const vod_mask_url		    	    = '/mask.png';
    const ch_num_font_color	    	    = '#EFAA16FF';
	const ch_num_font_size	    	    = 50; # size in pt
	const ch_title_font_color		    = '#EFAA16FF';
	const ch_title_font_size		    = 56; # size in pt
	const prog_title_font_color		    = '#FFFFD0FF';
	const prog_title_font_size		    = 38; # size in pt
	const prog_item_font_color		    = '#AFAFA0FF';
	const prog_item_font_size		    = 28; # size in pt
	const prog_item_height			    = 60;
    const separator_line_color		    = '#1919BE9F';
    const fav_btn_width      		    = 52;
    const fav_btn_height      		    = 50;
    const fav_btn_font_size 		    = 26; # size in pt
    const fav_btn_font_color	    	= '#E0E0E0FF';
    const fav_btn_disabled_font_color	= '#808080FF';
    const fav_button_green              = 'gui_skin://special_icons/controls_button_green.aai';
    const fav_button_yellow             = 'gui_skin://special_icons/controls_button_yellow.aai';
    const fav_button_blue               = 'gui_skin://special_icons/controls_button_blue.aai';

    public static $ch_num_pos = array(
            0 => array('x' => 690,  'y' => 520), // bottom left
            1 => array('x' => 690,  'y' => 0),   // top left
            2 => array('x' => 1670, 'y' => 0),   // top right
            3 => array('x' => 1670, 'y' => 520), // bottom right
        );
}

class RowsParams
{
	const dx						= 0;
	const width						= 1920;
	const left_padding				= 90;
	const inactive_left_padding		= 100;
	const right_padding				= 120;
    const hfactor                   = 1.0;
    const vfactor                   = 0.0;
    const vend_min_offset           = 50;
	const fade_icon_mix_color		= 0;
	const fade_icon_mix_alpha		= 170; # 0-255
	const lite_fade_icon_mix_alpha	= 128; # 0-255
	const fade_caption_color		= '#808080FF';
}

class TitleRowsParams
{
	const width						= 1920;
	const height					= 80;
	const font_size					= 35; # size in pt
	const left_padding				= 115;
	const fade_color				= '#606060FF';
	const lite_fade_color			= '#808080FF';
	const def_caption_color			= '#FFFFE0FF';
    const fav_caption_color		    = '#EFAA16FF';
	const history_caption_color		= '#19BE199F';
}

class RowsItemsParams
{
    const def_caption_dy			= 0;
    const sel_caption_dy			= 5;
    const inactive_caption_dy		= 0;
    const caption_max_num_lines		= 2;
    const caption_line_spacing		= 0;
    const inactive_caption_color	= '#00000000';
    const def_caption_color			= '#AFAFA0FF';
    const sel_caption_color			= '#FFFFE0FF';
    const def_icon_dx               = 0;
    const def_icon_dy               = 5;
    const sel_zoom_delta            = 15;
    const sel_icon_dx               = 5;
    const sel_icon_dy               = 0;
    const inactive_icon_dx          = 0;
    const inactive_icon_dy          = 0;
    const fav_sticker_icon_url		= 'star.png';
    const fav_sticker_bg_width		= 40;
    const fav_sticker_bg_height		= 40;
    const fav_sticker_icon_width	= 36;
    const fav_sticker_icon_height	= 36;
    const fav_sticker_bg_color		= '#40404080';
    const fav_sticker_logo_bg_color	= '#FFFFFFFF';
    const view_total_color	        = '#6A6A6ACF';
    const view_viewed_color	        = '#EFAA16FF';
    const view_progress_height      = 10;
    const icon_prop 				= 0.6;
    const icon_prop_sq 				= 1;
    const vgravity                  = -0.5;
    const vgravity_sq               = 0.5;
    const icon_loading_url			= 'loading.png';
    const icon_loading_url_sq		= 'loading_square.png';
    const icon_loading_failed_url	= 'unset.png';
    const icon_loading_failed_url_sq = 'unset_square.png';
}

class RowsItemsParams5 extends RowsItemsParams
{
    const items_in_row              = 5;
    const caption_font_size			= 28; # size in pt
    const width					    = 360; // (1920 - 120) / 5 = 360
    const width_inactive		    = 280; // (1920 - 526) / 5 = 278
    const icon_width				= 260;
    const icon_width_inactive       = 260;
}

class RowsItemsParams6 extends RowsItemsParams
{
    const items_in_row              = 6;
    const caption_font_size			= 26; # size in pt
    const width					    = 300; // (1920 - 120) / 6 = 300
    const width_inactive		    = 232; // (1920 - 526) / 6 = 232
    const icon_width				= 200;
    const icon_width_inactive       = 200;
}

class RowsItemsParams7 extends RowsItemsParams
{
    const items_in_row              = 7;
    const caption_font_size			= 24; # size in pt
    const width					    = 258; // (1920 - 120) / 7 = 258
    const width_inactive		    = 200; // (1920 - 526) / 7 = 200
    const icon_width				= 180;
    const icon_width_inactive       = 180;
}
