<?php
/**
 * 修改sku库存参数封装,给美团是什么参数,就用参数作为方法调用
 * 传入的参数类型必须和 args 中对应类型一致
 */
namespace App\Takeaways\Meituans;

use App\Takeaways\Meituan;
use App\Takeaways\BaseFactory;
class Banknumber extends Meituan{
	use BaseFactory;
	protected $method 	= 'post';
	protected $uri 		= '/comprehensive/stock/querystock';
	protected $args 	= [
		'locationCodeSegmentList'			=> ['', '', '', ''],
		'page'				=> 1,
		'pageSize'			=> 50,
		'repositoryId'		=> 1046462,
		'repositoryType'	=> 3,
	];

	public function check(){
		return true;
	}
}

