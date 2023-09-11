<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Store;
use App\Models\Platform;
use App\Takeaways\Factory;
class ProductSku extends Model{
    use HasFactory;
    public $timestamps      = false;
    private $pltobjs        = [];

    public function setStocksAttribute($val){
        if($val != $this->stocks){
            if(!isset($this->pltobjs[$this->platform_id][$this->storeId])){
                $tmp    = Store::getInstance($this->storeId, $this->platform_id);
                if(!($tmp instanceof Factory)){
                    throw new \Exception('平台sdk实例化失败!', 1);
                }
                $this->pltobjs[$this->platform_id][$this->storeId]  = $tmp;
            }

            $str   = $this->pltobjs[$this->platform_id][$this->storeId]->changeStock($val, $this);
            $cont   = json_decode($str, true);
            if(!$cont){
                throw new \Exception('返回失败: ' . $str, 1);
            }elseif(!isset($cont['code'])){
                throw new \Exception('返回格式错误: ' . $str, 1);
            }elseif($cont['code'] != 0){
                $msg    = $cont['msg'] ?? '!!请求失败!';
                throw new \Exception($msg, 1);
            }
            $this->attributes['stocks']     = $val;
        }
    }

    /**
     * 通过upc查找相同的商品
     */
    public static function sameByUpc($upc){
        if(is_array($upc)){
            return self::whereIn('upc', $upc)->get();
        }
        return self::where('upc', $upc)->get();
    }

    /**
     * 根据商品名称和属性名称查找商品
     * @param $name 参数需使用商品标题和规格名称的拼接
     */
    public static function sameByName($title, $guige){
        return self::where('title', $title)->where('spec', $guige)->get();
    }
}
