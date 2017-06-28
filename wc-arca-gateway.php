<?php

class WC_ArCa extends WC_Payment_Gateway
{
    private $environment;

    private $username;

    private $password;

    /**
     * WC_ArCa constructor.
     */
    function __construct()
    {

        $plugin_dir = plugin_dir_url(__FILE__);

        $this->id = "arca";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __("ArCa", 'ArCa');

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("ArCa payment gateway", 'Description');

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __("Title", 'ArCa');

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        //$this->icon = apply_filters('woocommerce_arca_icon', [$this, 'card_icons']);

        // Bool. Can be set to true if you want payment fields to show on the checkout
        $this->has_fields = true;

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // $this->title = $this->get_option( 'title' );
        $this->init_settings();

        // True if test mode is enabled
        $this->environment = $this->settings['environment'];

        // ArCa username
        $this->username = $this->settings['username'];

        // ArCa password
        $this->password = $this->settings['password'];

        $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'WC_ArCa', home_url('/')));

        // Lets check for SSL
        if (is_ssl()) {
            add_action('admin_notices', array($this, 'do_ssl_check'));
        }

        // Payment listener/API hook
        add_action('woocommerce_api_wc_arca', array($this, 'check_ipn_response'));

        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }
    }

    // Build the administration fields for this specific Gateway
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'arca'),
                'label' => __('Enable this payment gateway', 'arca'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'arca'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'arca'),
                'default' => __('Credit card', 'arca'),
            ),
            'description' => array(
                'title' => __('Description', 'arca'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'arca'),
                'default' => __('Pay securely using your credit card.', 'arca'),
                'css' => 'max-width:350px;'
            ),
            'username' => array(
                'title' => __('Username', 'username'),
                'type' => 'text',
                'desc_tip' => __('Username description.', 'username'),
            ),
            'password' => array(
                'title' => __('Password', 'password'),
                'type' => 'password',
                'desc_tip' => __('Password description.', 'password'),
            ),
            'environment' => array(
                'title' => __('ArCa Test Mode', 'arca'),
                'label' => __('Enable Test Mode', 'arca'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode.', 'arca'),
                'default' => 'no',
            )
        );
    }

    /**
     * Submit payment and handle response
     *
     * @param int $order_id
     * @throws Exception
     *
     * @return mixed
     */
    public function process_payment($order_id)
    {
        // Get this Order's information so that we know
        $customer_order = new WC_Order($order_id);
        $customer_order->update_status('on-hold', __('Awaiting BACS payment', 'woocommerce'));
        wc_reduce_stock_levels($order_id);
        $environment = ($this->environment == "yes") ? 'TRUE' : 'FALSE';
        $environment_url = ("FALSE" == $environment)
            ? 'https://ipay.arca.am/payment/rest/'
            : 'https://ipaytest.arca.am:8445/payment/rest/';

        $cur = $customer_order->get_currency();
        $cur = $this->codeCurrency($cur);

        $payload = array(
            "userName" => $this->username,
            "password" => $this->password,
            "amount" => floatval($customer_order->order_total) * 100,
            "returnUrl" => $this->notify_url,
            "description" => $this->method_description,
            "orderNumber" => intval($customer_order->get_order_number()) + 119332,
            "currency" => $cur
        );
        $ssl = false;

        if (is_ssl()) {
            $ssl = true;
        }

        $response = wp_remote_post($environment_url . "register.do", array(
            'method' => 'POST',
            'body' => http_build_query($payload),
            'timeout' => 90,
            'sslverify' => $ssl,
        ));

        if (is_wp_error($response)) {
            throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'arca'));
        }

        if (empty($response['body'])) {
            throw new Exception(__('ArCa\'s Response was empty.', 'arca'));
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (isset($response_data["errorCode"]) && isset($response_data["formUrl"])) {
            if ($response_data["errorCode"] == 0) {
                return array(
                    'result' => 'success',
                    'redirect' => $response_data['formUrl'],
                );
            } else {
                throw new Exception(__($response_data["errorMessage"], 'arca'));
            }
        }
        throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'arca'));
    }

    /**
     * Excange amount with currency
     * @param $currency string
     *
     * @return int|float
     */
    public function codeCurrency($currency)
    {
        if ($currency == 'AMD') {
            return '051';
        }

        if ($currency == 'USD') {
            return '840';
        }

        if ($currency == 'RUB') {
            return '643';
        }

        if ($currency == 'GBP') {
            return '840';
        }

        if ($currency == 'EUR') {
            return '978';
        }

        return '051';
    }

    /**
     * Check ARCA Payment network response and then redirect to error or thank you page
     * return mixed
     */
    function check_ipn_response()
    {
        try {
            global $woocommerce;
            @ob_clean();
            $orderId = $_GET['orderId'];

            $username = $this->username;
            $password = $this->password;
            $environment = $this->environment;

            $environment_url = ("FALSE" == $environment)
                ? 'https://ipay.arca.am/payment/rest/'
                : 'https://ipaytest.arca.am:8445/payment/rest/';

            $url = $environment_url . "getOrderStatus.do?" . 'orderId=' . $orderId . '&userName=' . $username . '&password=' . $password;
            $response = wp_remote_get($url, array(
                'method' => 'GET',
                'timeout' => 90,
                'sslverify' => false,
            ));

            if (is_wp_error($response)) {
                throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'arca'));
            }

            if (empty($response['body'])) {
                throw new Exception(__('ArCa\'s Response was empty.', 'arca'));
            }

            // Retrieve the body's response if no errors found
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            if (isset($response_data["ErrorCode"])) {
                if ($response_data["ErrorCode"] == 0) {

                    $orderId = (int)$response_data["OrderNumber"] - 119332;
                    $order = new WC_Order($orderId);

                    $order->payment_complete();
                    $woocommerce->cart->empty_cart();
                    $order->add_order_note(__('IPN payment completed', 'woothemes'));

                    wp_redirect($this->get_return_url($order));
                    exit;

                } else {
                    throw new Exception($response_data['ErrorMessage']);
                }
            } else {
                throw new Exception($response_data['ErrorMessage']);
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            wc_add_notice($error, $notice_type = 'error');
        }
    }

    function get_icon() {
        $icon  = '<img style="width:50px" src="'.plugin_dir_url(__FILE__).'/icons/visa.png" alt="Visa" />';
        $icon  .= '<img style="width:50px" src="'.plugin_dir_url(__FILE__).'/icons/mastercard.png" alt="MasterCard" />';
        $icon  .= '<img style="width:50px"  src="'.plugin_dir_url(__FILE__).'/icons/arca.png" alt="Arca" />';

        return apply_filters( 'woocommerce_arca_icon', $icon, $this->id );
    }
}