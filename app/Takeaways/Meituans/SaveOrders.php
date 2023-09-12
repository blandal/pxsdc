<?php
namespace App\Takeaways\Meituans;

use App\Models\Order;
use App\Models\Store;
use App\Models\Platform;
use App\Models\OrderProduct;
use Illuminate\Support\Facades\DB;
use App\Takeaways\Meituan;
use App\Takeaways\BaseFactory;
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

	public function render(){
		if($this->status === true){
			DB::transaction(function () {
				Order::insert($this->addOrder);
				OrderProduct::insert($this->addOp);
			}, 3);
		}
		return $this->status;
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
		$createTime = $row['createTime'];

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
					'orderId_tm'		=> $row['orderId'],
					'platform_id'		=> $this->platform,
					'addtime'			=> time(),
				];
				if(isset($this->stores[$store_id])){
					$orderProducts 		= json_decode($this->stores[$store_id]['oob']->orderProducts($orderid), true);
					if(!isset($orderProducts['data']['itemInfo'])){
						return $this->seterr($orderid . ' 获取商品详情失败!');
					}
					try {
						foreach($orderProducts['data']['itemInfo'] as $item){
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