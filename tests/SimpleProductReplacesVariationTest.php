<?php

use Mockery as M;
use PHPUnit\Framework\TestCase;

/**
 * After variants are switched off in StoreLinkr, a product comes back as a simple product while WooCommerce
 * still has it as a variation of a trashed variable product. That variation must not be reused.
 */
class SimpleProductReplacesVariationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';
        $GLOBALS['deletedPostIds'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['deletedPostIds']);
        M::close();
        parent::tearDown();
    }

    public function testTheVariationIsRemovedAndASimpleProductIsReturned(): void
    {
        $service = M::mock('StoreLinkrWooCommerceService[logWarning]');
        $service->shouldReceive('logWarning')
            ->once()
            ->with('Variation 7253 removed, the product is created again as a simple product.');

        $result = $this->replace($service, new WC_Product_Variation(7253));

        $this->assertInstanceOf(WC_Product_Simple::class, $result);
        $this->assertSame([7253], $GLOBALS['deletedPostIds']);
    }

    public function testAnUnsavedVariationIsNotDeleted(): void
    {
        $service = M::mock('StoreLinkrWooCommerceService[logWarning]');
        $service->shouldNotReceive('logWarning');

        $result = $this->replace($service, new WC_Product_Variation(0));

        $this->assertInstanceOf(WC_Product_Simple::class, $result);
        $this->assertSame([], $GLOBALS['deletedPostIds']);
    }

    private function replace(StoreLinkrWooCommerceService $service, WC_Product_Variation $variation): WC_Product
    {
        $method = new ReflectionMethod(StoreLinkrWooCommerceService::class, 'replaceVariationBySimpleProduct');

        return $method->invoke($service, $variation);
    }
}
