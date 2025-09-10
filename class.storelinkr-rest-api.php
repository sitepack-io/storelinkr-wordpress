<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

require_once(STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php');
require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-admin.php');

class StoreLinkrRestApi
{
    private const MESSAGE_UNAUTHORIZED = 'UNAUTHORIZED';

    /** @var StoreLinkrWooCommerceService */
    private $eCommerceService = null;
    private string $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    public function init()
    {
        if (storelinkrWooIsActive() === false) {
            return;
        }

        if (!class_exists('WP_Filesystem_Direct')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        $this->eCommerceService = new StoreLinkrWooCommerceService();

        register_rest_route('storelinkr/v1', '/test-connection', [
            'methods' => 'GET',
            'callback' => [$this, 'renderTestConnection'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'renderCategories'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/categories/create', [
            'methods' => 'POST',
            'callback' => [$this, 'renderCategoriesCreate'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/categories/update/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'renderCategoryUpdate'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'renderOrders'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/orders/create', [
            'methods' => 'POST',
            'callback' => [$this, 'renderOrderCreate'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'renderListProducts'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products/create', [
            'methods' => 'POST',
            'callback' => [$this, 'renderCreateProduct'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products/(?P<id>\d+)/update', [
            'methods' => 'POST',
            'callback' => [$this, 'renderUpdateProduct'],
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
            ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products/create-variant', [
            'methods' => 'POST',
            'callback' => [$this, 'renderCreateProductVariant'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products/(?P<id>\d+)/update-variant', [
            'methods' => 'POST',
            'callback' => [$this, 'renderUpdateProductVariant'],
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
            ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products/(?P<id>\d+)/image', [
            'methods' => 'POST',
            'callback' => [$this, 'renderImageProduct'],
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
            ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products/archive/validate', [
            'methods' => 'POST',
            'callback' => [$this, 'renderArchiveValidation'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/images/create', [
            'methods' => 'POST',
            'callback' => [$this, 'renderCreateImage'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products/(?P<id>\d+)/archive', [
            'methods' => 'POST',
            'callback' => [$this, 'renderArchiveProduct'],
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
            ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products/link-variants', [
            'methods' => 'POST',
            'callback' => [$this, 'renderLinkVariants'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/products/upsert-bulk', [
            'methods' => 'POST',
            'callback' => [$this, 'renderUpsertBulkProducts'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/stock-locations/create', [
            'methods' => 'POST',
            'callback' => [$this, 'renderCreateStockLocation'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('storelinkr/v1', '/stock-locations/(?P<id>\d+)/update', [
            'methods' => 'PUT',
            'callback' => [$this, 'renderUpdateStockLocation'],
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
            ],
            'permission_callback' => '__return_true',
        ]);
    }

    public function renderTestConnection(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);

            $upload_dir_info = wp_upload_dir();

            return [
                'status' => 'success',
                'version' => esc_attr($this->version),
                'domain' => get_site_url(),
                'writable' => is_writable($upload_dir_info['basedir']),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderCategories(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);

            return [
                'status' => 'success',
                'categories' => $this->eCommerceService->getCategories(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderCategoriesCreate(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'import_source',
                'name',
                'slug',
                'parentId',
            ]);

            $category = $this->eCommerceService->createCategory(
                $request['import_source'],
                $request['name'],
                $request['slug'],
                $request['parentId'],
            );

            return [
                'status' => 'success',
                'category' => [
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'url' => get_term_link($category),
                    'parent' => $category->parent,
                ],
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderCategoryUpdate(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'name',
                'parentId',
            ]);

            $category = $this->eCommerceService->findCategory((int)$request['id']);

            if (!$category instanceof WP_Term) {
                throw new Exception('Invalid category!');
            }

            $this->eCommerceService->updateCategory(
                $category,
                $request['name'],
                $request['parentId'],
            );

            return [
                'status' => 'success',
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderOrders(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);

            return [
                'status' => 'success',
                'orders' => $this->eCommerceService->getOrders(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderOrderCreate(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'uuid',
                'first_name',
                'last_name',
                'currency',
                'mail_address',
                'billing_address',
                'shipping_address',
            ]);

            $orderId = $this->eCommerceService->createOrder($request);

            return [
                'status' => 'success',
                'id' => $orderId,
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderListProducts(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);

            $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
            $posts_per_page = 250;

            $args = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $posts_per_page,
                'paged' => $page,
            ];

            $query = new WP_Query($args);
            $products = $query->posts;
            $total_records = $query->found_posts;
            $total_pages = $query->max_num_pages;

            $data = [
                'status' => 'success',
                'total_records' => $total_records,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'products' => [],
            ];

            foreach ($products as $product_post) {
                $product = wc_get_product($product_post->ID);
                $price_cents = round(floatval($product->get_price()) * 100);
                $promo_price_cents = round(floatval($product->get_sale_price()) * 100);

                $categories = get_the_terms($product->get_id(), 'product_cat');
                $category_hierarchy = array();
                if ($categories && !is_wp_error($categories)) {
                    $category_hierarchy = array_pad(
                        wp_list_pluck($categories, 'name'),
                        5,
                        null
                    );
                }

                $images = [];
                $image_ids = array_unique(array_merge([$product->get_image_id()], $product->get_gallery_image_ids()));
                foreach ($image_ids as $image_id) {
                    if (empty($image_id)) {
                        continue;
                    }

                    $images[] = [
                        'url' => wp_get_attachment_url($image_id),
                        'id' => $image_id,
                        'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                    ];
                }

                $facets = [];
                foreach ($product->get_attributes() as $facet) {
                    assert($facet instanceof WC_Product_Attribute);

                    $facets[] = [
                        'name' => $facet->get_name(),
                        'values' => $facet->get_options(),
                        'visible' => $facet->get_visible(),
                        'variation' => $facet->get_variation(),
                    ];
                }

                $data['products'][] = [
                    'id' => $product->get_id(),
                    'url' => get_permalink($product->get_id()),
                    'product_type' => $product::class,
                    'catalog_visibility' => $product->get_catalog_visibility(),
                    'virtual' => $product->is_virtual(),
                    'downloadable' => $product->is_downloadable(),
                    'featured' => $product->is_featured(),
                    'ean' => $product->get_global_unique_id(),
                    'sku' => $product->get_sku(),
                    'brand' => $product->get_attribute('pa_brand'),
                    'title' => get_the_title($product->get_id()),
                    'name' => $product->get_name(),
                    'short_description' => $product->get_short_description(),
                    'description' => $product->get_description(),
                    'price_cents' => $price_cents,
                    'promo_price_cents' => $promo_price_cents,
                    'stock' => [
                        'stock_management' => $product->managing_stock(),
                        'has_stock' => $product->is_in_stock(),
                        'quantity' => $product->get_stock_quantity(),
                        'allow_backorder' => $product->backorders_allowed(),
                    ],
                    'dimensions' => [
                        'length' => $product->get_length(),
                        'width' => $product->get_width(),
                        'height' => $product->get_height(),
                        'unit' => get_option('woocommerce_dimension_unit'),
                    ],
                    'weight' => [
                        'weight' => $product->get_weight(),
                        'unit' => get_option('woocommerce_weight_unit'),
                    ],
                    'category_main' => $category_hierarchy[0] ?? null,
                    'category_sub' => $category_hierarchy[1] ?? null,
                    'category_sub_sub' => $category_hierarchy[2] ?? null,
                    'category_sub_sub_sub' => $category_hierarchy[3] ?? null,
                    'category_sub_sub_sub_sub' => $category_hierarchy[4] ?? null,
                    'images' => $images,
                    'facets' => $facets,
                    'rating' => [
                        'total' => $product->get_rating_count(),
                        'average' => $product->get_average_rating(),
                    ],
                ];
            }

            return new WP_REST_Response($data, 200);
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderCreateProduct(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'name',
                'ean',
                'site',
                'importSource',
                'salesPrice',
                'categoryId',
                'inStock',
                'stockSupplier',
                'hasStock',
            ]);

            $product = $this->eCommerceService->mapProductFromDataArray(
                $this->convertRequestToArray($request)
            );

            $gallery = (array)$request->get_param('images');
            $this->eCommerceService->linkProductGalleryImages($product, $gallery);
            $productId = $this->eCommerceService->saveProduct(
                $product,
                $request['facets'],
                (isset($request['brand'])) ? $request['brand'] : null
            );

            return [
                'status' => 'success',
                'type' => $product::class,
                'product_id' => $productId,
                'url' => get_permalink($productId),
                'warnings' => $this->eCommerceService->getWarnings(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderUpdateProduct(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'name',
                'ean',
                'site',
                'importSource',
                'salesPrice',
                'categoryId',
                'id',
                'inStock',
                'stockSupplier',
                'hasStock',
            ]);

            $product = $this->eCommerceService->mapProductFromDataArray(
                $this->convertRequestToArray($request)
            );

            $gallery = (array)$request->get_param('images');
            $this->eCommerceService->linkProductGalleryImages($product, $gallery);
            $productId = $this->eCommerceService->saveProduct(
                $product,
                $request['facets'],
                (isset($request['brand'])) ? $request['brand'] : null
            );

            $invalidMediaIds = [];
            foreach ($gallery as $mediaId) {
                $mediaItem = get_post($mediaId);
                if ($mediaItem && $mediaItem->post_type === 'attachment') {
                    $filePath = get_attached_file($mediaId);

                    if (!$filePath || !file_exists($filePath)) {
                        $invalidMediaIds[] = $mediaId;
                    }

                    continue;
                }

                $invalidMediaIds[] = $mediaId;
            }

            return [
                'status' => 'success',
                'type' => $product::class,
                'product_id' => $productId,
                'url' => get_permalink($productId),
                'images' => $gallery,
                'invalid_media' => $invalidMediaIds,
                'warnings' => $this->eCommerceService->getWarnings(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderCreateProductVariant(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'name',
                'products',
                'options',
                'categories',
                'importSource',
            ]);

            $product = $this->eCommerceService->mapProductFromDataArray(
                $this->convertRequestToArray($request),
                'variant'
            );
            assert($product instanceof WC_Product_Variable);

            $product->set_category_ids($request->get_param('categories'));

            $productId = $this->eCommerceService->saveProduct(
                $product,
                $request['facets'],
                (isset($request['brand'])) ? $request['brand'] : null
            );

            $variationMap = $this->eCommerceService->buildProductVariantOptions(
                $productId,
                $request->get_param('options'),
                $request->get_param('products'),
            );

            return [
                'status' => 'success',
                'type' => $product::class,
                'product_id' => $productId,
                'variant_options' => $variationMap,
                'url' => get_permalink($productId),
                'warnings' => $this->eCommerceService->getWarnings(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderUpdateProductVariant(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'name',
                'products',
                'options',
                'categories',
                'importSource',
            ]);

            $product = $this->eCommerceService->mapProductFromDataArray(
                $this->convertRequestToArray($request),
                'variant'
            );
            assert($product instanceof WC_Product_Variable);

            $product->set_category_ids($request->get_param('categories'));

            $productId = $this->eCommerceService->saveProduct(
                $product,
                $request['facets'],
                (isset($request['brand'])) ? $request['brand'] : null
            );

            $variationMap = $this->eCommerceService->buildProductVariantOptions(
                $productId,
                $request->get_param('options'),
                $request->get_param('products'),
            );

            return [
                'status' => 'success',
                'type' => $product::class,
                'product_id' => $productId,
                'variant_options' => $variationMap,
                'url' => get_permalink($productId),
                'warnings' => $this->eCommerceService->getWarnings(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderImageProduct(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'id',
                'alt',
            ]);

            $product = $this->eCommerceService->findProduct($request['id']);
            $mediaId = $this->eCommerceService->saveProductImage(
                $product,
                $request
            );

            $gallery = (array)$request->get_param('imageGallery');

            if (!empty($mediaId)) {
                $gallery = array_merge($gallery, [$mediaId]);
            }

            $this->eCommerceService->linkProductGalleryImages($product, $gallery);
            $product->save();

            return [
                'status' => 'success',
                'product' => [
                    'main' => wp_get_attachment_url($product->get_image_id()),
                    'gallery' => $product->get_gallery_image_ids(),
                    'thumb' => get_post_thumbnail_id($product),
                    'gallery_debug' => $gallery,
                ],
                'image_id' => $mediaId,
                'image_url' => wp_get_attachment_url($mediaId),
                'warnings' => $this->eCommerceService->getWarnings(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderArchiveValidation(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'products',
            ]);

            $archivedProducts = [];

            foreach ($request['products'] as $productData) {

                if (!empty($productData['ean'])) {
                    $product = $this->eCommerceService->findProductByEan($productData['ean']);
                } elseif (!empty($productData['sku'])) {
                    $product = $this->eCommerceService->findProductBySku($productData['sku']);
                }

                if (!empty($product)) {
                    $product->set_status('trash');
                    $product->save();

                    $archivedProducts[] = [
                        'ean' => $product->get_global_unique_id(),
                        'sku' => $product->get_sku(),
                        'post_id' => $product->get_id(),
                        'post_type' => $product->get_type(),
                    ];
                    continue;
                }

                $notFound[] = $productData;
            }

            return [
                'archived' => $archivedProducts,
                'not_found' => $notFound,
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderCreateImage(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'alt',
            ]);

            $mediaId = $this->createStandaloneImage($request);

            return [
                'status' => 'success',
                'image_id' => $mediaId,
                'media_id' => $mediaId, // Include media_id as requested
                'image_url' => wp_get_attachment_url($mediaId),
                'warnings' => $this->eCommerceService->getWarnings(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    private function createStandaloneImage(WP_REST_Request $request): int
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
                throw new Exception('Image download failed: ' . $response->get_error_message());
            }

            $imageContent = wp_remote_retrieve_body($response);
        }

        if (empty($request['cdn_url']) && empty($request['imageContent'])) {
            throw new Exception('Please set the image content or provide a CDN url!');
        }

        if (empty($imageContent)) {
            $imageContent = $request['imageContent'];
        }

        if (empty($imageContent)) {
            throw new Exception('Empty image content!');
        }

        // Generate filename using alt text or timestamp
        $alt = sanitize_text_field($request['alt']);
        $filename = sprintf(
            'storelinkr_%s_%d.jpg',
            sanitize_file_name($alt ?: 'image'),
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
            'post_title' => $alt,
            'post_excerpt' => $alt,
            'post_content' => $alt,
            'post_status' => 'inherit',
            'guid' => $upload['url']
        ];

        $mediaId = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($mediaId)) {
            throw new Exception('Image error: ' . $mediaId->get_error_message());
        }

        $attach_data = wp_generate_attachment_metadata($mediaId, $file_path);
        wp_update_attachment_metadata($mediaId, $attach_data);
        update_post_meta($mediaId, '_wp_attachment_image_alt', $alt);

        return $mediaId;
    }

    public function renderArchiveProduct(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'id',
            ]);

            $product = $this->eCommerceService->findProduct($request['id']);
            $product->set_status('trash');
            $product->save();

            return [
                'status' => 'success',
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderLinkVariants(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'variant',
                'linkProducts',
            ]);

            $groupedVariant = isset($request['groupedVariant']) && (bool)$request['groupedVariant'];

            // Validate and filter linkProducts to ensure only valid product IDs
            $linkProducts = [];
            if (isset($request['linkProducts']) && is_array($request['linkProducts'])) {
                foreach ($request['linkProducts'] as $productId) {
                    try {
                        $this->eCommerceService->findProduct($productId);
                        $linkProducts[] = $productId;
                    } catch (\Exception $exception) {
                        $this->eCommerceService->logWarning(
                            $exception->getMessage(),
                        );
                    }
                }
            }

            // Validate and filter removeProducts to ensure only valid product IDs
            $removeProducts = [];
            if (isset($request['removeProducts']) && is_array($request['removeProducts'])) {
                foreach ($request['removeProducts'] as $productId) {
                    try {
                        $this->eCommerceService->findProduct($productId);
                        $removeProducts[] = $productId;
                    } catch (\Exception $exception) {
                        $this->eCommerceService->logWarning(
                            $exception->getMessage(),
                        );
                    }
                }
            }

            // Extract variant ID from either top-level 'id' parameter or nested 'variant.id'
            $extractedVariantId = null;
            if (isset($request['variant']['id'])) {
                $extractedVariantId = (int)$request['variant']['id'];
            } elseif (isset($request['id'])) {
                $extractedVariantId = (int)$request['id'];
            }

            $variantId = $this->eCommerceService->linkProductsAsVariant(
                $linkProducts,
                $removeProducts,
                $groupedVariant,
                (isset($request['variant'])) ? (array)$request['variant'] : [],
                $extractedVariantId,
                (!empty($request['images'])) ? $request['images'] : [],
                (!empty($request['facets'])) ? $request['facets'] : [],
            );

            return [
                'status' => 'success',
                'variant_id' => ($groupedVariant === true) ? $variantId : null,
                'variant_url' => ($groupedVariant === true && !empty($variantId))
                    ? get_permalink($variantId) : null,
                'link_to' => $linkProducts,
                'remove_from' => $removeProducts,
                'warnings' => $this->eCommerceService->getWarnings(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderUpsertBulkProducts(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'products',
            ]);

            if (!is_array($request->get_param('products'))) {
                throw new Exception('Products is not an array!');
            }

            $result = [];
            foreach ($request->get_param('products') as $uuid => $rawProduct) {
                try {
                    $rawProduct = (array)$rawProduct;
                    $product = $this->eCommerceService->mapProductFromDataArray($rawProduct);
                    $gallery = [];
                    if (isset($rawProduct['images'])) {
                        $gallery = (array)$rawProduct['images'];
                    }
                    $this->eCommerceService->linkProductGalleryImages($product, $gallery);
                    $productId = $this->eCommerceService->saveProduct(
                        $product,
                        (!empty($rawProduct['facets']) && is_array($rawProduct['facets'])) ? $rawProduct['facets'] : [],
                        (isset($rawProduct['brand'])) ? $rawProduct['brand'] : null
                    );

                    $result[$uuid] = [
                        'product_id' => $productId,
                        'url' => get_permalink($productId),
                    ];
                } catch (\Exception $exception) {
                    $result[$uuid] = [
                        'error' => $exception->getMessage(),
                        'product_uuid' => $uuid,
                    ];
                }
            }

            return [
                'status' => 'success',
                'products' => $result,
                'warnings' => $this->eCommerceService->getWarnings(),
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderCreateStockLocation(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'name',
            ]);

            $post_id = wp_insert_post([
                'post_type' => 'sl_stock_location',
                'post_title' => $request->get_param('name'),
                'post_status' => 'publish',
            ]);

            if ($post_id instanceof WP_Error || $post_id === 0) {
                throw new Exception('Stock location could not be saved!');
            }

            $this->updateStockLocationMetaFields($post_id, $request);

            return [
                'status' => 'success',
                'location' => [
                    'id' => $post_id,
                ],
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    public function renderUpdateStockLocation(WP_REST_Request $request)
    {
        try {
            $this->authenticateRequest($request);
            $this->validateRequiredFields($request, [
                'id',
                'name',
            ]);

            wp_insert_post([
                'ID' => $request->get_param('id'),
                'post_type' => 'sl_stock_location',
                'post_title' => $request->get_param('name'),
                'post_status' => 'publish',
            ]);
            $this->updateStockLocationMetaFields($request->get_param('id'), $request);

            return [
                'status' => 'success',
                'location' => [
                    'id' => $request->get_param('id'),
                ],
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
    }

    private function renderError(string $message): WP_Error
    {
        if ($message === self::MESSAGE_UNAUTHORIZED) {
            return new WP_Error(
                'rest_forbidden',
                'You do not have permissions to view this data.', ['status' => 401]
            );
        }

        return new WP_Error(
            400,
            esc_attr($message),
            [
                'status' => 'failed',
                'message' => esc_attr($message),
            ]
        );
    }

    private function authenticateRequest(WP_REST_Request $request)
    {
        $request->get_header('x-api-key');
        $request->get_header('x-api-secret');

        if (empty($request->get_header('x-api-key'))
            || $request->get_header('x-api-key') !== get_option(StoreLinkrAdmin::STORELINKR_API_KEY)) {
            throw new Exception(esc_attr(self::MESSAGE_UNAUTHORIZED));
        }

        if (empty($request->get_header('x-api-secret'))
            || $request->get_header('x-api-secret') !== get_option(StoreLinkrAdmin::STORELINKR_API_SECRET)) {
            throw new Exception(esc_attr(self::MESSAGE_UNAUTHORIZED));
        }
    }

    private function validateRequiredFields(WP_REST_Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($request[$field])) {
                throw new Exception('Missing data field: ' . esc_attr($field));
            }
        }
    }

    private function convertRequestToArray(WP_REST_Request $request): array
    {
        return [
            'updateStock' => $request->get_param('updateStock'),
            'updatePrice' => $request->get_param('updatePrice'),
            'sku' => $request->get_param('sku'),
            'id' => $request->get_param('id'),
            'ean' => $request->get_param('ean'),
            'name' => $request->get_param('name'),
            'salesPrice' => $request->get_param('salesPrice'),
            'promoSalesPrice' => $request->get_param('promoSalesPrice'),
            'promoStart' => $request->get_param('promoStart'),
            'promoEnd' => $request->get_param('promoEnd'),
            'shortDescription' => $request->get_param('shortDescription'),
            'longDescription' => $request->get_param('longDescription'),
            'categoryId' => $request->get_param('categoryId'),
            'hasStock' => $request->get_param('hasStock'),
            'inStock' => $request->get_param('inStock'),
            'stockSupplier' => $request->get_param('stockSupplier'),
            'metadata' => $request->get_param('metadata'),
            'importSource' => $request->get_param('importSource'),
            'site' => $request->get_param('site'),
            'stockLocations' => $request->get_param('stockLocations'),
            'attachments' => $request->get_param('attachments'),
            'images' => $request->get_param('images'),
            'facets' => $request->get_param('facets'),
            'isUsed' => $request->get_param('isUsed'),
        ];
    }

    private function updateStockLocationMetaFields(WP_Error|int $post_id, WP_REST_Request $request): void
    {
        if (!empty($request->get_param('address'))) {
            update_post_meta($post_id, '_address', $request->get_param('address'));
        }
        if (!empty($request->get_param('city'))) {
            update_post_meta($post_id, '_city', $request->get_param('city'));
        }
        if (!empty($request->get_param('postal_code'))) {
            update_post_meta($post_id, '_postal_code', $request->get_param('postal_code'));
        }
        if (!empty($request->get_param('country_code'))) {
            update_post_meta($post_id, '_country_code', $request->get_param('country_code'));
        }
        if (!empty($request->get_param('phone_number'))) {
            update_post_meta($post_id, '_phone_number', $request->get_param('phone_number'));
        }
    }

}
