<?php
/**
 * 此接口只做插入不执行更新
 */
namespace App\Takeaways\Elemes;

use App\Models\Store;
use App\Models\Product;
use App\Models\ProductSpu;
use App\Models\ProductSku;
use App\Takeaways\BaseFactory;
class SaveProducts{
	use BaseFactory;
	// private $status 	= true;
	// private $errmsg 	= [];
	private $platform 	= 0;
	private $dbstore 	= [];
	private $stores 	= [];
	private $addStores 	= [];
	private $addBases 	= [];
	private $addSpus 	= [];
	private $addSkus 	= [];
	public function __construct(array $data, $store){
		$this->store 		= $store;
		$this->platform 	= $store->platform->id;
		$this->stores 		= Store::getStoreId2Name($this->platform);
		$this->dbstore 		= $this->stores;
		// dd($data);
		if(!isset($data[0])){
			if(!isset($data['data']['data'])){
				return $this->seterr('传入数据格式错误,不是一个数组或者没有 data 的 key' . json_encode($data, JSON_UNESCAPED_UNICODE));
			}
			$data 	= $data['data']['data'];
		}
		try {
			foreach($data as $item){
				$this->fmtrow($item);
				if($this->status == false){
					break;
				}
			}
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage(), 1);
		}
	}

	public function render(){
		if($this->status === true){
			$addstore 	= array_diff_key($this->addStores, $this->dbstore);
			if(!empty($addstore)){
				Store::insert($addstore);
			}

			$spus 			= array_keys($this->addBases);
			$skus 			= array_keys($this->addSkus);
			$dbProducts 	= Product::whereIn('spu', $spus)->pluck('id', 'spu')->toArray();
			$diff 			= array_diff_key($this->addBases, $dbProducts);
			if(!empty($diff)){
				Product::insert($diff);
				$dbProducts 	= Product::whereIn('spu', $spus)->pluck('id', 'spu')->toArray();
			}

			$dbSpus 	= ProductSpu::where('platform_id', $this->platform)->whereIn('spu_id', $spus)->pluck('product_id', 'spu_id')->toArray();
			$diff 		= array_diff_key($this->addSpus, $dbSpus);
			if(!empty($diff)){
				foreach($diff as &$item){
					$item['product_id']		= $dbProducts[$item['spu_id']] ?? 0;
				}
				try {
					ProductSpu::insert($diff);
				} catch (\Exception $e) {
					dd($e->getMessage(), $diff);
				}
			}

			$dbSkus 	= ProductSku::where('platform_id', $this->platform)->whereIn('itemId', $skus)->pluck('itemId', 'itemId')->toArray();
			$diff 		= array_diff_key($this->addSkus, $dbSkus);
			if(!empty($diff)){
				foreach($diff as &$item){
					$item['product_id']		= $dbProducts[$item['spu_id']] ?? 0;
				}
				// dd($this->addSkus, $diff, '-----');
				ProductSku::insert($diff);
			}
		}
		return $this->status;
	}

	/**
	 * 处理一行的数据
	 */
	private function fmtrow($row){
		// dd($row);
		if(!isset($row['itemId'], $row['title'])){
			return $this->seterr('产品 itemId 和 title 不存在!');
		}
		$row['title']	= trim($row['title']);
		$this->store($row);
		// $this->spu($row);
		$this->sku($row);
	}

	/**
	 * 店铺信息,如果数据库中不存在,则加入待插入列表
	 */
	private function store($row){
		$storeId 		= (int)$row['storeId'];
		if(!isset($this->stores[$storeId])){
			$title 		= $row['shopName'];
			$this->addStores[$storeId] 	= [
				'platform_id'	=> $this->platform,
				'title'			=> $title,
				'store_id'		=> $storeId,
			];
			$this->stores[$storeId] 	= $title;
		}
	}

	/**
	 * spu 和 基础表 信息
	 */
	private function spu($row){
		$spu_id 	= $row['productId'];
		$image 		= $row['picUrl'];
		$title 		= $row['title'];

		$storeId 				= $row['storeId'];
		$category 				= $row['cateName'] ?? null;
		// $standerTypeDesc 		= $row['standerTypeDesc'] ?? null;
		$channelId 				= 0;
		$frontCategoryNamePath 	= null;
		$frontCategoryIdPath 	= null;
		$customSpuId 			= null;
		if(isset($row['channelSpu'])){
			$channel 	= $row['channelSpu'];
			$channelId 	= $channel['channelId'] ?? 0;
			$frontCategoryNamePath 	= $channel['frontCategories'][0]['frontCategoryNamePath'] ?? null;
			$frontCategoryIdPath 	= $channel['frontCategories'][0]['frontCategoryIdPath'] ?? null;
			$customSpuId 			= $channel['customSpuId'] ?? null;
		}

		$this->addBases[$spu_id]	= [
			'title'		=> $title,
			'spu'		=> $spu_id,
			'image'		=> $image,
		];
		$this->addSpus[$spu_id] 	= [
			'platform_id'		=> $this->platform,
			'title'				=> $title,
			'spu_id'			=> $spu_id,
			'store_id'			=> $storeId,
			'category'			=> is_array($category) ? json_encode($category) : $category,
			'channel_id'		=> $channelId,
			'standerTypeDesc'	=> $standerTypeDesc,
			'frontCategoryNamePath'	=> $frontCategoryNamePath,
			'frontCategoryId'	=> $frontCategoryIdPath,
			'status'			=> $row['channelSpu']['spuStatus'] ?? 0,
			'customSpuId'		=> $customSpuId,
		];
	}

	/**
	 * sku 信息,使用upc作为唯一sku
	 */
	private function sku($row){
		if(!isset($row['picUrl'])){
			return false;
		}

		if($row['itemSkuList']){
			foreach($row['itemSkuList'] as $item){
				$this->addSkus[$item['barcode']] 	= [
					'platform_id'	=> $this->platform,
					'spu_id'		=> $row['productId'],
					'sku_id'		=> $item['itemSkuId'],
					'upc'			=> $item['barcode'],
					'weight'		=> $item['itemWeight'] ?? 0,
					'spec'			=> $item['salePropertyList'][0]['valueText'],
					'sale_price'	=> $item['price'],
					'stocks'		=> $item['quantity'],
					'unit'			=> $row['stockUnit'] ?? null,
					'customSkuId'	=> $item['skuOuterId'],
					'storeId'		=> $row['storeId'],
					'title'			=> $row['title'],
					'itemId'		=> $row['itemId'],
					'isWeight'		=> $row['isWeight'],
					'weightType'	=> $row['weightType'],
					'productId'		=> $row['productId'],
					'itemSkuId'		=> $item['itemSkuId'],
					'propId'		=> $item['salePropertyList'][0]['propId'],
					'propText'		=> $item['salePropertyList'][0]['propText'],
					'many'			=> true,
				];
			}
		}else{
			$this->addSkus[$row['barCode']] 	= [
				'platform_id'	=> $this->platform,
				'spu_id'		=> $row['productId'],
				'sku_id'		=> $row['barCode'],
				'upc'			=> $row['barCode'],
				'weight'		=> $row['itemWeight'] ?? 0,
				'spec'			=> null,
				'sale_price'	=> $row['price'],
				'stocks'		=> $row['quantity'],
				'unit'			=> $row['stockUnit'] ?? null,
				'customSkuId'	=> $row['outId'],
				'storeId'		=> $row['storeId'],
				'title'			=> $row['title'],
				'itemId'		=> $row['itemId'],
				'isWeight'		=> $row['isWeight'],
				'weightType'	=> $row['weightType'],
				'productId'		=> $row['productId'],
				'itemSkuId'		=> null,
				'propId'		=> null,
				'propText'		=> null,
				'many'			=> false,
			];
		}
		// foreach($row['storeSkuList'] as $item){
		// 	if(!isset($item['skuId'])){
		// 		return $this->seterr('不存在skuId字段!');
		// 	}
		// 	$sku_id 	= $item['skuId'];
		// 	$this->addSkus[$sku_id] 	= [
		// 		'platform_id'	=> $this->platform,
		// 		'spu_id'		=> $row['spuId'],
		// 		'sku_id'		=> $sku_id,
		// 		'upc'			=> $item['upc'] ?? null,
		// 		'weight'		=> $item['weight'] ?? 0,
		// 		'spec'			=> $item['spec'] ?? null,
		// 		'sale_price'	=> 0,
		// 		'stocks'		=> 0.0,
		// 		'purchase_price'=> $item['masterPurchasePrice'] ?? 0.0,
		// 		'unit'			=> $item['unit'] ?? null,
		// 		'customSkuId'	=> null,
		// 		'storeId'		=> $item['storeId'],
		// 		'title'			=> $row['name'],
		// 	];
		// }
		// foreach($row['channelSpu']['channelSkuList'] as $item){
		// 	$sku_id 	= $item['skuId'];
		// 	if(isset($this->addSkus[$sku_id])){
		// 		$this->addSkus[$sku_id]['sale_price']		= $item['price'];
		// 		$this->addSkus[$sku_id]['stocks']			= $item['stock'];
		// 		$this->addSkus[$sku_id]['customSkuId']		= $item['customSkuId'];
		// 	}
		// }
	}

	public function checkProduct(){

	}

	// public function getError(){
	// 	return $this->errmsg;
	// }
	// private function seterr($msg){
	// 	$this->status 	= false;
	// 	$this->errmsg[]	= $msg;
	// 	return false;
	// }
}