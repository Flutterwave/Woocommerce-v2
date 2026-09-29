<?php
/**
 * Tests for the admin settings model that backs the onboarding wizard.
 *
 * @package Flutterwave\WooCommerce\Tests\phpunit
 */

use Flutterwave\WooCommerce\Admin\Flutterwave_Settings;

/**
 * Tests for Flutterwave_Settings.
 */
class Test_Flutterwave_Admin_Settings extends \WP_UnitTestCase {

	/**
	 * Start each test from a clean option so defaults are predictable.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Flutterwave_Settings::OPTION_KEY );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		delete_option( Flutterwave_Settings::OPTION_KEY );
		parent::tear_down();
	}

	/**
	 * A method selected in the UI produces the tokens Flutterwave expects.
	 */
	public function test_selected_methods_become_payment_options() {
		$this->assertSame( 'card', Flutterwave_Settings::option_from_methods( array( 'card' ) ) );
		$this->assertSame(
			'card,banktransfer,account',
			Flutterwave_Settings::option_from_methods( array( 'card', 'banktransfer' ) )
		);
	}

	/**
	 * Mobile money expands to every country variant Flutterwave splits it into.
	 */
	public function test_mobile_money_expands_to_country_variants() {
		$option = Flutterwave_Settings::option_from_methods( array( 'mobilemoney' ) );

		$this->assertStringContainsString( 'mobilemoneyghana', $option );
		$this->assertStringContainsString( 'mobilemoneyrwanda', $option );
		$this->assertStringContainsString( 'mpesa', $option );
	}

	/**
	 * Reading a stored option back lights up the right checkboxes.
	 */
	public function test_payment_options_map_back_to_methods() {
		$methods = Flutterwave_Settings::methods_from_option( 'card,mpesa,applepay' );

		$this->assertContains( 'card', $methods );
		$this->assertContains( 'mobilemoney', $methods );
		$this->assertContains( 'applepay', $methods );
		$this->assertNotContains( 'opay', $methods );
	}

	/**
	 * The legacy "All" default keeps working when read by the new UI.
	 */
	public function test_legacy_default_option_string_round_trips() {
		$legacy  = 'card,ussd,account,mpesa,banktransfer,mobilemoneyghana,mobilemoneyfranco,mobilemoneyrwanda, mobilemoneyzambia,mobilemoneyuganda,ussd';
		$methods = Flutterwave_Settings::methods_from_option( $legacy );

		$this->assertContains( 'card', $methods );
		$this->assertContains( 'banktransfer', $methods );
		$this->assertContains( 'mobilemoney', $methods );

		// USSD has no checkbox, so it must survive as an advanced token.
		$this->assertContains( 'ussd', Flutterwave_Settings::advanced_from_option( $legacy ) );
	}

	/**
	 * Saving methods must not silently drop options the new UI does not show.
	 */
	public function test_advanced_tokens_survive_a_save() {
		update_option(
			Flutterwave_Settings::OPTION_KEY,
			array_merge(
				Flutterwave_Settings::defaults(),
				array( 'payment_options' => 'card,ussd,qr' )
			)
		);

		Flutterwave_Settings::update( array( 'paymentMethods' => array( 'card', 'applepay' ) ) );

		$stored = get_option( Flutterwave_Settings::OPTION_KEY );

		$this->assertStringContainsString( 'ussd', $stored['payment_options'] );
		$this->assertStringContainsString( 'qr', $stored['payment_options'] );
		$this->assertStringContainsString( 'applepay', $stored['payment_options'] );
	}

	/**
	 * A partial update leaves untouched keys alone, so one wizard step cannot
	 * clobber another.
	 */
	public function test_partial_update_preserves_other_keys() {
		Flutterwave_Settings::update(
			array(
				'title'         => 'Minna Store',
				'testPublicKey' => 'FLWPUBK_TEST-abc',
			)
		);

		Flutterwave_Settings::update( array( 'description' => 'Buy all your ultimate online wears.' ) );

		$stored = get_option( Flutterwave_Settings::OPTION_KEY );

		$this->assertSame( 'Minna Store', $stored['title'] );
		$this->assertSame( 'FLWPUBK_TEST-abc', $stored['test_public_key'] );
		$this->assertSame( 'Buy all your ultimate online wears.', $stored['description'] );
	}

	/**
	 * Booleans from the UI become the yes/no strings WooCommerce stores.
	 */
	public function test_booleans_are_stored_as_yes_no() {
		Flutterwave_Settings::update(
			array(
				'goLive'            => true,
				'autocompleteOrder' => false,
			)
		);

		$stored = get_option( Flutterwave_Settings::OPTION_KEY );

		$this->assertSame( 'yes', $stored['go_live'] );
		$this->assertSame( 'no', $stored['autocomplete_order'] );
	}

	/**
	 * Saving never leaves the secret hash blank, so webhook verification is
	 * never comparing against an empty string.
	 */
	public function test_secret_hash_is_generated_when_missing() {
		Flutterwave_Settings::update( array( 'title' => 'Minna Store' ) );

		$stored = get_option( Flutterwave_Settings::OPTION_KEY );

		$this->assertNotEmpty( $stored['secret_hash'] );
		$this->assertSame( 64, strlen( $stored['secret_hash'] ) );
	}

	/**
	 * An explicitly set secret hash is never overwritten on later saves.
	 */
	public function test_existing_secret_hash_is_preserved() {
		Flutterwave_Settings::update( array( 'secretHash' => 'my-dashboard-hash' ) );
		Flutterwave_Settings::update( array( 'title' => 'Minna Store' ) );

		$stored = get_option( Flutterwave_Settings::OPTION_KEY );

		$this->assertSame( 'my-dashboard-hash', $stored['secret_hash'] );
	}

	/**
	 * Loading the admin app on a fresh install stores a random hash, so the
	 * API & Webhook step shows a real value before anything is saved.
	 */
	public function test_payload_generates_and_stores_secret_hash_on_fresh_install() {
		$payload = Flutterwave_Settings::to_payload();
		$stored  = get_option( Flutterwave_Settings::OPTION_KEY );

		$this->assertSame( 64, strlen( $payload['secretHash'] ) );
		$this->assertSame( $stored['secret_hash'], $payload['secretHash'] );
		$this->assertNotSame( Flutterwave_Settings::LEGACY_SECRET_HASH, $payload['secretHash'] );
		$this->assertFalse( $payload['secretHashIsLegacy'] );
	}

	/**
	 * Two installs never end up sharing a generated hash.
	 */
	public function test_generated_secret_hashes_are_unique() {
		$this->assertNotSame( Flutterwave_Settings::generate_secret_hash(), Flutterwave_Settings::generate_secret_hash() );
	}

	/**
	 * The constant matches the value older versions shipped as the default.
	 */
	public function test_legacy_secret_hash_constant_matches_old_default() {
		$this->assertSame( hash( 'sha256', 'Rave-Secret-Hash' ), Flutterwave_Settings::LEGACY_SECRET_HASH );
	}

	/**
	 * A store still on the old default is flagged, but the stored hash is not
	 * swapped out on an unrelated save: the merchant has to generate the new
	 * one on the API & Webhook tab, where they can copy it to their dashboard.
	 */
	public function test_legacy_secret_hash_is_flagged_but_kept() {
		update_option( Flutterwave_Settings::OPTION_KEY, array( 'secret_hash' => Flutterwave_Settings::LEGACY_SECRET_HASH ) );

		$payload = Flutterwave_Settings::update( array( 'title' => 'Minna Store' ) );

		$this->assertTrue( $payload['secretHashIsLegacy'] );
		$this->assertSame( Flutterwave_Settings::LEGACY_SECRET_HASH, get_option( Flutterwave_Settings::OPTION_KEY )['secret_hash'] );
	}

	/**
	 * Webhooks are only authenticated against a hash the merchant has set:
	 * blank and the old default are both refused.
	 */
	public function test_only_generated_or_custom_hashes_are_usable_for_webhooks() {
		$this->assertFalse( Flutterwave_Settings::is_usable_secret_hash( '' ) );
		$this->assertFalse( Flutterwave_Settings::is_usable_secret_hash( '   ' ) );
		$this->assertFalse( Flutterwave_Settings::is_usable_secret_hash( Flutterwave_Settings::LEGACY_SECRET_HASH ) );
		$this->assertTrue( Flutterwave_Settings::is_usable_secret_hash( Flutterwave_Settings::generate_secret_hash() ) );
		$this->assertTrue( Flutterwave_Settings::is_usable_secret_hash( 'my-dashboard-hash' ) );
	}

	/**
	 * Explicitly saving the old default replaces it with a generated hash.
	 */
	public function test_saving_legacy_secret_hash_generates_a_new_one() {
		$payload = Flutterwave_Settings::update( array( 'secretHash' => Flutterwave_Settings::LEGACY_SECRET_HASH ) );

		$this->assertNotSame( Flutterwave_Settings::LEGACY_SECRET_HASH, $payload['secretHash'] );
		$this->assertSame( 64, strlen( $payload['secretHash'] ) );
		$this->assertFalse( $payload['secretHashIsLegacy'] );
	}

	/**
	 * The legacy gateway form no longer falls back to a shared, public hash.
	 */
	public function test_gateway_form_has_no_default_secret_hash() {
		$gateway = new \FLW_WC_Payment_Gateway();

		$this->assertSame( '', $gateway->form_fields['secret_hash']['default'] );
	}

	/**
	 * Admins on the old default see a warning linking to the API & Webhook tab.
	 */
	public function test_legacy_secret_hash_shows_admin_notice() {
		update_option( Flutterwave_Settings::OPTION_KEY, array( 'secret_hash' => Flutterwave_Settings::LEGACY_SECRET_HASH ) );

		$output = $this->capture_notice();

		$this->assertStringContainsString( 'flw-legacy-secret-hash-notice', $output );
		$this->assertStringContainsString( 'tab=api', $output );
	}

	/**
	 * No notice once the store has its own hash.
	 */
	public function test_custom_secret_hash_shows_no_admin_notice() {
		update_option( Flutterwave_Settings::OPTION_KEY, array( 'secret_hash' => Flutterwave_Settings::generate_secret_hash() ) );

		$this->assertSame( '', $this->capture_notice() );
	}

	/**
	 * Users who cannot fix the setting are not shown the notice.
	 */
	public function test_legacy_secret_hash_notice_hidden_from_non_managers() {
		update_option( Flutterwave_Settings::OPTION_KEY, array( 'secret_hash' => Flutterwave_Settings::LEGACY_SECRET_HASH ) );

		$this->assertSame( '', $this->capture_notice( 'subscriber' ) );
	}

	/**
	 * Render the secret hash notice as a user with the given role.
	 *
	 * @param string $role User role.
	 *
	 * @return string The notice markup, or '' when none is shown.
	 */
	private function capture_notice( string $role = 'administrator' ): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

		ob_start();
		\Flutterwave\WooCommerce\Admin\Flutterwave_Admin_Page::instance()->legacy_secret_hash_notice();

		return (string) ob_get_clean();
	}

	/**
	 * A fresh install sees the welcome screen.
	 */
	public function test_fresh_install_is_not_onboarded() {
		$this->assertFalse( Flutterwave_Settings::is_onboarded() );
	}

	/**
	 * Merchants upgrading with keys already saved skip the wizard, even though
	 * they never ran it.
	 */
	public function test_existing_merchant_with_keys_is_treated_as_onboarded() {
		update_option(
			Flutterwave_Settings::OPTION_KEY,
			array_merge(
				Flutterwave_Settings::defaults(),
				array( 'test_public_key' => 'FLWPUBK_TEST-abc' )
			)
		);

		$this->assertTrue( Flutterwave_Settings::is_onboarded() );
		$this->assertTrue( Flutterwave_Settings::to_payload()['onboardingComplete'] );
	}

	/**
	 * The payload exposes the webhook URL the API & webhook step asks merchants
	 * to copy.
	 */
	public function test_payload_exposes_webhook_url() {
		$payload = Flutterwave_Settings::to_payload();

		$this->assertArrayHasKey( 'webhookUrl', $payload );
		$this->assertStringContainsString( 'Flw_WC_Payment_Webhook', $payload['webhookUrl'] );
	}
}
