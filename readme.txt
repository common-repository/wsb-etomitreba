=== WSB eTomitreba ===
Contributors: branahr
Donate link: https://www.paypal.me/branahr
Tags: rba, etomitreba, woocommerce, payment gateway
Requires at least: 5.0
Tested up to: 6.1
Requires PHP: 5.6
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin for Croatian eTomitreba payment gateway by RBA bank.

== Description ==

WSB eTomitreba is a plugin for Woocommerce that allows online credit cards payment via secure gateway by RBA bank. When you sign 
a contract with a bank, they will provide you with testing and production details along with server certificate and all 
other info. You will need to create a private key in OpenSSL and certificate that you need to send to the bank support. 
After you exchange keys and certificates with bank, set the plugin details and start with testing. 

### Requirements ###

- PHP version 5.6 and above
- OpenSSL installed on server
- Wordpress verison 5.0 and above
- Woocommerce plugin installed and enabled (v 3.8 or greater)
- HRK as a default payment currency

### Documentation ###

The official documentation is located at the [WSB eTomitreba Documentation](https://www.webstudiobrana.com/wsb-etomitreba/) page.

### Features ###

* Notify URL used (no need to enter success and failure urls in merchant interface)
* Test / Production mode
* Authorization mode supported
* Languages: HR, EN
* Show/hide eTomitreba logo on checkout 

== Installation ==

1. Upload entire `woocommerce-gateway-rbawsb` folder to your site's `/wp-content/plugins/` directory. You can also use the *Add new* 
option in the *Plugins* menu in WordPress to upload and install the zip file.  
2. Activate the plugin from the *Plugins* menu in WordPress.
3. Find plugin settings in payment tab on Woocommerce settings page

== Frequently Asked Questions ==

= Where I can find a detailed documentation =

The official documentation is located at the [WSB eTomitreba Documentation](https://www.webstudiobrana.com/wsb-etomitreba/) page.

= Where is the settings page? =

Settings page is on a payment tab under Woocommerce settings page.

== Screenshots ==

1. eTomitreba settings
2. Checkout page with logo

== Changelog ==

= 1.1 =
* Added support for EUR as official Croatian currency from 01.01.2023

= 1.0 =
* Initial release of the plugin.