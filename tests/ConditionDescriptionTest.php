<?php

use PHPUnit\Framework\TestCase;

/**
 * The condition description of a second-hand product is stored as the plain meta key
 * condition_description, like used and advised_price, not as a prefixed StoreLinkr metafield.
 */
class ConditionDescriptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once STORELINKR_PLUGIN_DIR . 'helpers/class.storelinkr-ean-helper.php';
        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';
    }

    public function testTheDescriptionIsStoredWithoutPrefix(): void
    {
        $product = new ConditionDescriptionTestProduct();

        StoreLinkrWooCommerceMapper::convertRequestToProduct(
            $product,
            ['conditionDescription' => " Rug licht beschadigd\nVerder netjes "]
        );

        $this->assertSame("Rug licht beschadigd\nVerder netjes", $product->meta['condition_description']);
        $this->assertArrayNotHasKey('storelinkr_condition_description', $product->meta);
    }

    public function testAnEmptyDescriptionRemovesTheMeta(): void
    {
        $product = new ConditionDescriptionTestProduct();
        $product->meta['condition_description'] = 'Oude omschrijving';

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['conditionDescription' => '']);

        $this->assertArrayNotHasKey('condition_description', $product->meta);
    }

    public function testAnOlderStoreLinkrWithoutTheFieldKeepsTheStoredDescription(): void
    {
        $product = new ConditionDescriptionTestProduct();
        $product->meta['condition_description'] = 'Bestaande omschrijving';

        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, ['conditionDescription' => null]);
        StoreLinkrWooCommerceMapper::convertRequestToProduct($product, []);

        $this->assertSame('Bestaande omschrijving', $product->meta['condition_description']);
    }
}

class ConditionDescriptionTestProduct extends WC_Product_Variation
{
    public array $meta = [];

    public function __construct()
    {
        parent::__construct(0);
    }

    public function update_meta_data($key, $value, $meta_id = 0) { $this->meta[$key] = $value; }
    public function delete_meta_data($key) { unset($this->meta[$key]); }
}
