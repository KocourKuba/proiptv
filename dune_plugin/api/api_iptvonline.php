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
     * @var object
     */
    protected $device;

    /**
     * @var array
     */
    protected $servers = array();

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

            Default_Dune_Plugin::get_last_error(LAST_ERROR_REQUEST, false);
            if (!empty($error)) {
                hd_debug_print("Previous token request failed!");
                return false;
            }
        }

        Default_Dune_Plugin::clear_last_error(LAST_ERROR_REQUEST);

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
            $pairs['login'] = $this->GetParameter(MACRO_LOGIN);
            $pairs['password'] = $this->GetParameter(MACRO_PASSWORD);
        }

        $pairs['client_id'] = "TestAndroidAppV0";
        $pairs['client_secret'] = "kshdiouehruyiwuresuygr736t4763b7637"; // dummy
        $pairs['device_id'] = get_serial_number();

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER][] = CONTENT_TYPE_JSON;
        $curl_opt[CURLOPT_POSTFIELDS] = json_encode($pairs);

        $data = $this->execApiCommand($cmd, null, true, $curl_opt);
        if (isset($data->access_token)) {
            hd_debug_print("token requested", true);
            $this->plugin->set_cookie(PARAM_TOKEN, $data->access_token, $data->expires_time);
            $this->plugin->set_cookie(PARAM_REFRESH_TOKEN, $data->refresh_token, PHP_INT_MAX);
            return true;
        }

        if ($can_refresh && isset($data->error)) {
            // refresh token failed. Need to make complete auth
            $this->plugin->remove_cookie(PARAM_TOKEN);
            $this->plugin->remove_cookie(PARAM_REFRESH_TOKEN);
            return $this->request_provider_token(true);
        }

        hd_debug_print("token not received: " . pretty_json_format($data), true);
        Default_Dune_Plugin::set_last_error(LAST_ERROR_REQUEST, TR::load('err_cant_get_token') . "\n" . pretty_json_format($data));
        return false;
    }

    /**
     * @inheritDoc
     */
    public function load_playlist($tmp_file)
    {
        hd_debug_print(null, true);

        $response = $this->make_json_request(API_COMMAND_GET_PLAYLIST);

        if (isset($response->success, $response->data)) {
            $curl_wrapper = Curl_Wrapper::getInstance();
            $this->plugin->set_curl_timeouts($curl_wrapper);
            return $curl_wrapper->download_file($response->data, $tmp_file, true);
        }

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
                    $packages .= TR::load('end_date__1', $subscription->end_date) . PHP_EOL;
                    $packages .= TR::load('recurring__1', TR::load($subscription->auto_prolong ? 'yes' : 'no')) . PHP_EOL;
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
            $response = $this->make_json_request(API_COMMAND_GET_DEVICE);
            hd_debug_print("GetServers: " . pretty_json_format($response), true);
            if (isset($response->status) && $response->status === 200) {
                $this->device = $response;
            }
        }

        if (empty($this->servers)) {
            $this->collect_servers($selected);
            if ($selected !== $this->GetParameter(MACRO_SERVER_ID)) {
                $this->SetParameter(MACRO_SERVER_ID, $selected);
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
     * @inheritDoc
     */
    public function SetServer($server, &$error_msg)
    {
        $curl_params[CURLOPT_POST] = true;
        $curl_params[CURLOPT_POSTFIELDS] = array("server_location" => $server);

        $response = $this->make_json_request(API_COMMAND_SET_DEVICE, $curl_params);
        if (isset($response->status) && $response->status === 200) {
            $this->device = $response;
            $this->collect_servers($selected);
            $this->account_info = null;
            parent::SetServer($selected, $error_msg);
            return true;
        }

        hd_debug_print("Can't set device: " . json_encode($response));
        if (isset($response->message)) {
            $error_msg = $response->message;
        } else {
            $error_msg = pretty_json_format($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

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
            $response = $this->make_json_request(API_COMMAND_GET_DEVICE);
            if (isset($response->status) && $response->status === 200) {
                $this->device = $response;
            }
        }

        if (empty($this->playlists)) {
            $this->collect_playlists($selected);
            if ($selected !== $this->GetParameter(MACRO_PLAYLIST_ID)) {
                $this->SetParameter(MACRO_PLAYLIST_ID, $selected);
            }
        }

        return $this->playlists;
    }

    /**
     * collect playlists information
     * @param string &$selected
     * @return void
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

        $this->playlists[DIRECT_PLAYLIST_ID][COLUMN_NAME] = TR::load('setup_native_url');
        $this->playlists[DIRECT_PLAYLIST_ID][COLUMN_URL] = '';
    }

    /**
     * @inheritDoc
     */
    public function SetPlaylist($id)
    {
        hd_debug_print(null, true);
        hd_debug_print("SetPlaylist: $id");

        $curl_params[CURLOPT_POST] = true;
        $curl_params[CURLOPT_POSTFIELDS] = array("user_playlists" => $id);

        $response = $this->make_json_request(API_COMMAND_SET_DEVICE, $curl_params);
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

        $playlists = $this->GetPlaylists();
        if (!empty($playlists)) {
            $idx = $this->GetParameter(MACRO_PLAYLIST_ID);
            if (empty($idx)) {
                $this->SetParameter(MACRO_PLAYLIST_ID, (string)key($playlists));
            }
        }

        $playlists_vod = $this->GetPlaylistsVod();
        if (!empty($playlists_vod)) {
            $idx = $this->GetParameter(MACRO_PLAYLIST_VOD_ID);
            if (empty($idx)) {
                $this->SetParameter(MACRO_PLAYLIST_VOD_ID, (string)key($playlists_vod));
            }
        }
    }

    /**
     * @param string $cmd
     * @param array|null $params
     * @return bool|object
     */
    protected function make_json_request($cmd, $params = null)
    {
        $curl_opt = array();

        if (isset($params[CURLOPT_CUSTOMREQUEST])) {
            $curl_opt[CURLOPT_CUSTOMREQUEST] = $params[CURLOPT_CUSTOMREQUEST];
        }

        if (isset($params[CURLOPT_POSTFIELDS])) {
            $curl_opt[CURLOPT_HTTPHEADER][] = CONTENT_TYPE_JSON;
            $curl_opt[CURLOPT_POSTFIELDS] = json_encode($params[CURLOPT_POSTFIELDS]);
        }

        return $this->execApiCommand($cmd, null, true, $curl_opt);
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
