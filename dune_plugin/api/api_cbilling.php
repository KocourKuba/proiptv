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
     * @param bool $force
     * @return bool|object
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force get_provider_info: " . var_export($force, true), true);

        if (!$this->request_provider_token()) {
            hd_debug_print("Failed to get provider token", true);
            return null;
        }

        if ((empty($this->account_info) || $force)) {
            $curl_opt[CURLOPT_HTTPHEADER][] = "x-public-key: {PASSWORD}";
            $this->account_info = $this->execApiCommand(API_COMMAND_ACCOUNT_INFO, null, true, $curl_opt);
            hd_debug_print("get_provider_info: " . pretty_json_format($this->account_info), true);
        }

        return $this->account_info;
    }

    /**
     * @inheritDoc
     */
    public function GetInfoUI($handler)
    {
        $account_info = $this->get_provider_info();

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (empty($account_info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else if (isset($account_info->data)) {
            $data = $account_info->data;
            if (isset($data->end_date)) {
                Control_Factory::add_label($defs, TR::t('end_date'), $data->end_date, -15);
            }
            if (isset($data->devices_num)) {
                Control_Factory::add_label($defs, TR::t('devices'), $data->devices_num, -15);
            }
            if (isset($data->server)) {
                Control_Factory::add_label($defs, TR::t('server'), $data->server, -15);
            }
            if (isset($data->ssl)) {
                Control_Factory::add_label($defs, TR::t('ssl'), $data->ssl ? TR::t('yes') : TR::t('no'), -15);
            }
            if (isset($data->disable_adult)) {
                Control_Factory::add_label($defs, TR::t('disable_adult'), $data->disable_adult ? TR::t('yes') : TR::t('no'), -15);
            }
            if (isset($data->vod)) {
                Control_Factory::add_label($defs, TR::load_string('plugin_vod') . ":", $data->vod ? TR::t('yes') : TR::t('no'), -15);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog(TR::t('subscription'), $defs, true, 1000, null /*$attrs*/);
    }

    /**
     * @inheritDoc
     */
    public function GetServers()
    {
        hd_debug_print(null, true);

        if (empty($this->servers)) {
            $response = $this->execApiCommand(API_COMMAND_GET_SERVERS);
            hd_debug_print("GetServers: " . pretty_json_format($response), true);
            if (isset($response->data)) {
                foreach ($response->data as $server) {
                    $this->servers[$server->name] = $server->country;
                }

                $cur_server = $this->getCredential(MACRO_SERVER_ID);
                if (empty($cur_server) && isset($this->account_info->data->server)) {
                    $this->setCredential(MACRO_SERVER_ID, $this->account_info->data->server);
                }
            }
        }

        return $this->servers;
    }
}
