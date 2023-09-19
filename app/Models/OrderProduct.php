<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Store;
use App\Models\ProductSku;
use App\Takeaways\Factory;
use App\Models\Sku;
use Illuminate\Support\Facades\Log;

class OrderProduct extends Model{
    use HasFactory;
    private $instances      = [];
    public $timestamps      = false;
    public $proSameRule     = 3;//不同平台同商品判断条件,1-使用upc, 2-使用customer_sku_id(自定义sku), 3-名称

    public function skurow(){
        return $this->hasOne(Sku::class, 'id', 'sku_table_id');
    }

    public static function getSkuByName($title, $spce = null){
        return ProductSku::where('title', $title)->where('spec', $spce)->get();
    }
    public static function getSkuByCustomer($customer_sku_id){
        return ProductSku::where('customSkuId', $customer_sku_id)->get();
    }
    public static function getSkuDefault(OrderProduct $op){
        return ProductSku::where('upc', $op->upc)->get();
    }

    /**
     * @param $type         -1-增加库存,也就是退单的时候的状态, 1-扣除库存. 简单理解就是退单了, 给 -(杆) 回来
     */
    public function syncPros(int $type){
        switch($this->proSameRule){
            case 3:
                $pros       = self::getSkuByName($this->title, $this->spec);
                break;
            case 2:
                if($this->upc){
                    $pros   = self::getSkuByCustomer($this->upc);
                }
                break;
            default:
                $pros       = self::getSkuDefault($this);
        }

        if(count($pros) < 1){
            throw new \Exception('查找商品失败,库中可能没有这个商品', 1);
        }

        foreach($pros as $item){//需要判断是否是自身平台的商品,和订单同平台的商品会自己更新库存，无需此次更新
            $item->stocks   -= $type * $this->quantity;
            $item->save();
        }
        return true;
    }

    // public function setStatusAttribute($val){
    //     if($val == -1 && $this->sync == 1){
    //         $this->syncPros(-1);
    //         $this->attributes['sync']   = 1;
    //     }
    //     dd('opopoppp');
    //     $this->attributes['status']     = $val;
    // }

    public function rebackStocks(){
        if($this->status == -1){//已经执行过退单,不继续执行
            return true;
        }

        if($this->sync == 0){//该订单并未执行库存同步,不继续执行
            return true;
        }
        $sku            = $this->skurow;
        if(!$sku) return true;

        $sku->stocks    += $this->quantity;
        $bind           = $sku->bind;
        if(!$bind){
            return true;
        }
        if($sku->changeStock($this->quantity*-1, $sku->platform . ':' . $sku->store_id . '['.$this->order_id.']' . ' - 订单取消退回库存.', 0, 1) == true){
            $this->status   = -1;
            $sku->save();
            Log::info('库存回退成功! [order_products] 表的id为: ' . $this->id);
            return $this->save();
        }
        Log::error('库存回退失败! [order_products] 表的id为: ' . $this->id);
        return false;
    }
}
