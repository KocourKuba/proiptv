<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
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

class dune_config
{
    /**
     * @var array
     */
    private $data;

    /**
     * @param string $conf_file_name
     */
    public function __construct($conf_file_name)
    {
        $this->data = array();

        $this->read_conf_file("/config/$conf_file_name") or
        $this->read_conf_file("/firmware/config/$conf_file_name");
    }

    /**
     * @param string $conf_file_path
     * @return bool
     */
    private function read_conf_file($conf_file_path)
    {
        hd_silence_warnings();
        $lines = file($conf_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        hd_restore_warnings();

        if ($lines === false) {
            hd_debug_print("Configuration file '$conf_file_path' does not exist.");
            return false;
        }

        hd_debug_print("Reading configuration from '$conf_file_path'...");

        foreach ($lines as $i => $iValue) {
            /** @var array $matches */
            if (preg_match('/^ *(\S+) *= *(\S+)$/', $iValue, $matches) !== 1) {
                hd_debug_print(
                    "Warning: line " . ($i + 1) . ": unknown format. " .
                    "Data: '" . $iValue . "'.");
                continue;
            }

            $this->data[$matches[1]] = $matches[2];
        }

        return true;
    }

    /**
     * @param string $key
     * @return string
     */
    public function __get($key)
    {
        return $this->data[$key];
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set_default($key, $value)
    {
        if (!isset($this->data[$key])) {
            hd_debug_print("Warning: no value for key '$key'. Using default: '$value'");
            $this->__set($key, $value);
        }
    }
}
