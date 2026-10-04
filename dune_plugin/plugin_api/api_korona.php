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

class api_korona extends api_default
{
    /**
     * @inheritDoc
     */
    public function request_provider_token($force = false)
    {
        $login_pairs = array(
            'grant_type' => 'password',
            'username' => $this->GetProviderParameter(MACRO_LOGIN),
            'password' => $this->GetProviderParameter(MACRO_PASSWORD),
        );

        return $this->request_oauth_token($force, $login_pairs, array(), CONTENT_TYPE_WWW_FORM_URLENCODED, 0);
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

        if (isset($this->account_info['balance'], $this->account_info['tariff'])) {
            Control_Factory::add_label($defs, TR::t('balance'), "{$this->account_info['balance']} {$this->account_info['tariff']['currency']}", -15);
            $packages = $this->account_info['tariff']['name'] . PHP_EOL;
            $packages .= TR::load('end_date__1', $this->account_info['expiry_date']) . PHP_EOL;
            $packages .= TR::load('package_timed__1', $this->account_info['tariff']['period']) . PHP_EOL;
            $packages .= TR::load('money_need__1', "{$this->account_info['tariff']['full_price']} {$this->account_info['tariff']['currency']}") . PHP_EOL;
            Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function GetServers()
    {
        hd_debug_print(null, true);

        $this->load_servers('data', 'id', 'title');
        return $this->servers;
    }

    /**
     * @inheritDoc
     */
    protected function get_additional_headers($command)
    {
        if ($command !== API_COMMAND_REQUEST_TOKEN && $command !== API_COMMAND_REFRESH_TOKEN) {
            return array($this->replace_macros('Authorization: Bearer {TOKEN}'));
        }

        return array();
    }
}
