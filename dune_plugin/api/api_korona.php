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

class api_korona extends api_default
{
    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $token = $this->plugin->get_cookie(PARAM_TOKEN, true);
        $expired = empty($token);

        if (!$force && !$expired) {
            hd_debug_print("request or refresh token not required", true);
            return true;
        }

        $refresh_token = $this->plugin->get_cookie(PARAM_REFRESH_TOKEN);
        $can_refresh = $expired && !empty($refresh_token);
        if ($can_refresh) {
            hd_debug_print("need to refresh token", true);
            $cmd = API_COMMAND_REFRESH_TOKEN;
            $pairs['grant_type'] = 'refresh_token';
            $pairs['refresh_token'] = $refresh_token;
        } else {
            hd_debug_print("need to request token", true);
            $cmd = API_COMMAND_REQUEST_TOKEN;
            $pairs['grant_type'] = 'password';
            $pairs['username'] = $this->GetProviderParameter(MACRO_LOGIN);
            $pairs['password'] = $this->GetProviderParameter(MACRO_PASSWORD);
        }

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER][] = CONTENT_TYPE_WWW_FORM_URLENCODED;
        $curl_opt[CURLOPT_POSTFIELDS] = $pairs;

        $data = $this->execApiCommandResponse($cmd, $curl_opt, Curl_Wrapper::RET_ARRAY);
        $access_token = safe_get_value($data, 'access_token');
        $refresh_token = safe_get_value($data, 'refresh_token');
        if (!empty($access_token) && !empty($refresh_token)) {
            hd_debug_print("token requested: " . json_format_unescaped($data), true);
            $this->plugin->set_cookie(PARAM_TOKEN, $access_token, time() + $data->expires_in);
            $this->plugin->set_cookie(PARAM_REFRESH_TOKEN, $refresh_token, PHP_INT_MAX);
            return true;
        }

        $error = safe_get_value($data, 'error');
        if ($can_refresh && !empty($error)) {
            // refresh token failed. Need to make complete auth
            $this->plugin->remove_cookie(PARAM_TOKEN);
            $this->plugin->remove_cookie(PARAM_REFRESH_TOKEN);
            return $this->request_provider_token(true);
        }

        hd_debug_print("token not received: " . json_format_unescaped($data));
        Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, TR::load('err_cant_get_token') . "\n\n" . json_format_unescaped($data));
        return false;
    }

    /**
     * @inheritDoc
     */
    public function GetInfoUI($handler)
    {
        $this->request_provider_info();

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (empty($this->account_info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('warn_msg3'), null, -10);
        } else if (isset($this->account_info['balance'], $this->account_info['tariff'])) {
            Control_Factory::add_label($defs, TR::t('balance'), "{$this->account_info['balance']} {$this->account_info['tariff']['currency']}", -15);
            $packages = $this->account_info['tariff']['name'] . PHP_EOL;
            $packages .= TR::load('end_date__1', $this->account_info['expiry_date']) . PHP_EOL;
            $packages .= TR::load('package_timed__1', $this->account_info['tariff']['period']) . PHP_EOL;
            $packages .= TR::load('money_need__1', "{$this->account_info['tariff']['full_price']} {$this->account_info['tariff']['currency']}") . PHP_EOL;
            Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
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
                $this->servers[(string)$server['id']] = $server['title'];
            }
        }

        return $this->servers;
    }

    /**
     * @inheritDoc
     */
    protected function get_additional_headers($command)
    {
        if ($command !== API_COMMAND_REQUEST_TOKEN && $command !== API_COMMAND_REFRESH_TOKEN) {
            return array($this->replace_macros("Authorization: Bearer {TOKEN}"));
        }

        return array();
    }
}
