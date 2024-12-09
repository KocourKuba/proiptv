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

// class to implement json serialization classes with protected variables
// private variables can't be serialized
// result of serialization can't be desirialized!
class Json_Serializer
{
    public function __toString()
    {
        return str_replace(array('"{', '}"', '\"'), array('{', '}', '"'), (string)pretty_json_format($this->_toStdClass()));
    }

    public function _toStdClass()
    {
        $object = new StdClass();
        $object->_class = get_class($this);
        $serialized = method_exists($this, '__sleep') ? $this->__sleep() : array();

        foreach (get_object_vars($this) as $name => $value) {
            if (!empty($serialized) && !in_array($name, $serialized)) continue;

            if (is_object($value) && method_exists($value, '_toStdClass')) {
                $object->$name = $value->_toStdClass();
            } else if (is_array($value)) {
                $object->$name = $this->_toArray($value);
            } else {
                $object->$name = $value;
            }
        }

        return $object;
    }

    public function _toArray($value)
    {
        $array = array();
        foreach ($value as $key => $item) {
            if (is_object($item) && method_exists($item, '_toStdClass')) {
                $array[$key] = $item->_toStdClass();
            } else if (is_array($item)) {
                $array[$key] = $this->_toArray($item);
            } else {
                $array[$key] = $item;
            }
        }
        return $array;
    }
}
