<?php
/**
 * Tests for the payment callback guard.
 *
 * The gateway return URL is a public, unauthenticated endpoint, so these tests
 * are the regression net for CWE-288: they assert that the callback cannot be
 * used to reach an order the caller has no claim to.
 *
 * @package Flutterwave\WooCommerce\Tests\phpunit
 */

use Flutterwave\WooCommerce\Util\Flutterwave_Callback;

/**
 * Tests for Flutterwave_Callback.
 */
class Test_Flutterwave_Callback extends \WP_UnitTestCase {

	/**
	 * An order standing in for the victim's.
	 *
	 * @var WC_Order
	 */
	private WC_Order $order;

	/**
	 * A second order, standing in for one the attacker controls.
	 *
	 * @var WC_Order
	 */
	private WC_Order $other_order;

	/**
	 * Sets up two pending orders.
	 */
	public function set_up() {
		parent::set_up();

		$this->order = new WC_Order();
		$this->order->set_currency( 'NGN' );
		$this->order->set_total( 1000 );
		$this->order->set_payment_method( 'rave' );
		$this->order->set_status( 'pending' );
		$this->order->save();

		$this->other_order = new WC_Order();
		$this->other_order->set_currency( 'NGN' );
		$this->other_order->set_total( 1000 );
		$this->other_order->set_payment_method( 'rave' );
		$this->other_order->set_status( 'pending' );
		$this->other_order->save();
	}

	/**
	 * Clears request superglobals between tests.
	 */
	public function tear_down() {
		$_GET  = array();
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Populates the request the callback will read.
	 *
	 * @param array $params Query parameters.
	 */
	private function request( array $params ) {
		$_GET  = $params;
		$_POST = array();
	}

	/**
	 * A callback with no order key must not resolve any order.
	 */
	public function test_callback_without_an_order_key_is_rejected() {
		$this->request( array( 'order_id' => (string) $this->order->get_id() ) );

		$this->assertNull( Flutterwave_Callback::resolve_order( '' ) );
	}

	/**
	 * A guessed order key must not resolve the order.
	 */
	public function test_callback_with_a_wrong_order_key_is_rejected() {
		$this->request(
			array(
				'order_id'      => (string) $this->order->get_id(),
				'flw_order_key' => 'wc_order_not_the_real_key',
			)
		);

		$this->assertNull( Flutterwave_Callback::resolve_order( '' ) );
	}

	/**
	 * Holding one order's key must not grant access to a different order.
	 *
	 * This is the arbitrary-order-targeting case from the CVE.
	 */
	public function test_one_orders_key_does_not_unlock_another_order() {
		$this->request(
			array(
				'order_id'      => (string) $this->order->get_id(),
				'flw_order_key' => $this->other_order->get_order_key(),
			)
		);

		$this->assertNull( Flutterwave_Callback::resolve_order( '' ) );
	}

	/**
	 * An order id that does not exist must not resolve.
	 */
	public function test_unknown_order_is_rejected() {
		$this->request(
			array(
				'order_id'      => '99999999',
				'flw_order_key' => $this->order->get_order_key(),
			)
		);

		$this->assertNull( Flutterwave_Callback::resolve_order( '' ) );
	}

	/**
	 * The customer returning from Flutterwave still gets through.
	 */
	public function test_correct_order_key_resolves_the_order() {
		$this->request(
			array(
				'order_id'      => (string) $this->order->get_id(),
				'flw_order_key' => $this->order->get_order_key(),
			)
		);

		$resolved = Flutterwave_Callback::resolve_order( '' );

		$this->assertInstanceOf( WC_Order::class, $resolved );
		$this->assertSame( $this->order->get_id(), $resolved->get_id() );
	}

	/**
	 * The order id can still be derived from the transaction reference.
	 */
	public function test_order_id_can_be_derived_from_the_transaction_reference() {
		$txn_ref = 'WOOC_' . $this->order->get_id() . '_1700000000';

		$this->request( array( 'flw_order_key' => $this->order->get_order_key() ) );

		$resolved = Flutterwave_Callback::resolve_order( $txn_ref );

		$this->assertInstanceOf( WC_Order::class, $resolved );
		$this->assertSame( $this->order->get_id(), $resolved->get_id() );
	}

	/**
	 * A reference issued for this order is accepted.
	 */
	public function test_issued_transaction_reference_is_accepted() {
		$txn_ref = 'WOOC_' . $this->order->get_id() . '_1700000000';
		Flutterwave_Callback::record_txn_ref( $this->order, $txn_ref );

		$this->assertTrue( Flutterwave_Callback::txn_ref_belongs_to_order( $this->order, $txn_ref ) );
	}

	/**
	 * Several attempts on one order all stay valid.
	 */
	public function test_every_reference_issued_for_an_order_stays_valid() {
		$first  = 'WOOC_' . $this->order->get_id() . '_1700000000';
		$second = 'WOOC_' . $this->order->get_id() . '_1700000500';

		Flutterwave_Callback::record_txn_ref( $this->order, $first );
		Flutterwave_Callback::record_txn_ref( $this->order, $second );

		$this->assertTrue( Flutterwave_Callback::txn_ref_belongs_to_order( $this->order, $first ) );
		$this->assertTrue( Flutterwave_Callback::txn_ref_belongs_to_order( $this->order, $second ) );
	}

	/**
	 * A reference issued for another order cannot be replayed against this one.
	 */
	public function test_reference_from_another_order_is_rejected() {
		Flutterwave_Callback::record_txn_ref( $this->order, 'WOOC_' . $this->order->get_id() . '_1700000000' );
		Flutterwave_Callback::record_txn_ref( $this->other_order, 'WOOC_' . $this->other_order->get_id() . '_1700000001' );

		$this->assertFalse(
			Flutterwave_Callback::txn_ref_belongs_to_order(
				$this->other_order,
				'WOOC_' . $this->order->get_id() . '_1700000000'
			)
		);
	}

	/**
	 * An order with no recorded reference still accepts one issued in its name.
	 */
	public function test_unrecorded_order_accepts_a_reference_in_its_own_name() {
		$this->assertTrue(
			Flutterwave_Callback::txn_ref_belongs_to_order(
				$this->order,
				'WOOC_' . $this->order->get_id() . '_1700000000'
			)
		);
	}

	/**
	 * An order with no recorded reference must not accept another order's reference.
	 */
	public function test_unrecorded_order_rejects_a_reference_from_another_order() {
		$this->assertFalse(
			Flutterwave_Callback::txn_ref_belongs_to_order(
				$this->order,
				'WOOC_' . $this->other_order->get_id() . '_1700000000'
			)
		);
	}

	/**
	 * An order with no recorded reference must not accept an arbitrary string.
	 *
	 * @param string $txn_ref Transaction reference.
	 *
	 * @dataProvider data_malformed_references
	 */
	public function test_unrecorded_order_rejects_malformed_references( string $txn_ref ) {
		$this->assertFalse( Flutterwave_Callback::txn_ref_belongs_to_order( $this->order, $txn_ref ) );
	}

	/**
	 * References that do not follow the WOOC_<order id>_<suffix> shape.
	 *
	 * @return array
	 */
	public function data_malformed_references(): array {
		return array(
			'no prefix'      => array( 'anything' ),
			'wrong prefix'   => array( 'ABCD_1_1700000000' ),
			'no suffix'      => array( 'WOOC_1' ),
			'empty suffix'   => array( 'WOOC_1_' ),
			'non-numeric id' => array( 'WOOC_x_1700000000' ),
		);
	}

	/**
	 * Builds webhook event data for a charge.
	 *
	 * @param WC_Order $order  The order the charge is for.
	 * @param string   $status Charge status reported by the webhook.
	 *
	 * @return object
	 */
	private function charge_event( WC_Order $order, string $status ): object {
		return (object) array(
			'id'     => 123456,
			'tx_ref' => 'WOOC_' . $order->get_id() . '_1700000000',
			'status' => $status,
		);
	}

	/**
	 * A successful charge on a cancelled Flutterwave order may reopen it.
	 */
	public function test_successful_charge_recovers_a_cancelled_order() {
		$this->order->set_status( 'cancelled' );
		$this->order->save();

		$this->assertTrue(
			Flutterwave_Callback::may_recover_cancelled_order( $this->order, $this->charge_event( $this->order, 'successful' ), 'rave' )
		);
	}

	/**
	 * A charge that did not succeed must leave a cancelled order alone.
	 */
	public function test_unsuccessful_charge_does_not_recover_a_cancelled_order() {
		$this->order->set_status( 'cancelled' );
		$this->order->save();

		$this->assertFalse(
			Flutterwave_Callback::may_recover_cancelled_order( $this->order, $this->charge_event( $this->order, 'failed' ), 'rave' )
		);
	}

	/**
	 * Only cancelled orders are candidates for recovery.
	 */
	public function test_recovery_only_applies_to_cancelled_orders() {
		$this->order->set_status( 'refunded' );
		$this->order->save();

		$this->assertFalse(
			Flutterwave_Callback::may_recover_cancelled_order( $this->order, $this->charge_event( $this->order, 'successful' ), 'rave' )
		);
	}

	/**
	 * A cancelled order paid with some other gateway must not be reopened.
	 */
	public function test_recovery_requires_the_flutterwave_payment_method() {
		$this->order->set_payment_method( 'bacs' );
		$this->order->set_status( 'cancelled' );
		$this->order->save();

		$this->assertFalse(
			Flutterwave_Callback::may_recover_cancelled_order( $this->order, $this->charge_event( $this->order, 'successful' ), 'rave' )
		);
	}

	/**
	 * A charge made under another order's reference must not reopen this one.
	 */
	public function test_recovery_requires_the_reference_to_belong_to_the_order() {
		Flutterwave_Callback::record_txn_ref( $this->order, 'WOOC_' . $this->order->get_id() . '_1700000000' );
		$this->order->set_status( 'cancelled' );
		$this->order->save();

		$this->assertFalse(
			Flutterwave_Callback::may_recover_cancelled_order( $this->order, $this->charge_event( $this->other_order, 'successful' ), 'rave' )
		);
	}

	/**
	 * Orders that have been settled are off limits to the callback.
	 *
	 * @param string $status Order status.
	 *
	 * @dataProvider data_settled_statuses
	 */
	public function test_settled_orders_are_not_awaiting_payment( string $status ) {
		$this->order->set_status( $status );
		$this->order->save();

		$this->assertFalse( Flutterwave_Callback::order_awaiting_payment( $this->order ) );
	}

	/**
	 * Statuses a callback must never transition away from.
	 *
	 * @return array
	 */
	public function data_settled_statuses(): array {
		return array(
			'processing' => array( 'processing' ),
			'completed'  => array( 'completed' ),
			'cancelled'  => array( 'cancelled' ),
			'refunded'   => array( 'refunded' ),
		);
	}

	/**
	 * Orders still waiting to be paid remain actionable.
	 *
	 * @param string $status Order status.
	 *
	 * @dataProvider data_open_statuses
	 */
	public function test_open_orders_are_awaiting_payment( string $status ) {
		$this->order->set_status( $status );
		$this->order->save();

		$this->assertTrue( Flutterwave_Callback::order_awaiting_payment( $this->order ) );
	}

	/**
	 * Statuses a callback is allowed to act on.
	 *
	 * @return array
	 */
	public function data_open_statuses(): array {
		return array(
			'pending' => array( 'pending' ),
			'failed'  => array( 'failed' ),
			'on-hold' => array( 'on-hold' ),
		);
	}

	/**
	 * The order key has to survive the round trip to Flutterwave, including on
	 * sites whose API request URL already carries a query string.
	 */
	public function test_callback_url_carries_the_order_key() {
		$url = Flutterwave_Callback::build_url( $this->order );

		$this->assertStringContainsString( 'flw_order_key=' . $this->order->get_order_key(), $url );
		$this->assertStringContainsString( 'order_id=' . $this->order->get_id(), $url );
	}

	/**
	 * Building the URL must not drop the wc-api parameter on sites using plain
	 * permalinks - that regression is why the old nonce check was made permissive.
	 */
	public function test_callback_url_preserves_existing_query_parameters() {
		add_filter( 'pre_option_permalink_structure', '__return_empty_string' );

		$url = Flutterwave_Callback::build_url( $this->order );

		remove_filter( 'pre_option_permalink_structure', '__return_empty_string' );

		$this->assertStringContainsString( 'wc-api=FLW_WC_Payment_Gateway', $url );
		$this->assertStringContainsString( 'flw_order_key=' . $this->order->get_order_key(), $url );
	}
}
