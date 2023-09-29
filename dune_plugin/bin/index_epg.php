<?php
require_once "cgi_config.php";

set_include_path(get_include_path(). PATH_SEPARATOR . DuneSystem::$properties['install_dir_path']);

global $LOG_FILE;

require_once 'lib/ordered_array.php';
require_once 'lib/hashed_array.php';
require_once 'lib/hd.php';
require_once 'lib/epg_manager_sql.php';

hd_print("Script start: index_epg");
hd_print("Version: " . DuneSystem::$properties['plugin_version']);
hd_print("Log: $LOG_FILE");

$parameters = HD::get_data_items('common.settings', true, false);

if (!isset($parameters[PARAM_PLAYLISTS])) {
    HD::set_last_error("No playlist defined!");
    return;
}

$debug = isset($parameters[PARAM_ENABLE_DEBUG]) ? $parameters[PARAM_ENABLE_DEBUG] : SetupControlSwitchDefs::switch_off;
$cache_dir = isset($parameters[PARAM_CACHE_PATH]) ? $parameters[PARAM_CACHE_PATH] : get_data_path(EPG_CACHE_SUBDIR);
set_debug_log($debug === SetupControlSwitchDefs::switch_on);

if (class_exists('SQLite3')) {
    $cache_engine = isset($parameters[PARAM_EPG_CACHE_ENGINE]) ? $parameters[PARAM_EPG_CACHE_ENGINE] : ENGINE_SQLITE;
} else {
    $cache_engine = ENGINE_LEGACY;
}

/** @var Ordered_Array $playlists */
$playlists = $parameters[PARAM_PLAYLISTS];
$name = hash('crc32', $playlists->get_selected_item()) . '.settings';
if (!file_exists(get_data_path($name))) {
    HD::set_last_error("No settings for playlist!");
    return;
}

$settings = HD::get_data_items($name, true, false);
$cache_ttl = isset($settings[PARAM_EPG_CACHE_TTL]) ? $settings[PARAM_EPG_CACHE_TTL] : 3;
$xmltv_url = isset($settings[PARAM_CUR_XMLTV_SOURCE]) ? $settings[PARAM_CUR_XMLTV_SOURCE] : '';

if ($cache_engine === ENGINE_SQLITE) {
    hd_debug_print("Using sqlite cache engine");
    $epg_man = new Epg_Manager_Sql(DuneSystem::$properties['plugin_version'], $cache_dir, $xmltv_url);
} else {
    hd_debug_print("Using legacy cache engine");
    $epg_man = new Epg_Manager(DuneSystem::$properties['plugin_version'], $cache_dir, $xmltv_url);
}

$epg_man->set_cache_ttl($cache_ttl);

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
