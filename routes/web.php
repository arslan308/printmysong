<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    Log::debug('abc'); 
    return view('welcome');    
});  
Route::get('/verify', 'ShopifyController@index')->name('verify'); 
Route::get('/authenticate', 'ShopifyController@redirect');
// Route::get('/create_webhok', 'ShopifyController@create_webhok'); 
Route::post('/call_webhok', 'ShopifyController@call_webhok');      
Route::post('/order_confirm', 'ShopifyController@order_confirm');     
// Route::get('/delweb', 'ShopifyController@delweb');   



//     $api_key = env("APP_API_KEY");
//     $shared_secret = env("APP_SECRET_KEY");

//     $shopifyClient = new ShopifyClient([ 
//         'private_app'   => true,
//         'api_key'       => $api_key,
//         'password'    => $shared_secret,
//         'shop'          => 'printmysong.myshopify.com', 
//         'version'       => '2020-10'  
//     ]);  
//    $res = $shopifyClient->getWebhooks();
//    print_r($res);       