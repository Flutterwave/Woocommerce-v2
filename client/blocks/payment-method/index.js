/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import {
	getBlocksConfiguration,
} from 'wcflutterwave/blocks/utils';

/**
 * Content component
 */
const Content = () => {
	return <div>{ decodeEntities( getBlocksConfiguration()?.description || __('You may be redirected to a secure page to complete your payment.', 'woocommerce-rave') ) }</div>;
};

const FLW_ASSETS = getBlocksConfiguration()?.asset_url ?? null;


const paymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: (
		<div style={{ display: 'flex', flexDirection: 'row', rowGap: '.5em'}}>
			<img
			src={ `${ FLW_ASSETS }/img/flutterwave-full.svg` }
			alt={ decodeEntities(
				getBlocksConfiguration()?.title || __( 'Flutterwave', 'woocommerce-rave' )
			) }
			/>
			<b><h4>Flutterwave</h4></b>
		</div>
	),
	placeOrderButtonLabel: __(
		'Proceed to Flutterwave',
		'woocommerce-rave'
	),
	ariaLabel: decodeEntities(
		getBlocksConfiguration()?.title ||
		__( 'Payment via Flutterwave', 'woocommerce-rave' )
	),
	canMakePayment: () => true,
	content: <Content />,
	edit: <Content />,
	paymentMethodId: PAYMENT_METHOD_NAME,
	supports: {
		features:  getBlocksConfiguration()?.supports ?? [],
	},
}

export default paymentMethod;