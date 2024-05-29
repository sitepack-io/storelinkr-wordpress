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

        $formatted_orders = array();

        foreach ($orders as $order) {
            $billingAddress = $order->get_address();
            $shippingAddress = $order->get_address('shipping');

            $formatted_order = [
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer' => [
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
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
                'payment_status' => $order->get_status(),
            ];

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                $ean = $product->get_attribute('ean');

                if (empty($ean)) {
                    foreach ($product->get_meta_data() as $metaDataItem) {
                        assert($metaDataItem instanceof WC_Meta_Data);

                        if ($metaDataItem->get_data()['key'] === 'ean') {
                            $ean = $metaDataItem->get_data()['value'];
                        }
                    }
                }

                $formatted_order['order_lines'][] = [
                    'product_id' => $product->get_id(),
                    'ean' => $ean,
                    'sku' => $product->get_sku(),
                    'name' => $product->get_name(),
                    'quantity' => $item->get_quantity(),
                    'price' => $order->get_line_total($item, false, false),
                    'price_incl_vat' => $order->get_line_total($item, false, false),
                    'subtotal' => $order->get_line_subtotal($item, false, false) * 100,
                    'subtotal_incl_vat' => $order->get_line_subtotal($item, true, false) * 100,
                    'item_metadata' => $item->get_meta_data(),
                    'product_metadata' => $product->get_meta_data(),
                ];
            }

            $formatted_orders[] = $formatted_order;
        }

        return $formatted_orders;
    }

    public function mapProductFromData(WP_REST_Request $data): WC_Product
    {
        $product = new WC_Product_Simple();
        if (!empty($data['id'])) {
            $product = $this->findProduct($data['id']);
        }

        $product->set_name($data['name']);
        $product->set_regular_price($this->formatPrice((int)$data['salesPrice']));

        if (!empty($data['promoSalesPrice'])) {
            $product->set_sale_price($this->formatPrice((int)$data['promoSalesPrice']));
        }

        $product->set_date_on_sale_from(null);
        $product->set_date_on_sale_to(null);
        if (!empty($data['promoStart']) && !empty($data['promoEnd'])) {
            $product->set_date_on_sale_from((new DateTimeImmutable($data['promoStart']))->format('Y-m-d H:i:s'));
            $product->set_date_on_sale_to((new DateTimeImmutable($data['promoEnd']))->format('Y-m-d H:i:s'));
        }

        $product->set_short_description($data['shortDescription']);
        if (!empty($data['longDescription'])) {
            $product->set_description($data['longDescription']);
        }

        $product->set_category_ids($this->getCorrespondingCategoryIds((int )$data['categoryId']));

        $product->set_manage_stock(true);
        $product->set_stock_quantity(0);
        $product->set_stock_status('outofstock');
        if ((bool)$data['hasStock'] === true || (int)$data['inStock'] >= 1 || (int)$data['stockSupplier'] >= 1) {
            $product->set_stock_status('instock');
            $product->set_stock_quantity((int)$data['inStock'] + (int)$data['stockSupplier']);

            if ($product->get_stock_quantity() < 1) {
                $product->set_stock_quantity(1);
            }
        }

        $metaData = [];
        if (!empty($data['metadata'])) {
            $json = \json_decode($data['metadata'], true);

            if (is_array($json)) {
                $metaData = $json;

                foreach ($metaData as $key => $value) {
                    $product->add_meta_data($key, $value);
                }
            }
        }

        $product->add_meta_data('import_provider', 'STORELINKR', true);
        $product->add_meta_data('import_source', $data['importSource'], true);
        $product->add_meta_data('site', $data['site'], true);
        $product->add_meta_data('ean', $data['ean'], true);

        if ($product->get_date_created() === null) {
            $product->set_date_created((new DateTimeImmutable())->format('Y-m-d H:i:s'));
        }

        return $product;
    }

    public function saveProduct(WP_REST_Request $request, WC_Product $product): int
    {
        $product->set_date_modified((new DateTimeImmutable())->format('Y-m-d H:i:s'));

        $productId = $product->save();
        $data = [];

        foreach ($request['facets'] as $facet) {
            if (empty($facet['name']) || empty($facet['value'])) {
                continue;
            }

            wc_create_attribute([
                'name' => $facet['name'],
                'type' => 'select'
            ]);
            if (taxonomy_exists('pa_' . self::formatName($facet['name'])) === false) {
                register_taxonomy('pa_' . self::formatName($facet['name']), ['product'], []);
            }
            wp_insert_term($facet['value'], 'pa_' . self::formatName($facet['name']));
            wp_set_object_terms(
                $productId,
                $facet['value'],
                'pa_' . self::formatName($facet['name']),
                true
            );

            $data[self::formatName($facet['name'])] = [
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

    public function findProduct($productId): WC_Product
    {
        $product = wc_get_product($productId);

        if ($product === false) {
            throw new Exception('Product not found!');
        }

        return $product;
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

        $product_gallery = (array)$product->get_gallery_image_ids();
        $product_gallery[] = $mediaId;

        if (empty($product_gallery)) {
            $product->set_image_id($mediaId);
            $product->save();
        } else {
            $product->set_gallery_image_ids($product_gallery);
            $product->save();
        }

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