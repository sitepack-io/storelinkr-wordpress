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
                    'price' => intval($order->get_line_total($item, false, false) / $item->get_quantity() * 100),
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
        ?string $brandName = null
    ): int {
        $product->set_date_modified((new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $product->set_status('publish');

        $productId = $product->save();

        if (!empty($brandName)) {
            $brandTermId = $this->upsertBrandName($brandName);
            wp_set_object_terms($productId, [$brandTermId], 'product_brand');
        }

        if (!empty($facets)) {
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

    private function findProductBySku(mixed $get_param): WC_Product|WC_Product_Grouped|bool
    {
        $args = [
            'post_type' => 'product',
            'meta_key' => '_sku',
            'meta_value' => sanitize_text_field($get_param),
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
        array $products,
        array $removeProducts,
        bool $groupedVariant,
        array $variantInfo = [],
        ?int $variantId = null,
        array $images = [],
        array $facets = [],
    ): null|int {
        foreach ($products as $wooProductId) {
            $wooProduct = $this->findProduct($wooProductId);

            if ($wooProduct instanceof WC_Product) {
                $variantIds = get_post_meta($wooProductId, 'storelinkr_variant_ids', true);

                if (!is_array($variantIds)) {
                    $variantIds = [];
                }

                foreach ($products as $id) {
                    if (!in_array($id, $variantIds)) {
                        $variantIds[] = $id;
                    }
                }

                update_post_meta($wooProductId, 'storelinkr_variant_ids', $variantIds);

                if ($groupedVariant === true) {
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

            $variantProduct->set_children($products);
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
            foreach ($products as $wooProductId) {
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
        $currentImages = $product->get_gallery_image_ids();
        $newImages = [];

        foreach ($images as $imageId) {
            if ($imageId !== $featuredImage && in_array($imageId, $currentImages) === false) {
                $newImages[] = $imageId;
            }
        }

        if (
            $product->get_image_id() == $featuredImage
            && empty(array_diff($newImages, $currentImages))
            && empty(array_diff($currentImages, $newImages))) {
            return $product;
        }

        $product->set_gallery_image_ids($newImages);

        if (!empty($featuredImage)) {
            if ($product->get_image_id() !== $featuredImage) {
                $product->set_image_id($featuredImage);
            }
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
        } else {
            throw new Exception('Invalid type requested!');
        }

        if (!empty($data['sku'])) {
            $productSku = $this->findProductBySku($data['sku']);

            if ($productSku !== false) {
                $product = $productSku;
            }
        }

        if (!empty($data['id'])) {
            $product = $this->findProduct($data['id']);

            if ($type === 'variant' && !$product instanceof WC_Product_Variable) {
                throw new Exception('Product is not an instance of Variable product!');
            }
        }

        $product = StoreLinkrWooCommerceMapper::convertRequestToProduct(
            $product,
            $data,
        );
        if (!empty($data['categoryId'])) {
            $product->set_category_ids($this->getCorrespondingCategoryIds((int)$data['categoryId']));
        }

        return $this->linkProductGalleryImages($product, (isset($data['images'])) ? (array)$data['images'] : []);
    }

    public function buildProductVariantOptions(int $productId, array $optionLabels, array $products): array
    {
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

        $product_attributes = [];
        foreach ($attribute_taxonomies as $label => $taxonomy) {
            $attribute_slug = wc_sanitize_taxonomy_name($label);
            $attribute_id = 0;

            foreach (wc_get_attribute_taxonomies() as $attr) {
                if ($attr->attribute_name === $attribute_slug) {
                    $attribute_id = (int)$attr->attribute_id;
                    break;
                }
            }

            $term_names = wp_list_pluck(get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]), 'name');

            $attribute = new WC_Product_Attribute();
            $attribute->set_id($attribute_id);
            $attribute->set_name($taxonomy);
            $attribute->set_options($term_names);
            $attribute->set_visible(false);
            $attribute->set_variation(true);

            $product_attributes[] = $attribute;
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
            } else {
                $variation = new WC_Product_Variation();
            }

            assert($variation instanceof WC_Product_Variation);

            $variation->set_parent_id($productId);
            $variation = StoreLinkrWooCommerceMapper::convertRequestToProduct(
                $variation,
                $productOption,
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
                    $this->logWarning(sprintf('Term %s not found in taxonmy %s', $term_value, $taxonomy));
                }
            }

            if (isset($productOption['images'])) {
                $this->linkProductGalleryImages($variation, (array)$productOption['images']);
            }

            $variation->set_attributes($attributes);
            $variation->save();

            $variation_id = $variation->get_id();
            $variation_map[$productOption['ean']] = $variation_id;
        }


        wc_delete_product_transients($productId);

        return $variation_map;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
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

    private function upsertAttributeAndTerm(int $productId, string $attribute_taxonomy_key, string $name, mixed $value)
    {
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

    private function logWarning(string $string): void
    {
        $this->warnings[] = trim($string);
        error_log($string);
    }
}
