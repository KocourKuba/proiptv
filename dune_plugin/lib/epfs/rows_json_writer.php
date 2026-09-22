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

/**
 * Writes a rows folder view to a file without ever holding the whole thing in
 * memory.
 *
 * json_encode() on a finished pane needs the complete rows array and then a
 * complete JSON string on top of it - for a 50000 channel playlist that is
 * ~90 MB of PHP arrays for a 12 MB file. Here the pane is encoded with a
 * placeholder where its rows belong, and the rows are encoded one at a time
 * straight into the file, so only one row exists at a time.
 *
 * The screen puts ROWS_PLACEHOLDER at pane[rows] and, when it emits any,
 * HEADERS_PLACEHOLDER at pane[headers]. Headers only become known while the
 * rows are produced, so they are substituted into the (small) tail afterwards.
 * Key order in the output is exactly what json_encode() of the finished pane
 * would have produced.
 */
class Rows_Json_Writer
{
    /**
     * Stand-ins for the two values that are filled in while streaming. They
     * only ever sit in the pane scaffolding, never next to playlist data, and
     * write_to_file() refuses to run if either appears more than once.
     */
    const ROWS_PLACEHOLDER = '@@proiptv_rows_placeholder@@';
    const HEADERS_PLACEHOLDER = '@@proiptv_headers_placeholder@@';
    const MIN_ROW_INDEX_PLACEHOLDER = '@@proiptv_min_row_index_placeholder@@';

    /**
     * @var resource
     */
    private $handle;

    /**
     * @var resource # hash context
     */
    private $hash_ctx;

    /**
     * @var bool
     */
    private $first_row = true;

    /**
     * @var int
     */
    private $rows_written = 0;

    /**
     * @var array
     */
    private $headers = array();

    /**
     * @var array # placeholder => value, substituted into the tail at the end
     */
    private $deferred = array();

    /**
     * Writes $folder_view to $path, asking $producer for the rows.
     *
     * $producer is the screen supplying the rows; it is called directly rather
     * than through call_user_func() so that its frame keeps a file and a line.
     * hd_debug_print(null) reads those off the backtrace, and when they are
     * missing it logs "unknown line" and dumps a full backtrace on every write.
     *
     * @param string $path
     * @param array $folder_view # pane[rows] must be ROWS_PLACEHOLDER
     * @param Starnet_Tv_Rows_Screen $producer # must have produce_rows($writer)
     * @return array|false array('md5', 'rows', 'produced'), false if nothing was written.
     *                     'produced' is whatever produce_rows() returned
     */
    public static function write_to_file($path, $folder_view, $producer)
    {
        $json = json_encode($folder_view);
        if ($json === false) {
            hd_debug_print("failed to encode folder view for: $path");
            return false;
        }

        $marker = '"' . self::ROWS_PLACEHOLDER . '"';
        $parts = explode($marker, $json);
        if (count($parts) !== 2) {
            hd_debug_print("rows placeholder found " . (count($parts) - 1) . " times, expected once");
            return false;
        }

        $writer = new Rows_Json_Writer();
        if (!$writer->open($path)) {
            return false;
        }

        $writer->put($parts[0] . '[');
        $produced = $producer->produce_rows($writer);
        $writer->put(']');
        $writer->put($writer->fill_tail($parts[1]));

        return $writer->finish($produced);
    }

    /**
     * Appends one row to the rows array being written.
     *
     * @param array $row
     * @return void
     */
    public function put_row($row)
    {
        $this->put(($this->first_row ? '' : ',') . json_encode($row));
        $this->first_row = false;
        $this->rows_written++;
    }

    /**
     * Collects a header to be written after the rows.
     *
     * @param array $header # PluginRowsHeader
     * @return void
     */
    public function add_header($header)
    {
        $this->headers[] = $header;
    }

    /**
     * Supplies the value for a placeholder the screen left in the pane. For
     * the pane fields whose value is only known once the rows have been
     * produced, by which time the head of the file is already written - they
     * sit after the rows in the encoded pane, so the tail can still carry them.
     *
     * @param string $placeholder
     * @param mixed $value
     * @return void
     */
    public function set_deferred_value($placeholder, $value)
    {
        $this->deferred[$placeholder] = $value;
    }

    /**
     * @return int
     */
    public function get_rows_written()
    {
        return $this->rows_written;
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * @param string $path
     * @return bool
     */
    private function open($path)
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            hd_debug_print("failed to open for writing: $path");
            return false;
        }

        $this->handle = $handle;
        $this->hash_ctx = hash_init('md5');

        return true;
    }

    /**
     * @param string $str
     * @return void
     */
    private function put($str)
    {
        fwrite($this->handle, $str);
        hash_update($this->hash_ctx, $str);
    }

    /**
     * Substitutes the headers and any deferred value into the encoded tail of
     * the pane. Each placeholder is matched with its quotes, so the value it
     * stands for keeps whatever JSON type it really has.
     *
     * @param string $tail
     * @return string
     */
    private function fill_tail($tail)
    {
        $deferred = $this->deferred;
        $deferred[self::HEADERS_PLACEHOLDER] = $this->headers;

        foreach ($deferred as $placeholder => $value) {
            $marker = '"' . $placeholder . '"';
            if (strpos($tail, $marker) !== false) {
                $tail = str_replace($marker, json_encode($value), $tail);
            }
        }

        return $tail;
    }

    /**
     * @param mixed $produced # what the producer returned
     * @return array|false
     */
    private function finish($produced)
    {
        if (!fclose($this->handle)) {
            hd_debug_print('failed to close epfs file');
            return false;
        }

        return array(
            'md5' => hash_final($this->hash_ctx),
            'rows' => $this->rows_written,
            'produced' => $produced,
        );
    }
}

/**
 * Chunks one group's items into regular rows and hands them to a
 * Rows_Json_Writer as each row fills up, so neither the group's items nor its
 * rows are ever all in memory at once.
 *
 * The title row and the header are emitted lazily, on the first item, so a
 * group that turns out to have no items produces nothing at all - which is
 * what emit_group_rows() does for an empty item list.
 */
class Rows_Group_Streamer
{
    /**
     * @var Rows_Json_Writer
     */
    private $writer;

    /**
     * @var int
     */
    private $items_in_row;

    /**
     * @var array # the group's title row, emitted before the first item row
     */
    private $title_row;

    /**
     * @var array|null # PluginRowsHeader, collected before the first item row
     */
    private $header;

    /**
     * @var string # row id up to the row index
     */
    private $row_id_prefix;

    /**
     * @var array # from Rows_Factory::regular_row_template()
     */
    private $row_template;

    private $started = false;
    private $chunk = array();
    private $in_row = 0;
    private $idx = 0;

    /**
     * @param Rows_Json_Writer $writer
     * @param int $items_in_row
     * @param array $title_row
     * @param array|null $header
     * @param string $row_id_prefix
     * @param array $row_template
     */
    public function __construct($writer, $items_in_row, $title_row, $header, $row_id_prefix, $row_template)
    {
        $this->writer = $writer;
        $this->items_in_row = $items_in_row;
        $this->title_row = $title_row;
        $this->header = $header;
        $this->row_id_prefix = $row_id_prefix;
        $this->row_template = $row_template;
    }

    /**
     * @return int how many items make one row
     */
    public function get_items_in_row()
    {
        return $this->items_in_row;
    }

    /**
     * Writes one row's worth of items. The caller is expected to have chunked
     * them - a loop over many channels can then keep its own chunk and reach
     * this once per row instead of once per channel, which on the 5.3 target
     * is the difference that matters.
     *
     * @param array $items # up to items_in_row PluginRegularItem
     * @return void
     */
    public function put_chunk($items)
    {
        if (empty($items)) {
            return;
        }

        if (!$this->started) {
            $this->started = true;
            if ($this->header !== null) {
                $this->writer->add_header($this->header);
            }
            $this->writer->put_row($this->title_row);
        }

        $this->writer->put_row(
            Rows_Factory::regular_row_from_template($this->row_template, $this->row_id_prefix . $this->idx, $items)
        );
        $this->idx++;
    }

    /**
     * Adds one item, writing a row whenever enough of them have arrived. For
     * the groups small enough that a call per item does not matter.
     *
     * @param array $item # PluginRegularItem
     * @return void
     */
    public function add_item($item)
    {
        $this->chunk[] = $item;
        if (++$this->in_row === $this->items_in_row) {
            $this->put_chunk($this->chunk);
            $this->chunk = array();
            $this->in_row = 0;
        }
    }

    /**
     * Writes whatever is left in the current chunk. Must be called once the
     * group's items are exhausted.
     *
     * @return bool true if the group produced any rows
     */
    public function flush()
    {
        if ($this->in_row !== 0) {
            $this->put_chunk($this->chunk);
            $this->chunk = array();
            $this->in_row = 0;
        }

        return $this->started;
    }
}

/**
 * The in-memory counterpart of Rows_Json_Writer: takes the same rows and
 * headers, keeps them in arrays instead of writing them out. Lets the screen
 * produce its rows through one code path whether they end up in a pane handed
 * back to the framework or streamed straight to the epfs file.
 */
class Rows_Array_Collector
{
    /**
     * @var array
     */
    private $rows = array();

    /**
     * @var array
     */
    private $headers = array();

    /**
     * @param array $row
     * @return void
     */
    public function put_row($row)
    {
        $this->rows[] = $row;
    }

    /**
     * @param array $header # PluginRowsHeader
     * @return void
     */
    public function add_header($header)
    {
        $this->headers[] = $header;
    }

    /**
     * Nothing to defer: the pane is built from get_rows()/get_headers() after
     * the rows are produced, so it can read these values directly. Here to
     * keep the sink interface the same as Rows_Json_Writer's.
     *
     * @param string $placeholder
     * @param mixed $value
     * @return void
     */
    public function set_deferred_value($placeholder, $value)
    {
    }

    /**
     * @return int
     */
    public function get_rows_written()
    {
        return count($this->rows);
    }

    /**
     * @return array
     */
    public function get_rows()
    {
        return $this->rows;
    }

    /**
     * @return array
     */
    public function get_headers()
    {
        return $this->headers;
    }
}
