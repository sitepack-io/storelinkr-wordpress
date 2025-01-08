<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

require_once(STORELINKR_PLUGIN_DIR . 'models/class.storelinkr-category.php');

class StoreLinkrWooCommerceService
{

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

    public function mapProductFromData(WP_REST_Request $data): WC_Product
    {
        $product = new WC_Product_Simple();

        if (!empty($data->get_param('sku'))) {
            $productSku = $this->findProductBySku($data->get_param('sku'));

            if ($productSku !== false) {
                $product = $productSku;
            }
        }

        if (!empty($data->get_param('id'))) {
            $product = $this->findProduct($data->get_param('id'));
        }

        if (!empty($data->get_param('sku'))) {
            $product->set_sku($data->get_param('sku'));
        }
        if (!empty($data->get_param('ean'))) {
            $product->set_global_unique_id($data->get_param('ean'));
        }

        $product->set_name($data->get_param('name'));
        $product->set_regular_price($this->formatPrice((int)$data->get_param('salesPrice')));

        if (!empty($data['promoSalesPrice'])) {
            $product->set_sale_price($this->formatPrice((int)$data->get_param('promoSalesPrice')));
        }

        $product->set_date_on_sale_from(null);
        $product->set_date_on_sale_to(null);
        if (!empty($data->get_param('promoStart')) && !empty($data->get_param('promoEnd'))) {
            $product->set_date_on_sale_from(
                (new DateTimeImmutable($data->get_param('promoStart')))->format('Y-m-d H:i:s')
            );
            $product->set_date_on_sale_to((new DateTimeImmutable($data->get_param('promoEnd')))->format('Y-m-d H:i:s'));
        }

        if (!empty($data->get_param('shortDescription'))) {
            $product->set_short_description($data->get_param('shortDescription'));
        }
        if (!empty($data->get_param('longDescription'))) {
            $product->set_description($data->get_param('longDescription'));
        }

        $product->set_category_ids($this->getCorrespondingCategoryIds((int )$data->get_param('categoryId')));
        $product->set_manage_stock(true);
        $product->set_stock_quantity(0);
        $product->set_stock_status('outofstock');
        if (
            (bool)$data->get_param('hasStock') === true
            || (int)$data->get_param('inStock') >= 1
            || (int)$data->get_param('stockSupplier') >= 1
        ) {
            $product->set_stock_status('instock');
            $product->set_stock_quantity((int)$data->get_param('inStock') + (int)$data->get_param('stockSupplier'));

            if ($product->get_stock_quantity() < 1) {
                $product->set_stock_quantity(1);
            }
        }

        $metaData = [];
        if (!empty($data->get_param('metadata'))) {
            $json = \json_decode($data->get_param('metadata'), true);

            if (is_array($json)) {
                $metaData = $json;

                foreach ($metaData as $key => $value) {
                    $product->add_meta_data($key, $value);
                }
            }
        }

        $product->add_meta_data('import_provider', 'STORELINKR', true);
        $product->add_meta_data('import_source', $data->get_param('importSource'), true);
        $product->add_meta_data('site', $data->get_param('site'), true);
        $product->add_meta_data('ean', $data->get_param('ean'), true);

        if ($product->get_date_created() === null) {
            $product->set_date_created((new DateTimeImmutable())->format('Y-m-d H:i:s'));
        }

        $attachments = [];
        if (!empty($data->get_param('attachments'))) {
            if (is_array($data->get_param('attachments'))) {
                foreach ($data->get_param('attachments') as $attachment) {
                    $attachments[] = [
                        'uuid' => $attachment['uuid'],
                        'name' => $attachment['name'],
                        'title' => (!empty($attachment['title'])) ? $attachment['title'] : null,
                        'description' => (!empty($attachment['description'])) ? $attachment['description'] : null,
                        'cdn_url' => $attachment['cdn_url'],
                    ];
                }
            }
        }

        $product->update_meta_data('_product_attachments', json_encode($attachments));

        return $this->linkProductGalleryImages($product, (array)$data->get_param('images'));
    }

    public function saveProduct(WP_REST_Request $request, WC_Product $product): int
    {
        $product->set_date_modified((new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $product->set_status('publish');

        $productId = $product->save();
        $data = [];

        foreach ($request['facets'] as $facet) {
            if (empty($facet['name']) || empty($facet['value'])) {
                continue;
            }

            $attribute_name = self::formatName($facet['name']);
            $attribute_taxonomy = 'pa_' . $attribute_name;

            $attributes = wc_get_attribute_taxonomies();
            $attribute_exists = false;

            foreach ($attributes as $attribute) {
                if ($attribute->attribute_name === $attribute_name) {
                    $attribute_exists = true;
                    break;
                }
            }

            if (!$attribute_exists) {
                wc_create_attribute([
                    'name' => $facet['name'],
                    'type' => 'select'
                ]);

                if (taxonomy_exists($attribute_taxonomy) === false) {
                    register_taxonomy($attribute_taxonomy, ['product'], []);
                }
            }

            wp_insert_term($facet['value'], $attribute_taxonomy);
            wp_set_object_terms(
                $productId,
                $facet['value'],
                $attribute_taxonomy,
                true
            );

            $data[$attribute_name] = [
                'name' => $facet['name'],
                'value' => $facet['value'],
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0,
            ];
        }

        update_post_meta($productId, '_product_attributes', $data);

        return $productId;
    }

    public function findProduct($productId): WC_Product|WC_Product_Grouped
    {
        $product = wc_get_product($productId);

        if ($product === false) {
            throw new Exception('Product not found!');
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

        $decoded = base64_decode(str_replace(['data:image/jpeg;base64,', ' '], ['', '+'], $request['imageContent']));
        $filename = sprintf(
            '%d_%s_%d.jpg',
            $product->get_id(),
            self::formatName($product->get_name()),
            wp_rand(1, 50000)
        );
        $file_type = 'image/jpeg';

        $upload = wp_upload_bits($filename, null, $decoded);

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
     * @param string $parentUuid
     * @return WP_Term
     * @throws Exception
     */
    public function createCategory(string $source, string $name, string $slug, string $parentUuid): WP_Term
    {
        $term = wp_insert_term($name, 'product_cat', [
            'description' => null,
            'parent' => (!empty($parentUuid)) ? $parentUuid : 0,
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

            if ((int)$existing->parent === (int)$parentUuid) {
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
        ?int $variantId = null
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
                    $wooProduct->set_catalog_visibility('search');
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

            $variantId = $variantProduct->save();
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

    public function linkProductGalleryImages(WC_Product $product, array $images): WC_Product
    {
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

    private function getCorrespondingCategoryIds(int $categoryId): array
    {
        $categories = [$categoryId];

        $term = $this->findCategory($categoryId);
        if (!empty($term->parent)) {
            $categories[] = $term->parent;

            $parent = $this->findCategory($term->parent);

            if (!empty($parent->parent)) {
                $categories[] = $parent->parent;
            }
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

    /**
     * Format the price cents to a correctly formatted decimal as a float.
     *
     * @param int $priceCents
     * @return float
     */
    private function formatPrice(int $priceCents): float
    {
        if ($priceCents <= 0) {
            return \floatval(0);
        }

        return \floatval($priceCents / 100);
    }

}
