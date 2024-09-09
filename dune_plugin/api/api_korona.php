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
    const TOKEN_FILE = "%s.token";
    const REFRESH_TOKEN_FILE = "%s.refresh_token";

    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $token_file = sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id());
        $token = HD::get_cookie($token_file);
        $expired = empty($token);

        if (!$force && !$expired) {
            hd_debug_print("request not required", true);
            return true;
        }

        $refresh_token_file = sprintf(self::REFRESH_TOKEN_FILE, $this->get_provider_playlist_id());
        $refresh_token = HD::get_cookie($refresh_token_file, true);
        $need_refresh = $expired && !empty($refresh_token);
        if ($need_refresh) {
            /*
            grant_type=refresh_token
            refresh_token={{refresh_token}}"
            */
            hd_debug_print("need to refresh token", true);
            $cmd = API_COMMAND_REFRESH_TOKEN;
            $pairs['grant_type'] = 'refresh_token';
            $pairs['refresh_token'] = $refresh_token;
        } else {
            /*
            grant_type=password
            username={LOGIN}"
            password={PASSWORD}
            */
            hd_debug_print("need to request token", true);
            $cmd = API_COMMAND_REQUEST_TOKEN;
            $pairs['grant_type'] = 'password';
            $pairs['username'] = $this->getCredential(MACRO_LOGIN);
            $pairs['password'] = $this->getCredential(MACRO_PASSWORD);
        }

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER][] = "Content-Type: application/x-www-form-urlencoded";
        $data = '';
        foreach($pairs as $key => $value) {
            if (!empty($data)) {
                $data .= "&";
            }
            $data .= $key . "=" . urlencode($value);
        }
        $curl_opt[CURLOPT_POSTFIELDS] = $data;

        $data = $this->execApiCommand($cmd, null, true, $curl_opt);
        if (isset($data->access_token)) {
            hd_debug_print("token requested: " . pretty_json_format($data), true);
            HD::set_cookie($token_file, $data->access_token, time() + $data->expires_in);
            HD::set_cookie($refresh_token_file, $data->refresh_token, PHP_INT_MAX, true);
            return true;
        }

        hd_debug_print("token not received: " . pretty_json_format($data), true);
        HD::set_last_error("rq_last_error", TR::load_string('err_cant_get_token') . "\n\n" . pretty_json_format($data));
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear_session_info()
    {
        HD::clear_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()));
        HD::clear_cookie(sprintf(self::REFRESH_TOKEN_FILE, $this->get_provider_playlist_id()), true);
    }

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        hd_debug_print("current api template: $string", true);
        $token = HD::get_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()));
        $string = str_replace(MACRO_TOKEN, $token, $string);
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
            $packages .= TR::load_string('end_date') . " $account_info->expiry_date" . PHP_EOL;
            $packages .= TR::load_string('package_timed') . " {$account_info->tariff->period}" . PHP_EOL;
            $packages .= TR::load_string('money_need') . " {$account_info->tariff->full_price} {$account_info->tariff->currency}" . PHP_EOL;
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
            $response = $this->make_json_request(API_COMMAND_GET_SERVERS);
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
     * @param string $cmd
     * @param array|null $params
     * @return bool|object
     */
    protected function make_json_request($cmd, $params = null)
    {
        if (!$this->request_provider_token()) {
            return false;
        }

        $curl_opt = array();

        if (isset($params[CURLOPT_CUSTOMREQUEST])) {
            $curl_opt[CURLOPT_CUSTOMREQUEST] = $params[CURLOPT_CUSTOMREQUEST];
        }

        $curl_opt[CURLOPT_HTTPHEADER][] = "Authorization: Bearer {TOKEN}";

        return $this->execApiCommand($cmd, null, true, $curl_opt);
    }
}
