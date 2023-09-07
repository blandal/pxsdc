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

            $str   = $this->pltobjs[$this->platform_id][$this->storeId]->changeStock($val, $this->storeId, $this->sku_id, $this->spu_id);
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
}
