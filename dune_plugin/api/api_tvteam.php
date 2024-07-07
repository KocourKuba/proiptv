<?php
require_once 'api_default.php';

class api_tvteam extends api_default
{
    /** @var array  */
    protected $servers = array();

    /**
     * @param bool $force
     * @return bool
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);

        $session = $this->getCredential(MACRO_SESSION_ID);
        $expired = time() > (int)$this->getCredential(MACRO_EXPIRE_DATA) + 3600;
        if (!$force && !empty($session) && !$expired) {
            hd_debug_print("request not required", true);
            return true;
        }

        $params[CURLOPT_CUSTOMREQUEST] = md5($this->getCredential(MACRO_PASSWORD));
        $response = $this->execApiCommand(API_COMMAND_REQUEST_TOKEN, null, true, $params);
        hd_debug_print("request provider token response: " . raw_json_encode($response), true);
        if ($response->status === 0 || !empty($response->error)) {
            HD::set_last_error("pl_last_error", $response->error);
        } else if (isset($response->data->sessionId)) {
            $this->setCredential(MACRO_SESSION_ID, $response->data->sessionId);
            $this->setCredential(MACRO_EXPIRE_DATA, time());
            $this->save_credentials();
            return true;
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

        if (!$this->request_provider_token()) {
            hd_debug_print("Failed to get session id");
            return false;
        }

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
                $name = isset($this->servers[$info->groupId]) ? $this->servers[$info->groupId] : 'Not set';
                Control_Factory::add_label($defs, TR::t('server'), $name, -15);
            }

            if (isset($info->showPorno)) {
                Control_Factory::add_label($defs, TR::t('disable_adult'), $info->showPorno ? TR::t('no') : TR::t('yes'), -15);
            }

            $response = $this->execApiCommand(API_COMMAND_GET_PACKAGES);
            if (isset($response->data->userPackagesList)) {
                $packages = '';
                foreach ($response->data->userPackagesList as $package) {
                    $packages .= TR::load_string('package') . " " . $package->packageId . PHP_EOL;
                    $packages .= TR::load_string('start_date') . " " . $package->fromDate . PHP_EOL;
                    $packages .= TR::load_string('end_date') . " " . $package->toDate . PHP_EOL;
                    $packages .= TR::load_string('package_timed') . " " . TR::load_string($package->packageIsTimed ? 'yes' : 'no') . PHP_EOL;
                    $packages .= TR::load_string('money_need') . " " . $package->salePrice . PHP_EOL;
                }
                Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
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
            hd_debug_print("GetServers: " . raw_json_encode($response), true);
            if (((int)$response->status === 1) && isset($response->status, $response->data->serversGroupsList)) {
                foreach ($response->data->serversGroupsList as $server) {
                    $this->servers[(int)$server->groupId] = "$server->portalDomainName ($server->streamDomainName)";
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
            return true;
        }

        $this->setCredential(MACRO_SERVER_ID, $old);
        if (isset($response->error)) {
            $error_msg = $response->error;
        }

        return false;
    }
}
