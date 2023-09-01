<?php
////////////////////////////////////////////////////////////////////////////////

require_once "starnet_tv_rows_screen.php";

require_once 'lib/dune_stb_api.php';
require_once "lib/epfs/config.php";
require_once "lib/epfs/base_epfs_handler.php";
require_once "lib/epfs/dummy_epfs_screen.php";

////////////////////////////////////////////////////////////////////////////////

class Starnet_Epfs_Handler extends Base_Epfs_Handler
{
    /**
     * @var bool
     */
    protected static $enabled;

    /**
     * @var string
     */
    protected static $epf_id;

    /**
     * @var string
     */
    protected static $no_internet_epfs;

    /**
     * @var Starnet_Tv_Rows_Screen
     */
    protected static $tv_rows_screen;

    /**
     * @var Dummy_Epfs_Screen
     */
    protected static $dummy_epf_screen;

    protected static $no_internet_epfs_created = false;

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param Default_Dune_Plugin $plugin
     * @throws Exception
     */
    public static function init(Default_Dune_Plugin $plugin)
    {
        self::$enabled = $plugin->new_ui_support;
        if (!self::$enabled)
            return;

        self::$epf_id = $plugin->plugin_info['app_name'];
        self::$no_internet_epfs = self::$epf_id . '.no_internet';

        hd_debug_print("epf_id: " . self::$epf_id);
        parent::initialize(self::$epf_id);

        self::$tv_rows_screen = new Starnet_Tv_Rows_Screen($plugin);
        $plugin->create_screen(self::$tv_rows_screen);

        self::$dummy_epf_screen = new Dummy_Epfs_Screen($plugin);
        $plugin->create_screen(self::$dummy_epf_screen);
    }

    /**
     * @param bool $first_run
     * @param $plugin_cookies
     */
    private static function ensure_no_internet_epfs_created($first_run, &$plugin_cookies)
    {
        if (!self::$enabled || self::$no_internet_epfs_created)
            return;

        if ($first_run || !is_file(self::get_epf_path(self::$no_internet_epfs))) {
            self::write_epf_view(self::$no_internet_epfs, self::$dummy_epf_screen->get_folder_view_for_epf(true, $plugin_cookies));
        }

        self::$no_internet_epfs_created = true;
    }

    /**
     * @return object|null
     */
    public static function get_epf()
    {
        return self::$enabled ? self::read_epf_data(self::$epf_id) : null;
    }

    /**
     * @return void
     */
    public static function need_update_epf_mapping()
    {
        if (self::$enabled && !empty(self::$tv_rows_screen))
            self::$tv_rows_screen->need_update_epf_mapping_flag = true;
    }

    /**
     * @param array|null $media_urls
     * @param $post_action
     * @return array
     */
    public static function invalidate_folders($media_urls = null, $post_action = null)
    {
        if (!self::$enabled)
            return $post_action;

        $arr = array_merge(array(self::$epf_id), (is_array($media_urls) ? $media_urls : array()));
        return Action_Factory::invalidate_folders($arr, $post_action);
    }

    /**
     * @param $first_run
     * @param $plugin_cookies
     * @return array|null
     */
    public static function update_all_epfs(&$plugin_cookies, $first_run = false)
    {
        if (!self::$enabled)
            return null;

        if ($first_run)
            hd_debug_print("first run");

        self::ensure_no_internet_epfs_created($first_run, $plugin_cookies);

        try {
            $folder_view = self::$tv_rows_screen->get_folder_view_for_epf($plugin_cookies);
        } catch (Exception $e) {
            hd_debug_print("Exception while generating epf: " . $e->getMessage());
            return null;
        }

        $cold_run = !is_file(self::warmed_up_path());
        if ($cold_run) {
            hd_debug_print("Cold run");
            file_put_contents(self::warmed_up_path(), '');
        }

        if (self::is_folder_view_changed(self::$epf_id, $folder_view)) {
            self::write_epf_view(self::$epf_id, $folder_view);
        }

        return Action_Factory::status(0);
    }
}
