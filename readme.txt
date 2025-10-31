=== StoreLinkr: all-in-one platform for webshops ===
Contributors: storelinkr, petervw
Tags: woocommerce, marketing, returns, marketplace, cyclesoftware
Requires at least: 6.4
Tested up to: 6.8
Stable tag: 2.14.4
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

= 2.14.4 =

Release date: 2025-10-31

#### Enhancements

None

#### Bugfixes

* Parse large dataset from json method in request object

= 2.14.3 =

Release date: 2025-10-31

#### Enhancements

* Return the received amount of productoptions for validation on a variant call

#### Bugfixes

* Clear the cache when a new term is created in the variant create/update flow

= 2.14.2 =

Release date: 2025-10-30

#### Enhancements

* Improved logic in building variant options from dataset

#### Bugfixes

None

= 2.14.1 =

Release date: 2025-10-30

#### Enhancements

* Added the WooCommerce line item id in the response

#### Bugfixes

* Removed deprecated method get_notes() from WooCommerce order and replaced with current method

= 2.14.0 =

Release date: 2025-10-30

#### Enhancements

None

#### Bugfixes

* Improved EAN/GTIN check to prevent false positives

= 2.13.0 =

Release date: 2025-10-23

#### Enhancements

* Save images from the StoreLinkr CDN as .webp format, to support transparancy

#### Bugfixes

None
