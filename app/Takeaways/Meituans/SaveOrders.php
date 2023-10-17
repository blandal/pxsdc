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
	private $doneStatus 	= 15;
	private $opIds 		= [];

	//闪电仓参数
	private $poiid 		= 18463790;
	private $sccookie 	= null;
	public function __construct(array $data, Store $store){
		$thos->sccookie 	= file_get_contents(storage_path('app/meituan.cookie'));
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
		if(!$store){
			Log::error('店铺不存在!' . implode(",", $orderIds));
			return;
		}
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


			if($this->doneStatus == $status){//已完成的情况需要判断是否有部分退款情况
				$orderTags 	= $item['orderTagList'] ?? [];
				$bufentui 	= false;
				foreach($orderTags as $ots){
					if(strpos($ots['name'], '退单') !== false){//有退单的情况
						$bufentui 	= true;
						break;
					}
				}
				if($bufentui === true && $order->status != -2){//订单信息中如果有部分退款,则获取订单商品详情查看部分退款商品详情,并执行库存增加
					$res 		= $store->getInstances()->getOrderInfo($channelId, $orderId);
					$orderInfo 	= json_decode($res, true);
					if(!$orderInfo){
						continue;
					}
					$orderInfo 				= $orderInfo['data'];
					$refundOrderProducts 	= [];
					foreach($orderInfo['itemInfo'] as $info){
						if(isset($info['refundCount']) && $info['refundCount'] <= $info['realQuantity']){
							$refundOrderProducts[] 	= ['orderItemId' => $info['orderItemId'], 'sku_id' => $info['sku'], 'count' => $info['refundCount']];
						}
					}
					if(!empty($refundOrderProducts)){
						foreach($refundOrderProducts as $refound){
							$refoundRow 	= OrderProduct::where(['orderItemId' => $refound['orderItemId'], 'sku_id' => $refound['sku_id']])->first();
							if($refoundRow){
								$refoundRow->rebackStocks($refound['count']);
							}
						}
					}
					$order->status 				= -2;
				}
			}elseif($status == $this->changeStatus){//订单状态是取消
				$order->status 				= -1;
				if($order->orderStatus != $status){//如果当前订单状态和上传的不一致
					$res 	= OrderProduct::where('order_id', $orderId)->get();
					if($res){
						Log::info('美团订单编号[' . $orderId . ']: 用户取消,执行退回库存!');
						foreach($res as $vvzzs){//逐个商品退回库存
							$vvzzs->rebackStocks();
						}
					}
				}
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