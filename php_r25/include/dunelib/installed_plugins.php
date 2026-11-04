<?php

require_once 'dunelib/utils.php';

class InstalledPlugins
{
    const INSTALLED_PLUGINS_FILE = "/tmp/run/installed_plugins.xml";

    private static $plugins = null;
    private static $mtime = -2;

    public static function get_by_name($plugins, $name)
    {
        foreach ($plugins as $p) {
            if ($p->name == $name)
                return $p;
        }
        return null;
    }

    public static function reload()
    {
        self::$plugins = null;
        return self::ensure_loaded();
    }

    public static function ensure_loaded()
    {
        $mtime = HD::filemtime(self::INSTALLED_PLUGINS_FILE);

        $reload_needed = !isset(self::$plugins) || $mtime != self::$mtime;

        if ($reload_needed)
        {
            if (self::$plugins) {
                hd_print("D: (installed_plugins) mtime changed ".
                    self::$mtime . " => $mtime");
            }
            self::$mtime = $mtime;
            self::do_load();
        }
        return self::$plugins;
    }

    private static function do_load()
    {
        self::$plugins = array();
        if (self::$mtime > 0) {
            $doc = file_get_contents(self::INSTALLED_PLUGINS_FILE);
            if ($doc === false)
                return;
            $xml = simplexml_load_string($doc);
            if ($xml === false)
                return;
            self::$plugins = $xml->children();
        }
    }
}

?>
