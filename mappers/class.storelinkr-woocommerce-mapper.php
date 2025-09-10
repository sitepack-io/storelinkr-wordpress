<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

class StoreLinkrWooCommerceMapper
{

    public static function convertRequestToProduct(
        WC_Product|WC_Product_Variable|WC_Product_Variation $product,
        array $data
    ): WC_Product|WC_Product_Variable|WC_Product_Variation {
        $updateStockInfo = !(isset($data['updateStock'])) || (bool)$data['updateStock'];
        $updatePriceInfo = !(isset($data['updatePrice'])) || (bool)$data['updatePrice'];

        if (!empty($data['sku'])) {
            $product->set_sku($data['sku']);
        }

        if (!empty($data['ean'])) {
            $product->set_global_unique_id($data['ean']);
        }

        $product->set_name((isset($data['name'])) ? $data['name'] : null);

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

        if (!empty($data['shortDescription'])) {
            $product->set_short_description($data['shortDescription']);
        }

        if (!empty($data['longDescription'])) {
            $product->set_description($data['longDescription']);
        }

        if ($updateStockInfo === true) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity(0);
            $product->set_stock_status('outofstock');

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

        if (!empty($data['advisedPrice'])) {
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

        if ($product->get_date_created() === null) {
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

        $product->update_meta_data('_product_attachments', json_encode($attachments));

        return $product;
    }


    /**
     * Format the price cents to a correctly formatted decimal as a float.
     *
     * @param int $priceCents
     * @return float
     */
    private static function formatPrice(int $priceCents): float
    {
        if ($priceCents <= 0) {
            return \floatval(0);
        }

        return \floatval($priceCents / 100);
    }

}
