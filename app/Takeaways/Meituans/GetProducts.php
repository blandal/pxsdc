<?php
/**
 * 获取商品列表参数封装,给美团是什么参数,就用参数作为方法调用
 * 传入的参数类型必须和 args 中对应类型一致
 */
namespace App\Takeaways\Meituans;

use App\Takeaways\Meituan;
use App\Takeaways\BaseFactory;
class GetProducts extends Meituan{
	use BaseFactory;
	protected $uri 		= '/store/spu/pageQuery';
	protected $method 	= 'post';
	protected $args 	= [
		'scope'				=> -1,
		'onlineStatus'		=> 0,
		'categoryIdList'	=> [],
		'frontCategoryIds'	=> [],
		'spuIdList'			=> [],
		'skuIdList'			=> [],
		'erpCodeList'		=> [],
		'upcList'			=> [],
		'poiId'				=> [],
		'saleStatusList'	=> [],
		'page'				=> 1,
		'asyncQueryPromotion'	=> true,
		'activityChannelId'	=> 0,
		'priceSource'		=> 0,
		'mtAllowSaleStatus'	=> 0,
		'status'			=> 1,
		'pageSize'			=> 10,
		'tabStatus'			=> 1,
	];
}
