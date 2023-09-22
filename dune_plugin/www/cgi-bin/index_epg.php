<?php
require_once "cgi_config.php";

require_once 'lib/epg_manager_sql.php';

hd_print("Script start");

epg_config::load();
$start = microtime(true);
if (epg_config::$cache_engine === ENGINE_SQLITE) {
    hd_print("Using sqlite cache engine");
    $epg_man = new Epg_Manager_Sql();
} else {
    hd_print("Using legacy cache engine");
    $epg_man = new Epg_Manager();
}

$epg_man->init_cache_dir(epg_config::$cache_dir, epg_config::$cache_ttl);
$epg_man->set_xmltv_url(epg_config::$xmltv_url);
$epg_man->index_xmltv_program();

hd_print("Script execution time: ". format_duration_seconds(round(microtime(true) - $start)));
