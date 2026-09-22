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
    /**
     * Deeper than this the value is cut off; json_encode() itself stops at 512.
     */
    const MAX_DEPTH = 512;

    /**
     * Objects on the path currently being serialized, by spl_object_hash().
     * An object met again while it is still on the path is a reference cycle.
     * Only the path is tracked, not everything seen, so an object that simply
     * appears twice in different places is written out both times, the way
     * json_encode() does it.
     *
     * @var array
     */
    private static $json_path = array();

    /**
     * @var int
     */
    private static $json_depth = 0;

    /**
     * @return string
     */
    public function __toString()
    {
        // __toString() must return a string - anything else is a fatal error
        $json = json_encode($this->_toStdClass());
        return is_string($json) ? $json : '';
    }

    /**
     * Public and protected members of this object, recursively converted.
     * Private members are not reachable from here and are left out.
     *
     * @return stdClass
     */
    public function _toStdClass()
    {
        $hash = spl_object_hash($this);
        self::$json_path[$hash] = true;

        $object = new stdClass();
        $object->_class = get_class($this);

        foreach ($this->json_members() as $name => $value) {
            $object->$name = self::to_json_value($value);
        }

        unset(self::$json_path[$hash]);

        return $object;
    }

    /**
     * The members written out by _toStdClass(), as name => value.
     *
     * By default: the public and protected members, filtered by __sleep()
     * when the class has one. __sleep() returning array() means "no members",
     * so only a missing __sleep() (or one returning something unusable) means
     * "all members". A class whose JSON should differ from what __sleep() says
     * - or whose __sleep() does more than name members - overrides this.
     *
     * @return array
     */
    protected function json_members()
    {
        $only = null;
        if (method_exists($this, '__sleep')) {
            $names = $this->__sleep();
            if (is_array($names)) {
                $only = array_flip($names);
            }
        }

        $members = array();
        foreach (get_object_vars($this) as $name => $value) {
            if ($only === null || isset($only[$name])) {
                $members[$name] = $value;
            }
        }

        return $members;
    }

    /**
     * @param array $value
     * @return array
     */
    public function _toArray($value)
    {
        return self::to_json_value($value);
    }

    /**
     * Converts any value into something json_encode() writes out completely.
     *
     * A Json_Serializer anywhere in the value - in a member, an array, a
     * stdClass, or an object of some other class - comes out with its
     * protected members; json_encode() on its own would give only the public
     * ones. Other objects keep exactly what json_encode() gives them, their
     * public members, but are walked so the serializers inside them are found.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function to_json_value($value)
    {
        if (!is_array($value) && !is_object($value)) {
            return $value;
        }

        if (self::$json_depth >= self::MAX_DEPTH) {
            return '*MAX DEPTH*';
        }

        self::$json_depth++;

        if (is_array($value)) {
            $out = array();
            foreach ($value as $key => $item) {
                $out[$key] = self::to_json_value($item);
            }
        } else {
            $hash = spl_object_hash($value);
            if (isset(self::$json_path[$hash])) {
                $out = '*RECURSION ' . get_class($value) . '*';
            } else if ($value instanceof Json_Serializer) {
                $out = $value->_toStdClass();
            } else {
                self::$json_path[$hash] = true;
                $out = new stdClass();
                foreach (get_object_vars($value) as $name => $item) {
                    $out->$name = self::to_json_value($item);
                }
                unset(self::$json_path[$hash]);
            }
        }

        self::$json_depth--;

        return $out;
    }
}
