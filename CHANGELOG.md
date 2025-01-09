# Changelog
## 2.3.6 | 01-09-2025
Bug Fixes and Webhook Handler improvements.
### Version Changes
- [FIXED] Dynamic Adjustment to Custom Permalink Set by Merchant.
- [FIXED] Redirect Payment option return a Payment Mismatch Error.
- [FIXED] Reject Invalid Order Reference hooks.
## 2.3.5 | 01-01-2024
Added Support for WooCommerce HPOS.
### Version Changes
- [ADDED] Support for WooCommerce HPOS.
- [FIXED] WooCommerce Blocks Compatibility Issues with WooCommerce 7.0 to 6.9.1.
- [FIXED] Payment Option alignment on WooCommerce Checkout Block.
- [FIXED] Handle Cancel Event on redirect Checkout option.
## 2.3.4 | 01-11-2023
Handle Webhook Acknowledgement.
### Version Changes
- [FIXED] Aknowledge hooks sent to prevent unsuccessful webhook delivery.

## 2.3.3 | 24-07-2023
Update Order Notes and Confirmation Alerts.
### Version Changes
- [FIXED] Order Note Details and Confirmation Alerts.

## 2.3.2 | 25-04-2023
Wordpress requirement changes and updates.
### Version changes
- [ADDED] Add support for WooCommerce Blocks.
- [CHANGED] Updated Payment Gateway Checkout Process for better user experience.
- [CHANGED] Updated Payment Tokenization for saved cards feature.
- [CHANGED] Support for Flutterwave V3 API.
- [CHANGED] Updated WooCommerce Subscription Integration.
- [REMOVED] Remove outdated PHP Software Development Kit (SDK) from the plugin.


## 2.3.0 | 25-10-2022
Routine maintenance. Resolved bug on Mobile money.
### Version changes
- [FIXED] Handled MobileMoney Payment Handler Error.


## 2.2.9 | 23-09-2022
Bug fix
### Version changes
- [FIXED] PHP 8 support for v3 Webhook Handler.


## 2.2.8 | 20-06-2022
Bugfixes
### Version changes
- [CHANGED] Switch to WC-Logger class for logging.
- [FIXED] Fix processing function error on Woocommerce Subscription.


## 2.2.7 | 30-05-2022
Bugfixes
### Version changes
- [FIXED] Fix redirect to order reciept page in redirect method.
- [FIXED] Add support for PHP 8.0.



## 2.2.0 | 06-07-2018
Updated base URL for API calls and added support for recurring payment
### Version changes
- [ADDED] Add support for Woocommerce recurring to allow merchants collect recurring payments.
- [CHANGED] Update base URL to support both transactions on both test and live mode.



## 2.0.0
New payment currencies
### Version changes
- [ADDED] Add support for new currencies (ZMW, UGX, RWF, TZS, SLL).



## 1.0.1
Bugfixes
### Version changes
- [ADDED] Add redirect style with admin toogle for redirect or popup payment style.
- [CHANGED] Add custom gateway name.
- [FIXED] fix bugs for country.


## 1.0.0
Initial release
### Version changes
- [ADDED] First plugin release.
