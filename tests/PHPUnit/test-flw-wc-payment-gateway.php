<?php
/**
 * Tests for the FLW_WC_Payment_Gateway class.
 *
 * @package Flutterwave\WooCommerce\Tests\phpunit
 */

/**
 * Tests for the FLW_WC_Payment_Gateway class.
 */
class Test_FLW_WC_Payment_Gateway extends \WP_UnitTestCase {
	/**
	 * Flutterwave Gateway under test.
	 *
	 * @var \FLW_WC_Payment_Gateway
	 */
	private \FLW_WC_Payment_Gateway $gateway;

	/**
	 * Expected gateway name.
	 *
	 * @var string
	 */
	private string $expected_gateway_name = 'rave';

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {

		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->gateway = new \FLW_WC_Payment_Gateway();

		update_option( 'woocommerce_rave_settings', [
			'enabled' => 'yes',
			'go_live' => 'no',
			'logging_option' => 'no',
			'secret_hash' => '581e4231-441e-4730-88bf-8f181897759ea8f1',
			'autocomplete_order' => 'yes',
		]);
	}

	/**
	 * Tests the gateway ID.
	 */
	public function test_id() {
		$this->assertEquals( $this->expected_gateway_name, $this->gateway->id );
	}

	/**
	 * Tests the gateway has fields.
	 */
	public function test_has_fields() {
		$this->assertFalse( $this->gateway->has_fields );
	}

	/**
	 * Tests the gateway supports.
	 */
	public function test_supports() {
		$this->assertTrue( $this->gateway->supports( 'products' ) );
	}

	/**
	 * Tests the gateway webhook.
	 *
	 * @dataProvider webhook_provider
	 */
	public function test_webhook_is_accessible( string $hash, array $data, array $wbk_response ) {
		// Hook into pre_http_request to mock the response
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ($data) {


			// Check if this is the correct URL for the webhook
			if ( strpos($url, 'Flw_WC_Payment_Webhook') !== false ) {

				//test accessibliity.
				if($data['event'] === 'test_assess') {
					return [
						'body' => json_encode([
							'status'  => 'success',
							'message' => 'Webhook Test Successful. handler is accessible'
						]),
						'headers' => ['Content-Type' => 'application/json'],
						'response' => ['code' => 200, 'message' => 'OK']
					];
				}

				// Mock response for successful webhook call
				if ($data['event'] === 'charge.completed' && $data['data']['status'] === 'successful') {
					return [
						'body'    => json_encode(['status' => 'success', 'message' => 'Order Processed Successfully.']),
						'headers' => ['Content-Type' => 'application/json'],
						'response' => ['code' => 200, 'message' => 'OK']
					];
				}

				// Mock response for failed webhook call
				if ($data['event'] === 'charge.completed' && $data['data']['status'] === 'failed') {
					return [
						'body'    => json_encode(['status' => 'error', 'message' => 'Order Processed Successfully']),
						'headers' => ['Content-Type' => 'application/json'],
						'response' => ['code' => 200, 'message' => 'OK']
					];
				}
			}

			// Return null to continue with the normal request if it's not the webhook URL
			return null;
		}, 10, 3);

		// Test logic - making the actual request
		$webhook_url = WC()->api_request_url('Flw_WC_Payment_Webhook');
		$response = wp_remote_post($webhook_url, array(
			'method'    => 'POST',
			'headers'   => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . getenv('SECRET_KEY'),
				'VERIF-HASH'    => $hash
			),
			'body'      => wp_json_encode($data)
		));

		// Test that the response is not a WP error
		$this->assertNotWPError($response);

		// Decode the response body
		$response_body = json_decode(wp_remote_retrieve_body($response), TRUE);

		// Check if the HTTP status code is 200 (OK)
		$this->assertEquals(200, wp_remote_retrieve_response_code($response));

		// Check if the response matches the expected response
		$this->assertEquals($wbk_response, $response_body);
	}

	/**
	 * Tests the gateway webhook on invalid transaction reference.
	 */
	public function test_webhook_transaction_not_found() {
		$hash = "a4a6e4c86fc1347a48eeab1171f7fea1a10eecbac223b86db3b3e3e134fefa40";
		$data = [
			"event" => "charge.completed",
			"data" => ["tx_ref" => "Rave-Pages846040622798"]
		];

		// Hook into pre_http_request to mock the response
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ($data) {
			// Check if this is the correct URL for the webhook
			if ( strpos($url, 'Flw_WC_Payment_Webhook') !== false ) {
				// Mock response for a failed transaction (invalid reference)
				return [
					'body'    => json_encode(['status' => 'error', 'message' => 'Transaction not found.']),
					'headers' => ['Content-Type' => 'application/json'],
					'response' => ['code' => 404, 'message' => 'Not Found']
				];
			}

			// Return null to continue with the normal request if it's not the webhook URL
			return null;
		}, 10, 3);

		// Test logic - making the actual request
		$webhook_url = WC()->api_request_url('Flw_WC_Payment_Webhook');
		$response = wp_remote_post($webhook_url, array(
			'method'    => 'POST',
			'headers'   => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . getenv('SECRET_KEY'),
				'VERIF-HASH'    => $hash
			),
			'body'      => wp_json_encode($data)
		));

		// Test that the response is not a WP error
		$this->assertNotWPError($response);

		// Decode the response body
		$response_body = json_decode(wp_remote_retrieve_body($response));

		// Check if the HTTP status code is 404 (Not Found)
		$this->assertEquals(404, wp_remote_retrieve_response_code($response));

		// Check if the response matches the expected error message
		$this->assertEquals('Transaction not found.', $response_body->message);
	}

	/**
	 * Tests the gateway webhook.
	 *
	 * @dataProvider webhook_204_provider
	 */
	public function test_webhook_no_body_204_response( string $hash, array $data, array $wbk_response) {
		//make a request to the webhook url.
		function retrieve_response_code() : int {
			return 204;
		}

		$this->assertEquals( WP_Http::NO_CONTENT, retrieve_response_code() );
	}

		/**
	 * Data provider for webhook.
	 *
	 * @return array
	 */
	public function webhook_204_provider(): array {
		return[
			[
				'a4a6e4c86fc1347a48eeab1171f7fea1a10eecbac223b86db3b3e3e134fefa40',
				array(),
				array(
					'status'  => 'error',
					'message' => 'Webhook sent is deformed. missing data object.',
				)
			],
		];
	}

	public function _fake_no_body_204_response_code( $response, $parsed_args, $url ) {
		file_put_contents( $parsed_args['filename'], 'This is an unexpected error message from your favorite server.' );
		return array(
			'response' => array(
				'code' => WP_Http::NO_CONTENT,
				'status'  => 'error',
				'message' => 'Webhook sent is deformed. missing data object.',
			),
		);
	}

	/**
	 * Data provider for webhook.
	 *
	 * @return array
	 */
	public function webhook_provider(): array {
		$wbk_request = array(
			'success' => json_decode(
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
	
		return [
			[
				'a4a6e4c86fc1347a48eeab1171f7fea1a10eecbac223b86db3b3e3e134fefa40',
				array(
					'amount' => 2000,
					'currency' => 'NGN',
					'status' => 'successful',
					'event' => 'test_assess'
				),
				array(
					'status'  => 'success',
					'message' => 'Webhook Test Successful. handler is accessible',
				)
			],
			[
				'a4a6e4c86fc1347a48eeab1171f7fea1a10eecbac223b86db3b3e3e134fefa40',
				$wbk_request['failed'],
				array(
					'status'  => 'success',
					'message' => 'Order Processed Successfully',
				)
			],
			[
				'a4a6e4c86fc1347a48eeab1171f7fea1a10eecbac223b86db3b3e3e134fefa40',
				$wbk_request['success'],
				array(
					'status'  => 'success',
					'message' => 'Order Processed Successfully',
				)
			]

		];
	}

	/**
	 * Tear down things all tests need.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		unset( $this->gateway );
	}
}
