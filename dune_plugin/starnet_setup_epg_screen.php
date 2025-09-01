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

require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';
require_once 'lib/epg/epg_manager_json.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Setup_Epg_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'epg_setup';

    const CONTROL_CHANGE_CACHE_PATH = 'xmltv_cache_path';
    const CONTROL_ITEMS_CLEAR_EPG_CACHE = 'clear_epg_cache';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        return $this->do_get_control_defs();
    }

    /**
     * EPG dialog defs
     * @return array
     */
    public function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // EPG cache engine
        $engine = $this->plugin->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV);
        $engine_variants[ENGINE_XMLTV] = TR::t('setup_epg_cache_xmltv');
        $provider = $this->plugin->get_active_provider();
        if (!is_null($provider)) {
            $epg_presets = $provider->getConfigValue(EPG_JSON_PRESETS);
            if (!empty($epg_presets)) {
                $engine_variants[ENGINE_JSON] = TR::t('setup_epg_cache_json');
            }
        }

        if (count($engine_variants) > 1) {
            Control_Factory::add_combobox($defs, $this, null,
                PARAM_EPG_CACHE_ENGINE, TR::t('setup_epg_cache_engine'),
                $engine, $engine_variants, self::CONTROLS_WIDTH, true);
        } else if (count($engine_variants) === 1) {
            Control_Factory::add_button($defs, $this, null, "dummy",
                TR::t('setup_epg_cache_engine'), reset($engine_variants), self::CONTROLS_WIDTH);
        }

        if ($engine === ENGINE_XMLTV) {
            //////////////////////////////////////
            // EPG cache dir
            $cache_dir = $this->plugin->get_cache_dir();
            $free_size = TR::t('setup_storage_info__1', HD::get_storage_size($cache_dir));
            $cache_dir = HD::string_ellipsis($cache_dir . '/');
            Control_Factory::add_image_button($defs, $this, null, self::CONTROL_CHANGE_CACHE_PATH,
                $free_size, $cache_dir, get_image_path('folder.png'), self::CONTROLS_WIDTH);

            //////////////////////////////////////
            // clear epg cache
            Control_Factory::add_image_button($defs, $this, null,
                self::CONTROL_ITEMS_CLEAR_EPG_CACHE, TR::t('entry_epg_cache_clear_all'), TR::t('clear'),
                get_image_path('brush.png'), self::CONTROLS_WIDTH);
        } else {
            if (isset($epg_presets)) {
                if (count($epg_presets) > 1) {
                    $preset = $this->plugin->get_setting(PARAM_EPG_JSON_PRESET, 0);
                    $presets = array();
                    foreach ($epg_presets as $epg_preset) {
                        $presets[] = safe_get_value($epg_preset, 'title', $epg_preset['name']);
                    }
                    Control_Factory::add_combobox($defs, $this, null,
                        PARAM_EPG_JSON_PRESET, TR::t('setup_epg_cache_json'),
                        $preset, $presets, self::CONTROLS_WIDTH, true);
                }

                foreach (array(1, 2, 3, 6, 12) as $hour) {
                    $caching_range[$hour] = TR::t('setup_epg_cache_json_time__1', $hour);
                }
                $cache_time = $this->plugin->get_setting(PARAM_EPG_CACHE_TIME, 1);
                Control_Factory::add_combobox($defs, $this, null,
                    PARAM_EPG_CACHE_TIME, TR::t('setup_epg_cache_json_time'),
                    $cache_time, $caching_range, self::CONTROLS_WIDTH, true);
            }
        }

        //////////////////////////////////////
        // Fake EPG
        $fake_epg = $this->plugin->get_setting(PARAM_FAKE_EPG, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_FAKE_EPG, TR::t('entry_epg_fake'), SwitchOnOff::translate($fake_epg),
            get_image_path(SwitchOnOff::to_image($fake_epg)), self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $action_reload = User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);
        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("Changing $control_id value to $new_value", true);
        }

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(
                        Starnet_Setup_Screen::ID,
                        RESET_CONTROLS_ACTION_ID,
                        null,
                        array('initial_sel_ndx' => $this->return_index)
                    )
                );

            case self::CONTROL_CHANGE_CACHE_PATH:
                $media_url = Starnet_Folder_Screen::make_media_url(static::ID,
                    array(
                        PARAM_END_ACTION => ACTION_RELOAD,
                        Starnet_Folder_Screen::PARAM_CHOOSE_FOLDER => ACTION_FOLDER_SELECTED,
                        Starnet_Folder_Screen::PARAM_RESET_ACTION => ACTION_RESET_DEFAULT,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                    )
                );
                return Action_Factory::open_folder($media_url->get_media_url_str(), TR::t('setup_epg_xmltv_cache_caption'));

            case PARAM_EPG_CACHE_ENGINE:
            case PARAM_EPG_JSON_PRESET:
                $this->plugin->set_setting($control_id, $user_input->{$control_id});
                $this->plugin->init_epg_manager();
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case PARAM_EPG_CACHE_TIME:
                $this->plugin->set_setting($control_id, $user_input->{$control_id});
                break;

            case self::CONTROL_ITEMS_CLEAR_EPG_CACHE:
                foreach ($this->plugin->get_selected_xmltv_ids() as $id) {
                    $this->plugin->safe_clear_selected_epg_cache($id);
                }
                return Action_Factory::show_title_dialog(TR::t('entry_epg_cache_cleared'),
                    Action_Factory::reset_controls($this->do_get_control_defs()));

            case ACTION_RESET_DEFAULT:
                hd_debug_print(ACTION_RESET_DEFAULT);
                foreach ($this->plugin->get_xmltv_sources_hash(XMLTV_SOURCE_ALL, $this->plugin->get_active_playlist_id()) as $id) {
                    $this->plugin->safe_clear_selected_epg_cache($id);
                }
                $this->plugin->set_parameter(PARAM_CACHE_PATH, '');
                $this->plugin->init_epg_manager();

                $default_path = $this->plugin->get_cache_dir();
                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $default_path),
                    $action_reload, $default_path, self::CONTROLS_WIDTH);

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                hd_debug_print(ACTION_FOLDER_SELECTED . ": " . $data->{PARAM_FILEPATH}, true);
                if ($this->plugin->get_cache_dir() === $data->{PARAM_FILEPATH}) break;

                $this->plugin->safe_clear_selected_epg_cache(null);
                $this->plugin->set_parameter(PARAM_CACHE_PATH, str_replace("//", "/", $data->{PARAM_FILEPATH}));
                $this->plugin->init_epg_manager();

                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->{Starnet_Folder_Screen::PARAM_CAPTION}),
                    $action_reload, $data->{PARAM_FILEPATH}, self::CONTROLS_WIDTH);

            case PARAM_FAKE_EPG:
                $this->plugin->toggle_setting($control_id, false);
                $this->plugin->init_epg_manager();
                break;

            case ACTION_RELOAD:
                hd_debug_print(ACTION_RELOAD);
                if (!$this->plugin->reload_channels($plugin_cookies)) {
                    return Action_Factory::invalidate_all_folders(
                        $plugin_cookies,
                        null,
                        Action_Factory::show_title_dialog(TR::t('err_load_playlist'),
                            null,
                            HD::get_last_error($this->plugin->get_pl_error_name())
                        )
                    );
                }

                return Action_Factory::invalidate_all_folders(
                    $plugin_cookies,
                    null,
                    Action_Factory::reset_controls($this->do_get_control_defs())
                );
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }
}
