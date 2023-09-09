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

interface ExtTag
{
    /**
     * @param string $tag_name
     * @return bool
     */
    public function isTag($tag_name);

    /**
     * @return string|null
     */
    public function getTagName();

    /**
     * @param string $tag_name
     * @return void
     */
    public function setTagName($tag_name);

    /**
     * @return array
     */
    public function getTagValues();

    /**
     * @return string
     */
    public function getTagValue($idx = 0);

    /**
     * @param string $tag_value;
     * @return void
     */
    public function setTagValue($tag_value, $idx = 0);

    /**
     * @param string $tag_value;
     * @return void
     */
    public function addTagValue($tag_value);

    /**
     * @param string $data;
     * @return void
     */
    public function parseTagAttributes($data);

    /**
     * @return array
     */
    public function getAttributes();

    /**
     * @param array $attributes;
     * @return void
     */
    public function setAttributes($attributes);

    /**
     * @param string $attribute_name
     * @return string
     */
    public function getAttributeValue($attribute_name);

    /**
     * @param string $attribute_name
     * @return bool
     */
    public function hasAttributeValue($attribute_name);
}
