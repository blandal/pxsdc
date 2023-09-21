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
class SaveOrders extends Meituan{
	use BaseFactory;
	// private $status 	= true;
	// private $errmsg 	= [];
	private $dbOrders 	= [];
	private $platform 	= 0;
	private $addOrder 	= [];
	private $addOp 		= [];
	private $stores 	= [];
	private $platformObj= [];
	private $changeStatus 	= 25;
	private $opIds 		= [];
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
		$tmps 				= Order::whereIn('orderid', $orderIds)->where('platform_id', $this->platform)->get();
		foreach($tmps as $item){
			$this->dbOrders[$item->orderid]	= $item;
		}
		// $this->dbOrders 	= Order::whereIn('orderid', $orderIds)->pluck('orderStatus', 'orderid')->toArray();
		$this->platformObj	= Platform::pluck('object', 'id')->toArray();
		$tmp 		= Store::select('platform_id', 'store_id', 'cookie')->get()->toArray();
		foreach($tmp as $item){
			if(!$item['cookie']){
				continue;
			}
			$item['oob']		= null;
			$oob 	= Store::getInstance($item['store_id'], $item['platform_id']);
			if($oob){
				$item['oob']	= $oob;
			}
			// if(isset($this->platformObj[$item['platform_id']])){
			// 	$item['oob'] 	= new $this->platformObj[$item['platform_id']]($item['cookie']);
			// }
			$this->stores[$item['store_id']]	= $item;
		}

		try {
			foreach($data as $item){
				$this->fmtrow($item);
				if(!$this->status){
					break;
				}
			}
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage(), 1);
		}
	}

	public function render(){//返回的是product_skus表的id对应本次列表下单的总数量
		$platform_order_ids 	= [];
		if($this->status === true){
			DB::transaction(function () {
				if(!empty($this->addOrder)){
					Order::insert($this->addOrder);
				}
				if(!empty($this->addOp)){
					OrderProduct::insert($this->addOp);
					$skulessStocks 	= [];
					foreach($this->addOp as $item){
						if(!isset($skulessStocks[$item['sku_id']])){
							$skulessStocks[$item['sku_id']] 	= $item['quantity'];
						}else{
							$skulessStocks[$item['sku_id']] 	+= $item['quantity'];
						}
					}
					$ps 	= Sku::whereIn('sku_id', array_keys($skulessStocks))
										->where('platform', $this->platform)
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
			Log::info('[美团]订单id: ' . ($platform_order_ids ? implode(',', $platform_order_ids) : '本次没有新订单插入!'));
		}
		return $platform_order_ids;
	}

	/**
	 * 处理一行的数据
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