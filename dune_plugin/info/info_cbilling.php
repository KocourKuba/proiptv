<?php
require_once 'info_default.php';

/**
 * "data": {
 *      "public_token": "2f5787bd535caee4e25ba3ed3019babc",
 *      "private_token": "5acf87d0206d915b73489234703bf666",
 *      "end_time": 1706129968,
 *      "end_date": "2024-01-24 23:59:28",
 *      "devices_num": 1,
 *      "server": "s01.wsbof.com",
 *      "vod": true,
 *      "ssl": false,
 *      "disable_adult": false
 * }
 */

class info_cbilling extends info_default
{
    public function GetInfoUI($handler)
    {
        $provider = $this->plugin->get_current_provider();
        if (is_null($provider)) {
            return null;
        }

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        $data = $provider->request_provider_info();
        if (empty($data)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('err_error'), TR::t('warn_msg3'), -10);
        } else if (isset($data['data'])) {
            $data = $data['data'];
            if (isset($data['end_date'])) {
                Control_Factory::add_label($defs, TR::t('end_date'), $data['end_date'], -15);
            }
            if (isset($data['devices_num'])) {
                Control_Factory::add_label($defs, TR::t('devices'), $data['devices_num'], -15);
            }
            if (isset($data['server'])) {
                Control_Factory::add_label($defs, TR::t('server'), $data['server'], -15);
            }
            if (isset($data['ssl'])) {
                Control_Factory::add_label($defs, TR::t('ssl'), $data['ssl'] ? TR::t('yes') : TR::t('no'), -15);
            }
            if (isset($data['disable_adult'])) {
                Control_Factory::add_label($defs, TR::t('disable_adult'), $data['disable_adult'] ? TR::t('yes') : TR::t('no'), -15);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog(TR::t('subscription'), $defs, true, 1000, null /*$attrs*/);
    }
}
