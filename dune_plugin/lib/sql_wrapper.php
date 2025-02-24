<?php

class Sql_Wrapper
{
    /**
     * @var SQLite3
    */
    protected $db = null;

    public function __construct($db_name, $flags = 0)
    {
        if ($flags === 0) {
            $flags = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE;
        }

        hd_debug_print("Open db: $db_name", true);
        $this->db = new SQLite3($db_name, $flags, '');
        $this->db->exec("PRAGMA journal_mode=MEMORY;");
    }

    public function get_db()
    {
        return $this->db;
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
     * Make INSERT list from array values (array[key1], array[key2], array[key3])
     * (key1,key2,key3) VALUES ('array[key1]','array[key2]','array[key3]')
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_insert_list($arr, $quoted = true, $bind = false)
    {
        $columns = self::sql_make_list_from_keys($arr);
        $values = self::sql_make_list_from_values($arr, $quoted, $bind ? ':' : '');
        return "($columns) VALUES ($values)";
    }

    /**
     * Make INSERT list from array values (array[key1], array[key2], array[key3])
     * (array[key1],array[key2],array[key3]) VALUES ('array[key1]','array[key2]','array[key3]')
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_insert_list_from_values($arr, $quoted = true, $bind = false)
    {
        $columns = self::sql_make_list_from_values($arr, false);
        $values = self::sql_make_list_from_values($arr, $quoted, $bind ? ':' : '');
        return "($columns) VALUES ($values)";
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
        $str = "SET ";
        foreach ($arr as $col => $type) {
            $str .= "$col = " . self::sql_quote($type) . ",";
        }
        return rtrim($str, ",");
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
            $where = "WHERE $column $in ($q_values)";
        } else {
            $eq = $not ? "!=" : "=";
            $where = "WHERE $column $eq" . Sql_Wrapper::sql_quote($values);
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
        return $this->db->prepare($query);
    }

    /**
     * Prepare bind based on array of columns
     *
     * @param string $table
     * @param array $columns
     * @return SQLite3Stmt
     */
    public function prepare_bind($action, $table, $columns)
    {
        $insert = self::sql_make_insert_list_from_values($columns, false, true);
        $query = "$action INTO $table $insert;";
        hd_debug_print($query);
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
        $result = $this->db->querySingle($query, $full_row);
        if ($result === false) {
            hd_debug_print();
            hd_debug_print("failed to execute query: $query");
        }
        return $result;
    }

    /**
     * Fetch array of values
     * fetch returns array of rows['column'] it will convert to simple array() of values row['column']
     *
     * @param string $query
     * @param string $column
     * @return array
     */
    public function fetch_single_array($query, $column)
    {
        $rows = array();
        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row[$column];
            }
        } else {
            hd_debug_print();
            hd_debug_print("failed to execute query: $query");
        }

        return $rows;
    }

    /**
     * Fetch array of rows that contains array of columns
     *
     * @param string $query
     * @return array
     */
    public function fetch_array($query)
    {
        $rows = array();
        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }
        } else {
            hd_debug_print();
            hd_debug_print("failed to execute query: $query");
        }

        return $rows;
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
        if (!empty($query)) {
            $query = "BEGIN;" . $query . "COMMIT;" ;
            if (!$this->db->exec($query)) {
                hd_debug_print();
                hd_debug_print("Error commit transaction!");
                hd_debug_print($query);
                $this->db->exec("ROLLBACK;");
                return false;
            }
        }

        return true;
    }
}