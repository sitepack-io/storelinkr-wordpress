<?php

use Mockery as M;
use PHPUnit\Framework\TestCase;

/**
 * StoreLinkr keeps sending the WooCommerce ids it knows. When such a product was removed in
 * WooCommerce, the sync must recreate it instead of failing on every run (SPD-2139).
 */
class StaleProductIdTest extends TestCase
{
    private const PARENT_ID = 12546;

    protected function setUp(): void
    {
        parent::setUp();
        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';

        $parent = M::mock('WC_Product_Variable');
        $parent->shouldReceive('get_attributes')->andReturn([]);
        $parent->shouldReceive('set_attributes')->andReturnSelf();
        $parent->shouldReceive('set_manage_stock')->andReturnSelf();
        $parent->shouldReceive('set_stock_quantity')->andReturnSelf();
        $parent->shouldReceive('set_stock_status')->andReturnSelf();
        $parent->shouldReceive('save')->andReturn(self::PARENT_ID);

        $GLOBALS['mockVariableProduct'] = $parent;
        $GLOBALS['mockProductsById'] = [self::PARENT_ID => $parent];
        $GLOBALS['deletedPostIds'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mockProductsById'], $GLOBALS['deletedPostIds']);
        M::close();
        parent::tearDown();
    }

    public function testARemovedProductIsNotFoundInsteadOfThrowing(): void
    {
        $GLOBALS['mockProductsById'][99999] = false;

        $service = M::mock('StoreLinkrWooCommerceService[logWarning]');
        $service->shouldReceive('logWarning')
            ->once()
            ->with('Product not found with id 99999, creating it again.');

        $this->assertNull($service->findExistingProductById(99999, 'variant'));
    }

    public function testAnExistingVariableProductIsReturned(): void
    {
        $service = new StoreLinkrWooCommerceService();

        $this->assertSame(
            $GLOBALS['mockProductsById'][self::PARENT_ID],
            $service->findExistingProductById(self::PARENT_ID, 'variant')
        );
    }

    public function testASimpleProductIsRefusedAsVariableProduct(): void
    {
        $GLOBALS['mockProductsById'][4711] = new WC_Product_Simple();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Product is not an instance of Variable product!');

        (new StoreLinkrWooCommerceService())->findExistingProductById(4711, 'variant');
    }

    public function testAnOptionIdOfTheParentItselfNeverDeletesTheParent(): void
    {
        $result = $this->buildOptions([
            $this->option(ean: '8719615083088', id: self::PARENT_ID),
        ]);

        $this->assertNotContains(self::PARENT_ID, $GLOBALS['deletedPostIds']);
        $this->assertArrayHasKey('8719615083088', $result['ean'], 'The variation is created instead');
    }

    public function testAnOptionIdOfAnotherVariableProductNeverDeletesThatProduct(): void
    {
        $GLOBALS['mockProductsById'][3650] = new WC_Product_Variable();

        $result = $this->buildOptions([
            $this->option(ean: '8719615083095', id: 3650),
        ]);

        $this->assertNotContains(3650, $GLOBALS['deletedPostIds']);
        $this->assertArrayHasKey('8719615083095', $result['ean']);
    }

    public function testAnOptionIdOfASimpleProductIsStillReplacedByAVariation(): void
    {
        $GLOBALS['mockProductsById'][2574] = new WC_Product_Simple();

        $result = $this->buildOptions([
            $this->option(ean: '8719615081091', id: 2574),
        ]);

        $this->assertSame([2574], $GLOBALS['deletedPostIds']);
        $this->assertArrayHasKey('8719615081091', $result['ean']);
    }

    public function testAnOptionIdThatWasRemovedCreatesANewVariation(): void
    {
        $GLOBALS['mockProductsById'][2575] = false;

        $result = $this->buildOptions([
            $this->option(ean: '8719615081107', id: 2575),
        ]);

        $this->assertSame([], $GLOBALS['deletedPostIds']);
        $this->assertArrayHasKey('8719615081107', $result['ean']);
    }

    private function buildOptions(array $products): array
    {
        $service = M::mock('StoreLinkrWooCommerceService[logWarning]');
        $service->shouldReceive('logWarning')->andReturn(null);

        return $service->buildProductVariantOptions(self::PARENT_ID, ['Kleur'], $products, []);
    }

    private function option(string $ean, int $id): array
    {
        return [
            'uuid' => 'uuid-' . $ean,
            'ean' => $ean,
            'id' => $id,
            'name' => 'NOA Pendant',
            'inStock' => 1,
            'stockSupplier' => 0,
            'options' => ['Kleur' => 'Lichtbrons'],
        ];
    }
}
