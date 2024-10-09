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
                'shortDescription',
                'longDescription',
                'categoryId',
                'inStock',
                'stockSupplier',
                'hasStock',
            ]);

            $product = $this->eCommerceService->mapProductFromData(
                $request
            );

            $gallery = (array)$request->get_param('images');
            $this->eCommerceService->linkProductGalleryImages($product, $gallery);
            $productId = $this->eCommerceService->saveProduct($request, $product);

            $this->eCommerceService->mergeDuplicateAttributes();

            return [
                'status' => 'success',
                'product_id' => $productId,
                'url' => get_permalink($productId),
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
                'shortDescription',
                'longDescription',
                'categoryId',
                'id',
                'inStock',
                'stockSupplier',
                'hasStock',
            ]);

            $product = $this->eCommerceService->mapProductFromData(
                $request
            );

            $gallery = (array)$request->get_param('images');
            $this->eCommerceService->linkProductGalleryImages($product, $gallery);
            $productId = $this->eCommerceService->saveProduct($request, $product);

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

            $this->eCommerceService->mergeDuplicateAttributes();

            return [
                'status' => 'success',
                'product_id' => $productId,
                'url' => get_permalink($productId),
                'images' => $gallery,
                'invalid_media' => $invalidMediaIds,
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
                'imageContent',
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
            ];
        } catch (\Exception $exception) {
            return $this->renderError($exception->getMessage());
        }
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
            ]);

            $this->eCommerceService->linkProductsAsVariant(
                (!empty($request['products'])) ? (array)$request['products'] : [],
                (!empty($request['removeProducts'])) ? (array)$request['removeProducts'] : []
            );
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

}
