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
const ACTION_EXTERNAL_PLAYER = 'use_external_player';
const ACTION_INTERNAL_PLAYER = 'use_internal_player';
const ACTION_FOLDER_SELECTED = 'folder_selected';
const ACTION_DO_EDIT_PROVIDER = 'do_edit_provider';
const ACTION_DO_EDIT_PROVIDER_EXT = 'do_edit_provider_ext';
const ACTION_EDIT_PROVIDER_DLG = 'select_provider';
const ACTION_EDIT_PROVIDER_DLG_APPLY = 'select_provider_apply';
const ACTION_EDIT_PROVIDER_EXT_DLG = 'edit_ext_provider';
const ACTION_EDIT_PROVIDER_EXT_DLG_APPLY = 'select_ext_provider_apply';
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
const ACTION_TOGGLE_ICONS_TYPE = 'toggle_icons_type';
const ACTION_RELOAD = 'reload';
const ACTION_RESTORE_GROUPS = 'restore_groups';
const ACTION_RESTORE_CHANNELS = 'restore_chanels';
const ACTION_RESET_DEFAULT = 'reset_default';
const ACTION_SETTINGS = 'settings';
const ACTION_DO_SETTINGS = 'do_edit_settings';
const ACTION_ZOOM_POPUP_MENU = 'zoom_popup_menu';
const ACTION_ZOOM_APPLY = 'zoom_apply';
const ACTION_ZOOM_SELECT = 'zoom_select';
const ACTION_EMPTY = 'empty';
const ACTION_PLUGIN_INFO = 'plugin_info';
const ACTION_DONATE_DLG = 'donate_dlg';
const ACTION_CHANGE_GROUP_ICON = 'change_group_icon';
const ACTION_CHANGE_BACKGROUND = 'change_background';
const ACTION_CHANNEL_INFO = 'channel_info';
const ACTION_CHANGE_EPG_SOURCE = 'change_epg_source';
const ACTION_CHANGE_PICONS_SOURCE = 'change_picons_source';
const ACTION_EPG_CACHE_ENGINE = 'cache_engine';
const ACTION_EPG_SOURCE_SELECTED = 'epg_source_selected';
const ACTION_SHOW_INDEX_PROGRESS = 'show_index_progress';
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
const ACTION_NEED_CONFIGURE = 'configure';
const ACTION_BALANCE = 'balance';
const ACTION_INFO = 'info';
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
const ACTION_EDIT_PL_TYPE_DLG = 'edit_pl_type_dlg';
const ACTION_PL_TYPE_DLG_APPLY = 'pl_type_dlg_apply';
const ACTION_CLEAR_CACHE = 'clear_cache';
const ACTION_INDEX_EPG = 'index_epg';

const CONTROL_ACTION_EDIT = 'action_edit';
const CONTROL_EDIT_NAME = 'set_item_name';
const CONTROL_EDIT_ITEM = 'edit_item';
const CONTROL_URL_PATH = 'url_path';
const CONTROL_LOGIN = 'login';
const CONTROL_PASSWORD = 'password';
const CONTROL_OTT_SUBDOMAIN = 'subdomain';
const CONTROL_OTT_KEY = 'ottkey';
const CONTROL_VPORTAL = 'vportal';
const CONTROL_DEVICE = 'device';
const CONTROL_SERVER = 'server';
const CONTROL_DOMAIN = 'domain';
const CONTROL_QUALITY = 'quality';
const CONTROL_STREAM = 'stream';
const CONTROL_PLAYLIST = 'playlist';
const CONTROL_REPLACE_ICONS = 'replace_icons';
const CONTROL_PLAYLIST_IPTV = 'iptv';
const CONTROL_PLAYLIST_VOD = 'vod';

# Special groups ID
const FAVORITES_GROUP_ID = '##favorites##';
const ALL_CHANNEL_GROUP_ID = '##all_channels##';
const HISTORY_GROUP_ID = '##playback_history_tv_group##';
const CHANGED_CHANNELS_GROUP_ID = '##changed_channels_group##';
const VOD_GROUP_ID = '##mediateka##';
const FAVORITES_MOVIE_GROUP_ID = '##movie_favorites##';
const SEARCH_MOVIES_GROUP_ID = '##search_movie##';
const FILTER_MOVIES_GROUP_ID = '##filter_movie##';
const HISTORY_MOVIES_GROUP_ID = '##playback_history_vod_group##';

# Common parameters
const PLUGIN_PARAMETERS = "parameters";
const PLUGIN_SETTINGS = "settings";
const PLUGIN_ORDERS = "orders";
const PLUGIN_HISTORY = "history";
const PLUGIN_CONFIG_VERSION = 'config_version';
const HISTORY_MOVIES = 'vod_history';
const VOD_SEARCH_LIST = 'vod_search';
const VOD_FILTER_LIST = 'vod_filter_items';

const PARAM_ADULT_PASSWORD = 'adult_password';
const PARAM_SETTINGS_PASSWORD = 'settings_password';
const PARAM_PLAYLIST_STORAGE = 'playlist_storage';
const PARAM_CUR_PLAYLIST_ID = 'cur_playlist_id';
const PARAM_PLAYLIST_ID = 'playlist_id';
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
const PARAM_SHOW_CHANGED_CHANNELS = 'show_changed_channels';
const PARAM_SHOW_VOD_ICON = 'show_vod_icon';
const PARAM_VOD_LAST = 'vod_last';
const PARAM_DISABLED_CHANNELS = 'disabled_channels';
const PARAM_SQUARE_ICONS = 'square_icons';
const PARAM_ICONS_IN_ROW = 'icons_in_row';
const PARAM_PLAYLIST_FOLDER = 'playlist_folder';
const PARAM_HISTORY_PATH = 'history_path';
const PARAM_CHANNELS_LIST_PATH = 'channels_list_path';
const PARAM_CHANNELS_LIST_NAME = 'channels_list_name';
const PARAM_CHANNELS_LIST = 'channels_list';
const PARAM_CHANNELS_SOURCE = 'channels_source';
const PARAM_CHANNELS_URL = 'channels_url';
const PARAM_CHANNELS_DIRECT_URL = 'channels_direct_url';
const PARAM_EPG_CACHE_ENGINE = 'epg_cache_engine';
const PARAM_EPG_JSON_PRESET = 'epg_json_preset';
const PARAM_EPG_CACHE_TYPE = 'epg_cache_type';
const PARAM_EPG_CACHE_TTL = 'epg_cache_ttl';
const PARAM_EPG_SHIFT = 'epg_shift';
const PARAM_EPG_FONT_SIZE = 'epg_font_size';
const PARAM_EPG_SOURCE = 'epg_source';
const PARAM_CHANNEL_POSITION = 'channel_position';
const PARAM_INTERNAL_EPG_IDX = 'epg_idx';
const PARAM_CACHE_PATH = 'xmltv_cache_path';
const PARAM_EXT_XMLTV_SOURCES = 'xmltv_sources';
const PARAM_CUR_XMLTV_SOURCES = 'cur_xmltv_sources';
const PARAM_DUNE_PARAMS = 'dune_params';
const PARAM_DUNE_FORCE_TS = 'dune_force_ts';
const PARAM_EXT_VLC_OPTS = 'ext_vlc_opts';
const PARAM_EXT_HTTP = 'ext_http';
const PARAM_CHANNELS_ZOOM = 'channels_zoom';
const PARAM_CHANNEL_PLAYER = 'channel_player';
const PARAM_KNOWN_CHANNELS = 'known_channels';
const PARAM_USER_CATCHUP = 'user_catchup';
const PARAM_USE_PICONS = 'use_picons';
const PARAM_ID_MAPPER = 'id_mapper';
const PARAM_TV_HISTORY_ITEMS = '_tv_history_items';
const PARAM_USER_AGENT = 'user_agent';
const PARAM_BUFFERING_TIME = 'buffering_time';
const PARAM_ARCHIVE_DELAY_TIME = 'archive_delay_time';
const PARAM_GROUPS_ICONS = 'groups_icons';
const PARAM_PLUGIN_BACKGROUND = 'plugin_background';
const PARAM_ENABLE_DEBUG = 'enable_debug';
const PARAM_PER_CHANNELS_ZOOM = 'per_channels_zoom';
const PARAM_FAKE_EPG = 'fake_epg';
const PARAM_STREAM_FORMAT = 'stream_format';
const PARAM_CUSTOM_DELETE_STRING = 'custom_delete_string';
const PARAM_CUSTOM_DELETE_REGEX = 'custom_delete_regex';
const PARAM_PROVIDER = 'provider';
const PARAM_LINK = 'link';
const PARAM_FILE = 'file';
const PARAM_URI = 'uri';
const PARAM_PL_TYPE = 'playlist_type';
const PARAM_VOD_DEFAULT_QUALITY = 'quality';
const PARAM_FORCE_HTTP = 'force_http';
const PARAM_REPLACE_ICON = 'replace_playlist_icon';

const LIST_IDX = 'list_idx';
const IS_LIST_SELECTED = 'is_list_selected';
const PLAYLIST_PICONS = 'playlist_picons';
const XMLTV_PICONS = 'xmltv_picons';
const COMBINED_PICONS = 'combined_picons';

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
const MACRO_PLAYLIST = '{PLAYLIST}';
const MACRO_EPG_DOMAIN = '{EPG_DOMAIN}';
const MACRO_CUSTOM_PLAYLIST = '{CUSTOM_PLAYLIST}';
const MACRO_VPORTAL = '{VPORTAL}';
const MACRO_SCHEME = '{SCHEME}';
const MACRO_DOMAIN = '{DOMAIN}';
const MACRO_EXPIRE_DATA = '{EXPIRE_DATA}';
const MACRO_ID = '{ID}';

const MACRO_TIMESTAMP = '{TIMESTAMP}';
const MACRO_YEAR = '{YEAR}';
const MACRO_MONTH = '{MONTH}';
const MACRO_DAY = '{DAY}';
const MACRO_EPG_ID = '{EPG_ID}';

// provider type access
const PROVIDER_TYPE_PIN = 'pin';
const PROVIDER_TYPE_LOGIN = 'login';
const PROVIDER_EXT_PARAMS = 'ext_params';

const EPG_SOURCES_SEPARATOR_TAG = 'special_source_separator_tag';
const ENGINE_JSON = 'json';
const ENGINE_XMLTV = 'xmltv';
const XMLTV_CACHE_AUTO = 'auto';
const XMLTV_CACHE_MANUAL = 'manual';
const EPG_CACHE_SUBDIR = 'epg_cache';
const HISTORY_SUBDIR = 'history';
const EPG_FAKE_EPG = 2;
const EPG_JSON_PRESETS = 'epg_presets';
const EPG_JSON_SOURCE = 'json_source';
const EPG_JSON_PARSER = 'parser';
const EPG_JSON_PRESET_NAME = 'name';
const EPG_JSON_PRESET_ALIAS = 'alias';
const EPG_JSON_AUTH = 'json_auth';
const CUSTOM_PLAYLIST_ID = 'custom';

# HTTP params
const USER_AGENT = 'User-Agent';
const REFERER = 'Referer';

# Media types patterns
const AUDIO_PATTERN = 'mp3|ac3|wma|ogg|ogm|m4a|aif|iff|mid|mpa|ra|wav|flac|ape|vorbis|aac|a52';
const VIDEO_PATTERN = 'avi|mp4|mpg|mpeg|divx|m4v|3gp|asf|wmv|mkv|mov|ogv|vob|flv|ts|3g2|swf|ps|qt|m2ts';
const IMAGE_PREVIEW_PATTERN = 'png|jpg|jpeg|bmp|gif|aai';
const IMAGE_PATTERN = '|psd|pspimage|thm|tif|yuf|svg|ico|djpg|dbmp|dpng';
const PLAYLIST_PATTERN = 'm3u|m3u8';
const EPG_PATTERN = 'xml|xmltv|gz';
const HTTP_PATTERN = '/^(https?):\/\/([^\?]+)\??/';
const TS_REPL_PATTERN = '/^(https?:\/\/)(.+)$/';
const PROVIDER_PATTERN = '/^([^@]+)@(.+)$/';
const VPORTAL_PATTERN = '/^portal::\[key:([^]]+)](.+)$/';

# Mounted storages path
const DUNE_MOUNTED_STORAGES_PATH = '/tmp/mnt/storage';
const DUNE_APK_STORAGE_PATH = '/sdcard/DuneHD/Dune_backup';

if (!defined('JSON_UNESCAPED_SLASHES'))
    define("JSON_UNESCAPED_SLASHES", 64);
if (!defined('JSON_PRETTY_PRINT'))
    define('JSON_PRETTY_PRINT', 128);
if (!defined('JSON_UNESCAPED_UNICODE'))
    define('JSON_UNESCAPED_UNICODE', 256);

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
const CONFIG_PLAYLISTS = 'playlists';
const CONFIG_VOD_PARSER = 'vod_parser';
const CONFIG_URL_SUBST = 'url_subst';
const CONFIG_ICON_REPLACE = 'replace_icon_patterns';

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
