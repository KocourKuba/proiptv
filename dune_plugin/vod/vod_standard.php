<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/abstract_vod.php';
require_once 'lib/movie.php';
require_once 'lib/default_dune_plugin.php';
require_once 'lib/history_item.php';

require_once 'starnet_vod_search_screen.php';
require_once 'starnet_vod_filter_screen.php';
require_once 'starnet_vod_category_list_screen.php';
require_once 'starnet_vod_list_screen.php';
require_once 'starnet_vod_movie_screen.php';
require_once 'starnet_vod_seasons_list_screen.php';
require_once 'starnet_vod_series_list_screen.php';
require_once 'starnet_vod_favorites_screen.php';
require_once 'starnet_vod_history_screen.php';

///////////////////////////////////////////////////////////////////////////

class vod_standard extends Abstract_Vod
{
    const VOD_FAVORITES_LIST = 'vod_favorite_items';
    const VOD_HISTORY_ITEMS = 'vod_history_items';

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    /**
     * @var Provider_Config
     */
    protected $provider;

    /**
     * @var array|false
     */
    protected $vod_items;

    /**
     * @template Group
     * @var Hashed_Array<string, Group>
     */
    protected $special_groups;

    /**
     * @var M3uParser
     */
    protected $m3u_parser;

    /**
     * @var array[]
     */
    protected $vod_m3u_indexes;

    /**
     * @var string
     */
    protected $vod_pattern;

    /**
     * @var string
     */
    protected $vod_source;

    /**
     * @var array
     */
    protected $vod_filters = array();

    /**
     * @var bool
     */
    protected $vod_quality = false;

    /**
     * @var array
     */
    protected $pages = array();

    /**
     * @var bool
     */
    protected $is_entered = false;

    /**
     * @var array
     */
    protected $movie_counter = array();

    /**
     * @var array
     */
    protected $filters = array();

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->m3u_parser = new M3uParser();
        $this->special_groups = new Hashed_Array();
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Provider_Config $provider
     * @return void
     */
    public function init_vod($provider)
    {
        $this->provider = $provider;
        $this->special_groups->clear();

        $this->plugin->destroy_screen(Starnet_Vod_Favorites_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_History_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Category_List_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_List_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Movie_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Seasons_List_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Series_List_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Search_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Filter_Screen::ID);

        $this->plugin->create_screen(new Starnet_Vod_Favorites_Screen($this->plugin));
        $this->plugin->create_screen(new Starnet_Vod_History_Screen($this->plugin));
        $this->plugin->create_screen(new Starnet_Vod_Category_List_Screen($this->plugin));
        $this->plugin->create_screen(new Starnet_Vod_List_Screen($this->plugin));
        $this->plugin->create_screen(new Starnet_Vod_Movie_Screen($this->plugin));
        $this->plugin->create_screen(new Starnet_Vod_Seasons_List_Screen($this->plugin));
        $this->plugin->create_screen(new Starnet_Vod_Series_List_Screen($this->plugin));
        $this->plugin->create_screen(new Starnet_Vod_Search_Screen($this->plugin));
        $this->plugin->create_screen(new Starnet_Vod_Filter_Screen($this->plugin));

        $this->vod_source = $this->provider->replace_macros($this->provider->getVodConfigValue('vod_source'));
        $this->vod_pattern = $this->provider->getVodConfigValue('vod_parser');
        if (!empty($this->vod_pattern)) {
            $this->vod_pattern = "/$this->vod_pattern/";
        }
        $this->vod_quality = $this->provider->getVodConfigValue('vod_quality');

        // Favorites category
        $special_group = new Default_Group($this->plugin,
            FAVORITES_MOVIE_GROUP_ID,
            TR::load_string(Default_Group::FAV_MOVIES_GROUP_CAPTION),
            Default_Group::FAV_MOVIES_GROUP_ICON);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // History channels category
        $special_group = new Default_Group($this->plugin,
            HISTORY_MOVIES_GROUP_ID,
            TR::load_string(Default_Group::HISTORY_MOVIES_GROUP_CAPTION),
            Default_Group::HISTORY_MOVIES_GROUP_ICON,
            false);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // Search category
        $special_group = new Default_Group($this->plugin,
            SEARCH_MOVIES_GROUP_ID,
            TR::load_string(Default_Group::SEARCH_MOVIES_GROUP_CAPTION),
            Default_Group::SEARCH_MOVIES_GROUP_ICON,
            true);
        $this->special_groups->set($special_group->get_id(), $special_group);

        // Filter category
        $special_group = new Default_Group($this->plugin,
            FILTER_MOVIES_GROUP_ID,
            TR::load_string(Default_Group::FILTER_MOVIES_GROUP_CAPTION),
            Default_Group::FILTER_MOVIES_GROUP_ICON,
            true);
        $special_group->set_disabled(empty($this->vod_filters));

        $this->special_groups->set($special_group->get_id(), $special_group);
    }

    /**
     * @return M3uParser
     */
    public function get_m3u_parser()
    {
        return $this->m3u_parser;
    }

    /**
     * @return Hashed_Array
     */
    public function get_special_groups()
    {
        return $this->special_groups;
    }

    /**
     * @return Group
     */
    public function get_special_group($id)
    {
        return $this->special_groups->get($id);
    }

    /**
     * @return bool
     */
    public function getVodQuality()
    {
        return $this->vod_quality;
    }

    public function try_reset_pages()
    {
        if ($this->is_entered) {
            $this->is_entered = false;
            $this->pages = array();
        }
    }

    public function reset_movie_counter()
    {
        $this->is_entered = true;
        $this->movie_counter = array();
    }

    /**
     * @param mixed $key
     * @return integer
     */
    public function get_movie_counter($key)
    {
        if (!array_key_exists($key, $this->movie_counter)) {
            $this->movie_counter[$key] = 0;
        }

        return $this->movie_counter[$key];
    }

    /**
     * @param string $key
     * @param int $val
     */
    public function add_movie_counter($key, $val)
    {
        // repeated count data
        if (!array_key_exists($key, $this->movie_counter)) {
            $this->movie_counter[$key] = 0;
        }

        $this->movie_counter[$key] += $val;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function try_load_movie($movie_id)
    {
        $this->set_cached_movie($this->TryLoadMovie($movie_id));
    }

    /**
     * @param string $idx
     * @param int $increment
     * @return int
     */
    public function get_next_page($idx, $increment = 1)
    {
        if (!array_key_exists($idx, $this->pages)) {
            $this->pages[$idx] = 0;
        }

        $this->pages[$idx] += $increment;

        return $this->pages[$idx];
    }

    /**
     * @param string $idx
     * @param int $value
     */
    public function set_next_page($idx, $value)
    {
        $this->pages[$idx] = $value;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function get_filter($name)
    {
        return isset($this->filters[$name]) ? $this->filters[$name] : null;
    }

    /**
     * @param array $filters
     */
    public function set_filters($filters)
    {
        $this->filters = $filters;
    }

    /**
     * @param string $fav_op_type
     * @param string $movie_id
     */
    public function change_vod_favorites($fav_op_type, $movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("action: $fav_op_type, moive id: $movie_id", true);
        $order = &$this->get_special_group(FAVORITES_MOVIE_GROUP_ID)->get_items_order();

        switch ($fav_op_type) {
            case PLUGIN_FAVORITES_OP_ADD:
                if ($order->add_item($movie_id)) {
                    hd_debug_print("Movie id: $movie_id added to favorites");
                }
                break;

            case PLUGIN_FAVORITES_OP_REMOVE:
                if ($order->remove_item($movie_id)) {
                    hd_debug_print("Movie id: $movie_id removed from favorites");
                }
                break;

            case ACTION_ITEMS_CLEAR:
                hd_debug_print("Movie favorites cleared");
                $order->clear();
                break;

            case PLUGIN_FAVORITES_OP_MOVE_UP:
                $order->arrange_item($movie_id, Ordered_Array::UP);
                break;

            case PLUGIN_FAVORITES_OP_MOVE_DOWN:
                $order->arrange_item($movie_id, Ordered_Array::DOWN);
                break;
            default:
        }
    }

    /**
     * @param array $vod_info
     * @param bool $is_external
     * @return array|null
     */
    public function vod_player_exec($vod_info, $is_external)
    {
        if (!isset($vod_info[PluginVodInfo::initial_series_ndx], $vod_info[PluginVodInfo::series][$vod_info[PluginVodInfo::initial_series_ndx]])) {
            return null;
        }

        if (!$is_external) {
            return Action_Factory::vod_play($vod_info);
        }

        $series = $vod_info[PluginVodInfo::series];
        $idx = $vod_info[PluginVodInfo::initial_series_ndx];
        $url = $series[$idx][PluginVodSeriesInfo::playback_url];
        $param_pos = strpos($url, '|||dune_params');
        $url = $param_pos !== false ? substr($url, 0, $param_pos) : $url;
        $cmd = 'am start -d "' . $url . '" -t "video/*" -a android.intent.action.VIEW 2>&1';
        hd_debug_print("play movie in the external player: $cmd");
        exec($cmd, $output);
        hd_debug_print("external player exec result code" . HD::ArrayToStr($output));
        return null;
    }

    /**
     * @param array &$category_list
     * @param array &$category_index
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        hd_debug_print(null, true);
        if (!$this->plugin->init_vod_playlist()) {
            hd_debug_print("VOD not available");
            return;
        }

        $t = microtime(1);

        $this->vod_m3u_indexes = $this->m3u_parser->indexFile();

        $category_list = array();
        $category_index = array();
        $all_indexes = array();
        foreach ($this->vod_m3u_indexes as $index_array) {
            foreach ($index_array as $element) {
                $all_indexes[] = $element;
            }
        }
        sort($all_indexes);
        $this->vod_m3u_indexes[Vod_Category::FLAG_ALL] = $all_indexes;

        // all movies
        $count = count($all_indexes);
        $category = new Vod_Category(Vod_Category::FLAG_ALL, "Все фильмы ($count)");
        $category_list[] = $category;
        $category_index[Vod_Category::FLAG_ALL] = $category;

        foreach ($this->vod_m3u_indexes as $group => $indexes) {
            if ($group === Vod_Category::FLAG_ALL) continue;

            $count = count($indexes);
            $cat = new Vod_Category($group, "$group ($count)");
            $category_list[] = $cat;
            $category_index[$group] = $cat;
        }
        hd_debug_print("Categories read: " . count($category_list));
        hd_debug_print("Fetched categories at " . (microtime(1) - $t) . " secs");
        HD::ShowMemoryUsage();
    }

    /**
     * @param string $keyword
     * @return array
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print($keyword);

        $t = microtime(1);
        $movies = array();
        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));

        foreach ($this->vod_m3u_indexes[Vod_Category::FLAG_ALL] as $index) {
            $title = $this->m3u_parser->getTitleByIdx($index);
            if (empty($title)) continue;

            $search_in = utf8_encode(mb_strtolower($title, 'UTF-8'));
            if (strpos($search_in, $keyword) === false) continue;

            if (!empty($this->vod_pattern) && preg_match($this->vod_pattern, $title, $match)) {
                $title = isset($match['title']) ? $match['title'] : $title;
            }

            $entry = $this->m3u_parser->getEntryByIdx($index);
            if ($entry === null) continue;

            $poster_url = $entry->getEntryAttribute('tvg-logo');
            hd_debug_print("Found at $index movie '$title', poster url: '$poster_url'");
            $movies[] = new Short_Movie($index, $title, $poster_url);
        }

        hd_debug_print("Movies found: " . count($movies));
        hd_debug_print("Search at " . (microtime(1) - $t) . " secs");

        return $movies;
    }

    /**
     * @param string $params
     * @param $from_ndx
     * @return array
     */
    public function getFilterList($params, $from_ndx)
    {
        hd_debug_print("params: $params, from ndx: $from_ndx", true);
        return array();
    }

    /**
     * @param string $query_id
     * @return array
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($query_id);

        $movies = array();
        $arr = explode("_", $query_id);
        $category_id = ($arr === false) ? $query_id : $arr[0];

        $current_offset = $this->get_next_page($query_id, 0);
        $indexes = $this->vod_m3u_indexes[$category_id];

        $max = count($indexes);
        $ubound = min($max, $current_offset + 5000);
        hd_debug_print("Read from: $current_offset to $ubound");

        $pos = $current_offset;
        while($pos < $ubound) {
            $index = $indexes[$pos++];
            $entry = $this->m3u_parser->getEntryByIdx($index);
            if ($entry === null || $entry->isM3U_Header()) continue;

            $title = $entry->getEntryTitle();
            if (!empty($this->vod_pattern) && preg_match($this->vod_pattern, $title, $match)) {
                $title = isset($match['title']) ? $match['title'] : $title;
            }
            $title = trim($title);

            $movies[] = new Short_Movie($index, $title, $entry->getEntryAttribute('tvg-logo'));
        }

        $this->get_next_page($query_id, $pos - $current_offset);

        return $movies;
    }

    /**
     * @param string $movie_id
     * @return Movie
     * @throws Exception
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);
        $movie = new Movie($movie_id, $this->plugin);

        $entry = $this->get_m3u_parser()->getEntryByIdx($movie_id);
        if ($entry === null) {
            hd_debug_print("Movie not found");
        } else {
            $logo = $entry->getEntryAttribute('tvg-logo');
            $title = $entry->getEntryTitle();
            $title_orig = '';
            $country = '';
            $year = '';

            if (!empty($this->vod_pattern) && preg_match($this->vod_pattern, $title, $match)) {
                $title = isset($match['title']) ? $match['title'] : $title;
                $title_orig = isset($match['title_orig']) ? $match['title_orig'] : $title_orig;
                $country = isset($match['country']) ? $match['country'] : $country;
                $year = isset($match['year']) ? $match['year'] : $year;
            }

            $category = '';
            foreach ($this->vod_m3u_indexes as $group => $indexes) {
                if ($group === Vod_Category::FLAG_ALL) continue;
                if (in_array($movie_id, $indexes)) {
                    $category = $group;
                    break;
                }
            }

            $movie->set_data(
                $title,// $xml->caption,
                $title_orig,// $xml->caption_original,
                '',// $xml->description,
                $logo,// $xml->poster_url,
                '',// $xml->length,
                $year,// $xml->year,
                '',// $xml->director,
                '',// $xml->scenario,
                '',// $xml->actors,
                $category,// $xml->genres,
                '',// $xml->rate_imdb,
                '',// $xml->rate_kinopoisk,
                '',// $xml->rate_mpaa,
                $country,// $xml->country,
                ''// $xml->budget
            );

            $movie->add_series_data($movie_id, $title, '', $entry->getPath());
        }

        return $movie;
    }

    /**
     * @param array $defs
     * @param Starnet_Vod_Filter_Screen $parent
     * @param int $initial
     * @return bool
     */
    public function AddFilterUI(&$defs, $parent, $initial = -1)
    {
        if (empty($this->vod_filters)) {
            return false;
        }

        hd_debug_print($initial);
        $added = false;
        Control_Factory::add_vgap($defs, 20);
        foreach ($this->vod_filters as $name) {
            $filter = $this->get_filter($name);
            if ($filter === null) {
                hd_debug_print("no filters with '$name'");
                continue;
            }

            $values = $filter['values'];
            if (empty($values)) {
                hd_debug_print("no filters values for '$name'");
                continue;
            }

            $idx = $initial;
            if ($initial !== -1) {
                $pairs = explode(" ", $initial);
                foreach ($pairs as $pair) {
                    if (strpos($pair, $name . ":") !== false && preg_match("/^$name:(.+)/", $pair, $m)) {
                        $idx = array_search($m[1], $values) ?: -1;
                        break;
                    }
                }
            }

            Control_Factory::add_combobox($defs, $parent, null, $name,
                $filter['title'], $idx, $values, 600, true);

            Control_Factory::add_vgap($defs, 30);
            $added = true;
        }

        return $added;
    }

    /**
     * @param array $user_input
     * @return string
     */
    public function CompileSaveFilterItem($user_input)
    {
        if (empty($this->vod_filters)) {
            return '';
        }

        $compiled_string = "";
        foreach ($this->vod_filters as $name) {
            $filter = $this->get_filter($name);
            if ($filter !== null && $user_input->{$name} !== -1) {
                if (!empty($compiled_string)) {
                    $compiled_string .= ",";
                }

                $compiled_string .= $name . ":" . $filter['values'][$user_input->{$name}];
            }
        }

        return $compiled_string;
    }

    /**
     * @param string $url
     * @return string
     */
    public function UpdateDuneParams($url)
    {
        $dune_params = $this->plugin->get_setting(PARAM_DUNE_PARAMS, array());
        $dune_params_str = '';
        foreach ($dune_params as $key => $param) {
            if (!empty($dune_params_str)) {
                $dune_params_str .= ',';
            }
            $dune_params_str .= "$key:$param";
        }

        if (!empty($dune_params_str)) {
            $url .= "|||dune_params|||$dune_params_str";
        }

        return $url;
    }

    /**
     * @return string
     */
    protected function get_vod_cache_file()
    {
        return get_temp_path($this->plugin->get_active_playlist_key() . "_playlist_vod.json");
    }
}
