<?php


namespace App\Services;


use App\Offer;
use Illuminate\Support\Facades\Auth;

class OfferService
{
    public static function getAllOffers(){
        return Offer::with('user', 'intern')->orderBy('created_at', 'desc')->paginate(100);
    }

    public static function getCompanyOffers($company_id){
        return Offer::where('company_id', $company_id)->with('user', 'intern')->orderBy('created_at', 'desc')->paginate(50);
    }

    public static function getUserOffers($user_id){
        return Offer::where('user_id', $user_id)->with('company', 'intern')->orderBy('created_at', 'desc')->paginate(50);
    }

    public static function findOffer($offer_id){
        return Offer::with('user', 'intern','company')->find($offer_id);
    }

}
