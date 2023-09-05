<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/hd.php';
require_once 'lib/default_dune_plugin.php';
require_once 'lib/json_serializer.php';

require_once 'starnet_entry_handler.php';
require_once 'starnet_tv_groups_screen.php';
require_once 'starnet_setup_screen.php';
require_once 'starnet_playlists_setup_screen.php';
require_once 'starnet_interface_setup_screen.php';
require_once 'starnet_epg_setup_screen.php';
require_once 'starnet_streaming_setup_screen.php';
require_once 'starnet_history_setup_screen.php';
require_once 'starnet_folder_screen.php';
require_once 'starnet_tv.php';
require_once 'starnet_tv_channel_list_screen.php';
require_once 'starnet_tv_favorites_screen.php';
require_once 'starnet_tv_history_screen.php';
require_once 'starnet_epfs_handler.php';
require_once 'starnet_edit_list_screen.php';

class Starnet_Plugin extends Default_Dune_Plugin
{
    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        print_sysinfo();

        hd_print("----------------------------------------------------");
        hd_print("Plugin name:         " . $this->plugin_info['app_caption']);
        hd_print("Plugin version:      " . $this->plugin_info['app_version']);
        hd_print("Plugin date:         " . $this->plugin_info['app_release_date']);
        hd_print("LocalTime            " . format_datetime('Y-m-d H:i', time()));
        hd_print("TimeZone             " . getTimeZone());
        hd_print("Daylight             " . (date('I') ? 'yes' : 'no'));
        hd_print("New UI support       " . ($this->new_ui_support ? "yes" : "no"));

        hd_print("----------------------------------------------------");

        $this->epg_man->init_cache_dir();
        $this->init_user_agent();

        User_Input_Handler_Registry::get_instance()->register_handler(new Starnet_Entry_Handler($this));

        $this->tv = new Starnet_Tv($this);

        $this->create_screen(new Starnet_Tv_Groups_Screen($this));
        $this->create_screen(new Starnet_Tv_Channel_List_Screen($this));
        $this->create_screen(new Starnet_Tv_Favorites_Screen($this));
        $this->create_screen(new Starnet_TV_History_Screen($this));

        $this->create_screen(new Starnet_Setup_Screen($this));
        $this->create_screen(new Starnet_Playlists_Setup_Screen($this));
        $this->create_screen(new Starnet_Interface_Setup_Screen($this));
        $this->create_screen(new Starnet_Epg_Setup_Screen($this));
        $this->create_screen(new Starnet_Streaming_Setup_Screen($this));
        $this->create_screen(new Starnet_History_Setup_Screen($this));

        $this->create_screen(new Starnet_Folder_Screen($this));
        $this->create_screen(new Starnet_Edit_List_Screen($this));

        $this->playback_points = new Playback_Points($this);

        Starnet_Epfs_Handler::init($this);

        hd_debug_print("Init done.");
    }
}
