<?php

namespace App\Admin\Controllers\Sdc;

use App\Models\Product;
use App\Models\ProductSku;
use App\Models\Store;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class ProductsController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '商品列表';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Product());

        $tmp        = Store::get();
        $stores     = [];
        foreach($tmp as $item){
            $stores[$item->store_id]    = '<img src="'. asset($item->platform->svg) .'" style="max-width:30px;max-height:30px;">' . $item->title;
        }
        $grid->column('id', __('编号'));
        $grid->column('image', __('图片'))->image(60, 60);
        $grid->column('title', __('标题'))->filter('like');
        $grid->column('skus', __('规格'))->display(function() use ($stores){
            $rows   = ProductSku::where('product_id', $this->id)->get();
            $arr    = [];
            foreach($rows as $item){
                $arr[$item->storeId][]  = $item;
            }  

            $html   = '<table><tr><td>';
            foreach($arr as $sid => $item){
                $html   .= '<tr style="height:50px;border-bottom:solid 1px #ddd;"><td>'.($stores[$sid] ?? null).'</td><td><table>';
                foreach($item as $jjs){
                    $html   .= '<tr><td>' . $jjs->spec . ' - 库存:' . $jjs->stocks . '</td></tr>';
                }
                $html   .= '</table></td>';
            }
            $html   .= '</td>';
            // $html   .= '<td><input type="number" style="width:50px;border:solid 1px #ddd;margin-left:10px;outline:none;padding:3px 4px;"></td>';
            $html   .= '</tr></table>';
            return $html;
        });
        // $grid->column('spu', __('Spu'));

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
