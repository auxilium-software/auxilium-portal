<?php

namespace Auxilium;
class ICalendarComponent
{
    private $properties = null;

    public function __construct(array $content = null)
    {
        if($content == null)
        {
            $properties = [];
        }
        else
        {
            foreach($content as &$line)
            {
                $components = explode(":", $line);
                $attributes = explode(";", array_shift($components));
                $value = implode($components);
                $key = strtoupper(array_shift($attributes));
                $attributeArray = [];
                foreach($attributes as &$attribute)
                {
                    $attribute = explode("=", $attribute);
                    $attributeKey = strtoupper(array_shift($attribute));
                    $attribute = implode("=", $attribute);
                    $attributeArray[$attributeKey] = $attribute;
                }
                $this->properties[$key] = [
                    "value" => $value,
                    "attributes" => $attributeArray
                ];
            }
        }
    }

    public function unsetProperty($key)
    {
        $this->setProperty($key, null);
    }

    public function setProperty($key, $value = null, $attribs = [])
    {
        $key = strtoupper($key);
        if($value == null)
        {
            unset($this->properties[$key]);
        }
        else
        {
            $this->properties[$key] = [
                "value" => $value,
                "attributes" => []
            ];
            foreach($attribs as $attribKey => $attribValue)
            {
                $this->properties[$key]["attributes"][strtoupper($attribKey)] = $attribValue;
            }
        }
    }

    public function __toString()
    {
        return $this->stringify();
    }

    public function stringify()
    {
        $stringified = "";
        foreach($this->properties as $key => $value)
        {
            $attribs = [];
            foreach($value["attributes"] as $attribKey => $attribValue)
            {
                $attribs[] = strtoupper($attribKey) . "=" . $attribValue;
            }
            $stringified .= ICalendarObject::fold(strtoupper($key) . implode(";", $attribs) . ":" . ICalendarObject::escape($value["value"]) . "\r\n");
        }
        return $stringified;
    }
}
