<?php


namespace App\Utils;

use App\Intern;
use App\Intern_area;
use Illuminate\Support\Facades\Log;

class InternCellArea
{
    public static function getMaxFiveArea($areas)
    {
        $show = [];
        $touhoku = [];
        $kanto = [];
        $chubu = [];
        $kinki = [];
        $chugoku = [];
        $shikoku = [];
        $kyushu = [];

        foreach ($areas as $area) {
            switch ($area->region) {
                case 'zenkoku':
                    $show[] = config('const_prefecture.regions.zenkoku')[0];
                    return $show;
                case 'touhoku':
                    array_push($touhoku, config('const_prefecture.regions.touhoku')[1][$area->prefecture]);
                    break;
                case 'kanto':
                    array_push($kanto, config('const_prefecture.regions.kanto')[1][$area->prefecture]);
                    break;
                case 'chubu':
                    array_push($chubu, config('const_prefecture.regions.chubu')[1][$area->prefecture]);
                    break;
                case 'kinki':
                    array_push($kinki, config('const_prefecture.regions.kinki')[1][$area->prefecture]);
                    break;
                case 'chugoku':
                    array_push($chugoku, config('const_prefecture.regions.chugoku')[1][$area->prefecture]);
                    break;
                case 'shikoku':
                    array_push($shikoku, config('const_prefecture.regions.shikoku')[1][$area->prefecture]);
                    break;
                case 'kyushu':
                    array_push($kyushu, config('const_prefecture.regions.kyushu')[1][$area->prefecture]);
                    break;
            }
        }

        if (count($touhoku) == count(config('const_prefecture.regions.touhoku')[1])) {
            array_push($show, config('const_prefecture.regions.touhoku')[0]);
            $touhoku = [];
        }
        if (count($kanto) == count(config('const_prefecture.regions.kanto')[1])) {
            array_push($show, config('const_prefecture.regions.kanto')[0]);
            $kanto = [];
        }
        if (count($chubu) == count(config('const_prefecture.regions.chubu')[1])) {
            array_push($show, config('const_prefecture.regions.chubu')[0]);
            $chubu = [];
        }
        if (count($kinki) == count(config('const_prefecture.regions.kinki')[1])) {
            array_push($show, config('const_prefecture.regions.kinki')[0]);
            $kinki = [];
        }
        if (count($chugoku) == count(config('const_prefecture.regions.chugoku')[1])) {
            array_push($show, config('const_prefecture.regions.chugoku')[0]);
            $chugoku = [];
        }
        if (count($shikoku) == count(config('const_prefecture.regions.shikoku')[1])) {
            array_push($show, config('const_prefecture.regions.shikoku')[0]);
            $shikoku = [];
        }
        if (count($kyushu) == count(config('const_prefecture.regions.kyushu')[1])) {
            array_push($show, config('const_prefecture.regions.kyushu')[0]);
            $kyushu = [];
        }

        return array_slice(array_merge($show, $touhoku , $kanto , $chubu , $kinki , $chugoku , $shikoku , $kyushu), 0, 5);
    }
}
