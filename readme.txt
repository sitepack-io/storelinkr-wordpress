=== StoreLinkr: all-in-one platform for webshops ===
Contributors: storelinkr, petervw
Tags: dropshipping, woocommerce, cyclesoftware, productfeeds, ecommerce
Requires at least: 6.4
Tested up to: 6.7
Stable tag: 2.7.1
Requires PHP: 8.2
License: GPLv2 or later

Easily manage your online store and scale your sales with StoreLinkr. Automate product integrations and advertising campaigns, all from one platform.

== Description ==

Are you currently managing a WooCommerce webshop with a few sales per day and do you want to grow to hundreds or even thousands of orders? Manually managing your processes can quickly become too much. StoreLinkr helps you to easily automate your sales activities, so that you can focus on what really matters: your growth.

With StoreLinkr you can manage everything from one central and clear platform. Whether you want to keep your inventory up to date or optimize your product advertisements, we have the solutions to make your webshop more efficient.

## StoreLinkr Solutions

Select one or more solutions for your online store:

* [**Full webshop integration**](https://storelinkr.com/en/solutions/ecommerce-dropshipping) – Products from your wholesalers are automatically loaded into your webshop and the inventory is continuously updated.
* [**Product feed management**](https://storelinkr.com/en/solutions/generate-product-feeds) – Promote your products with CPC ads by selecting them directly in StoreLinkr or automate your campaigns with filters such as price, category or availability.

## Extensive solutions

### eCommerce Integration
With our eCommerce integration, managing your webshop becomes much easier. Products from your wholesalers are automatically loaded and your inventory is always kept up to date, without having to perform manual updates.

#### Most popular integrations
* [VidaXL](https://storelinkr.com/en/integrations/vidaxl-dropshipment)
* [Skwirrel](https://storelinkr.com/en/integrations/skwirrel-online-store)
* [CycleSoftware](https://storelinkr.com/en/integrations/cyclesoftware-dropshipment)
* [Wilmar](https://storelinkr.com/en/integrations/wilmar-dropshipment)

### Product feeds for advertising
StoreLinkr offers a powerful product feed management system that allows you to easily manage CPC advertising. Manually select the products you want to promote or set up automated campaigns based on filters such as price, category or inventory status. With this solution, you are always visible on the largest advertising platforms such as Google Shopping and Facebook Ads.

#### Popular product feeds
* Google Merchant Center (formerly Google Shopping)
* Meta product feeds (Facebook and Instagram)
* Bing Ads

## THE #1 DATA LINK FOR YOUR WEBSHOP

Our data connection helps you to quickly and easily connect one or more wholesalers to your WooCommerce online store. We are unique in our kind, because we have created an intermediate step between the wholesaler and your webshop.

This intermediate step allows us to read all catalog data from the wholesaler directly to our platform. On the StoreLinkr platform you can exclude, edit and overwrite data.

Once the data is correct, it will only be transferred to your WordPress website. We update your online store at least 4 times a day and stock data on the StoreLinkr platform is maximum 15 minutes old!

### STAY IN CONTROL OF YOUR CATALOGUE

With our unique import filters it is possible to put together your range during import. Select only the product groups you would like to sell.

It is also possible to exclude products from export to your webshop based on:
* **Minimum quantity in stock**, only display products in your webshop that you can sell directly
* **Minimum number of images**, only show products if they have an image
* **Minimum price or maximum price**, determine your own range by activating an active price filter
* And much more..

### DROPSHIP IN A FEW CLICKS

You can get started within a few minutes. Start your own StoreLinkr trial now and activate the first workflow to your WooCommerce online store to start dropshipping.

### GAIN MORE ECOMMERCE KNOWLEGDE

Our comprehensive [eCommerce blog](https://storelinkr.com/en/blog) provides weekly insights into the industry, and we also have extensive [help articles and live chat](https://storelinkr.com/en/support) available to answer your questions.

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

= How I change the product variant display on a product page? =

You can programmatically change the rendering of the variant options on a product details page. Use the "storelinkr_variant_dropdown_label" filter the change the label, or the "storelinkr_variant_html" filter to change the complete HTML rendering.
The style and design of the product variant could also changed by using the "storelinkr_variant_css" filter for the CSS code.

= I have another question not listed here =

Perhaps our [online helpdesk](https://storelinkr.com) can assist you further. Otherwise, our support team is also available to answer your question.

== Developers ==

If you want to contribute, please take a look at our [Github Repository](https://github.com/sitepack-io/storelinkr-wordpress).

== Changelog ==

= 2.7.1 =

Release date: 2025-03-12

#### Enhancements

* Support for Brand taxonomies in WooCommerce

#### Bugfixes

* Download images from the StoreLinkr CDN with wp safe method, fixes Entity too large warning
* Stock call undefined notice fixed

= 2.7.0 =

Release date: 2025-03-05

#### Enhancements

* Download images directly from the StoreLinkr CDN instead of receiving the image content in the API call
* Support for longer attribute names (max length was 28 characters due to data model in WooCommerce)
* Improved code for creating and linking product attributes

#### Bugfixes

* Fallback for facet data, validate if string was already json decoded and validate if array is iterable

= 2.6.3 =

Release date: 2025-03-04

#### Enhancements

* Price updates are optional, configure this setting in the StoreLinkr portal

#### Bugfixes

None

= 2.6.2 =

Release date: 2025-02-04

#### Enhancements

* Stock updates are now optional, configure them in the WordPress export settings in the StoreLinkr portal

#### Bugfixes

None

= 2.6.1 =

Release date: 2025-02-04

#### Enhancements

None

#### Bugfixes

* Remove facets when they are not provided anymore on update

= 2.6.0 =

Release date: 2025-01-16

#### Enhancements

* List products in API for product feeds and marketplaces
* Create product image for grouped product

#### Bugfixes

None

= 2.5.10 =

Release date: 2025-01-15

#### Enhancements

* Rebuild WooCommerce lookup table after changing the product attributes

#### Bugfixes

None

= 2.5.9 =

Release date: 2025-01-10

#### Enhancements

* Support for 4th and 5th category level in getCategories API call

#### Bugfixes

None

= 2.5.8 =

Release date: 2025-01-09

#### Enhancements

None

#### Bugfixes

* Soft fail on create category call, return existing term when the category already exists

= 2.5.7 =

Release date: 2025-01-09

#### Enhancements

* Support deeper category levels

#### Bugfixes

None

= 2.5.6 =

Release date: 2025-01-08

#### Enhancements

None

#### Bugfixes

* Optimized attributes on products create and update

= 2.5.4 =

Release date: 2025-01-06

#### Enhancements

None

#### Bugfixes

* Find products based on SKU in API

= 2.5.3 =

Release date: 2025-01-01

#### Enhancements

* Happy new year!

#### Bugfixes

* Update attachment meta data in WC product

= 2.5.2 =

Release date: 2024-11-20

#### Enhancements

* PHP Support requires 8.2+

#### Bugfixes

None

= 2.5.0 =

Release date: 2024-11-20

#### Enhancements

* Support for grouped products (for product variants)
* Made product variants clickable from grouped product
* Improved readme file

#### Bugfixes

None

= 2.4.1 =

Release date: 2024-11-07

#### Enhancements

* Prevent category delete when StoreLinkr created the category before
* Added link to StoreLinkr portal in category term row actions
* Toggle between variants in product page

#### Bugfixes

* Create category failed when main term was not found in API endpoint
* Allow short description to be empty

= 2.4.0 =

Release date: 2024-11-01

#### Enhancements

* Added a diagnostic tab in the admin for improved support for our customers

#### Bugfixes

None

= 2.3.7 =

Release date: 2024-11-01

#### Enhancements

None

#### Bugfixes

* Skip refunded orders from order API

= 2.3.6 =

Release date: 2024-10-30

#### Enhancements

None

#### Bugfixes

* Convert product status to publish of a product

= 2.3.5 =

Release date: 2024-10-30

#### Enhancements

None

#### Bugfixes

* Product publish status fix from trash or draft

= 2.3.4 =

Release date: 2024-10-30

#### Enhancements

* Upgraded plugin support to WordPress 6.7

#### Bugfixes

* Product SKU was not set in WooCommerce products, added in product sync and mapping

= 2.3.3 =

Release date: 2024-10-18

#### Enhancements

* Display attachment title and description when the data is available, the filename will be used as a fallback
* Set no-cache headers for wp-json/storelinkr endpoints to prevent caching

#### Bugfixes

* Do not return WooCommerce orders with status "checkout-draft"

= 2.3.2 =

Release date: 2024-10-16

#### Enhancements

* Added a new filter to change the Attachment tab label. Use the filter "storelinkr_attachment_label" the change the tab label in the product page frontend.

= 2.3.1 =

Release date: 2024-10-09

#### Enhancements

* Remove / merge duplicate product attributes

#### Bugfixes

* Fixed the empty orders, count valid line items
* Fixed the frontend attachment tab, do not show when the attachment count is zero

= 2.3.0 =

Release date: 2024-10-08

#### Enhancements

* Added file attachments on product details page

#### Bugfixes

* Fixed the StoreLinkrStock construct argument (bool, null was given)
* Fixed an issue with the WP_Error to string parsing
* Fixed an error with the get_attribute() on bool in WooCommerce

= 2.2.0 =

Release date: 2024-08-07

#### Enhancements

* Display product variants on product details page
* Return invalid media id's on product update (useful when media has been removed)

#### Bugfixes

None

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
