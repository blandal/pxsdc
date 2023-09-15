<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ProductSku;
use App\Models\Store;
class IndexController extends Controller{
    public function stocks2z(Request $request){//将平台库存设置未0
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
}
