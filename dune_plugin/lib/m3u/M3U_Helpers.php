<?php

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


