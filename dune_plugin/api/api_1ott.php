<?php
require_once 'api_cbilling.php';

class api_1ott extends api_default
{
    const SESSION_FILE = "%s_session_id";

    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $session_file = get_temp_path(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
        $expired = true;
        if (file_exists($session_file)) {
            $session_id = file_get_contents($session_file);
            $expired = time() > filemtime($session_file);
            if ($expired) {
                unlink($session_file);
            }
        }

        if (!$force && !empty($session_id) && !$expired) {
            hd_debug_print("request not required", true);
            return true;
        }

        // remove old settings
        $res = $this->removeCredential(MACRO_TOKEN);
        if ($res) {
            $this->save_credentials();
        }

        $response = $this->execApiCommand(API_COMMAND_REQUEST_TOKEN);
        if (isset($response->token)) {
            file_put_contents($session_file, $response->token);
            touch($session_file, time() + 86400);
            HD::set_last_error("rq_last_error", null);
            return true;
        }

        HD::set_last_error("rq_last_error", TR::load_string('err_cant_get_token'));
        return false;
    }

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $session_file = get_temp_path(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
        $session_id = file_exists($session_file) ? file_get_contents($session_file) : '';

        $string = str_replace(MACRO_SESSION_ID, $session_id, $string);

        return parent::replace_macros($string);
    }

}
