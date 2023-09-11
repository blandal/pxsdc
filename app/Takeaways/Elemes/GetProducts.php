<?php
/**
 * 获取商品列表参数封装,给美团是什么参数,就用参数作为方法调用
 * 传入的参数类型必须和 args 中对应类型一致
 */
namespace App\Takeaways\Elemes;

use App\Takeaways\Eleme;
use App\Takeaways\BaseFactory;
class GetProducts extends Eleme{
	/**
	 * 饿了么商品状态, 0,1是上架
	 * -2,-3,-5是下架
	 */
	use BaseFactory;
	protected $uri 		= 'mtop.ele.newretail.item.pageQuery';
	protected $method 	= 'get';
	protected $args 	= [
		'pageSize'				=> 20,
		'pageNum'				=> 1,
		'sellerId'				=> '',
		'storeIds'				=> [],
		'titleWithoutSplitting'	=> true,
		'minQuantity'			=> 'null',
		'maxQuantity'			=> 'null',
	];
}


// 59ec0414ea63b47fdc417f012f3db6fc_1694148817614&1694144598474&12574478&{"pageSize":20,"pageNum":1,"sellerId":"2216508507961","storeIds":"[\"1097214140\"]","titleWithoutSplitting":true,"minQuantity":null,"maxQuantity":null}