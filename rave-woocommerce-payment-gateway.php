<?php
/**
 * Plugin Name: Flutterwave WooCommerce
 * Plugin URI: https://developer.flutterwave.com/
 * Description: Official WooCommerce payment gateway for Flutterwave.
 * Version: 3.2.0
 * Author: Flutterwave Developers
 * Author URI: http://flutterwave.com/us
 * License: MIT License
 * Text Domain: rave-woocommerce-payment-gateway
 * Domain Path: i18n/languages
 * WC requires at least:   9.6.0
 * WC tested up to:        8.4.0
 * Requires at least:      5.6
 * Requires PHP:           7.4
 *
 * @package Flutterwave WooCommerce
 **/

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'FLW_WC_PLUGIN_FILE' ) ) {
	define( 'FLW_WC_PLUGIN_FILE', __FILE__ );
}

/**
 * Initialize Flutterwave WooCommerce payment gateway.
 */
function flutterwave_bootstrap() {
	if ( ! class_exists( 'Flutterwave' ) ) {
		include_once dirname( FLW_WC_PLUGIN_FILE ) . '/includes/class-flutterwave.php';
		// Global for backwards compatibility.
		$GLOBALS['flutterwave'] = Flutterwave::instance();
	}
}

add_action( 'plugins_loaded', 'flutterwave_bootstrap', 99 );

/**
 * Register the Flutterwave payment gateway for WooCommerce Blocks.
 *
 * @return void
 */
function flutterwave_woocommerce_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( FLW_WC_PLUGIN_FILE ) . '/includes/blocks/class-flutterwave-wc-gateway-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {

				$payment_method_registry->register( new Flutterwave_WC_Gateway_Blocks_Support() );
			}
		);
	}
}

// add woocommerce block support.
add_action( 'woocommerce_blocks_loaded', 'flutterwave_woocommerce_blocks_support' );


add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Add the Settings link to the plugin
 *
 * @param  array $links Existing links on the plugin page.
 *
 * @return array Existing links with our settings link added
 */
function flw_plugin_action_links( array $links ): array {

	$rave_settings_url = esc_url( get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=rave' ) );
	array_unshift( $links, "<a title='Flutterwave Settings Page' href='$rave_settings_url'>Settings</a>" );

	return $links;

}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'flw_plugin_action_links' );

add_filter('woocommerce_gateway_title', 'flutterwave_title', 10, 2);
add_filter('woocommerce_gateway_icon', 'flutterwave_gateway_icon', 10, 2);

function flutterwave_title($title, $gateway_id) {

    if($gateway_id !== 'rave') {
        return $title;
    }

    if (wp_is_mobile()) {
        ob_start();
        ?>
        <div class="rave-front-checkout" style="display: flex; flex-direction: row; justify-content: left; align-items: center; padding: 24px; background: #FFF; border-radius: 4px;">
            <p style="margin: 10px 16px 10px 16px; font-size: 20px; font-weight: 500; line-height: 100%;">
                Flutterwave
            </p>
            <img src="<?= plugin_dir_url(__FILE__) . 'assets/img/flutterwave-full.svg' ?>" alt="flutterwave" style="height: 40px;" />
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start();
    ?>
    <label class="payment_method_rave">
            Flutterwave
        <img decoding="async" src="<?= plugin_dir_url(__FILE__) . 'assets/img/rave.png' ?>" alt="flutterwave" style="height: 40px;" />
    </label>
    <div class="payment_box payment_method_rave" style="display:none;">
        <p>Powered by Flutterwave: Accepts Mastercard, Visa, Verve, Discover, AMEX, Diners Club and Union Pay.</p>
    </div>
    <?php
    return ob_get_clean();
}

function flutterwave_gateway_icon($icon, $gateway_id) {
    return ($gateway_id === 'rave') ? '' : $icon;
}








