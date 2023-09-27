<?php
/**
 * 获取订单列表参数封装,给美团是什么参数,就用参数作为方法调用
 * 传入的参数类型必须和 args 中对应类型一致
 */
namespace App\Takeaways\Elemes;

use App\Takeaways\Eleme;
use App\Takeaways\BaseFactory;
class GetOrders extends Eleme{
	use BaseFactory;
	protected $uri 		= 'mtop.ele.newretail.order.seller.QueryPcService.getOrderRecord';
	protected $method 	= 'get';
	protected $args 	= [
		// 'start_timestamp'	=> 0,
		// 'end_timestamp'		=> 0,
		// 'order_status'		=> 0,
		// 'shop_id'			=> '',
		'page'				=> 1,
	];

	public function __construct(){
		// $this->args['end_timestamp'] 		= time();
		// $this->args['start_timestamp'] 		= $this->args['end_timestamp'] - 86400;
	}
}