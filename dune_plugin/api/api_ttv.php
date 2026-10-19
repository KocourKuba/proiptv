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
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $session_id = HD::get_cookie(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
        $token = HD::get_cookie(sprintf(self::TOKEN_FILE, $this->get_provider_playlist_id()));
        $string = str_replace(
            array(MACRO_SESSION_ID, MACRO_TOKEN, MACRO_GUID),
            array($session_id, $token, $this->get_guid()),
            $string);

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

        if (empty($this->account_info) || $force) {
            $this->request_user_info();
        }

        return $this->account_info;
    }

    /**
     * Both the session id and the playlist token are obtained by the login/password pair:
     * the session id by the auth request, the playlist token from the account info.
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

        if (empty($session_id)) {
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
        }

        if (!$this->request_user_info() || empty($this->account_info->playlist_token)) {
            hd_debug_print("Can't get playlist token from account info", true);
            HD::set_last_error("rq_last_error", TR::load_string('err_cant_get_token'));
            return false;
        }

        return true;
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

    ///////////////////////////////////////////////////////////////////////

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
        $serial = get_serial_number();
        if (empty($serial)) {
            $serial = 'PROIPTV';
        }

        $hash = md5("torrenttv:$serial");
        return sprintf('%s-%s-%s-%s-%s',
            substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }
}
