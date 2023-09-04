<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller{
    public function index(Request $request){
        $data       = $request->post('list');
        $platform   = $request->post('platform');
        $data       = json_decode($data, true);

        if(!$data || !is_array($data)){
            return $this->error();
        }
    }
}
