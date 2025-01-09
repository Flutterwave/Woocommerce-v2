<?php
/**
 * Tests for the FLW_WC_Payment_Gateway_Request class.
 *
 * @package Flutterwave\WooCommerce\Tests\phpunit
 */

use Flutterwave\WooCommerce\Client\FLW_WC_Payment_Gateway_Request;

class Test_FLW_WC_Payment_Gateway_Request extends \WP_UnitTestCase {

	public $gateway_options;

	public function set_up() {
		parent::set_up();

		update_option( 'woocommerce_rave_settings', [
			'enabled' => 'yes',
			'go_live' => 'no',
			'logging_option' => 'no',
			'secret_hash' => '581e4231-441e-4730-88bf-8f181897759ea8f1',
			'autocomplete_order' => 'yes',
		]);
	}

	public function data_provider_for_test_get_prepared_payload(): array {
			$secret_key = 'FLWSECK-XXXXXXXXXXXXXXX-X';
			update_option( 'woocommerce_rave_settings', [
				'test_secret_key' => $secret_key 
			]);

			$order = new WC_Order();
			$order->set_id( 1 );
			$order->set_currency( 'NGN' );
			$order->set_total( 1000 );
			$order->set_billing_email( 'jbond@gmail.com');
			$order->set_billing_company('MI6' );
			$order->set_billing_phone( '0000000007' );
			$order->set_billing_address_1( '1, MI6 Street' );
			$order->set_billing_address_2( 'MI6' );
			$order->set_billing_city( 'London' );
			$order->set_billing_state( 'London' );
			$order->set_billing_postcode( '00007' );
			$order->set_billing_country( 'GB' );
			$order->set_billing_first_name( 'James' );
			$order->set_billing_last_name( 'Bond' );
			// $order->set_customer_ip_address( $_SERVER['REMOTE_ADDR']);

			$txnref = 'WOOC_'.$order->get_id().'_TEST';

			$data_to_join  = array(
				'amount'     => (float) $order->get_total(),
				'currency'   => $order->get_currency(),
				'email'      => $order->get_billing_email(),
				'tx_ref'     => $txnref,
				'secret_key' => 'FLWSECK-XXXXXXXXXXXXXXX-X',
			);

			$stringToHash = '';
			foreach ( $data_to_join as $key => $value ) {
				if ( 'secret_key' === $key ) {
					$stringToHash .= hash( 'sha256', $value );
				} else {
					$stringToHash .= $value;
				}
			}

			$hash = hash( 'sha256', $stringToHash );

			return [
				[
					$order,
					'FLWSECK-XXXXXXXXXXXXXXX-X',
					[
						'amount'          => (float) $order->get_total(),
						'tx_ref'          => $txnref,
						'currency'        => $order->get_currency(),
						'payment_options' => 'card',
						'redirect_url'    => get_site_url().'/?wc-api=FLW_WC_Payment_Gateway&order_id=1',
						'payload_hash'   => $hash,
						'customer'        => [
							'email'        => 'jbond@gmail.com',
							'phone_number' => '0000000007',
							'name'         => 'James Bond',
						],
						'meta'            => array(
							'consumer_id' => $order->get_customer_id(),
							'ip_address'  => $order->get_customer_ip_address(),
							'user-agent'  => $order->get_customer_user_agent(),
						),
						'customizations'  => array(
							'title'       => get_bloginfo( 'name' ),
							'description' => sprintf('Payment for order %s', $order->get_order_number() ),
						),
					],
				],
			];

	}

	/**
	 * @dataProvider data_provider_for_test_get_prepared_payload
	 */
	public function test_get_prepared_payload( $order, $secret_key, $expected ) {
		$flw_wc_payment_gateway_request = new FLW_WC_Payment_Gateway_Request();

		$payload = $flw_wc_payment_gateway_request->get_prepared_payload( $order, $secret_key, true );
		$this->assertEquals( $expected, $payload );
	}

	public function test_get_prepared_payload_with_no_secret_key() {
		$order = new WC_Order();
		$order->set_id( 1 );
		$order->set_currency( 'NGN' );
		$order->set_total( 1000 );
		$order->set_billing_email( 'sample@gmail.com' );
		$this->expectExceptionMessage('This Payment Method is current unavailable as Administrator is yet to Configure it.Please contact Administrator for more information.');
		$this->expectException(\InvalidArgumentException::class);
		(new FLW_WC_Payment_Gateway_Request())->get_prepared_payload( $order, '', true );
	}
}
