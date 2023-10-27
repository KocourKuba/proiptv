<?php

# Common actions
const ACTION_ADD_FAV = 'add_favorite';
const ACTION_CHANGE_PLAYLIST = 'change_playlist';
const ACTION_EXTERNAL_PLAYER = 'use_external_player';
const ACTION_INTERNAL_PLAYER = 'use_internal_player';
const ACTION_FOLDER_SELECTED = 'folder_selected';
const ACTION_PLAYLIST_SELECTED = 'playlist_selected';
const ACTION_EDIT_PROVIDER_DLG = 'select_provider';
const ACTION_FILE_SELECTED = 'file_selected';
const ACTION_ITEM_ADD = 'item_add';
const ACTION_ITEM_DELETE = 'item_delete';
const ACTION_ITEM_DELETE_CHANNELS = 'item_delete_channels';
const ACTION_ITEM_DELETE_BY_STRING = 'item_delete_custom';
const ACTION_ITEM_DOWN = 'item_down';
const ACTION_ITEM_REMOVE = 'item_remove';
const ACTION_ITEM_UP = 'item_up';
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
const ACTION_ZOOM_POPUP_MENU = 'zoom_popup_menu';
const ACTION_ZOOM_APPLY = 'zoom_apply';
const ACTION_ZOOM_SELECT = 'zoom_select';
const ACTION_EMPTY = 'empty';
const ACTION_PLUGIN_INFO = 'plugin_info';
const ACTION_CHANGE_GROUP_ICON = 'change_group_icon';
const ACTION_CHANGE_BACKGROUND = 'change_background';
const ACTION_CHANNEL_INFO = 'channel_info';
const ACTION_CHANGE_EPG_SOURCE = 'change_epg_source';
const ACTION_EPG_SOURCE_SELECTED = 'epg_source_selected';
const ACTION_SHOW_INDEX_PROGRESS = 'show_index_progress';
const ACTION_CREATE_FILTER = 'create_filter';
const ACTION_RUN_FILTER = 'run_filter';
const ACTION_CREATE_SEARCH = 'create_search';
const ACTION_NEW_SEARCH = 'new_search';
const ACTION_RUN_SEARCH = 'run_search';
const ACTION_WATCHED = 'watched';
const ACTION_QUALITY = 'quality';
const ACTION_CHANNELS_SETTINGS = 'channels_settings';
const ACTION_NEED_CONFIGURE = 'configure';
const ACTION_BALANCE = 'balance';
const ACTION_INFO = 'info';
const ACTION_SORT_TYPE = 'sort_type';
const ACTION_RESET_TYPE = 'reset_type';
const ACTION_SORT_CHANNELS = 'channels';
const ACTION_SORT_GROUPS = 'groups';
const ACTION_SORT_ALL = 'all';

# Special groups ID
const FAVORITES_GROUP_ID = '##favorites##';
const ALL_CHANNEL_GROUP_ID = '##all_channels##';
const HISTORY_GROUP_ID = '##playback_history_tv_group##';
const CHANGED_CHANNELS_GROUP_ID = '##changed_channels_group##';
const VOD_GROUP_ID = '##mediateka##';

# Common parameters
const PLUGIN_PARAMETERS = "parameters";
const PLUGIN_SETTINGS = "settings";
const PLUGIN_ORDERS = "orders";
const PLUGIN_CONFIG_VERSION = 'config_version';

const PARAM_ADULT_PASSWORD = 'adult_password';
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
const PARAM_SHOW_CHANGED_CHANNELS = 'show_changed_channels';
const PARAM_VOD_LAST = 'vod_last';
const PARAM_DISABLED_CHANNELS = 'disabled_channels';
const PARAM_SQUARE_ICONS = 'square_icons';
const PARAM_PLAYLIST_FOLDER = 'playlist_folder';
const PARAM_HISTORY_PATH = 'history_path';
const PARAM_CHANNELS_LIST_PATH = 'channels_list_path';
const PARAM_CHANNELS_LIST_NAME = 'channels_list_name';
const PARAM_CHANNELS_LIST = 'channels_list';
const PARAM_CHANNELS_SOURCE = 'channels_source';
const PARAM_CHANNELS_URL = 'channels_url';
const PARAM_CHANNELS_DIRECT_URL = 'channels_direct_url';
const PARAM_EPG_CACHE_ENGINE = 'epg_cache_engine';
const PARAM_EPG_CACHE_TTL = 'epg_cache_ttl';
const PARAM_EPG_SHIFT = 'epg_shift';
const PARAM_EPG_FONT_SIZE = 'epg_font_size';
const PARAM_EPG_SOURCE = 'epg_source';
const PARAM_INTERNAL_EPG_IDX = 'epg_idx';
const PARAM_CACHE_PATH = 'xmltv_cache_path';
const PARAM_XMLTV_SOURCES = 'xmltv_sources';
const PARAM_CUR_XMLTV_SOURCE_KEY = 'cur_xmltv_source_key';
const PARAM_CUR_XMLTV_SOURCE = 'cur_xmltv_source';
const PARAM_DUNE_PARAMS = 'dune_params';
const PARAM_CHANNELS_ZOOM = 'channels_zoom';
const PARAM_CHANNEL_PLAYER = 'channel_player';
const PARAM_KNOWN_CHANNELS = 'known_channels';
const PARAM_USER_CATCHUP = 'user_catchup';
const PARAM_TV_HISTORY_ITEMS = '_tv_history_items';
const PARAM_USER_AGENT = 'user_agent';
const PARAM_BUFFERING_TIME = 'buffering_time';
const PARAM_ARCHIVE_DELAY_TIME = 'archive_delay_time';
const PARAM_GROUPS_ICONS = 'groups_icons';
const PARAM_PLUGIN_BACKGROUND = 'plugin_background';
const PARAM_ENABLE_DEBUG = 'enable_debug';
const PARAM_PER_CHANNELS_ZOOM = 'per_channels_zoom';
const PARAM_FUZZY_SEARCH_EPG = 'fuzzy_search_epg';
const PARAM_FAKE_EPG = 'fake_epg';
const PARAM_USE_HTTPS_PROXY = 'use_proxy';
const PARAM_STREAM_FORMAT = 'stream_format';
const PARAM_CUSTOM_DELETE_STRING = 'custom_delete_string';
const PARAM_CUSTOM_DELETE_REGEX = 'custom_delete_regex';
const PARAM_PROVIDER = 'provider';
const PARAM_LINK = 'link';
const PARAM_FILE = 'file';

const LIST_IDX = 'list_idx';

// deprecated and removed after upgrade
const PARAM_PLAYLISTS = 'playlists';
const PARAM_PLAYLISTS_NAMES = 'playlists_names';
const PARAM_EXT_XMLTV_SOURCES = 'ext_xmltv_sources';
const PARAM_XMLTV_SOURCE_KEY = 'cur_xmltv_key';
const PARAM_XMLTV_SOURCE_NAMES = 'xmltv_source_names';

// macroses used to replace template in providers playlists
const MACRO_LOGIN = '{LOGIN}';
const MACRO_PASSWORD = '{PASSWORD}';
const MACRO_SUBDOMAIN = '{SUBDOMAIN}';
const MACRO_OTTKEY = '{OTTKEY}';
const MACRO_TOKEN = '{TOKEN}';
const MACRO_DEVICE = '{DEVICE_ID}';
const MACRO_SERVER = '{SERVER_ID}';
const MACRO_QUALITY = '{QUALITY_ID}';

// provider type access
const PROVIDER_TYPE_PIN = 'pin';
const PROVIDER_TYPE_LOGIN = 'login';
const PROVIDER_TYPE_LOGIN_TOKEN = 'login-token';
const PROVIDER_TYPE_LOGIN_STOKEN = 'login-stoken';
const PROVIDER_TYPE_EDEM = 'edem';

const EPG_SOURCES_SEPARATOR_TAG = 'special_source_separator_tag';
const ENGINE_JSON = 'json';
const ENGINE_SQLITE = 'sqlite';
const ENGINE_LEGACY = 'legacy';
const EPG_CACHE_SUBDIR = 'epg_cache';
const HISTORY_SUBDIR = 'history';
const EPG_FUZZY_SEARCH = 1;
const EPG_FAKE_EPG = 2;

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
const PROVIDER_PATTERN = '/^(.+)@(.+)$/';

# Mounted storages path
const DUNE_MOUNTED_STORAGES_PATH = '/tmp/mnt/storage';
const DUNE_APK_STORAGE_PATH = '/sdcard/DuneHD/Dune_backup';

if (!defined('JSON_UNESCAPED_SLASHES'))
    define("JSON_UNESCAPED_SLASHES", 64);
if (!defined('JSON_PRETTY_PRINT'))
    define('JSON_PRETTY_PRINT', 128);
if (!defined('JSON_UNESCAPED_UNICODE'))
    define('JSON_UNESCAPED_UNICODE', 256);
