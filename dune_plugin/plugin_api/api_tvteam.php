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

class api_tvteam extends api_default
{
    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $string = parent::replace_macros($string);

        hd_debug_print("current api template: $string", true);
        $hash_password = md5($this->GetProviderParameter(MACRO_PASSWORD));
        $string = str_replace(MACRO_HASH_PASSWORD, $hash_password, $string);
        hd_debug_print("current api result: $string", true);

        return $string;
    }

    /**
     * @inheritDoc
     */
    public function GetInfoUI($handler)
    {
        $this->request_provider_info();

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        $data = safe_get_value($this->account_info, 'data');
        $userData = safe_get_value($data, 'userData');
        if (empty($userData)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else {
            if (isset($userData['userLogin'])) {
                Control_Factory::add_label($defs, TR::t('login'), $userData['userLogin'], -15);
            }

            if (isset($userData['userEmail'])) {
                Control_Factory::add_label($defs, TR::t('name'), $userData['userEmail'], -15);
            }

            if (isset($userData['userBalance'])) {
                Control_Factory::add_label($defs, TR::t('balance'), "{$userData['userBalance']}$", -15);
            }

            if (isset($userData['groupId'])) {
                $name = safe_get_value($this->servers, $userData['groupId'], 'Not set');
                Control_Factory::add_label($defs, TR::t('server'), $name, -15);
            }

            if (isset($userData['showPorno'])) {
                Control_Factory::add_label($defs, TR::t('disable_adult'), $userData['showPorno'] ? TR::t('no') : TR::t('yes'), -15);
            }

            $packages = '';
            foreach (safe_get_value($data, 'userPackagesList', array()) as $package) {
                $packages .= TR::load('package__1', $package->packageName) . PHP_EOL;
                $packages .= TR::load('start_date__1', $package->fromDate) . PHP_EOL;
                $packages .= TR::load('end_date__1', $package->toDate) . PHP_EOL;
                $packages .= TR::load('money_need__1', "$package->salePrice$") . PHP_EOL;
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
    public function request_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request_provider_info: " . var_export($force, true), true);

        if (!$this->request_provider_token()) {
            hd_debug_print("Failed to get provider token");
            return;
        }

        if (!$this->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
            $this->account_info = array();
        } else if (empty($this->account_info) || $force) {
            $curl_opt[CURLOPT_TIMEOUT] = 30;
            $response = $this->execApiCommandResponse(API_COMMAND_ACCOUNT_INFO, $curl_opt, Curl_Wrapper::RET_ARRAY);
            if ($response === false) {
                Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, "Bad provider response");
                return;
            }

            $status = (int)safe_get_value($response, 'status');
            $error = safe_get_value($response, 'error');
            if ($status !== 1 || !empty($error)) {
                hd_debug_print("request_provider_info error response: " . json_format_unescaped($response), true);
            }

            $this->account_info = $response;
            $data = safe_get_value($response, 'data');
            $userData = safe_get_value($response, 'userData');
            $userToken = safe_get_value($userData, 'userToken');

            if (!empty($userToken)) {
                $this->plugin->set_cookie(PARAM_TOKEN, $userToken);
            }

            foreach (safe_get_value($data, 'serversGroupsList', array()) as $server) {
                $id = safe_get_value($server, 'groupId');
                if (empty($id)) continue;

                $groupCountry = safe_get_value($server, 'groupCountry');
                $domainName = safe_get_value($server, 'streamDomainName');
                $this->servers[$id] = "$groupCountry ($domainName)";
            }

            $groupId = safe_get_value($userData, 'groupId');
            if (!empty($groupId)) {
                $this->SetProviderParameter(MACRO_SERVER_ID, $groupId);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $session_id = $this->plugin->get_cookie(PARAM_SESSION_ID, true);
        $expired = empty($session_id);

        if (!$force && !$expired) {
            hd_debug_print("request not required", true);
            return true;
        }

        $error_msg = Dune_Last_Error::get_last_error(LAST_ERROR_REQUEST);
        if (!$force && !empty($error_msg)) {
            $info_msg = str_replace('|', PHP_EOL, TR::load('err_auth_no_spam'));
            hd_debug_print($info_msg);
            Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, "$info_msg\n\n$error_msg");
        } else {
            $curl_opt[CURLOPT_TIMEOUT] = 30;
            $response = $this->execApiCommandResponse(API_COMMAND_REQUEST_TOKEN, $curl_opt, Curl_Wrapper::RET_ARRAY);
            if ($response === false) {
                Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, "Bad provider response");
                return false;
            }
            $status = safe_get_value($response, 'status');
            $error = safe_get_value($response, 'error');
            if (empty($status) || !empty($error)) {
                hd_debug_print("request provider token bad response: " . json_format_unescaped($response), true);
                Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, $error);
                return false;
            }

            $session_id = safe_get_value($response, array('data', 'sessionId'));
            if (!empty($session_id)) {
                $this->plugin->set_cookie(PARAM_SESSION_ID, $session_id, time() + 86400 * 7);
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function GetServers()
    {
        hd_debug_print(null, true);

        if (empty($this->servers)) {
            $curl_opt[CURLOPT_TIMEOUT] = 30;
            $response = $this->execApiCommandResponse(API_COMMAND_GET_SERVERS, $curl_opt, Curl_Wrapper::RET_ARRAY);
            $status = (int)safe_get_value($response, 'status');
            $error = safe_get_value($response, 'error');
            if ($status === 1 && empty($error)) {
                $servers_list = safe_get_value($response, array('data', 'serversGroupsList'), array());
                foreach ($servers_list as $server) {
                    $id = safe_get_value($server, 'groupId');
                    if (!empty($id)) {
                        $groupCountry = safe_get_value($server, 'groupCountry');
                        $domainName = safe_get_value($server, 'streamDomainName');
                        $this->servers[$id] = "$groupCountry ($domainName)";
                    }
                }

                $groupId = safe_get_value($response, array('data', 'userData', 'groupId'));
                if (!empty($groupId)) {
                    $this->SetProviderParameter(MACRO_SERVER_ID, $groupId);
                }
            } else {
                hd_debug_print("GetServers failed response: " . json_format_unescaped($response), true);
            }
        }

        return empty($this->servers) ? array() : $this->servers;
    }

    /**
     * @inheritDoc
     */
    public function SetServer($server, &$error_msg)
    {
        parent::SetServer($server, $error_msg);

        $curl_opt[CURLOPT_TIMEOUT] = 30;
        $response = $this->execApiCommandResponse(API_COMMAND_SET_SERVER, $curl_opt, Curl_Wrapper::RET_ARRAY);
        hd_debug_print("SetServer: " . json_format_unescaped($response), true);
        $status = (int)safe_get_value($response, 'status');
        if ($status === 1) {
            $this->account_info = null;
            $this->servers = array();
            return true;
        }

        $error_msg = safe_get_value($response, 'error', '');
        return false;
    }
}
