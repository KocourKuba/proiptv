<?php
$update_template = 'update_proiptv.tpl.xml';
$plugin_info = 'dune_plugin.xml';
$update_info = 'update_proiptv.xml';
$update_file = 'update_proiptv.tar.gz';
$hash = hash('md5', file_get_contents($update_file));
echo "hash of $update_file: $hash" . PHP_EOL;

$plugin = simplexml_load_string(file_get_contents("dune_plugin/$plugin_info"));
$plugin->release_date = date('Y.m.d');
$plugin->version_index = date('YmdHi');
echo "version: $plugin->version" . PHP_EOL;
echo "update date $plugin->release_date" . PHP_EOL;
echo "version index: $plugin->version_index" . PHP_EOL;

$plugin->saveXML("dune_plugin/$plugin_info");

$update = simplexml_load_string(file_get_contents($update_template));
$update->plugin_version_descriptor->version = $plugin->version;
$update->plugin_version_descriptor->version_index = $plugin->version_index;
$update->plugin_version_descriptor->md5 = hash('md5', file_get_contents($update_file));
$update->plugin_version_descriptor->size = filesize($update_file);
$update->saveXML($update_info);

