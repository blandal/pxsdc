<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Takeaways\Factory;

class Product extends Model{
    use HasFactory;
    public $timestamps     = false;

    /**
     * 将平台传递的商品列表信息写入数据库,也就是采集入库
     */
    public static function saveProduct($data, Factory $oob){
        return $oob->saveProducts($data, $oob->getStore());
    }
}
