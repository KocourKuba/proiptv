<?php
require_once "cgi_config.php";

set_include_path(get_include_path(). PATH_SEPARATOR . DuneSystem::$properties['install_dir_path']);

require_once "lib/hd.php";
require_once 'lib/epg_manager_sql.php';

global $LOG_FILE;

$config_file = get_temp_path('parse_config.json');
if (!file_exists($config_file)) {
    HD::set_last_error("Config file for indexing not exist");
    return;
}

$config = json_decode(file_get_contents($config_file));
if ($config === false) {
    HD::set_last_error("Invalid config file for indexing");
    @unlink($config_file);
    return;
}

$LOG_FILE = $config->log_file;
if (!empty($LOG_FILE)) {
    if (file_exists($LOG_FILE)) {
        @unlink($LOG_FILE);
    }
    date_default_timezone_set('UTC');
}

hd_print("Script start: index_epg");
hd_print("Version: $config->version");
hd_print("XMLTV source: $config->xmltv_url");
hd_print("Engine: $config->cache_engine");
hd_print("Cache TTL: $config->cache_ttl");
hd_print("Log: $LOG_FILE");

set_debug_log($config->debug);

if ($config->cache_engine === ENGINE_SQLITE) {
    $epg_man = new Epg_Manager_Sql($config->version, $config->cache_dir, $config->xmltv_url);
} else {
    $epg_man = new Epg_Manager($config->version, $config->cache_dir, $config->xmltv_url);
}

$epg_man->set_cache_ttl($config->cache_ttl);

$start = microtime(true);
$res = $epg_man->is_xmltv_cache_valid();
if ($res === -1) {
    hd_debug_print("Error load xmltv");
    return;
}

if ($res === 0) {
    hd_debug_print("XMLTV source not downloaded, nothing to parse");
    return;
}

$epg_man->index_xmltv_positions();

hd_print("Script execution time: ". format_duration(round(1000 * (microtime(true) - $start))));
