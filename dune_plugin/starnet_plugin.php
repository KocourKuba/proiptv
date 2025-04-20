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
require_once 'starnet_setup_playlists_screen.php';
require_once 'starnet_setup_interface_screen.php';
require_once 'starnet_setup_interface_newui_screen.php';
require_once 'starnet_setup_category_screen.php';
require_once 'starnet_setup_epg_screen.php';
require_once 'starnet_setup_history_screen.php';
require_once 'starnet_setup_playback_screen.php';
require_once 'starnet_setup_ext_screen.php';
require_once 'starnet_setup_ext_playlists_screen.php';
require_once 'starnet_tv_changed_channels_screen.php';
require_once 'starnet_folder_screen.php';
require_once 'starnet_tv.php';
require_once 'starnet_tv_channel_list_screen.php';
require_once 'starnet_tv_favorites_screen.php';
require_once 'starnet_tv_history_screen.php';
require_once 'starnet_epfs_handler.php';
require_once 'starnet_edit_playlists_screen.php';
require_once 'starnet_edit_xmltv_list_screen.php';
require_once 'starnet_edit_providers_list_screen.php';
require_once 'starnet_edit_hidden_list_screen.php';
require_once 'starnet_setup_backup_screen.php';

class Starnet_Plugin extends Default_Dune_Plugin
{
    const CONFIG_URL = 'http://iptv.esalecrm.net/config/providers';

    /**
     * @throws Exception
     */
    public function __construct($plugin_cookies)
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
        $this->create_screen(new Starnet_Setup_Interface_Screen($this));
        $this->create_screen(new Starnet_Setup_Interface_NewUI_Screen($this));
        $this->create_screen(new Starnet_Setup_Category_Screen($this));
        $this->create_screen(new Starnet_Setup_Playlists_Screen($this));
        $this->create_screen(new Starnet_Setup_Epg_Screen($this));
        $this->create_screen(new Starnet_Setup_History_Screen($this));
        $this->create_screen(new Starnet_Setup_Playback_Screen($this));
        $this->create_screen(new Starnet_Setup_Ext_Screen($this));
        $this->create_screen(new Starnet_Setup_Ext_Playlists_Screen($this));
        $this->create_screen(new Starnet_Setup_Backup_Screen($this));

        $this->create_screen(new Starnet_Folder_Screen($this));
        $this->create_screen(new Starnet_Edit_Playlists_Screen($this));
        $this->create_screen(new Starnet_Edit_Xmltv_List_Screen($this));
        $this->create_screen(new Starnet_Edit_Providers_List_Screen($this));
        $this->create_screen(new Starnet_Edit_Hidden_List_Screen($this));

        Starnet_Epfs_Handler::init($this);

        $this->init_providers_config();
        $this->init_screen_view_parameters($this->plugin_info['app_background']);

        $this->ext_epg_supported = is_ext_epg_supported();
        print_sysinfo();

        hd_debug_print_separator();
        hd_print("Plugin name:         " . $this->plugin_info['app_caption']);
        hd_print("Plugin version:      " . $this->plugin_info['app_version']);
        hd_print("Plugin date:         " . $this->plugin_info['app_release_date']);
        hd_print("LocalTime:           " . format_datetime('Y-m-d H:i', time()));
        hd_print("TimeZone:            " . getTimeZone());
        hd_print("Daylight:            " . date('I'));
        hd_print("Daylight Fix:        " . var_export(is_need_daylight_fix(), true));
        hd_print("New UI support:      " . var_export(HD::rows_api_support(), true));
        hd_print("Ext EPG support:     " . var_export($this->ext_epg_supported, true));
        hd_print("Auto resume enabled: " . safe_get_member($plugin_cookies,'auto_resume'));
        hd_print("Auto play enabled:   " . safe_get_member($plugin_cookies,'auto_play'));
        hd_print("Debug log enabled:   " . safe_get_member($plugin_cookies,PARAM_ENABLE_DEBUG));
        hd_debug_print_separator();

        hd_debug_print("Plugin loading complete.");
    }

    public function init_providers_config()
    {
        if ($this->providers->size() !== 0) {
            return;
        }

        // 1. Check local debug version
        // 2. Try to download from web release version
        // 3. Check previously downloaded web release version
        // 4. Check preinstalled version
        // 5. Houston we have a problem
        $tmp_file = get_install_path("providers_debug.json");
        if (file_exists($tmp_file)) {
            hd_debug_print("Load debug providers configuration: $tmp_file");
            $jsonArray = parse_json_file($tmp_file);
        } else {
            $name = "providers_{$this->plugin_info['app_base_version']}.json";
            $tmp_file = get_data_path($name);
            $serial = get_serial_number();
            if (empty($serial)) {
                hd_debug_print("Unable to get DUNE serial.");
                $serial = 'XXXX';
            }
            $ver = $this->plugin_info['app_version'];
            $model = get_product_id();
            $firmware = get_raw_firmware_version();
            $jsonArray = HD::DownloadJson(self::CONFIG_URL . "?ver=$ver&model=$model&firmware=$firmware&serial=$serial");
            if ($jsonArray === false || !isset($jsonArray['providers'])) {
                if (file_exists($tmp_file)) {
                    hd_debug_print("Load actual providers configuration");
                    $jsonArray = parse_json_file($tmp_file);
                } else if (file_exists($tmp_file = get_install_path($name))) {
                    hd_debug_print("Load installed providers configuration");
                    $jsonArray = parse_json_file($tmp_file);
                }
            } else {
                store_to_json_file($tmp_file, $jsonArray);
            }
        }

        foreach ($jsonArray['plugin_config']['image_libs'] as $key => $value) {
            hd_debug_print("available image lib: $key");
            $this->image_libs->set($key, $value);
        }

        foreach ($jsonArray['epg_presets'] as $key => $value) {
            hd_debug_print("available epg preset: $key");
            $this->epg_presets->set($key, $value);
        }

        if ($jsonArray === false || !isset($jsonArray['providers'])) {
            hd_debug_print("Problem to get providers configuration");
            return;
        }

        foreach ($jsonArray['providers'] as $item) {
            if (!isset($item['id'], $item['enable']) || $item['enable'] === false) continue;

            $api_class = 'api_default';
            if (isset($item['class']) && class_exists("api_" . $item['class'])) {
                $api_class = "api_" . $item['class'];
            }

            /** @var api_default $provider */
            $provider = new $api_class($this);
            foreach ($item as $key => $value) {
                $words = explode('_', $key);
                $setter = "set";
                foreach ($words as $word) {
                    $setter .= ucwords($word);
                }
                if (method_exists($provider, $setter)) {
                    $provider->{$setter}($value);
                } else {
                    hd_debug_print("Unknown method $setter", true);
                }
            }

            // cache provider logo
            $logo = $provider->getLogo();
            $filename = basename($logo);
            $local_file = get_install_path("logo/$filename");
            if (file_exists($local_file)) {
                $provider->setLogo("plugin_file://logo/$filename");
            } else {
                $cached_file = get_cached_image_path($filename);
                list($res,) = Curl_Wrapper::simple_download_file($logo, $cached_file);
                if ($res) {
                    $provider->setLogo($cached_file);
                } else {
                    hd_debug_print("failed to download provider logo: $logo");
                }
            }
            $this->providers->set($provider->getId(), $provider);
        }
    }
}
