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

require_once 'lib/ext_epg_program.php';
require_once 'lib/playback_points.php';

require_once 'lib/epfs/abstract_rows_screen.php';
require_once 'lib/epfs/rows_factory.php';
require_once 'lib/epfs/gcomps_factory.php';
require_once 'lib/epfs/gcomp_geom.php';

class Starnet_Tv_Rows_Screen extends Abstract_Rows_Screen implements User_Input_Handler
{
    const ID = 'rows_epf';

    ///////////////////////////////////////////////////////////////////////////

    private $removed_playback_point;
    private $clear_playback_points = false;

    public $need_update_epf_mapping_flag = false;

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param $pane
     * @param $rows_before
     * @param $rows_after
     * @param $min_row_index_for_y2
     * @return void
     */
    public function add_rows_to_pane(&$pane, $rows_before = null, $rows_after = null, $min_row_index_for_y2 = null)
    {
        if (is_array($rows_before))
            $pane[PluginRowsPane::rows] = array_merge($rows_before, $pane[PluginRowsPane::rows]);

        if (is_array($rows_after))
            $pane[PluginRowsPane::rows] = array_merge($pane[PluginRowsPane::rows], $rows_after);

        if (!is_null($min_row_index_for_y2))
            $pane[PluginRowsPane::min_row_index_for_y2] = $min_row_index_for_y2;
    }

    /**
     * @param $parent_sel_state
     * @return MediaURL|null
     */
    public function get_parent_media_url($parent_sel_state)
    {
        foreach (explode("\n", $parent_sel_state) as $line) {
            if (strpos($line, 'channel_id')) {
                return MediaURL::decode($line);
            }
        }

        return null;
    }

    /**
     * @param $media_url
     * @param $plugin_cookies
     * @return array|null
     */
    protected function do_get_info_children($media_url, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $group_id = isset($media_url->group_id) ? $media_url->group_id : null;
        $channel_id = isset($media_url->channel_id) ? $media_url->channel_id : null;

        if (is_null($channel_id) || empty($group_id))
            return null;

        $channel = $this->plugin->tv->get_channel($channel_id);
        if (is_null($channel)) {
            hd_debug_print("Unknown channel $channel_id");
            return null;
        }

        $title_num = 1;
        $defs = array();

        ///////////// Channel number /////////////////

        $number = $channel->get_number();
        $defs[] = GComps_Factory::label(GComp_Geom::place_top_left(130, 50, 690, 520),
            null,
            $number,
            1,
            PaneParams::ch_num_font_color,
            PaneParams::ch_num_font_size,
            'ch_number'
        );

        ///////////// Channel title /////////////////

        $defs[] = GComps_Factory::label(GComp_Geom::place_top_left(PaneParams::info_width + 200, PaneParams::prog_item_height),
            null,
            $channel->get_title(),
            1,
            PaneParams::ch_title_font_color,
            PaneParams::ch_title_font_size,
            'ch_title'
        );
        $y = PaneParams::prog_item_height;

        ///////////// start_time, end_time, genre, country, person /////////////////

        if (is_null($epg_data = $this->plugin->get_program_info($channel_id, -1, $plugin_cookies))) {

            hd_debug_print("no epg data");
            $channel_desc = $channel->get_desc();
            if (!empty($channel_desc)) {
                $geom = GComp_Geom::place_top_left(PaneParams::info_width, -1, 0, $y);
                $defs[] = GComps_Factory::label($geom,
                    null,
                    $channel_desc,
                    13 - $title_num,
                    PaneParams::prog_item_font_color,
                    PaneParams::prog_item_font_size,
                    'ch_desc',
                    array('line_spacing' => 6)
                );
            }
        } else {

            $program = (object)array();
            $program->time = sprintf("%s - %s",
                gmdate('H:i', $epg_data[PluginTvEpgProgram::start_tm_sec] + get_local_time_zone_offset()),
                gmdate('H:i', $epg_data[PluginTvEpgProgram::end_tm_sec] +  get_local_time_zone_offset())
            );
            //$program->year = preg_match('/\s+\((\d{4,4})\)$/', $epg_data[Ext_Epg_Program::main_category], $matches) ? $matches[1] : '';
            //$program->age = preg_match('/\s+\((\d{1,2}\+)\)$/', $epg_data[Ext_Epg_Program::main_category], $matches) ? $matches[1] : '';

            $title = $epg_data[PluginTvEpgProgram::name];
            $desc = (!empty($epg_data[Ext_Epg_Program::sub_title]) ? $epg_data[Ext_Epg_Program::sub_title] . "\n" : '') . $epg_data[PluginTvEpgProgram::description];
            $fanart_url = '';

            // duration
            $geom = GComp_Geom::place_top_left(PaneParams::info_width, PaneParams::prog_item_height, 0, $y);
            $defs[] = GComps_Factory::label($geom, null, $program->time, 1, PaneParams::prog_item_font_color, PaneParams::prog_item_font_size);
            $y += PaneParams::prog_item_height;

            ///////////// Program title ////////////////

            if (!empty($title)) {
                $lines = array_slice(explode("\n",
                    iconv('Windows-1251', 'UTF-8',
                        wordwrap(iconv('UTF-8', 'Windows-1251',
                            trim(preg_replace('/([!?])\.+\s*$/Uu', '$1', $title))),
                            40, "\n", true)
                    )),
                    0, 2);

                $prog_title = implode("\n", $lines);

                if (strlen($prog_title) < strlen($title))
                    $prog_title = $title;

                $lines = min(2, count($lines));
                $geom = GComp_Geom::place_top_left(PaneParams::info_width + 100, PaneParams::prog_item_height, 0, $y + ($lines > 1 ? 20 : 0));
                $defs[] = GComps_Factory::label($geom,
                    null,
                    $prog_title,
                    2,
                    PaneParams::prog_title_font_color,
                    PaneParams::prog_title_font_size,
                    'prog_title',
                    array('line_spacing' => 5)
                );
                $y += (PaneParams::prog_item_height - 20) * $lines + ($lines > 1 ? 10 : 0);
                $title_num += $lines > 1 ? 1 : 0;
            } else {
                $title_num--;
            }

            ///////////// Description ////////////////

            if (!empty($desc)) {
                $geom = GComp_Geom::place_top_left(PaneParams::info_width, -1, 0, $y + 5);
                $defs[] = GComps_Factory::label($geom,
                    null,
                    $desc,
                    10 - $title_num,
                    PaneParams::prog_item_font_color,
                    PaneParams::prog_item_font_size,
                    'prog_desc',
                    array('line_spacing' => 5)
                );
            }
        }

        // separator line
        $defs[] = GComps_Factory::get_rect_def(GComp_Geom::place_top_left(510, 4, 0, 590), null, PaneParams::separator_line_color);

        $dy_icon = 530;
        $dy_txt = $dy_icon - 4;
        $dx = 15;
        hd_debug_print("newUI: $group_id");
        if ($group_id === HISTORY_GROUP_ID || $group_id === ALL_CHANNEL_GROUP_ID || $group_id === CHANGED_CHANNELS_GROUP_ID) {

            // blue button image (D)
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_blue);

            $dx += 55;
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left(PaneParams::info_width, -1, $dx, $dy_txt), // label
                null,
                ($group_id === CHANGED_CHANNELS_GROUP_ID) ? TR::load_string('clear_changed') : TR::load_string('add_delete_favorite'),
                1,
                PaneParams::fav_btn_font_color,
                PaneParams::fav_btn_font_size
            );
        } else {
            /** @var Group $group */
            if ($group_id === FAVORITES_GROUP_ID) {
                $group = $this->plugin->tv->get_special_group($group_id);
            } else {
                $group = $this->plugin->tv->get_group($group_id);
            }
            $order = $group->get_items_order()->get_order();

            $is_first_channel = ($channel_id === reset($order));
            // green button image (B) 52x50
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_green,
                false,
                true,
                null,
                null,
                null,
                $is_first_channel ? 99 : 255);

            $dx += 55;
            // green button text
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left(PaneParams::info_width, -1, $dx, $dy_txt), // label
                null,
                TR::t('left'),
                1,
                $is_first_channel ? PaneParams::fav_btn_disabled_font_color : PaneParams::fav_btn_font_color,
                PaneParams::fav_btn_font_size
            );

            $is_last_channel = ($channel_id === end($order));
            $dx += 105;
            // yellow button image (C)
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_yellow,
                1,
                false,
                null,
                null,
                null,
                $is_last_channel ? 99 : 255
            );

            $dx += 55;
            // yellow button text
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left(PaneParams::info_width, -1, $dx, $dy_txt), // label
                null,
                TR::t('right'),
                1,
                $is_last_channel ? PaneParams::fav_btn_disabled_font_color : PaneParams::fav_btn_font_color,
                PaneParams::fav_btn_font_size
            );

            $dx += 105;
            // blue button image (D)
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_blue);

            $dx += 55;
            // blue button text
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left(PaneParams::info_width, -1, $dx, $dy_txt), // label
                null,
                TR::t('delete'),
                1,
                PaneParams::fav_btn_font_color,
                PaneParams::fav_btn_font_size
            );
        }

        ///////////// Enclosing panel ////////////////

        $pane_def = GComps_Factory::get_panel_def('info_pane',
            GComp_Geom::place_top_left(PaneParams::pane_width, PaneParams::pane_height),
            null,
            $defs,
            GCOMP_OPT_PREPAINT
        );
        GComps_Factory::add_extra_var($pane_def, 'info_inf_dimmed', null, array('alpha' => 64));

        return array(
            'defs' => array($pane_def),
            'fanart_url' => empty($fanart_url) ? '' : $fanart_url,
        );
    }

    /**
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_folder_view_for_epf(&$plugin_cookies)
    {
        hd_debug_print(null, true);

        if ($this->plugin->tv->load_channels() === 0) {
            hd_debug_print("Channels not loaded!");
        }

        return $this->get_folder_view(MediaURL::decode(static::ID), $plugin_cookies);
    }

    /**
     * @inheritDoc
     */
    public function get_rows_pane(MediaURL $media_url, $plugin_cookies)
    {
        hd_debug_print(null, true);
        $rows = array();

        $history_rows = $this->get_history_rows($plugin_cookies);
        if (!is_null($history_rows)) {
            $rows = array_merge($rows, $history_rows);
            hd_debug_print("added history: " . count($history_rows) . " rows", true);
        }

        $favorites_rows = $this->get_favorites_rows();
        if (!is_null($favorites_rows)) {
            hd_debug_print("added favorites: " . count($favorites_rows) . " rows", true);
            $rows = array_merge($rows, $favorites_rows);
        }

        $changed_rows = $this->get_changed_channels_rows();
        if (!is_null($changed_rows)) {
            hd_debug_print("added changed channels: " . count($changed_rows) . " rows", true);
            $rows = array_merge($rows, $changed_rows);
        }

        $all_channels_rows = $this->get_all_channels_row();
        if (!is_null($all_channels_rows)) {
            $rows = array_merge($rows, $all_channels_rows);
            hd_debug_print("added all channels: " . count($all_channels_rows) . " rows", true);
        }

        $category_rows = $this->get_regular_rows();
        if (is_null($category_rows)) {
            hd_debug_print("no category rows");
            return null;
        }

        $rows = array_merge($rows, $category_rows);
        hd_debug_print("added group channels: " . count($category_rows) . " rows", true);

        $pane = Rows_Factory::pane(
            $rows,
            Rows_Factory::focus(GCOMP_FOCUS_DEFAULT_CUT_IMAGE, GCOMP_FOCUS_DEFAULT_RECT),
            null, true, true, -1, null, null,
            1.0, 0.0, -0.5, 250);

        Rows_Factory::pane_set_geometry(
            $pane,
            PaneParams::width,
            PaneParams::height,
            PaneParams::dx,
            PaneParams::dy,
            PaneParams::info_height,
            empty($history_rows) ? 1 : 2,
            PaneParams::width - PaneParams::info_dx,
            PaneParams::info_height - PaneParams::info_dy,
            PaneParams::info_dx, PaneParams::info_dy,
            PaneParams::vod_width, PaneParams::vod_height
        );

        $square_icons =  $this->plugin->get_bool_setting(PARAM_SQUARE_ICONS, false);
        $icon_width = $square_icons ? RowsItemsParams::icon_width_sq : RowsItemsParams::icon_width;
        $icon_prop = $icon_width / RowsItemsParams::icon_height;

        $def_params = Rows_Factory::variable_params(
            RowsItemsParams::width,
            RowsItemsParams::height,
            0,
            $icon_width,
            RowsItemsParams::icon_height,
            5,
            RowsItemsParams::caption_dy,
            RowsItemsParams::def_caption_color,
            RowsItemsParams::caption_font_size
        );

        $sel_icon_width = $icon_width + 12;
        $sel_icon_height = round(($icon_width + 12) / $icon_prop);
        $sel_params = Rows_Factory::variable_params(
            RowsItemsParams::width,
            RowsItemsParams::height,
            5,
            $sel_icon_width,
            $sel_icon_height,
            0,
            RowsItemsParams::caption_dy + 10,
            RowsItemsParams::sel_caption_color,
            RowsItemsParams::caption_font_size
        );

        $width = round((RowsItemsParams::width * PaneParams::max_items_in_row - PaneParams::group_list_width) / PaneParams::max_items_in_row);
        $inactive_icon_width = round(($icon_width * PaneParams::max_items_in_row - PaneParams::group_list_width) / PaneParams::max_items_in_row)
            + round((RowsItemsParams::width - $icon_width) / $icon_prop);
        $inactive_icon_height = round($inactive_icon_width / $icon_prop) - 10;

        $inactive_params = Rows_Factory::variable_params(
            $width,
            round($width / RowsItemsParams::width * RowsItemsParams::height), 0,
            $inactive_icon_width,
            $inactive_icon_height,
            0,
            RowsItemsParams::caption_dy,
            RowsItemsParams::inactive_caption_color,
            RowsItemsParams::caption_font_size
        );

        $params = Rows_Factory::item_params(
            $def_params,
            $sel_params,
            $inactive_params,
            get_image_path($square_icons ? RowsItemsParams::icon_sq_loading_url : RowsItemsParams::icon_loading_url),
            get_image_path($square_icons ? RowsItemsParams::icon_sq_loading_failed_url : RowsItemsParams::icon_loading_failed_url),
            RowsItemsParams::caption_max_num_lines,
            RowsItemsParams::caption_line_spacing,
            Rows_Factory::margins(6, 2, 2, 2)
        );

        Rows_Factory::set_item_params_template($pane, 'common', $params);

        return $pane;
    }

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        return array(
            GUI_EVENT_KEY_PLAY                => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_PLAY),
            GUI_EVENT_KEY_ENTER               => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
            GUI_EVENT_KEY_B_GREEN             => User_Input_Handler_Registry::create_action($this, PLUGIN_FAVORITES_OP_MOVE_UP),
            GUI_EVENT_KEY_C_YELLOW            => User_Input_Handler_Registry::create_action($this, PLUGIN_FAVORITES_OP_MOVE_DOWN),
            GUI_EVENT_KEY_D_BLUE              => User_Input_Handler_Registry::create_action($this, PLUGIN_FAVORITES_OP_ADD),
            GUI_EVENT_KEY_POPUP_MENU          => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU),
            GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE => User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (isset($user_input->item_id)) {
            $media_url_str = $user_input->item_id;
            $media_url = MediaURL::decode($media_url_str);
        } else if ($user_input->control_id === ACTION_REFRESH_SCREEN) {
            $media_url = '';
            $media_url_str = '';
        } else {
            $media_url = $this->get_parent_media_url($user_input->parent_sel_state);
            $media_url_str = '';
            if (is_null($media_url))
                return null;
        }

        $control_id = $user_input->control_id;

        switch ($control_id) {
            case GUI_EVENT_TIMER:
                // rising after playback end + 100 ms
                $this->plugin->get_playback_points()->update_point(null);
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case GUI_EVENT_KEY_PLAY:
            case GUI_EVENT_KEY_ENTER:
                $tv_play_action = Action_Factory::tv_play($user_input->selected_item_id);

                if (isset($user_input->action_origin)) {
                    return Action_Factory::close_and_run(Starnet_Epfs_Handler::invalidate_folders(null, $tv_play_action));
                }

                $new_actions = array_merge($this->get_action_map($media_url, $plugin_cookies),
                    array(GUI_EVENT_TIMER => User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER)));

                return Action_Factory::change_behaviour($new_actions, 100, $tv_play_action);

            case GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE:
                if (!isset($user_input->item_id, $user_input->folder_key))
                    return null;

                $info_children = $this->do_get_info_children(MediaURL::decode($media_url_str), $plugin_cookies);

                return Action_Factory::update_rows_info(
                    $user_input->folder_key,
                    $user_input->item_id,
                    $info_children['defs'],
                    empty($info_children['fanart_url']) ? get_image_path(PaneParams::vod_bg_url) : $info_children['fanart_url'],
                    get_image_path(PaneParams::vod_bg_url),
                    get_image_path(PaneParams::vod_mask_url),
                    array("plugin_tv://" . get_plugin_name() . "/$user_input->item_id")
                );

            case ACTION_SORT_POPUP:
                hd_debug_print("Start event popup menu for playlist");
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_SORT_POPUP => true));

            case GUI_EVENT_KEY_POPUP_MENU:
                return Action_Factory::show_popup_menu($this->do_popup_menu($user_input));

            case ACTION_ZOOM_POPUP_MENU:
                $menu_items = array();
                $zoom_data = $this->plugin->tv->get_channel_zoom($media_url->channel_id);
                foreach (DuneVideoZoomPresets::$zoom_ops as $idx => $zoom_item) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_ZOOM_APPLY,
                        TR::t($zoom_item),
                        (strcmp($idx, $zoom_data) !== 0 ? null : "check.png"),
                        array(ACTION_ZOOM_SELECT => (string)$idx)
                    );
                }

                return Action_Factory::show_popup_menu($menu_items);

            case PLUGIN_FAVORITES_OP_ADD:
            case PLUGIN_FAVORITES_OP_REMOVE:
                if (!isset($media_url->group_id) || $media_url->group_id === HISTORY_GROUP_ID)
                    break;

                if ($media_url->group_id === CHANGED_CHANNELS_GROUP_ID) {
                    $known_order = &$this->plugin->tv->get_known_channels();
                    $known_order->erase($media_url->channel_id);
                    $channel = $this->plugin->tv->get_channel($media_url->channel_id);
                    if (!is_null($channel)) {
                        $known_order->set($channel->get_id(), $channel->get_title());
                    }
                    $this->set_changes();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($media_url->group_id === FAVORITES_GROUP_ID) {
                    if ($control_id === PLUGIN_FAVORITES_OP_ADD) {
                        if ($this->plugin->tv->get_channel($media_url->channel_id) === null) break;

                        $is_in_favorites = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID)->in_items_order($media_url->channel_id);
                        $control_id = $is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                    }

                    if ($this->plugin->tv->change_tv_favorites($control_id, $media_url->channel_id)) {
                        $this->set_changes();
                    }
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case PLUGIN_FAVORITES_OP_MOVE_UP:
            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                if (!isset($media_url->group_id)) break;

                $direction = ($control_id === PLUGIN_FAVORITES_OP_MOVE_UP ? Ordered_Array::UP : Ordered_Array::DOWN);
                if (isset($user_input->selected_item_id)) {

                    if ($media_url->group_id === HISTORY_GROUP_ID
                        || $media_url->group_id === ALL_CHANNEL_GROUP_ID
                        || $media_url->group_id === CHANGED_CHANNELS_GROUP_ID
                    ) break;

                    if ($media_url->group_id === FAVORITES_GROUP_ID) {
                        if ($this->plugin->tv->change_tv_favorites($control_id, $media_url->channel_id) !== null) {
                            $this->set_changes();
                        }
                    } else {
                        $group = $this->plugin->tv->get_group($media_url->group_id);
                        if (!is_null($group) && $group->get_items_order()->arrange_item($media_url->channel_id, $direction)) {
                            $this->set_changes();
                        }
                    }
                } else if ($this->plugin->tv->get_groups_order()->arrange_item($media_url->group_id, $direction)) {
                    $this->set_changes();
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_ITEMS_SORT:
                $group = $this->plugin->tv->get_group($media_url->group_id);
                if (is_null($group) || !isset($user_input->{ACTION_SORT_TYPE})) {
                    return null;
                }

                if ($user_input->{ACTION_SORT_TYPE} === ACTION_SORT_CHANNELS) {
                    $group->sort_group_items();
                } else {
                    $this->plugin->tv->get_groups_order()->sort_order();
                }
                $this->set_changes();
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RESET_ITEMS_SORT:
                if (!isset($user_input->{ACTION_RESET_TYPE})) {
                    return null;
                }

                /** @var Channel $channel */
                if ($user_input->{ACTION_RESET_TYPE} === ACTION_SORT_CHANNELS) {
                    if (!is_null($sel_group = $this->plugin->tv->get_group($media_url->group_id))) {
                        $sel_group->sort_group_items(true);
                        $this->set_changes();
                    }
                } else if ($user_input->{ACTION_RESET_TYPE} === ACTION_SORT_GROUPS) {
                    $order = &$this->plugin->tv->get_groups_order();
                    $order->clear();
                    foreach ($this->plugin->tv->get_enabled_groups() as $group) {
                        $order->add_item($group->get_id());
                    }
                    $this->set_changes();
                } else if ($user_input->{ACTION_RESET_TYPE} === ACTION_SORT_ALL) {
                    $order = &$this->plugin->tv->get_groups_order();
                    $order->clear();
                    foreach ($this->plugin->tv->get_enabled_groups() as $group) {
                        $order->add_item($group->get_id());
                        $group->sort_group_items();
                    }
                    $this->set_changes();
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_ITEM_REMOVE:
                $this->removed_playback_point = $media_url->get_raw_string();
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_ITEMS_CLEAR:
                hd_debug_print($media_url->get_media_url_str());
                if ($media_url->group_id === HISTORY_GROUP_ID) {
                    $this->clear_playback_points = true;
                    $this->plugin->get_playback_points()->clear_points();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($media_url->group_id === FAVORITES_GROUP_ID) {
                    $this->set_changes();
                    $this->plugin->tv->change_tv_favorites(ACTION_ITEMS_CLEAR, null);
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                break;

            case ACTION_ITEM_DELETE:
                if (isset($user_input->selected_item_id)) {
                    $channel = $this->plugin->tv->get_channel($media_url->channel_id);
                    if (!is_null($channel)) {
                        $channel->set_disabled(true);
                        $this->set_changes();
                    }
                } else {
                    $group = $this->plugin->tv->get_group($media_url->group_id);
                    if (!is_null($group)) {
                        $group->set_disabled(true);
                        $this->set_changes();
                    }
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_CHANGE_PLAYLIST:
                hd_debug_print("Start event popup menu for playlist");
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_PLAYLIST => true));

            case ACTION_PLAYLIST_SELECTED:
                if (!isset($user_input->{LIST_IDX})) break;

                $this->plugin->set_active_playlist_key($user_input->{LIST_IDX});
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_CHANGE_EPG_SOURCE:
                hd_debug_print("Start event popup menu for epg source");
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_EPG_SOURCE => true));

            case ACTION_EPG_SOURCE_SELECTED:
                if (!isset($user_input->{LIST_IDX})) break;

                $this->plugin->set_active_xmltv_source_key($user_input->{LIST_IDX});

                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD, null, array('reload_action' => 'epg'));

            case ACTION_ZOOM_APPLY:
                $channel_id = $media_url->channel_id;
                if (isset($user_input->{ACTION_ZOOM_SELECT})) {
                    $zoom_select = $user_input->{ACTION_ZOOM_SELECT};
                    $this->plugin->tv->set_channel_zoom($channel_id, ($zoom_select !== DuneVideoZoomPresets::not_set) ? $zoom_select : null);
                }
                break;

            case ACTION_EXTERNAL_PLAYER:
            case ACTION_INTERNAL_PLAYER:
                $this->plugin->tv->set_channel_for_ext_player($media_url->channel_id, $user_input->control_id === ACTION_EXTERNAL_PLAYER);
                break;

            case ACTION_TOGGLE_ICONS_TYPE:
                $this->plugin->toggle_setting(PARAM_SQUARE_ICONS, false);
                $this->set_changes();
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_RELOAD:
                if (isset($user_input->reload_action) && $user_input->reload_action === 'epg') {
                    $this->plugin->save_settings(true);
                    $this->plugin->get_epg_manager()->clear_epg_cache();
                    $this->plugin->init_epg_manager();
                    $res = $this->plugin->get_epg_manager()->is_xmltv_cache_valid();
                    if ($res === -1) {
                        return Action_Factory::show_title_dialog(TR::t('err_epg_not_set'), null, HD::get_last_error());
                    }

                    if ($res === 0) {
                        $res = $this->plugin->get_epg_manager()->download_xmltv_source();
                        if ($res === -1) {
                            return Action_Factory::show_title_dialog(TR::t('err_load_xmltv_epg'), null, HD::get_last_error());
                        }
                    }
                }

                if ($this->has_changes()) {
                    $this->set_no_changes();
                    $this->plugin->save_orders(true);
                }

                if ($this->plugin->tv->reload_channels() === 0) {
                    Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
                    return Starnet_Epfs_Handler::invalidate_folders(null,
                        Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_REFRESH_SCREEN:
                if ($this->has_changes()) {
                    $this->set_no_changes();
                    $this->plugin->save_orders(true);
                }
                Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
                return Starnet_Epfs_Handler::invalidate_folders();
        }

        return null;
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param $plugin_cookies
     * @return array|null
     */
    private function get_history_rows($plugin_cookies)
    {
        hd_debug_print(null, true);
        if (!$this->plugin->get_bool_parameter(PARAM_SHOW_HISTORY)) {
            hd_debug_print("History group disabled");
            return null;
        }

        if ($this->clear_playback_points) {
            $this->clear_playback_points = false;
            return null;
        }

        // Fill view history data
        $now = time();
        $rows = array();
        $watched = array();
        foreach ($this->plugin->get_playback_points()->get_all() as $channel_id => $channel_ts) {
            if (is_null($channel = $this->plugin->tv->get_channel($channel_id))) continue;

            $prog_info = $this->plugin->get_program_info($channel_id, $channel_ts, $plugin_cookies);
            $progress = 0;

            if (is_null($prog_info)) {
                $title = $channel->get_title();
            } else {
                // program epg available
                $title = $prog_info[PluginTvEpgProgram::name];
                if ($channel_ts > 0) {
                    $start_tm = $prog_info[PluginTvEpgProgram::start_tm_sec];
                    $epg_len = $prog_info[PluginTvEpgProgram::end_tm_sec] - $start_tm;
                    if ($channel_ts >= $now - $channel->get_archive_past_sec() - 60) {
                        $progress = max(0.01, min(1.0, round(($channel_ts - $start_tm) / $epg_len, 2)));
                    }
                }
            }

            $watched[(string)$channel_id] = array(
                'channel_id' => $channel_id,
                'archive_tm' => $channel_ts,
                'view_progress' => $progress,
                'program_title' => $title,
            );
        }

        // fill view history row items
        $items = array();
        foreach ($watched as $item) {
            if (!is_null($channel = $this->plugin->tv->get_channel($item['channel_id']))) {
                $id = json_encode(array('group_id' => HISTORY_GROUP_ID, 'channel_id' => $item['channel_id'], 'archive_tm' => $item['archive_tm']));
                //hd_debug_print("MediaUrl info for {$item['channel_id']} - $id");
                if (isset($this->removed_playback_point))
                    if ($this->removed_playback_point === $id) {
                        $this->removed_playback_point = null;
                        $this->plugin->get_playback_points()->erase_point($item['channel_id']);
                        continue;
                    }

                $stickers = null;

                if ($item['view_progress'] > 0) {
                    // item size 229x142
                    if (!empty($item['program_icon_url'])) {
                        // add small channel logo
                        $rect = Rows_Factory::r(129, 0, 100, 64);
                        $stickers[] = Rows_Factory::add_regular_sticker_rect(RowsItemsParams::fav_sticker_logo_bg_color, $rect);
                        $stickers[] = Rows_Factory::add_regular_sticker_image($channel->get_icon_url(), $rect);
                    }

                    // add progress indicator
                    $stickers[] = Rows_Factory::add_regular_sticker_rect(
                        RowsItemsParams::view_total_color,
                        Rows_Factory::r(0,
                            RowsItemsParams::fav_progress_dy,
                            RowsItemsParams::view_progress_width,
                            RowsItemsParams::view_progress_height)); // total

                    $stickers[] = Rows_Factory::add_regular_sticker_rect(
                        RowsItemsParams::view_viewed_color,
                        Rows_Factory::r(0,
                            RowsItemsParams::fav_progress_dy,
                            round(RowsItemsParams::view_progress_width * $item['view_progress']),
                            RowsItemsParams::view_progress_height)); // viewed
                }

                $items[] = Rows_Factory::add_regular_item(
                    $id,
                    $channel->get_icon_url(),
                    $item['program_title'],
                    $stickers);
            }
        }

        // create view history group
        if (!empty($items)) {
            $new_rows = $this->create_rows($items,
                json_encode(array('group_id' => HISTORY_GROUP_ID)),
                TR::t('tv_screen_continue'),
                TR::t('tv_screen_continue_view'),
                null,
                TitleRowsParams::history_caption_color
            );

            foreach ($new_rows as $row) {
                $rows[] = $row;
            }
        }

        //hd_debug_print("History rows: " . count($rows));
        return $rows;
    }

    /**
     * @return array|null
     */
    private function get_favorites_rows()
    {
        hd_debug_print(null, true);

        $group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
        if (is_null($group)) {
            hd_debug_print("Favorites group not found");
            return null;
        }

        if ($group->is_disabled()) {
            hd_debug_print("Favorites group disabled");
            return null;
        }

        foreach ($group->get_items_order() as $channel_id) {
            $channel = $this->plugin->tv->get_channel($channel_id);
            if (is_null($channel) || $channel->is_disabled()) continue;

            $items[] = Rows_Factory::add_regular_item(
                json_encode(array('group_id' => FAVORITES_GROUP_ID, 'channel_id' => $channel_id)),
                $channel->get_icon_url(),
                $channel->get_title()
            );
        }

        if (empty($items)) {
            return null;
        }

        return $this->create_rows($items,
            json_encode(array('group_id' => FAVORITES_GROUP_ID)),
            $group->get_title(),
            $group->get_title(),
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
            TitleRowsParams::fav_caption_color
        );
    }

    /**
     * @return array|null
     */
    private function get_changed_channels_rows()
    {
        hd_debug_print(null, true);

        $group = $this->plugin->tv->get_special_group(CHANGED_CHANNELS_GROUP_ID);
        if (is_null($group)) {
            hd_debug_print("Changed channels group not found");
            return null;
        }

        if ($group->is_disabled()) {
            hd_debug_print("Changed channels group disabled");
            return null;
        }

        $changed = $this->plugin->tv->get_changed_channels();
        if (empty($changed)) {
            return null;
        }

        $new_channels = $this->plugin->tv->get_changed_channels('new');
        hd_debug_print("New channels: " . raw_json_encode($new_channels), true);
        $removed_channels = $this->plugin->tv->get_changed_channels('removed');
        hd_debug_print("Removed channels: " . raw_json_encode($removed_channels), true);

        $bg = Rows_Factory::add_regular_sticker_rect(
            RowsItemsParams::fav_sticker_bg_color,
            Rows_Factory::r(
                0,
                0,
                RowsItemsParams::fav_sticker_bg_width,
                RowsItemsParams::fav_sticker_bg_width));
        $added_stickers[] = $bg;

        $added_stickers[] = Rows_Factory::add_regular_sticker_image(
            get_image_path('page_plus_btn.png'),
            Rows_Factory::r(
                0,
                2,
                RowsItemsParams::fav_sticker_icon_width,
                RowsItemsParams::fav_sticker_icon_height));

        $removed_stickers[] = $bg;
        $removed_stickers[] = Rows_Factory::add_regular_sticker_image(
            get_image_path('page_minus_btn.png'),
            Rows_Factory::r(
                0,
                2,
                RowsItemsParams::fav_sticker_icon_width,
                RowsItemsParams::fav_sticker_icon_height));

        foreach ($this->plugin->tv->get_channels($new_channels) as $channel) {
            if (is_null($channel) || $channel->is_disabled()) continue;

            $items[] = Rows_Factory::add_regular_item(
                json_encode(array('group_id' => CHANGED_CHANNELS_GROUP_ID, 'channel_id' => $channel->get_id())),
                $channel->get_icon_url(),
                $channel->get_title(),
                $added_stickers
            );
        }

        $square_icons = $this->plugin->get_bool_setting(PARAM_SQUARE_ICONS, false)
            ? RowsItemsParams::icon_sq_loading_failed_url
            : RowsItemsParams::icon_loading_failed_url;

        foreach ($removed_channels as $item) {
            $items[] = Rows_Factory::add_regular_item(
                json_encode(array('group_id' => CHANGED_CHANNELS_GROUP_ID, 'channel_id' => $item)),
                $square_icons,
                $this->plugin->tv->get_known_channels()->get($item),
                $removed_stickers
            );
        }

        if (empty($items)) {
            return null;
        }

        return $this->create_rows($items,
            json_encode(array('group_id' => CHANGED_CHANNELS_GROUP_ID)),
            $group->get_title(),
            $group->get_title(),
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
            TitleRowsParams::fav_caption_color
        );
    }

    /**
     * @return array|null
     */
    private function get_all_channels_row()
    {
        hd_debug_print(null, true);

        $all_channels_group = $this->plugin->tv->get_special_group(ALL_CHANNEL_GROUP_ID);
        if (is_null($all_channels_group)) {
            hd_debug_print("All channels group not found");
            return null;
        }

        if ($all_channels_group->is_disabled()) {
            hd_debug_print("All channels group disabled");
            return null;
        }

        $row_item_width = $this->plugin->get_bool_setting(PARAM_SQUARE_ICONS, false) ? RowsItemsParams::width_sq : RowsItemsParams::width;

        $fav_stickers[] = Rows_Factory::add_regular_sticker_rect(
            RowsItemsParams::fav_sticker_bg_color,
            Rows_Factory::r(
                $row_item_width - RowsItemsParams::fav_sticker_bg_width - 21,
                0,
                RowsItemsParams::fav_sticker_bg_width,
                RowsItemsParams::fav_sticker_bg_width));

        $fav_stickers[] = Rows_Factory::add_regular_sticker_image(
            get_image_path(RowsItemsParams::fav_sticker_icon_url),
            Rows_Factory::r(
                $row_item_width - RowsItemsParams::fav_sticker_icon_width - 23,
                2,
                RowsItemsParams::fav_sticker_icon_width,
                RowsItemsParams::fav_sticker_icon_height));

        $channels_order = $all_channels_group->get_group_enabled_channels();

        $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);

        $items = array();
        foreach ($channels_order as $channel) {
            $items[] = Rows_Factory::add_regular_item(
                json_encode(array('group_id' => ALL_CHANNEL_GROUP_ID, 'channel_id' => $channel->get_id())),
                $channel->get_icon_url(),
                $channel->get_title(),
                $fav_group->in_items_order($channel->get_id()) ? $fav_stickers : null
            );
        }

        if (empty($items)) {
            return null;
        }

        return $this->create_rows($items,
            json_encode(array('group_id' => ALL_CHANNEL_GROUP_ID)),
            $all_channels_group->get_title(),
            $all_channels_group->get_title(),
            User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER)
        );
    }

    /**
     * @return array|null
     */
    private function get_regular_rows()
    {
        hd_debug_print(null, true);

        $row_item_width = $this->plugin->get_bool_setting(PARAM_SQUARE_ICONS, false) ? RowsItemsParams::width_sq : RowsItemsParams::width;
        $action_enter = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);

        $fav_stickers[] = Rows_Factory::add_regular_sticker_rect(
            RowsItemsParams::fav_sticker_bg_color,
            Rows_Factory::r(
                $row_item_width - RowsItemsParams::fav_sticker_bg_width - 21,
                0,
                RowsItemsParams::fav_sticker_bg_width,
                RowsItemsParams::fav_sticker_bg_width));

        $fav_stickers[] = Rows_Factory::add_regular_sticker_image(
            get_image_path(RowsItemsParams::fav_sticker_icon_url),
            Rows_Factory::r(
                $row_item_width - RowsItemsParams::fav_sticker_icon_width - 23,
                2,
                RowsItemsParams::fav_sticker_icon_width,
                RowsItemsParams::fav_sticker_icon_height));

        $rows = array();
        $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
        /** @var Group $group */
        /** @var Channel $channel */
        foreach ($this->plugin->tv->get_groups($this->plugin->tv->get_groups_order()) as $group) {
            if (is_null($group)) continue;

            $group_id = $group->get_id();
            $items = array();
            foreach ($group->get_items_order() as $channel_id) {
                $channel = $this->plugin->tv->get_channel($channel_id);
                if (is_null($channel) || $channel->is_disabled()) continue;

                $items[] = Rows_Factory::add_regular_item(
                    json_encode(array('group_id' => $group_id, 'channel_id' => $channel_id)),
                    $channel->get_icon_url(),
                    $channel->get_title(),
                    $fav_group->in_items_order($channel_id) ? $fav_stickers : null
                );
            }

            if (empty($items)) continue;

            $new_rows = $this->create_rows($items,
                json_encode(array('group_id' => $group_id)),
                $group->get_title(),
                $group->get_title(),
                $action_enter
            );

            foreach ($new_rows as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array $items
     * @param string $row_id
     * @param string $title
     * @param string $caption
     * @param array|null $action
     * @param string|null $color
     * @return array
     */
    private function create_rows($items, $row_id, $title, $caption, $action, $color = null)
    {
        $rows = array();
        $rows[] = Rows_Factory::title_row(
            $row_id,
            $caption,
            $row_id,
            TitleRowsParams::width, TitleRowsParams::height,
            is_null($color) ? TitleRowsParams::def_caption_color : $color,
            TitleRowsParams::font_size,
            TitleRowsParams::left_padding,
            0, 0,
            TitleRowsParams::fade_enabled,
            TitleRowsParams::fade_color,
            TitleRowsParams::lite_fade_color);

        for ($i = 0, $iMax = count($items); $i < $iMax; $i += PaneParams::max_items_in_row) {
            $row_items = array_slice($items, $i, PaneParams::max_items_in_row);
            $rows[] = Rows_Factory::regular_row(
                json_encode(array('row_ndx' => (int)($i / PaneParams::max_items_in_row), 'row_id' => $row_id)),
                $row_items,
                'common',
                null,
                $title,
                $row_id,
                RowsParams::width,
                RowsParams::height,
                RowsParams::height - TitleRowsParams::height,
                RowsParams::left_padding,
                RowsParams::inactive_left_padding,
                RowsParams::right_padding,
                RowsParams::hide_captions,
                false,
                RowsParams::fade_enable,
                null,
                $action,
                RowsParams::fade_icon_mix_color,
                RowsParams::fade_icon_mix_alpha,
                RowsParams::lite_fade_icon_mix_alpha,
                RowsParams::fade_caption_color
            );
        }

        return $rows;
    }

    /**
     * @param $user_input
     * @return array
     */
    protected function do_popup_menu($user_input)
    {
        hd_debug_print(null, true);

        // Dump $user_input message for NewUI. So... What you smoke dear Dune developer?

        //selected_header => 1 - user in the leftmost list
        //selected_row_id => {"row_ndx":0,"row_id":"{\"group_id\":\"TV Group\"}"}
        //selected_header_id => H2 - stupid selected_title_ndx with H prefix...
        //selected_title_ndx => 2
        //parent_sel_state => - Why this stupid shit is used and sent to us?

        //selected_header => 0 - user in selected item in row
        //selected_row_id => {"row_ndx":0,"row_id":"{\"group_id\":\"TV Group\"}"}
        //selected_item_id => {"group_id":"TV Group","channel_id":"e2a79c7b"}
        //selected_title_ndx => 2
        //parent_sel_state => - Why this stupid shit is used and sent to us?

        // show changing playlist and xmltv source in any place
        $menu_items = array();
        $add_param = null;
        if (isset($user_input->{ACTION_CHANGE_PLAYLIST})) {
            // popup menu for change playlist
            hd_debug_print("change playlist menu", true);
            $menu_items = $this->plugin->playlist_menu($this);
        } else if (isset($user_input->{ACTION_CHANGE_EPG_SOURCE})) {
            // popup menu for change epg source
            hd_debug_print("change epg source menu", true);
            $menu_items = $this->plugin->epg_source_menu($this);
        } else if (isset($user_input->{ACTION_SORT_POPUP})) {
            hd_debug_print("sort menu", true);
            $media_url = MediaURL::decode($user_input->selected_item_id);
            if ($this->plugin->tv->get_group($media_url->group_id) !== null) {
                $menu_items = $this->plugin->sort_menu($this);
            }
        } else if (isset($user_input->selected_item_id)) {
            // popup menu for selected chennel in row
            hd_debug_print("in channels rows", true);
            $media_url = MediaURL::decode($user_input->selected_item_id);
            hd_debug_print($media_url->get_media_url_str());
            if ($media_url->group_id === HISTORY_GROUP_ID) {
                hd_debug_print("in history rows", true);
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_REMOVE, TR::t('delete'), "remove.png");
            } else if ($media_url->group_id === FAVORITES_GROUP_ID && !is_apk()) {
                hd_debug_print("in favorites rows", true);
                $menu_items[] = $this->plugin->create_menu_item($this, PLUGIN_FAVORITES_OP_REMOVE, TR::t('delete_from_favorite'), "star.png");
            } else {
                hd_debug_print("Selected channel in row: $media_url->channel_id", true);
                $channel_id = $media_url->channel_id;

                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_channel'), "remove.png");
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

                if (is_android() && !is_apk()) {
                    $is_external = $this->plugin->tv->get_channels_for_ext_player()->in_order($channel_id);
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_EXTERNAL_PLAYER,
                        TR::t('tv_screen_external_player'),
                        ($is_external ? "play.png" : null)
                    );

                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_INTERNAL_PLAYER,
                        TR::t('tv_screen_internal_player'),
                        ($is_external ? null : "play.png")
                    );

                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                }

                if ($this->plugin->get_bool_setting(PARAM_PER_CHANNELS_ZOOM)) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ZOOM_POPUP_MENU, TR::t('video_aspect_ratio'), "aspect.png");
                }
            }

            if (is_apk()) {
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                if ($media_url->group_id === FAVORITES_GROUP_ID) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        PLUGIN_FAVORITES_OP_MOVE_UP, TR::t('left'), PaneParams::fav_button_green);
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        PLUGIN_FAVORITES_OP_MOVE_DOWN, TR::t('right'), PaneParams::fav_button_yellow);
                }

                $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
                $is_in_favorites = $fav_group->in_items_order($media_url->channel_id);
                $caption = $is_in_favorites ? TR::t('delete_from_favorite') : TR::t('add_to_favorite');
                $action = $is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $menu_items[] = $this->plugin->create_menu_item($this, $action, $caption, PaneParams::fav_button_blue);
            }

            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_REFRESH_SCREEN, TR::t('refresh'), "refresh.png", $add_param);
        } else {
            // popup menu for left side list
            hd_debug_print("in menu side", true);
            $menu_items[] = $this->plugin->create_menu_item($this,
                null,
                TR::t('playlist_name_msg__1', $this->plugin->get_current_playlist()->name));

            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

            $media_url = MediaURL::decode($user_input->selected_row_id);
            if (isset($media_url->row_id)) {
                $row_id = json_decode($media_url->row_id);
                hd_debug_print("Selected group in left menu: $row_id->group_id", true);
                if ($row_id->group_id === HISTORY_GROUP_ID) {
                    hd_debug_print("in history category", true);
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_history'), "brush.png");
                } else if ($row_id->group_id === FAVORITES_GROUP_ID) {
                    hd_debug_print("in favorites category", true);
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "brush.png");
                } else if ($row_id->group_id === CHANGED_CHANNELS_GROUP_ID) {
                    hd_debug_print("in changed channels category", true);
                    $menu_items[] = $this->plugin->create_menu_item($this, PLUGIN_FAVORITES_OP_REMOVE, TR::t('clear_changed'), "brush.png");
                } else if ($row_id->group_id !== ALL_CHANNEL_GROUP_ID) {
                    hd_debug_print("in all channels category", true);
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_group'), "hide.png");
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_SORT_POPUP, TR::t('sort_popup_menu'), "sort.png");
                }
            }

            if ($this->plugin->get_playlists()->size()) {
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_CHANGE_PLAYLIST, TR::t('change_playlist'), "playlist.png");
            }

            if ($this->plugin->get_all_xmltv_sources()->size()) {
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_CHANGE_EPG_SOURCE, TR::t('change_epg_source'), "epg.png");
            }

            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_TOGGLE_ICONS_TYPE, TR::t('tv_screen_toggle_icons_aspect'), "image.png");

            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_REFRESH_SCREEN, TR::t('refresh'), "refresh.png", $add_param);
        }

        return $menu_items;
    }
}
