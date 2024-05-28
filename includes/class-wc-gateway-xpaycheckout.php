<?php 

if (!defined('ABSPATH')) {
    exit;
}
class WC_Gateway_XpayCheckout extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'xpaycheckout';
        $this->icon = ''; // URL to your icon
        $this->has_fields = false;
        $this->method_title = 'XpayCheckout';
        $this->method_description = 'Allows payments with XpayCheckout.';
        
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->xpay_public_key = $this->get_option('xpay_public_key');
        $this->xpay_secret_key = $this->get_option('xpay_secret_key');
        $this->callback_url = $this->get_option('callback_url');

        // Save settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_xpaycheckout', array($this, 'handle_callback'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable XpayCheckout Payment Gateway',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'Title that the user sees during checkout.',
                'default' => 'XpayCheckout',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'Description that the user sees during checkout.',
                'default' => 'Pay securely using XpayCheckout.',
            ),
            'xpay_public_key' => array(
                'title' => 'Xpay Public Key',
                'type' => 'text'
            ),
            'xpay_secret_key' => array(
                'title' => 'Xpay Secret Key',
                'type' => 'password'
            ),
            'callback_url' => array(
                'title' => 'Callback URL',
                'type' => 'text',
                'description' => 'The URL where the payment gateway will send the payment status.',
            ),
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Create payment intent
        $response = $this->create_payment_intent($order);
        if (!$response) {
            wc_add_notice('Payment error: Unable to create payment intent', 'error');
            return;
        }

        // Redirect to payment page
        return array(
            'result' => 'success',
            'redirect' => 'https://pay.xpaycheckout.com?xpay_intent_id=' . $response->xIntentId . '&xpay_payment_type=null'
        );
    }

    private function create_payment_intent($order) {
        $public_key = $this->xpay_public_key;
        $secret_key = $this->xpay_secret_key;
        $callback_url = $this->callback_url;

        $body = json_encode(array(
            'amount' => $order->get_total() * 100, // Xpay expects amount in cents
            'currency' => get_woocommerce_currency(),
            'receiptId' => $order->get_order_number(),
            'customerDetails' => array(
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'contactNumber' => $order->get_billing_phone()
            ),
            'description' => 'Order for ' . $order->get_item_count() . ' items',
            'callbackUrl' => $callback_url
        ));

        
     
        $response = wp_remote_post('https://api.xpaycheckout.com/payments/create-intent', array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($public_key . ':' . $secret_key),
                'Content-Type' => 'application/json'
            ),
            'body' => $body
        ));
   
        if (is_wp_error($response)) {
            return false;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response));

        if ($response_body && isset($response_body->xIntentId)) {
            return $response_body;
        }

        return false;
    }

     public function handle_callback() {
// Check if the 'xpay_intent_id' key exists in the $_GET array
if (!isset($_GET['xpay_intent_id'])) {
    wp_redirect(home_url()); // Redirect to home page if Xpay Intent ID is not found
    exit;
}

$xpay_intent_id = sanitize_text_field($_GET['xpay_intent_id']);

// Retrieve the order ID associated with the xpay_intent_id
$order_id = $this->get_order_id_from_xpay_intent_id($xpay_intent_id);

if (!$order_id) {
    wp_redirect(home_url()); // Redirect to home page if order ID is not found
    exit;
}

$order = wc_get_order($order_id);

if (!$order) {
    wp_redirect(home_url()); // Redirect to home page if order is not found
    exit;
}

// Mark the order as payment complete and add order note
$order->payment_complete();
$order->add_order_note('Payment received via XpayCheckout.');

// Reduce stock levels
wc_reduce_stock_levels($order_id);

// Redirect to the thank you page
$redirect_url = $this->get_return_url($order);
wp_redirect($redirect_url);
exit;
}


  private function get_order_id_from_xpay_intent_id($xpay_intent_id) {
// Construct the URL with the Xpay Intent ID as a query parameter
$api_url = "https://api.xpaycheckout.com/payments/get-intent/$xpay_intent_id";

// Get the XpayCheckout API credentials
$public_key = $this->xpay_public_key;
$secret_key = $this->xpay_secret_key;

// Send a GET request to the XpayCheckout API to retrieve the order ID
$response = wp_remote_get($api_url, array(
    'headers' => array(
        'Authorization' => 'Basic ' . base64_encode($public_key . ':' . $secret_key),
        'Content-Type' => 'application/json'
    )
));

if (is_wp_error($response)) {
    return false;
}

$response_body = json_decode(wp_remote_retrieve_body($response));

// Check if the response contains the order ID
// Check if the response contains the order ID
if ($response_body && isset($response_body->status) && $response_body->status === 'SUCCESS' && isset($response_body->receiptId)) {
    // Return the receiptId as the order ID
    return $response_body->receiptId;
} else {
    // Return false if the order ID is not found in the response or status is not SUCCESS
    return false;
}
}


}