=== TezroPay Checkout for WooCommerce ===
Contributors: tezro
Tags: payment gateway, bitcoin, crypto, tezro, tether, woocommerce
Requires at least: 5.1
Tested up to: 5.8
Requires PHP: 7.2
Stable tag: 1.1.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 
 
== Description ==

Create Payments through TezroPay.
===============================

## Build Status

This plugin allows stores using the WooCommerce shopping cart system to accept cryptocurrency payments via the TezroPay gateway. It only takes a few minutes to configure.

# Requirements

This plugin requires the following:

* [Woocommerce](https://wordpress.org/plugins/woocommerce/).
* A Tezro merchant account https://web.tezro.com

# Installation

Install the plugin via the [Wordpress Plugin Manager]

### When Installing From the Downloadable Archive

Visit the https://tezro.com/documentation.html page of this repository and download the latest version. Once this is done, you can just go to Wordpress's Adminstration Panels > Plugins > Add New > Upload Plugin, select the downloaded archive and click Install Now. After the plugin is installed, click on Activate.

# Setup

To setup plugins navigate to WooCommerce > Settings > Payments > TezroPay Checkout for WooCommerce (Click button "Manage" on the right side) -> Please setup your key and secret key (You need to create account here: https://web.tezro.com/) and configurate plugin for your needs. 

**WARNING:** 
* It is good practice to backup your database before installing plugins. Please make sure you create backups.

## Support

**TezroPay Support:**

https://tezro.com

## Troubleshooting

The latest version of this plugin can always be downloaded from the official Tezro repository located here: https://tezro.com/documentation.html

* This plugin requires PHP 5.5 or higher to function correctly. Contact your webhosting provider or server administrator if you are unsure which version is installed on your web server.
* Ensure a valid SSL certificate is installed on your server. Also ensure your root CA cert is updated. If your CA cert is not current, you will see curl SSL verification errors.
* Verify that your web server is not blocking POSTs from servers it may not recognize. Double check this on your firewall as well, if one is being used.
* Check the system error log file (usually the web server error log) for any errors during Tezro Pay payment attempts. If you contact Tezro Pay support, they will ask to see the log file to help diagnose the problem.
* Check the version of this plugin against the official plugin repository to ensure you are using the latest version. Your issue might have been addressed in a newer version!

**NOTE:** When contacting support it will help us if you provide:

* WordPress and WooCommerce Version
* PHP Version
* Other plugins you have installed
* Configuration settings for the plugin (Most merchants take screen grabs)
* Any log files that will help
  * Web server error logs
* Screen grabs of error message if applicable.

## License

Please refer to the [LICENSE](https://tezro.com/) file that came with this project.
