<?php
require_once 'api_default.php';

/**
 * "user_info": {
 *      "login": "shegwow",
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
    public function GetInfoUI($handler)
    {
        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        $data = $this->execApiCommand(API_COMMAND_INFO);
        if ($data === false) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else {
            if (isset($data->user_info)) {
                $info = $data->user_info;
                if (isset($info->login)) {
                    Control_Factory::add_label($defs, TR::t('login'), $info->login, -15);
                }
                if (isset($info->cash)) {
                    Control_Factory::add_label($defs, TR::t('balance'), $info->cash, -15);
                }
            }

            if (isset($data->package_info)) {
                $packages = '';
                foreach ($data->package_info as $package) {
                    if (isset($package->name)) {
                        $packages .= $package->name . "\n";
                    }
                }

                Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog(TR::t('subscription'), $defs, true, 1000, null /*$attrs*/);
    }
}
