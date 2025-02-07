For local debugging of the plugin:

1. Install php 5.3.6 (https://windows.php.net/downloads/releases/archives/php-5.3.6-Win32-VC9-x86.zip)
2. If you plans to using php debugger
   1. Download and copy into <your php folder>\ext\php_xdebug-2.1.4-5.3-vc9.dll (https://xdebug.org/files/php_xdebug-2.1.4-5.2-vc9.dll)
   2. Update php.ini for using xdebug 
       zend_extension=<your php folder>\ext\php_xdebug-2.1.4-5.3-vc9.dll
3. Create test php file in the repository root. For example 'test.php'
4. In the 'test.php' file the add line:
    require_once "plugin_test_env.php";
5. To start debugging or run any function - add lines:
   LogSeverity::$is_debug = true; // to enable debug log output
   $plugin = new Starnet_Plugin(); // instantiate Plugin
   $plugin->init_plugin(true); // initialize plugin
   now you can call any function of the plugin.
   To get any Screens use:
   $plugin->get_screen(Starnet_Vod_Series_List_Screen::ID)
   after you can call any public function of selected screen. For example:
   $screen->get_all_folder_items(MediaURL::make(array('movie_id' => '6541', 'series_id' => '51243'));
   $plugin_cookies already created in plugin_test_env.php and you can safely pass it to the any function that requires $plugin_cookies
