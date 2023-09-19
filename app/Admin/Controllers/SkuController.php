<?php

namespace App\Admin\Controllers;

use App\Models\Sku;
use App\Models\Platform;
use App\Models\Store;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SkuController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Sku';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Sku());

        $platforms  = Platform::pluck('name', 'id')->toArray();
        $stores     = Store::pluck('title', 'store_id')->toArray();

        $grid->column('id', __('编号'));
        $grid->column('platform', __('平台'))->filter($platforms)->using($platforms);
        $grid->column('store_id', __('店铺'))->filter($stores)->using($stores);
        // $grid->column('images', __('店铺'))->display(function(){
        //     return $this->pro->images;
        // })->image(60,60);
        $grid->column('pro.images', __('图片'))->image(60,60);
        $grid->column('title', __('标题'))->filter('like');
        // $grid->column('sku_id', __('Sku id'));
        // $grid->column('pro_id', __('商品'));
        // $grid->column('spu_id', __('Spu id'));
        $grid->column('price', __('价格'));
        $grid->column('stocks', __('库存'));
        // $grid->column('upc', __('Upc'));
        // $grid->column('weight', __('Weight'));
        $grid->column('name', __('规格'));
        // $grid->column('customid', __('Customid'));
        // $grid->column('other', __('Other'));
        $grid->column('status', __('Status'));
        $grid->column('pro.cate1', __('分类'));
        $grid->column('pro.cate2', __('子分类'));

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
        $show = new Show(Sku::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('platform', __('Platform'));
        $show->field('store_id', __('Store id'));
        $show->field('sku_id', __('Sku id'));
        $show->field('pro_id', __('Pro id'));
        $show->field('spu_id', __('Spu id'));
        $show->field('price', __('Price'));
        $show->field('stocks', __('Stocks'));
        $show->field('upc', __('Upc'));
        $show->field('weight', __('Weight'));
        $show->field('title', __('Title'));
        $show->field('name', __('Name'));
        $show->field('customid', __('Customid'));
        $show->field('other', __('Other'));
        $show->field('status', __('Status'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Sku());

        $form->number('platform', __('Platform'));
        $form->text('store_id', __('Store id'));
        $form->text('sku_id', __('Sku id'));
        $form->number('pro_id', __('Pro id'));
        $form->text('spu_id', __('Spu id'));
        $form->decimal('price', __('Price'));
        $form->decimal('stocks', __('Stocks'));
        $form->text('upc', __('Upc'));
        $form->decimal('weight', __('Weight'));
        $form->text('title', __('Title'));
        $form->text('name', __('Name'));
        $form->text('customid', __('Customid'));
        $form->textarea('other', __('Other'));
        $form->switch('status', __('Status'));

        return $form;
    }
}
