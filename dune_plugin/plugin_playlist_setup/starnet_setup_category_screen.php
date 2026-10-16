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
require_once 'lib/list_utils.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Setup_Category_Screen extends Abstract_Controls_Screen
{
    const ID = 'category_setup';

    const ACTION_APPLY_PICONS_SOURCE = 'apply_picons_source';

    ///////////////////////////////////////////////////////////////////////

    protected $force_parent_reload = false;

    /**
     * interface dialog defs
     * @return array
     */
    protected function do_get_control_defs($media_url = null, &$plugin_cookies = null)
    {
        hd_debug_print(null, true);

        $defs = array();
        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // picon settings
        $picons_sources = $this->plugin->get_picons_sources();
        $captions = array();
        foreach ($picons_sources as $source) {
            $captions[] = TR::load($source);
        }
        Control_Factory::add_button($defs, $this, PARAM_PICONS_SOURCE, TR::t('setup_channels_picons_source'),
            implode(' + ', $captions));

        //////////////////////////////////////
        // Delayed picons indexing
        if (in_array(XMLTV_PICONS, $picons_sources) && is_delay_load_supported()) {
            $delay_load = $this->plugin->get_setting(PARAM_PICONS_DELAY_LOAD, SwitchOnOff::off);
            Control_Factory::add_image_button($defs, $this, PARAM_PICONS_DELAY_LOAD,
                TR::t('setup_channels_delay_picons_load'), SwitchOnOff::translate($delay_load),
                SwitchOnOff::to_image($delay_load));
        }

        //////////////////////////////////////
        // show all channels category
        $show_all = $this->plugin->get_setting(PARAM_SHOW_ALL, SwitchOnOff::on);
        hd_debug_print("All channels group: $show_all", true);
        Control_Factory::add_image_button($defs, $this, PARAM_SHOW_ALL,
            TR::t('setup_show_all_channels'), SwitchOnOff::translate($show_all), SwitchOnOff::to_image($show_all));

        //////////////////////////////////////
        // show favorites category
        $show_fav = $this->plugin->get_setting(PARAM_SHOW_FAVORITES, SwitchOnOff::on);
        hd_debug_print("Favorites group: $show_fav", true);
        Control_Factory::add_image_button($defs, $this, PARAM_SHOW_FAVORITES,
            TR::t('setup_show_favorites'), SwitchOnOff::translate($show_fav), SwitchOnOff::to_image($show_fav));

        //////////////////////////////////////
        // show history category
        $show_history = $this->plugin->get_setting(PARAM_SHOW_HISTORY, SwitchOnOff::on);
        hd_debug_print("History group: $show_history", true);
        Control_Factory::add_image_button($defs, $this, PARAM_SHOW_HISTORY,
            TR::t('setup_show_history'), SwitchOnOff::translate($show_history), SwitchOnOff::to_image($show_history));

        //////////////////////////////////////
        // show changed channels category
        $show_changed = $this->plugin->get_setting(PARAM_SHOW_CHANGED_CHANNELS, SwitchOnOff::on);
        hd_debug_print("Changed group: $show_changed", true);
        Control_Factory::add_image_button($defs, $this, PARAM_SHOW_CHANGED_CHANNELS,
            TR::t('setup_show_changed_channels'), SwitchOnOff::translate($show_changed), SwitchOnOff::to_image($show_changed));

        //////////////////////////////////////
        // show VOD
        $show_vod = $this->plugin->get_setting(PARAM_SHOW_VOD, SwitchOnOff::on);
        hd_debug_print("VOD group: $show_vod", true);
        Control_Factory::add_image_button($defs, $this, PARAM_SHOW_VOD,
            TR::t('setup_show_vod'), SwitchOnOff::translate($show_vod), SwitchOnOff::to_image($show_vod));

        //////////////////////////////////////
        // show adult
        $show_adult = $this->plugin->get_setting(PARAM_SHOW_ADULT, SwitchOnOff::on);
        hd_debug_print("Adult group: $show_adult", true);
        Control_Factory::add_image_button($defs, $this, PARAM_SHOW_ADULT,
            TR::t('setup_show_adult'), SwitchOnOff::translate($show_adult), SwitchOnOff::to_image($show_adult));

        //////////////////////////////////////
        // Show channel count
        $show_count = $this->plugin->get_setting(PARAM_SHOW_CLASSIC_CHANNEL_COUNT, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, PARAM_SHOW_CLASSIC_CHANNEL_COUNT,
            TR::t('setup_show_channel_count'), SwitchOnOff::translate($show_count), SwitchOnOff::to_image($show_count));

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $control_id = $user_input->control_id;
        $post_action = null;

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $reload = $this->force_parent_reload;
                $this->force_parent_reload = false;
                hd_debug_print('Force parent reload', true);
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                $post_action = self::make_return_action($parent_media_url);

                if ($reload) {
                    return Action_Factory::invalidate_all_folders($plugin_cookies, null, $post_action);
                }
                return $post_action;

            case PARAM_SHOW_ALL:
            case PARAM_SHOW_FAVORITES:
            case PARAM_SHOW_HISTORY:
            case PARAM_SHOW_CHANGED_CHANNELS:
            case PARAM_SHOW_VOD:
            case PARAM_SHOW_ADULT:
                $this->force_parent_reload = true;
                $this->plugin->toggle_setting($control_id, true);
                $this->plugin->update_ui_settings();
                break;

            case PARAM_PICONS_DELAY_LOAD:
            case PARAM_SHOW_CLASSIC_CHANNEL_COUNT:
                $this->force_parent_reload = true;
                $this->plugin->toggle_setting($control_id, false);
                $this->plugin->update_ui_settings();
                break;

            case PARAM_PICONS_SOURCE:
                // enabled sources first in the current order, disabled ones after them
                $picons_sources = $this->plugin->get_picons_sources();
                $cfg = array();
                foreach ($picons_sources as $source) {
                    $cfg[$source] = 1;
                }
                foreach (array(PLAYLIST_PICONS, XMLTV_PICONS) as $source) {
                    if (!isset($cfg[$source])) {
                        $cfg[$source] = 0;
                    }
                }

                $all_items = array();
                $all_ids = array();
                foreach ($cfg as $source => $enabled) {
                    Action_Factory::add_gui_item($all_items, $all_ids, $source, TR::t($source));
                }

                $config_id = $this->get_picons_config_id();
                List_Utils::write_config_file($config_id, $cfg);

                return Action_Factory::edit_list_config($config_id, TR::t('setup_channels_picons_source'),
                    $all_items, $picons_sources,
                    null, null, null, null,
                    User_Input_Handler_Registry::create_action($this, self::ACTION_APPLY_PICONS_SOURCE));

            case self::ACTION_APPLY_PICONS_SOURCE:
                $config_id = $this->get_picons_config_id();
                $cfg = List_Utils::read_config_file($config_id);
                safe_unlink(List_Utils::config_file_path($config_id));

                $picons_sources = array();
                foreach ($cfg as $source => $enabled) {
                    if ($enabled && ($source === PLAYLIST_PICONS || $source === XMLTV_PICONS)) {
                        $picons_sources[] = $source;
                    }
                }

                if (empty($picons_sources)) {
                    // dialog cancelled or nothing selected, keep current settings
                    break;
                }

                if ($picons_sources !== $this->plugin->get_picons_sources()) {
                    $this->force_parent_reload = true;
                    $this->plugin->set_picons_sources($picons_sources);
                }

                if (in_array(XMLTV_PICONS, $picons_sources)) {
                    $active_sources = $this->plugin->get_selected_xmltv_ids();
                    if (empty($active_sources)) {
                        $post_action = Action_Factory::show_title_dialog(TR::t('error'), TR::t('err_no_xmltv_sources'));
                    }
                }
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs(), $post_action);
    }

    /**
     * List config id for picons sources dialog of the active playlist
     *
     * @return string
     */
    protected function get_picons_config_id()
    {
        return 'proiptv_picons_' . $this->plugin->get_active_playlist_id();
    }
}
