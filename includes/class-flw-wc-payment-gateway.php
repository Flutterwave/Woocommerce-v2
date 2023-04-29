<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://flutterwave.com
 * @since      2.3.2
 *
 * @package    Flutterwave\WooCommerce
 * @subpackage FLW_WC_Payment_Gateway/includes
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FLUTTERWAVEACCESS' ) ) {
	exit;
}

require_once __DIR__ . '/class-flw-wc-payment-gateway-event-handler.php';
require_once __DIR__ . '/client/class-flw-wc-payment-gateway-request.php';
require_once __DIR__ . '/client/class-flw-wc-payment-gateway-sdk.php';

use Flutterwave\WooCommerce\Client\Flw_WC_Payment_Gateway_Request;
use Flutterwave\WooCommerce\Client\FLW_WC_Payment_Gateway_Sdk as FlwSdk;
use FLW_WC_Payment_Gateway_Event_Handler as FlwEventHandler;

/**
 * Main Flutterwave Gateway Class
 */
class FLW_WC_Payment_Gateway extends WC_Payment_Gateway {

	/**
	 * Disable logging.
	 *
	 * @var bool the should logging be disabled.
	 */
	public static bool $log_enabled = false;
	/**
	 * Public Key
	 *
	 * @var string the public key
	 */
	protected string $public_key;
	/**
	 * Secret Key
	 *
	 * @var string the secret key
	 */
	protected string $secret_key;
	/**
	 * Test Public Key
	 *
	 * @var string the test public key
	 */
	private string $test_public_key;
	/**
	 * Test Secret Key
	 *
	 * @var string the test secret key
	 */
	private string $test_secret_key;
	/**
	 * Live Public Key
	 *
	 * @var string the live public key
	 */
	private string $live_public_key;
	/**
	 * Go Live Status
	 *
	 * @var string the go live status
	 */
	private string $go_live;
	/**
	 * Live Secret Key
	 *
	 * @var string the live secret key
	 */
	private string $live_secret_key;
	/**
	 * Auto Complete Order
	 *
	 * @var false|mixed|null
	 */
	private $auto_complete_order;
	/**
	 * Logger
	 *
	 * @var WC_Logger the logger
	 */
	private WC_Logger $logger;
	/**
	 * Flutterwave Sdk
	 *
	 * @var FlwSdk the sdk
	 */
	private FlwSdk $sdk;
	/**
	 * Base Url
	 *
	 * @var string the base url
	 */
	private string $base_url;
	/**
	 * Payment Options
	 *
	 * @var string the payment options
	 */
	private string $payment_options;
	/**
	 * Payment Style
	 *
	 * @var string the payment style
	 */
	private string $payment_style;
	/**
	 * Barter
	 *
	 * @var string should barter be disabled
	 */
	private string $barter;
	/**
	 * Logging Option
	 *
	 * @var bool the logging option
	 */
	private bool $logging_option;
	/**
	 * Country
	 *
	 * @var string the country
	 */
	private string $country;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->base_url           = 'https://api.flutterwave.com';
		$this->id                 = 'rave';
		$this->icon               = plugins_url( 'assets/img/rave.png', FLW_WC_PLUGIN_FILE );
		$this->has_fields         = false;
		$this->method_title       = 'Flutterwave';
		$this->method_description = 'Flutterwave ' . __( 'allows you to accept payment from cards and bank accounts in multiple currencies. You can also accept payment offline via USSD and POS.', 'woocommerce-rave' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title               = $this->get_option( 'title' );
		$this->description         = $this->get_option( 'description' );
		$this->enabled             = $this->get_option( 'enabled' );
		$this->test_public_key     = $this->get_option( 'test_public_key' );
		$this->test_secret_key     = $this->get_option( 'test_secret_key' );
		$this->live_public_key     = $this->get_option( 'live_public_key' );
		$this->live_secret_key     = $this->get_option( 'live_secret_key' );
		$this->auto_complete_order = get_option( 'autocomplete_order' );
		$this->go_live             = $this->get_option( 'go_live' );
		$this->payment_options     = $this->get_option( 'payment_options' );
		$this->payment_style       = $this->get_option( 'payment_style' );
		$this->barter              = $this->get_option( 'barter' );
		$this->logging_option      = 'yes' === $this->get_option( 'logging_option', 'no' );
		$this->country             = '';
		self::$log_enabled         = $this->logging_option;
		$this->supports            = array(
			'products',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
			'gateway_scheduled_payments',
		);

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_flw_wc_payment_gateway', array( $this, 'flw_verify_payment' ) );

		// Webhook listener/API hook.
		add_action( 'woocommerce_api_flw_wc_payment_webhook', array( $this, 'flutterwave_webhooks' ) );

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		$this->public_key = $this->test_public_key;
		$this->secret_key = $this->test_secret_key;

		if ( 'yes' === $this->go_live ) {
			$this->public_key = $this->live_public_key;
			$this->secret_key = $this->live_secret_key;
		}

		$this->sdk = new FlwSdk( $this->secret_key, self::$log_enabled );

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

	}

	/**
	 * Get Secret Key
	 *
	 * @return string
	 */
	public function get_secret_key(): string {
		return $this->secret_key;
	}

	/**
	 * Initial gateway settings form fields
	 *
	 * @return void
	 */
	public function init_form_fields() {

		$this->form_fields = array(

			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-rave' ),
				'label'       => __( 'Enable Flutterwave', 'woocommerce-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Flutterwave as a payment option on the checkout page', 'woocommerce-rave' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'webhook'            => array(
				'title'       => __( 'Webhook Instruction', 'woocommerce-rave' ),
				'type'        => 'hidden',
				'description' => __( 'Please copy this webhook URL and paste on the webhook section on your dashboard <strong style="color: red"><pre><code>' . WC()->api_request_url( 'Flw_WC_Payment_Webhook' ) . '</code></pre></strong> (<a href="https://rave.flutterwave.com/dashboard/settings/webhooks" target="_blank">Flutterwave Account</a>)', 'woocommerce-rave' ), //phpcs:ignore
			),
			'secret_hash'        => array(
				'title'       => __( 'Enter Secret Hash', 'woocommerce-rave' ),
				'type'        => 'text',
				'description' => __( 'Please change from default hash and ensure that <b>SECRET HASH</b> is the same with the one on your Flutterwave dashboard', 'woocommerce-rave' ),
				'default'     => hash( 'sha256', 'Rave-Secret-Hash' ),
			),
			'title'              => array(
				'title'       => __( 'Payment method title', 'woocommerce-rave' ),
				'type'        => 'text',
				'description' => __( 'Optional', 'woocommerce-rave' ),
				'default'     => 'Flutterwave',
			),
			'description'        => array(
				'title'       => __( 'Payment method description', 'woocommerce-rave' ),
				'type'        => 'text',
				'description' => __( 'Optional', 'woocommerce-rave' ),
				'default'     => 'Powered by Flutterwave: Accepts Mastercard, Visa, Verve, Discover, AMEX, Diners Club and Union Pay.',
			),
			'test_public_key'    => array(
				'title'       => __( 'Test Public Key', 'woocommerce-rave' ),
				'type'        => 'text',
				'description' => __( 'Required! Enter your Flutterwave test public key here', 'woocommerce-rave' ),
				'default'     => '',
			),
			'test_secret_key'    => array(
				'title'       => __( 'Test Secret Key', 'woocommerce-rave' ),
				'type'        => 'password',
				'description' => __( 'Required! Enter your Flutterwave test secret key here', 'woocommerce-rave' ),
				'default'     => '',
			),
			'live_public_key'    => array(
				'title'       => __( 'Live Public Key', 'woocommerce-rave' ),
				'type'        => 'text',
				'description' => __( 'Required! Enter your Flutterwave live public key here', 'woocommerce-rave' ),
				'default'     => '',
			),
			'live_secret_key'    => array(
				'title'       => __( 'Live Secret Key', 'woocommerce-rave' ),
				'type'        => 'password',
				'description' => __( 'Required! Enter your Flutterwave live secret key here', 'woocommerce-rave' ),
				'default'     => '',
			),
			'payment_style'      => array(
				'title'       => __( 'Payment Style on checkout', 'woocommerce-rave' ),
				'type'        => 'select',
				'description' => __( 'Optional - Choice of payment style to use. Either inline or redirect. (Default: inline)', 'woocommerce-rave' ),
				'options'     => array(
					'inline'   => esc_html_x( 'Popup(Keep payment experience on the website)', 'payment_style', 'woocommerce-rave' ),
					'redirect' => esc_html_x( 'Redirect', 'payment_style', 'woocommerce-rave' ),
				),
				'default'     => 'inline',
			),
			'autocomplete_order' => array(
				'title'       => __( 'Autocomplete Order After Payment', 'woocommerce-rave' ),
				'label'       => __( 'Autocomplete Order', 'woocommerce-rave' ),
				'type'        => 'checkbox',
				'class'       => 'wc-flw-autocomplete-order',
				'description' => __( 'If enabled, the order will be marked as complete after successful payment', 'woocommerce-rave' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'payment_options'    => array(
				'title'       => __( 'Payment Options', 'woocommerce-rave' ),
				'type'        => 'select',
				'description' => __( 'Optional - Choice of payment method to use. Card, Account etc.', 'woocommerce-rave' ),
				'options'     => array(
					''                    => esc_html_x( 'Default', 'payment_options', 'woocommerce-rave' ),
					'card'                => esc_html_x( 'Card Only', 'payment_options', 'woocommerce-rave' ),
					'account'             => esc_html_x( 'Account Only', 'payment_options', 'woocommerce-rave' ),
					'ussd'                => esc_html_x( 'USSD Only', 'payment_options', 'woocommerce-rave' ),
					'qr'                  => esc_html_x( 'QR Only', 'payment_options', 'woocommerce-rave' ),
					'mpesa'               => esc_html_x( 'Mpesa Only', 'payment_options', 'woocommerce-rave' ),
					'mobilemoneyghana'    => esc_html_x( 'Ghana MM Only', 'payment_options', 'woocommerce-rave' ),
					'mobilemoneyrwanda'   => esc_html_x( 'Rwanda MM Only', 'payment_options', 'woocommerce-rave' ),
					'mobilemoneyzambia'   => esc_html_x( 'Zambia MM Only', 'payment_options', 'woocommerce-rave' ),
					'mobilemoneytanzania' => esc_html_x( 'Tanzania MM Only', 'payment_options', 'woocommerce-rave' ),
				),
				'default'     => '',
			),
			'go_live'            => array(
				'title'       => __( 'Mode', 'woocommerce-rave' ),
				'label'       => __( 'Live mode', 'woocommerce-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'Check this box if you\'re using your live keys.', 'woocommerce-rave' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'logging_option'     => array(
				'title'       => __( 'Disable Logging', 'woocommerce-rave' ),
				'label'       => __( 'Disable Logging', 'woocommerce-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'Check this box if you\'re disabling logging.', 'woocommerce-rave' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'barter'             => array(
				'title'       => __( 'Disable Barter', 'woocommerce-rave' ),
				'label'       => __( 'Disable Barter', 'woocommerce-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'Check the box if you want to disable barter.', 'woocommerce-rave' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),

		);

	}

	/**
	 * Order id
	 *
	 * @param int $order_id  Order id.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		// For Redirect Checkout.
		if ( 'redirect' === $this->payment_style ) {
			return $this->process_redirect_payments( $order_id );
		}

		// For inline Checkout.
		$order = wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Order id
	 *
	 * @param int $order_id  Order id.
	 *
	 * @return array|void
	 */
	public function process_redirect_payments( $order_id ) {
		include_once dirname( __FILE__ ) . '/client/class-flw-wc-payment-gateway-request.php';

		$order = wc_get_order( $order_id );

		try {
			$flutterwave_request = ( new FLW_WC_Payment_Gateway_Request() )->get_prepared_payload( $order, $this->get_secret_key() );
		} catch ( \InvalidArgumentException $flw_e ) {
			wc_add_notice( $flw_e, 'error' );
			// redirect user to check out page.
			return array(
				'result'   => 'fail',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}

		$sdk = $this->sdk->set_event_handler( new FlwEventHandler( $order ) );

		$response = $sdk->get_client()->request( $this->sdk::$standard_inline_endpoint, 'POST', $flutterwave_request );
		if ( ! is_wp_error( $response ) ) {
			$response = json_decode( $response['body'] );
			return array(
				'result'   => 'success',
				'redirect' => $response->data->link,
			);
		} else {
			wc_add_notice( 'Unable to Connect to Flutterwave.', 'error' );
			// redirect user to check out page.
			return array(
				'result'   => 'fail',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}
	}

	/**
	 * Handles admin notices
	 *
	 * @return void
	 */
	public function admin_notices(): void {

		if ( 'yes' === $this->enabled ) {

			if ( empty( $this->public_key ) || empty( $this->secret_key ) ) {

				$message = sprintf(
				/* translators: %s: url */
					__( 'Flutterwave is enabled, but the API keys are not set. Please <a href="%s">set your Flutterwave API keys</a> to be able to accept payments.', 'woocommerce-rave' ),
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_flw_payment_gateway' ) )
				);
			}
		}

	}

	/**
	 * Checkout receipt page
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	public function receipt_page( int $order_id ) {
		$order = wc_get_order( $order_id );
	}

	/**
	 * Loads (enqueue) static files (js & css) for the checkout page
	 *
	 * @return void
	 */
	public function payment_scripts() {

		// Load only on checkout page.
		if ( ! is_checkout_pay_page() ) {
			return;
		}

		$order_key = urldecode( $_GET['key'] ); //phpcs:ignore
		$order_id  = absint( get_query_var( 'order-pay' ) );

		$order = wc_get_order( $order_id );

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		wp_enqueue_script( 'flutterwave', $this->sdk::$checkout_url, array( 'jquery' ), FLW_WC_VERSION, false );

		$checkout_frontend_script = 'assets/js/checkout.js';
		if ( 'yes' === $this->go_live ) {
			$checkout_frontend_script = 'assets/js/checkout.min.js';
		}

		wp_enqueue_script( 'flutterwave_js', plugins_url( $checkout_frontend_script, FLW_WC_PLUGIN_FILE ), array( 'jquery', 'flutterwave' ), FLW_WC_VERSION, false );

		$payment_args = array();

		if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

			$email         = $order->get_billing_email();
			$amount        = $order->get_total();
			$txnref        = 'WOOC_' . $order_id . '_' . time();
			$the_order_id  = $order->get_id();
			$the_order_key = $order->get_order_key();
			$currency      = $order->get_currency();
			$redirect_url  = WC()->api_request_url( 'FLW_WC_Payment_Gateway' ) . '?order_id=' . $order_id;

			if ( $the_order_id === $order_id && $the_order_key === $order_key ) {

				$payment_args['email']           = $email;
				$payment_args['amount']          = $amount;
				$payment_args['tx_ref']          = $txnref;
				$payment_args['currency']        = $currency;
				$payment_args['public_key']      = $this->public_key;
				$payment_args['redirect_url']    = $redirect_url;
				$payment_args['payment_options'] = 'card,ussd,qr,PayPal';
				$payment_args['phone_number']    = $order->get_billing_phone();
				$payment_args['first_name']      = $order->get_billing_first_name();
				$payment_args['last_name']       = $order->get_billing_last_name();
				$payment_args['consumer_id']     = $order->get_customer_id();
				$payment_args['ip_address']      = $order->get_customer_ip_address();
				$payment_args['title']           = esc_html__( 'Order Payment', 'woocommerce-rave' );
				$payment_args['description']     = 'Payment for Order: ' . $order_id;
				$payment_args['logo']            = wp_get_attachment_url( get_theme_mod( 'custom_logo' ) );
				$payment_args['checkout_url']    = wc_get_checkout_url();
				$payment_args['cancel_url']      = $order->get_cancel_order_url();
			}
			update_post_meta( $order_id, '_flw_payment_txn_ref', $txnref );
		}
		wp_localize_script( 'flutterwave_js', 'flw_payment_args', $payment_args );
	}

	/**
	 * Verify payment made on the checkout page
	 *
	 * @return void
	 */
	public function flw_verify_payment() {
		$public_key     = $this->public_key;
		$secret_key     = $this->secret_key;
		$logging_option = $this->logging_option;
		$sdk            = $this->sdk;

		if ( isset( $_POST['tx_ref'] ) || isset( $_GET['tx_ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$txn_ref  = urldecode( sanitize_text_field( wp_unslash( $_GET['tx_ref'] ) ) ) ?? sanitize_text_field( wp_unslash( $_POST['tx_ref'] ) );// phpcs:ignore WordPress.Security.NonceVerification
			$o        = explode( '_', sanitize_text_field( $txn_ref ) );
			$order_id = intval( $o[1] );
			$order    = wc_get_order( $order_id );

			if ( isset( $_GET['status'] ) && 'cancelled' === $_GET['status'] ) {// phpcs:ignore WordPress.Security.NonceVerification
				$sdk->set_event_handler( new FlwEventHandler( $order ) )->cancel_payment( $txn_ref );
				header( 'Location: ' . wc_get_cart_url() );
				die();
			}

			$sdk->set_event_handler( new FlwEventHandler( $order ) )->requery_transaction( $txn_ref );

			$redirect_url = $this->get_return_url( $order );
			header( 'Location: ' . $redirect_url );
			die();
		}
	}

	/**
	 * Process Webhook
	 */
	public function flutterwave_webhooks() {
		$public_key     = $this->public_key;
		$secret_key     = $this->secret_key;
		$logging_option = $this->logging_option;
		$sdk            = $this->sdk;

		$event = file_get_contents( 'php://input' );

		// retrieve the signature sent in the request header's.
		$signature = ( $_SERVER['HTTP_VERIF_HASH'] ?? '' ); // phpcs:ignore

		if ( ! $signature ) {
			// redirect to the home page.
			wp_safe_redirect( home_url() );
			exit();
		}

		$local_signature = $this->get_option( 'secret_hash' );

		if ( $signature !== $local_signature ) {
			$msg = wp_json_encode(
				array(
					'status'  => 'error',
					'message' => 'Access Denied Hash does not match',
				)
			);
			die( $msg ); //phpcs:ignore
		}

		http_response_code( 200 );
		$event = json_decode( $event );

		if ( 'test_assess' === $event->event ) {
			$msg = wp_json_encode(
				array(
					'status'  => 'error',
					'message' => 'Test Webhook Successful',
				)
			);
			die( $msg ); //phpcs:ignore
		}

		if ( 'charge.completed' === $event->event ) {
			sleep( 6 );

			$event_type = $event->event;
			$event_data = $event->data;

			$txn_ref  = sanitize_text_field( $event_data->tx_ref );
			$o        = explode( '_', $txn_ref );
			$order_id = intval( $o[1] );
			$order    = wc_get_order( $order_id );
			// get order status.
			$current_order_status = $order->get_status();

			/**
			 * Fires after the webhook has been processed.
			 *
			 * @param string $event The webhook event.
			 * @since 2.3.0
			 */
			do_action( 'flw_webhook_after_action', wp_json_encode( $event, true ) );
			// TODO: Handle Checkout draft status for WooCommerce Blocks users.
			$statuses_in_question = array( 'pending', 'on-hold' );
			if ( 'failed' === $current_order_status ) {
				// NOTE: customer must have tried to make payment again in the same session.
				// TODO: add timeline to order notes to brief merchant as to why the order status changed.
				$statuses_in_question[] = 'failed';
			}
			if ( ! in_array( $current_order_status, $statuses_in_question, true ) ) {
				$msg = wp_json_encode(
					array(
						'status'  => 'error',
						'message' => 'Order already processed',
					)
				);
				die( $msg ); //phpcs:ignore
			}

			$sdk->set_event_handler( new FlwEventHandler( $order ) )->webhook_verify( $event_type, $event_data );
		}

		wp_safe_redirect( home_url() );
		exit();
	}

	/**
	 * Save Customer Card Details
	 *
	 * @param object $rave_response The response from Rave.
	 * @param int    $user_id The user ID.
	 * @param string $order_id The order ID.
	 */
	public static function save_card_details( object $rave_response, int $user_id, string $order_id ) {

		$token_code = $rave_response->card->card_tokens[0]->embedtoken ?? '';

		// save payment token to the order.
		self::save_subscription_payment_token( $order_id, $token_code );
		//phpcs:ignore $save_card = get_post_meta( $order_id, '_wc_rave_save_card', true );
	}

	/**
	 * Save payment token to the order for automatic renewal for further subscription payment
	 *
	 * @param mixed|string $order_id  The order ID.
	 * @param string       $payment_token The payment token.
	 */
	public static function save_subscription_payment_token( string $order_id, string $payment_token ) {

		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return;
		}

		if ( WC_Subscriptions_Order::order_contains_subscription( $order_id ) && ! empty( $payment_token ) ) {

			// Also store it on the subscriptions being purchased or paid for in the order.
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_order( $order_id );

			} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

			} else {

				$subscriptions = array();

			}

			foreach ( $subscriptions as $subscription ) {

				$subscription_id = $subscription->get_id();

				update_post_meta( $subscription_id, '_rave_wc_token', $payment_token );

			}
		}
	}
}



