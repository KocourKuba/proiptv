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
    const SESSION_FILE = "%s.session_id";
    const TOKEN_FILE = "%s.token";

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $hash_password = md5($this->getCredential(MACRO_PASSWORD));
        $session_id = HD::get_cookie(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
        $token = HD::get_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()), true);
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
                $name = isset($this->servers[$info->groupId]) ? $this->servers[$info->groupId] : 'Not set';
                Control_Factory::add_label($defs, TR::t('server'), $name, -15);
            }

            if (isset($info->showPorno)) {
                Control_Factory::add_label($defs, TR::t('disable_adult'), $info->showPorno ? TR::t('no') : TR::t('yes'), -15);
            }

            if (isset($account_info->data->userPackagesList)) {
                $packages = '';
                foreach ($account_info->data->userPackagesList as $package) {
                    $packages .= TR::load_string('package') . " " . $package->packageName . PHP_EOL;
                    $packages .= TR::load_string('start_date') . " " . $package->fromDate . PHP_EOL;
                    $packages .= TR::load_string('end_date') . " " . $package->toDate . PHP_EOL;
                    $packages .= TR::load_string('money_need') . " " . "$package->salePrice$" . PHP_EOL;
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
            hd_debug_print("Failed to get provider token", true);
            return null;
        }

        if (empty($this->account_info) || $force) {
            $this->account_info = $this->execApiCommand(API_COMMAND_ACCOUNT_INFO);
            hd_debug_print("get provider info response: " . pretty_json_format($this->account_info), true);

            if (isset($this->account_info->data->userData->userToken)) {
                HD::set_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()),
                    $this->account_info->data->userData->userToken,
                    PHP_INT_MAX,
                    true);
            }

            if (isset($this->account_info->data->userData->groupId)) {
                $this->setCredential(MACRO_SERVER_ID, $this->account_info->data->userData->groupId);
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

        $session_file = sprintf(self::SESSION_FILE, $this->get_provider_playlist_id());
        $session_id = HD::get_cookie($session_file);
        $expired = empty($session_id);

        if (!$force && !$expired) {
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
            hd_debug_print("request provider token response: " . pretty_json_format($response), true);
            if ($response->status === 0 || !empty($response->error)) {
                HD::set_last_error("pl_last_error", $response->error);
                HD::set_last_error("rq_last_error", $response->error);
            } else if (isset($response->data->sessionId)) {
                HD::set_cookie($session_file, $response->data->sessionId, time() + 86400 * 7);
                HD::set_last_error("rq_last_error", null);

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
        $old = $this->getCredential(MACRO_SERVER_ID);
        $this->setCredential(MACRO_SERVER_ID, $server);

        $response = $this->execApiCommand(API_COMMAND_SET_SERVER);
        hd_debug_print("SetServer: " . pretty_json_format($response), true);
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
