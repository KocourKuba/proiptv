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

class api_tvteam extends api_default
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
        $hash_password = md5($this->GetParameter(MACRO_PASSWORD));
        $session_id = $this->plugin->get_cookie(PARAM_SESSION_ID);
        $token = $this->plugin->get_cookie(PARAM_TOKEN);
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
                Control_Factory::add_label($defs, TR::t('balance'), "$info->userBalance$", -15);
            }

            if (isset($info->groupId)) {
                $name = safe_get_value($this->servers, $info->groupId, 'Not set');
                Control_Factory::add_label($defs, TR::t('server'), $name, -15);
            }

            if (isset($info->showPorno)) {
                Control_Factory::add_label($defs, TR::t('disable_adult'), $info->showPorno ? TR::t('no') : TR::t('yes'), -15);
            }

            if (isset($account_info->data->userPackagesList)) {
                $packages = '';
                foreach ($account_info->data->userPackagesList as $package) {
                    $packages .= TR::load('package__1', $package->packageName) . PHP_EOL;
                    $packages .= TR::load('start_date__1', $package->fromDate) . PHP_EOL;
                    $packages .= TR::load('end_date__1', $package->toDate) . PHP_EOL;
                    $packages .= TR::load('money_need__1', "$package->salePrice$") . PHP_EOL;
                }
                Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog(TR::t('subscription'), $defs, true, 1000, null /*$attrs*/);
    }

    /**
     * @param bool $force
     * @return bool|object
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force get_provider_info: " . var_export($force, true), true);

        if (!$this->request_provider_token()) {
            hd_debug_print("Failed to get provider token");
            return null;
        }

        if (!$this->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
            $this->account_info = array();
        } else if (empty($this->account_info) || $force) {
            $this->account_info = $this->execApiCommand(API_COMMAND_ACCOUNT_INFO);
            hd_debug_print("get provider info response: " . pretty_json_format($this->account_info), true);

            if (isset($this->account_info->data->userData->userToken)) {
                $this->plugin->set_cookie(PARAM_TOKEN, $this->account_info->data->userData->userToken);
            }

            if (isset($this->account_info->data->userData->groupId)) {
                $this->SetParameter(MACRO_SERVER_ID, $this->account_info->data->userData->groupId);
            }

            if (isset($this->account_info->data->serversGroupsList)) {
                foreach ($this->account_info->data->serversGroupsList as $server) {
                    $this->servers[$server->groupId] = "$server->groupCountry ($server->streamDomainName)";
                }
            }
        }

        return $this->account_info;
    }

    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $session_id = $this->plugin->get_cookie(PARAM_SESSION_ID, true);
        $expired = empty($session_id);

        if (!$force && !$expired) {
            hd_debug_print("request not required", true);
            return true;
        }

        $pl_last_error = $this->plugin->get_pl_error_name();
        $rq_last_error = $this->plugin->get_request_error_name();
        $error_msg = HD::check_last_error($rq_last_error);
        if (!$force && !empty($error_msg)) {
            $info_msg = str_replace('|', PHP_EOL, TR::load('err_auth_no_spam'));
            hd_debug_print($info_msg);
            HD::set_last_error($pl_last_error, "$info_msg\n\n$error_msg");
        } else {
            HD::set_last_error($pl_last_error, null);
            HD::set_last_error($pl_last_error, null);
            $response = $this->execApiCommand(API_COMMAND_REQUEST_TOKEN);
            hd_debug_print("request provider token response: " . pretty_json_format($response), true);
            if (!$response) {
                HD::set_last_error($pl_last_error, "Bad provider response");
                HD::set_last_error($rq_last_error, "Bad provider response");
            } else if ($response->status === 0 || !empty($response->error)) {
                HD::set_last_error($pl_last_error, $response->error);
                HD::set_last_error($rq_last_error, $response->error);
            } else if (isset($response->data->sessionId)) {
                $this->plugin->set_cookie(PARAM_SESSION_ID, $response->data->sessionId, time() + 86400 * 7);
                HD::set_last_error($rq_last_error, null);

                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function SetServer($server, &$error_msg)
    {
        parent::SetServer($server, $error_msg);

        $response = $this->execApiCommand(API_COMMAND_SET_SERVER);
        hd_debug_print("SetServer: " . pretty_json_format($response), true);
        if (isset($response->status) && (int)$response->status === 1) {
            $this->account_info = null;
            $this->servers = array();
            return true;
        }

        if (isset($response->error)) {
            $error_msg = $response->error;
        }

        return false;
    }
}
