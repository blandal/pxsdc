<?php

namespace App\Admin\Extensions\Tools;

use Encore\Admin\Admin;
use Encore\Admin\Grid\Tools\AbstractTool;
use Illuminate\Support\Facades\Request;

class Orders extends AbstractTool
{
    protected function script()
    {
        $url = route('admin.orders.index');

        return <<<EOT

$('.addvirtuls').click(function () {

    var url = "$url";

    $.pjax({container:'#pjax-container', url: url });

});

EOT;
    }

    public function render()
    {
        Admin::script($this->script());

        return view('admin.tools.orders');
    }
}