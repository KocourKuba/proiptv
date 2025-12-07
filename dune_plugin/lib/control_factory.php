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

require_once 'action_factory.php';

class Control_Factory
{
    const SCR_CONTROLS_WIDTH = 850;
    const DLG_CONTROLS_WIDTH = 850;
    const DLG_MAX_CONTROLS_WIDTH = 1400;
    const DLG_BUTTON_WIDTH = 300;

    public static function apply_action($handler, $name, $add_params)
    {
        $params = array('action_type' => 'apply');
        if (isset($add_params)) {
            $params = safe_merge_array($params, $add_params);
        }

        return User_Input_Handler_Registry::create_action($handler, $name, null, $params);
    }

    public static function confirm_action($handler, $name, $add_params)
    {
        $params = array('action_type' => 'confirm');
        if (isset($add_params)) {
            $params = safe_merge_array($params, $add_params);
        }

        return User_Input_Handler_Registry::create_action($handler, $name, null, $params);
    }

    /**
     * @param array &$defs
     * @param int $vgap
     */
    public static function add_vgap(&$defs, $vgap)
    {
        $defs[] = array(
            GuiControlDef::kind => GUI_CONTROL_VGAP,
            GuiControlDef::specific_def => array(GuiVGapDef::vgap => $vgap),
        );
    }

    /**
     * @param array &$defs
     * @param string $title
     * @param string $text
     * @param bool $vgap_after
     */
    public static function add_label(&$defs, $title, $text, $vgap_after = 4)
    {
        $defs[] = array(
            GuiControlDef::name => '',
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_LABEL,
            GuiControlDef::specific_def => array(GuiLabelDef::caption => $text),
        );

        if ($vgap_after !== false) {
            self::add_vgap($defs, $vgap_after);
        }
    }

    /**
     * @param array &$defs
     * @param string $title
     * @param string $text
     * @param int $max_lines
     */
    public static function add_multiline_label(&$defs, $title, $text, $max_lines = 2)
    {
        $defs[] = array(
            GuiControlDef::name => '',
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_LABEL,
            GuiControlDef::specific_def => array(GuiLabelDef::caption => $text,),
            GuiControlDef::params => array('smart' => false, 'max_lines' => $max_lines),
        );
    }

    /**
     * @param array &$defs
     * @param string $title
     * @param string $text
     * @param int $vgap_after
     */
    public static function add_smart_label(&$defs, $title, $text, $vgap_after = false)
    {
        $defs[] = array(
            GuiControlDef::name => '',
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_LABEL,
            GuiControlDef::specific_def => array(GuiLabelDef::caption => $text),
            GuiControlDef::params => array('smart' => true),
        );
        if ($vgap_after !== false) {
            self::add_vgap($defs, $vgap_after);
        }
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param string $name
     * @param string $title
     * @param string $caption
     * @param array|null $add_params
     * @param int $width
     * @param bool $caption_centered
     */
    public static function add_button(&$defs, $handler, $name, $title, $caption,
                                      $add_params = null, $width = self::SCR_CONTROLS_WIDTH, $caption_centered = false)
    {
        $defs[] = array(
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::params => array('button_caption_centered' => $caption_centered),
            GuiControlDef::specific_def => array(
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => self::apply_action($handler, $name, $add_params)
            ),
        );
    }

    /**
     * @param array &$defs
     * @param string $name
     * @param string $title
     * @param string $caption
     * @param int $width
     * @param array $push_action
     * @param bool $caption_centered
     */
    public static function add_custom_action_button(&$defs, $name, $title, $caption, $width, $push_action, $caption_centered = false)
    {
        $defs[] = array(
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::params => array('button_caption_centered' => $caption_centered),
            GuiControlDef::specific_def => array(
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => $push_action
            ),
        );
    }

    /**
     * @param $defs
     * @param $button_defs
     * @param $viewport_width
     * @return void
     */
    public static function add_button_centered(&$defs, $button_defs, $viewport_width)
    {
        $def = end($button_defs);
        $def[GuiControlDef::title] = str_repeat(' ', ($viewport_width - $def[GuiControlDef::specific_def][GuiButtonDef::width]) / (15 * 2));
        $defs[] = $def;
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param string $name
     * @param string $title
     * @param string $caption
     * @param string $image
     * @param int $width
     * @param array $add_params
     */
    public static function add_image_button(&$defs, $handler, $name, $title, $caption, $image, $width = self::SCR_CONTROLS_WIDTH, &$add_params = array())
    {
        $defs[] = array(
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::specific_def => array(
                GuiButtonDef::caption => '',
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => self::apply_action($handler, $name, $add_params),
            ),
        );

        self::add_vgap($defs, -65);
        self::add_smart_label($defs, null, "<gap width=15/><icon dy='-2'>$image</icon><gap width=20/><text dy='-2'>$caption</text>");

        if (isset($add_params[PARAM_RETURN_INDEX])) {
            $add_params[PARAM_RETURN_INDEX] += 2;
        }
    }

    /**
     * @param array &$defs
     * @param string $caption
     * @param bool $caption_centered
     * @param int $width
     */
    public static function add_close_dialog_button(&$defs, $caption, $caption_centered = false, $width = self::DLG_BUTTON_WIDTH)
    {
        $defs[] = array(
            GuiControlDef::name => 'close',
            GuiControlDef::title => null,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::params => array('button_caption_centered' => $caption_centered),
            GuiControlDef::specific_def => array(
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => Action_Factory::close_dialog(),
            ),
        );
    }

    /**
     * @param array &$defs
     */
    public static function add_ok_button(&$defs, $caption_centered = false)
    {
        Control_Factory::add_close_dialog_button($defs, TR::t('ok'), $caption_centered);
    }

    /**
     * @param array &$defs
     */
    public static function add_cancel_button(&$defs, $caption_centered = false)
    {
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), $caption_centered);
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param string $name
     * @param string $caption
     * @param array|null $add_params
     * @param int $width
     */
    public static function add_close_dialog_and_apply_button(&$defs, $handler, $name, $caption, $add_params = null, $width = self::DLG_BUTTON_WIDTH)
    {
        $defs[] = array(
            GuiControlDef::name => $name,
            GuiControlDef::title => null,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::specific_def => array(
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => Action_Factory::close_dialog_and_run(self::apply_action($handler, $name, $add_params)),
            ),
        );
    }

    /**
     * @param array &$defs
     * @param string $name
     * @param string $caption
     * @param int $width
     * @param array $post_action
     */
    public static function add_custom_close_dialog_and_apply_button(&$defs, $name, $caption, $width = self::DLG_BUTTON_WIDTH, $post_action = null)
    {
        $defs[] = array(
            GuiControlDef::name => $name,
            GuiControlDef::title => null,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::specific_def => array(
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => Action_Factory::close_dialog_and_run($post_action),
            ),
        );
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param string $name
     * @param string $title
     * @param string $initial_value
     * @param bool $numeric
     * @param bool $password
     * @param bool $has_osk
     * @param bool $always_active
     * @param int $width
     * @param bool $need_confirm
     * @param bool $need_apply
     * @param array|null $add_params
     */
    public static function add_text_field(&$defs,
                                          $handler, $name,
                                          $title, $initial_value, $numeric,
                                          $password, $has_osk, $always_active,
                                          $width = self::SCR_CONTROLS_WIDTH,
                                          $need_confirm = false, $need_apply = false, &$add_params = array())
    {
        $apply_action = null;
        if ($need_apply) {
            $apply_action = self::apply_action($handler, $name, $add_params);
        }

        $confirm_action = null;
        if ($need_confirm) {
            $confirm_action = self::confirm_action($handler, $name, $add_params);
        }

        $defs[] = array(
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_TEXT_FIELD,
            GuiControlDef::specific_def => array(
                GuiTextFieldDef::initial_value => (string)$initial_value,
                GuiTextFieldDef::numeric => (bool)$numeric,
                GuiTextFieldDef::password => (bool)$password,
                GuiTextFieldDef::has_osk => (bool)$has_osk,
                GuiTextFieldDef::always_active => (bool)$always_active,
                GuiTextFieldDef::width => (int)$width,
                GuiTextFieldDef::apply_action => $apply_action,
                GuiTextFieldDef::confirm_action => $confirm_action,
            ),
        );

        if (isset($add_params[PARAM_RETURN_INDEX])) {
            $add_params[PARAM_RETURN_INDEX] += 1;
        }
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param string $name
     * @param string $title
     * @param string $initial_value
     * @param array $value_caption_pairs
     * @param array|null $add_params
     * @param int $width
     * @param bool $need_confirm
     * @param bool $need_apply
     */
    public static function add_combobox(&$defs, $handler, $name, $title, $initial_value, $value_caption_pairs,
                                        $add_params, $width = Control_Factory::SCR_CONTROLS_WIDTH,
                                        $need_confirm = false, $need_apply = false)
    {
        $apply_action = null;
        if ($need_apply) {
            $apply_action = self::apply_action($handler, $name, $add_params);
        }

        $confirm_action = null;
        if ($need_confirm) {
            $confirm_action = self::confirm_action($handler, $name, $add_params);
        }

        $defs[] = array(
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_COMBOBOX,
            GuiControlDef::specific_def => array(
                GuiComboboxDef::initial_value => $initial_value,
                GuiComboboxDef::value_caption_pairs => $value_caption_pairs,
                GuiComboboxDef::width => $width,
                GuiComboboxDef::apply_action => $apply_action,
                GuiComboboxDef::confirm_action => $confirm_action,
            ),
        );

        self::add_vgap($defs, 4);

        if (isset($add_params[PARAM_RETURN_INDEX])) {
            $add_params[PARAM_RETURN_INDEX] += 1;
        }
    }

    public static function add_progress_bar(&$defs, $title = null, $width = 0, $progress = 0, $gui_params = null)
    {
        $defs[] = array(
            GuiControlDef::name => '',
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_PROGRESS_BAR,
            GuiControlDef::specific_def => array(
                GuiProgressBarDef::width => $width,
                GuiProgressBarDef::progress => $progress,
                ),
            GuiControlDef::params => $gui_params,
        );
    }

    /**
     * @param string|array $img
     * @param int $img_x
     * @param int $img_y
     * @param string $img_halign
     * @param string $img_valign
     * @return false|string
     */
    public static function create_sticker($img, $img_x = 0, $img_y = 0, $img_halign = 'right', $img_valign = 'top', $above_selection = false)
    {
        $items = array();
        if (is_array($img)) {
            foreach ($img as $k => $im) {
                $im_x = isset($img_x[$k]) ? $img_x[$k] : 0;
                $im_y = isset($img_y[$k]) ? $img_y[$k] : 0;
                $im_halign = isset($img_halign[$k]) ? $img_halign[$k] : 'right';
                $im_valign = isset($img_valign[$k]) ? $img_valign[$k] : 'top';
                $items[] = self::sticker_geometry($im, $im_x, $im_y, $im_halign, $im_valign);
            }
        } else {
            $items[] = self::sticker_geometry($img, $img_x, $img_y, $img_halign, $img_valign);
        }

        return json_encode(array('items' => $items, 'above_selection' => $above_selection));
    }

    public static function sticker_geometry($img, $img_x, $img_y, $img_halign, $img_valign)
    {
        return array(
            'geom' => array('x' => $img_x, 'y' => $img_y, 'halign' => $img_halign, 'valign' => $img_valign),
            'comp' => array('items' => array(array('type' => 'icon', 'url' => $img)))
        );
    }
}
