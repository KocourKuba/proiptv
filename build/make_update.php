<?php
/**
 * Builds the plugin update package.
 * Requires PHP 8.x. Usage: php -f build/make_update.php <version> <version_index> [debug]
 */

// build stamps must follow the workstation clock, php.ini may default to another zone
date_default_timezone_set(getenv('BUILD_TZ') ?: 'Europe/Moscow');

$plugin_info = 'dune_plugin.xml';
$update_info = 'update_proiptv.xml';
$update_tar = 'update_proiptv.tar';
$update_file = 'update_proiptv.tar.gz';
$release_date = date('Y.m.d');

$version = isset($argv[1]) ? $argv[1] : '';
$version_index = isset($argv[2]) ? $argv[2] : '';
$is_debug = (isset($argv[3]) && $argv[3] === 'debug');
$full_version = "$version.$version_index";

if ($version === '' || $version_index === '') {
    fail("usage: php -f build/make_update.php <version> <version_index> [debug]");
}

function fail($message)
{
    fwrite(STDERR, "ERROR: $message" . PHP_EOL);
    exit(1);
}

function copy_or_fail($src, $dst)
{
    if (!file_exists($src)) {
        fail("required file not found: $src");
    }

    if (!copy($src, $dst)) {
        fail("can't copy $src to $dst");
    }
}

$tpl = "build/$plugin_info.tpl";
if (!file_exists($tpl)) {
    fail("required file not found: $tpl");
}

$xml = file_get_contents($tpl);
$xml = preg_replace("|<version>(.*)</version>|", "<version>$full_version</version>", $xml);
$xml = preg_replace("|<release_date>(.*)</release_date>|", "<release_date>$release_date</release_date>", $xml);
$xml = preg_replace("|<version_index>(.*)</version_index>|", "<version_index>$version_index</version_index>", $xml);
if ($is_debug) {
    $xml = preg_replace("|<debug>(.*)</debug>|", "<debug>true</debug>", $xml);
}

echo "version: $full_version" . PHP_EOL;
echo "version index: $version_index" . PHP_EOL;
echo "update date: $release_date" . PHP_EOL;
echo "is debug: " . ($is_debug ? 'yes' : 'no') . PHP_EOL;

if (file_put_contents("./dune_plugin/$plugin_info", $xml) === false) {
    fail("can't write ./dune_plugin/$plugin_info");
}

copy_or_fail("./build/changelog.russian.md", "./dune_plugin/changelog.russian.md");
copy_or_fail("./build/changelog.english.md", "./dune_plugin/changelog.english.md");

$providers = $is_debug ? "providers_debug.json" : "providers_$version.json";
copy_or_fail("./build/$providers", "./dune_plugin/$providers");

if ($is_debug) {
    return;
}

foreach (array($update_tar, $update_file) as $stale) {
    if (file_exists($stale) && !unlink($stale)) {
        fail("can't remove stale $stale");
    }
}

try {
    $pd = new PharData($update_tar);
    $pd->buildFromDirectory("./dune_plugin");
    $pd->compress(Phar::GZ);
    unset($pd);
} catch (Exception $ex) {
    fail("can't create $update_file : " . $ex->getMessage());
}

unlink($update_tar);

if (!file_exists($update_file)) {
    fail("$update_file was not created");
}

$hash = hash_file('md5', $update_file);
echo "md5: $hash" . PHP_EOL;

$update = simplexml_load_string(file_get_contents("./build/$update_info.tpl"));
if ($update === false) {
    fail("can't parse ./build/$update_info.tpl");
}

$update->plugin_version_descriptor->version = $full_version;
$update->plugin_version_descriptor->version_index = $version_index;
$update->plugin_version_descriptor->md5 = $hash;
$update->plugin_version_descriptor->size = filesize($update_file);
if ($update->saveXML($update_info) === false) {
    fail("can't write $update_info");
}

$folder_path = "archive/{$full_version}_" . date('d-m_H-i-s');
if (!is_dir($folder_path) && !@mkdir($folder_path, 0777, true) && !is_dir($folder_path)) {
    fail("directory '$folder_path' was not created");
}

copy_or_fail($update_file, "$folder_path/$update_file");
copy_or_fail($update_info, "$folder_path/$update_info");
