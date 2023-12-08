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

require_once 'abstract_screen.php';

abstract class Abstract_Controls_Screen extends Abstract_Screen
{
    const CONTROLS_WIDTH = 850;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    abstract public function get_control_defs(MediaURL $media_url, &$plugin_cookies);

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $defs = $this->get_control_defs($media_url, $plugin_cookies);

        $folder_view = array
        (
            PluginControlsFolderView::defs => $defs,
            PluginControlsFolderView::initial_sel_ndx => -1,
            PluginControlsFolderView::actions => $this->get_action_map($media_url, $plugin_cookies),
            PluginControlsFolderView::timer => $this->get_timer($media_url, $plugin_cookies),
            PluginControlsFolderView::params => array(
                PluginFolderViewParams::paint_path_box => true,
                PluginFolderViewParams::paint_content_box_background => true,
                PluginFolderViewParams::background_url => $this->plugin->get_background_image(),
            ),
        );

        return array
        (
            PluginFolderView::multiple_views_supported => false,
            PluginFolderView::archive => null,
            PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_CONTROLS,
            PluginFolderView::data => $folder_view,
        );
    }

    /**
     * @param $plugin_cookies
     * @param string $param
     * @param bool $default
     * @return mixed
     */
    protected static function get_cookie_bool_param($plugin_cookies, $param, $default = true)
    {
        if (!isset($plugin_cookies->{$param}))
            $plugin_cookies->{$param} = $default ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off;

        return $plugin_cookies->{$param};
    }

    /**
     * @param $plugin_cookies
     * @param string $param
     * @return void
     */
    protected static function toggle_cookie_param($plugin_cookies, $param)
    {
        $plugin_cookies->{$param} = $plugin_cookies->{$param} === SetupControlSwitchDefs::switch_off
            ? SetupControlSwitchDefs::switch_on
            : SetupControlSwitchDefs::switch_off;

        hd_debug_print("$param: " . $plugin_cookies->{$param}, true);
    }
}
