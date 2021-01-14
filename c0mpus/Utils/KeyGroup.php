<?php


namespace App\Utils;


class KeyGroup
{
    public static function array_group_by(array $items, $keyName)
    {
        $groups = [];
        foreach ($items as $item) {
            $key = $item[$keyName];
            if (array_key_exists($key, $groups)) {
                $groups[$key][] = $item;
            } else {
                $groups[$key] = [$item];
            }
        }
        return $groups;
    }
    public static function getUniqueArray($array, $column)
    {
        $tmp = [];
        $uniqueArray = [];
        foreach ($array as $value){
            if (!in_array($value[$column], $tmp)) {
                $tmp[] = $value[$column];
                $uniqueArray[] = $value;
            }
        }
        return $uniqueArray;
    }


}
