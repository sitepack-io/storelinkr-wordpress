<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

/**
 * The stock status StoreLinkr sends per stock location and for the whole product: own stock, stock at a
 * supplier or dropshipping. A shop can read the product status from the storelinkr_stock_status meta.
 */
class StoreLinkrStockStatusHelper
{

    public const META_KEY = 'storelinkr_stock_status';

    public const IN_STOCK = 'in_stock';
    public const IN_STOCK_SUPPLIER = 'in_stock_supplier';
    public const DROPSHIPPING = 'dropshipping';
    public const OUT_OF_STOCK = 'out_of_stock';

    public const LOCATION_STATUSES = [
        self::IN_STOCK,
        self::IN_STOCK_SUPPLIER,
        self::DROPSHIPPING,
    ];

    public const PRODUCT_STATUSES = [
        self::IN_STOCK,
        self::IN_STOCK_SUPPLIER,
        self::DROPSHIPPING,
        self::OUT_OF_STOCK,
    ];

    /**
     * Older StoreLinkr versions do not send a product status, their products keep what is stored.
     *
     * @param WC_Product|WC_Product_Variable|WC_Product_Variation $product
     */
    public static function applyToProduct($product, array $data): void
    {
        if (!isset($data['stockStatus'])) {
            return;
        }

        $status = trim((string)$data['stockStatus']);
        if (!in_array($status, self::PRODUCT_STATUSES, true)) {
            $product->delete_meta_data(self::META_KEY);

            return;
        }

        $product->update_meta_data(self::META_KEY, $status);
    }

    /**
     * The status of a stock location, null for locations sent by a StoreLinkr version without statuses.
     */
    public static function locationStatus(array $location): ?string
    {
        $status = isset($location['stock_status']) ? (string)$location['stock_status'] : '';

        return in_array($status, self::LOCATION_STATUSES, true) ? $status : null;
    }

    public static function label(string $status): string
    {
        switch ($status) {
            case self::IN_STOCK:
                return __('In stock', 'storelinkr');
            case self::IN_STOCK_SUPPLIER:
                return __('In stock at supplier', 'storelinkr');
            case self::DROPSHIPPING:
                return __('Dropshipping', 'storelinkr');
            default:
                return __('Out of stock', 'storelinkr');
        }
    }

    /**
     * The availability of one stock location on the product page. Without a status the quantity decides,
     * like before StoreLinkr sent statuses. The piece count is only shown for own stock.
     */
    public static function renderLocationAvailability(array $location): string
    {
        $quantity = (int)($location['quantity'] ?? 0);
        if ($quantity <= 0) {
            return '<span class="sl-sold-out">' . esc_html(self::label(self::OUT_OF_STOCK)) . '</span>';
        }

        $status = self::locationStatus($location) ?? self::IN_STOCK;

        if ($status === self::IN_STOCK_SUPPLIER) {
            return '<span class="sl-in-stock-supplier">' . esc_html(self::label($status)) . '</span>';
        }

        if ($status === self::DROPSHIPPING) {
            return '<span class="sl-dropshipping">' . esc_html(self::label($status)) . '</span>';
        }

        return '<span class="sl-in-stock">' . esc_html(self::label(self::IN_STOCK)) . '</span>'
            . ' <span class="sl-muted">' . esc_html((string)$quantity) . ' ' . esc_html(__('piece(s)', 'storelinkr'))
            . '</span>';
    }

}
