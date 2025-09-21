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
     * @var array
     */
    protected $servers = array();

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
            $pairs['username'] = $this->GetParameter(MACRO_LOGIN);
            $pairs['password'] = $this->GetParameter(MACRO_PASSWORD);
        }

        $data = '';
        foreach($pairs as $key => $value) {
            if (!empty($data)) {
                $data .= "&";
            }
            $data .= $key . "=" . urlencode($value);
        }

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER][] = CONTENT_TYPE_WWW_FORM_URLENCODED;
        $curl_opt[CURLOPT_POSTFIELDS] = $data;

        $data = $this->execApiCommand($cmd, null, true, $curl_opt);
        if (isset($data->access_token)) {
            hd_debug_print("token requested: " . pretty_json_format($data), true);
            $this->plugin->set_cookie(PARAM_TOKEN, $data->access_token, time() + $data->expires_in);
            $this->plugin->set_cookie(PARAM_REFRESH_TOKEN, $data->access_token, PHP_INT_MAX);
            return true;
        }

        if ($can_refresh && isset($data->error)) {
            // refresh token failed. Need to make complete auth
            $this->plugin->remove_cookie(PARAM_TOKEN);
            $this->plugin->remove_cookie(PARAM_REFRESH_TOKEN);
            return $this->request_provider_token(true);
        }

        $rq_last_error_name = $this->plugin->get_request_error_name();
        hd_debug_print("token not received: " . pretty_json_format($data), true);
        HD::set_last_error($rq_last_error_name, TR::load('err_cant_get_token') . "\n\n" . pretty_json_format($data));
        return false;
    }

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        hd_debug_print("current api template: $string", true);
        $string = str_replace(MACRO_TOKEN, $this->plugin->get_cookie(PARAM_TOKEN), $string);
        hd_debug_print("current api result: $string", true);

        return parent::replace_macros($string);
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
            Control_Factory::add_label($defs, TR::t('warn_msg3'), null, -10);
        } else if (isset($account_info->balance, $account_info->tariff)) {
            Control_Factory::add_label($defs, TR::t('balance'), "$account_info->balance {$account_info->tariff->currency}", -15);
            $packages = $account_info->tariff->name . PHP_EOL;
            $packages .= TR::load('end_date__1', $account_info->expiry_date) . PHP_EOL;
            $packages .= TR::load('package_timed__1', $account_info->tariff->period) . PHP_EOL;
            $packages .= TR::load('money_need__1', "{$account_info->tariff->full_price} {$account_info->tariff->currency}") . PHP_EOL;
            Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog(TR::t('subscription'), $defs, true, 1100);
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
                    $this->servers[(string)$server->id] = $server->title;
                }
            }
        }

        return $this->servers;
    }

    /**
     * @inheritDoc
     */
    public function set_provider_defaults()
    {
        $servers = $this->GetServers();
        if (!empty($servers)) {
            $idx = $this->GetParameter(MACRO_SERVER_ID);
            if (empty($idx)) {
                $this->SetParameter(MACRO_SERVER_ID, key($servers));
            }
        }
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
