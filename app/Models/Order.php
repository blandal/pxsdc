<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Takeaways\Factory;
// use Illuminate\Support\Facades\DB;
use App\Models\OrderProduct;

class Order extends Model{
    use HasFactory;
    public $timestamps     = false;

    /**
     * 将平台传递的订单列表信息写入数据库,也就是采集入库
     */
    public static function saveOrder($data, Factory $oob){
        $insertIds  = $oob->saveOrders($data);
        dd($insertIds);
        return $oob->saveOrders($data);
    }

    public function setStatusAttribute($val){
        if($val == -1 && $this->status != $val){
            try {
                OrderProduct::where('order_id', $this->orderid)->update(['status' => -1]);
                // foreach(OrderProduct::where('order_id', $this->orderid)->get() as $item){
                //     $item->status   = -1;
                //     $item->save();
                // }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), 1);
            }
        }
        $this->attributes['status']     = $val;
    }
}
