<?php
class ExtendedZip extends ZipArchive {

    // Member function to add a whole file system subtree to the archive
    public function addTree($dirname, $local_name = '') {
        if ($local_name) {
            $this->addEmptyDir($local_name);
        }
        $this->_addTree($dirname, $local_name);
    }

    // Internal function, to recurse
    protected function _addTree($dirname, $local_name) {
        $dir = opendir($dirname);
        while ($filename = readdir($dir)) {
            // Discard . and ..
            if ($filename === '.' || $filename === '..')
                continue;

            // Proceed according to type
            $path = $dirname . '/' . $filename;
            $local_path = $local_name ? ($local_name . '/' . $filename) : $filename;
            if (is_dir($path)) {
                // Directory: add & recurse
                $this->addEmptyDir($local_path);
                $this->_addTree($path, $local_path);
            }
            else if (is_file($path)) {
                // File: just add
                $this->addFile($path, $local_path);
            }
        }
        closedir($dir);
    }

    // Helper function
    public static function zipTree($dirname, $zipFilename, $flags = 0, $local_name = '') {
        $zip = new self();
        $zip->open($zipFilename, $flags);
        $zip->addTree($dirname, $local_name);
        $zip->close();
    }
}

$plugin_info = 'dune_plugin.xml';
$update_info = 'update_proiptv.xml';
$packed_plugin = 'dune_plugin_proiptv.zip';
$update_tar = 'update_proiptv.tar';
$update_file = 'update_proiptv.tar.gz';
$release_date = date('Y.m.d');
$version = $argv[1].$argv[2];
$version_index = $argv[2];

$xml = file_get_contents("$plugin_info.tpl");
$xml = preg_replace("|<version>(.*)</version>|", "<version>$version</version>", $xml);
$xml = preg_replace("|<release_date>(.*)</release_date>|", "<release_date>$release_date</release_date>", $xml);
$xml = preg_replace("|<version_index>(.*)</version_index>|", "<version_index>$version_index</version_index>", $xml);
echo "version: $version" . PHP_EOL;
echo "version index: $version_index" . PHP_EOL;
echo "update date $release_date" . PHP_EOL;
file_put_contents("dune_plugin/$plugin_info", $xml);

ExtendedZip::zipTree('./dune_plugin', $packed_plugin, ZipArchive::CREATE);

try
{
    unlink($update_tar);
    unlink($update_file);
    $pd = new PharData($update_tar);
    $pd->buildFromDirectory("./dune_plugin");
    $pd->compress(Phar::GZ);
    unset($pd);
} catch (Exception $e) {
    echo "Exception : " . $e;
}

unlink($update_tar);
$hash = hash('md5', file_get_contents($update_file));
echo "md5: $hash" . PHP_EOL;

$update = simplexml_load_string(file_get_contents("$update_info.tpl"));
$update->plugin_version_descriptor->version = $version;
$update->plugin_version_descriptor->version_index = $version_index;
$update->plugin_version_descriptor->md5 = hash('md5', file_get_contents($update_file));
$update->plugin_version_descriptor->size = filesize($update_file);
$update->saveXML($update_info);
