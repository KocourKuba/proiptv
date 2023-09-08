<?php

class Base_Epfs_Handler
{
	const EPFS_PATH = '/flashdata/plugins_epfs/';

    protected static $dir_path;

	///////////////////////////////////////////////////////////////////////

    /**
     * @param $plugin_name
     * @return void
     */
    public static function initialize($plugin_name)
    {
        self::$dir_path = self::EPFS_PATH . $plugin_name;
    }

    /**
     * @param string $path
     * @param string $data
     * @return void
     */
    private static function do_write_epf_data($path, $data)
	{
        $tmp_path = "$path.tmp";
		//hd_debug_print("Write epf for $epf_id to $path (" . strlen($data) . ' bytes)');

        if (false === file_put_contents($tmp_path, $data)) {
            hd_debug_print("Failed to write tmp file: $tmp_path", LOG_LEVEL_ERROR);
        } else if (!rename($tmp_path, $path)) {
            hd_debug_print("Failed to rename $tmp_path to $path", LOG_LEVEL_ERROR);
            unlink($tmp_path);
        }
	}

	////////////////////////////////////////////////////////////////////////////

    /**
     * @param string $epf_id
     * @return object|null
     */
    protected static function read_epf_data($epf_id)
    {
        return file_exists($path = self::get_epf_path($epf_id)) ? HD::ReadContentFromFile($path, false) : null;
    }

    /**
     * @param string $epf_id
     * @param array|object $folder_view
     * @return void
     */
	protected static function write_epf_view($epf_id, $folder_view)
	{
		if ($folder_view) {
            self::do_write_epf_data(self::get_epf_path($epf_id), json_encode($folder_view));
        }
	}

    /**
     * @param string $epf_id
     * @param array|object $folder_view
     * @return bool
     */
    protected static function is_folder_view_changed($epf_id, $folder_view)
    {
        return (json_encode($folder_view) !== self::read_epf_data($epf_id));
    }

    protected static function get_ilang_path()
	{
		return self::$dir_path . '/ilang';
	}

	protected static function get_epfs_ts_path($id)
	{
		return self::$dir_path . "/${id}_timestamp";
	}

	protected static function read_epfs_ts($id)
	{
		return is_file($path = self::get_epfs_ts_path($id)) ? file_get_contents($path) : '';
	}

    /**
     * @param string $epf_id
     * @return string
     */
    protected static function get_epf_path($epf_id)
	{
		return self::$dir_path . "/$epf_id.json";
	}

	////////////////////////////////////////////////////////////////////////////

	public static function warmed_up_path()
	{
		return get_temp_path('epfs_warmed_up');
	}

    public static function async_worker_warmed_up_path()
    {
        return get_temp_path('/async_worker_warmed_up');
    }

    public static function get_epfs_changed_path()
	{
		return get_temp_path('update_epfs_if_needed_flag');
	}
}
