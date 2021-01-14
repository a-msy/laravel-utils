<?php
namespace App\Utils;

class Sort
{
    public static function groupBySubKey($items, $key1, $key2)
    {
        $groups = [];
        foreach ($items as $item) {
            $key = $item[$key1][$key2];
            if (array_key_exists($key, $groups)) {
                $groups[$key][] = $item;
            } else {
                $groups[$key] = [$item];
            }
        }
        return $groups;
    }
}
