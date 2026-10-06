<?php

use Mockery as M;
use PHPUnit\Framework\TestCase;

/**
 * A sync of an unchanged variant must not write anything per variation: berla.nl timed out after
 * 120 seconds on update-variant for a large variant (SPD-2139).
 */
class UnchangedVariantSyncTest extends TestCase
{
    private const PARENT_ID = 9298;

    protected function setUp(): void
    {
        parent::setUp();
        require_once STORELINKR_PLUGIN_DIR . 'helpers/class.storelinkr-ean-helper.php';
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
        $GLOBALS['postMeta'] = [];
        $GLOBALS['gtinIndex'] = [];
        $GLOBALS['gtinLookups'] = 0;
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['mockProductsById'],
            $GLOBALS['deletedPostIds'],
            $GLOBALS['postMeta'],
            $GLOBALS['gtinIndex'],
            $GLOBALS['gtinLookups']
        );
        M::close();
        parent::tearDown();
    }

    public function testAnUnchangedVariationIsNotSavedAgain(): void
    {
        $variation = new WC_Product_Variation(9299);
        $variation->set_attributes(['pa_kleur' => 'lichtbrons']);
        $variation->save();
        $GLOBALS['mockProductsById'][9299] = $variation;

        $result = $this->buildOptions([$this->option(id: 9299, colour: 'Lichtbrons')]);

        $this->assertSame(1, $variation->saveCount, 'Only the save of the test setup');
        $this->assertSame(9299, $result['uuid']['uuid-9299']);
    }

    public function testAVariationWithChangedAttributesIsSaved(): void
    {
        $variation = new WC_Product_Variation(9300);
        $variation->set_attributes(['pa_kleur' => 'wit']);
        $variation->save();
        $GLOBALS['mockProductsById'][9300] = $variation;

        $this->buildOptions([$this->option(id: 9300, colour: 'Donkerbrons')]);

        $this->assertSame(2, $variation->saveCount);
        $this->assertSame(['pa_kleur' => 'donkerbrons'], $variation->get_attributes());
    }

    public function testANewVariationIsSaved(): void
    {
        $result = $this->buildOptions([$this->option(id: null, colour: 'Zwart')]);

        $this->assertNotEmpty($result['uuid']['uuid-new'], 'A new variation gets an id from its first save');
    }

    public function testPendingMetaChangesNeedASave(): void
    {
        $service = new StoreLinkrWooCommerceService();

        $this->assertFalse($service->hasPendingChanges(new UnchangedVariantSyncTestProduct([])));
        $this->assertTrue($service->hasPendingChanges(new UnchangedVariantSyncTestProduct([], ['name' => 'X'])));
        $this->assertTrue($service->hasPendingChanges(new UnchangedVariantSyncTestProduct([
            new UnchangedVariantSyncTestMeta(id: 0, changes: []),
        ])), 'A meta row that was never stored');
        $this->assertTrue($service->hasPendingChanges(new UnchangedVariantSyncTestProduct([
            new UnchangedVariantSyncTestMeta(id: 12, changes: ['value' => '1']),
        ])), 'A changed meta row');
        $this->assertFalse($service->hasPendingChanges(new UnchangedVariantSyncTestProduct([
            new UnchangedVariantSyncTestMeta(id: 12, changes: []),
        ])));
    }

    public function testVariationFacetsKeepOneRowAndDropTheDuplicates(): void
    {
        $facets = [['name' => 'IP', 'value' => 'IP54']];
        $GLOBALS['postMeta'][9301]['_product_attributes'] = [
            [['name' => 'IP', 'value' => 'IP20']],
            [['name' => 'IP', 'value' => 'IP44']],
            [['name' => 'IP', 'value' => 'IP54']],
        ];

        (new StoreLinkrWooCommerceService())->storeVariationFacets(9301, $facets);

        $this->assertSame([$facets], $GLOBALS['postMeta'][9301]['_product_attributes']);
    }

    public function testUnchangedVariationFacetsAreNotWrittenAgain(): void
    {
        $facets = [['name' => 'IP', 'value' => 'IP54']];
        $GLOBALS['postMeta'][9302]['_product_attributes'] = [$facets];
        $GLOBALS['postMeta'][9302]['marker'] = ['kept'];

        (new StoreLinkrWooCommerceService())->storeVariationFacets(9302, $facets);
        // A delete would have dropped and re-added the row, the marker shows nothing else was touched
        $this->assertSame([$facets], $GLOBALS['postMeta'][9302]['_product_attributes']);
        $this->assertSame(['kept'], $GLOBALS['postMeta'][9302]['marker']);
    }

    public function testAProductThatAlreadyHoldsTheEanSkipsTheDuplicateLookup(): void
    {
        $GLOBALS['mockProductsById'][9303] = new UnchangedVariantSyncTestProduct([], [], '8719615083088');

        (new StoreLinkrWooCommerceService())->removeDuplicateByEan('8719615083088', 9303);

        $this->assertSame(0, $GLOBALS['gtinLookups']);
        $this->assertSame([], $GLOBALS['deletedPostIds']);
    }

    public function testAnotherProductWithTheSameEanIsStillRemoved(): void
    {
        $GLOBALS['mockProductsById'][9304] = new UnchangedVariantSyncTestProduct([], [], '');
        $GLOBALS['mockProductsById'][4711] = new UnchangedVariantSyncTestProduct([], [], '8719615083095', 4711);
        $GLOBALS['gtinIndex']['8719615083095'] = 4711;

        (new StoreLinkrWooCommerceService())->removeDuplicateByEan('8719615083095', 9304);

        $this->assertSame([4711], $GLOBALS['deletedPostIds']);
    }

    public function testTheMapperLeavesTheVariationTitleToWooCommerce(): void
    {
        $variation = new UnchangedVariantSyncTestMappedVariation();
        StoreLinkrWooCommerceMapper::convertRequestToProduct($variation, ['name' => 'BR6852LB-Y27']);

        $this->assertArrayNotHasKey('set_name', $variation->calls);
    }

    public function testTheMapperSetsEachStockPropOnce(): void
    {
        $variation = new UnchangedVariantSyncTestMappedVariation();
        StoreLinkrWooCommerceMapper::convertRequestToProduct($variation, ['inStock' => 2, 'stockSupplier' => 1]);

        $this->assertSame([[3]], $variation->calls['set_stock_quantity']);
        $this->assertSame([['instock']], $variation->calls['set_stock_status']);

        $outOfStock = new UnchangedVariantSyncTestMappedVariation();
        StoreLinkrWooCommerceMapper::convertRequestToProduct($outOfStock, ['inStock' => 0, 'stockSupplier' => 0]);

        $this->assertSame([[0]], $outOfStock->calls['set_stock_quantity']);
        $this->assertSame([['outofstock']], $outOfStock->calls['set_stock_status']);
    }

    public function testTheMapperOnlySetsAChangedSkuOrEan(): void
    {
        $unchanged = new UnchangedVariantSyncTestMappedVariation('BR6852LB-Y27', '8719615083088');
        StoreLinkrWooCommerceMapper::convertRequestToProduct($unchanged, [
            'sku' => 'BR6852LB-Y27',
            'ean' => '8719615083088',
        ]);

        $this->assertArrayNotHasKey('set_sku', $unchanged->calls);
        $this->assertArrayNotHasKey('set_global_unique_id', $unchanged->calls);

        $changed = new UnchangedVariantSyncTestMappedVariation('BR6852LB-Y27', '8719615083088');
        StoreLinkrWooCommerceMapper::convertRequestToProduct($changed, [
            'sku' => 'BR6852DB-Y27',
            'ean' => '8719615083095',
        ]);

        $this->assertSame([['BR6852DB-Y27']], $changed->calls['set_sku']);
        $this->assertSame([['8719615083095']], $changed->calls['set_global_unique_id']);
    }

    public function testTheMapperStoresTheUsedFlagAsItIsReadBack(): void
    {
        $variation = new UnchangedVariantSyncTestMappedVariation();
        StoreLinkrWooCommerceMapper::convertRequestToProduct($variation, ['isUsed' => false]);

        $this->assertSame('0', $variation->meta['used']);
    }

    private function buildOptions(array $products): array
    {
        $service = M::mock('StoreLinkrWooCommerceService[logWarning]');
        $service->shouldReceive('logWarning')->andReturn(null);

        return $service->buildProductVariantOptions(self::PARENT_ID, ['Kleur'], $products, []);
    }

    private function option(?int $id, string $colour): array
    {
        return [
            'uuid' => 'uuid-' . ($id ?? 'new'),
            'id' => $id,
            'name' => 'HOLLY ' . $colour,
            'inStock' => 1,
            'stockSupplier' => 0,
            'options' => ['Kleur' => $colour],
        ];
    }
}

class UnchangedVariantSyncTestMeta
{
    public function __construct(public int $id, private array $changes)
    {
    }

    public function get_changes(): array
    {
        return $this->changes;
    }
}

class UnchangedVariantSyncTestProduct extends WC_Product
{
    public function __construct(array $meta, array $changes = [], private string $gtin = '', int $id = 0)
    {
        $this->meta_data = $meta;
        $this->changes = $changes;
        $this->id = $id;
    }

    public function get_global_unique_id($context = 'view')
    {
        return $this->gtin;
    }
}

/**
 * Records the setters the mapper calls; method_exists() must see them, so they are real methods.
 */
class UnchangedVariantSyncTestMappedVariation extends WC_Product_Variation
{
    public array $calls = [];
    public array $meta = [];

    public function __construct(private string $sku = '', private string $gtin = '')
    {
        parent::__construct(0);
    }

    public function set_name($name) { $this->calls['set_name'][] = [$name]; }
    public function get_sku($context = 'view') { return $this->sku; }
    public function set_sku($sku) { $this->calls['set_sku'][] = [$sku]; }
    public function get_global_unique_id($context = 'view') { return $this->gtin; }
    public function set_global_unique_id($gtin) { $this->calls['set_global_unique_id'][] = [$gtin]; }
    public function set_manage_stock($manage) { $this->calls['set_manage_stock'][] = [$manage]; }
    public function set_backorders($backorders) { $this->calls['set_backorders'][] = [$backorders]; }
    public function set_stock_quantity($quantity) { $this->calls['set_stock_quantity'][] = [$quantity]; }
    public function set_stock_status($status) { $this->calls['set_stock_status'][] = [$status]; }
    public function update_meta_data($key, $value, $meta_id = 0) { $this->meta[$key] = $value; }
}
