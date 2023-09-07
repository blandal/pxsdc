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
        $grid = new Grid(new Product());

        $grid->column('id', __('Id'))->hide();
        $grid->column('image', __('图片'))->image(60, 60);
        $grid->column('spu', __('Spu'));
        $grid->column('title', __('标题'))->filter('like');
        $grid->column('sku', __('Sku'))->display(function(){
            $res    = ProductSku::where('product_id', $this->id)->get();
            $html   = '';
            foreach($res as $item){
                $html   .= ($item->spec ? $item->spec : '无') . ' ('.$item->stocks.')' . '<br>';
            }
            return $html;
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
