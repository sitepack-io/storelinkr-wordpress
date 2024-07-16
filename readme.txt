=== StoreLinkr ===
Contributors: storelinkr, petervw
Tags: connect, woocommerce, cyclesoftware, wilmar, storelinkr
Requires at least: 6.3
Tested up to: 6.6
Stable tag: 2.1.1
License: GPLv2 or later

Streamline dropshipping effortlessly! Sync with wholesalers, POS systems & suppliers for seamless product updates and order management. Start now!

== Description ==

Start selling products without your own stock now. Your own dropshipping online store with real quality products and partners. No drop shipment from China via the StoreLinkr platform.

## THE #1 DATA LINK FOR YOUR WEBSHOP

Our data connection helps you to quickly and easily connect one or more wholesalers to your WooCommerce online store. We are unique in our kind, because we have created an intermediate step between the wholesaler and your webshop.

This intermediate step allows us to read all catalog data from the wholesaler directly to our platform. On the StoreLinkr platform you can exclude, edit and overwrite data.

Once the data is correct, it will only be transferred to your WordPress website. We update your online store at least 4 times a day and stock is even a maximum of 15 minutes old!

### STAY IN CONTROL OF YOUR CATALOGUE

With our unique import filters it is possible to put together your range during import. Select only the product groups you would like to sell.

It is also possible to exclude products from export to your webshop based on:
* **Minimum quantity in stock**, only display products in your webshop that you can sell directly
* **Minimum number of images**, only show products if they have an image
* **Minimum price or maximum price**, determine your own range by activating an active price filter
* And much more..

### DROPSHIP IN A FEW CLICKS

You can get started within a few minutes. Start your own StoreLinkr trial now and activate the first workflow to your WooCommerce online store to start dropshipping.

### REQUEST ACCESS FROM A WHOLESALE STORE

We are currently integrating with a number of wholesalers and cash register systems. Including these systems:
* [CycleSoftware](https://storelinkr.com/en/integrations/cyclesoftware-dropshipment)
* [Wilmar](https://storelinkr.com/en/integrations/wilmar-dropshipment)

It is important that you have been given access as a dropshipper with one of our affiliated wholesalers.

### WE PROVIDE ALL THE TECHNOLOGY

The StoreLinkr plugin and the SaaS platform is a powerful combination. The platform is being developed every day and our people are ready to help you with questions.

This is the official [StoreLinkr](https://storelinkr.com) plugin, powered by [SitePack B.V.](https://sitepack.nl).

== Installation ==

Upload the plugin files to the `/wp-content/plugins/` directory.
Activate the plugin through the 'Plugins' menu in WordPress.
Configure the plugin settings via the 'StoreLinkr' menu in WordPress.

== Configuration ==

Go to the 'StoreLinkr' menu in WordPress.
Generate the API key and secret.
Open the StoreLinkr portal and create a new workflow using the above API credentials.
Configure the synchronization settings for products and orders.
The products and categories will be automatically created shortly.

== Frequently Asked Questions ==

= Which point of sale systems are supported by this plugin? =

This plugin works with point of sale systems such as CycleSoftware and Wilmar.

= Do I need a StoreLinkr account to use this plugin? =

Yes, you need a StoreLinkr account to use this plugin.

= How can I find my StoreLinkr API key? =

You can find the StoreLinkr API key in the WordPress environment by clicking on the StoreLinkr menu item on the left side. The API key and secret needed in Connect are listed there.

= Can I manually synchronize my products? =

No, we update the products multiple times a day from StoreLinkr. It is often unnecessary to do this manually.

= How often are products automatically updated? =

Products are regularly updated; the interval depends on the chosen [StoreLinkr](https://storelinkr.com) subscription.

= I have another question not listed here =

Perhaps our [online helpdesk](https://storelinkr.com) can assist you further. Otherwise, our support team is also available to answer your question.

== Developers ==

If you want to contribute, please take a look at our [Github Repository](https://github.com/sitepack-io/storelinkr-wordpress).

== Changelog ==

= 2.1.1 =

Release date: 2024-07-16

#### Enhancements

* StoreLinkr icon improvement in sidebar menu (icon is now in SVG)

#### Bugfixes

None

= 2.1.0 =

Release date: 2024-07-16

#### Enhancements

* WordPress compatibility updated to 6.6

#### Bugfixes

* Make sure a product is published when we update a WooCommerce item

= 2.0.18 =

Release date: 2024-06-05

#### Enhancements

* Improved product image handling in create, update product flow (set correct image_id and gallery_ids)

#### Bugfixes

* Prevent generation of duplicate product image thumbnails
* Set correct featured product image
* Only list image once (don't repeat featured image in thumbnails)

= 2.0.17 =

Release date: 2024-06-03

#### Enhancements

* Extended order line items information for further processing

#### Bugfixes

None

= 2.0.16 =

Release date: 2024-05-29

#### Enhancements

* Return if the image directory is writable in test connection
* Return the product permalink after a create or update call

#### Bugfixes

* Improved image error handling for debugging

= 2.0.15 =

Release date: 2024-05-15

#### Enhancements

* Return version number of plugin in test connection to validate compatability

#### Bugfixes

None

= 2.0.14 =

Release date: 2024-05-15

#### Enhancements

* Order data improvements with correct fields for StoreLinkr

#### Bugfixes

* Response code 500 removed and changed to a HTTP 400

= 2.0.13 =

Release date: 2024-04-15

#### Enhancements

None

#### Bugfixes

* Price formatting issue fixed in the StoreLinkr API

= 2.0.12 =

Release date: 2024-04-11

#### Enhancements

* Test endpoint added for StoreLinkr connection

#### Bugfixes

None

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

= 2.0.9 =

Release date: 2024-03-19

#### Enhancements

None

#### Bugfixes

* Even more feedback implemented from the WordPress plugin team
* Improved security (escaping before output)
* Minor ABSPATH improvements

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

* Initial StoreLinkr plugin for WordPress release

#### Bugfixes

None
