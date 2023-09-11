<?php
/**
 * 修改sku库存参数封装,给美团是什么参数,就用参数作为方法调用
 * 传入的参数类型必须和 args 中对应类型一致
 */
namespace App\Takeaways\Elemes;

use App\Takeaways\Eleme;
use App\Takeaways\BaseFactory;
class ChangeStock extends Eleme{
	use BaseFactory;
	protected $method 	= 'get';
	protected $uri 		= 'mtop.ele.newretail.item.edit';//mtop.ele.newretail.item.update 是post请求,多个sku的情况下使用post
	protected $args 	= [
		'sellerId'			=> '',
		'itemId'			=> '',
		'storeId'			=> '',
		'isWeight'			=> 'false',
		'weightType'		=> null,
		'quantity'			=> 0,
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

// data: {"itemEditDTO":"{\"itemId\":737926614602,\"itemSkuList\":[\"{\\\"barcode\\\":\\\"2080371063161\\\",\\\"itemSkuId\\\":5095732889798,\\\"itemWeight\\\":20,\\\"price\\\":7.9,\\\"productSkuId\\\":\\\"5095693429628\\\",\\\"quantity\\\":10,\\\"salePropertyList\\\":[{\\\"elePropText\\\":null,\\\"eleValueText\\\":null,\\\"images\\\":null,\\\"inputValue\\\":true,\\\"levelSource\\\":null,\\\"propId\\\":168606316,\\\"propText\\\":\\\"规格\\\",\\\"showImage\\\":null,\\\"valueId\\\":-1,\\\"valueText\\\":\\\"白色1/支\\\"}],\\\"skuOuterId\\\":\\\"1329105774\\\"}\",\"{\\\"barcode\\\":\\\"2080339076554\\\",\\\"itemSkuId\\\":5095732889799,\\\"itemWeight\\\":20,\\\"price\\\":7.9,\\\"productSkuId\\\":\\\"5095693429629\\\",\\\"quantity\\\":10,\\\"salePropertyList\\\":[{\\\"elePropText\\\":null,\\\"eleValueText\\\":null,\\\"images\\\":null,\\\"inputValue\\\":true,\\\"levelSource\\\":null,\\\"propId\\\":168606316,\\\"propText\\\":\\\"规格\\\",\\\"showImage\\\":null,\\\"valueId\\\":-1,\\\"valueText\\\":\\\"黄色1/支\\\"}],\\\"skuOuterId\\\":\\\"1329105775\\\"}\"],\"fromChannel\":\"ITEM_EDIT\",\"sellerId\":\"2216508507961\",\"storeId\":1097214140}"}