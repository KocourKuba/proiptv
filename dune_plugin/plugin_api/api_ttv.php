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
 * The playlists_vod url is a placeholder: the VOD class loads the catalog through the API commands.
 */
class api_ttv extends api_default
{
    const CONTROL_OTT_KEY = 'ottkey';

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

        if (!$this->request_provider_token(true)) {
            hd_debug_print('Failed to get provider token');
            return;
        }

        $response = $this->execApiCommandResponseNoOpt(API_COMMAND_ACCOUNT_INFO);
        if ($response === false) {
            hd_debug_print('Failed to request provider info');
            return;
        }

        if ((int)safe_get_value($response, 'success') !== 1) {
            hd_debug_print('request_provider_info error response: ' . json_format_unescaped($response), true);
            return;
        }

        hd_debug_print('request_provider_info: ' . json_format_unescaped($response), true);
        $this->account_info = $response;
    }

    /**
     * The OTT key is used to obtain the IPTV playlist only. The login and
     * password are used for the VOD section.
     *
     * @inheritDoc
     */
    public function GetSetupUI($name, $playlist_id, $handler)
    {
        hd_debug_print(null, true);
        $defs = array();

        Control_Factory::add_text_field($defs, $handler, CONTROL_EDIT_NAME,
            TR::t('name'), $name, false,
            false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        Control_Factory::add_text_field($defs, $handler, self::CONTROL_OTT_KEY,
            TR::t('ottkey'), $this->GetProviderParameter(MACRO_OTTKEY), false,
            false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        Control_Factory::add_label($defs, null, TR::t('setup_vod_credentials'));

        Control_Factory::add_text_field($defs, $handler, self::CONTROL_LOGIN,
            TR::t('login'), $this->GetProviderParameter(MACRO_LOGIN), false,
            false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        Control_Factory::add_text_field($defs, $handler, self::CONTROL_PASSWORD,
            TR::t('password'), $this->GetProviderParameter(MACRO_PASSWORD), false,
            false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        $this->add_setup_ui_buttons($defs, $handler, $playlist_id);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function ApplySetupUI($user_input)
    {
        hd_debug_print(null, true);

        $is_new = empty($user_input->{CONTROL_EDIT_ITEM});
        if ($is_new) {
            hd_debug_print('Create new provider info', true);
            $params[PARAM_TYPE] = PARAM_PROVIDER;
            $params[PARAM_PROVIDER] = $user_input->{PARAM_PROVIDER};
        } else {
            hd_debug_print("load info for existing playlist id: $this->playlist_id", true);
            $params = $this->plugin->get_playlist_parameters($this->playlist_id);
            hd_debug_print('provider info: ' . json_format_unescaped($params), true);
        }

        if (safe_get_value($params, PARAM_NAME) !== $user_input->{CONTROL_EDIT_NAME}) {
            $params[PARAM_NAME] = $user_input->{CONTROL_EDIT_NAME};
        }

        if (empty($user_input->{self::CONTROL_OTT_KEY})
            || empty($user_input->{self::CONTROL_LOGIN})
            || empty($user_input->{self::CONTROL_PASSWORD})) {
            return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
        }

        $params[MACRO_OTTKEY] = $user_input->{self::CONTROL_OTT_KEY};
        $params[MACRO_LOGIN] = $user_input->{self::CONTROL_LOGIN};
        $params[MACRO_PASSWORD] = $user_input->{self::CONTROL_PASSWORD};

        $error_action = $this->save_setup_params($params, $is_new);
        if ($error_action !== null) {
            return $error_action;
        }

        if ($is_new) {
            $this->apply_config_defaults();
        }

        $this->plugin->clear_playlist_cache($this->playlist_id);

        // the session id is requested for VOD and the account info only
        $this->plugin->remove_cookie(PARAM_SESSION_ID);
        if (!$this->request_provider_token(true)) {
            hd_debug_print("Can't get provider token");
            return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'), array(TR::t('err_cant_get_token')));
        }

        return $this->playlist_id;
    }

    /**
     * @inheritDoc
     */
    public function get_hash($info)
    {
        $str = safe_get_value($info, MACRO_OTTKEY, '');
        $str .= safe_get_value($info, MACRO_LOGIN, '');
        $str .= safe_get_value($info, MACRO_PASSWORD, '');
        if (empty($str)) {
            return '';
        }

        $type = safe_get_value($info, PARAM_TYPE, PARAM_PROVIDER);
        $name = safe_get_value($info, PARAM_NAME);
        return $this->getId() . "_" . Hashed_Array::hash($type . $name . $str);
    }

    /**
     * The session id is required only for VOD and the account info. The IPTV
     * playlist is downloaded by the OTT key, so the non-forced request is ignored.
     *
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print('force request provider token: ' . var_export($force, true));

        if (!$force) {
            hd_debug_print('session is not required for IPTV', true);
            return true;
        }

        $session_id = $this->plugin->get_cookie(PARAM_SESSION_ID, true);
        if (!empty($session_id)) {
            hd_debug_print('session is still valid', true);
            return true;
        }

        Dune_Last_Error::clear_last_error(LAST_ERROR_REQUEST);

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

        $session = safe_get_value($response, 'session');
        if (empty($session)) {
            hd_debug_print('Empty session in response: ' . json_format_unescaped($response), true);
            Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, TR::load('err_cant_get_token'));
            return false;
        }

        $this->plugin->set_cookie(PARAM_SESSION_ID, $session, time() + 86400 * 7);
        $this->account_info = null;

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function add_account_info_defs(&$defs, $handler)
    {
        $this->request_provider_info();

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

    ///////////////////////////////////////////////////////////////////////

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
