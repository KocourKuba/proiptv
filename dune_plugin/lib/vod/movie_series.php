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

require_once 'movie_playback_url.php';

class Movie_Series
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var string
     */
    public $season_id = '';

    /**
     * @var Movie_Playback_Url
     */
    public $default_playback_url;

    /**
     * @var string
     */
    public $poster = '';

    /**
     * @var Movie_Variant[]
     */
    public $variants;

    /**
     * @param string $id
     * @param string $name
     * @param Movie_Playback_Url|null $default_playback_url
     * @param string $season_id
     */
    public function __construct($id, $name, $default_playback_url, $season_id = '')
    {
        $this->id = (string)$id;
        $this->name = $name;
        $this->default_playback_url = $default_playback_url;
        $this->season_id = (string)$season_id;
    }

    /**
     * @param string $id
     * @param Movie_Variant $movie_variant
     */
    public function add_variant_data($id, $movie_variant)
    {
        $this->variants[$id] = $movie_variant;
    }

    /**
     * @return bool
     */
    public function has_variants()
    {
        return !empty($this->variants);
    }

    /**
     * @return Movie_Variant[]
     */
    public function get_variants()
    {
        return empty($this->variants) ? array() : $this->variants;
    }

    /**
     * @param string $id
     * @return Movie_Variant|null
     */
    public function get_variant($id)
    {
        return isset($this->variants[$id]) ? $this->variants[$id] : null;
    }
}
