<?php

class Sql_Wrapper
{
    /**
     * @var SQLite3
    */
    protected $db = null;

    public function __construct($db)
    {
        $this->init($db);
    }

    /**
     * @param SQLite3|null $db
     * @return void
     */
    public function init($db)
    {
        $this->db = $db;
    }

    public function valid()
    {
        return !is_null($this->db);
    }

    public function reset()
    {
        $this->db = null;
    }

    public function get_db()
    {
        return $this->db;
    }

    /**
     * @param string $var
     * @return string
     */
    public static function sql_quote($var)
    {
        return "'" . SQLite3::escapeString($var) . "'";
    }

    /**
     * @param array $arr
     * @return array
     */
    public static function sql_quote_array($arr)
    {
        return array_map(function($var) {
            return "'" . SQLite3::escapeString($var) . "'";
        }, $arr);
    }

    /**
     * @param array $arr
     * @return string
     */
    public static function sql_collect_params($arr)
    {
        return implode(',', $arr);
    }

    /**
     * @param array $arr
     * @return string
     */
    public static function sql_collect_bind_values($arr)
    {
        return ":" . implode(',:', $arr);
    }

    /**
     * @param array $arr
     * @return string
     */
    public static function sql_collect_values($arr)
    {
        return implode(',', self::sql_quote_array($arr));
    }

    /**
     * @param array $arr
     * @return string
     */
    public static function sql_collect_keys($arr)
    {
        return implode(',', self::sql_quote_array(array_keys($arr)));
    }

    /**
     * @param string $query
     * @return bool
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
     * @param string $query
     * @return SQLite3Stmt
     */
    public function prepare($query)
    {
        return $this->db->prepare($query);
    }

    /**
     * @param string $table
     * @param array $columns
     * @return SQLite3Stmt
     */
    public function prepare_bind($action, $table, $columns)
    {
        $query = "$action INTO $table (" . self::sql_collect_params($columns) . ") VALUES (" . self::sql_collect_bind_values($columns) . ");";
        $result = $this->db->prepare($query);
        if ($result === false) {
            hd_debug_print();
            hd_debug_print("failed to prepare statement: $query");
        }

        return $result;
    }

    /**
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