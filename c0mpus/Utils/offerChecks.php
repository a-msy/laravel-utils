<?php


namespace App\Utils;


use App\Company;
use App\Offer;
use Carbon\Carbon;

class offerChecks
{
    private $free_max = 5;
    private $subscription_max = 999;

    public function leftOfferCount(Company $company){
        $date = new Carbon();
        $offer_count = Offer::where('company_id', $company->id)->whereMonth('created_at', $date->format('m'))->count();

        if($company->billing_option == 0){
            $offer_count = $this->free_max - $offer_count;
            if($offer_count < 0){
                $offer_count = 0;
            }
        }else{
            $offer_count = $this->subscription_max - $offer_count;
        }

        return $offer_count;
    }

}
