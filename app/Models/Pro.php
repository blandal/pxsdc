<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pro extends Model{
    use HasFactory;
    public $timestamps  = false;

    public function skus(){
        return $this->hasMany('App\Models\Sku', 'pro_id')->orderBy('name');
    }

    /**
     * 将pro下面的关联商品展示，并带上关联标识
     */
    public static function links($skuids){
        $skutoid        = [];
        foreach($skuids as $item){
            $skutoid[$item['id']]   = $item;
        }
        $bdarrs         = [];
        $skutoidtmp     = $skutoid;
        foreach($skutoid as $item){
            if(isset($skutoidtmp[$item['id']])){
                $bdtmp                  = explode(',', $item['bind']);
                $bdarrs[$item['id']][$item['id']]    = $item;
                foreach($bdtmp as $cc){
                    if(isset($skutoidtmp[$cc])){
                        $bdarrs[$item['id']][$skutoidtmp[$cc]['id']]    = $skutoidtmp[$cc];
                        unset($skutoidtmp[$cc]);
                    }
                }
            }
        }
        return $bdarrs;
    }
}
