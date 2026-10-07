<?php

use PHPUnit\Framework\TestCase;

/**
 * The condition and condition description of a second-hand product are stored as the plain meta keys
 * condition and condition_description, like used and advised_price, not as prefixed metafields.
 */
class ConditionMetaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once STORELINKR_PLUGIN_DIR . 'helpers/class.storelinkr-ean-helper.php';
        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';
    }

    public function testTheDescriptionIsStoredWithoutPrefix(): void
    {
        $product = new ConditionMetaTestProduct();

        StoreLinkrWooCommerceMapper::convertRequestToProduct(
            $product,
            ['conditionDescription' => " Rug licht beschadigd\nVerder netjes "]
        );

        $this->assertSame("Rug licht beschadigd\nVerder netjes", $product->meta['condition_description']);
        $this->assertArrayNotHasKey('storelinkr_condition_description', $product->meta);
    }

    public function testAnEmptyDescriptionRemovesTheMeta(): void
    {
        $product = new ConditionMetaTestProduct();
        $product->meta['condition_description'] = 'Oude omschrijving';

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['conditionDescription' => '']);

        $this->assertArrayNotHasKey('condition_description', $product->meta);
    }

    public function testAnOlderStoreLinkrWithoutTheFieldKeepsTheStoredDescription(): void
    {
        $product = new ConditionMetaTestProduct();
        $product->meta['condition_description'] = 'Bestaande omschrijving';

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['conditionDescription' => null]);
        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, []);

        $this->assertSame('Bestaande omschrijving', $product->meta['condition_description']);
    }

    public function testTheConditionIsStoredWithoutPrefix(): void
    {
        $product = new ConditionMetaTestProduct();

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['condition' => 'as new']);

        $this->assertSame('as new', $product->meta['condition']);
        $this->assertArrayNotHasKey('storelinkr_condition', $product->meta);
    }

    public function testAnOlderStoreLinkrWithoutTheConditionKeepsTheStoredOne(): void
    {
        $product = new ConditionMetaTestProduct();
        $product->meta['condition'] = 'good';

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['condition' => null]);
        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, []);

        $this->assertSame('good', $product->meta['condition']);
    }
}

class ConditionMetaTestProduct extends WC_Product_Variation
{
    public array $meta = [];

    public function __construct()
    {
        parent::__construct(0);
    }

    public function update_meta_data($key, $value, $meta_id = 0) { $this->meta[$key] = $value; }
    public function delete_meta_data($key) { unset($this->meta[$key]); }
}
