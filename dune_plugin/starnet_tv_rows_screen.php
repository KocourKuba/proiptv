<?php
require_once 'lib/tv/ext_epg_program.php';

require_once 'lib/epfs/abstract_rows_screen.php';
require_once 'lib/epfs/rows_factory.php';
require_once 'lib/epfs/gcomps_factory.php';
require_once 'lib/epfs/gcomp_geom.php';
require_once 'lib/epfs/playback_points.php';

class Starnet_Tv_Rows_Screen extends Abstract_Rows_Screen implements User_Input_Handler
{
    const ID = 'rows_epf';

    ///////////////////////////////////////////////////////////////////////////

    private $removed_playback_point;
    private $clear_playback_points = false;

    public $need_update_epf_mapping_flag = false;

    ///////////////////////////////////////////////////////////////////////////

    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct(self::ID, $plugin);
    }

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
        $group_id = isset($media_url->group_id) ? $media_url->group_id : null;
        $channel_id = isset($media_url->channel_id) ? $media_url->channel_id : null;

        if (is_null($channel_id) || empty($group_id))
            return null;

        $channel = $this->plugin->tv->get_channel($channel_id);
        if (is_null($channel)) {
            hd_print(__METHOD__ . ": Unknown channel $channel_id");
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

        if (is_null($epg_data = $this->plugin->tv->get_program_info($channel_id, -1, $plugin_cookies))) {

            hd_print(__METHOD__ . ": no epg data");
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

            //foreach ($epg_data as $key=>$value) hd_print("$key => $value");
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
        if ($group_id === PLAYBACK_HISTORY_GROUP_ID || $group_id === ALL_CHANNEL_GROUP_ID) {

            // blue button image (D)
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_top_left(PaneParams::fav_btn_width, PaneParams::fav_btn_height, $dx, $dy_icon),
                null,
                PaneParams::fav_button_blue);

            $dx += 55;
            $defs[] = GComps_Factory::label(GComp_Geom::place_top_left(PaneParams::info_width, -1, $dx, $dy_txt), // label
                null,
                TR::load_string(Favorites_Group::FAV_CHANNEL_GROUP_CAPTION),
                1,
                PaneParams::fav_btn_font_color,
                PaneParams::fav_btn_font_size
            );
        } else {

            if ($group_id === FAV_CHANNEL_GROUP_ID) {
                $order = $this->plugin->tv->get_favorites()->get_order();
            } else {
                /** @var Group $group */
                $group = $this->plugin->tv->get_group($group_id);
                $order = $group->get_items_order()->get_order();
            }

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

        return array
        (
            'defs' => array($pane_def),
            'fanart_url' => empty($fanart_url) ? '' : $fanart_url,
        );
    }

    /**
     * @throws Exception
     */
    public function get_folder_view_for_epf(&$plugin_cookies)
    {
        $media_url = MediaURL::decode(self::ID);
        $this->plugin->tv->get_tv_info($media_url, $plugin_cookies);

        return $this->get_folder_view($media_url, $plugin_cookies);
    }

    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        return null;
    }

    public function get_next_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        return null;
    }

    /**
     * @throws Exception
     */
    public function get_rows_pane(MediaURL $media_url, $plugin_cookies)
    {
        hd_print(__METHOD__);
        $rows = array();

        $channels_rows = $this->get_regular_rows();
        if (is_null($channels_rows)) {
            hd_print(__METHOD__ . ": no channels rows");
            return null;
        }

        $history_rows = $this->get_history_rows($plugin_cookies);
        if (!is_null($history_rows)) {
            $rows = array_merge($rows, $history_rows);
            //hd_print(__METHOD__ . ": added history: " . count($history_rows) . " rows");
        }

        $favorites_rows = $this->get_favorites_rows($plugin_cookies);
        if (!is_null($favorites_rows)) {
            //hd_print(__METHOD__ . ": added favorites: " . count($favorites_rows) . " rows");
            $rows = array_merge($rows, $favorites_rows);
        }

        $all_channels_rows = $this->get_all_channels_row($plugin_cookies);
        if (!is_null($all_channels_rows)) {
            $rows = array_merge($rows, $all_channels_rows);
            //hd_print(__METHOD__ . ": added all channels: " . count($all_channels_rows) . " rows");
        }

        $rows = array_merge($rows, $channels_rows);
        //hd_print(__METHOD__ . ": added channels: " . count($channels_rows) . " rows");

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

        $square_icons = ($this->plugin->get_settings(PARAM_SQUARE_ICONS, SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on);
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

        $sel_params = Rows_Factory::variable_params(
            RowsItemsParams::width,
            RowsItemsParams::height,
            5,
            $icon_width + 12,
            round(($icon_width + 12) / $icon_prop),
            0,
            RowsItemsParams::caption_dy + 10,
            RowsItemsParams::sel_caption_color,
            RowsItemsParams::caption_font_size
        );

        $width = round((RowsItemsParams::width * PaneParams::max_items_in_row - PaneParams::group_list_width) / PaneParams::max_items_in_row);
        $inactive_icon_width = round(($icon_width * PaneParams::max_items_in_row - PaneParams::group_list_width) / PaneParams::max_items_in_row)
            + round((RowsItemsParams::width - $icon_width) / $icon_prop);

        $inactive_params = Rows_Factory::variable_params(
            $width,
            round($width / RowsItemsParams::width * RowsItemsParams::height), 0,
            $inactive_icon_width,
            round($inactive_icon_width / $icon_prop) - 10,
            0,
            RowsItemsParams::caption_dy,
            RowsItemsParams::inactive_caption_color,
            RowsItemsParams::caption_font_size
        );

        $params = Rows_Factory::item_params(
            $def_params,
            $sel_params,
            $inactive_params,
            $this->plugin->get_image_path(RowsItemsParams::icon_loading_url),
            $this->plugin->get_image_path(RowsItemsParams::icon_loading_failed_url),
            RowsItemsParams::caption_max_num_lines,
            RowsItemsParams::caption_line_spacing,
            Rows_Factory::margins(6, 2, 2, 2)
        );

        Rows_Factory::set_item_params_template($pane, 'common', $params);

        return $pane;
    }

    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(
            GUI_EVENT_KEY_ENTER               => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER),
            GUI_EVENT_KEY_B_GREEN             => User_Input_Handler_Registry::create_action($this, PLUGIN_FAVORITES_OP_MOVE_UP),
            GUI_EVENT_KEY_C_YELLOW            => User_Input_Handler_Registry::create_action($this, PLUGIN_FAVORITES_OP_MOVE_DOWN),
            GUI_EVENT_KEY_D_BLUE              => User_Input_Handler_Registry::create_action($this, PLUGIN_FAVORITES_OP_ADD),
            GUI_EVENT_KEY_POPUP_MENU          => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU),
            GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE => User_Input_Handler_Registry::create_action($this, GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE),
        );
    }

    /**
     * @throws Exception
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);

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
                $this->plugin->playback_points->update_point(null);
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case GUI_EVENT_KEY_ENTER:
                $tv_play_action = Action_Factory::tv_play($media_url);

                if (isset($user_input->action_origin)) {
                    Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
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
                    empty($info_children['fanart_url']) ? $this->plugin->get_image_path(PaneParams::vod_bg_url) : $info_children['fanart_url'],
                    $this->plugin->get_image_path(PaneParams::vod_bg_url),
                    $this->plugin->get_image_path(PaneParams::vod_mask_url),
                    array("plugin_tv://" . get_plugin_name() . "/$user_input->item_id")
                );

            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($user_input->choose_playlist)) {
                    $menu_items = array();
                    $cur = $this->plugin->get_current_playlist();
                    foreach ($this->plugin->get_playlists()->get_order() as $key => $playlist) {
                        if ($key !== 0 && ($key % 15) === 0)
                            $this->create_menu_item($menu_items, GuiMenuItemDef::is_separator);

                        $icon = ($cur !== $playlist) ? null : "link.png";
                        $ar = explode('/', $playlist);
                        $playlist = end($ar);
                        $this->create_menu_item($menu_items, ACTION_FOLDER_SELECTED, $playlist, $icon, array('playlist_idx' => $key));
                    }
                    return Action_Factory::show_popup_menu($menu_items);
                }

                if (isset($user_input->selected_item_id)) {
                    $common_menu = array();
                    if ($media_url->group_id === PLAYBACK_HISTORY_GROUP_ID) {
                        $this->create_menu_item($menu_items, ACTION_REMOVE_PLAYBACK_POINT, TR::t('delete'), "remove.png");
                    } else if ($media_url->group_id === FAV_CHANNEL_GROUP_ID) {
                        $this->create_menu_item($menu_items, PLUGIN_FAVORITES_OP_REMOVE, TR::t('delete'), "star.png");
                    } else {
                        $channel_id = $media_url->channel_id;
                        hd_print(__METHOD__ . ": Selected channel id: $channel_id");

                        $is_in_favorites = $this->plugin->tv->get_favorites()->in_order($channel_id);
                        $caption = $is_in_favorites ? TR::t('delete') : TR::t('add');
                        $add_action = $is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;

                        if (is_apk()) {
                            $this->create_menu_item($common_menu, $add_action, $caption, "star.png");
                        }

                        $this->create_menu_item($common_menu, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_channel'), "remove.png");

                        if ($media_url->group_id !== ALL_CHANNEL_GROUP_ID) {
                            $this->create_menu_item($menu_items, ACTION_ITEMS_SORT, TR::t('sort_items'), "sort.png");
                        }

                        $zoom_data = $this->plugin->get_settings(PARAM_CHANNELS_ZOOM, array());
                        $current_idx = (string)(isset($zoom_data[$channel_id]) ? $zoom_data[$channel_id] : DuneVideoZoomPresets::not_set);

                        //hd_print(__METHOD__ . ": Current idx: $current_idx");

                        $this->create_menu_item($common_menu, GuiMenuItemDef::is_separator);

                        foreach (DuneVideoZoomPresets::$zoom_ops as $idx => $zoom_item) {
                            $this->create_menu_item($common_menu, ACTION_ZOOM_APPLY, $zoom_item,
                                strcmp($idx, $current_idx) !== 0 ? null : "aspect.png",
                                array(ACTION_ZOOM_SELECT => (string)$idx));
                        }
                    }

                    if (!is_apk()) {
                        $this->create_menu_item($menu_items, ACTION_EXTERNAL_PLAYER, TR::t('tv_screen_external_player'), "play.png");
                    }
                    $this->create_menu_item($menu_items, GuiMenuItemDef::is_separator);
                    $menu_items = array_merge($menu_items, $common_menu);
                } else {
                    if ($media_url->group_id === PLAYBACK_HISTORY_GROUP_ID) {
                        $this->create_menu_item($menu_items, ACTION_ITEMS_CLEAR, TR::t('clear_history'), "brush.png");
                    } else if ($media_url->group_id === FAV_CHANNEL_GROUP_ID) {
                        $this->create_menu_item($menu_items, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "star.png");
                    } else if ($media_url->group_id !== ALL_CHANNEL_GROUP_ID) {
                        $this->create_menu_item($menu_items, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_group'),"hide.png");
                    }

                    if ($this->plugin->get_playlists()->size()) {
                        $this->create_menu_item($menu_items, GuiMenuItemDef::is_separator);
                        $this->create_menu_item($menu_items, ACTION_CHANGE_PLAYLIST, TR::t('setup_channels_src_playlists'),"playlist.png");
                    }
                }

                $this->create_menu_item($menu_items, GuiMenuItemDef::is_separator);
                $this->create_menu_item($menu_items, ACTION_REFRESH_SCREEN, TR::t('refresh'),"refresh.png");

                return Action_Factory::show_popup_menu($menu_items);

            case PLUGIN_FAVORITES_OP_ADD:
            case PLUGIN_FAVORITES_OP_REMOVE:
                if (!isset($media_url->group_id) || $media_url->group_id === PLAYBACK_HISTORY_GROUP_ID)
                    break;

                if ($control_id === PLUGIN_FAVORITES_OP_ADD) {
                    $is_in_favorites = $this->plugin->tv->get_favorites()->in_order($media_url->channel_id);
                    $control_id = $is_in_favorites ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                }

                return $this->plugin->change_tv_favorites($control_id, $media_url->channel_id, $plugin_cookies);

            case PLUGIN_FAVORITES_OP_MOVE_UP:
            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                if (isset($user_input->selected_item_id)) {
                    if (isset($media_url->group_id)) {
                        if ($media_url->group_id === FAV_CHANNEL_GROUP_ID) {
                            return $this->plugin->change_tv_favorites($control_id, $media_url->channel_id, $plugin_cookies);
                        }

                        if ($media_url->group_id !== PLAYBACK_HISTORY_GROUP_ID && $media_url->group_id !== ALL_CHANNEL_GROUP_ID) {
                            $direction = $control_id === PLUGIN_FAVORITES_OP_MOVE_UP ? Ordered_Array::UP : Ordered_Array::DOWN;
                            $group = $this->plugin->tv->get_group($media_url->group_id);
                            if (!is_null($group) && $group->get_items_order()->arrange_item($media_url->channel_id, $direction)) {
                                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                            }
                        }
                    }
                } else {
                    $direction = $control_id === PLUGIN_FAVORITES_OP_MOVE_UP ? Ordered_Array::UP : Ordered_Array::DOWN;
                    if ($this->plugin->tv->get_groups_order()->arrange_item($media_url->group_id, $direction)) {
                        return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                    }
                }
                break;

            case ACTION_ITEMS_SORT:
                $group = $this->plugin->tv->get_group($media_url->group_id);
                $group->get_items_order()->sort_order();
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_REFRESH_SCREEN:
                Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
                return Starnet_Epfs_Handler::invalidate_folders();

            case ACTION_REMOVE_PLAYBACK_POINT:
                $this->removed_playback_point = $media_url->get_raw_string();
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_ITEMS_CLEAR:
                if ($media_url->group_id === PLAYBACK_HISTORY_GROUP_ID) {
                    $this->clear_playback_points = true;
                    $this->plugin->playback_points->clear_points();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($media_url->group_id === FAV_CHANNEL_GROUP_ID) {
                    return $this->plugin->change_tv_favorites(ACTION_ITEMS_CLEAR, $media_url->channel_id, $plugin_cookies);
                }

                break;

            case ACTION_ITEM_DELETE:
                hd_print(__METHOD__ . ": Hide $media_url->group_id");
                $this->plugin->tv->disable_group($media_url->group_id);
                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_CHANGE_PLAYLIST:
                hd_print(__METHOD__ . ": Start event popup menu for playlist");
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array('choose_playlist' => true));

            case ACTION_FOLDER_SELECTED:
                if (isset($user_input->playlist_idx)) {
                    $this->plugin->set_playlists_idx($user_input->playlist_idx);
                    return $this->plugin->tv->reload_channels($this, $plugin_cookies, Starnet_Epfs_Handler::invalidate_folders());
                }
                break;

            case ACTION_ZOOM_APPLY:
                if (!isset($user_input->{ACTION_ZOOM_SELECT})) break;

                $channel_id = $media_url->channel_id;
                $zoom_select = $user_input->{ACTION_ZOOM_SELECT};
                $zoom_data = $this->plugin->get_settings(PARAM_CHANNELS_ZOOM, array());
                if ($zoom_select === DuneVideoZoomPresets::not_set) {
                    hd_print(__METHOD__ . ": Zoom preset removed for channel: $channel_id");
                    unset ($zoom_data[$channel_id]);
                } else {
                    hd_print(__METHOD__ . ": Zoom preset $zoom_select for channel: $channel_id");
                    $zoom_data[$channel_id] = $zoom_select;
                }

                $this->plugin->set_settings(PARAM_CHANNELS_ZOOM, $zoom_data);
                return Starnet_Epfs_Handler::invalidate_folders();

            case ACTION_EXTERNAL_PLAYER:
                try {
                    $url = $this->plugin->generate_stream_url(
                        isset($media_url->archive_tm) ? $media_url->archive_tm : -1,
                        $this->plugin->tv->get_channel($media_url->channel_id));
                    $url = str_replace("ts://", "", $url);
                    $param_pos = strpos($url, '|||dune_params');
                    $url =  $param_pos!== false ? substr($url, 0, $param_pos) : $url;
                    $cmd = 'am start -d "' . $url . '" -t "video/*" -a android.intent.action.VIEW 2>&1';
                    hd_print(__METHOD__ . ": play movie in the external player: $cmd");
                    exec($cmd, $output);
                    hd_print(__METHOD__ . ": external player exec result code" . HD::ArrayToStr($output));
                } catch (Exception $ex) {
                    hd_print(__METHOD__ . ": Movie can't played, exception info: " . $ex->getMessage());
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }
                break;
        }

        return null;
    }

    /**
     * @param $menu_items array
     * @param $action_id string
     * @param $caption string
     * @param $icon string
     * @param $add_params array|null
     * @return void
     */
    private function create_menu_item(&$menu_items, $action_id, $caption = null, $icon = null, $add_params = null)
    {
        if ($action_id === GuiMenuItemDef::is_separator) {
            $menu_items[] = array($action_id => true);
        } else {
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                $action_id, $caption, ($icon === null) ? null : $this->plugin->get_image_path($icon), $add_params);
        }
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param $plugin_cookies
     * @return array|null
     */
    private function get_history_rows($plugin_cookies)
    {
        //hd_print(__METHOD__);
        if (!$this->plugin->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::SETUP_ACTION_SHOW_HISTORY)) {
            hd_print(__METHOD__ . ": History group disabled");
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
        foreach ($this->plugin->playback_points->get_all() as $channel_id => $channel_ts) {
            if (is_null($channel = $this->plugin->tv->get_channel($channel_id))) continue;

            $prog_info = $this->plugin->tv->get_program_info($channel_id, $channel_ts, $plugin_cookies);
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

            //hd_print("Starnet_Tv_Rows_Screen::get_history_rows: channel id: $channel_id (epg: '$title') time mark: $channel_ts progress: " . $progress * 100 . "%");
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
                $id = json_encode(array('group_id' => PLAYBACK_HISTORY_GROUP_ID, 'channel_id' => $item['channel_id'], 'archive_tm' => $item['archive_tm']));
                //hd_print("MediaUrl info for {$item['channel_id']} - $id");
                if (isset($this->removed_playback_point))
                    if ($this->removed_playback_point === $id) {
                        $this->removed_playback_point = null;
                        $this->plugin->playback_points->erase_point($item['channel_id']);
                        continue;
                    }

                $stickers = null;

                if ($item['view_progress'] > 0) {
                    // item size 229x142
                    if (!empty($item['program_icon_url'])) {
                        // add small channel logo
                        $rect = Rows_Factory::r(129, 0, 100, 64);
                        Rows_Factory::add_regular_sticker_rect($stickers, RowsItemsParams::fav_sticker_logo_bg_color, $rect);
                        Rows_Factory::add_regular_sticker_image($stickers, $channel->get_icon_url(), $rect);
                    }

                    // add progress indicator
                    Rows_Factory::add_regular_sticker_rect(
                        $stickers,
                        RowsItemsParams::view_total_color,
                        Rows_Factory::r(0,
                            RowsItemsParams::fav_progress_dy,
                            RowsItemsParams::view_progress_width,
                            RowsItemsParams::view_progress_height)); // total

                    Rows_Factory::add_regular_sticker_rect(
                        $stickers,
                        RowsItemsParams::view_viewed_color,
                        Rows_Factory::r(0,
                            RowsItemsParams::fav_progress_dy,
                            round(RowsItemsParams::view_progress_width * $item['view_progress']),
                            RowsItemsParams::view_progress_height)); // viewed
                }

                Rows_Factory::add_regular_item(
                    $items,
                    $id,
                    $channel->get_icon_url(),
                    $item['program_title'],
                    $stickers);
            }
        }

        // create view history group
        if (!empty($items)) {
            $new_rows = $this->create_rows($items,
                json_encode(array('group_id' => PLAYBACK_HISTORY_GROUP_ID)),
                TR::t('tv_screen_continue'),
                TR::t('tv_screen_continue_view'),
                null,
                TitleRowsParams::history_caption_color
            );

            foreach ($new_rows as $row) {
                $rows[] = $row;
            }
        }

        //hd_print(__METHOD__ . ": History rows: " . count($rows));
        return $rows;
    }

    /**
     * @param $plugin_cookies
     * @return array|null
     */
    private function get_favorites_rows($plugin_cookies)
    {
        //hd_print(__METHOD__);
        if (!$this->plugin->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::SETUP_ACTION_SHOW_FAVORITES)) {
            hd_print(__METHOD__ . ": Favorites group disabled");
            return null;
        }

        $group = $this->plugin->tv->get_special_group(FAV_CHANNEL_GROUP_ID);
        if (is_null($group)) {
            hd_print(__METHOD__ . ": Favorites group not found");
            return null;
        }

        $fav_count = $this->plugin->tv->get_favorites()->size();
        $fav_idx = 0;
        $rows = array();
        foreach ($this->plugin->tv->get_favorites()->get_order() as $channel_id) {
            $channel = $this->plugin->tv->get_channel($channel_id);
            if (is_null($channel) || $channel->is_disabled()) continue;

            Rows_Factory::add_regular_item(
                $items,
                json_encode(array('group_id' => $group->get_id(), 'channel_id' => $channel->get_id(), 'fav_idx' => "$fav_idx/$fav_count")),
                $channel->get_icon_url(),
                $channel->get_title()
            );
            $fav_idx++;
        }

        if (!empty($items)) {
            $action_enter = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);
            $new_rows = $this->create_rows($items,
                json_encode(array('group_id' => $group->get_id())),
                $group->get_title(),
                $group->get_title(),
                $action_enter,
                TitleRowsParams::fav_caption_color
            );

            foreach ($new_rows as $row) {
                $rows[] = $row;
            }
        }

        //hd_print(__METHOD__ . ": Favorites rows: " . count($rows));
        return $rows;
    }

    /**
     * @param $plugin_cookies
     * @return array|null
     */
    private function get_all_channels_row($plugin_cookies)
    {
        //hd_print(__METHOD__);
        if (!$this->plugin->is_special_groups_enabled($plugin_cookies, Starnet_Interface_Setup_Screen::SETUP_ACTION_SHOW_ALL)) {
            hd_print(__METHOD__ . ": All channels group disabled");
            return null;
        }

        /** @var Default_Group $group */
        $group = $this->plugin->tv->get_special_group(ALL_CHANNEL_GROUP_ID);
        if (is_null($group)) {
            hd_print(__METHOD__ . ": All channels group not found");
            return null;
        }

        /** @var Channel $channel */
        $rows = array();
        $items = array();
        $fav_stickers = null;
        $square_icons = ($this->plugin->get_settings(PARAM_SQUARE_ICONS, SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on);
        $row_item_width = $square_icons ? RowsItemsParams::width_sq : RowsItemsParams::width;

        Rows_Factory::add_regular_sticker_rect(
            $fav_stickers,
            RowsItemsParams::fav_sticker_bg_color,
            Rows_Factory::r(
                $row_item_width - RowsItemsParams::fav_sticker_bg_width - 21,
                0,
                RowsItemsParams::fav_sticker_bg_width,
                RowsItemsParams::fav_sticker_bg_width));

        Rows_Factory::add_regular_sticker_image(
            $fav_stickers,
            $this->plugin->get_image_path(RowsItemsParams::fav_sticker_icon_url),
            Rows_Factory::r(
                $row_item_width - RowsItemsParams::fav_sticker_icon_width - 23,
                2,
                RowsItemsParams::fav_sticker_icon_width,
                RowsItemsParams::fav_sticker_icon_height));

        foreach ($this->plugin->tv->get_channels() as $channel) {
            if ($channel->is_disabled()) continue;

            Rows_Factory::add_regular_item(
                $items,
                json_encode(array('group_id' => $group->get_id(), 'channel_id' => $channel->get_id())),
                $channel->get_icon_url(),
                $channel->get_title(),
                $this->plugin->tv->get_favorites()->in_order($channel->get_id()) ? $fav_stickers : null
            );
        }

        if (!empty($items)) {
            $action_enter = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);
            $new_rows = $this->create_rows($items,
                json_encode(array('group_id' => $group->get_id())),
                $group->get_title(),
                $group->get_title(),
                $action_enter
            );

            foreach ($new_rows as $row) {
                $rows[] = $row;
            }
        }

        //hd_print(__METHOD__ . ": All channels rows: " . count($rows));
        return $rows;
    }

    /**
     * @return array|null
     */
    private function get_regular_rows()
    {
        hd_print(__METHOD__);
        $groups = $this->plugin->tv->get_groups();
        if (is_null($groups))
            return null;

        $rows = array();
        $square_icons = ($this->plugin->get_settings(PARAM_SQUARE_ICONS, SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on);
        $row_item_width = $square_icons ? RowsItemsParams::width_sq : RowsItemsParams::width;

        /** @var Default_Group $group */
        /** @var Channel $channel */
        foreach ($this->plugin->tv->get_groups_order()->get_order() as $group_id) {
            $group = $groups->get($group_id);
            if (is_null($group)) continue;

            $items = array();
            $fav_stickers = null;

            Rows_Factory::add_regular_sticker_rect(
                $fav_stickers,
                RowsItemsParams::fav_sticker_bg_color,
                Rows_Factory::r(
                    $row_item_width - RowsItemsParams::fav_sticker_bg_width - 21,
                    0,
                    RowsItemsParams::fav_sticker_bg_width,
                    RowsItemsParams::fav_sticker_bg_width));

            Rows_Factory::add_regular_sticker_image(
                $fav_stickers,
                $this->plugin->get_image_path(RowsItemsParams::fav_sticker_icon_url),
                Rows_Factory::r(
                    $row_item_width - RowsItemsParams::fav_sticker_icon_width - 23,
                    2,
                    RowsItemsParams::fav_sticker_icon_width,
                    RowsItemsParams::fav_sticker_icon_height));

            foreach ($group->get_items_order()->get_order() as $channel_id) {
                $channel = $this->plugin->tv->get_channel($channel_id);
                if (is_null($channel) || $channel->is_disabled()) continue;

                Rows_Factory::add_regular_item(
                    $items,
                    json_encode(array('group_id' => $group->get_id(), 'channel_id' => $channel->get_id())),
                    $channel->get_icon_url(),
                    $channel->get_title(),
                    $this->plugin->tv->get_favorites()->in_order($channel->get_id()) ? $fav_stickers : null
                );
            }

            if (!empty($items)) {
                $action_enter = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);
                $new_rows = $this->create_rows($items,
                    json_encode(array('group_id' => $group->get_id())),
                    $group->get_title(),
                    $group->get_title(),
                    $action_enter
                );

                foreach ($new_rows as $row) {
                    $rows[] = $row;
                }
            }
        }

        //hd_print(__METHOD__ . ": Regular rows: " . count($rows));
        return $rows;
    }

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
}
