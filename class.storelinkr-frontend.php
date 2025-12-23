<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

class StoreLinkrFrontend
{

    public function init()
    {
        add_action('woocommerce_single_product_summary', [$this, 'storelinkrCustomProductCode'], 33);
        add_filter('woocommerce_get_availability_text', [$this, 'storelinkrProductAvailability'], 99, 2);
        add_action('wp_enqueue_scripts', [$this, 'storelinkrEnqueueScripts']);
        add_action('wp_enqueue_scripts', [$this, 'storelinkrEnqueueStyles']);
    }

    public function storelinkrProductAvailability($availability, $product): string
    {
        return $availability;
    }

    public function storelinkrCustomProductCode(): void
    {
        global $product;

        echo '<div id="storelinkrStockLocations"></div>';
        echo '<script>
var ajaxurl = "' . esc_url(admin_url('admin-ajax.php')) . '";
var data = {
    action: "storelinkr_product_stock",
    product_id: ' . esc_attr($product->get_id()) . ',
    nonce: "' . esc_attr(wp_create_nonce('storelinkr_product_stock')) . '",
};

fetch(ajaxurl, {
    method: "POST",
    headers: {
        "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams(data),
})
.then(function(response) {
    if (response.ok) {
        return response.json();
    } else {
        throw new Error("Error: " + response.status);
    }
})
.then(function(data) {
    storelinkrDisplayStockLocations(data.data.stock);
})
.catch(function(error) {
    console.log(error);
});
</script>';
    }

    public function storelinkrEnqueueScripts()
    {
        wp_enqueue_script(
            'storelinkr-stock',
            plugins_url('/assets/storelinkr_stock.js', STORELINKR_PLUGIN_FILE),
            ['jquery'],
            1.0,
            true
        );
        wp_localize_script('sitpack-connect', 'productStockNonce', [
            'ajax_nonce_storelinkr' => wp_create_nonce('storelinkr_product_stock'),
        ]);
    }

    public function storelinkrEnqueueStyles()
    {
        wp_enqueue_style(
            'storelinkr-stock-styles',
            plugins_url('/assets/storelinkr_stock.css', STORELINKR_PLUGIN_FILE)
        );
    }

}

