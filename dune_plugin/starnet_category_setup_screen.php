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

///////////////////////////////////////////////////////////////////////////

class Starnet_Category_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'category_setup';

    const ACTION_SHOW_ALL = 'show_all_channels';
    const ACTION_SHOW_FAVORITES = 'show_favorites';
    const ACTION_SHOW_HISTORY = 'show_history';
    const ACTION_SHOW_VOD = 'show_vod';
    const ACTION_SHOW_CHANGED_CHANNELS = 'show_changed_channels';
    const ACTION_SHOW_VOD_ICON = 'show_vod_icon';

    ///////////////////////////////////////////////////////////////////////

    protected $force_parent_reload = false;

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
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
     * interface dialog defs
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
        // picon settings

        $active_sources = $this->plugin->get_selected_xmltv_sources();
        if (count($active_sources) !==0) {
            $picons_ops[PLAYLIST_PICONS] = TR::t('playlist_picons');
            $picons_ops[XMLTV_PICONS] = TR::t('xmltv_picons');
            $picons_ops[COMBINED_PICONS] = TR::t('combined_picons');
            $picons_idx = $this->plugin->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS);
            Control_Factory::add_combobox($defs, $this, null, PARAM_USE_PICONS,
                TR::t('setup_channels_picons_source'), $picons_idx, $picons_ops, self::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // show all channels category
        $show_all = SwitchOnOff::to_def($this->plugin->get_bool_setting(PARAM_SHOW_ALL));
        hd_debug_print("All channels group: $show_all", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_ALL, TR::t('setup_show_all_channels'), SwitchOnOff::translate($show_all),
            get_image_path(SwitchOnOff::to_image($show_all)), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show favorites category
        $show_fav = SwitchOnOff::to_def($this->plugin->get_bool_setting(PARAM_SHOW_FAVORITES));
        hd_debug_print("Favorites group: $show_fav", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_FAVORITES, TR::t('setup_show_favorites'), SwitchOnOff::translate($show_fav),
            get_image_path(SwitchOnOff::to_image($show_fav)), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show history category
        $show_history = SwitchOnOff::to_def($this->plugin->get_bool_setting(PARAM_SHOW_HISTORY));
        hd_debug_print("History group: $show_history", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_HISTORY, TR::t('setup_show_history'), SwitchOnOff::translate($show_history),
            get_image_path(SwitchOnOff::to_image($show_history)), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show changed channels category
        $show_changed = SwitchOnOff::to_def($this->plugin->get_bool_setting(PARAM_SHOW_CHANGED_CHANNELS));
        hd_debug_print("Changed group: $show_changed", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_CHANGED_CHANNELS, TR::t('setup_show_changed_channels'), SwitchOnOff::translate($show_changed),
            get_image_path(SwitchOnOff::to_image($show_changed)), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // show VOD
        $show_mediateka = SwitchOnOff::to_def($this->plugin->get_bool_setting(PARAM_SHOW_VOD));
        hd_debug_print("VOD group: $show_mediateka", true);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_SHOW_VOD, TR::t('setup_show_vod'), SwitchOnOff::translate($show_mediateka),
            get_image_path(SwitchOnOff::to_image($show_mediateka)), self::CONTROLS_WIDTH);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("changing $control_id value to $new_value", true);
        }

        switch ($control_id) {
            case GUI_EVENT_KEY_RETURN:
                $reload = $this->force_parent_reload;
                $this->force_parent_reload = false;

                $post_action = Action_Factory::close_and_run();
                if ($reload) {
                    return Action_Factory::invalidate_all_folders($plugin_cookies, array(Starnet_Tv_Groups_Screen::ID), $post_action);
                }
                return $post_action;

            case PARAM_SHOW_ALL:
            case PARAM_SHOW_FAVORITES:
            case PARAM_SHOW_HISTORY:
            case PARAM_SHOW_CHANGED_CHANNELS:
            case PARAM_SHOW_VOD:
                $this->force_parent_reload = true;
                $this->plugin->toggle_setting($control_id);
                break;

            case PARAM_USE_PICONS:
                $this->force_parent_reload = true;
                $this->plugin->set_setting($user_input->control_id, $user_input->{$user_input->control_id});
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }

    protected static function param_to_group($value)
    {
        switch ($value) {
            case self::ACTION_SHOW_ALL:
                return TV_ALL_CHANNELS_GROUP_ID;

            case self::ACTION_SHOW_FAVORITES:
                return TV_FAV_GROUP_ID;

            case self::ACTION_SHOW_HISTORY:
                return TV_HISTORY_GROUP_ID;

            case self::ACTION_SHOW_CHANGED_CHANNELS:
                return TV_CHANGED_CHANNELS_GROUP_ID;

            case self::ACTION_SHOW_VOD:
                return VOD_GROUP_ID;

            default:
                return null;
        }
    }
}
