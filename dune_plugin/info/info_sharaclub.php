<?php
require_once 'info_default.php';

/**
 * "status": "1",
 * "data": {
 *      "login": "sharky",
 *      "money": "334.52",
 *      "money_need": "298",
 *      "currency": "RUB",
 *      "vod": true,
 *      "abon": [
 *          "Кино и Сериалы **",
 *          "Россия",
 *          "Кино плюс",
 *          "Europe (De, Fr, Pol + Tur)"
 *       ]
 * }
 */

class info_sharaclub extends info_default
{
    public function GetInfoUI($handler)
    {
        $provider = $this->plugin->get_current_provider();
        if (is_null($provider)) {
            return null;
        }

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        $pay_url = $provider->getProviderInfoConfigValue('pay_url');
        if (!empty($pay_url)) {
            Control_Factory::add_button($defs, $handler, null,
                ACTION_ADD_MONEY_DLG, "", TR::t('add_money'), 450, true);
        }

        $data = $provider->request_provider_info();
        if (empty($data)) {
            hd_debug_print("Can't get account status");
            Control_Factory::add_label($defs, TR::t('warn_msg3'), null, -10);
        } else if (isset($data['status']) && (int)$data['status'] !== 1) {
            Control_Factory::add_label($defs, TR::t('err_error'), $data['status'], -10);
        } else if (isset($data['data'])) {
            $data = $data['data'];
            if (isset($data['login'])) {
                Control_Factory::add_label($defs, TR::t('login'), $data['login'], -15);
            }
            if (isset($data['money'], $data['currency'])) {
                Control_Factory::add_label($defs, TR::t('balance'), "{$data['money']} {$data['currency']}", -15);
            }
            if (isset($data['money_need'], $data['currency'])) {
                Control_Factory::add_label($defs, TR::t('money_need'), "{$data['money_need']} {$data['currency']}", -15);
            }

            if (isset($data['abon'])) {
                $packages = '';
                foreach ($data['abon'] as $package) {
                    $packages .= $package . "\n";
                }
                Control_Factory::add_multiline_label($defs, TR::t('packages'), $packages, 10);
            }
        }

        Control_Factory::add_vgap($defs, 20);

        return Action_Factory::show_dialog(TR::t('subscription'), $defs, true, 1100);
    }

    /**
     * @return array|null
     */
    protected function do_show_add_money()
    {
        $provider = $this->plugin->get_current_provider();
        if (is_null($provider)) {
            return null;
        }

        try {
            $img = get_temp_path($this->plugin->get_active_playlist_key() . '.png');
            if (file_exists($img)) {
                unlink($img);
            }

            $content = HD::http_download_https_proxy($provider->replace_macros($provider->getProviderInfoConfigValue('pay_url')));
            file_put_contents($img, $content);
            Control_Factory::add_vgap($defs, 20);

            if (file_exists($img)) {
                Control_Factory::add_smart_label($defs, "", "<gap width=25/><icon width=450 height=450>$img</icon>");
                Control_Factory::add_vgap($defs, 450);
            } else {
                Control_Factory::add_smart_label($defs, "", "<text>" . TR::load_string('err_incorrect_access_data') . "</text>");
                Control_Factory::add_vgap($defs, 50);
            }

            return Action_Factory::show_dialog(TR::t("add_money"), $defs, true, 600);
        } catch (Exception $ex) {
        }

        return null;
    }
}
