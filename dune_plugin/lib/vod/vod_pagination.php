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

class vod_pagination
{
    /**
     * @var bool
     */
    protected $is_entered = false;

    /**
     * @var array
     */
    protected $pages = array();

    /**
     * @var array
     */
    protected $movie_counter = array();

    public function try_reset_pages()
    {
        if ($this->is_entered) {
            $this->is_entered = false;
            $this->pages = array();
        }
    }

    /**
     * @param string $page_id
     * @param int $start_from
     * @return int
     */
    public function get_current_page_index($page_id, $start_from = 0)
    {
        hd_debug_print(null, true);
        if (!array_key_exists($page_id, $this->pages)) {
            $this->pages[$page_id] = $start_from;
        }

        $current_idx = array_key_exists($page_id, $this->pages) ? $this->pages[$page_id] : 0;
        hd_debug_print("get_current_page page_id: $page_id current_idx: $current_idx", true);
        return $current_idx;
    }

    /**
     * @param string $page_id
     */
    public function is_page_index_stopped($page_id)
    {
        hd_debug_print(null, true);
        return array_key_exists($page_id, $this->pages) && $this->pages[$page_id] < 0;
    }

    /**
     * @param string $page_id
     */
    public function stop_page_index($page_id)
    {
        hd_debug_print(null, true);
        hd_debug_print("stop_page_index page_id: $page_id", true);
        $this->pages[$page_id] = -1;
    }

    /**
     * @param string $page_id
     * @param int $increment
     */
    public function shift_next_page_index($page_id, $increment = 1)
    {
        hd_debug_print(null, true);

        if ($this->pages[$page_id] !== -1) {
            $this->pages[$page_id] += $increment;
        }

        hd_debug_print("get_next_page page_id: $page_id next_idx: {$this->pages[$page_id]}", true);
    }

    public function reset_movie_counter()
    {
        $this->is_entered = true;
        $this->movie_counter = array();
    }

    /**
     * @param mixed $key
     * @return int
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
}
