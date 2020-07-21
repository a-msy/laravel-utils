<?php


namespace App\Utils;


class Polygon
{
    public static function isPointinPolygon($point,$PolygonArray){
    // latをx軸，lngをy軸として考えているつもり
        $cn = 0;
        if($point["lat"]==null){
            $point["lat"]=0.0;
        }
        if($point["lng"]==null){
            $point["lng"]=0.0;
        }
        for($i = 0; $i < count($PolygonArray) - 1; $i++){
            // 上向きの辺。点Pがy軸方向について、始点と終点の間にある。ただし、終点は含まない。(ルール1)
            if( (($PolygonArray[$i]["lat"] <= $point["lat"]) && ($PolygonArray[$i+1]["lat"] > $point["lat"]))
                // 下向きの辺。点Pがy軸方向について、始点と終点の間にある。ただし、始点は含まない。(ルール2)
                || (($PolygonArray[$i]["lat"] > $point["lat"]) && ($PolygonArray[$i+1]["lat"] <= $point["lat"])) ){
                // ルール1,ルール2を確認することで、ルール3も確認できている。
                // 辺は点pよりも右側にある。ただし、重ならない。(ルール4)
                // 辺が点pと同じ高さになる位置を特定し、その時のxの値と点pのxの値を比較する。
                $vt = ($point["lat"] - $PolygonArray[$i]["lat"]) / ($PolygonArray[$i+1]["lat"]- $PolygonArray[$i]["lat"]);
                if($point["lng"] < ($PolygonArray[$i]["lng"] + ($vt * ($PolygonArray[$i+1]["lng"] - $PolygonArray[$i]["lng"])))){
                    ++$cn;
                }
            }
        }
        if($cn%2 == 0){
            return false;//偶数点だと外部
        }
        else{
            return true;//奇数点だと内部
        }
    }

    public static function geo($addr)
    {
        mb_language("Japanese"); //文字コードの設定
        mb_internal_encoding("UTF-8");

        $address = $addr; // 岡山県岡山市津島１－１－１　とか

        if (config('app.debug')) {
            //テスト環境, ローカル環境用の記述
            $myKey = "Your local Google Map API Key (Geo Coding)";
        } else {
            $myKey = "Your production Google Map API Key (Geo Coding)";
        }

        $address = urlencode($address);

        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . $address . "+CA&key=" . $myKey;

        $contents = file_get_contents($url);
        $jsonData = json_decode($contents, true);

        if($jsonData["status"]=="ZERO_RESULTS"){
        // GoogleMapAPIが受け取れない住所が入力されていたときの処理
            $lat = null;
            $lng = null;
        }else{
        //正常なときの処理
            $lat = $jsonData["results"][0]["geometry"]["location"]["lat"];
            $lng = $jsonData["results"][0]["geometry"]["location"]["lng"];
        }
        return array($lat, $lng);
    }
}
