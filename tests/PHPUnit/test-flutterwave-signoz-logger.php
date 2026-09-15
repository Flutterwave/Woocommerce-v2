<?php
/**
 * Tests for the Flutterwave_Signoz_Logger class.
 *
 * Every event except app.created is handed to Action Scheduler, so the track_*
 * tests assert on what gets enqueued. The transport (health gate, circuit
 * breaker, 503 retries) is driven directly through handle_scheduled_send().
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
	 * Events intercepted on their way into Action Scheduler.
	 *
	 * @var array
	 */
	private array $queued = array();

	/**
	 * HTTP requests made to the SigNoz service.
	 *
	 * @var array
	 */
	private array $requests = array();

	// -------------------------------------------------------------------------
	// Lifecycle
	// -------------------------------------------------------------------------

	public function set_up(): void {
		parent::set_up();
		delete_option( 'woocommerce_rave_settings' );
		$this->reset_singleton();
		$this->logger   = Flutterwave_Signoz_Logger::instance();
		$this->queued   = array();
		$this->requests = array();
	}

	public function tear_down(): void {
		$this->reset_singleton();
		parent::tear_down();
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
	 * @return mixed
	 */
	private function get_property( string $name ) {
		$ref  = new ReflectionClass( Flutterwave_Signoz_Logger::class );
		$prop = $ref->getProperty( $name );
		$prop->setAccessible( true );
		return $prop->getValue( $this->logger );
	}

	/**
	 * Intercept events the logger enqueues so they are recorded, not scheduled.
	 */
	private function capture_queued_events(): void {
		add_filter(
			'pre_as_enqueue_async_action',
			function ( $pre, $hook, $args, $group ) {
				if ( Flutterwave_Signoz_Logger::SCHEDULED_SEND_HOOK !== $hook ) {
					return $pre;
				}
				$this->queued[] = array(
					'group'     => $group,
					'name'      => $args[0],
					'data'      => $args[1],
					'timestamp' => $args[2],
				);
				return 1;
			},
			10,
			4
		);
	}

	/**
	 * Payload of the most recently queued event.
	 *
	 * @return array
	 */
	private function last_queued_data(): array {
		$this->assertNotEmpty( $this->queued, 'Expected an event to be queued.' );
		$last = end( $this->queued );
		return $last['data'];
	}

	/**
	 * Stub the SigNoz service and record every request made to it.
	 *
	 * @param array          $event_responses Responses for successive POST /events calls, each
	 *                                        array( code, body ) or a WP_Error. The last one repeats.
	 * @param array|WP_Error $health          Response for GET /health/ready.
	 */
	private function stub_signoz( array $event_responses = array( array( 200, '{}' ) ), $health = array( 200, '{"status":"ok"}' ) ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( &$event_responses, $health ) {
				if ( 0 !== strpos( $url, Flutterwave_Signoz_Logger::BASE_URL ) ) {
					return $pre;
				}

				$path             = substr( $url, strlen( Flutterwave_Signoz_Logger::BASE_URL ) );
				$this->requests[] = array(
					'path' => $path,
					'args' => $args,
					'body' => isset( $args['body'] ) ? json_decode( $args['body'], true ) : null,
				);

				if ( Flutterwave_Signoz_Logger::HEALTH_PATH === $path ) {
					$response = $health;
				} else {
					$response = count( $event_responses ) > 1 ? array_shift( $event_responses ) : $event_responses[0];
				}

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				return array(
					'headers'  => array(),
					'body'     => $response[1],
					'response' => array(
						'code'    => $response[0],
						'message' => '',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Recorded SigNoz requests for one path.
	 *
	 * @param string $path Request path, e.g. "/events".
	 * @return array
	 */
	private function requests_to( string $path ): array {
		return array_values(
			array_filter(
				$this->requests,
				function ( $request ) use ( $path ) {
					return $path === $request['path'];
				}
			)
		);
	}

	/**
	 * Stub the merchant-info API response.
	 *
	 * @param string|WP_Error $body Raw response body, or a WP_Error.
	 */
	private function mock_merchant_api( $body ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $body ) {
				if ( false === strpos( $url, 'api.ravepay.co' ) ) {
					return $pre;
				}
				if ( is_wp_error( $body ) ) {
					return $body;
				}
				return array(
					'headers'  => array(),
					'body'     => $body,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);
	}

	// =========================================================================
	// Singleton / hooks
	// =========================================================================

	public function test_instance_returns_flutterwave_signoz_logger(): void {
		$this->assertInstanceOf( Flutterwave_Signoz_Logger::class, Flutterwave_Signoz_Logger::instance() );
	}

	public function test_instance_returns_same_object_on_repeated_calls(): void {
		$this->assertSame( Flutterwave_Signoz_Logger::instance(), Flutterwave_Signoz_Logger::instance() );
	}

	public function test_register_hooks_attaches_the_scheduled_send_callback(): void {
		Flutterwave_Signoz_Logger::register_hooks();

		$this->assertSame(
			10,
			has_action( Flutterwave_Signoz_Logger::SCHEDULED_SEND_HOOK, array( $this->logger, 'handle_scheduled_send' ) )
		);
	}

	// =========================================================================
	// get_merchant_id()
	// =========================================================================

	public function test_get_merchant_id_prefers_stored_merchant_id_without_a_request(): void {
		update_option( 'woocommerce_rave_settings', array( 'merchant_id' => 'StoredMerchant' ) );
		$this->mock_merchant_api( new WP_Error( 'http_request_failed', 'should not be called' ) );

		$this->assertSame( 'StoredMerchant', $this->logger->get_merchant_id( 'FLWPUBK-test-key' ) );
	}

	public function test_get_merchant_id_returns_name_from_mn_field(): void {
		$this->mock_merchant_api( wp_json_encode( array( 'mn' => 'AcmeCorp' ) ) );

		$this->assertSame( 'AcmeCorp', $this->logger->get_merchant_id( 'FLWPUBK-test-key' ) );
	}

	public function test_get_merchant_id_returns_null_on_wp_error(): void {
		$this->mock_merchant_api( new WP_Error( 'http_request_failed', 'Connection timed out' ) );

		$this->assertNull( $this->logger->get_merchant_id( 'FLWPUBK-bad-key' ) );
	}

	public function test_get_merchant_id_returns_null_when_mn_key_absent(): void {
		$this->mock_merchant_api( wp_json_encode( array( 'status' => 'success' ) ) );

		$this->assertNull( $this->logger->get_merchant_id( 'FLWPUBK-test-key' ) );
	}

	public function test_get_merchant_id_returns_null_on_malformed_json(): void {
		$this->mock_merchant_api( 'not-valid-json' );

		$this->assertNull( $this->logger->get_merchant_id( 'FLWPUBK-test-key' ) );
	}

	public function test_get_merchant_id_returns_null_on_empty_body(): void {
		$this->mock_merchant_api( '' );

		$this->assertNull( $this->logger->get_merchant_id( 'FLWPUBK-test-key' ) );
	}

	public function test_get_merchant_id_returns_null_when_mn_is_null_in_response(): void {
		$this->mock_merchant_api( wp_json_encode( array( 'mn' => null ) ) );

		$this->assertNull( $this->logger->get_merchant_id( 'FLWPUBK-test-key' ) );
	}

	// =========================================================================
	// init() / environment / app id
	// =========================================================================

	public function test_environment_defaults_to_sandbox(): void {
		$this->logger->init();

		$this->assertSame( 'sandbox', $this->get_property( 'environment' ) );
	}

	public function test_init_sets_environment_to_production_when_live(): void {
		update_option( 'woocommerce_rave_settings', array( 'go_live' => 'yes' ) );

		$this->logger->init();

		$this->assertSame( 'production', $this->get_property( 'environment' ) );
	}

	public function test_init_resets_app_registered_and_keeps_other_settings(): void {
		update_option(
			'woocommerce_rave_settings',
			array(
				'app_registered' => true,
				'public_key'     => 'FLWPUBK-test-key',
			)
		);

		$this->logger->init();

		$settings = get_option( 'woocommerce_rave_settings' );
		$this->assertFalse( $settings['app_registered'] );
		$this->assertSame( 'FLWPUBK-test-key', $settings['public_key'] );
	}

	public function test_mark_app_registered_sets_flag(): void {
		$this->logger->mark_app_registered();

		$this->assertTrue( get_option( 'woocommerce_rave_settings' )['app_registered'] );
	}

	public function test_get_app_id_prefers_stored_app_id_and_normalizes_whitespace(): void {
		update_option(
			'woocommerce_rave_settings',
			array(
				'app_id'     => ' My App  Id ',
				'public_key' => 'FLWPUBK-test-key',
			)
		);

		$this->assertSame( 'My-App-Id', $this->logger->get_app_id() );
	}

	public function test_get_app_id_falls_back_to_public_key(): void {
		update_option( 'woocommerce_rave_settings', array( 'public_key' => 'FLWPUBK-test-key' ) );

		$this->assertSame( 'FLWPUBK-test-key', $this->logger->get_app_id() );
	}

	public function test_get_app_id_returns_unknown_when_nothing_configured(): void {
		$this->assertSame( 'unknown', $this->logger->get_app_id() );
	}

	// =========================================================================
	// track_app_created() — synchronous
	// =========================================================================

	public function test_track_app_created_does_nothing_for_empty_public_key(): void {
		$this->stub_signoz();

		$this->assertNull( $this->logger->track_app_created( '' ) );
		$this->assertEmpty( $this->requests );
	}

	public function test_track_app_created_reuses_stored_app_id_without_a_request(): void {
		update_option( 'woocommerce_rave_settings', array( 'app_id' => 'app-existing' ) );
		$this->stub_signoz();

		$this->assertSame( 'app-existing', $this->logger->track_app_created( 'FLWPUBK-test-key' ) );
		$this->assertEmpty( $this->requests );
	}

	public function test_track_app_created_sends_event_and_persists_returned_app_id(): void {
		$this->stub_signoz( array( array( 200, wp_json_encode( array( 'app_id' => 'app-123' ) ) ) ) );

		$app_id = $this->logger->track_app_created( 'FLWPUBK-test-key' );

		$this->assertSame( 'app-123', $app_id );
		$this->assertSame( 'app-123', get_option( 'woocommerce_rave_settings' )['app_id'] );

		$events = $this->requests_to( '/events' );
		$this->assertCount( 1, $events );
		$this->assertSame( 'app.created', $events[0]['body']['name'] );

		$data = $events[0]['body']['data'];
		$this->assertNull( $data['client_id'] );
		$this->assertSame( 'FLWPUBK-test-key', $data['public_key'] );
		$this->assertSame( Flutterwave_Signoz_Logger::LIBRARY, $data['library'] );
		$this->assertArrayHasKey( 'library_version', $data );
	}

	public function test_track_app_created_does_not_queue_through_action_scheduler(): void {
		$this->capture_queued_events();
		$this->stub_signoz( array( array( 200, wp_json_encode( array( 'app_id' => 'app-123' ) ) ) ) );

		$this->logger->track_app_created( 'FLWPUBK-test-key' );

		$this->assertEmpty( $this->queued );
	}

	public function test_track_app_created_returns_null_when_response_has_no_app_id(): void {
		$this->stub_signoz( array( array( 200, '{}' ) ) );

		$this->assertNull( $this->logger->track_app_created( 'FLWPUBK-test-key' ) );
		$this->assertArrayNotHasKey( 'app_id', get_option( 'woocommerce_rave_settings', array() ) );
	}

	public function test_track_app_created_skips_post_when_service_unhealthy(): void {
		$this->stub_signoz( array( array( 200, '{"app_id":"app-123"}' ) ), array( 503, '' ) );

		$this->assertNull( $this->logger->track_app_created( 'FLWPUBK-test-key' ) );
		$this->assertEmpty( $this->requests_to( '/events' ) );
	}

	// =========================================================================
	// track_request_sent()
	// =========================================================================

	public function test_track_request_sent_queues_event_without_inline_http(): void {
		$this->capture_queued_events();
		$this->stub_signoz();

		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );

		$this->assertCount( 1, $this->queued );
		$this->assertSame( 'request.sent', $this->queued[0]['name'] );
		$this->assertSame( Flutterwave_Signoz_Logger::AS_GROUP, $this->queued[0]['group'] );
		$this->assertEmpty( $this->requests );
	}

	public function test_track_request_sent_payload_contains_required_fields(): void {
		update_option(
			'woocommerce_rave_settings',
			array(
				'go_live'    => 'yes',
				'public_key' => 'FLWPUBK-test-key',
			)
		);
		$this->capture_queued_events();

		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );

		$data = $this->last_queued_data();
		$this->assertSame( 'FLWPUBK-test-key', $data['app_id'] );
		$this->assertSame( 'production', $data['environment'] );
		$this->assertSame( 'v3', $data['api_version'] );
		$this->assertSame( Flutterwave_Signoz_Logger::LIBRARY, $data['library'] );
		$this->assertSame( 'card', $data['method'] );
		$this->assertSame( '/v3/charges', $data['path'] );
		$this->assertSame( 'txn-ref-123', $data['reference'] );
		$this->assertArrayHasKey( 'library_version', $data );
	}

	public function test_track_request_sent_uses_sandbox_environment_when_not_live(): void {
		$this->capture_queued_events();

		$this->logger->track_request_sent( 'banktransfer', 'ref-456', '/v3/transfers' );

		$this->assertSame( 'sandbox', $this->last_queued_data()['environment'] );
	}

	public function test_track_request_sent_normalizes_reference(): void {
		$this->capture_queued_events();

		$this->logger->track_request_sent( 'card', ' WOOC 12/abc? ', '/v3/charges' );

		$this->assertSame( 'WOOC-12-abc', $this->last_queued_data()['reference'] );
	}

	public function test_track_request_sent_is_deduplicated_per_reference(): void {
		$this->capture_queued_events();

		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );
		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );
		$this->logger->track_request_sent( 'card', 'txn-ref-456', '/v3/charges' );

		$this->assertCount( 2, $this->queued );
	}

	public function test_track_request_sent_writes_payload_to_wc_logger(): void {
		$this->capture_queued_events();
		$wc_logger = new class() {
			public array $messages = array();

			public function info( $message ) {
				$this->messages[] = $message;
			}
		};

		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges', $wc_logger );

		$this->assertCount( 1, $wc_logger->messages );
		$this->assertStringStartsWith( 'request.sent: ', $wc_logger->messages[0] );
	}

	public function test_track_request_sent_generates_trace_context_for_reference(): void {
		$this->capture_queued_events();

		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );

		$context = $this->last_queued_data()['trace_context'];
		$this->assertTrue( ctype_xdigit( $context['trace_id'] ) );
		$this->assertTrue( ctype_xdigit( $context['span_id'] ) );
		$this->assertSame( 32, strlen( $context['trace_id'] ) );
		$this->assertSame( 16, strlen( $context['span_id'] ) );
		$this->assertArrayNotHasKey( 'parent_span_id', $context );
	}

	public function test_track_request_sent_links_spans_for_same_reference(): void {
		$this->capture_queued_events();

		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );
		delete_transient( 'flw_signoz_req_' . md5( 'txn-ref-123' ) );
		$this->logger->track_request_sent( 'card', 'txn-ref-123', '/v3/charges' );

		$this->assertCount( 2, $this->queued );
		$first  = $this->queued[0]['data']['trace_context'];
		$second = $this->queued[1]['data']['trace_context'];

		$this->assertSame( $first['trace_id'], $second['trace_id'] );
		$this->assertSame( $first['span_id'], $second['parent_span_id'] );
		$this->assertNotSame( $first['span_id'], $second['span_id'] );
	}

	// =========================================================================
	// track_transaction()
	// =========================================================================

	public function test_track_transaction_payload_contains_required_fields(): void {
		update_option( 'woocommerce_rave_settings', array( 'app_id' => 'app-123' ) );
		$this->capture_queued_events();

		$this->logger->track_transaction( 'ref-123', 'NGN', 5000.00, 'card', 70.00 );

		$this->assertSame( 'app.transaction', $this->queued[0]['name'] );
		$data = $this->last_queued_data();
		$this->assertSame( 'app-123', $data['app_id'] );
		$this->assertSame( 'ref-123', $data['reference'] );
		$this->assertSame( Flutterwave_Signoz_Logger::LIBRARY, $data['library'] );
		$this->assertSame( 'NGN', $data['currency'] );
		$this->assertSame( 5000.00, $data['amount'] );
		$this->assertSame( 70.00, $data['fee'] );
		$this->assertSame( 'card', $data['method'] );
		$this->assertArrayHasKey( 'trace_context', $data );
	}

	public function test_track_transaction_with_zero_and_fractional_amounts(): void {
		$this->capture_queued_events();

		$this->logger->track_transaction( 'ref-zero', 'USD', 0.0, 'card', 0.0 );
		$this->logger->track_transaction( 'ref-frac', 'GHS', 99.99, 'mobilemoney', 1.50 );

		$this->assertSame( 0.0, $this->queued[0]['data']['amount'] );
		$this->assertSame( 0.0, $this->queued[0]['data']['fee'] );
		$this->assertSame( 99.99, $this->queued[1]['data']['amount'] );
		$this->assertSame( 1.50, $this->queued[1]['data']['fee'] );
	}

	public function test_track_transaction_queues_one_event_per_call(): void {
		$this->capture_queued_events();

		foreach ( array( 'NGN', 'USD', 'GBP', 'KES', 'ZAR' ) as $currency ) {
			$this->logger->track_transaction( 'ref-' . $currency, $currency, 100.0, 'card', 1.0 );
		}

		$this->assertSame(
			array( 'NGN', 'USD', 'GBP', 'KES', 'ZAR' ),
			array_column( array_column( $this->queued, 'data' ), 'currency' )
		);
	}

	public function test_track_transaction_continues_trace_from_request_sent(): void {
		$this->capture_queued_events();

		$this->logger->track_request_sent( 'card', 'ref-123', '/v3/charges' );
		$this->logger->track_transaction( 'ref-123', 'NGN', 100.0, 'card', 1.0 );

		$request     = $this->queued[0]['data']['trace_context'];
		$transaction = $this->queued[1]['data']['trace_context'];
		$this->assertSame( $request['trace_id'], $transaction['trace_id'] );
		$this->assertSame( $request['span_id'], $transaction['parent_span_id'] );
	}

	// =========================================================================
	// track_error()
	// =========================================================================

	public function test_track_error_payload_contains_required_fields(): void {
		update_option( 'woocommerce_rave_settings', array( 'app_id' => 'app-123' ) );
		$this->capture_queued_events();

		$this->logger->track_error( 'PAYMENT_FAILED', 'Card declined by issuer' );

		$this->assertSame( 'app.error', $this->queued[0]['name'] );
		$data = $this->last_queued_data();
		$this->assertSame( 'app-123', $data['app_id'] );
		$this->assertSame( Flutterwave_Signoz_Logger::LIBRARY, $data['library'] );
		$this->assertSame( 'PAYMENT_FAILED', $data['error_code'] );
		$this->assertSame( 'Card declined by issuer', $data['error_message'] );
		$this->assertArrayHasKey( 'library_version', $data );
		$this->assertArrayNotHasKey( 'reference', $data );
		$this->assertArrayNotHasKey( 'error_stacktrace', $data );
	}

	public function test_track_error_includes_reference_and_stack_trace_when_given(): void {
		$this->capture_queued_events();

		$this->logger->track_error( 'ERR', 'msg', 'ref 123', null, "#0 foo()\n#1 bar()" );

		$data = $this->last_queued_data();
		$this->assertSame( 'ref-123', $data['reference'] );
		$this->assertSame( "#0 foo()\n#1 bar()", $data['error_stacktrace'] );
	}

	public function test_track_error_truncates_message_and_stack_trace(): void {
		$this->capture_queued_events();

		$this->logger->track_error(
			'ERR',
			str_repeat( 'é', Flutterwave_Signoz_Logger::ERROR_MESSAGE_MAX_LENGTH + 10 ),
			'',
			null,
			str_repeat( 'x', Flutterwave_Signoz_Logger::ERROR_STACKTRACE_MAX_LENGTH + 10 )
		);

		$data = $this->last_queued_data();
		$this->assertSame( Flutterwave_Signoz_Logger::ERROR_MESSAGE_MAX_LENGTH, mb_strlen( $data['error_message'] ) );
		$this->assertSame( Flutterwave_Signoz_Logger::ERROR_STACKTRACE_MAX_LENGTH, strlen( $data['error_stacktrace'] ) );
	}

	public function test_track_error_with_empty_strings(): void {
		$this->capture_queued_events();

		$this->logger->track_error( '', '' );

		$data = $this->last_queued_data();
		$this->assertSame( '', $data['error_code'] );
		$this->assertSame( '', $data['error_message'] );
	}

	public function test_track_error_preserves_special_characters(): void {
		$this->capture_queued_events();

		$code    = 'ERR_<script>';
		$message = 'Failure: "card" & \'token\' not valid <EOF>';
		$this->logger->track_error( $code, $message );

		$data = $this->last_queued_data();
		$this->assertSame( $code, $data['error_code'] );
		$this->assertSame( $message, $data['error_message'] );
	}

	public function test_track_error_uses_explicit_trace_context_as_parent(): void {
		$this->capture_queued_events();
		$parent = array(
			'trace_id' => str_repeat( 'a', 32 ),
			'span_id'  => str_repeat( 'b', 16 ),
		);

		$this->logger->track_error( 'ERR', 'msg', 'ref-1', $parent );

		$context = $this->last_queued_data()['trace_context'];
		$this->assertSame( $parent['trace_id'], $context['trace_id'] );
		$this->assertSame( $parent['span_id'], $context['parent_span_id'] );
		$this->assertNotSame( $parent['span_id'], $context['span_id'] );
	}

	public function test_queued_timestamp_matches_iso8601_format(): void {
		$this->capture_queued_events();

		$this->logger->track_error( 'ERR', 'msg' );

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.000Z$/',
			$this->queued[0]['timestamp']
		);
	}

	// =========================================================================
	// Trace context registry
	// =========================================================================

	public function test_trace_context_round_trips_and_survives_a_new_instance(): void {
		$context = array(
			'trace_id' => str_repeat( 'c', 32 ),
			'span_id'  => str_repeat( 'd', 16 ),
		);

		$this->logger->set_trace_context_for_reference( 'ref-1', $context );
		$this->reset_singleton();

		$this->assertSame( $context, Flutterwave_Signoz_Logger::instance()->get_trace_context_for_reference( 'ref-1' ) );
	}

	public function test_setting_null_trace_context_clears_it(): void {
		$this->logger->set_trace_context_for_reference( 'ref-1', array( 'trace_id' => str_repeat( 'c', 32 ) ) );
		$this->logger->set_trace_context_for_reference( 'ref-1', null );

		$this->assertNull( $this->logger->get_trace_context_for_reference( 'ref-1' ) );
	}

	public function test_empty_reference_has_no_trace_context(): void {
		$this->logger->set_trace_context_for_reference( '  ', array( 'trace_id' => str_repeat( 'c', 32 ) ) );

		$this->assertNull( $this->logger->get_trace_context_for_reference( '  ' ) );
	}

	public function test_default_traceparent_is_used_as_parent(): void {
		$this->capture_queued_events();
		$trace_id = str_repeat( 'e', 32 );
		$span_id  = str_repeat( 'f', 16 );
		$this->logger->set_default_trace_context( array( 'traceparent' => "00-{$trace_id}-{$span_id}-01" ) );

		$this->logger->track_error( 'ERR', 'msg', 'ref-1' );

		$context = $this->last_queued_data()['trace_context'];
		$this->assertSame( $trace_id, $context['trace_id'] );
		$this->assertSame( $span_id, $context['parent_span_id'] );
	}

	// =========================================================================
	// Transport — handle_scheduled_send()
	// =========================================================================

	public function test_send_posts_json_event_to_events_endpoint(): void {
		$this->stub_signoz();
		$data = array( 'error_code' => 'ERR' );

		$this->logger->handle_scheduled_send( 'app.error', $data, '2026-01-01T00:00:00.000Z' );

		$events = $this->requests_to( '/events' );
		$this->assertCount( 1, $events );
		$this->assertSame(
			array(
				'name'      => 'app.error',
				'data'      => $data,
				'timestamp' => '2026-01-01T00:00:00.000Z',
			),
			$events[0]['body']
		);
		$this->assertSame( 'application/json', $events[0]['args']['headers']['Content-Type'] );
		$this->assertTrue( $events[0]['args']['blocking'] );
		$this->assertSame( 2, $events[0]['args']['timeout'] );
	}

	public function test_successful_health_check_is_cached(): void {
		$this->stub_signoz();

		$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );
		$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );

		$this->assertCount( 1, $this->requests_to( Flutterwave_Signoz_Logger::HEALTH_PATH ) );
		$this->assertCount( 2, $this->requests_to( '/events' ) );
	}

	public function test_event_is_dropped_when_health_status_is_not_ok(): void {
		$this->stub_signoz( array( array( 200, '{}' ) ), array( 200, '{"status":"degraded"}' ) );

		$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );

		$this->assertEmpty( $this->requests_to( '/events' ) );
		$this->assertSame( 1, (int) get_transient( Flutterwave_Signoz_Logger::CB_FAILURES_KEY ) );
	}

	public function test_circuit_opens_after_failure_threshold_and_blocks_requests(): void {
		$this->stub_signoz( array( array( 200, '{}' ) ), new WP_Error( 'http_request_failed', 'down' ) );

		for ( $i = 0; $i < Flutterwave_Signoz_Logger::CB_FAILURE_THRESHOLD; $i++ ) {
			$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );
		}

		$this->assertTrue( (bool) get_transient( Flutterwave_Signoz_Logger::CB_OPEN_KEY ) );

		$this->requests = array();
		$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );

		$this->assertEmpty( $this->requests );
	}

	public function test_503_is_retried_up_to_max_attempts(): void {
		$this->stub_signoz( array( array( 503, '' ) ) );

		$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );

		$this->assertCount( Flutterwave_Signoz_Logger::MAX_ATTEMPTS, $this->requests_to( '/events' ) );
		$this->assertSame( 1, (int) get_transient( Flutterwave_Signoz_Logger::CB_FAILURES_KEY ) );
	}

	public function test_503_followed_by_success_stops_retrying(): void {
		$this->stub_signoz( array( array( 503, '' ), array( 200, '{}' ) ) );

		$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );

		$this->assertCount( 2, $this->requests_to( '/events' ) );
		$this->assertFalse( get_transient( Flutterwave_Signoz_Logger::CB_FAILURES_KEY ) );
	}

	public function test_non_503_error_is_not_retried(): void {
		$this->stub_signoz( array( array( 500, '' ) ) );

		$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );

		$this->assertCount( 1, $this->requests_to( '/events' ) );
		$this->assertSame( 1, (int) get_transient( Flutterwave_Signoz_Logger::CB_FAILURES_KEY ) );
	}

	public function test_transport_error_is_not_retried(): void {
		$this->stub_signoz( array( new WP_Error( 'http_request_failed', 'reset' ) ) );

		$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );

		$this->assertCount( 1, $this->requests_to( '/events' ) );
	}

	public function test_success_resets_failure_count(): void {
		set_transient( Flutterwave_Signoz_Logger::CB_FAILURES_KEY, 2, 60 );
		$this->stub_signoz();

		$this->logger->handle_scheduled_send( 'app.error', array(), '2026-01-01T00:00:00.000Z' );

		$this->assertFalse( get_transient( Flutterwave_Signoz_Logger::CB_FAILURES_KEY ) );
	}

	// =========================================================================
	// library_version() / constants
	// =========================================================================

	public function test_library_version_matches_defined_constant_when_available(): void {
		if ( ! defined( 'FLW_WC_VERSION' ) ) {
			$this->markTestSkipped( 'FLW_WC_VERSION constant is not defined in this environment.' );
		}
		$this->capture_queued_events();

		$this->logger->track_error( 'ERR', 'msg' );

		$this->assertSame( FLW_WC_VERSION, $this->last_queued_data()['library_version'] );
	}

	public function test_base_url_constant_points_to_signoz_service(): void {
		$this->assertStringContainsString( 'signozservice-prod', Flutterwave_Signoz_Logger::BASE_URL );
	}

	public function test_library_constant_is_woocommerce(): void {
		$this->assertSame( 'WooCommerce', Flutterwave_Signoz_Logger::LIBRARY );
	}
}
