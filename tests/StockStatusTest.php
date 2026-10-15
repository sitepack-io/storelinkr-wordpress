<?php

use PHPUnit\Framework\TestCase;

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * StoreLinkr sends a stock status per stock location (in stock, in stock at supplier, dropshipping) and
 * one for the whole product, stored as the storelinkr_stock_status meta.
 */
class StockStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once STORELINKR_PLUGIN_DIR . 'helpers/class.storelinkr-ean-helper.php';
        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';
    }

    public function testTheProductStatusIsStored(): void
    {
        $product = new StockStatusTestProduct();

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['stockStatus' => 'in_stock_supplier']);

        $this->assertSame('in_stock_supplier', $product->meta['storelinkr_stock_status']);
    }

    public function testOutOfStockIsStored(): void
    {
        $product = new StockStatusTestProduct();

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['stockStatus' => 'out_of_stock']);

        $this->assertSame('out_of_stock', $product->meta['storelinkr_stock_status']);
    }

    public function testAnUnknownStatusRemovesTheMeta(): void
    {
        $product = new StockStatusTestProduct();
        $product->meta['storelinkr_stock_status'] = 'in_stock';

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['stockStatus' => 'backorder']);

        $this->assertArrayNotHasKey('storelinkr_stock_status', $product->meta);
    }

    public function testAnOlderStoreLinkrWithoutTheStatusKeepsTheStoredOne(): void
    {
        $product = new StockStatusTestProduct();
        $product->meta['storelinkr_stock_status'] = 'dropshipping';

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, []);
        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['stockStatus' => null]);

        $this->assertSame('dropshipping', $product->meta['storelinkr_stock_status']);
    }

    public function testTheLocationStatusIsKeptInTheStockLocationsMeta(): void
    {
        $product = new StockStatusTestProduct();
        $locations = [
            ['id' => '101', 'name' => 'Main warehouse', 'quantity' => 2, 'stock_status' => 'in_stock'],
            ['id' => '102', 'name' => 'Supplier', 'quantity' => 7, 'stock_status' => 'in_stock_supplier'],
        ];

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['stockLocations' => ['locations' => $locations]]);

        $this->assertSame($locations, $product->meta['stock_locations']);
    }

    public function testAMetafieldCannotOverwriteTheProductStatus(): void
    {
        $this->assertNull(StoreLinkrMetafieldHelper::buildMetaKey('stock_status'));
    }

    /**
     * @dataProvider provideLocations
     */
    public function testTheAvailabilityOfALocation(array $location, string $expected): void
    {
        $this->assertSame($expected, StoreLinkrStockStatusHelper::renderLocationAvailability($location));
    }

    public static function provideLocations(): array
    {
        return [
            'own stock' => [
                ['quantity' => 3, 'stock_status' => 'in_stock'],
                '<span class="sl-in-stock">In stock</span> <span class="sl-muted">3 piece(s)</span>',
            ],
            'stock at the supplier' => [
                ['quantity' => 3, 'stock_status' => 'in_stock_supplier'],
                '<span class="sl-in-stock-supplier">In stock at supplier</span>',
            ],
            'dropshipping' => [
                ['quantity' => 3, 'stock_status' => 'dropshipping'],
                '<span class="sl-dropshipping">Dropshipping</span>',
            ],
            'a supplier location without stock' => [
                ['quantity' => 0, 'stock_status' => 'in_stock_supplier'],
                '<span class="sl-sold-out">Out of stock</span>',
            ],
            'an older StoreLinkr without status' => [
                ['quantity' => 2],
                '<span class="sl-in-stock">In stock</span> <span class="sl-muted">2 piece(s)</span>',
            ],
            'an older StoreLinkr without stock' => [
                ['quantity' => 0],
                '<span class="sl-sold-out">Out of stock</span>',
            ],
            'an unknown status' => [
                ['quantity' => 1, 'stock_status' => 'backorder'],
                '<span class="sl-in-stock">In stock</span> <span class="sl-muted">1 piece(s)</span>',
            ],
        ];
    }
}

class StockStatusTestProduct extends WC_Product_Variation
{
    public array $meta = [];

    public function __construct()
    {
        parent::__construct(0);
    }

    public function update_meta_data($key, $value, $meta_id = 0) { $this->meta[$key] = $value; }
    public function delete_meta_data($key) { unset($this->meta[$key]); }
}
