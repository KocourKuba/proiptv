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

require_once 'abstract_rows_screen.php';
require_once 'rows_factory.php';
require_once 'gcomps_factory.php';
require_once 'gcomp_geom.php';

class Dummy_Epfs_Screen extends Abstract_Rows_Screen implements User_Input_Handler
{
    const ID = 'dummy_epf';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        return array(GUI_EVENT_KEY_ENTER => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER));
    }

    /**
     * @inheritDoc
     */
    public function get_rows_pane(MediaURL $media_url, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $defs = array();

        $caption = $media_url->no_internet ? TR::t('err_no_internet') : TR::t('loading');

        $rows[] = Rows_Factory::vgap_row(50);
        $defs[] = GComps_Factory::label_v2(GComp_Geom::place_center(), null, $caption, 1, "#AFAFA0FF", 60);
        $rows[] = Rows_Factory::gcomps_row("single_row", $defs, null, 1920, 500);

        return Rows_Factory::pane($rows);
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        return null;
    }

    /**
     * @param $no_internet
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_folder_view_for_epf($no_internet, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $media_url = MediaURL::make(array('no_internet' => $no_internet));

        return $this->get_folder_view($media_url, $plugin_cookies);
    }
}
