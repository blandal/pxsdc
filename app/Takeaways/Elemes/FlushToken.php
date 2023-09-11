<?php
/**
 * 自动更新过期的token
 */
namespace App\Takeaways\Elemes;

use App\Takeaways\Eleme;
use App\Takeaways\BaseFactory;
class FlushToken extends Eleme{
	use BaseFactory;
	protected $method 	= 'get';
	protected $uri 		= 'mtop.ele.newretail.merchant.notice.DevMsgService.unreadMsgListV2';
	protected $args 	= [
		'deviceId'			=> '6F049574F7B0055A19F2E2C0EFF68904',
		'platformType'		=> 4,
		'pageSize'			=> 100,
		'pageIndex'			=> 1,
	];
}