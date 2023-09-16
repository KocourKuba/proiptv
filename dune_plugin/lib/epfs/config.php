<?php
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
	const info_width				    = 700;
	const info_height				    = 640;
	const vod_width				        = 1120;
	const vod_height				    = 630;
	const vod_bg_url				    = '/bg.jpg';
	const vod_mask_url		    	    = '/mask.png';
	const max_items_in_row	    	    = 7;
	const ch_num_font_color	    	    = '#EFAA16FF';
	const ch_num_font_size	    	    = 50; # size in pt
	const ch_title_font_color		    = '#EFAA16FF';
	const ch_title_font_size		    = 64; # size in pt
	const prog_title_font_color		    = '#FFFFD0FF';
	const prog_title_font_size		    = 40; # size in pt
	const prog_item_font_color		    = '#AFAFA0FF';
	const prog_item_font_size		    = 30; # size in pt
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
}

class RowsParams
{
	const dx						= 0;
	const width						= 1920;
	const height					= 230;
	const left_padding				= 90;
	const inactive_left_padding		= 120;
	const right_padding				= 120;
	const hide_captions				= false;
	const fade_enable				= true;
	const fade_icon_mix_color		= 0;
	const fade_icon_mix_alpha		= 170; # 0-255
	const lite_fade_icon_mix_alpha	= 128; # 0-255
	const fade_caption_color		= '#808080FF';
}

class TitleRowsParams
{
	const width						= 1920;
	const height					= 65;
	const font_size					= 35; # size in pt
	const left_padding				= 115;
	const fade_enabled				= true;
	const fade_color				= '#606060FF';
	const lite_fade_color			= '#808080FF';
	const def_caption_color			= '#FFFFE0FF';
    const fav_caption_color		    = '#EFAA16FF';
	const history_caption_color		= '#19BE199F';
}

class RowsItemsParams
{
	const width						= 250;
    const width_sq					= 178;
	const height					= 230;
	const icon_width				= 230;
    const icon_width_sq				= 158;
	const icon_height				= 140;
    const caption_dy				= 0;
    const caption_max_num_lines		= 2;
    const caption_line_spacing		= 0;
    const def_caption_color			= '#AFAFA0FF';
    const sel_caption_color			= '#FFFFE0FF';
    const inactive_caption_color	= '#00000000';
    const caption_font_size			= 28; # size in pt
    const icon_loading_url			= 'loading.png';
    const icon_sq_loading_url		= 'loading_square.png';
    const icon_loading_failed_url	= 'unset.png';
    const icon_sq_loading_failed_url = 'unset_square.png';
    const fav_sticker_icon_url		= 'star.png';
    const fav_sticker_bg_width		= 40;
    const fav_sticker_bg_height		= 40;
    const fav_sticker_icon_width	= 36;
    const fav_sticker_icon_height	= 36;
    const fav_sticker_bg_color		= '#000000FF';
    const fav_sticker_logo_bg_color	= '#FFFFFFFF';
    const fav_progress_dy           = 134;
    const view_progress_width       = 228;
    const view_progress_height      = 8;
    const view_total_color	        = '#6A6A6ACF';
    const view_viewed_color	        = '#EFAA16FF';
}
