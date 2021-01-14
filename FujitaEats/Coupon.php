<?php


namespace App\Utils;
use App\Models\FriendCoupon;
use App\Models\User;

class Coupon
{
    public static function checkExistUserReferFrined($couponcode,$id){
        $flag = false;
        $user = User::where('refer_friend',$couponcode)->first();
        if(isset($user)){
            if($user->id == $id){
                $flag =  false;
            }
            else{
                $flag = true;
            }
        }

        return $flag;
    }

    public static function CouponNebiki($coupon_id,$user_id){
        $coupon = FriendCoupon::find($coupon_id);
        if(isset($coupon)){
            if($coupon->user_id != $user_id){
                return 0;
            }else{
                if($coupon->status == config('const.Coupon.Status.Code.available')){
                    return $coupon->nebiki;
                }
                else{
                    return 0;
                }
            }
        }
        return 0;
    }

    public static function createFriendCoupon($refer_friend,$tyumon_id,$nebiki){
        $user = User::where('refer_friend',$refer_friend)->first();
        $friend_coupon = FriendCoupon::create([
            'user_id'=>$user->id,
            'status'=>config('const.Coupon.Status.Code.unavailable'),
            'coupon_code'=>sha1(uniqid()),
            'nebiki'=>$nebiki,
            'tyumon_id'=>$tyumon_id,
            'term'=>config('const.Coupon.Term.Code.1500'),
        ]);
        return $friend_coupon;
    }

    public static function createTyumonFriendCoupon($user_id,$tyumon_id,$nebiki){
        $friend_coupon = FriendCoupon::create([
            'user_id'=>$user_id,
            'status'=>config('const.Coupon.Status.Code.unavailable'),
            'coupon_code'=>sha1(uniqid()),
            'nebiki'=>$nebiki,
            'tyumon_id'=>$tyumon_id,
            'term'=>config('const.Coupon.Term.Code.1500'),
        ]);
        return $friend_coupon;
    }

    public static function Couponused($coupon_id){
        $friend_coupon = FriendCoupon::where('id',$coupon_id)->update([
            'status'=>config('const.Coupon.Status.Code.used')
        ]);
        return $friend_coupon;
    }

    public static function CouponAvailable($tyumon_id){
        if(FriendCoupon::where('tyumon_id',$tyumon_id)->exists() === true){
            FriendCoupon::where('tyumon_id',$tyumon_id)->update(['status'=>config('const.Coupon.Status.Code.available')]);
        }
    }
}
