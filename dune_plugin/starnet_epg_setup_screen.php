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
require_once 'lib/epg_manager.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Epg_Setup_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'epg_setup';

    const ACTION_RELOAD_EPG = 'reload_epg';
    const CONTROL_EPG_SOURCE_TYPE = 'epg_source_type';
    const CONTROL_XMLTV_EPG_IDX = 'xmltv_epg_idx';
    const CONTROL_CHANGE_XMLTV_CACHE_PATH = 'xmltv_cache_path';
    const CONTROL_ITEMS_CLEAR_EPG_CACHE = 'clear_epg_cache';

    ///////////////////////////////////////////////////////////////////////

    /**
     * EPG dialog defs
     * @return array
     */
    public function do_get_control_defs()
    {
        hd_debug_print(null, true);

        $defs = array();

        $this->plugin->init_playlist();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // XMLTV sources

        $sources = $this->plugin->get_all_xmltv_sources();
        $source_key = $this->plugin->get_active_xmltv_source_key();
        $display_sources = array();
        foreach ($sources as $key => $source) {
            if ($source === EPG_SOURCES_SEPARATOR_TAG) continue;

            $display_sources[$key] = HD::string_ellipsis($source);
        }

        $item = $sources->get($source_key);
        if (empty($item) && $sources->size()) {
            $order = $sources->get_order();
            $source_key = reset($order);
        }

        $display_path = HD::string_ellipsis($sources->get($source_key));
        if ($sources->size() > 1) {
            Control_Factory::add_combobox($defs, $this, null,
                self::CONTROL_XMLTV_EPG_IDX, TR::t('setup_xmltv_epg_source'),
                $source_key, $display_sources, self::CONTROLS_WIDTH, true);
        } else {
            Control_Factory::add_label($defs, TR::t('setup_xmltv_epg_source'), empty($display_path) ? TR::t('no') : $display_path);
        }

        Control_Factory::add_image_button($defs, $this, null, ACTION_ITEMS_EDIT,
            TR::t('setup_edit_xmltv_list'), TR::t('edit'), get_image_path('edit.png'), self::CONTROLS_WIDTH);

        if ($sources->size() > 0) {
            Control_Factory::add_image_button($defs, $this, null, self::ACTION_RELOAD_EPG,
                TR::t('setup_reload_xmltv_epg'), TR::t('refresh'), get_image_path('refresh.png'), self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // EPG cache dir
        $xcache_dir = $this->plugin->get_xmltv_cache_dir();
        $free_size = TR::t('setup_storage_info__1', HD::get_storage_size($xcache_dir));
        $xcache_dir = HD::string_ellipsis($xcache_dir . DIRECTORY_SEPARATOR);
        Control_Factory::add_image_button($defs, $this, null, self::CONTROL_CHANGE_XMLTV_CACHE_PATH,
            $free_size, $xcache_dir, get_image_path('folder.png'), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // EPG cache
        $epg_cache_ops = array();
        $epg_cache_ops[1] = 1;
        $epg_cache_ops[2] = 2;
        $epg_cache_ops[3] = 3;
        $epg_cache_ops[5] = 5;
        $epg_cache_ops[7] = 7;

        $cache_ttl = $this->plugin->get_setting(PARAM_EPG_CACHE_TTL, 3);
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_EPG_CACHE_TTL, TR::t('setup_epg_cache_ttl'),
            $cache_ttl, $epg_cache_ops, self::CONTROLS_WIDTH, true);

        //////////////////////////////////////
        // clear epg cache
        Control_Factory::add_image_button($defs, $this, null,
            self::CONTROL_ITEMS_CLEAR_EPG_CACHE, TR::t('entry_epg_cache_clear'), TR::t('clear'),
            get_image_path('brush.png'), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // epg time shift
        /*
        $show_epg_shift_ops = array();
        for ($i = -11; $i < 12; $i++) {
            $show_epg_shift_ops[$i] = TR::t('setup_epg_shift__1', sprintf("%+03d", $i));
        }
        $show_epg_shift_ops[0] = TR::t('setup_epg_shift_default__1', "00");

        if (!isset($plugin_cookies->{PARAM_EPG_SHIFT})) {
            $plugin_cookies->{PARAM_EPG_SHIFT} = 0;
        }
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_EPG_SHIFT, TR::t('setup_epg_shift'),
            $plugin_cookies->{PARAM_EPG_SHIFT}, $show_epg_shift_ops, self::CONTROLS_WIDTH);
        */
        return $defs;
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
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $action_reload = User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);
        $control_id = $user_input->control_id;
        if (isset($user_input->action_type, $user_input->{$control_id})
            && ($user_input->action_type === 'confirm' || $user_input->action_type === 'apply')) {
            $new_value = $user_input->{$control_id};
            hd_debug_print("Setup: changing $control_id value to $new_value");
        }

        switch ($control_id) {
            case self::CONTROL_XMLTV_EPG_IDX:
                $index = $user_input->{$control_id};
                $this->plugin->set_active_xmltv_source_key($index);
                hd_debug_print("Selected xmltv epg: ($index): {$this->plugin->get_all_xmltv_sources()->get($index)}");
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case self::CONTROL_CHANGE_XMLTV_CACHE_PATH:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'allow_network' => false,
                        'choose_folder' => static::ID,
                        'allow_reset' => true,
                        'end_action' => ACTION_RELOAD,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_epg_xmltv_cache_caption'));

            case PARAM_EPG_CACHE_TTL:
            case PARAM_EPG_SHIFT:
                $this->plugin->set_setting($control_id, $user_input->{$control_id});
                break;

            case self::CONTROL_ITEMS_CLEAR_EPG_CACHE:
                $this->plugin->tv->unload_channels();
                $this->plugin->epg_man->clear_epg_cache();
                return Action_Factory::show_title_dialog(TR::t('entry_epg_cache_cleared'),
                    Action_Factory::reset_controls($this->do_get_control_defs()));

            case ACTION_ITEMS_EDIT:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Edit_List_Screen::ID,
                        'source_window_id' => static::ID,
                        'edit_list' => Starnet_Edit_List_Screen::SCREEN_EDIT_EPG_LIST,
                        'end_action' => ACTION_RELOAD,
                        'cancel_action' => RESET_CONTROLS_ACTION_ID,
                        'save_data' => PLUGIN_PARAMETERS,
                        'extension' => EPG_PATTERN,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('setup_edit_xmltv_list'));

            case ACTION_RESET_DEFAULT:
                hd_debug_print(ACTION_RESET_DEFAULT);
                $this->plugin->epg_man->clear_all_epg_cache();

                $this->plugin->set_xmltv_cache_dir(null);
                $default_path = $this->plugin->get_xmltv_cache_dir();
                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $default_path),
                    $action_reload, $default_path, self::CONTROLS_WIDTH);

            case ACTION_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                hd_debug_print(ACTION_FOLDER_SELECTED . ": $data->filepath");
                if ($this->plugin->get_xmltv_cache_dir() === $data->filepath) break;

                $this->plugin->epg_man->clear_all_epg_cache();
                $this->plugin->set_parameter(PARAM_XMLTV_CACHE_PATH, $data->filepath);
                $this->plugin->init_epg_manager();

                return Action_Factory::show_title_dialog(TR::t('folder_screen_selected_folder__1', $data->caption),
                    $action_reload, $data->filepath, self::CONTROLS_WIDTH);

            case self::ACTION_RELOAD_EPG:
                $this->plugin->epg_man->clear_epg_cache();
                hd_debug_print(self::ACTION_RELOAD_EPG);
                $cmd = 'wget --quiet -O - "'. get_plugin_cgi_url('index_epg.sh.sh') . '" > /dev/null &';
                hd_debug_print("exec: $cmd", true);
                exec($cmd);
                sleep(1);
                return User_Input_Handler_Registry::create_action($this, ACTION_SHOW_INDEX_PROGRESS);

            case ACTION_SHOW_INDEX_PROGRESS:
                $counter['counter'] = 0;
                if (isset($user_input->counter)) {
                    $counter['counter'] = $user_input->counter + 5;
                }

                $qtime = format_duration_seconds($counter['counter']);
                Control_Factory::add_label($defs, '', "Прошло $qtime сек.");

                if ($this->plugin->epg_man->is_index_locked()) {
                    hd_debug_print("index locked for {$counter['counter']} sec.", true);
                    $run = User_Input_Handler_Registry::create_action($this, ACTION_SHOW_INDEX_PROGRESS, null, $counter);
                } else {
                    $run = $action_reload;
                }

                $attrs[GUI_EVENT_TIMER] = Action_Factory::timer(5000);
                $attrs['actions'] = array(GUI_EVENT_TIMER => Action_Factory::close_dialog_and_run($run));
                return Action_Factory::show_dialog('Прогресс индексирования EPG', $defs, true, self::CONTROLS_WIDTH, $attrs);

            case ACTION_RELOAD:
                hd_debug_print(ACTION_RELOAD);
                $this->plugin->tv->reload_channels($plugin_cookies);
                return Action_Factory::invalidate_all_folders($plugin_cookies,
                    Action_Factory::reset_controls($this->do_get_control_defs()));
        }

        return Action_Factory::reset_controls($this->do_get_control_defs());
    }
}
