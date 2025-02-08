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

require_once 'starnet_entry_handler.php';
require_once 'starnet_tv_groups_screen.php';
require_once 'starnet_playlists_setup_screen.php';
require_once 'starnet_interface_setup_screen.php';
require_once 'starnet_interface_newui_setup_screen.php';
require_once 'starnet_category_setup_screen.php';
require_once 'starnet_epg_setup_screen.php';
require_once 'starnet_streaming_setup_screen.php';
require_once 'starnet_ext_setup_screen.php';
require_once 'starnet_tv_changed_channels_screen.php';
require_once 'starnet_folder_screen.php';
require_once 'starnet_tv.php';
require_once 'starnet_tv_channel_list_screen.php';
require_once 'starnet_tv_favorites_screen.php';
require_once 'starnet_tv_history_screen.php';
require_once 'starnet_epfs_handler.php';
require_once 'starnet_edit_list_screen.php';
require_once 'starnet_edit_providers_list_screen.php';
require_once 'starnet_edit_hidden_list_screen.php';

class Starnet_Plugin extends Default_Dune_Plugin
{
    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        User_Input_Handler_Registry::get_instance()->register_handler(new Starnet_Entry_Handler($this));

        $this->iptv = new Starnet_Tv($this);

        $this->create_screen(new Starnet_Tv_Groups_Screen($this));
        $this->create_screen(new Starnet_Tv_Channel_List_Screen($this));
        $this->create_screen(new Starnet_Tv_Favorites_Screen($this));
        $this->create_screen(new Starnet_Tv_History_Screen($this));
        $this->create_screen(new Starnet_Tv_Changed_Channels_Screen($this));

        $this->create_screen(new Starnet_Setup_Screen($this));
        $return_index = 2;
        $this->create_screen(new Starnet_Interface_Setup_Screen($this, $return_index));
        if (HD::rows_api_support()) {
            $return_index += 2;
            $this->create_screen(new Starnet_Interface_NewUI_Setup_Screen($this, $return_index));
        }
        $return_index += 2;
        $this->create_screen(new Starnet_Category_Setup_Screen($this, $return_index));
        $return_index += 2;
        $this->create_screen(new Starnet_Playlists_Setup_Screen($this, $return_index));
        $return_index += 2;
        $this->create_screen(new Starnet_Epg_Setup_Screen($this, $return_index));
        $return_index += 2;
        $this->create_screen(new Starnet_Streaming_Setup_Screen($this, $return_index));
        $return_index += 2;
        $this->create_screen(new Starnet_Ext_Setup_Screen($this, $return_index));

        $this->create_screen(new Starnet_Folder_Screen($this));
        $this->create_screen(new Starnet_Edit_List_Screen($this));
        $this->create_screen(new Starnet_Edit_Providers_List_Screen($this));
        $this->create_screen(new Starnet_Edit_Hidden_List_Screen($this));

        Starnet_Epfs_Handler::init($this);

        print_sysinfo();

        hd_debug_print_separator();
        hd_print("Plugin name:         " . $this->plugin_info['app_caption']);
        hd_print("Plugin version:      " . $this->plugin_info['app_version']);
        hd_print("Plugin date:         " . $this->plugin_info['app_release_date']);
        hd_print("LocalTime:           " . format_datetime('Y-m-d H:i', time()));
        hd_print("TimeZone:            " . getTimeZone());
        hd_print("Daylight:            " . date('I'));
        hd_print("New UI support:      " . var_export(HD::rows_api_support(), true));
        hd_debug_print_separator();

        hd_debug_print("Plugin loading complete.");
    }
}
