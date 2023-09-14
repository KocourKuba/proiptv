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

require_once 'lib/json_serializer.php';
require_once 'ExtTag.php';

class ExtTagDefault extends Json_Serializer implements ExtTag
{
    /**
     * @var string $tag_name
     */
    protected $tag_name;

    /**
     * @var array $tag_values
     */
    protected $tag_values;

    /**
     * @var array $attributes
     */
    protected $attributes;

    /**
     * @inheritDoc
     */
    public function isTag($tag_name)
    {
        return is_null($this->tag_name) ? null : (stripos($this->tag_name, $tag_name) === 0);
    }

    /**
     * @inheritDoc
     */
    public function getTagName()
    {
        return $this->tag_name;
    }

    /**
     * @inheritDoc
     */
    public function setTagName($tag_name)
    {
        return $this->tag_name = $tag_name;
    }

    /**
     * @inheritDoc
     */
    public function getTagValues()
    {
        return $this->tag_values;
    }

    /**
     * @inheritDoc
     */
    public function getTagValue($idx = 0)
    {
        return isset($this->tag_values[$idx]) ? $this->tag_values[$idx] : null;
    }

    /**
     * @inheritDoc
     */
    public function setTagValue($tag_value, $idx = 0)
    {
        $this->tag_values[$idx] = $tag_value;
    }

    /**
     * @inheritDoc
     */
    public function addTagValue($tag_value)
    {
        $this->tag_values[] = $tag_value;
    }

    /**
     * @inheritDoc
     */
    public function parseTagAttributes($data)
    {
        preg_match_all('/([a-zA-Z0-9\-]+?)="([^"]*)"/', $data, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $this->attributes[$match[1]] = $match[2];
        }
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttributeValue($attribute_name)
    {
        return isset($this->attributes[$attribute_name]) ? $this->attributes[$attribute_name] : null;
    }

    /**
     * @inheritDoc
     */
    public function hasAttributeValue($attribute_name)
    {
        return array_key_exists($attribute_name, $this->attributes);
    }

    /**
     * @param string $line
     */
    public function parseData($line)
    {
        // currently duration parsing not implemented
        if (empty($line) || $line[0] !== '#')
            return null;

        if (preg_match("/^(#[^: ]+)[:\s]?(.*)/", $line, $m)) {
            $this->setTagName($m[1]);
        }

        return $this;
    }

    /**
     * @param string $line
     */
    public function parseFullData($line)
    {
        // currently duration parsing not implemented
        if (empty($line) || $line[0] !== '#')
            return null;

        if (preg_match("/^(#[^: ]+)[:\s]?(.*)/", $line, $m)) {
            $this->setTagName($m[1]);
            if ($this->isTag(Entry::TAG_EXTINF)) {
                $split = explode(',', $m[2]);
                $this->setTagValue(trim(end($split)));
            } else {
                $this->setTagValue(trim($m[2]));
            }
            $this->parseTagAttributes($m[2]);
        }

        return $this;
    }
}
