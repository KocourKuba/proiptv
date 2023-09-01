<?php

class Json_Serializer
{
    public function __toString()
    {
        return $this->toJsonString();
    }

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
