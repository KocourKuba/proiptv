<?php
require_once "cgi_config.php";

require_once 'lib/epg_manager_sql.php';

hd_print("Script start");

epg_config::load();
$start = microtime(true);
if (class_exists('SQLite3')) {
    hd_print("indexing use sqlite engine");
    $epg_man = new Epg_Manager_Sql();
} else {
    hd_print("indexing use classic engine");
    $epg_man = new Epg_Manager();
}
$epg_man->init_cache_dir(epg_config::$cache_dir, epg_config::$cache_ttl);
$epg_man->set_xmltv_url(epg_config::$xmltv_url);
$epg_man->index_xmltv_program();

hd_print("Script execution time: ". format_duration_seconds(round(microtime(true) - $start)));
