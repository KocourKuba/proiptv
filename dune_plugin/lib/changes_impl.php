<?php

class Changes_Impl
{

    protected $plugin;

    /**
     * @var bool[]
     */
    private $has_changes = array();

    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param string $save_data
     * @return bool
     */
    protected function set_changes($save_data = PLUGIN_ORDERS)
    {
        $old = isset($this->has_changes[$save_data]) && $this->has_changes[$save_data];
        $this->has_changes[$save_data] = true;
        $this->plugin->set_dirty(true, $save_data);
        return $old;
    }

    /**
     * @param string $save_data
     * @return bool
     */
    protected function set_no_changes($save_data = PLUGIN_ORDERS)
    {
        $old = isset($this->has_changes[$save_data]) && $this->has_changes[$save_data];
        $this->has_changes[$save_data] = false;
        return $old;
    }

    protected function has_changes()
    {
        foreach ($this->has_changes as $change) {
            if ($change) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function save_if_changed()
    {
        $saved = false;
        foreach ($this->has_changes as $key => $value) {
            if (!$value) continue;

            switch ($key) {
                case PLUGIN_PARAMETERS:
                    $saved = $saved || $this->plugin->save_parameters();
                    $this->set_no_changes($key);
                    break;

                case PLUGIN_SETTINGS:
                    $saved = $saved || $this->plugin->save_settings();
                    $this->set_no_changes($key);
                    break;

                case PLUGIN_ORDERS:
                    $saved = $saved || $this->plugin->save_orders();
                    $this->set_no_changes($key);
                    break;

                case PLUGIN_HISTORY:
                    $saved = $saved || $this->plugin->save_history();
                    $this->set_no_changes($key);
                    break;
            }
        }

        return $saved;
    }
}
