<?php


namespace App\Services;


use App\Intern;
use App\Apply;

class ApplyService
{
    function getApplies($company_id): object
    {
        $interns = Intern::where('company_id', $company_id)->get();
        return Apply::whereIn('intern_id', $interns->map(function ($it) {
            return $it->id;
        }))->with('user', 'intern')->get()->groupBy('intern_id');
    }

    function getAppliesByIntern($intern_id): object
    {
        return Apply::where('intern_id', $intern_id)->get()->groupBy('intern_id');
    }
}
