<?php

namespace App\Admin\Actions\Sku;

use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;

class BatchBind extends BatchAction
{
    public $name = '绑定';

    public function handle(Collection $collection)
    {
        $total          = count($collection);
        if($total < 2){
            return $this->response()->warning('绑定必须2个sku以上!');
        }

        $collArr            = $collection->toArray();
        $storeIds           = array_column($collArr, 'storeId');
        if(count(array_flip($storeIds)) < $total){
            return $this->response()->warning('同店商品不允许绑定!');
        }

        $proIds             = array_column($collArr, 'product_id');
        $product_id         = min($proIds);

        foreach ($collection as $model) {
            $binfIds        = [];
            foreach($collection as $val){
                if($val->id != $model->id){
                    $binfIds[]  = $val->id;
                }
            }
            if(!empty($binfIds)){
                $model->bind        = implode(',', $binfIds);
                $model->byhum       = 1;
                if($model->product_id != $product_id){
                    $model->product_id  = $product_id;
                }
                $model->save();
            }
        }

        return $this->response()->success('SKU绑定成功!')->refresh();
    }

}