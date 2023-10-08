<?php
namespace App\Takeaways\Elemes;

use App\Models\Order;
use App\Models\Store;
use App\Models\Platform;
use App\Models\ProductSku;
use App\Models\Sku;
use App\Models\OrderProduct;
use Illuminate\Support\Facades\DB;
use App\Takeaways\Eleme;
use App\Takeaways\BaseFactory;
use Illuminate\Support\Facades\Log;
/**
 * orderDetailBizDTO.distance 预计送达(2km)
 * orderDetailDeliveryDTO.deliveryFeeDesc 预估配送费(预估配送费5.72元)
 * orderDetailDeliveryDTO.deliveryParty 配送模式id(2)
 * orderDetailDeliveryDTO.deliveryPartyDesc 配送模式(众包-跑腿)
 * orderDetailDeliveryDTO.shopPickedTakeTime 拣货时长(拣货用时 14分39秒)
 * 4099450128299691538 退款订单号
 * 4002110128273411679 部分退款单号
 */


class SaveOrders extends Eleme{
	use BaseFactory;
	private $changeStatus 	= 10;
	private $newOrderid		= [];
	public function __construct(array $data, Store $store){
		if(!isset($data[0])){
			if(!isset($data['data']['data']['mainOrderList'])){
				return $this->seterr('传入数据格式错误,不是一个数组或者没有 data 的 key');
			}
			$order_list 	= $data['data']['data']['order_list'];
			$data 			= $data['data']['data']['mainOrderList'];
		}
		$platform_id 	= $store->platform->id;
		$orders 		= [];
		foreach(Order::whereIn('orderid', $this->getOrderIds($data))->where('platform_id', $platform_id)->where('store_id', $store->store_id)->get() as $item){
			$orders[$item->orderid]	= $item;
		}
		$tableSkuIds 			= [];
		foreach($data as $indexx => $item){
			$orderId 			= $item['orderDetailBizDTO']['orderId'];
			$status 			= $item['orderDetailBizDTO']['status'];
			$otherCost 			= $item['ols'] = $order_list[$indexx];

			if(!isset($orders[$orderId])){
				$order 					= new Order;
				$order->orderid 		= $orderId;
				$order->store_id 		= $store->store_id;
				$order->platform_id 	= $platform_id;

				$order->product_amount 	= $otherCost['order_sub_total']['price'];
				$order->deliveryAmount 	= $otherCost['orderSettle']['callRiderDeliveryAmt'];
				$order->pay_amount 		= $this->price($item['orderDetailSettleDTO']['subCostItemList'][0]['subItemList'][0]['value'])[0];//支付金额
				$order->merchantAmount 	= $this->price($item['orderDetailSettleDTO']['estimateIncomeItem']['value'])[0];//预计收入
				$order->performService 	= $this->price($otherCost['extract_commission']['commission_total'])[0];//履约服务费
				$order->createTime 		= strtotime($item['orderDetailBizDTO']['createTime']);//订单创建时间
				$order->pay_time 		= strtotime($item['orderDetailBizDTO']['confirmTime']);//订单支付时间
				$order->userid 			= $item['orderDetailUserDTO']['userId'];
				$order->username 		= $item['orderDetailUserDTO']['userRealName'];
				$order->phone 			= $item['orderDetailUserDTO']['userPhone'];
				$order->address 		= $item['orderDetailUserDTO']['userAddress'];
				$order->user_tags 		= implode(',', $item['orderDetailUserDTO']['userTagsDescList']);
				$order->lat 			= str_replace('.', '', $item['orderDetailUserDTO']['userAddressLat']);
				$order->log 			= str_replace('.', '', $item['orderDetailUserDTO']['userAddressLng']);
				$order->order_index 	= $item['orderDetailBizDTO']['orderIndex'];
				$order->itemCount 		= $item['orderDetailGoodsDTO']['goodsTotalNum'];
				$order->status 			= $status == $this->changeStatus ? -1 : 1;
				$order->addtime 		= time();
				$order->juli 			= strtolower($item['orderDetailBizDTO']['distance']);
				if(strpos($order->juli, 'km')){
					$order->juli 		= intval(str_replace('km', '', $order->juli))*1000;
				}else{
					$order->juli 		= intval(str_replace('m', '', $order->juli));
				}
				$order->weight 			= array_sum(array_column($item['orderDetailGoodsDTO']['goodsList'], 'fixWeight'));
				$order->comments 		= implode(';;', array_column($item['orderDetailBizDTO']['orderRemarkList'], 'remarkContext'));
				$order->pack_status 	= $status;
				$order->pack_status_desc= $item['orderDetailBizDTO']['statusDesc'];
				Order::saveOrigin(json_encode($item, JSON_UNESCAPED_UNICODE), date('Ymd', $order->createTime) . '/' . $orderId . '-' . $status . '.txt');

				foreach($otherCost['orderDiscount']['discountDistributeList'] as $vvc){
					switch($vvc['title']){
						case '商家补贴合计':
							$order->butie 			= $this->price($vvc['price'])[0];
							break;
						case '平台补贴合计':
							$order->butie_platform 	= $this->price($vvc['price'])[0];
							break;

					}
				}
				if(isset($otherCost['orderDiscount']['discountInfoList'][0]['list'])){
					$order->butie_details 	= implode(',', array_column($otherCost['orderDiscount']['discountInfoList'][0]['list'], 'activityName'));//补贴说明
				}
				$order->save();

				$productDbArr 			= [];
				foreach($item['orderDetailGoodsDTO']['goodsList'] as $product){
					$skuid 				= $product['skuId'] ?? $product['itemId'];
					$skuTableId 		= 0;
					if(isset($tableSkuIds[$skuid])){
						$skuTableId 	= $tableSkuIds[$skuid];
					}else{
						$row 			= Sku::where('sku_id', $skuid)->where('platform', $platform_id)->where('store_id', $order->store_id)->first();
						if($row){
							$tableSkuIds[$skuid] 	= $row->id;
							$skuTableId 			= $row->id;
						}
					}

					$productDbArr[] 	= [
						'order_id'			=> $orderId,
						'sku_id'			=> $product['ext']['storeAttr']['skuId'],
						'orderItemId'		=> $product['subOrderId'],
						'quantity'			=> $product['number'],
						'upc'				=> $product['ext']['storeAttr']['upcCode'],
						'storeId'			=> $order->store_id,
						'spec'				=> implode(',', array_column($product['ext']['propertyLabel'], 'detail')),
						'title'				=> $product['name'],
						'customSkuId'		=> $product['ext']['extCode'],
						'platform_id'		=> $order->platform_id,
						'itemSkuId'			=> $skuid,
						'sku_table_id'		=> $skuTableId,
						'createtime'		=> $order->createTime,
					];
				}
				if(!empty($productDbArr)){
					OrderProduct::insert($productDbArr);
				}
				$this->newOrderid[] 	= $orderId;
			}else{
				$order 		= $orders[$orderId];
			}


			$deliveryList 			= $item['orderDetailDeliveryDTO']['deliveryTimeLine'];
			$start 					= $deliveryList[0]['timeLineTime'] ?? 0;
			$end 					= $deliveryList[count($deliveryList) - 1]['timeLineTime'] ?? 0;
			$order->used_time 		= (int)(($end-$start) / 1000);
			if($order->used_time < 0){
				$order->used_time 	= 0;
			}

			foreach($deliveryList as $pcv){
				switch($pcv['title']){
					case '商家已接单'://接单开始时间
						$order->jiedan_time = $order->pack_time	= $pcv['timeLineTime'] / 1000;
					break;
					case '商家呼叫配送'://拣货完成时间
						$order->pack_end_time 	= $pcv['timeLineTime'] / 1000;
					break;
					case '骑士已取货'://配送开始时间
						$order->ship_time 		= $pcv['timeLineTime'] / 1000;
					break;
					case 8://完成时间
						$order->done_time 		= $pcv['timeLineTime'] / 1000;
					break;
				}
				if(strpos($pcv['title'], '订单已送达') !== false){
					$order->ship_end_time = $order->done_time 	= $pcv['timeLineTime'] / 1000;
				}
			}

			// if($status == $this->changeStatus && $order->orderStatus != $status){//如果退单,则要同步加库存
			// 	$res 	= OrderProduct::where('order_id', $orderId)->get();
			// 	if($res){
			// 		Log::info('饿了么订单编号[' . $orderId . ']: 用户取消,执行退回库存!');
			// 		foreach($res as $aksc){//逐个商品退回库存
			// 			$aksc->rebackStocks();
			// 		}
			// 	}
			// 	$order->status 				= -1;
			// }
			if(!empty($item['orderDetailReverseDTO']['refundOrderInfoList'])){//不管订单什么状态,有这个列表在的统一是退库存的
				$order->status 		= -2;
				foreach($item['orderDetailReverseDTO']['refundOrderInfoList'] as $refund){
					foreach($refund['refundOrderProductList'] as $rrrs){
						$row 			= OrderProduct::where(['sku_id' => $rrrs['ext']['storeAttr']['skuId'], 'order_id' => $orderId])->first();
						if($row){
							$row->rebackStocks($rrrs['number']);
						}else{
							Log::error('饿了么产品订单:' . $rrrs['ext']['storeAttr']['skuId'] . '--' . $orderId . '--' . $rrrs['itemId']);
						}
					}
				}
			}elseif($status == $this->changeStatus){
				$order->status 		= -1;
			}

			$order->orderStatus 		= $status;
			$order->orderStatusDesc 	= $item['orderDetailBizDTO']['statusDesc'];
			$order->save();
		}


		// foreach($data as $item){
		// 	$this->fmtrow($item);
		// 	if(!$this->status){
		// 		break;
		// 	}
		// }
	}

	public function render(){//返回的是product_skus表的id对应本次列表下单的总数量
		return $this->newOrderid;
		$platform_order_ids		= [];
		if($this->status === true){
			DB::transaction(function () {
				if(!empty($this->addOrder)){
					Order::insert($this->addOrder);
				}
				if(!empty($this->addOp)){
					OrderProduct::insert($this->addOp);
					$skulessStocks 		= [];
					foreach($this->addOp as $item){
						if(!isset($skulessStocks[$item['itemSkuId']])){
							$skulessStocks[$item['itemSkuId']] 	= $item['quantity'];
						}else{
							$skulessStocks[$item['itemSkuId']] 	+= $item['quantity'];
						}
					}
					$ps 	= Sku::whereIn('sku_id', array_keys($skulessStocks))
										->where('platform', $this->store->platform->id)
										->where('store_id', $this->store->store_id)->get();

					foreach($ps as $item){
						if(isset($skulessStocks[$item->sku_id])){
							$stock 			= $skulessStocks[$item->sku_id];
							if($stock < 0){
								$stock 		= 0;
							}
							$this->opIds[$item->id] 	= $stock;
						}
					}
				}
			}, 3);
			$platform_order_ids 	= array_keys($this->addOrder);
			Log::info('[饿了么]订单id: ' . ($platform_order_ids ? implode(',', $platform_order_ids) : '本次没有新订单插入!'));
		}
		return $platform_order_ids;
	}

	/**
	 * 处理一行的数据
	 */
	private function fmtrow($row){
		if(!isset($row['orderDetailBizDTO'], $row['orderDetailGoodsDTO'], $row['orderDetailGoodsDTO'])){
			return $this->seterr('订单 orderDetailBizDTO, orderDetailGoodsDTO 和 orderDetailGoodsDTO 不存在!');
		}
		$base 		= $row['orderDetailBizDTO'];
		$orderid 	= $base['orderId'];
		$status 	= $base['status'];
		// if($status == $this->changeStatus){//10是取消订单,订单取消需要加库存
		// 	$res 	= OrderProduct::where('order_id', $orderid)->get();
		// 	if($res){
		// 		Log::info('饿了么订单编号[' . $orderid . ']: 用户取消,执行退回库存!');
		// 		foreach($res as $item){//逐个商品退回库存
		// 			$item->rebackStocks();
		// 		}
		// 	}
		// }
		$orderStatusDesc 	= $base['statusDesc'];
		$store_id 	= $this->store->store_id;
		$itemcount 	= $row['orderDetailGoodsDTO']['goodsTotalNum'];
		$createTime = strtotime($base['createTime']);
		$platform_id=$this->store->platform->id;

		$tableSkuIds 	= [];
		if(isset($this->dbOrders[$orderid])){
			if($status == $this->changeStatus && $this->dbOrders[$orderid]->status != $status){//如果退单,则要同步加库存
				$this->dbOrders[$orderid]->status 			= -1;
				$this->dbOrders[$orderid]->orderStatusDesc 	= $orderStatusDesc;
				$this->dbOrders[$orderid]->origin_content	= json_encode($row, JSON_UNESCAPED_UNICODE);
				$this->dbOrders[$orderid]->save();
				$res 	= OrderProduct::where('order_id', $orderid)->get();
				if($res){
					Log::info('饿了么订单编号[' . $orderid . ']: 用户取消,执行退回库存!');
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
					// 'orderId_tm'		=> $row['orderId'],
					'platform_id'		=> $platform_id,
					'addtime'			=> time(),
					'origin_content'	=> json_encode($row, JSON_UNESCAPED_UNICODE),
				];

				foreach($row['orderDetailGoodsDTO']['goodsList'] as $item){
					$skuid 				= $item['skuId'] ?? $item['itemId'];
					$skuTableId 		= 0;
					if(isset($tableSkuIds[$skuid])){
						$skuTableId 	= $tableSkuIds[$skuid];
					}else{
						$row 			= Sku::where('sku_id', $skuid)->where('platform', $platform_id)->where('store_id', $store_id)->first();
						if($row){
							$tableSkuIds[$skuid] 	= $row->id;
							$skuTableId 			= $row->id;
						}
					}
					$this->addOp[] 		= [
						'order_id'			=> $orderid,
						'sku_id'			=> $item['ext']['storeAttr']['skuId'],
						'orderItemId'		=> $item['subOrderId'],
						'quantity'			=> $item['number'],
						'upc'				=> $item['ext']['storeAttr']['upcCode'],
						'storeId'			=> $store_id,
						'spec'				=> implode(',', array_column($item['ext']['propertyLabel'], 'detail')),
						'title'				=> $item['name'],
						'customSkuId'		=> $item['ext']['extCode'],
						'platform_id'		=> $platform_id,
						'itemSkuId'			=> $item['skuId'] ?? $item['itemId'],
						'sku_table_id'		=> $skuTableId,
						'createtime'		=> $createTime,
					];
				}
			}
		}
	}


	//提取订单列表的 orderid
	private function getOrderIds($data){
		$orderids 		= [];
		foreach($data as $item){
			$orderids[] 	= $item['orderDetailBizDTO']['orderId'];
		}
		return $orderids;
	}

	/**
	 * 提取数字
	 */
	private function price($val){
		preg_match_all('`[\d\.]+`', $val, $nn);
		return isset($nn[0][0]) ? $nn[0] : [''];
	}
}