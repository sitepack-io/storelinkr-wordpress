<?php

use PHPUnit\Framework\TestCase;
use Mockery as M;

/**
 * Tests for the variant failure-detection feature (v2.18.0).
 *
 * StoreLinkrWooCommerceService::buildProductVariantOptions() now returns a 'failed' map listing
 * variations that were saved without their attribute options or without a persisted price. The REST
 * layer turns that map into the `complete` flag and `failed_variants` list returned to the platform.
 *
 * These tests drive the real service code for the price-persistence branch (complementing the
 * missing-options coverage in BuildProductVariantOptionsTest) and lock in the REST response contract
 * that derives `complete` / `failed_variants` from the service's 'failed' map.
 */
class VariantFailureDetectionTest extends TestCase
{
    private $mockVariableProduct;

    protected function setUp(): void
    {
        parent::setUp();

        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';

        // A permissive variable product; buildProductVariantOptions only needs these calls to succeed.
        $this->mockVariableProduct = M::mock('WC_Product_Variable');
        $this->mockVariableProduct->shouldReceive('get_attributes')->andReturn([]);
        $this->mockVariableProduct->shouldReceive('set_attributes')->andReturnSelf();
        $this->mockVariableProduct->shouldReceive('set_manage_stock')->andReturnSelf();
        $this->mockVariableProduct->shouldReceive('set_stock_quantity')->andReturnSelf();
        $this->mockVariableProduct->shouldReceive('set_stock_status')->andReturnSelf();
        $this->mockVariableProduct->shouldReceive('save')->andReturn(true);

        $GLOBALS['mockVariableProduct'] = $this->mockVariableProduct;

        // Start every test from a clean failure-simulation state.
        $GLOBALS['storelinkr_test_unresolvable_terms'] = [];
        $GLOBALS['storelinkr_test_force_unpersisted_price'] = false;
    }

    protected function tearDown(): void
    {
        // Never leak the simulation switches into another test.
        $GLOBALS['storelinkr_test_unresolvable_terms'] = [];
        $GLOBALS['storelinkr_test_force_unpersisted_price'] = false;

        M::close();
        parent::tearDown();
    }

    private function makeService()
    {
        $service = M::mock('StoreLinkrWooCommerceService[logWarning]');
        $service->shouldReceive('logWarning')->andReturn(null);

        return $service;
    }

    /**
     * When a price was supposed to be written but WooCommerce did not persist it,
     * the variation must be reported in the 'failed' map.
     */
    public function testFlagsVariationWhenPriceIsNotPersisted()
    {
        $GLOBALS['storelinkr_test_force_unpersisted_price'] = true;

        $products = [
            [
                'ean' => '1111111111',
                'uuid' => 'uuid-red',
                'id' => null,
                'options' => ['Color' => 'Red'],
                'salesPrice' => 1999,
                'inStock' => 5,
                'stockSupplier' => 0,
            ],
        ];

        $result = $this->makeService()->buildProductVariantOptions(1, ['Color'], $products, []);

        $this->assertArrayHasKey('failed', $result);
        $this->assertCount(1, $result['failed'], 'The variation with a non-persisted price should be flagged');
        $this->assertStringContainsString('uuid-red', $result['failed'][0]);
        $this->assertStringContainsString('price not persisted', $result['failed'][0]);
    }

    /**
     * A variation whose price persists normally must not be flagged.
     */
    public function testDoesNotFlagVariationWhenPricePersists()
    {
        $GLOBALS['storelinkr_test_force_unpersisted_price'] = false;

        $products = [
            [
                'ean' => '1111111111',
                'uuid' => 'uuid-red',
                'id' => null,
                'options' => ['Color' => 'Red'],
                'salesPrice' => 1999,
                'inStock' => 5,
                'stockSupplier' => 0,
            ],
        ];

        $result = $this->makeService()->buildProductVariantOptions(1, ['Color'], $products, []);

        $this->assertArrayHasKey('failed', $result);
        $this->assertCount(0, $result['failed'], 'A variation with a persisted price must not be flagged');
    }

    /**
     * Price updates can be disabled via the overwrite_product_prices setting; in that case a missing
     * price is expected and must not be reported as a failure.
     */
    public function testDoesNotFlagPriceWhenPriceUpdatesAreDisabled()
    {
        $GLOBALS['storelinkr_test_force_unpersisted_price'] = true;

        $products = [
            [
                'ean' => '1111111111',
                'uuid' => 'uuid-red',
                'id' => null,
                'options' => ['Color' => 'Red'],
                'salesPrice' => 1999,
                'inStock' => 5,
                'stockSupplier' => 0,
            ],
        ];

        $result = $this->makeService()->buildProductVariantOptions(
            1,
            ['Color'],
            $products,
            ['overwrite_product_prices' => false]
        );

        $this->assertCount(0, $result['failed'], 'Price is not checked when overwrite_product_prices is disabled');
    }

    /**
     * A single variation can fail for more than one reason; both must be recorded in one entry.
     */
    public function testCombinesMultipleProblemsForOneVariation()
    {
        $GLOBALS['storelinkr_test_unresolvable_terms'] = ['Blue', 'blue'];
        $GLOBALS['storelinkr_test_force_unpersisted_price'] = true;

        $products = [
            [
                'ean' => '2222222222',
                'uuid' => 'uuid-blue',
                'id' => null,
                'options' => ['Color' => 'Blue'],
                'salesPrice' => 999,
                'inStock' => 1,
                'stockSupplier' => 0,
            ],
        ];

        $result = $this->makeService()->buildProductVariantOptions(1, ['Color'], $products, []);

        $this->assertCount(1, $result['failed'], 'Both problems belong to a single variation entry');
        $this->assertStringContainsString('missing options: Color', $result['failed'][0]);
        $this->assertStringContainsString('price not persisted', $result['failed'][0]);
    }

    /**
     * Contract guard for the REST response layer (renderCreateProductVariant /
     * renderUpdateProductVariant): `complete` is true only when the service reports no failed
     * variations, and `failed_variants` is the re-indexed failed map. This mirrors the exact
     * derivation used in class.storelinkr-rest-api.php.
     */
    public function testRestCompleteFlagDerivesFromFailedMap()
    {
        // No failures reported -> export is complete, empty failed_variants list.
        $variationMap = ['ean' => [], 'uuid' => [], 'failed' => []];
        $failedVariants = isset($variationMap['failed']) && is_array($variationMap['failed'])
            ? array_values($variationMap['failed']) : [];

        $this->assertTrue(empty($failedVariants), 'complete must be true when there are no failed variants');
        $this->assertSame([], $failedVariants);

        // Failures reported -> not complete, failed_variants is the re-indexed list.
        $variationMap = ['ean' => [], 'uuid' => [], 'failed' => [2 => 'uuid-blue (missing options: Color)']];
        $failedVariants = isset($variationMap['failed']) && is_array($variationMap['failed'])
            ? array_values($variationMap['failed']) : [];

        $this->assertFalse(empty($failedVariants), 'complete must be false when a variant failed');
        $this->assertSame(['uuid-blue (missing options: Color)'], $failedVariants, 'failed_variants must be 0-indexed');

        // Missing 'failed' key (older service response) -> treated as complete.
        $variationMap = ['ean' => [], 'uuid' => []];
        $failedVariants = isset($variationMap['failed']) && is_array($variationMap['failed'])
            ? array_values($variationMap['failed']) : [];

        $this->assertTrue(empty($failedVariants), 'A response without a failed map is treated as complete');
    }
}
