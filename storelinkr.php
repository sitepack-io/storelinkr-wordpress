<?php
/**
 * @package storelinkr
 */

/*
Plugin Name: StoreLinkr
Plugin URI: https://storelinkr.com/en/integrations/wordpress-woocommerce-dropshipment
Description: Stop manual work: the all-in-one platform for complete online store automation. Integrate with marketplaces, product feeds, and suppliers.
Version: 2.9.2
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
define('STORELINKR_VERSION', '2.9.2');
define('STORELINKR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STORELINKR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr.php');
require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-rest-api.php');
require_once(STORELINKR_PLUGIN_DIR . 'class.storelinkr-frontend.php');

add_action('init', ['StoreLinkr', 'init']);

$storelinkrRestApi = new StoreLinkrRestApi(STORELINKR_VERSION);
add_action('rest_api_init', [$storelinkrRestApi, 'init']);
add_action('wp_ajax_storelinkr_product_stock', 'storelinkrStockAjaxHandler');
add_action('wp_ajax_nopriv_storelinkr_product_stock', 'storelinkrStockAjaxHandler');

add_filter('woocommerce_product_tabs', 'storelinkrProductTabs', 10, 2);
add_filter('rest_authentication_errors', 'storelinkrWooCommerceRestApi');

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

if (!function_exists('storelinkrWooCommerceRestApi')) {
    function storelinkrWooCommerceRestApi($result)
    {
        if (!empty($result)) {
            return $result;
        }

        if (is_user_logged_in()) {
            return $result;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        $auth = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

        if (str_starts_with($auth, 'Basic ') || str_starts_with($auth, 'Baerer')) {
            $validToken = base64_encode(
                get_option(StoreLinkrAdmin::STORELINKR_API_KEY) . ':' .
                get_option(StoreLinkrAdmin::STORELINKR_API_SECRET)
            );

            $inputToken = str_replace(['Basic ', 'Baerer '], '', $auth);
            if ($inputToken === $validToken) {
                $shop_users = get_users([
                    'role__in' => ['shop_manager', 'administrator'],
                    'number' => 1,
                    'orderby' => 'ID',
                    'order' => 'ASC',
                    'fields' => ['ID'],
                ]);

                if (!empty($shop_users)) {
                    $userId = $shop_users[0]->ID;

                    wp_set_current_user($userId);

                    return new WP_User($userId);
                } else {
                    return new WP_Error('rest_forbidden', __('No admin user found', 'storelinkr'), ['status' => 403]);
                }
            }
        }

        return $result;
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
    add_filter('woocommerce_single_product_summary', 'storeLinkrStockLocations', 32);
    add_action('wp_head', 'storeLinkrCustomProductPageCss');
    add_action('wp_head', 'storeLinkrCustomProductPageStockCss');

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

    function storeLinkrStockLocations()
    {
        global $product;

        if (!empty($product->get_meta('stock_locations', null))) {
            $data = $product->get_meta('stock_locations', null);
            if (!is_array($data)) {
                return;
            }
            $locations = current($data);

            if (!$locations instanceof WC_Meta_Data || !isset($locations->get_data()['value'])) {
                return;
            }

            $html = '<table id="sl-product-stock-locations">';
            foreach ($locations->get_data()['value'] as $location) {
                if (empty($location['name'])) {
                    continue;
                }

                $html .= '<tr>';
                $html .= '<td>' . esc_html($location['name']) . '</td>';
                $html .= '<td class="sl-text-right">';
                if ((int)$location['quantity'] === 0) {
                    $html .= '<span class="sl-sold-out">' . __('Out of stock', 'storelinkr') . '</span>';
                } else {
                    $html .= '<span class="sl-in-stock">' . __('In stock', 'storelinkr') . '</span>';
                    $html .= ' <span class="sl-muted">' . esc_attr($location['quantity']);
                    $html .= ' ' . __('piece(s)', 'storelinkr') . '</span>';
                }
                $html .= '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';

            echo apply_filters('storelinkr_stock_html', $html, $product);
        }
    }

    function storeLinkrCustomProductPageStockCss()
    {
        if (is_product()) {
            $css = '#sl-product-stock-locations { width: 100%; }';
            $css .= '#sl-product-stock-locations tr td.sl-text-right { text-align: right; }';
            $css .= '#sl-product-stock-locations tr td span.sl-sold-out { color: #a94442 }';
            $css .= '#sl-product-stock-locations tr td span.sl-in-stock { color: #3c763d }';
            $css .= '#sl-product-stock-locations tr td span.sl-muted { color: #777 }';

            $css = apply_filters('storelinkr_stock_css', $css);

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

if (!function_exists('storelinkrAddLinkBeforeGroupedProductLabel')) {
    add_action('woocommerce_grouped_product_list_column_label', 'storelinkrAddLinkBeforeGroupedProductLabel', 10, 2);

    function storelinkrAddLinkBeforeGroupedProductLabel($value, $grouped_product)
    {
        $product_id = $grouped_product->get_id();
        $product = wc_get_product($product_id);

        if ($product) {
            $product_link = get_permalink($product_id);

            return '<a href="' . esc_url($product_link) . '">' . esc_html($product->get_name()) . '</a>';
        }

        return $value;
    }
}
