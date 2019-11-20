<?php
/**
 * Electro Child
 *
 * @package electro-child
 */
add_filter( 'wpcf7_autop_or_not', '__return_false' );
add_filter('wpcf7_form_elements', function($content) {
    $content = preg_replace('/<(span).*?class="\s*(?:.*\s)?wpcf7-form-control-wrap(?:\s[^"]+)?\s*"[^\>]*>(.*)<\/\1>/i', '\2', $content);

    return $content;
});


add_filter('woocommerce_product_accessories_tab_title', function($content) {
    $content = preg_replace('/Accessories/', 'Аксессуары', $content);

    return $content;
});

add_filter('woocommerce_product_specification_tab_title', function($content) {
    $content = preg_replace('/Specification/', 'Характеристика', $content);

    return $content;
});

add_filter('woocommerce_product_reviews_tab_title', function($content) {
    $content = preg_replace('/Reviews/', 'Отзывы', $content);

    return $content;
});

function woocommerce_template_loop_product_title() { 
    echo '<div class="woocommerce-loop-product__title">' . get_the_title() . '</div>'; 
} 

add_filter('woocommerce_variable_price_html', 'custom_variation_price', 10, 2);

    function custom_variation_price( $price, $product ) {

        foreach($product->get_available_variations() as $pav){
            $def=true;
            foreach($product->get_variation_default_attributes() as $defkey=>$defval){
                if($pav['attributes']['attribute_'.$defkey]!=$defval){
                    $def=false;             
                }   
            }
            if($def){
                $price = $pav['display_price'];         
            }
        }
        
        if(is_product_category())
            return woocommerce_price($price);
    }


/**
 * Include all your custom code here
 */