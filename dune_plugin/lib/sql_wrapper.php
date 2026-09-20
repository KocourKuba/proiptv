<?php

class Sql_Wrapper
{
    /**
     * @var SQLite3
    */
    protected $db = null;

    /**
     * @var string
     */
    protected $db_path = '';

    /**
     * @var int
     */
    protected $open_mode;

    // Cap the per-connection page cache so N simultaneously open databases
    // (common/plugin, playlist, playlist_settings, vod, tv_history, vod_history ...)
    // can't push the process over the device's 384Mb memory budget.
    const MAX_CACHE_SIZE_KB = 1500;

    // SQLite picked 1024 bytes as the default page size until 3.12; the device ships 3.7.4, so
    // every database created here would use it. 4096 matches the erase/read granularity of the
    // flash the plugin writes to and cuts the b-tree depth, which is what the bulk index build
    // in Epg_Manager_Xmltv spends its time on. Only a database that has no page yet can change
    // it - an existing file keeps the size it was created with until it is rebuilt.
    const PAGE_SIZE = 4096;

    // 'PRAGMA cache_size=-N' started meaning "N KiB" in SQLite 3.7.10, and 'PRAGMA mmap_size'
    // only exists from 3.7.17. On the 3.7.4 the device ships, a negative cache_size is taken as
    // a page count instead, so the same statement would reserve PAGE_SIZE/1024 times the memory
    // it asks for, and mmap_size is silently ignored.
    const SQLITE_CACHE_SIZE_KB_VERSION = 3007010;
    const SQLITE_MMAP_VERSION = 3007017;

    // Default flags SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE
    /**
     * @param string $db_path
     * @param int $flags
     * @param string $journal
     * @return void
     */
    public function __construct($db_path, $flags = 6, $journal = 'MEMORY')
    {
        try {
            $this->db = new SQLite3($db_path, $flags, '');
            $version = SQLite3::version();
            $version = $version['versionNumber'];

            // has to come before anything that makes the database allocate its first page
            $this->db->exec('PRAGMA page_size=' . self::PAGE_SIZE . ';');
            $this->db->exec("PRAGMA journal_mode=$journal;");

            // bound the page cache instead of relying on the SQLite/OS default
            if ($version >= self::SQLITE_CACHE_SIZE_KB_VERSION) {
                $this->db->exec('PRAGMA cache_size=-' . self::MAX_CACHE_SIZE_KB . ';');
            } else {
                // older SQLite counts pages, so convert the budget with the page size in use
                $page_size = (int)$this->db->querySingle('PRAGMA page_size;');
                if ($page_size <= 0) {
                    $page_size = self::PAGE_SIZE;
                }
                $this->db->exec('PRAGMA cache_size=' . (int)ceil(self::MAX_CACHE_SIZE_KB * 1024 / $page_size) . ';');
            }

            // memory-mapped I/O inflates RSS on embedded boxes for no query benefit here; keep it
            // off where the pragma exists at all (it is off by default in the versions without it)
            if ($version >= self::SQLITE_MMAP_VERSION) {
                $this->db->exec('PRAGMA mmap_size=0;');
            }

            // spill temp b-trees (ORDER BY/GROUP BY on unindexed columns) to disk, not to the heap
            $this->db->exec('PRAGMA temp_store=FILE;');
            // journal_mode=MEMORY keeps the rollback journal in RAM, so unlike the default
            // rollback-journal-on-disk mode it does NOT protect against corruption on power loss -
            // keep synchronous=FULL here; NORMAL only becomes safe to trade for fewer fsyncs
            // if this connection is switched to journal_mode=WAL
            $this->db->exec('PRAGMA synchronous=FULL;');
            $this->open_mode = $flags;
            $this->db_path = $db_path;
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            $this->db = null;
            $this->open_mode = 0;
            $this->db_path = '';
        }
    }

    /**
     * Explicitly release the SQLite connection (page cache, prepared statement cache)
     * instead of waiting for PHP's garbage collector. Call this as soon as a database
     * is no longer needed, not only at script end - important on a hard memory budget.
     *
     * @return void
     */
    public function close()
    {
        if ($this->db !== null) {
            $this->db->close();
            $this->db = null;
        }
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return SQLite3|null
     */
    public function get_db()
    {
        return $this->db;
    }

    /**
     * @return string
     */
    public function get_db_path()
    {
        return $this->db_path;
    }

    /**
     * @return int
     */
    public function is_readonly()
    {
        return $this->open_mode & SQLITE3_OPEN_READONLY;
    }

    /**
     * @return bool
     */
    public function is_valid()
    {
        return $this->db !== null;
    }

    /**
     * Returns 0 - if attach failed
     * Returns 1 - if attach success
     * Returns 2 - if database already attached
     *
     * @param string $db_filename
     * @param string $name
     * @return int
     */
    public function attachDatabase($db_filename, $name)
    {
        hd_debug_print("Trying to attach: as '$name' db: '$db_filename'", true);
        $result = $this->is_database_attached($name, $db_filename);
        if ($result === 2) {
            hd_debug_print('Already attached', true);
            return $result;
        }

        if ($result !== 0) {
            $this->exec("DETACH DATABASE '$name';");
        }

        $this->exec("ATTACH DATABASE '$db_filename' AS $name;");
        $result = $this->is_database_attached($name, $db_filename);
        hd_debug_print('Attach: ' . ($result ? 'success' : 'fail'), true);
        return $result;
    }

    /**
     * Returns true - if detach success
     * Returns false - if detach failed
     *
     * @param string $name
     * @return bool
     */
    public function detachDatabase($name)
    {
        if ($this->is_database_attached($name) !== 0) {
            hd_debug_print("Trying to detach: '$name'", true);
            $this->exec("DETACH DATABASE '$name';");
            $result = $this->is_database_attached($name) === 0;
            hd_debug_print('Detach: ' . ($result ? 'success' : 'fail'), true);
            return $result;
        }
        return true;
    }

    /**
     * Return 0 if no database attached
     * Return 1 if database attached (filename to check not set)
     * Return 2 if database attached and filename is match
     * Return 3 if database attached and filename not match
     *
     * @param string $db_name
     * @param string|null $db_filename Full path to database file
     * @return int
     */
    public function is_database_attached($db_name, $db_filename = null)
    {
        if (!$this->is_valid()) {
            hd_debug_print('Sqlite wrapper db not inited!');
            return 0;
        }

        $result = 0;
        foreach ($this->fetch_array('PRAGMA database_list') as $database) {
            if ($database['name'] !== $db_name) continue;

            if ($db_filename == null) {
                $result = 1;
                break;
            }

            if ($db_filename == ':memory:' && empty($database['file'])) {
                $result = 2;
                break;
            }

            $used_db_file = basename($database['file']);
            $checked_db_file = basename($db_filename);
            $result = ($used_db_file === $checked_db_file) ? 2 : 3;
            break;
        }

        return $result;
    }

    /**
     * @param string $table_name
     * @param string|null $db_name
     * @return bool
     */
    public function is_table_exists($table_name, $db_name = null)
    {
        if (!empty($db_name) && !$this->is_database_attached($db_name)) {
            return false;
        }

        $db_name = empty($db_name) ? 'sqlite_master' : "$db_name.sqlite_master";
        return (int)$this->query_value("SELECT count(name) FROM $db_name WHERE type='table' AND name='$table_name';") !== 0;
    }

    /**
     * @param string $table_name
     * @param string $column_name
     * @return bool
     */
    public function is_column_exists($table_name, $column_name)
    {
        $query = sprintf("SELECT count(*) FROM sqlite_master WHERE type='table' AND name=%s AND sql like %s;",
            Sql_Wrapper::sql_quote($table_name), Sql_Wrapper::sql_quote("%$column_name%"));
        return (int)$this->query_value($query) !== 0;
    }

    /**
     * @param string|null $db_name
     * @return array
     */
    public function get_master_table_list($db_name = null)
    {
        if (!is_null($db_name) && !$this->is_database_attached($db_name)) {
            hd_debug_print("get_master_table_list: Database '$db_name' not attached!");
            return array();
        }

        $db_name = is_null($db_name) ? 'sqlite_master' : "$db_name.sqlite_master";
        return $this->fetch_array("SELECT name FROM $db_name WHERE type='table';", 'name');
    }

    /**
     * quote value (val1 -> 'val1')
     * *
     * @param string $var
     * @return string
     */
    public static function sql_quote($var)
    {
        return "'" . SQLite3::escapeString($var) . "'";
    }

    /**
     * prepare data to create table from array
     * array must contain follow data: column => column condition
     * channel_id => TEXT PRIMARY KEY NOT NULL, name => TEXT
     *
     * @param array $values
     * @return string
     */
    public static function make_table_columns($values)
    {
        $str = '';
        foreach ($values as $col => $type) {
            $str .= "$col $type,";
        }

        return rtrim($str, ",");
    }

    /**
     * Make SET list "SET key1 = 'array[key1]', key2 = 'array[key2]', key4 = 'array[key3]'"
     * from array values (array[key1], array[key2], array[key3])
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_set_list($arr)
    {
        $str = array();
        foreach ($arr as $col => $type) {
            $str[] = $col . '=' . self::sql_quote($type);
        }
        return implode(',', $str);
    }

    /**
     * Make where clause for single value or array
     *
     * @param array|string $values
     * @param string $column
     * @param bool $not
     * @return string
     */
    public static function sql_make_where_clause($values, $column, $not = false)
    {
        if (is_array($values)) {
            $in = $not ? "NOT IN" : "IN";
            $q_values = Sql_Wrapper::sql_make_list_from_values($values);
            $where = "$column $in ($q_values)";
        } else {
            $eq = $not ? "!=" : "=";
            $where = "$column $eq" . Sql_Wrapper::sql_quote($values);
        }

        return $where;
    }

    /**
     * Make insert list from array
     * array(val1, val2, val3) => val1, val2, val3
     * if quoted: array(val1, val2, val3) => 'val1', 'val2', 'val3'
     * if prefix ':' : array(val1, val2, val3) => :val1, :val2, :val3
     * if quoted prefix ':' : array(val1, val2, val3) => ':val1', ':val2', ':val3'
     *
     * @param array $arr
     * @param bool $quoted
     * @param string $prefix
     * @return string
     */
    public static function sql_make_list_from_values($arr, $quoted = true, $prefix = '')
    {
        if ($quoted) {
            $arr = array_map(function($var) {
                return "'" . SQLite3::escapeString($var) . "'";
            }, $arr);
        }

        return $prefix . implode(",$prefix", $arr);
    }

    /**
     * Make list from array keys
     * array(key1=>val1,key2=>val2,key3=>val3) -> key1,key2,key3
     *
     * @param array $arr
     * @param bool $quoted
     * @param string $prefix
     * @return string
     */
    public static function sql_make_list_from_keys($arr, $quoted = false, $prefix = '')
    {
        return self::sql_make_list_from_values(array_keys($arr), $quoted, $prefix);
    }

    /**
     * Execute query
     *
     * @param string $query
     * @return bool result of exec
     */
    public function exec($query)
    {
        if (empty($query)) {
            return false;
        }

        if ($this->db === null) {
            hd_debug_print("failed to execute query, db is closed: $query");
            return false;
        }

        $result = $this->db->exec($query);
        if ($result === false) {
            hd_debug_print();
            hd_debug_print("failed to execute query: $query");
        }
        return $result;
    }

    /**
     * Prepare bind based on query
     *
     * @param string $query
     * @return SQLite3Stmt|false
     */
    public function prepare($query)
    {
        if ($this->db === null) {
            hd_debug_print("failed to prepare statement, db is closed: $query");
            return false;
        }

        return $this->db->prepare($query);
    }

    /**
     * Prepare bind based on array of columns
     *
     * @param string $action
     * @param string $table
     * @param array $columns
     * @return SQLite3Stmt|false
     */
    public function prepare_bind($action, $table, $columns)
    {
        $col = self::sql_make_list_from_values($columns, false);
        $val = self::sql_make_list_from_values($columns, false, ':');
        $query = "$action INTO $table ($col) VALUES ($val);";
        if ($this->db === null) {
            hd_debug_print("failed to prepare statement, db is closed: $query");
            return false;
        }

        $result = $this->db->prepare($query);
        if ($result === false) {
            hd_debug_print();
            hd_debug_print("failed to prepare statement: $query");
        }

        return $result;
    }

    /**
     * query single value.
     * Typically for SELECT count(), SELECT channel_id, group_id etc
     * if full_row - returns entire row instead of signgle column
     * query returns only one value!
     *
     * @param string $query
     * @param bool $full_row
     * @return mixed
     */
    public function query_value($query, $full_row = false)
    {
        if (empty($query)) {
            return false;
        }

        if ($this->db === null) {
            hd_debug_print("failed to execute query, db is closed: $query");
            return false;
        }

        $result = $this->db->querySingle($query, $full_row);
        if ($result === false) {
            hd_debug_print();
            hd_debug_print("failed to execute query: $query");
        }
        return $result;
    }

    /**
     * Fetch array of rows that contains array of columns
     * if column is null then returned array of rows['column'] it will convert to simple array() of values row['column']
     *
     * @param string $query
     * @param string|null $column
     * @return array
     */
    public function fetch_array($query, $column = null)
    {
        if (empty($query)) {
            return array();
        }

        if ($this->db === null) {
            hd_debug_print("failed to fetch array, db is closed: $query");
            return array();
        }

        $rows = array();
        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = is_null($column) ? $row : $row[$column];
            }
        } else {
            hd_debug_print();
            hd_debug_print("failed to fetch array: $query");
        }

        return $rows;
    }

    /**
     * Bulk insert/replace many rows into $table using a single prepared statement
     * inside one transaction, instead of concatenating one INSERT string per row
     * and running it through exec(). Column/placeholder convention matches
     * {@see prepare_bind}.
     * Roughly ~2x faster than the concatenated-exec()
     * equivalent, in addition to avoiding query-string building/escaping cost.
     *
     * @param string $action e.g. 'INSERT', 'INSERT OR IGNORE', 'INSERT OR REPLACE'
     * @param string $table
     * @param array $columns column names, also the keys expected in each $rows entry
     * @param array $rows array of rows, each row: array($column => $value, ...)
     * @return bool
     */
    public function bulk_insert($action, $table, $columns, $rows)
    {
        if (empty($rows)) {
            return true;
        }

        $stmt = $this->prepare_bind($action, $table, $columns);
        if ($stmt === false) {
            return false;
        }

        // If we're already inside a caller-managed transaction, BEGIN fails here -
        // in that case don't COMMIT/ROLLBACK below either, leave it to the caller.
        $own_transaction = $this->db->exec('BEGIN;');

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $value = isset($row[$column]) ? $row[$column] : null;
                if (is_bool($value) || is_int($value)) {
                    $stmt->bindValue(":$column", (int)$value, SQLITE3_INTEGER);
                } elseif (is_null($value)) {
                    $stmt->bindValue(":$column", null, SQLITE3_NULL);
                } elseif (is_float($value)) {
                    $stmt->bindValue(":$column", $value, SQLITE3_FLOAT);
                } else {
                    $stmt->bindValue(":$column", $value);
                }
            }

            if ($stmt->execute() === false) {
                hd_debug_print("Error executing bulk_insert statement for table: $table");
                if ($own_transaction !== false) {
                    $this->db->exec('ROLLBACK;');
                }
                return false;
            }
        }

        if ($own_transaction === false) {
            return true;
        }

        if ($this->db->exec('COMMIT;') === false) {
            hd_debug_print("Error commit bulk_insert transaction for table: $table");
            $this->db->exec('ROLLBACK;');
            return false;
        }

        return true;
    }

    /**
     * Execute query as one transaction (multiple insert/update/delete etc)
     * If transaction failed it's immediatelly rollback, i.e. database not updated!
     *
     * @param string $query
     * @return bool result of transaction
     */
    public function exec_transaction($query)
    {
        if (empty($query)) {
            return false;
        }

        $query = 'BEGIN;' . $query . 'COMMIT;' ;
        if ($this->db->exec($query)) {
            return true;
        }

        hd_debug_print();
        hd_debug_print('Error commit transaction!');
        hd_debug_print($query);
        $this->db->exec('ROLLBACK;');
        return false;
    }
}