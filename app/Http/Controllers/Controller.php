<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function success($data = null, $msg = '', $code = 200){

    }
    public function error($msg = '', $data = null, $code = 500){

    }
    private function resp($data = null, $msg = '', $code = 200){

    }
}
