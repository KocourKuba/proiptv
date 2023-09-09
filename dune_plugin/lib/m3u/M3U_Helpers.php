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

const TAG_EXTM3U    = '#EXTM3U';
const TAG_EXTINF    = '#EXTINF';
const TAG_EXTGRP    = '#EXTGRP';
const TAG_EXTHTTP   = '#EXTHTTP';
const TAG_EXTVLCOPT = '#EXTVLCOPT';

/**
 * @param string $line
 * @return bool
 */
function isLineTag($line)
{
    return !empty($line) && $line[0] === '#';
}

/**
 * @param string $line
 * @return bool
 */
function isExtM3u($line)
{
    return stripos($line, TAG_EXTM3U) === 0;
}

/**
 * @param string $line
 * @return bool
 */
function isExtInf($line)
{
    return stripos($line, TAG_EXTINF) === 0;
}

/**
 * @param string $line
 * @return bool
 */
function isExtGrp($line)
{
    return stripos($line, TAG_EXTGRP) === 0;
}


