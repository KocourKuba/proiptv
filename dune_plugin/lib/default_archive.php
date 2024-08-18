<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code: Brigadir (forum.mydune.ru)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

require_once 'archive_cache.php';

class Default_Archive implements Archive
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $url_prefix;

    /**
     * @var string
     */
    protected $version_by_name;

    /**
     * @var int
     */
    protected $total_size;

    /**
     * @param string $id
     * @param string $url_prefix
     * @param array $version_by_name
     * @param int $total_size
     */
    public function __construct($id, $url_prefix, $version_by_name, $total_size)
    {
        $this->id = $id;
        $this->url_prefix = $url_prefix;
        $this->version_by_name = $version_by_name;
        $this->total_size = $total_size;
    }

    /**
     * @return void
     */
    public static function clear_cache()
    {
        Archive_Cache::clear_all();
    }

    /**
     * @param string $id
     * @return void
     */
    public static function clear_cached_image_archive($id)
    {
        Archive_Cache::clear_archive($id);
    }

    /**
     * @param string $id
     * @return Archive|null
     */
    public static function get_cached_image_archive($id)
    {
        return Archive_Cache::get_archive_by_id($id);
    }

    /**
     * @param string $id
     * @param string $url_prefix
     * @return Archive|null
     */
    public static function get_image_archive($id, $url_prefix)
    {
        $archive = Archive_Cache::get_archive_by_id($id);
        if (!is_null($archive)) {
            return $archive;
        }

        $version_url = "$url_prefix/versions.txt";
        $version_by_name = array();
        $total_size = 0;

        $doc = Curl_Wrapper::simple_download_content($version_url);
        if ($doc === false) {
            hd_debug_print("Failed to fetch archive versions.txt from $version_url.");
        } else {
            while (($tok = strtok($doc, "\n")) !== false) {
                $pos = strrpos($tok, ' ');
                if ($pos === false) {
                    hd_debug_print("Invalid line in versions.txt for archive '$id'.");
                    continue;
                }

                $name = trim(substr($tok, 0, $pos));
                $version = trim(substr($tok, $pos + 1));
                $version_by_name[$name] = $version;
            }

            hd_debug_print("Archive $id: " . count($version_by_name) . " files.");

            $size_url = "$url_prefix/size.txt";
            $doc = Curl_Wrapper::simple_download_content($size_url);
            if ($doc === false) {
                hd_debug_print("Failed to fetch archive size.txt from $size_url.");
                $version_by_name = array();
            } else {
                $total_size = (int)$doc;
                hd_debug_print("Archive $id: size = $total_size");
            }
        }

        $archive = new Default_Archive($id, $url_prefix, $version_by_name, $total_size);

        Archive_Cache::set_archive($archive);

        return $archive;
    }

    /**
     * @inheritDoc
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function get_image_archive_def()
    {
        $urls_with_keys = array();
        foreach ($this->version_by_name as $name => $version) {
            $pos = strrpos($name, ".");
            if ($pos === false)
                $key = "$name.$version";
            else {
                $key = substr($name, 0, $pos) . '.' . $version . substr($name, $pos);
            }

            $urls_with_keys[$key] = "$this->url_prefix/$name";
        }

        return array(
            PluginArchiveDef::id => $this->id,
            PluginArchiveDef::urls_with_keys => $urls_with_keys,
            PluginArchiveDef::all_tgz_url => "$this->url_prefix/all.tgz",
            PluginArchiveDef::total_size => $this->total_size,
        );
    }

    /**
     * @param string $name
     * @return string
     */
    public function get_archive_url($name)
    {
        if (!isset($this->version_by_name[$name])) {
            return "missing://";
        }

        $version = $this->version_by_name[$name];

        $pos = strrpos($name, ".");
        if ($pos === false) {
            $key = "$name.$version";
        } else {
            $key = substr($name, 0, $pos) . '.' . $version . substr($name, $pos);
        }

        return "plugin_archive://$this->id/$key";
    }
}
