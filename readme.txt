=== storelinkr ===
Contributors: storelinkr, petervw
Tags: connect, woocommerce, cyclesoftware, wilmar, storelinkr
Requires at least: 6.3
Tested up to: 6.5
Stable tag: 2.0.11
License: GPLv2 or later

Streamline dropshipping effortlessly! Sync with wholesalers, POS systems & suppliers for seamless product updates and order management. Start now!

== Description ==

Connect the storelinkr platform to your WordPress website.

== Installation ==

Upload the plugin files to the `/wp-content/plugins/` directory.
Activate the plugin through the 'Plugins' menu in WordPress.
Configure the plugin settings via the 'storelinkr' menu in WordPress.

== Configuration ==

Go to the 'storelinkr' menu in WordPress.
Generate the API key and secret.
Open the storelinkr portal and create a new workflow using the above API credentials.
Configure the synchronization settings for products and orders.
The products and categories will be automatically created shortly.

== Frequently Asked Questions ==

= Which point of sale systems are supported by this plugin? =

This plugin works with point of sale systems such as CycleSoftware and Wilmar.

= Do I need a storelinkr account to use this plugin? =

Yes, you need a storelinkr account to use this plugin.

= How can I find my storelinkr API key? =

You can find the storelinkr API key in the WordPress environment by clicking on the storelinkr menu item on the left side. The API key and secret needed in Connect are listed there.

= Can I manually synchronize my products? =

No, we update the products multiple times a day from storelinkr. It is often unnecessary to do this manually.

= How often are products automatically updated? =

Products are regularly updated; the interval depends on the chosen [storelinkr](https://storelinkr.com) subscription.

= I have another question not listed here =

Perhaps our [online helpdesk](https://storelinkr.com) can assist you further. Otherwise, our support team is also available to answer your question.

== Developers ==

If you want to contribute, please take a look at our [Github Repository](https://github.com/sitepack-io/storelinkr-wordpress).

== Changelog ==

= 2.0.9 =

Release date: 2024-03-19

#### Enhancements

None

#### Bugfixes

* Even more feedback implemented from the WordPress plugin team
* Improved security (escaping before output)
* Minor ABSPATH improvements

= 2.0.11 =

Release date: 2024-04-01

#### Enhancements

None

#### Bugfixes

* Improved file upload mechanism for upload image endpoint

= 2.0.10 =

Release date: 2024-03-24

#### Enhancements

None

#### Bugfixes

* Improved usage of the WP_Filesystem and fallbacks implemented for constants FS_CHMOD_FILE and FS_CHMOD_DIR

= 2.0.8 =

Release date: 2024-03-14

#### Enhancements

None

#### Bugfixes

* Feedback implemented from the WordPress plugin team

= 2.0.7 =

Release date: 2024-02-23

#### Enhancements

None

#### Bugfixes

* Make use of the new API endpoint for stock info calls
* Renamed some public functions with sl* prefix for stock implementation

= 2.0.6 =

Release date: 2024-02-22

#### Enhancements

None

#### Bugfixes

* Use wc_price instead of number_format when the method is available for product prices

= 2.0.5 =

Release date: 2024-02-22

#### Enhancements

* Changed plugin URI in description to: https://storelinkr.com/en/integrations/wordpress-woocommerce-dropshipment

#### Bugfixes

* Category class name fixes
* Undefined constants in class fixed FS_CHMOD_DIR and FS_CHMOD_FILE

= 2.0.4 =

Release date: 2024-02-22

#### Enhancements

None

#### Bugfixes

* REST API authentication issue fixed

= 2.0.3 =

Release date: 2024-02-22

#### Enhancements

None

#### Bugfixes

* Callback permission to public, return true in register REST API endpoints

= 2.0.2 =

Release date: 2024-02-14

#### Enhancements

None

#### Bugfixes

* WP_Filesystem include when class is not defined

= 2.0.1 =

Release date: 2024-02-08

#### Enhancements

None

#### Bugfixes

* Fixed WordPress compatibility with the plugin check plugin from the WordPress plugin team

= 2.0.0 =

Release date: 2024-01-27

#### Enhancements

* Initial storelinkr plugin for WordPress release

#### Bugfixes

None
