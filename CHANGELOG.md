# Changelog
## 3.3.2 | 08-10-2026
Redesigned onboarding and settings experience.

### Version Changes
- [ADDED] A standalone Flutterwave admin screen under WooCommerce, replacing the WooCommerce gateway settings section as the place merchants configure the plugin.
- [ADDED] A three-step setup wizard for new installs — general details, API keys and webhook, then payment methods — followed by a confirmation screen. Each step saves on its own, so a merchant who drops out halfway keeps what they entered.
- [ADDED] A tabbed settings screen (General / API & Webhook / Payment Methods) for stores that are already configured. Merchants upgrading with keys already saved skip the wizard.
- [ADDED] Payment methods are now individual checkboxes (Cards, Stablecoin, Bank Transfer, Mobile Money, Apple Pay, Google Pay, Opay) instead of a single-choice dropdown.
- [ADDED] REST endpoints under `flutterwave/v1` backing the admin app, restricted to `manage_woocommerce`.
- [CHANGED] The plugin's Settings link and the old `wc-settings&tab=checkout&section=rave` URL now lead to the new screen.
- [CHANGED] Settings still persist to `woocommerce_rave_settings`, so the gateway, blocks integration and webhook handling are unchanged.
- [ADDED] The Flutterwave brand fonts — Millik for headings, Moderat for body text, with Inter beneath it — self-hosted from the plugin rather than loaded from Google Fonts and jsDelivr, so wp-admin makes no third-party font requests.
- [CHANGED] Logging and Barter toggles, and the payment options without a checkbox (USSD, QR, NQR, Credit, Barter) moved to an Advanced section on the Payment Methods tab. Existing values are preserved.
- [CHANGED] The secret hash now sits beside the webhook URL on the API & Webhook tab, with a Generate button.
- [SECURITY] Each store now gets its own generated secret hash. Stores still on the previous default must generate a new one (an admin notice links to it) before webhooks are accepted again.
## 3.3.1 | 08-09-2026
Security release.

### Version Changes
- [SECURITY] Authenticate the payment callback (`?wc-api=flw_wc_payment_gateway`) against the order key. The endpoint previously accepted any order ID, so an unauthenticated request could cancel or fail arbitrary pending orders (CWE-288 / CWE-306).
- [SECURITY] Confirm a cancellation with Flutterwave before changing an order. A client-supplied `status=cancelled` no longer moves an order on its own.
- [SECURITY] Bind each callback to a transaction reference this store issued for that order, so a reference from another order cannot be replayed.
- [SECURITY] Ignore callbacks for orders that are no longer awaiting payment, making repeat callbacks inert.
- [SECURITY] Compare the webhook `verif-hash` with `hash_equals()`, and reject webhooks when no secret hash is configured.
- [SECURITY] Reject replayed webhooks by recording the Flutterwave transaction id already processed for an order (CWE-294).
- [SECURITY] Encrypt the stored Flutterwave card token at rest instead of keeping it as plaintext order meta.
- [SECURITY] Stop writing the full webhook body, which contains customer PII, to the WooCommerce log unless logging is explicitly enabled.
- [SECURITY] Scope checkout nonces to the order they belong to rather than validating against the default action.
- [SECURITY] Orders with no recorded transaction reference now only accept a reference issued in that order's name (`WOOC_<order id>_…`), rather than any reference.
- [FIXED] Preserve callback parameters on stores using plain permalinks, where the return URL previously lost them.
- [FIXED] Store order metadata through the CRUD API so transaction references and payment tokens work under HPOS.
- [FIXED] Correct the plugin header, which declared a WooCommerce floor above its own "tested up to" version.
- [FIXED] A successful Flutterwave charge that completes after its order was cancelled now reopens the order (after confirming the charge with Flutterwave) instead of being rejected as "Order already processed", which left customers charged with nothing fulfilled.
- [CHANGED] Update the JavaScript and PHP toolchains; the build now requires Node 24.
- [REMOVED] Unreferenced express-checkout scaffolding (`client/blocks/payment-request/`) and the unused `flutterwave-react-v3`, `gridicons` and `@automattic/interpolate-components` dependencies, which pulled a vulnerable axios into the dependency tree without reaching the build.
## 3.3.0 | 21-07-2026
- [ADDED] Update the signoz service to take traces and spans.
- [ADDED] Support for PHP 8.2 - 8.4
## 3.2.0 | 15-06-2026
Signoz implementation for service reliability.

### Version Changes
- [ADDED] Signoz monitoring for integration events and errors to improve service reliability.

## 3.1.0 | 28-08-2025
General Update

### Version Changes
- [ADDED] Set Minimum Support to WooCommerce 6.9 or greater.
- [ADDED] Flutterwave Logger instance using wc_logger.
  
## 2.3.6 | 01-09-2025
Bug Fixes and Webhook Handler improvements.

### Version Changes
- [FIXED] Dynamic Adjustment to Custom Permalink Set by Merchant.
- [FIXED] Redirect Payment option returns a Payment Mismatch Error.
- [FIXED] Reject Invalid Order Reference hooks.

## 2.3.5 | 01-01-2024
Added Support for WooCommerce HPOS.

### Version Changes
- [ADDED] Support for WooCommerce HPOS.
- [FIXED] WooCommerce Blocks Compatibility Issues with WooCommerce 7.0 to 6.9.1.
- [FIXED] Payment Option alignment on WooCommerce Checkout Block.
- [FIXED] Handle Cancel Event on redirect to checkout option.

## 2.3.4 | 01-11-2023
Handle Webhook Acknowledgement.

### Version Changes
- [FIXED] Acknowledge hooks sent to prevent unsuccessful webhook delivery.

## 2.3.3 | 24-07-2023
Update Order Notes and Confirmation Alerts.

### Version Changes
- [FIXED] Order Note Details and Confirmation Alerts.

## 2.3.2 | 25-04-2023
WordPress requirement changes and updates.

### Version changes
- [ADDED] Add support for WooCommerce Blocks.
- [CHANGED] Updated Payment Gateway Checkout Process for better user experience.
- [CHANGED] Updated Payment Tokenisation for the saved cards feature.
- [CHANGED] Support for Flutterwave V3 API.
- [CHANGED] Updated WooCommerce Subscription Integration.
- [REMOVED] Remove outdated PHP Software Development Kit (SDK) from the plugin.


## 2.3.0 | 25-10-2022
Routine maintenance. Resolved bug on Mobile Money.

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
- [FIXED] Fix processing function error on WooCommerce Subscription.


## 2.2.7 | 30-05-2022
Bugfixes
### Version changes
- [FIXED] Fix redirect to order receipt page in the redirect method.
- [FIXED] Add support for PHP 8.0.



## 2.2.0 | 06-07-2018
Updated base URL for API calls and added support for recurring payments.

### Version changes
- [ADDED] Add support for WooCommerce recurring to allow merchants to collect recurring payments.
- [CHANGED] Update base URL to support both transactions in both test and live mode.



## 2.0.0
New payment currencies

### Version changes
- [ADDED] Add support for new currencies (ZMW, UGX, RWF, TZS, SLL).



## 1.0.1
Bugfixes

### Version changes
- [ADDED] Add redirect style with admin toggle for redirect or pop-up payment style.
- [CHANGED] Add custom gateway name.
- [FIXED] fix bugs for the country.


## 1.0.0
Initial release

### Version changes
- [ADDED] First plugin release.
