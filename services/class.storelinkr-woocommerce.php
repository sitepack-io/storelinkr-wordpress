<?php

require_once(STORELINKR_PLUGIN_DIR . 'models/class.storelinkr-category.php');

if (!function_exists('wp_generate_attachment_metadata')) {
    require ABSPATH . 'wp-admin/includes/image.php';
}

class StoreLinkrWooCommerceService
{

    private WP_Filesystem_Direct $wpFileSystem;

    public function __construct(WP_Filesystem_Direct $wpFileSystem)
    {
        $this->wpFileSystem = $wpFileSystem;
    }

    public function getCategories(): array
    {
        $cat_args = [
            'orderby' => 'name',
            'order' => 'asc',
            'hide_empty' => false,
        ];

        $product_categories = get_terms('product_cat', $cat_args);

        $categories = [];

        if (!empty($product_categories)) {
            foreach ($product_categories as $key => $category) {
                $categories[$category->term_id] = $category;
            }
        }

        $wooCategories = [];
        foreach ($categories as $category) {
            $wooCategories[] = StoreLinkrCategory::fromWooCommerce($categories, $category);
        }

        return $wooCategories;
    }

    public function getOrders(): array
    {
        // TODO, future implementation
    }

    public function mapProductFromData(WP_REST_Request $data): WC_Product
    {
        $product = new WC_Product_Simple();
        if (!empty($data['id'])) {
            $product = $this->findProduct($data['id']);
        }

        $product->set_name($data['name']);
        $product->set_regular_price(\number_format($data['salesPrice'] / 100, 2, '.', ''));

        if (!empty($data['promoSalesPrice'])) {
            $product->set_sale_price(\number_format($data['promoSalesPrice'] / 100, 2, '.', ''));
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
        return wc_get_product($productId);
    }

    public function saveProductImage(WC_Product $product, WP_REST_Request $request): int
    {
        $uploadDir = wp_upload_dir();
        $uploadPath = str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $uploadDir['path']
            ) . DIRECTORY_SEPARATOR;

        $decoded = base64_decode(
            str_replace([
                'data:image/jpeg;base64,',
                ' '
            ],
                [
                    '',
                    '+',
                ],
                $request['imageContent']
            )
        );

        $filename = sprintf(
            '%d_%s_%d.jpg',
            $product->get_id(),
            self::formatName($product->get_name()),
            wp_rand(1, 50000)
        );
        $file_type = 'image/jpeg';

        $this->wpFileSystem->put_contents(
            $uploadPath . $filename,
            $decoded
        );

        $attachment = [
            'post_mime_type' => $file_type,
            'post_title' => sanitize_text_field($product->get_name()),
            'post_excerpt' => sanitize_text_field($product->get_name()),
            'post_content' => sanitize_text_field($product->get_name()),
            'post_status' => 'inherit',
            'guid' => $uploadDir['url'] . '/' . basename($filename)
        ];

        $mediaId = wp_insert_attachment($attachment, $uploadDir['path'] . DIRECTORY_SEPARATOR . $filename);
        $attach_data = wp_generate_attachment_metadata($mediaId, $uploadDir['path'] . DIRECTORY_SEPARATOR . $filename);
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

        if (!is_array($term)) {
            $existing = get_term_by('name', $name, 'product_cat');

            if ((int)$existing->parent === (int)$parentUuid) {
                return $existing;
            }

            throw new Exception('Could not create a new category, because it already exists! ' . print_r($term, true));
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


}