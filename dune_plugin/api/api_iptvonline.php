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
    const TOKEN_FILE = "%s.token";
    const REFRESH_TOKEN_FILE = "%s.refresh_token";

    /**
     * @var Object
     */
    protected $device;

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

        // remove old settings
        $res = $this->removeCredential(MACRO_TOKEN);
        $res |= $this->removeCredential(MACRO_REFRESH_TOKEN);
        $res |= $this->removeCredential(MACRO_EXPIRE_DATA);
        if ($res) {
            $this->save_credentials();
        }

        $refresh_token_file = sprintf(self::REFRESH_TOKEN_FILE, $this->get_provider_playlist_id());
        $refresh_token = HD::get_cookie($refresh_token_file, true);
        $need_refresh = $expired && !empty($refresh_token);
        if ($need_refresh) {
            /*
            {
                "client_id" : "{{client_id}}",
                "client_secret": "{{client_secret}}",
                "device_id": "{{DEVICE_UNIQ_ID}}",
                "grant_type" : "refresh_token",
                "refresh_token" : "{{refresh_token}}",
            }
            */
            hd_debug_print("need to refresh token", true);
            $cmd = API_COMMAND_REFRESH_TOKEN;
            $pairs['grant_type'] = 'refresh_token';
            $pairs['refresh_token'] = $refresh_token;
        } else {
            /*
            {
                "client_id" : "{{client_id}}",
                "client_secret": "{{client_secret}}",
                "device_id": "{{DEVICE_UNIQ_ID}}".
                "login": "{{TEST_EMAIL_CLIENT}}",
                "password" : "{{TEST_EMAIL_CLIENT_PASSWORD}}"
            }
            */
            hd_debug_print("need to request token", true);
            $cmd = API_COMMAND_REQUEST_TOKEN;
            $pairs['login'] = $this->getCredential(MACRO_LOGIN);
            $pairs['password'] = $this->getCredential(MACRO_PASSWORD);
        }

        $pairs['client_id'] = "TestAndroidAppV0";
        $pairs['client_secret'] = "kshdiouehruyiwuresuygr736t4763b7637"; // dummy
        $pairs['device_id'] = get_serial_number();

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER] = array("Content-Type: application/json; charset=utf-8");
        $curl_opt[CURLOPT_POSTFIELDS] = escaped_raw_json_encode($pairs);

        $data = $this->execApiCommand($cmd, null, true, $curl_opt);
        if (isset($data->access_token)) {
            hd_debug_print("token requested", true);
            HD::set_cookie($token_file, $data->access_token, $data->expires_time);
            HD::set_cookie($refresh_token_file, $data->refresh_token, PHP_INT_MAX, true);
            return true;
        }

        hd_debug_print("token not received: " . raw_json_encode($data), true);
        HD::set_last_error("rq_last_error", TR::load_string('err_cant_get_token') . "\n\n" . raw_json_encode($data));
        return false;
    }

    /**
     * @inheritDoc
     */
    public function load_playlist($tmp_file)
    {
        hd_debug_print(null, true);

        $data = parent::load_playlist(null);

        if (isset($data->success, $data->data)) {
            return Curl_Wrapper::simple_download_file($data->data, $tmp_file, false);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $token = HD::get_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()));
        $string = str_replace(MACRO_TOKEN, $token, $string);

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
        } else if (!isset($account_info->status) || $account_info->status !== 200) {
            Control_Factory::add_label($defs, TR::t('err_error'), $account_info->message, -10);
        } else if (isset($account_info->data)) {
            $data = $account_info->data;
            if (isset($data->login)) {
                Control_Factory::add_label($defs, TR::t('login'), $data->login, -15);
            }

            if (isset($data->balance, $data->currency)) {
                Control_Factory::add_label($defs, TR::t('balance'), $data->balance . " " . $data->currency, -15);
            }

            if (isset($data->server_name)) {
                Control_Factory::add_label($defs, TR::t('server'), $data->server_name, -15);
            }

            if (isset($data->selected_playlist->title)) {
                Control_Factory::add_label($defs, TR::t('playlist'), $data->selected_playlist->title, -15);
            }

            if (isset($data->subscriptions)) {
                $packages = '';
                foreach ($data->subscriptions as $subscription) {
                    $packages .= $subscription->name . PHP_EOL;
                    $packages .= TR::load_string('end_date') . " " . $subscription->end_date . PHP_EOL;
                    $packages .= TR::load_string('recurring') . " " .
                        ($subscription->auto_prolong ? TR::load_string('yes') : TR::load_string('no')) . PHP_EOL;
                }
                Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
            }
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

        if (empty($this->device)) {
            $response = $this->execApiCommand(API_COMMAND_GET_DEVICE);
            hd_debug_print("GetServers: " . raw_json_encode($response), true);
            if (isset($response->status) && $response->status === 200) {
                $this->device = $response;
            }
        }

        if (empty($this->servers)) {
            $this->collect_servers($selected);
            if ($selected !== $this->getCredential(MACRO_SERVER_ID)) {
                $this->setCredential(MACRO_SERVER_ID, $selected);
            }
        }

        return $this->servers;
    }

    /**
     * @inheritDoc
     */
    public function SetServer($server, &$error_msg)
    {
        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER] = array("Content-Type: application/json; charset=utf-8");
        $curl_opt[CURLOPT_POSTFIELDS] = escaped_raw_json_encode(array("server_location" => $server));

        $response = $this->execApiCommand(API_COMMAND_SET_DEVICE, null, true, $curl_opt);
        if (isset($response->status) && $response->status === 200) {
            $this->device = $response;
            $this->collect_servers($selected);
            $this->setCredential(MACRO_SERVER_ID, $selected);
            $this->account_info = null;
            return true;
        }

        hd_debug_print("Can't set device: " . json_encode($response));

        $error_msg = '';

        return false;
    }

    /**
     * returns list of account playlists
     * @return array|null
     */
    public function GetPlaylists()
    {
        hd_debug_print(null, true);

        if (empty($this->device)) {
            $data = $this->execApiCommand(API_COMMAND_GET_DEVICE);
            if (isset($data->status) && $data->status === 200) {
                $this->device = $data;
            }
        }

        if (empty($this->playlists)) {
            $this->collect_playlists($selected);
            if ($selected !== $this->getCredential(MACRO_PLAYLIST_ID)) {
                $this->setCredential(MACRO_PLAYLIST_ID, $selected);
            }
        }

        return $this->playlists;
    }

    /**
     * set server
     * @param string $id
     * @return void
     */
    public function SetPlaylist($id)
    {
        hd_debug_print(null, true);
        hd_debug_print("SetPlaylist: $id");

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER] = array("Content-Type: application/json; charset=utf-8");
        $curl_opt[CURLOPT_POSTFIELDS] = escaped_raw_json_encode(array("user_playlists" => $id));

        $response = $this->execApiCommand(API_COMMAND_SET_DEVICE, null, true, $curl_opt);
        if (isset($response->status) && $response->status === 200) {
            $this->device = $response;
            $this->collect_playlists($selected);
            parent::SetPlaylist($selected);
            $this->account_info = null;
        } else {
            hd_debug_print("Can't set playlist: " . json_encode($response));
        }
    }

    /**
     * collect servers information
     * @param string $selected
     * @return array
     */
    protected function collect_servers(&$selected = "-1")
    {
        $this->servers = array();

        if (isset($this->device->device->settings->server_location->value)) {
            foreach ($this->device->device->settings->server_location->value as $server) {
                $this->servers[(string)$server->id] = $server->label;
                if ($server->selected) {
                    $selected = (string)$server->id;
                }
            }
        }

        return $this->servers;
    }

    /**
     * collect playlists information
     * @param string &$selected
     * @return array
     */
    protected function collect_playlists(&$selected = "-1")
    {
        $this->playlists = array();

        if (isset($this->device->device->settings->user_playlists->value)) {
            foreach ($this->device->device->settings->user_playlists->value as $playlist) {
                $idx = (string)$playlist->id;
                $this->playlists[$idx]['name'] = $playlist->label;
                if ($playlist->selected) {
                    $selected = $idx;
                }
            }
        }

        return $this->playlists;
    }
}
