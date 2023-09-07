<?php
/**
 * 美团-牵牛花 后台操作方法
 */
namespace App\Takeaways;

use App\Takeaways\Factory;
use QL\QueryList;
use App\Models\Product;
class Meituan implements Factory{
	private $domain 	= 'https://qnh.meituan.com/api/v1/';
	protected $cookie 	= null;
	protected $method 	= null;
	protected $url 		= null;
	protected $args 	= null;
	protected $headers 	= [];
	/**
	 * 初始化时必须传入美团登录的cookie,此对象不会自动获取cookie.
	 * cookie 格式是一个数组 ['key' => $value] 格式
	 */
	public function __construct(array $cookie = []){
		if($cookie){
			$this->cookie 	= $cookie;
		}
		return $this;
	}


	/**
	 * 获取产品列表
	 * 请使用链式调用设置请求参数,调用方法为牵牛花此接口的参数名称,复制参数名称调用即可
	 * @return App\Takeaways\Meituans\GetProducts
	 */
	public function getProducts(int $page = 1, int $pagesize = 10, int $platform_id){
		$this->method 	= (new \App\Takeaways\Meituans\GetProducts())
				->page($page)
				->pageSize($pagesize);
		$content 		= $this();
		$data           = json_decode($content, true);
        if(!$data){
            return '产品数据解析失败! ' . $content;
        }
        if(!Product::saveProduct($data, $this, $platform_id)){
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
	public function changeStock(int $stock, $storeid = null, $skuid = null, $spuid = null, $upc = null, $customer_sku_id = null){
		$this->method 	= (new \App\Takeaways\Meituans\ChangeStock())
				->storeId($storeid)
				->spuId($spuid)
				->skuStocks_0_skuId($skuid)
				->skuStocks_0_stock($stock);
		return $this();
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
		// dd($this->method->args);
		if($this->method->method == 'get'){
			$resp 	= $q->get($url, $this->method->args, ['headers' -> $headers]);
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
	public function saveProducts(array $data, int $platform) :bool{
		$this->method 	= new \App\Takeaways\Meituans\SaveProducts($data, $platform);
		return $this->method->status();
	}

	/**
	 * 解析并保存平台订单信息
	 * @param $data 	平台返回的商品列表
	 * @return bool
	 */
	public function saveOrders(array $data, int $platform) :bool{
		$this->method 	= new \App\Takeaways\Meituans\SaveOrders($data, $platform);
		return $this->method->status();
	}

	public function errs(){
		return $this->method->getError();
	}
}