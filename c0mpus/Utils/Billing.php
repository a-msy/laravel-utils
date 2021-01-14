<?php


namespace App\Utils;

use App\Apply;
use App\Intern;
use Carbon\Carbon;
use phpDocumentor\Reflection\Types\Boolean;
use Illuminate\Support\Facades\DB;

class Billing
{
    private $businessFirstMonth = 6800;
    private $techFirstMonth = 9800;
    private $businessFixed = 10000;
    private $techFixed = 15000;
    private $businessVariableRate = 0.17;
    private $techVariableRate = 0.25;
    private $defaultSalary = 0;
    private $taxRate = 1.1;
    private $maxUnder = 3000;

    public function returnNormalAmount(Apply $apply, $salary)
    {
        $amount = 0;
        //契約種別の確認
        switch ($apply->type) {
            case config('const.billing_type.status.BusinessFixed'):
                $amount = $this->businessFixed;
                break;

            case config('const.billing_type.status.BusinessVariable'):
                $amount = $salary * $this->businessVariableRate;
                break;

            case config('const.billing_type.status.TechFixed'):
                $amount = $this->techFixed;
                break;

            case config('const.billing_type.status.TechVariable'):
                $amount = $salary * $this->techVariableRate;
                break;

            default:
                $amount = 0;
                break;
        }
        return $amount;
    }

    public function returnFirstMonthAmount(Apply $apply)
    {
        $amount = 0;
        //契約種別の確認
        switch ($apply->type) {
            case config('const.billing_type.status.BusinessFixed'):
                $amount = $this->businessFirstMonth;
                break;

            case config('const.billing_type.status.BusinessVariable'):
                $amount = $this->businessFirstMonth;
                break;

            case config('const.billing_type.status.TechFixed'):
                $amount = $this->techFirstMonth;
                break;

            case config('const.billing_type.status.TechVariable'):
                $amount = $this->techFirstMonth;
                break;

            default:
                $amount = 0;
                break;
        }
        return $amount;
    }

    public function returnStatus($date, Apply $apply)
    {
        if (!$this->isBillingFirstMonth($date, $apply)) {
            if ($apply->type == config('const.billing_type.status.BusinessVariable') || $apply->type == config('const.billing_type.status.TechVariable')) {
                return config('const.billing_status.state.WaitingSalaryInput');
            } elseif ($apply->type == config('const.billing_type.status.BusinessFixed') || $apply->type == config('const.billing_type.status.TechFixed')) {
                return config('const.billing_status.state.WaitingPayment');
            } else {
                return config('const.billing_status.state.BillingIssued');
            }
        } else {
            if ($apply->type == config('const.billing_type.status.UnDefined')) {
                return config('const.billing_status.state.BillingIssued');
            } else {
                return config('const.billing_status.state.WaitingPayment');
            }
        }
    }

    public function isBillingFirstMonth($date, Apply $apply)
    {
        //　対象のインターンidの
        //　今月よりも前の請求が
        //　billingsテーブルになければ，その請求は初月扱い
        $billings = \App\Billing::where('apply_id', '=', $apply->id)->where('billing_date', '<', $date)->doesntExist();
        return $billings;
    }

    public function isHalfMonth(Apply $apply)
    {
        $date = Carbon::now();
        if ($apply->first_attend_date->day < "16") {
            return true;
        } else {
            return false;
        }
    }

    public function checkDiscount($date, Apply $apply, $amount)
    {
        if ($this->isBillingFirstMonth($date, $apply)) {
            $amount = $this->returnFirstMonthAmount($apply);
        }
        if ($this->isHalfMonth($apply)) {
            $amount = $amount * 0.5;
        }
        if ($amount < $this->maxUnder) {
            $amount = $this->maxUnder;
        }
        return $amount;
    }

    public function returnBillingDate($date, Apply $apply)
    {
        if ($this->isBillingFirstMonth($date, $apply)) {
            $date = $apply->first_attend_date->addMonth()->startOfMonth();
            $due_date = $apply->first_attend_date->addMonth()->endOfMonth();
        } else {
            $date = Carbon::today()->addMonth()->startOfMonth();
            $due_date = Carbon::today()->addMonth()->endOfMonth();
        }
        return array($date, $due_date);
    }

    public function calcAmount(Apply $apply, $salary, $calcTax, $date)
    {
        $amount = $this->returnNormalAmount($apply, $salary);
        $discountedAmount = $this->checkDiscount($date, $apply, $amount);

        if ($calcTax == true) {
            return floor($discountedAmount * $this->taxRate);
        } else {
            return $discountedAmount;
        }
    }

    public function createBilling(Apply $apply)
    {
        list($billingDate, $due_date) = $this->returnBillingDate(Carbon::now()->startOfMonth(), $apply);

        $billing = \App\Billing::create([
            'apply_id' => $apply->id,
            'status' => $this->returnStatus($billingDate, $apply),
            'amount' => $this->calcAmount($apply, $this->defaultSalary, true, $billingDate),
            'billing_date' => $billingDate,
            'due_date' => $due_date,
        ]);
    }

    public function hasBillings($company_id)
    {
        $interns = Intern::where('company_id',$company_id)->select('id')->get()->pluck('id');
        $applies = Apply::whereIn('intern_id',$interns)->select('id')->get()->pluck('id');
        $billings = \App\Billing::whereIn('apply_id',$applies)->with('apply')->orderBy('billing_date','desc')->get();
        return $billings;
    }
}
