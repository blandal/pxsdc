<?php

namespace App\Admin\Controllers\Sdc;

use App\Models\Product;
use App\Models\ProductSku;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class ProductController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Product';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ProductSku());
        $grid->model()->orderByDesc('title');
        // $grid->model()->select('products.image', 'product_skus.*')->leftJoin('products', 'product_skus.product_id', '=', 'products.id');
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
        $grid->column('platform_id', __('平台'))->using($platforms)->filter();
        $grid->column('storeId', __('店铺'))->using($stores);
        $grid->column('title', __('标题'))->filter('like');
        $grid->column('spec', __('规格'));
        $grid->column('sale_price', __('价格'))->hide();
        $grid->column('purchase_price', __('成本'))->hide();
        $grid->column('stocks', __('库存'))->sortable()->editable();
        $grid->column('spu_id', __('Spu'));
        $grid->column('sku_id', __('Sku'));
        $grid->column('upc', __('upc'));
        $grid->column('customSkuId', __('自有id'))->hide();
        $grid->column('weight', __('Weight'))->hide();
        $grid->column('unit', __('Unit'))->hide();
        $grid->column('product_id_platform', __('Product id platform'))->hide();

        $grid->batchActions(function ($batch) {
            $batch->add(new BatchBind());
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Product::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('image', __('Image'));
        $show->field('title', __('Title'));
        $show->field('spu', __('Spu'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Product());

        $form->textarea('image', __('Image'));
        $form->text('title', __('Title'));
        $form->text('spu', __('Spu'));

        return $form;
    }
}
