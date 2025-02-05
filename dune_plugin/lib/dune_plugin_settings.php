<?php
require_once 'hd.php';
require_once 'dune_plugin_constants.php';
require_once 'ui_parameters.php';

class dune_plugin_settings extends UI_Parameters
{
    /**
     * @var array
     */
    private $settings;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var array
     */
    private $postpone_save;

    /**
     * @var array
     */
    private $is_dirty;

    protected function init_settings()
    {
        $this->parameters = null;
        $this->settings = null;
        $this->setup_postpone(PLUGIN_PARAMETERS);
        $this->setup_postpone(PLUGIN_SETTINGS);
    }

    /**
     * @param string $item
     * @param bool $flag
     * @return void
     */
    protected function setup_postpone($item, $flag = false)
    {
        $this->postpone_save[$item] = $flag;
        $this->is_dirty[$item] = $flag;
    }

    /**
     * @param string $item
     * @return bool
     */
    protected function is_postpone($item)
    {
        return $this->postpone_save[$item];
    }

    /**
     * Set that settings/paramters contains unsaved changes
     *
     * @param bool $val
     * @param string $item
     */
    public function set_dirty($val = true, $item = PLUGIN_SETTINGS)
    {
        if (!is_null($item)) {
            $this->is_dirty[$item] = $val;
        }
    }

    /**
     * Is settings/parameters contains unsaved changes
     *
     * @return bool
     */
    public function is_dirty($item)
    {
        return $this->is_dirty[$item];
    }

    /**
     * Upgrade playlist settings
     *
     * @return void
     */
    public function upgrade_settings()
    {
        hd_debug_print(null, true);

        $this->load_settings(true);
        $this->set_postpone_save(true, PLUGIN_SETTINGS);
        if ($this->has_setting('cur_xmltv_sources')) {
            $active_sources = $this->get_setting('cur_xmltv_sources', new Hashed_Array());
            hd_debug_print("convert active sources to hashed array: " . $active_sources, true);
            $active_sources = $active_sources->get_keys();
            hd_debug_print("converted active sources: " . json_encode($active_sources), true);
            $this->set_setting(PARAM_SELECTED_XMLTV_SOURCES, $active_sources);
        }

        $move_parameters = array(PARAM_SHOW_ALL, PARAM_SHOW_FAVORITES, PARAM_SHOW_HISTORY, PARAM_SHOW_CHANGED_CHANNELS, PARAM_SHOW_VOD);
        foreach ($move_parameters as $parameter) {
            if (!$this->has_setting($parameter)) {
                $this->set_bool_setting($parameter, $this->get_bool_parameter($parameter));
            }
        }

        $show_vod = $this->get_setting(PARAM_SHOW_VOD);
        if ($show_vod !== SetupControlSwitchDefs::switch_on && $show_vod !== SetupControlSwitchDefs::switch_off) {
            $this->set_setting(PARAM_SHOW_VOD, SetupControlSwitchDefs::switch_on);
        }

        // obsolete settings
        $removed_parameters = array('cur_xmltv_sources', 'epg_cache_ttl', 'epg_cache_ttl');
        foreach ($removed_parameters as $parameter) {
            if ($this->has_setting($parameter)) {
                $this->remove_setting($parameter);
            }
        }
        $this->set_postpone_save(false, PLUGIN_SETTINGS);
    }

    public function upgrade_parameters()
    {
        hd_debug_print(null, true);

        $this->load_parameters(true);

        // obsolete parameters
        $removed_parameters = array(
            'config_version', 'cur_xmltv_source', 'cur_xmltv_key', 'fuzzy_search_epg', ALL_CHANNELS_GROUP_ID,
            PARAM_EPG_JSON_PRESET, PARAM_BUFFERING_TIME, PARAM_ICONS_IN_ROW, PARAM_CHANNEL_POSITION,
            PARAM_EPG_CACHE_ENGINE, PARAM_PER_CHANNELS_ZOOM,
        );

        $this->set_postpone_save(true, PLUGIN_PARAMETERS);
        foreach ($removed_parameters as $parameter) {
            if ($this->has_parameter($parameter)) {
                $this->remove_parameter($parameter);
            }
        }

        // Fix old playlist params that not have playlist type
        $playlists = $this->get_parameter(PARAM_PLAYLIST_STORAGE, new Hashed_Array());
        /** @var Named_Storage $playlist */
        foreach ($playlists as $playlist) {
            if ($playlist->type === PARAM_FILE || $playlist->type === PARAM_LINK) {
                if (!isset($playlist->params[PARAM_PL_TYPE])) {
                    $playlist->params[PARAM_PL_TYPE] = CONTROL_PLAYLIST_IPTV;
                }
            }
        }

        $this->set_postpone_save(false, PLUGIN_PARAMETERS);
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin settings methods (per playlist configuration)
    //

    /**
     * load playlist settings
     *
     * @param bool $force
     * @return void
     */
    public function load_settings($force = false)
    {
        $id = $this->get_parameter(PARAM_CUR_PLAYLIST_ID);
        if (!empty($id)) {
            if (!isset($this->{PLUGIN_SETTINGS}) || $force) {
                hd_debug_print(null, true);
                $this->load("$id.settings", PLUGIN_SETTINGS, $force);
            }
        }
    }

    /**
     * Remove settings for selected playlist
     * @param string $id
     */
    public function unlink_settings($id)
    {
        unset($this->settings);
        hd_debug_print("remove $id.settings", true);
        HD::erase_data_items("$id.settings");

        foreach (glob_dir(get_cached_image_path(), "/^$id.*$/i") as $file) {
            hd_debug_print("remove cached image: $file", true);
            unlink($file);
        }
    }

    /**
     * Remove setting for selected playlist
     *
     * @param string $param
     */
    public function remove_setting($param)
    {
        hd_debug_print(null, true);
        hd_debug_print("Remove setting: $param", true);
        if (array_key_exists($param, $this->settings)) {
            unset($this->settings[$param]);
            $this->set_dirty();
            $this->save_settings();
        }
    }

    /**
     * load playlist settings by ID
     *
     * @param string $id
     * @return array
     */
    static public function get_settings($id)
    {
        if (empty($id)) {
            return array();
        }

        return HD::get_data_items("$id.settings", true, false);
    }

    /**
     * load playlist settings by ID
     *
     * @param string $id
     * @param array $data
     * @return void
     */
    static public function put_settings($id, $data)
    {
        if (!empty($id)) {
            HD::put_data_items("$id.settings", $data, false);
        }
    }

    /**
     * Get settings for selected playlist
     *
     * @param string $type
     * @param mixed|null $default
     * @return mixed
     */
    public function &get_setting($type, $default = null)
    {
        $this->load_settings();

        if (!isset($this->settings[$type])) {
            $this->settings[$type] = $default;
        } else {
            $default_type = gettype($default);
            $param_type = gettype($this->settings[$type]);
            if ($default_type === 'object' && $param_type !== $default_type) {
                hd_debug_print("Settings type requested: $default_type. But $param_type loaded. Reset to default", true);
                $this->settings[$type] = $default;
            }
        }

        return $this->settings[$type];
    }

    /**
     * Set settings for selected playlist
     *
     * @param string $param
     * @param mixed $val
     */
    public function set_setting($param, $val)
    {
        hd_debug_print(null, true);
        hd_debug_print("Set setting: $param", true);

        $this->settings[$param] = $val;
        $this->set_dirty();
        $this->save_settings();
    }

    /**
     * Is set settings for selected playlist
     *
     * @param string $type
     */
    public function has_setting($type)
    {
        return array_key_exists($type, $this->settings);
    }

    /**
     * @param string $param
     * @param bool $default
     * @return bool
     */
    public function toggle_setting($param, $default = true)
    {
        $new_val = !$this->get_bool_setting($param, $default);
        $this->set_bool_setting($param, $new_val);
        return $new_val;
    }

    /**
     * Get plugin boolean parameters
     *
     * @param string $type
     * @param bool $default
     * @return bool
     */
    public function get_bool_setting($type, $default = true)
    {
        return $this->get_setting($type,
                $default ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on;
    }

    /**
     * Set plugin boolean parameters
     *
     * @param string $type
     * @param bool $val
     */
    public function set_bool_setting($type, $val = true)
    {
        $this->set_setting($type, $val ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off);
    }

    /**
     * save playlist settings
     *
     * @param bool $force
     * @return bool
     */
    public function save_settings($force = false)
    {
        if ($force || $this->is_dirty(PLUGIN_SETTINGS)) {
            hd_debug_print(null, true);
        }

        return $this->save($this->get_parameter(PARAM_CUR_PLAYLIST_ID) . '.settings', PLUGIN_SETTINGS, $force);
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin parameters (global)
    //

    /**
     * Get plugin parameter type
     *
     * @param string $param
     * @return string|null
     */
    public function get_parameter_type($param)
    {
        $this->load_parameters();

        if (!isset($this->parameters[$param])) {
            return null;
        }

        $type = gettype($this->parameters[$param]);
        return ($type === 'object') ? get_class($this->parameters[$param]) : $type;
    }

    /**
     * Is set settings for selected playlist
     *
     * @param string $type
     */
    public function has_parameter($type)
    {
        return array_key_exists($type, $this->parameters);
    }

    /**
     * @param string $param
     * @param bool $default
     * @return bool
     */
    public function toggle_parameter($param, $default = true)
    {
        $new_val = !$this->get_bool_parameter($param, $default);
        $this->set_bool_parameter($param, $new_val);
        return $new_val;
    }

    /**
     * Get plugin boolean parameters
     *
     * @param string $type
     * @param bool $default
     * @return bool
     */
    public function get_bool_parameter($type, $default = true)
    {
        return $this->get_parameter($type,
                $default ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off) === SetupControlSwitchDefs::switch_on;
    }

    /**
     * Set plugin boolean parameters
     *
     * @param string $type
     * @param bool $val
     */
    public function set_bool_parameter($type, $val = true)
    {
        $this->set_parameter($type, $val ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off);
    }

    /**
     * Get global plugin parameters
     * Parameters does not depend on playlists and used globally
     *
     * @param string $param
     * @param mixed|null $default
     * @return mixed
     */
    public function &get_parameter($param, $default = null)
    {
        $this->load_parameters();

        if (!isset($this->parameters[$param])) {
            if ($default !== null) {
                hd_debug_print("load default $param: $default", true);
            }
            $this->parameters[$param] = $default;
        } else {
            $default_type = gettype($default);
            $param_type = gettype($this->parameters[$param]);
            if ($default_type === 'object' && $param_type !== $default_type) {
                hd_debug_print("Parameter type requested: $default_type. But $param_type loaded. Reset to default", true);
                $this->parameters[$param] = $default;
            }
        }

        return $this->parameters[$param];
    }

    /**
     * Load global plugin settings
     *
     * @param bool $force
     * @return void
     */
    public function load_parameters($force = false)
    {
        if (!isset($this->{PLUGIN_PARAMETERS}) || $force) {
            hd_debug_print(null, true);
            $this->load('common.settings', PLUGIN_PARAMETERS, $force);
        }
    }

    /**
     * Set global plugin parameter
     * Parameters does not depend on playlists and used globally
     *
     * @param string $param
     * @param mixed $val
     */
    public function set_parameter($param, $val)
    {
        hd_debug_print(null, true);
        hd_debug_print("Set parameter: $param", true);

        $this->parameters[$param] = $val;
        $this->set_dirty(true, PLUGIN_PARAMETERS);
        $this->save_parameters();
    }

    /**
     * Remove parameter
     * @param string $param
     */
    public function remove_parameter($param)
    {
        if (array_key_exists($param, $this->parameters)) {
            unset($this->parameters[$param]);
            $this->set_dirty(true, PLUGIN_PARAMETERS);
            $this->save_parameters();
        }
    }

    /**
     * save plugin parameters
     *
     * @param bool $force
     * @return bool
     */
    public function save_parameters($force = false)
    {
        if ($force || $this->is_dirty(PLUGIN_PARAMETERS)) {
            hd_debug_print(null, true);
        }

        return $this->save('common.settings', PLUGIN_PARAMETERS, $force);
    }

    /**
     * Block or release save settings action
     * If released will perform save action
     *
     * @param bool $snooze
     * @param string $item
     */
    public function set_postpone_save($snooze, $item)
    {
        hd_debug_print(null, true);
        hd_debug_print("Snooze: " . var_export($snooze, true) . ", item: $item", true);
        $this->postpone_save[$item] = $snooze;
        if ($snooze) {
            return;
        }

        if ($item === PLUGIN_SETTINGS) {
            $this->save_settings();
        } else if ($item === PLUGIN_PARAMETERS) {
            $this->save_parameters();
        }
    }

    ///////////////////////////////////////////////////////////////////////
    // Private methods
    //

    /**
     * load plugin/playlist/orders/history settings
     *
     * @param string $name
     * @param string $type
     * @param bool $force
     * @return void
     */
    private function load($name, $type, $force = false)
    {
        if ($force) {
            hd_debug_print(null, true);
            hd_debug_print("Force load ($type): $name");
            $this->{$type} = null;
        }

        if (!isset($this->{$type})) {
            hd_debug_print(null, true);
            hd_debug_print("Load ($type): $name");
            $this->{$type} = HD::get_data_items($name, true, false);
            if (LogSeverity::$is_debug) {
                foreach ($this->{$type} as $key => $param) {
                    hd_debug_print("$key => '" . (is_array($param) ? json_encode($param) : $param) . "'");
                }
            }
        }
    }

    /**
     * save data, settings or parameters
     * @param string $name
     * @param string $type
     * @param bool $force
     * @return bool
     */
    private function save($name, $type, $force = false)
    {
        if (is_null($this->{$type})) {
            hd_debug_print("this->$type is not set!", true);
            return false;
        }

        if ($this->postpone_save[$type] && !$force) {
            return false;
        }

        if ($force || $this->is_dirty($type)) {
            hd_debug_print(null, true);
            hd_debug_print("Save: $name", true);
            if (LogSeverity::$is_debug) {
                foreach ($this->{$type} as $key => $param) {
                    hd_debug_print("$key => " . (is_array($param) ? json_encode($param) : $param));
                }
            }
            HD::put_data_items($name, $this->{$type}, false);
            $this->set_dirty(false, $type);
            return true;
        }

        return false;
    }
}