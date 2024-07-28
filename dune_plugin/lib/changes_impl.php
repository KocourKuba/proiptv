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
     * Set changed status to true
     * @param string $save_data
     * @return bool previous state
     */
    protected function set_changes($save_data = PLUGIN_ORDERS)
    {
        $old = isset($this->has_changes[$save_data]) && $this->has_changes[$save_data];
        $this->has_changes[$save_data] = true;
        $this->plugin->set_dirty(true, $save_data);
        return $old;
    }

    /**
     * Reset changes
     * @param string $save_data
     * @return bool previous state
     */
    protected function set_no_changes($save_data = PLUGIN_ORDERS)
    {
        $old = isset($this->has_changes[$save_data]) && $this->has_changes[$save_data];
        $this->has_changes[$save_data] = false;
        return $old;
    }

    /**
     * Return true if changes exist
     * @return bool
     */
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
