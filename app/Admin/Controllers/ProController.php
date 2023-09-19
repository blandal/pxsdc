<?php

namespace App\Admin\Controllers;

use App\Models\Pro;
use App\Models\Sku;
use App\Models\Platform;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Admin;
use App\Admin\Extensions\Tools\ProFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '商品';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $url        = route('admin.syncstocks');
        Admin::style('table{padding:10px;}input{border:solid 1px #ddd;outline:none;padding:4px 5px;width:80px;}table .fa-refresh{color:#999;margin-left:5px; font-size:12px;cursor:pointer;transition:all .2s ease-in-out;}table .fa-refresh:hover{color:#333;}');
        $script     = <<<EOT
$('.rrrf').click(function(){
    if(!$(this).hasClass('active')){
        var that    = $(this);
        var stock   = that.prev().val();
        if(stock == ''){
            alert('请填写库存!');
            return;
        }
        $(this).addClass('active');
        $.post('$url',{"sku": $(this).parent().attr('data-skuid'), "stock": stock}).done(function(res){
            that.removeClass('active');
            if(res.code != 200){
                alert(res.msg);
                return;
            }
            history.go(0);
        }).fail(function(err){
            that.removeClass('active');
            alert('错误');
        });
    }
});
EOT;
        Admin::script($script);
        $grid = new Grid(new Pro());
        $errs   = request()->get('errs');
        $upds   = true;
        switch($errs){
            case 'e'://upc错误
                $grid->model()->where('upcerr', 1);
                $upds   = false;
            break;
            case 'r'://upc重复
                $res    = Sku::where('bind', 'like', '%,%')->pluck('pro_id', 'pro_id')->toArray();
                $grid->model()->whereIn('id', $res);
                $upds   = false;
            break;
        }

        $pltsArr    = Platform::pluck('svg', 'id')->toArray();
        $grid->column('id', __('编号'));
        $grid->column('images', __('图片'))->image(60,60);
        $grid->column('title', __('标题'))->filter('like')->display(function($val){
            return '<div style="max-width:140px">' . $val . '</div>';
        });
        $grid->column('Sku', __('规格'))->display(function() use($pltsArr, $upds){
            $html       = '';
            foreach(Pro::links($this->skus->toArray()) as $items){
                $html   .= '<table class="table"><tr><th>upc</th><th>规格</th><th>库存</th>';
                if($upds == true){
                    $html   .= '<th>目标库存</th>';
                }
                $html   .= '</tr><tr>';
                $idx    = 0;
                foreach($items as $val){
                    $html   .= '<tr><td><img src="'.asset($pltsArr[$val['platform']]) . '" style="width:14px">';
                    $html   .= $val['upc'].'</td><td>'.$val['name'].'</td><td>'.$val['stocks'].'</td>';
                    $html   .= ($upds == true && $idx++ == 0 ? '<td rowspan="2" style="vertical-align: middle" data-skuid="'.$val['id'].'"><input="number"> <i class="fa fa-refresh rrrf"></i></td>' : '');
                    $html   .= '</tr>';
                }
                $html   .= '</tr></table>';
            }
            return $html;
        });
        $grid->column('cate1', __('分类'))->filter();
        $grid->column('cate2', __('子分类'))->filter();
        $state      = [0 => '已绑', 1 => '未绑'];
        $grid->column('err', __('绑定'))->filter($state)->using($state);

        $grid->tools(function ($tools) {
            $tools->append(new ProFilter());
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

    public function syncstocks(Request $request){
        $skuid      = $request->post('sku');
        $stocks     = $request->post('stock');

        if(!$skuid || !$stocks){
            return $this->error('参数错误!');
        }

        $sku        = Sku::find($skuid);
        if(!$sku){
            return $this->error('sku不存在!');
        }

        $res        = $sku->changeStock($stocks, $sku->id . ' -> 手动修改库存为 ' . $stocks, \Admin::user()->id, 2, 0, true);
        if($res === true){
            return $this->success();
        }else{
            Log::error($sku->id . ' - skus id 修改为 ' . $stocks . ' 失败!操作管理员: ' . \Admin::user()->name);
            return $this->error($res);
        }
    }

    public function success($data = null, $msg = '', $code = 200){
        return $this->resp($data, $msg, $code);
    }
    public function error($msg = '', $data = null, $code = 500){
        return $this->resp($data, $msg, $code);
    }
    private function resp($data = null, $msg = '', $code = 200){
        return response()->json([
            'code'  => $code,
            'msg'   => $msg,
            'data'  => $data
        ]);
    }
}
