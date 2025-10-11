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
     * @var array
     */
    protected $servers = array();

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $token = md5(strtolower($this->GetProviderParameter(MACRO_LOGIN)) . md5($this->GetProviderParameter(MACRO_PASSWORD)));
        $string = str_replace(MACRO_SESSION_ID, $token, $string);

        return parent::replace_macros($string);
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

        if (empty($this->servers)) {
            $response = $this->execApiCommand(API_COMMAND_GET_SERVERS);
            hd_debug_print("GetServers: " . pretty_json_format($response), true);
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

        $response = $this->execApiCommand(API_COMMAND_SET_SERVER);
        if (isset($response->settings->value)) {
            $this->servers = array();
            $this->account_info = null;
            return true;
        }

        return false;
    }
}
