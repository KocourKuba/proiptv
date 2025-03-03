<?php
require_once 'sql_wrapper.php';

class Dune_Default_Sqlite_Engine
{
    const PLAYLIST_ORDERS_DB = 'playlist_orders';
    const TV_HISTORY_DB = 'tv_history';
    const VOD_HISTORY_DB = 'vod_history';

    const PLAYLISTS_TABLE = 'playlists';
    const PARAMETERS_TABLE = 'parameters';

    const GROUPS_INFO_TABLE = 'groups_info';
    const GROUPS_ORDER_TABLE = 'groups_order';
    const CHANNELS_INFO_TABLE = 'channels_info';

    const FAV_TV_ORDERS_TABLE = 'tv_fav_orders';
    const FAV_VOD_ORDERS_TABLE = 'vod_fav_orders';

    const VOD_SEARCHES_TABLE = 'vod_searches';
    const VOD_FILTERS_TABLE = 'vod_filters';

    const TV_HISTORY_TABLE = 'tv_history';
    const VOD_HISTORY_TABLE = 'vod_history';

    const XMLTV_TABLE = 'xmltv_sources';
    const SELECTED_XMLTV_TABLE = 'selected_xmltv';

    const SETTINGS_TABLE = 'settings';
    const COOKIES_TABLE = 'cookies';

    const CREATE_PLAYLISTS_TABLE = "CREATE TABLE IF NOT EXISTS %s (playlist_id TEXT PRIMARY KEY NOT NULL, shortcut TEXT DEFAULT '');";
    const CREATE_PARAMETERS_TABLE = "CREATE TABLE IF NOT EXISTS %s (name TEXT PRIMARY KEY, value TEXT);";

    // orders_xxxx, GROUPS_ORDER_TABLE, VOD_SEARCHES_TABLE, VOD_FILTERS_TABLE, FAV_MOVIE_GROUP_ID
    const CREATE_ORDERED_TABLE = "CREATE TABLE IF NOT EXISTS %s (%s TEXT PRIMARY KEY NOT NULL);";

    const CREATE_GROUPS_INFO_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                        (id INTEGER PRIMARY KEY AUTOINCREMENT,
                                         group_id TEXT UNIQUE,
                                         title TEXT DEFAULT '',
                                         icon TEXT DEFAULT '',
                                         adult INTEGER DEFAULT 0,
                                         disabled INTEGER DEFAULT 0,
                                         special INTEGER DEFAULT 0);";

    const CREATE_CHANNELS_INFO_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                        (channel_id TEXT PRIMARY KEY NOT NULL,
                                         title TEXT DEFAULT '',
                                         group_id TEXT DEFAULT '',
                                         disabled INTEGER DEFAULT 0,
                                         adult INTEGER DEFAULT 0,
                                         changed INTEGER DEFAULT 1,
                                         zoom TEXT,
                                         external_player INTEGER DEFAULT 0);";

    const CREATE_PLAYLIST_SETTINGS_TABLE = "CREATE TABLE IF NOT EXISTS %s (name TEXT PRIMARY KEY NOT NULL, value TEXT DEFAULT '', type TEXT DEFAULT '');";
    const CREATE_XMLTV_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                    (hash TEXT PRIMARY KEY NOT NULL, type TEXT, name TEXT NOT NULL, uri TEXT NOT NULL, cache TEXT DEFAULT 'auto');";
    const CREATE_SELECTED_XMTLV_TABLE = "CREATE TABLE IF NOT EXISTS %s (hash TEXT PRIMARY KEY NOT NULL);";
    const CREATE_COOKIES_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                    (param TEXT PRIMARY KEY NOT NULL, value TEXT DEFAULT '', time_stamp INTEGER DEFAULT 0);";

    const CREATE_TV_HISTORY_TABLE = "CREATE TABLE IF NOT EXISTS %s (channel_id TEXT PRIMARY KEY NOT NULL, time_stamp INTEGER DEFAULT 0);";
    const CREATE_VOD_HISTORY_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                        (movie_id TEXT, series_id TEXT, watched INTEGER DEFAULT 0, position INTEGER DEFAULT 0,
                                        duration INTEGER DEFAULT 0, time_stamp INTEGER DEFAULT 0, UNIQUE(movie_id, series_id));";

    /**
     * @var Sql_Wrapper
     */
    protected $sql_params;

    /**
     * @var Sql_Wrapper
     */
    protected $sql_playlist;

    /**
     * @var string
     */
    protected $channel_id_map = '';

    /**
     * @var string
     */
    protected $current_playback_channel_id;

    public function get_sql_playlist()
    {
        return $this->sql_playlist;
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin parameters methods (global)
    //

    /**
     * Load global plugin settings
     *
     * @return void
     */
    public function init_parameters()
    {
        hd_debug_print(null, true);

        $this->sql_params = new Sql_Wrapper(get_data_path("common.db"));
        $query =  sprintf(self::CREATE_PARAMETERS_TABLE, self::PARAMETERS_TABLE);
        $query .= sprintf(self::CREATE_PLAYLISTS_TABLE, self::PLAYLISTS_TABLE);
        $query .= sprintf(self::CREATE_XMLTV_TABLE, self::XMLTV_TABLE);
        $this->sql_params->exec_transaction($query);

        $parameters = HD::get_data_items('common.settings', true, false);
        if (!empty($parameters)) {
            hd_debug_print("Move 'common.settings' to common.db");
            $removed_parameters = array(
                'config_version', 'cur_xmltv_source', 'cur_xmltv_key', 'fuzzy_search_epg', 'force_http',
                PARAM_EPG_JSON_PRESET, PARAM_BUFFERING_TIME, PARAM_NEWUI_ICONS_IN_ROW, PARAM_NEWUI_CHANNEL_POSITION,
                PARAM_EPG_CACHE_ENGINE, PARAM_PER_CHANNELS_ZOOM, PARAM_SHOW_FAVORITES, PARAM_SHOW_HISTORY, PARAM_SHOW_ALL,
                PARAM_SHOW_CHANGED_CHANNELS, PARAM_FAKE_EPG, PARAM_SHOW_VOD, TV_ALL_CHANNELS_GROUP_ID,
            );

            $parameters_table = self::PARAMETERS_TABLE;
            $query = '';
            /** @var Named_Storage|string $param */
            foreach ($parameters as $key => $param) {
                if (in_array($key, $removed_parameters)) {
                    unset($parameters[$key]);
                    continue;
                }

                hd_debug_print("$key => '" . $param . "'");
                if ($key === PARAM_PLAYLIST_STORAGE) {
                    foreach ($param as $playlist_id => $stg) {
                        if (empty($playlist_id)) continue;

                        if (($stg->type === PARAM_FILE || $stg->type === PARAM_LINK) && !isset($stg->params[PARAM_PL_TYPE])) {
                            $stg->params[PARAM_PL_TYPE] = CONTROL_PLAYLIST_IPTV;
                        }

                        if ($stg->type === PARAM_PROVIDER) {
                            // remove obsolete parameters for provider
                            foreach (array(MACRO_TOKEN, MACRO_REFRESH_TOKEN, MACRO_SESSION_ID, MACRO_EXPIRE_DATA) as $macro) {
                                if (isset($stg->params[$macro])) {
                                    unset($stg->params[$macro]);
                                }
                            }
                        }

                        $values = array(
                            PARAM_TYPE => $stg->type,
                            PARAM_NAME => $stg->name,
                        );
                        $values = array_merge($values, $stg->params);
                        $this->set_playlist_parameters($playlist_id, $values);
                    }
                    unset($parameters[$key]);
                } else if ($key === PARAM_EXT_XMLTV_SOURCES) {
                    foreach ($param as $hash => $stg) {
                        if (!isset($stg->params[PARAM_URI]) || !is_proto_http($stg->params[PARAM_URI])) continue;

                        $item = array(
                            PARAM_HASH => $hash,
                            PARAM_TYPE => PARAM_LINK,
                            PARAM_NAME => $stg->name,
                            PARAM_URI => $stg->params[PARAM_URI],
                            PARAM_CACHE => safe_get_value($stg->params, PARAM_CACHE, XMLTV_CACHE_AUTO)
                        );
                        $this->set_xmltv_source(XMLTV_SOURCE_EXTERNAL, $item);
                    }
                    unset($parameters[$key]);
                } else {
                    $type = gettype($param);
                    if ($type === 'NULL' ) {
                        $param = '';
                    } else if ($type == 'boolean') {
                        $param = SwitchOnOff::to_def($param);
                    }
                    $q_key = Sql_Wrapper::sql_quote($key);
                    $q_param = Sql_Wrapper::sql_quote($param);
                    $query .= "INSERT OR IGNORE INTO $parameters_table (name, value) VALUES ($q_key, $q_param);";
                    unset($parameters[$key]);
                }
            }
            $this->sql_params->exec_transaction($query);
            if (empty($parameters)) {
                unlink(get_data_path("common.settings"));
            }
            foreach ($parameters as $key => $value) {
                hd_debug_print("!!!!! Parameter $key is not imported: " . $value);
            }
        }
    }

    /**
     * Set global plugin parameter
     * Parameters does not depend on playlists and used globally
     *
     * @param string $name
     * @param string $value
     */
    public function set_parameter($name, $value)
    {
        hd_debug_print(null, true);
        hd_debug_print("Set parameter: $name => $value", true);

        $table_name = self::PARAMETERS_TABLE;
        $q_name = Sql_Wrapper::sql_quote($name);
        $q_value = Sql_Wrapper::sql_quote($value);
        $this->sql_params->exec("INSERT OR REPLACE INTO $table_name (name, value) VALUES ($q_name, $q_value);");
    }

    /**
     * Get global plugin parameters
     * Parameters does not depend on playlists and used globally
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    public function get_parameter($name, $default = '')
    {
        $table_name = self::PARAMETERS_TABLE;
        $q_name = Sql_Wrapper::sql_quote($name);
        $value = $this->sql_params->query_value("SELECT value FROM $table_name WHERE name = $q_name;");
        if (empty($value)) {
            $value = $default;
        }
        return $value;
    }

    /**
     * Remove parameter
     *
     * @param string $name
     */
    public function remove_parameter($name)
    {
        $table_name = self::PARAMETERS_TABLE;
        $this->sql_params->exec("DELETE FROM $table_name WHERE name = $name;");
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
        return SwitchOnOff::to_bool($this->get_parameter($type, SwitchOnOff::to_def($default)));
    }

    /**
     * Set plugin boolean parameters
     *
     * @param string $type
     * @param bool $val
     */
    public function set_bool_parameter($type, $val = true)
    {
        $this->set_parameter($type, SwitchOnOff::to_def($val));
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin settings methods (per playlist configuration)
    //

    /**
     * @param string $playlist_id
     * @param array $stg
     * @return void
     */
    public function set_playlist_parameters($playlist_id, $stg)
    {
        hd_debug_print(null, true);
        hd_debug_print("Setting playlist $playlist_id to " . json_encode($stg), true);

        $table_name = self::PLAYLISTS_TABLE;

        // update playlist table
        $q_id = Sql_Wrapper::sql_quote($playlist_id);
        $query = "INSERT OR IGNORE INTO $table_name (playlist_id) VALUES ($q_id);";
        $this->sql_params->exec($query);

        // add params array to stg array as pair
        if (isset($stg[PARAM_PARAMS])) {
            foreach ($stg[PARAM_PARAMS] as $k => $v) {
                $stg[$k] = $v;
            }
            unset($stg[PARAM_PARAMS]);
        }

        // create table for playlist parameters if not exist
        $parameters_table = self::get_playlist_parameters_table_name($playlist_id);
        $query = sprintf(self::CREATE_PARAMETERS_TABLE, $parameters_table);
        // save parameter
        foreach ($stg as $name => $value) {
            $q_name = Sql_Wrapper::sql_quote($name);
            $q_value = Sql_Wrapper::sql_quote($value);
            $query .= "INSERT OR IGNORE INTO $parameters_table (name, value) VALUES ($q_name, $q_value);";
            $query .= "UPDATE $parameters_table SET value = $q_value WHERE name = $q_name;";
        }
        $this->sql_params->exec_transaction($query);
    }

    /**
     * @param string $playlist_id
     * @return array
     */
    public function get_playlist_parameters($playlist_id)
    {
        $parameters_table = self::get_playlist_parameters_table_name($playlist_id);
        $query = "SELECT * FROM $parameters_table;";
        $rows = $this->sql_params->fetch_array($query);
        if (empty($rows)) {
            return array();
        }
        $params = array();
        foreach ($rows as $row) {
            $params[$row[PARAM_NAME]] = $row[PARAM_VALUE];
        }
        return $params;
    }

    /**
     * @param string $playlist_id
     * @param string $name
     * @param string $value
     * @return void
     */
    public function set_playlist_parameter($playlist_id, $name, $value)
    {
        hd_debug_print(null, true);
        // create table for playlist parameters if not exist
        $parameters_table = self::get_playlist_parameters_table_name($playlist_id);
        $query = sprintf(self::CREATE_PARAMETERS_TABLE, $parameters_table);
        // save parameter
        $q_name = Sql_Wrapper::sql_quote($name);
        $q_value = Sql_Wrapper::sql_quote($value);
        $query .= "INSERT OR IGNORE INTO $parameters_table (name, value) VALUES ($q_name, $q_value);";
        $query .= "UPDATE $parameters_table SET value = $q_value WHERE name = $q_name;";
        $this->sql_params->exec_transaction($query);
    }

    /**
     * @param string $playlist_id
     * @param string $name
     * @param string $default
     * @return string
     */
    public function get_playlist_parameter($playlist_id, $name, $default = '')
    {
        if (empty($playlist_id)) {
            return $default;
        }

        $parameters_table = self::get_playlist_parameters_table_name($playlist_id);
        $q_name = Sql_Wrapper::sql_quote($name);
        $query = "SELECT value FROM $parameters_table WHERE name = $q_name;";
        $value = $this->sql_params->query_value($query);
        return empty($value) ? $default : $value;
    }

    /**
     * @param string $playlist_id
     * @param string $name
     * @return void
     */
    public function remove_playlist_parameter($playlist_id, $name)
    {
        hd_debug_print(null, true);
        $parameters_table = self::get_playlist_parameters_table_name($playlist_id);
        $q_name = Sql_Wrapper::sql_quote($name);
        $query = "DELETE FROM $parameters_table WHERE name = $q_name;";
        $this->sql_params->exec($query);
    }

    ///////////////////////////////////////////////////////////////////////
    // XMLTV table
    //

    /**
     * get xmltv sources
     *
     * @param string $type
     * @return Hashed_Array
     */
    public function get_xmltv_sources($type)
    {
        $sources = new Hashed_Array();
        if ($type === XMLTV_SOURCE_ALL || $type === XMLTV_SOURCE_PLAYLIST) {
            $table_name = self::get_table_name(XMLTV_SOURCE_PLAYLIST);
            $rows = $this->sql_playlist->fetch_array("SELECT * FROM $table_name;");
            foreach ($rows as $row) {
                $sources->set($row[PARAM_HASH], $row);
            }
        }

        if ($type === XMLTV_SOURCE_ALL || $type === XMLTV_SOURCE_EXTERNAL) {
            $table_name = self::get_table_name(XMLTV_SOURCE_EXTERNAL);
            $rows = $this->sql_params->fetch_array("SELECT * FROM $table_name;");
            foreach ($rows as $row) {
                $sources->set($row[PARAM_HASH], $row);
            }
        }

        return $sources;
    }

    /**
     * get xmltv sources hashes
     *
     * @param string $type
     * @return array
     */
    public function get_xmltv_sources_hash($type)
    {
        $hashes = array();
        if ($type === XMLTV_SOURCE_ALL || $type === XMLTV_SOURCE_PLAYLIST) {
            $table_name = self::get_table_name(XMLTV_SOURCE_PLAYLIST);
            $hashes = $this->sql_playlist->fetch_single_array("SELECT hash FROM $table_name;", 'hash');
        }

        if ($type === XMLTV_SOURCE_ALL || $type === XMLTV_SOURCE_EXTERNAL) {
            $table_name = self::get_table_name(XMLTV_SOURCE_EXTERNAL);
            $hashes = array_merge($hashes, $this->sql_params->fetch_single_array("SELECT hash FROM $table_name;", 'hash'));
        }

        return array_unique($hashes);
    }

    /**
     * get external xmltv sources count
     *
     * @param $type
     * @return int
     */
    public function get_xmltv_sources_count($type)
    {
        hd_debug_print(null, true);

        if ($type === XMLTV_SOURCE_PLAYLIST || $type === XMLTV_SOURCE_EXTERNAL) {
            $table_name = self::get_table_name($type);
            $wrapper = $type === XMLTV_SOURCE_PLAYLIST ? $this->sql_playlist : $this->sql_params;
            return $wrapper->query_value("SELECT COUNT(*) FROM $table_name;");
        }

        return $this->get_xmltv_sources_count(XMLTV_SOURCE_PLAYLIST) + $this->get_xmltv_sources_count(XMLTV_SOURCE_EXTERNAL);
    }

    /**
     * get xmltv source
     *
     * @param string $type
     * @param string $hash
     * @return array|null
     */
    public function get_xmltv_source($type, $hash)
    {
        hd_debug_print(null, true);

        if ($type === XMLTV_SOURCE_PLAYLIST || $type === XMLTV_SOURCE_EXTERNAL) {
            $table_name = self::get_table_name($type);
            $wrapper = $type === XMLTV_SOURCE_PLAYLIST ? $this->sql_playlist : $this->sql_params;
            return $wrapper->query_value("SELECT * FROM $table_name WHERE hash = '$hash';", true);
        }
        return null;
    }

    /**
     * set xmltv source
     *
     * @param string $type
     * @param array $value
     * @return void
     */
    public function set_xmltv_source($type, $value)
    {
        if ($type === XMLTV_SOURCE_PLAYLIST || $type === XMLTV_SOURCE_EXTERNAL) {
            $table_name = self::get_table_name($type);
            $wrapper = $type === XMLTV_SOURCE_PLAYLIST ? $this->sql_playlist : $this->sql_params;
            $q_list = Sql_Wrapper::sql_make_insert_list($value);
            $query = "INSERT OR REPLACE INTO $table_name $q_list;";
            $wrapper->exec($query);
        }
    }

    /**
     * Bulk set xmltv sources
     * @param string $type
     * @param Hashed_Array $values
     */
    public function set_xmltv_sources($type, $values)
    {
        hd_debug_print(null, true);

        if ($type === XMLTV_SOURCE_PLAYLIST || $type === XMLTV_SOURCE_EXTERNAL) {
            $table_name = self::get_table_name($type);
            $query = "DROP TABLE IF EXISTS $table_name;";
            $query .= sprintf(self::CREATE_XMLTV_TABLE, $table_name);
            foreach ($values as $key => $params) {
                $q_name = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_NAME));
                $q_type = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_TYPE));
                $q_uri = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_URI));
                $q_cache = Sql_Wrapper::sql_quote(safe_get_value($params, PARAM_CACHE, XMLTV_CACHE_AUTO));
                $query .= "INSERT INTO $table_name (hash, name, type, uri, cache) VALUES ('$key', $q_name, $q_type, $q_uri, $q_cache);";
            }
            $this->sql_playlist->exec_transaction($query);
        }
    }

    /**
     * remove xmltv sources
     *
     * @param string $type
     * @param string|array $hash
     * @return void
     */
    public function remove_xmltv_source($type, $hash)
    {
        hd_debug_print(null, true);

        if ($type === XMLTV_SOURCE_PLAYLIST || $type === XMLTV_SOURCE_EXTERNAL) {
            $table_name = self::get_table_name($type);
            $wrapper = $type === XMLTV_SOURCE_PLAYLIST ? $this->sql_playlist : $this->sql_params;
            $where = Sql_Wrapper::sql_make_where_clause($hash, 'hash');
            $query = "DELETE FROM $table_name $where;";
            $wrapper->exec($query);
        }
    }

    /**
     * @return array
     */
    public function get_selected_xmltv_sources()
    {
        $table_name = self::SELECTED_XMLTV_TABLE;
        return $this->sql_playlist->fetch_single_array("SELECT hash FROM $table_name;", PARAM_HASH);
    }

    /**
     * @param array $values
     */
    public function set_selected_xmltv_sources($values)
    {
        $table_name = self::SELECTED_XMLTV_TABLE;
        $query = "DROP TABLE IF EXISTS $table_name;";
        $query .= sprintf(self::CREATE_SELECTED_XMTLV_TABLE, $table_name);
        foreach ($values as $hash) {
            $query .= "INSERT INTO $table_name (hash) VALUES ('$hash');";
        }

        $this->sql_playlist->exec_transaction($query);
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin settings methods (per playlist configuration)
    //

    /**
     * load playlist settings by ID
     *
     * @param string $id
     * @param array $data
     * @return void
     */
    public function put_settings($id, $data)
    {
        if (!empty($id)) {
            $db = new Sql_Wrapper(get_data_path("$id.db"));
            $table_name = self::SETTINGS_TABLE;
            $query  = sprintf(self::CREATE_PLAYLIST_SETTINGS_TABLE, $table_name);
            foreach ($data as $key => $value) {
                $type = gettype($value);
                if ($type === 'NULL') {
                    $type = 'string';
                    $value = '';
                }
                $q_value = Sql_Wrapper::sql_quote($value);
                $query .= "INSERT OR IGNORE INTO $table_name (name, value, type) VALUES ('$key', $q_value, '$type');";
            }

            $db->exec_transaction($query);
        }
    }

    /**
     * Get settings for selected playlist
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get_setting($name, $default)
    {
        $table_name = self::SETTINGS_TABLE;
        $type = gettype($default);
        if ($this->sql_playlist !== null) {
            $row = $this->sql_playlist->query_value("SELECT value, type FROM $table_name WHERE name = '$name';", true);
        }

        if (empty($row)) {
            return $default;
        }

        settype($row['value'], $type);
        return $row['value'];
    }

    /**
     * Set settings for selected playlist
     *
     * @param string $name
     * @param mixed $value
     */
    public function set_setting($name, $value)
    {
        hd_debug_print(null, true);
        hd_debug_print("Set setting: $name => $value", true);

        $table_name = self::SETTINGS_TABLE;
        $q_value = Sql_Wrapper::sql_quote($value);
        $type = gettype($value);
        if ($this->sql_playlist) {
            $this->sql_playlist->exec("INSERT OR REPLACE INTO $table_name (name, value, type) VALUES ('$name', $q_value, '$type');");
        }
    }

    /**
     * Remove setting
     *
     * @param string $name
     */
    public function remove_setting($name)
    {
        $table_name = self::SETTINGS_TABLE;
        $this->sql_playlist->exec("DELETE FROM $table_name WHERE name = $name;");
    }

    /**
     * Toggle playlist boolean setting
     *
     * @param string $param
     * @param bool $default
     * @return bool
     */
    public function toggle_setting($param, $default = true)
    {
        $old = $this->get_bool_setting($param, $default);
        $new = !$old;
        $this->set_bool_setting($param, $new);
        return $new;
    }

    /**
     * Get playlist boolean setting
     *
     * @param string $type
     * @param bool $default
     * @return bool
     */
    public function get_bool_setting($type, $default = true)
    {
        $value = $this->get_setting($type, SwitchOnOff::to_def($default));
        return SwitchOnOff::to_bool($value);
    }

    /**
     * Set plugin boolean setting
     *
     * @param string $type
     * @param bool $val
     */
    public function set_bool_setting($type, $val = true)
    {
        $this->set_setting($type, SwitchOnOff::to_def($val));
    }

    ///////////////////////////////////////////////////////////////////////
    // Plugin cookies table
    //
    /**
     * Get cookie
     *
     * @param string $name
     * @param bool $check_expire
     * @return string
     */
    public function get_cookie($name, $check_expire = false)
    {
        if ($check_expire) {
            $were = "param = '$name' AND time_stamp > " . time();
        } else {
            $were = "param = '$name'";
        }
        $table_name = self::COOKIES_TABLE;
        return $this->sql_playlist->query_value("SELECT value FROM $table_name WHERE $were;");
    }

    /**
     * Get cookie
     *
     * @param string $name
     * @param string $value
     * @param int|null $expired
     */
    public function set_cookie($name, $value, $expired = null)
    {
        if ($expired === null) {
            $expired = time();
        }

        $table_name = self::COOKIES_TABLE;
        $this->sql_playlist->exec("INSERT OR REPLACE INTO $table_name (param, value, time_stamp) VALUES ('$name', '$value', '$expired');");
    }

    /**
     * Get cookie
     *
     * @param string $name
     */
    public function remove_cookie($name)
    {
        $table_name = self::COOKIES_TABLE;
        $this->sql_playlist->exec("DELETE FROM $table_name WHERE param = '$name';");
    }

    ///////////////////////////////////////////////////////////////////////
    // Storages methods
    //

    /**
     * Get all custom table values ordered by ROWID
     *
     * @param string $table
     * @return array
     */
    public function get_all_table_values($table)
    {
        $table_name = self::get_table_name($table);
        return $this->sql_playlist->fetch_array("SELECT * FROM $table_name ORDER BY ROWID ASC");
    }

    /**
     * count values
     *
     * @param string $table
     * @return array
     */
    public function get_all_table_values_count($table)
    {
        $table_name = self::get_table_name($table);
        return $this->sql_playlist->query_value("SELECT COUNT(*) FROM $table_name");
    }

    /**
     * Get ROWID for value
     *
     * @param string $table
     * @param string $value
     * @return array
     */
    public function get_table_value_id($table, $value)
    {
        $table_name = self::get_table_name($table);
        $q_value = Sql_Wrapper::sql_quote($value);
        return $this->sql_playlist->query_value("SELECT ROWID FROM $table_name WHERE item = $q_value");
    }

    /**
     * Get value by ROWID
     *
     * @param string $table
     * @param int $id
     * @return array
     */
    public function get_table_value($table, $id)
    {
        $table_name = self::get_table_name($table);
        return $this->sql_playlist->query_value("SELECT item FROM $table_name WHERE ROWID = $id");
    }

    /**
     * Update or add value
     *
     * @param string $table
     * @param string $value
     * @param int $id
     */
    public function set_table_value($table, $value, $id = -1)
    {
        $table_name = self::get_table_name($table);
        $q_value = Sql_Wrapper::sql_quote($value);
        if ($id === -1) {
            $this->sql_playlist->exec("INSERT OR IGNORE INTO $table_name (item) VALUES ($q_value);");
        } else {
            $this->sql_playlist->exec("UPDATE $table_name SET item = $q_value WHERE ROWID = $id;");
        }
    }

    /**
     * Remove value
     *
     * @param string $table
     * @param string $value
     */
    public function remove_table_value($table, $value)
    {
        $table_name = self::get_table_name($table);
        $q_value = Sql_Wrapper::sql_quote($value);
        $this->sql_playlist->exec("DELETE FROM $table_name WHERE item = $q_value;");
    }

    /**
     * Arrange values (VOD_SEARCH, VOD_FILTER)
     *
     * @param string $table
     * @param string $item
     * @param int $direction
     * @return bool
     */
    public function arrange_table_values($table, $item, $direction)
    {
        return $this->arrange_rows($table, 'item', $item, $direction);
    }

    /////////////////////////////////////////////////////////////////
    /// Changed channels

    /**
     * @param string $channel_id
     */
    public function remove_changed_channel($channel_id)
    {
        $table_name = self::get_table_name(CHANNELS_INFO);
        $id = Sql_Wrapper::sql_quote($channel_id);
        $this->sql_playlist->exec("UPDATE $table_name SET changed = 0 WHERE channel_id = $id");
    }

    /**
     * @param int $type
     * @return array
     */
    public function get_changed_channels($type)
    {
        $column = $this->get_id_column();
        $iptv_channels = M3uParser::CHANNELS_TABLE;
        $table_name = self::get_table_name(CHANNELS_INFO);
        if ($type === PARAM_NEW) {
            $query = "SELECT ch.ROWID, ch.channel_id, pl.*
                        FROM $table_name AS ch
                        JOIN $iptv_channels AS pl ON pl.$column = ch.channel_id
                        WHERE changed = 1 ORDER BY ch.ROWID;";
        } else if ($type === PARAM_REMOVED) {
            $query = "SELECT ROWID, channel_id, title FROM $table_name WHERE changed = -1 ORDER BY ROWID;";
        } else {
            $query = "SELECT ch.ROWID, ch.channel_id, pl.*, ch.title
                    FROM $table_name AS ch
                        LEFT JOIN $iptv_channels AS pl ON pl.$column = ch.channel_id
                    WHERE changed != 0
                    ORDER BY ch.ROWID;";
        }

        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param int $type // PARAM_NEW, PARAM_REMOVED, null or other value - total
     * @return array
     */
    public function get_changed_channels_ids($type)
    {
        $val = "changed = $type";
        if ($type == PARAM_CHANGED) {
            $val = "NOT $val";
        }

        $table_name = self::get_table_name(CHANNELS_INFO);
        $query = "SELECT channel_id FROM $table_name WHERE $val ORDER BY ROWID;";
        return $this->sql_playlist->fetch_single_array($query, COLUMN_CHANNEL_ID);
    }

    /**
     * @param string $type // PARAM_NEW, PARAM_REMOVED, PARAM_ALL - total
     * @param string $channel_id
     * @return int
     */
    public function get_changed_channels_count($type = PARAM_ALL, $channel_id = null)
    {
        $val = "changed = $type";
        if ($type == PARAM_CHANGED) {
            $val = "NOT $val";
        }

        $table_name = self::get_table_name(CHANNELS_INFO);
        $cond = is_null($channel_id) ? "" : ("AND channel_id = " . Sql_Wrapper::sql_quote($channel_id));
        $query = "SELECT COUNT(*) FROM $table_name WHERE $val $cond;";

        return $this->sql_playlist->query_value($query);
    }

    /**
     * @return void
     */
    public function clear_changed_channels()
    {
        $channels_info_table = self::get_table_name(CHANNELS_INFO);
        $groups_info_table = self::get_table_name(self::GROUPS_INFO_TABLE);
        $q_changed = Sql_Wrapper::sql_quote(TV_CHANGED_CHANNELS_GROUP_ID);
        $query = "DELETE FROM $channels_info_table WHERE changed = -1;";
        $query .= "UPDATE $channels_info_table SET changed = 0 WHERE changed = 1;";
        $query .= "UPDATE $groups_info_table SET disabled = 1 WHERE group_id = $q_changed;";
        $this->sql_playlist->exec_transaction($query);
    }

    ///////////////////////////////////////////////////////////////////////
    /// groups
    /**
     * returns groups
     *
     * @param int $type PARAM_GROUP_ORDINARY - regular groups, PARAM_GROUP_SPECIAL - special groups, PARAM_ALL - all groups
     * @param int $disabled PARAM_DISABLED - disabled, PARAM_ENABLED - enabled, PARAM_ALL - all groups
     * @return array
     */
    public function get_groups($type, $disabled)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $where = ($disabled === -1) ? "" : "WHERE disabled = $disabled";
        $and = empty($where) ? "WHERE" : "AND";
        $where = $type === -1 ? "" : "$where $and special = $type";
        $query = "SELECT * FROM $groups_info_table $where ORDER by ROWID;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * returns group with selected id
     * @param int $type PARAM_GROUP_ORDINARY - only regular groups, PARAM_GROUP_SPECIAL - special groups, PARAM_ALL - all groups
     *
     * @param string $group_id
     * @return array
     */
    public function get_group($group_id, $type = PARAM_GROUP_ORDINARY)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $q_group_id = Sql_Wrapper::sql_quote($group_id);
        $and = $type === -1 ? "" : "AND special = $type";
        $query = "SELECT * FROM $groups_info_table WHERE group_id = $q_group_id AND disabled = 0 $and ORDER by ROWID;";
        return $this->sql_playlist->query_value($query, true);
    }

    /**
     * Returns how many enabled groups
     *
     * @param int $type PARAM_GROUP_ORDINARY - regular groups, PARAM_GROUP_SPECIAL - special groups, PARAM_ALL - all groups
     * @param int $disabled PARAM_ENABLED - enabled groups, PARAM_DISABLED - disabled groups, PARAM_ALL - all groups
     * @return int
     */
    public function get_groups_count($type, $disabled)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $where = ($disabled === -1) ? "" : "WHERE disabled = $disabled";
        $and = empty($where) ? "WHERE" : "AND";
        $where = $type === -1 ? "" : "$where $and special = $type";
        $query = "SELECT COUNT(*) FROM $groups_info_table $where ORDER by ROWID;";
        return $this->sql_playlist->query_value($query);
    }

    /**
     * Get visibility for group
     *
     * @param string $group_id
     * @param int $type PARAM_GROUP_ORDINARY - regular groups, PARAM_GROUP_SPECIAL - special groups
     * @return bool true if group is visible and false otherwise
     */
    public function get_group_visible($group_id, $type)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $q_group = Sql_Wrapper::sql_quote($group_id);
        $query = "SELECT disabled FROM $groups_info_table WHERE special = $type AND group_id = $q_group;";
        $value = $this->sql_playlist->query_value($query);
        return empty($value);
    }

    /**
     * Set visibility for group or groups array
     *
     * @param string|array $group_ids
     * @param bool $show
     * @param bool $special
     * @return void
     */
    public function set_groups_visible($group_ids, $show, $special = false)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $groups_order_table = self::get_table_name(GROUPS_ORDER);
        $channels_info_table = self::get_table_name(CHANNELS_INFO);
        $disabled = (int)!$show;

        $where = Sql_Wrapper::sql_make_where_clause($group_ids, 'group_id');

        if (is_array($group_ids)) {
            $to_alter = $group_ids;
        } else {
            $to_alter[] = $group_ids;
        }

        if ($special) {
            $groups_info_table = self::get_table_name(GROUPS_INFO);
            $query = "UPDATE $groups_info_table SET disabled = $disabled $where AND special = 1;";
        } else {
            $query = "UPDATE $groups_info_table SET disabled = $disabled $where AND special = 0;";
            foreach ($to_alter as $group_id) {
                $q_group_id = Sql_Wrapper::sql_quote($group_id);
                $table_name = self::get_table_name($group_id);

                if ($disabled) {
                    $query .= "DELETE FROM $groups_order_table WHERE group_id = $q_group_id;";
                    $query .= "DROP TABLE IF EXISTS $table_name;";
                    $query .= "UPDATE $channels_info_table SET disabled = 1 WHERE group_id = $q_group_id;";
                } else {
                    $query .= sprintf(self::CREATE_ORDERED_TABLE, $table_name, COLUMN_CHANNEL_ID);
                    $query .= "INSERT OR IGNORE INTO $groups_order_table (group_id) VALUES ($q_group_id);";
                    $query .= "INSERT OR IGNORE INTO $table_name (channel_id )
                            SELECT channel_id FROM $channels_info_table WHERE group_id = $q_group_id AND disabled = 0;";
                    $query .= "UPDATE $channels_info_table SET disabled = 0 WHERE group_id = $q_group_id;";
                }
            }
        }

        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * Toggle visibility for special group
     *
     * @param string $group_ids
     * @param int $type PARAM_GROUP_ORDINARY - regular groups, PARAM_GROUP_SPECIAL - special groups
     */
    public function toggle_groups_visible($group_ids, $type = PARAM_GROUP_ORDINARY)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        if ($type === PARAM_GROUP_ORDINARY) {
            $show = $this->get_group_visible($group_ids, $type);
            $this->set_groups_visible($group_ids, !$show);
        } else {
            $where = Sql_Wrapper::sql_make_where_clause($group_ids, 'group_id');
            $query = "UPDATE $groups_info_table SET disabled = CASE WHEN disabled = 0 THEN 1 ELSE 0 END $where AND special = $type;";
            $this->sql_playlist->exec($query);
        }
    }

    /**
     * @param $group_id
     * @return string|false
     */
    public function get_group_icon($group_id)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $group_id = Sql_Wrapper::sql_quote($group_id);
        return $this->sql_playlist->query_value("SELECT icon FROM $groups_info_table WHERE group_id = $group_id;");
    }

    /**
     * @param string $group_id
     * @param string $icon
     * @return void
     */
    public function set_group_icon($group_id, $icon)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $group_id = Sql_Wrapper::sql_quote($group_id);
        $q_icon = Sql_Wrapper::sql_quote($icon);
        $old_cached_image = $this->get_group_icon($group_id);
        hd_debug_print("Assign icon: $icon to group: $group_id");
        $this->sql_playlist->exec("UPDATE $groups_info_table SET icon = $q_icon WHERE group_id = $group_id;");

        if (!empty($old_cached_image)
            && strpos($old_cached_image, 'plugin_file://') !== false
            && $this->sql_playlist->query_value("SELECT COUNT(*) FROM $groups_info_table WHERE icon = $q_icon;") == 0) {
            $old_cached_image_path = get_cached_image_path($old_cached_image);
            if (file_exists($old_cached_image_path)) {
                unlink($old_cached_image_path);
            }
        }
    }

    /**
     * @return array
     */
    public function get_groups_order()
    {
        $groups_order_table = self::get_table_name(GROUPS_ORDER);
        return $this->sql_playlist->fetch_single_array("SELECT group_id FROM $groups_order_table;", COLUMN_GROUP_ID);
    }

    /**
     * @return array
     */
    public function get_groups_by_order()
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $groups_order_table = self::get_table_name(GROUPS_ORDER);
        $query = "SELECT grp.group_id, grp.title, grp.icon, grp.adult
                    FROM $groups_info_table AS grp
                    INNER JOIN $groups_order_table as ord USING(group_id) ORDER BY ord.ROWID;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param bool $reset
     * @return void
     */
    public function sort_groups_order($reset = false)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        $groups_order_table = self::get_table_name(GROUPS_ORDER);
        $groups_order_table_s = self::get_table_name(GROUPS_ORDER, true);
        $tmp_table = self::get_table_name(GROUPS_ORDER) . "_tmp";
        $query = sprintf(self::CREATE_ORDERED_TABLE, $tmp_table, COLUMN_GROUP_ID);

        if ($reset) {
            $iptv_groups = M3uParser::GROUPS_TABLE;
            $query .= "INSERT INTO $tmp_table (group_id)
                        SELECT group_id FROM $iptv_groups
                        WHERE group_id IN (SELECT group_id FROM $groups_info_table WHERE disabled == 0)
                        ORDER BY ROWID;";
        } else {
            $query .= "INSERT INTO $tmp_table (group_id)
                       SELECT group_id FROM $groups_info_table WHERE disabled = 0 AND special = 0 ORDER BY group_id;";
        }
        $query .= "DROP TABLE $groups_order_table;";
        $query .= "ALTER TABLE $tmp_table RENAME TO $groups_order_table_s;";

        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * Arrange channels in group
     *
     * @param string $group_id
     * @param string $channel_id
     * @param int $direction
     * @return bool
     */
    public function arrange_channels_order_rows($group_id, $channel_id, $direction)
    {
        return $this->arrange_rows($group_id, COLUMN_CHANNEL_ID, $channel_id, $direction);
    }

    /**
     * Arrange groups
     *
     * @param string $group_id
     * @param int $direction
     * @return bool
     */
    public function arrange_groups_order_rows($group_id, $direction)
    {
        return $this->arrange_rows(GROUPS_ORDER, COLUMN_GROUP_ID, $group_id, $direction);
    }

    /**
     * Returns orders for selected group
     *
     * @param string $group_id
     * @return array
     */
    public function get_channels_order($group_id)
    {
        $table_name = self::get_table_name($group_id);
        return $this->sql_playlist->fetch_single_array("SELECT channel_id FROM $table_name ORDER BY ROWID;", COLUMN_CHANNEL_ID);
    }

    /**
     * return is channel in group order
     * @param string $group_id
     * @return int
     */
    public function is_channel_in_order($group_id, $channel_id)
    {
        $table_name = self::get_table_name($group_id);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $query = "SELECT channel_id FROM $table_name WHERE channel_id = $q_channel_id;";
        return (int)$this->sql_playlist->query_value($query);
    }

    /**
     * @param string $group_id
     * @return void
     */
    public function remove_channels_order($group_id)
    {
        $table_name = self::get_table_name($group_id);
        $query = "DROP TABLE IF EXISTS $table_name;";
        $query .= sprintf(self::CREATE_ORDERED_TABLE, $table_name, COLUMN_CHANNEL_ID);
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param string $group_id
     * @param string $channel_id
     * @param bool $remove
     * @return bool
     */
    public function change_channels_order($group_id, $channel_id, $remove)
    {
        $table_name = self::get_table_name($group_id);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        if ($remove) {
            $qry = "DELETE FROM $table_name WHERE channel_id = $q_channel_id;";
        } else {
            $qry = "INSERT INTO $table_name (channel_id) VALUES ($q_channel_id);";
        }
        return $this->sql_playlist->exec($qry);
    }

    /**
     * @param string $group_id
     * @param bool $reset
     * @return void
     */
    public function sort_channels_order($group_id, $reset = false)
    {
        $iptv_channels = M3uParser::CHANNELS_TABLE;
        $channels_info_table = self::get_table_name(CHANNELS_INFO);
        $group_table = self::get_table_name($group_id);
        $tmp_table = $group_table . "_tmp";
        $alter_table_name = self::get_table_name($group_id, true);
        $q_group_id = Sql_Wrapper::sql_quote($group_id);

        $query = sprintf(self::CREATE_ORDERED_TABLE, $tmp_table, COLUMN_CHANNEL_ID);

        if ($reset) {
            $column = $this->get_id_column();
            $query .= "INSERT INTO $tmp_table (channel_id)
                        SELECT $column FROM $iptv_channels
                        WHERE group_id == $q_group_id AND $column IN
                        (SELECT channel_id FROM $channels_info_table WHERE disabled == 0);";
        } else {
            $query .= "INSERT INTO $tmp_table (channel_id)
                        SELECT channel_id FROM $channels_info_table
                        WHERE group_id == $q_group_id AND channel_id IN (SELECT channel_id FROM $group_table)
                        ORDER BY title;";
        }
        $query .= "DROP TABLE $group_table;";
        $query .= "ALTER TABLE $tmp_table RENAME TO $alter_table_name;";

        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param string $group_id
     * @return int
     */
    public function get_channels_order_count($group_id)
    {
        $table_name = self::get_table_name($group_id);
        return $this->sql_playlist->query_value("SELECT COUNT(*) FROM $table_name;");
    }

    /**
     * @param string|null $group_id
     * @param int $disabled_channels -1 - all, 0 - only enabled, 1 - only disabled
     * @param bool $full true - full information, false only channel_id, title and statuses
     * @return array
     */
    public function get_channels($group_id, $disabled_channels, $full = false)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        if (is_null($group_id) || $group_id === TV_ALL_CHANNELS_GROUP_ID) {
            $where = "WHERE ch.group_id IN (SELECT group_id FROM $groups_info_table WHERE special = 0 AND disabled = 0)";
        } else {
            $where = "WHERE ch.group_id = " . Sql_Wrapper::sql_quote($group_id);
        }

        if ($disabled_channels !== -1) {
            $where = empty($where) ? "WHERE disabled = $disabled_channels" : "$where AND disabled = $disabled_channels";
        }

        $iptv_channels = M3uParser::CHANNELS_TABLE;
        $table_name = self::get_table_name(CHANNELS_INFO);
        if ($full) {
            $column = $this->get_id_column();
            $query = "SELECT ch.channel_id, pl.* FROM $iptv_channels AS pl JOIN $table_name AS ch ON pl.$column = ch.channel_id $where;";
        } else {
            $query = "SELECT * FROM $table_name AS ch $where;";
        }
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param string|null $group_id
     * @param int $disabled_channels -1 - all, 0 - only enabled, 1 - only disabled
     * @return array
     */
    public function get_channels_ids($group_id, $disabled_channels)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        if (is_null($group_id) || $group_id === TV_ALL_CHANNELS_GROUP_ID) {
            $where = "WHERE group_id IN (SELECT group_id FROM $groups_info_table WHERE special = 0 AND disabled = 0)";
        } else {
            $where = "WHERE group_id = " . Sql_Wrapper::sql_quote($group_id);
        }

        if ($disabled_channels !== -1) {
            $where = empty($where) ? "WHERE disabled = $disabled_channels" : "$where AND disabled = $disabled_channels";
        }

        $table_name = self::get_table_name(CHANNELS_INFO);
        $query = "SELECT channel_id FROM $table_name $where;";
        return $this->sql_playlist->fetch_single_array($query, COLUMN_CHANNEL_ID);
    }

    /**
     * @param string|null $group_id
     * @param int $disabled_channels -1 - all, 0 - only enabled, 1 - only disabled
     * @param int $disabled_groups -1 - all, 0 - only enabled, 1 - only disabled
     * @return int
     */
    public function get_channels_count($group_id, $disabled_channels, $disabled_groups = 0)
    {
        $groups_info_table = self::get_table_name(GROUPS_INFO);
        if (is_null($group_id) || $group_id === TV_ALL_CHANNELS_GROUP_ID) {
            $and = ($disabled_groups !== -1) ? "AND disabled = $disabled_groups" : "";
            $where = "WHERE group_id IN (SELECT group_id FROM $groups_info_table WHERE special = 0 $and)";
        } else {
            $where = "WHERE group_id = " . Sql_Wrapper::sql_quote($group_id);
        }

        if ($disabled_channels !== -1) {
            $where = empty($where) ? "WHERE disabled = $disabled_channels" : "$where AND disabled = $disabled_channels";
        }

        $table_name = self::get_table_name(CHANNELS_INFO);
        $query = "SELECT count(channel_id) FROM $table_name $where;";
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @return int
     */
    public function get_playlist_entries_count()
    {
        $query = "SELECT name FROM " . M3uParser::IPTV_DB . ".sqlite_master WHERE type = 'table' AND name = '" . M3uParser::S_CHANNELS_TABLE . "';";
        if ($this->sql_playlist->query_value($query)) {
            $iptv_channels = M3uParser::CHANNELS_TABLE;
            return $this->sql_playlist->query_value("SELECT COUNT(*) FROM $iptv_channels;");
        }

        return 0;
    }

    /**
     * @return array
     */
    public function get_all_playlists_ids()
    {
        $table_name = self::PLAYLISTS_TABLE;
        return $this->sql_params->fetch_single_array("SELECT playlist_id FROM $table_name ORDER BY ROWID;", 'playlist_id');
    }

    /**
     * @return int
     */
    public function get_all_playlists_count()
    {
        $table_name = self::PLAYLISTS_TABLE;
        return $this->sql_params->query_value("SELECT COUNT(*) FROM $table_name;");
    }

    /**
     * @param string $id
     * @return integer
     */
    public function is_playlist_exist($id)
    {
        $table_name = self::PLAYLISTS_TABLE;
        $q_key = Sql_Wrapper::sql_quote($id);
        $query = "SELECT COUNT(*) FROM $table_name WHERE playlist_id = $q_key LIMIT 1;";
        return $this->sql_params->query_value($query);
    }

    /**
     * @return array|null
     */
    public function get_playlists_shortcuts()
    {
        $table_name = self::PLAYLISTS_TABLE;
        return $this->sql_params->fetch_array("SELECT playlist_id, shortcut FROM $table_name WHERE shortcut != '' ORDER BY shortcut;");
    }

    /**
     * @param string $id
     * @return string
     */
    public function get_playlist_shortcut($id)
    {
        $table_name = self::PLAYLISTS_TABLE;
        return $this->sql_params->query_value("SELECT shortcut FROM $table_name WHERE playlist_id = '$id';");
    }

    /**
     * @param string $id
     * @param string $shortcut
     * @return bool
     */
    public function set_playlist_shortcut($id, $shortcut)
    {
        $table_name = self::PLAYLISTS_TABLE;
        $q_shortcut = Sql_Wrapper::sql_quote($shortcut);
        return $this->sql_params->exec("UPDATE $table_name SET shortcut = $q_shortcut WHERE playlist_id = '$id';");
    }

    /**
     * @return string|null
     */
    public function get_channel_zoom($channel_id)
    {
        $table_name = self::get_table_name(CHANNELS_INFO);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $query = "SELECT zoom FROM $table_name WHERE channel_id = $q_channel_id";
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @param string $channel_id
     * @param string|null $preset
     * @return void
     */
    public function set_channel_zoom($channel_id, $preset)
    {
        $table_name = self::get_table_name(CHANNELS_INFO);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $q_preset = Sql_Wrapper::sql_quote($preset === null ? "x" : $preset);
        $query = "UPDATE $table_name SET zoom = $q_preset WHERE channel_id = $q_channel_id;";
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @return array
     */
    public function get_channels_zoom($group_id)
    {
        $table_name = self::get_table_name(CHANNELS_INFO);
        $order_table = self::get_table_name($group_id);
        $query = "SELECT ch.channel_id, ch.zoom FROM $table_name AS ch
                    JOIN $order_table AS ord ON ch.channel_id = ord.channel_id;";
        $result = array();
        foreach ($this->sql_playlist->fetch_array($query) as $value) {
            $result[$value[COLUMN_CHANNEL_ID]] = $value['zoom'];
        }
        return $result;
    }

    /**
    /**
     * @param string $channel_id
     * @param bool $external
     * @return void
     */
    public function set_channel_ext_player($channel_id, $external)
    {
        $table_name = self::get_table_name(CHANNELS_INFO);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $q_external = Sql_Wrapper::sql_quote($external ? 1 : 0);
        $query = "UPDATE $table_name SET external_player = $q_external WHERE channel_id = $q_channel_id;";
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @return bool
     */
    public function get_channel_ext_player($channel_id)
    {
        $table_name = self::get_table_name(CHANNELS_INFO);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $query = "SELECT external_player FROM $table_name WHERE channel_id = $q_channel_id";
        $value = $this->sql_playlist->query_value($query);
        return !empty($value);
    }

    ////////////////////////////////////////////////////////////////////////////
    /// TV history

    /**
     * @return array
     */
    public function get_tv_history()
    {
        hd_debug_print(null, true);
        $tv_history = self::get_table_name(TV_HISTORY);
        $channels_info = self::get_table_name(CHANNELS_INFO);
        $iptv_channels = M3uParser::CHANNELS_TABLE;
        $ch_id_column = $this->get_id_column();

        $query = "SELECT * FROM $tv_history as tv
                    INNER JOIN $channels_info as ord ON tv.channel_id = ord.channel_id
                    INNER JOIN $iptv_channels as iptv ON iptv.$ch_id_column = tv.channel_id
                    WHERE ord.disabled = 0 ORDER BY tv.time_stamp DESC;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @return int
     */
    public function get_tv_history_count()
    {
        $table_name = self::get_table_name(TV_HISTORY);
        return $this->sql_playlist->query_value("SELECT COUNT(*) FROM $table_name;");
    }

    /**
     * @param string|null $id
     */
    public function update_tv_history($id)
    {
        if ($this->current_playback_channel_id === null && $id === null)
            return;

        // update point for selected channel
        $id = ($id !== null) ? $id : $this->current_playback_channel_id;

        if (isset($this->playback_points[$id])) {
            $player_state = get_player_state_assoc();
            $state = safe_get_value($player_state, PLAYBACK_STATE);
            if ($state === PLAYBACK_PLAYING || $state === PLAYBACK_STOPPED) {

                // if channel does support archive do not update current point
                $this->playback_points[$id] += ($this->playback_points[$id] !== 0) ? safe_get_value($player_state, PLAYBACK_POSITION, 0) : 0;
                hd_debug_print("channel_id $id at time mark: {$this->playback_points[$id]}", true);
            }
        }
    }

    /**
     * @param string $channel_id
     * @param integer $archive_ts
     */
    public function push_tv_history($channel_id, $archive_ts)
    {
        $player_state = get_player_state_assoc();
        if (isset($player_state[PLAYER_STATE]) && $player_state[PLAYER_STATE] !== PLAYER_STATE_NAVIGATOR) {
            if (!isset($player_state[LAST_PLAYBACK_EVENT]) || ($player_state[LAST_PLAYBACK_EVENT] !== PLAYBACK_PCR_DISCONTINUITY)) {
                $list = array(COLUMN_CHANNEL_ID => $channel_id, COLUMN_TIMESTAMP => $archive_ts);
                $table_name = self::get_table_name(TV_HISTORY);
                $this->current_playback_channel_id = $channel_id;

                $q_id = Sql_Wrapper::sql_quote($channel_id);
                $insert = Sql_Wrapper::sql_make_insert_list($list);
                $query = "INSERT OR IGNORE INTO $table_name $insert;";
                $query .= "UPDATE $table_name SET time_stamp = $archive_ts WHERE channel_id = $q_id;";
                $query .= "DELETE FROM $table_name WHERE ROWID NOT IN (SELECT rowid FROM $table_name ORDER BY time_stamp DESC LIMIT 7);";
                $this->sql_playlist->exec_transaction($query);

            }
        }
    }

    /**
     * @param string $id
     */
    public function erase_tv_history($id)
    {
        hd_debug_print("erase $id");
        $table_name = self::get_table_name(TV_HISTORY);
        $q_id = Sql_Wrapper::sql_quote($id);
        $this->sql_playlist->exec("DELETE FROM $table_name WHERE channel_id = $q_id;");
    }

    /**
     * @return void
     */
    public function clear_tv_history()
    {
        $table_name = self::get_table_name(TV_HISTORY);
        $query = "DROP TABLE IF EXISTS $table_name;";
        $query .= sprintf(self::CREATE_TV_HISTORY_TABLE, $table_name);
        $this->sql_playlist->exec_transaction($query);
    }

    ////////////////////////////////////////////////////////////////////////////
    /// VOD history

    /**
     * Get VOD history for selected playlist sorted by movie_id and last viewed time_stamp (most recent first)
     *
     * @return array
     */
    public function get_all_vod_history()
    {
        $table_name = self::get_table_name(VOD_HISTORY);
        return $this->sql_playlist->fetch_array("SELECT *, MAX(time_stamp) FROM $table_name GROUP BY movie_id ORDER BY time_stamp DESC;");
    }

    /**
     * Get count of VOD history for selected playlist
     *
     * @return int
     */
    public function get_all_vod_history_count()
    {
        $table_name = self::get_table_name(VOD_HISTORY);
        return $this->sql_playlist->query_value("SELECT COUNT(DISTINCT movie_id) FROM $table_name;");
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @return array
     */
    public function get_vod_history($movie_id)
    {
        $table_name = self::get_table_name(VOD_HISTORY);
        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
        return $this->sql_playlist->fetch_array("SELECT * FROM $table_name WHERE movie_id = $q_movie_id;");
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @param string $series_id
     * @param array $values
     * @return void
     */
    public function set_vod_history($movie_id, $series_id, $values)
    {
        $table_name = self::get_table_name(VOD_HISTORY);
        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series_id = Sql_Wrapper::sql_quote($series_id);
        $q_params = Sql_Wrapper::sql_make_list_from_keys($values);
        $q_values = Sql_Wrapper::sql_make_list_from_values($values);
        $query = "INSERT OR REPLACE INTO $table_name (movie_id, series_id, $q_params)
                    VALUES ($q_movie_id, $q_series_id, $q_values);";
        $this->sql_playlist->exec($query);
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @return int
     */
    public function get_vod_history_count($movie_id)
    {
        $table_name = self::get_table_name(VOD_HISTORY);
        $q_id = Sql_Wrapper::sql_quote($movie_id);
        return $this->sql_playlist->query_value("SELECT COUNT(*) FROM $table_name WHERE movie_id = $q_id;");
    }

    /**
     * Get param for movie_id and series_id
     *
     * @param string $movie_id
     * @param string $series_id
     * @param string $param_name
     * @return array
     */
    public function get_vod_history_params($movie_id, $series_id, $param_name = null)
    {
        $table_name = self::get_table_name(VOD_HISTORY);
        $q_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series = Sql_Wrapper::sql_quote($series_id);
        if ($param_name === null) {
            $query = "SELECT * FROM $table_name WHERE movie_id = $q_id AND series_id = $q_series;";
        } else {
            $q_param = Sql_Wrapper::sql_quote($param_name);
            $query = "SELECT $q_param FROM $table_name WHERE movie_id = $q_id AND series_id = $q_series;";
        }
        return $this->sql_playlist->query_value($query, $param_name === null);
    }

    /**
     * Remove history by movie_id
     *
     * @param string $movie_id
     */
    public function remove_vod_history($movie_id)
    {
        $table_name = self::get_table_name(VOD_HISTORY);
        $q_value = Sql_Wrapper::sql_quote($movie_id);
        $this->sql_playlist->exec("DELETE FROM $table_name WHERE movie_id = $q_value;");
    }

    /**
     * Remove history by movie_id and series_id
     *
     * @param $movie_id
     * @param $series_id
     */
    public function remove_vod_history_part($movie_id, $series_id)
    {
        $table_name = self::get_table_name(VOD_HISTORY);
        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series_id = Sql_Wrapper::sql_quote($series_id);
        $this->sql_playlist->exec("DELETE FROM $table_name WHERE movie_id = $q_movie_id AND series_id = $q_series_id;");
    }

    /**
     * Clear all history
     */
    public function clear_all_vod_history()
    {
        $table_name = self::get_table_name(VOD_HISTORY);
        $query = "DROP TABLE IF EXISTS $table_name;";
        $query .= sprintf(self::CREATE_VOD_HISTORY_TABLE, $table_name);
        $this->sql_playlist->exec_transaction($query);

    }

    /**
     * @return int
     */
    public function get_playlist_group_count()
    {
        $query = "SELECT name FROM " . M3uParser::IPTV_DB . ".sqlite_master WHERE type = 'table' AND name = '" . M3uParser::S_GROUPS_TABLE . "';";
        if ($this->sql_playlist->query_value($query)) {
            $iptv_groups = M3uParser::GROUPS_TABLE;
            return $this->sql_playlist->query_value("SELECT COUNT(*) FROM $iptv_groups;");
        }

        return 0;
    }

    /**
     * @return array
     */
    public function get_channels_by_order($group_id)
    {
        $order_table = self::get_table_name($group_id);
        $iptv_channels = M3uParser::CHANNELS_TABLE;
        $channels_info_table = self::get_table_name(CHANNELS_INFO);
        $column = $this->get_id_column();
        $query = "SELECT ord.channel_id, pl.*
                    FROM $iptv_channels AS pl
                    JOIN $order_table AS ord ON pl.$column = ord.channel_id
                    JOIN $channels_info_table as ch ON ch.channel_id = ord.channel_id AND ch.disabled = 0
                    ORDER BY ord.ROWID;";
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @return int
     */
    public function get_channels_by_order_cnt($group_id)
    {
        $order_table = self::get_table_name($group_id);
        $iptv_channels = M3uParser::CHANNELS_TABLE;
        $channels_info_table = self::get_table_name(CHANNELS_INFO);
        $column = $this->get_id_column();
        $query = "SELECT COUNT(ord.channel_id)
                    FROM $iptv_channels AS pl
                    JOIN $order_table AS ord ON pl.$column = ord.channel_id
                    JOIN $channels_info_table as ch ON ch.channel_id = ord.channel_id AND ch.disabled = 0
                    ORDER BY ord.ROWID;";
        return $this->sql_playlist->query_value($query);
    }

    /**
     * enable/disable channel(s)
     *
     * @param string|array $channel_id
     * @param bool $show
     */
    public function set_channel_visible($channel_id, $show)
    {
        if (empty($channel_id)) {
            return;
        }

        $disable = (int)!$show;
        $table_name = self::get_table_name(CHANNELS_INFO);
        $distinct = is_array($channel_id) ? 'DISTINCT' : '';
        $where = Sql_Wrapper::sql_make_where_clause($channel_id, 'channel_id');
        $groups_select = "SELECT $distinct group_id FROM $table_name $where;";

        $query = '';
        foreach ($this->sql_playlist->fetch_array($groups_select) as $group) {
            $q_table = self::get_table_name($group[COLUMN_GROUP_ID]);
            if ($show) {
                $q_group = Sql_Wrapper::sql_quote($group[COLUMN_GROUP_ID]);
                $query .= "INSERT OR IGNORE INTO $q_table (channel_id) SELECT channel_id FROM $table_name $where AND group_id = $q_group ORDER BY ROWID;";
            } else {
                $query .= "DELETE FROM $q_table $where;";
            }
        }
        $query .= "UPDATE $table_name SET disabled = $disable $where;";

        $this->sql_playlist->exec($query);
    }

    /**
     * @param string $channel_id
     * @param bool $full
     * @return array
     */
    public function get_channel_info($channel_id, $full = false)
    {
        $channel_id = Sql_Wrapper::sql_quote($channel_id);
        $table_name = self::get_table_name(CHANNELS_INFO);
        if ($full) {
            $iptv_channels = M3uParser::CHANNELS_TABLE;
            $column = $this->get_id_column();
            $query = "SELECT ch.channel_id, tv.*
                        FROM $iptv_channels as tv
                            JOIN $table_name AS ch ON tv.$column = ch.channel_id
                        WHERE ch.channel_id = $channel_id AND ch.disabled = 0;";
        } else {
            $query = "SELECT * FROM $table_name WHERE channel_id = $channel_id AND disabled = 0;";
        }

        return $this->sql_playlist->query_value($query, true);
    }

    ////////////////////////////////////////////////////////
    /// database methods

    /**
     * Return 0 if no database attached
     * Return 1 if database attached (filename to check not set)
     * Return 2 if database attached and filename is match
     * Return 3 if database attached and filename not match
     *
     * @param string $db_name
     * @param string $db_filename Full path to database file
     * @return int
     */
    public function is_database_attached($db_name, $db_filename = null)
    {
        if ($this->sql_playlist) {
            foreach ($this->sql_playlist->fetch_array("PRAGMA database_list") as $database) {
                if ($database['name'] !== $db_name) continue;

                hd_debug_print("Database exist: '{$database['name']}' - '{$database['file']}'");
                if ($db_filename == null) {
                    return 1;
                }

                if ($db_filename == ':memory:' && empty($database['file'])) {
                    return 2;
                }

                $used_db_file = basename($database['file']);
                $checked_db_file = basename($db_filename);
                if ($used_db_file === $checked_db_file) {
                    hd_debug_print("Database already attached as '{$database['name']}' '$used_db_file'");
                    return 2;
                }

                return 3;
            }
            hd_debug_print("Not exist: $db_name, with filename: $db_filename");
        } else {
            hd_debug_print("No sql wrapper");
        }
        return 0;
    }

    /**
     * Returns 0 - if attach failed
     * Returns 1 - if attach success
     * Returns 2 - if database already attached
     *
     * @param $db_filename
     * @param $name
     * @return int
     */
    public function attachDatabase($db_filename, $name)
    {
        $result = $this->is_database_attached($name, $db_filename);
        if ($result === 2) {
            return $result;
        }

        if ($result !== 0) {
            $this->sql_playlist->exec("DETACH DATABASE '$name';");
        }

        $this->sql_playlist->exec("ATTACH DATABASE '$db_filename' AS $name;");
        $result = $this->is_database_attached($name, $db_filename);
        return ($result === 1 || $result === 2) ? 1 : 0;
    }

    public function detachDatabase($name)
    {
        if ($this->is_database_attached($name) !== 0) {
            $this->sql_playlist->exec("DETACH DATABASE '$name';");
        }
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $item
     * @param int $direction
     * @return bool
     */
    protected function arrange_rows($table, $column, $item, $direction)
    {
        if ($table === self::PLAYLISTS_TABLE) {
            $script = self::CREATE_PLAYLISTS_TABLE;
            $table_name = $table;
            $table_name_short = $table;
            $sql_wrapper = $this->sql_params;
        } else {
            $script = self::CREATE_ORDERED_TABLE;
            $table_name = self::get_table_name($table);
            $table_name_short = self::get_table_name($table, true);
            $sql_wrapper = $this->sql_playlist;
        }

        $q_item = Sql_Wrapper::sql_quote($item);
        $cur = '';
        $new = '';
        if ($direction === Ordered_Array::UP || $direction === Ordered_Array::DOWN) {
            $sub_query = "SELECT ROWID AS cur FROM $table_name WHERE $column = $q_item";
            if ($direction === Ordered_Array::UP) {
                $query = "SELECT * FROM ((SELECT MAX(ROWID) AS new FROM $table_name WHERE ROWID < ($sub_query)) INNER JOIN ($sub_query));";
            } else {
                $query = "SELECT * FROM ((SELECT MIN(ROWID) AS new FROM $table_name WHERE ROWID > ($sub_query)) INNER JOIN ($sub_query));";
            }
            $positions = $sql_wrapper->query_value($query, true);
            if (empty($positions) || $positions['cur'] === null || $positions['new'] === null) {
                return false;
            }

            $cur = $positions['cur'];
            $new = $positions['new'];
            $query = "UPDATE $table_name SET ROWID = -$cur WHERE ROWID = $cur;
                      UPDATE $table_name SET ROWID =  $cur WHERE ROWID = $new;
                      UPDATE $table_name SET ROWID =  $new WHERE ROWID = -$cur;";
            return $sql_wrapper->exec_transaction($query);
        }

        if ($direction === Ordered_Array::TOP || $direction === Ordered_Array::BOTTOM) {
            if ($direction == Ordered_Array::TOP) {
                $query = "SELECT ROWID AS cur
                            FROM $table_name
                            WHERE $column = $q_item AND ROWID > (SELECT MIN(ROWID) FROM $table_name) LIMIT 1;";
                $cur = $sql_wrapper->query_value($query);
                if (empty($cur)) {
                    return false;
                }

                $new = -$cur;
            }

            if ($direction === Ordered_Array::BOTTOM) {
                $query_pos = "SELECT * FROM (
                                (SELECT ROWID AS cur FROM $table_name
                                    WHERE $column = $q_item AND ROWID < (SELECT MAX(ROWID) FROM $table_name))
                                INNER JOIN (SELECT ROWID AS new FROM $table_name ORDER BY ROWID DESC LIMIT 1));";
                $positions = $sql_wrapper->query_value($query_pos, true);
                if (empty($positions) || $positions['cur'] === null || $positions['new'] === null) {
                    return false;
                }

                $cur = $positions['cur'];
                $new = $positions['new'] + 1;
            }

            $tmp_table =  $table_name . "_tmp";
            $query = sprintf($script, $tmp_table, $column);
            $query .= "UPDATE $table_name SET ROWID = $new WHERE ROWID = $cur;";
            $query .= "INSERT INTO $tmp_table SELECT * FROM $table_name ORDER BY ROWID;";
            $query .= "DROP TABLE $table_name;";
            $query .= "ALTER TABLE $tmp_table RENAME TO $table_name_short;";
            return $sql_wrapper->exec_transaction($query);
        }

        return false;
    }

    /**
     * @return void
     */
    public function reset_playlist_db()
    {
        hd_debug_print(null, true);
        $this->sql_playlist = null;
    }

    public function get_id_column()
    {
        return safe_get_value(M3uParser::$id_to_column_mapper, $this->channel_id_map, PARAM_HASH);
    }

    /**
     * Returns full table name
     *
     * @param string $id
     * @param bool $only_table true - does not include database name into full table name
     * @return string
     */
    public static function get_table_name($id, $only_table = false)
    {
        $db = '';
        switch ($id) {
            case XMLTV_SOURCE_EXTERNAL:
            case XMLTV_SOURCE_PLAYLIST:
                $table_name = self::XMLTV_TABLE;
                break;

            case VOD_FAV_GROUP_ID:
                $table_name = self::FAV_VOD_ORDERS_TABLE;
                break;

            case TV_FAV_GROUP_ID:
                $db = self::PLAYLIST_ORDERS_DB;
                $table_name = self::FAV_TV_ORDERS_TABLE;
                break;

            case TV_HISTORY:
                $db = self::TV_HISTORY_DB;
                $table_name = self::TV_HISTORY_TABLE;
                break;

            case VOD_HISTORY:
                $db = self::VOD_HISTORY_DB;
                $table_name = self::VOD_HISTORY_TABLE;
                break;

            case VOD_FILTER_LIST:
                $table_name = self::VOD_FILTERS_TABLE;
                break;

            case VOD_SEARCH_LIST:
                $table_name = self::VOD_SEARCHES_TABLE;
                break;

            case GROUPS_ORDER:
                $db = self::PLAYLIST_ORDERS_DB;
                $table_name = self::GROUPS_ORDER_TABLE;
                break;

            case GROUPS_INFO:
                $db = self::PLAYLIST_ORDERS_DB;
                $table_name = self::GROUPS_INFO_TABLE;
                break;

            case CHANNELS_INFO:
                $db = self::PLAYLIST_ORDERS_DB;
                $table_name = self::CHANNELS_INFO_TABLE;
                break;

            default:
                $db = self::PLAYLIST_ORDERS_DB;
                $table_name = "orders_" . Hashed_Array::hash($id);
                break;
        }

        if (!$only_table && !empty($db)) {
            $db .= ".";
        }

        return $only_table ? $table_name : ($db . $table_name);
    }

    /**
     * Returns table name for playlist
     *
     * @param string $playlist_id
     * @return string
     */
    public static function get_playlist_parameters_table_name($playlist_id)
    {
        return str_replace('.', '_', "parameters_$playlist_id");
    }
}