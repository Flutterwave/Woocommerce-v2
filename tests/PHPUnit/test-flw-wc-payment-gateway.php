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
