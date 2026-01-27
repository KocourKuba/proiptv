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
 * "user_info": {
 *      "login": "shegow",
 *      "pay_system": "2",
 *      "cash": "0.09"
 * },
 * "package_info": [
 *      {
 *          "name": "Основной"
 *      },
 *      {
 *          "name": "Кино"
 *      },
 *      {
 *          "name": "Спорт"
 *      },
 *      {
 *          "name": "Детский"
 *      }
 * ],
 * "channels": [] - ignore this
 * }
 */

class api_itvlive extends api_default
{
    /**
     * @inheritDoc
     */
    public function GetInfoUI($handler)
    {
        $this->request_provider_info();

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (empty($this->account_info)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('error'), TR::t('warn_msg3'), -10);
        } else {
            $info = safe_get_value($this->account_info, 'user_info');
            if (isset($info['login'])) {
                Control_Factory::add_label($defs, TR::t('login'), $info['login'], -15);
            }
            if (isset($info['cash'])) {
                Control_Factory::add_label($defs, TR::t('balance'), $info['cash'], -15);
            }

            $packages = '';
            foreach (safe_get_value($this->account_info, 'package_info') as $package) {
                if (isset($package['name'])) {
                    $packages .= $package['name'] . PHP_EOL;
                }
            }

            if (!empty($packages)) {
                Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog($defs, TR::t('subscription'));
    }
}
