<?php
/**
 * Class Flutterwave_WC_Gateway_Blocks_SupportTest
 *
 * @package PHPUnit
 */

class Test_Flutterwave_WC_Gateway_Blocks_Support extends \WP_UnitTestCase {
	/**
	 * @var Flutterwave_WC_Gateway_Blocks_Support
	 */
	private $flutterwave_wc_gateway_blocks_support;

	/**
	 * @var array
	 */
	private $settings;

	/**
	 * @var WC_Payment_Gateway
	 */
	private $gateway;

	/**
	 * @var string
	 */
	private string $name;

	/**
	 * @var string[]
	 */
	private $supported_features;

	/**
	 * @var boolean
	 */
	private $is_active;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		update_option(
			'woocommerce_rave_settings',
			[
				'enabled'            => 'yes',
				'go_live'            => 'no',
				'logging_option'     => 'no',
				'secret_hash'        => '581e4231-441e-4730-88bf-8f181897759ea8f1',
				'autocomplete_order' => 'yes',
			]
		);

		$this->flutterwave_wc_gateway_blocks_support = new Flutterwave_WC_Gateway_Blocks_Support();
		$this->settings                              = get_option( 'woocommerce_rave_settings', array() );
		$this->gateway                               = new FLW_WC_Payment_Gateway();
		$this->name                                  = 'rave';
		$this->supported_features                    = $this->gateway->supports;
		$this->is_active                             = $this->gateway->is_available();
	}

	/**
	 * @test
	 */
	public function test_is_active() {
		$this->assertTrue( $this->is_active );
	}

	/**
	 * @test
	 */
	public function test_get_supported_features() {
		$this->assertIsArray( $this->supported_features );
	}

	/**
	 * @test
	 */
	public function test_initialize() {
		$this->assertIsArray( $this->settings );
	}

	/**
	 * @test
	 */
	public function test_get_name() {
		$this->assertEquals( $this->name, $this->flutterwave_wc_gateway_blocks_support->get_name() );
	}
}
