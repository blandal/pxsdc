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
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

//cna=eH15HR52OU4CATs4lCf1Qf6E; WMUSS=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ; SWITCH_SHOP=; WMSTOKEN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ; OUTER_AUTH_LOGIN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ%3BMWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ; xlly_s=1; _m_h5_tk=4eae4153ca19b7606d417c040fd8d3b3_1694403492958; _m_h5_tk_enc=36cf5530f1d7895304a8b293984da617; l=fBMkwck7Nvs9yn4FBO5CFurza77t4QAb8sPzaNbMiIEGB6v0ZFv9XY-Q2OlErxxPWhQNes6wR3-WjmOpBWLRLyCT4RpK5n5LJCHmndhyN3pR.; tfstk=dkhK11HW-g-Mxz98rL4JILPylhDWSxJcY2lrA_fuZkvsXligr8TEq_HtjmEIK0YEw_HsjzxeUU5-j4nAZeknqcC-2Al3x20n2YE7buc3Ok4S24nxTk0n-0U-xWwrKuq3273rDFxDiI0E87PBmnADkxZbt2tKrdqj82PzNNjWnAgUyNdz3GXHmM_P6_coZy9yD1RPm5xOHJqjRkgn-fsgB9uLX_1S6AeCQDcmMasVmoUldFLOUTyQ7nEBzJ1..; isg=BDw9BV30dDGRB0Bqekif8o7NDdruNeBf-tf6iBa_HycG4d1rPkED78odwQmZqRi3

class Eleme implements Factory{
	private $domain 	= 'https://nrshop.ele.me/h5/';
	private $store 		= null;
	private $tokenTimout=0;
	protected $cookie 	= null;
	protected $method 	= null;
	protected $url 		= null;
	protected $args 	= [
		'jsv'		=> '2.7.2',
		'type'		=> 'json',
		'dataType'	=> 'json',
		'valueType'	=> 'string',
		'appKey'	=> '12574478',
	];
	protected $headers 	= [
		'Accept'    => 'application/json, text/plain, */*',
		'Content-Type'  => 'application/json;charset=UTF-8',
		'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
	];
	protected $token 	= null;
	private $version 	= '1.0';
	/**
	 * 初始化时必须传入登录的cookie,此对象不会自动获取cookie.
	 * cookie 格式是一个数组 ['key' => $value] 或者 使用;分割的字符串
	 */
	public function __construct(Store $store){
		$this->store 	= $store;
		$cookie 		= $store->cookie;
		if($cookie){
			if(is_string($cookie)){
				$cookie 	= explode(';', $cookie);
			}
			foreach($cookie as &$item){
				$item 	= trim($item);
				$tmp 	= explode('=', $item);
				if($tmp[0] == '_m_h5_tk'){
					$tk 			= explode('_', $tmp[1]);
					$this->token 	= $tk[0];
					$this->tokenTimout 	= (int)($tk[1] / 1000);
				}elseif($tmp[0] == 'OUTER_AUTH_LOGIN'){
					$this->headers['X-Ele-Eb-Token']	= $tmp[1];
					$this->headers['X-Ele-Platform']	= 'eb';
				}
			}
			$this->cookie 	= $cookie;
			$this->headers['Cookie']	= implode(';', $this->cookie);

			if($this->tokenTimout <= time()){
				$this->autoFlush();
			}
		}
		return $this;
	}

	public function getPlatform(){
		return $this->store->platform;
	}


	/**
	 * 获取产品列表
	 * 请使用链式调用设置请求参数,调用方法为牵牛花此接口的参数名称,复制参数名称调用即可
	 * @return string or bool
	 */
	public function getProducts(int $page = 1, int $pagesize = 20, $title = null){
		$this->method 	= (new \App\Takeaways\Elemes\GetProducts())
				->pageNum($page)
				->pageSize($pagesize)
				->sellerId($this->store->sellerId)
				->storeIds_0($this->store->store_id);
		if($title){
			$this->method->title($title);
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
        if(isset($data['ret'][0]) && strpos($data['ret'][0], '成功') !== false){
            return $nums;
        }
        return $data['msg'] ?? '错误!';
	}

	public function getProductRow(Sku $sku){//同步单个产品
		$this->method 	= (new \App\Takeaways\Elemes\GetProductRow())
				->sellerId($this->store->sellerId)
				->storeIds_0($this->store->store_id)
				->mixedBarCodeOrId($sku->upc);
		$resp 			= $this();
		$arr 			= json_decode($resp, true);
		if(isset($arr['data']['data'][0])){
			$row 		= $arr['data']['data'][0];
			$title 		= $row['title'];
			$cate1 		= $row['customCategoryParentName'];
			$cate2		= $row['customCategoryName'];
			$spu_id 	= $sku->spu_id;

			$isMany 	= $sku->other ? true : false;//是否多sku判断
			$originSkus	= [];
			$waitAdd 	= [];
			if($row['hasSku'] != $isMany){//如果产品的sku发生改变,则删除原有的sku并新增
				Sku::where('pro_id', $sku->pro_id)->where('platform', $sku->platform)->where('store_id', $sku->store_id)->delete();
			}else{
				$tmp 	= Sku::where('pro_id', $sku->pro_id)->where('platform', $sku->platform)->where('store_id', $sku->store_id)->get();
				foreach($tmp as $item){
					$originSkus[$item->upc] 	= $item;
				}
			}
			$skuarr 	= [
				'platform'	=> $sku->platform,
				'store_id'	=> $sku->store_id,
				'sku_id'	=> $spu_id,
				'pro_id'	=> $sku->pro_id,
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
				'bind'		=> null,
				'byhum'		=> null,
				'stockupdate'	=> time(),
			];
			if($row['hasSku'] == true){
				foreach($row['itemSkuList'] as $item){
					$upc 	= $item['barcode'];
					if(isset($originSkus[$upc])){
						$rrr 	= $originSkus[$upc];
						$rrr->title 	= $title;
						$rrr->stocks 	= $item['quantity'];
						$rrr->other 	= json_encode($item, JSON_UNESCAPED_UNICODE);
						$rrr->sku_id 	= $item['itemSkuId'];
						$rrr->isWeight 	= $row['isWeight'] ?? null;
						$rrr->weightType= $row['weightType'];
						$rrr->status 	= $row['status'];
						$rrr->customid 	= $item['skuOuterId'];
						$rrr->price 	= $item['price'];
						$rrr->name 		= $item['salePropertyList'][0]['valueText'];
						$rrr->weight 	= $item['itemWeight'];
						$rrr->stockupdate 	= time();
						$rrr->save();
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
						if($sku->upc == $upc){
							$tmp['bind']	= $sku->bind;
							$tmp['byhum']	= $sku->byhum;
						}
						$waitAdd[] 	= $tmp;
					}
				}
			}else{
				$upc 		= $row['barCode'];
				if(isset($originSkus[$upc])){
					$rrr 	= $originSkus[$upc];
					$rrr->title 	= $title;
					$rrr->stocks 	= $skuarr['stocks'];
					$rrr->other 	= null;
					$rrr->sku_id 	= $skuarr['sku_id'];
					$rrr->isWeight 	= $row['isWeight'];
					$rrr->weightType= $row['weightType'];
					$rrr->status 	= $row['status'];
					$rrr->customid 	= $skuarr['customid'];
					$rrr->price 	= $skuarr['price'];
					$rrr->name 		= null;
					$rrr->weight 	= $skuarr['weight'];
					$rrr->stockupdate 	= time();
					$rrr->save();
				}else{
					$tmp 		= $skuarr;
					if($sku->upc == $upc){
						$tmp['bind']	= $sku->bind;
						$tmp['byhum']	= $sku->byhum;
					}
					$waitAdd[] 	= $tmp;
				}
			}
			if(!empty($waitAdd)){
				Sku::insert($waitAdd);
			}
			return true;
		}else{
			Log::error('Eleme同步sku['.$sku->id.']错误. ' . $arr['data']['errMessage'] ?? null);
			return false;
		}
	}

	/**
	 * 获取订单列表
	 */
	public function getOrders(int $page = 1, int $pagesize = 10){
		$this->method 	= (new \App\Takeaways\Elemes\GetOrders())
				->page($page);
		return $this();
	}

	/**
	 * 修改sku库存
	 * @return response string
	 */
	public function changeStock(int $stock, Sku $productSku){
		$this->method 		= new \App\Takeaways\Elemes\ChangeStock();
		if($productSku->other){
			$skus 			= Sku::where('spu_id', $productSku->spu_id)->get();
			$this->method->itemEditDTO__itemId((int)$productSku->spu_id);
			$arr 			= [];
			foreach($skus as $item){
				$params 	= json_decode($item->other);
				if(!$params){
					return '商品信息错误!';
				}
				if($item->id == $productSku->id){
					$params->quantity 	= $stock;
					$item->stocks 		= $stock;
					$item->other 		= json_encode($params, JSON_UNESCAPED_UNICODE);
				}elseif($params->quantity != $item->stocks){
					$params->quantity 	= $item->stocks;
					$item->other 		= json_encode($params, JSON_UNESCAPED_UNICODE);
				}
				$strss 		= json_encode([
					'barcode'		=> (string)$params->barcode,
					'itemSkuId'		=> (int)$params->itemSkuId,
					'itemWeight'	=> (int)$params->itemWeight,
					'price'			=> (float)$params->price,
					'productSkuId'	=> (string)$params->productSkuId,
					'quantity'		=> $item->id == $productSku->id ? $stock : (int)$item->stocks,
					'salePropertyList'	=> $params->salePropertyList,
					'skuOuterId'	=> $params->skuOuterId,
				], JSON_UNESCAPED_UNICODE);
				$arr[]		= str_replace('\\/', '/', $strss);
			}
			$this->method->itemEditDTO__itemSkuList($arr);
			$this->method->itemEditDTO__fromChannel('ITEM_EDIT');
			$this->method->itemEditDTO__sellerId($this->store->sellerId);
			$this->method->itemEditDTO__itemId((int)$productSku->spu_id);
			$this->method->itemEditDTO__storeId((int)$this->store->store_id);
			$this->method->manyUpdate();
		}else{
			$this->method->sellerId($this->store->sellerId)
							->itemId((int)$productSku->spu_id)
							->storeId((int)$this->store->store_id)
							->isWeight($productSku->isWeight ? true : false)
							->weightType($productSku->weightType)
							->quantity($stock);
		}
		try {
			$str 		= $this();
		} catch (\Exception $e) {
			Log::error('饿了么: ' . $e->getMessage());
		}
		$res 		= json_decode($str, true);
		if(isset($res['ret'][0]) && strpos($res['ret'][0], 'SUCCESS') !== false){
			return true;
		}else{
			Log::debug('修改库存失败:' . $str);
		}
		// dd($res, '~~~~~~~~');
		return '库存修改失败!';
	}


	/**
	 * 获取订单商品详情
	 * @return App\Takeaways\Elemes\ChangeStock
	 */
	public function orderProducts(string $orderid){
		$this->method 	= (new \App\Takeaways\Elemes\GetOrderProducts())
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

		$headers 	= $this->headers;
		$data 		= $this->method->args;
		if(isset($data['itemEditDTO']['itemSkuList'])){
	        $data['itemEditDTO']    = str_replace('\\/', '/', json_encode($data['itemEditDTO'], JSON_UNESCAPED_UNICODE));
	        $data                   = str_replace('\\/', '/', json_encode($data, JSON_UNESCAPED_UNICODE));
		}
		$this->sigin($data);
		$args 		= $this->args;
		$url 		= $this->domain . trim($this->method->uri, '/') . '/' . $this->version . '/';

		if($this->method->method == 'post'){
			$headers['Content-Type']	= 'application/x-www-form-urlencoded';
		}
		$client 	= new Client([
			'verify'	=> false,
			'headers'	=> $headers,
		]);

		if($this->method->method == 'get'){
			$result 		= $client->get($url, ['query' => $args]);
		}else{
			$data 			= $args['data'];
			unset($args['data']);
			// dd($args, $url, ['data' => $data], $headers);
			$result 		= $client->post($url, ['query' => $args, 'form_params' => ['data' => $data]]);
		}
		$respCookies 		= $result->getHeaders();
		if(isset($respCookies['Set-Cookie'])){
			$respCookies 	= $respCookies['Set-Cookie'];
			$cookietmp 	= [];
			foreach($this->cookie as $item){
				$tmp 	= explode('=', $item);
				$cookietmp[trim($tmp[0])] 	= $tmp[1];
			}
			foreach($respCookies as $item){
				$tmp 	= explode(';', trim($item));
				$item 	= $tmp[0];
				$tmp 	= explode('=', $item);
				$cookietmp[trim($tmp[0])]	= trim($tmp[1]);
			}
			$cooikeArr 			= [];
			foreach($cookietmp as $k => $item){
				if($k == '_m_h5_tk'){
					$tts 			= explode('_', $item);
					$this->token 	= $tts[0];
					$this->tokenTimout 	= (int)($tts[1] / 1000);
				}
				$cooikeArr[] 	= "$k=$item";
			}
			$this->cookie 		= $cooikeArr;
			$this->store->cookie 		= implode(';', $this->cookie);
			$this->store->save();
			$this->headers['Cookie']	= $this->store->cookie;
		}

		return $result->getBody()->getContents();
// cna=eH15HR52OU4CATs4lCf1Qf6E; WMUSS=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ; SWITCH_SHOP=; WMSTOKEN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ; OUTER_AUTH_LOGIN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ%3BMWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ; xlly_s=1; _m_h5_tk=4eae4153ca19b7606d417c040fd8d3b3_1694403492958; _m_h5_tk_enc=36cf5530f1d7895304a8b293984da617; l=fBMkwck7Nvs9yv9fBO5Clurza77T1IOb4sPzaNbMiIEGB6Y74Fp9XY-Q2O8o8q-5WhQNF659R3-WjmOpBeYBqCXUBYDeDTxk1CMmnmOk-Wf..; tfstk=dnGkzF1BkYy7ET2dJTFWZBj4Qevvr7NaTHoLvk3UYzqb4JeK86YnXoe-e78W-6mxj3wLpQwhKcHbJYEpFBY30cYBV3N-tXmqDbrz93i0YVibJYKzwXcn0cu-x2T78koExkHJHC3SPWNeXDA9646MoW-9qvpY74NQThQATddiPc_AJmsGA1snN3GXrREGJnu1E01lsag8m65G5z2Pg4BdT6l0r8cErOuG3OP_V6a2JjWCd8zbolEgVMVV.; isg=BCkp6zB3aaaNXVUVhytypYteONWD9h0oH-RPF8sfzZBDkkmkE0RL-DyMVDakCrVg


// cna=eH15HR52OU4CATs4lCf1Qf6E;WMUSS=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ;SWITCH_SHOP=;WMSTOKEN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ;OUTER_AUTH_LOGIN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ;MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ;xlly_s=1;_m_h5_tk=e5fc5a1110c0941fb6355954c09dcddf_1694422612074;_m_h5_tk_enc=40ce8cabab97177e2afd6c4632a1382f;l=fBMkwck7Nvs9yv9fBO5Clurza77T1IOb4sPzaNbMiIEGB6Y74Fp9XY-Q2O8o8q-5WhQNF659R3-WjmOpBeYBqCXUBYDeDTxk1CMmnmOk-Wf..;tfstk=dnGkzF1BkYy7ET2dJTFWZBj4Qevvr7NaTHoLvk3UYzqb4JeK86YnXoe-e78W-6mxj3wLpQwhKcHbJYEpFBY30cYBV3N-tXmqDbrz93i0YVibJYKzwXcn0cu-x2T78koExkHJHC3SPWNeXDA9646MoW-9qvpY74NQThQATddiPc_AJmsGA1snN3GXrREGJnu1E01lsag8m65G5z2Pg4BdT6l0r8cErOuG3OP_V6a2JjWCd8zbolEgVMVV.;isg=BCkp6zB3aaaNXVUVhytypYteONWD9h0oH-RPF8sfzZBDkkmkE0RL-DyMVDakCrVg




// cna=eH15HR52OU4CATs4lCf1Qf6E;WMUSS=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ;SWITCH_SHOP=;WMSTOKEN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ;OUTER_AUTH_LOGIN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ%3BMWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ;xlly_s=1;_m_h5_tk=bc193d56e93ed1457a61ccddf07e341a_1694423595566;_m_h5_tk_enc=116f4084eac8009d86564a11a7e6499b;l=fBMkwck7Nvs9yv9fBO5Clurza77T1IOb4sPzaNbMiIEGB6Y74Fp9XY-Q2O8o8q-5WhQNF659R3-WjmOpBeYBqCXUBYDeDTxk1CMmnmOk-Wf..;tfstk=dnGkzF1BkYy7ET2dJTFWZBj4Qevvr7NaTHoLvk3UYzqb4JeK86YnXoe-e78W-6mxj3wLpQwhKcHbJYEpFBY30cYBV3N-tXmqDbrz93i0YVibJYKzwXcn0cu-x2T78koExkHJHC3SPWNeXDA9646MoW-9qvpY74NQThQATddiPc_AJmsGA1snN3GXrREGJnu1E01lsag8m65G5z2Pg4BdT6l0r8cErOuG3OP_V6a2JjWCd8zbolEgVMVV.;isg=BCkp6zB3aaaNXVUVhytypYteONWD9h0oH-RPF8sfzZBDkkmkE0RL-DyMVDakCrVg
// cna=eH15HR52OU4CATs4lCf1Qf6E; WMUSS=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ; SWITCH_SHOP=; WMSTOKEN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ; OUTER_AUTH_LOGIN=MWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ%3BMWYZYZMTAwMDIwMDI1ODA0MzEyT2lBOXlWQjNQ; xlly_s=1; _m_h5_tk=26cd7b22156158f07556eb68e9366e61_1694423659297; _m_h5_tk_enc=f18135beffa9bef867b95538b53d8e71; tfstk=dUPDrhA1y-kbcuk9WKhfi1d-OzWJHnG__ldtXfnNU0oSBVUNlNAiqu2x0ZZ9s5qLFA3OGO9Ms8zTHCBbHNquvlIbMjUYslqofmoOcjoZ7uzTHSGYk5qgAoqiCZitbcqT7-QR96UblfGajMCd92kK1fS0WTnUlrGs_UKNp8zjSUb38G9hMGzwbS_mpDYOVVTWbt7IfroktzNouvJbu0Aw_72onOuDUplIcCgPW7J6CxuSrDnnchcP.; l=fBMkwck7Nvs9yI_6BOfwFurza77OsIRAguPzaNbMi9fP9hWM5u0GW1TUs0xHCnGVF64JR3-WjmOpBeYBq19LBYDeDTxk1CMmnmOk-Wf..; isg=BEFBpeF3wW76OC1dr6PKnSMmUI1bbrVgR-xX36OWMMinimFc6785MGzGbP7Mgk2Y

		$q 			= QueryList::getInstance(implode(';', $this->cookie));
		$args 		= $this->args;
		if($this->method->method == 'get'){
			$resp 	= $q->get($url, $args, ['headers' => $headers]);
		}else{
			$resp 	= $q->post($url, '', ['headers' => $headers, 'json' => $args]);
		}
		return $resp->getHtml();
	}

	/**
	 * 饿了么更新令牌
	 */
	public function autoFlush(){
		$this->method 	= (new \App\Takeaways\Elemes\FlushToken());
		$res 			= $this();
		return;
	}

	/**
	 * 解析并保存平台商品信息
	 * @param $data 	平台返回的商品列表
	 * @return bool
	 */
	public function saveProducts(array $data){
		$this->method 	= new \App\Takeaways\Elemes\SaveProducts($data, $this->store);
		return $this->method->render();
	}

	/**
	 * 解析并保存平台订单信息
	 * @param $data 	平台返回的商品列表
	 * @return bool
	 */
	public function saveOrders(array $data) :array{
		$this->method 	= new \App\Takeaways\Elemes\SaveOrders($data, $this->store);
		return $this->method->render();
	}

	public function errs(){
		return $this->method->getError();
	}

	protected function sigin($data, $ts = null, $appkey = null, $uri = null){
		if(!$this->token){
			throw new \Exception('账号未登录!', 1);
		}
		if(!$ts){
			list($microsecond , $time) = explode(' ', microtime());
			$ts 	= (string)sprintf('%.0f',(floatval($microsecond)+floatval($time))*1000);
		}
		if($appkey){
			$this->args['appKey']	= $appkey;
		}
		if(is_array($data)){
			foreach($data as &$item){
				if(is_array($item)){
					$item 	= json_encode($item, JSON_UNESCAPED_UNICODE);
				}
			}
			$data 	= json_encode($data, JSON_UNESCAPED_UNICODE);
		}
		// if($this->method->method == 'post'){
		// 	$data 	= str_replace('\\', '\\\\', $data);
		// }

		$signStr 				= "$this->token&$ts&".$this->args['appKey']."&$data";
		$this->args['t']		= $ts;
		$this->args['sign']		= md5($signStr);//$this->command($signStr);//
		$this->args['api']		= $uri ? $uri : ($this->method->uri ?? null);
		$this->args['v']		= $this->version;
		$this->args['data']		= $data;
		// dd($signStr);
		return true;
	}

	private function fmtdata($data){
		if(is_array($data)){
			foreach($data as $k => $v){
				if(is_array($v)){
					foreach($v as $z => $c){
						if(is_array($c)){
							$v[$z] 	= json_encode($c, JSON_UNESCAPED_UNICODE);
						}
					}
				}else{
					$data[$k]	= $v;
				}
			}
			$data 	= json_encode($data, JSON_UNESCAPED_UNICODE);
		}
		return $data;
	}

	private function command($str){
		$str 	= str_replace('"', '\\"', str_replace('\\', '\\\\', $str));
		$cmd 	= 'cd ' . storage_path('app') . ' && elesign.exe -str="'.$str.'"';
		$out 	= [];
		exec($cmd, $out);
		return $out[0] ?? null;
        // '6f2101aaaa553684d515c7f39f3f2997&1694153542465&12574478&{"pageSize":20,"pageNum":1,"sellerId":"2216508507961","storeIds":"[\"1097214140\"]","titleWithoutSplitting":true,"minQuantity":null,"maxQuantity":null}', '11e0d85dabe8b8e9a2f7fc7f9e25f23a'
	}

	public function getStore(){
		return $this->store;
	}
}


