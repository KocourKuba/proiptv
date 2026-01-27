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
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        if ($force || empty($this->account_info)) {
            $this->request_provider_info($force);
        }

        $list_domain = safe_get_value($this->account_info, array('data', 'listdomain'));
        if (!empty($list_domain)) {
            $this->SetProviderParameter(MACRO_PL_DOMAIN_ID, $list_domain);
        }

        $epg_domain = safe_get_value($this->account_info, array('data', 'jsonEpgDomain'));
        if (isset($epg_domain)) {
            $this->SetProviderParameter(MACRO_EPG_DOMAIN, $epg_domain);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function GetInfoUI($handler)
    {
        $this->request_provider_info();

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if ($this->hasApiCommand(API_COMMAND_PAY)) {
            Control_Factory::add_button($defs, $handler, ACTION_ADD_MONEY_DLG,
                "", TR::t('add_money'), null, 450, true);
        }

        if (empty($this->account_info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('warn_msg3'), null, -10);
        } else if (isset($this->account_info['status']) && (int)$this->account_info['status'] !== 1) {
            Control_Factory::add_label($defs, TR::t('error'), $this->account_info['status'], -10);
        } else {
            $data = safe_get_value($this->account_info, 'data', array());
            if (isset($data['login'])) {
                Control_Factory::add_label($defs, TR::t('login'), $data['login'], -15);
            }
            if (isset($data['money'], $data['currency'])) {
                Control_Factory::add_label($defs, TR::t('balance'), $data['money'] . " " . $data['currency'], -15);
            }
            if (isset($data['money_need'], $data['currency'])) {
                Control_Factory::add_label($defs, TR::t('money_need'), "{$data['money_need']} {$data['currency']}", -15);
            }

            $packages = '';
            foreach (safe_get_value($data, 'abon', array()) as $package) {
                $packages .= $package . PHP_EOL;
            }
            if (!empty($packages)) {
                Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog($defs, TR::t('subscription'));
    }

    /**
     * @inheritDoc
     */
    public function GetPayUI()
    {
        try {
            $img = tempnam(get_temp_path() . '.png', '');
            if ($this->execApiCommandFile(API_COMMAND_PAY, $img) === false) {
                return null;
            }

            Control_Factory::add_vgap($defs, 20);

            if (file_exists($img)) {
                Control_Factory::add_smart_label($defs, "", "<gap width=25/><icon width=450 height=450>$img</icon>");
                Control_Factory::add_vgap($defs, 450);
            } else {
                Control_Factory::add_smart_label($defs, "", "<text>" . TR::t('err_incorrect_access_data') . "</text>");
                Control_Factory::add_vgap($defs, 50);
            }

            return Action_Factory::show_dialog($defs, TR::t("add_money"), Action_Factory::SMALL_DLG_WIDTH);
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
            $response = $this->execApiCommandResponseNoOpt(API_COMMAND_GET_SERVERS, Curl_Wrapper::RET_ARRAY);
            hd_debug_print("GetServers: " . json_format_unescaped($response), true);
            if (isset($response['status'])) {
                foreach (safe_get_value($response, 'allow_nums', array()) as $server) {
                    if (isset($server['id'])) {
                        $this->servers[(int)$server['id']] = safe_get_value($server, 'name', 'unknown');
                    }
                }

                $this->SetProviderParameter(MACRO_SERVER_ID, $response['current']);
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

        $response = $this->execApiCommandResponseNoOpt(API_COMMAND_SET_SERVER, Curl_Wrapper::RET_ARRAY);
        $status = (int)safe_get_value($response, 'status', 0);
        if ($status !== 1) {
            return false;
        }

        $this->servers = array();
        return true;
    }
}
