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
Route::post('/test', 'App\Http\Controllers\api\ProductController@test');
