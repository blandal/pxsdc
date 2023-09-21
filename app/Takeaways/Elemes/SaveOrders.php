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
class SaveOrders extends Eleme{
	use BaseFactory;
	private $changeStatus 	= 10;
	private $dbOrders 	= [];
	private $addOrder 	= [];
	private $addOp 		= [];
	private $data  		= [];
	private $store 		= null;
	private $opIds 		= [];
	public function __construct(array $data, Store $store){
		if(!isset($data[0])){
			if(!isset($data['data']['data']['mainOrderList'])){
				return $this->seterr('传入数据格式错误,不是一个数组或者没有 data 的 key');
			}
			$data 	= $data['data']['data']['mainOrderList'];
		}
		$this->store 	= $store;
		$this->data 	= $data;
		$orderIds 		= $this->getOrderIds();
		$tmps 			= Order::whereIn('orderid', $orderIds)->where('platform_id', $store->platform->id)->get();
		foreach($tmps as $item){
			$this->dbOrders[$item->orderid]	= $item;
		}
		// $this->dbOrders = Order::whereIn('orderid', $orderIds)
		// 					->where('platform_id', $store->platform->id)
		// 					->where('store_id', $store->store_id)->pluck('id', 'orderid');
		foreach($data as $item){
			$this->fmtrow($item);
			if(!$this->status){
				break;
			}
		}
	}

	public function render(){//返回的是product_skus表的id对应本次列表下单的总数量
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
	private function getOrderIds(){
		$orderids 		= [];
		foreach($this->data as $item){
			$orderids[] 	= $item['orderDetailBizDTO']['orderId'];
		}
		return $orderids;
	}
}