function get_api_token(){
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://auth.deftinvoice.com/Token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=password&client_id=6ecf2a98-c6d9-4627-9909-3657f3d7928d&client_secret=d44da381&password=apiuser&username=apiuser");
    curl_setopt($ch, CURLOPT_POST, 1);

    $headers = array();
    $headers[] = "Accept: application/json";
    $headers[] = "Content-Type: application/x-www-form-urlencoded";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
       // echo 'Error:' . curl_error($ch);
    }
    curl_close ($ch);
    $token_array = json_decode($result);    
    $token = $token_array->access_token;
    
    return $token;
}

// Latest David Stuff
// On Purchase of product
add_action( 'woocommerce_order_status_completed', 'wc_order_completed', 10, 1 );

function wc_order_completed( $order_id ) {

    //Get Product Id then get sku/qty

    $token = get_api_token();

    $order = wc_get_order( $order_id );
    $items = $order->get_items();

    $newarray["InvoiceNo"] = $order_id;

    foreach ($items as $item_key => $item_values):

        $item_id = $item_values->get_id();

        $item_name = $item_values->get_name(); // Name of the product
        $item_type = $item_values->get_type(); // Type of the order item ("line_item")

        $product_id = $item_values->get_product_id(); // the Product id
        $wc_product = $item_values->get_product(); // the WC_Product object
        ## Access Order Items data properties (in an array of values) ##
        $item_data = $item_values->get_data();

        $product_name = $item_data['name'];
        $product_id = $item_data['product_id'];
        $variation_id = $item_data['variation_id'];
        $quantity = $item_data['quantity'];

        if ($variation_id){
             $product = $item_data['variation_id'];
          } else {
            $product = $item_data['product_id'];
          }

          // Get SKU
          $sku = $wc_product->get_sku();

          // Get what's left
          $numleft  = $wc_product->get_stock_quantity(); 

        $newarray["UnitLines"][] = array(
            "Qty" => $quantity,
            "SKU" => $sku
        );

    endforeach;

    $wee = json_encode($newarray);

    //POST IT
    $post = json_encode($newarray);

    $ch = curl_init('https://zzlsapi.deftinvoice.com/UpdateInventory');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

    $headers = array();
    //$headers[] = "Accept: application/json";
    $headers[] = "Content-Type: application/json";
    $headers[] = "Authorization: Bearer ". $token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // execute!
    $response = curl_exec($ch);

    // close the connection, release resources used
    curl_close($ch);

    $response_re = json_encode($response);

//     error_log( "Order complete for order $order_id | Items: $wee | Product Name: $product_name | Product Qty: $quantity | Product ID: $product | SKU: $sku | What is Left: $numleft | Response: $response_re | POST: $post", 1, "david.hinojosa@voicemediagroup.com" );
}



// Pulls products from woo and gets sku/qty
// next will be to update qty of local woo products

function get_product_info_final(){
    $token = get_api_token();

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://zzlsapi.deftinvoice.com/ProductsForWoocommerce");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

    $headers = array();
    $headers[] = "Accept: application/json";
    $headers[] = "Authorization: Bearer ". $token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close ($ch);
    $product_result = json_decode($result);
    // echo '<pre>';
    // var_dump($product_result);
    // echo '</pre>';

    $items = $product_result;
    foreach ( $items as $item ):

        if (!empty($item->SKU)){
            $api_product_list[] = array(
                'SKU' => $item->SKU,
                'QTY' => $item->Qty,
                'ProductID' => wc_get_product_id_by_sku($item->SKU),
            );
        }

    endforeach;

    foreach ($api_product_list as $key => $products):
        if ($products['ProductID'] === 0){
            unset($api_product_list[$key]);
        }

    endforeach;
    
//      echo '<pre>';
//      print_r($api_product_list);
//      echo '</pre>';

        $final_result = $api_product_list;

//  echo '<pre>';
//  var_dump($items);
//  echo '</pre';
    return $final_result;
}
  add_shortcode('get_product_info_final', 'get_product_info_final');
  function update_local_qty(){
    $product_list = get_product_info_final();
    //$wooProd = new WC_Product_Data_Store_Interface();
    //$wooProd = new WC_Product_Data_Store_CPT();
    foreach ($product_list as $product):
        if(wc_update_product_stock($product['ProductID'],$product['QTY'], 'set')){
//              echo 'It ran!';
//              echo '<br>';
//              echo $product['ProductID'];
//              echo '<br>';
//              echo $product['QTY'];
//              echo '<br>';
        } else{
//              echo 'Uhoh!';
//              echo '<br>';
//              echo $product['ProductID'];
//              echo '<br>';
//              echo $product['QTY'];
//              echo '<br>';
        }
    endforeach;
  }
