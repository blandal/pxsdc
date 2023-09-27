<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/products', 'App\Http\Controllers\api\ProductController@getindex');
Route::post('/products', 'App\Http\Controllers\api\ProductController@index');
// Route::get('/orders', 'App\Http\Controllers\api\ProductController@getorders');
Route::post('/orders', 'App\Http\Controllers\api\OrderController@orders');
// Route::get('/test', 'App\Http\Controllers\api\ProductController@test');


Route::get('/stocks2z', 'App\Http\Controllers\api\IndexController@stocks2z')->name('api.stocks2z');//将平台未关联上的产品库存设置未0
Route::get('/autolink', 'App\Http\Controllers\api\ProductController@autolink')->name('api.autolink');//自动关联平台间的产品
Route::get('/upccheck', 'App\Http\Controllers\api\ProductController@upccheck')->name('api.upccheck');//upc错误检查
Route::get('/syncMt2Elm', 'App\Http\Controllers\api\ProductController@syncMt2Elm')->name('api.syncMt2Elm');//upc错误检查
Route::get('/setorder', 'App\Http\Controllers\api\OrderController@setorder');

Route::get('/elesigns', function(){
    $arr        = [
        'token'     => '6f2101aaaa553684d515c7f39f3f2997',
        'ts'        => '1694150164285',
        'appkey'    => '12574478',
        'data'      => '{"pageSize":20,"pageNum":1,"sellerId":"2216508507961","storeIds":"[\"1097214140\"]","titleWithoutSplitting":true}',
    ];
    dd(md5(implode('&', array_values($arr))));
    return view('elesign');
});
