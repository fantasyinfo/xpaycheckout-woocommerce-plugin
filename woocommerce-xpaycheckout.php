<?php

/*
 * Plugin Name:       WooCommerce XpayCheckout Payment Gateway
 * Description:       Custom payment gateway for WooCommerce to integrate XpayCheckout.
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Gaurav Sharma (fantasyinfo)
 * Author URI:        https://freelancer.com/u/fantasyinfo
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       xpay-checkout
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_xpaycheckout_gateway');

function init_xpaycheckout_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

   
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-xpaycheckout.php';
    function add_xpaycheckout_gateway($methods) {
        $methods[] = 'WC_Gateway_XpayCheckout';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_xpaycheckout_gateway');
}
?>
