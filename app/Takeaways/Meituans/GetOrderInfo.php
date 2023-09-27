<?php
/**
 * 获取订单列表参数封装,给美团是什么参数,就用参数作为方法调用
 * 传入的参数类型必须和 args 中对应类型一致
 */
namespace App\Takeaways\Meituans;

use App\Takeaways\Meituan;
use App\Takeaways\BaseFactory;
class GetOrderInfo extends Meituan{
	use BaseFactory;
	protected $uri 		= '/orderfuse/detail';
	protected $method 	= 'post';
	protected $args 	= [
		'channelId'			=> 100,
		'orderId'			=> '',
	];
}