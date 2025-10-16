<?php
require_once 'dune_plugin_constants.php';

class Dune_Last_Error
{
    /**
     * @var array
     */
    protected static $last_error;

    public static function get_last_error($entity, $clear_after = true)
    {
        if ($entity === LAST_ERROR_XMLTV) {
            $error_file = get_temp_path($entity);
            $last_error = file_exists($error_file) ? file_get_contents($error_file) : '';
        } else {
            $last_error = self::$last_error[$entity];
        }

        if ($clear_after) {
            Dune_Last_Error::clear_last_error($entity);
        }

        return $last_error;
    }

    public static function set_last_error($entity, $value)
    {
        $value = trim($value);
        if ($entity === LAST_ERROR_XMLTV) {
            $error_file = get_temp_path($entity);
            if (empty($error) && file_exists($error_file)) {
                unlink($error_file);
            } else {
                file_put_contents($error_file, $value);
            }
        } else {
            self::$last_error[$entity] = $value;
        }
    }

    public static function clear_last_error($entity)
    {
        if ($entity === LAST_ERROR_XMLTV) {
            $error_file = get_temp_path($entity);
            if (file_exists($error_file)) {
                unlink($error_file);
            }
        } else {
            self::$last_error[$entity] = '';
        }
    }
}
