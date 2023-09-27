<?php
namespace App\Takeaways\Meituans;

use App\Models\Order;
use App\Models\Store;
use App\Models\Platform;
use App\Models\ProductSku;
use App\Models\Sku;
use App\Models\OrderProduct;
use Illuminate\Support\Facades\DB;
use App\Takeaways\Meituan;
use App\Takeaways\BaseFactory;
use Illuminate\Support\Facades\Log;
use QL\QueryList;
use GuzzleHttp\Client;
class SaveOrders extends Meituan{
	use BaseFactory;
	// private $status 	= true;
	// private $errmsg 	= [];
	private $dbOrders 	= [];
	private $platform 	= 0;
	private $addOrder 	= [];
	private $newOrderid	= [];
	private $addOp 		= [];
	private $stores 	= [];
	private $platformObj= [];
	private $changeStatus 	= 25;
	private $opIds 		= [];

	//闪电仓参数
	private $poiid 		= 18463790;
	private $sccookie 	= '_lxsdk_cuid=18a5dae5e60c8-002c984d7d55a2-26031f51-1fa400-18a5dae5e60c8; _lxsdk=18a5dae5e60c8-002c984d7d55a2-26031f51-1fa400-18a5dae5e60c8; uuid=6394452bd591b7090f6e.1693788822.1.0.0; WEBDFPID=2u4x8uvv5663567y13904zyu060w9yu181z4wzv391y97958wu93z3zv-2009148825058-1693788824367WGWAMCKfd79fef3d01d5e9aadc18ccd4d0c95079724; _ga=GA1.1.373643657.1694075781; _ga_95GX0SH5GM=GS1.1.1694836653.7.1.1694836741.0.0.0; uuid_update=true; acctId=162626363; token=0ZEKX2G_evEV333G7r_BRq0PUDvG-5K1JLPiJ6JaYZ40*; brandId=-1; isOfflineSelfOpen=0; city_id=350100; isChain=0; existBrandPoi=true; ignore_set_router_proxy=false; region_version=1688959557; newCategory=true; bsid=pVld0HLCk-it4Z32JBvwH7HJimH22Eb8Q1RYYZ2IzEc3h0VjLBaRtV6hCmJNGgZVPs81G20JFwxLe1QE9ux60w; device_uuid=!442e66a1-9d02-409f-86ee-6b5be3cbb051; grayPath=newRoot; logistics_support=1; cityId=350100; provinceId=350000; city_location_id=350100; location_id=350104; pushToken=0ZEKX2G_evEV333G7r_BRq0PUDvG-5K1JLPiJ6JaYZ40*; pharmacistAccount=0; igateApp=shangouepc; _et=f-Ami5RAzSjsTxw8NdjOCSiuyt6DuXGA1V1RrS-2k0Pjz-Ycoee7Fm0Z1CUlSibmqKWBATrA4giReAdGtzdpXQ; wpush_server_url=wss://wpush.meituan.com; wmPoiId=18463790; region_id=1000350100; accessToken=pVld0HLCk-it4Z32JBvwH7HJimH22Eb8Q1RYYZ2IzEc3h0VjLBaRtV6hCmJNGgZVPs81G20JFwxLe1QE9ux60w; set_info=%7B%22wmPoiId%22%3A18463790%2C%22region_id%22%3A%221000350100%22%2C%22region_version%22%3A1688959557%7D; JSESSIONID=ev9mdxzpnm1mt1w438usmhuq; _gw_ab_call_29856_23=TRUE; _gw_ab_29856_23=73; cacheTimeMark=2023-09-27; shopCategory=market; signToken="uLvxwg0UwYyrDDacZsonRbutk64EdNTR3nLGU6nEoFfAxirD6pjrYGNLGX4XVZfTAk7m6TKZSJuNJd66I+glc09TX2E3aPI2+jZpxFJd7E6KdaoBl9rMKQXmUfvEpJGJsFS4DDVV9jhpusQKvP899w=="; _lxsdk_s=18ad377aa05-6d6-9aa-2f5%7C%7C150';
	public function __construct(array $data, Store $store){
		$this->store 		= $store;
		$this->platform 	= $store->platform->id;
		if(!isset($data[0])){
			if(!isset($data['data']['orderList'])){
				return $this->seterr('传入数据格式错误,不是一个数组或者没有 data 的 key');
			}
			$data 	= $data['data'];
			if(!isset($data['orderList'])){
				return $this->seterr('orderList 不存在');
			}
			$data 	= $data['orderList'];
		}
		$orderIds 			= array_column($data, 'channelOrderId');
		$orders 			= [];
		foreach(Order::whereIn('orderid', $orderIds)->where('platform_id', $this->platform)->where('store_id', $store->store_id)->get() as $item){
			$orders[$item->orderid] 	= $item;
		}
		$tableSkuIds 		= [];

		foreach($data as $item){
			$channelId 			= $item['channelId'];
			$orderId 			= $item['channelOrderId'];
			$status 			= $item['orderStatus'];

			if(!isset($orders[$orderId])){
				$res 		= $store->getInstances()->getOrderInfo($channelId, $orderId);
				$orderInfo 	= json_decode($res, true);
				if(!$orderInfo){
					continue;
				}
				$orderInfo 	= $orderInfo['data'];

				$order 					= new Order;
				$order->orderid 		= $orderId;
				$order->channelId 		= $channelId;
				$order->store_id 		= $store->store_id;
				$order->platform_id 	= $this->platform;
				$order->product_amount 	= $orderInfo['baseInfo']['productAmount'];
				$order->merchantAmount 	= $orderInfo['baseInfo']['merchantAmount'];
				$order->deliveryAmount 	= $orderInfo['baseInfo']['deliveryAmount'];
				$order->performService 	= $orderInfo['billInfo']['performService'];
				$order->createTime 		= $item['createTime'] / 1000;
				$order->pay_time 		= $item['payTime'] / 1000;
				$order->itemCount 		= $item['itemCount'];
				$order->orderId_tm 		= $item['orderId'];
				$order->status 			= $status == $this->changeStatus ? -1 : 1;
				$order->addtime 		= time();
				$order->juli 			= 0;
				$order->userid 			= $item['userId'];
				$order->username 		= $item['receiverName'];
				$order->phone 			= $item['userPrivacyPhone'];
				$order->order_index 	= $item['serialNo'];
				$order->pay_amount 		= $orderInfo['baseInfo']['paidAmount'];
				$order->address 		= $item['receiverAddress'];
				$order->weight 			= $item['weight'];
				$order->comments 		= $item['comments'];
				$order->pack_status 	= $item['fuseOrderStatus'];
				$order->pack_status_desc= $item['fuseOrderStatusDesc'];
				$order->user_tags 		= implode(',', array_column($item['userTags'], 'name'));
				$order->butie_platform 	= array_sum(array_column($orderInfo['promotionInfo'], 'platformAmount'));//平台补贴
				$order->butie 			= array_sum(array_column($orderInfo['promotionInfo'], 'merchantAmount'));//商家补贴
				$order->butie_details 	= implode(',', array_column($orderInfo['promotionInfo'], 'promotionName'));//补贴说明

				try {
					$orderLl 				= $this->ScOrderLonLat($orderId);
					if(isset($orderLl['pageList'][0])){
						$order->lat 		= $orderLl['pageList'][0]['address_latitude'];
						$order->log 		= $orderLl['pageList'][0]['address_longitude'];
					}
				} catch (\Exception $e) {
					// dd($e->getMessage());
					Log::error('获取闪仓订单经纬度失败! - ' . $orderId);
				}
				try {
					$orderDist 				= $this->ScOrderJuli($orderId);
					if($orderDist && isset($orderDist[$orderId])){
						$order->juli 		= $orderDist[$orderId]['distance'];
					}
				} catch (\Exception $e) {
					dd($e->getMessage());
					Log::error('获取闪仓订单距离失败! - ' . $orderId);
				}
				$item['latlons']		= $orderLl;
				$item['dists']			= $orderDist;
				Order::saveOrigin(json_encode($item, JSON_UNESCAPED_UNICODE), date('Ymd', $order->createTime) . '/' . $orderId . '-' . $status . '.txt');
				$order->save();

				//获取产品sku
				$orderProducts 		= json_decode($store->getInstances()->orderProducts($orderId), true);
				if(!isset($orderProducts['data']['itemInfo'])){
					return $this->seterr($orderId . ' 获取商品详情失败!');
				}
				try {
					foreach($orderProducts['data']['itemInfo'] as $zzcf){
						$skuid 				= $zzcf['sku'];
						$skuTableId 		= 0;
						if(isset($tableSkuIds[$skuid])){
							$skuTableId 	= $tableSkuIds[$skuid];
						}else{
							$row 			= Sku::where('sku_id', $skuid)->where('platform', $this->platform)->where('store_id', $store->store_id)->first();
							if($row){
								$tableSkuIds[$skuid] 	= $row->id;
								$skuTableId 			= $row->id;
							}
						}
						$productDbArr[] 		= [
							'order_id'			=> $orderId,
							'sku_id'			=> $zzcf['sku'],
							'orderItemId'		=> $zzcf['orderItemId'],
							'quantity'			=> $zzcf['orderQuantity'],
							'upc'				=> $zzcf['upc'],
							'storeId'			=> $store->store_id,
							'spec'				=> $zzcf['spec'],
							'title'				=> $zzcf['skuName'],
							'customSkuId'		=> $zzcf['customSkuId'],
							'platform_id'		=> $this->platform,
							'sku_table_id'		=> $skuTableId,
							'createtime'		=> $order->createTime,
						];
					}
				} catch (\Exception $e) {
					return $this->seterr($e->getMessage());
				}

				// $getSkuIds 				= array_column($item['productList'], 'skuId');
				// $skus 					= Sku::whereIn('sku_id', $getSkuIds)->pluck('id', 'sku_id')->toArray();
				// $productDbArr 			= [];
				// foreach($orderInfo['itemInfo'] as $product){
				// 	$productDbArr[] 	= [
				// 		'order_id'			=> $orderId,
				// 		'sku_id'			=> $product['sku'],
				// 		'orderItemId'		=> $product['orderItemId'],
				// 		'quantity'			=> $product['quantity'],
				// 		'upc'				=> $product['upc'],
				// 		'storeId'			=> $order->store_id,
				// 		'spec'				=> $product['spec'],
				// 		'title'				=> $product['skuName'],
				// 		'customSkuId'		=> $product['customSkuId'],
				// 		'platform_id'		=> $this->platform,
				// 		'sku_table_id'		=> $skus[$product['sku']] ?? null,
				// 		'createtime'		=> $order->createTime,
				// 	];
				// }
				if(!empty($productDbArr)){
					// dd($productDbArr, $tableSkuIds);
					OrderProduct::insert($productDbArr);
				}
				$this->newOrderid[] 	= $orderId;
			}else{
				$order 		= $orders[$orderId];
			}


			if(isset($item['orderOperatorLogList'])){
				$start 					= $item['orderOperatorLogList'][0]['operationTime'] ?? 0;
				$end 					= $item['orderOperatorLogList'][count($item['orderOperatorLogList']) - 1]['operationTime'] ?? 0;
				$order->used_time 		= (int)(($end-$start) / 1000);
				foreach($item['orderOperatorLogList'] as $pcv){
					switch($pcv['operatorType']){
						case 3://接单开始时间
							$order->jiedan_time 	= $pcv['operationTime'] / 1000;
						break;
						case 4://开始拣货时间
							$order->pack_time 		= $pcv['operationTime'] / 1000;
						break;
						case 5://拣货完成时间
							$order->pack_end_time 	= $pcv['operationTime'] / 1000;
						break;
						case 6://配送开始时间
							$order->ship_time 		= $pcv['operationTime'] / 1000;
						break;
						case 7://配送送达时间
							$order->ship_end_time 	= $pcv['operationTime'] / 1000;
						break;
						case 8://完成时间
							$order->done_time 		= $pcv['operationTime'] / 1000;
						break;
					}
				}
			}


			if($status == $this->changeStatus && $order->orderStatus != $status){//如果退单,则要同步加库存
				$res 	= OrderProduct::where('order_id', $orderId)->get();
				if($res){
					Log::info('美团订单编号[' . $orderId . ']: 用户取消,执行退回库存!');
					foreach($res as $vvzzs){//逐个商品退回库存
						$vvzzs->rebackStocks();
					}
				}
				$order->status 				= -1;
			}

			$order->orderStatus 		= $status;
			$order->orderStatusDesc 	= $item['orderStatusDesc'];
			$order->save();
		}
	}

	/**
	 * 获取美团闪仓订单信息,此接口返回配送距离
	 */
	private function ScOrderJuli($orderid){
		$poid 		= $this->poiid;
		$cookies 	= str_replace('; ', ';', $this->sccookie);
		$url 		= 'https://shangoue.meituan.com/api/retail/v3/delivery/overInfo?region_id=1000350100&region_version=1688959557';
		$client		= new Client();
		$headers 	= [
			'Host'			=> 'shangoue.meituan.com',
			'Cookie'    	=> $cookies,
			'Accept'    	=> 'application/json, text/plain, */*',
			'Content-Type'  => 'application/json',
			'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
		];

		$data 		= [['poiId' => $poid, 'orderViewId' => $orderid]];
		$response = $client->post($url, [
			'headers' 		=> $headers,
			'json' 			=> $data,
		]);
		$resp 	= json_decode($response->getBody()->getContents(), true);
		if(isset($resp['data'])){
			return $resp['data'];
		}
		return false;
	}

	/**
	 * 获取闪仓订单配送地址经纬度
	 */
	private function ScOrderLonLat($orderid){
		$client 	= new Client();

		$poid 		= $this->poiid;
		$cookies 	= str_replace('; ', ';', $this->sccookie);
		$url 		= 'https://shangoue.meituan.com/api/retail/v3/order/orderSearch?region_id=1000350100&region_version=1688959557';
		$headers 	= [
			'Host'			=> 'shangoue.meituan.com',
			'Cookie'    	=> $cookies,
			'Accept'    	=> 'application/json, text/plain, */*',
			'Content-Type'  => 'application/x-www-form-urlencoded',
			'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
		];
		$data 		= [
			'poiId' 		=> $poid,
			'sortType'		=> 1,
			'pageSize'		=> 10,
			'pageNum'		=> 1,
			'searchItem' 	=> (int)$orderid,
			'acctId' 		=> 162626363,
		];
		$response = $client->post($url, [
			'headers' 		=> $headers,
			'form_params' 	=> $data, // 使用x-www-form-urlencoded方式发送数据
		]);
		$responseBody = $response->getBody()->getContents();
		$resp 	= json_decode($responseBody, true);
		if(isset($resp['data'])){
			return $resp['data'];
		}
		return false;
	}

	public function render(){//返回的是product_skus表的id对应本次列表下单的总数量
		return $this->newOrderid;
		// $platform_order_ids 	= [];
		// if($this->status === true){
		// 	DB::transaction(function () {
		// 		if(!empty($this->addOrder)){
		// 			Order::insert($this->addOrder);
		// 		}
		// 		if(!empty($this->addOp)){
		// 			OrderProduct::insert($this->addOp);
		// 			$skulessStocks 	= [];
		// 			foreach($this->addOp as $item){
		// 				if(!isset($skulessStocks[$item['sku_id']])){
		// 					$skulessStocks[$item['sku_id']] 	= $item['quantity'];
		// 				}else{
		// 					$skulessStocks[$item['sku_id']] 	+= $item['quantity'];
		// 				}
		// 			}
		// 			$ps 	= Sku::whereIn('sku_id', array_keys($skulessStocks))
		// 								->where('platform', $this->platform)
		// 								->where('store_id', $this->store->store_id)->get();
		// 			foreach($ps as $item){
		// 				if(isset($skulessStocks[$item->sku_id])){
		// 					$stock 			= $skulessStocks[$item->sku_id];
		// 					if($stock < 0){
		// 						$stock 		= 0;
		// 					}
		// 					$this->opIds[$item->id] 	= $stock;
		// 				}
		// 			}
		// 		}
		// 	}, 3);
		// 	$platform_order_ids 	= array_keys($this->addOrder);
		// 	Log::info('[美团]订单id: ' . ($platform_order_ids ? implode(',', $platform_order_ids) : '本次没有新订单插入!'));
		// }
		// return $platform_order_ids;
	}

	/**
	 * 处理一行的数据(已废弃)
	 */
	private function fmtrow($row){
		if(!isset($row['channelOrderId'], $row['orderStatus'])){//22是退单待审核, 25是取消
			return $this->seterr('订单 channelOrderId 和 orderStatus 不存在!');
		}
		$orderid 	= $row['channelOrderId'];
		$status 	= $row['orderStatus'];
		$store_id 	= $row['storeId'];
		$orderStatusDesc 	= $row['orderStatusDesc'];
		$itemcount 	= $row['itemCount'];
		$createTime = $row['createTime'] / 1000;

		// if($status == $this->changeStatus){//25是取消订单,订单取消需要加库存
		// 	$res 	= OrderProduct::where('order_id', $orderid)->get();
		// 	if($res){
		// 		Log::info('美团订单编号[' . $orderid . ']: 用户取消,执行退回库存!');
		// 		foreach($res as $item){//逐个商品退回库存
		// 			$item->rebackStocks();
		// 		}
		// 	}
		// }

		if(isset($this->dbOrders[$orderid])){
			if($status == $this->changeStatus && $this->dbOrders[$orderid]->orderStatus != $status){//如果退单,则要同步加库存
				$this->dbOrders[$orderid]->orderStatus 		= -1;
				$this->dbOrders[$orderid]->orderStatusDesc 	= $orderStatusDesc;
				$this->dbOrders[$orderid]->origin_content	= json_encode($row, JSON_UNESCAPED_UNICODE);
				$this->dbOrders[$orderid]->save();
				$res 	= OrderProduct::where('order_id', $orderid)->get();
				if($res){
					Log::info('美团订单编号[' . $orderid . ']: 用户取消,执行退回库存!');
					foreach($res as $item){//逐个商品退回库存
						$item->rebackStocks();
					}
				}
			}
		}else{//新订单
			if($status != $this->changeStatus){//如果订单不是取消单,则需要同步扣除库存
				$this->addOrder[$orderid] 	= [
					'orderid'			=> $orderid,
					'store_id'			=> $store_id,
					'orderStatus'		=> $status,
					'orderStatusDesc'	=> $orderStatusDesc,
					'itemCount'			=> $itemcount,
					'createTime'		=> $createTime,
					'orderId_tm'		=> $row['orderId'],
					'platform_id'		=> $this->platform,
					'addtime'			=> time(),
					'origin_content'	=> json_encode($row, JSON_UNESCAPED_UNICODE),
				];
				if(isset($this->stores[$store_id])){
					$orderProducts 		= json_decode($this->stores[$store_id]['oob']->orderProducts($orderid), true);
					if(!isset($orderProducts['data']['itemInfo'])){
						return $this->seterr($orderid . ' 获取商品详情失败!');
					}
					try {
						foreach($orderProducts['data']['itemInfo'] as $item){
							$skuid 				= $item['sku'];
							$skuTableId 		= 0;
							if(isset($tableSkuIds[$skuid])){
								$skuTableId 	= $tableSkuIds[$skuid];
							}else{
								$row 			= Sku::where('sku_id', $skuid)->where('platform', $this->platform)->where('store_id', $store_id)->first();
								if($row){
									$tableSkuIds[$skuid] 	= $row->id;
									$skuTableId 			= $row->id;
								}
							}
							$this->addOp[] 		= [
								'order_id'			=> $orderid,
								'sku_id'			=> $item['sku'],
								'orderItemId'		=> $item['orderItemId'],
								'quantity'			=> $item['orderQuantity'],
								'upc'				=> $item['upc'],
								'storeId'			=> $store_id,
								'spec'				=> $item['spec'],
								'title'				=> $item['skuName'],
								'customSkuId'		=> $item['customSkuId'],
								'platform_id'		=> $this->platform,
								'sku_table_id'		=> $skuTableId,
								'createtime'		=> $createTime,
							];
						}
					} catch (\Exception $e) {
						return $this->seterr($e->getMessage());
					}
				}else{
					return $this->seterr($store_id . ' 实例化失败!');
				}
			}
		}
	}
}