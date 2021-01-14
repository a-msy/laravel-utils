<?php


namespace App\Utils;


use App\Intern_area;
use App\InternTag;
use App\Tag;

class InternStore
{
    protected $max = 47;
    public static function area($request, $intern)
    {
        $already_intern_area_ids = Intern_area::where('intern_id', $intern->id)->get();
        $store_intern_area_ids = collect([]);
        for ($i = 0; $i < $max; $i++) {
            $region = "region-" . (string)$i;
            $prefecture = "prefecture-" . (string)$i;

            if ($request->input($region)) {

                // validation(nullがあるレコードは無視)
                if($request->input($prefecture) == ""){
                    continue;
                }

                $already_intern_area = Intern_area::where('intern_id', $intern->id)
                    ->where('region', $request->input($region))
                    ->where('prefecture', $request->input($prefecture));

                // mediumがnullでない かつ Intern_areaに存在しないデータのみ登録
                if ($already_intern_area->doesntExist()) {
                    $intern_area = new Intern_area();
                    $intern_area->intern_id = $intern->id;
                    $intern_area->region = $request->input($region);
                    $intern_area->prefecture = $request->input($prefecture);
                    $intern_area->save();

                    $store_intern_area_ids->push($intern_area->id);
                } else {
                    $store_intern_area_ids->push($already_intern_area->get()[0]->id);
                }
            }
        }

        foreach ($already_intern_area_ids as $already_intern_area_id) {
            $del_flag = true;
            foreach ($store_intern_area_ids as $store_intern_area_id) {
                if ($already_intern_area_id->id == $store_intern_area_id) {
                    $del_flag = false;
                    continue;
                }
            }
            if ($del_flag) {
                $already_intern_area_id->delete();
            }
        }
    }

    public static function tag($request, $intern)
    {
        $tags = Tag::all();
        if (isset($request->input_tags)) {
            foreach ($tags as $tag) {
                if (array_key_exists($tag->id, $request->input_tags)) {//checkbox が チェックされている場合
//                dd($request->input_tags[$tag->id]);
                    $match = InternTag::where('intern_id', $intern->id)
                        ->where('tag_id', $request->input_tags[$tag->id])
                        ->exists();
                    if ($match == False) {
                        InternTag::insert([
                            'intern_id' => $intern->id,
                            'tag_id' => $request->input_tags[$tag->id],
                        ]);
                    }
                } else {
                    $match = InternTag::where('intern_id', $intern->id)
                        ->where('tag_id', $tag->id)
                        ->exists();
                    if ($match) {
                        InternTag::where('intern_id', $intern->id)
                            ->where('tag_id', $tag->id)
                            ->delete();
                    }
                }
            }
        } else {
            foreach ($tags as $tag) {
                $match = InternTag::where('intern_id', $intern->id)
                    ->where('tag_id', $tag->id)
                    ->exists();
                if ($match) {
                    InternTag::where('intern_id', $intern->id)
                        ->where('tag_id', $tag->id)
                        ->delete();
                }
            }
        }
    }

}
