<?php
require_once 'lib/abstract_preloaded_regular_screen.php';
require_once 'starnet_setup_screen.php';
require_once 'starnet_playlists_setup_screen.php';

class Starnet_Tv_Groups_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_groups';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct(self::ID, $plugin);

        $plugin->create_screen($this);
    }

    /**
     * @return string
     */
    public function get_handler_id()
    {
        return self::ID . '_handler';
    }

    /**
     * @return false|string
     */
    public static function get_media_url_str()
    {
        return MediaURL::encode(array('screen_id' => self::ID));
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        // if token not set force to open setup screen
        //hd_print(__METHOD__);

        return array(
            GUI_EVENT_KEY_ENTER      => User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER),
            GUI_EVENT_KEY_PLAY       => User_Input_Handler_Registry::create_action($this, ACTION_PLAY_FOLDER),
            GUI_EVENT_KEY_B_GREEN    => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up')),
            GUI_EVENT_KEY_C_YELLOW   => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down')),
            GUI_EVENT_KEY_D_BLUE     => User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS, TR::t('entry_setup')),
            GUI_EVENT_KEY_POPUP_MENU => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU),
        );
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);

        switch ($user_input->control_id) {
            case ACTION_OPEN_FOLDER:
            case ACTION_PLAY_FOLDER:
                $post_action = $user_input->control_id === ACTION_OPEN_FOLDER ? Action_Factory::open_folder() : Action_Factory::tv_play();
                $has_error = $this->plugin->get_last_error();
                if (!empty($has_error)) {
                    $this->plugin->set_last_error('');
                    return Action_Factory::show_title_dialog(TR::t('err_load_any'), $post_action, $has_error);
                }

                return $post_action;

            case ACTION_ITEM_DELETE:
                if (isset($user_input->selected_media_url)) {
                    $media_url = MediaURL::decode($user_input->selected_media_url);
                    $group = $this->plugin->tv->get_group($media_url->group_id);
                    if ($group->is_favorite_group() || $group->is_all_channels_group() || $group->is_history_group())
                        break;

                    $disabled_groups = $this->plugin->get_settings(PARAM_DISABLED_GROUPS, array());
                    if (!in_array($group->get_title(), $disabled_groups)) {
                        $disabled_groups[] = $group->get_title();
                        $this->plugin->set_settings(PARAM_DISABLED_GROUPS, $disabled_groups);
                        return $this->plugin->tv->reload_channels($this, $plugin_cookies);
                    }
                }
                break;

            case ACTION_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case ACTION_CHANNELS_SETTINGS:
                return Action_Factory::open_folder(Starnet_Playlists_Setup_Screen::get_media_url_str(), TR::t('tv_screen_channels_setup'));

            case ACTION_EPG_SETTINGS:
                return Action_Factory::open_folder(Starnet_Epg_Setup_Screen::get_media_url_str(), TR::t('setup_epg_settings'));

            case ACTION_ITEM_UP:
            case ACTION_ITEM_DOWN:
                // TODO not yet implemented
                break;

            case GUI_EVENT_KEY_RETURN:
                Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
                return Starnet_Epfs_Handler::invalidate_folders(null, Action_Factory::close_and_run());

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_CHANNELS_SETTINGS,
                    TR::t('tv_screen_channels_setup'),
                    'gui_skin://small_icons/playlist_file.aai'
                );

                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_EPG_SETTINGS,
                    TR::t('setup_epg_settings'),
                    'gui_skin://small_icons/language_settings.aai'
                );

                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_ITEM_DELETE,
                    TR::t('tv_screen_hide_group'),
                    'gui_skin://button_icons/cancel.aai'
                );

                return Action_Factory::show_popup_menu($menu_items);
        }

        return null;
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_print(__METHOD__ . ": get_all_folder_items");
        $items = array();
        try {
            $this->plugin->tv->ensure_channels_loaded($plugin_cookies);
        } catch (Exception $e) {
            hd_print(__METHOD__ . ": Channels not loaded");
            return $items;
        }

        /** @var Default_Group $group */
        $groups = $this->plugin->tv->get_groups();
        if ($groups === null) {
            return $items;
        }

        foreach ($this->plugin->tv->get_groups() as $group) {

            //hd_print("group: {$group->get_title()} , icon: {$group->get_icon_url()}");
            $icons_param = array(
                ViewItemParams::icon_path => $group->get_icon_url(),
                ViewItemParams::item_detailed_icon_path => $group->get_icon_url()
            );

            if ($group->is_favorite_group()) {
                $fav_item = array(
                    PluginRegularFolderItem::media_url => Starnet_Tv_Favorites_Screen::get_media_url_str(),
                    PluginRegularFolderItem::caption => Default_Dune_Plugin::FAV_CHANNEL_GROUP_CAPTION,
                    PluginRegularFolderItem::view_item_params => $icons_param
                );
            } else if ($group->is_all_channels_group()) {
                $all_item = array(
                    PluginRegularFolderItem::media_url => Starnet_Tv_Channel_List_Screen::get_media_url_str(
                        Default_Dune_Plugin::ALL_CHANNEL_GROUP_ID),
                    PluginRegularFolderItem::caption => Default_Dune_Plugin::ALL_CHANNEL_GROUP_CAPTION,
                    PluginRegularFolderItem::view_item_params => $icons_param
                );
            } else if ($group->is_history_group()) {
                $hist_item = array(
                    PluginRegularFolderItem::media_url => Starnet_TV_History_Screen::get_media_url_str(),
                    PluginRegularFolderItem::caption => Default_Dune_Plugin::PLAYBACK_HISTORY_CAPTION,
                    PluginRegularFolderItem::view_item_params => $icons_param
                );
            } else {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_Tv_Channel_List_Screen::get_media_url_str($group->get_id()),
                    PluginRegularFolderItem::caption => $group->get_title(),
                    PluginRegularFolderItem::view_item_params => $icons_param
                );
            }
        }

        if (isset($all_item) && (!isset($plugin_cookies->show_all) || $plugin_cookies->show_all === 'yes')) {
            array_unshift($items, $all_item);
        }

        if (isset($hist_item) && (!isset($plugin_cookies->show_history) || $plugin_cookies->show_history === 'yes')) {
            array_unshift($items, $hist_item);
        }

        if (isset($fav_item) && (!isset($plugin_cookies->show_favorites) || $plugin_cookies->show_favorites === 'yes')) {
            array_unshift($items, $fav_item);
        }

        //hd_print("Loaded items " . count($items));
        return $items;
    }

    /**
     * @return array[]
     */
    public function GET_FOLDER_VIEWS()
    {
        return array(

            // 1x10 list view with info
            array
            (
                PluginRegularFolderView::async_icon_loading => true,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 10,
                    ViewParams::paint_details => true,
                    ViewParams::paint_item_info_in_details => true,
                    ViewParams::item_detailed_info_auto_line_break => true,
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,

                    ViewParams::paint_sandwich => false,
                    ViewParams::sandwich_base => Default_Dune_Plugin::SANDWICH_BASE,
                    ViewParams::sandwich_mask => Default_Dune_Plugin::SANDWICH_MASK,
                    ViewParams::sandwich_cover => Default_Dune_Plugin::SANDWICH_COVER,
                    ViewParams::sandwich_width => Default_Dune_Plugin::VOD_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => Default_Dune_Plugin::VOD_SANDWICH_HEIGHT,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => false,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_GROUP_ICON_PATH,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_sel_margin_top => 0,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::item_caption_width => 1100,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => false,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_GROUP_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),

            // 3x10 list view
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 3,
                    ViewParams::num_rows => 10,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::paint_details => false,
                    ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => false,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_caption_width => 485,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_dx => 50,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_GROUP_ICON_PATH,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::icon_path => Default_Dune_Plugin::DEFAULT_GROUP_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => 'missing://',
                ),
            ),
        );
    }
}
