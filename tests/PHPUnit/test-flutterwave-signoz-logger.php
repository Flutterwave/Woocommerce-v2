<?php
/**
 * Tests for the Flutterwave_Signoz_Logger class.
 *
 * @package Flutterwave\WooCommerce\Tests\phpunit
 */

use Flutterwave\WooCommerce\Util\Flutterwave_Signoz_Logger;

/**
 * Tests for the Flutterwave_Signoz_Logger class.
 */
class Test_Flutterwave_Signoz_Logger extends \WP_UnitTestCase {

	/**
	 * Logger under test.
	 *
	 * @var Flutterwave_Signoz_Logger
	 */
	private Flutterwave_Signoz_Logger $logger;

	/**
	 * HTTP requests captured by the pre_http_request filter.
	 *
	 * @var array
	 */
	private array $captured_requests = [];

	// -------------------------------------------------------------------------
	// Lifecycle
	// -------------------------------------------------------------------------

	public function set_up(): void {
		parent::set_up();
		$this->reset_singleton();
		$this->logger            = Flutterwave_Signoz_Logger::instance();
		$this->captured_requests = [];
	}

	public function tear_down(): void {
		parent::tear_down();
		remove_all_filters( 'pre_http_request' );
		$this->reset_singleton();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Reset the singleton so each test starts with a fresh instance.
	 */
	private function reset_singleton(): void {
		$ref  = new ReflectionClass( Flutterwave_Signoz_Logger::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * Read a private instance property via reflection.
	 *
	 * @param string $name Property name.
	 * @return mixed       Property value.
	 * @throws ReflectionException If the property does not exist.
	 */
	private function get_property( string $name ) {
		$ref  = new ReflectionClass( Flutterwave_Signoz_Logger::class );
		$prop = $ref->getProperty( $name );
		$prop->setAccessible( true );
		return $prop->getValue( $this->logger );
	}

	/**
	 * Add a filter that stubs the merchant-info API response.
	 *
	 * @param string|null $merchant_name  Value to return in `mn`; null omits the key.
	 * @param bool        $wp_error       Return a WP_Error instead of a response.
	 * @param string|null $raw_body       Override the full response body (raw JSON string).
	 */
	private function mock_merchant_api(
		?string $merchant_name = null,
		bool $wp_error = false,
		?string $raw_body = null
	): void {
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( $merchant_name, $wp_error, $raw_body ) {
				if ( strpos( $url, 'api.ravepay.co' ) === false ) {
					return $response;
				}
				if ( $wp_error ) {
					return new WP_Error( 'http_request_failed', 'Connection timed out' );
				}
				if ( null !== $raw_body ) {
					$body = $raw_body;
				} elseif ( null !== $merchant_name ) {
					$body = wp_json_encode(
						[
							'mn'     => $merchant_name,
							'status' => 'success',
						]
					);
				} else {
					$body = wp_json_encode( [ 'status' => 'success' ] );
				}
				return [
					'body'     => $body,
					'headers'  => [],
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			},
			10,
			3
		);
	}

	/**
	 * Add a filter that captures every request sent to the SigNoz endpoint.
	 */
	private function capture_signoz_requests(): void {
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) {
				if ( strpos( $url, 'signozservice-prod' ) === false ) {
					return $response;
				}
				$this->captured_requests[] = [
					'url'  => $url,
					'args' => $args,
					'body' => json_decode( $args['body'], true ),
				];
				return [
					'body'     => wp_json_encode( [ 'status' => 'ok' ] ),
					'headers'  => [],
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			},
			10,
			3
		);
	}

	// =========================================================================
	// Singleton
	// =========================================================================

	public function test_instance_returns_flutterwave_signoz_logger(): void {
		$this->assertInstanceOf( Flutterwave_Signoz_Logger::class, Flutterwave_Signoz_Logger::instance() );
	}

	public function test_instance_returns_same_object_on_repeated_calls(): void {
		$a = Flutterwave_Signoz_Logger::instance();
		$b = Flutterwave_Signoz_Logger::instance();
		$this->assertSame( $a, $b );
	}

	// =========================================================================
	// get_merchant_id()
	// =========================================================================

	public function test_get_merchant_id_returns_name_from_mn_field(): void {
		$this->mock_merchant_api( 'AcmeCorp' );
		$result = $this->logger->get_merchant_id( 'FLWPUBK-test-key' );
		$this->assertEquals( 'AcmeCorp', $result );
	}

	public function test_get_merchant_id_returns_null_on_wp_error(): void {
		$this->mock_merchant_api( null, true );
		$result = $this->logger->get_merchant_id( 'FLWPUBK-bad-key' );
		$this->assertNull( $result );
	}

	public function test_get_merchant_id_returns_null_when_mn_key_absent(): void {
		$this->mock_merchant_api( null ); // response has no 'mn' key
		$result = $this->logger->get_merchant_id( 'FLWPUBK-test-key' );
		$this->assertNull( $result );
	}

	public function test_get_merchant_id_returns_null_on_malformed_json(): void {
		$this->mock_merchant_api( null, false, 'not-valid-json' );
		$result = $this->logger->get_merchant_id( 'FLWPUBK-test-key' );
		$this->assertNull( $result );
	}

	public function test_get_merchant_id_returns_null_on_empty_body(): void {
		$this->mock_merchant_api( null, false, '' );
		$result = $this->logger->get_merchant_id( 'FLWPUBK-test-key' );
		$this->assertNull( $result );
	}

	public function test_get_merchant_id_returns_null_when_mn_is_null_in_response(): void {
		$this->mock_merchant_api( null, false, wp_json_encode( [ 'mn' => null ] ) );
		$result = $this->logger->get_merchant_id( 'FLWPUBK-test-key' );
		$this->assertNull( $result );
	}

	// =========================================================================
	// init()
	// =========================================================================

	public function test_init_sets_app_id_from_merchant_name(): void {
		$this->mock_merchant_api( 'TestMerchant' );
		$this->logger->init( 'FLWPUBK-test-key', 'sandbox' );
		$this->assertEquals( 'TestMerchant', $this->get_property( 'app_id' ) );
	}

	public function test_init_falls_back_to_public_key_when_merchant_id_is_null(): void {
		$this->mock_merchant_api( null ); // no 'mn' in response.
		$this->logger->init( 'FLWPUBK-fallback-key', 'sandbox' );
		$this->assertEquals( 'FLWPUBK-fallback-key', $this->get_property( 'app_id' ) );
	}

	public function test_init_falls_back_to_public_key_on_wp_error(): void {
		$this->mock_merchant_api( null, true );
		$this->logger->init( 'FLWPUBK-network-fail', 'sandbox' );
		$this->assertEquals( 'FLWPUBK-network-fail', $this->get_property( 'app_id' ) );
	}

	public function test_init_sets_environment_to_sandbox(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key', 'sandbox' );
		$this->assertEquals( 'sandbox', $this->get_property( 'environment' ) );
	}

	public function test_init_sets_environment_to_production(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key', 'production' );
		$this->assertEquals( 'production', $this->get_property( 'environment' ) );
	}

	public function test_init_defaults_environment_to_sandbox(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->assertEquals( 'sandbox', $this->get_property( 'environment' ) );
	}

	public function test_app_id_is_empty_before_init(): void {
		$this->assertEquals( '', $this->get_property( 'app_id' ) );
	}

	// =========================================================================
	// track_app_created()
	// =========================================================================

	public function test_track_app_created_does_not_send_before_init(): void {
		$this->capture_signoz_requests();
		$this->logger->track_app_created( 'FLWPUBK-test-key' );
		$this->assertEmpty( $this->captured_requests );
	}

	public function test_track_app_created_sends_exactly_one_request(): void {
		$this->mock_merchant_api( 'TestMerchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_app_created( 'FLWPUBK-test-key' );

		$this->assertCount( 1, $this->captured_requests );
	}

	public function test_track_app_created_sends_correct_event_name(): void {
		$this->mock_merchant_api( 'TestMerchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_app_created( 'FLWPUBK-test-key' );

		$this->assertEquals( 'app.created', $this->captured_requests[0]['body']['name'] );
	}

	public function test_track_app_created_payload_contains_required_fields(): void {
		$this->mock_merchant_api( 'TestMerchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_app_created( 'FLWPUBK-test-key' );

		$data = $this->captured_requests[0]['body']['data'];
		$this->assertEquals( 'TestMerchant', $data['app_id'] );
		$this->assertNull( $data['client_id'] );
		$this->assertEquals( 'FLWPUBK-test-key', $data['public_key'] );
		$this->assertEquals( 'woocommerce', $data['library'] );
		$this->assertArrayHasKey( 'library_version', $data );
	}

	// =========================================================================
	// track_request_sent()
	// =========================================================================

	public function test_track_request_sent_does_not_send_before_init(): void {
		$this->capture_signoz_requests();
		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );
		$this->assertEmpty( $this->captured_requests );
	}

	public function test_track_request_sent_sends_correct_event_name(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key', 'production' );
		$this->capture_signoz_requests();

		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );

		$this->assertEquals( 'request.sent', $this->captured_requests[0]['body']['name'] );
	}

	public function test_track_request_sent_payload_contains_required_fields(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key', 'production' );
		$this->capture_signoz_requests();

		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );

		$data = $this->captured_requests[0]['body']['data'];
		$this->assertEquals( 'Merchant', $data['app_id'] );
		$this->assertEquals( 'production', $data['environment'] );
		$this->assertEquals( 'v3', $data['api_version'] );
		$this->assertEquals( 'card', $data['method'] );
		$this->assertEquals( 'txn-ref-123', $data['reference'] );
		$this->assertEquals( '/v3/charges', $data['path'] );
		$this->assertArrayHasKey( 'library_version', $data );
	}

	public function test_track_request_sent_includes_sandbox_environment(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key', 'sandbox' );
		$this->capture_signoz_requests();

		$this->logger->track_request_sent( 'banktransfer', 'ref-456', '/v3/transfers' );

		$this->assertEquals( 'sandbox', $this->captured_requests[0]['body']['data']['environment'] );
	}

	// =========================================================================
	// track_transaction()
	// =========================================================================

	public function test_track_transaction_does_not_send_before_init(): void {
		$this->capture_signoz_requests();
		$this->logger->track_transaction( 'ref-123', 'NGN', 5000.00, 'card', 70.00 );
		$this->assertEmpty( $this->captured_requests );
	}

	public function test_track_transaction_sends_correct_event_name(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_transaction( 'ref-123', 'NGN', 5000.00, 'card', 70.00 );

		$this->assertEquals( 'app.transaction', $this->captured_requests[0]['body']['name'] );
	}

	public function test_track_transaction_payload_contains_required_fields(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_transaction( 'ref-123', 'NGN', 5000.00, 'card', 70.00 );

		$data = $this->captured_requests[0]['body']['data'];
		$this->assertEquals( 'Merchant', $data['app_id'] );
		$this->assertEquals( 'ref-123', $data['reference'] );
		$this->assertEquals( 'NGN', $data['currency'] );
		$this->assertEquals( 5000.00, $data['amount'] );
		$this->assertEquals( 70.00, $data['fee'] );
		$this->assertEquals( 'card', $data['method'] );
	}

	public function test_track_transaction_with_zero_amount_and_fee(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_transaction( 'ref-zero', 'USD', 0.0, 'card', 0.0 );

		$data = $this->captured_requests[0]['body']['data'];
		$this->assertEquals( 0.0, $data['amount'] );
		$this->assertEquals( 0.0, $data['fee'] );
	}

	public function test_track_transaction_with_fractional_amounts(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_transaction( 'ref-frac', 'GHS', 99.99, 'mobilemoney', 1.50 );

		$data = $this->captured_requests[0]['body']['data'];
		$this->assertEquals( 99.99, $data['amount'] );
		$this->assertEquals( 1.50, $data['fee'] );
	}

	public function test_track_transaction_supports_different_currencies(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		foreach ( [ 'NGN', 'USD', 'GBP', 'KES', 'ZAR' ] as $currency ) {
			$this->logger->track_transaction( 'ref-' . $currency, $currency, 100.0, 'card', 1.0 );
		}

		$this->assertCount( 5, $this->captured_requests );
		$sent_currencies = array_column(
			array_column( array_column( $this->captured_requests, 'body' ), 'data' ),
			'currency'
		);
		$this->assertEquals( [ 'NGN', 'USD', 'GBP', 'KES', 'ZAR' ], $sent_currencies );
	}

	// =========================================================================
	// track_error()
	// =========================================================================

	public function test_track_error_does_not_send_before_init(): void {
		$this->capture_signoz_requests();
		$this->logger->track_error( 'PAYMENT_FAILED', 'Card declined' );
		$this->assertEmpty( $this->captured_requests );
	}

	public function test_track_error_sends_correct_event_name(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_error( 'PAYMENT_FAILED', 'Card declined by issuer' );

		$this->assertEquals( 'app.error', $this->captured_requests[0]['body']['name'] );
	}

	public function test_track_error_payload_contains_required_fields(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_error( 'PAYMENT_FAILED', 'Card declined by issuer' );

		$data = $this->captured_requests[0]['body']['data'];
		$this->assertEquals( 'Merchant', $data['app_id'] );
		$this->assertEquals( 'woocommerce', $data['library'] );
		$this->assertEquals( 'PAYMENT_FAILED', $data['error_code'] );
		$this->assertEquals( 'Card declined by issuer', $data['error_message'] );
		$this->assertArrayHasKey( 'library_version', $data );
	}

	public function test_track_error_with_empty_strings(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_error( '', '' );

		$data = $this->captured_requests[0]['body']['data'];
		$this->assertEquals( '', $data['error_code'] );
		$this->assertEquals( '', $data['error_message'] );
	}

	public function test_track_error_with_special_characters(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$code    = 'ERR_<script>';
		$message = 'Failure: "card" & \'token\' not valid <EOF>';
		$this->logger->track_error( $code, $message );

		$data = $this->captured_requests[0]['body']['data'];
		$this->assertEquals( $code, $data['error_code'] );
		$this->assertEquals( $message, $data['error_message'] );
	}

	// =========================================================================
	// send() — verified indirectly through public track_* methods
	// =========================================================================

	public function test_send_posts_to_correct_url(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_error( 'ERR', 'msg' );

		$this->assertEquals(
			Flutterwave_Signoz_Logger::BASE_URL . '/events',
			$this->captured_requests[0]['url']
		);
	}

	public function test_send_uses_non_blocking_request(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );

		$blocking_value = null;
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( &$blocking_value ) {
				if ( strpos( $url, 'signozservice-prod' ) !== false ) {
					$blocking_value = $args['blocking'];
					return [
						'body'     => '{}',
						'headers'  => [],
						'response' => [ 'code' => 200 ],
					];
				}
				return $response;
			},
			10,
			3
		);

		$this->logger->track_error( 'ERR', 'msg' );

		$this->assertFalse( $blocking_value );
	}

	public function test_send_uses_five_second_timeout(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );

		$timeout_value = null;
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( &$timeout_value ) {
				if ( strpos( $url, 'signozservice-prod' ) !== false ) {
					$timeout_value = $args['timeout'];
					return [
						'body'     => '{}',
						'headers'  => [],
						'response' => [ 'code' => 200 ],
					];
				}
				return $response;
			},
			10,
			3
		);

		$this->logger->track_error( 'ERR', 'msg' );

		$this->assertEquals( 5, $timeout_value );
	}

	public function test_send_includes_correct_content_type_header(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );

		$content_type = null;
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( &$content_type ) {
				if ( strpos( $url, 'signozservice-prod' ) !== false ) {
					$content_type = $args['headers']['Content-Type'];
					return [
						'body'     => '{}',
						'headers'  => [],
						'response' => [ 'code' => 200 ],
					];
				}
				return $response;
			},
			10,
			3
		);

		$this->logger->track_error( 'ERR', 'msg' );

		$this->assertEquals( 'application/json', $content_type );
	}

	public function test_send_includes_api_key_header(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );

		$api_key = null;
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( &$api_key ) {
				if ( strpos( $url, 'signozservice-prod' ) !== false ) {
					$api_key = $args['headers']['x-api-key'];
					return [
						'body'     => '{}',
						'headers'  => [],
						'response' => [ 'code' => 200 ],
					];
				}
				return $response;
			},
			10,
			3
		);

		$this->logger->track_error( 'ERR', 'msg' );

		$this->assertEquals( Flutterwave_Signoz_Logger::API_KEY, $api_key );
	}

	public function test_send_payload_has_name_data_and_timestamp_keys(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_error( 'ERR', 'msg' );

		$body = $this->captured_requests[0]['body'];
		$this->assertArrayHasKey( 'name', $body );
		$this->assertArrayHasKey( 'data', $body );
		$this->assertArrayHasKey( 'timestamp', $body );
	}

	public function test_send_timestamp_matches_iso8601_format(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_error( 'ERR', 'msg' );

		$timestamp = $this->captured_requests[0]['body']['timestamp'];
		// Expect YYYY-MM-DDTHH:ii:ss.000Z
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.000Z$/',
			$timestamp
		);
	}

	// =========================================================================
	// library_version() — verified indirectly
	// =========================================================================

	public function test_library_version_matches_defined_constant_when_available(): void {
		if ( ! defined( 'FLW_WC_VERSION' ) ) {
			$this->markTestSkipped( 'FLW_WC_VERSION constant is not defined in this environment.' );
		}

		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_error( 'ERR', 'msg' );

		$this->assertEquals( FLW_WC_VERSION, $this->captured_requests[0]['body']['data']['library_version'] );
	}

	public function test_library_version_falls_back_to_hardcoded_default_when_constant_absent(): void {
		if ( defined( 'FLW_WC_VERSION' ) ) {
			$this->markTestSkipped( 'FLW_WC_VERSION is defined; cannot test the fallback path.' );
		}

		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_error( 'ERR', 'msg' );

		$this->assertEquals( '3.1.0', $this->captured_requests[0]['body']['data']['library_version'] );
	}

	// =========================================================================
	// Constants
	// =========================================================================

	public function test_base_url_constant_points_to_signoz_service(): void {
		$this->assertStringContainsString( 'signozservice-prod', Flutterwave_Signoz_Logger::BASE_URL );
	}

	public function test_library_constant_is_woocommerce(): void {
		$this->assertEquals( 'woocommerce', Flutterwave_Signoz_Logger::LIBRARY );
	}

	public function test_api_key_constant_is_not_empty(): void {
		$this->assertNotEmpty( Flutterwave_Signoz_Logger::API_KEY );
	}

	// =========================================================================
	// Edge cases — multiple calls / reinit
	// =========================================================================

	public function test_multiple_track_calls_each_send_one_request(): void {
		$this->mock_merchant_api( 'Merchant' );
		$this->logger->init( 'FLWPUBK-test-key' );
		$this->capture_signoz_requests();

		$this->logger->track_error( 'ERR1', 'first error' );
		$this->logger->track_error( 'ERR2', 'second error' );
		$this->logger->track_error( 'ERR3', 'third error' );

		$this->assertCount( 3, $this->captured_requests );
	}

	public function test_init_called_twice_overwrites_app_id(): void {
		$this->mock_merchant_api( 'FirstMerchant' );
		$this->logger->init( 'FLWPUBK-first-key', 'sandbox' );
		remove_all_filters( 'pre_http_request' );

		$this->mock_merchant_api( 'SecondMerchant' );
		$this->logger->init( 'FLWPUBK-second-key', 'production' );

		$this->assertEquals( 'SecondMerchant', $this->get_property( 'app_id' ) );
		$this->assertEquals( 'production', $this->get_property( 'environment' ) );
	}

	public function test_no_requests_sent_when_all_track_methods_called_before_init(): void {
		$this->capture_signoz_requests();

		$this->logger->track_app_created( 'FLWPUBK-test-key' );
		$this->logger->track_request_sent( 'card', 'ref-1', '/v3/charges' );
		$this->logger->track_transaction( 'ref-2', 'NGN', 1000.0, 'card', 14.0 );
		$this->logger->track_error( 'ERR', 'failed' );

		$this->assertEmpty( $this->captured_requests );
	}
}
