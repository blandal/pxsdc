<?php

namespace App\Admin\Controllers;

use App\Models\Pro;
use App\Models\Platform;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Admin;

class ProController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Pro';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        Admin::style('table{padding:10px;}input{border:solid 1px #ddd;outline:none;padding:4px 5px;width:80px;}');
        $grid = new Grid(new Pro());
        if(request()->get('upcerr')){
            $grid->model()->where('upcerr', 1);
        }

        $pltsArr    = Platform::pluck('svg', 'id')->toArray();
        $grid->column('id', __('编号'));
        $grid->column('images', __('图片'))->image(60,60);
        $grid->column('title', __('标题'))->filter('like');
        // $grid->column('err', __('Err'));
        $grid->column('Sku', __('规格'))->display(function() use($pltsArr){
            // $skuids         = $this->skus->toArray();
            // if(count($skuids) > 2){
            //     dd($bdarrs, $skuids);
            // }
            // $binds          = array_column($skuids, 'bind');
            // dd($binds); 
            // foreach($this->skus as $item){
            //     $html .= ('<img src="' . asset($item->platforms->svg) . '" style="width:14px;">') . ' - ' . $item->name . '('.$item->stocks.')<br>';
            // }
            $html       = '';
            foreach(Pro::links($this->skus->toArray()) as $items){
                $html   .= '<table class="table"><tr><th>upc</th><th>规格</th><th>库存</th><th>目标库存</th></tr><tr>';
                $idx    = 0;
                foreach($items as $val){
                    $html   .= '<tr><td><img src="'.asset($pltsArr[$val['platform']]).'" style="width:14px">'.$val['upc'].'</td><td>'.$val['name'].'</td><td>'.$val['stocks'].'</td>'.($idx++ == 0 ? '<td rowspan="2"><input="number"></td>' : '').'</tr>';
                }
                $html   .= '</tr></table>';
            }
            return $html;
        });
        $grid->column('cate1', __('分类'))->filter();
        $grid->column('cate2', __('子分类'))->filter();
        $grid->column('err', __('错误'))->filter();

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
        $show = new Show(Pro::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('title', __('Title'));
        $show->field('images', __('Images'));
        $show->field('err', __('Err'));
        $show->field('cate1', __('Cate1'));
        $show->field('cate2', __('Cate2'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Pro());

        $form->text('title', __('Title'));
        $form->textarea('images', __('Images'));
        $form->switch('err', __('Err'));
        $form->text('cate1', __('Cate1'));
        $form->text('cate2', __('Cate2'));

        return $form;
    }
}
