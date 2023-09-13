<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Store;
use App\Models\Platform;
use App\Takeaways\Factory;
class ProductSku extends Model{
    use HasFactory;
    public $timestamps      = false;
    private $pltobjs        = [];

    public function store(){
        return $this->hasOne('App\Models\Store', 'store_id', 'storeId')->where('platform_id', $this->platform_id);
    }

    public static function newOrderForChangeStocks(array $order_product_ids){
        // dd($order_product_ids);
        foreach(self::whereIn('id', array_keys($order_product_ids))->get() as $item){
            $item->stocks   -= $order_product_ids[$item->id];
            if($item->stocks < 0){
                $item->stocks   = 0;
            }
            $item->save();
            dd($item->toArray());
        }
    }

    public function setStocksAttribute($val){
        if($val != $this->stocks){
            set_time_limit(0);
            //找出其他平台同产品
            $binds  = explode(',', $this->bind);
            foreach($binds as $bindid){
                $needchange  = self::find($bindid);

                if($needchange && $needchange->store->getInstances()->changeStock($val, $needchange) == true){
                    self::where('id', $bindid)->update(['stocks' => $val]);
                }else{
                    dd($needchange);
                    throw new \Exception('更新失败!!!!', 1);
                }
            }

            // if(!isset($this->pltobjs[$this->platform_id][$this->storeId])){
            //     $tmp    = Store::getInstance($this->storeId, $this->platform_id);
            //     if(!($tmp instanceof Factory)){
            //         throw new \Exception('平台sdk实例化失败!', 1);
            //     }
            //     $this->pltobjs[$this->platform_id][$this->storeId]  = $tmp;
            // }

            // $issuccess   = $this->pltobjs[$this->platform_id][$this->storeId]->changeStock($val, $this);
            // $cont  = json_decode($str, true);
            // if(!$cont){
            //     throw new \Exception('返回失败: ' . $str, 1);
            // }elseif(!isset($cont['code'])){
            //     throw new \Exception('返回格式错误: ' . $str, 1);
            // }elseif($cont['code'] != 0){
            //     $msg    = $cont['msg'] ?? '!!请求失败!';
            //     throw new \Exception($msg, 1);
            // }
            // if($issuccess !== true){
            //     throw new \Exception($issuccess, 1);
            // }
            $this->attributes['stocks']     = $val;
        }
    }

    /**
     * 通过upc查找相同的商品
     */
    public static function sameByUpc($upc){
        if(is_array($upc)){
            return self::whereIn('upc', $upc)->get();
        }
        return self::where('upc', $upc)->get();
    }

    /**
     * 根据商品名称和属性名称查找商品
     * @param $name 参数需使用商品标题和规格名称的拼接
     */
    public static function sameByName($title, $guige){
        return self::where('title', $title)->where('spec', $guige)->get();
    }

    /**
     * 自动绑定upc
     */
    public static function bind(){
        set_time_limit(0);
        $res    = self::select('upc', 'id', 'product_id', 'platform_id', 'storeId', 'byhum')->whereRaw('upc is not null')->get()->toArray();
        $upcs   = [];
        foreach($res as $item){
            $upcs[$item['upc']][]     = $item;
        }
        $binds      = [];
        foreach($upcs as $upc => $item){
            if(count($item) > 1){
                $product_ids    = array_column($item, 'product_id');
                $tmp            = array_flip($product_ids);
                if(count($tmp) > 1){
                    $min        = min($product_ids);
                    self::where('upc', (string)$upc)->update(['product_id' => $min]);
                }


                foreach($item as $zv){
                    $zvBindIds  = [];
                    foreach($item as $val){
                        if($val['id'] == $zv['id']){
                            continue;
                        }
                        if($zv['byhum'] == 1){
                            continue;
                        }
                        if($val['storeId'] != $zv['storeId']){
                            $zvBindIds[]    = $val['id'];
                        }
                    }
                    if(!empty($zvBindIds)){
                        $r          = self::find($zv['id']);
                        $r->bind    = implode(',', $zvBindIds);
                        $r->save();
                    }
                }
            }
        }
        return true;
    }
}
