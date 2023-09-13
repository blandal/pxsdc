<?php

namespace App\Admin\Controllers;

use App\Models\ProductSku;
use App\Models\Platform;
use App\Models\Store;
use App\Models\Product;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use App\Admin\Actions\Sku\BatchBind;

class BindSkuController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'SKU绑定';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ProductSku());
        $grid->model()->whereRaw('(bind is null or bind = "")')->orderByDesc('title');
        $platforms  = Platform::pluck('name', 'id')->toArray();
        $stores     = Store::pluck('title', 'store_id')->toArray();

        $grid->column('id', __('Id'))->hide();
        $grid->column('image', __('图片'))->display(function(){
            $rr     = Product::find($this->product_id);
            if($rr){
                return $rr->image;
            }
            return '';
        })->image(60, 60);
        $grid->column('product_id', __('Product id'))->hide();
        $grid->column('platform_id', __('平台'))->using($platforms)->filter($platforms);
        $grid->column('storeId', __('店铺'))->using($stores)->filter($stores);
        $grid->column('title', __('标题'))->filter('like');
        $grid->column('spec', __('规格'));
        $grid->column('sale_price', __('价格'))->hide();
        $grid->column('purchase_price', __('成本'))->hide();
        $grid->column('stocks', __('库存'))->sortable();
        $grid->column('spu_id', __('Spu'))->hide();
        $grid->column('sku_id', __('Sku'))->hide();
        $grid->column('upc', __('upc'))->filter('like');
        $grid->column('customSkuId', __('自有id'))->hide();
        $grid->column('weight', __('Weight'))->hide();
        $grid->column('unit', __('Unit'))->hide();
        $grid->column('product_id_platform', __('Product id platform'))->hide();

        $grid->batchActions(function ($batch) {
            $batch->add(new BatchBind());
        });
        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(ProductSku::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('product_id', __('Product id'));
        $show->field('platform_id', __('Platform id'));
        $show->field('spu_id', __('Spu id'));
        $show->field('sku_id', __('Sku id'));
        $show->field('storeId', __('StoreId'));
        $show->field('upc', __('Upc'));
        $show->field('weight', __('Weight'));
        $show->field('title', __('Title'));
        $show->field('spec', __('Spec'));
        $show->field('sale_price', __('Sale price'));
        $show->field('purchase_price', __('Purchase price'));
        $show->field('stocks', __('Stocks'));
        $show->field('unit', __('Unit'));
        $show->field('customSkuId', __('CustomSkuId'));
        $show->field('product_id_platform', __('Product id platform'));
        $show->field('itemId', __('ItemId'));
        $show->field('isWeight', __('IsWeight'));
        $show->field('weightType', __('WeightType'));
        $show->field('productId', __('ProductId'));
        $show->field('itemSkuId', __('ItemSkuId'));
        $show->field('propId', __('PropId'));
        $show->field('propText', __('PropText'));
        $show->field('many', __('Many'));
        $show->field('params', __('Params'));
        $show->field('links', __('Links'));
        $show->field('bind', __('Bind'));
        $show->field('byhum', __('Byhum'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new ProductSku());

        $form->number('product_id', __('Product id'));
        $form->number('platform_id', __('Platform id'));
        $form->text('spu_id', __('Spu id'));
        $form->text('sku_id', __('Sku id'));
        $form->text('storeId', __('StoreId'));
        $form->text('upc', __('Upc'));
        $form->number('weight', __('Weight'));
        $form->text('title', __('Title'));
        $form->text('spec', __('Spec'));
        $form->decimal('sale_price', __('Sale price'));
        $form->decimal('purchase_price', __('Purchase price'))->default(0.0000);
        $form->decimal('stocks', __('Stocks'));
        $form->text('unit', __('Unit'));
        $form->text('customSkuId', __('CustomSkuId'));
        $form->text('product_id_platform', __('Product id platform'));
        $form->text('itemId', __('ItemId'));
        $form->switch('isWeight', __('IsWeight'));
        $form->text('weightType', __('WeightType'));
        $form->text('productId', __('ProductId'));
        $form->text('itemSkuId', __('ItemSkuId'));
        $form->text('propId', __('PropId'));
        $form->text('propText', __('PropText'));
        $form->switch('many', __('Many'));
        $form->textarea('params', __('Params'));
        $form->textarea('links', __('Links'));
        $form->textarea('bind', __('Bind'));
        $form->switch('byhum', __('Byhum'));

        return $form;
    }
}
