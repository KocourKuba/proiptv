<?php
$plugin_info = 'dune_plugin.xml';
$plugin_metadata = 'dune_plugin_metadata.xml';
$update_info = 'update_proiptv.xml';
$update_tar = 'update_proiptv.tar';
$update_file = 'update_proiptv.tar.gz';
$release_date = date('Y.m.d');
list(, $version, $version_index, $is_debug) = $argv;
$full_version = "$version.$version_index";

$xml = file_get_contents("build/$plugin_info.tpl");
$xml = preg_replace("|<version>(.*)</version>|", "<version>$full_version</version>", $xml);
$xml = preg_replace("|<release_date>(.*)</release_date>|", "<release_date>$release_date</release_date>", $xml);
$xml = preg_replace("|<version_index>(.*)</version_index>|", "<version_index>$version_index</version_index>", $xml);
echo "version: $full_version" . PHP_EOL;
echo "version index: $version_index" . PHP_EOL;
echo "update date $release_date" . PHP_EOL;
file_put_contents("./dune_plugin/$plugin_info", $xml);

$xml = file_get_contents("build/$plugin_metadata.tpl");
$xml = preg_replace("|<version>(.*)</version>|", "<version>$full_version</version>", $xml);
$xml = preg_replace("|<version_index>(.*)</version_index>|", "<version_index>$version_index</version_index>", $xml);
file_put_contents("./dune_plugin/$plugin_metadata", $xml);

copy("./build/changelog.russian.md", "./dune_plugin/changelog.russian.md");
copy("./build/changelog.english.md", "./dune_plugin/changelog.english.md");

$providers = ($is_debug === 'debug') ? "providers_debug.json" : "providers_$version.json";
copy("./build/$providers", "./dune_plugin/$providers");

if (!$is_debug) {
    try
    {
        unlink($update_tar);
        unlink($update_file);
        $pd = new PharData($update_tar);
        $pd->buildFromDirectory("./dune_plugin");
        $pd->compress(Phar::GZ);
        unset($pd);
    } catch (Exception $ex) {
        echo "Exception : " . $ex;
    }

    unlink($update_tar);

    $hash = hash_file('md5', $update_file);
    echo "md5: $hash" . PHP_EOL;

    $update = simplexml_load_string(file_get_contents("./build/$update_info.tpl"));
    $update->plugin_version_descriptor->version = $full_version;
    $update->plugin_version_descriptor->version_index = $version_index;
    $update->plugin_version_descriptor->md5 = hash_file('md5', $update_file);
    $update->plugin_version_descriptor->size = filesize($update_file);
    $update->saveXML($update_info);

    $folder_path = "archive/{$full_version}_" . date('d-m_H-i-s');
    if (!file_exists($folder_path) && !@mkdir($folder_path) && !is_dir($folder_path)) {
        echo "Directory '$folder_path' was not created";
        return;
    }

    copy($update_file, "$folder_path/$update_file");
    copy($update_info, "$folder_path/$update_info");
}
