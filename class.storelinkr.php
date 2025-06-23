<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

include STORELINKR_PLUGIN_DIR . 'models/class.storelinkr-stock.php';
include STORELINKR_PLUGIN_DIR . 'models/class.storelinkr-stock-location.php';

class StoreLinkr
{

    private static ?StoreLinkr $instance = null;

    public static function getInstance(): StoreLinkr
    {
        if (!isset(self::$instance)) {
            self::$instance = new StoreLinkr();
            self::$instance->init();
        }

        return self::$instance;
    }

    public static function init()
    {
        self::registerStockLocationPostType();
    }

    private static function registerStockLocationPostType()
    {
        $labels = [
            'name' => __('Stock locations', 'storelinkr'),
            'singular_name' => __('Stock location', 'storelinkr'),
            'menu_name' => __('Stock locations', 'storelinkr'),
            'name_admin_bar' => __('Stock Location', 'storelinkr'),
            'add_new' => __('Add New', 'storelinkr'),
            'add_new_item' => __('Add New Stock location', 'storelinkr'),
            'new_item' => __('New stock location', 'storelinkr'),
            'edit_item' => __('Edit stock location', 'storelinkr'),
            'view_item' => __('View stock location', 'storelinkr'),
            'all_items' => __('All stock locations', 'storelinkr'),
            'search_items' => __('Search stock locations', 'storelinkr'),
            'not_found' => __('No stock locations found.', 'storelinkr'),
            'not_found_in_trash' => __('No stock locations found in Trash.', 'storelinkr'),
        ];

        register_post_type('sl_stock_location', [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'product_stock_location',
            'capabilities' => [
                'edit_post' => 'edit_product_stock_location',
                'read_post' => 'read_product_stock_location',
                'edit_posts' => 'edit_product_stock_locations',
                'edit_others_posts' => 'edit_others_product_stock_locations',
                'publish_posts' => 'publish_product_stock_locations',
                'read_private_posts' => 'read_private_product_stock_locations',
                'create_posts' => 'do_not_allow',
                'delete_post' => 'do_not_allow',
                'delete_posts' => 'do_not_allow',
                'delete_private_posts' => 'do_not_allow',
                'delete_published_posts' => 'do_not_allow',
                'delete_others_posts' => 'do_not_allow',
            ],
            'map_meta_cap' => true,
        ]);
    }

    public function fetchLiveStock(string $siteUuid, string $importSource, string $ean): StoreLinkrStock
    {
        $storelinkrApiHost = apply_filters('storelinkr_api_hostname', 'https://api.storelinkr.com');
        $url = $storelinkrApiHost . '/api/public/products/%s/%s/%s/connect/stock';
        $url = sprintf(
            $url,
            $siteUuid,
            $importSource,
            $ean
        );

        $response = wp_remote_post($url);

        if (is_wp_error($response)) {
            $errorMessage = $response->get_error_message();

            return new StoreLinkrStock(
                false,
                0,
                0,
                0,
                false,
                [],
                null,
                $errorMessage
            );
        }

        if (empty($response['body'])) {
            return new StoreLinkrStock(
                false,
                0,
                0,
                0,
                false,
                [],
                null,
                'Empty body response'
            );
        }

        $data = json_decode($response['body'], true);

        if (!isset($data['stock']) || (!is_array($data['stock']) || $data === false)) {
            return new StoreLinkrStock(
                false,
                0,
                0,
                0,
                false,
                [],
                null,
                'Stock response is not an array'
            );
        }

        $locations = [];
        if (isset($data['stock']['locations']) && is_array($data['stock']['locations'])) {
            foreach ($data['stock']['locations'] as $dataLocation) {
                $locations[] = StoreLinkrStockLocation::fromStoreLinkrData($dataLocation);
            }
        }

        return new StoreLinkrStock(
            (bool)$data['stock']['inStock'],
            (int)$data['stock']['quantityAvailable'],
            (int)$data['stock']['quantitySupplier'],
            0, // TODO in SP API
            (bool)$data['stock']['allowBackorder'],
            $locations,
            (isset($data['stock']['deliveryDate'])) ? new \DateTimeImmutable($data['stock']['deliveryDate']) : null,
            (isset($data['stock']['errorReason'])) ? $data['stock']['errorReason'] : null,
        );
    }

}
