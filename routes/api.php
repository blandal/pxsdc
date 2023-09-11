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
Route::get('/orders', 'App\Http\Controllers\api\ProductController@getorders');
Route::post('/orders', 'App\Http\Controllers\api\ProductController@orders');
Route::get('/elesign', 'App\Http\Controllers\api\ProductController@elesign');
Route::get('/test', 'App\Http\Controllers\api\ProductController@test');

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
