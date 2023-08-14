<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/tr.php';
require_once 'action_factory.php';
require_once 'dune_exception.php';

///////////////////////////////////////////////////////////////////////////

class Default_Dune_Plugin_Fw extends DunePluginFw
{
    public static $plugin_class_name;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return mixed
     */
    public function create_plugin()
    {
        return new self::$plugin_class_name;
    }

    /**
     * @param $call_ctx_json
     * @return false|string
     * @throws Exception
     */
    public function call_plugin($call_ctx_json)
    {
        return json_encode($this->call_plugin_impl(json_decode($call_ctx_json)));
    }

    /**
     * @param $call_ctx
     * @return array
     * @throws Exception
     */
    protected function call_plugin_impl($call_ctx)
    {
        static $plugin;

        if (is_null($plugin)) {
            try {
                hd_print('Instantiating plugin...');
                $plugin = $this->create_plugin();
                hd_print('Plugin instance created.');
            } catch (Exception $e) {
                hd_print('Error: can not instantiate plugin (' . $e->getMessage() . ')');

                return
                    array
                    (
                        PluginOutputData::has_data => false,
                        PluginOutputData::plugin_cookies => $call_ctx->plugin_cookies,
                        PluginOutputData::is_error => true,
                        PluginOutputData::error_action =>
                            Action_Factory::show_error(
                                true,
                                'System error',
                                array(
                                    'Can not create PHP plugin instance.',
                                    'Call the PHP plugin vendor.'))
                    );
            }
        }

        // assert($plugin);

        try {
            $out_data = $this->invoke_operation($plugin, $call_ctx);
        } catch (Dune_Exception $e) {
            hd_print("Error: DuneException caught: " . $e->getMessage());
            return
                array
                (
                    PluginOutputData::has_data => false,
                    PluginOutputData::plugin_cookies => $call_ctx->plugin_cookies,
                    PluginOutputData::is_error => true,
                    PluginOutputData::error_action => $e->get_error_action()
                );
        } catch (Exception $e) {
            hd_print("Error: Exception caught: " . $e->getMessage());

            return
                array
                (
                    PluginOutputData::has_data => false,
                    PluginOutputData::plugin_cookies => $call_ctx->plugin_cookies,
                    PluginOutputData::is_error => true,
                    PluginOutputData::error_action =>
                        Action_Factory::show_error(
                            true,
                            'System error',
                            array(
                                'Unhandled PHP plugin error.',
                                'Call the PHP plugin vendor.'))
                );
        }

        // Note: change_tv_favorites() may return NULL even if it's completed
        // successfully.

        $plugin_output_data = array
        (
            PluginOutputData::has_data => !is_null($out_data),
            PluginOutputData::plugin_cookies => $call_ctx->plugin_cookies,
            PluginOutputData::is_error => false,
            PluginOutputData::error_action => null
        );

        if ($plugin_output_data[PluginOutputData::has_data]) {
            $plugin_output_data[PluginOutputData::data_type] =
                $this->get_out_type_code($call_ctx->op_type_code);

            $plugin_output_data[PluginOutputData::data] = $out_data;
        }

        return $plugin_output_data;
    }
}

///////////////////////////////////////////////////////////////////////////

DunePluginFw::$instance = new Default_Dune_Plugin_Fw();
