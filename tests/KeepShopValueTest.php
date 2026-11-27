<?php

use Mockery as M;
use PHPUnit\Framework\TestCase;

/**
 * A shop owner who keeps categories or attributes in WooCommerce gets them only on new products.
 */
class KeepShopValueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once STORELINKR_PLUGIN_DIR . 'helpers/class.storelinkr-ean-helper.php';
        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';
    }

    protected function tearDown(): void
    {
        M::close();
        parent::tearDown();
    }

    private function product(int $id): WC_Product
    {
        $product = M::mock('WC_Product');
        $product->shouldReceive('get_id')->andReturn($id);

        return $product;
    }

    public function testExistingProductKeepsValueWhenSettingIsOff(): void
    {
        $service = new StoreLinkrWooCommerceService();

        $this->assertTrue(
            $service->keepsShopValue($this->product(39417), ['overwrite_categories' => false], 'overwrite_categories')
        );
    }

    public function testNewProductAlwaysGetsTheValue(): void
    {
        $service = new StoreLinkrWooCommerceService();

        $this->assertFalse(
            $service->keepsShopValue($this->product(0), ['overwrite_facets' => false], 'overwrite_facets')
        );
    }

    public function testOverwritesWhenSettingIsOnOrNotSent(): void
    {
        $service = new StoreLinkrWooCommerceService();

        $this->assertFalse(
            $service->keepsShopValue($this->product(39417), ['overwrite_facets' => true], 'overwrite_facets')
        );
        $this->assertFalse($service->keepsShopValue($this->product(39417), [], 'overwrite_facets'));
    }
}
