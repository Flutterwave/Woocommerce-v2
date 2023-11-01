<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Flutterwave\WooCommerce\Tests
 */

 require_once __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( PHP_VERSION_ID >= 80000 && file_exists( $_tests_dir . '/includes/phpunit7/MockObject' ) ) {
	// WP Core test library includes patches for PHPUnit 7 to make it compatible with PHP8.
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/NamespaceMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/ParametersMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/InvocationMocker.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/MockMethod.php';
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the WooCommerce plugin so we can use its classes in our WooCommerce Payments plugin.
	require_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';

	/**
	 * Set up shared by all tests.
	 */
	update_option( 'woocommerce_default_country', 'US:CA' );

	$_plugin_dir = __DIR__ . '/../';
	require $_plugin_dir . 'rave-woocommerce-payment-gateway.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

$wbk_request = array(
	'sucess' => json_decode(
		'{
			"event": "charge.completed",
			"data": {
			  "id": 285959875,
			  "tx_ref": "WOOC_1_TEST",
			  "flw_ref": "PeterEkene/FLW270177170",
			  "device_fingerprint": "a42937f4a73ce8bb8b8df14e63a2df31",
			  "amount": 100,
			  "currency": "NGN",
			  "charged_amount": 100,
			  "app_fee": 1.4,
			  "merchant_fee": 0,
			  "processor_response": "Approved by Financial Institution",
			  "auth_model": "PIN",
			  "ip": "197.210.64.96",
			  "narration": "CARD Transaction ",
			  "status": "successful",
			  "payment_type": "card",
			  "created_at": "2020-07-06T19:17:04.000Z",
			  "account_id": 17321,
			  "customer": {
				"id": 215604089,
				"name": "Yemi Desola",
				"phone_number": null,
				"email": "user@gmail.com",
				"created_at": "2020-07-06T19:17:04.000Z"
			  },
			  "card": {
				"first_6digits": "123456",
				"last_4digits": "7889",
				"issuer": "VERVE FIRST CITY MONUMENT BANK PLC",
				"country": "NG",
				"type": "VERVE",
				"expiry": "02/23"
			  }
			}
		  }',
	true
	),
	'failed' => json_decode(
		'{
			"event": "charge.completed",
			"data": {
			  "id": 408136545,
			  "tx_ref": "WOOC_1_TEST",
			  "flw_ref": "NETFLIX/SM31570678271",
			  "device_fingerprint": "7852b6c97d67edce50a5f1e540719e39",
			  "amount": 100000,
			  "currency": "NGN",
			  "charged_amount": 100000,
			  "app_fee": 1400,
			  "merchant_fee": 0,
			  "processor_response": "invalid token supplied",
			  "auth_model": "PIN",
			  "ip": "72.140.222.142",
			  "narration": "CARD Transaction ",
			  "status": "failed",
			  "payment_type": "card",
			  "created_at": "2021-04-16T14:52:37.000Z",
			  "account_id": 82913,
			  "customer": {
				"id": 255128611,
				"name": "a a",
				"phone_number": null,
				"email": "a@b.com",
				"created_at": "2021-04-16T14:52:37.000Z"
			  },
			  "card": {
				"first_6digits": "536613",
				"last_4digits": "8816",
				"issuer": "MASTERCARD ACCESS BANK PLC  CREDIT",
				"country": "NG",
				"type": "MASTERCARD",
				"expiry": "12/21"
			  }
			},
			"event.type": "CARD_TRANSACTION"
		  }'
		,true
	),
	'empty' => array()
);
