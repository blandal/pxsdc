<?php
/**
 * 美团-牵牛花 后台操作方法
 */
namespace App\Takeaways;

use App\Takeaways\Factory;
use QL\QueryList;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\Store;
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
	public function getProducts(int $page = 1, int $pagesize = 10){
		$this->method 	= (new \App\Takeaways\Meituans\GetProducts())
				->page($page)
				->pageSize($pagesize);
		$content 		= $this();
		$data           = json_decode($content, true);
        if(!$data){
            return '产品数据解析失败! ' . $content;
        }
        if(!Product::saveProduct($data, $this)){
            return implode("\r\n", $this->errs());
        }
        if(isset($data['code']) && $data['code'] == 0){
            return true;
        }
        return $data['msg'] ?? '错误!';
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
	 * 修改sku库存
	 * @return App\Takeaways\Meituans\ChangeStock
	 */
	public function changeStock(int $stock, ProductSku $productSku){
		$this->method 	= (new \App\Takeaways\Meituans\ChangeStock())
				->storeId($this->store->store_id)
				->spuId($productSku->spu_id)
				->skuStocks__0__skuId($productSku->sku_id)
				->skuStocks__0__stock($stock);
		$resp 	= $this();
		$resp 	= json_decode($resp, true);
		if(isset($resp['code']) && $resp['code'] == 0){
			return true;
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
		if($this->method->method == 'get'){
			$resp 	= $q->get($url, $this->method->args, ['headers' => $headers]);
		}else{
			$resp 	= $q->post($url, '', ['headers' => $headers, 'json' => $this->method->args]);
		}
		return $resp->getHtml();
	}

	/**
	 * 解析并保存平台商品信息
	 * @param $data 	平台返回的商品列表
	 * @return bool
	 */
	public function saveProducts(array $data) :bool{
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