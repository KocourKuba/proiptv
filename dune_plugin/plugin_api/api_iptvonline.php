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

class api_iptvonline extends api_default
{
    /**
     * @var array
     */
    protected $playlists = array();

    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $token = $this->plugin->get_cookie(PARAM_TOKEN, true);
        $expired = empty($token);

        if (!$force) {
            if (!$expired) {
                hd_debug_print("request not required", true);
                return true;
            }

            Dune_Last_Error::get_last_error(LAST_ERROR_REQUEST, false);
            if (!empty($error)) {
                hd_debug_print("Previous token request failed!");
                return false;
            }
        }

        Dune_Last_Error::clear_last_error(LAST_ERROR_REQUEST);

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
            $pairs['login'] = $this->GetProviderParameter(MACRO_LOGIN);
            $pairs['password'] = $this->GetProviderParameter(MACRO_PASSWORD);
        }

        $pairs['client_id'] = "TestAndroidAppV0";
        $pairs['client_secret'] = "kshdiouehruyiwuresuygr736t4763b7637"; // dummy
        $pairs['device_id'] = get_serial_number();

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER][] = CONTENT_TYPE_JSON;
        $curl_opt[CURLOPT_POSTFIELDS] = $pairs;

        $data = $this->execApiCommandResponse($cmd, $curl_opt, Curl_Wrapper::RET_ARRAY);
        $access_token = safe_get_value($data, 'access_token');
        $refresh_token = safe_get_value($data, 'refresh_token');
        if (!empty($access_token) && !empty($refresh_token)) {
            hd_debug_print("token requested", true);
            $this->plugin->set_cookie(PARAM_TOKEN, $access_token, safe_get_value($data, 'expires_time', time() + 86400));
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

        hd_debug_print("token not received: " . json_format_unescaped($data), true);
        Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, TR::load('err_cant_get_token') . "\n" . json_format_unescaped($data));
        return false;
    }

    /**
     * @inheritDoc
     */
    public function postExecAction($command, $execResult, $file = null, &$error_msg = null)
    {
        hd_debug_print(null, true);

        if ($execResult === false) {
            return false;
        }

        $response = Curl_Wrapper::decodeJsonResponse(true, $file);
        if ($response === false || $response === null) {
            hd_debug_print("Can't decode response on request: " . $command, true);
        }

        switch ($command) {
            case API_COMMAND_GET_PLAYLIST:

                $success = (int)safe_get_value($response, 'success');
                if ($success !== 1) break;
                $data = safe_get_value($response, 'data');
                if (empty($data)) break;

                $curl_wrapper = $this->plugin->setup_curl();
                return $curl_wrapper->download_file($data, $file);

            case API_COMMAND_GET_DEVICE:
                hd_debug_print("GetServers: " . json_format_unescaped($response), true);
                $status = (int)safe_get_value($response, 'status');
                if ($status !== 200) break;

                $this->devices = $response;
                if (empty($this->servers)) {
                    $this->collect_servers($selected);
                    if ($selected !== $this->GetProviderParameter(MACRO_SERVER_ID)) {
                        $this->SetProviderParameter(MACRO_SERVER_ID, $selected);
                    }
                }
                return true;

            case API_COMMAND_SET_DEVICE:
                $status = (int)safe_get_value($response, 'status');
                if ($status === 200) {
                    $this->devices = $response;
                    $this->collect_servers($selected);
                    $this->account_info = null;
                    parent::SetServer($selected, $error_msg);
                    return true;
                }

                hd_debug_print("Can't set device: " . json_format_unescaped($response));
                if (isset($response['message'])) {
                    $error_msg = $response['message'];
                } else {
                    $error_msg = json_format_readable($response);
                }
                break;
        }

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
        } else if (!isset($this->account_info['status']) || $this->account_info['status'] !== 200) {
            Control_Factory::add_label($defs, TR::t('err_error'), $this->account_info['message'], -10);
        } else {
            $data = safe_get_value($this->account_info, 'data', array());
            if (isset($data['login'])) {
                Control_Factory::add_label($defs, TR::t('login'), $data['login'], -15);
            }

            if (isset($data['balance'], $data['currency'])) {
                Control_Factory::add_label($defs, TR::t('balance'), "{$data['balance']} {$data['currency']}", -15);
            }

            if (isset($data['server_name'])) {
                Control_Factory::add_label($defs, TR::t('server'), $data['server_name'], -15);
            }

            if (isset($data['selected_playlist']['title'])) {
                Control_Factory::add_label($defs, TR::t('playlist'), $data['selected_playlist']['title'], -15);
            }

            $packages = '';
            foreach (safe_get_value($data, 'subscriptions', array()) as $subscription) {
                $packages .= safe_get_value($subscription, 'name', '') . PHP_EOL;
                $packages .= TR::load('end_date__1', safe_get_value($subscription, 'end_date', '')) . PHP_EOL;
                $packages .= TR::load('recurring__1', TR::load(safe_get_value($subscription, 'auto_prolong', false) ? 'yes' : 'no')) . PHP_EOL;
            }
            if (!empty($packages)) {
                Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
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

        $cmd = API_COMMAND_GET_DEVICE;
        if (empty($this->devices)) {
            if ($this->execApiCommandWithPostResponse($cmd, $this->getCurlOpts($cmd)) === false) {
                return array();
            }
        }

        return $this->servers;
    }

    /**
     * collect servers information
     * @param string $selected
     * @return array
     */
    protected function collect_servers(&$selected = "-1")
    {
        $this->servers = array();

        foreach (safe_get_value($this->devices, array('device', 'settings', 'server_location', 'value'), array()) as $server) {
            $id = (string)safe_get_value($server, 'id');
            if (empty($id)) continue;

            $this->servers[$id] = $server['label'];
            if ($server['selected']) {
                $selected = $id;
            }
        }

        return $this->servers;
    }

    /**
     * @inheritDoc
     */
    public function SetServer($server, &$error_msg)
    {
        $curl_params[CURLOPT_POST] = true;
        $curl_params[CURLOPT_POSTFIELDS] = array("server_location" => $server);
        $cmd = API_COMMAND_SET_DEVICE;
        return $this->execApiCommandWithPostResponse($cmd, $this->getCurlOpts($cmd, $curl_params), $error_msg);
    }

    /**
     * @inheritDoc
     */
    public function getCurlOpts($command = null, $params = null)
    {
        $curl_opt = array();

        if (isset($params[API_COMMAND_ADD_PARAMS])) {
            $curl_opt[API_COMMAND_ADD_PARAMS] = $params[API_COMMAND_ADD_PARAMS];
        }

        if (isset($params[CURLOPT_POSTFIELDS])) {
            $curl_opt[CURLOPT_HTTPHEADER][] = CONTENT_TYPE_JSON;
            $curl_opt[CURLOPT_POSTFIELDS] = $params[CURLOPT_POSTFIELDS];
        }

        return $curl_opt;
    }

    /**
     * @inheritDoc
     */
    protected function get_additional_headers($command)
    {
        $token = $this->plugin->get_cookie(PARAM_TOKEN);
        if (!empty($token) && $command !== API_COMMAND_REQUEST_TOKEN && $command !== API_COMMAND_REFRESH_TOKEN) {
            return array($this->replace_macros("Authorization: Bearer {TOKEN}"));
        }

        return array();
    }
}
