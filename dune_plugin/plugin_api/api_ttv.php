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
 * The api_commands section defines the templates for all API requests: request_token and
 * account_info are used by api_ttv, film_* are used by vod_ttv. {GUID} is replaced by replace_macros().
 * The account is set up by the login/password pair only: the session id is used by the VOD and the account
 * info, the playlist token (the {TOKEN} macro of the playlist url) is taken from the account info.
 * The playlists_vod url is a placeholder: the VOD class loads the catalog through the API commands.
 */
class api_ttv extends api_default
{
    const SESSION_LIFETIME = 604800; // 7 days

    /**
     * @var string
     */
    protected $guid = '';

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $string = parent::replace_macros($string);

        return str_replace(MACRO_GUID, $this->getGuid(), $string);
    }

    /**
     * The provider responses always contain the 'error' field (empty string on success),
     * so the account info must be collected with the TorrentTV envelope check.
     *
     * @inheritDoc
     */
    public function request_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print('force request_provider_info: ' . var_export($force, true));

        if (empty($this->account_info)) {
            $force = true;
        }

        if (!$force) {
            return;
        }

        $this->account_info = null;
        if (!$this->request_provider_token()) {
            hd_debug_print('Failed to get provider token');
            return;
        }

        // the token request fills the account info only when it requests a new session
        if (empty($this->account_info)) {
            $this->request_user_info();
        }
    }

    /**
     * The session of the old credentials must not be used to get the playlist token
     *
     * @inheritDoc
     */
    public function ApplySetupUI($user_input)
    {
        hd_debug_print(null, true);

        $this->RemoveProviderCookie(PARAM_SESSION_ID);

        return parent::ApplySetupUI($user_input);
    }

    /**
     * Both the session id and the playlist token are obtained by the login/password pair:
     * the session id by the auth request, the playlist token from the account info.
     * The force flag is ignored: a missing or expired session is always requested again.
     *
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print('force request provider token: ' . var_export($force, true));

        $session_id = $this->GetProviderCookie(PARAM_SESSION_ID, true);
        $token = $this->GetProviderCookie(PARAM_TOKEN, true);
        if (!empty($session_id) && !empty($token)) {
            hd_debug_print('session is still valid', true);
            return true;
        }

        Dune_Last_Error::clear_last_error(LAST_ERROR_REQUEST);

        if (empty($session_id) && !$this->request_session()) {
            return false;
        }

        if (!$this->request_user_info()) {
            Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, TR::load('err_cant_get_token'));
            return false;
        }

        $token = safe_get_value($this->account_info, 'playlist_token');
        if (empty($token)) {
            hd_debug_print('Empty playlist token in account info', true);
            Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, TR::load('err_cant_get_token'));
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function add_account_info_defs(&$defs, $handler)
    {
        $this->request_provider_info();

        if ($this->hasApiCommand(API_COMMAND_PAY)) {
            Control_Factory::add_button($defs, $handler, ACTION_ADD_MONEY_DLG,
                "", TR::t('add_money'), null, 450, true);
        }

        if (empty($this->account_info)) {
            return false;
        }

        $email = safe_get_value($this->account_info, 'email');
        if (!empty($email)) {
            Control_Factory::add_label($defs, TR::t('login'), $email, -15);
        }

        Control_Factory::add_label($defs, TR::t('balance'), safe_get_value($this->account_info, 'balance', ''), -15);

        $vip = (int)safe_get_value($this->account_info, 'vip_status', 0);
        Control_Factory::add_label($defs, TR::t('package'), $vip ? TR::load('yes') : TR::load('no'), -15);

        $tsproxy_date = (int)safe_get_value($this->account_info, 'tsproxy_date', 0);
        if ($tsproxy_date > 0) {
            $tariff = safe_get_value($this->account_info, 'tsproxy_tariff');
            $date = format_datetime('Y-m-d', $tsproxy_date);
            Control_Factory::add_label($defs, TR::t('end_date'), (empty($tariff) ? '' : "$tariff - ") . $date, -15);
        }

        return true;
    }

    /**
     * The payment_qr response contains the one-time link of the payment page and the link of its QR code image.
     * The link carries the payment token, so the QR code is taken from the provider and never made by HD::get_qr_code().
     *
     * @inheritDoc
     */
    public function GetPayUI()
    {
        hd_debug_print(null, true);

        try {
            $response = $this->request_payment_qr();
            $qr_url = safe_get_value($response, 'qr');

            $img = get_temp_path('ttv_pay_qr.png');
            if (file_exists($img)) {
                unlink($img);
            }

            $defs = array();
            Control_Factory::add_vgap($defs, 20);

            if (!empty($qr_url) && Curl_Wrapper::getInstance()->download_file($qr_url, $img) && file_exists($img)) {
                HD::add_qr_code_defs($defs, $img, Action_Factory::SMALL_DLG_WIDTH);
            } else {
                Control_Factory::add_smart_label($defs, "<text>" . TR::t('err_incorrect_access_data') . "</text>");
                Control_Factory::add_vgap($defs, 50);
            }

            return Action_Factory::show_dialog($defs, TR::t('add_money'), Action_Factory::SMALL_DLG_WIDTH);
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Request the payment link, a session rejected by the provider is requested again once
     *
     * @return array|null
     */
    protected function request_payment_qr()
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if (!$this->request_provider_token()) {
                return null;
            }

            $response = $this->execApiCommandResponseNoOpt(API_COMMAND_PAY);
            if ($response === false) {
                return null;
            }

            if ((int)safe_get_value($response, 'success') === 1) {
                return $response;
            }

            hd_debug_print('payment_qr error response: ' . json_format_unescaped($response), true);
            if (safe_get_value($response, 'error') !== 'incorrect') {
                return null;
            }

            $this->RemoveProviderCookie(PARAM_SESSION_ID);
        }

        return null;
    }

    /**
     * Request a new session by the login/password pair and store it
     *
     * @return bool
     */
    protected function request_session()
    {
        $response = $this->execApiCommandResponse(API_COMMAND_REQUEST_TOKEN, array(), Curl_Wrapper::RET_ARRAY);
        if ($response === false) {
            Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, TR::load('err_cant_get_token'));
            return false;
        }

        $error = safe_get_value($response, 'error');
        if ((int)safe_get_value($response, 'success') !== 1 || !empty($error)) {
            hd_debug_print('request provider token bad response: ' . json_format_unescaped($response), true);
            Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, $error);
            return false;
        }

        $session_id = safe_get_value($response, 'session');
        if (empty($session_id)) {
            hd_debug_print('Empty session in response: ' . json_format_unescaped($response), true);
            Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, TR::load('err_cant_get_token'));
            return false;
        }

        $this->SetProviderCookie(PARAM_SESSION_ID, $session_id, time() + self::SESSION_LIFETIME);
        return true;
    }

    /**
     * Request the account info by the current session and store the playlist token from it.
     * A session rejected by the server is requested again once.
     *
     * @param bool $renew_session
     * @return bool
     */
    protected function request_user_info($renew_session = true)
    {
        $response = $this->execApiCommandResponseNoOpt(API_COMMAND_ACCOUNT_INFO);
        if ($response === false) {
            hd_debug_print('Failed to request provider info');
            return false;
        }

        if ((int)safe_get_value($response, 'success') !== 1) {
            hd_debug_print('request_provider_info error response: ' . json_format_unescaped($response), true);
            if ($renew_session && safe_get_value($response, 'error') === 'incorrect') {
                hd_debug_print('ttv session rejected, request new session', true);
                $this->RemoveProviderCookie(PARAM_SESSION_ID);
                $this->RemoveProviderCookie(PARAM_TOKEN);
                return $this->request_session() && $this->request_user_info(false);
            }
            return false;
        }

        hd_debug_print('request_provider_info: ' . json_format_unescaped($response), true);
        $this->account_info = $response;

        $token = safe_get_value($response, 'playlist_token');
        if (!empty($token)) {
            $this->SetProviderCookie(PARAM_TOKEN, $token, time() + self::SESSION_LIFETIME);
        }

        return true;
    }

    /**
     * Stable GUID of the device for the TorrentTV auth.
     *
     * @return string
     */
    protected function getGuid()
    {
        if (!empty($this->guid)) {
            return $this->guid;
        }

        $guid = $this->GetProviderParameter(MACRO_GUID);
        if (empty($guid)) {
            $serial = get_serial_number();
            if (empty($serial)) {
                $serial = 'PROIPTV';
            }

            $hash = md5("torrenttv:$serial");
            $guid = sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
            $this->SetProviderParameter(MACRO_GUID, $guid);
        }

        $this->guid = $guid;

        return $guid;
    }
}
