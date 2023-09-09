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

class Json_Serializer
{
    public function __toString()
    {
        return $this->toJsonString();
    }

    /**
     * @return string
     */
    public function toJsonString()
    {
        $object = new StdClass();
        $object->_class = get_class($this);
        foreach (get_object_vars($this) as $name => $value) {
            if (is_object($value) && method_exists($value, 'toJsonString')) {
                $object->$name = $value->toJsonString();
            } else if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_object($item) && method_exists($item, 'toJsonString')) {
                        $object->$name = $item->toJsonString();
                    } else {
                        $object->$name = $value;
                    }
                }
            } else {
                $object->$name = $value;
            }
        }

        return (string)raw_json_encode($object);
    }
}
