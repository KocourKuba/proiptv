<?php
require_once 'sql_wrapper.php';

class Dune_Default_Sqlite_Engine
{
    const PLAYLIST_ORDERS_DB = 'playlist_orders';
    const TV_HISTORY_DB = 'tv_history';
    const VOD_HISTORY_DB = 'vod_history';

    const PLAYLISTS_TABLE = 'playlists';
    const PARAMETERS_TABLE = 'parameters';
    const PLAYLIST_PARAMETERS_TABLE = 'playlist_parameters';

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
    const PLAYLIST_XMLTV_TABLE = 'playlist_xmltv_sources';
    const SELECTED_XMLTV_TABLE = 'selected_xmltv';
    const VOD_LIST_TABLE = 'vod_list_orders';
    const SELECTED_JSON_TABLE = 'selected_json_sources';

    const SETTINGS_TABLE = 'settings';
    const COOKIES_TABLE = 'cookies';

    const CREATE_PLUGIN_PARAMETERS_TABLE = "CREATE TABLE IF NOT EXISTS %s (name TEXT PRIMARY KEY, value TEXT);";
    const CREATE_PLAYLISTS_TABLE = "CREATE TABLE IF NOT EXISTS %s (playlist_id TEXT PRIMARY KEY NOT NULL, shortcut TEXT DEFAULT '', last_update INTEGER DEFAULT 0);";
    const CREATE_PLAYLIST_PARAMETERS_TABLE = "CREATE TABLE IF NOT EXISTS %s (playlist_id TEXT NOT NULL, name TEXT NOT NULL, value TEXT, UNIQUE(playlist_id, name));";

    // orders_xxxx, GROUPS_ORDER_TABLE, VOD_SEARCHES_TABLE, VOD_FILTERS_TABLE, FAV_MOVIE_GROUP_ID
    const CREATE_ORDERED_TABLE = "CREATE TABLE IF NOT EXISTS %s (%s TEXT PRIMARY KEY NOT NULL);";

    const CREATE_GROUPS_INFO_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                        (group_id TEXT PRIMARY KEY NOT NULL,
                                         title TEXT DEFAULT '',
                                         icon TEXT DEFAULT '',
                                         adult INTEGER DEFAULT 0,
                                         disabled INTEGER DEFAULT 0,
                                         special INTEGER DEFAULT 0);";

    const CREATE_CHANNELS_INFO_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                        (channel_id TEXT PRIMARY KEY NOT NULL,
                                         title TEXT DEFAULT '',
                                         show_title TEXT DEFAULT '',
                                         group_id TEXT DEFAULT '',
                                         disabled INTEGER DEFAULT 0,
                                         adult INTEGER DEFAULT 0,
                                         changed INTEGER DEFAULT 1,
                                         zoom TEXT,
                                         epg_shift INTEGER DEFAULT 0,
                                         external_player INTEGER DEFAULT 0);";

    const CREATE_PLAYLIST_SETTINGS_TABLE = "CREATE TABLE IF NOT EXISTS %s (name TEXT PRIMARY KEY NOT NULL, value TEXT DEFAULT '', type TEXT DEFAULT '');";
    const CREATE_COMMON_XMLTV_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                    (hash TEXT PRIMARY KEY NOT NULL, type TEXT, name TEXT NOT NULL, uri TEXT NOT NULL, cache TEXT DEFAULT 'auto');";
    const CREATE_PLAYLIST_XMLTV_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                    (playlist_id TEXT NOT NULL, hash TEXT NOT NULL, type TEXT, name TEXT NOT NULL,
                                     uri TEXT NOT NULL, cache TEXT DEFAULT 'auto', UNIQUE(playlist_id, hash));";
    const CREATE_SELECTED_XMTLV_TABLE = "CREATE TABLE IF NOT EXISTS %s (playlist_id TEXT NOT NULL, hash TEXT NOT NULL, UNIQUE(playlist_id, hash));";
    const CREATE_SELECTED_JSON_TABLE = "CREATE TABLE IF NOT EXISTS %s (name TEXT NOT NULL, enabled INTEGER DEFAULT 1, UNIQUE(name));";
    const CREATE_COOKIES_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                    (param TEXT PRIMARY KEY NOT NULL, value TEXT DEFAULT '', time_stamp INTEGER DEFAULT 0);";

    const CREATE_TV_HISTORY_TABLE = "CREATE TABLE IF NOT EXISTS %s
                                        (channel_id TEXT PRIMARY KEY NOT NULL, time_stamp INTEGER DEFAULT 0,
                                        time_start INTEGER DEFAULT 0, time_end INTEGER DEFAULT 0);";
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
     * @var array
     */
    protected $playback_points = array();

    /**
     * @var object
     */
    protected $plugin_cookies;

    public function get_sql_playlist()
    {
        return $this->sql_playlist;
    }

    public function get_plugin_cookies()
    {
        return $this->plugin_cookies;
    }

    public function set_plugin_cookies(&$plugin_cookies)
    {
        $this->plugin_cookies = $plugin_cookies;
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

        $this->sql_params = new Sql_Wrapper(get_data_path('common.db'));
        if (!$this->sql_params->is_valid()) {
            return;
        }

        $query = sprintf(self::CREATE_PLUGIN_PARAMETERS_TABLE, self::PARAMETERS_TABLE);
        $query .= sprintf(self::CREATE_PLAYLISTS_TABLE, self::PLAYLISTS_TABLE);
        $query .= sprintf(self::CREATE_PLAYLIST_PARAMETERS_TABLE, self::PLAYLIST_PARAMETERS_TABLE);
        $query .= sprintf(self::CREATE_COMMON_XMLTV_TABLE, self::XMLTV_TABLE);
        $query .= sprintf(self::CREATE_PLAYLIST_XMLTV_TABLE, self::PLAYLIST_XMLTV_TABLE);
        $query .= sprintf(self::CREATE_SELECTED_XMTLV_TABLE, self::SELECTED_XMLTV_TABLE);
        $this->sql_params->exec_transaction($query);

        if (!$this->sql_params->is_column_exists(self::PLAYLISTS_TABLE, COLUMN_LAST_UPDATE)) {
            $query = sprintf('ALTER TABLE %s ADD COLUMN %s INTEGER DEFAULT 0;', self::PLAYLISTS_TABLE, COLUMN_LAST_UPDATE);
            $this->sql_params->exec($query);
        }

        // transfer old 6.x playlist parameters to new table
        $playlist_parameters = self::PLAYLIST_PARAMETERS_TABLE;
        $query = '';
        foreach ($this->get_all_playlists_ids() as $playlist_id) {
            $old_parameters_table = str_replace('.', '_', "parameters_$playlist_id");
            if ($this->is_params_table_exists($old_parameters_table)) {
                $query .= sprintf('INSERT INTO %s (%s,%s,%s) SELECT %s,%s,%s FROM %s;',
                    $playlist_parameters, COLUMN_PLAYLIST_ID, COLUMN_NAME, COLUMN_VALUE,
                    $playlist_id, COLUMN_NAME, COLUMN_VALUE, $old_parameters_table);
                $query .= sprintf('DROP TABLE IF EXISTS %s;', $old_parameters_table);
            }
        }
        $query .= sprintf('UPDATE %s SET %s=%s WHERE %s=%s;', $playlist_parameters,
            COLUMN_NAME, Sql_Wrapper::sql_quote(PARAM_CUSTOM_PLAYLIST_IPTV), COLUMN_NAME, Sql_Wrapper::sql_quote('{CUSTOM_PLAYLIST}'));
        $this->sql_params->exec_transaction($query);

        $query = '';
        foreach ($this->sql_params->get_master_table_list() as $table) {
            if (strpos($table, 'parameters_') === 0) {
                $query .= sprintf('DROP TABLE IF EXISTS %s;', $table);
            }
        }
        $this->sql_params->exec_transaction($query);

        // remove unused parameters
        $query = sprintf("DELETE FROM %s WHERE %s;",
            self::PARAMETERS_TABLE, Sql_Wrapper::sql_make_where_clause(array(PARAM_COOKIE_ENABLE_DEBUG, 'xmltv_source_names'), COLUMN_NAME));
        $this->sql_params->exec($query);

        $parameters = HD::get_data_items('common.settings', true, false);
        if (!empty($parameters)) {
            hd_debug_print("Move 'common.settings' to common.db");
            $removed_parameters = array(
                'config_version', 'cur_xmltv_source', 'cur_xmltv_key', 'fuzzy_search_epg', 'force_http', 'xmltv_source_names',
                PARAM_COOKIE_ENABLE_DEBUG, PARAM_BUFFERING_TIME, PARAM_NEWUI_ICONS_IN_ROW, PARAM_NEWUI_CHANNEL_POSITION,
                PARAM_EPG_CACHE_ENGINE, PARAM_PER_CHANNELS_ZOOM, PARAM_SHOW_FAVORITES, PARAM_SHOW_HISTORY, PARAM_SHOW_ALL,
                PARAM_SHOW_CHANGED_CHANNELS, PARAM_FAKE_EPG, PARAM_SHOW_VOD, TV_ALL_CHANNELS_GROUP_ID,
            );

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

                        if (($stg->type === PARAM_FILE || $stg->type === PARAM_LINK) && !isset($stg->params[PARAM_PLAYLIST_TYPE])) {
                            $stg->params[PARAM_PLAYLIST_TYPE] = CONTROL_PLAYLIST_IPTV;
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
                        $values = safe_merge_array($values, $stg->params);
                        $this->set_playlist_parameters($playlist_id, $values);
                    }
                    unset($parameters[$key]);
                } else if ($key === PARAM_EXT_XMLTV_SOURCES) {
                    foreach ($param as $hash => $stg) {
                        if (empty($stg->params[PARAM_URI]) || !is_proto_http($stg->params[PARAM_URI])) continue;

                        $item = array(
                            PARAM_HASH => $hash,
                            PARAM_TYPE => PARAM_LINK,
                            PARAM_NAME => $stg->name,
                            PARAM_URI => $stg->params[PARAM_URI],
                            PARAM_CACHE => safe_get_value($stg->params, PARAM_CACHE, XMLTV_CACHE_AUTO)
                        );
                        $this->set_xmltv_source(null, $item);
                    }
                    unset($parameters[$key]);
                } else {
                    $type = gettype($param);
                    if ($type === 'NULL') {
                        $param = '';
                    } else if ($type === 'boolean') {
                        $param = SwitchOnOff::to_def($param);
                    }
                    $query .= sprintf('INSERT OR IGNORE INTO %s (%s,%s) VALUES (%s,%s);',
                        self::PARAMETERS_TABLE, COLUMN_NAME, COLUMN_VALUE, Sql_Wrapper::sql_quote($key), Sql_Wrapper::sql_quote($param));
                    unset($parameters[$key]);
                }
            }
            $this->sql_params->exec_transaction($query);
            if (empty($parameters)) {
                safe_unlink(get_data_path('common.settings'));
            }
            foreach ($parameters as $key => $value) {
                hd_debug_print("!!!!! Parameter $key is not imported: " . $value);
            }
        }

        $get_query = function(&$plugin_cookies, $name, $default) {
            $query = '';
            if (isset($plugin_cookies->{$name})) {
                $value = safe_get_value($plugin_cookies, $name, $default);
                if (!empty($value)) {
                    unset($plugin_cookies->{$name});
                    $query = sprintf('INSERT OR IGNORE INTO %s (%s,%s) VALUES (%s,%s);',
                        Dune_Default_Sqlite_Engine::PARAMETERS_TABLE, COLUMN_NAME, COLUMN_VALUE,
                        Sql_Wrapper::sql_quote($name), Sql_Wrapper::sql_quote($value));
                }
            }
            return $query;
        };

        if (isset($this->plugin_cookies->toggle_move)) {
            unset($this->plugin_cookies->toggle_move);
        }

        // move parameters from plugin_cookies to db
        $query = $get_query($this->plugin_cookies, PARAM_SHOW_TV, SwitchOnOff::on);
        $query .= $get_query($this->plugin_cookies, PARAM_AUTO_PLAY, SwitchOnOff::off);
        $query .= $get_query($this->plugin_cookies, PARAM_AUTO_RESUME, SwitchOnOff::off);
        $query .= $get_query($this->plugin_cookies, PARAM_PLAYLIST_FIRST, SwitchOnOff::off);
        $query .= $get_query($this->plugin_cookies, PARAM_LAST_TV_SEARCH, '');
        $query .= $get_query($this->plugin_cookies, PARAM_LAST_PLAYLIST, '');

        if (!empty($query)) {
            $this->sql_params->exec_transaction($query);
        }

        // cleanup xmltv table from wrong values
        $query = sprintf("DELETE FROM %s WHERE %s ISNULL OR %s='' OR %s ISNULL OR %s='' OR %s ISNULL OR %s='';",
            self::XMLTV_TABLE, COLUMN_HASH, COLUMN_HASH, COLUMN_TYPE, COLUMN_TYPE, COLUMN_URI, COLUMN_URI);
        $this->sql_params->exec($query);

        // check default parameters

        // 30s - 300s
        $param = $this->get_parameter(PARAM_CURL_CONNECT_TIMEOUT, 30);
        if ($param < 30 || $param > 300) {
            $this->set_parameter(PARAM_CURL_CONNECT_TIMEOUT, 30);
        }

        // 30s - 300s
        $param = $this->get_parameter(PARAM_CURL_DOWNLOAD_TIMEOUT, 120);
        if ($param < 30 || $param > 300) {
            $this->set_parameter(PARAM_CURL_DOWNLOAD_TIMEOUT, 120);
        }

        // 1h - 24h
        $param = $this->get_parameter(PARAM_CURL_FILE_CACHE_TIME, 1);
        if ($param < 1 || $param > 24) {
            $this->set_parameter(PARAM_CURL_FILE_CACHE_TIME, 1);
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

        $query = sprintf('INSERT OR REPLACE INTO %s (%s,%s) VALUES (%s,%s);',
            self::PARAMETERS_TABLE, COLUMN_NAME, COLUMN_VALUE, Sql_Wrapper::sql_quote($name), Sql_Wrapper::sql_quote($value));
        $this->sql_params->exec($query);
    }

    /**
     * Get global plugin parameters
     * Parameters does not depend on playlists and used globally
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get_parameter($name, $default = '')
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s;',
            COLUMN_VALUE, self::PARAMETERS_TABLE, COLUMN_NAME, Sql_Wrapper::sql_quote($name));
        $value = $this->sql_params->query_value($query);
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
        $query = sprintf('DELETE FROM %s WHERE %s=%s;',
            self::PARAMETERS_TABLE, COLUMN_NAME, Sql_Wrapper::sql_quote($name));
        $this->sql_params->exec($query);
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

    public function remove_playlist($playlist_id)
    {
        $tables = array(self::PLAYLISTS_TABLE, self::PLAYLIST_PARAMETERS_TABLE, self::PLAYLIST_XMLTV_TABLE, self::SELECTED_XMLTV_TABLE);
        $query = '';
        foreach ($tables as $table) {
            $query .= sprintf('DELETE FROM %s WHERE %s=%s;', $table, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id));
        }
        $this->sql_params->exec($query);
    }

    /**
     * @param string $playlist_id
     * @param array $stg
     * @return void
     */
    public function set_playlist_parameters($playlist_id, $stg)
    {
        hd_debug_print(null, true);
        hd_debug_print("Setting playlist $playlist_id to " . json_format_unescaped($stg), true);

        // update playlist table
        $query = sprintf('INSERT OR IGNORE INTO %s (%s) VALUES (%s);',
            self::PLAYLISTS_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id));
        $this->sql_params->exec($query);

        // add params array to stg array as pair
        if (isset($stg[PARAM_PARAMS])) {
            foreach ($stg[PARAM_PARAMS] as $k => $v) {
                $stg[$k] = $v;
            }
            unset($stg[PARAM_PARAMS]);
        }

        // save parameters
        foreach ($stg as $name => $value) {
            $this->set_playlist_parameter($playlist_id, $name, $value);
        }
    }

    /**
     * Get all parameters associated with playlist id
     *
     * @param string $playlist_id
     * @return array
     */
    public function get_playlist_parameters($playlist_id)
    {
        if (empty($playlist_id)) {
            return array();
        }

        $query = sprintf('SELECT * FROM %s WHERE %s=%s;',
            self::PLAYLIST_PARAMETERS_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id));
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
        hd_debug_print("Playlist ID: $playlist_id, Name: $name, Value: $value", true);
        // save parameter

        $q_name = Sql_Wrapper::sql_quote($name);
        $q_value = Sql_Wrapper::sql_quote($value);
        $q_playlist_id = Sql_Wrapper::sql_quote($playlist_id);
        $query = sprintf('INSERT OR IGNORE INTO %s (%s,%s,%s) VALUES (%s,%s,%s);', self::PLAYLIST_PARAMETERS_TABLE,
            COLUMN_PLAYLIST_ID, COLUMN_NAME, COLUMN_VALUE, $q_playlist_id, $q_name, $q_value);
        $query .= sprintf('UPDATE %s SET %s=%s WHERE %s=%s AND %s=%s;', self::PLAYLIST_PARAMETERS_TABLE,
            COLUMN_VALUE, $q_value, COLUMN_PLAYLIST_ID, $q_playlist_id, COLUMN_NAME, $q_name);
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

        $query = sprintf('SELECT %s FROM %s WHERE %s=%s AND %s=%s;', COLUMN_VALUE, self::PLAYLIST_PARAMETERS_TABLE,
            COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id), COLUMN_NAME, Sql_Wrapper::sql_quote($name));
        $value = $this->sql_params->query_value($query);
        return is_null($value) ? $default : $value;
    }

    /**
     * @param string $playlist_id
     * @param string $name
     * @return void
     */
    public function remove_playlist_parameter($playlist_id, $name)
    {
        hd_debug_print(null, true);
        $query = sprintf('DELETE FROM %s WHERE %s=%s AND %s=%s;', self::PLAYLIST_PARAMETERS_TABLE,
            COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id), COLUMN_NAME, Sql_Wrapper::sql_quote($name));
        $this->sql_params->exec($query);
    }

    ///////////////////////////////////////////////////////////////////////
    // XMLTV table
    //

    /**
     * get xmltv sources
     *
     * @param string $type
     * @param string|null $playlist_id
     * @return Hashed_Array<string, array>
     */
    public function get_xmltv_sources($type, $playlist_id)
    {
        $sources = new Hashed_Array();
        if (($type & XMLTV_SOURCE_PLAYLIST) && $playlist_id !== null) {
            $query = sprintf('SELECT * FROM %s WHERE %s=%s;',
                self::PLAYLIST_XMLTV_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id));
            $rows = $this->sql_params->fetch_array($query);
            foreach ($rows as $row) {
                $sources->set($row[PARAM_HASH], $row);
            }
        }

        if ($type & XMLTV_SOURCE_EXTERNAL) {
            $query = sprintf('SELECT * FROM %s;', self::XMLTV_TABLE);
            $rows = $this->sql_params->fetch_array($query);
            foreach ($rows as $row) {
                $sources->set($row[PARAM_HASH], $row);
            }
        }

        return $sources;
    }

    /**
     * get xmltv sources
     *
     * @return Hashed_Array<string, array>
     */
    public function get_external_xmltv_sources()
    {
        $sources = new Hashed_Array();
        $query = sprintf('SELECT * FROM %s;', self::XMLTV_TABLE);
        $rows = $this->sql_params->fetch_array($query);
        foreach ($rows as $row) {
            $sources->set($row[PARAM_HASH], $row);
        }

        return $sources;
    }

    /**
     * @param string $playlist_id
     * @param string $hash
     */
    public function add_selected_xmltv_id($playlist_id, $hash)
    {
        hd_debug_print(null, true);
        hd_debug_print("Add to selected: $hash", true);

        $query = sprintf("INSERT OR IGNORE INTO %s (%s, %s) VALUES (%s, %s);", self::SELECTED_XMLTV_TABLE,
            COLUMN_PLAYLIST_ID, COLUMN_HASH, Sql_Wrapper::sql_quote($playlist_id), Sql_Wrapper::sql_quote($hash));
        $this->sql_params->exec($query);
    }

    /**
     * @param string $playlist_id
     * @param string $hash
     */
    public function remove_selected_xmltv_id($playlist_id, $hash)
    {
        hd_debug_print(null, true);
        hd_debug_print("Removed from selected: $hash", true);

        $query = sprintf("DELETE FROM %s WHERE %s=%s AND %s=%s;",
            self::SELECTED_XMLTV_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id),
            COLUMN_HASH, Sql_Wrapper::sql_quote($hash));
        $this->sql_params->exec($query);
    }

    /**
     * @param string $playlist_id
     * @return array
     */
    public function get_selected_xmltv_ids($playlist_id)
    {
        if (!$this->is_playlist_table_exists(M3uParser::S_CHANNELS_TABLE)) {
            return array();
        }

        $query = sprintf("SELECT %s FROM %s WHERE %s=%s ORDER BY ROWID;",
        COLUMN_HASH, self::SELECTED_XMLTV_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id));
        return $this->sql_params->fetch_array($query, PARAM_HASH);
    }

    /**
     * @param string $playlist_id
     * @param string $hash
     * @return bool
     */
    public function is_selected_xmltv_id($playlist_id, $hash)
    {
        if (!$this->is_playlist_table_exists(M3uParser::S_CHANNELS_TABLE)) {
            return false;
        }

        $query = sprintf("SELECT count(*) FROM %s WHERE %s=%s AND %s=%s;", self::SELECTED_XMLTV_TABLE,
            COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id), COLUMN_HASH, Sql_Wrapper::sql_quote($hash));
        return (bool)$this->sql_params->query_value($query);
    }

    /**
     * @param string $playlist_id
     * @param array|string $values
     */
    public function set_selected_xmltv_ids($playlist_id, $values)
    {
        hd_debug_print(null, true);

        if (!is_array($values)) {
            $values = array($values);
        }
        hd_debug_print('Set selected: ' . json_format_unescaped($values), true);

        $query = '';
        foreach ($values as $hash) {
            $query .= sprintf("INSERT INTO %s (%s,%s) VALUES (%s,%s);", self::SELECTED_XMLTV_TABLE,
                COLUMN_PLAYLIST_ID, COLUMN_HASH, Sql_Wrapper::sql_quote($playlist_id), Sql_Wrapper::sql_quote($hash));
        }

        $this->sql_params->exec_transaction($query);
    }

    /**
     * get xmltv sources hashes
     *
     * @param string $type
     * @param string|null $playlist_id
     * @return array
     */
    public function get_xmltv_sources_hash($type, $playlist_id)
    {
        $query = '';
        if (($type & XMLTV_SOURCE_PLAYLIST) && $playlist_id !== null) {
            $query .= sprintf('SELECT %s FROM %s WHERE %s=%s',
                COLUMN_HASH, self::PLAYLIST_XMLTV_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id));
        }

        if ($type & XMLTV_SOURCE_EXTERNAL) {
            if (!empty($query)) {
                $query .= ' UNION ';
            }
            $query .= sprintf('SELECT %s FROM %s;', COLUMN_HASH, self::XMLTV_TABLE);
        }
        return $this->sql_params->fetch_array($query, COLUMN_HASH);
    }

    /**
     * get external xmltv sources count
     *
     * @param string|null $playlist_id
     * @return int
     */
    public function get_xmltv_sources_count($playlist_id)
    {
        hd_debug_print(null, true);

        if ($playlist_id === null) {
            $query = sprintf('SELECT COUNT(*) FROM %s;', self::XMLTV_TABLE);
        } else {
            $query = sprintf('SELECT COUNT(*) FROM %s WHERE %s=%s;',
                self::PLAYLIST_XMLTV_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id));
        }

        return (int)$this->sql_params->query_value($query);
    }

    /**
     * get xmltv source
     *
     * @param string|null $playlist_id
     * @param string $hash
     * @return array|null
     */
    public function get_xmltv_source($playlist_id, $hash)
    {
        if ($playlist_id === null) {
            $query = sprintf("SELECT * FROM %s WHERE %s=%s AND %s<>'';",
                self::XMLTV_TABLE, COLUMN_HASH, Sql_Wrapper::sql_quote($hash), COLUMN_TYPE);
        } else {
            $query = sprintf("SELECT * FROM %s WHERE %s=%s AND %s=%s AND NOT %s='';", self::PLAYLIST_XMLTV_TABLE,
                COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($playlist_id), COLUMN_HASH, Sql_Wrapper::sql_quote($hash), COLUMN_TYPE);
        }

        return $this->sql_params->query_value($query, true);
    }

    /**
     * @param string $hash
     * @return array|null
     */
    public function find_xmltv_source($hash)
    {
        hd_debug_print(null, true);

        $q_columns = Sql_Wrapper::sql_make_list_from_values(array('hash', 'type', 'name', 'uri', 'cache'), false);
        $query = sprintf('SELECT * FROM (SELECT %s FROM %s UNION SELECT %s FROM %s) WHERE %s=%s;',
            $q_columns, self::XMLTV_TABLE, $q_columns, self::PLAYLIST_XMLTV_TABLE, COLUMN_HASH, Sql_Wrapper::sql_quote($hash));

        return $this->sql_params->query_value($query, true);
    }

    /**
     * update xmltv source
     *
     * @param string $playlist_id
     * @param array $value
     * @return void
     * @noinspection Annotator
     */
    public function set_xmltv_source($playlist_id, $value)
    {
        hd_debug_print(null, true);

        if ($playlist_id === null) {
            $query = sprintf('INSERT OR IGNORE INTO %s %s;', self::XMLTV_TABLE, Sql_Wrapper::sql_make_insert_list($value));
        } else {
            $value[COLUMN_PLAYLIST_ID] = $playlist_id;
            $query = sprintf('INSERT OR IGNORE INTO %s %s;', self::PLAYLIST_XMLTV_TABLE, Sql_Wrapper::sql_make_insert_list($value));
        }

        $this->sql_params->exec($query);
    }

    /**
     * update xmltv source
     *
     * @param string $playlist_id
     * @param array $value
     * @return void
     * @noinspection Annotator
     */
    public function update_xmltv_source($playlist_id, $value)
    {
        hd_debug_print(null, true);

        $query = sprintf('UPDATE %s SET %s WHERE %s=%s;', ($playlist_id === null ? self::XMLTV_TABLE : self::PLAYLIST_XMLTV_TABLE),
            Sql_Wrapper::sql_make_set_list($value), COLUMN_HASH, Sql_Wrapper::sql_quote($value[COLUMN_HASH]));
        $this->sql_params->exec($query);
    }

    /**
     * Bulk set xmltv sources
     * @param string $playlist_id
     * @param Hashed_Array<string, array> $values
     * @noinspection Annotator
     */
    public function set_playlist_xmltv_sources($playlist_id, $values)
    {
        hd_debug_print(null, true);

        $query = '';
        foreach ($values as $params) {
            $type = safe_get_value($params, PARAM_TYPE);
            $uri = safe_get_value($params, PARAM_URI);
            if (empty($type) || empty($uri)) continue;

            $params[COLUMN_PLAYLIST_ID] = $playlist_id;
            $query .= sprintf('INSERT OR REPLACE INTO %s %s;', self::PLAYLIST_XMLTV_TABLE, Sql_Wrapper::sql_make_insert_list($params));
        }
        $this->sql_params->exec_transaction($query);
    }

    /**
     * remove xmltv sources
     *
     * @param string|array $hash
     * @param string|null $playlist_id
     * @return void
     */
    public function remove_xmltv_source($hash, $playlist_id = null)
    {
        hd_debug_print(null, true);

        $query = sprintf('DELETE FROM %s WHERE %s;', ($playlist_id === null ? self::XMLTV_TABLE : self::PLAYLIST_XMLTV_TABLE),
            Sql_Wrapper::sql_make_where_clause($hash, COLUMN_HASH));
        $this->sql_params->exec($query);
    }

    /**
     * get selected json sources
     *
     * @param bool $only_enabled
     * @return array
     */
    public function get_selected_json_sources($only_enabled)
    {
        hd_debug_print(null, true);

        if ($only_enabled) {
            $query = sprintf('SELECT * FROM %s WHERE %s=%d ORDER by ROWID;', self::SELECTED_JSON_TABLE, COLUMN_ENABLED, TRUE);
            return $this->sql_playlist->fetch_array($query, COLUMN_NAME);
        }

        $query = sprintf('SELECT * FROM %s ORDER by ROWID;', self::SELECTED_JSON_TABLE);
        $result = $this->sql_playlist->fetch_array($query);

        $json_sources = array();
        foreach ($result as $item) {
            $json_sources[$item[COLUMN_NAME]] = $item[COLUMN_ENABLED];
        }
        return $json_sources;
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
            if (!$db->is_valid()) {
                return;
            }

            $query = sprintf(self::CREATE_PLAYLIST_SETTINGS_TABLE, self::SETTINGS_TABLE);
            foreach ($data as $key => $value) {
                $type = gettype($value);
                if ($type === 'NULL') {
                    $type = 'string';
                    $value = '';
                }

                $query .= sprintf('INSERT OR IGNORE INTO %s (%s,%s,%s) VALUES (%s,%s,%s);',
                    self::SETTINGS_TABLE, COLUMN_NAME, COLUMN_VALUE, COLUMN_TYPE,
                    Sql_Wrapper::sql_quote($key), Sql_Wrapper::sql_quote($value), Sql_Wrapper::sql_quote($type));
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
        $type = gettype($default);
        if ($this->sql_playlist !== null) {
            $query = sprintf('SELECT %s,%s FROM %s WHERE %s=%s;',
                COLUMN_VALUE, COLUMN_TYPE, self::SETTINGS_TABLE, COLUMN_NAME, Sql_Wrapper::sql_quote($name));
            $row = $this->sql_playlist->query_value($query, true);
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

        $type = gettype($value);
        hd_debug_print("Set setting: $name => $value ($type)", true);
        if ($this->sql_playlist) {
            $query = sprintf('INSERT OR REPLACE INTO %s (%s,%s,%s) VALUES (%s,%s,%s);',
                self::SETTINGS_TABLE, COLUMN_NAME, COLUMN_VALUE, COLUMN_TYPE,
                Sql_Wrapper::sql_quote($name), Sql_Wrapper::sql_quote($value), Sql_Wrapper::sql_quote($type));
            $this->sql_playlist->exec($query);
        } else {
            hd_debug_print('Playlist db not set. Setting not saved', true);
        }
    }

    /**
     * Remove setting
     *
     * @param string $name
     */
    public function remove_setting($name)
    {
        $query = sprintf('DELETE FROM %s WHERE %s=%s;', self::SETTINGS_TABLE, COLUMN_NAME, Sql_Wrapper::sql_quote($name));
        $this->sql_playlist->exec($query);
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
            $query = sprintf('SELECT %s FROM %s WHERE %s=%s AND %s > %s;',
                COLUMN_VALUE, self::COOKIES_TABLE, COLUMN_PARAM, Sql_Wrapper::sql_quote($name), COLUMN_TIMESTAMP, time());
        } else {
            $query = sprintf('SELECT %s FROM %s WHERE %s=%s;',
                COLUMN_VALUE, self::COOKIES_TABLE, COLUMN_PARAM, Sql_Wrapper::sql_quote($name));
        }
        return $this->sql_playlist->query_value($query);
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

        $query = sprintf('INSERT OR REPLACE INTO %s (%s,%s,%s) VALUES (%s,%s,%s);', self::COOKIES_TABLE,
            COLUMN_PARAM, COLUMN_VALUE, COLUMN_TIMESTAMP,
            Sql_Wrapper::sql_quote($name), Sql_Wrapper::sql_quote($value), Sql_Wrapper::sql_quote($expired));
        $this->sql_playlist->exec($query);
    }

    /**
     * Get cookie
     *
     * @param string $name
     */
    public function remove_cookie($name)
    {
        if ($this->sql_playlist) {
            $query = sprintf('DELETE FROM %s WHERE %s=%s;', self::COOKIES_TABLE, COLUMN_PARAM, Sql_Wrapper::sql_quote($name));
            $this->sql_playlist->exec($query);
        }
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
    public function get_table_values($table)
    {
        $query = sprintf('SELECT * FROM %s ORDER BY ROWID;', self::get_table_full_name($table));
        return $this->sql_playlist->fetch_array($query);
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
        $query = sprintf('SELECT ROWID FROM %s WHERE %s=%s;',
            self::get_table_full_name($table), COLUMN_ITEM, Sql_Wrapper::sql_quote($value));
        return $this->sql_playlist->query_value($query);
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
        $query = sprintf('SELECT %s FROM %s WHERE ROWID = %s;', COLUMN_ITEM, self::get_table_full_name($table), Sql_Wrapper::sql_quote($id));
        return $this->sql_playlist->query_value($query);
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
        if ($id === -1) {
            $query = sprintf('INSERT OR IGNORE INTO %s (%s) VALUES (%s);',
                self::get_table_full_name($table), COLUMN_ITEM, Sql_Wrapper::sql_quote($value));
        } else {
            $query = sprintf('UPDATE %s SET %s=%s WHERE ROWID=%s;',
                self::get_table_full_name($table), COLUMN_ITEM, Sql_Wrapper::sql_quote($value), Sql_Wrapper::sql_quote($id));
        }
        $this->sql_playlist->exec($query);
    }

    /**
     * Remove value
     *
     * @param string $table
     * @param string $value
     */
    public function remove_table_value($table, $value)
    {
        $query = sprintf('DELETE FROM %s WHERE %s=%s;', self::get_table_full_name($table), COLUMN_ITEM, Sql_Wrapper::sql_quote($value));
        $this->sql_playlist->exec($query);
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
        $table_name = self::get_table_full_name(CHANNELS_INFO);
        $q_id = Sql_Wrapper::sql_quote($channel_id);
        $query = sprintf('UPDATE %s SET %s=%d WHERE %s=%s AND %s=%d;',
            $table_name, COLUMN_CHANGED, FALSE, COLUMN_CHANNEL_ID, $q_id, COLUMN_CHANGED, TRUE);
        $query .= sprintf('DELETE FROM %s WHERE %s=%s AND %s=%d;',
            $table_name, COLUMN_CHANNEL_ID, $q_id, COLUMN_CHANGED, -1);
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param int $type
     * @return array
     */
    public function get_changed_channels($type)
    {
        if (!$this->is_playlist_table_exists(M3uParser::S_CHANNELS_TABLE)) {
            return array();
        }

        $column = $this->get_id_column();
        $table_name = self::get_table_full_name(CHANNELS_INFO);
        if ($type == PARAM_NEW) {
            $query = sprintf('SELECT ch.ROWID, ch.%s, pl.*
                        FROM %s AS ch
                        JOIN %s AS pl ON pl.%s=ch.%s
                        WHERE %s=%d ORDER BY ch.ROWID;',
                COLUMN_CHANNEL_ID, $table_name, M3uParser::CHANNELS_TABLE, $column, COLUMN_CHANNEL_ID, COLUMN_CHANGED, TRUE);
        } else if ($type === PARAM_REMOVED) {
            $query = sprintf('SELECT ROWID, %s,%s FROM %s WHERE %s=%d ORDER BY ROWID;',
                COLUMN_CHANNEL_ID, COLUMN_TITLE, $table_name, COLUMN_CHANGED, -1);
        } else {
            $query = sprintf('SELECT ch.ROWID, ch.%s, pl.*, ch.%s
                        FROM %s AS ch
                            LEFT JOIN %s AS pl ON pl.%s = ch.%s
                        WHERE %s != 0
                        ORDER BY ch.ROWID;',
                COLUMN_CHANNEL_ID, COLUMN_TITLE, $table_name, M3uParser::CHANNELS_TABLE, $column, COLUMN_CHANNEL_ID, COLUMN_CHANGED);
        }

        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param int $type // PARAM_NEW, PARAM_REMOVED, null or other value - total
     * @return array
     */
    public function get_changed_channels_ids($type)
    {
        $table_name = self::get_table_full_name(CHANNELS_INFO);
        if ($type == PARAM_CHANGED) {
            $query = sprintf('SELECT %s FROM %s WHERE %s<>%s ORDER BY ROWID;',
                COLUMN_CHANNEL_ID, $table_name, COLUMN_CHANGED, $type);
        } else {
            $query = sprintf('SELECT %s FROM %s WHERE %s=%s ORDER BY ROWID;',
                COLUMN_CHANNEL_ID, $table_name, COLUMN_CHANGED, $type);
        }

        return $this->sql_playlist->fetch_array($query, COLUMN_CHANNEL_ID);
    }

    /**
     * @param int $type // PARAM_CHANGED, PARAM_NEW, PARAM_REMOVED - total
     * @param string $channel_id
     * @return int
     */
    public function get_changed_channels_count($type, $channel_id = null)
    {
        $val = sprintf('%s %s=%s', $type == PARAM_CHANGED ? 'NOT' : '', COLUMN_CHANGED, $type);
        $table_name = self::get_table_full_name(CHANNELS_INFO);
        if (is_null($channel_id)){
            $query = sprintf('SELECT COUNT(*) FROM %s WHERE %s;', $table_name, $val);
        } else {
            $query = sprintf('SELECT COUNT(*) FROM %s WHERE %s AND %s=%s;',
                $table_name, $val, COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        }

        return (int)$this->sql_playlist->query_value($query);
    }

    /**
     * @return void
     */
    public function clear_changed_channels()
    {
        $channels_info_table = self::get_table_full_name(CHANNELS_INFO);
        $tmp_table = $channels_info_table . '_tmp';

        $query = sprintf('DELETE FROM %s WHERE %s=%d;', $channels_info_table, COLUMN_CHANGED, -1);
        $query .= sprintf('UPDATE %s SET %s=%d WHERE %s=%d;', $channels_info_table, COLUMN_CHANGED, FALSE, COLUMN_CHANGED, TRUE);
        $query .= sprintf('UPDATE %s SET %s=%d WHERE %s=%s;', self::get_table_full_name(self::GROUPS_INFO_TABLE),
            COLUMN_DISABLED, TRUE, COLUMN_GROUP_ID, Sql_Wrapper::sql_quote(TV_CHANGED_CHANNELS_GROUP_ID));
        $this->sql_playlist->exec_transaction($query);

        $query = sprintf(self::CREATE_CHANNELS_INFO_TABLE, $tmp_table);
        $columns = sprintf('ch.%s, ch.%s, ch.%s, ch.%s, ch.%s, ch.%s, ch.%s, ch.%s, ch.%s, ch.%s',
            COLUMN_CHANNEL_ID, COLUMN_TITLE, COLUMN_SHOW_TITLE, COLUMN_GROUP_ID, COLUMN_DISABLED,
            COLUMN_ADULT, COLUMN_CHANGED, COLUMN_ZOOM, COLUMN_EPG_SHIFT, COLUMN_EXTERNAL_PLAYER);
        $query .= sprintf('INSERT INTO %s SELECT %s FROM %s as ch INNER JOIN %s as pl ON ch.%s = pl.%s ORDER BY pl.ROWID;',
            $tmp_table, $columns, $channels_info_table, M3uParser::CHANNELS_TABLE, COLUMN_CHANNEL_ID, $this->get_id_column());

        $query .= sprintf('DROP TABLE %s;', $channels_info_table);
        $query .= sprintf('ALTER TABLE %s RENAME TO %s;', $tmp_table, self::get_table_name(CHANNELS_INFO));
        $this->sql_playlist->exec_transaction($query);
    }

    ///////////////////////////////////////////////////////////////////////
    /// groups
    /**
     * returns groups
     *
     * @param int $type PARAM_GROUP_ORDINARY - regular groups, PARAM_GROUP_SPECIAL - special groups, PARAM_ALL - all groups
     * @param int $disabled PARAM_DISABLED - disabled, PARAM_ENABLED - enabled, PARAM_ALL - all groups
     * @param string|null $column
     * @return array
     */
    public function get_groups($type, $disabled, $column = null)
    {
        if (!$this->sql_playlist) {
            return array();
        }

        hd_debug_print(null, true);
        $where = ($disabled === PARAM_ALL) ? '' : COLUMN_DISABLED . '=' . $disabled;
        $and = empty($where) ? '' : 'AND';
        $where = $type === PARAM_ALL ? '' : sprintf("%s %s %s=%d", $where, $and, COLUMN_SPECIAL, $type);
        $query = sprintf('SELECT * FROM %s WHERE %s ORDER by ROWID;', self::get_table_full_name(GROUPS_INFO), $where);
        $rows = $this->sql_playlist->fetch_array($query);
        if ($column !== null) {
            $rows = extract_column($rows, $column);
        }

        return $rows;
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
        $where = ($disabled === PARAM_ALL) ? '' : COLUMN_DISABLED . '=' . $disabled;
        $and = empty($where) ? '' : 'AND';
        $where = $type === PARAM_ALL ? '' : sprintf('WHERE %s %s %s=%s', $where, $and, COLUMN_SPECIAL, $type);
        $query = sprintf('SELECT COUNT(*) FROM %s %s ORDER by ROWID;', self::get_table_full_name(GROUPS_INFO), $where);
        return (int)$this->sql_playlist->query_value($query);
    }

    /**
     * returns group with selected id
     * @param string $group_id
     * @param int $type PARAM_GROUP_ORDINARY - only regular groups, PARAM_GROUP_SPECIAL - special groups, PARAM_ALL - all groups
     * @param bool $include_adult
     * @return array
     */
    public function get_group($group_id, $type, $include_adult = true)
    {
        if ($include_adult) {
            $where = sprintf('%s<>%d', COLUMN_ADULT, -1);
        } else {
            $where = sprintf('%s=%d', COLUMN_ADULT, FALSE);
        }

        if ($type === PARAM_ALL) {
            $query = sprintf('SELECT * FROM %s WHERE %s=%s AND %s=%d AND %s ORDER by ROWID;', self::get_table_full_name(GROUPS_INFO),
                COLUMN_GROUP_ID, Sql_Wrapper::sql_quote($group_id), COLUMN_DISABLED, FALSE, $where);
        } else {
            $query = sprintf('SELECT * FROM %s WHERE %s=%s AND %s=%d AND %s=%d AND %s ORDER by ROWID;', self::get_table_full_name(GROUPS_INFO),
                COLUMN_GROUP_ID, Sql_Wrapper::sql_quote($group_id), COLUMN_DISABLED, FALSE, COLUMN_SPECIAL, $type, $where);
        }
        return $this->sql_playlist->query_value($query, true);
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
        $groups_order_table = self::get_table_full_name(GROUPS_ORDER);
        $channels_info_table = self::get_table_full_name(CHANNELS_INFO);
        $disabled = (int)!$show;

        $where = Sql_Wrapper::sql_make_where_clause($group_ids, COLUMN_GROUP_ID);

        $query = sprintf('UPDATE %s SET %s=%s WHERE %s AND %s=%s;', self::get_table_full_name(GROUPS_INFO),
            COLUMN_DISABLED, $disabled, $where, COLUMN_SPECIAL, (int)$special);

        if (!$special) {
            if (is_array($group_ids)) {
                $to_alter = $group_ids;
            } else {
                $to_alter[] = $group_ids;
            }

            foreach ($to_alter as $group_id) {
                $q_group_id = Sql_Wrapper::sql_quote($group_id);
                $group_table_name = self::get_table_full_name($group_id);

                if ($disabled) {
                    $query .= sprintf('DELETE FROM %s WHERE %s=%s;', $groups_order_table, COLUMN_GROUP_ID, $q_group_id);
                    $query .= sprintf('DROP TABLE IF EXISTS %s;', $group_table_name);
                    $query .= sprintf('UPDATE %s SET %s=%d WHERE %s=%s;', $channels_info_table,
                        COLUMN_DISABLED, TRUE, COLUMN_GROUP_ID, $q_group_id);
                } else {
                    $query .= sprintf(self::CREATE_ORDERED_TABLE, $group_table_name, COLUMN_CHANNEL_ID);
                    $query .= sprintf('INSERT OR IGNORE INTO %s (%s) VALUES (%s);', $groups_order_table, COLUMN_GROUP_ID, $q_group_id);
                    $query .= sprintf('INSERT OR IGNORE INTO %s (%s) SELECT %s FROM %s WHERE %s=%s;',
                        $group_table_name, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, $channels_info_table, COLUMN_GROUP_ID, $q_group_id);
                    $query .= sprintf('UPDATE %s SET %s=%d WHERE %s=%s;', $channels_info_table,
                        COLUMN_DISABLED, FALSE, COLUMN_GROUP_ID, $q_group_id);
                }
            }
        }

        hd_debug_print($query);
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param string $group_id
     * @param string|null $title
     * @return string|false
     */
    public function set_group_title($group_id, $title)
    {
        $table = self::get_table_full_name(GROUPS_INFO);
        $q_group_id = Sql_Wrapper::sql_quote($group_id);
        if (empty($title)) {
            $query = sprintf('UPDATE %s SET %s=%s.%s WHERE %s=%s;', $table,
                COLUMN_TITLE, $table, COLUMN_GROUP_ID, COLUMN_GROUP_ID, $q_group_id);
        } else {
            $query = sprintf('UPDATE %s SET %s=%s WHERE %s=%s;', $table,
                COLUMN_TITLE, Sql_Wrapper::sql_quote($title), COLUMN_GROUP_ID, $q_group_id);
        }

        hd_debug_print($query);
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @param string $group_id
     * @return string|false
     */
    public function get_group_title($group_id)
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s;',
            COLUMN_TITLE, self::get_table_full_name(GROUPS_INFO), COLUMN_GROUP_ID, Sql_Wrapper::sql_quote($group_id));
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @param $group_id
     * @return string|false
     */
    public function get_group_icon($group_id)
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s;',
            COLUMN_ICON, self::get_table_full_name(GROUPS_INFO), COLUMN_GROUP_ID, Sql_Wrapper::sql_quote($group_id));
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @param string $group_id
     * @param string $icon
     * @return void
     */
    public function set_group_icon($group_id, $icon)
    {
        $q_group_id = Sql_Wrapper::sql_quote($group_id);
        if (empty($icon)) {
            $query = sprintf('SELECT %s FROM %s WHERE %s=%s;',
                COLUMN_ICON, M3uParser::GROUPS_TABLE, COLUMN_GROUP_ID, $q_group_id);
            $icon = $this->sql_playlist->query_value($query);
            if (empty($icon)) {
                $icon = DEFAULT_GROUP_ICON;
            }
        }

        $groups_info_table = self::get_table_full_name(GROUPS_INFO);
        $q_icon = Sql_Wrapper::sql_quote($icon);
        $old_cached_image = $this->get_group_icon($group_id);
        hd_debug_print("Assign icon: $icon to group: $group_id");
        $query = sprintf('UPDATE %s SET %s=%s WHERE %s=%s;',
            $groups_info_table, COLUMN_ICON, $q_icon, COLUMN_GROUP_ID, $q_group_id);
        $this->sql_playlist->exec($query);

        if (!empty($old_cached_image) && strpos($old_cached_image, 'plugin_file://') !== false) {
            $query = sprintf('SELECT COUNT(*) FROM %s WHERE %s=%s;', $groups_info_table, COLUMN_ICON, $q_icon);
            if (!$this->sql_playlist->query_value($query)) {
                $old_cached_image_path = get_cached_image_path($old_cached_image);
                safe_unlink($old_cached_image_path);
            }
        }
    }

    /**
     * @param bool $include_adults
     * @return array
     */
    public function get_groups_by_order($include_adults = true)
    {
        $where = '';
        if (!$include_adults) {
            $where = sprintf('WHERE %s=%d', COLUMN_ADULT, FALSE);
        }

        /** @noinspection Annotator */
        $query = sprintf('SELECT %s, %s, %s FROM %s INNER JOIN %s as ord USING(%s) %s ORDER BY ord.ROWID;',
            COLUMN_GROUP_ID, COLUMN_TITLE, COLUMN_ICON,
            self::get_table_full_name(GROUPS_INFO), self::get_table_full_name(GROUPS_ORDER), COLUMN_GROUP_ID, $where);

        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param bool $include_adults
     * @return array
     */
    public function get_groups_ids_by_order($include_adults = true)
    {
        if ($include_adults) {
            $query = sprintf('SELECT %s FROM %s as ord ORDER BY ord.ROWID;', COLUMN_GROUP_ID, self::get_table_full_name(GROUPS_ORDER));
        } else {
            $query = sprintf('SELECT %s FROM %s as ord INNER JOIN %s USING(%s) WHERE %s=%d ORDER BY ord.ROWID;',
                COLUMN_GROUP_ID, self::get_table_full_name(GROUPS_ORDER), self::get_table_full_name(GROUPS_INFO),
                COLUMN_GROUP_ID, COLUMN_ADULT, FALSE);
        }

        return $this->sql_playlist->fetch_array($query, COLUMN_GROUP_ID);
    }

    /**
     * @param bool $reset
     * @return void
     */
    public function sort_groups_order($reset = false)
    {
        $groups_info_table = self::get_table_full_name(GROUPS_INFO);
        $groups_order_table = self::get_table_full_name(GROUPS_ORDER);
        $tmp_table = $groups_order_table . '_tmp';
        $query = sprintf(self::CREATE_ORDERED_TABLE, $tmp_table, COLUMN_GROUP_ID);

        if ($reset) {
            $query .= sprintf('INSERT INTO %s (%s) SELECT %s FROM %s AS pl WHERE %s IN (SELECT %s FROM %s WHERE %s=%d) ORDER BY pl.ROWID;',
                $tmp_table, COLUMN_GROUP_ID, COLUMN_GROUP_ID, M3uParser::GROUPS_TABLE,
                COLUMN_GROUP_ID, COLUMN_GROUP_ID, $groups_info_table, COLUMN_DISABLED, FALSE);
        } else {
            $query .= sprintf('INSERT INTO %s (%s) SELECT %s FROM %s WHERE %s=%d AND %s=%d ORDER BY %s;',
                $tmp_table, COLUMN_GROUP_ID, COLUMN_GROUP_ID, $groups_info_table, COLUMN_DISABLED, FALSE, COLUMN_SPECIAL, FALSE, COLUMN_TITLE);
        }
        $query .= sprintf('DROP TABLE IF EXISTS %s;', $groups_order_table);
        $query .= sprintf('ALTER TABLE %s RENAME TO %s;', $tmp_table, self::get_table_name(GROUPS_ORDER));

        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param string $channel_id
     * @param string $title
     * @return string|false
     */
    public function set_channel_title($channel_id, $title)
    {
        if (empty($title)) {
            $query = sprintf('UPDATE %s SET %s=%s WHERE %s=%s;', self::get_table_full_name(CHANNELS_INFO),
                COLUMN_SHOW_TITLE, COLUMN_TITLE, COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        } else {
            $query = sprintf('UPDATE %s SET %s=%s WHERE %s=%s;', self::get_table_full_name(CHANNELS_INFO),
                COLUMN_SHOW_TITLE, Sql_Wrapper::sql_quote($title), COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        }
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @param string $channel_id
     * @param bool $original
     * @return string|false
     */
    public function get_channel_title($channel_id, $original)
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s;', $original ? COLUMN_TITLE : COLUMN_SHOW_TITLE,
            self::get_table_full_name(CHANNELS_INFO), COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        return $this->sql_playlist->query_value($query);
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
     * @param string|array $group_id
     * @param int $direction
     * @return bool
     */
    public function arrange_groups_order_rows($group_id, $direction)
    {
        if (is_array($group_id)) {
            $res = false;
            if ($direction === Ordered_Array::DOWN) {
                $group_id = array_reverse($group_id);
            }
            foreach ($group_id as $id) {
                $res |= $this->arrange_rows(GROUPS_ORDER, COLUMN_GROUP_ID, $id, $direction);
            }
            return $res;
        }
        return $this->arrange_rows(GROUPS_ORDER, COLUMN_GROUP_ID, $group_id, $direction);
    }

    /**
     * Arrange groups
     *
     * @param array $groups_ids
     * @return bool
     */
    public function store_groups_order_rows($groups_ids)
    {
        if (!$this->sql_playlist) {
            return false;
        }

        $table_name = self::get_table_full_name(GROUPS_ORDER);
        $tmp_table = $table_name . '_tmp';
        $query = sprintf(self::CREATE_ORDERED_TABLE, $tmp_table, COLUMN_GROUP_ID);
        foreach ($groups_ids as $item) {
            $query .= sprintf('INSERT INTO %s (%s) VALUES (%s);',
                $tmp_table, COLUMN_GROUP_ID, Sql_Wrapper::sql_quote($item));
        }
        $query .= sprintf('DROP TABLE IF EXISTS %s;', $table_name);
        $query .= sprintf('ALTER TABLE %s RENAME TO %s;', $tmp_table, self::get_table_name(GROUPS_ORDER));

        return $this->sql_playlist->exec_transaction($query);
    }

    /**
     * Arrange groups
     *
     * @param array $channel_ids
     * @return bool
     */
    public function store_channels_order_rows($group_id, $channel_ids)
    {
        if (!$this->sql_playlist) {
            return false;
        }

        $table_name = self::get_table_full_name($group_id);
        $tmp_table = $table_name . '_tmp';
        $query = sprintf(self::CREATE_ORDERED_TABLE, $tmp_table, COLUMN_CHANNEL_ID);
        foreach ($channel_ids as $item) {
            $query .= sprintf('INSERT INTO %s (%s) VALUES (%s);',
                $tmp_table, COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($item));
        }
        $query .= sprintf('DROP TABLE IF EXISTS %s;', $table_name);
        $query .= sprintf('ALTER TABLE %s RENAME TO %s;', $tmp_table, self::get_table_name($group_id));

        return $this->sql_playlist->exec_transaction($query);
    }

    /**
     * Returns orders for selected group
     *
     * @param string $group_id
     * @return array
     */
    public function get_channels_order($group_id)
    {
        if (!$this->sql_playlist) {
            return array();
        }

        $query = sprintf('SELECT %s FROM %s ORDER BY ROWID;', COLUMN_CHANNEL_ID, self::get_table_full_name($group_id));
        return $this->sql_playlist->fetch_array($query, COLUMN_CHANNEL_ID);
    }

    /**
     * return is channel in group order
     * @param string $group_id
     * @param string $channel_id
     * @return int
     */
    public function is_channel_in_order($group_id, $channel_id)
    {
        $query = sprintf('SELECT COUNT(*) FROM %s WHERE %s=%s;',
            self::get_table_full_name($group_id), COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        return (int)$this->sql_playlist->query_value($query);
    }

    /**
     * return is channel in group order
     * @param string $channel_id
     * @return bool
     */
    public function is_channel_visible($channel_id)
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s;',
            COLUMN_DISABLED, self::get_table_full_name(CHANNELS_INFO), COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        return $this->sql_playlist->query_value($query) === 0;
    }

    /**
     * @param string $group_id
     * @return void
     */
    public function remove_channels_order($group_id)
    {
        $table_name = self::get_table_full_name($group_id);
        $query = sprintf('DROP TABLE IF EXISTS %s;', $table_name);
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
        $table_name = self::get_table_full_name($group_id);
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        if ($remove) {
            $query = sprintf('DELETE FROM %s WHERE %s=%s;', $table_name, COLUMN_CHANNEL_ID, $q_channel_id);
        } else {
            $query = sprintf('INSERT OR IGNORE INTO %s (%s) VALUES (%s);', $table_name, COLUMN_CHANNEL_ID, $q_channel_id);
        }
        return $this->sql_playlist->exec($query);
    }

    /**
     * @param string $group_id
     * @param array $channel_ids
     * @param bool $remove
     * @return bool
     */
    public function bulk_change_channels_order($group_id, $channel_ids, $remove)
    {
        $table_name = self::get_table_full_name($group_id);
        if ($remove) {
            $query = sprintf('DELETE FROM %s WHERE %s IN (%s);',
                $table_name, COLUMN_CHANNEL_ID, Sql_Wrapper::sql_make_list_from_values($channel_ids));
        } else {
            $query = '';
            foreach ($channel_ids as $channel_id) {
                $query .= sprintf('INSERT OR IGNORE INTO %s (%s) VALUES (%s);',
                    $table_name, COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
            }
        }
        return $this->sql_playlist->exec($query);
    }

    /**
     * @param string $group_id
     * @param bool $reset
     * @return void
     */
    public function sort_channels_order($group_id, $reset = false)
    {
        if (!$this->is_playlist_table_exists(M3uParser::S_CHANNELS_TABLE)) {
            return;
        }

        $channels_info_table = self::get_table_full_name(CHANNELS_INFO);
        $group_table = self::get_table_full_name($group_id);
        $tmp_table = $group_table . "_tmp";
        $q_group_id = Sql_Wrapper::sql_quote($group_id);

        $query = sprintf(self::CREATE_ORDERED_TABLE, $tmp_table, COLUMN_CHANNEL_ID);

        if ($reset) {
            $column = $this->get_id_column();
            $query .= sprintf('INSERT INTO %s (%s) SELECT %s FROM %s AS pl WHERE %s=%s AND %s IN (SELECT %s FROM %s WHERE %s=%d) ORDER by pl.ROWID;',
                $tmp_table, COLUMN_CHANNEL_ID, $column, M3uParser::CHANNELS_TABLE,
                COLUMN_GROUP_ID, $q_group_id, $column, COLUMN_CHANNEL_ID, $channels_info_table, COLUMN_DISABLED, FALSE);
        } else {
            $query .= sprintf('INSERT INTO %s (%s) SELECT %s FROM %s WHERE %s=%s AND %s IN (SELECT %s FROM %s) ORDER BY %s;',
                $tmp_table, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, $channels_info_table,
                COLUMN_GROUP_ID, $q_group_id, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, $group_table, COLUMN_SHOW_TITLE);
        }
        $query .= sprintf('DROP TABLE IF EXISTS %s;', $group_table);
        $query .= sprintf('ALTER TABLE %s RENAME TO %s;', $tmp_table, self::get_table_name($group_id));

        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param string $group_id
     * @return int
     */
    public function get_order_count($group_id)
    {
        $query = sprintf('SELECT COUNT(*) FROM %s;', self::get_table_full_name($group_id));
        return (int)$this->sql_playlist->query_value($query);
    }

    /**
     * @param string|null $group_id
     * @param int $disabled_channels PARAM_ALL - all, PARAM_ENABLED - only enabled, PARAM_DISABLED - only disabled
     * @param bool $full true - full information, false only channel_id, title and statuses
     * @return array
     */
    public function get_channels($group_id, $disabled_channels, $full = false)
    {
        if (is_null($group_id) || $group_id === TV_ALL_CHANNELS_GROUP_ID) {
            if (!$this->is_playlist_table_exists(GROUPS_INFO)) {
                return array();
            }
            $where = sprintf('ch.%s IN (SELECT %s FROM %s WHERE %s=%d AND %s=%d)',
            COLUMN_GROUP_ID, COLUMN_GROUP_ID, self::get_table_full_name(GROUPS_INFO), COLUMN_SPECIAL, FALSE, COLUMN_DISABLED, FALSE);
        } else {
            $where = sprintf('ch.%s=%s', COLUMN_GROUP_ID, Sql_Wrapper::sql_quote($group_id));
        }

        if ($disabled_channels !== PARAM_ALL) {
            $where = sprintf('%s AND %s=%s', $where, COLUMN_DISABLED, $disabled_channels);
        }

        if (!$this->is_playlist_table_exists(CHANNELS_INFO)) {
            return array();
        }

        $table_name = self::get_table_full_name(CHANNELS_INFO);
        if ($full) {
            if (!$this->is_playlist_table_exists(M3uParser::S_CHANNELS_TABLE)) {
                return array();
            }

            $query = sprintf('SELECT ch.%s, pl.* FROM %s AS pl JOIN %s AS ch ON pl.%s = ch.%s WHERE %s;',
                COLUMN_CHANNEL_ID, M3uParser::CHANNELS_TABLE, $table_name, $this->get_id_column(), COLUMN_CHANNEL_ID, $where);
        } else {
            $query = sprintf('SELECT * FROM %s AS ch WHERE %s;', $table_name, $where);
        }

        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param string|null $group_id
     * @param int $disabled_channels PARAM_ALL - all, PARAM_ENABLED - only enabled, PARAM_DISABLED - only disabled
     * @return array
     */
    public function get_channels_ids($group_id, $disabled_channels)
    {
        if (is_null($group_id) || $group_id === TV_ALL_CHANNELS_GROUP_ID) {
            $where = sprintf('%s IN (SELECT %s FROM %s WHERE %s=%d AND %s=%d)',
                COLUMN_GROUP_ID, COLUMN_GROUP_ID, self::get_table_full_name(GROUPS_INFO),
                COLUMN_SPECIAL, FALSE, COLUMN_DISABLED, FALSE);
        } else {
            $where = sprintf('%s=%s', COLUMN_GROUP_ID, Sql_Wrapper::sql_quote($group_id));
        }

        if ($disabled_channels !== PARAM_ALL) {
            if (empty($where)) {
                $where = sprintf('%s=%s', COLUMN_DISABLED, $disabled_channels);
            } else {
                $where = sprintf('%s AND %s=%s', $where, COLUMN_DISABLED, $disabled_channels);
            }
        }

        $query = sprintf('SELECT %s FROM %s WHERE %s;', COLUMN_CHANNEL_ID, self::get_table_full_name(CHANNELS_INFO), $where);
        return $this->sql_playlist->fetch_array($query, COLUMN_CHANNEL_ID);
    }

    /**
     * @return int
     */
    public function get_playlist_entries_count()
    {
        if (!$this->is_playlist_table_exists(M3uParser::S_CHANNELS_TABLE)) {
            return 0;
        }

        $query = sprintf('SELECT COUNT(*) FROM %s;', M3uParser::CHANNELS_TABLE);
        return (int)$this->sql_playlist->query_value($query);
    }

    /**
     * @param string|null $group_id
     * @param int $disabled_channels PARAM_ALL - all, PARAM_ENABLED - only enabled, PARAM_DISABLED - only disabled
     * @param int $disabled_groups PARAM_ALL - all, PARAM_ENABLED - only enabled, PARAM_DISABLED - only disabled
     * @return int
     */
    public function get_channels_count($group_id, $disabled_channels, $disabled_groups = 0)
    {
        if (is_null($group_id) || $group_id === TV_ALL_CHANNELS_GROUP_ID) {
            $groups_info_table = self::get_table_full_name(GROUPS_INFO);
            if (($disabled_groups === PARAM_ALL)) {
                $where = sprintf('%s IN (SELECT %s FROM %s WHERE %s=%d)',
                    COLUMN_GROUP_ID, COLUMN_GROUP_ID, $groups_info_table, COLUMN_SPECIAL, FALSE);
            } else {
                $where = sprintf('%s IN (SELECT %s FROM %s WHERE %s=%d AND %s=%d)',
                    COLUMN_GROUP_ID, COLUMN_GROUP_ID, $groups_info_table, COLUMN_SPECIAL, FALSE, COLUMN_DISABLED, $disabled_groups);
            }
        } else {
            $q_group_id = Sql_Wrapper::sql_quote($group_id);
            $where = sprintf('%s=%s', COLUMN_GROUP_ID, $q_group_id);
        }

        $where = sprintf('%s AND %s != %s', $where, COLUMN_CHANGED, PARAM_ALL);

        if ($disabled_channels !== PARAM_ALL) {
            $where = sprintf('%s AND %s=%s', $where, COLUMN_DISABLED, $disabled_channels);
        }

        $query = sprintf('SELECT COUNT(*) FROM %s WHERE %s;', self::get_table_full_name(CHANNELS_INFO), $where);
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @param $include_adult
     * @return array
     */
    public function get_groups_channels($include_adult)
    {
        if ($include_adult) {
            $adult = sprintf('%s<>%d', COLUMN_ADULT, -1);
        } else {
            $adult = sprintf('%s=%d', COLUMN_ADULT, FALSE);
        }

        $ch_where = sprintf('SELECT COUNT(*) FROM %s WHERE %s=ord.%s AND %s AND %s',
            self::get_table_full_name(CHANNELS_INFO), COLUMN_GROUP_ID, COLUMN_GROUP_ID, $adult, COLUMN_DISABLED);

        $query = sprintf('SELECT %s, %s, %s, (%s=%d) AS enabled, (%s=%d) AS disabled
                                    FROM %s AS ord
                                    INNER JOIN %s USING(%s) WHERE %s ORDER BY ord.ROWID;',
        COLUMN_GROUP_ID, COLUMN_TITLE, COLUMN_ICON, $ch_where, FALSE, $ch_where, TRUE,
            self::get_table_full_name(GROUPS_ORDER), self::get_table_full_name(GROUPS_INFO), COLUMN_GROUP_ID, $adult);

        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param $include_adult
     * @return array
     */
    public function get_all_channels_count($include_adult)
    {
        if ($include_adult) {
            $adult = sprintf('%s<>%d', COLUMN_ADULT, -1);
        } else {
            $adult = sprintf('%s=%d', COLUMN_ADULT, FALSE);
        }

        $ch_where = sprintf('SELECT COUNT(*) FROM %s WHERE %s=groups.%s AND %s AND %s',
            self::get_table_full_name(CHANNELS_INFO), COLUMN_GROUP_ID, COLUMN_GROUP_ID, $adult, COLUMN_DISABLED);

        $query = sprintf('SELECT SUM(ch_enabled) AS enabled, SUM(ch_disabled) AS disabled FROM (
                                SELECT (%s=%d) AS ch_enabled, (%s=%d) AS ch_disabled FROM %s AS groups WHERE %s=%d AND %s);',
            $ch_where, FALSE, $ch_where, TRUE, self::get_table_full_name(GROUPS_INFO), COLUMN_SPECIAL, FALSE, $adult);

        return $this->sql_playlist->query_value($query, true);
    }

    /**
     * @return array
     */
    public function get_all_playlists_ids()
    {
        $query = sprintf('SELECT %s FROM %s ORDER BY ROWID;', COLUMN_PLAYLIST_ID, self::PLAYLISTS_TABLE);
        return $this->sql_params->fetch_array($query, COLUMN_PLAYLIST_ID);
    }

    /**
     * @return int
     */
    public function get_all_playlists_count()
    {
        if (!$this->is_params_table_exists(self::PLAYLISTS_TABLE)) {
            return 0;
        }

        $query = sprintf('SELECT COUNT(*) FROM %s;', self::PLAYLISTS_TABLE);
        return (int)$this->sql_params->query_value($query);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function is_playlist_entry_exist($id)
    {
        if (empty($id) || !$this->is_params_table_exists(self::PLAYLISTS_TABLE)) {
            return false;
        }

        $query = sprintf('SELECT COUNT(*) FROM %s WHERE %s=%s LIMIT 1;',
            self::PLAYLISTS_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($id));
        return (bool)$this->sql_params->query_value($query);
    }

    /**
     * @return array|null
     */
    public function get_playlists_shortcuts()
    {
        $query = sprintf("SELECT %s,%s FROM %s WHERE %s<>'' ORDER BY %s;",
            COLUMN_PLAYLIST_ID, COLUMN_SHORTCUT, self::PLAYLISTS_TABLE, COLUMN_SHORTCUT, COLUMN_SHORTCUT);
        return $this->sql_params->fetch_array($query);
    }

    /**
     * @param string $id
     * @return string
     */
    public function get_playlist_shortcut($id)
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s;',
            COLUMN_SHORTCUT, self::PLAYLISTS_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($id));
        return $this->sql_params->query_value($query);
    }

    /**
     * @param string $id
     * @param string $shortcut
     * @return bool
     */
    public function set_playlist_shortcut($id, $shortcut)
    {
        $query = sprintf('UPDATE %s SET %s=%s WHERE %s=%s;',
            self::PLAYLISTS_TABLE, COLUMN_SHORTCUT, Sql_Wrapper::sql_quote($shortcut), COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($id));
        return $this->sql_params->exec($query);
    }

    /**
     * @param string $id
     * @param int $timestamp
     * @return bool
     */
    public function set_playlist_last_update($id, $timestamp)
    {
        $query = sprintf('UPDATE %s SET %s=%d WHERE %s=%s;',
            self::PLAYLISTS_TABLE, COLUMN_LAST_UPDATE, $timestamp, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($id));
        return $this->sql_params->exec($query);
    }

    /**
     * @param string $id
     * @return int
     */
    public function get_playlist_last_update($id)
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s;',
            COLUMN_LAST_UPDATE, self::PLAYLISTS_TABLE, COLUMN_PLAYLIST_ID, Sql_Wrapper::sql_quote($id));
        return $this->sql_params->query_value($query);
    }

    /**
     * @return string|null
     */
    public function get_channel_zoom($channel_id)
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s',
            COLUMN_ZOOM, self::get_table_full_name(CHANNELS_INFO), COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @param string $channel_id
     * @param string|null $preset
     * @return void
     */
    public function set_channel_zoom($channel_id, $preset)
    {
        $q_preset = Sql_Wrapper::sql_quote($preset === null ? "x" : $preset);
        $query = sprintf('UPDATE %s SET %s=%s WHERE %s=%s;',
            self::get_table_full_name(CHANNELS_INFO), COLUMN_ZOOM, $q_preset, COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @param string $channel_id
     * @return string|null
     */
    public function get_channel_epg_shift($channel_id)
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s',
            COLUMN_EPG_SHIFT, self::get_table_full_name(CHANNELS_INFO), COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        return $this->sql_playlist->query_value($query);
    }

    /**
     * @param string $channel_id
     * @param int $shift_hour
     * @param int $shift_mins
     * @return void
     */
    public function set_channel_epg_shift($channel_id, $shift_hour, $shift_mins)
    {
        $sign = $shift_hour < 0 ? -1 : 1;
        $shift = $shift_hour * 3600 + $sign * $shift_mins * 60;

        $query = sprintf('UPDATE %s SET %s=%s WHERE %s=%s;', self::get_table_full_name(CHANNELS_INFO),
            COLUMN_EPG_SHIFT, Sql_Wrapper::sql_quote($shift), COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * /**
     * @param string $channel_id
     * @param bool $external
     * @return void
     */
    public function set_channel_ext_player($channel_id, $external)
    {
        $query = sprintf('UPDATE %s SET %s=%s WHERE %s=%s;', self::get_table_full_name(CHANNELS_INFO),
            COLUMN_EXTERNAL_PLAYER, (int)$external, COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));

        $this->sql_playlist->exec_transaction($query);
    }

    /**
     * @return bool
     */
    public function get_channel_ext_player($channel_id)
    {
        $query = sprintf('SELECT %s FROM %s WHERE %s=%s',
            COLUMN_EXTERNAL_PLAYER, self::get_table_full_name(CHANNELS_INFO), COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($channel_id));
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

        if (!$this->is_playlist_table_exists(TV_HISTORY)
            || !$this->is_playlist_table_exists(CHANNELS_INFO)
            || !$this->is_playlist_table_exists(M3uParser::S_CHANNELS_TABLE)) {
            return array();
        }

        $query = sprintf('SELECT * FROM %s as tv
                    INNER JOIN %s as ord ON tv.%s = ord.%s
                    INNER JOIN %s as iptv ON iptv.%s = tv.%s
                    WHERE ord.%s=%d ORDER BY tv.%s DESC;', self::get_table_full_name(TV_HISTORY),
            self::get_table_full_name(CHANNELS_INFO), COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID,
            M3uParser::CHANNELS_TABLE, $this->get_id_column(), COLUMN_CHANNEL_ID, COLUMN_DISABLED, FALSE, COLUMN_TIMESTAMP);

        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @return int
     */
    public function get_tv_history_count()
    {
        $query = sprintf('SELECT COUNT(*) FROM %s;', self::get_table_full_name(TV_HISTORY));
        return (int)$this->sql_playlist->query_value($query);
    }

    /**
     * @param string $id
     */
    public function erase_tv_history($id)
    {
        hd_debug_print("erase $id");
        $query = sprintf('DELETE FROM %s WHERE %s=%s;',
            self::get_table_full_name(TV_HISTORY), COLUMN_CHANNEL_ID, Sql_Wrapper::sql_quote($id));
        $this->sql_playlist->exec($query);
    }

    /**
     * @return void
     */
    public function clear_tv_history()
    {
        $table_name = self::get_table_full_name(TV_HISTORY);
        $query = sprintf('DROP TABLE IF EXISTS %s;', $table_name);
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
        $query = sprintf('SELECT *, MAX(%s) FROM %s GROUP BY %s ORDER BY %s DESC;',
            COLUMN_TIMESTAMP, self::get_table_full_name(VOD_HISTORY), COLUMN_MOVIE_ID, COLUMN_TIMESTAMP);
        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * Get count of VOD history for selected playlist
     *
     * @return int
     */
    public function get_all_vod_history_count()
    {
        $query = sprintf('SELECT COUNT(DISTINCT %s) FROM %s;', COLUMN_MOVIE_ID, self::get_table_full_name(VOD_HISTORY));
        return $this->sql_playlist->query_value($query);
    }

    /**
     * Get history for selected movie_id
     *
     * @param string $movie_id
     * @return array
     */
    public function get_vod_history($movie_id)
    {
        $query = sprintf('SELECT * FROM %s WHERE %s=%s;',
            self::get_table_full_name(VOD_HISTORY), COLUMN_MOVIE_ID, Sql_Wrapper::sql_quote($movie_id));
        return $this->sql_playlist->fetch_array($query);
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
        $query = sprintf('INSERT OR REPLACE INTO %s (%s,%s,%s) VALUES (%s,%s,%s);',
            self::get_table_full_name(VOD_HISTORY), COLUMN_MOVIE_ID, COLUMN_SERIES_ID, Sql_Wrapper::sql_make_list_from_keys($values),
            Sql_Wrapper::sql_quote($movie_id), Sql_Wrapper::sql_quote($series_id), Sql_Wrapper::sql_make_list_from_values($values));
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
        $query = sprintf('SELECT COUNT(*) FROM %s WHERE %s=%s;',
            self::get_table_full_name(VOD_HISTORY), COLUMN_MOVIE_ID, Sql_Wrapper::sql_quote($movie_id));
        return (int)$this->sql_playlist->query_value($query);
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
        $table_name = self::get_table_full_name(VOD_HISTORY);
        $q_movie_id = Sql_Wrapper::sql_quote($movie_id);
        $q_series_id = Sql_Wrapper::sql_quote($series_id);
        if ($param_name === null) {
            $query = sprintf('SELECT * FROM %s WHERE %s=%s AND %s=%s;',
                $table_name, COLUMN_MOVIE_ID, $q_movie_id, COLUMN_SERIES_ID, $q_series_id);
        } else {
            $query = sprintf('SELECT %s FROM %s WHERE %s=%s AND %s=%s;',
                Sql_Wrapper::sql_quote($param_name), $table_name, COLUMN_MOVIE_ID, $q_movie_id, COLUMN_SERIES_ID, $q_series_id);
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
        $query = sprintf('DELETE FROM %s WHERE %s=%s;',
            self::get_table_full_name(VOD_HISTORY), COLUMN_MOVIE_ID, Sql_Wrapper::sql_quote($movie_id));
        $this->sql_playlist->exec($query);
    }

    /**
     * Remove history by movie_id and series_id
     *
     * @param $movie_id
     * @param $series_id
     */
    public function remove_vod_history_part($movie_id, $series_id)
    {
        $query = sprintf('DELETE FROM %s WHERE %s=%s AND %s=%s;',
            self::get_table_full_name(VOD_HISTORY), COLUMN_MOVIE_ID, Sql_Wrapper::sql_quote($movie_id),
            COLUMN_SERIES_ID, Sql_Wrapper::sql_quote($series_id));
        $this->sql_playlist->exec($query);
    }

    /**
     * Clear all history
     */
    public function clear_all_vod_history()
    {
        $table_name = self::get_table_full_name(VOD_HISTORY);
        $query = sprintf('DROP TABLE IF EXISTS %s;', $table_name);
        $query .= sprintf(self::CREATE_VOD_HISTORY_TABLE, $table_name);
        $this->sql_playlist->exec_transaction($query);

    }

    /**
     * @return int
     */
    public function get_playlist_group_count()
    {
        if (!$this->is_playlist_table_exists(M3uParser::S_GROUPS_TABLE)) {
            return 0;
        }

        $query = sprintf('SELECT COUNT(*) FROM %s;', M3uParser::GROUPS_TABLE);
        return (int)$this->sql_playlist->query_value($query);
    }

    /**
     * @param string $group_id
     * @param bool $include_adult
     * @param bool $include_hidden
     * @return array
     */
    public function get_channels_by_order($group_id, $include_adult = true, $include_hidden = false)
    {
        if (!$this->is_playlist_table_exists(M3uParser::S_CHANNELS_TABLE)) {
            hd_debug_print('No channels in ' . M3uParser::S_CHANNELS_TABLE . ' table!');
            return array();
        }

        if ($include_adult) {
            $where = sprintf('ch.%s<>%d', COLUMN_ADULT, -1);
        } else {
            $where = sprintf('ch.%s=%d', COLUMN_ADULT, FALSE);
        }

        if ($include_hidden) {
            $on = sprintf('ch.%s=ord.%s', COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID);
        } else {
            $on = sprintf('ch.%s=ord.%s AND ch.%s=%d',
                COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, COLUMN_DISABLED, FALSE);
        }

        $query = sprintf('SELECT ord.%s, ch.%s, ch.%s, ch.%s, pl.*, pl.ROWID as ch_number
                    FROM %s AS pl
                    JOIN %s AS ord ON pl.%s=ord.%s
                    JOIN %s as ch ON %s
                    WHERE %s ORDER BY ord.ROWID;',
            COLUMN_CHANNEL_ID, COLUMN_TITLE, COLUMN_SHOW_TITLE, COLUMN_DISABLED,
            M3uParser::CHANNELS_TABLE, self::get_table_full_name($group_id), $this->get_id_column(), COLUMN_CHANNEL_ID,
            self::get_table_full_name(CHANNELS_INFO), $on, $where);

        return $this->sql_playlist->fetch_array($query);
    }

    /**
     * @param string $group_id
     * @param bool $include_adult
     * @return array
     */
    public function get_channels_ids_by_order($group_id, $include_adult = true)
    {
        if ($include_adult) {
            $query = sprintf('SELECT %s FROM %s ORDER BY ROWID;', COLUMN_CHANNEL_ID, self::get_table_full_name($group_id));
        } else {
            $query = sprintf('SELECT %s FROM %s as ord INNER JOIN %s USING(%s) WHERE %s=%d ORDER BY ord.ROWID;',
                COLUMN_CHANNEL_ID, self::get_table_full_name($group_id), self::get_table_full_name(CHANNELS_INFO),
                COLUMN_CHANNEL_ID, COLUMN_ADULT, FALSE);
        }

        return $this->sql_playlist->fetch_array($query, COLUMN_CHANNEL_ID);
    }

    /**
     * @return int
     */
    public function get_channels_by_order_cnt($group_id, $include_adult = true, $include_hidden = false)
    {
        if (!$this->is_playlist_table_exists(M3uParser::S_CHANNELS_TABLE)) {
            return 0;
        }

        if ($include_adult) {
            $where = sprintf('ch.%s<>%d', COLUMN_ADULT, -1);
        } else {
            $where = sprintf('ch.%s=%d', COLUMN_ADULT, FALSE);
        }

        if ($include_hidden) {
            $query = sprintf('SELECT COUNT(ord.%s) FROM %s AS pl
                    JOIN %s AS ord ON pl.%s=ord.%s
                    JOIN %s as ch ON ch.%s=ord.%s
                    WHERE %s ORDER BY ord.ROWID;',
                COLUMN_CHANNEL_ID, M3uParser::CHANNELS_TABLE, self::get_table_full_name($group_id), $this->get_id_column(), COLUMN_CHANNEL_ID,
                self::get_table_full_name(CHANNELS_INFO), COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, $where);
        } else {
            $query = sprintf('SELECT COUNT(ord.%s) FROM %s AS pl
                    JOIN %s AS ord ON pl.%s=ord.%s
                    JOIN %s as ch ON ch.%s = ord.%s AND ch.%s=%d
                    WHERE %s ORDER BY ord.ROWID;',
                COLUMN_CHANNEL_ID, M3uParser::CHANNELS_TABLE, self::get_table_full_name($group_id), $this->get_id_column(), COLUMN_CHANNEL_ID,
                self::get_table_full_name(CHANNELS_INFO), COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, COLUMN_DISABLED, FALSE, $where);
        }
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
        $table_name = self::get_table_full_name(CHANNELS_INFO);
        $distinct = is_array($channel_id) ? 'DISTINCT ' . COLUMN_GROUP_ID : COLUMN_GROUP_ID;
        $where = Sql_Wrapper::sql_make_where_clause($channel_id, COLUMN_CHANNEL_ID);
        $groups_select = sprintf('SELECT %s FROM %s WHERE %s;', $distinct, $table_name, $where);

        $query = '';
        foreach ($this->sql_playlist->fetch_array($groups_select) as $group) {
            $q_table = self::get_table_full_name($group[COLUMN_GROUP_ID]);
            if ($show) {
                $query .= sprintf('INSERT OR IGNORE INTO %s (%s) SELECT %s FROM %s WHERE %s AND %s=%s ORDER BY ROWID;',
                    $q_table, COLUMN_CHANNEL_ID, COLUMN_CHANNEL_ID, $table_name,
                    $where, COLUMN_GROUP_ID, Sql_Wrapper::sql_quote($group[COLUMN_GROUP_ID]));
            } else {
                $query .= sprintf('DELETE FROM %s WHERE %s;', $q_table, $where);
            }
        }
        $query .= sprintf('UPDATE %s SET %s=%s WHERE %s;', $table_name, COLUMN_DISABLED, $disable, $where);

        $this->sql_playlist->exec($query);
    }

    /**
     * @param string $channel_id
     * @param bool $full
     * @return array
     */
    public function get_channel_info($channel_id, $full = true)
    {
        $q_channel_id = Sql_Wrapper::sql_quote($channel_id);
        $table_name = self::get_table_full_name(CHANNELS_INFO);
        if ($full) {
            $query = sprintf('SELECT ch.%s, ch.%s, pl.*, pl.ROWID AS ch_number FROM %s as pl
                                        JOIN %s AS ch ON pl.%s = ch.%s WHERE ch.%s=%s AND ch.%s=%d;',
                COLUMN_CHANNEL_ID, COLUMN_EPG_SHIFT, M3uParser::CHANNELS_TABLE, $table_name, $this->get_id_column(), COLUMN_CHANNEL_ID,
                COLUMN_CHANNEL_ID, $q_channel_id, COLUMN_DISABLED, FALSE);
        } else {
            $query = sprintf('SELECT * FROM %s WHERE %s=%s AND %s=%d;',
                $table_name, COLUMN_CHANNEL_ID, $q_channel_id, COLUMN_DISABLED, FALSE);
        }

        return $this->sql_playlist->query_value($query, true);
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
     * @param User_Input_Handler $handler
     * @param array $actions
     * @return void
     */
    public function add_shortcuts_handlers($handler, &$actions)
    {
        foreach ($this->get_playlists_shortcuts() as $row) {
            $actions[$row[PARAM_SHORTCUT]] = User_Input_Handler_Registry::create_action($handler,
                ACTION_SHORTCUT,
                null,
                array(COLUMN_PLAYLIST_ID => $row[COLUMN_PLAYLIST_ID])
            );
        }
    }

    /**
     * Returns full table name
     *
     * @param string $id
     * @return string
     */
    public static function get_table_name($id)
    {
        switch ($id) {
            case VOD_FAV_GROUP_ID:
                $table_name = self::FAV_VOD_ORDERS_TABLE;
                break;

            case TV_FAV_GROUP_ID:
            case TV_FAV_COMMON_GROUP_ID:
                $table_name = self::FAV_TV_ORDERS_TABLE;
                break;

            case TV_HISTORY:
                $table_name = self::TV_HISTORY_TABLE;
                break;

            case VOD_HISTORY:
                $table_name = self::VOD_HISTORY_TABLE;
                break;

            case VOD_LIST_GROUP_ID:
                $table_name = self::VOD_LIST_TABLE;
                break;

            case VOD_FILTER_LIST:
                $table_name = self::VOD_FILTERS_TABLE;
                break;

            case VOD_SEARCH_LIST:
                $table_name = self::VOD_SEARCHES_TABLE;
                break;

            case GROUPS_ORDER:
                $table_name = self::GROUPS_ORDER_TABLE;
                break;

            case GROUPS_INFO:
                $table_name = self::GROUPS_INFO_TABLE;
                break;

            case CHANNELS_INFO:
                $table_name = self::CHANNELS_INFO_TABLE;
                break;

            case self::PLAYLISTS_TABLE:
                $table_name = self::PLAYLISTS_TABLE;
                break;

            case self::XMLTV_TABLE:
                $table_name = self::XMLTV_TABLE;
                break;

            case self::SELECTED_XMLTV_TABLE:
                $table_name = self::SELECTED_XMLTV_TABLE;
                break;

            case self::SELECTED_JSON_TABLE:
                $table_name = self::SELECTED_JSON_TABLE;
                break;

            case M3uParser::S_CHANNELS_TABLE:
                $table_name = M3uParser::S_CHANNELS_TABLE;
                break;

            case M3uParser::S_GROUPS_TABLE:
                $table_name = M3uParser::S_GROUPS_TABLE;
                break;

            default:
                $table_name = "orders_" . Hashed_Array::hash($id);
                break;
        }

        return $table_name;
    }

    public static function get_table_full_name($id)
    {
        $table_name = self::get_table_name($id);
        $db = self::get_db_name($id);
        if (empty($db)) {
            return $table_name;
        }

        return "$db.$table_name";
    }

    public static function get_db_name($id)
    {
        switch ($id) {
            case TV_HISTORY:
                $db = self::TV_HISTORY_DB;
                break;

            case VOD_HISTORY:
                $db = self::VOD_HISTORY_DB;
                break;

            case VOD_FAV_GROUP_ID:
            case VOD_LIST_GROUP_ID:
            case VOD_FILTER_LIST:
            case VOD_SEARCH_LIST:
            case TV_FAV_COMMON_GROUP_ID:
            case self::PLAYLISTS_TABLE:
            case self::XMLTV_TABLE:
            case self::SELECTED_XMLTV_TABLE:
                $db = '';
                break;

            case M3uParser::S_CHANNELS_TABLE:
            case M3uParser::S_GROUPS_TABLE:
                $db = M3uParser::IPTV_DB;
                break;

            case GROUPS_INFO:
            case CHANNELS_INFO:
            case GROUPS_ORDER:
            case TV_FAV_GROUP_ID:
            default:
                $db = self::PLAYLIST_ORDERS_DB;
                break;
        }

        return $db;
    }

    ////////////////////////////////////////////////////////
    /// database methods

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
            $sql_wrapper = $this->sql_params;
        } else {
            $script = self::CREATE_ORDERED_TABLE;
            $sql_wrapper = $this->sql_playlist;
        }
        $table_name = self::get_table_full_name($table);

        $q_item = Sql_Wrapper::sql_quote($item);
        $cur = '';
        $new = '';
        if ($direction === Ordered_Array::UP || $direction === Ordered_Array::DOWN) {
            $sub_query = sprintf('SELECT ROWID AS cur FROM %s WHERE %s=%s', $table_name, $column, $q_item);
            $min_max = $direction === Ordered_Array::UP ? 'MAX(ROWID)' : 'MIN(ROWID)';
            $logic = sprintf($direction === Ordered_Array::UP ? 'ROWID < (%s)' : 'ROWID > (%s)', $sub_query);
            $query = sprintf('SELECT * FROM ((SELECT %s AS new FROM %s WHERE %s) INNER JOIN (%s));',
                $min_max, $table_name, $logic, $sub_query);

            $positions = $sql_wrapper->query_value($query, true);
            if (empty($positions) || $positions['cur'] === null || $positions['new'] === null) {
                return false;
            }

            $cur = $positions['cur'];
            $new = $positions['new'];
            $query  = sprintf('UPDATE %s SET ROWID=%d WHERE ROWID=%d;', $table_name, -$cur, $cur);
            $query .= sprintf('UPDATE %s SET ROWID=%d WHERE ROWID=%d;', $table_name, $cur, $new);
            $query .= sprintf('UPDATE %s SET ROWID=%d WHERE ROWID=%d;', $table_name, $new, -$cur);
        } else  if ($direction === Ordered_Array::TOP || $direction === Ordered_Array::BOTTOM) {
            if ($direction == Ordered_Array::TOP) {
                $query = sprintf('SELECT ROWID AS cur FROM %s WHERE %s=%s AND ROWID > (SELECT MIN(ROWID) FROM %s) LIMIT 1;',
                    $table_name, $column, $q_item, $table_name);
                $cur = $sql_wrapper->query_value($query);
                if (empty($cur)) {
                    return false;
                }

                $new = -$cur;
            }

            if ($direction === Ordered_Array::BOTTOM) {
                $query_pos = sprintf('SELECT * FROM ((SELECT ROWID AS cur FROM %s WHERE %s = %s AND ROWID < (SELECT MAX(ROWID) FROM %s))
                                                INNER JOIN (SELECT ROWID AS new FROM %s ORDER BY ROWID DESC LIMIT 1));',
                    $table_name, $column, $q_item, $table_name, $table_name);

                $positions = $sql_wrapper->query_value($query_pos, true);
                if (empty($positions) || $positions['cur'] === null || $positions['new'] === null) {
                    return false;
                }

                $cur = $positions['cur'];
                $new = $positions['new'] + 1;
            }

            $tmp_table =  $table_name . "_tmp";
            $query = sprintf($script, $tmp_table, $column);
            $query .= sprintf('UPDATE %s SET ROWID=%d WHERE ROWID=%d;', $table_name, $new, $cur);
            $query .= sprintf('INSERT INTO %s SELECT * FROM %s ORDER BY ROWID;', $tmp_table, $table_name);
            $query .= sprintf('DROP TABLE IF EXISTS %s;', $table_name);
            $query .= sprintf('ALTER TABLE %s RENAME TO %s;', $tmp_table, self::get_table_name($table));
        } else {
            return false;
        }
        return $sql_wrapper->exec_transaction($query);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function is_playlist_table_exists($name)
    {
        $table_name = self::get_table_name($name);
        $db_name = self::get_db_name($name);

        if (!empty($db_name) && !$this->sql_playlist->is_database_attached($db_name)) {
            return false;
        }

        $db_name = empty($db_name) ? 'sqlite_master' : "$db_name.sqlite_master";
        return (int)$this->sql_playlist->query_value("SELECT COUNT(name) FROM $db_name WHERE type='table' AND name='$table_name';") !== 0;
    }

    /**
     * @return bool
     */
    public function is_params_table_exists($name)
    {
        return $this->sql_params && $this->sql_params->is_table_exists($name);
    }
}