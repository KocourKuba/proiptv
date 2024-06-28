<?php
require_once "cgi_config.php";

set_include_path(get_include_path(). PATH_SEPARATOR . DuneSystem::$properties['install_dir_path']);

require_once 'lib/epg/epg_manager_xmltv.php';

$epg_manager = new Epg_Manager_Xmltv();
if ($epg_manager->init_by_config()) {
    $epg_manager->index_all();
}
