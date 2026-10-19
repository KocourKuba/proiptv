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
 * RU TV provider (https://rutv.vip/)
 *
 * The provider has several VOD playlists. The list is defined by the vod_playlists config section
 * (playlist file name => caption) and the selected one replaces the {VOD_PLAYLIST_ID} macro of the get_vod command.
 */
class api_rutv extends api_default
{
    const CONFIG_VOD_PLAYLISTS = 'vod_playlists';
    const MACRO_VOD_PLAYLIST_ID = '{VOD_PLAYLIST_ID}';
    const CONTROL_VOD_PLAYLIST = 'vod_playlist';

    /**
     * @inheritDoc
     */
    public function replace_macros($string)
    {
        $string = str_replace(self::MACRO_VOD_PLAYLIST_ID, $this->get_vod_playlist_id(), $string);

        return parent::replace_macros($string);
    }

    /**
     * @inheritDoc
     */
    public function set_config_defaults()
    {
        parent::set_config_defaults();

        $vod_playlists = $this->getConfigValue(self::CONFIG_VOD_PLAYLISTS);
        if (!empty($vod_playlists) && $this->getCredential(self::MACRO_VOD_PLAYLIST_ID) === '') {
            $this->setCredential(self::MACRO_VOD_PLAYLIST_ID, (string)key($vod_playlists));
        }
    }

    /**
     * @inheritDoc
     */
    protected function add_ext_setup_controls(&$defs, $handler)
    {
        $vod_playlists = $this->getConfigValue(self::CONFIG_VOD_PLAYLISTS);
        if (empty($vod_playlists) || count($vod_playlists) < 2) {
            return;
        }

        $idx = $this->get_vod_playlist_id();
        hd_debug_print("vod playlists ($idx): " . json_encode($vod_playlists), true);

        Control_Factory::add_combobox($defs, $handler, null, self::CONTROL_VOD_PLAYLIST,
            TR::t('vod_playlist'), $idx, $vod_playlists, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
    }

    /**
     * @inheritDoc
     */
    protected function apply_ext_setup_controls($user_input)
    {
        if (!$this->check_control_parameters($user_input, self::CONTROL_VOD_PLAYLIST, self::MACRO_VOD_PLAYLIST_ID)) {
            return false;
        }

        $this->setCredential(self::MACRO_VOD_PLAYLIST_ID, $user_input->{self::CONTROL_VOD_PLAYLIST});
        return true;
    }

    /**
     * Selected VOD playlist or the first one from config
     *
     * @return string
     */
    protected function get_vod_playlist_id()
    {
        $idx = $this->getCredential(self::MACRO_VOD_PLAYLIST_ID);
        if ($idx === '') {
            $vod_playlists = $this->getConfigValue(self::CONFIG_VOD_PLAYLISTS);
            if (!empty($vod_playlists)) {
                $idx = (string)key($vod_playlists);
            }
        }

        return $idx;
    }
}
