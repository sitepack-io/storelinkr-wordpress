<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

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

        if (!empty($data['sku']) && method_exists($product, 'set_sku')) {
            $product->set_sku($data['sku']);
        }

        if (!empty($data['ean']) && method_exists($product, 'set_global_unique_id')) {
            $product->set_global_unique_id($data['ean']);
        }

        if(method_exists($product, 'set_name')) {
            $product->set_name((isset($data['name'])) ? $data['name'] : null);
        }

        if ($updatePriceInfo === true && isset($data['salesPrice'])) {
            $product->set_regular_price(self::formatPrice((int)$data['salesPrice']));

            if (!empty($data['promoSalesPrice'])) {
                $product->set_sale_price(self::formatPrice((int)$data['promoSalesPrice']));
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
        }

        if ($updateShortDescription === true && isset($data['shortDescription'])) {
            $product->set_short_description($data['shortDescription']);
        }

        if ($updateLongDescription === true && isset($data['longDescription'])) {
            $product->set_description($data['longDescription']);
        }

        if ($updateStockInfo === true && method_exists($product, 'set_manage_stock')) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity(0);
            $product->set_stock_status('outofstock');
            if ($allowBackOrder === true) {
                $product->set_backorders('yes');
            } else {
                $product->set_backorders('no');
            }

            if (
                (isset($data['hasStock']) && (bool)$data['hasStock'] === true) ||
                (isset($data['inStock']) && (int)$data['inStock'] >= 1) ||
                (isset($data['stockSupplier']) && (int)$data['stockSupplier'] >= 1)
            ) {
                $product->set_stock_status('instock');
                $product->set_stock_quantity(
                    (int)$data['inStock'] + (int)$data['stockSupplier']
                );

                if ($product->get_stock_quantity() < 1) {
                    $product->set_stock_quantity(1);
                }
            }
        }

        if (!empty($data['metadata'])) {
            $json = \json_decode($data['metadata'], true);

            if (is_array($json)) {
                foreach ($json as $key => $value) {
                    $product->update_meta_data($key, $value, true);
                }
            }
        }

        $product->update_meta_data('import_provider', 'STORELINKR', true);
        $product->update_meta_data('import_source', (isset($data['importSource'])) ? $data['importSource'] : null,
            true);
        $product->update_meta_data('site', (isset($data['site'])) ? $data['site'] : null, true);
        $product->update_meta_data('ean', (isset($data['ean'])) ? $data['ean'] : null, true);
        $product->update_meta_data('used', (isset($data['isUsed'])) ? (int)$data['isUsed'] : 0, true);

        if ($updatePriceInfo === true && isset($data['advisedPrice'])) {
            $product->update_meta_data('advised_price', self::formatPrice((int)$data['advisedPrice']), true);
        }

        if (!empty($data['stockLocations'])) {
            $stockInfo = $data['stockLocations'];
            $stockMeta = [];

            if (is_array($stockInfo) && isset($stockInfo['locations'])) {
                $stockMeta = $stockInfo['locations'];
            }

            $product->update_meta_data('stock_locations', $stockMeta, true);
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
            $product->update_meta_data('_positive_points', $data['positive_points'], true);
        }
        if (isset($data['negative_points'])) {
            $product->update_meta_data('_negative_points', $data['negative_points'], true);
        }

        $product->update_meta_data('_product_attachments', json_encode($attachments), true);

        if(method_exists($product, 'set_cross_sell_ids')) {
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
