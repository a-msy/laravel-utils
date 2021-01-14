<?php


namespace App\Utils;


use App\Fav;

class FavChecks
{
    public static function isUserFavedIntern($user_id,$intern_id){
        return Fav::where(['user_id'=>$user_id, 'intern_id'=>$intern_id])->exists();
    }
}
