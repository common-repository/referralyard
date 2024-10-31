<?php
/*
Plugin Name: ReferralYard
Plugin URI: https://referralyard.com
Description: Grow your revenue & customer happiness with a Referral program.
Author: ReferralYard
Version: 1.3
*/

if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly

global $referralBaseUrl;
$referralBaseUrl = "https://referralyard.com/";

/**
 * ----------------------------------------------
 * Init the Plugin and Check WooCommerce
 * ----------------------------------------------
 */

add_action('after_setup_theme', 'referralyard_init');
if (!function_exists('referralyard_init')) {
    function referralyard_init() {
        global $referralyard_options;
        require plugin_dir_path( __FILE__ ).'settings-page.php';
        if(is_admin()) $my_settings_page = new referralyardSettingsPage();
        $referralyard_options = get_option('referralyard_options');
        $order_status = $referralyard_options['referralyard_order_status'];
        if($order_status) {
            $order_status = str_replace("wc-","",$order_status);
            add_action( 'woocommerce_order_status_' . $order_status, 'referralyard_process_order_api' );
        }
    }
}

register_activation_hook( __FILE__, 'referralyard_woo_plugin_activate' );
function referralyard_woo_plugin_activate(){
    // Require parent plugin
    if (!is_plugin_active( 'woocommerce/woocommerce.php' ) and current_user_can('activate_plugins')) {
        wp_die(__('Sorry, but this plugin requires Woocommerce Plugin to be installed and active.', 'woocommerce-referralyard') . '<br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; ' . __('Return to Plugins','woocommerce-referralyard') . '</a>');
    }
}

/**
 * ----------------------------------------------
 * API Call Helper
 * ----------------------------------------------
 */

function referralyard_api_call($data, $endpoint){
    global $referralBaseUrl;
    $url = $referralBaseUrl.ltrim($endpoint, '/');

    $response = wp_remote_post($url, [
        'body'        => $data,
        'timeout'     => '5',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array(),
        'cookies'     => array(),
    ]);
    $status = wp_remote_retrieve_response_code($response);
    return ['status' => $status];
}

/**
 * ----------------------------------------------
 * Verify account settings
 * ----------------------------------------------
 */
add_action('wp_ajax_referralyard_verify_account', 'referralyard_verify_account');
function referralyard_verify_account() {
    if(current_user_can('administrator')) {
        $data = array(
            'api_key'      => sanitize_text_field($_POST['api_key']),
            'api_secret'   => sanitize_text_field($_POST['api_secret']),
            'api_endpoint' => get_rest_url(null, '/referralyard')
        );
        $request = referralyard_api_call($data, '/api/webhook/common/verify/woocommerce');
        echo $request['status'];
    }
    die();
}

/**
 * ----------------------------------------------
 * Order event - API Call
 * ----------------------------------------------
 */
function referralyard_process_order_api($order_id) {
    $referralyard_options = get_option('referralyard_options');
    $order = wc_get_order($order_id);
    if($order) {
        $coupons = [];
        $orderArray = (array) $order->data;
        $data = [
            'api_key' => $referralyard_options['referralyard_api_key'],
            'secret_key' => $referralyard_options['referralyard_api_secret'],
            'order_id' => $orderArray['id'],
            'status' => $orderArray['status'],
            'price' => $orderArray['total'],
            'customer_id' => $orderArray['customer_id'],
            'customer_name' => $orderArray['billing']['first_name'].' '.$orderArray['billing']['last_name'],
            'customer_email' => $orderArray['billing']['email'],
            'coupons' => $order->get_coupon_codes()
        ];
        $result = referralyard_api_call($data, "/api/webhook/common/order/created");
    }
}

/**
 * ----------------------------------------------
 * Add ReferralYard Scripts to Header
 * ----------------------------------------------
 */
add_action('wp_head', 'add_referralyard_integration_script', 2);
function add_referralyard_integration_script() {
    global $referralBaseUrl;
    $referralyard_options = get_option('referralyard_options');
    echo '<script>
        window.ReferralYard = {
            provider: "'.$referralBaseUrl.'",
            api_key: "'.$referralyard_options['referralyard_api_key'].'",
            customer_id: "'.get_current_user_id().'",
            customer_name: "'.wp_get_current_user()->user_firstname.' '.wp_get_current_user()->user_lastname.'",
            customer_email: "'.wp_get_current_user()->user_email.'"
        }
    </script>';
}

add_action('wp_enqueue_scripts', 'referralyard_integration_enqueue_script');
function referralyard_integration_enqueue_script() {
    global $referralBaseUrl;
    wp_enqueue_script('referralyard_integration_script', $referralBaseUrl.'js/integrations/script.js');
}

/**
 * ----------------------------------------------
 * Generate coupons
 * ----------------------------------------------
 */
add_action('rest_api_init', function () {
    register_rest_route('referralyard', 'generate-coupon', array(
        'methods' => 'POST',
        'callback' => 'referralyard_generate_coupon',
    ));
});

function referralyard_generate_coupon($request) {
    $data = $request->get_params();
    $referralyard_options = get_option('referralyard_options');

    if(isset($data['api_key']) && 
       isset($data['api_secret']) && 
       $data['api_key'] == $referralyard_options['referralyard_api_key'] && 
       $data['api_secret'] == $referralyard_options['referralyard_api_secret']
    ) {

        $coupon_code = substr("abcdefghijklmnopqrstuvwxyz123456789", mt_rand(0, 50), 1).substr(md5(time()), 1);
        $coupon_code = substr($coupon_code, 0, 10);    
        $amount = $data['value']; // Amount
        $discount_type = $data['type']; // Type: fixed_cart, percent
        $min_order_value = $data['min_order_value']; // Minimum order ammount
        $customer_email = [];
        if(isset($data['email'])) {
            $customer_email = is_array($data['email']) ? $data['email'] : [$data['email']];
        }

        $coupon = wp_insert_post([
            'post_title' => $coupon_code,
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'shop_coupon'
        ]);

        // Add meta
        update_post_meta($coupon, 'discount_type', $discount_type);
        update_post_meta($coupon, 'coupon_amount', $amount);
        update_post_meta($coupon, 'individual_use', 'no');
        update_post_meta($coupon, 'usage_limit', 1);
        update_post_meta($coupon, 'customer_email', $customer_email);
        if($min_order_value && $min_order_value > 0) {
            update_post_meta($coupon, 'minimum_amount', $min_order_value);
        }
        
        $response = new WP_REST_Response(['coupon_code' => $coupon_code]);
        $response->set_status(200);
        return $response;
    }

    return new WP_Error('cannot_generate_coupon', 'Cannot generate coupon', array('status' => 403) );
}