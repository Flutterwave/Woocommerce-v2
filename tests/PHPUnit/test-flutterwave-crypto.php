<?php
/**
 * Tests for at-rest encryption of the stored payment token.
 *
 * @package Flutterwave\WooCommerce\Tests\phpunit
 */

use Flutterwave\WooCommerce\Util\Flutterwave_Crypto;

/**
 * Tests for Flutterwave_Crypto.
 */
class Test_Flutterwave_Crypto extends \WP_UnitTestCase {

	/**
	 * A representative Flutterwave card token.
	 *
	 * @var string
	 */
	private string $token = 'flw-tkn-01HXYZabcdef1234567890';

	/**
	 * The stored form must not contain the token.
	 */
	public function test_token_is_not_stored_in_the_clear() {
		$stored = Flutterwave_Crypto::encrypt( $this->token );

		$this->assertNotSame( $this->token, $stored );
		$this->assertStringNotContainsString( $this->token, $stored );
	}

	/**
	 * What goes in comes back out.
	 */
	public function test_token_round_trips() {
		$stored = Flutterwave_Crypto::encrypt( $this->token );

		$this->assertSame( $this->token, Flutterwave_Crypto::decrypt( $stored ) );
	}

	/**
	 * Each encryption uses a fresh IV, so the same token does not produce the
	 * same ciphertext twice.
	 */
	public function test_encryption_is_not_deterministic() {
		$this->assertNotSame(
			Flutterwave_Crypto::encrypt( $this->token ),
			Flutterwave_Crypto::encrypt( $this->token )
		);
	}

	/**
	 * Tokens stored by earlier versions are plaintext and must still be readable.
	 */
	public function test_legacy_plaintext_tokens_are_still_readable() {
		$this->assertSame( $this->token, Flutterwave_Crypto::decrypt( $this->token ) );
	}

	/**
	 * A modified ciphertext must fail rather than return anything.
	 */
	public function test_tampered_ciphertext_is_rejected() {
		$stored = Flutterwave_Crypto::encrypt( $this->token );
		$body   = base64_decode( substr( $stored, strlen( 'flwenc:v1:' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		$body[ strlen( $body ) - 1 ] = chr( ord( $body[ strlen( $body ) - 1 ] ) ^ 0xFF );

		$tampered = 'flwenc:v1:' . base64_encode( $body ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$this->assertSame( '', Flutterwave_Crypto::decrypt( $tampered ) );
	}

	/**
	 * A value that is not valid ciphertext must fail closed.
	 */
	public function test_malformed_ciphertext_is_rejected() {
		$this->assertSame( '', Flutterwave_Crypto::decrypt( 'flwenc:v1:not-valid-base64!!!' ) );
	}

	/**
	 * Empty input stays empty rather than becoming a ciphertext of nothing.
	 */
	public function test_empty_values_pass_through() {
		$this->assertSame( '', Flutterwave_Crypto::encrypt( '' ) );
		$this->assertSame( '', Flutterwave_Crypto::decrypt( '' ) );
	}
}
