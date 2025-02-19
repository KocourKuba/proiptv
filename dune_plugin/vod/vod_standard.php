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

require_once 'lib/abstract_vod.php';
require_once 'lib/movie.php';
require_once 'lib/default_dune_plugin.php';
require_once 'lib/history_item.php';
require_once 'lib/perf_collector.php';

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
     * @var api_default
     */
    protected $provider;

    /**
     * @var array|false
     */
    protected $vod_items;

    /**
     * @template Group
     * @var Hashed_Array<Group>
     */
    protected $special_groups;

    /**
     * @var array
     */
    protected $vod_filters = array();

    /**
     * @var bool
     */
    protected $vod_quality = false;

    /**
     * @var bool
     */
    protected $vod_audio = false;

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
     * @var string
     */
    protected $vod_parser;

    /**
     * @var Sql_Wrapper
     */
    protected $wrapper = null;

    /**
     * @var Perf_Collector
     */
    protected $perf;

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->special_groups = new Hashed_Array();
        $this->perf = new Perf_Collector();
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param api_default|null $provider
     * @return bool
     */
    public function init_vod($provider)
    {
        $this->provider = $provider;
        if ($this->provider) {
            if (!$provider->hasApiCommand(API_COMMAND_GET_VOD)) {
                return false;
            }
            $this->vod_parser = $this->provider->getConfigValue(CONFIG_VOD_PARSER);
        }

        $this->wrapper = $this->plugin->get_sql_playlist();

        return true;
    }

    /**
     * @return void
     */
    public function init_vod_screens()
    {
        $this->plugin->destroy_screen(Starnet_Vod_Favorites_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_History_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Category_List_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_List_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Movie_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Seasons_List_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Series_List_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Search_Screen::ID);
        $this->plugin->destroy_screen(Starnet_Vod_Filter_Screen::ID);

        $this->special_groups->clear();

        if ($this->plugin->is_vod_enabled()) {
            $this->plugin->create_screen(new Starnet_Vod_Favorites_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_History_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Category_List_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_List_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Movie_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Seasons_List_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Series_List_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Search_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Filter_Screen($this->plugin));

            // Favorites category
            $special_group = array(
                'group_id' => FAV_MOVIE_GROUP_ID,
                'title' => FAV_MOVIES_GROUP_CAPTION,
                'icon' => FAV_MOVIES_GROUP_ICON,
                'disabled' => false
            );
            $this->special_groups->set(FAV_MOVIE_GROUP_ID, $special_group);

            // History channels category
            $special_group = array(
                'group_id' => HISTORY_MOVIES_GROUP_ID,
                'title' => HISTORY_MOVIES_GROUP_CAPTION,
                'icon' => HISTORY_MOVIES_GROUP_ICON,
                'order_support' => false,
                'disabled' => false,
            );
            $this->special_groups->set(HISTORY_MOVIES_GROUP_ID, $special_group);

            // Search category
            $special_group = array(
                'group_id' => SEARCH_MOVIES_GROUP_ID,
                'title' => SEARCH_MOVIES_GROUP_CAPTION,
                'icon' => SEARCH_MOVIES_GROUP_ICON,
                'order_support' => true,
                'disabled' => false,
            );
            $this->special_groups->set(SEARCH_MOVIES_GROUP_ID, $special_group);

            // Filter category
            $special_group = array(
                'group_id' => FILTER_MOVIES_GROUP_ID,
                'title' => FILTER_MOVIES_GROUP_CAPTION,
                'icon' => FILTER_MOVIES_GROUP_ICON,
                'order_support' => true,
                'disabled' => empty($this->vod_filters),
            );
            $this->special_groups->set(FILTER_MOVIES_GROUP_ID, $special_group);
        }
    }

    /**
     * @return Hashed_Array
     */
    public function get_special_groups()
    {
        return $this->special_groups;
    }

    /**
     * @return array
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

    /**
     * @return bool
     */
    public function getVodAudio()
    {
        return $this->vod_audio;
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
        $movie = $this->TryLoadMovie($movie_id);
        if (!is_null($movie)) {
            $this->set_cached_movie($movie);
        }
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

        $entry = $this->getVod($movie_id);
        if (empty($entry)) {
            hd_debug_print("Movie not found");
            $movie = null;
        } else {
            $logo = $entry['icon'];
            $title = $entry['title'];
            $category = $entry['group_id'];
            $path = $entry['path'];
            $title_orig = '';
            $country = '';
            $year = '';

            if (!empty($this->vod_parser) && preg_match($this->vod_parser, $title, $match)) {
                $title = safe_get_value($match, 'title', $title);
                $title_orig = safe_get_value($match, 'title_orig', $title_orig);
                $country = safe_get_value($match, 'country', $country);
                $year = safe_get_value($match, 'year', $year);
            }

            $movie = new Movie($movie_id, $this->plugin);
            $movie->set_data(
                $title,            // caption,
                $title_orig,       // caption_original,
                '',      // description,
                $logo,             // poster_url,
                '',      // length,
                $year,             // year,
                '',     // director,
                '',     // scenario,
                '',       // actors,
                $category,         // genres,
                '',       // rate_imdb,
                '',    // rate_kinopoisk,
                '',       // rate_mpaa,
                $country           // country,
            );

            $movie->add_series_data($movie_id, $title, '', $path);
        }

        return $movie;
    }

    /**
     * @param string $page_id
     * @param int $value
     */
    public function set_next_page($page_id, $value)
    {
        hd_debug_print("set_next_page page_id: $page_id idx: $value", true);
        $this->pages[$page_id] = $value;
    }

    /**
     * @param array $filters
     */
    public function set_filters($filters)
    {
        $this->filters = $filters;
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
     * @param Vod_Category[] &$category_list
     * @param array &$category_index
     * @return bool
     */
    public function fetchVodCategories(&$category_list, &$category_index)
    {
        hd_debug_print(null, true);
        if (!$this->plugin->init_vod_playlist()) {
            hd_debug_print("VOD not available");
            return false;
        }

        if ($this->plugin->get_vod_m3u_parser()->parseVodPlaylist($this->wrapper) === false) {
            hd_debug_print("Parse VOD failed");
            return false;
        }

        $this->perf->reset('start');

        $category_index = array();

        // all movies must be first
        $all_count = $this->getVodCount();
        $category = new Vod_Category(Vod_Category::FLAG_ALL_MOVIES, TR::t('vod_screen_all_movies__1', " ($all_count)"));
        $category_index[Vod_Category::FLAG_ALL_MOVIES] = $category;

        $category_count = 0;
        foreach ($this->getVodGroups() as $group) {
            $count = $this->getVodCount($group);
            if ($count === 0) continue;

            $category_count++;
            $cat = new Vod_Category($group, "$group ($count)");
            $category_index[$group] = $cat;
        }

        $category_list = array();
        foreach ($category_index as $cat) {
            $category_list[] = $cat;
        }

        $this->perf->setLabel('end');
        $report = $this->perf->getFullReport();

        hd_debug_print("Categories read: $category_count");
        hd_debug_print("Total movies: $all_count");
        hd_debug_print("Fetch time: {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_debug_print_separator();

        return true;
    }

    /**
     * @param string $keyword
     * @return array
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print($keyword);

        $this->perf->reset('start');

        $movies = array();
        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));

        foreach ($this->getVodEntries() as $entry) {
            $title = $entry['title'];
            if (empty($title)) continue;

            $search_in = utf8_encode(mb_strtolower($title, 'UTF-8'));
            if (strpos($search_in, $keyword) === false) continue;

            if (!empty($this->vod_parser) && preg_match($this->vod_parser, $title, $match)) {
                $title = safe_get_value($match, 'title', $title);
            }

            $poster_url = $entry['icon'];
            hd_debug_print("Found movie '$title', poster url: '$poster_url'", true);
            $movies[] = new Short_Movie($entry['hash'], $title, $poster_url);
        }

        $this->perf->setLabel('end');
        $report = $this->perf->getFullReport();

        hd_debug_print("Movies found: " . count($movies));
        hd_debug_print("Search time: {$report[Perf_Collector::TIME]} secs");

        return $movies;
    }

    /**
     * @param string $params
     * @return array
     */
    public function getFilterList($params)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $params");
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

        $page_idx = $this->get_current_page($query_id);
        if ($page_idx < 0)
            return array();

        $max = $this->getVodCount($category_id);
        $ubound = min($max, $page_idx + 500);

        hd_debug_print("Read from: $page_idx to $ubound");
        $entries = $this->getVodEntries($category_id, $page_idx, $ubound);

        $pos = $page_idx;
        foreach ($entries as $entry) {
            $pos++;

            $title = $entry['title'];
            if (!empty($this->vod_parser) && preg_match($this->vod_parser, $title, $match)) {
                $title = safe_get_value($match, 'title', $title);
            }

            $movies[] = new Short_Movie($entry['hash'], trim($title), $entry['icon']);
        }

        $this->get_next_page($query_id, $pos - $page_idx);

        return $movies;
    }

    /**
     * @param string $page_id
     * @return int
     */
    public function get_current_page($page_id)
    {
        hd_debug_print(null, true);
        $current_idx = array_key_exists($page_id, $this->pages) ? $this->pages[$page_id] : 0;
        hd_debug_print("get_current_page page_id: $page_id current_idx: $current_idx", true);
        return $current_idx;
    }

    /**
     * @param string $page_id
     * @param int $increment
     * @return int
     */
    public function get_next_page($page_id, $increment = 1)
    {
        if (!array_key_exists($page_id, $this->pages)) {
            $this->pages[$page_id] = 0;
        }

        if ($this->pages[$page_id] !== -1) {
            $this->pages[$page_id] += $increment;
        }

        hd_debug_print("get_next_page page_id: $page_id next_idx: {$this->pages[$page_id]}", true);
        return $this->pages[$page_id];
    }

    /**
     * @param Starnet_Vod_Filter_Screen $parent
     * @param int $initial
     * @return array|null
     */
    public function AddFilterUI($parent, $initial = -1)
    {
        if (empty($this->vod_filters)) {
            return null;
        }

        hd_debug_print($initial);
        $added = false;
        if ($initial !== -1) {
            $user_filter = $this->plugin->get_table_value(VOD_FILTER_LIST, $initial);
        } else {
            $user_filter = '';
        }

        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        foreach ($this->vod_filters as $name) {
            $filter = $this->get_filter($name);
            hd_debug_print("filter: $name : " . json_encode($filter));
            if ($filter === null) {
                hd_debug_print("no filters with '$name'");
                continue;
            }

            // fill get value from already set user filter
            if (!empty($user_filter)) {
                $pairs = explode(",", $user_filter);
                foreach ($pairs as $pair) {
                    if (strpos($pair, $name . ":") !== false && preg_match("/^$name:(.+)/", $pair, $m)) {
                        $user_value = $m[1];
                        break;
                    }
                }
            }

            if (isset($filter['text'])) {
                $initial_value = isset($user_value) ? $user_value : '';
                Control_Factory::add_text_field($defs, $parent, null, $name,
                    $filter['title'], $initial_value, true, false, false, false, 600);
                Control_Factory::add_vgap($defs, 20);
                $added = true;
            }

            if (!empty($filter['values'])) {
                $idx = -1;
                if (isset($user_value)) {
                    $idx = array_search($user_value, $filter['values']) ?: -1;
                }

                Control_Factory::add_combobox($defs, $parent, null, $name,
                    $filter['title'], $idx, $filter['values'], 600);
                Control_Factory::add_vgap($defs, 20);
                $added = true;
            }
        }

        if (!$added) {
            return null;
        }

        Control_Factory::add_close_dialog_and_apply_button($defs, $parent, array(ACTION_ITEMS_EDIT => $initial), ACTION_RUN_FILTER, TR::t('ok'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);
        return Action_Factory::show_dialog(TR::t('filter'), $defs, true);
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
     * @param Object $user_input
     * @return string
     */
    public function CompileSaveFilterItem($user_input)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (empty($this->vod_filters)) {
            return '';
        }

        $compiled_string = "";
        foreach ($this->vod_filters as $name) {
            $filter = $this->get_filter($name);
            if ($filter === null) continue;

            $add_text = '';
            if (isset($filter['text']) && !empty($user_input->{$name})) {
                $add_text = $user_input->{$name};
            } else if ((int)$user_input->{$name} !== -1) {
                $add_text = $filter['values'][$user_input->{$name}];
            }

            if (empty($add_text)) {
                continue;
            }

            if (!empty($compiled_string)) {
                $compiled_string .= ",";
            }

            $compiled_string .= $name . ":" . $add_text;
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
            $url .= HD::DUNE_PARAMS_MAGIC . $dune_params_str;
        }

        return $url;
    }

    /**
     * @return bool
     */
    protected function load_vod_json_full($assoc = false)
    {
        $this->vod_items = false;
        $tmp_file = $this->get_vod_cache_file();
        $need_load = true;
        if (file_exists($tmp_file)) {
            $mtime = filemtime($tmp_file);
            $diff = time() - $mtime;
            if ($diff > 3600) {
                hd_debug_print("Vod playlist cache expired " . ($diff - 3600) . " sec ago. Timestamp $mtime. Forcing reload");
                unlink($tmp_file);
            } else {
                $need_load = false;
            }
        }

        if (!$need_load) {
            $this->vod_items = parse_json_file($tmp_file, $assoc);
        } else {
            $response = $this->provider->execApiCommand(API_COMMAND_GET_VOD, $tmp_file);
            if ($response === false) {
                $exception_msg = TR::load_string('err_load_vod') . "\n\n" . $this->provider->getCurlWrapper()->get_raw_response_headers();
                HD::set_last_error($this->plugin->get_vod_error_name(), $exception_msg);
                if (file_exists($tmp_file)) {
                    unlink($tmp_file);
                }
            } else {
                $this->vod_items = Curl_Wrapper::decodeJsonResponse(true, $tmp_file, $assoc);
                if ($this->vod_items === false) {
                    $exception_msg = TR::load_string('err_decoding_vod');
                    HD::set_last_error($this->plugin->get_vod_error_name(), $exception_msg);
                    if (file_exists($tmp_file)) {
                        unlink($tmp_file);
                    }
                }
            }
        }

        return $this->vod_items !== false;
    }

    /**
     * @return string
     */
    public function get_vod_cache_file()
    {
        return get_temp_path($this->plugin->get_active_playlist_key() . "_playlist_vod.json");
    }

    /**
     * get indexes count for selected group
     *
     * @param string $group_id
     * @return int
     */
    public function getVodCount($group_id = '')
    {
        if ($this->wrapper === null) {
            return 0;
        }

        $where = '';
        if (!empty($groupId)) {
            $group_id = SQLite3::escapeString($group_id);
            $where = "WHERE group_id == '$group_id';";
        }

        $query = "SELECT count(*) FROM " . M3uParser::VOD_TABLE . " $where;";
        $result = $this->wrapper->query_value($query);
        return empty($result) ? 0 : (int)$result;
    }

    /**
     * get indexes count for selected group
     *
     * @param string $group_id
     * @return array
     */
    public function getVodEntries($group_id = '', $from = 0, $limit = 0)
    {
        if ($this->wrapper === null) {
            return array();
        }

        $where = '';
        if (!empty($group_id)) {
            $group_id = SQLite3::escapeString($group_id);
            $where = "WHERE group_id == '$group_id';";
        }

        if ($limit > 0) {
            $limit = "LIMIT $from, $limit";
        }

        $query = "SELECT * FROM " . M3uParser::VOD_TABLE . " $where $limit;";
        return $this->wrapper->fetch_array($query);
    }

    /**
     * get groups
     *
     * @return array
     */
    public function getVodGroups()
    {
        if ($this->wrapper === null) {
            return array();
        }

        $query = "SELECT DISTINCT group_id FROM " . M3uParser::VOD_TABLE . ";";
        return $this->wrapper->fetch_single_array($query, 'group_id');
    }

    /**
     * get entry by idx
     *
     * @param string $hash
     * @return array
     */
    public function getVod($hash)
    {
        if ($this->wrapper === null) {
            return array();
        }

        $query = "SELECT * FROM " . M3uParser::VOD_TABLE . " WHERE hash = '$hash';";
        return $this->wrapper->query_value($query, true);
    }
}
