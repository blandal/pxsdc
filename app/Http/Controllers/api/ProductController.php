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
        // dd(ProductSku::bind());
        // $row    = ProductSku::find(9998);
        // $row->stocks    = 2;
        // $row->save();
        // dd('----');

        $platform       = (int)$request->post('platform', 0);
        $storeid        = (int)$request->post('store_id', 0);
        $page           = (int)$request->post('page', 1);
        $pagesize       = (int)$request->post('limit', 20);
        $maxpage        = (int)$request->post('maxpage', 20);

        // if($platform < 1 || $storeid < 1){
        //     return $this->error('参数不完整!');
        // }
        // $row            = Store::where('store_id', $storeid)->where('platform_id', $platform)->first();
        // if(!$row){
        //     return $this->error('不存在!');
        // }
        // if(!$row->cookie){
        //     return $this->error('商店未登录!');
        // }
        // if(!$row->platform || !$row->platform->object){
        //     return $this->error('平台方法未定义!');
        // }

        $instance       = Store::getInstance($storeid, $platform);//new $row->platform->object($row, $row->cookie);
        set_time_limit(0);
        // try {
            for(;$page <= $maxpage; $page++){
                $res            = $instance->getProducts($page, $pagesize);
            }
            if($res === true){
                return $this->success('成功!');
            }
            return $this->error($res);
        // } catch (\Exception $e) {
        //     return $this->error('Exception: ' . $e->getMessage());   
        // }
    }

    /**
     * 获取订单列表
     * @param platform  平台id,内部维护(platforms)
     * @param storeid   第三方店铺id
     * @param page      页码
     * @param pagesize  页面大小
     */
    public function getorders(Request $request){
        // $od     = Order::find(7);
        // $od->status = -1;
        // try {
        //     $od->save();
        // } catch (\Exception $e) {
        //     dd($e->getMessage());
        // }
        // $od->save();
        // dd('1212122');


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
        // } catch (\Exception $e) {
        //     return $this->error($e->getMessage());
        // }
        // $row            = Store::where('store_id', $storeid)->where('platform_id', $platform)->first();
        // if(!$row){
        //     return $this->error('不存在!');
        // }
        // if(!$row->cookie){
        //     return $this->error('商店未登录!');
        // }
        // if(!$row->platform || !$row->platform->object){
        //     return $this->error('平台方法未定义!');
        // }

        // $instance       = new $row->platform->object($row);
        // $res            = $instance->getOrders($page, $pagesize);
        // $data           = json_decode($res, true);
        // if(!$data || !isset($data['code']) || $data['code'] != 0){
        //     return $this->error('订单数据解析失败!', $res);
        // }

        // if(!Order::saveOrder($data, $instance, $row->platform->id)){
        //     dd($instance->errs());
        // }
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

    public function test(Request $request){
        print_r($request->header('X-Ele-Eb-Token'));
        echo "<br>\r\n";
        print_r($request->get('appKey'));
    }

    public function elesign(Request $request){
        $timestamps     = $request->get('t');
        $data           = json_decode($request->get('data'), true);
        return view('elesign', ['ts' =>$timestamps, 'data' => $data]);
    }

    public function autolink(){//临时解决方案
        set_time_limit(0);
        $allcount   = 0;
        foreach(Pro::get() as $item){
            $skus   = $item->skus->where('byhum', 0);
            $count  = count($skus);
            if($count == 2){//如果下面是单个sku,则直接绑定
                if($skus[0]->store_id == $skus[1]->store_id){
                    $item->err  = 1;
                    $item->save();
                    continue;
                }
                $skus[0]->bind    = $this->bds($skus[0]->bind, $skus[1]->id);
                $skus[0]->save();
                $skus[1]->bind    = $this->bds($skus[1]->bind, $skus[0]->id);
                $skus[1]->save();
                $allcount       += 2;
            }else{
                $allbind        = true;
                foreach($skus as $val){
                    $binded     = false;
                    foreach($skus as $res){
                        if($val->name == $res->name && $val->store_id != $res->store_id){
                            $val->bind  = $this->bds( $val->bind, $res->id);
                            $val->save();
                            $binded     = true;
                            $allcount++;
                        }
                    }
                    if($binded == false){
                        $allbind    = false;
                    }
                }
                if($allbind == false){
                    $item->err  = 1;
                    $item->save();
                }
            }
            // dd($item->skus);
        }
        return $this->success('绑定完成!' . $allcount);
        dd('----');



        $res    = ProductSku::select('upc', 'id', 'product_id', 'platform_id')->whereRaw('upc is not null')->get()->toArray();
        $upcs   = [];
        // $skuids     = array_column($res, 'id');

        // $binded    = ProductSkuBind::whereIn('sku_table_id', $skuids)->pluck('product_id', 'sku_table_id')->toArray();
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
                    ProductSku::where('upc', (string)$upc)->update(['product_id' => $min]);
                }



                $skuids         = array_flip(array_column($item, 'id'));
                foreach($item as $zv){
                    $tmp        = $skuids;
                    unset($tmp[$zv['id']]);
                    $r          = ProductSku::find($zv['id']);
                    $r->bind    = implode(',', array_keys($tmp));
                    $r->save();
                }
            }
        }
        dd($upcs);
    }
    private function bds($o, $a){
        $o      = $o ? explode(',', $o) : [];
        $o[]    = $a;
        $o      = array_flip(array_flip($o));
        return implode(',', $o);
    }

    public function upccheck(){
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
            // dd(in_array(null, $upcArr), $upcArr);
            if(in_array(null, $upcArr)){
                $item->upcerr   = 1;
                $item->save();
                continue;
            }
            $storeUpc   = $skus->pluck('upc', 'store_id')->toArray();
            // dd($storeUpc);
            
            // foreach($skus as $val){
            //     if(!$val->upc){
            //         $iserr  = true;
            //         break;   
            //     }
            //     $upcs[$val->store_id][$val->upc]    = $val;
            // }
            // if($iserr == true){
            //     $item->upcerr   = 1;
            //     $item->save();
            //     continue;
            // }
        }
        return $this->success();
    }
}
