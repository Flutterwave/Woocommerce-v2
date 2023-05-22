<p align="center">
    <img title="Flutterwave" height="200" src="https://flutterwave.com/images/logo/full.svg" width="50%"/>
</p>

# Flutterwave WooCommerce

## Introduction

The WooCommerce Plugin makes it very easy and quick to add Flutterwave Payment option on Checkout for your online store. Accept Credit card, Debit card and Bank account payment directly on your store with the Rave payment gateway for WooCommerce.

Available features include:

- Collections: Card, Account, Mobile money, Bank Transfers, USSD, Barter, NQR.
- Recurring payments: Tokenization and Subscriptions (WooCommerce Subscriptions).
- Split payments: Split payments between multiple recipients.


## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Initialization](#initialization)
4. [Best Practices](#best-practices)
5. [Debugging Errors](#debugging-errors)
6. [Support](#support)
7. [Contribution guidelines](#contribution-guidelines)
9. [License](#license)
10. [Changelog](#changelog)


## Requirements

1. Flutterwave for business [API Keys](https://developer.flutterwave.com/docs/integration-guides/authentication)
2. [WooCommerce](https://woocommerce.com/)
6. Supported PHP version: 5.6.0 - 8.1.0


## Installation

### Github Zip Download

To install the plugin, you need to first clone the repository from [GitHub](https://github.com/Flutterwave/WooCommerce) and then upload the folder to your WordPress plugins directory. For WooCommerce Blocks support, you would need to build block assets by running the command `npm install && npm run build:webpack`.

### Automatic Installation

- Login to your WordPress Dashboard.
- Click on "Plugins > Add New" from the left menu.
- In the search box type **Flutterwave Woocommerce**.
- Click on **Install Now** on **Flutterwave WooCommerce** to install the plugin on your site.
- Confirm the installation.
- Activate the plugin.
- Click on "WooCommerce > Settings" from the left menu and click the **"Payments"** tab.
- Click on the **Flutterwave** link from the available Checkout Options
- Configure your **Flutterwave** settings accordingly.

### Manual Installation

- Download the plugin zip file.
- Login to your WordPress Admin. Click on "Plugins > Add New" from the left menu.
- Click on the "Upload" option, then click "Choose File" to select the zip file you downloaded. Click "OK" and "Install Now" to complete the installation.
- Activate the plugin.
- Click on "WooCommerce > Settings" from the left menu and click the **"Payments"** tab.
- Click on the **Flutterwave** link from the available Checkout Options
- Configure your **Flutterwave** settings accordingly.

For FTP manual installation, [check here](https://wordpress.org/documentation/article/manage-plugins/).

## Best Practices

- When in doubt about a transaction, always check the Flutterwave Dashboard to confirm the status of a transaction.
- Always ensure you keep your API keys securely and privately. Do not share with anyone.
- Ensure you change from the default secret hash on the Wordpress admin and apply same on the Flutterwave Dashboard.
- Always ensure you install the most recent version of the Flutterwave WooCommerce plugin.


## Debugging Errors

We understand that you may run into some errors while integrating our plugin. You can read more about our error messages [here](https://developer.flutterwave.com/docs/integration-guides/errors).

For `authorization` and `validation` error responses, double-check your API keys and request. If you get a `server` error, kindly engage the team for support.


## Support

For additional assistance using this library, contact the developer experience (DX) team via [email](mailto:developers@flutterwavego.com) or on [slack](https://bit.ly/34Vkzcg). 

You can also follow us [@FlutterwaveEng](https://twitter.com/FlutterwaveEng) and let us know what you think 😊.


## Contribution guidelines

We love to get your input. Read more about our community contribution guidelines [here](/CONTRIBUTING.md)


## License

By contributing to the Rave WooCommerce Plugin, you agree that your contributions will be licensed under its [MIT license](/LICENSE).

Copyright (c) Flutterwave Inc.


## Changelog

Read about the changes we have made in our recent releases [here](/CHANGELOG.md)
