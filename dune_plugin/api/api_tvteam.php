<?php
require_once 'api_default.php';

class api_tvteam extends api_default
{
    const SESSION_FILE = "%s_session_id";

    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $session_file = get_temp_path(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
        $expired = true;
        if (file_exists($session_file)) {
            $session_id = file_get_contents($session_file);
            $expired = time() > filemtime($session_file);
            if ($expired) {
                unlink($session_file);
            }
        }

        if (!$force && !empty($session_id) && !$expired) {
            hd_debug_print("request not required", true);
            return true;
        }

        // remove old settings
        $res = $this->removeCredential(MACRO_SESSION_ID);
        $res |= $this->removeCredential(MACRO_EXPIRE_DATA);
        if ($res) {
            $this->save_credentials();
        }

        $error_msg = HD::check_last_error('rq_last_error');
        if (!$force && !empty($error_msg)) {
            $info_msg = str_replace('|', PHP_EOL, TR::load_string('err_auth_no_spam'));
            hd_debug_print($info_msg);
            HD::set_last_error("pl_last_error", "$info_msg\n\n$error_msg");
        } else {
            HD::set_last_error("pl_last_error", null);
            HD::set_last_error("rq_last_error", null);
            $response = $this->execApiCommand(API_COMMAND_REQUEST_TOKEN);
            hd_debug_print("request provider token response: " . raw_json_encode($response), true);
            if ($response->status === 0 || !empty($response->error)) {
                HD::set_last_error("pl_last_error", $response->error);
                HD::set_last_error("rq_last_error", $response->error);
            } else if (isset($response->data->sessionId)) {
                file_put_contents($session_file, $response->data->sessionId);
                touch($session_file, time() + 86400);
                HD::set_last_error("rq_last_error", null);

                return true;
            }
        }

        return false;
    }

    /**
     * @param bool $force
     * @return bool|object
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force get_provider_info: " . var_export($force, true), true);

        if (empty($this->account_info) || $force) {
            $this->account_info = $this->execApiCommand(API_COMMAND_ACCOUNT_INFO);
            if (isset($this->account_info->data->userData->userToken)) {
                $this->setCredential(MACRO_TOKEN, $this->account_info->data->userData->userToken);
                $this->save_credentials();
            }
            hd_debug_print("get provider info response: " . raw_json_encode($this->account_info), true);
        }

        return $this->account_info;
    }

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $hash_password = md5($this->getCredential(MACRO_PASSWORD));
        $session_file = get_temp_path(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
        $session_id = file_exists($session_file) ? file_get_contents($session_file) : '';
        $token = $this->getCredential(MACRO_TOKEN);

        $string = str_replace(
            array(MACRO_SESSION_ID, MACRO_HASH_PASSWORD, MACRO_TOKEN),
            array($session_id, $hash_password, $token),
            $string);

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
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else if (isset($account_info->data->userData)) {
            $info = $account_info->data->userData;
            if (isset($info->userLogin)) {
                Control_Factory::add_label($defs, TR::t('login'), $info->userLogin, -15);
            }

            if (isset($info->userEmail)) {
                Control_Factory::add_label($defs, TR::t('name'), $info->userEmail, -15);
            }

            if (isset($info->userBalance)) {
                Control_Factory::add_label($defs, TR::t('balance'), $info->userBalance, -15);
            }

            if (isset($info->groupId)) {
                if (empty($this->servers)) {
                    $this->GetServers();
                }
                $name = isset($this->servers[$info->groupId]) ? $this->servers[$info->groupId] : 'Not set';
                Control_Factory::add_label($defs, TR::t('server'), $name, -15);
            }

            if (isset($info->showPorno)) {
                Control_Factory::add_label($defs, TR::t('disable_adult'), $info->showPorno ? TR::t('no') : TR::t('yes'), -15);
            }

            if (empty($this->packages)) {
                $response = $this->execApiCommand(API_COMMAND_GET_PACKAGES);
                if (isset($response->data->userPackagesList)) {
                    $this->packages = $response->data->userPackagesList;
                }
            }

            $packages = '';
            foreach ($this->packages as $package) {
                $packages .= TR::load_string('package') . " " . $package->packageId . PHP_EOL;
                $packages .= TR::load_string('start_date') . " " . $package->fromDate . PHP_EOL;
                $packages .= TR::load_string('end_date') . " " . $package->toDate . PHP_EOL;
                $packages .= TR::load_string('package_timed') . " " . TR::load_string($package->packageIsTimed ? 'yes' : 'no') . PHP_EOL;
                $packages .= TR::load_string('money_need') . " " . $package->salePrice . PHP_EOL;
            }
            Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
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
            hd_debug_print("GetServers: " . raw_json_encode($response), true);
            if (((int)$response->status === 1) && isset($response->status, $response->data->serversGroupsList)) {
                foreach ($response->data->serversGroupsList as $server) {
                    $this->servers[$server->groupId] = "$server->portalDomainName ($server->streamDomainName)";
                }
            }

            if (isset($this->account_info->data->userData->groupId)) {
                $this->setCredential(MACRO_SERVER_ID, $this->account_info->data->userData->groupId);
            }
        }

        return $this->servers;
    }

    /**
     * @inheritDoc
     */
    public function SetServer($server, &$error_msg)
    {
        $old = $this->getCredential(MACRO_SERVER_ID);
        $this->setCredential(MACRO_SERVER_ID, $server);

        $response = $this->execApiCommand(API_COMMAND_SET_SERVER);
        hd_debug_print("SetServer: " . raw_json_encode($response), true);
        if (isset($response->status) && (int)$response->status === 1) {
            $this->account_info = null;
            $this->servers = array();
            return true;
        }

        $this->setCredential(MACRO_SERVER_ID, $old);
        if (isset($response->error)) {
            $error_msg = $response->error;
        }

        return false;
    }
}
