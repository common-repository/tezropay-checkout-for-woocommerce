<?php
/**
 * Plugin Name: TezroPay Checkout for WooCommerce
 * Plugin URI: https://www.tezro.com
 * Description: Create Payments through TezroPay.
 * Version: 1.1.9
 * Author: Tezro
 * Author URI: mailto:secured@tezro.com?subject=TezroPay Checkout for WooCommerce
 */
if (!defined('ABSPATH')): exit;endif;
add_action('wp_enqueue_scripts', 'enable_tezropayquickpay_js');
global $current_user;
#autoloader
function TEZRO_autoloader($class)
{
    if (strpos($class, 'TEZRO_') !== false):
        if (!class_exists('TEZROLib/' . $class, false)):
            #if doesnt exist so include it
            include 'TEZROLib/' . $class . '.php';
        endif;
    endif;
}
function TEZRO_Logger($msg, $type = null, $isJson = false, $error = false)
{
    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    $structure = plugin_dir_path(__FILE__) . 'logs/';
    if (!file_exists($structure)) {
        mkdir($structure);
    }
    $transaction_log = plugin_dir_path(__FILE__) . 'logs/' . date('Ymd') . '_transactions.log';
    $error_log = plugin_dir_path(__FILE__) . 'logs/' . date('Ymd') . '_error.log';

    $header = PHP_EOL . '======================' . $type . '===========================' . PHP_EOL;
    $footer = PHP_EOL . '=================================================' . PHP_EOL;

    if ($error):
        error_log($header, 3, $error_log);
        error_log($msg, 3, $error_log);
        error_log($footer, 3, $error_log);
    else:
        if ($tezropay_checkout_options['tezropay_log_mode'] == 1):
            error_log($header, 3, $transaction_log);
            if ($isJson):
                error_log(print_r($msg, true), 3, $transaction_log);
            else:
                error_log($msg, 3, $transaction_log);
            endif;
            error_log($footer, 3, $transaction_log);
        endif;
    endif;
}

spl_autoload_register('TEZRO_autoloader');

#check and see if requirements are met for turning on plugin
function tezropay_checkout_woocommerce_tezropay_failed_requirements()
{
    global $wp_version;
    global $woocommerce;
    $errors = array();

    // WooCommerce required
    if (true === empty($woocommerce)) {
        $errors[] = 'The WooCommerce plugin for WordPress needs to be installed and activated. Please contact your web server administrator for assistance.';
    } elseif (true === version_compare($woocommerce->version, '2.2', '<')) {
        $errors[] = 'Your WooCommerce version is too old. The TezroPay payment plugin requires WooCommerce 2.2 or higher to function. Your version is ' . $woocommerce->version . '. Please contact your web server administrator for assistance.';
    }
    if (empty($errors)):
        return false;
    else:
        return implode("<br>\n", $errors);
    endif;
}

add_action('plugins_loaded', 'wc_tezropay_checkout_gateway_init', 11);
#create the table if it doesnt exist

#clear the cart if using a custom page
add_action( 'init', 'tezropay_woocommerce_clear_cart_url' );
function tezropay_woocommerce_clear_cart_url() {
    if ( isset( $_GET['custompage'] ) ) {
        global $woocommerce;
        $woocommerce->cart->empty_cart();
    }
}

function tezropay_checkout_plugin_setup()
{

    $failed = tezropay_checkout_woocommerce_tezropay_failed_requirements();
    $plugins_url = admin_url('plugins.php');

    if ($failed === false) {

        global $wpdb;
        $table_name = '_tezropay_checkout_transactions';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` varchar(255) NOT NULL,
        `transaction_id` varchar(255) NOT NULL,
        `transaction_status` varchar(50) NOT NULL DEFAULT 'new',
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        #check out of date plugins
        $plugins = get_plugins();
        foreach ($plugins as $file => $plugin) {
            if ('Tezropay Woocommerce' === $plugin['Name'] && true === is_plugin_active($file)) {
                deactivate_plugins(plugin_basename(__FILE__));
                wp_die('TezroPay for WooCommerce requires that the old plugin, <b>Tezropay Woocommerce</b>, is deactivated and deleted.<br><a href="' . esc_url($plugins_url) . '">Return to plugins screen</a>');
            }
        }

    } else {

        // Requirements not met, return an error message
        wp_die($failed . '<br><a href="' . esc_url($plugins_url) . '">Return to plugins screen</a>');

    }

}
register_activation_hook(__FILE__, 'tezropay_checkout_plugin_setup');

function tezropay_checkout_insert_order_note($order_id = null, $transaction_id = null)
{
    global $wpdb;

    if ($order_id != null && $transaction_id != null):
        global $woocommerce;

    //Retrieve the order
    $order = new WC_Order($order_id);
    $order->set_transaction_id($transaction_id);
    $order->save();
    //Retrieve the transaction ID

        $table_name = '_tezropay_checkout_transactions';
        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'transaction_id' => $transaction_id,
            )
        );
    else:
        TEZRO_Logger('Missing values' . PHP_EOL . 'order id: ' . $order_id . PHP_EOL . 'transaction id: ' . $transaction_id, 'error', false, true);
    endif;

}

function tezropay_checkout_update_order_note($order_id = null, $transaction_id = null, $transaction_status = null)
{
    global $wpdb;
    $table_name = '_tezropay_checkout_transactions';
    if ($order_id != null && $transaction_id != null && $transaction_status != null):
        $wpdb->update($table_name, array('transaction_status' => $transaction_status), array("order_id" => $order_id, 'transaction_id' => $transaction_id));
    else:
        TEZRO_Logger('Missing values' . PHP_EOL . 'order id: ' . $order_id . PHP_EOL . 'transaction id: ' . $transaction_id . PHP_EOL . 'transaction status: ' . $transaction_status . PHP_EOL, 'error', false, true);
    endif;
}

function tezropay_checkout_get_order_transaction($order_id, $transaction_id)
{
    global $wpdb;
    $table_name = '_tezropay_checkout_transactions';
    $rowcount = $wpdb->get_var($wpdb->prepare("SELECT COUNT(order_id) FROM $table_name WHERE transaction_id = %s",$transaction_id));
    return $rowcount;

}
function tezropay_checkout_get_order_id_tezropay_invoice_id($transaction_id)
{
    global $wpdb;
    $table_name = '_tezropay_checkout_transactions';
    $order_id = $wpdb->get_var($wpdb->prepare("SELECT order_id FROM $table_name WHERE transaction_id = %s LIMIT 1", $transaction_id));
    return $order_id;
}
function tezropay_checkout_delete_order_transaction($order_id)
{
    global $wpdb;
    $table_name = '_tezropay_checkout_transactions';
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE order_id = %s",$order_id));

}

function wc_tezropay_checkout_gateway_init()
{     

    if (class_exists('WC_Payment_Gateway')) {
        class WC_Gateway_TezroPay extends WC_Payment_Gateway
        {

            public function __construct()
            {
                $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');

                $this->id = 'tezropay_checkout_gateway';
                $this->icon = TEZRO_getTezroPaymentIcon();

                $this->has_fields = true;
                $this->method_title = __(TEZRO_getTezroPayVersionInfo($clean = true), 'wc-tezropay');
                $this->method_label = __('TezroPay', 'wc-tezropay');
                $this->method_description = __('Expand your payment options by accepting cryptocurrency payments (BTC, BCH, ETH, and Stable Coins) without risk or price fluctuations.', 'wc-tezropay');

                if (empty($_GET['woo-tezropay-return'])) {
                    $this->order_button_text = __('Pay with TezroPay', 'woocommerce-gateway-tezropay_checkout_gateway');

                    
                }
                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user set variables
                $this->title = 'TezroPay';
                $this->description = $this->get_option('description') . '<br>';
                $this->instructions = $this->get_option('instructions', $this->description);

                // Actions
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                // Customer Emails
                add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
            }
            public function email_instructions($order, $sent_to_admin, $plain_text = false)
            {
                if ($this->instructions && !$sent_to_admin && 'tezropay_checkout_gateway' === $order->get_payment_method() && $order->has_status('processing')) {
                    echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
                }
            }
            public function init_form_fields()
            {
                $wc_statuses_arr = wc_get_order_statuses();
                unset($wc_statuses_arr['wc-cancelled']);
                unset($wc_statuses_arr['wc-refunded']);
                unset($wc_statuses_arr['wc-failed']);
                #add an ignore option
                $wc_statuses_arr['tezropay-ignore'] = "Do not change status";
                $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
                $tezropay_checkout_endpoint = $tezropay_checkout_options['tezropay_checkout_endpoint'];

               
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woocommerce'),
                        'label' => __('Enable TezroPay', 'woocommerce'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no',
                    ),
                    'tezropay_checkout_info' => array(
                        'description' => __('You should not ship any products until TezroPay has finalized your transaction.<br>The order will stay in a <b>Hold</b> and/or <b>Processing</b> state, and will automatically change to <b>Completed</b> after the payment has been confirmed.', 'woocommerce'),
                        'type' => 'title',
                    ),

                    'tezropay_checkout_merchant_info' => array(
                        'description' => __('If you have not created a TezroPay Merchant Token, you can create one in your TezroPay App.', 'woocommerce'),
                        'type' => 'title',
                    ),

                    'tezropay_checkout_tier_info' => array(
                        'description' => __('<em><b>*** </b>If you are having trouble creating TezroPay invoices, verify your settings on your app.', 'woocommerce'),
                        'type' => 'title',
                    ),
                   
                    'description' => array(
                        'title' => __('Description', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This is the message box that will appear on the <b>checkout page</b> when they select TezroPay.', 'woocommerce'),
                        'default' => 'Pay with TezroPay using one of the supported payment methods',

                    ),
                    'tezropay_checkout_token_dev_1' => array(
                        'title' => __('Key ID (Test)', 'woocommerce'),
                        'label' => __('Key ID (Test)', 'woocommerce'),
                        'type' => 'text',
                        'description' => 'Your <b>development</b> Key ID.  <a href = "https://tezro.com/documentation.html" target = "_blank">Create one here</a>`.',
                        'default' => '',

                    ),
                    'tezropay_checkout_token_dev_2' => array(
                        'title' => __('Secret (Test)', 'woocommerce'),
                        'label' => __('Secret (Test)', 'woocommerce'),
                        'type' => 'text',
                        'description' => 'Your <b>development</b> Secret token.  <a href = "https://tezro.com/documentation.html" target = "_blank">Create one here</a>.',
                        'default' => '',

                    ),
                    'tezropay_checkout_token_prod_1' => array(
                        'title' => __('Key ID', 'woocommerce'),
                        'label' => __('Key ID', 'woocommerce'),
                        'type' => 'text',
                        'description' => 'Your <b>production</b> Key ID.  <a href = "https://tezro.com/documentation.html" target = "_blank">Create one here</a>.',
                        'default' => '',

                    ),
                    'tezropay_checkout_token_prod_2' => array(
                        'title' => __('Secret', 'woocommerce'),
                        'label' => __('Secret', 'woocommerce'),
                        'type' => 'text',
                        'description' => 'Your <b>production</b> Secret token.  <a href = "https://tezro.com/documentation.html" target = "_blank">Create one here</a>`.',
                        'default' => '',

                    ),
                    'tezropay_checkout_endpoint' => array(
                        'title' => __('Endpoint', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('Select <b>Test</b> for testing the plugin, <b>Production</b> when you are ready to go live.'),
                        'options' => array(
                            'production' => 'Production',
                            'test' => 'Test',
                        ),
                        'default' => 'test',
                    ),

                    'tezropay_checkout_flow' => array(
                        'title' => __('Checkout Flow', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('If this is set to <b>Redirect</b>, then the customer will be redirected to <b>TezroPay</b> to checkout, and return to the checkout page once the payment is made.<br>If this is set to <b>Modal</b>, the user will stay on <b>' . get_bloginfo('name', null) . '</b> and complete the transaction.', 'woocommerce'),
                        'options' => array(
                            '1' => 'Modal',
                            '2' => 'Redirect',
                        ),
                        'default' => '2',
                    ),
                    'tezropay_checkout_slug' => array(
                        'title' => __('Checkout Page', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('By default, this will be "checkout".  If you have a different Checkout page, enter the <b>page slug</b>. <br>ie. ' . get_home_url() . '/<b>checkout</b><br><br>View your pages <a target = "_blank" href  = "/wp-admin/edit.php?post_type=page">here</a>, your current checkout page should have <b>Checkout Page</b> next to the title.<br><br>Click the "quick edit" and copy and paste a custom slug here if needed.', 'woocommerce'),

                        'default' => 'checkout',
                    ),
                    'tezropay_custom_redirect' => array(
                        'title' => __('Custom Redirect Page', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('Set the full url  (ie. <i>https://yoursite.com/custompage</i>) if you would like the customer to be redirected to a custom page after completing theh purchase.  <b>Note: this will only work if the REDIRECT mode is used</b> ', 'woocommerce'),
                    ),

                    'tezropay_checkout_mini' => array(
                        'title' => __('Show in mini cart ', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('Set to YES if you would like to show TezroPay as an immediate checkout option in the mini cart', 'woocommerce'),
                        'options' => array(
                            '1' => 'Yes',
                            '2' => 'No',
                            '3' => 'Yes - ( BUY NOW - GET LOAN )',
                        ),
                        'default' => '3',
                    ),
          

                    'tezropay_checkout_capture_email' => array(
                        'title' => __('Auto-Capture Email', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('Should TezroPay try to auto-add the client\'s email address?  If <b>Yes</b>, the client will not be able to change the email address on the TezroPay invoice.  If <b>No</b>, they will be able to add their own email address when paying the invoice.', 'woocommerce'),
                        'options' => array(
                            '1' => 'Yes',
                            '0' => 'No',

                        ),
                        'default' => '1',
                    ),
                    'tezropay_checkout_checkout_message' => array(
                        'title' => __('Checkout Message', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Insert your custom message for the <b>Order Received</b> page, so the customer knows that the order will not be completed until TezroPay releases the funds.', 'woocommerce'),
                        'default' => 'Thank you.  We will notify you when TezroPay has processed your transaction.',
                    ),
                    'tezropay_checkout_error' => array(
                        'title' => __('Error handling', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('If there is an error with creating the invoice, enter the <b>page slug</b>. <br>ie. ' . get_home_url() . '/<b>error</b><br><br>View your pages <a target = "_blank" href  = "/wp-admin/edit.php?post_type=page">here</a>,.<br><br>Click the "quick edit" and copy and paste a custom slug here.', 'woocommerce'),
                       
                    ),
                    'tezropay_checkout_order_process_confirmed_status' => array(
                        'title' => __('TezroPay Confirmed Invoice Status', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('Configure your Account on your <a href = "https://web.tezro.com" target = "_blank">TezroPay Dashboard</a>, and map the TezroPay <b>confirmed</b> invoice status to one of the available WooCommerce order states.<br>All WooCommerce status options are listed here for your convenience.<br><br><em>Note: setting the status to <b>Completed</b> will reduce stock levels included in the order.  <b>TezroPay Complete Invoice Status</b> should <b>NOT</b> be set to <b>Completed</b>, if using <b>TezroPay Confirmed Invoice Status</b> to mark the order as complete.</em><br><br>', 'woocommerce'),
                       'options' =>$wc_statuses_arr,
                        'default' => 'wc-processing',
                    ),
                    'tezropay_checkout_order_process_complete_status' => array(
                        'title' => __('TezroPay Complete Invoice Status', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('Configure your Account on your <a href = "https://web.tezro.com" target = "_blank">TezroPay Dashboard</a>, and map the TezroPay <b>complete</b> invoice status to one of the available WooCommerce order states.<br>All WooCommerce status options are listed here for your convenience.<br><br><em>Note: setting the status to <b>Completed</b> will reduce stock levels included in the order.  <b>TezroPay Confirmed Invoice Status</b> should <b>NOT</b> be set to <b>Completed</b>, if using <b>TezroPay Complete Invoice Status</b> to mark the order as complete.</em>', 'woocommerce'),
                       'options' =>$wc_statuses_arr,
                        'default' => 'wc-processing',
                    ),
                    'tezropay_checkout_order_expired_status' => array(
                        'title' => __('TezroPay Expired Status', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('If set to <b>Yes</b>,  automatically set the order to canceled when the invoice has expired and has been notified by the TezroPay IPN.', 'woocommerce'),
                       
                        'options' => array(
                            '0'=>'No',
                            '1'=>'Yes'
                        ),
                        'default' => '0',
                    ),
                   

                    'tezropay_log_mode' => array(
                        'title' => __('Developer Logging', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('Errors will be logged to the plugin <b>log</b> directory automatically.  Set to <b>Enabled</b> to also log transactions, ie invoices and IPN updates', 'woocommerce'),
                        'options' => array(
                            '0' => 'Disabled',
                            '1' => 'Enabled',
                        ),
                        'default' => '1',
                    ),

                );
            }
            
            function process_payment($order_id)
            {
                #this is the one that is called intially when someone checks out
                global $woocommerce;
                $order = new WC_Order($order_id);
                // Return thankyou redirect
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            }
        } // end \WC_Gateway_Offline class
    } //end check for class existence
    else {
            global $wpdb;
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugins_url = admin_url('plugins.php');
            $plugins = get_plugins();
            foreach ($plugins as $file => $plugin) {

                if ('TezroPay Checkout for WooCommerce' === $plugin['Name'] && true === is_plugin_active($file)) {

                    deactivate_plugins(plugin_basename(__FILE__));
                    wp_die('WooCommerce needs to be installed and activated before TezroPay Checkout for WooCommerce can be activated.<br><a href="' . esc_url($plugins_url) . '">Return to plugins screen</a>');

                }
            }

        }

    }


//update the order_id field in the custom table, try and create the table if this is called before the original
add_action('admin_notices', 'tpcw_update_db_1');
function tpcw_update_db_1()
{
   

    if (isset($_GET['section'])  && $_GET['section'] == 'tezropay_checkout_gateway'  && is_admin()):
        if(get_option('tezropay_wc_checkout_db1') != 1):
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table_name = '_tezropay_checkout_transactions';
       
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` varchar(255) NOT NULL,
            `transaction_id` varchar(255) NOT NULL,
            `transaction_status` varchar(50) NOT NULL DEFAULT 'new',
            `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
            ) $charset_collate;";

        dbDelta($sql);
        $sql = "ALTER TABLE `$table_name` CHANGE `order_id` `order_id` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL; ";
        $wpdb->query($sql);
        update_option('tezropay_wc_checkout_db1',1);
        endif;
      
    endif;
}

//Instant Pay
function tezropay_mini_checkout() {
    ?>
<script type="text/javascript">
    var instantPay = function(e) {
        var json = JSON.parse(e.getAttribute("data-json"));
        tezroApiInstantPay.runExtButtonSpinner(e, json.orderId);
        tezroApiInstantPay.payInit(e, json);
    };
</script>
<?php
}

add_action( 'woocommerce_widget_shopping_cart_buttons', 'tezropay_mini_checkout', 20 );

//redirect to cart if tezropay single page enabled
function tezropay_redirect_to_checkout( $url ) {
    $url = get_permalink( get_option( 'woocommerce_checkout_page_id' ) ); 
    $url.='?payment=tezropay';
    return $url;
 }
 add_filter( 'woocommerce_add_to_cart_redirect', 'tezropay_redirect_to_checkout' );

 function tezropay_default_payment_gateway(){
     if( is_checkout() && ! is_wc_endpoint_url() ) {
        global $woocommerce;
        $default_payment_id = 'tezropay_checkout_gateway';
        if(isset($_GET['payment']) && $_GET['payment'] == 'tezropay'):
            WC()->session->set( 'chosen_payment_method', $default_payment_id );
        endif;
     }
 }
 function enable_tezropayquickpay_js(){
    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    if($tezropay_checkout_options['tezropay_checkout_endpoint'] == "test"){
        wp_enqueue_script('tezropayquickpay_js', esc_url( plugins_url( '/js/tezropayquickpay_js_dev.js', __FILE__ )));
    }else{
        wp_enqueue_script('tezropayquickpay_js', esc_url( plugins_url( '/js/tezropayquickpay_js.js', __FILE__ )));
    }
    wp_enqueue_script('qrcode', esc_url( plugins_url( '/js/qrcode.min.js', __FILE__ )));
    wp_enqueue_style('tezropay', esc_url( plugins_url( '/tezropay.css', __FILE__ )));
}

add_action( 'woocommerce_after_shop_loop_item', 'tezropay_add_loop_custom_button', 1000 );
add_action( 'woocommerce_single_product_summary', 'tezropay_add_loop_custom_button', 30 );

function tezropay_add_loop_custom_button() {
    global $product;
    $params = new stdClass();

    $id = $product->get_id();
    $name = $product->get_title();
    $image_id = $product->get_image_id();
    $image_url = wp_get_attachment_image_url( $image_id, 'full' );

    $price = $product->get_price();
    $confirmUrlAddress = get_home_url() . '/wp-json/tezropay/instantpay/status';
    $confirmUrl = get_home_url() . '/wp-json/tezropay/ipn/status';
    $confirmAmountUrl = get_home_url() . '/wp-json/tezropay/ipn/statusamount';
    $redirectUrl = $tezropay_checkout_options['tezropay_custom_redirect'];
    $initTezro = get_home_url() . '/wp-json/tezropay/instantpay/init?productid='.$id;
    $currency = get_woocommerce_currency(); // TODO

    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    $tezropay_checkout_keyid = TEZRO_getTezroPayKeyID($tezropay_checkout_options['tezropay_checkout_endpoint']);
    $tezropay_checkout_secret = TEZRO_getTezroPaySecret($tezropay_checkout_options['tezropay_checkout_endpoint']);

    $params->keyId = $tezropay_checkout_keyid;
    $params->initTezro = $initTezro;
    $params->orderId = $id;
    $params->name = $name;
    $params->amount = strval($price);
    $params->currency = $currency;
    $params->confirmStatusUrl = $confirmUrlAddress;
    $params->confirmAmountUrl = $confirmAmountUrl;
    $params->redirectUrl = $redirectUrl;
    $params->confirmAmountUrlAddress = $confirmUrlAddress;
    $params->photos = [$image_url];
    $params->quantity = 1;
    $json = json_encode($params, true);

    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    $tezropay_checkout_mini = $tezropay_checkout_options['tezropay_checkout_mini'];
    if($tezropay_checkout_mini == 1){
        // Instant Pay Button
        echo '<div class="button thickbox tezroInstantPay" data-product_id="'. esc_attr($id) .'" data-json="'. htmlentities($json, ENT_QUOTES, 'UTF-8') .'" href="#" id="tezroInstantPay'. esc_attr($id) .'" onclick="instantPay(this);return false;" onmouseover="instantPay(this);return false;"><img src="'. esc_url( plugins_url( '/images/qrcode.jpeg', __FILE__ )).'" alt="QR">'. __( "Instant Pay" ) .'</div>';
    }else if($tezropay_checkout_mini == 3){
        // Instant Pay Button - BUY NOW - GET LOAN
        echo '<button class="thickbox tezroBuyNowButton" data-product_id="'. esc_attr($id) .'" data-json="'. htmlentities($json, ENT_QUOTES, 'UTF-8') .'" href="#" id="tezroInstantPay'. esc_attr($id) .'" onclick="buyNow(this);return false;" onclick="buyNow(this);return false;"><div class="tezroBuyNowQR"><img src="'. esc_url( plugins_url( '/images/qrcode.jpeg', __FILE__ )).'" alt="QR"></div><div class="tezroBuyNowText">'. __( "BUY NOW - GET LOAN" ) .'</div><div class="tezroBuyNowPay"><img src="'. esc_url( plugins_url( '/images/pay.tezro.png', __FILE__ )).'" alt="TEZRO PAY"></div></button>';
    }
}
// Custom Currency
  add_filter( 'woocommerce_currencies', 'tezropay_add_my_currency' );

  function tezropay_add_my_currency( $currencies ) {
      $currencies['USDT'] = __( 'USDT', 'woocommerce' );
      $currencies['ETH'] = __( 'ETH', 'woocommerce' );
      $currencies['EURT'] = __( 'EURT', 'woocommerce' );
      $currencies['CNHT'] = __( 'CNHT', 'woocommerce' );
      $currencies['XAUT'] = __( 'XAUT', 'woocommerce' );
      $currencies['BTC'] = __( 'BTC', 'woocommerce' );
      return $currencies;
  }

  add_filter('woocommerce_currency_symbol', 'tezropay_add_my_currency_symbol', 10, 2);

  function tezropay_add_my_currency_symbol( $currency_symbol, $currency ) {
      switch( $currency ) {
          case 'USDT': $currency_symbol = '$'; break;
          case 'USDT': $currency_symbol = '$'; break;
          case 'ETH': $currency_symbol = 'E'; break;
          case 'EURT': $currency_symbol = 'T'; break;
          case 'CNHT': $currency_symbol = 'T'; break;
          case 'XAUT': $currency_symbol = 'T'; break;
          case 'BTC': $currency_symbol = '฿'; break;
      }
      return $currency_symbol;
  }
function tezropay_EDID($stringToHandle = "",$encryptDecrypt = 'e',$secret_key){
    global $wp;
     $output = null;
     $key = hash('sha256',$secret_key);
     $iv = substr(hash('sha256',"TEZROPAY"),0,16);
     if($encryptDecrypt == 'e'){
        $url = (string)time(); // For multi site (string)get_site_url().(string)$stringToHandle.
        $output = base64_encode(openssl_encrypt($url,"AES-256-CBC",$key,0,$iv));
     }else if($encryptDecrypt == 'd'){
        $output = openssl_decrypt(base64_decode((string)$stringToHandle),"AES-256-CBC",$key,0,$iv);
     }
     return $output;
}
function tezropay_instant_pay_init(WP_REST_Request $request){
    global $wp;
    global $woocommerce;
    
    WC()->frontend_includes();
    WC()->cart = new WC_Cart();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();

    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    $tezropay_checkout_keyid = TEZRO_getTezroPayKeyID($tezropay_checkout_options['tezropay_checkout_endpoint']);
    $tezropay_checkout_secret = TEZRO_getTezroPaySecret($tezropay_checkout_options['tezropay_checkout_endpoint']);
    
    $data = $request->get_body();
    $data = json_decode($data);

    $order = wc_create_order();
    $order_id = $order->get_id();
    // If you need order currency same as payment uncommment this lines
    // Example for woo currency plugin
    //$order->set_currency($data->currency);
    //$rate_val = get_option('woo_multi_currency_params');
    //$order->set_total($data->amount * $rate_val);
    //$data->KeyId = $tezropay_checkout_keyid;
    $encryptedOrderId = tezropay_EDID((string)$order_id,'e',$tezropay_checkout_secret);
    $data->orderId = $encryptedOrderId;


    $config = new TEZRO_Configuration($tezropay_checkout_options['tezropay_checkout_endpoint']);
    #create a hash for the ipn
    $hash_key = $config->TEZRO_generateHash($tezropay_checkout_secret);
    $item = new TEZRO_Item($config, $data);
    $invoice = new TEZRO_Invoice($item);
    //this creates the invoice with all of the config params from the item
    $invoice->TEZRO_createInvoice($tezropay_checkout_keyid);
    $invoiceData = json_decode($invoice->TEZRO_getInvoiceData());
    
    TEZRO_Logger(get_site_url(), 'NEW TEZROPAY INVOICE', true);
    TEZRO_Logger($invoiceData, 'NEW TEZROPAY INVOICE', true);
    //now we have to append the invoice transaction id for the callback verification
  
    $invoiceID = $invoiceData->id;
    //set a cookie for redirects and updating the order status
    $cookie_name = "tezropay-invoice-id";
    $cookie_value = $invoiceID;
    setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");

    $cookie_name2 = "tezropay-invoice-link";
    $cookie_value2 = $invoiceData->link;
    setcookie($cookie_name2, $cookie_value2, time() + (86400 * 30), "/");

    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    $use_modal = intval($tezropay_checkout_options['tezropay_checkout_flow']);

    $address = array(
        'first_name' => (string)$invoiceData->address->fullName,
        'last_name'  => (string)$invoiceData->address->fullName,
        'company'    => '',
        'email'      => (string)$invoiceData->address->email,
        'phone'      => (string)$invoiceData->address->phone,
        'address_1'  => (string)$invoiceData->address->address,
        'address_2'  => '', 
        'city'       => (string)$invoiceData->address->city,
        'state'      => (string)$invoiceData->address->region,
        'postcode'   => (string)$invoiceData->address->postal,
        'country'    => (string)$invoiceData->address->country
    );
    $product_id = $request->get_param('productid');
    $product = wc_get_product($product_id);
    $order->add_product( $product, 1 );
    $order->set_address( $address, 'billing' );
    $order->set_address( $address, 'shipping' );
    //$order->add_coupon('Tezro','10','2'); // accepted param $couponcode, $couponamount,$coupon_tax
    $order->calculate_totals();

    #insert into the database
    tezropay_checkout_insert_order_note($order_id, $invoiceID);

    $invoiceJSON = json_encode($invoiceData);
    wp_send_json($invoiceData);
}

function tezropay_instant_pay(WP_REST_Request $request){
    global $woocommerce;
    
    WC()->frontend_includes();
    WC()->cart = new WC_Cart();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();

    $data = $request->get_body();
    $data = json_decode($data);
    $order_id = tezropay_checkout_get_order_id_tezropay_invoice_id($data->id);
    $order = wc_get_order($order_id);
    TEZRO_Logger($data, 'INCOMING INSTANT PAY STATUS IPN', true);
    TEZRO_Logger("Order id = ".$order_id.", TezroPay invoice id = ".$data->id.". Current status = ".$data->status." ,payment method = " . $order->get_payment_method(), 'Ignore IPN', true);

    if($data->status == 'address_confirmed' || $data->status == 'order_confirmed' || $data->status == 'order_payed'){
        $address = array(
            'first_name' => $data->address->fullName,
            'last_name'  => $data->address->fullName,
            'company'    => '',
            'email'      => $data->address->email,
            'phone'      => $data->address->phone,
            'address_1'  => $data->address->address,
            'address_2'  => '', 
            'city'       => $data->address->city,
            'state'      => $data->address->region,
            'postcode'   => $data->address->postal,
            'country'    => $data->address->country
        );
        $order->set_address( $address, 'billing' );
        $order->set_address( $address, 'shipping' );
        //$order->add_coupon('Fresher','10','2'); // accepted param $couponcode, $couponamount,$coupon_tax
        $order->calculate_totals();
    }
    if($data->status == 'address_confirmed'){
        $order->update_status('processing', 'Tezro Instant Pay', TRUE);
    }
    if($data->status == 'order_confirmed' || $data->status == 'order_payed'){
        $order->update_status("completed", 'Tezro Instant Pay', TRUE);
    }

    //$shippingMethod = new \WC_Shipping_Rate($selected_shipping_method->id,$selected_shipping_method->title, (float)$class_cost);
    //$sub->add_shipping($shippingMethod);
}
// ###
 add_action( 'template_redirect', 'tezropay_default_payment_gateway' );
add_action('rest_api_init', function () {
    register_rest_route('tezropay/instantpay', '/init', array(
        'methods' => 'POST,GET',
        'callback' => 'tezropay_instant_pay_init',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('tezropay/instantpay', '/status', array(
        'methods' => 'POST,GET',
        'callback' => 'tezropay_instant_pay',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('tezropay/ipn', '/status', array(
        'methods' => 'POST,GET',
        'callback' => 'tezropay_checkout_ipn',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('tezropay/ipn', '/statusamount', array(
        'methods' => 'POST,GET',
        'callback' => 'tezropay_checkoutamount_ipn',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('tezropay/cartfix', '/restore', array(
        'methods' => 'POST,GET',
        'callback' => 'tezropay_checkout_cart_restore',
        'permission_callback' => '__return_true',
    ));
});

function tezropay_checkout_cart_restore(WP_REST_Request $request)
{
    // Load cart functions which are loaded only on the front-end.
    include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
    include_once WC_ABSPATH . 'includes/class-wc-cart.php';

    if ( is_null( WC()->cart ) ) {
        wc_load_cart();
    }
    $data = $request->get_params();
    $order_id = $data['orderid'];
    $order = new WC_Order($order_id);
    $items = $order->get_items();

    TEZRO_Logger('User canceled order: ' . $order_id . ', removing from WooCommerce', 'USER CANCELED ORDER', true);
    $order->add_order_note('User closed the modal, the order will be set to canceled state');
    $order->update_status('canceled', __('TezroPay payment canceled by user', 'woocommerce'));

    //clear the cart first so things dont double up
    WC()->cart->empty_cart();
    foreach ($items as $item) {
        //now insert for each quantity
        $item_count = $item->get_quantity();
        for ($i = 0; $i < $item_count; $i++):
            WC()->cart->add_to_cart($item->get_product_id());
        endfor;
    }
}
function tezropay_checkoutamount_ipn(WP_REST_Request $request)
{
    global $woocommerce;
    
    WC()->frontend_includes();
    WC()->cart = new WC_Cart();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    $data = $request->get_body();

    $data = json_decode($data);
    
    $orderid = tezropay_checkout_get_order_id_tezropay_invoice_id($data->id);
    $invoiceID = $data->id;
    $order_status = $data->status;

    TEZRO_Logger($data, 'INCOMING AMOUNT IPN', true);

    $order = new WC_Order($orderid);
    if ($order->get_payment_method() != 'tezropay_checkout_gateway'){
        #ignore the IPN when the order payment method is (no longer) tezropay
        TEZRO_Logger("Order id = ".esc_attr($orderid).", TezroPay invoice id = ".esc_attr($invoiceID).". Current status = ".esc_attr($order_status)." ,payment method = " . $order->get_payment_method(), 'Ignore IPN', true);
        die();
    }
    
    #verify the ipn matches the status of the actual invoice
    if (tezropay_checkout_get_order_transaction($orderid, $invoiceID) == 1){
        $invoiceData = array(
            'orderId'      => (string)$data->orderId,
            'amountTotal'  => (string)$order->calculate_totals()
        );
        wp_send_json($invoiceData);
    }
}

//status
function tezropay_checkout_ipn(WP_REST_Request $request)
{
    global $woocommerce;
    
    WC()->frontend_includes();
    WC()->cart = new WC_Cart();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    $data = $request->get_body();

    $data = json_decode($data);
    
    $orderid = tezropay_checkout_get_order_id_tezropay_invoice_id($data->id);
    $invoiceID = $data->id;
    $order_status = $data->status;

    TEZRO_Logger($data, 'INCOMING STATUS IPN', true);

    $order = new WC_Order($orderid);
    if ($order->get_payment_method() != 'tezropay_checkout_gateway'){
        #ignore the IPN when the order payment method is (no longer) tezropay
        TEZRO_Logger("Order id = ".esc_attr($orderid).", TezroPay invoice id = ".esc_attr($invoiceID).". Current status = ".esc_attr($order_status)." ,payment method = " . $order->get_payment_method(), 'Ignore IPN', true);
        die();
    }
    
    #verify the ipn matches the status of the actual invoice
    if (tezropay_checkout_get_order_transaction($orderid, $invoiceID) == 1):
      
        $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
        //dev or prod token
        $tezropay_checkout_order_process_confirmed_status = $tezropay_checkout_options['tezropay_checkout_order_process_confirmed_status'];
        $tezropay_checkout_order_process_complete_status = $tezropay_checkout_options['tezropay_checkout_order_process_complete_status'];
        $tezropay_checkout_order_expired_status = $tezropay_checkout_options['tezropay_checkout_order_expired_status'];


        $config = new TEZRO_Configuration($tezropay_checkout_options['tezropay_checkout_endpoint']);
        $tezropay_checkout_endpoint = $tezropay_checkout_options['tezropay_checkout_endpoint'];

        $params = new stdClass();
        $params->extension_version = TEZRO_getTezroPayVersionInfo();
        $params->invoiceID = $invoiceID;

        $item = new TEZRO_Item($config, $params);
        $invoice = new TEZRO_Invoice($item); //this creates the invoice with all of the config params

        $tezropay_checkout_keyid = TEZRO_getTezroPayKeyID($tezropay_checkout_options['tezropay_checkout_endpoint']);
        $tezropay_checkout_secret = TEZRO_getTezroPaySecret($tezropay_checkout_options['tezropay_checkout_endpoint']);
        $hash_key = $config->TEZRO_generateHash($tezropay_checkout_secret);
        $orderStatus = json_decode($invoice->TEZRO_checkInvoiceStatus($invoiceID,$hash_key,$tezropay_checkout_keyid));
        if($orderStatus->status != $order_status){
          die();
        }
        #update the lookup table
        $note_set = null;
             
        tezropay_checkout_update_order_note($orderid, $invoiceID, $order_status);
        $wc_statuses_arr = wc_get_order_statuses();
        $wc_statuses_arr['tezropay-ignore'] = "Do not change status";
        switch ($orderStatus->status) {
            case 'order_confirmed':
                if ( $tezropay_checkout_order_process_confirmed_status != 'tezropay-ignore' ):
                        $lbl = $wc_statuses_arr[ $tezropay_checkout_order_process_confirmed_status ];
                    if ( !isset( $lbl ) ):
                        $lbl = "Processing";
                        $tezropay_checkout_order_process_confirmed_status = 'wc-pending';
                    endif;
                    $order->add_order_note( 'TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink( $tezropay_checkout_endpoint, $invoiceID ) . '">' . esc_attr($invoiceID) . '</a> has changed to ' . $lbl . '.' );
                    $order_status = $tezropay_checkout_order_process_confirmed_status;
                    if ( $order_status == 'wc-completed' ) {
                        $order->payment_complete( );
                        $order->add_order_note( 'Payment Completed' );
                        
                    } else {
                        $order->update_status( $order_status, __( 'TezroPay payment ', 'woocommerce' ) );
                    }
                    WC()->cart->empty_cart();
                   
                else :
                    $order->add_order_note( 'TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink( $tezropay_checkout_endpoint, $invoiceID ) . '">' . esc_attr($invoiceID) . '</a> has changed to Confirmed.  The order status has not been updated due to your settings.' );
                endif;
                break;
            case 'order_payed':
                  if ( $tezropay_checkout_order_process_confirmed_status != 'tezropay-ignore' ):
                        $lbl = $wc_statuses_arr[ $tezropay_checkout_order_process_confirmed_status ];
                    if ( !isset( $lbl ) ):
                        $lbl = "Processing";
                        $tezropay_checkout_order_process_confirmed_status = 'wc-pending';
                    endif;
                    $order->add_order_note( 'TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink( $tezropay_checkout_endpoint, $invoiceID ) . '">' . esc_attr($invoiceID) . '</a> has changed to ' . $lbl . '.' );
                    $order_status = $tezropay_checkout_order_process_confirmed_status;
                    if ( $order_status == 'wc-completed' ) {
                        $order->payment_complete( );
                        $order->add_order_note( 'Payment Completed' );
                        
                    } else {
                        $order->update_status( $order_status, __( 'TezroPay payment ', 'woocommerce' ) );
                    }
                    WC()->cart->empty_cart();
                   
                else :
                    $order->add_order_note( 'TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink( $tezropay_checkout_endpoint, $invoiceID ) . '">' . esc_attr($invoiceID) . '</a> has changed to Confirmed.  The order status has not been updated due to your settings.' );
                endif;
                break;
            case 'order_delivered':
                if ( $tezropay_checkout_order_process_complete_status != 'tezropay-ignore' ):
                    $lbl = $wc_statuses_arr[ $tezropay_checkout_order_process_complete_status ];
                if ( !isset( $lbl ) ):
                    $lbl = "Processing";
                $tezropay_checkout_order_process_complete_status = 'wc-pending';
                endif;
                $order->add_order_note( 'TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink( $tezropay_checkout_endpoint, $invoiceID ) . '">' . esc_attr($invoiceID) . '</a> has changed to ' . $lbl . '.' );
                $order_status = $tezropay_checkout_order_process_complete_status;
                if ( $order_status == 'wc-completed' ) {
                    $order->payment_complete( );
                    $order->add_order_note( 'Payment Completed' );
                    // Reduce stock levels
                    
                } else {
                    $order->update_status( $order_status, __( 'TezroPay payment ', 'woocommerce' ) );
                }
                
                // Remove cart
                WC()->cart->empty_cart();
                wc_reduce_stock_levels( $orderid );
                else :
                    $order->add_order_note( 'TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink( $tezropay_checkout_endpoint, $invoiceID ) . '">' . esc_attr($invoiceID) . '</a> has changed to Completed.  The order status has not been updated due to your settings.' );
                endif;
                break;
            case 'order_error':
                if ($orderStatus->data->status == 'invalid'):
                    $order->add_order_note('TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink($tezropay_checkout_endpoint, $invoiceID) . '">' . esc_attr($invoiceID) . '</a> has become invalid because of network connection.  Order will automatically update when the status changes.');
                    $order->update_status('failed', __('TezroPay payment invalid', 'woocommerce'));
                endif;
            break;

            case 'order_expired':
                if(property_exists($orderStatus->data,'underpaidAmount')):
                    $order->add_order_note('TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink($tezropay_checkout_endpoint, $invoiceID) . '">' . esc_attr($invoiceID) . ' </a> has been refunded.');
                    $order->update_status('refunded', __('TezroPay payment refunded', 'woocommerce'));
                else:
                    $order_status = "wc-cancelled";
                    $order->add_order_note('TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink($tezropay_checkout_endpoint, $invoiceID) . '">' . esc_attr($invoiceID) . '</a> has expired.');
                    if($tezropay_checkout_order_expired_status == 1):
                         $order->update_status($order_status, __('TezroPay payment invalid', 'woocommerce'));
                    endif;
                endif;
            break;

            case 'order_disputed':               
                $order->add_order_note('TezroPay Invoice ID: <a target = "_blank" href = "' . TEZRO_getTezroPayDashboardLink($tezropay_checkout_endpoint, $invoiceID) . '">' . esc_attr($invoiceID) . ' </a> has been refunded.');
                $order->update_status('refunded', __('TezroPay payment refunded', 'woocommerce'));
                break;
            default:
            break;
        }
        die();
    endif;
}

add_action('template_redirect', 'tezropay_custom_redirect_after_purchase');
function tezropay_custom_redirect_after_purchase()
{

    global $wp;
    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    $tezropay_checkout_keyid = TEZRO_getTezroPayKeyID($tezropay_checkout_options['tezropay_checkout_endpoint']);
    $tezropay_checkout_secret = TEZRO_getTezroPaySecret($tezropay_checkout_options['tezropay_checkout_endpoint']);

    if (is_checkout() && !empty($wp->query_vars['order-received'])) {

        $order_id = $wp->query_vars['order-received'];

        try {
            $order = new WC_Order($order_id);
           

            //this means if the user is using tezropay AND this is not the redirect
            $show_tezropay = true;

            if (isset($_GET['redirect']) && $_GET['redirect'] == 'false'):
                $show_tezropay = false;
                $invoiceID = sanitize_title($_COOKIE['tezropay-invoice-id']);

                //clear the cookie
                setcookie("tezropay-invoice-id", "", time() - 3600);
            endif;

            if ($order->get_payment_method() == 'tezropay_checkout_gateway' && $show_tezropay == true):
                $config = new TEZRO_Configuration($tezropay_checkout_options['tezropay_checkout_endpoint']);
                //sample values to create an item, should be passed as an object'
                $params = new stdClass();
                $current_user = wp_get_current_user();
               
                $params->extension_version = TEZRO_getTezroPayVersionInfo();
                $params->price = $order->get_total();
                $params->currency = $order->get_currency(); //set as needed
                if ($tezropay_checkout_options['tezropay_checkout_capture_email'] == 1):
                    $current_user = wp_get_current_user();

                    if ($current_user->user_email):
                        $buyerInfo = new stdClass();
                        $buyerInfo->name = $current_user->display_name;
                        $buyerInfo->email = $current_user->user_email;
                        $params->buyer = $buyerInfo;
                    endif;
                endif;

                //orderid
                $encryptedOrderId = tezropay_EDID($order->get_order_number($order_id),'e',$tezropay_checkout_secret);
                $params->orderId = $encryptedOrderId;
     
               
                //redirect and ipn stuff
                $checkout_slug = $tezropay_checkout_options['tezropay_checkout_slug'];
                if (empty($checkout_slug)):
                    $checkout_slug = 'checkout';
                endif;

                $redirectUrl = $tezropay_checkout_options['tezropay_custom_redirect'];
                $standardRedirectUrl = get_home_url() . '/' . $checkout_slug . '/order-received/' . $order_id . '/?key=' . $order->get_order_key() . '&redirect=false';
                if($redirectUrl == ""){
                    $redirectUrl = $standardRedirectUrl;
                }
                //
                $params->redirectURL = $standardRedirectUrl;
                //else:
                //$params->redirectURL = $tezropay_checkout_options['tezropay_custom_redirect']."?custompage=true";
                //endif;
                #create a hash for the ipn
                $hash_key = $config->TEZRO_generateHash($tezropay_checkout_secret);

                $params->confirmStatusUrl = get_home_url() . '/wp-json/tezropay/instantpay/status';
                #http://<host>/wp-json/tezropay/ipn/status
                $params->extendedNotifications = true;

                //$params->eosName = $tezropay_checkout_account;
                $params->amount = $order->get_total();

                $photosArray = [];
                $nameArray = [];
                $attributesArray = [];

                foreach ( $order->get_items() as $item_id => $item ) {
                    $name = $item->get_name();
                    $product = $item->get_product();
                    $quantity = $item->get_quantity();
                    $image_id = $product->get_image_id();
                    $image_url = wp_get_attachment_image_url( $image_id, 'full' );
                    $type = $item->get_type();
                    foreach ($item->get_meta_data() as $metaData) {
                        $attribute = $metaData->get_data();

                        // attribute value
                        $value = $attribute['value'];

                        // attribute slug
                        $slug = $attribute['key'];

                        //$params->attributes = $attribute;
                        array_push($attributesArray, (object)[
                                'name' => $slug,
                                'value' => $value
                        ]);
                    }
                      array_push($photosArray, $image_url);
                      array_push($nameArray, $name);
                }

                $params->photos = $photosArray;
                $params->name = implode(',',$nameArray);
                $params->quantity = $quantity;
                $params->attributes = $attributesArray;

                $confirmUrlAddress = get_home_url() . '/wp-json/tezropay/instantpay/status';
                $initTezro = get_home_url() . '/wp-json/tezropay/instantpay/init?productid='.$order_id;
                $params->keyId = $tezropay_checkout_keyid;
                $params->initTezro = $initTezro;
                $params->redirectUrl = $redirectUrl;
                $params->confirmAmountUrl = get_home_url() . '/wp-json/tezropay/ipn/statusamount';
                $params->confirmAmountUrlAddress = $confirmUrlAddress;
                $order_data = $order->get_data();
                $fullName = $order->get_formatted_billing_full_name();
                $address = array(
                    'phone'      => (string)$order->get_billing_phone(),
                    'fullName'   => (string)$fullName,
                    'address'    => (string)$order->get_billing_address_1(),
                    'city'       => (string)$order->get_billing_city(),
                    'region'     => (string)$order->get_billing_state(),
                    'postal'     => (string)$order->get_billing_postcode(),
                    'country'    => (string)$order->get_billing_country(),
                    'email'      => (string)$order->get_billing_email(),
                );
                $params->address = $address;

                $item = new TEZRO_Item($config, $params);
                $invoice = new TEZRO_Invoice($item);
                //this creates the invoice with all of the config params from the item
                $invoice->TEZRO_createInvoice($tezropay_checkout_keyid);
                $invoiceData = json_decode($invoice->TEZRO_getInvoiceData());
                TEZRO_Logger($invoiceData, 'NEW TEZROPAY PRE INVOICE',true);
                if (isset($invoiceData->error)):
                    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
                    $errorURL = get_home_url().'/'.$tezropay_checkout_options['tezropay_checkout_error'];
                    $order_status = "wc-cancelled";
                    $order = new WC_Order($order_id);
                    $items = $order->get_items();
                    $order->update_status($order_status, __($invoiceData->error.'.', 'woocommerce'));

                     //clear the cart first so things dont double up
                    WC()->cart->empty_cart();
                    foreach ($items as $item) {
                        //now insert for each quantity
                        $item_count = $item->get_quantity();
                        for ($i = 0; $i < $item_count; $i++):
                            WC()->cart->add_to_cart($item->get_product_id());
                        endfor;
                    }
                    wp_redirect($errorURL);
                    die();
                endif; 
                TEZRO_Logger($item, 'NEW TEZROPAY INVOICE ITEM', true);
                TEZRO_Logger($invoice, 'NEW TEZROPAY INVOICE', true);
                TEZRO_Logger($invoiceData, 'NEW TEZROPAY INVOICE DATA', true);
                //now we have to append the invoice transaction id for the callback verification
                
              
                $invoiceID = $invoiceData->id;
                //set a cookie for redirects and updating the order status
                $cookie_name = "tezropay-invoice-id";
                $cookie_value = $invoiceID;
                setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");

                $cookie_name2 = "tezropay-invoice-link";
                $cookie_value2 = $invoiceData->link;
                setcookie($cookie_name2, $cookie_value2, time() + (86400 * 30), "/");
                $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
                $use_modal = intval($tezropay_checkout_options['tezropay_checkout_flow']);

                #insert into the database
                tezropay_checkout_insert_order_note($order_id, $invoiceID);
                

                //use the modal if '1', otherwise redirect
                if ($use_modal == 2):
                    wp_redirect($invoice->TEZRO_getInvoiceURL());
                else:
                    wp_redirect($params->redirectURL);

                endif;

                exit;
            endif;
        } catch (Exception $e) {
            global $woocommerce;
            $cart_url = $woocommerce->cart->get_cart_url();
            wp_redirect($cart_url);
            exit;
        }
    }
}
// Replacing the Place order 
add_filter('woocommerce_order_button_html', 'tezropay_checkout_replace_order_button_html', 10, 2);
function tezropay_checkout_replace_order_button_html($order_button, $override = false)
{
    if ($override):
        return;
    else:
        return $order_button;
    endif;
}

function TEZRO_getTezroPayVersionInfo($clean = null)
{
    $plugin_data = get_file_data(__FILE__, array('Version' => 'Version', 'Plugin_Name' => 'Plugin Name'), false);
    $plugin_name = $plugin_data['Plugin_Name'];
    if ($clean):
        $plugin_version = $plugin_name . ' ' . $plugin_data['Version'];
    else:
        $plugin_name = str_replace(" ", "_", $plugin_name);
        $plugin_name = str_replace("_for_", "_", $plugin_name);
        $plugin_version = $plugin_name . '_' . $plugin_data['Version'];
    endif;
   
    return $plugin_version;
}

#retrieves the invoice token based on the endpoint
function TEZRO_getTezroPayDashboardLink($endpoint, $invoiceID)
{ //dev or prod token
    switch ($endpoint) {
        case 'test':
        default:
            return '//test.web.tezro.com/';
            break;
        case 'production':
            return '//web.tezro.com/';
            break;
    }
}

#retrieves the invoice token based on the endpoint
function TEZRO_getProcessingLink()
{ //dev or prod token
    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    $tezropay_checkout_endpoint = $tezropay_checkout_options['tezropay_checkout_endpoint'];
    switch ($tezropay_checkout_endpoint) {
        case 'test':
        default:
            return 'https://test.openapi.tezro.com/api/v1/orders';
            break;
        case 'production':
        return 'https://openapi.tezro.com/api/v1/orders';
            break;
    }
}

function TEZRO_getTezroPayKeyID($endpoint)
{
    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    //dev or prod token
    switch ($tezropay_checkout_options['tezropay_checkout_endpoint']) {
        case 'test':
        default:
            return $tezropay_checkout_options['tezropay_checkout_token_dev_1'];
            break;
        case 'production':
            return $tezropay_checkout_options['tezropay_checkout_token_prod_1'];
            break;
    }

}
function TEZRO_getTezroPaySecret($endpoint)
{
    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    //dev or prod token
    switch ($tezropay_checkout_options['tezropay_checkout_endpoint']) {
        case 'test':
        default:
            return $tezropay_checkout_options['tezropay_checkout_token_dev_2'];
            break;
        case 'production':
            return $tezropay_checkout_options['tezropay_checkout_token_prod_2'];
            break;
    }

}

//hook into the order recieved page and re-add to cart of modal canceled
add_action('woocommerce_thankyou', 'tezropay_checkout_thankyou_page', 10, 1);
function tezropay_checkout_thankyou_page($order_id)
{
    global $woocommerce;
    $order = new WC_Order($order_id);


    //$name = $product->get_title();
    //$image_id = $product->get_image_id();
    //$image_url = wp_get_attachment_image_url( $image_id, 'full' );

    $tpcw_price = $order->get_total();

    foreach ( $order->get_items() as $item_id => $item ) {
        $tpcw_name = $item->get_name();
        $tpcw_product = $item->get_product();
        $tpcw_quantity = $item->get_quantity();
        $tpcw_image_id = $tpcw_product->get_image_id();
        $tpcw_image_url = wp_get_attachment_image_url($tpcw_image_id , 'full' );
        $tpcw_type = $item->get_type();
    }


    $tpcw_confirmUrlAddress = get_home_url() . '/wp-json/tezropay/instantpay/status';
    $tpcw_confirmUrl = get_home_url() . '/wp-json/tezropay/ipn/status';
    $tpcw_confirmAmountUrl = get_home_url() . '/wp-json/tezropay/ipn/statusamount';
    $tpcw_redirectUrl = $tezropay_checkout_options['tezropay_custom_redirect'];
    $tpcw_currency = get_woocommerce_currency();

    $tpcw_checkout_keyid = TEZRO_getTezroPayKeyID($tezropay_checkout_options['tezropay_checkout_endpoint']);
    $tpcw_checkout_secret = TEZRO_getTezroPaySecret($tezropay_checkout_options['tezropay_checkout_endpoint']);

    $tpcw_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    $use_modal = intval($tpcw_checkout_options['tezropay_checkout_flow']);
    $tezropay_checkout_test_mode = $tpcw_checkout_options['tezropay_checkout_endpoint'];
    $restore_url = get_home_url() . '/wp-json/tezropay/cartfix/restore';
    $cart_url = wc_get_cart_url() . '/cart';
    $test_mode = false;
    $js_script = plugin_dir_path(__FILE__) . "js/tezropaywidget.js";
    if ($tezropay_checkout_test_mode == 'test'):
        $test_mode = true;
        $js_script = plugin_dir_path(__FILE__) .  "js/tezropaywidget-dev.js";
    endif;

    #use the modal
    if ($order->get_payment_method() == 'tezropay_checkout_gateway' && $use_modal == 1):
        $tpcw_invoiceID = sanitize_title($_COOKIE['tezropay-invoice-id']);
        $tpcw_invoiceLink = sanitize_url($_COOKIE['tezropay-invoice-link']);
        ?>
        <div id="tezroInstantPay<?php echo esc_attr($tpcw_invoiceID); ?>"></div>
        <script type='text/javascript'>
            //show the modal
            var tezroFrame = document.getElementById("tezroInstantPay<?php echo esc_attr($tpcw_invoiceID); ?>");
            tezroFrame.addEventListener('click', function (e) {
                var json = {
                    id: '<?php echo esc_attr($tpcw_invoiceID); ?>',
                    keyId: '<?php echo esc_attr($tpcw_checkout_keyid); ?>',
                    orderId: '<?php echo esc_attr($tpcw_invoiceID); ?>',
                    link: '<?php echo esc_url($tpcw_invoiceLink); ?>',
                    amount: '<?php echo strval(esc_attr($tpcw_price)); ?>',
                    currency: '<?php echo esc_attr($tpcw_currency); ?>',
                    confirmAmountUrl: '<?php echo esc_url($tpcw_confirmAmoutUrl); ?>',
                    confirmStatusUrl: '<?php echo esc_url($tpcw_confirmUrlAddress); ?>',
                    redirectUrl: '<?php echo esc_url($tpcw_redirectUrl); ?>',
                    photos: '<?php echo [esc_url($tpcw_image_url)]; ?>',
                    name: '<?php echo esc_attr($tpcw_name); ?>',
                    attributes: '<?php echo esc_attr($tpcw_type); ?>',
                    quantity: '<?php echo esc_attr($tpcw_quantity); ?>'
                }
                tezroApiInstantPay.runExtButtonSpinner(e, json.orderId);
                tezroApiInstantPay.checkoutInit(e, json, json);
            });
            window.addEventListener('load', (event) => {
                tezroFrame.click();
            });
            function finishTezroOrder(){
            }
        </script>
    <?php
    endif;
}

#custom info for TezroPay
add_action('woocommerce_thankyou', 'tezropay_checkout_custom_message');
function tezropay_checkout_custom_message($order_id)
{
    $order = new WC_Order($order_id);
    if ($order->get_payment_method() == 'tezropay_checkout_gateway'):
        $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
        $checkout_message = $tezropay_checkout_options['tezropay_checkout_checkout_message'];
        if ($checkout_message != ''):
            echo '<hr><b>' . esc_attr($checkout_message) . '</b><br><br><hr>';
        endif;
    endif;
}

#tezropay image on payment page
function TEZRO_getTezroPaymentIcon()
{

    $brand = esc_url( plugins_url( 'images/tezropay-currency-group.svg', __FILE__ ) );
    $icon = $brand . '" class="tezropay_logo"';
    return $icon;

    $tezropay_checkout_options = get_option('woocommerce_tezropay_checkout_gateway_settings');
    $tezropay_checkout_show_logo = $tezropay_checkout_options['tezropay_checkout_show_logo'];
    $icon = null;
    if($tezropay_checkout_show_logo  != 2):

    $brand = 'https://tezro.com/assets/images/header-logo.svg';
    $icon = $brand . '" class="tezropay_logo"';
    endif;
    return $icon;
   
}

#add the gatway to woocommerce
add_filter('woocommerce_payment_gateways', 'wc_tezropay_checkout_add_to_gateways');
function wc_tezropay_checkout_add_to_gateways($gateways)
{
    $gateways[] = 'WC_Gateway_TezroPay';
    return $gateways;
}
