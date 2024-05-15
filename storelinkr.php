<?php
/**
 * @package storelinkr
 */

/*
Plugin Name: StoreLinkr
Plugin URI: https://storelinkr.com/en/integrations/wordpress-woocommerce-dropshipment
Description: Streamline dropshipping effortlessly! Sync with wholesalers, POS systems & suppliers for seamless product updates and order management. Start now!
Version: 2.0.15
Author: storelinkr, powered by SitePack B.V.
Author URI: https://storelinkr.com
License: GPLv2 or later
Text Domain: storelinkr
*/

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

define('STORELINKR_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('STORELINKR_PLUGIN_FILE', __FILE__);
define('STORELINKR_VERSION', '2.0.15');
define('STORELINKR_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr.php');
require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-rest-api.php');
require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-frontend.php');

add_action('init', ['StoreLinkr', 'init']);

$storelinkrRestApi = new StoreLinkrRestApi(STORELINKR_VERSION);
add_action('rest_api_init', [$storelinkrRestApi, 'init']);
add_action('wp_ajax_storelinkr_product_stock', 'storelinkrStockAjaxHandler');
add_action('wp_ajax_nopriv_storelinkr_product_stock', 'storelinkrStockAjaxHandler');

if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
    require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-admin.php');
    $admin = new StoreLinkrAdmin();
    $admin->init();
}

if (is_admin() === false) {
    $storelinkrFrontend = new StoreLinkrFrontend();
    $storelinkrFrontend->init();
}

if (!function_exists('storelinkrWooIsActive')) {
    function storelinkrWooIsActive()
    {
        if (class_exists('woocommerce')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('storeLinkrGetProductStockInformation')) {
    /**
     * Fetch the live stock information
     *
     * @param int $productId
     * @return ?StoreLinkrStock
     */
    function storeLinkrGetProductStockInformation(int $productId): ?StoreLinkrStock
    {
        $storelinkrStock = StoreLinkr::getInstance();
        $product = wc_get_product($productId);

        if (!$product instanceof WC_Product) {
            return null;
        }

        if (empty($product->get_meta('site'))
            || empty($product->get_meta('import_source'))
            || empty($product->get_meta('ean'))
        ) {
            return null;
        }

        return $storelinkrStock->fetchLiveStock(
            $product->get_meta('site'),
            $product->get_meta('import_source'),
            $product->get_meta('ean')
        );
    }
}

if (!function_exists('storelinkrStockAjaxHandler')) {
    function storelinkrStockAjaxHandler(): void
    {
        try {
            if (!isset($_POST['product_id']) || !isset($_POST['nonce'])) {
                throw new Exception('Empty product id or nonce!');
            }

            $productId = sanitize_text_field(wp_unslash($_POST['product_id']));
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));

            if (empty($productId)) {
                throw new Exception('Empty product id!');
            }

            if (empty($nonce)) {
                throw new Exception('Empty token!');
            }

            $productId = (int)$productId;

            if (!wp_verify_nonce($nonce, 'storelinkr_product_stock')) {
                throw new Exception('Invalid nonce given! For key: ' . esc_attr($productId) . ' . ' . esc_attr($nonce));
            }

            $cached = get_transient('storelinkr_ajax_stock_' . $productId);
            $isCached = false;
            if ($cached !== false) {
                $stock = $cached;
                $isCached = true;
            } else {
                $stock = storeLinkrGetProductStockInformation($productId);

                set_transient('storelinkr_ajax_stock_' . $productId, $stock, 10 * 60);
            }

            $response = [
                'success' => true,
                'stock' => $stock,
                'is_cached' => $isCached,
            ];

            wp_send_json_success($response);
            wp_die();
        } catch (\Exception $exception) {
            $response = [
                'success' => false,
                'message' => $exception->getMessage(),
            ];

            wp_send_json_success($response);
            wp_die();
        }
    }
}
