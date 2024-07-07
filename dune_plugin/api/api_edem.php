<?php
require_once 'api_default.php';

class api_edem extends api_default
{
    /**
     * @inheritDoc
     */
    public function fill_default_provider_info($matches, &$hash)
    {
        $info = parent::fill_default_provider_info($matches, $hash);

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
            $info->params[MACRO_SUBDOMAIN] = $vars[0];
            hd_debug_print("set ottkey: $vars[1]", true);
            $info->params[MACRO_OTTKEY] = $vars[1];
        } else {
            $info->params[MACRO_SUBDOMAIN] = 'junior.edmonst.net';
            hd_debug_print("set ottkey: $vars[0]", true);
            $info->params[MACRO_OTTKEY] = $vars[0];
        }

        if (!empty($ext_vars[1])) {
            if (!preg_match(VPORTAL_PATTERN, $ext_vars[1])) {
                return false;
            }

            $info->params[MACRO_VPORTAL] = $ext_vars[1];
        }

        $hash = $this->get_hash($info);

        return $info;
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

        $subdomain = $this->getCredential(MACRO_SUBDOMAIN);
        if (!empty($subdomain) && $subdomain !== $this->getConfigValue(CONFIG_SUBDOMAIN)) {
            Control_Factory::add_text_field($defs, $handler, null,
                CONTROL_OTT_SUBDOMAIN, TR::t('domain'), $this->getCredential(MACRO_SUBDOMAIN),
                false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }
        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_OTT_KEY, TR::t('ottkey'), $this->getCredential(MACRO_OTTKEY),
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_VPORTAL, TR::t('vportal'), $this->getCredential(MACRO_VPORTAL),
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler,
            array(PARAM_PROVIDER => $this->getId(), CONTROL_EDIT_ITEM => $playlist_id),
            ACTION_EDIT_PROVIDER_DLG_APPLY,
            TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function ApplySetupUI($user_input)
    {
        $id = $user_input->{CONTROL_EDIT_ITEM};

        if (is_null($this->playlist_info)) {
            hd_debug_print("Create new provider info", true);
            $this->playlist_info = new Named_Storage();
            $this->playlist_info->type = PARAM_PROVIDER;
            $this->playlist_info->name = $user_input->{CONTROL_EDIT_NAME};
            $this->playlist_info->params[PARAM_PROVIDER] = $user_input->{PARAM_PROVIDER};
        }

        $changed = false;
        if (empty($user_input->{CONTROL_OTT_SUBDOMAIN})) {
            if ($this->playlist_info->params[MACRO_SUBDOMAIN] !== $this->getConfigValue(CONFIG_SUBDOMAIN)) {
                $this->playlist_info->params[MACRO_SUBDOMAIN] = $this->getConfigValue(CONFIG_SUBDOMAIN);
                $changed = true;
            }
        } else if ($this->check_control_parameters($user_input,CONTROL_OTT_SUBDOMAIN, MACRO_SUBDOMAIN)) {
            $this->playlist_info->params[MACRO_SUBDOMAIN] = $user_input->{CONTROL_OTT_SUBDOMAIN};
            $changed = true;
        }

        if (empty($user_input->{CONTROL_OTT_KEY})) {
            return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
        }

        if ($this->check_control_parameters($user_input,CONTROL_OTT_KEY, MACRO_OTTKEY)) {
            $this->playlist_info->params[MACRO_OTTKEY] = $user_input->{CONTROL_OTT_KEY};
            $changed = true;
        }

        if (!empty($user_input->{CONTROL_VPORTAL}) && !preg_match(VPORTAL_PATTERN, $user_input->{CONTROL_VPORTAL})) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_bad_vportal'), null, TR::t('edit_list_bad_vportal_fmt'));
        }

        if ($this->check_control_parameters($user_input,CONTROL_VPORTAL, MACRO_VPORTAL)) {
            $this->playlist_info->params[MACRO_VPORTAL] = $user_input->{CONTROL_VPORTAL};
            $changed = true;
        }

        if ($this->playlist_info->name !== $user_input->{CONTROL_EDIT_NAME}) {
            $this->playlist_info->name = $user_input->{CONTROL_EDIT_NAME};
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        $is_new = empty($id);
        $id = $is_new ? $this->get_hash($this->playlist_info) : $id;
        if (empty($id)) {
            return Action_Factory::show_title_dialog(TR::t('err_incorrect_access_data'));
        }

        hd_debug_print("compiled provider info: {$this->playlist_info->name}, provider params: " . raw_json_encode($this->playlist_info), true);

        if ($is_new) {
            hd_debug_print("Set default values for id: $id", true);
            $this->set_default_settings($user_input, $id);
        }

        return $id;
    }

    /**
     * @param Named_Storage $info
     * @return string
     */
    public function get_hash($info)
    {
        $str = '';
        if (isset($info->params[MACRO_SUBDOMAIN])) {
            $str .= $info->params[MACRO_SUBDOMAIN];
        }

        if (isset($info->params[MACRO_OTTKEY])) {
            $str .= $info->params[MACRO_OTTKEY];
        }

        if (isset($info->params[MACRO_VPORTAL])) {
            $str .= $info->params[MACRO_VPORTAL];
        }

        if (empty($str)) {
            return '';
        }

        return $this->getId() . "_" . Hashed_Array::hash($info->type . $info->name . $str);
    }
}
