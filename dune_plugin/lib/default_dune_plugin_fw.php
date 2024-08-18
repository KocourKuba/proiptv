<?php
///////////////////////////////////////////////////////////////////////////

require_once 'tr.php';
require_once 'action_factory.php';
require_once 'dune_exception.php';
require_once 'dune_stb_api.php';

///////////////////////////////////////////////////////////////////////////

class Default_Dune_Plugin_Fw extends DunePluginFw
{
    public static $plugin_class_name;

    ///////////////////////////////////////////////////////////////////////

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
                hd_print("Instantiating plugin...");
                $plugin = $this->create_plugin();
                hd_debug_print("Plugin instance created.");
            } catch (Exception $ex) {
                hd_debug_print("Error: can not instantiate plugin");
                print_backtrace_exception($ex);

                return array(
                    PluginOutputData::has_data => false,
                    PluginOutputData::plugin_cookies => $call_ctx->plugin_cookies,
                    PluginOutputData::is_error => true,
                    PluginOutputData::error_action =>
                        Action_Factory::show_error(
                            true,
                            'System error',
                            array(
                                'Can not create PHP plugin instance.',
                                'Call the PHP plugin vendor.'
                            )
                        )
                );
            }
        }

        // assert($plugin);

        try {
            $out_data = $this->invoke_operation($plugin, $call_ctx);
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (Dune_Exception $e) {
            hd_debug_print("Error: DuneException caught: " . $e->getMessage());
            return array(
                PluginOutputData::has_data => false,
                PluginOutputData::plugin_cookies => $call_ctx->plugin_cookies,
                PluginOutputData::is_error => true,
                PluginOutputData::error_action => $e->get_error_action()
            );
        } catch (Exception $ex) {
            print_backtrace_exception($ex);

            return array(
                PluginOutputData::has_data => false,
                PluginOutputData::plugin_cookies => $call_ctx->plugin_cookies,
                PluginOutputData::is_error => true,
                PluginOutputData::error_action =>
                    Action_Factory::show_error(
                        true,
                        'System error',
                        array(
                            'Unhandled PHP plugin error.',
                            'Call the PHP plugin vendor.'
                        )
                    )
            );
        }

        // Note: change_tv_favorites() may return NULL even if it's completed
        // successfully.

        $plugin_output_data = array(
            PluginOutputData::has_data => !is_null($out_data),
            PluginOutputData::plugin_cookies => $call_ctx->plugin_cookies,
            PluginOutputData::is_error => false,
            PluginOutputData::error_action => null
        );

        if ($plugin_output_data[PluginOutputData::has_data]) {
            $plugin_output_data[PluginOutputData::data_type] = $this->get_out_type_code($call_ctx->op_type_code);
            $plugin_output_data[PluginOutputData::data] = $out_data;
        }

        return $plugin_output_data;
    }

    /**
     * @return mixed
     */
    public function create_plugin()
    {
        return new self::$plugin_class_name;
    }
}

///////////////////////////////////////////////////////////////////////////

function plugin_error_handler($error_type, $message, $file, $line)
{
    hd_print("Error intercepted");
    print_backtrace();
    hd_error_handler($error_type, $message, $file, $line);
}

$old_error_handler = set_error_handler('plugin_error_handler');
hd_print("Old handler: $old_error_handler");
DunePluginFw::$instance = new Default_Dune_Plugin_Fw();
