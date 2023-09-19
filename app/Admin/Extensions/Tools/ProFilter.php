<?php

namespace App\Admin\Extensions\Tools;

use Encore\Admin\Admin;
use Encore\Admin\Grid\Tools\AbstractTool;
use Illuminate\Support\Facades\Request;

class ProFilter extends AbstractTool
{
    protected function script()
    {
        $url = Request::fullUrlWithQuery(['errs' => '_errs_']);

        return <<<EOT

$('input:radio.pro-err').change(function () {

    var url = "$url".replace('_errs_', $(this).val());

    $.pjax({container:'#pjax-container', url: url });

});

EOT;
    }

    public function render()
    {
        Admin::script($this->script());

        $options = [
            'a'     => __('全部'),
            'e'     => __('UPC错误'),
            'r'     => __('UPC重复'),
        ];

        return view('admin.tools.proerr', compact('options'));
    }
}