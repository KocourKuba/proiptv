<?php
require_once 'lib/abstract_preloaded_regular_screen.php';
require_once 'lib/tv/tv.php';

class Starnet_Tv_Favorites_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_favorites';

    /**
     * @return false|string
     */
    public static function get_media_url_str()
    {
        return MediaURL::encode(array(
                'screen_id' => self::ID,
                'is_favorites' => true)
        );
    }

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct(self::ID, $plugin);
        $plugin->create_screen($this);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $action_play = Action_Factory::tv_play();

        $move_backward_favorite_action = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
        $move_forward_favorite_action = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        $remove_favorite_action = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
        $show_popup = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        return array
        (
            GUI_EVENT_KEY_ENTER      => $action_play,
            GUI_EVENT_KEY_PLAY       => $action_play,
            GUI_EVENT_KEY_B_GREEN    => $move_backward_favorite_action,
            GUI_EVENT_KEY_C_YELLOW   => $move_forward_favorite_action,
            GUI_EVENT_KEY_D_BLUE     => $remove_favorite_action,
            GUI_EVENT_KEY_POPUP_MENU => $show_popup,
        );
    }

    /**
     * @return string
     */
    public function get_handler_id()
    {
        return self::ID . '_handler';
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        $channel_id = MediaURL::decode($user_input->selected_media_url)->channel_id;
        switch ($user_input->control_id) {

            case ACTION_ITEM_UP:
                $this->plugin->change_tv_favorites(PLUGIN_FAVORITES_OP_MOVE_UP, $channel_id, $plugin_cookies);
                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }
                break;

            case ACTION_ITEM_DOWN:
                $this->plugin->change_tv_favorites(PLUGIN_FAVORITES_OP_MOVE_DOWN, $channel_id, $plugin_cookies);
                $user_input->sel_ndx++;
                if ($user_input->sel_ndx >= $this->plugin->tv->get_favorites()->size()) {
                    $user_input->sel_ndx = $this->plugin->tv->get_favorites()->size() - 1;
                }
                break;

            case ACTION_ITEM_DELETE:
                $this->plugin->change_tv_favorites(PLUGIN_FAVORITES_OP_REMOVE, $channel_id, $plugin_cookies);
                break;

            case ACTION_ITEMS_CLEAR:
                $this->plugin->change_tv_favorites(ACTION_ITEMS_CLEAR, $channel_id, $plugin_cookies);
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                if (is_android() && !is_apk()) {
                    $this->create_menu_item($menu_items,ACTION_EXTERNAL_PLAYER, TR::t('vod_screen_external_player'), "play.png");
                    $this->create_menu_item($menu_items, GuiMenuItemDef::is_separator);
                }

                $this->create_menu_item($menu_items, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), null);

                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_EXTERNAL_PLAYER:
                try {
                    $channel = $this->plugin->tv->get_channel(MediaURL::decode($user_input->selected_media_url)->channel_id);
                    $url = $this->plugin->generate_stream_url(-1, $channel);
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
                return null;

            default:
                return null;
        }

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $post_action = Action_Factory::close_and_run(
            Action_Factory::open_folder(
                $user_input->parent_media_url,
                null,
                null,
                null,
                Action_Factory::update_regular_folder(
                    HD::create_regular_folder_range($this->get_all_folder_items($parent_media_url, $plugin_cookies)),
                    true,
                    $user_input->sel_ndx)
            )
        );

        Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
        $post_action = Starnet_Epfs_Handler::invalidate_folders(array($user_input->parent_media_url), $post_action);

        return Action_Factory::invalidate_folders(array(Starnet_Tv_Groups_Screen::get_media_url_str()), $post_action);

    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        $items = array();

        foreach ($this->plugin->tv->get_favorites()->get_order() as $channel_id) {
            if (!preg_match('/\S/', $channel_id)) {
                continue;
            }

            $channel = $this->plugin->tv->get_channel($channel_id);
            if (is_null($channel)) {
                hd_print(__METHOD__ . ": Unknown channel $channel_id");
                $this->plugin->change_tv_favorites(PLUGIN_FAVORITES_OP_REMOVE, $channel_id, $plugin_cookies);
                continue;
            }

            $items[] = array
            (
                PluginRegularFolderItem::media_url => MediaURL::encode(array(
                        'channel_id' => $channel->get_id(),
                        'group_id' => FAV_CHANNEL_GROUP_ID)
                ),
                PluginRegularFolderItem::caption => $channel->get_title(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $channel->get_icon_url(),
                    ViewItemParams::item_detailed_icon_path => $channel->get_icon_url(),
                ),
                PluginRegularFolderItem::starred => false,
            );
        }

        return $items;
    }

    /**
     * @return array[]
     */
    public function get_folder_views()
    {
        $square_icons = ($this->plugin->get_settings(PARAM_SQUARE_ICONS, SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on);
        $background = $this->plugin->plugin_info['app_background'];

        return array(
            // 4x3 with title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => Default_Dune_Plugin::SANDWICH_BASE,
                    ViewParams::sandwich_mask => Default_Dune_Plugin::SANDWICH_MASK,
                    ViewParams::sandwich_cover => Default_Dune_Plugin::SANDWICH_COVER,
                    ViewParams::sandwich_width => Default_Dune_Plugin::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => Default_Dune_Plugin::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.25,
                    ViewItemParams::icon_sel_scale_factor => 1.5,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 5x4 without title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 4,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => Default_Dune_Plugin::SANDWICH_BASE,
                    ViewParams::sandwich_mask => Default_Dune_Plugin::SANDWICH_MASK,
                    ViewParams::sandwich_cover => Default_Dune_Plugin::SANDWICH_COVER,
                    ViewParams::sandwich_width => Default_Dune_Plugin::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => Default_Dune_Plugin::TV_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => false,
                    ViewItemParams::icon_scale_factor => 1.0,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 2x10 title list view with right side icon
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 2,
                    ViewParams::num_rows => 10,
                    ViewParams::background_path => $background,
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 10,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => $square_icons ? 60 : 84,
                    ViewItemParams::icon_height => $square_icons ? 60 : 48,
                    ViewItemParams::item_caption_width => 485,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_SMALL,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 1x10 title list view with right side icon
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 10,
                    ViewParams::paint_icon_selection_box=> true,
                    ViewParams::paint_details => true,
                    ViewParams::paint_details_box_background => true,
                    ViewParams::paint_content_box_background => true,
                    ViewParams::paint_scrollbar => true,
                    ViewParams::paint_widget => true,
                    ViewParams::paint_help_line => true,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,
                    ViewParams::background_path=> $background,
                    ViewParams::background_order => 0,
                    ViewParams::item_detailed_info_text_color => 11,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::zoom_detailed_icon => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 50,
                    ViewItemParams::icon_dx => 26,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_width => 1060,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),
        );
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
}
