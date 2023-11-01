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
		$webhook_url = WC()->api_request_url( 'Flw_WC_Payment_Webhook' );

		//make a request to the webhook url.
		$response = wp_remote_post( $webhook_url, array(
			'method'      => 'POST',
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer '.getenv('SECRET_KEY'),
				'VERIF-HASH' => $hash
			),
			'body'        => wp_json_encode( $data )
		) );

		$response_body = json_decode( wp_remote_retrieve_body( $response ) );

		// when testing webhook accessibility.
		// when request body is has a successful payment event.
		// when request body is has a failed payment event.
		if( isset( $data['data']['status'] ) && 'successful' === $data['data']['status'] ) {
			$this->assertEquals( '200', wp_remote_retrieve_response_code( $response ) );
		}
		
		// when request body is empty.
		if( empty( $data ) ) {
			$this->assertEquals( '204', wp_remote_retrieve_response_code( $response ) );
		}
		$this->assertEquals( $wbk_response, $response_body );
	}

	/**
	 * Data provider for webhook.
	 *
	 * @return array
	 */
	public function webhook_provider(): array {
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
	
		return [
			[
				'a4a6e4c86fc1347a48eeab1171f7fea1a10eecbac223b86db3b3e3e134fefa40',
				array(
					'amount' => 2000,
					'currency' => 'NGN',
					'status' => 'successful',
					'event' => 'test_access'
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
					'message' => 'Order Updated Successfully',
				)
			],
			[
				'a4a6e4c86fc1347a48eeab1171f7fea1a10eecbac223b86db3b3e3e134fefa40',
				$wbk_request['success'],
				array(
					'status'  => 'success',
					'message' => 'Order Processed Successfully',
				)
			],
			[
				'a4a6e4c86fc1347a48eeab1171f7fea1a10eecbac223b86db3b3e3e134fefa40',
				$wbk_request['empty'],
				array(
					'status'  => 'success',
					'message' => 'Webhook sent is deformed. missing data object.',
				)
			],

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
