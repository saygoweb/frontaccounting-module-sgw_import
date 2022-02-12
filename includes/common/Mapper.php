<?php

namespace SGW;

use Anorm\Model;

class Mapper
{
    public static function writeArray(&$c, &$data, $exclude = array())
    {
        $mapper = $c->_mapper;
        if (isset($data)) {
            foreach ($mapper->map as $property => $field) {
                if ($property[0] == '_') {
                    continue;
                }
                if (!in_array($property, $exclude)) {
                    $data[$field] = $c->$property;
                }
            }
            return true;
        }
        return false;
    }

    public static function writeModel(array $data, Model &$c, $exclude = array())
    {
        $mapper = $c->_mapper;
        foreach ($mapper->map as $property => $field) {
            if ($property[0] == '_') {
                continue;
            }
            if (!in_array($property, $exclude)) {
                $c->$property = html_entity_decode($data[$field]);
            }
        }
    }
}
