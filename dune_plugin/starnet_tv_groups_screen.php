<?php
require_once 'lib/abstract_preloaded_regular_screen.php';
require_once 'starnet_setup_screen.php';
require_once 'starnet_playlists_setup_screen.php';

class Starnet_Tv_Groups_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_groups';

    const ACTION_CONFIRM_APPLY = 'apply_dlg';

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
            GUI_EVENT_KEY_RETURN     => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN),
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
        dump_input_handler(__METHOD__, $user_input);
        $min_sel = $this->plugin->get_special_groups_count($plugin_cookies);
        $group_id = MediaURL::decode($user_input->selected_media_url)->group_id;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);

        switch ($user_input->control_id) {
            case ACTION_OPEN_FOLDER:
            case ACTION_PLAY_FOLDER:
                $post_action = $user_input->control_id === ACTION_OPEN_FOLDER ? Action_Factory::open_folder() : Action_Factory::tv_play();
                $has_error = $this->plugin->get_last_error();
                if (!empty($has_error)) {
                    $this->plugin->set_last_error('');
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_load_any'), $post_action, $has_error);
                }

                return $post_action;

            case ACTION_ITEM_UP:
                if (!$this->plugin->tv->get_groups_order()->arrange_item($group_id, Ordered_Array::UP))
                    return null;

                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < $min_sel) {
                    $user_input->sel_ndx = $min_sel;
                }
                break;

            case ACTION_ITEM_DOWN:
                if (!$this->plugin->tv->get_groups_order()->arrange_item($group_id, Ordered_Array::DOWN))
                    return null;

                $groups_cnt = $min_sel + $this->plugin->tv->get_groups_order()->size();
                $user_input->sel_ndx++;
                if ($user_input->sel_ndx >= $groups_cnt) {
                    $user_input->sel_ndx = $groups_cnt - 1;
                }
                break;

            case ACTION_ITEM_DELETE:
                hd_print(__METHOD__ . ": Hide $group_id");
                $this->plugin->tv->disable_group($group_id);
                break;

            case ACTION_ITEMS_SORT:
                $this->plugin->tv->get_groups_order()->sort_order();
                break;

            case ACTION_ITEMS_EDIT:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Edit_List_Screen::ID,
                        'source_window_id' => self::ID,
                        'end_action' => ACTION_RELOAD,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('tv_screen_edit_hidden_group'));

            case ACTION_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case ACTION_CHANNELS_SETTINGS:
                return Action_Factory::open_folder(Starnet_Playlists_Setup_Screen::get_media_url_str(), TR::t('tv_screen_channels_setup'));

            case ACTION_EPG_SETTINGS:
                return Action_Factory::open_folder(Starnet_Epg_Setup_Screen::get_media_url_str(), TR::t('setup_epg_settings'));

            case GUI_EVENT_KEY_RETURN:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CONFIRM_APPLY);

            case self::ACTION_CONFIRM_APPLY:
                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                $return_action = $actions[GUI_EVENT_KEY_RETURN];
                unset($actions[GUI_EVENT_KEY_RETURN]);
                $post_action = Action_Factory::change_behaviour($actions, 0, $return_action);
                return Action_Factory::close_and_run($post_action);

            //return Action_Factory::show_main_screen();

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_ITEM_DELETE,
                    TR::t('tv_screen_hide_group'),
                    $this->plugin->get_image_path('hide.png')
                );

                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_ITEMS_SORT,
                    TR::t('sort_items'),
                    $this->plugin->get_image_path('sort.png')
                );

                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_ITEMS_EDIT,
                    TR::t('tv_screen_edit_hidden_group'),
                    $this->plugin->get_image_path('edit.png')
                );

                $menu_items[] = array(GuiMenuItemDef::is_separator => true,);

                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_CHANNELS_SETTINGS,
                    TR::t('tv_screen_channels_setup'),
                    $this->plugin->get_image_path('playlist.png')
                );

                $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                    ACTION_EPG_SETTINGS,
                    TR::t('setup_epg_settings'),
                    $this->plugin->get_image_path('epg.png')
                );

                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_RELOAD:
                hd_print(__METHOD__ . ": reload");
                return $this->plugin->tv->reload_channels($this, $plugin_cookies);

            default:
                return null;
        }

        $post_action = Action_Factory::close_and_run(
            Action_Factory::open_folder(
                $user_input->parent_media_url,
                null,
                null,
                null,
                Action_Factory::update_regular_folder(
                    $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
                    true,
                    $user_input->sel_ndx)
            )
        );

        Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
        return Starnet_Epfs_Handler::invalidate_folders(array($user_input->parent_media_url), $post_action);
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

        $show_all = (!isset($plugin_cookies->show_all) || $plugin_cookies->show_all === 'yes');
        $show_favorites = (!isset($plugin_cookies->show_favorites) || $plugin_cookies->show_favorites === 'yes');
        $show_history = (!isset($plugin_cookies->show_history) || $plugin_cookies->show_history === 'yes');

        /** @var Group $group */
        if ($show_favorites) {
            $group = $this->plugin->tv->get_special_group(FAV_CHANNEL_GROUP_ID);
            if (!is_null($group)) {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_Tv_Favorites_Screen::get_media_url_str(),
                    PluginRegularFolderItem::caption => $group->get_title(),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => $group->get_icon_url(),
                        ViewItemParams::item_detailed_icon_path => $group->get_icon_url()
                        )
                    );
            }
        }

        if ($show_history) {
            $group = $this->plugin->tv->get_special_group(PLAYBACK_HISTORY_GROUP_ID);
            if (!is_null($group)) {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_TV_History_Screen::get_media_url_str(),
                    PluginRegularFolderItem::caption => $group->get_title(),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => $group->get_icon_url(),
                        ViewItemParams::item_detailed_icon_path => $group->get_icon_url()
                    )
                );
            }
        }

        if ($show_all) {
            $group = $this->plugin->tv->get_special_group(ALL_CHANNEL_GROUP_ID);
            if (!is_null($group)) {
                $items[] = array(
                    PluginRegularFolderItem::media_url => Starnet_Tv_Channel_List_Screen::get_media_url_str(ALL_CHANNEL_GROUP_ID),
                    PluginRegularFolderItem::caption => $group->get_title(),
                    PluginRegularFolderItem::view_item_params => array(
                        ViewItemParams::icon_path => $group->get_icon_url(),
                        ViewItemParams::item_detailed_icon_path => $group->get_icon_url()
                    )
                );
            }
        }

        /** @var Group $group */
        foreach ($this->plugin->tv->get_groups_order()->get_order() as $item) {
            //hd_print("group: {$group->get_title()} , icon: {$group->get_icon_url()}");
            $group = $this->plugin->tv->get_group($item);
            if (is_null($group) || $group->is_disabled()) continue;

            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Tv_Channel_List_Screen::get_media_url_str($group->get_id()),
                PluginRegularFolderItem::caption => $group->get_title(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $group->get_icon_url(),
                    ViewItemParams::item_detailed_icon_path => $group->get_icon_url(),
                    ViewParams::item_detailed_info_title_color => DEF_LABEL_TEXT_COLOR_GREEN,
                    ViewParams::item_detailed_info_text_color => DEF_LABEL_TEXT_COLOR_WHITE,
                    ViewItemParams::item_detailed_info => $group->get_title(),
                ),
            );
        }

        //hd_print("Loaded items " . count($items));
        return $items;
    }

    /**
     * @return array[]
     */
    public function get_folder_views()
    {
        return array(

            // 1x12 list view with info
            array
            (
                PluginRegularFolderView::async_icon_loading => false,
                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 1,
                    ViewParams::num_rows => 12,
                    ViewParams::paint_details => true,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::background_height => 1080,
                    ViewParams::background_width => 1920,
                    ViewParams::optimize_full_screen_background => true,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),
                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::icon_dx => 20,
                    ViewItemParams::icon_dy => -5,
                    ViewItemParams::icon_width => 50,
                    ViewItemParams::icon_height => 55,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_width => 1100
                ),
                PluginRegularFolderView::not_loaded_view_item_params => array(),
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
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => false,
                    ViewItemParams::item_layout => HALIGN_LEFT,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_caption_width => 485,
                    ViewItemParams::item_caption_font_size => FONT_SIZE_NORMAL,
                    ViewItemParams::item_caption_dx => 50,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            // small with caption
            array
            (
                PluginRegularFolderView::async_icon_loading => false,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 5,
                    ViewParams::num_rows => 3,
                    ViewParams::paint_details => false,
                    ViewParams::paint_sandwich => true,
                    ViewParams::sandwich_base => Default_Dune_Plugin::SANDWICH_BASE,
                    ViewParams::sandwich_mask => Default_Dune_Plugin::SANDWICH_MASK,
                    ViewParams::sandwich_cover => Default_Dune_Plugin::SANDWICH_COVER,
                    ViewParams::sandwich_width => Default_Dune_Plugin::TV_SANDWICH_WIDTH,
                    ViewParams::sandwich_height => Default_Dune_Plugin::TV_SANDWICH_HEIGHT,
                    ViewParams::content_box_padding_left => 70,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
                    ViewParams::background_order => 0,
                    ViewParams::sandwich_icon_upscale_enabled => true,
                    ViewParams::sandwich_icon_keep_aspect_ratio => true,
                ),

                PluginRegularFolderView::base_view_item_params => array
                (
                    ViewItemParams::item_paint_icon => true,
                    ViewItemParams::item_layout => HALIGN_CENTER,
                    ViewItemParams::icon_valign => VALIGN_CENTER,
                    ViewItemParams::item_paint_caption => true,
                    ViewItemParams::icon_scale_factor => 1.2,
                    ViewItemParams::icon_sel_scale_factor => 1.2,
                ),

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),

            // 4x3 with title
            array
            (
                PluginRegularFolderView::async_icon_loading => true,

                PluginRegularFolderView::view_params => array
                (
                    ViewParams::num_cols => 4,
                    ViewParams::num_rows => 3,
                    ViewParams::background_path => $this->plugin->plugin_info['app_background'],
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

                PluginRegularFolderView::not_loaded_view_item_params => array(),
            ),
        );
    }
}
