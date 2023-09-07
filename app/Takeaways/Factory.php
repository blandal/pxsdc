<?php
namespace App\Takeaways;
/**
 * 这是一个外卖api的接口
 * 当需要调用api修改外卖数据时,请继承此接口
 */
interface Factory{
	public function getProducts(int $page = 1, int $pagesize = 10, int $platform_id);
	public function changeStock(int $stock, $storeid = null, $skuid = null, $spuid = null, $upc = null, $customer_sku_id = null);
	public function saveProducts(array $data, int $platform):bool;
	public function saveOrders(array $data, int $platform):bool;
	public function orderProducts(string $orderid);
	public function getOrders(int $page = 1, int $pagesize = 10);
}