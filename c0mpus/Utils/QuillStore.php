<?php


namespace App\Utils;


use Carbon\Carbon;

class QuillStore
{
    public static function quill_description_store($request_info, $group_id, $group_name)
    {

        switch ($group_name) {
            case "company":
                $group_name_multi = "companies";
                break;
            case "intern":
                $group_name_multi = "interns";
                break;
            case "interview":
                $group_name_multi = "interviews";
                break;
            default:
                throw new \Exception($group_name . "は不正な値です");
                break;
        }

        $search_img = '/<img src=\"(.*?)\">/';
        $sql_store = $request_info;

        if (($img_count = preg_match_all($search_img, $request_info, $matches_img)) > 0) {
            $search = '/<img src=\"data:image\/(.*?)\">/';

            $now = Carbon::now();
            for ($i = 0; $i < $img_count; $i++) {

                if (preg_match($search, $matches_img[0][$i], $matches) > 0) {
                    $filename = "quills" . $now->year . $now->month . $now->day . $now->hour . $now->minute . $now->second . "_" . $group_name . "_" . $group_id . "_" . $i . "th.png";

                    //data:image/jpeg;base64 の切り取り
                    $base64 = substr($matches[0], 32, strlen($matches[0]) - 13);
                    $path = public_path() . '/storage/quill_image/' . $group_name_multi . '/' . $filename;
                    // $path に　画像を保存
                    file_put_contents($path, base64_decode($base64));
                    $replace = 'img src="' . asset('/storage/quill_image/' . $group_name_multi . '\/') . $filename . '"';
                    // $request_info　の中で　$search と一致した部分を $replace に変換
                    $sub_matches_img = substr($matches_img[0][$i], 0, 50) . '(.*?)\">';
                    $sql_store = preg_replace($sub_matches_img, $replace, $sql_store);
                }

            }
        }
        //使っていないfileの削除
        $search_stg_img = '/<img src=\"(.*?)\.png">/';
        if (($img_count = preg_match_all($search_stg_img, $sql_store, $matches_img)) > 0) {
            $search_stg_img_name = "/quills(.*?).png/";

            // 保存したimgのファイル名を$storesに保存
            $stores = [];
            for ($i = 0; $i < $img_count; $i++) {
                preg_match_all($search_stg_img_name, $matches_img[0][$i], $matches_img_name);
                array_push($stores, $matches_img_name[0][0]);
            }

            // storageの中にあるファイルを取得し、$log_filesに入れる
            $log_files = \File::glob(public_path() . '/storage/quill_image/' . $group_name_multi . '/quills*_' . $group_name . '_' . $group_id . '_*th.png');
            foreach ($log_files as $log_file) {
                $file_use = false;
                foreach ($stores as $store) {
                    if ($store == basename($log_file)) {
                        $file_use = true;
                        break;
                    }
                }
                if ($file_use == false) {
                    \File::delete($log_file);
                }
            }
        }
        return $sql_store;
    }

}
