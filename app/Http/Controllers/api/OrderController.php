<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\ProductSku;

class OrderController extends Controller{
    public function fmtdata($data){
        if(is_array($data)){
            foreach($data as $k => &$item){
                if(is_array($item)){
                    if(is_array(array_values($item)[0])){
                        $this->fmtdata($item);
                    }
                }
                $item   = json_encode($item, JSON_UNESCAPED_UNICODE);
            }
            $data   = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
    }
    public function orders(Request $request){
        set_time_limit(0);
        $platform   = (int)$request->post('platform');
        $storeid    = (int)$request->post('store_id');

        // try {
        //     $instance       = Store::getInstance($storeid, $platform);

        //     $list           = $request->post('list');
        //     $list           = json_decode($list, true);
        //     if(!$list || !is_array($list)){
        //         return $this->error('data 数据解析错误!');
        //     }
        //     if(!$instance->saveOrders($list)){
        //         return $this->error(implode("<br>\r\n", $instance->errs()));
        //     }
        //     $last   = Order::select('orderid', 'store_id', 'platform_id', 'status')->where('platform_id', $platform)->where('store_id', $storeid)->orderByDesc('id')->first();
        //     return $this->success($last, '成功!');
        // } catch (\Exception $e) {
        //     return $this->error($e->getMessage());
        // }


        // // $plt        = $this->checkPlatform($platform);
        // $plt        = Store::getInstance($storeid, $platform);
        // if(!($plt instanceof Platform)){
        //     return $plt;
        // }

        $list       = $request->post('list');
        $list       = is_string($list) ? json_decode($list, true) : $list;
        if(!$list || !is_array($list)){
            return $this->error('data 数据解析错误!');
        }

        // try {
            $instance   = Store::getInstance($storeid, $platform);//where('store_id', $storeid)->where('platform_id', $platform)->first();
            $newOps     = $instance->saveOrders($list);
            if(empty($newOps)){
                $errs   = $instance->errs() ? implode("<br>\r\n", $instance->errs()) : '添加为空!';
                return $this->error($errs);
            }
            ProductSku::newOrderForChangeStocks($newOps);
            return $this->success(null, '成功!');
        // } catch (\Exception $e) {
        //     return $this->error($e->getMessage());
        // }
    }
}
