<?php


namespace App\Services;

use App\Models\Admin;
use App\Models\Master;
use App\Models\Menu;
use App\Models\Tyumon;
use App\Models\User;
use App\Utils\Coupon;
use App\Utils\OrderStringBuilder;
use App\Utils\Polygon as Polygon;
use App\Utils\Sort;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CartService
{
    private $public = 1;
    private $shipping = 385;
    private $tax = 1.08;
    private $second_shop_nebiki = 185;
    private $refer_friend_nebiki = 300;
    private $service_fee = 150;
    public $isFirstBuy = false;

    public function isCartEmpty($user_id)
    {
        return \Cart::session($user_id)->isEmpty($user_id);
    }

    public function isStoreHasItem(Menu $menu, $shop_id)
    {
        if (isset($shop_id) && $menu->admin_id != $shop_id) {
            return redirect(route('menu.detail', ['id' => $menu->id]))
                ->with('error', '不正なリクエストです');
        } else {
            return true;
        }
    }

    public function isMenuPublic(Menu $menu)
    {
        if ($menu->is_public != $this->public) {
            return redirect(route('shop.detail', ['id' => $menu->admin_id]))
                ->with('error', 'メニューが非公開です');
        } else {
            return true;
        }
    }

    public function isShopPublic(Menu $menu)
    {
        if ($menu->admin->public != $this->public) {
            return redirect(route('shop.detail', ['id' => $menu->admin_id]))
                ->with('error', '店舗が受付中ではありません');
        } else {
            return true;
        }
    }

    public function isFirstBuy($user_id)
    {
        $flag = Tyumon::where('user_id', $user_id)
            ->where([
                ['status', '<>', config('const.Order.Status.Code.Cancel')],
                ['status', '<>', config('const.Order.Status.Code.Invalidate')],
                ['status', '<>', config('const.Order.Status.Code.Reject')]
            ])
            ->doesntExist();
        return $flag;
    }

    public function validateUserInfo($user)
    {
        $modal = 0;
        if ($user->addr == "" || $user->phone == "") {
            $modal = 1; // 住所か電話番号が入力されていない
        }
        //latlngの有無判定
        if ($user->lat == null || $user->lng == null) {
            //2020/07/21現在既存のユーザはnullなため取得．
            list($lat, $lng) = Polygon::geo($user->addr);
            if ($lat == null || $lng == null) {
                //取得してもなお不正な場合
                $modal = 2;
            } else {
                User::where('id', $user->id)->update([
                    'lat' => $lat,
                    'lng' => $lng
                ]);
            }
        }
        return $modal;
    }

    public function deliveryFree($today, $nebiki)
    {
        $master = Master::find(1);
        $now = Carbon::now();
        if ($now->lt($master->delivery_free_date) && $master->delivery_free_status == 1) {
            return $nebiki;
        } else {
            return 0;
        }
    }

    public function isUnder700($shop_subtotal)
    {
        if ($shop_subtotal < 700) {
            return $this->service_fee;
        } else {
            return 0;
        }
    }

    public function checkNebiki($shop_count)
    {
        $nebiki = 0;
        if ($shop_count == 1) {//1店舗目
            $nebiki = $this->deliveryFree(date("Y/m/d"), $this->shipping);
            if($this->isFirstBuy) {
                if ($nebiki <= 0) { //キャンペーン期間中に二重に送料無料になるのを防ぐ
                    $nebiki += $this->shipping;
                }
            }
        }

        return $nebiki;
    }

    public function groupingItems($items)
    {
        return Sort::groupBySubKey($items, "attributes", "shop_id");
    }

    public function calcCart($grouped_items)
    {
        $subtotal = 0;
        $shipping = 0;
        $add = 0;
        $total_Intax = 0;
        $nebiki = 0;
        $shop_count = 0;

        foreach ($grouped_items as $shop_items) {
            $shop_subtotal = 0;
            $shop_total_Intax = 0;
            $shop_count++;
            $shipping += $this->shipping;

            $nebiki += self::checkNebiki($shop_count);

            foreach ($shop_items as $item) {
                $shop_subtotal += $item["price"] * $item["quantity"];
                $shop_total_Intax += floor($item["price"] * $this->tax) * $item["quantity"];
            }

            $add += self::isUnder700($shop_subtotal);//700円以下は追加料金
            $subtotal += $shop_subtotal;
            $total_Intax += $shop_total_Intax;
        }

        $tax = $total_Intax - $subtotal;

        return [
            'subtotal' => $subtotal,
            'total_intax' => $total_Intax,
            'shipping' => $shipping,
            'add' => $add,
            'nebiki' => $nebiki,
            'tax' => $tax,
            'shop_count' => $shop_count,
        ];
    }

    public function getCartMenusIds($items)
    {
        $cartMenus_ids = array();
        foreach ($items as $item) {
            foreach ($item as $i) {
                $cartMenus_ids[] = $i["id"];
            }
        }
        return $cartMenus_ids;
    }

    public function deleteScanItem($item_id, $user_id)
    {
        \Cart::session($user_id)->remove($item_id);
    }

    public function addUpdatedItem(Menu $newitem, $item_id, $item_quantity)
    {
        \Cart::session(Auth::id())->add(array(
                'id' => $newitem->id,
                'name' => $newitem->menu,
                'price' => $newitem->value,
                'quantity' => $item_quantity,
                'attributes' => array(
                    'avatar' => $newitem->avatar,
                    'shopname' => $newitem->admin->shopname,
                    'shop_id' => $newitem->admin_id,
                ),
                'associatedModel' => $newitem,
            )
        );
    }

    public function scanCart($items, $user_id)
    {
        $flag = false;
        $text = '';

        // itemのidを取得
        $itemsNowCondition = Menu::whereIn('id', self::getCartMenusIds($items))->get();
        $admins = Admin::whereIn('id', self::getCartShopsIds($items))->get();

        $public_mismatch = self::getPublicMismatchItems($items, $itemsNowCondition, $user_id);
        $price_mismatch = self::getPriceMismatchMenus($items, $itemsNowCondition, $user_id);
        $beforeOpen = self::getBeforeOpenShops($items, $admins);
        if ($public_mismatch["flag"] == true) {
            $text .= self::buildPublicMismatchItemsText($public_mismatch["items"]);
        }
        if ($price_mismatch["flag"] == true) {
            $text .= self::buildPriceMismatchItemsText($price_mismatch["items"]);
        }
        if ($beforeOpen["flag"] == true) {
            $text .= self::buildBeforeOpenShopsText($beforeOpen["shops"]);
        }
        return [
            'price_mismatch' => $price_mismatch,
            'public_mismatch' => $public_mismatch,
            'beforeOpen' => $beforeOpen,
            'text' => $text,
        ];
    }

    public function getPriceMismatchMenus($items, Collection $itemsNowCondition, $user_id)
    {
        /** $price_mismatch_items
         *
         * 値段に変化のあるアイテム
         *
         * ・アイテムを通知して最新の情報でカート内を自動更新する
         * ・アイテム，新価格を保持し，ユーザに通知する
         */
        $price_mismatch_items = array();
        $flag = false;
        foreach ($items as $item) {
            foreach ($item as $i) {
                $nowItemCond = $itemsNowCondition->firstWhere('id', $i["id"]);
                if ($nowItemCond->value != $i["price"]) {
                    $flag = true;
                    array_push($price_mismatch_items, array($i, $nowItemCond));
                    self::deleteScanItem($i["id"], $user_id);//変更のあったアイテムを削除し
                    self::addUpdatedItem($nowItemCond, $i["id"], $i["quantity"]);//更新後のアイテムを追加
                }
            }
        }
        return [
            'items' => $price_mismatch_items,
            'flag' => $flag,
        ];
    }

    public function getPublicMismatchItems($items, Collection $itemsNowCondition, $user_id)
    {
        /** $public_mismatch_items
         *
         * 公開状態に変化のあるアイテム
         * ・アイテムを保持し，自動で削除しておく．ユーザに通知
         */
        $public_mismatch_items = array();
        $flag = false;
        foreach ($items as $item) {
            foreach ($item as $i) {
                $nowItemCond = $itemsNowCondition->firstWhere('id', $i["id"]);
                if ($nowItemCond->is_public != 1) {
                    $flag = true;
                    array_push($public_mismatch_items, $i);
                    self::deleteScanItem($i["id"], $user_id);//非公開アイテムをカートから削除
                }
            }
        }
        return [
            'items' => $public_mismatch_items,
            'flag' => $flag,
        ];
    }

    public function getBeforeOpenShops($grouped_items, Collection $admins)
    {
        $flag = false;
        $shops = array();
        if (isset($grouped_items)) {
            foreach ($grouped_items as $key => $grouped_item) {
                $admin = $admins->firstWhere('id', $key);
                if ($admin != null && $admin->isOpen() == false) {
                    $shops[$key] = $grouped_item;
                    $flag = true;
                }
            }
        }
        return [
            'shops' => $shops,
            'flag' => $flag,
        ];
    }

    public function cashRegister(User $user)
    {
        //変更のチェック
        $grouped_items = self::groupingItems(\Cart::session($user->id)->getContent());
        $conditions = self::scanCart($grouped_items, $user->id);

        //カート内の再更新
        $grouped_items = self::groupingItems(\Cart::session($user->id)->getContent());
        $invoice = self::calcCart($grouped_items);

        $area = self::checkAreaAdd($user->lat, $user->lng, $invoice["subtotal"]);

        return [
            'invoice' => $invoice,
            'conditions' => $conditions,
            'area' => $area,
        ];
    }

    public function checkAreaAdd($lat, $lng, $subtotal)
    {
        $areaAdd = 0;
        $areaFlag = false;
        $newarea_instance = new Polygon();
        $oldarea_instance = new Polygon();

        $isNewArea = $newarea_instance->isPointinPolygon(["lat" => $lat, "lng" => $lng], config('const.DeliveryArea'));
        $isOldArea = $oldarea_instance->isPointinPolygon(["lat" => $lat, "lng" => $lng], config('const.OldDeliveryArea'));

        if ($isNewArea == false) {//外側のエリアにも内側にも入っていない
            if ($isOldArea == false) {
                $areaAdd = 0;
                $areaFlag = false;
            }
        } else {//外側には入っているけど，内側には入っていない
            $areaFlag = true;
            if ($isOldArea == false) {
                if ($subtotal < 1500) {
                    $areaAdd = 300;
                } else {
                    $areaAdd = 0;
                }
            } else {
                $areaAdd = 0;
            }
        }

        return [
            'areaAdd' => $areaAdd,
            'areaFlag' => $areaFlag,
        ];
    }

    public function calcAllTotal($cart)
    {
        $allTotal = $cart["invoice"]["total_intax"]
            + $cart["invoice"]["shipping"]
            - $cart["invoice"]["nebiki"]
            + $cart["invoice"]["add"]
            + $cart["area"]["areaAdd"];

        if ($allTotal < 0) {
            $allTotal = 0;
        }

        return $allTotal;
    }

    public function buildPriceMismatchItemsText($price_mismatch_items)
    {
        $text = '';
        if (empty($price_mismatch_items) == false) {
            $text .= "値段が変わった商品\n";
            foreach ($price_mismatch_items as $p) {
                $text .= "店名　：" . $p[0]["attributes"]["shopname"] . "\n";
                $text .= "商品名：" . $p[0]["name"] . "\n";
                $text .= "価格　：" . $p[0]["price"] . "円 =>" . $p[1]->value . "円\n";
            }
            $text .= "\n\n";
        }
        return $text;
    }

    public function buildPublicMismatchItemsText($public_mismatch_items)
    {
        $text = '';
        if (empty($public_mismatch_items) == false) {
            $text .= "買えなくなった商品\n";
            foreach ($public_mismatch_items as $p) {
                $text .= "店名　：" . $p[0]["attributes"]["shopname"] . "\n";
                $text .= "商品名：" . $p[0]["name"] . "\n";
            }
            $text .= "\n\n";
        }
        return $text;
    }

    public function buildBeforeOpenShopsText($before_open_shops)
    {
        $text = '';
        if (empty($before_open_shops) == false) {
            $text = "開店前の店舗の商品が入っています。\n";
            foreach ($before_open_shops as $before_open_shop) {
                $text .= "店名　：" . $before_open_shop[0]["attributes"]["shopname"] . "\n";
                foreach ($before_open_shop as $b) {
                    $text .= "商品名：" . $b["name"] . "\n";
                }
                $text .= "\n\n";
            }
        }
        return $text;
    }

    public function getCartShopsIds($grouped_items)
    {
        $cartShops_ids = array();
        foreach ($grouped_items as $item) {
            foreach ($item as $i) {
                $cartShops_ids[] = $i["attributes"]["shop_id"];
            }
        }
        return $cartShops_ids;
    }

    public function calcCouponNebiki($refer_friend, $coupon, $user_id)
    {
        /**
         * クーポン処理
         * ・友達紹介は初回注文のときだけ
         * ・その他のクーポンはいつでもつかえる
         * ・０円以下になってもマイナス表記はしない.
         * ・０円が下減
         */
        $coupon_nebiki = 0;
        $text = "";
        $refer_friend_error_flag = false;
        $refer_friend_use_flag = false;
        $coupon_nebiki_error_flag = false;
        $coupon_nebiki_use_flag = false;
        //友達紹介クーポンが入っていればそれを使う
        if ($this->isFirstBuy == true) {
            if (isset($refer_friend)) {
                if (Coupon::checkExistUserReferFrined($refer_friend, $user_id) == true) {
                    $coupon_nebiki += $this->refer_friend_nebiki;
                    $refer_friend_use_flag = true;
                } else {
                    $text = '該当のクーポンコードは有効ではありません';
                    $refer_friend_error_flag = true;
                }
            }
        }

        if (isset($refer_friend) && $coupon != 0) {
            $text = '復数クーポンの使用はできません';
            $coupon_nebiki_error_flag = true;
        } else if (!isset($refer_friend) && $coupon != 0) {
            $coupon_nebiki += Coupon::CouponNebiki($coupon, $user_id);//所有中のクーポン
            $coupon_nebiki_use_flag = true;
        } else {
            $coupon_nebiki += Coupon::CouponNebiki($coupon, $user_id);//所有中のクーポン
        }
        return [
            'nebiki' => $coupon_nebiki,
            'refer_friend' => [
                'error_flag' => $refer_friend_error_flag,
                'use' => $refer_friend_use_flag,
            ],
            'coupon_nebiki' => [
                'error_flag' => $coupon_nebiki_error_flag,
                'use' => $coupon_nebiki_use_flag,
            ],
            'text' => $text,
        ];
    }

    public function buildOrderString($u, $reservation, $reservation_date, $reservation_hours, $remark, $pay, $cart, $Alltotal, $coupon_nebiki)
    {
        $builder = new OrderStringBuilder();
        $grouped_items = self::groupingItems(\Cart::session($u->id)->getContent());

        $builder->reservation_date($reservation == 1 ? $reservation_date : null, $reservation == 1 ? $reservation_hours : null)->br()
            ->reservation_hours($reservation == 1 ? $reservation_date : null, $reservation == 1 ? $reservation_hours : null)->br()
            ->customerline($u->line_name)
            ->customer($u->name)
            ->address($u->addr)
            ->phone($u->phone)->br();
        foreach ($grouped_items as $g) {
            $shopname = $g[0]->attributes->shopname;
            $builder->shop($shopname)->items($g)->br();;//複数のショップがあるから，それごとにまとめて表示させる．
        }
        $builder->remark($remark)
            ->subtotal($cart["invoice"]["subtotal"])
            ->tax($cart["invoice"]["tax"])
            ->shipping($cart["invoice"]["shipping"])
            ->nebiki($cart["invoice"]["nebiki"])
            ->coupon_nebiki($coupon_nebiki)
            ->add($cart["invoice"]["add"] + $cart["area"]["areaAdd"])
            ->total($Alltotal)->br()
            ->payment(config('const.User.Pay')[$pay])->br();

        return $builder->build();
    }

    public function checkStatus($admin_id)
    {
        if (empty(Admin::find($admin_id)->line_message_id)) {
            $status = config('const.Order.Status.Code.Manual');
        } else {
            $status = config('const.Order.Status.Code.Prepare');
        }
        return $status;
    }

    public function transCouponUsed($parent_tyumon, $refer_friend_use_flag, $coupon_nebiki_use_flag, $refer_friend, $coupon, $user_id){
        //友達紹介クーポン発行
        if ($refer_friend_use_flag == true) {
            // 2020/10/29 紹介された人に300円のクーポンを５枚発行
            Coupon::createFriendCoupon($refer_friend, $parent_tyumon, 300);
            Coupon::createFriendCoupon($refer_friend, $parent_tyumon, 300);
            Coupon::createFriendCoupon($refer_friend, $parent_tyumon, 300);
            Coupon::createFriendCoupon($refer_friend, $parent_tyumon, 300);
            Coupon::createFriendCoupon($refer_friend, $parent_tyumon, 300);

            //　紹介コードを入力した初回注文の人に4枚発行（３００円は先に割り引いてる）
            Coupon::createTyumonFriendCoupon($user_id, $parent_tyumon, 300);
            Coupon::createTyumonFriendCoupon($user_id, $parent_tyumon, 300);
            Coupon::createTyumonFriendCoupon($user_id, $parent_tyumon, 300);
            Coupon::createTyumonFriendCoupon($user_id, $parent_tyumon, 300);
        }
        //クーポン利用済み
        if ($coupon_nebiki_use_flag == true) {
            Coupon::Couponused($coupon);
        }
    }

}
