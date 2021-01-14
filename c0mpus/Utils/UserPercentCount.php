<?php


namespace App\Utils;


use App\User;
use App\UserInterest;
use App\UserProfile;
use App\UserTag;
use App\WorkArea;

class UserPercentCount
{
    public static function ifIsSetCountUp($value, &$count){
        if(isset($value)){
            return $count++;
        }else{
            return $count;
        }
    }

    public static function ifGtNumCountUp($num, $value, &$count){
        if($value > $num){
            return $count++;
        }else{
            return $count;
        }
    }

    public static function calcProgSns(User $user)
    {
        // 完了(パーセント) ゲージ 計算
        $progressFinish = 0;

        //基本プロフィール
        self::ifIsSetCountUp($user->birthday, $progressFinish);
        self::ifIsSetCountUp($user->native_place, $progressFinish);
        self::ifIsSetCountUp($user->now_address, $progressFinish);
        self::ifIsSetCountUp($user->want_working_time, $progressFinish);
        self::ifIsSetCountUp($user->self_introduction, $progressFinish);

        //所属大学学部
        self::ifIsSetCountUp($user->school_name, $progressFinish);
        self::ifIsSetCountUp($user->faculty, $progressFinish);
        self::ifIsSetCountUp($user->subject, $progressFinish);
        self::ifIsSetCountUp($user->graduation_year, $progressFinish);
        self::ifIsSetCountUp($user->grade, $progressFinish);

        // 興味ある分野・タグ・エリア
        self::ifGtNumCountUp(0, UserInterest::where('user_id', $user->id)->count(), $progressFinish);
        self::ifGtNumCountUp(0, WorkArea::where('user_id', $user->id)->count(), $progressFinish);
        self::ifGtNumCountUp(0, UserTag::where('user_id', $user->id)->count(), $progressFinish);


        //後処理
        $progressFinish /= 13;

        // SNS 登録数 計算
        $snsTotal = 0;
        self::ifIsSetCountUp($user->facebook, $snsTotal);
        self::ifIsSetCountUp($user->twitter, $snsTotal);
        self::ifIsSetCountUp($user->github, $snsTotal);
        self::ifIsSetCountUp($user->note, $snsTotal);

        return array($progressFinish * 100, $snsTotal);
    }

    public static function crudInterest($user_id, $userInterests, $reqinterest)
    {
        if (!empty($reqinterest)) {

            $Input_interests = array_diff($reqinterest, $userInterests->pluck('interest')->toArray());//DBになくて，リクエストに増えたやつ
            $Delete_interests = array_diff($userInterests->pluck('interest')->toArray(), $reqinterest);//DBにあって，リクエストから減ったやつ

            foreach ($Input_interests as $input_interest) {
                UserInterest::create(['user_id' => $user_id, 'interest' => $input_interest]);
            }

            foreach ($Delete_interests as $delete_interest) {
                UserInterest::where('user_id', $user_id)->where('interest', $delete_interest)->delete();
            }
        } else {//何もチェックされてないとき
            if ($userInterests->isEmpty() == false) {//何かDBに値があるとき
                foreach ($userInterests as $userInterest) {
                    UserInterest::where('user_id', $user_id)->where('interest', $userInterest->interest)->delete();
                }
            }
        }
    }

    public static function crudTag($user_id, $userTags, $reqtag)
    {
        if (!empty($reqtag)) {

            $Input_tags = array_diff($reqtag, $userTags->pluck('tag_id')->toArray());//DBになくて，リクエストに増えたやつ
            $Delete_tags = array_diff($userTags->pluck('tag_id')->toArray(), $reqtag);//DBにあって，リクエストから減ったやつ

            foreach ($Input_tags as $input_tag) {
                UserTag::create(['user_id' => $user_id, 'tag_id' => $input_tag]);
            }

            foreach ($Delete_tags as $delete_tag) {
                UserTag::where('user_id', $user_id)->where('tag_id', $delete_tag)->delete();
            }
        } else {//何もチェックされてないとき
            if ($userTags->isEmpty() == false) {//何かDBに値があるとき
                foreach ($userTags as $userTag) {
                    UserTag::where('user_id', $user_id)->where('tag_id', $userTag->tag_id)->delete();
                }
            }
        }
    }

    public static function crudArea($user_id, $userAreas, $reqarea)
    {
        if (!empty($reqarea)) {

            $Input_areas = array_diff($reqarea, $userAreas->pluck('area_keys_id')->toArray());//DBになくて，リクエストに増えたやつ
            $Delete_areas = array_diff($userAreas->pluck('area_keys_id')->toArray(), $reqarea);//DBにあって，リクエストから減ったやつ

            foreach ($Input_areas as $input_area) {
                WorkArea::create(['user_id' => $user_id, 'area_keys_id' => $input_area]);
            }

            foreach ($Delete_areas as $delete_area) {
                WorkArea::where('user_id', $user_id)->where('area_keys_id', $delete_area)->delete();
            }
        } else {//何もチェックされてないとき
            if ($userAreas->isEmpty() == false) {//何かDBに値があるとき
                foreach ($userAreas as $userArea) {
                    WorkArea::where('user_id', $user_id)->where('area_keys_id', $userArea->area_keys_id)->delete();
                }
            }
        }
    }

    public static function crudLanguage($user_id, $request)
    {
        $already_UserProfile_ids = UserProfile::where('user_id', $user_id)->get();
        $store_UserProfile_ids = collect([]);
        for ($i = 0; $i < 10; $i++) {
            $large_info = "foreign_language";
            $medium_info = "foreign_language-medium-" . (string)$i;
            $small_info = "foreign_language-small-" . (string)$i;
            $level_info = "foreign_language-level-" . (string)$i;

            if ($request->input($medium_info)) {

                // validation(nullがあるレコードは無視)
                if($request->input($small_info) == "" || $request->input($level_info) == "" ){
                    continue;
                }

                $already_UserProfile = UserProfile::where('user_id', $user_id)
                    ->where('medium', $request->input($medium_info))
                    ->where('small', $request->input($small_info))
                    ->where('level', $request->input($level_info));

                // mediumがnullでない かつ UserProfileに存在しないデータのみ登録
                if ($already_UserProfile->doesntExist()) {
                    $userProfile = new UserProfile();
                    $userProfile->user_id = $user_id;
                    $userProfile->large = $large_info;
                    $userProfile->medium = $request->input($medium_info);
                    $userProfile->small = $request->input($small_info);
                    $userProfile->level = $request->input($level_info);
                    $userProfile->save();

                    $store_UserProfile_ids->push($userProfile->id);
                } else {
                    $store_UserProfile_ids->push($already_UserProfile->get()[0]->id);
                }
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $large_info = "programing_language";
            $medium_info = "programing_language-medium-" . (string)$i;
            $small_info = "programing_language-small-" . (string)$i;
            $level_info = "programing_language-level-" . (string)$i;

            if ($request->input($medium_info)) {

                // validation(nullがあるレコードは無視)
                if($request->input($small_info) == "" || $request->input($level_info) == "" ){
                    continue;
                }

                $already_UserProfile = UserProfile::where('user_id', $user_id)
                    ->where('medium', $request->input($medium_info))
                    ->where('small', $request->input($small_info))
                    ->where('level', $request->input($level_info));

                // mediumがnullでない かつ UserProfileに存在しないデータのみ登録
                if ($already_UserProfile->doesntExist()) {
                    $userProfile = new UserProfile();
                    $userProfile->user_id = $user_id;
                    $userProfile->large = $large_info;
                    $userProfile->medium = $request->input($medium_info);
                    $userProfile->small = $request->input($small_info);
                    $userProfile->level = $request->input($level_info);
                    $userProfile->save();

                    $store_UserProfile_ids->push($userProfile->id);
                } else {
                    $store_UserProfile_ids->push($already_UserProfile->get()[0]->id);
                }
            }
        }


        foreach ($already_UserProfile_ids as $already_UserProfile_id) {
            $del_flag = true;
            foreach ($store_UserProfile_ids as $store_UserProfile_id) {
                if ($already_UserProfile_id->id == $store_UserProfile_id) {
                    $del_flag = false;
                    continue;
                }
            }
            if ($del_flag) {
                $already_UserProfile_id->delete();
            }
        }
    }

    public static function updateRegistered($user_id, $request)
    {
        User::where('id', $user_id)->update([
            'gender' => $request->gender,
            'grade' => $request->grade,
            'school_name' => $request->school_name,
            'faculty' => $request->faculty,
            'subject' => $request->subject,
            'graduation_year'=>$request->graduation_year,
            'native_place'=>$request->native_place,
            'now_address'=>$request->now_address,
            'phone_number' => $request->phone_number,
            'traffic'=>$request->traffic,
            'shinsotsu_offer'=>$request->shinsotsu_offer
        ]);
    }
}
