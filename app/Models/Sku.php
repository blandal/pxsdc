<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Store;
use App\Models\LogStock;
use App\Models\OrderProduct;
use Illuminate\Support\Facades\Log;

class Sku extends Model{
    use HasFactory;
    public $timestamps  = false;
    public function pro(){
        return $this->hasOne('App\Models\Pro', 'id', 'pro_id');
    }

    public function platforms(){
        return $this->hasOne('App\Models\platform', 'id', 'platform');
    }

    public function instance(){
        return Store::getInstance($this->store_id, $this->platform);
    }

    /**
     * 修改平台库存
     * @param $val      int     扣除的库存,比如sku数据库中有10个库存, $val值为2, 那么平台最后的库存为8
     * @param $remark   string  备注
     * @param $admin_id int     操作管理员id,0为自动同步
     * @param $type     int     更新方式, 0-仅更新当前平台, 1-仅更新其他平台, 2-所有平台都更新
     * @param $isval    bool    是否直接设置为 val 的值
     */
    public function changeStock(int $val, string $remark, int $admin_id, int $type, int $createtime = 0, bool $isval = false, $orderid = null){//需要记录日志
        if(strpos($this->bind, ',') !== false){//如果闪仓店铺接入，不能用这种方式判断
            // dd('修改的sku 绑定有问题!');
            return false;
        }
        $start      = microtime(true);
        $binds      = explode(',', $this->bind);
        $binds      = array_flip(array_flip($binds));
        $mystore    = $this->store_id;

        switch($type){
            case 0:
                $skus   = self::whereIn('id', [$this->id])->get();
            break;
            case 2:
                $tmpid      = $binds;
                $tmpid[]    = $this->id;
                $tmpid      = array_flip(array_flip($tmpid));
                $skus       = self::whereIn('id', $tmpid)->get();
            break;
            default:
                $tmpid      = array_flip($binds);
                if(isset($tmpid[$this->id])){
                    unset($tmpid[$this->id]);
                }
                $skus       = self::whereIn('id', array_keys($tmpid))->get();
        }
        $updatedStores      = [];
        $updatedPaltforms   = [];

        $insertSkuIds       = [];
        $content            = [];
        foreach($skus as $item){
            if($createtime > 0 && $item->stockupdate >= $createtime){
                Log::debug('订单时间小于库存更新时间,不做修改!' . $item->id);
                continue;
            }
            //同平台同店铺的不更新其他绑定的sku, 防止绑定sku出差导致库存多扣除
            if(isset($updatedStores[$item->store_id]) || isset($updatedPaltforms[$item->platform])){
                continue;
            }
            $obj    = $item->instance();

            if($item->platform == 1 && $isval == false){//饿了么出单后修改牵牛花的库存,则先查询牵牛花库存并更新为最新库存.防止牵牛花进货后库存减少
                $obj->getProductRow($item);
                $item   = Sku::find($item->id);
            }

            $origin         = $item->stocks;
            $setTo          = $isval === true ? $val : $origin - $val;
            if($setTo < 0){
                $setTo      = 0;
            }
            if(true === $obj->changeStock($setTo, $item)){
                $insertSkuIds[]     = $item->id;
                $content[]          = $item->id . ':' . $origin . '->' . $setTo;
                $item->stocks       = $setTo;
                $item->save();
            }else{
                Log::debug('库存同步失败!');
            }
            $updatedStores[$item->store_id]     = $item->store_id;
            $updatedPaltforms[$item->platform]  = $item->platform;
        }
        if(!empty($insertSkuIds)){
            $end      = microtime(true);
            LogStock::insert([
                'remark'    => $remark,
                'addtime'   => time(),
                'userid'    => $admin_id,
                'skuids'    => implode(',', $insertSkuIds),
                'content'   => implode(',', $content),
                'take_time' => ($end-$start)*1000,
                'order_id'  => $orderid,
            ]);
        }
        return true;
    }

    /**
     * 接收来自第三方平台的订单，并修改库存
     * 此接口仅下单减库存同步, 退单加库存在接口中直接调用
     * 要注意库存修改时间和订单下单时间
     * @param $platform_order_ids   第三方订单号数组
     */
    public static function updateFromPlatformOrders(array $platform_order_ids, $platform_id, $store_id){
        $orderProducts  = OrderProduct::whereIn('order_id', $platform_order_ids)->get();
        $opSkuIds       = $orderProducts->pluck('sku_table_id')->toArray();
        $skus           = self::whereIn('id', $opSkuIds)->get();
        $skusArr        = [];
        foreach($skus as $item){
            $skusArr[$item->id]     = $item;
        }
        foreach($orderProducts as $item){
            if(!isset($skusArr[$item->sku_table_id])){
                continue;
            }
            $skuRow     = $skusArr[$item->sku_table_id];
            $res        = $skuRow->changeStock($item->quantity, $platform_id . ':' . $store_id . ' - 订单自动同步.', 0, 1, $item->createtime, false, $item->order_id);
            if($res){
                $item->sync     = 1;
                $item->save();
            }
            if($skuRow->stockupdate >= $item->createtime){//如果skus表的库存更新时间比订单时间还大,则认为库存已经是最新，不受订单影响
                continue;
            }
            $skuRow->stocks   -= $item->quantity;
            if($skuRow->stocks < 0){
                $skuRow->stocks     = 0;
            }
            $skuRow->save();
        }
        OrderProduct::whereIn('order_id', $platform_order_ids)->update(['status' => 1]);
        return true;


        $skuids         = self::whereIn('id', $orderProducts->pluck('sku_table_id')->toArray())->get();
        $skuidToTime    = $skuids->pluck('stockupdate', 'id')->toArray();
        $skuids         = [];
        $opobjs         = [];
        foreach($orderProducts as $item){
            // if(!isset($skuidToTime[$item->sku_table_id]) || $skuidToTime[$item->sku_table_id] >= $item->createtime){//如果订单的创建时间比库存的更新时间小,那么不执行库存同步
            //     continue;
            // }
            if(!isset($skuids[$item->sku_table_id])){
                $skuids[$item->sku_table_id]    = $item->quantity;
            }else{
                $skuids[$item->sku_table_id]    += $item->quantity;
            }
            $opobjs[$item->sku_table_id][]      = $item;
        }
        $lists          = self::whereIn('id', array_keys($skuids))->where('platform', $platform_id)->where('store_id', $store_id)->get();
        foreach($lists as $item){
            $skuUpTime          = $skuidToTime[$item->id] ?? 0;

            if($skuUpTime)
            $item->stocks       = $item->stocks - $skuids[$item->id];
            if($item->stocks < 0){
                $item->stocks   = 0;
            }
            $item->save();

            if(!$item->bind) continue;
            if($item->changeStock($skuids[$item->id], $platform_id . ':' . $store_id . ' - 订单自动同步.', 0, 1)){
                foreach($opobjs[$item->id] as $vv){
                    $vv->sync       = 1;
                    $vv->save();
                }
            }
        }
        OrderProduct::whereIn('order_id', $platform_order_ids)->update(['status' => 1]);
        return true;
    }

    public function syncMe(){//同步此sku信息
        // if($this->platform == 2) return false;
        $this->instance()->getProductRow($this);
    }

    /**
     * 当库存改变时,更改其他平台绑定商品的库存
     */
    // public function setStocksAttribute($val){
    //     if($val != $this->stocks){
    //         set_time_limit(0);
    //         //找出其他平台同产品
    //         $binds  = explode(',', $this->bind);
    //         foreach($binds as $bindid){
    //             if(strpos($bindid, ',') !== false){
    //                 continue;
    //             }
    //             $needchange  = self::find($bindid);

    //             if($needchange){
    //                 $rsp    = $needchange->store->getInstances()->changeStock($val, $needchange);
    //                 if($needchange->other){
    //                     $other     = json_decode($needchange->other, true);
    //                     $other['quantity']  = $val;
    //                     $needchange->other  = json_encode($other, JSON_UNESCAPED_UNICODE);
    //                     $needchange->save();
    //                 }
    //                 if($rsp == true){
    //                     self::where('id', $bindid)->update(['stocks' => $val]);
    //                 }else{
    //                     throw new \Exception('更新失败!!!!', 1);
    //                 }
    //             }else{
    //                 throw new \Exception('更新失败!!!!', 1);
    //             }
    //         }
    //         $this->attributes['stocks']     = $val;
    //     }
    // }
    public static function autolink($pro_id = 0){
        set_time_limit(0);
        $allcount   = 0;
        $objs       = Pro::where('status', 1);
        if($pro_id >0){
            $objs   = $objs->where('id', $pro_id);
        }
        foreach($objs->get() as $item){
            $skus   = $item->skus->where('byhum', 0);
            $count  = count($skus);
            if($count == 2){//如果下面是单个sku,则直接绑定
                if($skus[0]->store_id == $skus[1]->store_id){
                    $item->err  = 1;
                    $item->save();
                    continue;
                }
                $skus[0]->bind    = self::bds($skus[0]->bind, $skus[1]->id);
                $skus[0]->save();
                $skus[1]->bind    = self::bds($skus[1]->bind, $skus[0]->id);
                $skus[1]->save();
                $allcount       += 2;
                if($item->err == 1){
                    $item->err  = 0;
                }
                if($item->upcerr == 1){
                    $item->upcerr   = 0;
                }
                if($item->upcrep == 1){
                        $item->upcrep   = 0;
                    }
                $item->save();
            }else{
                Sku::where('pro_id', $item['id'])->where('byhum', 0)->update(['bind' => null]);
                $platformStores     = [];
                foreach($skus as $val){
                    $platformStores[$val->platform . $val->store_id] = 1;
                }

                $plss       = count($platformStores) - 1;//平台店铺数量,每个sku正常情况下需要绑定此数量的其他sku
                $binding    = [];
                $itemErr    = false;
                $itemReap   = false;
                foreach($skus as $val){
                    if(isset($binding[$val->upc][$val->platform . $val->store_id])){
                        $itemReap    = true;//upc重复
                        continue;
                    }
                    $binding[$val->upc][$val->platform . $val->store_id]    = 1;
                    $binds  = [];
                    foreach($skus as $vbl){
                        if($val->upc == $vbl->upc && ($val->platform != $vbl->platform || $val->store_id != $vbl->store_id)){
                            $binds[]    = $vbl->id;
                        }
                    }
                    if($plss != count($binds)){
                        $itemErr    = true;
                    }
                    if(!empty($binds)){//即使sku未到应绑定数量,可以绑的先绑上
                        Sku::where('id', $val->id)->update(['bind' => implode(',', $binds)]);
                    }
                }
                $itemChange         = false;
                if($itemErr  == true){
                    if($item->upcerr == 0){
                        $item->upcerr   = 1;
                        $itemChange     = true;
                    }
                }elseif($item->upcerr == 1){
                    $item->upcerr   = 0;
                    $itemChange     = true;
                }
                if($itemReap == true){
                    if($item->upcrep == 0){
                        $item->upcrep   = 1;
                        $itemChange     = true;
                    }
                }elseif($item->upcrep == 1){
                    $item->upcrep   = 0;
                    $itemChange     = true;
                }
                if($itemReap == true){
                    Sku::where('pro_id', $item['id'])->where('byhum', 0)->update(['bind' => null]);//upc存在重复的情况较严重,坚决不能有任何绑定!
                }
                if($itemChange === true){
                    $item->save();
                }
            }
        }
        return true;
    }
    private static function bds($o, $a){
        $o      = $o ? explode(',', $o) : [];
        // $o      = [];
        $o[]    = $a;
        $o      = array_flip(array_flip($o));
        return implode(',', $o);
    }
}
