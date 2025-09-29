<?php

use Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore;

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

require_once(STORELINKR_PLUGIN_DIR . 'mappers/class.storelinkr-woocommerce-mapper.php');
require_once(STORELINKR_PLUGIN_DIR . 'models/class.storelinkr-category.php');

class StoreLinkrWooCommerceService
{
    private array $warnings = [];

    public function getCategories(): array
    {
        $product_categories = get_terms([
            'taxonomy' => 'product_cat',
            'orderby' => 'name',
            'order' => 'asc',
            'hide_empty' => false,
        ]);

        $categories = [];

        if (!empty($product_categories)) {
            foreach ($product_categories as $category) {
                $categories[$category->term_id] = $category;
            }
        }

        $wooCategories = [];
        foreach ($categories as $category) {
            $wooCategories[] = StoreLinkrCategory::fromWooCommerce($categories, $category);
        }

        return $wooCategories;
    }

    public function getOrders(int $limit = 20): array
    {
        $orders = wc_get_orders([
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);

        $formatted_orders = [];
        foreach ($orders as $order) {
            if ($order instanceof \Automattic\WooCommerce\Admin\Overrides\OrderRefund) {
                continue;
            }

            if ($order->get_status() === 'checkout-draft') {
                continue;
            }

            $billingAddress = $order->get_address();
            $shippingAddress = $order->get_address('shipping');

            $shippingMethods = [];
            foreach ($order->get_shipping_methods() as $shippingItem) {
                $shippingMethods[] = $shippingItem->get_method_title();
            }
            $deliveryMethod = !empty($shippingMethods) ? implode(', ', $shippingMethods) : 'default';

            $formatted_order = [
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'order_status' => $order->get_status(),
                'created' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'customer' => [
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
                    'company_name' => $order->get_billing_company(),
                    'billingAddress' => [
                        'address' => $billingAddress['address_1'],
                        'addition' => $billingAddress['address_2'],
                        'city' => $billingAddress['city'],
                        'state' => $billingAddress['state'],
                        'postcode' => $billingAddress['postcode'],
                        'country' => $billingAddress['country'],
                        'email' => $billingAddress['email'],
                        'phone' => $billingAddress['phone'],
                    ],
                    'shippingAddress' => [
                        'address' => $shippingAddress['address_1'],
                        'addition' => $shippingAddress['address_2'],
                        'city' => $shippingAddress['city'],
                        'state' => $shippingAddress['state'],
                        'postcode' => $shippingAddress['postcode'],
                        'country' => $shippingAddress['country'],
                        'phone' => $shippingAddress['phone'],
                    ],
                ],
                'order_lines' => [],
                'currency' => $order->get_currency(),
                'total_amount_cents' => intval($order->get_subtotal() * 100),
                'shipping_costs_cents' => intval($order->get_shipping_total() * 100),
                'discount_cents' => intval($order->get_total_discount() * 100),
                'grand_total_cents' => intval($order->get_total() * 100),
                'ip_address' => $order->get_customer_ip_address(),
                'payment_status' => $order->is_paid() ? 'paid' : 'unpaid',
                'deliver_method' => $deliveryMethod,
            ];

            $validLineItems = 0;
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();

                if (is_bool($product)) {
                    continue;
                }

                $ean = $product->get_attribute('ean');

                if (empty($ean)) {
                    foreach ($product->get_meta_data() as $metaDataItem) {
                        assert($metaDataItem instanceof WC_Meta_Data);

                        if ($metaDataItem->get_data()['key'] === 'ean') {
                            $ean = trim($metaDataItem->get_data()['value']);
                        }
                    }
                }

                $formatted_order['order_lines'][] = [
                    'product_id' => $product->get_id(),
                    'ean' => $ean,
                    'sku' => $product->get_sku(),
                    'name' => $product->get_name(),
                    'quantity' => $item->get_quantity(),
                    'price' => intval(
                        $order->get_line_total(
                            $item,
                            false,
                            false
                        ) / $item->get_quantity() * 100
                    ),
                    'price_incl_vat' => intval(
                        $order->get_line_total($item, true, false) / $item->get_quantity() * 100
                    ),
                    'subtotal' => intval($order->get_line_subtotal($item, false, false) * 100),
                    'subtotal_incl_vat' => intval($order->get_line_subtotal($item, true, false) * 100),
                    'item_metadata' => $item->get_meta_data(),
                    'product_metadata' => $product->get_meta_data(),
                    'product_image' => wp_get_attachment_url($product->get_image_id()),
                ];
                $validLineItems++;
            }

            if ($validLineItems === 0) {
                // skip order, no valid line items
                continue;
            }

            $formatted_orders[] = $formatted_order;
        }

        return $formatted_orders;
    }

    public function saveProduct(
        WC_Product|WC_Product_Grouped|WC_Product_Variable $product,
        array $facets,
        ?string $brandName = null,
        bool $publishNewProduct = true,
        bool $isNewProduct = false,
    ): int {
        $product->set_date_modified((new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $product->set_date_modified((new DateTimeImmutable())->format('Y-m-d H:i:s'));

        $status = $product->get_status();

        if ($status !== 'draft') {
            if ($status === 'archived') {
                // Archived → publish of draft
                $product->set_status($publishNewProduct ? 'publish' : 'draft');
            } elseif ($isNewProduct === true) {
                // New product
                $product->set_status($publishNewProduct ? 'publish' : 'draft');
            } else {
                // Existing product
                $product->set_status('publish');
            }
        }

        $productId = $product->save();

        if (!empty($brandName)) {
            $brandTermId = $this->upsertBrandName($brandName);
            wp_set_object_terms($productId, [$brandTermId], 'product_brand');
        }

        if (!empty($facets)) {
            // Preserve cross-sell and upsell IDs before reloading the product
            $crossSellIds = $product->get_cross_sell_ids();
            $upsellIds = $product->get_upsell_ids();

            $product = wc_get_product($productId);
            $product_attributes = $product->get_attributes();
            $existing_facets = [];

            if (is_string($facets)) {
                $facets = json_decode($facets, true);
            }

            if (is_array($facets)) {
                foreach ($facets as $facet) {
                    if (empty($facet['name']) || empty($facet['value'])) {
                        continue;
                    }

                    if (wc_check_if_attribute_name_is_reserved(strtolower($facet['name'])) === true) {
                        continue;
                    }

                    $attribute_taxonomy_key = wc_attribute_taxonomy_name(
                        $this->buildAttributeSlug(self::formatName($facet['name']))
                    );
                    $attribute_id = $this->upsertAttributeAndTerm(
                        $productId,
                        $attribute_taxonomy_key,
                        $facet['name'],
                        $facet['value']
                    );

                    if (!is_int($attribute_id)) {
                        $attribute_object = new WC_Product_Attribute();
                        $attribute_object->set_id($attribute_id);
                        $attribute_object->set_name($attribute_taxonomy_key);
                        $attribute_object->set_options([$facet['value']]);
                        $attribute_object->set_visible(true);
                        $attribute_object->set_variation(false);

                        if (isset($facet['position'])) {
                            $attribute_object->set_position($facet['position']);
                        }

                        $product_attributes[$attribute_taxonomy_key] = $attribute_object;
                    }

                    $existing_facets[] = $attribute_taxonomy_key;
                }
            }

            foreach ($product_attributes as $key => $attribute) {
                if (!in_array($key, $existing_facets)) {
                    unset($product_attributes[$key]);
                }
            }

            $product->set_attributes($product_attributes);

            // Restore cross-sell and upsell IDs that were set by the mapper
            $product->set_cross_sell_ids($crossSellIds);
            $product->set_upsell_ids($upsellIds);

            $product->save();
        }

        $this->rebuildLookupTableForProduct($productId);

        return $productId;
    }

    public function findProduct($productId): WC_Product|WC_Product_Grouped
    {
        $product = wc_get_product($productId);

        if ($product === false) {
            throw new Exception(
                sprintf(
                    'Product not found with id %s!',
                    $productId
                )
            );
        }

        return $product;
    }

    public function findProductBySku(string $sku): WC_Product|WC_Product_Grouped|bool
    {
        $args = [
            'post_type' => 'product',
            'meta_key' => '_sku',
            'meta_value' => sanitize_text_field($sku),
            'post_status' => 'any',
            'posts_per_page' => 1,
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $product_id = $query->posts[0]->ID;
            $product = wc_get_product($product_id);

            if ($product) {
                return $product;
            }
        }

        return false;
    }

    public function findProductByEan(string $ean): WC_Product|WC_Product_Grouped|bool
    {
        $productId = wc_get_product_id_by_global_unique_id($ean);
        if (!empty($productId)) {
            return wc_get_product($productId);
        }

        $products = get_posts([
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => 'ean',
                    'value' => sanitize_text_field($ean),
                    'compare' => '='
                ]
            ]
        ]);

        if (!empty($products)) {
            return wc_get_product($products[0]->ID);
        }

        return false;
    }

    public function saveProductImage(WC_Product $product, WP_REST_Request $request): int
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $imageContent = null;
        if (!empty($request['cdn_url'])) {
            $response = wp_remote_get($request['cdn_url'], [
                'headers' => [
                    'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
                ],
            ]);

            if (is_wp_error($response)) {
                throw new Exception('Image download failed:' . $response->get_error_message());
            }

            $imageContent = wp_remote_retrieve_body($response);
        }

        if (empty($request['cdn_url']) && empty($request['imageContent'])) {
            throw new Exception('Pleas set the image content or could not fetch CDN url!');
        }

        if (empty($imageContent)) {
            throw new Exception('Empty image content!');
        }

        $filename = sprintf(
            '%d_%s_%d.jpg',
            $product->get_id(),
            self::formatName($product->get_name()),
            wp_rand(1, 50000)
        );
        $file_type = 'image/jpeg';

        $upload = wp_upload_bits($filename, null, $imageContent);

        if ($upload['error']) {
            throw new Exception('Error while uploading image: ' . esc_attr($upload['error']));
        }

        $file_path = $upload['file'];

        $attachment = [
            'post_mime_type' => $file_type,
            'post_title' => sanitize_text_field($product->get_name()),
            'post_excerpt' => sanitize_text_field($product->get_name()),
            'post_content' => sanitize_text_field($product->get_name()),
            'post_status' => 'inherit',
            'guid' => $upload['url']
        ];

        $mediaId = wp_insert_attachment($attachment, $file_path, $product->get_id());

        if (is_wp_error($mediaId)) {
            throw new Exception('Image error: ' . $mediaId->get_error_message());
        }

        $attach_data = wp_generate_attachment_metadata($mediaId, $file_path);

        wp_update_attachment_metadata($mediaId, $attach_data);
        update_post_meta($mediaId, '_wp_attachment_image_alt', sanitize_text_field($product->get_name()));

        return $mediaId;
    }

    /**
     * @param string $source
     * @param string $name
     * @param string $slug
     * @param string $parentId
     *
     * @return WP_Term
     * @throws Exception
     */
    public function createCategory(string $source, string $name, string $slug, string $parentId): WP_Term
    {
        $termExists = term_exists($name, 'product_cat', $parentId);

        if ($termExists) {
            return get_term($termExists['term_id'], 'product_cat');
        }

        $term = wp_insert_term($name, 'product_cat', [
            'description' => null,
            'parent' => (!empty($parentId)) ? $parentId : 0,
        ]);

        if ($term instanceof WP_Error) {
            throw new Exception(
                sprintf(
                    'Error while creating term: %s',
                    $term->get_error_message()
                )
            );
        }

        if (!is_array($term)) {
            $existing = get_term_by('name', $name, 'product_cat');

            if ((int)$existing->parent === (int)$parentId) {
                return $existing;
            }

            throw new Exception(
                sprintf(
                    'Could not create a new category, because it already exists! %s',
                    print_r(esc_attr($term), true)
                )
            );
        }

        add_term_meta($term['term_id'], 'import_provider', 'STORELINKR');
        add_term_meta($term['term_id'], 'import_source', $source);

        return get_term($term['term_id']);
    }

    public function updateCategory(
        WP_Term $category,
        string $name,
        string $parentId
    ): void {
        wp_update_term($category->term_id, $category->taxonomy, [
            'name' => $name,
            'parent' => $parentId,
        ]);
    }

    public function findCategory(int $id)
    {
        return get_term($id);
    }

    public function linkProductsAsVariant(
        array $linkProducts,
        array $removeProducts,
        bool $groupedVariant,
        array $variantInfo = [],
        ?int $variantId = null,
        array $images = [],
        array $facets = [],
    ): null|int {
        foreach ($linkProducts as $wooProductId) {
            $wooProduct = $this->findProduct($wooProductId);

            if ($wooProduct instanceof WC_Product) {
                $variantIds = get_post_meta($wooProductId, 'storelinkr_variant_ids', true);

                if (!is_array($variantIds)) {
                    $variantIds = [];
                }

                foreach ($linkProducts as $id) {
                    if (!in_array($id, $variantIds)) {
                        $variantIds[] = $id;
                    }
                }

                update_post_meta($wooProductId, 'storelinkr_variant_ids', $variantIds);

                if ($groupedVariant === true) {
                    $wooProduct->set_parent_id($variantId);
                    $wooProduct->set_catalog_visibility(
                        apply_filters('storelinkr_single_visibility', 'search', $wooProductId)
                    );
                    $wooProduct->save();
                } else {
                    if ($wooProduct->get_catalog_visibility() !== 'visible') {
                        $wooProduct->set_catalog_visibility('visible');
                        $wooProduct->save();
                    }
                }
            }
        }

        if ($groupedVariant === true) {
            $variantProduct = new WC_Product_Grouped();
            if (!empty($variantId)) {
                $variantProduct = $this->findProduct($variantId);

                if (!$variantProduct instanceof WC_Product_Grouped) {
                    $this->logWarning('Invalid product class: ' . $variantProduct::class);

                    throw new Exception('Invalid product class: ' . $variantProduct::class);
                }
            }

            $variantProduct->set_children($linkProducts);
            $variantProduct->set_category_ids(
                (!empty($variantInfo['categories'])) ? (array)$variantInfo['categories'] : []
            );
            $variantProduct->set_name(!empty($variantInfo['name']) ? trim($variantInfo['name']) : '');
            $variantProduct->set_short_description(
                !empty($variantInfo['shortDescription']) ? trim($variantInfo['shortDescription']) : ''
            );
            $variantProduct->set_description(
                !empty($variantInfo['description'])
                    ? trim($variantInfo['description']) : ''
            );
            $variantProduct->set_status('publish');
            $variantProduct->set_catalog_visibility('visible');

            $this->linkProductGalleryImages(
                $variantProduct,
                $images
            );

            $variantId = $this->saveProduct(
                $variantProduct,
                $facets,
                (isset($variantInfo['brand'])) ? $variantInfo['brand'] : null
            );
        } elseif ($groupedVariant === false && !empty($variantId)) {
            $groupedProduct = $this->findProduct($variantId);

            if ($groupedProduct instanceof WC_Product_Grouped) {
                $groupedProduct->set_children([]);
                $groupedProduct->set_status('trash');
                $groupedProduct->save();
            }
        }

        if (count($removeProducts) >= 1) {
            foreach ($linkProducts as $wooProductId) {
                $wooProduct = $this->findProduct($wooProductId);

                if ($wooProduct instanceof WC_Product) {
                    $variantIds = get_post_meta($wooProductId, 'storelinkr_variant_ids', true);

                    if (is_array($variantIds)) {
                        foreach ($removeProducts as $removeId) {
                            if (($key = array_search($removeId, $variantIds)) !== false) {
                                unset($variantIds[$key]);
                            }
                        }

                        update_post_meta($wooProductId, 'storelinkr_variant_ids', array_values($variantIds));

                        if ($wooProduct->get_catalog_visibility() !== 'visible') {
                            $wooProduct->set_catalog_visibility('visible');
                            $wooProduct->save();
                        }
                    }
                }
            }
        }

        return $variantId;
    }

    public function linkProductGalleryImages(
        WC_Product|WC_Product_Grouped|WC_Product_Variable|WC_Product_Variation $product,
        array $images
    ): WC_Product|WC_Product_Grouped|WC_Product_Variable|WC_Product_Variation {
        if (
            empty($images)
            && ($product->get_gallery_image_ids() !== [] || !empty($product->get_image_id()))
        ) {
            $product->set_gallery_image_ids([]);
            $product->set_image_id('');

            return $product;
        } elseif (empty($images)) {
            return $product;
        }

        $featuredImage = current($images);
        $currentGalleryImages = $product->get_gallery_image_ids();
        $currentFeaturedImage = $product->get_image_id();
        $validGalleryImages = [];

        // Build the complete list of valid gallery images (all except featured)
        foreach ($images as $imageId) {
            // Validate if image id still exists, otherwise, log in output:
            $attachment = get_post($imageId);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                $this->warnings[] = sprintf('Image %s does not exist anymore!', $imageId);
                continue;
            }

            // Add to gallery if it's not the featured image
            if ($imageId !== $featuredImage) {
                $validGalleryImages[] = $imageId;
            }
        }

        // Check if any changes are needed to prevent unnecessary updates and thumbnail regeneration
        $featuredImageChanged = $currentFeaturedImage != $featuredImage;
        $galleryImagesChanged = (
            count($validGalleryImages) !== count($currentGalleryImages) ||
            !empty(array_diff($validGalleryImages, $currentGalleryImages)) ||
            !empty(array_diff($currentGalleryImages, $validGalleryImages))
        );

        // Return early if no changes are needed
        if (!$featuredImageChanged && !$galleryImagesChanged) {
            return $product;
        }

        // Update gallery images only if they changed
        if ($galleryImagesChanged) {
            $product->set_gallery_image_ids($validGalleryImages);
        }

        // Update featured image only if it changed
        if ($featuredImageChanged && !empty($featuredImage)) {
            $product->set_image_id($featuredImage);
        }

        return $product;
    }

    public function rebuildLookupTableForProduct($product_id): void
    {
        $lookupDataStore = new LookupDataStore();
        $lookupDataStore->create_data_for_product($product_id, false);
    }

    public function mergeDuplicateAttributes(): void
    {
        $attributeTaxonomies = wc_get_attribute_taxonomies();
        $attributeNames = [];
        $duplicateAttributes = [];

        foreach ($attributeTaxonomies as $taxonomy) {
            if (in_array($taxonomy->attribute_name, $attributeNames)) {
                $duplicateAttributes[] = $taxonomy;
                continue;
            }

            $attributeNames[] = $taxonomy->attribute_name;
        }

        if (empty($duplicateAttributes)) {
            return;
        }

        foreach ($duplicateAttributes as $duplicate) {
            $taxonomyName = wc_attribute_taxonomy_name($duplicate->attribute_name);
            $terms = get_terms([
                'taxonomy' => $taxonomyName,
                'hide_empty' => false,
            ]);

            if (!is_wp_error($terms) && !empty($terms)) {
                $termNames = [];
                $duplicateTerms = [];

                foreach ($terms as $term) {
                    if (in_array($term->name, $termNames)) {
                        $duplicateTerms[] = $term;
                        continue;
                    }

                    $termNames[$term->term_id] = $term->name;
                }

                foreach ($duplicateTerms as $term) {
                    $termToKeepId = array_search($term->name, $termNames);

                    $args = [
                        'post_type' => 'product',
                        'numberposts' => -1,
                        'tax_query' => [
                            [
                                'taxonomy' => $taxonomyName,
                                'field' => 'term_id',
                                'terms' => $term->term_id,
                            ],
                        ],
                    ];
                    $products = get_posts($args);

                    if (!empty($products)) {
                        foreach ($products as $product) {
                            wp_set_object_terms($product->ID, (int)$termToKeepId, $taxonomyName, true);
                        }
                    }

                    wp_delete_term($term->term_id, $taxonomyName);
                }
            }

            wc_delete_attribute($duplicate->attribute_id);
        }
    }

    public function mapProductFromDataArray(array $data, string $type = 'simple'): WC_Product
    {
        if ($type === 'simple') {
            $product = new WC_Product_Simple();
        } elseif ($type === 'variant') {
            $product = new WC_Product_Variable();
            $product->set_manage_stock(false);

            if(!empty($data['variant']['name'])){
                $emptyVariable = $this->findEmptyVariableProductByName($data['variant']['name']);
                if ($emptyVariable instanceof WC_Product_Variable) {
                    $product = $emptyVariable;
                }
            }
        } else {
            throw new Exception('Invalid type requested!');
        }

        if (!empty($data['sku'])) {
            $productSku = $this->findProductBySku($data['sku']);

            if ($productSku !== false) {
                $product = $productSku;
            }
        }

        if (!empty($data['ean'])) {
            $productEan = $this->findProductByEan($data['ean']);

            if ($productEan !== false) {
                $product = $productEan;
            }
        }

        if (!empty($data['id'])) {
            $product = $this->findProduct($data['id']);

            if ($type === 'variant' && !$product instanceof WC_Product_Variable) {
                throw new Exception('Product is not an instance of Variable product!');
            }
        }

        $settings = [];
        if (isset($data['settings'])) {
            $settings = (array)$data['settings'];
        }

        $product = StoreLinkrWooCommerceMapper::convertRequestToProduct(
            $product,
            $data,
            $settings,
            (isset($data['cross_sell_products']))
                ? $this->onlyValidProductIds($data['cross_sell_products']) : [],
            (isset($data['upsell_products']))
                ? $this->onlyValidProductIds($data['upsell_products']) : [],
        );
        if (!empty($data['categoryId'])) {
            $product->set_category_ids($this->getCorrespondingCategoryIds((int)$data['categoryId']));
        }

        if (isset($settings['overwrite_images']) && $settings['overwrite_images'] === false) {
            return $product;
        }

        return $this->linkProductGalleryImages(
            $product,
            (isset($data['images'])) ? (array)$data['images'] : []
        );
    }

    public function buildProductVariantOptions(
        int $productId,
        array $optionLabels,
        array $products,
        array $settings
    ): array {
        $variable_product = wc_get_product($productId);
        $attribute_taxonomies = [];

        foreach ($optionLabels as $option_name) {
            $attribute_label = ucfirst($option_name);
            $attribute_slug = wc_sanitize_taxonomy_name($option_name);
            $taxonomy = 'pa_' . $attribute_slug;

            if (!taxonomy_exists($taxonomy)) {
                wc_create_attribute([
                    'name' => $attribute_label,
                    'slug' => $attribute_slug,
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false,
                ]);
                delete_transient('wc_attribute_taxonomies');
                flush_rewrite_rules();
            }

            $attribute_taxonomies[$option_name] = $taxonomy;

            $terms = array_unique(array_column(array_column($products, 'options'), $option_name));
            foreach ($terms as $term_name) {
                if (!term_exists($term_name, $taxonomy)) {
                    wp_insert_term($term_name, $taxonomy);
                }
            }
        }

        // Preserve existing non-variation attributes (main-level facets)
        $existing_attributes = $variable_product->get_attributes();
        $product_attributes = [];
        
        // First, add existing non-variation attributes
        foreach ($existing_attributes as $key => $existing_attribute) {
            if (!$existing_attribute->get_variation()) {
                $product_attributes[$key] = $existing_attribute;
            }
        }
        
        // Then add variation attributes
        foreach ($attribute_taxonomies as $label => $taxonomy) {
            $attribute_slug = wc_sanitize_taxonomy_name($label);
            $attribute_id = 0;

            foreach (wc_get_attribute_taxonomies() as $attr) {
                if ($attr->attribute_name === $attribute_slug) {
                    $attribute_id = (int)$attr->attribute_id;
                    break;
                }
            }

            // Get only the terms used by product variants instead of all terms
            $used_terms = array_unique(array_column(array_column($products, 'options'), $label));

            $attribute = new WC_Product_Attribute();
            $attribute->set_id($attribute_id);
            $attribute->set_name($taxonomy);
            $attribute->set_options($used_terms);
            $attribute->set_visible(false);
            $attribute->set_variation(true);

            $product_attributes[$taxonomy] = $attribute;
        }

        $totalStockQuantity = 0;
        foreach ($products as $productOption) {
            if (!isset($productOption['inStock']) || !isset($productOption['stockSupplier'])) {
                continue;
            }

            $totalStockQuantity += ((int)$productOption['inStock'] + (int)$productOption['stockSupplier']);
        }

        $variable_product->set_attributes($product_attributes);

        $updateStockInfo = !(isset($productOption['updateStock'])) || (bool)$productOption['updateStock'];
        if ($updateStockInfo === true) {
            $variable_product->set_manage_stock(true);
            $variable_product->set_stock_quantity($totalStockQuantity);
            if ($totalStockQuantity > 1) {
                $variable_product->set_stock_status('instock');
            } else {
                $variable_product->set_stock_status('outofstock');
            }
        }
        $variable_product->save();

        $variation_map = [];
        foreach ($products as $productOption) {
            if (!empty($productOption['id'])) {
                $variation = wc_get_product($productOption['id']);

                if ($variation === false) {
                    $variation = new WC_Product_Variation();
                }
            } else {
                $variation = new WC_Product_Variation();
            }

            assert($variation instanceof WC_Product_Variation);

            $variation->set_parent_id($productId);
            $variation = StoreLinkrWooCommerceMapper::convertRequestToProduct(
                $variation,
                $productOption,
                $settings,
                (isset($productOption['cross_sell_products']))
                    ? $this->onlyValidProductIds($productOption['cross_sell_products']) : [],
                (isset($productOption['upsell_products']))
                    ? $this->onlyValidProductIds($productOption['upsell_products']) : [],
            );

            if (!empty($data['categoryId'])) {
                $variation->set_category_ids($this->getCorrespondingCategoryIds((int)$data['categoryId']));
            }

            $attributes = [];
            foreach ($productOption['options'] as $label => $term_value) {
                if (!isset($attribute_taxonomies[$label])) {
                    continue;
                }

                $taxonomy = $attribute_taxonomies[$label];
                $term = get_term_by('name', $term_value, $taxonomy);

                if (!$term) {
                    $term = get_term_by('slug', sanitize_title($term_value), $taxonomy);
                }

                if ($term) {
                    $attributes[$taxonomy] = $term->slug;
                } else {
                    $this->logWarning(sprintf('Term %s not found in taxonomy %s', $term_value, $taxonomy));
                }
            }

            $overwriteImages = true;
            if (isset($settings['overwrite_images'])) {
                $overwriteImages = (bool)$settings['overwrite_images'];
            }

            if (isset($productOption['images']) && $overwriteImages === true) {
                $this->linkProductGalleryImages($variation, (array)$productOption['images']);
            }

            $variation->set_attributes($attributes);
            $variation->save();

            $variation_id = $variation->get_id();
            $variation_map[$productOption['ean']] = $variation_id;
        }

        // Clean up unused attribute terms after saving variants
        $this->cleanupUnusedVariantAttributes($productId, $products, $optionLabels);

        wc_delete_product_transients($productId);

        return $variation_map;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Clean up unused attribute terms that are not used by any product variants
     *
     * @param int $productId The variable product ID
     * @param array $products Array of product variants
     * @param array $optionLabels Array of option labels (attribute names)
     */
    private function cleanupUnusedVariantAttributes(int $productId, array $products, array $optionLabels): void
    {
        foreach ($optionLabels as $option_name) {
            $attribute_slug = wc_sanitize_taxonomy_name($option_name);
            $taxonomy = 'pa_' . $attribute_slug;

            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            // Get terms currently used by the variants
            $used_terms = array_unique(array_column(array_column($products, 'options'), $option_name));
            
            // Get all existing terms in this taxonomy
            $all_terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'names'
            ]);

            // Find terms that are no longer used by any variant
            $unused_terms = array_diff($all_terms, $used_terms);

            // Remove unused terms that are not being used by other products
            foreach ($unused_terms as $unused_term) {
                $term = get_term_by('name', $unused_term, $taxonomy);
                if ($term) {
                    // Check if this term is used by other products before deleting
                    $products_using_term = get_posts([
                        'post_type' => 'product',
                        'posts_per_page' => 1,
                        'tax_query' => [
                            [
                                'taxonomy' => $taxonomy,
                                'field' => 'term_id',
                                'terms' => $term->term_id
                            ]
                        ],
                        'post__not_in' => [$productId], // Exclude current product
                        'fields' => 'ids'
                    ]);

                    // Only delete term if no other products are using it
                    if (empty($products_using_term)) {
                        wp_delete_term($term->term_id, $taxonomy);
                    }
                }
            }
        }
    }

    public function logWarning(string $string): void
    {
        $this->warnings[] = trim($string);
        error_log($string);
    }

    public function createOrder(WP_REST_Request $request): ?string
    {
        try {
            // Extract request data
            $data = $request->get_params();

            // Create WooCommerce order
            $order = wc_create_order();

            if (is_wp_error($order)) {
                $this->logWarning('StoreLinkr error: Failed to create WooCommerce order: ' . $order->get_error_message());
                return null;
            }

            // Set basic order information
            $order->set_currency($data['currency'] ?? 'EUR');

            if (!empty($data['order_number'])) {
                $order->set_order_key($data['order_number']);
            }

            // Set customer information
            $order->set_billing_first_name($data['first_name'] ?? '');
            $order->set_billing_last_name($data['last_name'] ?? '');
            $order->set_billing_company($data['company_name'] ?? '');
            $order->set_billing_email($data['mail_address'] ?? '');
            $order->set_billing_phone($data['phone_number'] ?? '');

            // Set billing address
            $order->set_billing_address_1($data['billing_address'] ?? '');
            $order->set_billing_address_2($data['billing_address_two'] ?? '');
            $order->set_billing_postcode($data['billing_postal_code'] ?? '');
            $order->set_billing_city($data['billing_city'] ?? '');
            $order->set_billing_country($data['billing_country_code'] ?? '');

            // Set shipping address
            $order->set_shipping_first_name($data['first_name'] ?? '');
            $order->set_shipping_last_name($data['last_name'] ?? '');
            $order->set_shipping_company($data['company_name'] ?? '');
            $order->set_shipping_address_1($data['shipping_address'] ?? '');
            $order->set_shipping_address_2($data['shipping_address_two'] ?? '');
            $order->set_shipping_postcode($data['shipping_postal_code'] ?? '');
            $order->set_shipping_city($data['shipping_city'] ?? '');
            $order->set_shipping_country($data['shipping_country_code'] ?? '');

            // Add line items
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    // Find product by SKU
                    $product = null;
                    if (!empty($item['sku'])) {
                        $product = $this->findProductBySku($item['sku']);
                    }

                    // If no product found by SKU, try to find by EAN
                    if (!$product && !empty($item['ean'])) {
                        $product = $this->findProductByEan($item['ean']);
                    }

                    // Create order item
                    $order_item = new WC_Order_Item_Product();
                    $order_item->set_name($item['name'] ?? '');
                    $order_item->set_quantity($item['quantity'] ?? 1);

                    // Set price from cents
                    $price = isset($item['price']) ? floatval($item['price']) : 0;
                    $order_item->set_subtotal($price * ($item['quantity'] ?? 1));
                    $order_item->set_total($price * ($item['quantity'] ?? 1));

                    // Link to product if found
                    if ($product) {
                        $order_item->set_product($product);
                        $order_item->set_product_id($product->get_id());
                        $order_item->set_variation_id($product->get_type() === 'variation' ? $product->get_id() : 0);
                    }

                    // Add metadata
                    if (!empty($item['sku'])) {
                        $order_item->add_meta_data('sku', $item['sku'], true);
                    }
                    if (!empty($item['ean'])) {
                        $order_item->add_meta_data('ean', $item['ean'], true);
                    }
                    if (!empty($item['vat_rate'])) {
                        $order_item->add_meta_data('vat_rate', $item['vat_rate'], true);
                    }
                    if (!empty($item['commission_costs'])) {
                        $order_item->add_meta_data('commission_costs', $item['commission_costs'], true);
                    }
                    if (!empty($item['metadata'])) {
                        $order_item->add_meta_data('item_metadata', $item['metadata'], true);
                    }

                    $order->add_item($order_item);
                }
            }

            // Set order totals from cents
            if (isset($data['total_amount_cents'])) {
                $order->set_total(floatval($data['total_amount_cents']) / 100);
            }

            if (!empty($data['shipping_method']) || isset($data['shipping_costs_cents'])) {
                $shipping_item = new WC_Order_Item_Shipping();
                $shipping_item->set_method_title($data['shipping_method'] ?? 'Custom Shipping');
                $shipping_item->set_method_id($data['shipping_method'] ?? 'custom');
                if (isset($data['shipping_costs_cents'])) {
                    $shipping_item->set_total(((int)$data['shipping_costs_cents'] > 0)
                        ? (floatval($data['shipping_costs_cents']) / 100) : 0);
                }
                $order->add_item($shipping_item);
            }

            // Add discount as coupon item if discount is present
            if (isset($data['discount_cents']) && (int)$data['discount_cents'] > 0) {
                $discount_amount = floatval($data['discount_cents']) / 100;
                $coupon_item = new WC_Order_Item_Coupon();
                $coupon_item->set_props(
                    array(
                        'code' => 'discount',
                        'discount' => $discount_amount,
                        'discount_tax' => 0, // Assuming no tax on discount for now
                    )
                );
                $order->add_item($coupon_item);
            }

            // Set order metadata
            $order->add_meta_data('_storelinkr_uuid', $data['uuid'] ?? '', true);
            $order->add_meta_data('vat_number', $data['vat_number'] ?? '', true);
            $order->add_meta_data('order_number', $data['order_number'] ?? '', true);
            $order->add_meta_data('discount_cents', $data['discount_cents'] ?? 0, true);
            $order->add_meta_data('grand_total_amount_cents', $data['grand_total_amount_cents'] ?? 0, true);
            $order->add_meta_data('commission_costs_cents', $data['commission_costs_cents'] ?? 0, true);
            if (!empty($data['import_source'])) {
                $order->add_meta_data('import_source', $data['import_source']);
            }

            // Set customer notes
            if (!empty($data['notes'])) {
                $order->set_customer_note($data['notes']);
            }

            // Set order status based on payment status
            if (isset($data['paid']) && (bool)$data['paid'] === true) {
                $order->set_date_paid((new DateTimeImmutable())->format('Y-m-d H:i:s'));
                $order->set_status('processing');
            } else {
                $order->set_status('pending');
            }

            // Save the order
            $order->save();

            // Calculate totals
            $order->calculate_totals();

            // Apply discount after calculate_totals() if needed
            if (isset($data['discount_cents']) && (int)$data['discount_cents'] > 0) {
                $discount_amount = floatval($data['discount_cents']) / 100;
                $order->set_discount_total($discount_amount);
                $order->save();
            }

            return strval($order->get_id());

        } catch (Exception $e) {
            $this->logWarning('StoreLinkr error: Failed to create order: ' . $e->getMessage());
            return null;
        }
    }

    public function removeDuplicateByEan(string $ean, ?int $allowedId = null): void
    {
        $duplicateProduct = $this->findProductByEan($ean);

        if ($duplicateProduct !== false && method_exists($duplicateProduct, 'get_id')) {
            $duplicateId = (int)$duplicateProduct->get_id();

            if ($allowedId === null || ($allowedId !== $duplicateId)) {
                wp_delete_post($duplicateId, true);
                wc_delete_product_transients($duplicateId);
            }
        }
    }

    private function getCorrespondingCategoryIds(int $categoryId): array
    {
        $categories = [$categoryId];
        $currentCategory = $this->findCategory($categoryId);

        while (!empty($currentCategory) && !empty($currentCategory->parent)) {
            $categories[] = $currentCategory->parent;
            $currentCategory = $this->findCategory($currentCategory->parent);
        }

        return $categories;
    }

    private static function formatName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace("/[^A-Za-z0-9 ]/", '', $name);

        return \str_replace(
            [
                ' ',
                '(',
                ')',
                "\/",
                '!',
                '@',
                '`',
                '~',
                '@',
                '#',
                '$',
                '%',
                '^',
                '&',
                '*',
            ],
            '-',
            $name
        );
    }

    private function buildAttributeSlug(string $input): string
    {
        $input = trim($input);
        if (strlen($input) <= 25) {
            return $input;
        }

        $hash = md5($input);

        return substr($input, 0, 17) . '-' . substr($hash, 5, 7);
    }

    private function createAttribute(string $name, string $slug): ?int
    {
        $attribute_id = wc_create_attribute([
            'name' => $name,
            'slug' => $slug,
            'type' => 'select'
        ]);

        if (is_wp_error($attribute_id)) {
            $this->logWarning('StoreLinkr error: Attribute create failed: ' . $attribute_id->get_error_message());

            return null;
        }

        delete_transient('wc_attribute_taxonomies');
        wc_delete_product_transients();

        return $attribute_id;
    }

    /**
     * @param string $slug
     * @param string $attribute_taxonomy_key
     *
     * @return array<string, bool|int>
     */
    private function findExistingAttribute(
        string $slug,
        string $attribute_taxonomy_key
    ): array {
        $attributes = wc_get_attribute_taxonomies();

        foreach ($attributes as $attribute) {
            if (
                $attribute->attribute_name === $slug
                || 'pa_' . $attribute->attribute_name === wc_attribute_taxonomy_name($slug)
                || $attribute->attribute_name === $attribute_taxonomy_key
            ) {
                return [
                    'exists' => true,
                    'attribute_id' => $attribute->attribute_id,
                    'attribute_name' => $attribute->attribute_name,
                    'attribute_label' => $attribute->attribute_label,
                ];
            }
        }

        return [
            'exists' => false,
            'attribute_id' => 0,
            'attribute_name' => $attribute_taxonomy_key,
        ];
    }

    private function upsertAttributeAndTerm(
        int $productId,
        string $attribute_taxonomy_key,
        string $name,
        mixed $value
    ) {
        $slug = $this->buildAttributeSlug(self::formatName($name));
        $attribute_exists = false;
        $attribute_id = 0;

        $existingAttribute = $this->findExistingAttribute(
            $slug,
            $attribute_taxonomy_key,
        );
        if (isset($existingAttribute['exists']) && isset($existingAttribute['attribute_id'])) {
            $attribute_exists = $existingAttribute['exists'];
            $attribute_id = $existingAttribute['attribute_id'];
        }

        if ($attribute_exists === false || taxonomy_exists($attribute_taxonomy_key) === false) {
            $attribute_id = $this->createAttribute($name, $slug);

            if (taxonomy_exists($attribute_taxonomy_key) === false) {
                $registerResult = register_taxonomy($attribute_taxonomy_key, ['product'], []);

                if (is_wp_error($registerResult)) {
                    $this->logWarning(
                        'StoreLinkr error: Register attribute failed: ' . $registerResult->get_error_message()
                    );
                }
            }
        }

        if (taxonomy_exists($attribute_taxonomy_key)) {
            $term = term_exists(
                $this->buildAttributeSlug(self::formatName($value)),
                $attribute_taxonomy_key
            );
            if (!$term) {
                $term = wp_insert_term($value, $attribute_taxonomy_key, [
                    'slug' => $this->buildAttributeSlug(self::formatName($value))
                ]);
            }

            if (is_wp_error($term)) {
                $this->logWarning(
                    'StoreLinkr error: Term attribute: ' . $term->get_error_message()
                );
            }

            if (!is_wp_error($term)) {
                wp_set_object_terms(
                    $productId,
                    $value,
                    $attribute_taxonomy_key,
                    false
                );

                return $attribute_id;
            }
        }

        return $attribute_id;
    }

    private function upsertBrandName(string $brandName): ?int
    {
        if (!term_exists($brandName, 'product_brand')) {
            wp_insert_term(
                $brandName,
                'product_brand',
                [
                    'slug' => $this->buildAttributeSlug($brandName),
                ]
            );
        }

        $brand = get_term_by('name', $brandName, 'product_brand');

        return $brand ? $brand->term_id : null;
    }

    private function onlyValidProductIds(array $productIds): array
    {
        foreach ($productIds as $key => $productId) {
            $product = wc_get_product($productId);

            if ($product === false) {
                unset($productIds[$key]);
            }
        }

        return array_values($productIds);
    }

    /**
     * Find a variable product by exact name that has no variations.
     *
     * @param string $name
     * @return WC_Product_Variable|bool
     */
    private function findEmptyVariableProductByName(string $name): WC_Product_Variable|bool
    {
        if (empty($name)) {
            return false;
        }

        $query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'title' => $name,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => 'variable',
                ],
            ],
        ]);

        if (empty($query->posts)) {
            return false; // no match
        }

        // Take the first match
        $product_id = $query->posts[0];
        $product = wc_get_product($product_id);

        if (!$product instanceof WC_Product_Variable) {
            return false;
        }

        // Ensure no variations exist
        $variations = $product->get_children();
        if (!empty($variations)) {
            return false;
        }

        return $product;
    }

}
