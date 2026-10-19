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

class api_shurik extends api_default
{
    /**
     * @param bool $force
     * @return bool|object
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force get_provider_info: " . var_export($force, true), true);

        if ((empty($this->account_info) || $force)) {
            $this->account_info = $this->execApiCommand(API_COMMAND_ACCOUNT_INFO);
            hd_debug_print("get_provider_info: " . pretty_json_format($this->account_info), true);
        }

        return $this->account_info;
    }

    /**
     * @inheritDoc
     */
    public function GetInfoUI($handler)
    {
        hd_debug_print(null, true);
        $account_info = $this->get_provider_info();
        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        if (!isset($account_info[0])) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else {
            $account_info = $account_info[0];
            if (isset($account_info->packet)) {
                Control_Factory::add_label($defs, TR::t('package'), $account_info->packet, -15);
            }
            if (isset($account_info->expired)) {
                Control_Factory::add_label($defs, TR::t('end_date'), gmdate("d.m.Y  H:i", substr($account_info->expired, 0, -3)), -15);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog(TR::t('subscription'), $defs, true, 1000, null /*$attrs*/);
    }
}
