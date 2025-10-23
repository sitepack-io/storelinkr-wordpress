=== StoreLinkr: all-in-one platform for webshops ===
Contributors: storelinkr, petervw
Tags: woocommerce, marketing, returns, marketplace, cyclesoftware
Requires at least: 6.4
Tested up to: 6.8
Stable tag: 2.13.0
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
* [**Accounting for webshops**](https://storelinkr.com/en/solutions/accounting-integration) – Automatically create sales invoices and customers and link your webshop to bookkeeping software.
* [**Returns management**](https://storelinkr.com/en/solutions/returns-handling-for-web-stores) – From scattered emails to one clear returns portal: customer-friendly, efficient, and fully in your branding with less manual work and more control.

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

= How can I create a whitelabel return portal? =

Start by creating a free StoreLinkr account and activate the "Returns" solution. This solutions offers you a whitelabel returns portal to request all data from your customers.

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

Please read the changelog.txt for more commit history of this plugin.

= 2.13.0 =

Release date: 2025-10-23

#### Enhancements

* Save images from the StoreLinkr CDN as .webp format, to support transparancy

#### Bugfixes

None

= 2.12.0 =

Release date: 2025-10-22

#### Enhancements

* Create and validate a category path when a product is pushed

#### Bugfixes

* Fixed an error in the saveProduct method, facets was sometimes null instead of array

= 2.11.0 =

Release date: 2025-10-09

#### Enhancements

* Mark product as digital / virtual good
* Before creating a product variant, we look up existing EAN/GTIN numbers and move them to trash
* Before updating a product variant, we validate EAN/GTIN numbers with products
* Added a new debug tool to find products based on EAN in the StoreLinkr WP admin

#### Bugfixes

* Fixed a case where the plugin searched for an attribute name instead of the object

= 2.10.0 =

Release date: 2025-10-02

#### Enhancements

* New: danger zone area to execute "one time" actions
* Danger zone: merge duplicate attributes
* Danger zone: remove unused attribute values (not linked to any product)

#### Bugfixes

None

= 2.9.11 =

Release date: 2025-09-29

#### Enhancements

* Save attributes on product variable level
* Support for multiple attribute values on single attribute name

#### Bugfixes

* Remove duplicate GTIN / EAN before creating a new variant option to prevent errors
* Product variant only link facet values to main variant

= 2.9.10 =

Release date: 2025-09-24

#### Enhancements

* Overwrite images is now optional, default true
* Added positive points (metafield "_positive_points")
* Added negative points (metafield "_negative_points")
* Link cross-sell products when available
* Link upsell products when available

#### Bugfixes

* Set parent ID on single product fixed

= 2.9.9 =

Release date: 2025-09-16

#### Enhancements

* Overwrite short description is now optional, default true
* Overwrite long description is now optional, default true
* Product are published by default, draft is possible as setting. Default is published.
* Configure backorder setting on site level from StoreLinkr, default not allowed

#### Bugfixes

* Clean advised price field when data is empty
* Remove variant ids field when the product is a single product
* Search product by EAN sometimes failed, now handled correctly

= 2.9.8 =

Release date: 2025-09-10

#### Enhancements

* Archived products validation implemented
* Added new metadata field "advised_price" on product level when we know an advised product price

#### Bugfixes

* Find product by ean (global unique identifier) fixed

= 2.9.7 =

Release date: 2025-09-09

#### Enhancements

* Create new orders from the API

#### Bugfixes

None

= 2.9.6 =

Release date: 2025-08-14

#### Enhancements

None

#### Bugfixes

* Linking variants in grouped products (deprecated) fixed
* Permissions for stock location management fixed

= 2.9.5 =

Release date: 2025-08-12

#### Enhancements

* Added new REST API endpoint `/wp-json/storelinkr/v1/images/create` for standalone image creation in WordPress media library

#### Bugfixes

None

= 2.9.4 =

Release date: 2025-08-06

#### Enhancements

None

#### Bugfixes

* Report if image ID does not exist in WP installation
* Fixed edge cases in the handling links with media to post IDs

= 2.9.3 =

Release date: 2025-08-05

#### Enhancements

* Added dependency requirement to Woo
* Removed dashboard widget for dependency selection

#### Bugfixes

None

= 2.9.2 =

Release date: 2025-07-03

#### Enhancements

None

#### Bugfixes

* Fixed add meta in metadata fields

= 2.9.1 =

Release date: 2025-07-03

#### Enhancements

None

#### Bugfixes

* Fixed reserved slug names in WooCommerce attributes

= 2.9.0 =

Release date: 2025-06-27

#### Enhancements

* Added 2 new REST API endpoints for Variable products
* Allow StoreLinkr to create Variable products
* Set images per product variation
* View stock locations for variable products and variations

#### Bugfixes

None
