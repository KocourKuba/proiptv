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
    /**
     * @var array
     */
    protected $servers = array();

    /**
     * @inheritDoc
     */
    public function GetSessionId()
    {
        return md5($this->GetProviderParameter(MACRO_LOGIN) . md5($this->GetProviderParameter(MACRO_PASSWORD)));
    }

    public function GetInfoUI($handler)
    {
        $account_info = $this->get_provider_info();

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (empty($account_info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else if (isset($account_info->account)) {
            $data = $account_info->account;
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

        return Action_Factory::show_dialog($defs, TR::t('subscription'));
    }

    /**
     * @inheritDoc
     */
    public function GetServers()
    {
        hd_debug_print(null, true);

        if (empty($this->servers)) {
            $response = $this->execApiCommandResponseNoOpt(API_COMMAND_GET_SERVERS, Curl_Wrapper::RET_OBJECT);
            hd_debug_print("GetServers: " . json_format_unescaped($response), true);
            if (isset($response->servers)) {
                foreach ($response->servers as $server) {
                    $this->servers[(int)$server->id] = $server->name;
                }

                if (isset($this->account_info->account->settings->server_id)) {
                    $this->SetProviderParameter(MACRO_SERVER_ID, (int)$this->account_info->account->settings->server_id);
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
        parent::SetServer($server, $error_msg);

        $response = $this->execApiCommandResponseNoOpt(API_COMMAND_SET_SERVER, Curl_Wrapper::RET_OBJECT);
        if (isset($response->settings->current->server->id)) {
            $this->servers = array();
            $this->account_info = null;
            return true;
        }

        if (isset($response->error->msg)) {
            $error_msg = $response->error->msg;
        }

        return false;
    }
}
