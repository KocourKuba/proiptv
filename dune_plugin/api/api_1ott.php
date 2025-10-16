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

class api_1ott extends api_default
{
    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request provider token: " . var_export($force, true));

        $session_id = $this->plugin->get_cookie(PARAM_SESSION_ID, true);
        if (!$force && !empty($session_id)) {
            hd_debug_print("request not required", true);
            return true;
        }

        $response = $this->execApiCommand(API_COMMAND_REQUEST_TOKEN);
        if (isset($response->token)) {
            $this->plugin->set_cookie(PARAM_SESSION_ID, $response->token,time() + 86400);
            Dune_Last_Error::clear_last_error(LAST_ERROR_REQUEST);
            return true;
        }

        Dune_Last_Error::set_last_error(LAST_ERROR_REQUEST, TR::load('err_cant_get_token'));
        return false;
    }
}
