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
    public function GetInfoUI($handler)
    {
        parent::GetInfoUI($handler);

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (empty($this->info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else if (isset($this->info->error, $this->info->error->message)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), $this->info->error->message, -10);
        } else if (isset($this->info->account)) {
            $data = $this->info->account;
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
                        $packages .= $package->name . " (" . date('d M Y H:i', $package->expire) . ")\n";
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
        parent::GetServers();

        hd_debug_print(null, true);
        $servers = array();
        $data = $this->execApiCommand(API_COMMAND_SERVERS);
        if (isset($data->servers)) {
            foreach ($data->servers as $server) {
                $servers[(int)$server->id] = $server->name;
            }

            if (isset($this->info->account->settings->server_id)) {
                $this->setCredential(MACRO_SERVER_ID, (int)$this->info->account->settings->server_id);
            }
        }

        return $servers;
    }

    /**
     * @inheritDoc
     */
    public function SetServer($server)
    {
        parent::SetServer($server);

        $response = $this->execApiCommand(API_COMMAND_SET_SERVER);
        if ($response) {
            $data = HD::decodeResponse(false, $response);
            if (empty($data)) {
                hd_debug_print("Wrong response on command: " . API_COMMAND_SET_SERVER);
            } else if (isset($data->settings->value)) {
                $this->info = null;
            }
        }
    }
}
