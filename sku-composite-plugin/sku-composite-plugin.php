<?php
/**
 * Plugin Name:     Sku Composite Plugin
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          YOUR NAME HERE
 * Author URI:      YOUR SITE HERE
 * Text Domain:     sku-composite-plugin
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Sku_Composite_Plugin
 */

add_action('woocommerce_before_single_product', 'replace_template', 100);
add_filter( 'woocommerce_add_to_cart_validation', 'add_clara_composite_to_validation', 10, 3 );


function replace_template() {
    //add_action ('woocommerce_before_add_to_cart_button',   'add_sku_div');
    remove_all_actions('woocommerce_composited_product_add_to_cart');
}

function add_sku_div() {
    // Demo Function. Please insert your own SKU field from the frontend
    // $values = urlencode(json_encode(array("A1" => 4)));
    // echo '<span id="sku-field">
    // <input value="'.$values.'" name="threekit-skus" class="threekit-skus" display="none" style="visibility: hidden;">
    // </span>';
}


function find_wooproducts_by_clara_skus ($clara_skus){
    $clara_skus = json_decode(urldecode($clara_skus),true);
    if (empty($clara_skus)) {
        return null;
    }
    //match Clara SKUs with Woocom Product/Variation ids
    foreach ($clara_skus as $sku=>$quantity){
        $pid = wc_get_product_id_by_sku(trim($sku));
        $clara_pids[$pid] = $quantity;
    }

    return $clara_pids;
}


//small function that formats attribute values into proper format for composite_configuration
function get_options($variation_attributes){
      $variation_attributes_array =  $variation_attributes;
       return $variation_attributes_array;
}

function add_clara_composite_to_validation ($passed, $product_id, $qty) {
    $logger = wc_get_logger();
    $context = array('source' => 'Threekit-for-WooCommerce');

    $current_product = new WC_Product_Composite($product_id);
    $threekit_skus = $_POST['threekit-skus'];

    if (empty($threekit_skus))
    {
		var_dump($threekit_sku);
        return $passed;
    }

    if (!$current_product->is_type("composite"))
    {
        wc_add_notice("Not a composite product","error");
        $passed = false;
        return $passed;
    }

    $clara_pids = find_wooproducts_by_clara_skus ($threekit_skus);

    if (empty($clara_pids) ) {
        wc_add_notice("Missing SKU parameter","error");
        $passed = false;
        return $passed;
    }

    //get list of all the possible components for current composite
    $components  = $current_product->get_components();
    $comp_array = [];

    //match Woocommerce components to Clara Product ids
    foreach ($components as $component) {
        $data                 = $component->get_data();
        $component_id         = $data['component_id'];
        $component_product_id = $data['default_id'];
        $assigned_ids         = $data['assigned_ids'];

        foreach ($assigned_ids as $id) {
            $component_product    = wc_get_product( $id );
            $is_variable_product = $component_product->is_type( 'variable' );

            if ( $is_variable_product ) {
                $composite_variations = $component_product->get_children();
                $variation_ids        = array_intersect( $composite_variations, array_keys($clara_pids));
            }


            //first check variable products
            if ( $is_variable_product && $variation_ids ) {
                foreach ( $variation_ids as $variation_id ) {
                    $variation = wc_get_product($variation_id);
                    $variation_attributes_array =  $variation->get_variation_attributes();
                    $comp_array[ $component_id ] = array(
                        'product_id'   => $id,
                        'quantity'     => $clara_pids[$variation_id],
                        'variation_id' => $variation_id,
                        'attributes'   => $variation_attributes_array
                    );
                }
            }

            //secondly check simple products
            elseif (in_array($id, array_keys($clara_pids))) {
                $comp_array[ $component_id ] = array(
                    'product_id' => $id,
                    'quantity'   => $clara_pids[$id ],
                );
            }
        }
    }


    if(WC_CP()->cart->validate_composite_configuration( $product_id, $qty, $comp_array )){
        $added = WC_CP()->cart->add_composite_to_cart( $product_id, $qty, $comp_array );
    }
    else{
        $passed = false;
        return $passed;
    }

    remove_all_actions('woocommerce_add_to_cart');

    return $passed;
}

?>
