<?php

namespace App\Admin\Actions\Sync;

use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sku;

class Product extends RowAction
{
    public $name = '<i class="fa fa-refresh"></i>同步';

    public function handle(Model $model){
        $skus       = Sku::where('pro_id', $model->id)->get();
        $pltStos    = [];
        foreach($skus as $item){
            //由于更新是根据spu更新的,因此一个spu下有多个sku的情况无需重复更新
            if(!isset($pltStos[$item->platform]) || $item->platform != $item->store_id){
                $item->syncMe();
            }
        }
        return $this->response()->success('同步成功!')->refresh();
    }

}