<?php
/**
 * @package storelinkr
 */

/*
Plugin Name: Storelinkr
Plugin URI: https://storelinkr.com/en/integrations/wordpress
Description: Dropshipping made easy with storelinkr. Integrate with wholesalers, POS systems and suppliers. We synchronize products, live stock information and orders. Get started today!
Version: 2.0.3
Author: Storelinkr, powered by SitePack B.V.
Author URI: https://storelinkr.com
License: GPLv2 or later
Text Domain: storelinkr
*/

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    echo 'Hi there!  I\'m just a plugin, please visit our site if you want to: storelinkr.com.';
    exit;
}

define('STORELINKR_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('STORELINKR_PLUGIN_FILE', __FILE__);
define('STORELINKR_VERSION', '2.0.3');
define('STORELINKR_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr.php');
require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-rest-api.php');
require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-frontend.php');

add_action('init', ['StoreLinkr', 'init']);

$storelinkrRestApi = new StoreLinkrRestApi();
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

if (!function_exists('spWooIsActive')) {
    function spWooIsActive()
    {
        if (class_exists('woocommerce')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('spGetProductStockInformation')) {
    /**
     * Fetch the live stock information
     *
     * @param int $productId
     * @return ?StoreLinkrStock
     */
    function spGetProductStockInformation(int $productId): ?StoreLinkrStock
    {
        $connect = StoreLinkr::getInstance();
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

        return $connect->fetchLiveStock(
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
            if (empty($_POST['product_id'])) {
                throw new Exception('Empty product id!');
            }
            if (empty($_POST['nonce'])) {
                throw new Exception('Empty token!');
            }

            $productId = (int)$_POST['product_id'];

            if (!wp_verify_nonce($_POST['nonce'], 'storelinkr_product_stock')) {
                throw new Exception('Invalid nonce given! For key: ' . $productId . ' . ' . $_POST['nonce']);
            }

            $cached = get_transient('storelinkr_ajax_stock_' . $productId);
            $isCached = false;
            if ($cached !== false) {
                $stock = $cached;
                $isCached = true;
            } else {
                $stock = spGetProductStockInformation($productId);

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
