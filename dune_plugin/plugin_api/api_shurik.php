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
     * @inheritDoc
     */
    protected $keep_error_account_info = true;

    /**
     * @inheritDoc
     */
    protected function add_account_info_defs(&$defs, $handler)
    {
        hd_debug_print(null, true);
        $this->request_provider_info();

        if (!isset($this->account_info[0])) {
            return false;
        }

        $info = $this->account_info[0];
        if (isset($info['packet'])) {
            Control_Factory::add_label($defs, TR::t('package'), $info['packet'], -15);
        }
        if (isset($info['expired'])) {
            Control_Factory::add_label($defs, TR::t('end_date'),
                gmdate('d.m.Y  H:i', substr($info['expired'], 0, -3)),
                -15);
        }

        return true;
    }
}
