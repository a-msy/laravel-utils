<?php


namespace App\Services;


use App\Intern;

class InternService
{
    public static function findIntern($id){
        return Intern::find($id);
    }
}
