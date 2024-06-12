<?php
require_once 'api_default.php';

class api_iptvonline extends api_default
{
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

        $token = $this->getCredential(MACRO_TOKEN);
        $expired = time() > (int)$this->getCredential(MACRO_EXPIRE_DATA);
        if (!$force && !empty($token) && !$expired) {
            hd_debug_print("request not required", true);
            return true;
        }

        $refresh_token = $this->getCredential(MACRO_REFRESH_TOKEN);
        $refresh = $expired && !empty($refresh_token);
        if ($refresh) {
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
            $this->setCredential(MACRO_TOKEN, $data->access_token);
            $this->setCredential(MACRO_REFRESH_TOKEN, $data->refresh_token);
            $this->setCredential(MACRO_EXPIRE_DATA, $data->expires_time);
            $this->save_credentials();
            return true;
        }

        hd_debug_print("token not received: " . json_encode($data), true);
        return false;
    }

    /**
     * @param $tmp_file string
     * @return bool
     */
    public function load_playlist($tmp_file)
    {
        hd_debug_print(null, true);

        $data = parent::load_playlist(null);

        if (isset($data->success, $data->data)) {
            return HD::http_download_https_proxy($data->data, $tmp_file);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function fill_default_info($matches, &$hash)
    {
        $info = parent::fill_default_info($matches, $hash);

        $info->params[MACRO_TOKEN] = '';
        $info->params[MACRO_REFRESH_TOKEN] = '';
        $info->params[MACRO_EXPIRE_DATA] = 0;

        return $info;
    }

    /**
     * @inheritDoc
     */
    public function GetInfoUI($handler)
    {
        parent::GetInfoUI($handler);

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (empty($this->account_info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('warn_msg3'), null, -10);
        } else if (!isset($this->account_info->status) || $this->account_info->status !== 200) {
            Control_Factory::add_label($defs, TR::t('err_error'), $this->account_info->message, -10);
        } else if (isset($this->account_info->data)) {
            $data = $this->account_info->data;
            if (isset($data->login)) {
                Control_Factory::add_label($defs, TR::t('login'), $data->login, -15);
            }

            if (isset($data->balance, $data->currency)) {
                Control_Factory::add_label($defs, TR::t('balance'), "$data->balance $data->currency", -15);
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
                    $packages .= TR::load_string('end_date') . ": $subscription->end_date" . PHP_EOL;
                    $packages .= TR::load_string('recurring') . ": " .
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
        parent::GetServers();

        hd_debug_print(null, true);

        if (empty($this->device)) {
            $data = $this->execApiCommand(API_COMMAND_GET_DEVICE);
            if (isset($data->status) && $data->status === 200) {
                $this->device = $data;
            }
        }

        $servers = $this->collect_servers($selected);
        if ($selected !== $this->getCredential(MACRO_SERVER_ID)) {
            $this->setCredential(MACRO_SERVER_ID, $selected);
        }
        return $servers;
    }

    /**
     * @inheritDoc
     */
    public function SetServer($server)
    {
        parent::SetServer($server);

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER] = array("Content-Type: application/json; charset=utf-8");
        $curl_opt[CURLOPT_POSTFIELDS] = escaped_raw_json_encode(array("server_location" => $server));

        $response = $this->execApiCommand(API_COMMAND_SET_DEVICE, null, true, $curl_opt);
        if (isset($response->status) && $response->status === 200) {
            $this->device = $response;
            $this->collect_servers($selected);
            $this->setCredential(MACRO_SERVER_ID, $selected);
            $this->account_info = null;
        }
    }

    /**
     * returns list of account playlists
     * @return array|null
     */
    public function GetPlaylists()
    {
        hd_debug_print(null, true);

        $this->get_provider_info();

        if (empty($this->device)) {
            $data = $this->execApiCommand(API_COMMAND_GET_DEVICE);
            if (isset($data->status) && $data->status === 200) {
                $this->device = $data;
            }
        }

        $playlists = $this->collect_playlists($selected);
        if ($selected !== $this->getCredential(MACRO_PLAYLIST_ID)) {
            $this->setCredential(MACRO_PLAYLIST_ID, $selected);
        }

        return $playlists;
    }

    /**
     * set server
     * @param $id
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
        $servers = array();
        if (isset($this->device->device->settings->server_location->value)) {
            foreach ($this->device->device->settings->server_location->value as $server) {
                $servers[(string)$server->id] = $server->label;
                if ($server->selected) {
                    $selected = (string)$server->id;
                }
            }
        }

        return $servers;
    }

    /**
     * collect playlists information
     * @param string &$selected
     * @return array
     */
    protected function collect_playlists(&$selected = "-1")
    {
        $playlists = array();
        if (isset($this->device->device->settings->user_playlists->value)) {
            foreach ($this->device->device->settings->user_playlists->value as $playlist) {
                $idx = (string)$playlist->id;
                $playlists[$idx]['name'] = $playlist->label;
                if ($playlist->selected) {
                    $selected = $idx;
                }
            }
        }

        return $playlists;
    }
}
