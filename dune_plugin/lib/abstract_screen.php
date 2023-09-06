<?php
require_once 'screen.php';

class Abstract_Screen implements Screen
{
    const ID = 'abstract_screen';

    protected $plugin;
    private $need_update_epfs = false;

    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public static function get_handler_id()
    {
        return static::get_id() . '_handler';
    }


    public function invalidate_epfs()
    {
        $this->need_update_epfs = true;
    }

    /**
     * @param $plugin_cookies
     * @param array|null $media_urls
     * @param null $post_action
     * @return array
     */
    public function update_epfs_data($plugin_cookies, $media_urls, $post_action = null)
    {
        if ($this->need_update_epfs) {
            $this->plugin->save();
            $this->need_update_epfs = false;
            Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
        }
        return Starnet_Epfs_Handler::invalidate_folders($media_urls, $post_action);
    }

    /**
     * @return false|string
     */
    public static function get_media_url_str()
    {
        return MediaURL::encode(array('screen_id' => static::ID));
    }

    ///////////////////////////////////////////////////////////////////////
    // Screen interface

    /**
     * @inheritDoc
     */
    public static function get_id()
    {
        return static::ID;
    }

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function get_next_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        return null;
    }
}
