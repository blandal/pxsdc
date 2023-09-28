<?php

namespace App\Admin\Controllers\Sdc;

use App\Models\Order;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use App\Models\Platform;
use App\Models\Store;

class OrderController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Order';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Order());
        $grid->model()->orderByDesc('createTime');
        $pltsArr    = Platform::pluck('svg', 'id')->toArray();
        $stores     = Store::pluck('title', 'store_id')->toArray();

        // $grid->column('id', __('ID'));
        $grid->column('order_index', __('编号'))->display(function($val){
            return '#' . $val;
        })->sortable()->filter();
        $grid->column('createTime', __('下单时间'))->display(function($val){
            return $val ? date('m-d H:i:s', $val) : null;
        })->filter('range', 'datetime');
        $grid->column('orderid', __('订单号'))->filter('like')->hide();
        $grid->column('store_id', __('店铺'))->filter($stores)->using($stores)->display(function($val) use($pltsArr){
            return (isset($pltsArr[$this->platform_id]) ? '<img src="'.asset($pltsArr[$this->platform_id]).'" style="width:20px;">' : null) . $val;
        });
        $grid->column('itemCount', __('数量'))->sortable();
        $grid->column('deliveryAmount', __('配送费'))->hide();
        $grid->column('product_amount', __('小计'))->sortable()->filter('range');
        $grid->column('merchantAmount', __('预计收入'))->display(function($val){
            $persent    = ceil($val / $this->product_amount * 100);
            if($persent >= 80){
                $class      = 'label-default';
            }elseif($persent >= 60){
                $class      = 'label-success';
            }elseif($persent >= 50){
                $class      = 'label-warning';
            }else{
                $class      = 'label-danger';
            }

            return '<span class="label '.$class.'">'.$val . ' - '.$persent.'%'.'</span>';
        })->filter('range')->sortable();
        // $grid->column('channelId', __('ChannelId'));
        // $grid->column('orderStatus', __('OrderStatus'));
        // $grid->column('orderId_tm', __('OrderId tm'));
        // $grid->column('platform_id', __('Platform id'));
        // $grid->column('status', __('Status'));
        // $grid->column('addtime', __('Addtime'));
        // $grid->column('shipping_fee', __('Shipping fee'));
        // $grid->column('origin_content', __('Origin content'));
        // $grid->column('lat', __('Lat'));
        // $grid->column('log', __('Log'));
        // $grid->column('userid', __('Userid'))->hide();
        $grid->column('butie', __('补贴'))->filter('range')->sortable();
        $grid->column('butie_platform', __('平台补贴'))->sortable();
        $grid->column('butie_details', __('补贴细节'))->hide()->width(120);
        // $grid->column('pack_fee', __('Pack fee'));
        $grid->column('pay_amount', __('支付'))->filter('range')->sortable();
        $grid->column('performService', __('履约服务费'))->hide();
        $grid->column('pay_time', __('支付时间'))->display(function($val){
            return $val ? date('Y-m-d H:i:s', $val) : null;
        })->hide();
        $grid->column('weight', __('总重(g)'))->hide();
        $grid->column('comments', __('备注'))->hide();
        // $grid->column('pack_status', __('Pack status'));
        $grid->column('pack_status_desc', __('包裹状态'))->hide();
        // $grid->column('used_time', __('Used time'));
        $grid->column('jiedan_time', __('接单时间'))->display(function($val){
            return $val ? date('Y-m-d H:i:s', $val) : null;
        })->hide();
        $grid->column('pack_time', __('打包开始'))->display(function($val){
            return $val ? date('Y-m-d H:i:s', $val) : null;
        })->hide();
        $grid->column('pack_end_time', __('打包完成'))->display(function($val){
            return $val ? date('Y-m-d H:i:s', $val) : null;
        })->hide();
        $grid->column('ship_time', __('配送开始'))->display(function($val){
            return $val ? date('Y-m-d H:i:s', $val) : null;
        })->hide();
        $grid->column('ship_end_time', __('配送完成'))->display(function($val){
            return $val ? date('Y-m-d H:i:s', $val) : null;
        })->hide();
        $grid->column('done_time', __('完成时间'))->display(function($val){
            return $val ? date('Y-m-d H:i:s', $val) : null;
        })->hide();
        $grid->column('orderStatusDesc', __('状态'));
        $grid->column('user_tags', __('用户标签'))->filter('like');
        $grid->column('username', __('下单人'))->filter('like');
        $grid->column('phone', __('电话'))->filter('like');
        $grid->column('address', __('地址'))->width(100)->filter('like');
        $grid->column('juli', __('配送距离'))->filter('range')->sortable()->display(function($val){
            return $val > 1000 ? sprintf('%.1f', ($val / 1000)) . '公里' : $val . '米';
        });

        $grid->disableCreateButton();
        $grid->disableActions();//禁用行操作列
        $grid->disableFilter();//禁用查询过滤器
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();
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
        $show = new Show(Order::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('orderid', __('Orderid'));
        $show->field('product_amount', __('Product amount'));
        $show->field('store_id', __('Store id'));
        $show->field('merchantAmount', __('MerchantAmount'));
        $show->field('deliveryAmount', __('DeliveryAmount'));
        $show->field('channelId', __('ChannelId'));
        $show->field('orderStatus', __('OrderStatus'));
        $show->field('orderStatusDesc', __('OrderStatusDesc'));
        $show->field('itemCount', __('ItemCount'));
        $show->field('createTime', __('CreateTime'));
        $show->field('orderId_tm', __('OrderId tm'));
        $show->field('platform_id', __('Platform id'));
        $show->field('status', __('Status'));
        $show->field('addtime', __('Addtime'));
        $show->field('shipping_fee', __('Shipping fee'));
        $show->field('origin_content', __('Origin content'));
        $show->field('juli', __('Juli'));
        $show->field('lat', __('Lat'));
        $show->field('log', __('Log'));
        $show->field('userid', __('Userid'));
        $show->field('username', __('Username'));
        $show->field('phone', __('Phone'));
        $show->field('address', __('Address'));
        $show->field('butie', __('Butie'));
        $show->field('butie_platform', __('Butie platform'));
        $show->field('butie_details', __('Butie details'));
        $show->field('pack_fee', __('Pack fee'));
        $show->field('order_index', __('Order index'));
        $show->field('pay_amount', __('Pay amount'));
        $show->field('performService', __('PerformService'));
        $show->field('pay_time', __('Pay time'));
        $show->field('weight', __('Weight'));
        $show->field('comments', __('Comments'));
        $show->field('pack_status', __('Pack status'));
        $show->field('pack_status_desc', __('Pack status desc'));
        $show->field('used_time', __('Used time'));
        $show->field('jiedan_time', __('Jiedan time'));
        $show->field('pack_time', __('Pack time'));
        $show->field('pack_end_time', __('Pack end time'));
        $show->field('ship_time', __('Ship time'));
        $show->field('ship_end_time', __('Ship end time'));
        $show->field('done_time', __('Done time'));
        $show->field('user_tags', __('User tags'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Order());

        $form->text('orderid', __('Orderid'));
        $form->decimal('product_amount', __('Product amount'));
        $form->text('store_id', __('Store id'));
        $form->decimal('merchantAmount', __('MerchantAmount'));
        $form->decimal('deliveryAmount', __('DeliveryAmount'));
        $form->text('channelId', __('ChannelId'));
        $form->number('orderStatus', __('OrderStatus'));
        $form->text('orderStatusDesc', __('OrderStatusDesc'));
        $form->number('itemCount', __('ItemCount'));
        $form->text('createTime', __('CreateTime'));
        $form->text('orderId_tm', __('OrderId tm'));
        $form->number('platform_id', __('Platform id'));
        $form->switch('status', __('Status'))->default(1);
        $form->number('addtime', __('Addtime'));
        $form->text('shipping_fee', __('Shipping fee'));
        $form->textarea('origin_content', __('Origin content'));
        $form->text('juli', __('Juli'));
        $form->text('lat', __('Lat'));
        $form->text('log', __('Log'));
        $form->text('userid', __('Userid'));
        $form->text('username', __('Username'));
        $form->mobile('phone', __('Phone'));
        $form->text('address', __('Address'));
        $form->decimal('butie', __('Butie'));
        $form->decimal('butie_platform', __('Butie platform'));
        $form->textarea('butie_details', __('Butie details'));
        $form->text('pack_fee', __('Pack fee'));
        $form->number('order_index', __('Order index'));
        $form->decimal('pay_amount', __('Pay amount'));
        $form->decimal('performService', __('PerformService'));
        $form->number('pay_time', __('Pay time'));
        $form->text('weight', __('Weight'));
        $form->textarea('comments', __('Comments'));
        $form->number('pack_status', __('Pack status'));
        $form->text('pack_status_desc', __('Pack status desc'));
        $form->number('used_time', __('Used time'));
        $form->number('jiedan_time', __('Jiedan time'));
        $form->number('pack_time', __('Pack time'));
        $form->number('pack_end_time', __('Pack end time'));
        $form->number('ship_time', __('Ship time'));
        $form->number('ship_end_time', __('Ship end time'));
        $form->number('done_time', __('Done time'));
        $form->text('user_tags', __('User tags'));

        return $form;
    }
}
