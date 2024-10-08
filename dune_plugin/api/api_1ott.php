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

        $session_file = sprintf(self::SESSION_FILE, $this->get_provider_playlist_id());
        $session_id = HD::get_cookie($session_file);
        if (!$force && !empty($session_id)) {
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
            HD::set_cookie($session_file, $response->token,time() + 86400);
            HD::set_last_error("rq_last_error", null);
            return true;
        }

        HD::set_last_error("rq_last_error", TR::load_string('err_cant_get_token'));
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear_session_info()
    {
        HD::clear_cookie(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
    }

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $session_id = HD::get_cookie(sprintf(self::SESSION_FILE, $this->get_provider_playlist_id()));
        $string = str_replace(MACRO_SESSION_ID, $session_id, $string);

        return parent::replace_macros($string);
    }

}
