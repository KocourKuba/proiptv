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

# Common actions
const ACTION_ADD_FAV = 'add_favorite';
const ACTION_ADD_TO_LIST = 'add_to_list';
const ACTION_FOLDER_SELECTED = 'folder_selected';
const ACTION_DO_EDIT_PROVIDER = 'do_edit_provider';
const ACTION_DO_SETUP_PROVIDER = 'do_edit_provider_ext';
const ACTION_EDIT_PROVIDER_DLG = 'select_provider';
const ACTION_EDIT_PROVIDER_DLG_APPLY = 'select_provider_apply';
const ACTION_SETUP_PROVIDER = 'edit_setup_provider';
const ACTION_EDIT_CHANNEL_DLG = 'edit_channel';
const ACTION_EDIT_CHANNEL_APPLY = 'apply_edit_channel';
const ACTION_EDIT_CATEGORY_SCREEN = 'edit_category_screen';
const ACTION_DO_EDIT_CATEGORY_SCREEN = 'do_edit_category_screen';
const ACTION_FILE_SELECTED = 'file_selected';
const ACTION_ITEM_TOGGLE_MOVE = 'item_toggle_move';
const ACTION_ITEM_ADD = 'item_add';
const ACTION_ITEM_DELETE = 'item_delete';
const ACTION_ITEM_DELETE_CHANNELS = 'item_delete_channels';
const ACTION_ITEM_DELETE_BY_STRING = 'item_delete_custom';
const ACTION_ITEM_DOWN = 'item_down';
const ACTION_ITEM_UP = 'item_up';
const ACTION_ITEM_TOP = 'item_top';
const ACTION_ITEM_BOTTOM = 'item_bottom';
const ACTION_ITEM_REMOVE = 'item_remove';
const ACTION_ITEMS_CLEAR = 'items_clear';
const ACTION_ITEMS_EDIT = 'items_edit';
const ACTION_ITEMS_SORT = 'items_sort';
const ACTION_RESET_ITEMS_SORT = 'reset_items_sort';
const ACTION_SORT_POPUP = 'sort_popup';
const ACTION_OPEN_FOLDER = 'open_folder';
const ACTION_PLAY_FOLDER = 'play_folder';
const ACTION_PLAY_ITEM = 'play_item';
const ACTION_REFRESH_SCREEN = 'refresh_screen';
const ACTION_RELOAD = 'reload';
const ACTION_FORCE_OPEN = 'force_open';
const ACTION_RESET_DEFAULT = 'reset_default';
const ACTION_SETTINGS = 'settings';
const ACTION_DO_SETTINGS = 'do_edit_settings';
const ACTION_EDIT_PLAYLIST_SETTINGS = 'edit_playlist_settings';
const ACTION_DO_EDIT_PLAYLIST_SETTINGS = 'do_edit_playlist_settings';
const ACTION_EDIT_NEWUI_SETTINGS = 'edit_newui_settings';
const ACTION_DO_EDIT_NEWUI_SETTINGS = 'do_edit_newui_settings';
const ACTION_CONFIRM_EXIT_DLG_APPLY = 'apply_dlg';
const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';
const ACTION_SETUP_SCREEN = 'setup_screen';
const ACTION_DO_EDIT_XMLTV_SETTINGS = 'do_edit_xmltv_settings';
const ACTION_EMPTY = 'empty';
const ACTION_INVALIDATE = 'invalidate';
const ACTION_SHORTCUT = 'shortcut';
const ACTION_PLUGIN_INFO = 'plugin_info';
const ACTION_DONATE_DLG = 'donate_dlg';
const ACTION_CHANGE_GROUP_ICON = 'change_group_icon';
const ACTION_CHANGE_BACKGROUND = 'change_background';
const ACTION_CHANGE_EPG_SOURCE = 'change_epg_source';
const ACTION_EPG_CACHE_ENGINE = 'cache_engine';
const ACTION_EPG_SOURCE_SELECTED = 'epg_source_selected';
const ACTION_FILTER = 'action_filter';
const ACTION_CREATE_FILTER = 'create_filter';
const ACTION_RUN_FILTER = 'run_filter';
const ACTION_CREATE_SEARCH = 'create_search';
const ACTION_SEARCH = 'action_search';
const ACTION_NEW_SEARCH = 'new_search';
const ACTION_RUN_SEARCH = 'run_search';
const ACTION_WATCHED = 'watched';
const ACTION_QUALITY = 'quality';
const ACTION_AUDIO = 'audio';
const ACTION_SORT_TYPE = 'sort_type';
const ACTION_RESET_TYPE = 'reset_type';
const ACTION_SORT_CHANNELS = 'channels';
const ACTION_SORT_GROUPS = 'groups';
const ACTION_SORT_ALL = 'all';
const ACTION_INFO_DLG = 'info_dlg';
const ACTION_ADD_MONEY_DLG = 'add_money_dlg';
const ACTION_PASSWORD_APPLY = 'password_apply';
const ACTION_JUMP_TO_CHANNEL = 'jump_to_channel';
const ACTION_JUMP_TO_CHANNEL_IN_GROUP = 'jump_to_channel_in_group';
const ACTION_ADD_URL_DLG = 'add_url_dialog';
const ACTION_URL_DLG_APPLY = 'url_dlg_apply';
const ACTION_PL_TYPE_DLG_APPLY = 'pl_type_dlg_apply';
const ACTION_CLEAR_CACHE = 'clear_cache';
const ACTION_INDEX_EPG = 'index_epg';
const ACTION_CHOOSE_FILE = 'choose_file';
const ACTION_ADD_TO_EXTERNAL_SOURCE = 'add_external_source';
const ACTION_DISABLED = 'disabled';
const ACTION_ORDER_SUPPORT = 'order_support';
const ACTION_EXPORT = 'export';
const ACTION_EXPORT_APPLY_DLG = 'apply_export';

const CONTROL_ACTION_EDIT = 'action_edit';
const CONTROL_EDIT_NAME = 'set_item_name';
const CONTROL_EDIT_ITEM = 'edit_item';
const CONTROL_URL_PATH = 'url_path';
const CONTROL_SELECTED_PLAYLIST = 'selected_playlist';
const CONTROL_PLAYLIST_IPTV = 'iptv';
const CONTROL_PLAYLIST_VOD = 'vod';
const CONTROL_DETECT_ID = 'detect_id';
const CONTROL_EDIT_TYPE = 'playlist_type';
const CONTROL_EXT_PARAMS = 'ext_params';
const CONTROL_BACKUP = 'backup';
const CONTROL_RESTORE = 'restore';

# Special groups ID
const TV_ALL_CHANNELS_GROUP_ID = '##all_channels##';
const TV_ALL_CHANNELS_GROUP_CAPTION = 'plugin_all_channels';
const TV_ALL_CHANNELS_GROUP_ICON = 'plugin_file://icons/all_folder.png';

const TV_FAV_GROUP_ID = '##favorites##';
const TV_FAV_GROUP_CAPTION = 'plugin_favorites';
const TV_FAV_GROUP_ICON = 'plugin_file://icons/favorite_folder.png';

const TV_HISTORY_GROUP_ID = '##playback_history_tv_group##';
const TV_HISTORY_GROUP_CAPTION = 'plugin_history';
const TV_HISTORY_GROUP_ICON = 'plugin_file://icons/history_folder.png';

const TV_CHANGED_CHANNELS_GROUP_ID = '##changed_channels_group##';
const TV_CHANGED_CHANNELS_GROUP_CAPTION = 'plugin_changed';
const TV_CHANGED_CHANNELS_GROUP_ICON = 'plugin_file://icons/changed_channels.png';

const VOD_GROUP_ID = '##mediateka##';
const VOD_GROUP_CAPTION = 'plugin_vod';
const VOD_GROUP_ICON = 'plugin_file://icons/vod_folder.png';

const VOD_FAV_GROUP_ID = '##movie_favorites##';
const VOD_FAV_GROUP_CAPTION = 'plugin_favorites';
const VOD_FAV_GROUP_ICON = 'plugin_file://icons/favorite_vod_folder.png';

const VOD_LIST_GROUP_ID = '##movie_list##';
const VOD_LIST_GROUP_CAPTION = 'movie_list';
const VOD_LIST_GROUP_ICON = 'plugin_file://icons/vod_list_folder.png';

const VOD_HISTORY_GROUP_ID = '##playback_history_vod_group##';
const VOD_HISTORY_GROUP_CAPTION = 'plugin_history';
const VOD_HISTORY_GROUP_ICON = 'plugin_file://icons/history_vod_folder.png';

const VOD_SEARCH_GROUP_ID = '##search_movie##';
const VOD_SEARCH_GROUP_CAPTION = 'search';
const VOD_SEARCH_GROUP_ICON = 'plugin_file://icons/search_movie_folder.png';

const VOD_FILTER_GROUP_ID = '##filter_movie##';
const VOD_FILTER_GROUP_CAPTION = 'filters';
const VOD_FILTER_GROUP_ICON = 'plugin_file://icons/filter_movie_folder.png';

const DEFAULT_GROUP_ICON = 'plugin_file://icons/default_group.png';
const DEFAULT_CHANNEL_ICON_PATH = 'plugin_file://%proiptv%/icons/default_channel.png';
const DEFAULT_CHANNEL_ICON_PATH_SQ = 'plugin_file://%proiptv%/icons/default_channel_sq.png';

# Common parameters
const PLUGIN_ORDERS = 'orders';
const VOD_HISTORY = 'vod_history';
const VOD_SEARCH_LIST = 'vod_search';
const VOD_FILTER_LIST = 'vod_filter_items';
const TV_HISTORY = 'tv_history';
const IPTV_PLAYLIST = 'iptv_playlist';
const VOD_PLAYLIST = 'vod_playlist';

const COLUMN_PLAYLIST_ID = 'playlist_id';
const COLUMN_HASH = 'hash';
const COLUMN_CHANNEL_ID = 'channel_id';
const COLUMN_GROUP_ID = 'group_id';
const COLUMN_GROUP_ORDER = 'group_order';
const COLUMN_ICON = 'icon';
const COLUMN_TITLE = 'title';
const COLUMN_NAME = 'name';
const COLUMN_ADULT = 'adult';
const COLUMN_TYPE = 'type';
const COLUMN_URI = 'uri';
const COLUMN_URL = 'url';
const COLUMN_CH_NUMBER = 'ch_number';
const COLUMN_PARSED_ID = 'parsed_id';
const COLUMN_CUID = 'cuid';
const COLUMN_PARENT_CODE = 'parent_code';
const COLUMN_EPG_ID = 'epg_id';
const COLUMN_TVG_NAME = 'tvg_name';
const COLUMN_ARCHIVE = 'archive';
const COLUMN_TIMESHIFT = 'timeshift';
const COLUMN_CATCHUP = 'catchup';
const COLUMN_CATCHUP_SOURCE = 'catchup_source';
const COLUMN_PATH = 'path';
const COLUMN_EXT_PARAMS = 'ext_params';
const COLUMN_CACHE = 'cache';
const COLUMN_WATCHED = 'watched';
const COLUMN_POSITION = 'position';
const COLUMN_DURATION = 'duration';
const COLUMN_TIMESTAMP = 'time_stamp';
const COLUMN_ZOOM = 'zoom';
const COLUMN_EPG_SHIFT = 'epg_shift';

const PARAM_SCREEN_ID = 'screen_id';
const PARAM_SOURCE_WINDOW_ID = 'source_window_id';
const PARAM_END_ACTION = 'end_action';
const PARAM_CANCEL_ACTION = 'cancel_action';
const PARAM_SELECTED_ACTION = 'selected_action';
const PARAM_ACTION_ID = 'action_id';
const PARAM_WINDOW_COUNTER = 'window_counter';
const PARAM_EXTENSION = 'extension';
const PARAM_FILEPATH = 'filepath';

const PARAM_ADULT_PASSWORD = 'adult_password';
const PARAM_SETTINGS_PASSWORD = 'settings_password';
const PARAM_PLAYLIST_STORAGE = 'playlist_storage';
const PARAM_CUR_PLAYLIST_ID = 'cur_playlist_id';
const PARAM_VOD_IDX = 'vod_idx';
const PARAM_FAVORITES = 'favorites';
const PARAM_GROUPS_ORDER = 'groups_order';
const PARAM_DISABLED_GROUPS = 'disabled_groups';
const PARAM_CHANNELS_ORDER = 'channels_order';
const PARAM_ASK_EXIT = 'ask_exit';
const PARAM_SHOW_ALL = 'show_all';
const PARAM_SHOW_FAVORITES = 'show_favorites';
const PARAM_SHOW_HISTORY = 'show_history';
const PARAM_SHOW_VOD = 'show_vod';
const PARAM_SHOW_ADULT = 'show_adult';
const PARAM_SHOW_CHANGED_CHANNELS = 'show_changed_channels';
const PARAM_SHOW_VOD_ICON = 'show_vod_icon';
const PARAM_VOD_LAST = 'vod_last';
const PARAM_DISABLED_CHANNELS = 'disabled_channels';
const PARAM_NEWUI_SQUARE_ICONS = 'square_icons';
const PARAM_NEWUI_ICONS_IN_ROW = 'icons_in_row';
const PARAM_NEWUI_SHOW_CHANNEL_CAPTION = 'show_channel_caption';
const PARAM_NEWUI_SHOW_CHANNEL_COUNT = 'show_channel_count';
const PARAM_NEWUI_SHOW_CONTINUES = 'show_continues';
const PARAM_PLAYLIST_FOLDER = 'playlist_folder';
const PARAM_PLAYLIST_CACHE_TIME = 'playlist_cache_time';
const PARAM_PLAYLIST_ID = 'playlist_id';
const PARAM_DEFAULT_CONFIG_PLAYLIST_ID = 'default';
const PARAM_HISTORY_PATH = 'history_path';
const PARAM_RETURN_INDEX = 'return_index';
const PARAM_FULL_SIZE_REMOTE = 'use_full_size_remote';
const PARAM_CHANNELS_LIST_PATH = 'channels_list_path';
const PARAM_CHANNELS_LIST_NAME = 'channels_list_name';
const PARAM_CHANNELS_LIST = 'channels_list';
const PARAM_CHANNELS_SOURCE = 'channels_source';
const PARAM_CHANNELS_URL = 'channels_url';
const PARAM_CHANNELS_DIRECT_URL = 'channels_direct_url';
const PARAM_EPG_CACHE_ENGINE = 'epg_cache_engine';
const PARAM_EPG_CACHE_TIME = 'epg_cache_time';
const PARAM_EPG_JSON_PRESET = 'epg_json_preset';
const PARAM_EPG_PLAYLIST = 'epg_playlist';
const PARAM_EPG_SHIFT_HOURS = 'epg_shift_hours';
const PARAM_EPG_SHIFT_MINS = 'epg_shift_mins';
const PARAM_EPG_FONT_SIZE = 'epg_font_size';
const PARAM_EPG_SOURCE = 'epg_source';
const PARAM_PICONS_DELAY_LOAD = 'epg_delayed_index';
const PARAM_NEWUI_CHANNEL_POSITION = 'channel_position';
const PARAM_INTERNAL_EPG_IDX = 'epg_idx';
const PARAM_CACHE_PATH = 'xmltv_cache_path';
const PARAM_EXT_XMLTV_SOURCES = 'xmltv_sources';
const PARAM_SELECTED_XMLTV_SOURCES = 'selected_xmltv_sources';
const PARAM_USE_DUNE_PARAMS = 'use_dune_params';
const PARAM_DUNE_PARAMS = 'dune_params';
const PARAM_DUNE_FORCE_TS = 'dune_force_ts';
const PARAM_EXT_VLC_OPTS = 'ext_vlc_opts';
const PARAM_EXT_HTTP = 'ext_http';
const PARAM_KNOWN_CHANNELS = 'known_channels';
const PARAM_USER_CATCHUP = 'user_catchup';
const PARAM_USE_PICONS = 'use_picons';
const PARAM_ID_MAPPER = 'id_mapper';
const PARAM_TV_HISTORY_ITEMS = 'tv_history_items';
const PARAM_USER_AGENT = 'user_agent';
const PARAM_BUFFERING_TIME = 'buffering_time';
const PARAM_ARCHIVE_DELAY_TIME = 'archive_delay_time';
const PARAM_GROUPS_ICONS = 'groups_icons';
const PARAM_PLUGIN_BACKGROUND = 'plugin_background';
const PARAM_ENABLE_DEBUG = 'enable_debug';
const PARAM_PER_CHANNELS_ZOOM = 'per_channels_zoom';
const PARAM_SHOW_EXT_EPG = 'show_ext_epg';
const PARAM_FAKE_EPG = 'fake_epg';
const PARAM_STREAM_FORMAT = 'stream_format';
const PARAM_CUSTOM_DELETE_STRING = 'custom_delete_string';
const PARAM_CUSTOM_DELETE_REGEX = 'custom_delete_regex';
const PARAM_PROVIDER = 'provider';
const PARAM_NAME = 'name';
const PARAM_VALUE = 'value';
const PARAM_TYPE = 'type';
const PARAM_LINK = 'link';
const PARAM_CONF = 'config';
const PARAM_FILE = 'file';
const PARAM_URI = 'uri';
const PARAM_HASH = 'hash';
const PARAM_CACHE = 'cache';
const PARAM_PARAMS = 'params';
const PARAM_SHORTCUT = 'shortcut';
const PARAM_CACHE_DIR = 'cache_dir';
const PARAM_PL_TYPE = 'playlist_type';
const PARAM_VOD_DEFAULT_QUALITY = 'quality';
const PARAM_REPLACE_ICON = 'replace_playlist_icon';
const PARAMS_XMLTV = 'xmltv_params';
const PARAM_TOKEN = 'token';
const PARAM_REFRESH_TOKEN = 'refresh_token';
const PARAM_SESSION_ID = 'session_id';
const PARAM_INDEXING_FLAG = 'index_all';
const PARAM_FIX_PALETTE = 'fix_palette';
const PARAM_CURL_CONNECT_TIMEOUT = 'curl_connect_timeout';
const PARAM_CURL_DOWNLOAD_TIMEOUT = 'curl_download_timeout';
const PARAM_SELECTED_MIRROR = 'selected_mirror';

const PARAM_GROUP_ORDINARY = 0;
const PARAM_GROUP_SPECIAL = 1;

const PARAM_DISABLED = 1;
const PARAM_ENABLED = 0;
const PARAM_ALL = -1;

const PARAM_NEW = 1;
const PARAM_REMOVED = -1;
const PARAM_CHANGED = 0;

const LIST_IDX = 'list_idx';
const IS_LIST_SELECTED = 'is_list_selected';
const PLAYLIST_PICONS = 'playlist_picons';
const XMLTV_PICONS = 'xmltv_picons';
const COMBINED_PICONS = 'combined_picons';

const XMLTV_SOURCE_PLAYLIST = 1;
const XMLTV_SOURCE_EXTERNAL = 2;
const XMLTV_SOURCE_ALL = 3;

const LAST_ERROR_PLAYLIST = 0;
const LAST_ERROR_VOD_LIST = 1;
const LAST_ERROR_REQUEST = 2;
const LAST_ERROR_XMLTV = 3;

const GROUPS_INFO = 'groups_info';
const GROUPS_ORDER = 'groups_order';
const CHANNELS_INFO = 'channels_info';

const INDEXING_DOWNLOAD = 1;
const INDEXING_CHANNELS = 2;
const INDEXING_ENTRIES = 4;
const INDEXING_ALL = 7; // INDEXING_DOWNLOAD | INDEXING_CHANNELS | INDEXING_ENTRIES

// macroses used to replace template in providers playlists
const MACRO_API = '{API}';
const MACRO_PROVIDER = '{PROVIDER}';
const MACRO_LOGIN = '{LOGIN}';
const MACRO_PASSWORD = '{PASSWORD}';
const MACRO_SUBDOMAIN = '{SUBDOMAIN}';
const MACRO_OTTKEY = '{OTTKEY}';
const MACRO_TOKEN = '{TOKEN}';
const MACRO_SESSION_ID = '{SESSION_ID}';
const MACRO_HASH_PASSWORD = '{HASH_PASSWORD}';
const MACRO_REFRESH_TOKEN = '{REFRESH_TOKEN}';
const MACRO_DOMAIN_ID = '{DOMAIN_ID}';
const MACRO_DEVICE_ID = '{DEVICE_ID}';
const MACRO_SERVER_ID = '{SERVER_ID}';
const MACRO_QUALITY_ID = '{QUALITY_ID}';
const MACRO_STREAM_ID = '{STREAM_ID}';
const MACRO_PLAYLIST_ID = '{PLAYLIST_ID}';
const MACRO_PLAYLIST_VOD_ID = '{PLAYLIST_VOD_ID}';
const MACRO_PLAYLIST_IPTV = '{PLAYLIST}';
const MACRO_PLAYLIST_VOD = '{PLAYLIST_VOD}';
const MACRO_EPG_DOMAIN = '{EPG_DOMAIN}';
const MACRO_CUSTOM_PLAYLIST = '{CUSTOM_PLAYLIST}';
const MACRO_VPORTAL = '{VPORTAL}';
const MACRO_EXPIRE_DATA = '{EXPIRE_DATA}';
const MACRO_ID = '{ID}';
const MACRO_MIRROR = '{MIRROR}';

const MACRO_TIMESTAMP = '{TIMESTAMP}';
const MACRO_YEAR = '{YEAR}';
const MACRO_MONTH = '{MONTH}';
const MACRO_DAY = '{DAY}';
const MACRO_EPG_ID = '{EPG_ID}';

// provider type access
const PROVIDER_TYPE_PIN = 'pin';
const PROVIDER_TYPE_LOGIN = 'login';

const ENGINE_JSON = 'json';
const ENGINE_XMLTV = 'xmltv';
const XMLTV_CACHE_AUTO = 'auto';
const XMLTV_CACHE_MANUAL = 'manual';
const EPG_CACHE_SUBDIR = 'epg_cache';
const HISTORY_SUBDIR = 'history';
const EPG_FAKE_EPG = 1;
const EPG_JSON_PRESETS = 'epg_presets';
const EPG_JSON_SOURCE = 'json_source';
const EPG_JSON_PARSER = 'parser';
const EPG_JSON_PRESET_NAME = 'name';
const EPG_JSON_PRESET_ALIAS = 'alias';
const EPG_JSON_AUTH = 'json_auth';
const EPG_JSON_EPG_MAP = 'epg_map';
const DIRECT_PLAYLIST_ID = 'custom';

# Media types patterns
const AUDIO_PATTERN = 'mp3|ac3|wma|ogg|ogm|m4a|aif|iff|mid|mpa|ra|wav|flac|ape|vorbis|aac|a52';
const VIDEO_PATTERN = 'avi|mp4|mpg|mpeg|divx|m4v|3gp|asf|wmv|mkv|mov|ogv|vob|flv|ts|3g2|swf|ps|qt|m2ts';
const IMAGE_PREVIEW_PATTERN = 'png|jpg|jpeg|bmp|gif|aai';
const IMAGE_PATTERN = '|psd|pspimage|thm|tif|yuf|svg|ico|djpg|dbmp|dpng';
const PLAYLIST_PATTERN = 'm3u|m3u8';
const TEXT_FILE_PATTERN = 'txt|lst';
const EPG_PATTERN = 'xml|xmltv|gz';
const HTTP_PATTERN = '/^(https?):\/\/([^\?]+)\??/';
const TS_REPL_PATTERN = '/^(https?:\/\/)(.+)$/';
const PROVIDER_PATTERN = '/^([^@]+)@(.+)$/';
const VPORTAL_PATTERN = '/^portal::\[key:([^]]+)](.+)$/';

# Configuration parameters
const CONFIG_PLAYLIST_CATCHUP = 'playlist_catchup';
const CONFIG_ID_PARSER = 'id_parser';
const CONFIG_ID_MAP = 'id_map';
const CONFIG_IGNORE_GROUPS = 'ignore_groups';
const CONFIG_HEADERS = 'headers';
const CONFIG_XMLTV_SOURCES = 'xmltv_sources';
const CONFIG_SUBDOMAIN = 'domain';
const CONFIG_STREAMS = 'streams';
const CONFIG_SERVERS = 'servers';
const CONFIG_DOMAINS = 'domains';
const CONFIG_DEVICES = 'devices';
const CONFIG_QUALITIES = 'qualities';
const CONFIG_PLAYLISTS_IPTV = 'playlists';
const CONFIG_PLAYLISTS_VOD = 'playlists_vod';
const CONFIG_VOD_PARSER = 'vod_parser';
const CONFIG_URL_SUBST = 'url_subst';
const CONFIG_ICON_REPLACE = 'replace_icon_patterns';
const CONFIG_PLAYLIST_MIRRORS = 'playlist_mirrors';

const API_COMMAND_GET_PLAYLIST = 'get_playlist';
const API_COMMAND_GET_VOD = 'get_vod';
const API_COMMAND_ACCOUNT_INFO = 'account_info';
const API_COMMAND_PAY = 'pay';
const API_COMMAND_GET_SERVERS = 'get_servers';
const API_COMMAND_SET_SERVER = 'set_server';
const API_COMMAND_GET_DEVICE = 'get_device';
const API_COMMAND_SET_DEVICE = 'set_device';
const API_COMMAND_GET_PACKAGES = 'get_packages';
const API_COMMAND_REQUEST_TOKEN = 'request_token';
const API_COMMAND_REFRESH_TOKEN = 'refresh_token';

const API_ACTION_MOVIE = 'movie';
const API_ACTION_SERIAL = 'serial';
const API_ACTION_FILTERS = 'filters';
const API_ACTION_SEARCH = 'search';
const API_ACTION_FILTER = 'filter';
