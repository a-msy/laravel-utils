<?php


namespace App\Utils;

use App\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class UserCheck
{
    public static function isDeleted(User $user)
    {
        if (isset($user->deleted_at)) {
            return abort(404, 'このユーザは退会しています．');
        }
    }

    public static function getPhoto($photo)
    {
        $noimage = public_path() . '/img/no_image_user.png';
        if ($photo != null) {
            if (Storage::exists($photo)) {
                return ['image' => Storage::get($photo), 'path' => Storage::path($photo)];
            } else {
                return ['image' => File::get($noimage), 'path' => $noimage];
            }
        } else {
            return ['image' => File::get($noimage), 'path' => $noimage];
        }
    }
}
