<?php
namespace App\Takeaways\Elemes;

use App\Models\Order;
use App\Models\Store;
use App\Models\Platform;
use App\Models\ProductSku;
use App\Models\OrderProduct;
use Illuminate\Support\Facades\DB;
use App\Takeaways\Eleme;
use App\Takeaways\BaseFactory;
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
		$this->dbOrders = Order::whereIn('orderid', $orderIds)
							->where('platform_id', $store->platform->id)
							->where('store_id', $store->store_id)->pluck('id', 'orderid')->toArray();
		foreach($data as $item){
			$this->fmtrow($item);
			if(!$this->status){
				break;
			}
		}
	}

	public function render(){//返回的是product_skus表的id对应本次列表下单的总数量
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
					$ps 	= ProductSku::whereIn('itemSkuId', array_keys($skulessStocks))
										->where('platform_id', $this->store->platform->id)
										->where('storeId', $this->store->store_id)->get();

					foreach($ps as $item){
						if(isset($skulessStocks[$item->itemSkuId])){
							$stock 			= $skulessStocks[$item->itemSkuId];
							if($stock < 0){
								$stock 		= 0;
							}
							$this->opIds[$item->id] 	= $stock;
						}
					}
				}
			}, 3);
		}
		return $this->opIds;
	}

	/**
	 * 处理一行的数据
	 */
	private function fmtrow($row){
		if(!isset($row['orderDetailBizDTO'], $row['orderDetailGoodsDTO'], $row['orderDetailGoodsDTO'])){//22是退单待审核, 25是取消
			return $this->seterr('订单 orderDetailBizDTO, orderDetailGoodsDTO 和 orderDetailGoodsDTO 不存在!');
		}
		$base 		= $row['orderDetailBizDTO'];
		$orderid 	= $base['orderId'];
		$status 			= $base['status'];
		$orderStatusDesc 	= $base['statusDesc'];
		$store_id 	= $this->store->store_id;
		$itemcount 	= $row['orderDetailGoodsDTO']['goodsTotalNum'];
		$createTime = strtotime($base['createTime']);
		$platform_id=$this->store->platform->id;

		if(isset($this->dbOrders[$orderid])){
			if($status == $this->changeStatus && $dbOrders[$orderid]->orderStatus != $status){//如果退单,则要同步加库存
				$dbOrders[$orderid]->orderStatus 	= -1;
				$dbOrders[$orderid]->save();
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
				];

				foreach($row['orderDetailGoodsDTO']['goodsList'] as $item){
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
					];
				}
				// if(isset($this->stores[$store_id])){
				// 	$orderProducts 		= json_decode($this->stores[$store_id]['oob']->orderProducts($orderid), true);
				// 	if(!isset($orderProducts['data']['itemInfo'])){
				// 		return $this->seterr($orderid . ' 获取商品详情失败!');
				// 	}
				// 	try {
				// 		foreach($orderProducts['data']['itemInfo'] as $item){
				// 			$this->addOp[] 		= [
				// 				'order_id'			=> $orderid,
				// 				'sku_id'			=> $item['sku'],
				// 				'orderItemId'		=> $item['orderItemId'],
				// 				'quantity'			=> $item['orderQuantity'],
				// 				'upc'				=> $item['upc'],
				// 				'storeId'			=> $store_id,
				// 				'spec'				=> $item['spec'],
				// 				'title'				=> $item['skuName'],
				// 				'customSkuId'		=> $item['customSkuId'],
				// 			];
				// 		}
				// 	} catch (\Exception $e) {
				// 		return $this->seterr($e->getMessage());
				// 	}
				// }else{
				// 	return $this->seterr($store_id . ' 实例化失败!');
				// }
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