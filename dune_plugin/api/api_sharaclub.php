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
 * "status": "1",
 * "data": {
 *      "login": "sharky",
 *      "money": "334.52",
 *      "money_need": "298",
 *      "currency": "RUB",
 *      "vod": true,
 *      "abon": [
 *          "Кино и Сериалы **",
 *          "Россия",
 *          "Кино плюс",
 *          "Europe (De, Fr, Pol + Tur)"
 *       ]
 * }
 */

class api_sharaclub extends api_default
{
    /**
     * @var array
     */
    protected $servers = array();

    /**
     * @param bool $force
     * @return bool|object
     */
    public function get_provider_info($force = false)
    {
        parent::get_provider_info($force);

        if (isset($this->account_info->data->listdomain)) {
            $this->setParameter(MACRO_PLAYLIST, $this->account_info->data->listdomain);
        }

        if (isset($this->account_info->data->jsonEpgDomain)) {
            $this->setParameter(MACRO_EPG_DOMAIN, $this->account_info->data->jsonEpgDomain);
        }

        return $this->account_info;
    }

    /**
     * @inheritDoc
     */
    public function GetInfoUI($handler)
    {
        $account_info = $this->get_provider_info();

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if ($this->hasApiCommand(API_COMMAND_PAY)) {
            Control_Factory::add_button($defs, $handler, null,
                ACTION_ADD_MONEY_DLG, "", TR::t('add_money'), 450, true);
        }

        if (empty($account_info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('warn_msg3'), null, -10);
        } else if (isset($account_info->status) && (int)$account_info->status !== 1) {
            Control_Factory::add_label($defs, TR::t('err_error'), $account_info->status, -10);
        } else if (isset($account_info->data)) {
            $data = $account_info->data;
            if (isset($data->login)) {
                Control_Factory::add_label($defs, TR::t('login'), $data->login, -15);
            }
            if (isset($data->money, $data->currency)) {
                Control_Factory::add_label($defs, TR::t('balance'), $data->money . " " . $data->currency, -15);
            }
            if (isset($data->money_need, $data->currency)) {
                Control_Factory::add_label($defs, TR::t('money_need'), $data->money_need . " " . $data->currency, -15);
            }

            if (isset($data->abon)) {
                $packages = '';
                foreach ($data->abon as $package) {
                    $packages .= $package . PHP_EOL;
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
    public function GetPayUI()
    {
        try {
            $img = tempnam(get_temp_path() . '.png', '');
            if ($this->execApiCommand(API_COMMAND_PAY, $img) === false) {
                return null;
            }

            Control_Factory::add_vgap($defs, 20);

            if (file_exists($img)) {
                Control_Factory::add_smart_label($defs, "", "<gap width=25/><icon width=450 height=450>$img</icon>");
                Control_Factory::add_vgap($defs, 450);
            } else {
                Control_Factory::add_smart_label($defs, "", "<text>" . TR::load('err_incorrect_access_data') . "</text>");
                Control_Factory::add_vgap($defs, 50);
            }

            return Action_Factory::show_dialog(TR::t("add_money"), $defs, true, 600);
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        return null;
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
            if (isset($response->status)) {
                foreach ($response->allow_nums as $server) {
                    $this->servers[(int)$server->id] = $server->name;
                }

                $this->playlist_info[PARAM_PARAMS][MACRO_SERVER_ID] = (int)$response->current;
            }
        }

        return $this->servers;
    }

    /**
     * @inheritDoc
     */
    public function SetServer($server, &$error_msg)
    {
        $old = $this->getParameter(MACRO_SERVER_ID);
        $this->playlist_info[PARAM_PARAMS][MACRO_SERVER_ID] = $server;

        $response = $this->execApiCommand(API_COMMAND_SET_SERVER);
        if (isset($response->status) && (int)$response->status === 1) {
            $this->servers = array();
            return true;
        }

        $this->playlist_info[PARAM_NAME][MACRO_SERVER_ID] = $old;
        $error_msg = '';
        return false;
    }

    /**
     * @inheritDoc
     */
    public function set_provider_defaults()
    {
        $servers = $this->GetServers();
        if (!empty($servers)) {
            $idx = $this->getParameter(MACRO_SERVER_ID);
            if (empty($idx)) {
                $this->playlist_info[PARAM_PARAMS][MACRO_SERVER_ID] = key($servers);
            }
        }
    }
}
