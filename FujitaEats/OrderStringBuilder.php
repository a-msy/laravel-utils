<?php

namespace App\Utils;

use App\Models\Order;

class OrderStringBuilder
{
    private $order;
    public function __construct()
    {
        $this->order = "";
    }

    public function orderlist($orderlist)
    {
        $this->order = $this->order . "▼受け取り時間順▼\n";
        foreach ($orderlist as $key => $delivery) {
            $this->order = $this->order . $delivery['time'] . "  " . $delivery['from'] . " -> " . $delivery['to'] . "（" . $delivery['repeat'] . "回目）" . "\n";
        }
        $this->order = $this->order . "▲▲▲▲▲▲▲▲▲\n\n";
        return $this;
    }


    public function reservation_date($reservation_date, $reservation_hours)
    {
        if (!is_null($reservation_date)) {
            $this->order = "▼▼▼▼▼▼▼▼▼▼▼▼\n▼▼▼▼▼▼▼▼▼▼▼▼\n▼▼▼▼▼▼▼▼▼▼▼▼\nこちらは予約注文です。\nご注意下さい。\n"
                . $this->order . "配達日 : " . $reservation_date;
        } else if (!is_null($reservation_hours)) {
            $this->order = $this->order . "配達日 : " . '指定なし' . "\n";

        }
        return $this;
    }

    public function reservation_hours($reservation_date, $reservation_hours)
    {
        if (!is_null($reservation_hours)) {
            $this->order = $this->order . "配達時間 : " . $reservation_hours . "\n▲▲▲▲▲▲▲▲▲▲▲▲\n▲▲▲▲▲▲▲▲▲▲▲▲\n▲▲▲▲▲▲▲▲▲▲▲▲\n";
        } else if (!is_null($reservation_date)) {
            $this->order = $this->order . "配達時間 : " . '指定なし' . "\n";

        }
        return $this;
    }

    public function orderId($id)
    {
        $this->order = $this->order . "注文ID : " . $id . "\n";
        return $this;
    }

    public function customer($name)
    {
        $this->order = $this->order . "お客様名 : " . $name . "様\n";
        return $this;
    }

    public function customerline($name)
    {
        $this->order = $this->order . "LINE名 : " . $name . "様\n";
        return $this;
    }

    public function address($address)
    {
        $this->order = $this->order . "配送先 : " . $address . "\n";
        return $this;
    }

    public function phone($phone)
    {
        $this->order = $this->order . "電話番号 : " . $phone . "\n";
        return $this;
    }

    public function shop($shop)
    {
        $this->order = $this->order . "店名 : " . $shop . "\n";
        return $this;
    }

    public function items($items)
    {
        foreach ($items as $item) {
            $this->order = $this->order . $item["name"] . " " . $item["price"] . "円　【　" . $item["quantity"] . "個　】　(税抜" . $item["price"] * $item["quantity"] . "円)\n";
        }
        return $this;
    }

    public function orders($orders)
    {
        foreach ($orders as $o) {
            $this->order = $this->order . $o->menu->menu . " " . $o->menu_price . "円　【　" . $o->quantity . "個　】\n";
        }
        return $this;
    }

    public function shipping($shipping)
    {
        $this->order = $this->order . "配送料 : " . $shipping . "円\n";
        return $this;
    }

    public function nebiki($nebiki)
    {
        $this->order = $this->order . "配送料値引き : ▲" . $nebiki . "円\n";
        return $this;
    }

    public function coupon_nebiki($nebiki)
    {
        $this->order = $this->order . "クーポン値引き : ▲" . $nebiki . "円\n";
        return $this;
    }

    public function add($add)
    {
        $this->order = $this->order . "サービス料 : " . $add . "円\n";
        return $this;
    }

    public function total($total)
    {
        $this->order = $this->order . "合計金額 : " . $total . "円\n";
        return $this;
    }

    public function subtotal($subtotal)
    {
        $this->order = $this->order . "小計金額 : " . $subtotal . "円\n";
        return $this;
    }

    public function taxTotal($taxTotal)
    {
        $this->order = $this->order . "税込金額 : " . $taxTotal . "円\n";
        return $this;
    }

    public function tax($tax)
    {
        $this->order = $this->order . "消費税 : " . $tax . "円\n";
        return $this;
    }

    public function payment($payment)
    {
        $this->order = $this->order . "支払い方法 : " . $payment . "\n";
        return $this;
    }

    public function remark($remark)
    {
        $this->order = $this->order . "====== 備考 ======\n\n" . $remark . "\n\n================\n\n";
        return $this;
    }

    public function br()
    {
        $this->order = $this->order . "\n";
        return $this;
    }

    public function build()
    {
        return $this->order;
    }
}
