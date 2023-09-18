<?php
require_once "cgi_config.php";

require_once 'lib/epg_manager.php';

echoLog("Script start");

epg_config::load();
$start = microtime(true);
$epg_man = new Epg_Manager();
$epg_man->init_cache_dir(epg_config::$cache_dir, epg_config::$cache_ttl);
$epg_man->set_xmltv_url(epg_config::$xmltv_url);
$epg_man->index_xmltv_program();

echoLog("Script execution time: ". format_duration_seconds(round(microtime(true) - $start)));
echoLog("Script stop");
