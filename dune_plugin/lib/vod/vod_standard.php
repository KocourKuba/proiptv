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

require_once 'abstract_vod.php';
require_once 'movie.php';

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
require_once 'starnet_vod_movie_list_screen.php';

///////////////////////////////////////////////////////////////////////////

class vod_standard extends Abstract_Vod
{
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
     * @var array
     */
    protected $filter_types = array();

    /**
     * @var Vod_Category[]
     */
    protected $category_index;

    /**
     * @var string
     */
    protected $vod_parser;

    /**
     * @var M3uParser
     */
    protected $vod_m3u_parser;

    /**
     * @var Sql_Wrapper
     */
    protected $wrapper = null;

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->special_groups = new Hashed_Array();
        $this->vod_m3u_parser = new M3uParser();
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
        $this->category_index = null;

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
        $this->plugin->destroy_screen(Starnet_Vod_Movie_List_Screen::ID);
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
            $this->plugin->create_screen(new Starnet_Vod_Movie_List_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_List_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Movie_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Seasons_List_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Series_List_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Search_Screen($this->plugin));
            $this->plugin->create_screen(new Starnet_Vod_Filter_Screen($this->plugin));

            // Favorites category
            $special_group = array(
                COLUMN_GROUP_ID => VOD_FAV_GROUP_ID,
                COLUMN_TITLE => VOD_FAV_GROUP_CAPTION,
                COLUMN_ICON => VOD_FAV_GROUP_ICON,
                ACTION_ORDER_SUPPORT => true,
                ACTION_DISABLED => false
            );
            $this->special_groups->set(VOD_FAV_GROUP_ID, $special_group);

            // History channels category
            $special_group = array(
                COLUMN_GROUP_ID => VOD_HISTORY_GROUP_ID,
                COLUMN_TITLE => VOD_HISTORY_GROUP_CAPTION,
                COLUMN_ICON => VOD_HISTORY_GROUP_ICON,
                ACTION_ORDER_SUPPORT => false,
                ACTION_DISABLED => false,
            );
            $this->special_groups->set(VOD_HISTORY_GROUP_ID, $special_group);

            // List VOD
            $special_group = array(
                COLUMN_GROUP_ID => VOD_LIST_GROUP_ID,
                COLUMN_TITLE => VOD_LIST_GROUP_CAPTION,
                COLUMN_ICON => VOD_LIST_GROUP_ICON,
                ACTION_ORDER_SUPPORT => true,
                ACTION_DISABLED => false,
            );
            $this->special_groups->set(VOD_LIST_GROUP_ID, $special_group);

            // Search category
            $special_group = array(
                COLUMN_GROUP_ID => VOD_SEARCH_GROUP_ID,
                COLUMN_TITLE => VOD_SEARCH_GROUP_CAPTION,
                COLUMN_ICON => VOD_SEARCH_GROUP_ICON,
                ACTION_ORDER_SUPPORT => true,
                ACTION_DISABLED => false,
            );
            $this->special_groups->set(VOD_SEARCH_GROUP_ID, $special_group);

            // Filter category
            $special_group = array(
                COLUMN_GROUP_ID => VOD_FILTER_GROUP_ID,
                COLUMN_TITLE => VOD_FILTER_GROUP_CAPTION,
                COLUMN_ICON => VOD_FILTER_GROUP_ICON,
                ACTION_ORDER_SUPPORT => true,
                ACTION_DISABLED => empty($this->vod_filters),
            );
            $this->special_groups->set(VOD_FILTER_GROUP_ID, $special_group);
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
     * @param string $id
     * @return array|null
     */
    public function get_special_group($id)
    {
        return $this->special_groups->get($id);
    }

    /**
     * @param string $id
     * @param bool $disable
     * @return void
     */
    public function toggle_special_group($id, $disable)
    {
        $group = $this->special_groups->get($id);
        $group[ACTION_DISABLED] = $disable;
        $this->special_groups->set($id, $group);
    }

    /**
     * @return Vod_Category[]
     */
    public function getCategoryIndex()
    {
        return $this->category_index;
    }

    /**
     * @param string $movie_id
     * @return Movie
     */
    public function TryLoadMovie($movie_id)
    {
        hd_debug_print(null, true);
        hd_debug_print($movie_id);

        if (empty($movie_id)) {
            hd_debug_print("Movie ID is empty!");
            return null;
        }

        if ($movie_id === VOD_LIST_GROUP_ID) {
            $movie = new Movie($movie_id, $this->plugin);
            $title = TR::t('movie_list');
            $movie->set_data(
                $title,     // caption,
                '',         // caption_original,
                '',         // description,
                VOD_LIST_GROUP_ICON, // poster_url,
                '',         // length,
                '',         // year,
                '',         // director,
                '',         // scenario,
                '',         // actors,
                '',         // genres,
                '',         // rate_imdb,
                '',         // rate_kinopoisk,
                '',         // rate_mpaa,
                ''          // country,
            );

            $movie->add_series_data(new Movie_Series($movie_id, $title, new Movie_Playback_Url('')));
            return $movie;
        }

        $entry = $this->getVod($movie_id);
        if (empty($entry)) {
            hd_debug_print("Movie not found");
            $movie = null;
        } else {
            $logo = $entry[COLUMN_ICON];
            $title = $entry[COLUMN_TITLE];
            $category = $entry[COLUMN_GROUP_ID];
            $url = $entry[COLUMN_PATH];
            $title_orig = '';
            $country = '';
            $year = '';
            $rating = '';

            /** @var array $m */
            if (!empty($this->vod_parser) && preg_match($this->vod_parser, $title, $m)) {
                $title = safe_get_value($m, 'title', $title);
                $title_orig = safe_get_value($m, 'title_orig', '');
                $country = safe_get_value($m, 'country', '');
                $year = safe_get_value($m, 'year', '');
                $rating = safe_get_value($m, 'rating', '');
            }

            $movie = new Movie($movie_id, $this->plugin);
            $movie->set_data(
                $title,         // caption,
                $title_orig,    // caption_original,
                '',             // description,
                $logo,          // poster_url,
                '',             // length,
                $year,          // year,
                '',             // director,
                '',             // scenario,
                '',             // actors,
                $category,      // genres,
                $rating,        // rate_imdb,
                '',             // rate_kinopoisk,
                '',             // rate_mpaa,
                $country        // country,
            );

            $movie->add_series_data(new Movie_Series($movie_id, $title, new Movie_Playback_Url($url)));
        }

        return $movie;
    }

    /**
     * @param string $series_id
     * @param string $quality
     * @param string $audio
     * @return null
     * @noinspection PhpUnusedParameterInspection
     */
    public function get_vod_playback_url($series_id, $quality = 'auto', $audio = 'auto')
    {
        return null;
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
        /** @var array $output */
        exec($cmd, $output);
        hd_debug_print("external player exec result code" . HD::ArrayToStr($output));
        return null;
    }

    /**
     * @return bool
     */
    public function fetchVodCategories()
    {
        hd_debug_print(null, true);

        if (isset($this->category_index)) {
            return true;
        }

        if (!$this->init_vod_m3u_playlist()) {
            hd_debug_print("VOD not available");
            return false;
        }

        if ($this->plugin->get_sql_playlist()->is_database_attached('vod') === 0) {
            $perf = new Perf_Collector();
            $perf->reset('start');

            if ($this->vod_m3u_parser->parseVodPlaylist($this->wrapper) === false) {
                hd_debug_print("Parse VOD failed");
                return false;
            }

            $perf->setLabel('end');
            $report = $perf->getFullReport();

            hd_print_separator();
            hd_debug_print("IndexFile: {$report[Perf_Collector::TIME]} secs");
            hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
            hd_print_separator();
        }

        $perf = new Perf_Collector();
        $perf->reset('start');

        $this->category_index = array();

        // all movies must be first
        $all_count = $this->getVodCount('');
        $category = new Vod_Category(Vod_Category::FLAG_ALL_MOVIES, TR::t('vod_screen_all_movies__1', " ($all_count)"));
        $this->category_index[Vod_Category::FLAG_ALL_MOVIES] = $category;

        $category_count = 0;
        foreach ($this->getVodGroups() as $group) {
            $count = $this->getVodCount($group);
            if ($count === 0) continue;

            $category_count++;
            $this->category_index[$group] = new Vod_Category($group, "$group ($count)");
        }

        // Cleanup VOD play list if movie not exist
        foreach ($this->plugin->get_channels_order(VOD_LIST_GROUP_ID) as $movie_id) {
            $movie = $this->get_loaded_movie($movie_id);
            if (is_null($movie)) {
                $this->plugin->change_channels_order(VOD_LIST_GROUP_ID, $movie_id, true);
            }
        }

        $perf->setLabel('end');
        $report = $perf->getFullReport();

        hd_debug_print("Categories read: $category_count");
        hd_debug_print("Total movies: $all_count");
        hd_debug_print("Fetch time: {$report[Perf_Collector::TIME]} secs");
        hd_debug_print("Memory usage: {$report[Perf_Collector::MEMORY_USAGE_KB]} kb");
        hd_print_separator();

        return true;
    }

    /**
     * @param string $query_id
     * @return array
     */
    public function getMovieList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getMovieList: $query_id");

        $movies = array();
        $arr = explode("_", $query_id);
        $category_id = ($arr === false) ? $query_id : $arr[0];

        $page_idx = $this->get_current_page_index($query_id);
        if ($page_idx < 0) {
            return $movies;
        }

        $group_id = $category_id === Vod_Category::FLAG_ALL_MOVIES ? '' : $category_id;
        $max = $this->getVodCount($group_id);
        $ubound = min($max, $page_idx + 50);

        hd_debug_print("Read from: $page_idx to $ubound");
        $entries = $this->getVodEntries($group_id, $page_idx, $ubound);

        $pos = $page_idx;
        foreach ($entries as $entry) {
            $pos++;

            $title = $entry[COLUMN_TITLE];
            /** @var array $m */
            if (!empty($this->vod_parser) && preg_match($this->vod_parser, $title, $m)) {
                $title = safe_get_value($m, COLUMN_TITLE, $title);
            }

            $movies[] = new Short_Movie($entry[COLUMN_HASH], trim($title), $entry[COLUMN_ICON], $title);
        }

        $this->shift_next_page_index($query_id, $pos - $page_idx);

        return $movies;
    }

    /**
     * @param string $keyword
     * @return array
     */
    public function getSearchList($keyword)
    {
        hd_debug_print(null, true);
        hd_debug_print("getSearchList: $keyword");

        $perf = new Perf_Collector();
        $perf->reset('start');

        $movies = array();
        $keyword = utf8_encode(mb_strtolower($keyword, 'UTF-8'));

        foreach ($this->getVodEntries('') as $entry) {
            $title = $entry[COLUMN_TITLE];
            if (empty($title)) continue;

            $search_in = utf8_encode(mb_strtolower($title, 'UTF-8'));
            if (strpos($search_in, $keyword) === false) continue;

            /** @var array $m */
            if (!empty($this->vod_parser) && preg_match($this->vod_parser, $title, $m)) {
                $title = safe_get_value($m, COLUMN_TITLE, $title);
            }

            $poster_url = $entry[COLUMN_ICON];
            hd_debug_print("Found movie '$title', poster url: '$poster_url'", true);
            $movies[] = new Short_Movie($entry[COLUMN_HASH], $title, $poster_url);
        }

        $perf->setLabel('end');
        $report = $perf->getFullReport();

        hd_debug_print("Movies found: " . count($movies));
        hd_debug_print("Search time: {$report[Perf_Collector::TIME]} secs");

        return $movies;
    }

    /**
     * @param string $query_id
     * @return array
     */
    public function getFilterList($query_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("getFilterList: $query_id");
        return array();
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
            $filter = $this->get_filter_type($name);
            hd_debug_print("filter: $name : " . json_format_unescaped($filter), true);
            if ($filter === null) {
                hd_debug_print("no filters with '$name'");
                continue;
            }

            // fill get value from already set user filter
            if (!empty($user_filter)) {
                $pairs = explode(",", $user_filter);
                foreach ($pairs as $pair) {
                    /** @var array $m */
                    if (strpos($pair, $name . ":") !== false && preg_match("/^$name:(.+)/", $pair, $m)) {
                        $user_value = $m[1];
                        break;
                    }
                }
            }

            if (isset($filter['text'])) {
                $initial_value = isset($user_value) ? $user_value : '';
                Control_Factory::add_text_field($defs, $parent, $name, $filter['title'],
                    $initial_value, true, false, false, false, Control_Factory::DLG_CONTROLS_WIDTH);
                Control_Factory::add_vgap($defs, 20);
                $added = true;
            }

            if (!empty($filter['values'])) {
                $idx = -1;
                if (isset($user_value)) {
                    $idx = array_search($user_value, $filter['values']) ?: -1;
                }

                Control_Factory::add_combobox($defs, $parent, $name, $filter['title'],
                    $idx, $filter['values'], Control_Factory::DLG_CONTROLS_WIDTH, $params);
                Control_Factory::add_vgap($defs, 20);
                $added = true;
            }
        }

        if (!$added) {
            return null;
        }

        Control_Factory::add_close_dialog_and_apply_button($defs, $parent, ACTION_RUN_FILTER, TR::t('ok'),
            array(ACTION_ITEMS_EDIT => $initial)
        );
        Control_Factory::add_cancel_button($defs);
        Control_Factory::add_vgap($defs, 10);
        return Action_Factory::show_dialog($defs, TR::t('filter'));
    }

    /**
     * @param array $filter_types
     */
    public function set_filter_types($filter_types)
    {
        $this->filter_types = $filter_types;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function get_filter_type($name)
    {
        return isset($this->filter_types[$name]) ? $this->filter_types[$name] : null;
    }

    /**
     * @param object $user_input
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
            $filter = $this->get_filter_type($name);
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
     * get indexes count for selected group
     *
     * @param string $group_id
     * @return int
     */
    public function getVodCount($group_id)
    {
        if ($this->wrapper === null) {
            return 0;
        }

        $where = '';
        if (!empty($group_id)) {
            $q_group_id = Sql_Wrapper::sql_quote($group_id);
            $where = "WHERE group_id = $q_group_id";
        }

        $query = "SELECT COUNT(*) FROM " . M3uParser::VOD_TABLE . " $where;";
        return (int)$this->wrapper->query_value($query);
    }

    /**
     * get indexes count for selected group
     *
     * @param string $group_id
     * @return array
     */
    public function getVodEntries($group_id, $from = 0, $limit = 0)
    {
        if ($this->wrapper === null) {
            return array();
        }

        $where = '';
        if (!empty($group_id)) {
            $q_group_id = Sql_Wrapper::sql_quote($group_id);
            $where = " WHERE group_id = $q_group_id";
        }

        $limit_str = $limit > 0 ? "LIMIT $from, $limit" : '';

        $query = "SELECT * FROM " . M3uParser::VOD_TABLE . "$where $limit_str;";
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
        return $this->wrapper->fetch_single_array($query, COLUMN_GROUP_ID);
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

    /**
     * Initialize and parse selected playlist
     *
     * @return bool
     */
    protected function init_vod_m3u_playlist()
    {
        hd_debug_print(null, true);

        $playlist_id = $this->plugin->get_active_playlist_id();
        if (!$this->plugin->is_playlist_entry_exist($playlist_id)) {
            hd_debug_print("Playlist not defined");
            return false;
        }

        $params = $this->plugin->get_playlist_parameters($playlist_id);
        $type = safe_get_value($params, PARAM_TYPE);
        $pl_type = safe_get_value($params, PARAM_PLAYLIST_TYPE);

        if ($type === PARAM_PROVIDER) {
            $provider = $this->plugin->get_active_provider();
            if (is_null($provider)) {
                hd_debug_print("Unknown provider");
                return false;
            }

            if (!$provider->hasApiCommand(API_COMMAND_GET_VOD)) {
                hd_debug_print("Failed to get VOD playlist from provider");
                return false;
            }
        } else if ($pl_type !== CONTROL_PLAYLIST_VOD) {
            hd_debug_print("Playlist is not VOD type");
            return false;
        }

        $base_name = $this->plugin->get_playlist_cache_filepath(false);
        $m3u_file = "$base_name.m3u8";
        $db_file = "$base_name.db";

        try {
            $reload_playlist = $this->plugin->is_playlist_cache_expired(false);
            if ($reload_playlist || $this->vod_m3u_parser->get_filename() !== $m3u_file) {
                $uri = safe_get_value($params, PARAM_URI);
                if ($type === PARAM_PROVIDER) {
                    hd_debug_print("download provider vod");
                    if ($provider->execApiCommandFile(API_COMMAND_GET_VOD, $m3u_file) === false) {
                        $msg = sprintf("%s\nError code: %s\n%s",
                            TR::load('err_load_vod'), Curl_Wrapper::get_error_no(), Curl_Wrapper::get_error_desc());
                        throw new Exception($msg);
                    }
                } else if ($type === PARAM_FILE) {
                    hd_debug_print("m3u copy local file: $uri to $m3u_file");
                    if (empty($uri)) {
                        throw new Exception("Empty playlist path");
                    }

                    $res = copy($uri, $m3u_file);
                    if ($res === false) {
                        $errors = error_get_last();
                        $msg = sprintf("%s\nm3u copy local file: %s to %s\nCopy error: %s\n%s",
                            TR::load('err_load_vod'), $uri, $m3u_file, $errors['type'], $errors['message']);
                        throw new Exception($msg);
                    }
                } else if ($type === PARAM_LINK || $type === PARAM_CONF) {
                    hd_debug_print("m3u download link: $uri");
                    if (empty($uri)) {
                        throw new Exception("Empty playlist url");
                    }
                    $curl_wrapper = $this->plugin->setup_curl();
                    $res = $curl_wrapper->download_file($uri, $m3u_file);
                    if ($res === false) {
                        $msg = sprintf("%s\nError code: %s\n%s",
                            TR::load('err_load_vod'), Curl_Wrapper::get_error_no(), Curl_Wrapper::get_error_desc());
                        throw new Exception($msg);
                    }
                } else {
                    throw new Exception("Unknown playlist type");
                }

                $playlist_file = file_get_contents($m3u_file);
                if (strpos($playlist_file, TAG_EXTM3U) === false && strpos($playlist_file, TAG_EXTINF) === false) {
                    $msg = sprintf("%s\nPlaylist is not a M3U file\n%s", TR::load('err_load_vod'), $playlist_file);
                    throw new Exception($msg);
                }

                $mtime = filemtime($m3u_file);
                hd_debug_print("Stored $m3u_file (timestamp: $mtime)");
                $this->vod_m3u_parser->setVodPlaylist($m3u_file, $db_file);
            }
        } catch (Exception $ex) {
            hd_debug_print("Unable to load VOD playlist");
            Dune_Last_Error::set_last_error(LAST_ERROR_VOD_LIST, $ex->getMessage());
            print_backtrace_exception($ex);
            safe_unlink($m3u_file);
            return false;
        }

        hd_debug_print("Init VOD playlist done!");
        return true;
    }
}
