<?php
$plugin_info = 'dune_plugin.xml';
$release_date = date('Y.m.d');
$version = $argv[1];
$version_index = date('YmdH');

$xml = file_get_contents("dune_plugin/$plugin_info");
$xml = preg_replace("|<version>(.*)</version>|", "<version>$version</version>", $xml);
$xml = preg_replace("|<release_date>(.*)</release_date>|", "<release_date>$release_date</release_date>", $xml);
$xml = preg_replace("|<version_index>(.*)</version_index>|", "<version_index>$version_index</version_index>", $xml);
echo "version: $version" . PHP_EOL;
echo "version index: $version_index" . PHP_EOL;
echo "update date $release_date" . PHP_EOL;
file_put_contents("dune_plugin/$plugin_info", $xml);
