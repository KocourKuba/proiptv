<?php
///////////////////////////////////////////////////////////////////////////

require_once 'abstract_regular_screen.php';

abstract class Abstract_Preloaded_Regular_Screen extends Abstract_Regular_Screen
{
    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    abstract public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies);

    /**
     * @param MediaURL $media_url
     * @param int $from_ndx
     * @param $plugin_cookies
     * @return array
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        return HD::create_regular_folder_range($this->get_all_folder_items($media_url, $plugin_cookies), $from_ndx);
    }

    /**
     * @param MediaURL $parent_media_url
     * @param $plugin_cookies
     * @param int $sel_ndx
     * @return array
     */
    public function invalidate_current_folder2(MediaURL $parent_media_url, $plugin_cookies, $sel_ndx = -1)
    {
        return Action_Factory::update_regular_folder(
            $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
            true,
            $sel_ndx);
    }

    /**
     * @param MediaURL $parent_media_url
     * @param $plugin_cookies
     * @param int $sel_ndx
     * @return array
     */
    public function invalidate_current_folder(MediaURL $parent_media_url, $plugin_cookies, $sel_ndx = -1)
    {
        return Action_Factory::invalidate_folders(array(static::ID),
            Action_Factory::update_regular_folder(
            $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
            true,
            $sel_ndx)
        );
    }

    /**
     * @param User_Input_Handler $handler
     * @param array $menu_items
     * @param string $action_id
     * @param string $caption
     * @param string $icon
     * @param $add_params array|null
     * @return void
     */
    public function create_menu_item($handler, &$menu_items, $action_id, $caption = null, $icon = null, $add_params = null)
    {
        if ($action_id === GuiMenuItemDef::is_separator) {
            $menu_items[] = array($action_id => true);
        } else {
            $menu_items[] = User_Input_Handler_Registry::create_popup_item($handler,
                $action_id, $caption, ($icon === null) ? null : get_image_path($icon), $add_params);
        }
    }
}
