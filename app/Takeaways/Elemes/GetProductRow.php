<?php
/**
 * 自动更新过期的token
 */
namespace App\Takeaways\Elemes;

use App\Takeaways\Eleme;
use App\Takeaways\BaseFactory;
class GetProductRow extends Eleme{
	use BaseFactory;
	protected $method 	= 'get';
	protected $uri 		= 'mtop.ele.newretail.item.pageQuery';
	protected $args 	= [
		'pageSize'				=> 20,
		'pageNum'				=> 1,
		'titleWithoutSplitting'	=> true,
		'minQuantity'			=> null,
		'maxQuantity'			=> null,
	];
}