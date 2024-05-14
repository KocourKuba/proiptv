<?php
require_once 'api_default.php';

/**
 * "account": {
 *      "info": {
 *      "id": 9405,
 *      "login": "igore",
 *      "mail": "info@igore.ru",
 *      "name": "Igor Het",
 *      "balance": 0
 *      },
 *      "options": {
 *           "archive": 1
 *      },
 *      "services": [
 *           {
 *               "id": 1,
 *               "expire": 1731771536,
 *               "name": "Premium",
 *               "type": "TV package"
 *           },
 *           {
 *               "id": 20,
 *               "expire": 1731771536,
 *               "name": "Multiroom",
 *               "type": "Option"
 *           },
 *           {
 *               "id": 20,
 *               "expire": 1731771536,
 *               "name": "Multiroom",
 *               "type": "Option"
 *           },
 *           {
 *               "id": 21,
 *               "expire": 1731771536,
 *               "name": "TV-CLUB",
 *               "type": "Playlist"
 *           }
 *      ],
 *      "settings": {
 *           "server_id": 1,
 *           "server_name": "Server EU1",
 *           "tz_name": "Europe/Prague",
 *           "tz_gmt": "+01:00"
 *      }
 * },
 * "server": {
 *      "time": 1700235536
 * }
 *
 */

class api_tvclub extends api_default
{
    public function GetInfoUI($handler)
    {
        parent::GetInfoUI($handler);

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (empty($this->info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else if (isset($this->info->account)) {
            $data = $this->info->account;
            if (isset($data->info)) {
                $info = $data->info;
                if (isset($info->login)) {
                    Control_Factory::add_label($defs, TR::t('login'), $info->login, -15);
                }
                if (isset($info->name)) {
                    Control_Factory::add_label($defs, TR::t('name'), $info->name, -15);
                }
                if (isset($info->balance)) {
                    Control_Factory::add_label($defs, TR::t('balance'), $info->balance, -15);
                }

                if (isset($opts->options, $opts->options->archive)) {
                    Control_Factory::add_label($defs, TR::t('archive_support'), $opts->options->archive ? TR::t('yes') : TR::t('no'), -15);
                }
            }

            if (isset($data->settings)) {
                $settings = $data->settings;
                if (isset($settings->server_id, $settings->server_name)) {
                    Control_Factory::add_label($defs, TR::t('server'), "$settings->server_id ($settings->server_name)", -15);
                }
                if (isset($settings->time_zone, $settings->tz_gmt, $settings->tz_name)) {
                    Control_Factory::add_label($defs, TR::t('time_zone'), "$settings->tz_gmt ($settings->tz_name)", -15);
                }
            }

            if (isset($data->services)) {
                foreach ($data->services as $service) {
                    if (isset($service->type, $service->name, $service->expire)) {
                        $date = date('d M Y H:i', $service->expire);
                        Control_Factory::add_label($defs, $service->type, "$service->name ($date)", -15);
                    }
                }
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
        $response = $this->execApiCommand(API_COMMAND_SERVERS);
        if ($response === false) {
            hd_debug_print("Can't get servers status");
        } else {
            $data = HD::decodeResponse(false, $response);
            if ($data === false || !isset($data->servers)) {
                hd_debug_print("Wrong response on command: " . API_COMMAND_SERVERS);
            } else {
                foreach ($data->servers as $server) {
                    $servers[(int)$server->id] = $server->name;
                }

                if (isset($this->info->account->settings->server_id)) {
                    $this->setCredential(MACRO_SERVER_ID, (int)$this->info->account->settings->server_id);
                }
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
            } else if (isset($data->settings->current->server->id)) {
                $this->info = null;
            }
        }
    }
}
