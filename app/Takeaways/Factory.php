<?php
namespace App\Takeaways;

use App\Models\Store;
use App\Models\ProductSku;
/**
 * 这是一个外卖api的接口
 * 当需要调用api修改外卖数据时,请继承此接口
 */
interface Factory{
	public function getProducts(int $page = 1, int $pagesize = 10);
	public function changeStock(int $stock, ProductSku $productSku);
	public function saveProducts(array $data):bool;
	public function saveOrders(array $data):bool;
	public function orderProducts(string $orderid);
	public function getOrders(int $page = 1, int $pagesize = 10);
	public function getPlatform();
	public function getStore();
}