<?php


namespace App\Services;


use App\Intern;
use App\Intern_area;
use App\InternTag;
use App\Occupation;
use http\Env\Request;
use Illuminate\Validation\Rules\In;

class SearchService
{
    protected $defaultOccuId = 1;
    public function defaultOccupation()
    {
        return Occupation::find($this->defaultOccuId);
    }

    public function returnOccupationFromId($id)
    {
        return Occupation::find($id);
    }

    public function returnOccupationFromEng($eng)
    {
        return Occupation::where('eng', $eng)->first();
    }

    public function returnPrefectureName(string $key)
    {
        $pref = config('const_prefecture.prefectures');
        if (array_key_exists($key,$pref)) {
            return $pref[$key];
        } else {
            return "";
        }
    }

    public function returnRegionName(string $key)
    {
        $regions = config('const_prefecture.regions');
        if (array_key_exists($key,$regions)) {
            return $regions[$key][0];
        } else {
            return "";
        }
    }

    public function returnPrefecture(string $key)
    {
        $name = self::returnPrefectureName($key);

        if (empty($name)) { //県に引っかからない時
            $name = self::returnRegionName($key);
        }
        if (empty($name)) {
            // 県でも地方でもないとき
            $name = "";
        }
        return ['key' => $key, 'name' => $name];
    }

    public function getRegionOrPref($key)
    {
        if (!empty(self::returnRegionName($key))) {
            return [
                'key'=>$key,
                'name'=>config('const_prefecture.regions')[$key][0],
                'which'=>"region"
            ];
        } else if (!empty(self::returnPrefectureName($key))) {
            return [
                'key'=>$key,
                'name'=>config('const_prefecture.prefectures')[$key],
                'which'=>"prefecture"
            ];
        } else {
            return [
                'key'=>'',
                'name'=>'',
                'which'=>''
            ];
        }
    }

    public function getInterns($which, $occupation, $area_key, $keyword)
    {
        if (empty($which)) {
            return self::getInternsAll();
        }else {
            return self::getInternsByInternAreaIds($which, $area_key, $occupation, $keyword);
        }
    }

    public function getInternsAll(){
        return Intern::where('is_public','<>',0)->orderBy('is_public','asc')
            ->orderBy('priority', 'asc')
            ->with('occupation','tags')
            ->get();
    }

    public function getInternsByInternAreaIds($which, $area_key, $occupation, $keyword){
        $intern_area_ids = self::returnInternAreaInternIds($which, $area_key);
        $interns_query = Intern::query()
            ->where('is_public','<>',0)
            ->whereIn('id',$intern_area_ids);

        if(isset($occupation) && $occupation->id != $this->defaultOccuId){
            // 職種があるとき
            $interns_query->where('occupation_id',$occupation->id);
        }
        if(isset($keyword)){
            $interns_query->where(function($query) use ($keyword){
                $query->where('name','like',"%$keyword%")
                    ->orWhere('description','like',"%$keyword%");
            });
        }
        $interns = $interns_query->orderBy('is_public','asc')
            ->orderBy('priority', 'asc')
            ->with('occupation','tags')
            ->get();

        return $interns;
    }

    public function returnInternAreaInternIds($which, $area_key){
        $intern_area_query = Intern_area::query()->distinct();
        $intern_area_query->select('intern_id');
        if(isset($area_key) && $area_key != "zenkoku") {
            if(isset($which) && $which == 'region'){
                $intern_area_query
                    ->where('region',$area_key)
                    ->orWhere('region','zenkoku');
            }else{
                $intern_area_query
                    ->where('prefecture',$area_key)
                    ->orWhere('prefecture','zenkoku');
            }
        }
        $intern_area_ids = $intern_area_query
            ->get()
            ->pluck('intern_id')
            ->toArray();
        return $intern_area_ids;
    }
    public function getInternsByTag($which, $tag_id, $area, $keyword){

        $intern_area_ids = self::returnInternAreaInternIds($which, $area);

        $intern_ids = InternTag::where('tag_id', $tag_id)
            ->get()
            ->pluck('intern_id')
            ->toArray();

        $interns = Intern::where('is_public', '<>', 0)
            ->where(function ($query) use ($intern_ids,$intern_area_ids){
              $query->WhereIn('id',$intern_area_ids)
                  ->whereIn('id', $intern_ids);
            })->where(function($query) use ($keyword){
                $query->where('name','like',"%$keyword%")
                    ->orWhere('description','like',"%$keyword%");
            })
            ->orderBy('is_public', 'asc')
            ->orderBy('priority', 'asc')
            ->with('occupation', 'tags')
            ->get();
        return $interns;
    }
    public function buildTitle($occupation_name, $area_name, $keyword)
    {
        if (!empty($occupation_name)) {
            $occupation_text = $occupation_name . "の";
        } else {
            $occupation_text = '';
        }

        if (!empty($area_name)) {
            $area_text = $area_name . "の";
        } else {
            $area_text = '';
        }

        if (!empty($keyword)) {
            $keyword_text = $keyword . "の";
        } else {
            $keyword_text = '';
        }
        return $area_text . $keyword_text . $occupation_text . "長期インターン";
    }

    public function searchRecommendIntern($occupation, $area)
    {
        $occupations = [
            'designer' => [
                'occupation_id' => [4],
            ],
            'engineer' => [
                'occupation_id' => [3],
            ],
            'sales' => [
                'occupation_id' => [2]
            ],
            'moviemaker' => [
                'occupation_id' => [11],
            ],
        ];
        if (array_key_exists($occupation, $occupations) == false) {
            return array();
        } else {
            return Intern::where('occupation_id', $occupations[$occupation]['occupation_id'])
                ->orderBy('is_public', 'asc')
                ->orderBy('priority', 'asc')
                ->with('occupation', 'tags')->get();
        }
    }
}
