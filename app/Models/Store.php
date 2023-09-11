<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Platform;

class Store extends Model{
    use HasFactory;
    public $timestamps      = false;
    /**
     * 获取数据库中 第三方 的 店铺id和名称的二维数组列表
     * @param $platform     所属平台
     * @return array
     */
    public static function getStoreId2Name(int $platform){
        return self::where('platform_id', $platform)->pluck('title', 'store_id')->toArray();
    }

    public function getCookieAttribute($val){
        return explode(';', $val);
    }

    public function platform():BelongsTo{
        return $this->belongsTo(Platform::class);
    }

    public static function getInstance($store_id, $platform_id){
        $res    = self::where('platform_id', $platform_id)->where('store_id', $store_id)->first();
        if(!$res){
            throw new \Exception("找不到店铺!", 1);
        }
        if(!$res->cookie){
            throw new \Exception("店铺还未登录!", 1);
        }
        if(!$res->platform){
            throw new \Exception("平台不存在!", 1);
        }
        return new $res->platform->object($res);
    }

    /**
     * 联合获取商店信息,含 platforms 表的 object
     */
    // public static function forTakeaways($store_id){
    //     $res            = self::select('stores.store_id', 'stores.cookie', 'stores.id', 'platforms.object')
    //                         ->leftJoin('platforms', 'stores.platform_id', '=', 'platforms.id')
    //                         ->where('stores.store_id', $store_id)
    //                         ->where('stores.platform_id', '=', $platform_id)
    //                         ->first();

    //     return $res;
    //     $res['instant'] = null;
    //     if($instantiated === true){
    //         if($res['object'] && $res['cookie']){
    //             $res['instant']     = new $res['object'](explode(';', $res['cookie']));
    //         }
    //     }
    //     return $res;
    // }
}
