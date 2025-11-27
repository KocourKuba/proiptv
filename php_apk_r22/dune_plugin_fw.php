<?php
///////////////////////////////////////////////////////////////////////////

abstract class DunePluginFw
{
    // This variable should contain an instance of DunePluginFw.
    // 
    // NOTE: Generally, this variable does not belong to this class, it should
    // have been defined as global variable. It's here just because writing
    // 'DunePluginFw::$instance' is less error-prone than using a global
    // variable in PHP (no check for syntax errors, one has to explicitly
    // declare the intention to use globals a function).
    public static $instance = null;

    ///////////////////////////////////////////////////////////////////////

    public abstract function /* string */ call_plugin(
        /* string */ $call_ctx_json);

    ///////////////////////////////////////////////////////////////////////

    public function invoke_operation($plugin, $call_ctx)
    {
        if (method_exists($plugin, "set_plugin_cookies"))
            $plugin->set_plugin_cookies($call_ctx->plugin_cookies);

        if (method_exists($plugin, "set_internet_status"))
        {
            $internet_status = isset($call_ctx->internet_status) ?
                intval($call_ctx->internet_status) : -2;
            $plugin->set_internet_status($internet_status);
        }

        if (method_exists($plugin, "set_opexec_id"))
        {
            $opexec_id = isset($call_ctx->opexec_id) ?
                $call_ctx->opexec_id : "-1";
            $plugin->set_opexec_id($opexec_id);
        }

        if ($call_ctx->op_type_code === PLUGIN_OP_GET_FOLDER_VIEW ||
            $call_ctx->op_type_code === PLUGIN_OP_GET_NEXT_FOLDER_VIEW ||
            $call_ctx->op_type_code === PLUGIN_OP_GET_TV_INFO ||
            $call_ctx->op_type_code === PLUGIN_OP_GET_TV_STREAM_URL ||
            $call_ctx->op_type_code === PLUGIN_OP_GET_VOD_INFO ||
            $call_ctx->op_type_code === PLUGIN_OP_GET_VOD_STREAM_URL)
        {
            $php_func_name = $call_ctx->op_type_code;
            $php_func_name_v2 = $call_ctx->op_type_code . "_v2";

            if (isset($call_ctx->input_data) &&
                isset($call_ctx->input_data->sel_state) &&
                method_exists($plugin, $php_func_name_v2))
            {
                return $plugin->$php_func_name_v2(
                    $call_ctx->input_data->media_url,
                    $call_ctx->input_data->sel_state,
                    $call_ctx->plugin_cookies);
            }

            return $plugin->$php_func_name(
                $call_ctx->input_data->media_url,
                $call_ctx->plugin_cookies);
        }

        if ($call_ctx->op_type_code === PLUGIN_OP_GET_REGULAR_FOLDER_ITEMS)
        {
            return
                $plugin->get_regular_folder_items(
                    $call_ctx->input_data->media_url,
                    $call_ctx->input_data->from_ndx,
                    $call_ctx->plugin_cookies);
        }

        if ($call_ctx->op_type_code === PLUGIN_OP_GET_DAY_EPG)
        {
            return
                $plugin->get_day_epg(
                    $call_ctx->input_data->channel_id,
                    $call_ctx->input_data->day_start_tm_sec,
                    $call_ctx->plugin_cookies);
        }

        if ($call_ctx->op_type_code === PLUGIN_OP_GET_TV_PLAYBACK_URL)
        {
            $php_func_name_v2 = $call_ctx->op_type_code . "_v2";
            if (isset($call_ctx->input_data) &&
                isset($call_ctx->input_data->program_id) &&
                method_exists($plugin, $php_func_name_v2))
            {
                return $plugin->$php_func_name_v2(
                    $call_ctx->input_data->channel_id,
                    $call_ctx->input_data->archive_tm,
                    $call_ctx->input_data->protect_code,
                    $call_ctx->input_data->program_id,
                    $call_ctx->plugin_cookies);
            }

            return
                $plugin->get_tv_playback_url(
                    $call_ctx->input_data->channel_id,
                    $call_ctx->input_data->archive_tm,
                    $call_ctx->input_data->protect_code,
                    $call_ctx->plugin_cookies);
        }

        if ($call_ctx->op_type_code === PLUGIN_OP_CHANGE_TV_FAVORITES)
        {
            return $plugin->change_tv_favorites(
                $call_ctx->input_data->fav_op_type,
                $call_ctx->input_data->channel_id,
                $call_ctx->plugin_cookies);
        }

        // PluginOperationType::PLUGIN_OP_HANDLE_USER_INPUT

        return
            $plugin->handle_user_input(
                $call_ctx->input_data,
                $call_ctx->plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_out_type_code($op_code)
    {
        static $map = null;

        if (is_null($map))
        {
            $map = array
            (
                PLUGIN_OP_GET_FOLDER_VIEW           => PLUGIN_OUT_DATA_PLUGIN_FOLDER_VIEW,
                PLUGIN_OP_GET_NEXT_FOLDER_VIEW      => PLUGIN_OUT_DATA_PLUGIN_FOLDER_VIEW,
                PLUGIN_OP_GET_REGULAR_FOLDER_ITEMS  => PLUGIN_OUT_DATA_PLUGIN_REGULAR_FOLDER_RANGE,
                PLUGIN_OP_HANDLE_USER_INPUT         => PLUGIN_OUT_DATA_GUI_ACTION,
                PLUGIN_OP_GET_TV_INFO               => PLUGIN_OUT_DATA_PLUGIN_TV_INFO,
                PLUGIN_OP_GET_DAY_EPG               => PLUGIN_OUT_DATA_PLUGIN_TV_EPG_PROGRAM_LIST,
                PLUGIN_OP_GET_TV_PLAYBACK_URL       => PLUGIN_OUT_DATA_URL,
                PLUGIN_OP_GET_TV_STREAM_URL         => PLUGIN_OUT_DATA_URL,
                PLUGIN_OP_GET_VOD_INFO              => PLUGIN_OUT_DATA_PLUGIN_VOD_INFO,
                PLUGIN_OP_GET_VOD_STREAM_URL        => PLUGIN_OUT_DATA_URL,
                PLUGIN_OP_CHANGE_TV_FAVORITES       => PLUGIN_OUT_DATA_GUI_ACTION
            );
        }

        if (!isset($map[$op_code]))
        {
            hd_print("Error: get_out_type_code(): unknown operation code: '$op_code'.");
            throw new Exception("Uknown operation code");
        }

        return $map[$op_code];
    }
}

///////////////////////////////////////////////////////////////////////////
?>
