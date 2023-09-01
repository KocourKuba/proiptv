<?php

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
     * @return string
     */
    public function getTagValue();

    /**
     * @param string $tag_value;
     * @return void
     */
    public function setTagValue($tag_value);

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
