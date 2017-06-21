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
        $this->id = "arca";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __("ArCa", 'ArCa');

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("Description", 'ArCa payment gateway');

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __("Title", 'ArCa');

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = null;

        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();

        // True if test mode is enabled
        $this->environment = $this->settings['environment'];

        // ArCa username
        $this->username = $this->settings['username'];

        // ArCa password
        $this->password = $this->settings['password'];

        // Lets check for SSL
        add_action('admin_notices', array($this, 'do_ssl_check'));

        // Save settings
        if (is_admin()) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
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
        // who to charge and how much
        $customer_order = new WC_Order($order_id);

        // Are we testing right now or is it a real transaction
        $environment = ($this->environment == "yes") ? 'TRUE' : 'FALSE';

        // Decide which URL to post to
        $environment_url = ("FALSE" == $environment)
            ? 'https://ipay.arca.am/payment/rest/'
            : 'https://91.199.226.7:8445/payment/rest/';

        // This is where the fun stuff begins
        $payload = array(
            "userName" => $this->username,
            "password" => $this->password,
            "amount" => intval($customer_order->order_total) * 100,
            "returnUrl" => $this->get_return_url($customer_order),
            "orderNumber" => intval($customer_order->get_order_number()) + 119332,
        );

        // Send this payload to Authorize.net for processing
        $response = wp_remote_post($environment_url . "registerPreAuth.do", array(
            'method' => 'POST',
            'body' => http_build_query($payload),
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

        throw new Exception(__("Error processing checkout, please try again.", 'arca'));
    }
}