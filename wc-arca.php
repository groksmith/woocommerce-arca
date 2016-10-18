<?php
/*
Plugin Name: ArCa - WooCommerce Gateway
Plugin URI: http://www.hexdivision.com/
Description: ArCa payment gateway for WooCommerce
Version: 1
Author: HexDivision
Author URI: http://www.hexdivision.com/
*/

// Include our Gateway Class and Register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'woocommerce_arca_init', 0 );
function woocommerce_arca_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	// If we made it this far, then include our Gateway Class
	include_once( 'wc-arca-gateway.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'add_arca_gateway' );
	function add_arca_gateway( $methods ) {
		$methods[] = 'WC_ArCa';
		return $methods;
	}
}


// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_arca_action_links' );
function woocommerce_arca_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'arca' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}