<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

require_once(STORELINKR_PLUGIN_DIR . 'helpers/class.storelinkr-metafield-helper.php');

class StoreLinkrWooCommerceMapper
{

    public static function convertRequestToProduct(
        WC_Product|WC_Product_Variable|WC_Product_Variation $product,
        array $data,
        array $settings = [],
        array $validCrossSellIds = [],
        array $validUpsellIds = [],
    ): WC_Product|WC_Product_Variable|WC_Product_Variation {
        $updateStockInfo = !(isset($data['updateStock'])) || (bool)$data['updateStock'];
        $updatePriceInfo = !(isset($data['updatePrice'])) || (bool)$data['updatePrice'];
        $updateShortDescription = true;
        $updateLongDescription = true;
        $allowBackOrder = true;

        // Read settings and apply them;
        if (isset($settings['overwrite_product_prices'])) {
            $updatePriceInfo = (bool)$settings['overwrite_product_prices'];
        }
        if (isset($settings['overwrite_product_stock'])) {
            $updateStockInfo = (bool)$settings['overwrite_product_stock'];
        }
        if (isset($settings['overwrite_short_description'])) {
            $updateShortDescription = (bool)$settings['overwrite_short_description'];
        }
        if (isset($settings['overwrite_long_description'])) {
            $updateLongDescription = (bool)$settings['overwrite_long_description'];
        }
        if (isset($settings['allow_backorder'])) {
            $allowBackOrder = (bool)$settings['allow_backorder'];
        }

        if (isset($data['sku']) && method_exists($product, 'set_sku')) {
            // Normalize SKU like WooCommerce does; avoid setting empty/whitespace-only values
            $normalizedSku = trim((string)$data['sku']);
            // The setter runs a uniqueness query, only call it when the SKU really changes
            if ($normalizedSku !== '' && $product->get_sku('edit') !== $normalizedSku) {
                $product->set_sku($normalizedSku);
            }
        }

        if (!empty($data['ean']) && method_exists($product, 'set_global_unique_id')) {
            // EAN is now optional; only set when valid
            if (class_exists('StoreLinkrEanHelper')) {
                // The setter scans the product lookup table (global_unique_id has no index), so only call
                // it when the EAN really changes
                if (
                    StoreLinkrEanHelper::validateBarcode($data['ean']) === true
                    && $product->get_global_unique_id('edit') !== preg_replace('/[^0-9\-]/', '', (string)$data['ean'])
                ) {
                    $product->set_global_unique_id($data['ean']);
                }
            }
        }

        // WooCommerce always derives a variation title from its parent and attributes, so setting
        // a name on a variation only marks it as changed and forces a needless save.
        if (method_exists($product, 'set_name') && !$product instanceof WC_Product_Variation) {
            $product->set_name((isset($data['name'])) ? $data['name'] : null);
        }

        if ($updatePriceInfo === true && !empty($data['salesPrice'])) {
            $product->set_regular_price(self::formatPrice((int)$data['salesPrice']));

            if (!empty($data['promoSalesPrice'])) {
                $product->set_sale_price(self::formatPrice((int)$data['promoSalesPrice']));
            } else {
                $product->set_sale_price(null);
            }

            $product->set_date_on_sale_from(null);
            $product->set_date_on_sale_to(null);
            if (!empty($data['promoStart']) && !empty($data['promoEnd'])) {
                $product->set_date_on_sale_from(
                    (new DateTimeImmutable($data['promoStart']))->format('Y-m-d H:i:s')
                );
                $product->set_date_on_sale_to(
                    (new DateTimeImmutable($data['promoEnd']))->format('Y-m-d H:i:s')
                );
            }
        } elseif ($updatePriceInfo === true && empty($data['salesPrice'])) {
            // fallback for empty price
            $product->set_regular_price(self::formatPrice(0));
            $product->set_sale_price(null);
        }

        if ($updateShortDescription === true && isset($data['shortDescription'])) {
            $product->set_short_description($data['shortDescription']);
        }

        if ($updateLongDescription === true && isset($data['longDescription'])) {
            $product->set_description($data['longDescription']);
        }

        if ($updateStockInfo === true && method_exists($product, 'set_stock_status')) {
            $product->set_manage_stock(true);
            if ($allowBackOrder === true) {
                $product->set_backorders('yes');
            } else {
                $product->set_backorders('no');
            }

            // Set each stock prop once: WC_Data keeps a prop flagged as changed once it was set to another
            // value, even when it ends up unchanged, which forced a save of every variation on each sync.
            $stockQuantity = 0;
            if (
                (isset($data['hasStock']) && (bool)$data['hasStock'] === true) ||
                (isset($data['inStock']) && (int)$data['inStock'] >= 1) ||
                (isset($data['stockSupplier']) && (int)$data['stockSupplier'] >= 1)
            ) {
                $stockQuantity = max(1, (int)($data['inStock'] ?? 0) + (int)($data['stockSupplier'] ?? 0));
            }

            // The status WooCommerce derives on save for a stock managed product (WC_Product::validate_props),
            // so an unchanged product is not flagged as changed, for example out of stock with backorders.
            if ($stockQuantity > absint(get_option('woocommerce_notify_no_stock_amount', 0))) {
                $stockStatus = 'instock';
            } elseif ($allowBackOrder === true) {
                $stockStatus = 'onbackorder';
            } else {
                $stockStatus = 'outofstock';
            }

            $product->set_stock_quantity($stockQuantity);
            $product->set_stock_status($stockStatus);
        }

        if (!empty($data['metadata'])) {
            $json = \json_decode($data['metadata'], true);

            if (is_array($json)) {
                foreach ($json as $key => $value) {
                    $product->update_meta_data($key, self::metaValue($value));
                }
            }
        }

        // Only touch the metafields when StoreLinkr sent them, older versions do not send this key
        // at all and their products should keep the metafields of the previous sync.
        if (isset($data['metafields']) && is_array($data['metafields'])) {
            StoreLinkrMetafieldHelper::applyToProduct($product, $data['metafields']);
        }

        $product->update_meta_data('import_provider', 'STORELINKR');
        $product->update_meta_data(
            'import_source',
            (isset($data['importSource'])) ? $data['importSource'] : null
        );
        $product->update_meta_data('site', (isset($data['site'])) ? $data['site'] : null);
        $product->update_meta_data('ean', (isset($data['ean'])) ? $data['ean'] : null);
        // Stored as a string, as it is read back from the database, so an unchanged value is no change
        $product->update_meta_data('used', (string)((isset($data['isUsed'])) ? (int)$data['isUsed'] : 0));

        // Older StoreLinkr versions do not send the key, their products keep what is stored.
        if (isset($data['conditionDescription'])) {
            $conditionDescription = trim((string)$data['conditionDescription']);
            if ($conditionDescription !== '') {
                $product->update_meta_data('condition_description', $conditionDescription);
            } else {
                $product->delete_meta_data('condition_description');
            }
        }

        if (isset($data['uuid'])) {
            $product->update_meta_data('uuid', $data['uuid']);
        }

        if ($updatePriceInfo === true && isset($data['advisedPrice'])) {
            $product->update_meta_data('advised_price', self::metaValue(self::formatPrice((int)$data['advisedPrice'])));
        }

        if (!empty($data['stockLocations'])) {
            $stockInfo = $data['stockLocations'];
            $stockMeta = [];

            if (is_array($stockInfo) && isset($stockInfo['locations'])) {
                $stockMeta = $stockInfo['locations'];
            }

            $product->update_meta_data('stock_locations', $stockMeta);
        }

        if (method_exists($product, 'set_date_created') && $product->get_date_created() === null) {
            $product->set_date_created((new DateTimeImmutable())->format('Y-m-d H:i:s'));
        }

        $attachments = [];
        if (!empty($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $attachment) {
                if (empty($attachment['uuid'])) {
                    continue;
                }

                if (empty($attachment['cdn_url'])) {
                    continue;
                }

                $attachments[] = [
                    'uuid' => $attachment['uuid'],
                    'name' => (!empty($attachment['name'])) ? $attachment['name'] : null,
                    'title' => (!empty($attachment['title'])) ? $attachment['title'] : null,
                    'description' => (!empty($attachment['description'])) ? $attachment['description'] : null,
                    'cdn_url' => $attachment['cdn_url'],
                ];
            }
        }

        if (isset($data['positive_points'])) {
            $product->update_meta_data('_positive_points', $data['positive_points']);
        }
        if (isset($data['negative_points'])) {
            $product->update_meta_data('_negative_points', $data['negative_points']);
        }

        $product->update_meta_data('_product_attachments', json_encode($attachments));

        if (method_exists($product, 'set_cross_sell_ids')) {
            $product->set_cross_sell_ids(array_values($validCrossSellIds));
            $product->set_upsell_ids(array_values($validUpsellIds));
        }

        return $product;
    }


    /**
     * Format the price cents to a correctly formatted decimal as a float.
     *
     * @param int|null $priceCents
     * @return float
     */
    /**
     * A scalar meta value as WordPress stores it and reads it back. WC_Meta_Data compares strictly, so a
     * float or bool that equals the stored string would otherwise count as a change and force a save.
     */
    private static function metaValue($value)
    {
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        return $value;
    }

    private static function formatPrice(?int $priceCents): float
    {
        if (empty($priceCents)) {
            return 0;
        }

        if ($priceCents <= 0) {
            return \floatval(0);
        }

        return \floatval($priceCents / 100);
    }

}
