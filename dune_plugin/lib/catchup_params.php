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

class catchup_params
{
    const /* (char *)	*/ CU_START        = '${start}';
    const /* (char *)	*/ CU_UTC          = '${utc}';
    const /* (char *)	*/ CU_CURRENT_UTC  = '${current_utc}';
    const /* (char *)	*/ CU_TIMESTAMP    = '${timestamp}';
    const /* (char *)	*/ CU_OFFSET       = '${offset}';
    const /* (char *)   */ CU_END          = '${end}';
    const /* (char *)   */ CU_UTCEND       = '${utcend}';
    const /* (char *)   */ CU_DURATION     = '${duration}';
    const /* (char *)   */ CU_DURMIN       = '${durmin}';
    const /* (char *)   */ CU_YEAR         = '{Y}';
    const /* (char *)   */ CU_START_YEAR   = '${start-year}';
    const /* (char *)   */ CU_END_YEAR     = '${end-year}';
    const /* (char *)   */ CU_MONTH        = '{m}';
    const /* (char *)   */ CU_START_MONTH  = '${start-mon}';
    const /* (char *)   */ CU_END_MONTH    = '${end-mon}';
    const /* (char *)   */ CU_DAY          = '{d}';
    const /* (char *)   */ CU_START_DAY    = '${start-day}';
    const /* (char *)   */ CU_END_DAY      = '${end-day}';
    const /* (char *)   */ CU_HOUR         = '{H}';
    const /* (char *)   */ CU_START_HOUR   = '${start-hour}';
    const /* (char *)   */ CU_END_HOUR     = '${end-hour}';
    const /* (char *)   */ CU_MIN          = '{M}';
    const /* (char *)   */ CU_START_MIN    = '${start-min}';
    const /* (char *)   */ CU_END_MIN      = '${end-min}';
    const /* (char *)   */ CU_SEC          = '{S}';
    const /* (char *)   */ CU_START_SEC    = '${start-sec}';
    const /* (char *)   */ CU_END_SEC      = '${end-sec}';
}
