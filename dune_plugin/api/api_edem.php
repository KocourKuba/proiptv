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

class api_edem extends api_default
{
    /**
     * @inheritDoc
     */
    public function fill_default_provider_info($matches, &$playlist_id)
    {
        $info = parent::fill_default_provider_info($matches, $playlist_id);

        $ext_vars = explode('|', $matches[2]);
        if (empty($ext_vars)) {
            hd_debug_print("invalid provider_info: $matches[2]", true);
            return false;
        }

        $vars = explode(':', $ext_vars[0]);
        if (empty($vars)) {
            hd_debug_print("invalid provider_info: $ext_vars[0]", true);
            return false;
        }

        hd_debug_print("parse imported provider_info: $ext_vars[0]", true);

        if (isset($vars[1])) {
            hd_debug_print("set subdomain: $vars[0]", true);
            $info[MACRO_SUBDOMAIN] = $vars[0];
            hd_debug_print("set ottkey: $vars[1]", true);
            $info[MACRO_OTTKEY] = $vars[1];
        } else {
            $info[MACRO_SUBDOMAIN] = 'junior.edmonst.net';
            hd_debug_print("set ottkey: $vars[0]", true);
            $info[MACRO_OTTKEY] = $vars[0];
        }

        if (!empty($ext_vars[1])) {
            if (!preg_match(VPORTAL_PATTERN, $ext_vars[1])) {
                return false;
            }

            $info[MACRO_VPORTAL] = $ext_vars[1];
        }

        $playlist_id = $this->get_hash($info);

        return $info;
    }

    /**
     * @param array $info
     * @return string
     */
    public function get_hash($info)
    {
        $str = safe_get_value($info, MACRO_SUBDOMAIN, '');
        $str .= safe_get_value($info, MACRO_OTTKEY, '');
        $str .= safe_get_value($info, MACRO_VPORTAL, '');
        if (empty($str)) {
            return '';
        }

        $type = safe_get_value($info, PARAM_TYPE, PARAM_PROVIDER);
        $name = safe_get_value($info, PARAM_NAME);
        return $this->getId() . "_" . Hashed_Array::hash($type . $name . $str);
    }

    /**
     * @inheritDoc
     */
    public function GetSetupUI($name, $playlist_id, $handler)
    {
        hd_debug_print(null, true);
        $defs = array();

        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_EDIT_NAME, TR::t('name'), $name,
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        $subdomain = $this->getParameter(MACRO_SUBDOMAIN);
        if (!empty($subdomain) && $subdomain !== $this->getConfigValue(CONFIG_SUBDOMAIN)) {
            Control_Factory::add_text_field($defs, $handler, null,
                CONTROL_OTT_SUBDOMAIN, TR::t('domain'), $this->getParameter(MACRO_SUBDOMAIN),
                false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_OTT_KEY, TR::t('ottkey'), $this->getParameter(MACRO_OTTKEY),
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_VPORTAL, TR::t('vportal'), $this->getParameter(MACRO_VPORTAL),
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler,
            ACTION_EDIT_PROVIDER_DLG_APPLY,
            TR::t('ok'),
            300,
            array(PARAM_PROVIDER => $this->getId(), CONTROL_EDIT_ITEM => $playlist_id)
        );

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function ApplySetupUI($user_input)
    {
        $playlist_id = safe_get_member($user_input, CONTROL_EDIT_ITEM);

        if (empty($playlist_id)) {
            hd_debug_print("Create new provider info", true);
            $params[PARAM_TYPE] = PARAM_PROVIDER;
            $params[PARAM_NAME] = $user_input->{CONTROL_EDIT_NAME};
            $params[PARAM_PROVIDER] = $user_input->{PARAM_PROVIDER};
        } else {
            hd_debug_print("load info for existing playlist id: $playlist_id", true);
            $params = $this->plugin->get_playlist_parameters($playlist_id);
            hd_debug_print("provider info: " . pretty_json_format($params), true);
        }

        $changed = false;

        if (safe_get_value($params, PARAM_NAME) !== $user_input->{CONTROL_EDIT_NAME}) {
            $params[PARAM_NAME] = $user_input->{CONTROL_EDIT_NAME};
            $changed = true;
        }

        if ($this->IsParameterChanged($user_input, CONTROL_OTT_SUBDOMAIN, MACRO_SUBDOMAIN)) {
            $params[MACRO_SUBDOMAIN] = $user_input->{$param};
            $changed = true;
        }

        if ($this->IsParameterChanged($user_input, CONTROL_OTT_KEY, MACRO_OTTKEY)) {
            $params[MACRO_OTTKEY] = $user_input->{CONTROL_OTT_KEY};
            $changed = true;
        }

        if (empty($params[MACRO_OTTKEY])) {
            return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
        }

        if (!empty($user_input->{CONTROL_VPORTAL}) && !preg_match(VPORTAL_PATTERN, $user_input->{CONTROL_VPORTAL})) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_bad_vportal'), null, TR::t('edit_list_bad_vportal_fmt'));
        }

        if ($this->IsParameterChanged($user_input, CONTROL_VPORTAL, MACRO_VPORTAL)) {
            $params[MACRO_VPORTAL] = $user_input->{CONTROL_VPORTAL};
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        $is_new = empty($playlist_id);
        $playlist_id = $is_new ? $this->get_hash($params) : $playlist_id;
        if (empty($playlist_id)) {
            return Action_Factory::show_title_dialog(TR::t('err_incorrect_access_data'));
        }

        hd_debug_print("ApplySetupUI compiled provider ($playlist_id) info: " . pretty_json_format($params), true);

        if ($is_new) {
            hd_debug_print("Set default values for id: $playlist_id", true);
            $this->set_default_settings($playlist_id);
        }

        $this->plugin->set_playlist_parameters($playlist_id, $params);
        $this->plugin->clear_playlist_cache($playlist_id);

        return $playlist_id;
    }
}
