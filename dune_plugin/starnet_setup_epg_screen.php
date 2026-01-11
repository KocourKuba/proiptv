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

class Starnet_Setup_Epg_Screen extends Abstract_Controls_Screen
{
    const ID = 'epg_setup';

    const CONTROL_ITEMS_REFRESH_EPG_CACHE = 'refresh_epg_cache';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs();
    }

    /**
     * EPG dialog defs
     * @return array
     */
    protected function do_get_control_defs()
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
            Control_Factory::add_combobox($defs, $this, PARAM_EPG_CACHE_ENGINE,
                TR::t('setup_epg_cache_engine'), $engine,
                $engine_variants, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);
        } else if (count($engine_variants) === 1) {
            Control_Factory::add_button($defs, $this, "dummy", TR::t('setup_epg_cache_engine'), reset($engine_variants));
        }

        //////////////////////////////////////
        // ext epg
        if (is_ext_epg_supported()) {
            $ext_epg = $this->plugin->get_setting(PARAM_SHOW_EXT_EPG, SwitchOnOff::on);
            Control_Factory::add_image_button($defs, $this, PARAM_SHOW_EXT_EPG,
                TR::t('setup_ext_epg'), SwitchOnOff::translate($ext_epg), SwitchOnOff::to_image($ext_epg));
        }

        //////////////////////////////////////
        // clear epg cache
        Control_Factory::add_image_button($defs, $this, self::CONTROL_ITEMS_REFRESH_EPG_CACHE,
            TR::t('entry_epg_cache_refresh'), TR::t('refresh'), get_image_path('refresh.png'));

        if ($engine === ENGINE_JSON && isset($epg_presets)) {
            if (count($epg_presets) > 1) {
                $preset = $this->plugin->get_setting(PARAM_EPG_JSON_PRESET, 0);
                $presets = array();
                foreach ($epg_presets as $epg_preset) {
                    $presets[] = safe_get_value($epg_preset, 'title', $epg_preset['name']);
                }
                Control_Factory::add_combobox($defs, $this, PARAM_EPG_JSON_PRESET,
                    TR::t('setup_epg_cache_json'), $preset,
                    $presets, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);
            }

            foreach (array(1, 2, 3, 6, 12) as $hour) {
                $caching_range[$hour] = TR::t('setup_cache_time_h__1', $hour);
            }
            $cache_time = $this->plugin->get_setting(PARAM_EPG_CACHE_TIME, 1);
            Control_Factory::add_combobox($defs, $this, PARAM_EPG_CACHE_TIME,
                TR::t('setup_cache_time_epg'), $cache_time,
                $caching_range, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);
        }

        //////////////////////////////////////
        // Fake EPG
        $fake_epg = $this->plugin->get_setting(PARAM_FAKE_EPG, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, PARAM_FAKE_EPG,
            TR::t('entry_epg_fake'), SwitchOnOff::translate($fake_epg), SwitchOnOff::to_image($fake_epg));

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $control_id = $user_input->control_id;
        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                return self::make_return_action($parent_media_url);

            case PARAM_SHOW_EXT_EPG:
                $this->plugin->toggle_setting($control_id);
                break;

            case PARAM_EPG_CACHE_ENGINE:
                $post_action = User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);
                $val = $user_input->{$control_id};
                $active_sources = $this->plugin->get_selected_xmltv_ids();
                if (empty($active_sources) && $val === ENGINE_XMLTV) {
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_no_xmltv_sources'), $post_action);
                }
                $this->plugin->set_setting($control_id, $val);
                $this->plugin->init_epg_manager();
                return $post_action;

            case PARAM_EPG_JSON_PRESET:
                $this->plugin->set_setting($control_id, $user_input->{$control_id});
                $this->plugin->init_epg_manager();
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case PARAM_EPG_CACHE_TIME:
                $this->plugin->set_setting($control_id, $user_input->{$control_id});
                break;

            case self::CONTROL_ITEMS_REFRESH_EPG_CACHE:
                $this->plugin->clear_playlist_epg_cache();
                $this->plugin->reset_channels();
                $actions[] = Action_Factory::show_title_dialog(TR::t('entry_epg_cache_cleared'));
                $actions[] = Action_Factory::reset_controls($this->do_get_control_defs());
                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                return Action_Factory::composite($actions);

            case PARAM_FAKE_EPG:
                $this->plugin->toggle_setting($control_id, false);
                $this->plugin->init_epg_manager();
                break;

            case ACTION_RELOAD:
                hd_debug_print(ACTION_RELOAD);
                if ($this->plugin->load_channels($plugin_cookies, true)) {
                    $actions[] = Action_Factory::reset_controls($this->do_get_control_defs());
                } else {
                    $actions[] = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST));
                }

                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                return Action_Factory::composite($actions);
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }
}
