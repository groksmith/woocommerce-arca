<?php
/*
Plugin Name: ArCa - WooCommerce Gateway
Plugin URI: http://www.hexdivision.com/
Description: ArCa payment gateway for WooCommerce
Version: 1
Author: HexDivision
Author URI: http://www.hexdivision.com/
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Include our Gateway Class and Register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'woocommerce_arca_init', 0);

function woocommerce_arca_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    include_once('wc-arca-gateway.php');

    add_filter('woocommerce_payment_gateways', 'add_arca_gateway');

    /**
     * Add the gateway to WooCommerce
     **/
    function add_arca_gateway($methods)
    {
        $methods[] = 'WC_ArCa';
        return $methods;
    }

}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_arca_action_links');
function woocommerce_arca_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'arca') . '</a>',
    );

    return array_merge($plugin_links, $links);
}
