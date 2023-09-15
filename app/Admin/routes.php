<?php

use Illuminate\Routing\Router;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
    'as'            => config('admin.route.prefix') . '.',
], function (Router $router) {

    $router->get('/', 'HomeController@index')->name('home');
    $router->resource('sdc/productsku', Sdc\ProductController::class);
    $router->resource('sdc/products', Sdc\ProductsController::class);
    $router->resource('product-skus', BindSkuController::class);
    $router->resource('pros', ProController::class);
    $router->resource('skus', SkuController::class);
});
