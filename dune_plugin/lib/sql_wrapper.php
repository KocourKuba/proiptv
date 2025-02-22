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
     * Make insert list (key1, key2, key3) VALUES ('array[key1]', 'array[key2]', 'array[key3]') from array
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_list_from_values($arr)
    {
        return implode(', ', $arr);
    }

    /**
     * Make list ('array[key1]', 'array[key2]', 'array[key3]') from array values (array[key1], array[key2], array[key3])
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_insert_list($arr)
    {
        $columns = self::sql_make_list_from_keys($arr);
        $values = self::sql_make_list_from_quoted_values($arr);
        return "($columns) VALUES ($values)";
    }

    /**
     * Make quoted list from array values (array[key1], array[key2], array[key3] -> 'array[key1]', 'array[key2]', 'array[key3]')
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_list_from_quoted_values($arr)
    {
        $quoted_array = array_map(function($var) {
            return "'" . SQLite3::escapeString($var) . "'";
        }, $arr);

        return implode(',', $quoted_array);
    }

    /**
     * Make bind list from array values (array[key1], array[key2], array[key3] -> :array[key1], :array[key2], :array[key3])
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_bind_list_from_values($arr)
    {
        return ":" . implode(', :', $arr);
    }

    /**
     * Make list from array keys (array[key1], array[key2], array[key3] -> (key1,key2,key3)
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_list_from_keys($arr)
    {
        return self::sql_make_list_from_values(array_keys($arr));
    }

    /**
     * Make bind list from array keys (array[key1], array[key2], array[key3] -> :key1, :key2, :key3)
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_bind_list_from_keys($arr)
    {
        return self::sql_make_bind_list_from_values(array_keys($arr));
    }

    /**
     * Make quotted list from array keys (array[key1], array[key2], array[key3] -> 'key1', 'key2', 'key3')
     *
     * @param array $arr
     * @return string
     */
    public static function sql_make_list_from_quoted_keys($arr)
    {
        return self::sql_make_list_from_quoted_values(array_keys($arr));
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
            $q_values = Sql_Wrapper::sql_make_list_from_quoted_values($values);
            $where = "WHERE $column $in ($q_values)";
        } else {
            $eq = $not ? "!=" : "=";
            $where = "WHERE $column $eq" . Sql_Wrapper::sql_quote($values);
        }

        return $where;
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
        $query = "$action INTO $table (" . self::sql_make_list_from_values($columns) . ") VALUES (" . self::sql_make_bind_list_from_values($columns) . ");";
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