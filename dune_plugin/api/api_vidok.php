<?php
require_once 'api_default.php';

/**
 * "account": {
 *      "login": "sharky",
 *      "balance": "0",
 *      "tz ": "Europe/Prague",
 *      "first_name": "Pavel",
 *      "last_name": "Abakanov",
 *      "city": "Praha",
 *      "country": "CZ",
 *      "packages": [
 *          {
 *              "id": 7,
 *              "type": "1",
 *              "name": "Premium",
 *              "expire": "1702831941"
 *          },
 *          {
 *              "id": 4,
 *              "type": "2",
 *              "name": "Playlist",
 *              "expire": "1702831941"
 *          },
 *          {
 *              "id": 5,
 *              "type": "3",
 *              "name": "Multiroom",
 *              "expire": "1702831941"
 *          },
 *          {
 *              "id": 5,
 *              "type": "3",
 *              "name": "Multiroom",
 *              "expire": "1702831941"
 *          }
 *      ]
 * },
 * "servertime": 1700240140
 */

class api_vidok extends api_default
{
    /**
     * @param bool $force
     * @return bool
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);

        $gen_token = md5(strtolower($this->getCredential(MACRO_LOGIN)) . md5($this->getCredential(MACRO_PASSWORD)));
        $this->setCredential(MACRO_TOKEN, $gen_token);

        return true;
    }

    public function GetInfoUI($handler)
    {
        $account_info = $this->get_provider_info();

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (empty($account_info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else if (isset($account_info->error, $account_info->error->message)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), $account_info->error->message, -10);
        } else if (isset($this->account_info->account)) {
            $data = $account_info->account;
            if (isset($data->login)) {
                Control_Factory::add_label($defs, TR::t('login'), $data->login, -15);
            }
            if (isset($data->first_name, $data->last_name)) {
                Control_Factory::add_label($defs, TR::t('name'), "$data->first_name $data->last_name", -15);
            }
            if (isset($data->city, $data->country)) {
                Control_Factory::add_label($defs, TR::t('city'), "$data->city ($data->country)", -15);
            }
            if (isset($data->tz)) {
                Control_Factory::add_label($defs, TR::t('time_zone'), $data->tz, -15);
            }
            if (isset($data->balance)) {
                Control_Factory::add_label($defs, TR::t('balance'), $data->balance, -15);
            }

            if (isset($data->packages)) {
                $packages = '';
                foreach ($data->packages as $package) {
                    if (isset($package->name, $package->expire)) {
                        $packages .= $package->name . " (" . date('d M Y H:i', $package->expire) . ")" . PHP_EOL;
                    }
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
        $servers = array();
        $data = $this->execApiCommand(API_COMMAND_GET_SERVERS);
        if (isset($data->servers)) {
            foreach ($data->servers as $server) {
                $servers[(int)$server->id] = $server->name;
            }

            if (isset($this->account_info->account->settings->server_id)) {
                $this->setCredential(MACRO_SERVER_ID, (int)$this->account_info->account->settings->server_id);
            }
        }

        return $servers;
    }

    /**
     * @inheritDoc
     */
    public function SetServer($server, &$error_msg)
    {
        $old = $this->getCredential(MACRO_SERVER_ID);
        $this->setCredential(MACRO_SERVER_ID, $server);

        $response = $this->execApiCommand(API_COMMAND_SET_SERVER);
        if (isset($response->settings->value)) {
            $this->account_info = null;
            return true;
        }

        $this->setCredential(MACRO_SERVER_ID, $old);

        $error_msg = '';
        return false;
    }
}
