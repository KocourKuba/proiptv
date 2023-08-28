<?php
$update_template = 'update_proiptv.tpl.xml';
$plugin_info = 'dune_plugin.xml';
$update_info = 'update_proiptv.xml';
$update_tar = 'update_proiptv.tar';
$update_file = 'update_proiptv.tar.gz';
$release_date = date('Y.m.d');
$version = $argv[1];
$version_index = date('ymdHi');

$xml = file_get_contents("dune_plugin/$plugin_info");
$xml = preg_replace("|<version>(.*)</version>|", "<version>$version</version>", $xml);
$xml = preg_replace("|<release_date>(.*)</release_date>|", "<release_date>$release_date</release_date>", $xml);
$xml = preg_replace("|<version_index>(.*)</version_index>|", "<version_index>$version_index</version_index>", $xml);
echo "version: $version" . PHP_EOL;
echo "version index: $version_index" . PHP_EOL;
echo "update date $release_date" . PHP_EOL;
file_put_contents("dune_plugin/$plugin_info", $xml);

try
{
    unlink($update_tar);
    unlink($update_file);
    $pd = new PharData($update_tar);
    $pd->buildFromDirectory("./dune_plugin");
    $pd->compress(Phar::GZ);
} catch (Exception $e) {
    echo "Exception : " . $e;
}

unlink($update_tar);
$hash = hash('md5', file_get_contents($update_file));
echo "hash of $update_file: $hash" . PHP_EOL;
$update = simplexml_load_string(file_get_contents($update_template));
$update->plugin_version_descriptor->version = $version;
$update->plugin_version_descriptor->version_index = $version_index;
$update->plugin_version_descriptor->md5 = hash('md5', file_get_contents($update_file));
$update->plugin_version_descriptor->size = filesize($update_file);
$update->saveXML($update_info);
