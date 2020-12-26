<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use ZfrShopify\OAuth\AuthorizationRedirectResponse;
use ZfrShopify\Exception\InvalidRequestException;
use ZfrShopify\Validator\RequestValidator;
use ZfrShopify\ShopifyClient;
use GuzzleHttp\Client;
use ZfrShopify\OAuth\TokenExchanger;
use App\User;
use App\Shop;
use Auth; 


class ShopifyController extends Controller
{
    public function index(Request $request){

        $apiKey         = env("APP_API_KEY");   
        $shopDomain     = $request->input('shop');   
        $scopes         = ['write_orders', 'write_products', 'write_themes', 'write_script_tags', 'write_content'];
        $redirectionUri = env("REDIRECT_URL");      
        $nonce          = 'strong_nonce'; 

        $response = new AuthorizationRedirectResponse($apiKey, $shopDomain, $scopes, $redirectionUri, $nonce);
        return redirect($response->getHeader('location')[0]);
    } 

    public function redirect(Request $request){
        if (Shop::where('domain', '=', $_GET['shop'])->exists()) { 
                return "Already Installed"; 
        }
        else{
        // Set variables for our request
        $api_key = env("APP_API_KEY");
        $shared_secret = env("APP_SECRET_KEY");  
        $params = $_GET; // Retrieve all request parameters
        $hmac = $_GET['hmac']; // Retrieve HMAC request parameter
        $params = array_diff_key($params, array('hmac' => '')); // Remove hmac from params
        ksort($params); // Sort params lexographically

        // Compute SHA256 digest
        $computed_hmac = hash_hmac('sha256', http_build_query($params), $shared_secret);

        // Use hmac data to check that the response is from Shopify or not
        if (hash_equals($hmac, $computed_hmac)) {

        $shopDomain     = $_GET['shop'];
        $scopes         = ['write_orders', 'write_products', 'write_themes', 'write_script_tags', 'write_content'];
        $code           = $params['code'];
        $tokenExchanger = new TokenExchanger(new Client());
        $accessToken    = $tokenExchanger->exchangeCodeForToken($api_key, $shared_secret, $shopDomain,$scopes, $code);
        $shopifyClient = new ShopifyClient([
            'private_app'   => true,
            'api_key'       => $api_key,
            'password'  => $accessToken,
            'shop'          => $shopDomain,
            'version'       => '2020-10'  
        ]);
        $shopDomain2 = $shopifyClient->getShop();
        $mainshop = Shop::create([
            'domain' => $shopDomain2['domain'], 
            'access_token' => $accessToken,
        ]);
        return "App Installed"; 
        } else {
            dd('Something went wrong please try again');
        }
    }
}

public function create_webhok(Request $request){ 
    $api_key = env("APP_API_KEY");
    $access_token = Shop::pluck('access_token')->last(); 
    $shared_secret = $access_token; 

    $shopifyClient = new ShopifyClient([ 
        'private_app'   => true,
        'api_key'       => $api_key,
        'password'    => $shared_secret,
        'shop'          => 'printmysong.myshopify.com', 
        'version'       => '2020-10'  
    ]);  
    $res = $shopifyClient->createWebhook(array( 
        "topic" => "orders/create", 
        "address"=> env("WEBHOOK_URL"),     
        "format" => "json" 
        ));     
   return $res;        
}

public function call_webhok(Request $request){  

    $line_item_data = []; 
    $coupons = [];
    $discount = [];

    if($request->discount_codes){
        $coupons =  array(
        "coupon_name"=> $request->discount_codes[0]['title'] ?? null ,
        "coupon_amount"=>  $request->discount_codes[0]['value'] ?? null     
        );
    } 
    if($request->discount_applications){
        $discount =  array( 
        "coupon_name"=> $request->discount_applications[0]['title'] ?? null ,
        "coupon_amount"=>  $request->discount_applications[0]['value'] ?? null   
        );
    } 
    // return $discount;
    foreach ($request->line_items as $line_item){
        array_push($line_item_data, array(
        "lid" =>  $line_item['id'],
        "quantity" =>  $line_item['quantity'],
        "price" =>  $line_item['price'], 
        "sku" =>  $line_item['sku'] ?? null,  
        "hd_image" =>  $line_item['properties']['frame_image'] ?? null       
        ));
    } 
    // return $line_item_data;   
    $order_data = 
       array(
        "order_id" =>  $request->id,
        "order_date" => $request->created_at,
        "cart_sub_total" =>  $request->subtotal_price, 
        "shipment" => [array(
            "shipping_price" => $request->shipping_lines[0]['price'], 
            "shipping_declared_value" => $request->shipping_lines[0]['title'], 
            "shipping_label" => $request->shipping_lines[0]['title'],
            "shipping_service" => $request->shipping_lines[0]['source'], 
            "shipping_weight" => array( 
                "shipping_weight_units"=>  $request->total_weight,
                "shipping_weight_value"=> $request->total_weight 
            )
            )], 
        "cart_total" => $request->total_price, 
        "tax"=> $request->total_tax, 
        "customer_comments"=> null,
        "gift_message"=>null,
        "coupons"=> $coupons,
        "discount"=> [$discount],  
       "line_items"=> [$line_item_data], 
       "shipping_address_object" => array( 
        "first_name" => $request->shipping_address['first_name'],
        "last_name" =>$request->shipping_address['first_name'],
        "company" => $request->shipping_address['company'],
        "street1" => $request->shipping_address['address1'],
        "street2" => $request->shipping_address['address2'],
        "city" => $request->shipping_address['city'],
        "state" => $request->shipping_address['province_code'],
        "zip" => $request->shipping_address['zip'],
        "country" => $request->shipping_address['country_code'],
        "phone" => $request->shipping_address['phone'],
       ), 
       "billing_address_object" => array(
        "first_name" => $request->billing_address['first_name'],
        "last_name" =>$request->billing_address['first_name'],
        "email" => $request->customer['email'], 
        "street1" => $request->billing_address['address1'],
        "street2" => $request->billing_address['address2'],
        "company" => $request->billing_address['company'],
        "city" => $request->billing_address['city'],
        "zip" => $request->billing_address['zip'],
        "state" => $request->billing_address['province_code'],
        "country" => $request->billing_address['country_code'], 
       )

    ); 
    // print_r(json_encode($order_data));    
    $username = env("AMERICAN_USERNAME");
    $password = env("AMERICAN_PASS"); 
    $ch = curl_init();  
    curl_setopt($ch, CURLOPT_URL,env("REQUEST_URL"));     
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'X-CSRF-Token: env("AMERICAN_CSRF")'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($order_data)); 
    curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    $result  = curl_exec($ch);
    curl_close($ch);
    print_r($result);    
    
}
} 
