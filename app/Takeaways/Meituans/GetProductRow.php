<?php
/**
 * 修改sku库存参数封装,给美团是什么参数,就用参数作为方法调用
 * 传入的参数类型必须和 args 中对应类型一致
 */
namespace App\Takeaways\Meituans;

use App\Takeaways\Meituan;
use App\Takeaways\BaseFactory;
class GetProductRow extends Meituan{
	use BaseFactory;
	protected $method 	= 'post';
	protected $uri 		= '/store/spu/pageQuery';
	protected $args 	= [
		'activityChannelId'		=> 0,
		'asyncQueryPromotion'	=> '',
		'categoryIdList'		=> [],
		'erpCodeList'			=> [],
		'frontCategoryIds'		=> [],
		'mtAllowSaleStatus'		=> 0,
		'onlineStatus'			=> 0,
		'page'					=> 1,
		'pageSize'				=> 10,
		'poiId'					=> null,
		'priceSource'			=> 0,
		'saleStatusList'		=> [],
		'scope'					=> -1,
		'skuIdList'				=> [],
		'skuName'				=> '',
		'spuIdList'				=> [],
		'status'				=> 1,
		'tabStatus'				=> 1,
		'upcList'				=> [],
	];
}
