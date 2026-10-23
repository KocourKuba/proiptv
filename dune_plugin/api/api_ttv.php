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
 * TorrentTV (https://ttvrun.one/)
 *
 * The account is set up by the login/password pair only:
 * request_token returns the session id used by all other API commands,
 * account_info returns the playlist token ({TOKEN} macro of the get_playlist command).
 * The playlist token is absent if the account has no TV subscription, VOD is available without it.
 * Every response contains 'success' (1 on success) and 'error' (empty string on success) fields.
 *
 * "success": 1,
 * "error": "",
 * "email": "user@mail.com",
 * "balance": "100.00",
 * "vip_status": 1,
 * "tsproxy_date": 1767225600,
 * "tsproxy_tariff": "TS Proxy",
 * "playlist_token": "abcdef0123456789"
 */
class api_ttv extends api_default
{
    const SESSION_LIFETIME = 604800; // 7 days
    const AUTH_RETRY_TIMEOUT = 300; // 5 min
    const COOKIE_ACCOUNT = 'ttv_account';

    /**
     * Session id used when the playlist database is not attached (new or not active playlist)
     *
     * @var string
     */
    protected $session_id = '';

    /**
     * Playlist token used when the playlist database is not attached (new or not active playlist)
     *
     * @var string
     */
    protected $token = '';

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $string = str_replace(
            array(MACRO_SESSION_ID, MACRO_TOKEN, MACRO_GUID),
            array($this->get_session(), $this->get_token(), $this->get_guid()),
            $string);

        return parent::replace_macros($string);
    }

    /**
     * Provider responses always contain the 'error' field,
     * so the account info can't be checked by the default implementation
     *
     * @inheritDoc
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force get_provider_info: " . var_export($force, true), true);

        if (!$this->request_provider_token()) {
            hd_debug_print("Failed to get provider token");
            return null;
        }

        if (empty($this->account_info) || $force) {
            $this->request_user_info();
        }

        return $this->account_info;
    }

    /**
     * The session id is requested by the login/password pair, the playlist token is taken from the account info.
     * With force flag the stored session is verified by the account info request and requested again if rejected.
     *
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $attached = $this->is_playlist_db_attached();
        if ($attached && $this->plugin->get_cookie(self::COOKIE_ACCOUNT) !== $this->get_account_hash()) {
            // session and playlist token are stored for other credentials
            hd_debug_print("credentials changed, reset session", true);
            $this->clear_session(true);
        }

        $session_id = $this->get_session();
        if (!$force && !empty($session_id)) {
            hd_debug_print("request not required", true);
            return true;
        }

        // last error is stored for the active playlist only
        $rq_last_error = $attached ? $this->plugin->get_request_error_name() : null;
        if ($attached) {
            if (!$force && self::is_auth_blocked($rq_last_error)) {
                hd_debug_print("Previous token request failed!");
                return false;
            }

            HD::set_last_error($rq_last_error, null);
        }

        // the stored session can be invalidated by the provider at any time,
        // in this case the account info request fails and a new session is requested
        if (!empty($session_id) && $this->request_user_info()) {
            return true;
        }

        $this->clear_session();
        $response = $this->execApiCommand(API_COMMAND_REQUEST_TOKEN);
        hd_debug_print("request provider token response: " . pretty_json_format($response), true);
        if (!isset($response->success, $response->session) || (int)$response->success !== 1 || empty($response->session)) {
            if ($attached) {
                HD::set_last_error($rq_last_error, empty($response->error) ? TR::load('err_cant_get_token') : $response->error);
            }
            return false;
        }

        $this->set_session($response->session);

        if (!$this->request_user_info()) {
            if ($attached) {
                HD::set_last_error($rq_last_error, TR::load('err_cant_get_token'));
            }
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function ApplySetupUI($user_input)
    {
        hd_debug_print(null, true);

        // session of the previous credentials must not be used
        $this->clear_session(true);

        return parent::ApplySetupUI($user_input);
    }

    /**
     * Playlist is not available without the playlist token (account without TV subscription)
     *
     * @inheritDoc
     */
    public function load_playlist($tmp_file)
    {
        hd_debug_print(null, true);

        $token = $this->get_token();
        if (empty($token) && $this->request_user_info()) {
            $token = $this->get_token();
        }

        if (empty($token)) {
            hd_debug_print("No playlist token in the account info");
            return false;
        }

        return parent::load_playlist($tmp_file);
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
        } else {
            if (!empty($account_info->email)) {
                Control_Factory::add_label($defs, TR::t('login'), $account_info->email, -15);
            }

            if (isset($account_info->balance)) {
                Control_Factory::add_label($defs, TR::t('balance'), $account_info->balance, -15);
            }

            $vip = isset($account_info->vip_status) && (int)$account_info->vip_status !== 0;
            Control_Factory::add_label($defs, TR::t('vip_status'), $vip ? TR::t('yes') : TR::t('no'), -15);

            if (!empty($account_info->tsproxy_date)) {
                $tariff = empty($account_info->tsproxy_tariff) ? '' : "$account_info->tsproxy_tariff - ";
                $date = format_datetime('Y-m-d', (int)$account_info->tsproxy_date);
                Control_Factory::add_label($defs, TR::t('packages'), $tariff . TR::load('end_date__1', $date), -15);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog(TR::t('subscription'), $defs, true, 1100);
    }

    /**
     * The pay command returns the link to the QR code image of the one-time payment page
     *
     * @inheritDoc
     */
    public function GetPayUI()
    {
        try {
            $response = $this->request_pay_info();
            $img = get_temp_path('ttv_pay_qr.png');
            if (file_exists($img)) {
                unlink($img);
            }

            if (!empty($response->qr)) {
                $curl_wrapper = Curl_Wrapper::getInstance();
                $this->plugin->set_curl_timeouts($curl_wrapper);
                $curl_wrapper->download_file($response->qr, $img, false);
            }

            $defs = array();
            Control_Factory::add_vgap($defs, 20);

            if (file_exists($img)) {
                Control_Factory::add_smart_label($defs, "", "<gap width=25/><icon width=450 height=450>$img</icon>");
                Control_Factory::add_vgap($defs, 450);
            } else {
                Control_Factory::add_smart_label($defs, "", "<text>" . TR::t('err_incorrect_access_data') . "</text>");
                Control_Factory::add_vgap($defs, 50);
            }

            return Action_Factory::show_dialog(TR::t("add_money"), $defs, true, 600);
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        return null;
    }

    /**
     * Execute API command, a session rejected by the provider is requested again once
     *
     * @param string $command
     * @param array $curl_opt
     * @return bool|object
     */
    public function execTtvCommand($command, $curl_opt = array())
    {
        $response = $this->execApiCommand($command, null, true, $curl_opt);
        if (isset($response->success, $response->error) && (int)$response->success !== 1 && $response->error === 'incorrect') {
            hd_debug_print("session rejected for $command, request new session", true);
            $this->clear_session();
            if ($this->request_provider_token(true)) {
                $response = $this->execApiCommand($command, null, true, $curl_opt);
            }
        }

        return $response;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @return object|null
     */
    protected function request_pay_info()
    {
        if (!$this->request_provider_token()) {
            return null;
        }

        $response = $this->execTtvCommand(API_COMMAND_PAY);
        if (!isset($response->success) || (int)$response->success !== 1) {
            hd_debug_print("pay request failed: " . pretty_json_format($response), true);
            return null;
        }

        return $response;
    }

    /**
     * Request the account info by the current session and store the playlist token from it
     *
     * @return bool
     */
    protected function request_user_info()
    {
        $response = $this->execApiCommand(API_COMMAND_ACCOUNT_INFO);
        hd_debug_print("get provider info response: " . pretty_json_format($response), true);
        if (!isset($response->success) || (int)$response->success !== 1) {
            hd_debug_print("Failed to get provider info");
            return false;
        }

        $this->account_info = $response;
        $this->set_token(empty($response->playlist_token) ? '' : $response->playlist_token);

        return true;
    }

    /**
     * Session and playlist token are stored in the cookies of the playlist database if it attached.
     * Otherwise (new or not active playlist) they are kept in the memory only
     * to not overwrite the cookies of the active playlist.
     *
     * @return string
     */
    protected function get_session()
    {
        if ($this->is_playlist_db_attached()) {
            return (string)$this->plugin->get_cookie(PARAM_SESSION_ID, true);
        }

        return $this->session_id;
    }

    /**
     * @param string $session_id
     * @return void
     */
    protected function set_session($session_id)
    {
        if ($this->is_playlist_db_attached()) {
            $this->plugin->set_cookie(PARAM_SESSION_ID, $session_id, time() + self::SESSION_LIFETIME);
            $this->plugin->set_cookie(self::COOKIE_ACCOUNT, $this->get_account_hash(), PHP_INT_MAX);
        } else {
            $this->session_id = $session_id;
        }
    }

    /**
     * @return string
     */
    protected function get_token()
    {
        if ($this->is_playlist_db_attached()) {
            return (string)$this->plugin->get_cookie(PARAM_TOKEN, true);
        }

        return $this->token;
    }

    /**
     * @param string $token
     * @return void
     */
    protected function set_token($token)
    {
        if (!$this->is_playlist_db_attached()) {
            $this->token = $token;
        } else if (empty($token)) {
            $this->plugin->remove_cookie(PARAM_TOKEN);
        } else {
            $this->plugin->set_cookie(PARAM_TOKEN, $token, time() + self::SESSION_LIFETIME);
        }
    }

    /**
     * @param bool $with_token clear the playlist token too
     * @return void
     */
    protected function clear_session($with_token = false)
    {
        $this->session_id = '';
        if ($with_token) {
            $this->token = '';
        }

        if ($this->is_playlist_db_attached()) {
            $this->plugin->remove_cookie(PARAM_SESSION_ID);
            if ($with_token) {
                $this->plugin->remove_cookie(PARAM_TOKEN);
            }
        }
    }

    /**
     * Hash of the credentials the stored session belongs to
     *
     * @return string
     */
    protected function get_account_hash()
    {
        return md5($this->GetParameter(MACRO_LOGIN) . ':' . $this->GetParameter(MACRO_PASSWORD));
    }

    /**
     * Failed auth is not repeated automatically during the timeout to avoid the account blocking
     *
     * @param string $rq_last_error
     * @return bool
     */
    protected static function is_auth_blocked($rq_last_error)
    {
        $error_file = get_temp_path($rq_last_error);
        return file_exists($error_file) && filemtime($error_file) > time() - self::AUTH_RETRY_TIMEOUT;
    }

    /**
     * Stable device GUID required by the auth request
     *
     * @return string
     */
    protected function get_guid()
    {
        $guid = $this->GetParameter(MACRO_GUID);
        if (empty($guid)) {
            $serial = get_serial_number();
            $hash = md5("torrenttv:" . (empty($serial) ? 'PROIPTV' : $serial));
            $guid = substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-' . substr($hash, 12, 4)
                . '-' . substr($hash, 16, 4) . '-' . substr($hash, 20, 12);
            $this->SetParameter(MACRO_GUID, $guid);
        }

        return $guid;
    }
}
