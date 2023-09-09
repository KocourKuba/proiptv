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
    /**
     * @param array &$defs
     * @param int $vgap
     */
    public static function add_vgap(&$defs, $vgap)
    {
        $defs[] = array
        (
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
        $defs[] = array
        (
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
     */
    public static function add_smart_label(&$defs, $title, $text)
    {
        $defs[] = array(
            GuiControlDef::name => '',
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_LABEL,
            GuiControlDef::specific_def => array(GuiLabelDef::caption => $text),
            GuiControlDef::params => array('smart' => true),
        );
    }

    /**
     * @param array &$defs
     * @param string $title
     * @param string $text
     * @param int $num_lines
     */
    public static function add_multiline_label(&$defs, $title, $text, $num_lines = 2)
    {
        $defs[] = array(
            GuiControlDef::name => '',
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_LABEL,
            GuiControlDef::specific_def => array(GuiLabelDef::caption => $text,),
            GuiControlDef::params => array('smart' => false, 'max_lines' => $num_lines),
        );
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param array|null $add_params
     * @param string $name
     * @param string $title
     * @param string $caption
     * @param int $width
     * @param bool $caption_centered
     */
    public static function add_button(&$defs, $handler, $add_params,
                                      $name, $title, $caption, $width, $caption_centered = false)
    {
        $push_action = User_Input_Handler_Registry::create_action($handler, $name, null, $add_params);
        $push_action['params']['action_type'] = 'apply';

        $defs[] = array
        (
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::params => array('button_caption_centered' => $caption_centered),
            GuiControlDef::specific_def => array
            (
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => $push_action,
            ),
        );
    }

    public static function add_button_close(&$defs, $handler, $add_params,
                                            $name, $title, $caption, $width)
    {
        $push_action = User_Input_Handler_Registry::create_action($handler, $name, null, $add_params);
        $push_action['params']['action_type'] = 'apply';

        $defs[] = array
        (
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::specific_def =>array
            (
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => Action_Factory::close_dialog_and_run($push_action),
            ),
        );
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param array|null $add_params
     * @param string $name
     * @param string $title
     * @param string $caption
     * @param string $image
     * @param int $width
     */
    public static function add_image_button(&$defs, $handler, $add_params, $name, $title, $caption, $image, $width = 0)
    {
        $push_action = User_Input_Handler_Registry::create_action($handler, $name, null, $add_params);
        $push_action['params']['action_type'] = 'apply';

        $defs[] = array
        (
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::specific_def => array
            (
                GuiButtonDef::caption => '',
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => $push_action,
            ),
        );

        self::add_vgap($defs, -65);
        self::add_smart_label($defs, null, "<gap width=15/><icon dy='-2'>$image</icon><gap width=20/><text dy='-2'>$caption</text>");
    }

    /**
     * @param array &$defs
     * @param string $caption
     * @param int $width
     * @param bool $caption_centered
     */
    public static function add_close_dialog_button(&$defs, $caption, $width, $caption_centered = false)
    {
        $defs[] = array
        (
            GuiControlDef::name => 'close',
            GuiControlDef::title => null,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::params => array('button_caption_centered' => $caption_centered),
            GuiControlDef::specific_def => array
            (
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => Action_Factory::close_dialog(),
            ),
        );
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param array|null $add_params
     * @param string $name
     * @param string $caption
     * @param int $width
     */
    public static function add_close_dialog_and_apply_button(&$defs, $handler, $add_params, $name, $caption, $width)
    {
        $push_action = User_Input_Handler_Registry::create_action($handler, $name, null, $add_params);
        $push_action['params']['action_type'] = 'apply';

        $defs[] = array
        (
            GuiControlDef::name => $name,
            GuiControlDef::title => null,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::specific_def => array
            (
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => Action_Factory::close_dialog_and_run($push_action),
            ),
        );
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param array|null $add_params
     * @param string $name
     * @param string $title
     * @param string $caption
     * @param int $width
     */
    public static function add_close_dialog_and_apply_button_title(&$defs, $handler, $add_params, $name, $title, $caption, $width)
    {
        $push_action = User_Input_Handler_Registry::create_action($handler, $name, null, $add_params);
        $push_action['params']['action_type'] = 'apply';

        $defs[] = array
        (
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::specific_def => array
            (
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => Action_Factory::close_dialog_and_run($push_action),
            ),
        );
    }

    /**
     * @param array &$defs
     * @param string $name
     * @param string $caption
     * @param int $width
     * @param array $action
     */
    public static function add_custom_close_dialog_and_apply_buffon(&$defs, $name, $caption, $width, $action)
    {
        $defs[] = array
        (
            GuiControlDef::name => $name,
            GuiControlDef::title => null,
            GuiControlDef::kind => GUI_CONTROL_BUTTON,
            GuiControlDef::specific_def => array
            (
                GuiButtonDef::caption => $caption,
                GuiButtonDef::width => $width,
                GuiButtonDef::push_action => Action_Factory::close_dialog_and_run($action),
            ),
        );
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param array|null $add_params
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
     */
    public static function add_text_field(&$defs,
                                          $handler, $add_params,
                                          $name, $title, $initial_value,
                                          $numeric, $password, $has_osk, $always_active, $width,
                                          $need_confirm = false, $need_apply = false)
    {
        $apply_action = null;
        if ($need_apply) {
            $apply_action = User_Input_Handler_Registry::create_action($handler, $name, null, $add_params);
            $apply_action['params']['action_type'] = 'apply';
        }

        $confirm_action = null;
        if ($need_confirm) {
            $confirm_action = User_Input_Handler_Registry::create_action($handler, $name, null, $add_params);
            $confirm_action['params']['action_type'] = 'confirm';
        }

        $defs[] = array
        (
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_TEXT_FIELD,
            GuiControlDef::specific_def => array
            (
                GuiTextFieldDef::initial_value => (string)$initial_value,
                GuiTextFieldDef::numeric => (int)$numeric,
                GuiTextFieldDef::password => (int)$password,
                GuiTextFieldDef::has_osk => (int)$has_osk,
                GuiTextFieldDef::always_active => (int)$always_active,
                GuiTextFieldDef::width => (int)$width,
                GuiTextFieldDef::apply_action => $apply_action,
                GuiTextFieldDef::confirm_action => $confirm_action,
            ),
        );
    }

    /**
     * @param array &$defs
     * @param User_Input_Handler $handler
     * @param array|null $add_params
     * @param string $name
     * @param string $title
     * @param string $initial_value
     * @param array $value_caption_pairs
     * @param int $width
     * @param bool $need_confirm
     * @param bool $need_apply
     */
    public static function add_combobox(&$defs,
                                        $handler, $add_params,
                                        $name, $title, $initial_value, $value_caption_pairs, $width,
                                        $need_confirm = false, $need_apply = false)
    {
        $apply_action = null;
        if ($need_apply) {
            $apply_action = User_Input_Handler_Registry::create_action($handler, $name, null, $add_params);
            $apply_action['params']['action_type'] = 'apply';
        }

        $confirm_action = null;
        if ($need_confirm) {
            $confirm_action = User_Input_Handler_Registry::create_action($handler, $name, null, $add_params);
            $confirm_action['params']['action_type'] = 'confirm';
        }

        $defs[] = array
        (
            GuiControlDef::name => $name,
            GuiControlDef::title => $title,
            GuiControlDef::kind => GUI_CONTROL_COMBOBOX,
            GuiControlDef::specific_def => array
            (
                GuiComboboxDef::initial_value => $initial_value,
                GuiComboboxDef::value_caption_pairs => $value_caption_pairs,
                GuiComboboxDef::width => $width,
                GuiComboboxDef::apply_action => $apply_action,
                GuiComboboxDef::confirm_action => $confirm_action,
            ),
        );

        self::add_vgap($defs, 4);
    }
}
