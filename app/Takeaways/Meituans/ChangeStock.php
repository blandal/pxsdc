<?php
/**
 * 修改sku库存参数封装,给美团是什么参数,就用参数作为方法调用
 * 传入的参数类型必须和 args 中对应类型一致
 */
namespace App\Takeaways\Meituans;

use App\Takeaways\Meituan;
use App\Takeaways\BaseFactory;
class ChangeStock extends Meituan{
	use BaseFactory;
	protected $method 	= 'get';
	protected $uri 		= 'mtop.ele.newretail.item.edit';
	protected $args 	= [
		'storeId'			=> 0,
		'spuId'				=> '',
		'skuStocks'			=> [[
			'skuId'			=> '',
			'stock'			=> -1,
			'customizeStockFlag'	=> 1,
		]],
	];

	public function check(){
		if(!$this->args['storeId']){
			return '请设置 storeId';
		}
		if(!$this->args['spuId']){
			return '请设置 spuId';
		}
		// if(!isset($this->args['skuStocks']['skuId'], $this->args['skuStocks']['stock'], $this->args['skuStocks']['customizeStockFlag'])){
		// 	return '不允许更改 skuStocks 参数结构!';
		// }
		// if(!$this->args['skuStocks']['skuId'] || !$this->args['skuStocks']['stock'] || !$this->args['skuStocks']['customizeStockFlag']){
		// 	return '请确保 skuStocks参数下的: skuId, stock, customizeStockFlag 正确设置!';
		// }
		return true;
	}
}
