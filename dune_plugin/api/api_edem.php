<?php
require_once 'api_default.php';

class api_edem extends api_default
{
    /**
     * @inheritDoc
     */
    public function init_provider($info)
    {
        hd_debug_print("provider info:" . json_encode($info));
        hd_debug_print("parse provider_info ({$this->getType()}): $info", true);

        $this->setCredential(MACRO_SUBDOMAIN, isset($info->params[MACRO_SUBDOMAIN]) ? $info->params[MACRO_SUBDOMAIN] : '');
        $this->setCredential(MACRO_OTTKEY, isset($info->params[MACRO_OTTKEY]) ? $info->params[MACRO_OTTKEY] : '');
        $this->setCredential(MACRO_VPORTAL, isset($info->params[MACRO_VPORTAL]) ? $info->params[MACRO_VPORTAL] : '');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function set_info($matches, &$info)
    {
        $info->type = PARAM_PROVIDER;
        $info->params[PARAM_PROVIDER] = $matches[1];
        $info->name = $this->getName();

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

        if (!empty($ext_vars[1]) && !preg_match(VPORTAL_PATTERN, $ext_vars[1])) {
            return false;
        }

        $info->params[MACRO_VPORTAL] = $ext_vars[1];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function GetSetupUI($name, $playlist_id, $handler)
    {
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
    public function ApplySetupUI($user_input, &$item)
    {
        $id = $user_input->{CONTROL_EDIT_ITEM};

        if (!empty($user_input->{CONTROL_OTT_SUBDOMAIN})) {
            $item->params[MACRO_SUBDOMAIN] = $user_input->{CONTROL_OTT_SUBDOMAIN};
        } else {
            $item->params[MACRO_SUBDOMAIN] = $this->getConfigValue(CONFIG_SUBDOMAIN);
        }

        if (empty($user_input->{CONTROL_OTT_KEY})) {
            return null;
        }

        $item->params[MACRO_OTTKEY] = $user_input->{CONTROL_OTT_KEY};

        if (!empty($user_input->{CONTROL_VPORTAL}) && !preg_match(VPORTAL_PATTERN, $user_input->{CONTROL_VPORTAL})) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_bad_vportal'), null, TR::t('edit_list_bad_vportal_fmt'), 1000);
        }

        $item->params[MACRO_VPORTAL] = $user_input->{CONTROL_VPORTAL};

        $id = empty($id) ? Hashed_Array::hash($item->type.$item->name.$item->params[MACRO_SUBDOMAIN].$item->params[MACRO_OTTKEY]) : $id;

        hd_debug_print("compiled provider info: $item->name, provider params: " . raw_json_encode($item->params), true);

        return $id;
    }
}
