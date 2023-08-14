<?php
///////////////////////////////////////////////////////////////////////////

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
            hd_print(__METHOD__ . ": Configuration file '$conf_file_path' does not exist.");
            return false;
        }

        hd_print(__METHOD__ . ": Reading configuration from '$conf_file_path'...");

        foreach ($lines as $i => $iValue) {
            if (preg_match('/^ *(\S+) *= *(\S+)$/', $iValue, $matches) !== 1) {
                hd_print(
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
            hd_print(__METHOD__ . ": Warning: no value for key '$key'. Using default: '$value'");
            $this->__set($key, $value);
        }
    }
}
