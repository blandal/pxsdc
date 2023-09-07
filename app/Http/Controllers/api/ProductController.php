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

class ProductController extends Controller{
    public function index(Request $request){
        $platform   = (int)$request->post('platform');
        $plt        = $this->checkPlatform($platform);
        if(!($plt instanceof Platform)){
            return $plt;
        }

        $list       = $request->post('list');
        $list       = json_decode($list, true);
        if(!$list || !is_array($list)){
            return $this->error('data 数据解析错误!');
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
    public function getindex(Request $request){
        $platform       = (int)$request->post('platform', 0);
        $storeid        = (int)$request->post('store_id', 0);
        $page           = (int)$request->post('page', 1);
        $pagesize       = (int)$request->post('limit', 10);

        if($platform < 1 || $storeid < 1){
            return $this->error('参数不完整!');
        }
        $row            = Store::where('store_id', $storeid)->where('platform_id', $platform)->first();
        if(!$row){
            return $this->error('不存在!');
        }
        if(!$row->cookie){
            return $this->error('商店未登录!');
        }
        if(!$row->platform || !$row->platform->object){
            return $this->error('平台方法未定义!');
        }

        $instance       = new $row->platform->object($row->cookie);
        set_time_limit(0);
        try {
            $res            = $instance->getProducts($page, $pagesize, $row->platform->id);
            if($res === true){
                return $this->success('成功!');
            }
            return $this->error($res);
        } catch (\Exception $e) {
            return $this->error('Exception: ' . $e->getMessage());   
        }
    }

    /**
     * 获取牵牛花订单列表
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


        $platform       = (int)$request->post('platform', 0);
        $storeid        = (int)$request->post('store_id', 0);
        $page           = (int)$request->post('page', 1);
        $pagesize       = (int)$request->post('limit', 10);

        if($platform < 1 || $storeid < 1){
            return $this->error('参数不完整!');
        }
        $row            = Store::where('store_id', $storeid)->where('platform_id', $platform)->first();
        if(!$row){
            return $this->error('不存在!');
        }
        if(!$row->cookie){
            return $this->error('商店未登录!');
        }
        if(!$row->platform || !$row->platform->object){
            return $this->error('平台方法未定义!');
        }

        $instance       = new $row->platform->object($row->cookie);
        $res            = $instance->getOrders($page, $pagesize);
        $data           = json_decode($res, true);
        if(!$data || !isset($data['code']) || $data['code'] != 0){
            return $this->error('订单数据解析失败!', $res);
        }

        if(!Order::saveOrder($data, $instance, $row->platform->id)){
            dd($instance->errs());
        }
    }

    public function orders(Request $request){
        set_time_limit(0);
        $platform   = (int)$request->post('platform');
        $plt        = $this->checkPlatform($platform);
        if(!($plt instanceof Platform)){
            return $plt;
        }

        $list       = $request->post('list');
        $list       = json_decode($list, true);
        if(!$list || !is_array($list)){
            return $this->error('data 数据解析错误!');
        }

        try{
            $oob    = new $plt->object();
            if(!$oob->saveOrders($list, $plt->id)){
                return $this->error(implode("<br>\r\n", $oob->errs()));
            }
        } catch (\Exception $e) {
            dd($e);
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
}
