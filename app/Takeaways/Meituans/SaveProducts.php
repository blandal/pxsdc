<?php
/**
 * 此接口只做插入不执行更新
 */
namespace App\Takeaways\Meituans;

use App\Models\Store;
use App\Models\Pro;
use App\Models\Sku;
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
	private $dbSpus 	= [];
	private $addSpus 	= [];
	private $addSkus 	= [];
	private $upcTable 	= [];
	private $skusTable	= [];
	public function __construct(array $data, Store $store){
		// dd($data);
		if(!isset($data[0])){
			if(!isset($data['data']['list'])){
				return $this->seterr('传入数据格式错误,不是一个数组或者没有 data 的 key');
			}
			$data 	= $data['data']['list'];
		}


		$titles 			= array_column($data, 'name');
		$titleObj 			= [];
		$tmp 				= Pro::whereIn('title', $titles)->get();
		foreach($tmp as $item){
			$titleObj[$item->title]	= $item;
		}

		$skusIds 			= [];
		foreach($data as $item){
			foreach($item['storeSkuList'] as $val){
				$skusIds[] 	= $val['skuId'];
			}
		}

		$tmp 				= Sku::where('platform', $store->platform->id)
									->where('store_id', $store->store_id)
									->whereIn('sku_id', $skusIds)->get();
		$skus 				= [];//数据库已存在的sku
		foreach($tmp as $item){
			$skus[$item->sku_id] 	= $item;
		}

		$waitAdd 		= [];
		foreach($data as $row){
			$title 		= $row['name'];
			// if(strpos($title, '高弹加大可调节孕妇立体托腹包芯丝孕妇裤 1条') !== false){
			// 	dd($row, $skus);
			// }
			$cate1 				= '';
			$cate2 				= '';
			if(isset($row['channelSpuList'][0]['frontCategories'][0]['frontCategoryNamePath'])){
				$tmp 			= explode('>', html_entity_decode($row['channelSpuList'][0]['frontCategories'][0]['frontCategoryNamePath']));
				$cate1 			= $tmp[0];
				$cate2 			= $tmp[1] ?? '';
			}
			if(!isset($titleObj[$title])){
				$tmp 	= new Pro;
				$tmp->title 		= $title;
				$tmp->cate1 		= $cate1;
				$tmp->cate2 		= $cate2;
				$tmp->images 		= $row['mainImage'];
				$tmp->save();
				$titleObj[$title] 	= $tmp;
			}elseif((!$titleObj[$title]->cate1 || !$titleObj[$title]->cate2) && ($cate1 || $cate2)){
				$titleObj[$title]->cate1 	= $cate1;
				$titleObj[$title]->cate2 	= $cate2;
			}

			$proid 		= $titleObj[$title]->id;
			$spu_id 	= $row['spuId'];

			$themsku 	= [];
			foreach($row['storeSkuList'] as $item){
				$sku_id 	= $item['skuId'];
				// if($sku_id == '1680783353731117068'){
				// 	dd($row, $skus);
				// }
				$themsku[$sku_id] 	= [
					'platform'	=> $store->platform->id,
					'store_id'	=> $item['storeId'],
					'sku_id'	=> $sku_id,
					'pro_id'	=> $proid,
					'spu_id'	=> $spu_id,
					'price'		=> 0,
					'stocks'	=> 0,
					'upc'		=> $item['upc'],
					'weight'	=> $item['weight'],
					'title'		=> $title,
					'name'		=> $item['spec'],
					'customid'	=> '',
					'status'	=> $row['status'],
				];
			}
			foreach($row['channelSpu']['channelSkuList'] as $item){
				$sku_id 	= $item['skuId'];
				if(isset($themsku[$sku_id])){
					$themsku[$sku_id]['price']		= $item['price'];
					$themsku[$sku_id]['stocks']		= $item['stock'];
					$themsku[$sku_id]['customid']	= $item['customSkuId'];
				}
			}
			foreach($themsku as $sku_id => $item){
				if(isset($skus[$sku_id])){
					$this->updateSku($skus[$sku_id], $item['stocks'], $item['status'], $proid);
				}else{
					$waitAdd[] 	= $item;
				}
			}
		}
		Sku::insert($waitAdd);
		return true;




		$this->store 		= $store;
		$this->platform 	= $store->platform->id;
		$this->stores 		= Store::getStoreId2Name($this->platform);
		$this->dbstore 		= $this->stores;
		

		//解析出spu
		$spus 			= array_column($data, 'spuId');
		$this->dbSpus 	= ProductSpu::whereIn('spu_id', $spus)->pluck('product_id', 'spu_id')->toArray();


		//解析出所有的upc
		$upcs 		= [];
		$skus 		= [];
		foreach($data as $item){
			foreach($item['storeSkuList'] as $val){
				if($val['upc']){
					$upcs[] 	= $val['upc'];
				}
				if($val['skuId']){
					$skus[] 	= $val['skuId'];
				}
			}
		}
		if(!empty($upcs)){
			$this->upcTable 	= ProductSku::whereIn('upc', $upcs)->pluck('product_id', 'upc')->toArray();
		}

		//解析出skus
		$this->skusTable		= ProductSku::whereIn('sku_id', $skus)->pluck('product_id', 'sku_id')->toArray();


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

	/**
	 * 更新sku,目前仅支持更新库存和状态
	 */
	private function updateSku(Sku $dbrow, $quality, $status, $proid){
		$cansave 	= false;
		if($dbrow->stocks != $quality){
			$dbrow->stocks 			= $quality;
			$dbrow->stockupdate 	= time();
			$cansave 				= true;
		}
		if($dbrow->status != $status){
			$dbrow->status 	= $status;
			$cansave 		= true;
		}
		if($dbrow->pro_id != $proid){
			$dbrow->pro_id 	= $proid;
			$cansave 		= true;
		}
		
		if($cansave){
			$dbrow->save();
		}
	}

	public function render(){
		if($this->status === true){
			try {
				if(!empty($this->addSpus)){
					ProductSpu::insert($this->addSpus);
				}
				if(!empty($this->addSkus)){
					ProductSku::insert($this->addSkus);
				}
			} catch (\Exception $e) {
				dd($e->getMessage(), $this->addSpus, $this->skusTable, $this->addSkus);
			}
			
			// $addstore 	= array_diff_key($this->addStores, $this->dbstore);
			// if(!empty($addstore)){
			// 	Store::insert($addstore);
			// }

			// $spus 			= array_keys($this->addBases);
			// $skus 			= array_keys($this->addSkus);
			// $dbProducts 	= Product::whereIn('spu', $spus)->pluck('id', 'spu')->toArray();
			// $diff 			= array_diff_key($this->addBases, $dbProducts);
			// if(!empty($diff)){
			// 	Product::insert($diff);
			// 	$dbProducts 	= Product::whereIn('spu', $spus)->pluck('id', 'spu')->toArray();
			// }

			// $dbSpus 	= ProductSpu::where('platform_id', $this->platform)->whereIn('spu_id', $spus)->pluck('product_id', 'spu_id')->toArray();
			// $diff 		= array_diff_key($this->addSpus, $dbSpus);
			// if(!empty($diff)){
			// 	foreach($diff as &$item){
			// 		$item['product_id']		= $dbProducts[$item['spu_id']] ?? 0;
			// 	}
			// 	try {
			// 		ProductSpu::insert($diff);
			// 	} catch (\Exception $e) {
			// 		dd($e->getMessage(), $diff);
			// 	}
			// }

			// $dbSkus 	= ProductSku::where('platform_id', $this->platform)->whereIn('sku_id', $skus)->pluck('sku_id', 'sku_id')->toArray();
			// $diff 		= array_diff_key($this->addSkus, $dbSkus);
			// if(!empty($diff)){
			// 	foreach($diff as &$item){
			// 		$item['product_id']		= $dbProducts[$item['spu_id']] ?? 0;
			// 	}
			// 	// dd($this->addSkus, $diff, '-----');
			// 	ProductSku::insert($diff);
			// }
		}
		return $this->status;
	}

	/**
	 * 处理一行的数据
	 */
	private function fmtrow($row){
		if(!isset($row['spuId'], $row['name'])){
			return $this->seterr('产品 spu 和 name 不存在!');
		}
		$row['name']	= trim($row['name']);
		$storeId 		= (int)$row['store']['poiId'];
		$spu_id 	= $row['spuId'];
		$image 		= $row['imageUris'][0];
		$title 		= $row['name'];

		$category 				= $row['category'] ?? null;
		$standerTypeDesc 		= $row['standerTypeDesc'] ?? null;
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

		$product_id 	= 0;
		$upc 			= array_column($row['storeSkuList'], 'upc');
		foreach($upc as $u){
			if(isset($this->upcTable[$u])){
				$product_id 	= $this->upcTable[$u];
			}
		}
		if($product_id < 1){
			$product 	= new Product;
			$product->image 	= $image;
			$product->title 	= $title;
			$product->spu 		= $spu_id;
			$product->save();
			$product_id 		= $product->id;
		}
		if($product_id < 1){
			return $this->seterr('空的!');
		}

		if(!isset($this->dbSpus[$spu_id])){
			$this->addSpus[$spu_id] 	= [
				'product_id'		=> $product_id,
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
				'images'			=> $image,
			];
		}


		// $this->store($row);
		// $this->spu($row);
		$this->sku($row, $product_id);
	}

	/**
	 * 店铺信息,如果数据库中不存在,则加入待插入列表
	 */
	private function store($row){
		$storeId 		= (int)$row['store']['poiId'];
		if(!isset($this->stores[$storeId])){
			$title 		= $row['store']['poiName'];
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
		$spu_id 	= $row['spuId'];
		$image 		= $row['imageUris'][0];
		$title 		= $row['name'];

		$storeId 				= $row['store']['poiId'] ?? null;
		$category 				= $row['category'] ?? null;
		$standerTypeDesc 		= $row['standerTypeDesc'] ?? null;
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
	 * sku 信息
	 */
	private function sku($row, $product_id){
		if(!isset($row['storeSkuList'], $row['channelSpu'])){
			return false;
		}
		foreach($row['storeSkuList'] as $item){
			if(!isset($item['skuId'])){
				return $this->seterr('不存在skuId字段!');
			}
			$sku_id 	= $item['skuId'];
			if(isset($this->skusTable[$sku_id])){
				continue;
			}
			$this->addSkus[$sku_id] 	= [
				'platform_id'	=> $this->platform,
				'spu_id'		=> $row['spuId'],
				'sku_id'		=> $sku_id,
				'upc'			=> $item['upc'] ?? null,
				'weight'		=> $item['weight'] ?? 0,
				'spec'			=> $item['spec'] ?? null,
				'sale_price'	=> 0,
				'stocks'		=> 0.0,
				'purchase_price'=> $item['masterPurchasePrice'] ?? 0.0,
				'unit'			=> $item['unit'] ?? null,
				'customSkuId'	=> null,
				'storeId'		=> $item['storeId'],
				'title'			=> $row['name'],
				'product_id'	=> $product_id,
			];
		}
		foreach($row['channelSpu']['channelSkuList'] as $item){
			$sku_id 	= $item['skuId'];
			if(isset($this->addSkus[$sku_id])){
				$this->addSkus[$sku_id]['sale_price']		= $item['price'];
				$this->addSkus[$sku_id]['stocks']			= $item['stock'];
				$this->addSkus[$sku_id]['customSkuId']		= $item['customSkuId'];
			}
		}
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