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

require_once 'api_default.php';

/**
 * "data": {
 *      "public_token": "2f5787bd535caee4e25ba3ed3019babc",
 *      "private_token": "5acf87d0206d915b73489234703bf666",
 *      "end_time": 1706129968,
 *      "end_date": "2024-01-24 23:59:28",
 *      "devices_num": 1,
 *      "server": "s01.wsbof.com",
 *      "vod": true,
 *      "ssl": false,
 *      "disable_adult": false
 * }
 */

class api_cbilling extends api_default
{
    /**
     * @inheritDoc
     */
    public function request_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request_provider_info: " . var_export($force, true), true);

        if (!$this->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
            $this->account_info = array();
        } else if (empty($this->account_info) || $force) {
            $this->account_info = $this->execApiCommandResponseNoOpt(API_COMMAND_ACCOUNT_INFO, Curl_Wrapper::RET_ARRAY);
            hd_debug_print("request_provider_info: " . json_format_unescaped($this->account_info), true);
        }
    }

    /**
     * @inheritDoc
     */
    public function GetInfoUI($handler)
    {
        $this->request_provider_info();

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        $data = safe_get_value($this->account_info, 'data', array());
        if (empty($data)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else {
            if (isset($data['end_date'])) {
                Control_Factory::add_label($defs, TR::t('end_date'), $data['end_date'], -15);
            }
            if (isset($data['devices_num'])) {
                Control_Factory::add_label($defs, TR::t('devices'), $data['devices_num'], -15);
            }
            if (isset($data['server'])) {
                Control_Factory::add_label($defs, TR::t('server'), $data['server'], -15);
            }
            if (isset($data['ssl'])) {
                Control_Factory::add_label($defs, TR::t('ssl'), $data['ssl'] ? TR::t('yes') : TR::t('no'), -15);
            }
            if (isset($data['disable_adult'])) {
                Control_Factory::add_label($defs, TR::t('disable_adult'), $data['disable_adult'] ? TR::t('yes') : TR::t('no'), -15);
            }
            if (isset($data['vod'])) {
                Control_Factory::add_label($defs, TR::t('plugin_vod__1', ':'), $data['vod'] ? TR::t('yes') : TR::t('no'), -15);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog($defs, TR::t('subscription'));
    }

    /**
     * @inheritDoc
     */
    public function GetServers()
    {
        hd_debug_print(null, true);

        if (empty($this->servers)) {
            $response = $this->execApiCommandResponseNoOpt(API_COMMAND_GET_SERVERS, Curl_Wrapper::RET_ARRAY);
            hd_debug_print("GetServers: " . json_format_unescaped($response), true);
            foreach (safe_get_value($response, 'data', array()) as $server) {
                if (isset($server['name'])) {
                    $this->servers[$server['name']] = safe_get_value($server, 'country', 'unknown');
                }
            }
        }

        $cur_server = $this->GetProviderParameter(MACRO_SERVER_ID);
        if (empty($cur_server)) {
            $server = safe_get_value($this->account_info, array('data', 'server'));
            if (!empty($server)) {
                $this->SetProviderParameter(MACRO_SERVER_ID, $server);
            }
        }

        return $this->servers;
    }

    /**
     * @inheritDoc
     */
    protected function get_additional_headers($command)
    {
        if ($command === API_COMMAND_ACCOUNT_INFO) {
            return array($this->replace_macros("x-public-key: {PASSWORD}"));
        }

        return array();
    }
}
