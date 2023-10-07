<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\ProductSku;
use App\Models\Sku;

use GuzzleHttp\Client;
use App\Models\Order;

class OrderController extends Controller{

    //闪电仓参数
    private $poiid      = 18463790;
    private $sccookie   = '_lxsdk_cuid=18a5dae5e60c8-002c984d7d55a2-26031f51-1fa400-18a5dae5e60c8; _lxsdk=18a5dae5e60c8-002c984d7d55a2-26031f51-1fa400-18a5dae5e60c8; uuid=6394452bd591b7090f6e.1693788822.1.0.0; WEBDFPID=2u4x8uvv5663567y13904zyu060w9yu181z4wzv391y97958wu93z3zv-2009148825058-1693788824367WGWAMCKfd79fef3d01d5e9aadc18ccd4d0c95079724; _ga=GA1.1.373643657.1694075781; _ga_95GX0SH5GM=GS1.1.1694836653.7.1.1694836741.0.0.0; uuid_update=true; acctId=162626363; token=0ZEKX2G_evEV333G7r_BRq0PUDvG-5K1JLPiJ6JaYZ40*; brandId=-1; isOfflineSelfOpen=0; city_id=350100; isChain=0; existBrandPoi=true; ignore_set_router_proxy=false; region_version=1688959557; newCategory=true; bsid=pVld0HLCk-it4Z32JBvwH7HJimH22Eb8Q1RYYZ2IzEc3h0VjLBaRtV6hCmJNGgZVPs81G20JFwxLe1QE9ux60w; device_uuid=!442e66a1-9d02-409f-86ee-6b5be3cbb051; grayPath=newRoot; logistics_support=1; cityId=350100; provinceId=350000; city_location_id=350100; location_id=350104; pushToken=0ZEKX2G_evEV333G7r_BRq0PUDvG-5K1JLPiJ6JaYZ40*; pharmacistAccount=0; igateApp=shangouepc; _et=f-Ami5RAzSjsTxw8NdjOCSiuyt6DuXGA1V1RrS-2k0Pjz-Ycoee7Fm0Z1CUlSibmqKWBATrA4giReAdGtzdpXQ; wpush_server_url=wss://wpush.meituan.com; wmPoiId=18463790; region_id=1000350100; accessToken=pVld0HLCk-it4Z32JBvwH7HJimH22Eb8Q1RYYZ2IzEc3h0VjLBaRtV6hCmJNGgZVPs81G20JFwxLe1QE9ux60w; set_info=%7B%22wmPoiId%22%3A18463790%2C%22region_id%22%3A%221000350100%22%2C%22region_version%22%3A1688959557%7D; JSESSIONID=ev9mdxzpnm1mt1w438usmhuq; _gw_ab_call_29856_23=TRUE; _gw_ab_29856_23=73; cacheTimeMark=2023-09-27; shopCategory=market; signToken="uLvxwg0UwYyrDDacZsonRbutk64EdNTR3nLGU6nEoFfAxirD6pjrYGNLGX4XVZfTAk7m6TKZSJuNJd66I+glc09TX2E3aPI2+jZpxFJd7E6KdaoBl9rMKQXmUfvEpJGJsFS4DDVV9jhpusQKvP899w=="; _lxsdk_s=18ad377aa05-6d6-9aa-2f5%7C%7C150';




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

    /**
     * 通过其他方获得的订单数据,此接口仅保存订单数据和操作库存修改
     */
    public function orders(Request $request){
        // return $this->error('接口重新部署中....');
        set_time_limit(0);
        $platform   = (int)$request->post('platform');
        $storeid    = (int)$request->post('store_id');
        $list       = $request->post('list');
        $list       = is_string($list) ? json_decode($list, true) : $list;
        if(!$list || !is_array($list)){
            return $this->error('data 数据解析错误!');
        }

        // try {
            $instance   = Store::getInstance($storeid, $platform);//where('store_id', $storeid)->where('platform_id', $platform)->first();
            $orderids   = $instance->saveOrders($list);
            if(empty($orderids)){
                $errs   = $instance->errs() ? implode("<br>\r\n", $instance->errs()) : '添加为空!';
                return $this->error($errs);
            }
            Sku::updateFromPlatformOrders($orderids, $platform, $storeid);
            return $this->success(null, '成功!');
        // } catch (\Exception $e) {
        //     return $this->error($e->getMessage());
        // }
    }

    public function setorder(Request $request){
        $storeObj       = [];
        foreach(Order::whereRaw('origin_content is not null')->get() as $order){
            if(!isset($storeObj[$order->store_id])){
                $storeObj[$order->store_id]   = Store::where('store_id', $order->store_id)->first();
            }

            $item       = json_decode($order->origin_content, true);
            if($order->platform_id == 1){//美团
                $changeStatus       = 25;
                $orderId            = $item['channelOrderId'];
                $channelId          = $item['channelId'];
                $status             = $item['orderStatus'];
                $res        = $storeObj[$order->store_id]->getInstances()->getOrderInfo($channelId, $orderId);
                $orderInfo  = json_decode($res, true);
                if(!$orderInfo){
                    continue;
                }
                $orderInfo              = $orderInfo['data'];
                $order->product_amount  = $orderInfo['baseInfo']['productAmount'];
                $order->merchantAmount  = $orderInfo['baseInfo']['merchantAmount'];
                $order->deliveryAmount  = $orderInfo['baseInfo']['deliveryAmount'];
                $order->performService  = $orderInfo['billInfo']['performService'];
                $order->createTime      = $item['createTime'] / 1000;
                $order->pay_time        = $item['payTime'] / 1000;
                $order->itemCount       = $item['itemCount'];
                $order->orderId_tm      = $item['orderId'];
                $order->status          = $status == $changeStatus ? -1 : 1;
                $order->addtime         = time();
                $order->juli            = 0;
                $order->userid          = $item['userId'];
                $order->username        = $item['receiverName'];
                $order->phone           = $item['userPrivacyPhone'];
                $order->order_index     = $item['serialNo'];
                $order->pay_amount      = $orderInfo['baseInfo']['paidAmount'];
                $order->address         = $item['receiverAddress'];
                $order->weight          = $item['weight'];
                $order->comments        = $item['comments'];
                $order->pack_status     = $item['fuseOrderStatus'];
                $order->pack_status_desc= $item['fuseOrderStatusDesc'];
                $order->user_tags       = implode(',', array_column($item['userTags'], 'name'));
                $order->butie_platform  = array_sum(array_column($orderInfo['promotionInfo'], 'platformAmount'));//平台补贴
                $order->butie           = array_sum(array_column($orderInfo['promotionInfo'], 'merchantAmount'));//商家补贴
                $order->butie_details   = implode(',', array_column($orderInfo['promotionInfo'], 'promotionName'));//补贴说明

                try {
                    $orderLl                = $this->ScOrderLonLat($orderId);
                    if(isset($orderLl['pageList'][0])){
                        $order->lat         = $orderLl['pageList'][0]['address_latitude'];
                        $order->log         = $orderLl['pageList'][0]['address_longitude'];
                    }
                } catch (\Exception $e) {
                    // dd($e->getMessage());
                    Log::error('获取闪仓订单经纬度失败! - ' . $orderId);
                }
                try {
                    $orderDist              = $this->ScOrderJuli($orderId);
                    if($orderDist && isset($orderDist[$orderId])){
                        $order->juli        = $orderDist[$orderId]['distance'];
                    }
                } catch (\Exception $e) {
                    dd($e->getMessage());
                    Log::error('获取闪仓订单距离失败! - ' . $orderId);
                }
                $item['latlons']        = $orderLl;
                $item['dists']          = $orderDist;

                $start                  = $item['orderOperatorLogList'][0]['operationTime'] ?? 0;
                $end                    = $item['orderOperatorLogList'][count($item['orderOperatorLogList']) - 1]['operationTime'] ?? 0;
                $order->used_time       = (int)(($end-$start) / 1000);

                foreach($item['orderOperatorLogList'] as $pcv){
                    switch($pcv['operatorType']){
                        case 3://接单开始时间
                            $order->jiedan_time     = $pcv['operationTime'] / 1000;
                        break;
                        case 4://开始拣货时间
                            $order->pack_time       = $pcv['operationTime'] / 1000;
                        break;
                        case 5://拣货完成时间
                            $order->pack_end_time   = $pcv['operationTime'] / 1000;
                        break;
                        case 6://配送开始时间
                            $order->ship_time       = $pcv['operationTime'] / 1000;
                        break;
                        case 7://配送送达时间
                            $order->ship_end_time   = $pcv['operationTime'] / 1000;
                        break;
                        case 8://完成时间
                            $order->done_time       = $pcv['operationTime'] / 1000;
                        break;
                    }
                }

                $order->orderStatus         = $status;
                $order->orderStatusDesc     = $item['orderStatusDesc'];

                Order::saveOrigin(json_encode($item, JSON_UNESCAPED_UNICODE), date('Ymd', $order->createTime) . '/' . $orderId . '-' . $status . '.txt');
                $order->origin_content  = null;
                $order->save();
            }elseif($order->platform_id == 2){
                $changeStatus       = 10;
                $orderId            = $item['orderDetailBizDTO']['orderId'];
                $data               = json_decode($storeObj[$order->store_id]->getInstances()->getOrders(1, 10, $orderId), true);
                $order_list         = $data['data']['data']['order_list'];
                $data               = $data['data']['data']['mainOrderList'];

                $item               = $data[0];
                $status             = $item['orderDetailBizDTO']['status'];
                $otherCost          = $item['ols'] = $order_list[0];


                $order->product_amount  = $otherCost['order_sub_total']['price'];
                $order->deliveryAmount  = $otherCost['orderSettle']['callRiderDeliveryAmt'];
                $order->pay_amount      = $this->price($item['orderDetailSettleDTO']['subCostItemList'][0]['subItemList'][0]['value'])[0];//支付金额
                $order->merchantAmount  = $this->price($item['orderDetailSettleDTO']['estimateIncomeItem']['value'])[0];//预计收入
                $order->performService  = $this->price($otherCost['extract_commission']['commission_total'])[0];//履约服务费
                $order->createTime      = strtotime($item['orderDetailBizDTO']['createTime']);//订单创建时间
                $order->pay_time        = strtotime($item['orderDetailBizDTO']['confirmTime']);//订单支付时间
                $order->userid          = $item['orderDetailUserDTO']['userId'];
                $order->username        = $item['orderDetailUserDTO']['userRealName'];
                $order->phone           = $item['orderDetailUserDTO']['userPhone'];
                $order->address         = $item['orderDetailUserDTO']['userAddress'];
                $order->user_tags       = implode(',', $item['orderDetailUserDTO']['userTagsDescList']);
                $order->lat             = str_replace('.', '', $item['orderDetailUserDTO']['userAddressLat']);
                $order->log             = str_replace('.', '', $item['orderDetailUserDTO']['userAddressLng']);
                $order->order_index     = $item['orderDetailBizDTO']['orderIndex'];
                $order->itemCount       = $item['orderDetailGoodsDTO']['goodsTotalNum'];
                $order->status          = $status == $changeStatus ? -1 : 1;
                $order->addtime         = time();
                $order->juli            = strtolower($item['orderDetailBizDTO']['distance']);
                if(strpos($order->juli, 'km')){
                    $order->juli        = intval(str_replace('km', '', $order->juli))*1000;
                }else{
                    $order->juli        = intval(str_replace('m', '', $order->juli));
                }
                $order->weight          = array_sum(array_column($item['orderDetailGoodsDTO']['goodsList'], 'fixWeight'));
                $order->comments        = implode(';;', array_column($item['orderDetailBizDTO']['orderRemarkList'], 'remarkContext'));
                $order->pack_status     = $status;
                $order->pack_status_desc= $item['orderDetailBizDTO']['statusDesc'];

                foreach($otherCost['orderDiscount']['discountDistributeList'] as $vvc){
                    switch($vvc['title']){
                        case '商家补贴合计':
                            $order->butie           = $this->price($vvc['price'])[0];
                            break;
                        case '平台补贴合计':
                            $order->butie_platform  = $this->price($vvc['price'])[0];
                            break;

                    }
                }
                if(isset($otherCost['orderDiscount']['discountInfoList'][0]['list'])){
                    $order->butie_details   = implode(',', array_column($otherCost['orderDiscount']['discountInfoList'][0]['list'], 'activityName'));//补贴说明
                }

                $deliveryList           = $item['orderDetailDeliveryDTO']['deliveryTimeLine'];
                $start                  = $deliveryList[0]['timeLineTime'] ?? 0;
                $end                    = $deliveryList[count($deliveryList) - 1]['timeLineTime'] ?? 0;
                $order->used_time       = (int)(($end-$start) / 1000);
                if($order->used_time < 0){
                    $order->used_time   = 0;
                }

                foreach($deliveryList as $pcv){
                    switch($pcv['title']){
                        case '商家已接单'://接单开始时间
                            $order->jiedan_time = $order->pack_time = $pcv['timeLineTime'] / 1000;
                        break;
                        case '商家呼叫配送'://拣货完成时间
                            $order->pack_end_time   = $pcv['timeLineTime'] / 1000;
                        break;
                        case '骑士已取货'://配送开始时间
                            $order->ship_time       = $pcv['timeLineTime'] / 1000;
                        break;
                        case 8://完成时间
                            $order->done_time       = $pcv['timeLineTime'] / 1000;
                        break;
                    }
                    if(strpos($pcv['title'], '订单已送达') !== false){
                        $order->ship_end_time = $order->done_time   = $pcv['timeLineTime'] / 1000;
                    }
                }

                $order->orderStatus         = $status;
                $order->orderStatusDesc     = $item['orderDetailBizDTO']['statusDesc'];
                Order::saveOrigin(json_encode($item, JSON_UNESCAPED_UNICODE), date('Ymd', $order->createTime) . '/' . $orderId . '-' . $status . '.txt');
                $order->origin_content      = null;
                $order->save();
            }
        }
    }

    /**
     * 获取美团闪仓订单信息,此接口返回配送距离
     */
    private function ScOrderJuli($orderid){
        $poid       = $this->poiid;
        $cookies    = str_replace('; ', ';', $this->sccookie);
        $url        = 'https://shangoue.meituan.com/api/retail/v3/delivery/overInfo?region_id=1000350100&region_version=1688959557';
        $client     = new Client();
        $headers    = [
            'Host'          => 'shangoue.meituan.com',
            'Cookie'        => $cookies,
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json',
            'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
        ];

        $data       = [['poiId' => $poid, 'orderViewId' => $orderid]];
        $response = $client->post($url, [
            'headers'       => $headers,
            'json'          => $data,
        ]);
        $resp   = json_decode($response->getBody()->getContents(), true);
        if(isset($resp['data'])){
            return $resp['data'];
        }
        return false;
    }

    /**
     * 获取闪仓订单配送地址经纬度
     */
    private function ScOrderLonLat($orderid){
        $client     = new Client();

        $poid       = $this->poiid;
        $cookies    = str_replace('; ', ';', $this->sccookie);
        $url        = 'https://shangoue.meituan.com/api/retail/v3/order/orderSearch?region_id=1000350100&region_version=1688959557';
        $headers    = [
            'Host'          => 'shangoue.meituan.com',
            'Cookie'        => $cookies,
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
        ];
        $data       = [
            'poiId'         => $poid,
            'sortType'      => 1,
            'pageSize'      => 10,
            'pageNum'       => 1,
            'searchItem'    => (int)$orderid,
            'acctId'        => 162626363,
        ];
        $response = $client->post($url, [
            'headers'       => $headers,
            'form_params'   => $data, // 使用x-www-form-urlencoded方式发送数据
        ]);
        $responseBody = $response->getBody()->getContents();
        $resp   = json_decode($responseBody, true);
        if(isset($resp['data'])){
            return $resp['data'];
        }
        return false;
    }

    /**
     * 提取数字
     */
    private function price($val){
        preg_match_all('`[\d\.]+`', $val, $nn);
        return isset($nn[0][0]) ? $nn[0] : [''];
    }
}
