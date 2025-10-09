<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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

require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_Edit_Providers_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'edit_proiders_list';

    const ACTION_SHOW_QR = 'show_qr';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);


        $info = User_Input_Handler_Registry::create_action($this, self::ACTION_SHOW_QR, TR::t('info'));

        $actions = array();
        $actions[GUI_EVENT_KEY_INFO] = $info;
        $actions[GUI_EVENT_KEY_D_BLUE] = $info;
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $selected_id = isset($user_input->selected_media_url) ? MediaURL::decode($user_input->selected_media_url)->id : 0;

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                hd_debug_print("Call parent: " .
                    $parent_media_url->{PARAM_SOURCE_WINDOW_ID} . " action: ". $parent_media_url->{PARAM_END_ACTION}, true);

                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(
                        $parent_media_url->{PARAM_SOURCE_WINDOW_ID},
                        $parent_media_url->{PARAM_CANCEL_ACTION}
                    )
                );

            case GUI_EVENT_KEY_ENTER:
                hd_debug_print("Call parent: " .
                    $parent_media_url->{PARAM_SOURCE_WINDOW_ID} . " action: ". $parent_media_url->{PARAM_END_ACTION}, true);

                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(
                        $parent_media_url->{PARAM_SOURCE_WINDOW_ID},
                        $parent_media_url->{PARAM_END_ACTION},
                        null,
                        array(PARAM_PROVIDER => $selected_id)
                    )
                );

            case self::ACTION_SHOW_QR:
                /** @var api_default $provider */
                $provider = $this->plugin->get_providers()->get($selected_id);
                if (is_null($provider)) break;

                $qr_code = get_temp_path($provider->getId()) . ".jpg";
                if (!file_exists($qr_code)) {
                    $url = "https://api.qrserver.com/v1/create-qr-code/?size=450x450&format=jpg&data=" . urlencode($provider->getProviderUrl());
                    $res = Curl_Wrapper::simple_download_file($url, $qr_code);
                    if (!$res) break;
                }

                Control_Factory::add_vgap($defs, 20);
                Control_Factory::add_smart_label($defs, "", "<gap width=25/><icon width=450 height=450>$qr_code</icon>");
                Control_Factory::add_vgap($defs, 450);
                return Action_Factory::show_dialog(TR::t('provider_info'), $defs, true, 600);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();
        /** @var api_default $provider */
        foreach ($this->plugin->get_providers() as $provider) {
            $info = TR::t('setup_provider_info__3', $provider->getProviderUrl(), $provider->getId(), $provider->getType());
            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array(PARAM_SCREEN_ID => static::ID, 'id' => $provider->getId())),
                PluginRegularFolderItem::caption => $provider->getName(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => null,
                    ViewItemParams::icon_path => $provider->getLogo(),
                    ViewItemParams::item_detailed_icon_path => $provider->getLogo(),
                    ViewItemParams::item_detailed_info => $info,
                ),
            );
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $folder_view = parent::get_folder_view($media_url, $plugin_cookies);
        $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = null;
        return $folder_view;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_views()
    {
        hd_debug_print(null, true);

        return array(
            $this->plugin->get_screen_view('list_1x11_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),
        );
    }
}
