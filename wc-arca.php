<?php 
/*
  Plugin Name: Arca Payment Gateway
  Plugin URI: 
  Description: Allows to use ARCA payment gateway with WooCommerce plugin.
  Version: 0.1
  Author: Hovhannes Kuloghlyan
  Author URI: http://druid.am
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly



 /*
function arca_amd_currency( $currencies ) {
     $currencies['AMD'] = __( 'Armenian Dram', 'woothemes' );
     return $currencies;
}
  
function arca_amd_currency_symbol( $currency_symbol, $currency ) {
     switch( $currency ) {
          case 'AMD': $currency_symbol = "<span class='amd'>դր.</span>"; break;
     }
     return $currency_symbol;
}

add_filter( 'woocommerce_currencies', 'arca_amd_currency', 10, 1 );
add_filter( 'woocommerce_currency_symbol', 'arca_amd_currency_symbol', 10, 2);
*/







//ARCA Callback
function handle_additional_url() 
{
	$real_url = $_SERVER['REQUEST_URI'];	
	preg_match('/^\/([^\?]*)(\?.+)?$/i', $real_url, $real_matches);
	//preg_match('/^http(s)?\:\/\/[^\/]+\/(.*)$/i', $this->arca_additionalurl, $matches);
	
	//if($real_matches[1] == $matches[2])
	if($real_matches[1] == 'arca_callback')
	{
		// Get WC_ARCA class and call response trigger
		$wc_arca = new WC_ARCA();
		$wc_arca->check_ipn_response($_POST);
		die('1');
	}
}

add_action( 'init', 'handle_additional_url' );







/* Add a custom payment class to WC
  ------------------------------------------------------------ */

add_action('plugins_loaded', 'woocommerce_arca', 0);
function woocommerce_arca(){

	if (!class_exists('WC_Payment_Gateway'))
		return; // if the WC payment gateway class is not available, do nothing
	if(class_exists('WC_ARCA'))
		return;

class WC_ARCA extends WC_Payment_Gateway{
	public function __construct(){

		global $woocommerce;		
		
		$plugin_dir = plugin_dir_url(__FILE__);
        
        // Load ARCA translations
        load_plugin_textdomain('arca', false, $plugin_dir . 'languages/');

        // Set core gateway settings
        $this->method_title = __('ARCA', 'arca');
        $this->method_description = __('ARCA', 'arca');
        $this->id = 'arca';
   		$this->icon = apply_filters('woocommerce_arca_icon', ''.$plugin_dir.'arca.gif');
   		$this->has_fields = false;
		$this->arca_currency = "051";

        $this->version = '0.1';


        // Create admin configuration form
        $this->init_form_fields();
        // Initialise gateway settings
        $this->init_settings();


		// Define user set variables
		$this->arca_rooturl = $this->get_option('arca_rooturl');
		$this->arca_hostid = $this->get_option('arca_hostid');
		$this->arca_mid = $this->get_option('arca_mid');
		$this->arca_tid = $this->get_option('arca_tid');
		$this->arca_mtpass = $this->get_option('arca_mtpass');
		$this->arca_additionalurl = '/arca_callback';

		$this->action = $this->arca_rooturl.'/services/authorize.php';

		$this->title = $this->get_option('title');
		$this->debug = $this->get_option('debug');
		$this->testmode = $this->get_option('testmode');
		$this->description = $this->get_option('description');
		$this->instructions = $this->get_option('instructions');


		// Logs
		if ($this->debug == 'yes'){
			$this->log = $woocommerce->logger();
		}


		if (!$this->is_valid_for_use()) {
			$this->enabled = false;
		}

        // Check if on admin page
        if (is_admin()) {
        	if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                // WooCommerce 2.0.0+
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
        	    // WooCommerce 1.6.6 compatiblity
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
        }

		
		// Actions
		//add_action('valid-arca-standard-ipn-request', array($this, 'successful_request') );
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));


		// Payment listener/API hook
		//add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));
		
	}
	
	/**
	 * Check if this gateway is enabled and available in the user's country
	 */
	function is_valid_for_use(){
		if (!in_array(get_option('woocommerce_currency'), array('AMD'))){
			return false;
		}
		return true;
	}
	
	/**
	* Admin Panel Options 
	* - Options for bits like 'title' and availability on a country-by-country basis
	*
	**/
	public function admin_options() {
		?>
		<h3><?php _e('ARCA', 'arca'); ?></h3>
		<p><?php _e('ARCA Merchant e-payments gateway settings.', 'arca'); ?></p>

	  <?php if ( $this->is_valid_for_use() ) : ?>

		<table class="form-table">

		<?php    	
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
    ?>
    </table><!--/.form-table-->
    		
    <?php else : ?>
		<div class="inline error"><p><strong><?php _e('The gateway is switched off', 'arca'); ?></strong>: <?php _e('ARCA doesn\'t support currency of your shop ('. get_option('woocommerce_currency') .').', 'arca' ); ?></p></div>
		<?php
			endif;

    } // End admin_options()

  /**
  * Initialise Gateway Settings Form Fields
  *
  * @access public
  * @return void
  */
	function init_form_fields(){
		$this->form_fields = array(
				'enabled' => array(
					'title' => __('Switch On/Off', 'arca'),
					'type' => 'checkbox',
					'label' => __('On', 'arca'),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __('Title', 'arca'),
					'type' => 'text', 
					'description' => __( 'Title, which users will see at checkout.', 'arca' ), 
					'default' => __('ARCA', 'arca')
				),
				'arca_rooturl' => array(
					'title' => __('ARCA Service Root URL', 'arca'),
					'type' => 'text',
					'description' => __('Please enter Arca root url', 'arca'),
					'default' => 'https://91.199.226.106'
				),
				'arca_hostid' => array(
					'title' => __('Host ID', 'arca'),
					'type' => 'text',
					'description' => __('Please enter your host_id', 'arca'),
					'default' => ''
				),
				'arca_mid' => array(
					'title' => __('Merchant ID', 'arca'),
					'type' => 'text',
					'description' => __('Please enter your tid', 'arca'),
					'default' => ''
				),
				'arca_tid' => array(
					'title' => __('Terminal ID', 'arca'),
					'type' => 'text',
					'description' => __('Please enter your tid.', 'arca'),
					'default' => ''
				),
				'arca_mtpass' => array(
					'title' => __('Merchant password', 'arca'),
					'type' => 'text',
					'description' => __('Please enter mtpass.', 'arca'),
					'default' => ''
				),
				'debug' => array(
					'title' => __('Debug', 'arca'),
					'type' => 'checkbox',
					'label' => __('Enable logging (<code>arca/logs/arca.txt</code>)', 'arca'),
					'default' => 'no'
				),
				'test' => array(
					'title' => __('Test Mode', 'arca'),
					'type' => 'checkbox',
					'label' => __('Enable test mode.', 'arca'),
					'default' => 'no'
				),
				'description' => array(
					'title' => __( 'Description', 'arca' ),
					'type' => 'textarea',
					'description' => __( 'Payment method description for users.', 'arca' ),
					'default' => 'Pay through Armenian Card gateway.'
				),
				'instructions' => array(
					'title' => __( 'Instructions', 'arca' ),
					'type' => 'textarea',
					'description' => __( 'Instructions, which are visible on thanks page.', 'arca' ),
					'default' => 'Pay through Armenian Card gateway.'
				)
			);
	}

	/**
	* There are no payment fields for arca, but we want to show the description if set.
	**/
	function payment_fields(){
		if ($this->description){
			echo wpautop(wptexturize($this->description));
		}
	}

	/**
	* Generate the Arca button link
	**/
	public function generate_form($order_id){
		global $woocommerce;

		$order = new WC_Order( $order_id );
		$action_adr = $this->action;
		$out_summ = number_format($order->order_total, 2, '.', '');


		$args = array(
				// Merchant
				'hostID'			=> $this->arca_hostid,
				'mid'				=> $this->arca_mid,
				'tid'				=> $this->arca_tid,
				'additionalURL'		=> $this->arca_additionalurl,
				'orderID'			=> $order_id,
				'amount'			=> $out_summ,
				'currency'			=> $this->arca_currency,
				'opaque'			=> '23',
			);

		$paypal_args = apply_filters('woocommerce_arca_args', $args);
		$args_array = array();

		foreach ($args as $key => $value){
			$args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
		}

		return
			'<form action="'.esc_url($action_adr).'" method="POST" id="arca_payment_form">'."\n".
			implode("\n", $args_array).
			'<input type="submit" class="button alt" id="submit_arca_payment_form" value="'.__('Pay', 'arca').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order and return to cart', 'arca').'</a>'."\n".
			'</form>';
	}
	
	/**
	 * Process the payment and return the result
	 **/
	function process_payment($order_id) {
		global $woocommerce;
		$order = new WC_Order($order_id);

		return array(
			'result' => 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
		);
	}
	
	/**
	* receipt_page
	**/
	function receipt_page($order){
		echo '<p>'.__('Thank you for your order, please click the button bellow to pay with ARCA.', 'arca').'</p>';
		echo $this->generate_form($order);
	}

	/**
	* Check Response
	**/
	function check_ipn_response($post) {
		global $woocommerce;
		
		//var_dump($post);

		if(	isset($post['respcode']) && $post['respcode'] == '00' &&
			isset($post['orderID']) && ctype_digit($post['orderID']) ) {
			
			//echo 11 . ' ';
			
			$post = stripslashes_deep($post);

			$orderId = $post['orderID'];

			$order = new WC_Order($orderId);

			if ($order && $order->status == 'pending' ) {					
				//echo 12 . ' ';

				$postdata = array();
				$postdata['hostID'] = $this->arca_hostid;
				$postdata['orderID'] = $orderId;
				$amount = number_format($order->order_total, 2, '.', '');
				$postdata['amount'] = $amount;
				$postdata['currency'] = $this->arca_currency;
				$postdata['mid'] = $this->arca_mid;
				$postdata['tid'] = $this->arca_tid;
				$postdata['mtpass'] = $this->arca_mtpass;
				$postdata['trxnDetails'] = "Order placed";
				$res = $this->call_arca_rpc("merchant_check", $postdata);
				
                if ( 'yes' == $this->debug )
				{
					$this->log->add( 'arca', "ARCA :: MERCHANTCHECK REQUEST  = ". serialize($postdata));
					$this->log->add( 'arca', "ARCA :: MERCHANTCHECK RESPONCE = ". serialize($res));
				}
				
				//var_dump($res);
				//var_dump($postdata);
				
				if(isset($res['respcode']) && $res['respcode'] == "00" ) {				
					if($res['orderID'] == $orderId && $res['amount'] == $amount) {
						$this->data = $res;
						$res['mid'] = $this->arca_mid;
						$res['tid'] = $this->arca_tid;
						
						//echo 13 . ' ';
						
						$comment = "=== ARCA Transaction Details ===\r\n";
						
						$comment .= "Date/Time: ".$res['datetime']."\r\n";
						$comment .= "STAN: ".$res['stan']."\r\n";
						$comment .= "Auth Code: ".$res['authcode']."\r\n";
						$comment .= "RRN:	".$res['rrn']."\r\n";

						$postdata['trxnDetails'] = "Order confirmed";
						$res = $this->call_arca_rpc("confirmation", $postdata);

						//$order->update_status('on-hold', __('Order successfuly payed', 'arca'));
						$woocommerce->cart->empty_cart();

						// Payment completed
						$order->add_order_note(__('Order successfuly payed via Arca IPN', 'arca'));
						$order->payment_complete();

						wp_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $orderId, get_permalink(get_option('woocommerce_thanks_page_id')))));
						return;
					}
					else {
						$postdata['trxnDetails'] = "Order refused";
						$res = $this->call_arca_rpc("refuse", $postdata);
                		$order->update_status( 'failed', __( 'Payment via Arca has been refused', 'arca' ) ) ;							

						wp_redirect($order->get_cancel_order_url());
						return;
					}
				}
				elseif( isset($res['error']) ) {
					echo $res['error'];
					return;
				}
			}
		}
		elseif(isset($post['cancel']) && $post['cancel'] == 'CANCEL' ) {
			wp_redirect( home_url() );
			return;
		}
		
		//wp_redirect(home_url());
		//wp_redirect(add_query_arg('key', 123, add_query_arg('order', 123, get_permalink(get_option('woocommerce_thanks_page_id')))));
	}

	function call_arca_rpc($method, $arr) {
	
		$testmode = $this->arca_testmode;
		$url = $this->arca_rooturl;
		$url_arr = parse_url($url);
		
		if( $this->arca_testmode ) {
			$url_arr['path'] = 'ssljson.php';
		}
		else {
			$url_arr['port'] = 8194;
			$url_arr['path'] = 'ssljson.yaws';
		}

		$url = $url_arr['scheme'].'://'.$url_arr['host'];
		
		if( isset($url_arr['port']) )
			$url .= ':'.$url_arr['port'];
		
		$url .= '/'.$url_arr['path'];

		$postData = array (
	                    "id"=>"remoteRequest",
	                    "method"=>$method,
	                    "params"=> array($arr)
	                    );
		
		$postData = json_encode($postData);
		
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	    
	    $ret = curl_exec($ch);
		
		if($ret === false) {
			$decoded = array('error' => 'Curl error: ' . curl_error($ch));
		}
		else {
			$decoded = get_object_vars(json_decode($ret)->result);
		}
		
		curl_close($ch);

	    return $decoded;
	}
}


/**
 * Add the gateway to WooCommerce
 **/
function add_arca_gateway($methods){
	$methods[] = 'WC_ARCA';
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_arca_gateway');

}
