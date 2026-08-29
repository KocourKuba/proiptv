<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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

///////////////////////////////////////////////////////////////////////////

require_once 'lib/default_dune_plugin.php';
require_once 'lib/dune_last_error.php';
require_once "lib/curl_wrapper.php";

require_once 'starnet_entry_handler.php';
require_once 'starnet_epfs_handler.php';

require_once 'plugin_setup/starnet_setup_plugin_screen.php';
require_once 'plugin_setup/starnet_setup_plugin_interface_screen.php';
require_once 'plugin_setup/starnet_setup_download_screen.php';
require_once 'plugin_setup/starnet_setup_sleep_timer_screen.php';
require_once 'plugin_setup/starnet_setup_folders_screen.php';
require_once 'plugin_setup/starnet_setup_ext_screen.php';
require_once 'plugin_setup/starnet_setup_backup_screen.php';

require_once 'plugin_playlist_setup/starnet_setup_interface_screen.php';
require_once 'plugin_playlist_setup/starnet_setup_interface_newui_screen.php';
require_once 'plugin_playlist_setup/starnet_setup_category_screen.php';
require_once 'plugin_playlist_setup/starnet_setup_epg_screen.php';
require_once 'plugin_playlist_setup/starnet_setup_playback_screen.php';
require_once 'plugin_playlist_setup/starnet_setup_provider_screen.php';
require_once 'plugin_playlist_setup/starnet_setup_playlist_screen.php';
require_once 'plugin_playlist_setup/starnet_setup_simple_iptv_screen.php';

require_once 'screens_tv/starnet_tv_groups_screen.php';
require_once 'screens_tv/starnet_tv_changed_channels_screen.php';
require_once 'screens_tv/starnet_tv.php';
require_once 'screens_tv/starnet_tv_channel_list_screen.php';
require_once 'screens_tv/starnet_tv_favorites_screen.php';
require_once 'screens_tv/starnet_tv_history_screen.php';
require_once 'screens_tv/starnet_edit_hidden_list_screen.php';
require_once 'screens_tv/starnet_edit_group_list_screen.php';
require_once 'screens_tv/starnet_edit_channel_list_screen.php';

require_once 'screens_common/starnet_folder_screen.php';
require_once 'screens_common/starnet_edit_playlists_screen.php';
require_once 'screens_common/starnet_edit_xmltv_list_screen.php';
require_once 'screens_common/starnet_edit_json_list_screen.php';
require_once 'screens_common/starnet_edit_providers_list_screen.php';

class Starnet_Plugin extends Default_Dune_Plugin
{
    /**
     * @throws Exception
     */
    public function __construct(&$plugin_cookies)
    {
        parent::__construct();

        $this->set_plugin_cookies($plugin_cookies);

        if (is_r22_or_higher()) {
            ini_set('memory_limit', safe_get_value($plugin_cookies,PARAM_COOKIE_MEMORY_LIMIT, '256M'));
        }

        User_Input_Handler_Registry::get_instance()->register_handler(new Starnet_Entry_Handler($this));

        $this->iptv = new Starnet_Tv($this);

        // TV screens
        $this->create_screen(new Starnet_Tv_Groups_Screen($this));
        $this->create_screen(new Starnet_Tv_Channel_List_Screen($this));
        $this->create_screen(new Starnet_Tv_Favorites_Screen($this));
        $this->create_screen(new Starnet_Tv_History_Screen($this));
        $this->create_screen(new Starnet_Tv_Changed_Channels_Screen($this));

        // plugin setup screens
        $this->create_screen(new Starnet_Setup_Plugin_Screen($this));
        $this->create_screen(new Starnet_Setup_Plugin_Interface_Screen($this));
        $this->create_screen(new Starnet_Setup_Folders_Screen($this));
        $this->create_screen(new Starnet_Setup_Download_Screen($this));
        $this->create_screen(new Starnet_Setup_Sleep_Timer_Screen($this));
        $this->create_screen(new Starnet_Setup_Ext_Screen($this));

        $this->create_screen(new Starnet_Setup_Backup_Screen($this));

        // playlist setup screen
        $this->create_screen(new Starnet_Setup_Interface_Screen($this));
        $this->create_screen(new Starnet_Setup_Category_Screen($this));
        $this->create_screen(new Starnet_Setup_Interface_NewUI_Screen($this));
        $this->create_screen(new Starnet_Setup_Playlist_Screen($this));
        $this->create_screen(new Starnet_Setup_Epg_Screen($this));
        $this->create_screen(new Starnet_Setup_Playback_Screen($this));
        $this->create_screen(new Starnet_Setup_Provider_Screen($this));
        $this->create_screen(new Starnet_Setup_Simple_IPTV_Screen($this));

        $this->create_screen(new Starnet_Folder_Screen($this));
        $this->create_screen(new Starnet_Edit_Playlists_Screen($this));
        $this->create_screen(new Starnet_Edit_Xmltv_List_Screen($this));
        $this->create_screen(new Starnet_Edit_Json_List_Screen($this));
        $this->create_screen(new Starnet_Edit_Providers_List_Screen($this));
        $this->create_screen(new Starnet_Edit_Hidden_List_Screen($this));
        $this->create_screen(new Starnet_Edit_Group_List_Screen($this));
        $this->create_screen(new Starnet_Edit_Channel_List_Screen($this));

        Starnet_Epfs_Handler::init($this, $plugin_cookies);

        $this->init_providers_config();
        $this->init_screen_view_parameters();
        if (self::$plugin_info['debug']) {
            $plugin_cookies->{PARAM_COOKIE_ENABLE_DEBUG} = SwitchOnOff::on;
        }

        hd_print_separator();
        hd_print('Plugin name:             ' . self::$plugin_info['app_caption']);
        hd_print('Plugin version:          ' . self::$plugin_info['app_version']);
        hd_print('Plugin date:             ' . self::$plugin_info['app_release_date']);
        hd_print('LocalTime:               ' . format_datetime('Y-m-d H:i', time()));
        hd_print('TimeZone:                ' . getTimeZone());
        hd_print('NewUI support:           ' . SwitchOnOff::to_def(HD::rows_api_support()));
        hd_print('NewUI enabled:           ' . SwitchOnOff::to_def(Starnet_Epfs_Handler::$enabled));
        hd_print('Ext EPG support:         ' . SwitchOnOff::to_def(is_ext_epg_supported()));
        hd_print('Debug log enabled:       ' . safe_get_value($plugin_cookies,PARAM_COOKIE_ENABLE_DEBUG, SwitchOnOff::off));

        print_sysinfo();

        hd_print('Plugin loading complete.');
    }
}
