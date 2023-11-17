<?php

class info_default
{
    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function GetInfoUI($handler)
    {
        return null;
    }

    /**
     * @param array $data
     * @param array|null $ignored
     * @param int $deep
     * @return string
     */
    protected function collect_account_items($data, $ignored, $deep = 0)
    {
        $text = '';
        foreach ($data as $key => $value) {
            if (!is_null($ignored) && in_array($key, $ignored)) continue;

            if (is_array($value)) {
                hd_debug_print("level: $deep, key: $key data: " . raw_json_encode($value));
                if ($deep && !is_assoc_array($value)) {
                    $t = mapped_implode(',', $value, ': ', $ignored);
                    $text .= "$key: $t\n";
                } else {
                    if (is_assoc_array($data)) {
                        $text .= "-------- $key --------\n";
                    }
                    $text .= $this->collect_account_items($value, $ignored, $deep + 1);
                }
            } else {
                if (is_bool($value)) {
                    $value = var_export($value, true);
                }
                $text .= "$key: $value\n";
            }
        }

        return $text;
    }
}
