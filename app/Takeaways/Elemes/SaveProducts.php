<?php
/**
 * 此接口只做插入不执行更新
 */
namespace App\Takeaways\Elemes;

use App\Models\Store;
// use App\Models\Product;
// use App\Models\ProductSpu;
// use App\Models\ProductSku;

use App\Models\Pro;
use App\Models\Sku;
use Illuminate\Support\Facades\Log;

use App\Takeaways\BaseFactory;
class SaveProducts{
	use BaseFactory;
	private $platform 	= 0;
	private $addStores 	= [];
	private $addBases 	= [];
	private $addSpus 	= [];
	private $addSkus 	= [];
	private $spus 		= [];
	private $dbspus 	= [];
	private $dbskus 	= [];
	private $skus 		= [];
	private $dbupcs 	= [];
	public $nums 		= 0;
	public function __construct(array $data, Store $store){
		$this->store 		= $store;
		$this->platform 	= $store->platform->id;
		if(!isset($data[0])){
			if(!isset($data['data']['data'])){
				return $this->seterr('传入数据格式错误,不是一个数组或者没有 data 的 key' . json_encode($data, JSON_UNESCAPED_UNICODE));
			}
			$data 	= $data['data']['data'];
		}

		$titles 			= array_column($data, 'title');
		$titleObj 			= [];
		$tmp 				= Pro::whereIn('title', $titles)->get();
		foreach($tmp as $item){
			$titleObj[$item->title]	= $item;
		}

		$tmp 				= Sku::where('platform', $this->store->platform->id)
									->where('store_id', $this->store->store_id)
									->whereIn('spu_id', array_column($data, 'itemId'))->orderBy('id')->get();
		$skus 				= [];//数据库已存在的sku
		foreach($tmp as $item){
			$skus[$item->spu_id][$item->sku_id] 	= $item;
		}

		$waitAdd 		= [];
		foreach($data as $row){
			$this->nums++;
			$title 		= $row['title'];
			$cate1 		= $row['customCategoryParentName'];
			$cate2		= $row['customCategoryName'];
			if(!isset($titleObj[$title])){
				$tmp 	= new Pro;
				$tmp->title 		= $title;
				$tmp->cate1 		= $cate1;
				$tmp->cate2 		= $cate2;
				$tmp->images 		= $row['picUrl'];
				$tmp->save();
				$titleObj[$title] 	= $tmp;
			}elseif((!$titleObj[$title]->cate1 || !$titleObj[$title]->cate2) && ($cate1 || $cate2)){
				$titleObj[$title]->cate1 	= $cate1;
				$titleObj[$title]->cate2 	= $cate2;
			}

			$proid 		= $titleObj[$title]->id;
			$spu_id 	= $row['itemId'];
			$sku_id 	= '';

			$skuarr 	= [
				'platform'	=> $this->store->platform->id,
				'store_id'	=> $row['storeId'],
				'sku_id'	=> $spu_id,
				'pro_id'	=> $proid,
				'spu_id'	=> $spu_id,
				'price'		=> $row['price'] ?? 0,
				'stocks'	=> $row['quantity'],
				'upc'		=> $row['barCode'],
				'weight'	=> $row['itemWeight'],
				'title'		=> $title,
				'name'		=> '',
				'customid'	=> $row['outId'],
				'other'		=> '',
				'status'	=> $row['status'],
				'isWeight'	=> $row['isWeight'],
				'weightType'=> $row['weightType'],
			];
			if($row['hasSku'] == true){
				foreach($row['itemSkuList'] as $item){
					if(isset($skus[$spu_id][$item['itemSkuId']])){
						$this->updateSku($skus[$spu_id][$item['itemSkuId']], $item['quantity'], $row['status'], $proid, json_encode($item, JSON_UNESCAPED_UNICODE), $item['barCode']);
					}else{
						$tmp 	= $skuarr;
						$tmp['other']	= json_encode($item, JSON_UNESCAPED_UNICODE);
						$tmp['sku_id']	= $item['itemSkuId'];
						$tmp['price']	= $item['price'];
						$tmp['weight']	= $item['itemWeight'];
						$tmp['stocks']	= $item['quantity'];
						$tmp['upc']		= $item['barcode'];
						$tmp['customid']= $item['skuOuterId'];
						$tmp['name']	= $item['salePropertyList'][0]['valueText'];
						$waitAdd[] 	= $tmp;
					}
				}
			}else{
				if(isset($skus[$spu_id][$spu_id])){
					$this->updateSku($skus[$spu_id][$spu_id], $skuarr['stocks'], $skuarr['status'], $proid, null, $row['barCode']);
				}else{
					$waitAdd[] 	= $skuarr;
				}
			}
		}
		Sku::insert($waitAdd);
		return $this->nums;






		$this->spus 		= array_column($data, 'itemId');
		$this->dbspus 		= $this->dbspus($data);
		$this->getDbskus($data);

		// try {
			foreach($data as $item){
				if(!isset($item['itemId'])){
					continue;
				}
				$this->fmtrow($item);
				if($this->status == false){
					break;
				}
			}
		// } catch (\Exception $e) {
		// 	throw new \Exception($e->getMessage(), 1);
		// }
	}

	/**
	 * 更新sku,目前仅支持更新库存和状态
	 */
	private function updateSku(Sku $dbrow, $quality, $status, $proid, $other = null, $upc = null){
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
		if($other){
			if(md5($other) != md5($dbrow->other)){
				$dbrow->other 	= $other;
				$cansave 		= true;
			}
		}
		if($upc && $dbrow->upc != $upc){
			$dbrow->upc 	= $upc;
			$cansave 		= true;
		}
		if($cansave){
			$dbrow->save();
		}
	}
















	//获取所有的spuid
	private function dbspus($data){
		$arr 	= ProductSpu::whereIn('spu_id', $this->spus)->where('platform_id', $this->store->platform->id)->where('store_id', $this->store->store_id)->get();
		$spus 	= [];
		foreach($arr as $item){
			$spus[$item->spu_id]	= $item;
		}
		return $spus;
	}

	//获取数据库skus
	private function getDbskus($data){
		$this->skus 		= $this->skus($data);
		$arr 				= ProductSku::whereIn('upc', $this->skus)->where('platform_id', $this->platform)->where('storeId', $this->store->store_id)->get();
		foreach($arr as $item){
			$this->dbskus[$item->upc]	= $item;
		}
		$this->dbupcs 		= ProductSku::whereIn('upc', $this->skus)->pluck('product_id', 'upc')->toArray();
	}

	//获取所有的sku
	private function skus($data){
		$upcs 			= array_column($data, 'barCode');
		$arr 			= [];
		foreach($upcs as $item){
			if(strpos($item, ',') !== false){
				$tmp 	= explode(',', $item);
				foreach($tmp as $val){
					$arr[] 	= $val;
				}
			}else{
				$arr[] 	= $item;
			}
		}
		return $arr;
	}

	public function render(){
		// dd($this->status);
		// if($this->status === true){
		// 	if(!empty($this->addSpus)){
		// 		ProductSpu::insert($this->addSpus);
		// 	}
		// 	if(!empty($this->addSkus)){
		// 		ProductSku::insert($this->addSkus);
		// 	}
		// }

		if($this->status == false){
			// dd($this->errmsg);
			Log::error('饿了么商品同步失败: ' . implode('---', $this->errmsg));
			return false;
		}
		return $this->nums;
		// return $this->status == true ? $this->nums : false;
	}

	/**
	 * 处理一行的数据
	 */
	private function fmtrow($row){
		if(!isset($row['title'])){
			return $this->seterr('产品 title 不存在!');
		}
		$row['title']	= trim($row['title']);

		$spu_id 	= $row['itemId'];
		$image 		= $row['picUrl'];
		$title 		= $row['title'];
		$hasMany 	= $row['hasSku'] ?? false;
		$hasMany 	= $hasMany ? 1 : 0;
		$storeId 				= $row['storeId'];
		$category 				= $row['cateName'] ?? null;
		$customSpuId 			= $row['outId'] ?? null;
		$status 				= $row['status'] ?? 0;
		$upc 		= explode(',', $row['barCode'])[0];

		//通过 upc 反推出product.如果没有product,则添加
		$product_id 			= 0;
		if(isset($this->dbupcs[$upc])){
			$product_id 	= $this->dbupcs[$upc];
		}else{
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


		//处理spu
		$dbHasMany 		= 0;
		if(isset($this->dbspus[$spu_id])){
			if($this->dbspus[$spu_id]->product_id != $product_id){
				$product_id 	= $this->dbspus[$spu_id]->product_id;
				// return $this->seterr($spu_id . ' 的 product_id 不一致!');
			}
			if($this->dbspus[$spu_id]->manysku != $hasMany){//如果更改了sku
				$this->dbspus[$spu_id]->manysku 	= $hasMany;
				$this->dbspus[$spu_id]->save();//更改spu表的many属性,要删除sku表的记录
				ProductSku::where('spu_id', $spu_id)->where('storeId', $storeId)->where('platform_id', $this->store->platform->id)->update(['many' => $this->dbspus[$spu_id]->manysku]);
			}
		}else{
			$this->addSpus[] 	= [
				'platform_id'		=> $this->platform,
				'title'				=> $title,
				'spu_id'			=> $spu_id,
				'store_id'			=> $storeId,
				'category'			=> is_array($category) ? json_encode($category) : $category,
				'status'			=> $row['channelSpu']['spuStatus'] ?? 0,
				'customSpuId'		=> $customSpuId,
				'product_id'		=> $product_id,
				'manysku'			=> $hasMany,
				'images'			=> $image,
			];
		}

		$skubaseArr 	= [
			'platform_id'		=> $this->platform,
			'spu_id'			=> $spu_id,
			'sku_id'			=> $upc,
			'upc'				=> $row['barCode'],
			'weight'			=> $row['itemWeight'] ?? 0,
			'spec'				=> null,
			'sale_price'	=> $row['price'] ?? 0,
			'stocks'		=> $row['quantity'] ?? 0,
			'unit'			=> $row['stockUnit'] ?? null,
			'customSkuId'	=> $row['outId'],
			'storeId'		=> $row['storeId'],
			'title'			=> $row['title'],
			'itemId'		=> $row['itemId'],
			'isWeight'		=> $row['isWeight'],
			'weightType'	=> $row['weightType'],
			'productId'		=> $row['productId'],
			'itemSkuId'		=> $spu_id,
			'propId'		=> $row['productId'],
			'propText'		=> null,
			'many'			=> 0,
			'params'		=> null,
			'product_id'	=> $product_id,
		];
		if($hasMany){
			foreach($row['itemSkuList'] as $item){
				$upcc 			= $item['barcode'];
				if(isset($this->dbskus[$upcc])){
					if($this->dbskus[$upcc]->many != $hasMany){//如果之前是单sku改为现在的多sku
						$this->dbskus[$upcc]->many 		= 1;
						$this->dbskus[$upcc]->params 	= json_encode($item);
						$this->dbskus[$upcc]->itemSkuId = $item['itemSkuId'];
						$this->dbskus[$upcc]->save();
					}
				}else{
					$arrr 		= $skubaseArr;
					$arrr['itemSkuId']		= $item['itemSkuId'];
					$arrr['propId']			= $item['salePropertyList'][0]['propId'];
					$arrr['propText']		= $item['salePropertyList'][0]['propText'];
					$arrr['sku_id']			= $upcc;
					$arrr['upc']			= $upcc;
					$arrr['weight']			= $item['itemWeight'] ?? 0;
					$arrr['spec']			= $item['salePropertyList'][0]['valueText'];
					$arrr['sale_price']		= $item['price'];
					$arrr['stocks']			= $item['quantity'];
					$arrr['customSkuId']	= $item['skuOuterId'];
					$arrr['params'] 		= json_encode($item);
					$arrr['many']			= 1;
					$this->addSkus[] 		= $arrr;
				}
			}
		}elseif(isset($this->dbskus[$upc])){
			if($this->dbskus[$upc]->many == 1){
				$this->dbskus[$upc] 	= 0;
				$this->dbskus[$upc]->save();
			}
		}else{
			$this->addSkus[] 	= $skubaseArr;
		}
	}
}