<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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

const TAG_EXTM3U = '#EXTM3U';
const TAG_EXTINF = '#EXTINF';
const TAG_EXTGRP = '#EXTGRP';
const TAG_EXTHTTP = '#EXTHTTP';
const TAG_EXTVLCOPT = '#EXTVLCOPT';
const TAG_PLAYLIST = '#PLAYLIST';

const ATTR_CHANNEL_NAME = 'name';
const ATTR_CHANNEL_ID_ATTRS = 'channel-id-attributes';
const ATTR_GROUP_TITLE = 'group-title';
const ATTR_TVG_ID = 'tvg-id';
const ATTR_TVG_NAME = 'tvg-name';
const ATTR_TVG_REC = 'tvg-rec';
const ATTR_TVG_EPGID = 'tvg-epgid';
const ATTR_TIMESHIFT = 'timeshift';
const ATTR_TVG_SHIFT = 'tvg-shift';
const ATTR_ARC_TIMESHIFT = 'arc-timeshift';
const ATTR_ARC_TIME = 'arc-time';

const ATTR_CHANNEL_HASH = 'hash';
const ATTR_PARSED_ID = 'parsed_id';
const ATTR_CHANNEL_ID = 'channel-id';
const ATTR_CUID = 'CUID';
const ATTR_CH_ID = 'ch-id';
const ATTR_TVG_CHNO = 'tvg-chno';
const ATTR_CH_NUMBER = 'ch-number';

const ATTR_URL_TVG = 'url-tvg';
const ATTR_X_TVG_URL = 'x-tvg-url';

const ATTR_TVG_LOGO = 'tvg-logo';
const ATTR_URL_LOGO = 'url-logo';
const ATTR_GROUP_LOGO = 'group-logo';

const ATTR_ADULT = 'adult';
const ATTR_PARENT_CODE = 'parent-code';
const ATTR_CENSORED = 'censored';

const ATTR_CATCHUP = 'catchup';
const ATTR_CATCHUP_DAYS = 'catchup-days';
const ATTR_CATCHUP_TIME = 'catchup-time';
const ATTR_CATCHUP_TYPE = 'catchup-type';
const ATTR_CATCHUP_SOURCE = 'catchup-source';
const ATTR_CATCHUP_TEMPLATE = 'catchup-template';
const ATTR_CATCHUP_DEFAULT = 'default';
const ATTR_CATCHUP_SHIFT = 'shift';
const ATTR_CATCHUP_APPEND = 'append';
const ATTR_CATCHUP_ARCHIVE = 'archive';
const ATTR_CATCHUP_FS = 'fs'; // short name of flussonic
const ATTR_CATCHUP_FLUSSONIC = 'flussonic';
const ATTR_CATCHUP_FLUSSONIC_HLS = 'flussonic-hls';
const ATTR_CATCHUP_FLUSSONIC_TS = 'flussonic-ts';
const ATTR_CATCHUP_XTREAM_CODES = 'xs';
const ATTR_CATCHUP_UNKNOWN = 'unknown';
