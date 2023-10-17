<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ProductSku;
use App\Models\Store;
use App\Models\Sku;
class IndexController extends Controller{
    public function stocks2z(Request $request){//将平台库存设置未0
        return $this->error('功能暂未开放!');
        $platform   = $request->get('platform');
        $store      = $request->get('store');
        $page       = (int)$request->get('page', 1);
        $limit      = (int)$request->get('limit', 30);
        $page       = $page < 1 ? 1 : $page;
        $limit      = $limit < 1 ? 10 : $limit;

        $platform   = 2;
        $store      = 1097214140;

        $storeObj   = Store::getInstance($store, $platform);
        $list       = ProductSku::where('platform_id', $platform)
                            ->where('storeId', $store)
                            ->where('stocks', '>', 0)
                            ->whereRaw('(bind is null or bind = "")')->offset(($page-1) * $limit)->limit($limit)->get();
        $ids        = $list->pluck('id');
        // dd($ids, route('api.stocks2z', ['platform' => $platform, 'store' => $store, 'page' => ++$page, 'limit' => $limit]));
        ProductSku::whereIn('id', $ids)->update(['stocks' => 0]);
        set_time_limit(0);
        foreach($list as $item){
            $storeObj->changeStock(0 ,$item);
            sleep(rand(1,3));
        }
        if(count($ids) == $limit){
            header('location: ' . route('api.stocks2z', ['platform' => $platform, 'store' => $store, 'page' => ++$page, 'limit' => $limit]));
            return;
            // return redirect()->route('api.stocks2z', ['platform' => $platform, 'store' => $store, 'page' => ++$page, 'limit' => $limit]);
        }else{
            return $this->success('成功修改: ' . count($ids) . ' 个!');
        }
    }

    /**
     * 更新店铺cookie
     */
    public function cookie(Request $request){
        $platform   = $request->post('platform');
        $store      = $request->post('store_id');
        $cookie     = $request->post('cookie');

        if($platform == 3){
            file_put_contents(storage_path('app/meituan.cookie'), $cookie);
            return $this->success('美团闪仓cookie更新成功!');
        }

        $s          = Store::where(['platform_id' => $platform, 'store_id' => $store])->first();
        if(!$s){
            return $this->error('店铺不存在!');
        }
        $s->cookie  = $cookie;
        if($s->save()){
            return $this->success('cookie更新成功!');
        }
        return $this->error('cookie 更新失败!');
    }

    /**
     * 更新美团商品库位码
     */
    public function banknumber(Request $request){
        $pagesize       = 50;
        $page           = 1;
        $storeid        = 1046462;
        $platform       = 1;

        $store          = Store::where(['platform_id' => $platform,'store_id' => $storeid])->first();
        if(!$store){
            return $this->error('店铺不存在!');
        }
        $instance       = $store->getInstances();

        $nums           = 0;
        $hads           = 0;
        while (true) {
            $str    = $instance->banknumber($page++);
            $data   = json_decode($str, true);
            if(!isset($data['code'])){
                return $this->error($str);
            }
            if($data['code'] != 0){
                return $this->error($data['msg']);
            }
            $data       = $data['data']['list'];
            $skus       = [];
            foreach(Sku::whereIn('sku_id', array_column($data, 'skuId'))->where('store_id', $storeid)->where('platform', $platform)->get() as $item){
                $skus[$item->sku_id]    = $item;
            }
            foreach($data as $item){
                if(isset($skus[$item['skuId']])){
                    $skus[$item['skuId']]->kuweima  = implode(',', array_column($item['storeLocationInfoList'], 'locationCode'));
                    $skus[$item['skuId']]->save();
                    $nums++;
                }
            }

            $length         = count($data);
            $hads           += $length;
            if($length < $pagesize){
                break;
            }
        }
        return $this->success(['updated' => $nums, 'origins' => $hads], '库位码更新成功! ' . $nums . '/' . $hads);
    }
}