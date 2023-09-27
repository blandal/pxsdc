<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Takeaways\Meituan;
use App\Models\Platform;
use App\Models\Store;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\Product;
use App\Models\ProductSkuBind;
use App\Models\Sku;
use App\Models\Pro;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller{
    public function index(Request $request){//通过api上传数据同步
        $platform   = (int)$request->post('platform');
        $store      = (int)$request->post('store_id');

        $plt        = Store::getInstance($store, $platform);

        $list       = $request->post('list');
        $list       = json_decode($list, true);
        if(!$list || !is_array($list)){
            return $this->error('data 数据解析错误!');
        }
        $plt->saveProducts($list);
        return $this->success();



        $plt        = $this->checkPlatform($platform);
        if(!($plt instanceof Platform)){
            return $plt;
        }


        try{
            $oob    = new $plt->object();
            if($oob->saveProducts($list, $plt->id) !== true){
                return $this->error('失败');
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
        return $this->success('', '成功!');
    }

    /**
     * 获取商品列表
     */
    public function getindex(Request $request){//批量拉取平台同步
        $platform       = (int)$request->post('platform', 0);
        $storeid        = (int)$request->post('store_id', 0);
        $page           = (int)$request->post('page', 1);
        $pagesize       = (int)$request->post('limit', 20);
        $maxpage        = (int)$request->post('maxpage', 0);
        $title          = trim($request->post('title', ''));

        $instance       = Store::getInstance($storeid, $platform);//new $row->platform->object($row, $row->cookie);
        set_time_limit(0);
        $allsku         = [];
        while(true){
            try {
                $skuids     = $instance->getProducts($page, $pagesize, $title);
                if($platform == 1){
                    if(!is_array($skuids)){
                        return $this->error($skuids);
                    }
                    $nums   = count($skuids);
                    $allsku = array_merge($allsku, $skuids);
                }else{
                    if(!is_numeric($skuids)){
                        return $this->error($skuids);
                    }
                    $nums   = $skuids;
                }

                if(!$nums || $nums < $pagesize){
                    //删除牵牛花上删除的商品
                    if($platform == 1 && !empty($allsku)){
                        Pro::whereRaw('1=1')->update(['status' => 0]);
                        Pro::leftJoin('skus', 'skus.pro_id', '=', 'pros.id')->whereIn('skus.sku_id', $allsku)->where('skus.platform', 1)->update(['pros.status' => 1]);
                    }
                    return $this->success('商品自动同步结束!');
                }
            } catch (\Exception $e) {
                Log::error($platform . ' - ' . $storeid . ' 同步商品错误!' . $e->getMessage());
                return $this->error($e->getMessage());
            }
            $page++;
        }
    }

    /**
     * 获取订单列表
     * @param platform  平台id,内部维护(platforms)
     * @param storeid   第三方店铺id
     * @param page      页码
     * @param pagesize  页面大小
     */
    public function getorders(Request $request){
        return $this->error('此功能暂时不开发!');
        $platform       = (int)$request->get('platform', 0);
        $storeid        = (int)$request->get('store_id', 0);
        $page           = (int)$request->get('page', 1);
        $pagesize       = (int)$request->get('limit', 20);

        if($platform < 1 || $storeid < 1){
            return $this->error('参数不完整!' . $platform . $storeid);
        }

        // try {
            $instance       = Store::getInstance($storeid, $platform);
            $res            = $instance->getOrders($page, $pagesize);
            $data           = json_decode($res, true);
            if(!$data){
                return $this->error('订单数据解析失败!', $res);
            }
            if(!Order::saveOrder($data, $instance)){
                // dd($instance->errs());
                return $this->error($instance->errs());
            }else{
                $last   = Order::select('orderid', 'store_id', 'platform_id', 'status')->where('platform_id', $platform)->where('store_id', $storeid)->orderByDesc('id')->first();
                return $this->success($last, '成功!');
            }
    }

    private function checkPlatform(int $platform){
        if($platform < 1){
            return $this->error('请传入正确的平台id');
        }
        $plt        = Platform::find($platform);
        if(!$plt){
            return $this->error('平台不存在!');
        }
        if(!$plt->object){
            return $this->error('此平台为定义!');
        }
        return $plt;
    }

    public function autolink(){//临时解决方案.将不同平台的相同 sku 进行绑定关联
        Sku::autolink();
        // return $this->error('功能暂未开放!');
        // set_time_limit(0);
        // $allcount   = 0;
        // foreach(Pro::where('status', 1)->get() as $item){
        //     $skus   = $item->skus->where('byhum', 0);
        //     $count  = count($skus);
        //     if($count == 2){//如果下面是单个sku,则直接绑定
        //         if($skus[0]->store_id == $skus[1]->store_id){
        //             $item->err  = 1;
        //             $item->save();
        //             continue;
        //         }
        //         $skus[0]->bind    = $this->bds($skus[0]->bind, $skus[1]->id);
        //         $skus[0]->save();
        //         $skus[1]->bind    = $this->bds($skus[1]->bind, $skus[0]->id);
        //         $skus[1]->save();
        //         $allcount       += 2;
        //         if($item->err == 1){
        //             $item->err  = 0;
        //             $item->save();
        //         }
        //     }else{
        //         Sku::where('pro_id', $item['id'])->where('byhum', 0)->update(['bind' => null]);
        //         $platformStores     = [];
        //         foreach($skus as $val){
        //             $platformStores[$val->platform . $val->store_id] = 1;
        //         }

        //         $plss       = count($platformStores) - 1;//平台店铺数量,每个sku正常情况下需要绑定此数量的其他sku
        //         $binding    = [];
        //         $itemErr    = false;
        //         $itemReap   = false;
        //         foreach($skus as $val){
        //             if(isset($binding[$val->upc][$val->platform . $val->store_id])){
        //                 $itemReap    = true;//upc重复
        //                 continue;
        //             }
        //             $binding[$val->upc][$val->platform . $val->store_id]    = 1;
        //             $binds  = [];
        //             foreach($skus as $vbl){
        //                 if($val->upc == $vbl->upc && ($val->platform != $vbl->platform || $val->store_id != $vbl->store_id)){
        //                     $binds[]    = $vbl->id;
        //                 }
        //             }
        //             if($plss != count($binds)){
        //                 $itemErr    = true;
        //             }
        //             if(!empty($binds)){//即使sku未到应绑定数量,可以绑的先绑上
        //                 Sku::where('id', $val->id)->update(['bind' => implode(',', $binds)]);
        //             }
        //         }
        //         $itemChange         = false;
        //         if($itemErr  == true){
        //             if($item->err == 0){
        //                 $item->upcerr   = 1;
        //                 $itemChange     = true;
        //             }
        //         }elseif($item->err == 1){
        //             $item->upcerr   = 0;
        //             $itemChange     = true;
        //         }
        //         if($itemReap == true){
        //             if($item->upcrep == 0){
        //                 $item->upcrep   = 1;
        //                 $itemChange     = true;
        //             }
        //         }elseif($item->upcrep == 1){
        //             $item->upcrep   = 0;
        //             $itemChange     = true;
        //         }
        //         if($itemReap == true){
        //             Sku::where('pro_id', $item['id'])->where('byhum', 0)->update(['bind' => null]);//upc存在重复的情况较严重,坚决不能有任何绑定!
        //         }
        //         if($itemChange === true){
        //             $item->save();
        //         }
        //     }
        // }
        return $this->success('绑定完成!');
    }

    public function upccheck(){//检查upc是否错误
        set_time_limit(0);
        $allcount   = 0;
        $pltsCount  = 2;//店铺数量
        foreach(Pro::get() as $item){
            $skus   = $item->skus;
            $upcs   = [];
            $iserr  = false;
            $plts       = $skus->pluck('store_id', 'store_id')->toArray();
            if($plts < $pltsCount){
                $item->upcerr   = 1;
                $item->save();
                continue;
            }
            $upcArr     = $skus->pluck('upc', 'upc')->toArray();
            if(in_array(null, $upcArr)){
                $item->upcerr   = 1;
                $item->save();
                continue;
            }
            if($item->upcerr == 1){
                $item->upcerr   = 0;
                $item->save();
            }
        }
        return $this->success();
    }

    /**
     * 同步美团的库存到饿了么
     */
    public function syncMt2Elm(Request $request){
        $list   = [];
        foreach(Sku::get() as $item){
            $list[$item->id]    = $item;
        }
        foreach($list as $id => $item){
            if($item->platform == 1 && $item->bind && isset($list[$item->bind]) && $list[$item->bind]->platform==2){//如果找到相互绑定的sku,则继续
                $eleme  = $list[$item->bind];
                if($item->stocks != $eleme->stocks){//如果库存不一致则更新
                    $eleme->changeStock($item->stocks, '美团同步到饿了么', 0, 0, 0, true);
                }
            }
        }
    }
}
