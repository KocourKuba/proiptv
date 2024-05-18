<?php
require_once 'api_default.php';

class api_iptvonline extends api_default
{
    /**
     * @inheritDoc
     */
    public function init_provider($info)
    {
        hd_debug_print("provider info:" . json_encode($info));
        hd_debug_print("parse provider_info ({$this->getType()}): $info", true);

        $this->setCredential(MACRO_LOGIN, isset($info->params[MACRO_LOGIN]) ? $info->params[MACRO_LOGIN] : '');
        $this->setCredential(MACRO_PASSWORD, isset($info->params[MACRO_PASSWORD]) ? $info->params[MACRO_PASSWORD] : '');
        $this->setCredential(MACRO_TOKEN,isset($info->params[MACRO_TOKEN]) ? $info->params[MACRO_TOKEN] : '');
        $this->setCredential(MACRO_REFRESH_TOKEN,isset($info->params[MACRO_REFRESH_TOKEN]) ? $info->params[MACRO_REFRESH_TOKEN] : '');
        $this->setCredential(MACRO_EXPIRE_DATA,isset($info->params[MACRO_EXPIRE_DATA]) ? $info->params[MACRO_EXPIRE_DATA] : '');

        $servers = $this->GetServers();
        if (!empty($servers)) {
            $this->setCredential(MACRO_SERVER_ID, key($servers));
        }

        $streams = $this->getStreams();
        if (!empty($streams)) {
            $this->setCredential(MACRO_STREAM_ID, key($streams));
        }

        foreach($info->params as $key => $item) {
            if ($key === MACRO_SERVER_ID || $key === MACRO_STREAM_ID) {
                $this->setCredential($key, $item);
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function set_info($matches, &$info)
    {
        $info->type = PARAM_PROVIDER;
        $info->params[PARAM_PROVIDER] = $matches[1];
        $info->name = $this->getName();

        $vars = explode(':', $matches[2]);
        if (empty($vars)) {
            hd_debug_print("invalid provider_info: $matches[0]", true);
            return false;
        }

        hd_debug_print("parse imported provider_info: $vars[0]", true);

        hd_debug_print("set login: $vars[0]", true);
        $info->params[MACRO_LOGIN] = $vars[0];
        hd_debug_print("set password: $vars[1]", true);
        $info->params[MACRO_PASSWORD] = $vars[1];

        $info->params[MACRO_TOKEN] = '';
        $info->params[MACRO_REFRESH_TOKEN] = '';
        $info->params[MACRO_EXPIRE_DATA] = 0;

        $servers = $this->getConfigValue(CONFIG_SERVERS);
        if (!empty($servers)) {
            $info->params[MACRO_SERVER_ID] = key($servers);
        }

        $streams = $this->getConfigValue(CONFIG_STREAMS);
        if (!empty($streams)) {
            $info->params[MACRO_STREAM_ID] = key($streams);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function GetSetupUI($name, $playlist_id, $handler)
    {
        $defs = array();

        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_EDIT_NAME, TR::t('name'), $name,
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_LOGIN, TR::t('login'), $this->getCredential(MACRO_LOGIN),
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_PASSWORD, TR::t('password'), $this->getCredential(MACRO_PASSWORD),
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler,
            array(PARAM_PROVIDER => $this->getId(), CONTROL_EDIT_ITEM => $playlist_id),
            ACTION_EDIT_PROVIDER_DLG_APPLY,
            TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function ApplySetupUI($user_input, &$item)
    {
        $id = $user_input->{CONTROL_EDIT_ITEM};

        $item->params[MACRO_LOGIN] = $user_input->{CONTROL_LOGIN};
        $item->params[MACRO_PASSWORD] = $user_input->{CONTROL_PASSWORD};
        $id = empty($id) ? Hashed_Array::hash($item->type.$item->name.$item->params[MACRO_LOGIN].$item->params[MACRO_PASSWORD]) : $id;
        if (empty($item->params[MACRO_LOGIN]) || empty($item->params[MACRO_PASSWORD])) {
            return null;
        }

        $this->setCredential(MACRO_TOKEN, '');
        $this->setCredential(MACRO_REFRESH_TOKEN, '');
        $this->setCredential(MACRO_EXPIRE_DATA, 0);

        hd_debug_print("compiled provider info: $item->name, provider params: " . raw_json_encode($item->params), true);

        return $id;
    }

    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);

        $token = $this->getCredential(MACRO_TOKEN);
        $expired = time() > (int)$this->getCredential(MACRO_EXPIRE_DATA);
        if (!$force && !empty($token) && !$expired) {
            return true;
        }

        $pairs['client_id'] = "TestAndroidAppV0";
        $pairs['client_secret'] = "kshdiouehruyiwuresuygr736t4763b7637"; // dummy
        $pairs['device_id'] = get_serial_number();

        $refresh_token = $this->getCredential(MACRO_REFRESH_TOKEN);
        $refresh = $expired && !empty($refresh_token);
        if ($refresh) {
            $cmd = API_COMMAND_REFRESH_TOKEN;
            $pairs['grant_type'] = $this->getCredential(MACRO_LOGIN);
            $pairs['refresh_token'] = $refresh_token;
        } else {
            $cmd = API_COMMAND_REQUEST_TOKEN;
            $pairs['login'] = $this->getCredential(MACRO_LOGIN);
            $pairs['password'] = $this->getCredential(MACRO_PASSWORD);
        }

        $curl_opt[CURLOPT_POST] = true;
        $curl_opt[CURLOPT_HTTPHEADER] = array("Content-Type: application/json");
        $curl_opt[CURLOPT_POSTFIELDS] = str_replace('"', '\"', json_encode($pairs));

        $data = $this->execApiCommand($cmd, null, true, $curl_opt);
        if (isset($data->access_token)) {
            $this->setCredential(MACRO_TOKEN, $data->access_token);
            $this->setCredential(MACRO_REFRESH_TOKEN, $data->refresh_token);
            $this->setCredential(MACRO_EXPIRE_DATA, $data->expires_time);
            return true;
        }

        return false;
    }

    /**
     * @param $tmp_file string
     * @return bool
     */
    public function load_playlist($tmp_file)
    {
        hd_debug_print(null, true);

        if (!$this->request_provider_token()) {
            return false;
        }

        $data = $this->execApiCommand(API_COMMAND_PLAYLIST);
        if (isset($data->success, $data->data)) {
            return HD::http_download_https_proxy($data->data, $tmp_file);
        }

        return false;
    }
}
