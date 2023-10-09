<?php
/**
 * 美团-牵牛花 后台操作方法
 */
namespace App\Takeaways;

use App\Takeaways\Factory;
use QL\QueryList;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Store;
use Illuminate\Support\Facades\Log;
use App\Models\LogStock;
class Meituan implements Factory{
	private $domain 	= 'https://qnh.meituan.com/api/v1/';
	private $store 		= null;
	protected $cookie 	= null;
	protected $method 	= null;
	protected $url 		= null;
	protected $args 	= null;
	protected $headers 	= [];
	/**
	 * 初始化时必须传入美团登录的cookie,此对象不会自动获取cookie.
	 * cookie 格式是一个数组 ['key' => $value] 格式
	 */
	public function __construct(Store $store){
		$this->store 	= $store;
		$this->cookie 	= $store->cookie;
		return $this;
	}

	public function getPlatform(){
		return $this->store->platform;
	}


	/**
	 * 获取产品列表
	 * 请使用链式调用设置请求参数,调用方法为牵牛花此接口的参数名称,复制参数名称调用即可
	 * @return App\Takeaways\Meituans\GetProducts
	 */
	public function getProducts(int $page = 1, int $pagesize = 10, $title = null){
		$this->method 	= (new \App\Takeaways\Meituans\GetProducts())
				->page($page)
				->pageSize($pagesize);
		if($title){
			$this->method->name($title);
		}
		$content 		= $this();
		$data           = json_decode($content, true);
        if(!$data){
            return '产品数据解析失败! ' . $content;
        }
        $nums 		= Product::saveProduct($data, $this);
        if($nums === false){
            return implode("\r\n", $this->errs());
        }
        if(isset($data['code']) && $data['code'] == 0){
            return $nums;
        }
        return $data['msg'] ?? '错误!';
	}

	public function getProductRow(Sku $sku){//同步单个产品
		$this->method 	= (new \App\Takeaways\Meituans\GetProductRow())
				->poiId($sku->store_id)
				->skuIdList__0($sku->sku_id);
		$resp 			= $this();
		$arr 			= json_decode($resp, true);
		if(!$arr){
			Log::error('[美团]同步失败!' . $resp);
			return false;
		}

		$waitAdd 		= [];
		if(isset($arr['data']['list'][0])){
			$row 		= $arr['data']['list'][0];
			$tmp 		= Sku::where('pro_id', $sku->pro_id)->where('platform', $sku->platform)->where('store_id', $sku->store_id)->get();
			$originSkus	= [];
			foreach($tmp as $item){
				$originSkus[$item->sku_id] 	= $item;
			}

			$cate1 				= '';
			$cate2 				= '';
			if(isset($row['channelSpuList'][0]['frontCategories'][0]['frontCategoryNamePath'])){
				$tmp 			= explode('>', html_entity_decode($row['channelSpuList'][0]['frontCategories'][0]['frontCategoryNamePath']));
				$cate1 			= $tmp[0];
				$cate2 			= $tmp[1] ?? '';
			}

			$skku 				= [];
			foreach($row['channelSpu']['channelSkuList'] as $item){
				$tmpsku 		= $item['skuId'];
				$skku[$tmpsku] 	= [
					'price'		=> $item['price'],
					'stocks'	=> $item['stock'],
					'customid'	=> $item['customSkuId'],
				];
			}

			$title 				= $row['name'];
			foreach($row['storeSkuList'] as $item){
				$skuid 		= $item['skuId'];
				if(isset($originSkus[$skuid]) && $originSkus[$skuid]){
					$skuRow 	= $originSkus[$skuid];
					if($skuRow->stocks != $skku[$skuid]['stocks']){
						LogStock::insert([
			                'remark'    => '牵牛花库存不一致,更新本地库存.',
			                'addtime'   => time(),
			                'userid'    => 0,
			                'skuids'    => $skuRow->id,
			                'content'   => $skuRow->stocks . '->' . $skku[$skuid]['stocks'],
			                'take_time' => 0,
			            ]);
					}else{
						log::error('牵牛花 ' . $skuid . ' 不存在!, 更新本地库存失败!');
					}
					unset($originSkus[$skuid]);
					$skuRow->upc 		= $item['upc'];
					$skuRow->weight 	= $item['weight'];
					$skuRow->title 		= $title;
					$skuRow->name 		= $item['spec'];
					$skuRow->status 	= $row['status'];
					if(isset($skku[$skuid])){
						foreach($skku[$skuid] as $k => $v){
							$skuRow->{$k} 	= $v;
						}
					}
					$skuRow->stockupdate 	= time();
					$skuRow->save();
				}else{
					$tmp 	= [
						'platform'	=> $sku->platform,
						'store_id'	=> $sku->store_id,
						'sku_id'	=> $skuid,
						'pro_id'	=> $sku->pro_id,
						'spu_id'	=> $sku->pro_id,
						'price'		=> $skku[$skuid]['price'] ?? 0,
						'stocks'	=> $skku[$skuid]['stocks'] ?? 0,
						'upc'		=> $item['upc'],
						'weight'	=> $item['weight'],
						'title'		=> $title,
						'name'		=> $item['spec'],
						'customid'	=> $skku[$skuid]['customid'] ?? null,
						'status'	=> $row['status'],
						'stockupdate'	=> time(),
					];
					$waitAdd[] 	= $tmp;
				}
			}
			if(!empty($waitAdd)){
				Sku::insert($waitAdd);
			}
			if(!empty($originSkus)){
				foreach($originSkus as $item){
					$item->delete();
				}
			}
		}else{
			$msg 		= $arr['msg'] ?? '返回错误!';
			Log::error('[美团]同步商品:' . $msg);
			return $msg;
		}
	}

	/**
	 * 获取订单列表
	 */
	public function getOrders(int $page = 1, int $pagesize = 10){
		$this->method 	= (new \App\Takeaways\Meituans\GetOrders())
				->page($page)
				->pageSize($pagesize);
		return $this();
	}

	/**
	 * 美团特有
	 */
	public function getOrderInfo($channelId, $orderId){
		$this->method 	= (new \App\Takeaways\Meituans\getOrderInfo())
				->channelId($channelId)
				->orderId($orderId);
		return $this();
	}

	/**
	 * 修改sku库存
	 * @return App\Takeaways\Meituans\ChangeStock
	 */
	public function changeStock(int $stock, Sku $productSku){
		$this->method 	= (new \App\Takeaways\Meituans\ChangeStock())
				->storeId($this->store->store_id)
				->spuId($productSku->spu_id)
				->skuStocks__0__skuId($productSku->sku_id)
				->skuStocks__0__stock($stock);
		try {
			$str 		= $this();
		} catch (\Exception $e) {
			Log::error('美团: ' . $e->getMessage());
		}
		$resp 	= json_decode($str, true);
		if(isset($resp['code']) && $resp['code'] == 0){
			return true;
		}else{
			Log::debug('修改库存失败:' . $str);
		}
		return false;
	}

	/**
	 * 获取订单商品详情
	 * @return App\Takeaways\Meituans\ChangeStock
	 */
	public function orderProducts(string $orderid){
		$this->method 	= (new \App\Takeaways\Meituans\GetOrderProducts())
				->orderId($orderid);
		return $this();
	}

	/**
	 * 发起请求并返回结果
	 * @return string
	 */
	public function __invoke(){
		if(!$this->cookie){
			return '请在实例化的时候传入 cookie!';
		}
		if(!$this->method || !isset($this->method->uri, $this->method->args) || !$this->method->uri){
			return '参数不完整!';
		}
		if(method_exists($this->method, 'check')){
			$res 	= call_user_func_array([$this->method, 'check'], []);
			if($res !== true){
				return $res;
			}
		}

		$q 		= QueryList::getInstance();
		$headers 	= [
			'Cookie'    => implode(';', $this->cookie),
			'Accept'    => 'application/json, text/plain, */*',
			'Content-Type'  => 'application/json;charset=UTF-8',
			'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
		];
		$url 		= $this->domain . ltrim($this->method->uri, '/');
		// dd($url, $this->method->args, $headers);
		if($this->method->method == 'get'){
			$resp 	= $q->get($url, $this->method->args, ['headers' => $headers]);
		}else{
			$resp 	= $q->post($url, '', ['headers' => $headers, 'json' => $this->method->args]);
		}
		// dd($resp->getHtml(), $this->method->args);
		return $resp->getHtml();
	}

	/**
	 * 解析并保存平台商品信息
	 * @param $data 	平台返回的商品列表
	 * @return bool
	 */
	public function saveProducts(array $data){
		$this->method 	= new \App\Takeaways\Meituans\SaveProducts($data, $this->store);
		return $this->method->render();
	}

	/**
	 * 解析并保存平台订单信息
	 * @param $data 	平台返回的商品列表
	 * @return bool
	 */
	public function saveOrders(array $data) :array{
		$this->method 	= new \App\Takeaways\Meituans\SaveOrders($data, $this->store);
		return $this->method->render();
	}

	public function errs(){
		return $this->method->getError();
	}

	public function getStore(){
		return $this->store;
	}
}