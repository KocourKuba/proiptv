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
 * TorrentTV provider (https://ttvrun.one/)
 *
 * The account is set up by the login/password pair only: the session id obtained by the auth request
 * is used by the VOD and the account info, the playlist token ({TOKEN} macro of the playlist url)
 * is taken from the account info.
 */
class api_ttv extends api_default
{
    const SESSION_FILE = "%s.session_id";
    const TOKEN_FILE = "%s.token";
    const SESSION_LIFETIME = 604800; // 7 days

    /**
     * @var string
     */
    protected $guid;

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        // the cookies are read only if the template uses them
        if (strpos($string, MACRO_SESSION_ID) !== false) {
            $session_id = HD::get_cookie(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
            $string = str_replace(MACRO_SESSION_ID, $session_id, $string);
        }

        if (strpos($string, MACRO_TOKEN) !== false) {
            $token = HD::get_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()));
            $string = str_replace(MACRO_TOKEN, $token, $string);
        }

        if (strpos($string, MACRO_GUID) !== false) {
            $string = str_replace(MACRO_GUID, $this->get_guid(), $string);
        }

        return parent::replace_macros($string);
    }

    /**
     * @inheritDoc
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force get_provider_info: " . var_export($force, true), true);

        if (!$this->request_provider_token()) {
            hd_debug_print("Failed to get provider token", true);
            return null;
        }

        if ((empty($this->account_info) || $force) && !$this->request_user_info()) {
            // the session can be invalidated by the server at any time, request a new one
            hd_debug_print("Account info is not available, request new session", true);
            $this->request_provider_token(true);
        }

        return $this->account_info;
    }

    /**
     * Both the session id and the playlist token are obtained by the login/password pair:
     * the session id by the auth request, the playlist token from the account info.
     * If the stored session is rejected by the server, a new session is requested once.
     *
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $session_file = sprintf(self::SESSION_FILE, $this->get_provider_playlist_id());
        if ($force) {
            HD::clear_cookie($session_file);
        }

        $session_id = HD::get_cookie($session_file);
        $token = HD::get_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()));
        if (!empty($session_id) && !empty($token)) {
            hd_debug_print("request not required", true);
            return true;
        }

        HD::set_last_error("rq_last_error", null);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $new_session = empty($session_id);
            if ($new_session && !$this->request_session($session_file)) {
                return false;
            }

            if ($this->request_user_info() && !empty($this->account_info->playlist_token)) {
                return true;
            }

            if ($new_session) {
                break;
            }

            // the stored session is not accepted anymore, request a new one
            hd_debug_print("Stored session rejected, request new session", true);
            HD::clear_cookie($session_file);
            $session_id = '';
        }

        hd_debug_print("Can't get playlist token from account info", true);
        HD::set_last_error("rq_last_error", TR::load_string('err_cant_get_token'));
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear_session_info()
    {
        HD::clear_cookie(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
        HD::clear_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()));
        $this->account_info = null;
    }

    /**
     * @inheritDoc
     */
    public function load_playlist($tmp_file)
    {
        hd_debug_print(null, true);

        if (!$this->request_provider_token()) {
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
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else {
            if (!empty($account_info->email)) {
                Control_Factory::add_label($defs, TR::t('login'), $account_info->email, -15);
            }

            if (isset($account_info->balance)) {
                Control_Factory::add_label($defs, TR::t('balance'), $account_info->balance, -15);
            }

            $vip = isset($account_info->vip_status) ? (int)$account_info->vip_status : 0;
            Control_Factory::add_label($defs, TR::t('package'), $vip ? TR::t('yes') : TR::t('no'), -15);

            $tsproxy_date = isset($account_info->tsproxy_date) ? (int)$account_info->tsproxy_date : 0;
            if ($tsproxy_date > 0) {
                $tariff = empty($account_info->tsproxy_tariff) ? '' : "$account_info->tsproxy_tariff - ";
                Control_Factory::add_label($defs, TR::t('end_date'), $tariff . format_datetime('Y-m-d', $tsproxy_date), -15);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog(TR::t('subscription'), $defs, true, 1000);
    }

    /**
     * The payment_qr response contains the one-time link of the payment page and the link of its QR code image.
     * The link carries the payment token, so the QR code image is taken from the provider.
     *
     * @inheritDoc
     */
    public function GetPayUI()
    {
        hd_debug_print(null, true);

        try {
            $response = $this->request_payment_qr();
            $qr_url = isset($response->qr) ? $response->qr : '';

            $img = get_temp_path('ttv_pay_qr.png');
            if (file_exists($img)) {
                unlink($img);
            }

            $defs = array();
            Control_Factory::add_vgap($defs, 20);

            $res = false;
            if (!empty($qr_url)) {
                list($res,) = Curl_Wrapper::simple_download_file($qr_url, $img);
            }

            if ($res && file_exists($img)) {
                HD::add_qr_code_defs($defs, $img, Action_Factory::SMALL_DLG_WIDTH);
            } else {
                Control_Factory::add_smart_label($defs, "", "<text>" . TR::load_string('err_incorrect_access_data') . "</text>");
                Control_Factory::add_vgap($defs, 50);
            }

            return Action_Factory::show_dialog(TR::t("add_money"), $defs, true, Action_Factory::SMALL_DLG_WIDTH);
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Request the payment link, a session rejected by the provider is requested again once
     *
     * @return Object|null
     */
    protected function request_payment_qr()
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if (!$this->request_provider_token($attempt > 0)) {
                return null;
            }

            $response = $this->execApiCommand(API_COMMAND_PAY);
            if ($response === false || $response === null) {
                return null;
            }

            if (isset($response->success) && (int)$response->success === 1) {
                return $response;
            }

            hd_debug_print("payment_qr error response: " . json_encode($response), true);
            if (!isset($response->error) || $response->error !== 'incorrect') {
                return null;
            }
        }

        return null;
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
            hd_debug_print("Failed to request provider info");
            return false;
        }

        $this->account_info = $response;
        if (!empty($response->playlist_token)) {
            HD::set_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()),
                $response->playlist_token,
                time() + self::SESSION_LIFETIME);
        }

        return true;
    }

    /**
     * Stable GUID of the device for the TorrentTV auth
     *
     * @return string
     */
    protected function get_guid()
    {
        if (empty($this->guid)) {
            $serial = get_serial_number();
            if (empty($serial)) {
                $serial = 'PROIPTV';
            }

            $hash = md5("torrenttv:$serial");
            $this->guid = sprintf('%s-%s-%s-%s-%s',
                substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
        }

        return $this->guid;
    }

    /**
     * Request a new session by the login/password pair and store it
     *
     * @param string $session_file
     * @return bool
     */
    protected function request_session($session_file)
    {
        $response = $this->execApiCommand(API_COMMAND_REQUEST_TOKEN);
        hd_debug_print("request provider token response: " . pretty_json_format($response), true);
        if ($response === false || $response === null) {
            HD::set_last_error("rq_last_error", TR::load_string('err_cant_get_token'));
            return false;
        }

        if (!isset($response->success) || (int)$response->success !== 1 || !empty($response->error)) {
            HD::set_last_error("rq_last_error", isset($response->error) ? $response->error : TR::load_string('err_cant_get_token'));
            return false;
        }

        if (empty($response->session)) {
            hd_debug_print("Empty session in response", true);
            HD::set_last_error("rq_last_error", TR::load_string('err_cant_get_token'));
            return false;
        }

        HD::set_cookie($session_file, $response->session, time() + self::SESSION_LIFETIME);
        return true;
    }
}
