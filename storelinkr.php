<?php
/**
 * @package storelinkr
 */

/*
Plugin Name: StoreLinkr
Plugin URI: https://storelinkr.com/en/integrations/wordpress-woocommerce-dropshipment
Description: Streamline dropshipping effortlessly! Sync with wholesalers, POS systems & suppliers for seamless product updates and order management. Start now!
Version: 2.3.5
Author: StoreLinkr
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
define('STORELINKR_VERSION', '2.3.5');
define('STORELINKR_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr.php');
require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-rest-api.php');
require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-frontend.php');

add_action('init', ['StoreLinkr', 'init']);

$storelinkrRestApi = new StoreLinkrRestApi(STORELINKR_VERSION);
add_action('rest_api_init', [$storelinkrRestApi, 'init']);
add_action('wp_ajax_storelinkr_product_stock', 'storelinkrStockAjaxHandler');
add_action('wp_ajax_nopriv_storelinkr_product_stock', 'storelinkrStockAjaxHandler');

add_filter('woocommerce_product_tabs', 'storelinkrProductTabs', 10, 2);

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

if (!function_exists('storeLinkrVariantDropdown')) {
    add_filter('woocommerce_single_product_summary', 'storeLinkrVariantDropdown', 25);
    add_action('wp_head', 'storeLinkrCustomProductPageCss');

    function storeLinkrVariantDropdown()
    {
        global $product;

        $current_product_id = $product->get_id();
        $variant_ids = get_post_meta($current_product_id, 'storelinkr_variant_ids', true);

        if (is_array($variant_ids) && count($variant_ids) >= 2) {
            $variants = [];
            foreach ($variant_ids as $id) {
                $variant_product = wc_get_product($id);
                if ($variant_product) {
                    $variants[$id] = $variant_product->get_name();
                }
            }

            asort($variants);

            $label = apply_filters('storelinkr_variant_dropdown_label', __('Select variant', 'storelinkr') . ':');

            $html = '<div class="variant-dropdown" id="storelinkr-variant-dropdown">';
            $html .= '<label for="product-variant-select">' . esc_attr($label) . '</label>';
            $html .= '<select id="product-variant-select" name="product-variant-select" onchange="location = this.value;">';

            foreach ($variants as $id => $name) {
                $selected = ($id == $current_product_id) ? ' selected' : '';
                $html .= '<option value="' . get_permalink($id) . '"' . $selected . '>' . $name . '</option>';
            }

            $html .= '</select>';
            $html .= '</div>';

            echo apply_filters('storelinkr_variant_html', $html);
        }
    }

    function storeLinkrCustomProductPageCss()
    {
        if (is_product()) {
            $css = '#storelinkr-variant-dropdown { width: 100%; }';
            $css .= '#storelinkr-variant-dropdown label { width: 100%; line-height: 26px; font-size: 14px; font-weight: 700; display: block; margin-bottom: 4px; }';
            $css .= '#storelinkr-variant-dropdown select { width: 100%; height: 40px; font-size: 14px; font-weight: 400; display: block; padding: 0 7px; }';

            $css = apply_filters('storelinkr_variant_css', $css);

            echo '<style>' . esc_html($css) . '</style>';
        }
    }
}

if (!function_exists('storelinkrProductTabs')) {
    function storelinkrProductTabs($tabs)
    {
        global $product;
        $attachments = $product->get_meta('_product_attachments', true);

        if (!empty($attachments)) {
            $attachments = json_decode($attachments, true);

            if (is_iterable($attachments) && count($attachments) >= 1) {
                $tabs['attachment_tab'] = [
                    'title' => esc_html(storelinkrAttachmentLabel()),
                    'priority' => 22,
                    'callback' => 'storeLinkrAttachmentTabContent',
                ];
            }
        }

        return $tabs;
    }

    function storeLinkrAttachmentTabContent()
    {
        global $product;
        $attachments = $product->get_meta('_product_attachments', true);

        echo '<h2>' . esc_html(storelinkrAttachmentLabel()) . '</h2>';

        if (!empty($attachments)) {
            $attachments = json_decode($attachments, true);

            if (is_iterable($attachments)) {
                echo '<ul class="product-attachments-list">';
                foreach ($attachments as $attachment) {
                    if (empty($attachment['cdn_url']) || empty($attachment['name'])) {
                        continue;
                    }

                    echo '<li>';
                    if (!empty($attachment['description'])) {
                        echo '<a href="' . esc_url($attachment['cdn_url']) . '" target="_blank" rel="noopener" 
                        title="' . esc_attr($attachment['title']) . ': ' . esc_attr($attachment['description']) . '">';
                    } else {
                        echo '<a href="' . esc_url($attachment['cdn_url']) . '" target="_blank" rel="noopener">';
                    }
                    if (!empty($attachment['title'])) {
                        echo esc_attr($attachment['title']);
                    } else {
                        echo esc_attr($attachment['name']);
                    }
                    echo '</a></li>';
                }
                echo '</ul>';
            }
        } else {
            echo '<p>' . __('No attachments available for this product.', 'storelinkr') . '</p>';
        }
    }

    function storelinkrAttachmentLabel(): string
    {
        return apply_filters('storelinkr_attachment_label', __('Attachments', 'storelinkr'));
    }
}

if (!function_exists('storelinkrRestApiResponseHeaders')) {
    function storelinkrRestApiResponseHeaders($response, $server, $request)
    {
        $route = $request->get_route();
        if (str_contains($route, '/storelinkr/') === true) {
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            $response->header('X-StoreLinkr-Cache', 'no-cache');
        }

        return $response;
    }

    add_filter('rest_post_dispatch', 'storelinkrRestApiResponseHeaders', 10, 3);
}
